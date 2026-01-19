<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CartService
{
    protected const CART_TTL = 1800; // 30 minutes
    protected const CART_PREFIX = 'cart:';

    /**
     * Get cart key for a session/tenant combination
     */
    protected function getCartKey(string $sessionId, int $tenantId): string
    {
        return self::CART_PREFIX . $tenantId . ':' . $sessionId;
    }

    /**
     * Get cart for a session
     */
    public function getCart(string $sessionId, int $tenantId): array
    {
        $key = $this->getCartKey($sessionId, $tenantId);
        $cart = Cache::get($key, [
            'items' => [],
            'promo_code' => null,
            'discount' => 0,
            'created_at' => now()->toIso8601String(),
        ]);

        // Validate and refresh item data
        $cart['items'] = $this->validateCartItems($cart['items'], $tenantId);

        return $cart;
    }

    /**
     * Add item to cart
     */
    public function addItem(
        string $sessionId,
        int $tenantId,
        int $eventId,
        int $ticketTypeId,
        int $quantity,
        ?array $seatIds = null
    ): array {
        $cart = $this->getCart($sessionId, $tenantId);

        // Validate ticket type
        $ticketType = TicketType::where('id', $ticketTypeId)
            ->where('event_id', $eventId)
            ->first();

        if (!$ticketType) {
            return [
                'success' => false,
                'message' => 'Ticket type not found',
            ];
        }

        $event = $ticketType->event;
        if (!$event || $event->tenant_id !== $tenantId) {
            return [
                'success' => false,
                'message' => 'Event not found for this tenant',
            ];
        }

        // Check availability
        $available = $ticketType->quota_total - $ticketType->quota_sold;
        if ($available < $quantity) {
            return [
                'success' => false,
                'message' => 'Not enough tickets available',
                'available' => $available,
            ];
        }

        // Generate cart item ID
        $itemId = $this->generateItemId($eventId, $ticketTypeId, $seatIds);

        // Check if item already exists
        $existingIndex = $this->findItemIndex($cart['items'], $itemId);

        if ($existingIndex !== null) {
            // Update quantity
            $newQuantity = $cart['items'][$existingIndex]['quantity'] + $quantity;
            if ($newQuantity > 10) {
                return [
                    'success' => false,
                    'message' => 'Maximum 10 tickets per type',
                ];
            }
            $cart['items'][$existingIndex]['quantity'] = $newQuantity;
        } else {
            // Add new item
            $cart['items'][] = [
                'id' => $itemId,
                'event_id' => $eventId,
                'ticket_type_id' => $ticketTypeId,
                'quantity' => $quantity,
                'seat_ids' => $seatIds,
                'event_title' => $event->getTranslation('title', app()->getLocale()),
                'event_slug' => $event->slug,
                'event_date' => $event->event_date,
                'event_time' => $event->start_time,
                'event_poster' => $event->poster_url ? Storage::disk('public')->url($event->poster_url) : null,
                'ticket_type_name' => $ticketType->name,
                'price_cents' => $ticketType->display_price_cents ?? ($ticketType->display_price * 100),
                'original_price_cents' => $ticketType->price_cents ?? ($ticketType->price * 100),
                'currency' => $ticketType->currency ?? 'RON',
                'venue_name' => $event->venue?->getTranslation('name', app()->getLocale()),
                'venue_city' => $event->venue?->city,
                'added_at' => now()->toIso8601String(),
            ];
        }

        $this->saveCart($sessionId, $tenantId, $cart);

        return [
            'success' => true,
            'item_id' => $itemId,
            'cart' => $this->formatCart($cart, $tenantId),
        ];
    }

    /**
     * Update item quantity
     */
    public function updateItem(string $sessionId, int $tenantId, string $itemId, int $quantity): array
    {
        $cart = $this->getCart($sessionId, $tenantId);

        $index = $this->findItemIndex($cart['items'], $itemId);

        if ($index === null) {
            return [
                'success' => false,
                'message' => 'Item not found in cart',
            ];
        }

        if ($quantity <= 0) {
            return $this->removeItem($sessionId, $tenantId, $itemId);
        }

        if ($quantity > 10) {
            return [
                'success' => false,
                'message' => 'Maximum 10 tickets per type',
            ];
        }

        // Check availability
        $ticketType = TicketType::find($cart['items'][$index]['ticket_type_id']);
        if ($ticketType) {
            $available = $ticketType->quota_total - $ticketType->quota_sold;
            if ($available < $quantity) {
                return [
                    'success' => false,
                    'message' => 'Not enough tickets available',
                    'available' => $available,
                ];
            }
        }

        $cart['items'][$index]['quantity'] = $quantity;
        $this->saveCart($sessionId, $tenantId, $cart);

        return [
            'success' => true,
            'cart' => $this->formatCart($cart, $tenantId),
        ];
    }

    /**
     * Remove item from cart
     */
    public function removeItem(string $sessionId, int $tenantId, string $itemId): array
    {
        $cart = $this->getCart($sessionId, $tenantId);

        $index = $this->findItemIndex($cart['items'], $itemId);

        if ($index === null) {
            return [
                'success' => false,
                'message' => 'Item not found in cart',
            ];
        }

        array_splice($cart['items'], $index, 1);
        $this->saveCart($sessionId, $tenantId, $cart);

        return [
            'success' => true,
            'cart' => $this->formatCart($cart, $tenantId),
        ];
    }

    /**
     * Clear cart
     */
    public function clearCart(string $sessionId, int $tenantId): array
    {
        $key = $this->getCartKey($sessionId, $tenantId);
        Cache::forget($key);

        return [
            'success' => true,
            'cart' => $this->formatCart([
                'items' => [],
                'promo_code' => null,
                'discount' => 0,
            ], $tenantId),
        ];
    }

    /**
     * Apply promo code to cart
     */
    public function applyPromoCode(string $sessionId, int $tenantId, string $code, array $discount): array
    {
        $cart = $this->getCart($sessionId, $tenantId);

        $cart['promo_code'] = [
            'code' => $code,
            'discount_type' => $discount['discount_type'],
            'discount_value' => $discount['discount_value'],
            'discount_amount' => $discount['discount_amount'],
            'max_discount_amount' => $discount['max_discount_amount'] ?? null,
            'name' => $discount['name'] ?? $code,
        ];

        $this->saveCart($sessionId, $tenantId, $cart);

        return [
            'success' => true,
            'cart' => $this->formatCart($cart, $tenantId),
        ];
    }

    /**
     * Remove promo code from cart
     */
    public function removePromoCode(string $sessionId, int $tenantId): array
    {
        $cart = $this->getCart($sessionId, $tenantId);

        $cart['promo_code'] = null;

        $this->saveCart($sessionId, $tenantId, $cart);

        return [
            'success' => true,
            'cart' => $this->formatCart($cart, $tenantId),
        ];
    }

    /**
     * Save cart to cache
     */
    protected function saveCart(string $sessionId, int $tenantId, array $cart): void
    {
        $key = $this->getCartKey($sessionId, $tenantId);
        Cache::put($key, $cart, self::CART_TTL);
    }

    /**
     * Generate unique item ID
     */
    protected function generateItemId(int $eventId, int $ticketTypeId, ?array $seatIds = null): string
    {
        $base = "e{$eventId}-t{$ticketTypeId}";
        if ($seatIds && count($seatIds) > 0) {
            sort($seatIds);
            $base .= '-s' . implode(',', $seatIds);
        }
        return $base;
    }

    /**
     * Find item index in cart
     */
    protected function findItemIndex(array $items, string $itemId): ?int
    {
        foreach ($items as $index => $item) {
            if ($item['id'] === $itemId) {
                return $index;
            }
        }
        return null;
    }

    /**
     * Validate and refresh cart items data
     */
    protected function validateCartItems(array $items, int $tenantId): array
    {
        $validItems = [];

        foreach ($items as $item) {
            $ticketType = TicketType::with('event')->find($item['ticket_type_id']);

            if (!$ticketType || !$ticketType->event || $ticketType->event->tenant_id !== $tenantId) {
                continue; // Remove invalid items
            }

            if ($ticketType->event->status !== 'published') {
                continue; // Remove items from non-published events
            }

            // Update prices in case they changed
            $item['price_cents'] = $ticketType->display_price_cents ?? ($ticketType->display_price * 100);
            $item['original_price_cents'] = $ticketType->price_cents ?? ($ticketType->price * 100);
            $item['event_title'] = $ticketType->event->getTranslation('title', app()->getLocale());

            $validItems[] = $item;
        }

        return $validItems;
    }

    /**
     * Format cart for API response
     */
    public function formatCart(array $cart, int $tenantId): array
    {
        $subtotalCents = 0;
        $currency = 'RON';

        foreach ($cart['items'] as $item) {
            $subtotalCents += $item['price_cents'] * $item['quantity'];
            $currency = $item['currency'] ?? $currency;
        }

        $discountCents = 0;
        if (!empty($cart['promo_code'])) {
            $promo = $cart['promo_code'];
            if ($promo['discount_type'] === 'percentage') {
                $discountCents = (int) ($subtotalCents * ($promo['discount_value'] / 100));
                if ($promo['max_discount_amount']) {
                    $discountCents = min($discountCents, (int) ($promo['max_discount_amount'] * 100));
                }
            } else {
                $discountCents = (int) ($promo['discount_value'] * 100);
            }
        }

        // Get tenant fees settings
        $tenant = \App\Models\Tenant::find($tenantId);
        $feePercentage = $tenant->settings['ticket_fee_percentage'] ?? 0;
        $feesCents = (int) (($subtotalCents - $discountCents) * ($feePercentage / 100));

        $totalCents = $subtotalCents - $discountCents + $feesCents;

        return [
            'items' => array_values($cart['items']),
            'item_count' => count($cart['items']),
            'total_quantity' => array_sum(array_column($cart['items'], 'quantity')),
            'subtotal_cents' => $subtotalCents,
            'subtotal' => number_format($subtotalCents / 100, 2),
            'discount_cents' => $discountCents,
            'discount' => number_format($discountCents / 100, 2),
            'fees_cents' => $feesCents,
            'fees' => number_format($feesCents / 100, 2),
            'total_cents' => $totalCents,
            'total' => number_format($totalCents / 100, 2),
            'currency' => $currency,
            'promo_code' => $cart['promo_code'],
            'expires_at' => now()->addSeconds(self::CART_TTL)->toIso8601String(),
        ];
    }
}
