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
            'accept_terms' => 'required|accepted',
        ]);

        $sessionId = $this->getSessionId($request);
        $cart = MarketplaceCart::bySession($sessionId, $client->id)->first();

        if (!$cart || $cart->isEmpty()) {
            return $this->error('Cart is empty', 400);
        }

        // Validate cart items are still available
        $validationErrors = $this->validateCartForCheckout($cart);
        if (!empty($validationErrors)) {
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
            $itemsByEvent = collect($cart->items)->groupBy('event_id');
            $orders = [];

            foreach ($itemsByEvent as $eventId => $eventItems) {
                $event = MarketplaceEvent::with('organizer')->find($eventId);
                if (!$event) {
                    continue;
                }

                // Calculate totals for this event's items
                $subtotal = 0;
                $orderItems = [];

                foreach ($eventItems as $item) {
                    $ticketType = MarketplaceTicketType::where('id', $item['ticket_type_id'])
                        ->lockForUpdate()
                        ->first();

                    if (!$ticketType) {
                        throw new \Exception("Ticket type not found: {$item['ticket_type_id']}");
                    }

                    $quantity = (int) $item['quantity'];

                    // Check and reserve tickets
                    $available = $ticketType->quantity === null
                        ? PHP_INT_MAX
                        : $ticketType->quantity - $ticketType->quantity_sold - $ticketType->quantity_reserved;

                    if ($available < $quantity) {
                        throw new \Exception("Not enough tickets for {$ticketType->name}");
                    }

                    // Reserve tickets
                    if ($ticketType->quantity !== null) {
                        $ticketType->increment('quantity_reserved', $quantity);
                    }

                    $itemTotal = (float) $ticketType->price * $quantity;
                    $subtotal += $itemTotal;

                    $orderItems[] = [
                        'ticket_type' => $ticketType,
                        'quantity' => $quantity,
                        'unit_price' => (float) $ticketType->price,
                        'total' => $itemTotal,
                    ];
                }

                // Apply discount proportionally if promo code exists
                $discount = 0;
                if ($cart->promo_code && $cart->subtotal > 0) {
                    $ratio = $subtotal / $cart->subtotal;
                    $discount = round($cart->discount * $ratio, 2);
                }

                // Calculate commission
                $commissionRate = $event->organizer?->getEffectiveCommissionRate() ?? $client->commission_rate;
                $netAmount = $subtotal - $discount;
                $commissionAmount = round($netAmount * ($commissionRate / 100), 2);

                // Create order
                $order = Order::create([
                    'marketplace_client_id' => $client->id,
                    'marketplace_organizer_id' => $event->marketplace_organizer_id,
                    'marketplace_customer_id' => $customer->id,
                    'marketplace_event_id' => $event->id,
                    'order_number' => 'MKT-' . strtoupper(Str::random(8)),
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'subtotal' => $subtotal,
                    'discount_amount' => $discount,
                    'commission_rate' => $commissionRate,
                    'commission_amount' => $commissionAmount,
                    'total' => $netAmount,
                    'currency' => $cart->currency,
                    'source' => 'marketplace',
                    'customer_email' => $customer->email,
                    'customer_name' => $customer->first_name . ' ' . $customer->last_name,
                    'customer_phone' => $customer->phone,
                    'expires_at' => now()->addMinutes(15),
                    'meta' => [
                        'cart_id' => $cart->id,
                        'promo_code' => $cart->promo_code,
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
                        'name' => $item['ticket_type']->name,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total' => $item['total'],
                    ]);

                    // Create pending tickets
                    for ($i = 0; $i < $item['quantity']; $i++) {
                        $beneficiary = $validated['beneficiaries'][$ticketIndex] ?? null;

                        Ticket::create([
                            'marketplace_client_id' => $client->id,
                            'order_id' => $order->id,
                            'order_item_id' => $orderItem->id,
                            'marketplace_event_id' => $event->id,
                            'marketplace_ticket_type_id' => $item['ticket_type']->id,
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
                if ($cart->promo_code) {
                    MarketplacePromoCode::where('code', $cart->promo_code['code'])->increment('times_used');
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

            // Clear the cart
            $cart->clearItems();
            $cart->save();

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
     * Validate cart items for checkout
     */
    protected function validateCartForCheckout(MarketplaceCart $cart): array
    {
        $errors = [];
        $items = $cart->items ?? [];

        foreach ($items as $key => $item) {
            $ticketType = MarketplaceTicketType::with('event')->find($item['ticket_type_id']);

            if (!$ticketType) {
                $errors[$key] = 'Ticket type no longer exists';
                continue;
            }

            if ($ticketType->status !== 'on_sale') {
                $errors[$key] = 'Ticket type is no longer on sale';
                continue;
            }

            if (!$ticketType->event || $ticketType->event->status !== 'published') {
                $errors[$key] = 'Event is no longer available';
                continue;
            }

            $available = $ticketType->quantity === null
                ? PHP_INT_MAX
                : $ticketType->quantity - $ticketType->quantity_sold - $ticketType->quantity_reserved;

            if ($item['quantity'] > $available) {
                $errors[$key] = "Only {$available} tickets available";
            }
        }

        return $errors;
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
