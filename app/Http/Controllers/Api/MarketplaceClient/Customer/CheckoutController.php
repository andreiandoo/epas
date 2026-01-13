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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CheckoutController extends BaseController
{
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
        ]);

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
                    $cartItems[] = [
                        'event_id' => $eventId,
                        'ticket_type_id' => $ticketTypeId,
                        'quantity' => $quantity,
                        'price' => $price,
                        'ticket_type_name' => $ticketTypeName,
                    ];
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

            // Group items by event/organizer
            // Note: Marketplace sells tenant events, so we use Event model (not MarketplaceEvent)
            $itemsByEvent = collect($cartItems)->groupBy('event_id');
            $orders = [];

            foreach ($itemsByEvent as $eventId => $eventItems) {
                // Use Event model (tenant events), not MarketplaceEvent
                $event = Event::with('tenant')->find($eventId);
                if (!$event) {
                    Log::channel('marketplace')->warning('Checkout: Event not found', ['event_id' => $eventId]);
                    continue;
                }

                // Calculate totals for this event's items
                $subtotal = 0;
                $orderItems = [];

                foreach ($eventItems as $item) {
                    // Use TicketType model (tenant ticket types), not MarketplaceTicketType
                    $ticketType = TicketType::where('id', $item['ticket_type_id'])
                        ->lockForUpdate()
                        ->first();

                    if (!$ticketType) {
                        throw new \Exception("Ticket type not found: {$item['ticket_type_id']}");
                    }

                    $quantity = (int) $item['quantity'];

                    // Check and reserve tickets - TicketType uses quota_total/quota_sold
                    $available = $ticketType->quota_total === null
                        ? PHP_INT_MAX
                        : max(0, $ticketType->quota_total - ($ticketType->quota_sold ?? 0));

                    if ($available < $quantity) {
                        throw new \Exception("Not enough tickets for {$ticketType->name}");
                    }

                    // Reserve tickets by incrementing quota_sold
                    // Note: For marketplace, we mark as sold immediately since payment follows
                    if ($ticketType->quota_total !== null) {
                        $ticketType->increment('quota_sold', $quantity);
                    }

                    // TicketType uses price_cents (in cents), convert to price
                    $unitPrice = ($ticketType->sale_price_cents ?? $ticketType->price_cents) / 100;
                    $itemTotal = $unitPrice * $quantity;
                    $subtotal += $itemTotal;

                    $orderItems[] = [
                        'ticket_type' => $ticketType,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total' => $itemTotal,
                    ];
                }

                // Apply discount proportionally if promo code exists (cart may be null if items from request)
                $discount = 0;
                $promoCode = isset($cart) ? $cart->promo_code : null;
                $cartSubtotal = isset($cart) ? $cart->subtotal : 0;
                $cartDiscount = isset($cart) ? $cart->discount : 0;
                if ($promoCode && $cartSubtotal > 0) {
                    $ratio = $subtotal / $cartSubtotal;
                    $discount = round($cartDiscount * $ratio, 2);
                }

                // Calculate commission - for tenant events, use tenant or client commission rate
                $commissionRate = $event->tenant?->commission_rate ?? $client->commission_rate ?? 5;
                $netAmount = $subtotal - $discount;
                $commissionAmount = round($netAmount * ($commissionRate / 100), 2);

                // Get currency from cart or default to client's currency
                $currency = isset($cart) ? $cart->currency : ($client->currency ?? 'RON');

                // Create order
                // Note: For tenant events sold via marketplace, we store both tenant_id and marketplace references
                $order = Order::create([
                    'marketplace_client_id' => $client->id,
                    'tenant_id' => $event->tenant_id,  // Store tenant who owns the event
                    'marketplace_customer_id' => $customer->id,
                    'event_id' => $event->id,  // Use event_id for tenant events
                    'order_number' => 'MKT-' . strtoupper(Str::random(8)),
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'subtotal' => $subtotal,
                    'discount_amount' => $discount,
                    'commission_rate' => $commissionRate,
                    'commission_amount' => $commissionAmount,
                    'total' => $netAmount,
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
                    ],
                ]);

                // Create order items and pending tickets
                $ticketIndex = 0;
                foreach ($orderItems as $item) {
                    $orderItem = $order->items()->create([
                        'ticket_type_id' => $item['ticket_type']->id,
                        'name' => is_array($item['ticket_type']->name)
                            ? ($item['ticket_type']->name['ro'] ?? $item['ticket_type']->name['en'] ?? array_values($item['ticket_type']->name)[0] ?? 'Ticket')
                            : ($item['ticket_type']->name ?? 'Ticket'),
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total' => $item['total'],
                    ]);

                    // Create pending tickets
                    for ($i = 0; $i < $item['quantity']; $i++) {
                        $beneficiary = $validated['beneficiaries'][$ticketIndex] ?? null;

                        Ticket::create([
                            'marketplace_client_id' => $client->id,
                            'tenant_id' => $event->tenant_id,
                            'order_id' => $order->id,
                            'order_item_id' => $orderItem->id,
                            'event_id' => $event->id,  // Use event_id for tenant events
                            'ticket_type_id' => $item['ticket_type']->id,  // Use ticket_type_id for tenant ticket types
                            'marketplace_customer_id' => $customer->id,
                            'barcode' => Str::uuid()->toString(),
                            'status' => 'pending',
                            'price' => $item['unit_price'],
                            'attendee_name' => $beneficiary['name'] ?? null,
                            'attendee_email' => $beneficiary['email'] ?? null,
                        ]);

                        $ticketIndex++;
                    }
                }

                // Increment promo code usage
                if ($promoCode && isset($promoCode['code'])) {
                    MarketplacePromoCode::where('code', $promoCode['code'])->increment('times_used');
                }

                $orders[] = [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->name,
                    ],
                    'subtotal' => (float) $order->subtotal,
                    'discount' => (float) $order->discount_amount,
                    'total' => (float) $order->total,
                    'currency' => $order->currency,
                    'expires_at' => $order->expires_at->toIso8601String(),
                ];
            }

            // Clear the cart if it exists in database
            if (isset($cart)) {
                $cart->clearItems();
                $cart->save();
            }

            DB::commit();

            Log::channel('marketplace')->info('Checkout completed', [
                'client_id' => $client->id,
                'customer_id' => $customer->id,
                'orders' => collect($orders)->pluck('id'),
                'total' => collect($orders)->sum('total'),
            ]);

            return $this->success([
                'orders' => $orders,
                'customer' => [
                    'id' => $customer->id,
                    'email' => $customer->email,
                    'name' => $customer->first_name . ' ' . $customer->last_name,
                    'has_account' => $customer->password !== null,
                ],
                'payment_required' => collect($orders)->sum('total') > 0,
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
            if (!$ticketTypeId) {
                Log::channel('marketplace')->debug('Validation: missing ticket_type_id', ['item' => $item]);
                $errors[$key] = 'Invalid item: missing ticket type';
                continue;
            }

            // Use TicketType model (tenant ticket types), not MarketplaceTicketType
            $ticketType = TicketType::with('event')->find($ticketTypeId);

            if (!$ticketType) {
                Log::channel('marketplace')->debug('Validation: ticket type not found', ['ticket_type_id' => $ticketTypeId]);
                $errors[$key] = 'Ticket type no longer exists';
                continue;
            }

            // TicketType uses 'active' status, not 'on_sale'
            if ($ticketType->status !== 'active') {
                Log::channel('marketplace')->debug('Validation: ticket not active', [
                    'ticket_type_id' => $ticketTypeId,
                    'status' => $ticketType->status,
                ]);
                $errors[$key] = "Ticket type is not available (status: {$ticketType->status})";
                continue;
            }

            if (!$ticketType->event || $ticketType->event->status !== 'published') {
                Log::channel('marketplace')->debug('Validation: event not published', [
                    'ticket_type_id' => $ticketTypeId,
                    'event_id' => $ticketType->event_id,
                    'event_status' => $ticketType->event?->status,
                ]);
                $errors[$key] = "Event is no longer available (status: " . ($ticketType->event?->status ?? 'null') . ")";
                continue;
            }

            // TicketType uses quota_total and quota_sold
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
