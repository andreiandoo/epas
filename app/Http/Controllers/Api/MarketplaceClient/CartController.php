<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\TicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CartController extends Controller
{
    /**
     * Get the current cart.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $cartId = $this->getCartId($request);
        $cart = $this->getCart($cartId);

        return response()->json([
            'cart_id' => $cartId,
            'items' => $this->formatCartItems($cart['items'] ?? [], $tenant),
            'totals' => $this->calculateTotals($cart['items'] ?? [], $tenant),
        ]);
    }

    /**
     * Add item to cart.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function add(Request $request): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'ticket_type_id' => 'required|integer',
            'quantity' => 'required|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ticketType = TicketType::with('event')->find($request->ticket_type_id);

        if (!$ticketType) {
            return response()->json(['error' => 'Ticket type not found'], 404);
        }

        if ($ticketType->event->tenant_id !== $tenant->id) {
            return response()->json(['error' => 'Ticket type not found'], 404);
        }

        if (!$ticketType->is_active) {
            return response()->json(['error' => 'This ticket type is no longer available'], 400);
        }

        // Check availability
        $soldCount = $ticketType->tickets()->count();
        $available = $ticketType->quantity - $soldCount;

        if ($available < $request->quantity) {
            return response()->json([
                'error' => 'Not enough tickets available',
                'available' => $available,
            ], 400);
        }

        $cartId = $this->getCartId($request);
        $cart = $this->getCart($cartId);

        // Add or update item in cart
        $items = $cart['items'] ?? [];
        $found = false;

        foreach ($items as &$item) {
            if ($item['ticket_type_id'] === $ticketType->id) {
                $newQty = $item['quantity'] + $request->quantity;

                // Check max per order
                $maxPerOrder = $ticketType->max_per_order ?? 10;
                if ($newQty > $maxPerOrder) {
                    return response()->json([
                        'error' => "Maximum {$maxPerOrder} tickets per order",
                    ], 400);
                }

                // Check availability again
                if ($newQty > $available) {
                    return response()->json([
                        'error' => 'Not enough tickets available',
                        'available' => $available,
                    ], 400);
                }

                $item['quantity'] = $newQty;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $items[] = [
                'ticket_type_id' => $ticketType->id,
                'event_id' => $ticketType->event_id,
                'quantity' => $request->quantity,
                'price_cents' => $ticketType->price_cents,
                'added_at' => now()->toISOString(),
            ];
        }

        $cart['items'] = $items;
        $this->saveCart($cartId, $cart);

        return response()->json([
            'success' => true,
            'cart_id' => $cartId,
            'items' => $this->formatCartItems($cart['items'], $tenant),
            'totals' => $this->calculateTotals($cart['items'], $tenant),
        ]);
    }

    /**
     * Update item quantity in cart.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'ticket_type_id' => 'required|integer',
            'quantity' => 'required|integer|min:0|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $cartId = $this->getCartId($request);
        $cart = $this->getCart($cartId);
        $items = $cart['items'] ?? [];

        if ($request->quantity === 0) {
            // Remove item
            $items = array_values(array_filter($items, fn ($item) => $item['ticket_type_id'] !== $request->ticket_type_id));
        } else {
            // Update quantity
            foreach ($items as &$item) {
                if ($item['ticket_type_id'] === $request->ticket_type_id) {
                    $item['quantity'] = $request->quantity;
                    break;
                }
            }
        }

        $cart['items'] = $items;
        $this->saveCart($cartId, $cart);

        return response()->json([
            'success' => true,
            'cart_id' => $cartId,
            'items' => $this->formatCartItems($cart['items'], $tenant),
            'totals' => $this->calculateTotals($cart['items'], $tenant),
        ]);
    }

    /**
     * Remove item from cart.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function remove(Request $request): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'ticket_type_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $cartId = $this->getCartId($request);
        $cart = $this->getCart($cartId);

        $cart['items'] = array_values(array_filter(
            $cart['items'] ?? [],
            fn ($item) => $item['ticket_type_id'] !== $request->ticket_type_id
        ));

        $this->saveCart($cartId, $cart);

        return response()->json([
            'success' => true,
            'cart_id' => $cartId,
            'items' => $this->formatCartItems($cart['items'], $tenant),
            'totals' => $this->calculateTotals($cart['items'], $tenant),
        ]);
    }

    /**
     * Clear the cart.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clear(Request $request): JsonResponse
    {
        $cartId = $this->getCartId($request);
        $this->saveCart($cartId, ['items' => []]);

        return response()->json([
            'success' => true,
            'cart_id' => $cartId,
            'items' => [],
            'totals' => [
                'subtotal' => 0,
                'service_fee' => 0,
                'total' => 0,
            ],
        ]);
    }

    /**
     * Get or generate cart ID.
     */
    protected function getCartId(Request $request): string
    {
        $cartId = $request->header('X-Cart-Id') ?? $request->cookie('cart_id');

        if (!$cartId) {
            $cartId = 'cart_' . Str::random(32);
        }

        return $cartId;
    }

    /**
     * Get cart from cache.
     */
    protected function getCart(string $cartId): array
    {
        return Cache::get("marketplace_cart:{$cartId}", ['items' => []]);
    }

    /**
     * Save cart to cache.
     */
    protected function saveCart(string $cartId, array $cart): void
    {
        Cache::put("marketplace_cart:{$cartId}", $cart, now()->addHours(2));
    }

    /**
     * Format cart items with event/ticket details.
     */
    protected function formatCartItems(array $items, Tenant $tenant): array
    {
        $ticketTypeIds = array_column($items, 'ticket_type_id');
        $ticketTypes = TicketType::with('event')->whereIn('id', $ticketTypeIds)->get()->keyBy('id');

        return array_map(function ($item) use ($ticketTypes, $tenant) {
            $ticketType = $ticketTypes[$item['ticket_type_id']] ?? null;

            if (!$ticketType) {
                return null;
            }

            return [
                'ticket_type_id' => $item['ticket_type_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price_cents'] / 100,
                'line_total' => ($item['price_cents'] * $item['quantity']) / 100,
                'ticket_type' => [
                    'name' => $ticketType->name,
                    'price_formatted' => number_format($ticketType->price_cents / 100, 2) . ' ' . ($tenant->currency ?? 'RON'),
                ],
                'event' => [
                    'id' => $ticketType->event->id,
                    'slug' => $ticketType->event->slug,
                    'title' => $ticketType->event->getTranslation('title', 'ro'),
                    'date' => $ticketType->event->start_date?->format('M j, Y'),
                    'poster_url' => $ticketType->event->poster_url,
                ],
                'organizer' => $ticketType->event->organizer ? [
                    'name' => $ticketType->event->organizer->name,
                ] : null,
            ];
        }, $items);
    }

    /**
     * Calculate cart totals.
     */
    protected function calculateTotals(array $items, Tenant $tenant): array
    {
        $subtotal = 0;

        foreach ($items as $item) {
            $subtotal += $item['price_cents'] * $item['quantity'];
        }

        $serviceFee = 0; // Service fee can be calculated based on marketplace settings

        return [
            'subtotal' => $subtotal / 100,
            'service_fee' => $serviceFee / 100,
            'total' => ($subtotal + $serviceFee) / 100,
            'currency' => $tenant->currency ?? 'RON',
            'items_count' => array_sum(array_column($items, 'quantity')),
        ];
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
