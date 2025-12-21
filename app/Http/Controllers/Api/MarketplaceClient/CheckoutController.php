<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    /**
     * Initialize checkout session.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function init(Request $request): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $cartId = $request->header('X-Cart-Id');
        if (!$cartId) {
            return response()->json(['error' => 'Cart not found'], 400);
        }

        $cart = Cache::get("marketplace_cart:{$cartId}", ['items' => []]);

        if (empty($cart['items'])) {
            return response()->json(['error' => 'Cart is empty'], 400);
        }

        // Validate all items are still available
        $validation = $this->validateCartItems($cart['items'], $tenant);
        if (!$validation['valid']) {
            return response()->json([
                'error' => 'Some items are no longer available',
                'invalid_items' => $validation['invalid_items'],
            ], 400);
        }

        // Create checkout session
        $checkoutId = 'checkout_' . Str::random(32);
        $checkout = [
            'cart_id' => $cartId,
            'items' => $cart['items'],
            'totals' => $validation['totals'],
            'marketplace_id' => $tenant->id,
            'created_at' => now()->toISOString(),
            'expires_at' => now()->addMinutes(15)->toISOString(),
        ];

        Cache::put("marketplace_checkout:{$checkoutId}", $checkout, now()->addMinutes(15));

        return response()->json([
            'checkout_id' => $checkoutId,
            'expires_at' => $checkout['expires_at'],
            'items' => $this->formatCheckoutItems($cart['items'], $tenant),
            'totals' => $validation['totals'],
            'payment_methods' => $this->getPaymentMethods($tenant),
        ]);
    }

    /**
     * Complete checkout and create order.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function complete(Request $request): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'checkout_id' => 'required|string',
            'customer_email' => 'required|email',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'payment_method' => 'required|string',
            'accept_terms' => 'required|accepted',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $checkout = Cache::get("marketplace_checkout:{$request->checkout_id}");

        if (!$checkout) {
            return response()->json(['error' => 'Checkout session expired or not found'], 400);
        }

        // Validate items are still available
        $validation = $this->validateCartItems($checkout['items'], $tenant);
        if (!$validation['valid']) {
            return response()->json([
                'error' => 'Some items are no longer available',
                'invalid_items' => $validation['invalid_items'],
            ], 400);
        }

        try {
            $order = DB::transaction(function () use ($request, $checkout, $tenant, $validation) {
                // Get or create customer
                $customer = Customer::firstOrCreate(
                    ['email' => $request->customer_email],
                    [
                        'name' => $request->customer_name,
                        'phone' => $request->customer_phone,
                        'primary_tenant_id' => $tenant->id,
                    ]
                );

                // Group items by organizer
                $itemsByOrganizer = $this->groupItemsByOrganizer($checkout['items']);

                $orders = [];

                foreach ($itemsByOrganizer as $organizerId => $items) {
                    // Calculate totals for this organizer's items
                    $orderTotal = 0;
                    foreach ($items as $item) {
                        $orderTotal += $item['price_cents'] * $item['quantity'];
                    }

                    // Create order
                    $order = Order::create([
                        'tenant_id' => $tenant->id,
                        'organizer_id' => $organizerId ?: null,
                        'customer_id' => $customer->id,
                        'customer_email' => $request->customer_email,
                        'total_cents' => $orderTotal,
                        'status' => 'pending',
                        'meta' => [
                            'customer_name' => $request->customer_name,
                            'customer_phone' => $request->customer_phone,
                            'payment_method' => $request->payment_method,
                            'checkout_id' => $request->checkout_id,
                        ],
                    ]);

                    // Create tickets
                    foreach ($items as $item) {
                        $ticketType = TicketType::find($item['ticket_type_id']);

                        for ($i = 0; $i < $item['quantity']; $i++) {
                            Ticket::create([
                                'order_id' => $order->id,
                                'ticket_type_id' => $ticketType->id,
                                'status' => 'pending',
                                'code' => strtoupper(Str::random(8)),
                                'barcode' => $this->generateBarcode(),
                                'meta' => [
                                    'event_id' => $ticketType->event_id,
                                    'price_cents' => $item['price_cents'],
                                ],
                            ]);
                        }
                    }

                    $orders[] = $order;
                }

                // Clear cart
                Cache::forget("marketplace_cart:{$checkout['cart_id']}");
                Cache::forget("marketplace_checkout:{$request->checkout_id}");

                return $orders;
            });

            // Return first order for simplicity (multi-organizer orders create multiple orders)
            $primaryOrder = $order[0] ?? null;

            return response()->json([
                'success' => true,
                'order_id' => $primaryOrder?->id,
                'orders' => array_map(fn ($o) => [
                    'id' => $o->id,
                    'total' => $o->total_cents / 100,
                    'status' => $o->status,
                ], $order),
                'payment_url' => $this->getPaymentUrl($primaryOrder, $request->payment_method),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create order',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get order status.
     *
     * @param Request $request
     * @param int $orderId
     * @return JsonResponse
     */
    public function status(Request $request, int $orderId): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $order = Order::where('tenant_id', $tenant->id)
            ->where('id', $orderId)
            ->with('tickets.ticketType.event')
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        return response()->json([
            'order' => [
                'id' => $order->id,
                'status' => $order->status,
                'total' => $order->total_cents / 100,
                'customer_email' => $order->customer_email,
                'created_at' => $order->created_at->toISOString(),
                'tickets' => $order->tickets->map(fn ($ticket) => [
                    'id' => $ticket->id,
                    'code' => $ticket->code,
                    'status' => $ticket->status,
                    'event' => $ticket->ticketType->event->getTranslation('title', 'ro'),
                    'ticket_type' => $ticket->ticketType->name,
                ]),
            ],
        ]);
    }

    /**
     * Validate cart items are still available.
     */
    protected function validateCartItems(array $items, Tenant $tenant): array
    {
        $invalidItems = [];
        $validItems = [];
        $totalCents = 0;

        foreach ($items as $item) {
            $ticketType = TicketType::with('event')->find($item['ticket_type_id']);

            if (!$ticketType) {
                $invalidItems[] = [
                    'ticket_type_id' => $item['ticket_type_id'],
                    'reason' => 'Ticket type not found',
                ];
                continue;
            }

            if ($ticketType->event->tenant_id !== $tenant->id) {
                $invalidItems[] = [
                    'ticket_type_id' => $item['ticket_type_id'],
                    'reason' => 'Ticket type not found',
                ];
                continue;
            }

            if (!$ticketType->is_active) {
                $invalidItems[] = [
                    'ticket_type_id' => $item['ticket_type_id'],
                    'reason' => 'Ticket type no longer available',
                ];
                continue;
            }

            $soldCount = $ticketType->tickets()->count();
            $available = $ticketType->quantity - $soldCount;

            if ($available < $item['quantity']) {
                $invalidItems[] = [
                    'ticket_type_id' => $item['ticket_type_id'],
                    'reason' => 'Not enough tickets available',
                    'available' => $available,
                ];
                continue;
            }

            $validItems[] = $item;
            $totalCents += $item['price_cents'] * $item['quantity'];
        }

        return [
            'valid' => empty($invalidItems),
            'invalid_items' => $invalidItems,
            'valid_items' => $validItems,
            'totals' => [
                'subtotal' => $totalCents / 100,
                'service_fee' => 0,
                'total' => $totalCents / 100,
                'currency' => $tenant->currency ?? 'RON',
            ],
        ];
    }

    /**
     * Group cart items by organizer.
     */
    protected function groupItemsByOrganizer(array $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $ticketType = TicketType::with('event')->find($item['ticket_type_id']);
            $organizerId = $ticketType?->event?->organizer_id ?? 0;

            if (!isset($grouped[$organizerId])) {
                $grouped[$organizerId] = [];
            }

            $grouped[$organizerId][] = $item;
        }

        return $grouped;
    }

    /**
     * Format checkout items.
     */
    protected function formatCheckoutItems(array $items, Tenant $tenant): array
    {
        $ticketTypeIds = array_column($items, 'ticket_type_id');
        $ticketTypes = TicketType::with('event.organizer')->whereIn('id', $ticketTypeIds)->get()->keyBy('id');

        return array_map(function ($item) use ($ticketTypes, $tenant) {
            $ticketType = $ticketTypes[$item['ticket_type_id']] ?? null;

            return [
                'ticket_type_id' => $item['ticket_type_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price_cents'] / 100,
                'line_total' => ($item['price_cents'] * $item['quantity']) / 100,
                'ticket_type_name' => $ticketType?->name,
                'event_name' => $ticketType?->event?->getTranslation('title', 'ro'),
                'event_date' => $ticketType?->event?->start_date?->format('M j, Y'),
                'organizer_name' => $ticketType?->event?->organizer?->name,
            ];
        }, $items);
    }

    /**
     * Get available payment methods for the marketplace.
     */
    protected function getPaymentMethods(Tenant $tenant): array
    {
        $methods = [];

        // Check tenant payment settings
        if ($tenant->stripe_account_id) {
            $methods[] = [
                'id' => 'stripe',
                'name' => 'Card Payment',
                'icon' => 'credit-card',
            ];
        }

        if ($tenant->paypal_email) {
            $methods[] = [
                'id' => 'paypal',
                'name' => 'PayPal',
                'icon' => 'paypal',
            ];
        }

        // Default to bank transfer if no other methods
        if (empty($methods)) {
            $methods[] = [
                'id' => 'bank_transfer',
                'name' => 'Bank Transfer',
                'icon' => 'bank',
            ];
        }

        return $methods;
    }

    /**
     * Get payment URL for the order.
     */
    protected function getPaymentUrl(Order $order, string $paymentMethod): ?string
    {
        // This would integrate with actual payment providers
        // For now, return a generic payment URL
        return url("/payment/{$order->id}?method={$paymentMethod}");
    }

    /**
     * Generate unique barcode for ticket.
     */
    protected function generateBarcode(): string
    {
        return strtoupper(Str::random(16));
    }

    /**
     * Resolve the marketplace tenant from the request.
     */
    protected function resolveMarketplace(Request $request): ?Tenant
    {
        $marketplaceId = $request->header('X-Marketplace-Id');
        if ($marketplaceId) {
            return Tenant::find($marketplaceId);
        }

        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];

        return Tenant::where('slug', $subdomain)
            ->orWhere('custom_domain', $host)
            ->first();
    }
}
