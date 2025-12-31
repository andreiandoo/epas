<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopWishlistItem extends Model
{
    use HasUuids;

    protected $table = 'shop_wishlist_items';

    protected $fillable = [
        'wishlist_id',
        'product_id',
        'variant_id',
        'quantity',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    // Relationships

    public function wishlist(): BelongsTo
    {
        return $this->belongsTo(ShopWishlist::class, 'wishlist_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ShopProductVariant::class, 'variant_id');
    }

    // Accessors

    public function getDisplayPriceCentsAttribute(): int
    {
        return $this->variant?->display_price_cents ?? $this->product->display_price_cents;
    }

    public function getTotalCentsAttribute(): int
    {
        return $this->display_price_cents * $this->quantity;
    }

    public function isInStock(): bool
    {
        if ($this->variant) {
            return $this->variant->isInStock();
        }

        return $this->product->isInStock();
    }
}
