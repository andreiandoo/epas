<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCart;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceTicketType;
use App\Models\MarketplacePromoCode;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Event;
use App\Models\TicketType;
use App\Services\Seating\SeatHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckoutController extends BaseController
{
    public function __construct(
        protected SeatHoldService $seatHoldService
    ) {}
    /**
     * Create order from cart (checkout)
     */
    public function checkout(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'customer' => 'required|array',
            'customer.email' => 'required|email|max:255',
            'customer.first_name' => 'required|string|max:255',
            'customer.last_name' => 'required|string|max:255',
            'customer.phone' => 'nullable|string|max:50',
            'customer.password' => 'nullable|string|min:8', // Optional account creation
            'beneficiaries' => 'nullable|array',
            'beneficiaries.*.name' => 'required_with:beneficiaries|string|max:255',
            'beneficiaries.*.email' => 'nullable|email|max:255',
            'items' => 'nullable|array', // Accept items directly from frontend
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'payment_method' => 'nullable|string|in:card,cash,transfer',
            'accept_terms' => 'required|accepted',
            'ticket_insurance' => 'nullable|boolean',
            'ticket_insurance_amount' => 'nullable|numeric|min:0',
        ]);

        // Get ticket insurance data
        $hasInsurance = $request->boolean('ticket_insurance');
        $insuranceAmount = $hasInsurance ? (float) $request->input('ticket_insurance_amount', 0) : 0;
        $insurancePerTicket = 0; // Will be calculated after we know total ticket count

        // Try to get items from request body first (frontend sends localStorage cart)
        $requestItems = $request->input('items', []);
        $cartItems = [];

        if (!empty($requestItems)) {
            // Convert frontend items format to cart format
            foreach ($requestItems as $item) {
                // Support both new AmbiletCart format and legacy format
                $eventId = $item['event']['id'] ?? $item['event_id'] ?? null;
                $ticketTypeId = $item['ticketType']['id'] ?? $item['ticket_type_id'] ?? null;
                $quantity = $item['quantity'] ?? 1;
                $price = $item['ticketType']['price'] ?? $item['price'] ?? 0;
                $ticketTypeName = $item['ticketType']['name'] ?? $item['ticket_type_name'] ?? 'Bilet';

                if ($eventId && $ticketTypeId) {
                    $cartItem = [
                        'event_id' => $eventId,
                        'ticket_type_id' => $ticketTypeId,
                        'quantity' => $quantity,
                        'price' => $price,
                        'ticket_type_name' => $ticketTypeName,
                    ];

                    // Preserve seat information if present
                    if (!empty($item['seat_uids'])) {
                        $cartItem['seat_uids'] = $item['seat_uids'];
                        $cartItem['event_seating_id'] = $item['event_seating_id'] ?? null;
                        $cartItem['seats'] = $item['seats'] ?? [];
                    }

                    $cartItems[] = $cartItem;
                }
            }
        }

        // Fall back to database cart if no items in request
        if (empty($cartItems)) {
            $sessionId = $this->getSessionId($request);
            $cart = MarketplaceCart::bySession($sessionId, $client->id)->first();

            if (!$cart || $cart->isEmpty()) {
                return $this->error('Cart is empty', 400);
            }

            $cartItems = $cart->items ?? [];
        }

        if (empty($cartItems)) {
            return $this->error('Cart is empty', 400);
        }

        // Validate cart items are still available
        $validationErrors = $this->validateCartItemsForCheckout($cartItems);
        if (!empty($validationErrors)) {
            Log::channel('marketplace')->warning('Checkout validation failed', [
                'client_id' => $client->id,
                'cart_items' => $cartItems,
                'errors' => $validationErrors,
            ]);
            return $this->error('Some items are no longer available', 400, [
                'errors' => $validationErrors,
            ]);
        }

        // Calculate per-ticket insurance amount
        if ($hasInsurance && $insuranceAmount > 0) {
            $totalTicketCount = collect($cartItems)->sum(fn($item) => (int) ($item['quantity'] ?? 1));
            $insurancePerTicket = $totalTicketCount > 0 ? round($insuranceAmount / $totalTicketCount, 2) : 0;
        }

        try {
            DB::beginTransaction();

            // Find or create customer
            $customer = MarketplaceCustomer::where('marketplace_client_id', $client->id)
                ->where('email', $validated['customer']['email'])
                ->first();

            if (!$customer) {
                $customer = MarketplaceCustomer::create([
                    'marketplace_client_id' => $client->id,
                    'email' => $validated['customer']['email'],
                    'first_name' => $validated['customer']['first_name'],
                    'last_name' => $validated['customer']['last_name'],
                    'phone' => $validated['customer']['phone'] ?? null,
                    'password' => isset($validated['customer']['password'])
                        ? Hash::make($validated['customer']['password'])
                        : null,
                    'status' => 'active',
                ]);
            } else {
                // Update customer details
                $customer->update([
                    'first_name' => $validated['customer']['first_name'],
                    'last_name' => $validated['customer']['last_name'],
                    'phone' => $validated['customer']['phone'] ?? $customer->phone,
                ]);
            }

            // Process all cart items into a single order (supports multi-event carts)
            $subtotal = 0;
            $totalCommission = 0;
            $processedItems = [];
            $seatedItemsMeta = [];
            $eventIds = [];
            $primaryEvent = null;
            $primaryMarketplaceEvent = null;
            $primaryOrganizerId = null;

            foreach ($cartItems as $item) {
                $eventId = $item['event_id'] ?? null;
                $quantity = (int) $item['quantity'];
                $ticketTypeId = $item['ticket_type_id'];

                // Resolve event
                $marketplaceEvent = MarketplaceEvent::find($eventId);
                $event = Event::with(['tenant', 'marketplaceOrganizer'])->find($eventId);

                if (!$event && !$marketplaceEvent) {
                    Log::channel('marketplace')->warning('Checkout: Event not found', ['event_id' => $eventId]);
                    continue;
                }

                // Track primary event (first one) for order-level fields
                if (!$primaryEvent && $event) {
                    $primaryEvent = $event;
                    $primaryMarketplaceEvent = $marketplaceEvent;
                    $primaryOrganizerId = $event->marketplace_organizer_id ?? $marketplaceEvent?->marketplace_organizer_id;
                }
                if ($eventId) {
                    $eventIds[$eventId] = true;
                }

                // Resolve ticket type
                $mktTicketType = null;
                $ticketType = null;

                if ($marketplaceEvent) {
                    $mktTicketType = MarketplaceTicketType::where('id', $ticketTypeId)
                        ->where('marketplace_event_id', $marketplaceEvent->id)
                        ->lockForUpdate()
                        ->first();
                }

                if (!$mktTicketType) {
                    $ticketType = TicketType::where('id', $ticketTypeId)
                        ->when($event, fn ($q) => $q->where('event_id', $event->id))
                        ->lockForUpdate()
                        ->first();
                }

                if (!$ticketType && !$mktTicketType) {
                    throw new \Exception("Ticket type not found: {$ticketTypeId}");
                }

                // Check availability and update stock
                if ($mktTicketType) {
                    $available = $mktTicketType->quantity === null
                        ? PHP_INT_MAX
                        : max(0, $mktTicketType->quantity - ($mktTicketType->quantity_sold ?? 0) - ($mktTicketType->quantity_reserved ?? 0));

                    if ($available < $quantity) {
                        throw new \Exception("Not enough tickets for {$mktTicketType->name}");
                    }

                    $mktTicketType->increment('quantity_sold', $quantity);

                    if ($mktTicketType->quantity !== null && ($mktTicketType->quantity_sold >= $mktTicketType->quantity)) {
                        $mktTicketType->update(['status' => 'sold_out']);
                    }
                } elseif ($ticketType) {
                    $available = $ticketType->quota_total === null
                        ? PHP_INT_MAX
                        : max(0, $ticketType->quota_total - ($ticketType->quota_sold ?? 0));

                    if ($available < $quantity) {
                        throw new \Exception("Not enough tickets for {$ticketType->name}");
                    }

                    if ($ticketType->quota_total !== null) {
                        $ticketType->increment('quota_sold', $quantity);
                    }
                }

                // Determine unit price
                if ($mktTicketType) {
                    $unitPrice = (float) $mktTicketType->price;
                } elseif ($ticketType) {
                    $unitPrice = ($ticketType->sale_price_cents ?? $ticketType->price_cents) / 100;
                } else {
                    $unitPrice = (float) ($item['price'] ?? 0);
                }

                $itemTotal = $unitPrice * $quantity;
                $subtotal += $itemTotal;

                // Calculate per-item commission
                $itemCommissionRate = $event?->commission_rate
                    ?? $event?->marketplaceOrganizer?->commission_rate
                    ?? $event?->tenant?->commission_rate
                    ?? $client->commission_rate
                    ?? 5;
                $itemCommission = round($itemTotal * ($itemCommissionRate / 100), 2);
                $totalCommission += $itemCommission;

                $processedItems[] = [
                    'event' => $event,
                    'marketplace_event' => $marketplaceEvent,
                    'ticket_type' => $ticketType,
                    'marketplace_ticket_type' => $mktTicketType,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $itemTotal,
                    'seat_uids' => $item['seat_uids'] ?? [],
                    'seats' => $item['seats'] ?? [],
                    'event_seating_id' => $item['event_seating_id'] ?? null,
                ];

                // Collect seated items for order meta
                if (!empty($item['seat_uids']) && !empty($item['event_seating_id'])) {
                    $seatedItemsMeta[] = [
                        'event_seating_id' => (int) $item['event_seating_id'],
                        'seat_uids' => $item['seat_uids'],
                    ];
                }
            }

            // Apply discount from promo code
            $discount = 0;
            $promoCode = isset($cart) ? $cart->promo_code : null;
            if ($promoCode && isset($cart)) {
                $discount = (float) ($cart->discount ?? 0);
            }

            // Determine commission mode from primary event
            $commissionMode = $primaryEvent?->commission_mode
                ?? $primaryEvent?->marketplaceOrganizer?->default_commission_mode
                ?? $client->commission_mode
                ?? 'included';
            $isOnTop = in_array($commissionMode, ['on_top', 'added_on_top']);

            // Calculate weighted average commission rate
            $avgCommissionRate = $subtotal > 0 ? round(($totalCommission / $subtotal) * 100, 2) : 0;

            $netAmount = $subtotal - $discount;

            // Final order total
            $orderTotal = $netAmount;
            if ($hasInsurance && $insuranceAmount > 0) {
                $orderTotal += $insuranceAmount;
            }
            if ($isOnTop) {
                $orderTotal += $totalCommission;
            }

            $currency = isset($cart) ? $cart->currency : ($client->currency ?? 'RON');
            $isMultiEvent = count($eventIds) > 1;

            // Create single order
            $order = Order::create([
                'marketplace_client_id' => $client->id,
                'marketplace_organizer_id' => $isMultiEvent ? null : $primaryOrganizerId,
                'tenant_id' => $primaryEvent?->tenant_id,
                'marketplace_customer_id' => $customer->id,
                'event_id' => $isMultiEvent ? null : ($primaryEvent?->id),
                'marketplace_event_id' => $isMultiEvent ? null : ($primaryMarketplaceEvent?->id),
                'order_number' => 'MKT-' . strtoupper(Str::random(8)),
                'status' => 'pending',
                'payment_status' => 'pending',
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'commission_rate' => $avgCommissionRate,
                'commission_amount' => $totalCommission,
                'total' => $orderTotal,
                'currency' => $currency,
                'source' => 'marketplace',
                'customer_email' => $customer->email,
                'customer_name' => $customer->first_name . ' ' . $customer->last_name,
                'customer_phone' => $customer->phone,
                'expires_at' => now()->addMinutes(15),
                'meta' => [
                    'cart_id' => isset($cart) ? $cart->id : null,
                    'promo_code' => $promoCode,
                    'beneficiaries' => $validated['beneficiaries'] ?? [],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'ticket_insurance' => $hasInsurance && $insuranceAmount > 0,
                    'insurance_amount' => $hasInsurance ? $insuranceAmount : 0,
                    'commission_mode' => $commissionMode,
                    'multi_event' => $isMultiEvent,
                    'event_ids' => array_keys($eventIds),
                    'seated_items' => $seatedItemsMeta,
                ],
            ]);

            // Create order items and pending tickets
            $ticketIndex = 0;
            foreach ($processedItems as $item) {
                $mtt = $item['marketplace_ticket_type'];
                $tt = $item['ticket_type'];
                $event = $item['event'];
                $marketplaceEvent = $item['marketplace_event'];

                if ($mtt) {
                    $itemName = is_array($mtt->name)
                        ? ($mtt->name['ro'] ?? $mtt->name['en'] ?? array_values($mtt->name)[0] ?? 'Bilet')
                        : ($mtt->name ?? 'Bilet');
                } elseif ($tt) {
                    $itemName = is_array($tt->name)
                        ? ($tt->name['ro'] ?? $tt->name['en'] ?? array_values($tt->name)[0] ?? 'Bilet')
                        : ($tt->name ?? 'Bilet');
                } else {
                    $itemName = 'Bilet';
                }

                $orderItem = $order->items()->create([
                    'ticket_type_id' => $tt->id ?? $mtt->id ?? null,
                    'name' => $itemName,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['total'],
                    'meta' => [
                        'event_id' => $event?->id ?? $marketplaceEvent?->id,
                    ],
                ]);

                for ($i = 0; $i < $item['quantity']; $i++) {
                    $beneficiary = $validated['beneficiaries'][$ticketIndex] ?? null;

                    $seatUid = $item['seat_uids'][$i] ?? null;
                    $seatInfo = null;
                    if ($seatUid && !empty($item['seats'])) {
                        foreach ($item['seats'] as $seat) {
                            if (($seat['seat_uid'] ?? null) === $seatUid) {
                                $seatInfo = $seat;
                                break;
                            }
                        }
                    }

                    $seatLabel = null;
                    $seatMeta = null;

                    if ($seatUid) {
                        $labelParts = [];
                        if ($seatInfo) {
                            if (!empty($seatInfo['section_name'])) {
                                $labelParts[] = $seatInfo['section_name'];
                            }
                            if (!empty($seatInfo['row_label'])) {
                                $labelParts[] = 'Row ' . $seatInfo['row_label'];
                            }
                            if (!empty($seatInfo['seat_label'])) {
                                $labelParts[] = 'Seat ' . $seatInfo['seat_label'];
                            }
                        }
                        $seatLabel = !empty($labelParts) ? implode(', ', $labelParts) : $seatUid;

                        $seatMeta = [
                            'seat_uid' => $seatUid,
                            'event_seating_id' => $item['event_seating_id'],
                            'section_name' => $seatInfo['section_name'] ?? null,
                            'row_label' => $seatInfo['row_label'] ?? null,
                            'seat_number' => $seatInfo['seat_label'] ?? null,
                        ];
                    }

                    $ticketMeta = $seatMeta ?? [];
                    if ($hasInsurance && $insurancePerTicket > 0) {
                        $ticketMeta['has_insurance'] = true;
                        $ticketMeta['insurance_amount'] = $insurancePerTicket;
                    }

                    Ticket::create([
                        'marketplace_client_id' => $client->id,
                        'tenant_id' => $event?->tenant_id,
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'event_id' => $event?->id,
                        'marketplace_event_id' => $marketplaceEvent?->id,
                        'ticket_type_id' => $tt->id ?? null,
                        'marketplace_ticket_type_id' => $mtt->id ?? null,
                        'marketplace_customer_id' => $customer->id,
                        'code' => strtoupper(Str::random(8)),
                        'barcode' => Str::uuid()->toString(),
                        'status' => 'pending',
                        'price' => $item['unit_price'],
                        'seat_label' => $seatLabel,
                        'attendee_name' => $beneficiary['name'] ?? null,
                        'attendee_email' => $beneficiary['email'] ?? null,
                        'meta' => !empty($ticketMeta) ? $ticketMeta : null,
                    ]);

                    $ticketIndex++;
                }
            }

            // Increment promo code usage
            if ($promoCode && isset($promoCode['code'])) {
                MarketplacePromoCode::where('code', $promoCode['code'])->increment('times_used');
            }

            // Extend seat holds to match order expiration
            $orderExpiresAt = now()->addMinutes(15);
            foreach ($seatedItemsMeta as $seatedItem) {
                \App\Models\Seating\SeatHold::where('event_seating_id', $seatedItem['event_seating_id'])
                    ->whereIn('seat_uid', $seatedItem['seat_uids'])
                    ->update(['expires_at' => $orderExpiresAt]);

                Log::channel('marketplace')->info('Checkout: Extended seat holds for payment', [
                    'client_id' => $client->id,
                    'event_seating_id' => $seatedItem['event_seating_id'],
                    'seat_count' => count($seatedItem['seat_uids']),
                    'hold_extended_to' => $orderExpiresAt->toIso8601String(),
                ]);
            }

            // Build event name for response
            $eventName = 'Comandă';
            if ($primaryEvent) {
                $title = $primaryEvent->getTranslation('title', 'ro');
                if (is_string($title) && $title !== '') {
                    $eventName = $title;
                } elseif (is_array($title)) {
                    $eventName = $title['ro'] ?? $title['en'] ?? reset($title) ?: 'Event';
                }
                if ($isMultiEvent) {
                    $eventName .= ' + ' . (count($eventIds) - 1) . ' alt' . (count($eventIds) > 2 ? 'e evenimente' : ' eveniment');
                }
            }

            // Clear the cart if it exists in database
            if (isset($cart)) {
                $cart->clearItems();
                $cart->save();
            }

            DB::commit();

            // Build response (keep orders array format for backwards compatibility)
            $orders = [[
                'id' => $order->id,
                'order_number' => $order->order_number,
                'event' => [
                    'id' => $primaryEvent?->id ?? $primaryMarketplaceEvent?->id,
                    'name' => $eventName,
                ],
                'subtotal' => (float) $order->subtotal,
                'discount' => (float) $order->discount_amount,
                'insurance' => (float) ($hasInsurance ? $insuranceAmount : 0),
                'total' => (float) $order->total,
                'currency' => $order->currency,
                'expires_at' => $order->expires_at->toIso8601String(),
            ]];

            Log::channel('marketplace')->info('Checkout completed', [
                'client_id' => $client->id,
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'multi_event' => $isMultiEvent,
                'event_count' => count($eventIds),
                'total' => (float) $order->total,
            ]);

            return $this->success([
                'orders' => $orders,
                'customer' => [
                    'id' => $customer->id,
                    'email' => $customer->email,
                    'name' => $customer->first_name . ' ' . $customer->last_name,
                    'has_account' => $customer->password !== null,
                ],
                'payment_required' => (float) $order->total > 0,
            ], 'Checkout successful', 201);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::channel('marketplace')->error('Checkout failed', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Checkout failed: ' . $e->getMessage(), 400);
        }
    }

    /**
     * Get checkout summary (preview before checkout)
     */
    public function summary(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $sessionId = $this->getSessionId($request);

        $cart = MarketplaceCart::bySession($sessionId, $client->id)->first();

        if (!$cart || $cart->isEmpty()) {
            return $this->error('Cart is empty', 400);
        }

        // Group items by event
        $itemsByEvent = collect($cart->items)->groupBy('event_id');
        $summary = [];

        foreach ($itemsByEvent as $eventId => $items) {
            $event = MarketplaceEvent::with('organizer:id,name')->find($eventId);
            if (!$event) {
                continue;
            }

            $eventSubtotal = $items->sum(fn($item) => $item['price'] * $item['quantity']);

            $summary[] = [
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'date' => $event->starts_at->toIso8601String(),
                    'venue' => $event->venue_name,
                    'city' => $event->venue_city,
                    'organizer' => $event->organizer?->name,
                ],
                'items' => $items->map(fn($item) => [
                    'ticket_type' => $item['ticket_type_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['price'] * $item['quantity'],
                ])->values(),
                'subtotal' => $eventSubtotal,
            ];
        }

        return $this->success([
            'summary' => $summary,
            'cart' => [
                'subtotal' => (float) $cart->subtotal,
                'discount' => (float) $cart->discount,
                'total' => (float) $cart->total,
                'currency' => $cart->currency,
                'promo_code' => $cart->promo_code ? $cart->promo_code['code'] : null,
            ],
            'expires_at' => $cart->expires_at?->toIso8601String(),
        ]);
    }

    /**
     * Validate cart items for checkout (accepts array of items)
     * Note: Marketplace sells tenant events, so we use TicketType model (not MarketplaceTicketType)
     */
    protected function validateCartItemsForCheckout(array $items): array
    {
        $errors = [];

        foreach ($items as $key => $item) {
            $ticketTypeId = $item['ticket_type_id'] ?? null;
            $itemEventId = $item['event_id'] ?? null;

            if (!$ticketTypeId) {
                Log::channel('marketplace')->debug('Validation: missing ticket_type_id', ['item' => $item]);
                $errors[$key] = 'Invalid item: missing ticket type';
                continue;
            }

            // Determine which model by checking event context
            $mktTicketType = null;
            $ticketType = null;

            // Check MarketplaceTicketType if event_id matches a MarketplaceEvent
            if ($itemEventId) {
                $mktTicketType = MarketplaceTicketType::where('id', $ticketTypeId)
                    ->where('marketplace_event_id', $itemEventId)
                    ->first();
            }

            if ($mktTicketType) {
                if ($mktTicketType->status === 'sold_out') {
                    $errors[$key] = 'Ticket type is sold out';
                    continue;
                }
                if ($mktTicketType->status !== 'on_sale' && $mktTicketType->status !== 'active') {
                    $errors[$key] = "Ticket type is not available (status: {$mktTicketType->status})";
                    continue;
                }

                $available = $mktTicketType->quantity === null
                    ? PHP_INT_MAX
                    : max(0, $mktTicketType->quantity - ($mktTicketType->quantity_sold ?? 0) - ($mktTicketType->quantity_reserved ?? 0));

                $quantity = $item['quantity'] ?? 1;
                if ($quantity > $available) {
                    $errors[$key] = "Only {$available} tickets available";
                }
                continue;
            }

            // Fallback: try TicketType (tenant ticket types)
            $ticketType = TicketType::with('event')
                ->where('id', $ticketTypeId)
                ->when($itemEventId, fn ($q) => $q->where('event_id', $itemEventId))
                ->first();

            if (!$ticketType) {
                Log::channel('marketplace')->debug('Validation: ticket type not found', ['ticket_type_id' => $ticketTypeId]);
                $errors[$key] = 'Ticket type no longer exists';
                continue;
            }

            if ($ticketType->status !== 'active') {
                $errors[$key] = "Ticket type is not available (status: {$ticketType->status})";
                continue;
            }

            if (!$ticketType->event || !$ticketType->event->is_published) {
                $errors[$key] = "Event is no longer available";
                continue;
            }

            $available = $ticketType->quota_total === null
                ? PHP_INT_MAX
                : max(0, $ticketType->quota_total - ($ticketType->quota_sold ?? 0));

            $quantity = $item['quantity'] ?? 1;
            if ($quantity > $available) {
                $errors[$key] = "Only {$available} tickets available";
            }
        }

        return $errors;
    }

    /**
     * Validate cart items for checkout (accepts MarketplaceCart object)
     * @deprecated Use validateCartItemsForCheckout instead
     */
    protected function validateCartForCheckout(MarketplaceCart $cart): array
    {
        return $this->validateCartItemsForCheckout($cart->items ?? []);
    }

    /**
     * Get session ID from request
     */
    protected function getSessionId(Request $request): string
    {
        // Try header first
        if ($sessionId = $request->header('X-Session-ID')) {
            return $sessionId;
        }

        // Try cookie
        if ($sessionId = $request->cookie('session_id')) {
            return $sessionId;
        }

        // Try Laravel session (may not be available on API routes)
        try {
            if ($request->hasSession() && ($sessionId = $request->session()->getId())) {
                return $sessionId;
            }
        } catch (\Exception $e) {
            // Session not available, fall through to fallback
        }

        // Fallback: generate deterministic ID from IP and user agent
        return md5($request->ip() . $request->userAgent());
    }
}
