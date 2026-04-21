<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Coupon\CouponCode;
use App\Models\MarketplaceCart;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceOrganizerPromoCode;
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
            'payment_method' => 'nullable|string|in:card,card_cultural,cash,transfer',
            'accept_terms' => 'required|accepted',
            'promo_code' => 'nullable|string|max:50',
            'ticket_insurance' => 'nullable|boolean',
            'ticket_insurance_amount' => 'nullable|numeric|min:0',
            'cultural_card_surcharge' => 'nullable|numeric|min:0',
            'preview_token' => 'nullable|string',
        ]);

        // Determine if this is a test order via valid preview token
        $isTestOrder = false;
        $previewToken = $request->input('preview_token');
        if ($previewToken) {
            // Validate token against at least the first event in the items
            $firstEventId = $request->input('items.0.event.id') ?? $request->input('items.0.event_id');
            if ($firstEventId && $this->validatePreviewToken($previewToken, (int) $firstEventId)) {
                $isTestOrder = true;
            }
        }

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

                // Extract performance_id from cart item (from event data or direct field)
                $performanceId = $item['event']['performance_id'] ?? $item['performance_id'] ?? null;

                if ($eventId && $ticketTypeId) {
                    $cartItem = [
                        'event_id' => $eventId,
                        'ticket_type_id' => $ticketTypeId,
                        'performance_id' => $performanceId,
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

                    // Preserve leisure venue metadata (visit_date, vehicle_info, tour_slot_time)
                    $cartItem['visit_date'] = $item['meta']['visit_date'] ?? $item['visit_date'] ?? null;
                    $cartItem['vehicle_info'] = $item['meta']['vehicle_info'] ?? $item['vehicle_info'] ?? null;
                    $cartItem['meta'] = $item['meta'] ?? null;

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
        $validationErrors = $this->validateCartItemsForCheckout($cartItems, $isTestOrder);
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

            $autoCreatedPassword = null;
            if (!$customer) {
                $plainPassword = $validated['customer']['password'] ?? null;
                $customer = MarketplaceCustomer::create([
                    'marketplace_client_id' => $client->id,
                    'email' => $validated['customer']['email'],
                    'first_name' => $validated['customer']['first_name'],
                    'last_name' => $validated['customer']['last_name'],
                    'phone' => $validated['customer']['phone'] ?? null,
                    'password' => $plainPassword ? Hash::make($plainPassword) : null,
                    'status' => 'active',
                ]);
                if ($plainPassword) {
                    $autoCreatedPassword = $plainPassword;
                }
            } else {
                // Update customer details
                $customer->update([
                    'first_name' => $validated['customer']['first_name'],
                    'last_name' => $validated['customer']['last_name'],
                    'phone' => $validated['customer']['phone'] ?? $customer->phone,
                ]);
                // If customer exists but has no password and one was provided, set it
                if (!$customer->password && !empty($validated['customer']['password'])) {
                    $plainPassword = $validated['customer']['password'];
                    $customer->update(['password' => Hash::make($plainPassword)]);
                    $autoCreatedPassword = $plainPassword;
                }
            }

            // Process all cart items into a single order (supports multi-event carts)
            $subtotal = 0;
            $totalCommission = 0;
            $totalOnTopCommission = 0;
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
                // Skip stock validation and decrement for test orders (allows repeated testing)
                if (!$isTestOrder) {
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
                        // Own stock availability
                        $ownAvailable = ($ticketType->quota_total === null || $ticketType->quota_total < 0)
                            ? PHP_INT_MAX
                            : max(0, $ticketType->quota_total - ($ticketType->quota_sold ?? 0));

                        // Shared pool check
                        $available = $ownAvailable;
                        $event = $ticketType->event;
                        if ($event && $event->general_quota !== null && !$ticketType->is_independent_stock) {
                            $soldNonIndep = $event->ticketTypes()
                                ->where('is_independent_stock', false)
                                ->sum('quota_sold');
                            $poolRemaining = max(0, $event->general_quota - (int) $soldNonIndep);
                            $available = min($ownAvailable, $poolRemaining);
                        }

                        if ($available < $quantity) {
                            throw new \Exception("Not enough tickets for {$ticketType->name}");
                        }

                        if ($ticketType->quota_total !== null && $ticketType->quota_total >= 0) {
                            $ticketType->increment('quota_sold', $quantity);

                            // Check low stock alert
                            $this->checkLowStockAlert($ticketType);
                        }
                    }

                    // Date capacity check for leisure venue events
                    $visitDate = $item['visit_date'] ?? null;
                    if ($visitDate && $mktTicketType && $mktTicketType->daily_capacity) {
                        $dateCap = \App\Models\MarketplaceEventDateCapacity::where('marketplace_event_id', $eventId)
                            ->where('marketplace_ticket_type_id', $ticketTypeId)
                            ->where('visit_date', $visitDate)
                            ->lockForUpdate()
                            ->first();

                        if (!$dateCap) {
                            $dateCap = \App\Models\MarketplaceEventDateCapacity::create([
                                'marketplace_event_id' => $eventId,
                                'marketplace_ticket_type_id' => $ticketTypeId,
                                'visit_date' => $visitDate,
                                'capacity' => $mktTicketType->daily_capacity,
                                'sold' => 0,
                                'reserved' => 0,
                            ]);
                        }

                        if ($dateCap->is_closed) {
                            throw new \Exception("Tickets for {$mktTicketType->name} are not available on {$visitDate}");
                        }

                        if ($dateCap->available < $quantity) {
                            throw new \Exception("Not enough tickets for {$mktTicketType->name} on {$visitDate}");
                        }

                        $dateCap->increment('sold', $quantity);
                    }
                }

                // Determine unit price
                if ($mktTicketType) {
                    // For leisure venues, use effective price (includes pricing rules + date overrides)
                    $visitDate = $item['visit_date'] ?? null;
                    if ($visitDate && $marketplaceEvent?->isLeisureVenue()) {
                        $dateOverride = null;
                        $dateCap = \App\Models\MarketplaceEventDateCapacity::where('marketplace_event_id', $eventId)
                            ->where('marketplace_ticket_type_id', $ticketTypeId)
                            ->where('visit_date', $visitDate)
                            ->first();
                        if ($dateCap && $dateCap->price_override !== null) {
                            $dateOverride = (float) $dateCap->price_override;
                        }
                        $unitPrice = $marketplaceEvent->getEffectivePrice($mktTicketType, $visitDate, $dateOverride);
                    } else {
                        $unitPrice = (float) $mktTicketType->price;
                    }
                } elseif ($ticketType) {
                    $unitPrice = ($ticketType->sale_price_cents ?? $ticketType->price_cents) / 100;
                } else {
                    $unitPrice = (float) ($item['price'] ?? 0);
                }

                $itemTotal = $unitPrice * $quantity;
                $subtotal += $itemTotal;

                // Calculate per-item commission
                // Fallback rate from event > organizer > tenant > client
                $defaultCommissionRate = $event?->commission_rate
                    ?? $event?->marketplaceOrganizer?->commission_rate
                    ?? $event?->tenant?->commission_rate
                    ?? $client->commission_rate
                    ?? 5;
                $defaultCommissionMode = $event?->commission_mode
                    ?? $event?->marketplaceOrganizer?->default_commission_mode
                    ?? $client->commission_mode
                    ?? 'included';

                // Use ticket-type commission override if available
                $itemCommission = 0;
                $itemCommissionMode = $defaultCommissionMode;
                $itemCommissionRate = $defaultCommissionRate;

                if ($ticketType && $ticketType->commission_type) {
                    // Ticket type has its own commission settings
                    $itemCommission = $ticketType->calculateCommission($unitPrice, $defaultCommissionRate, $defaultCommissionMode) * $quantity;
                    $effective = $ticketType->getEffectiveCommission($defaultCommissionRate, $defaultCommissionMode);
                    $itemCommissionRate = $effective['rate'];
                    $itemCommissionMode = $effective['mode'];
                } else {
                    // Use default rate
                    $itemCommission = round($itemTotal * ($defaultCommissionRate / 100), 2);
                }
                $totalCommission += $itemCommission;

                // Track on-top commission per item
                if (in_array($itemCommissionMode, ['on_top', 'added_on_top'])) {
                    $totalOnTopCommission += $itemCommission;
                }

                $processedItems[] = [
                    'event' => $event,
                    'marketplace_event' => $marketplaceEvent,
                    'ticket_type' => $ticketType,
                    'marketplace_ticket_type' => $mktTicketType,
                    'performance_id' => $item['performance_id'] ?? null,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $itemTotal,
                    'commission_amount' => $itemCommission,
                    'commission_rate' => $itemCommissionRate,
                    'commission_mode' => $itemCommissionMode,
                    'seat_uids' => $item['seat_uids'] ?? [],
                    'seats' => $item['seats'] ?? [],
                    'event_seating_id' => $item['event_seating_id'] ?? null,
                    'visit_date' => $item['visit_date'] ?? null,
                    'vehicle_info' => $item['vehicle_info'] ?? null,
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
            $promoCode = null;
            $promoCodeInput = $request->input('promo_code');

            if ($promoCodeInput) {
                $promoCodeInput = strtoupper(trim($promoCodeInput));

                // Build cart data for validation
                $promoCartData = [
                    'event_id' => $primaryEvent?->id ?? $primaryMarketplaceEvent?->id,
                    'total' => $subtotal,
                    'ticket_count' => collect($processedItems)->sum('quantity'),
                    'items' => collect($processedItems)->map(fn ($pi) => [
                        'event_id' => $pi['event']?->id ?? $pi['marketplace_event']?->id,
                        // CouponCodes use TicketType IDs, organizer promos use MarketplaceTicketType IDs
                        'ticket_type_id' => $pi['ticket_type']?->id ?? $pi['marketplace_ticket_type']?->id,
                        'marketplace_ticket_type_id' => $pi['marketplace_ticket_type']?->id,
                        'quantity' => $pi['quantity'],
                        'total' => $pi['total'],
                    ])->all(),
                ];

                // 1. Try organizer promo codes (mkt_promo_codes)
                $orgPromo = MarketplaceOrganizerPromoCode::where('marketplace_client_id', $client->id)
                    ->where('code', $promoCodeInput)
                    ->first();

                if ($orgPromo) {
                    $validation = $orgPromo->validateForCart($promoCartData, $customer->email);
                    if ($validation['valid']) {
                        $calculation = $orgPromo->calculateDiscount($promoCartData);
                        $discount = $calculation['discount_amount'];
                        $promoCode = [
                            'code' => $orgPromo->code,
                            'type' => $orgPromo->type,
                            'value' => (float) $orgPromo->value,
                            'source' => 'organizer',
                            'id' => $orgPromo->id,
                        ];
                    }
                }

                // 2. Try coupon codes (coupon_codes) if no organizer promo matched
                if (!$promoCode) {
                    $coupon = CouponCode::where('marketplace_client_id', $client->id)
                        ->where('code', $promoCodeInput)
                        ->first();

                    if ($coupon && $coupon->isValid()) {
                        // Calculate discount base filtered by applicable ticket types
                        $discountBase = $subtotal;
                        $applicableTicketTypes = $coupon->applicable_ticket_types ?? [];
                        if (!empty($applicableTicketTypes)) {
                            $discountBase = 0;
                            foreach ($promoCartData['items'] as $item) {
                                $ttId = (int) ($item['ticket_type_id'] ?? 0);
                                if (in_array($ttId, array_map('intval', $applicableTicketTypes))) {
                                    $discountBase += (float) ($item['total'] ?? 0);
                                }
                            }
                        }
                        $discount = $coupon->calculateDiscount($discountBase);
                        $promoCode = [
                            'code' => $coupon->code,
                            'type' => $coupon->discount_type === 'percentage' ? 'percentage' : 'fixed',
                            'value' => (float) $coupon->discount_value,
                            'source' => 'coupon',
                            'id' => $coupon->id,
                        ];
                    }
                }
            } elseif (isset($cart) && $cart->promo_code) {
                // Fallback: read from database cart (legacy)
                $promoCode = $cart->promo_code;
                $discount = (float) ($cart->discount ?? 0);
            }

            // Determine primary commission mode (for order-level meta)
            $commissionMode = $primaryEvent?->commission_mode
                ?? $primaryEvent?->marketplaceOrganizer?->default_commission_mode
                ?? $client->commission_mode
                ?? 'included';

            // Calculate weighted average commission rate
            $avgCommissionRate = $subtotal > 0 ? round(($totalCommission / $subtotal) * 100, 2) : 0;

            $netAmount = $subtotal - $discount;

            // Final order total — add only the on-top portion of commission
            $orderTotal = $netAmount;
            if ($hasInsurance && $insuranceAmount > 0) {
                $orderTotal += $insuranceAmount;
            }
            if ($totalOnTopCommission > 0) {
                $orderTotal += $totalOnTopCommission;
            }

            // Cultural card surcharge
            $culturalCardSurcharge = 0;
            if ($request->input('payment_method') === 'card_cultural' && $request->has('cultural_card_surcharge')) {
                $culturalCardSurcharge = round((float) $request->input('cultural_card_surcharge'), 2);
                $orderTotal += $culturalCardSurcharge;
            }

            $currency = isset($cart) ? $cart->currency : ($client->currency ?? 'RON');
            $isMultiEvent = count($eventIds) > 1;

            // Create single order
            // Auto-confirm: test orders AND free orders (total = 0)
            $isFreeOrder = !$isTestOrder && $orderTotal <= 0;
            $isAutoConfirmed = $isTestOrder || $isFreeOrder;

            $order = Order::create([
                'marketplace_client_id' => $client->id,
                'marketplace_organizer_id' => $isMultiEvent ? null : $primaryOrganizerId,
                'tenant_id' => $primaryEvent?->tenant_id,
                'marketplace_customer_id' => $customer->id,
                'event_id' => $isMultiEvent ? null : ($primaryEvent?->id),
                'marketplace_event_id' => $isMultiEvent ? null : ($primaryMarketplaceEvent?->id),
                'order_number' => ($isTestOrder ? 'TEST-' : 'MKT-') . strtoupper(Str::random(8)),
                'status' => $isAutoConfirmed ? 'completed' : 'pending',
                'payment_status' => $isTestOrder ? 'test' : ($isFreeOrder ? 'free' : 'pending'),
                'subtotal' => $isTestOrder ? 0 : $subtotal,
                'discount_amount' => $isTestOrder ? 0 : $discount,
                'commission_rate' => $isTestOrder ? 0 : $avgCommissionRate,
                'commission_amount' => $isTestOrder ? 0 : $totalCommission,
                'total' => $isTestOrder ? 0 : $orderTotal,
                'currency' => $currency,
                'source' => $isTestOrder ? 'test_order' : ($isFreeOrder ? 'marketplace_free' : 'marketplace'),
                'customer_email' => $customer->email,
                'customer_name' => $customer->first_name . ' ' . $customer->last_name,
                'customer_phone' => $customer->phone,
                'expires_at' => $isAutoConfirmed ? null : now()->addMinutes(15),
                'paid_at' => $isAutoConfirmed ? now() : null,
                'meta' => array_merge([
                    'cart_id' => isset($cart) ? $cart->id : null,
                    'promo_code' => $promoCode,
                    'beneficiaries' => $validated['beneficiaries'] ?? [],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'ticket_insurance' => $hasInsurance && $insuranceAmount > 0,
                    'insurance_amount' => $hasInsurance ? $insuranceAmount : 0,
                    'cultural_card_surcharge' => $culturalCardSurcharge > 0 ? $culturalCardSurcharge : null,
                    'payment_method' => $isTestOrder ? 'test' : $request->input('payment_method', 'card'),
                    'commission_mode' => $commissionMode,
                    'commission_details' => collect($processedItems)->map(fn ($pi) => [
                        'ticket_type' => ($pi['marketplace_ticket_type']?->name ?? $pi['ticket_type']?->name ?? 'Bilet'),
                        'quantity' => $pi['quantity'],
                        'unit_price' => $pi['unit_price'],
                        'total' => $pi['total'],
                        'commission_rate' => $pi['commission_rate'],
                        'commission_amount' => $pi['commission_amount'],
                        'commission_mode' => $pi['commission_mode'],
                    ])->all(),
                    'multi_event' => $isMultiEvent,
                    'event_ids' => array_keys($eventIds),
                    'seated_items' => $seatedItemsMeta,
                ], $isTestOrder ? ['is_test_order' => true] : []),
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
                    'performance_id' => $item['performance_id'] ?? null,
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

                    // Leisure venue metadata
                    if (!empty($item['visit_date'])) {
                        $ticketMeta['visit_date'] = $item['visit_date'];
                    }
                    if (!empty($item['vehicle_info'])) {
                        $ticketMeta['vehicle_info'] = $item['vehicle_info'];
                    }
                    // Tour slot time (guided tours)
                    $tourSlot = $item['meta']['tour_slot_time'] ?? $item['tour_slot_time'] ?? null;
                    if ($tourSlot) {
                        $ticketMeta['tour_slot_time'] = $tourSlot;
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
                        'performance_id' => $item['performance_id'] ?? null,
                        'marketplace_customer_id' => $customer->id,
                        'code' => strtoupper(Str::random(8)),
                        'barcode' => Str::uuid()->toString(),
                        'status' => $isAutoConfirmed ? 'valid' : 'pending',
                        'price' => $isTestOrder ? 0 : $item['unit_price'],
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
                $promoSource = $promoCode['source'] ?? null;
                if ($promoSource === 'organizer' && !empty($promoCode['id'])) {
                    $orgPromoModel = MarketplaceOrganizerPromoCode::find($promoCode['id']);
                    if ($orgPromoModel) {
                        $orgPromoModel->recordUsage($order, $discount, $customer, $request->ip());
                    }
                } elseif ($promoSource === 'coupon' && !empty($promoCode['id'])) {
                    CouponCode::where('id', $promoCode['id'])->increment('current_uses');
                } else {
                    // Legacy fallback
                    MarketplacePromoCode::where('code', $promoCode['code'])->increment('times_used');
                }
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

            // Send account created email with set-password link
            // Uses marketplace mail transport (same as order confirmation) for reliable delivery
            if ($autoCreatedPassword) {
                try {
                    // Generate password reset token so user can set their own password
                    $setPasswordToken = \Illuminate\Support\Str::random(64);
                    DB::table('marketplace_password_resets')
                        ->where('email', $customer->email)
                        ->where('type', 'customer')
                        ->where('marketplace_client_id', $client->id)
                        ->delete();
                    DB::table('marketplace_password_resets')->insert([
                        'email' => $customer->email,
                        'type' => 'customer',
                        'marketplace_client_id' => $client->id,
                        'token' => Hash::make($setPasswordToken),
                        'created_at' => now(),
                    ]);

                    $this->sendAccountCreatedEmail($client, $customer, $setPasswordToken);
                } catch (\Exception $e) {
                    Log::channel('marketplace')->warning('Failed to send account created email', [
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

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
                'expires_at' => $order->expires_at?->toIso8601String(),
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
    protected function validateCartItemsForCheckout(array $items, bool $isTestOrder = false): array
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

            if (!$ticketType->event || (!$ticketType->event->is_published && !$isTestOrder)) {
                $errors[$key] = "Event is no longer available";
                continue;
            }

            $ownAvailable = ($ticketType->quota_total === null || $ticketType->quota_total < 0)
                ? PHP_INT_MAX
                : max(0, $ticketType->quota_total - ($ticketType->quota_sold ?? 0));

            $available = $ownAvailable;
            $event = $ticketType->event;
            if ($event && $event->general_quota !== null && !$ticketType->is_independent_stock) {
                $soldNonIndep = $event->ticketTypes()
                    ->where('is_independent_stock', false)
                    ->sum('quota_sold');
                $poolRemaining = max(0, $event->general_quota - (int) $soldNonIndep);
                $available = min($ownAvailable, $poolRemaining);
            }

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

    /**
     * Send account created email via marketplace mail transport.
     */
    protected function sendAccountCreatedEmail($client, MarketplaceCustomer $customer, string $setPasswordToken): void
    {
        $siteName = $client->name ?? 'bilete.online';
        $domain = $client->domain ? rtrim($client->domain, '/') : config('app.url');
        if ($domain && !str_starts_with($domain, 'http')) {
            $domain = 'https://' . $domain;
        }

        $setPasswordUrl = $domain . '/reset-password?' . http_build_query([
            'token' => $setPasswordToken,
            'email' => $customer->email,
        ]);

        $firstName = $customer->first_name ?: 'Client';
        $subject = "Contul tău pe {$siteName}";

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;background:#f8fafc">'
            . '<div style="max-width:600px;margin:0 auto;padding:40px 20px">'
            . '<div style="background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">'
            . '<div style="background:linear-gradient(135deg,#A51C30 0%,#8B1728 100%);padding:32px;text-align:center">'
            . '<h1 style="color:white;margin:0;font-size:24px">Bine ai venit!</h1>'
            . '</div>'
            . '<div style="padding:32px">'
            . '<p style="font-size:16px;color:#1e293b;margin:0 0 16px">Salut ' . htmlspecialchars($firstName) . ',</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 16px">Ți-am creat automat un cont pe <strong>' . htmlspecialchars($siteName) . '</strong> folosind datele de la ultima ta comandă.</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 8px"><strong>Email:</strong> ' . htmlspecialchars($customer->email) . '</p>'
            . '<p style="font-size:15px;color:#475569;margin:0 0 24px">Setează-ți o parolă pentru a-ți activa contul:</p>'
            . '<div style="text-align:center;margin:24px 0">'
            . '<a href="' . htmlspecialchars($setPasswordUrl) . '" style="display:inline-block;background:#A51C30;color:white;text-decoration:none;padding:14px 32px;border-radius:8px;font-weight:600;font-size:16px">Setează parola</a>'
            . '</div>'
            . '<p style="font-size:13px;color:#94a3b8;margin:16px 0 0;text-align:center">Linkul expiră în 60 de minute.</p>'
            . '</div>'
            . '<div style="padding:16px 32px;background:#f8fafc;text-align:center;border-top:1px solid #e2e8f0">'
            . '<p style="font-size:13px;color:#94a3b8;margin:0">Echipa ' . htmlspecialchars($siteName) . '</p>'
            . '</div>'
            . '</div></div></body></html>';

        $this->sendMarketplaceEmail($client, $customer->email, $firstName, $subject, $html, [
            'marketplace_customer_id' => $customer->id,
            'template_slug' => 'account_created',
        ]);
    }

    /**
     * Check if ticket type stock dropped below alert threshold and send notification.
     */
    protected function checkLowStockAlert(TicketType $ticketType): void
    {
        try {
            $event = $ticketType->event;
            if (!$event) return;

            $client = $event->marketplaceClient ?? \App\Models\MarketplaceClient::find($event->marketplace_client_id);
            if (!$client) return;

            $settings = $client->settings ?? [];
            $threshold = $settings['stock_alert_threshold'] ?? null;
            $alertEmail = $settings['stock_alert_email'] ?? null;

            if (!$threshold || !$alertEmail) return;

            // Calculate remaining
            $remaining = $event->getAvailableForTicketType($ticketType);

            // Only alert when crossing the threshold (remaining <= threshold AND remaining + qty_just_sold > threshold)
            if ($remaining > (int) $threshold) return;

            $ttName = is_array($ticketType->name) ? ($ticketType->name['ro'] ?? $ticketType->name['en'] ?? '') : $ticketType->name;
            $eventName = is_array($event->title) ? ($event->title['ro'] ?? $event->title['en'] ?? '') : ($event->title ?? '');
            $organizer = $event->marketplaceOrganizer;

            // Try to use email template
            $template = \App\Models\MarketplaceEmailTemplate::where('marketplace_client_id', $client->id)
                ->where('slug', 'stock_low_alert')
                ->where('is_active', true)
                ->first();

            $venue = $event->venue;
            $venueName = $venue ? (is_array($venue->name) ? ($venue->name['ro'] ?? $venue->name['en'] ?? '') : $venue->name) : '';
            $venueCity = $venue?->city ?? '';
            $eventDate = $event->event_date?->format('d.m.Y') ?? '';

            $variables = [
                'ticket_type' => $ttName,
                'event_name' => $eventName,
                'event_date' => $eventDate,
                'venue_name' => $venueName,
                'venue_city' => $venueCity,
                'remaining_stock' => $remaining,
                'organizer_name' => $organizer?->name ?? $organizer?->company_name ?? 'Organizator',
                'organizer_email' => $organizer?->email ?? '',
                'admin_url' => "https://{$client->domain}/marketplace/events/{$event->id}/edit?tab=bilete",
            ];

            if ($template) {
                $subject = $template->processSubject($variables);
                $html = $template->processBody($variables);
            } else {
                $subject = "⚠ Alertă stoc: {$ttName} — {$eventName}";
                $html = "<div style='font-family:sans-serif;font-size:14px;color:#333;'>"
                    . "Stocul pentru <strong>{$ttName}</strong> ({$eventName}) a scăzut la <strong>{$remaining}</strong> bilete."
                    . "</div>";
            }

            // Build recipient list: admin alert email + optionally organizer
            $recipients = array_filter([$alertEmail]);

            // Check if organizer should also be notified
            $orgNotifications = $event->organizer_notifications ?? [];
            if (!empty($orgNotifications['stock_low_alert']) && $organizer?->email) {
                $recipients[] = $organizer->email;
            }

            $recipients = array_unique($recipients);

            // Route through marketplace transport so the slug is auto-routed to the
            // transactional provider (stock_low_alert is in EmailRouting whitelist).
            foreach ($recipients as $recipient) {
                \App\Http\Controllers\Api\MarketplaceClient\BaseController::sendViaMarketplace(
                    $client,
                    $recipient,
                    '',
                    $subject,
                    $html,
                    [
                        'template_slug' => 'stock_low_alert',
                        'metadata' => [
                            'ticket_type_id' => $ticketType->id,
                            'event_id' => $event->id,
                            'remaining' => $remaining,
                            'threshold' => $threshold,
                        ],
                    ]
                );
            }

            \Log::info('Low stock alert sent', [
                'ticket_type_id' => $ticketType->id,
                'event_id' => $event->id,
                'remaining' => $remaining,
                'threshold' => $threshold,
                'recipients' => $recipients,
                'email' => $alertEmail,
            ]);
        } catch (\Exception $e) {
            \Log::warning('Failed to send low stock alert', ['error' => $e->getMessage()]);
        }
    }
}
