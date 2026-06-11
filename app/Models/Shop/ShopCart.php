<?php

namespace App\Models\Shop;

use App\Models\Tenant;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class ShopCart extends Model
{
    use HasUuids;

    protected $table = 'shop_carts';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'session_id',
        'email',
        'currency',
        'coupon_code',
        'status',
        'recovery_emails_sent',
        'last_recovery_email_at',
        'converted_at',
        'converted_order_id',
        'expires_at',
    ];

    protected $casts = [
        'recovery_emails_sent' => 'integer',
        'last_recovery_email_at' => 'datetime',
        'converted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShopCartItem::class, 'cart_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeAbandoned(Builder $query): Builder
    {
        return $query->where('status', 'abandoned');
    }

    public function scopeRecoverable(Builder $query): Builder
    {
        return $query->where('status', 'abandoned')
            ->where('recovery_emails_sent', '<', 3)
            ->whereNotNull('email');
    }

    // Methods

    public function addItem(string $productId, ?string $variantId, int $quantity, int $unitPriceCents): ShopCartItem
    {
        $existing = $this->items()
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->first();

        if ($existing) {
            $existing->update([
                'quantity' => $existing->quantity + $quantity,
                'unit_price_cents' => $unitPriceCents,
            ]);
            return $existing->fresh();
        }

        return $this->items()->create([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
            'unit_price_cents' => $unitPriceCents,
        ]);
    }

    public function updateItemQuantity(string $itemId, int $quantity): ?ShopCartItem
    {
        $item = $this->items()->find($itemId);

        if (!$item) {
            return null;
        }

        if ($quantity <= 0) {
            $item->delete();
            return null;
        }

        $item->update(['quantity' => $quantity]);
        return $item->fresh();
    }

    public function removeItem(string $itemId): bool
    {
        return $this->items()->where('id', $itemId)->delete() > 0;
    }

    public function clear(): void
    {
        $this->items()->delete();
        $this->update(['coupon_code' => null]);
    }

    public function getSubtotalCents(): int
    {
        return $this->items->sum(fn($item) => $item->unit_price_cents * $item->quantity);
    }

    public function getItemCount(): int
    {
        return $this->items->sum('quantity');
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function markAsAbandoned(): void
    {
        if ($this->status === 'active' && !$this->isEmpty()) {
            $this->update(['status' => 'abandoned']);
        }
    }

    public function markAsConverted(string $orderId): void
    {
        $this->update([
            'status' => 'converted',
            'converted_at' => now(),
            'converted_order_id' => $orderId,
        ]);
    }

    public function recordRecoveryEmailSent(): void
    {
        $this->increment('recovery_emails_sent');
        $this->update(['last_recovery_email_at' => now()]);
    }

    public function canSendRecoveryEmail(): bool
    {
        if ($this->status !== 'abandoned' || empty($this->email)) {
            return false;
        }

        if ($this->recovery_emails_sent >= 3) {
            return false;
        }

        // Check cooldown (at least 1 hour between emails)
        if ($this->last_recovery_email_at && $this->last_recovery_email_at->diffInHours(now()) < 1) {
            return false;
        }

        return true;
    }

    // Static

    public static function getOrCreate(int $tenantId, ?int $customerId = null, ?string $sessionId = null): self
    {
        if ($customerId) {
            $cart = static::where('tenant_id', $tenantId)
                ->where('customer_id', $customerId)
                ->where('status', 'active')
                ->first();

            if ($cart) {
                return $cart;
            }
        }

        if ($sessionId) {
            $cart = static::where('tenant_id', $tenantId)
                ->where('session_id', $sessionId)
                ->where('status', 'active')
                ->first();

            if ($cart) {
                // Associate with customer if now logged in
                if ($customerId && !$cart->customer_id) {
                    $cart->update(['customer_id' => $customerId]);
                }
                return $cart;
            }
        }

        return static::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'session_id' => $sessionId,
            'status' => 'active',
        ]);
    }
}
