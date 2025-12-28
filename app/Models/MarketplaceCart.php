<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceCart extends Model
{
    protected $fillable = [
        'marketplace_client_id',
        'session_id',
        'marketplace_customer_id',
        'items',
        'promo_code',
        'subtotal',
        'discount',
        'total',
        'currency',
        'expires_at',
    ];

    protected $casts = [
        'items' => 'array',
        'promo_code' => 'array',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the marketplace client
     */
    public function marketplaceClient(): BelongsTo
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

    /**
     * Get the customer if logged in
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'marketplace_customer_id');
    }

    /**
     * Check if cart is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if cart is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->items) || count($this->items) === 0;
    }

    /**
     * Get item count
     */
    public function getItemCount(): int
    {
        if (empty($this->items)) {
            return 0;
        }

        return collect($this->items)->sum('quantity');
    }

    /**
     * Add item to cart
     */
    public function addItem(array $item): void
    {
        $items = $this->items ?? [];
        $key = $item['event_id'] . '_' . $item['ticket_type_id'];

        if (isset($items[$key])) {
            $items[$key]['quantity'] += $item['quantity'];
        } else {
            $items[$key] = $item;
        }

        $this->items = $items;
        $this->recalculate();
    }

    /**
     * Update item quantity
     */
    public function updateItem(string $key, int $quantity): bool
    {
        $items = $this->items ?? [];

        if (!isset($items[$key])) {
            return false;
        }

        if ($quantity <= 0) {
            unset($items[$key]);
        } else {
            $items[$key]['quantity'] = $quantity;
        }

        $this->items = $items;
        $this->recalculate();
        return true;
    }

    /**
     * Remove item from cart
     */
    public function removeItem(string $key): bool
    {
        $items = $this->items ?? [];

        if (!isset($items[$key])) {
            return false;
        }

        unset($items[$key]);
        $this->items = $items;
        $this->recalculate();
        return true;
    }

    /**
     * Clear all items
     */
    public function clearItems(): void
    {
        $this->items = [];
        $this->promo_code = null;
        $this->subtotal = 0;
        $this->discount = 0;
        $this->total = 0;
    }

    /**
     * Apply promo code
     */
    public function applyPromoCode(array $promoData): void
    {
        $this->promo_code = $promoData;
        $this->recalculate();
    }

    /**
     * Remove promo code
     */
    public function removePromoCode(): void
    {
        $this->promo_code = null;
        $this->recalculate();
    }

    /**
     * Recalculate totals
     */
    public function recalculate(): void
    {
        $subtotal = 0;

        foreach ($this->items ?? [] as $item) {
            $subtotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
        }

        $this->subtotal = $subtotal;

        // Calculate discount
        $discount = 0;
        if ($this->promo_code) {
            $type = $this->promo_code['discount_type'] ?? 'fixed';
            $value = $this->promo_code['discount_value'] ?? 0;
            $maxDiscount = $this->promo_code['max_discount_amount'] ?? null;

            if ($type === 'percentage') {
                $discount = $subtotal * ($value / 100);
                if ($maxDiscount !== null && $discount > $maxDiscount) {
                    $discount = $maxDiscount;
                }
            } else {
                $discount = min($value, $subtotal);
            }
        }

        $this->discount = round($discount, 2);
        $this->total = round($subtotal - $discount, 2);
    }

    /**
     * Refresh item prices from database
     */
    public function refreshPrices(): void
    {
        $items = $this->items ?? [];

        foreach ($items as $key => $item) {
            $ticketType = MarketplaceTicketType::find($item['ticket_type_id']);
            if ($ticketType) {
                $items[$key]['price'] = (float) $ticketType->price;
                $items[$key]['name'] = $ticketType->name;
            }
        }

        $this->items = $items;
        $this->recalculate();
    }

    /**
     * Extend expiration
     */
    public function extendExpiration(int $minutes = 30): void
    {
        $this->expires_at = now()->addMinutes($minutes);
    }

    /**
     * Scope by session
     */
    public function scopeBySession($query, string $sessionId, int $clientId)
    {
        return $query->where('session_id', $sessionId)
            ->where('marketplace_client_id', $clientId);
    }

    /**
     * Get or create cart for session
     */
    public static function getOrCreate(string $sessionId, int $clientId, ?int $customerId = null): self
    {
        $cart = static::bySession($sessionId, $clientId)->first();

        if (!$cart) {
            $cart = static::create([
                'marketplace_client_id' => $clientId,
                'session_id' => $sessionId,
                'marketplace_customer_id' => $customerId,
                'items' => [],
                'currency' => 'RON',
                'expires_at' => now()->addMinutes(30),
            ]);
        } elseif ($customerId && !$cart->marketplace_customer_id) {
            $cart->update(['marketplace_customer_id' => $customerId]);
        }

        return $cart;
    }
}
