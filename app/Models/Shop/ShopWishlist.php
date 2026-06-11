<?php

namespace App\Models\Shop;

use App\Models\Tenant;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ShopWishlist extends Model
{
    use HasUuids;

    protected $table = 'shop_wishlists';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'name',
        'share_token',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
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
        return $this->hasMany(ShopWishlistItem::class, 'wishlist_id');
    }

    // Methods

    public function generateShareToken(): string
    {
        $token = Str::random(64);
        $this->update(['share_token' => $token]);
        return $token;
    }

    public function getShareUrl(): ?string
    {
        if (!$this->share_token || !$this->is_public) {
            return null;
        }

        return url("/wishlist/{$this->share_token}");
    }

    public function addProduct(string $productId, ?string $variantId = null, int $quantity = 1): ShopWishlistItem
    {
        $existing = $this->items()
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->first();

        if ($existing) {
            $existing->update(['quantity' => $existing->quantity + $quantity]);
            return $existing;
        }

        return $this->items()->create([
            'product_id' => $productId,
            'variant_id' => $variantId,
            'quantity' => $quantity,
        ]);
    }

    public function removeProduct(string $productId, ?string $variantId = null): bool
    {
        return $this->items()
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->delete() > 0;
    }

    public function hasProduct(string $productId, ?string $variantId = null): bool
    {
        return $this->items()
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->exists();
    }

    public function getItemCount(): int
    {
        return $this->items()->count();
    }

    public function getTotalValue(): int
    {
        $total = 0;

        foreach ($this->items as $item) {
            $price = $item->variant?->display_price_cents ?? $item->product->display_price_cents;
            $total += $price * $item->quantity;
        }

        return $total;
    }

    // Static

    public static function getOrCreateForCustomer(int $tenantId, int $customerId): self
    {
        return static::firstOrCreate(
            ['tenant_id' => $tenantId, 'customer_id' => $customerId],
            ['name' => 'My Wishlist']
        );
    }
}
