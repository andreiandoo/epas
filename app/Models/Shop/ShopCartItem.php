<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopCartItem extends Model
{
    use HasUuids;

    protected $table = 'shop_cart_items';

    protected $fillable = [
        'cart_id',
        'product_id',
        'variant_id',
        'quantity',
        'unit_price_cents',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_cents' => 'integer',
        'meta' => 'array',
    ];

    // Relationships

    public function cart(): BelongsTo
    {
        return $this->belongsTo(ShopCart::class, 'cart_id');
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

    public function getTotalCentsAttribute(): int
    {
        return $this->unit_price_cents * $this->quantity;
    }

    public function getUnitPriceAttribute(): float
    {
        return $this->unit_price_cents / 100;
    }

    public function getTotalAttribute(): float
    {
        return $this->total_cents / 100;
    }

    // Methods

    public function isInStock(): bool
    {
        if ($this->variant) {
            return $this->variant->isInStock() &&
                ($this->variant->stock_quantity === null || $this->variant->stock_quantity >= $this->quantity);
        }

        return $this->product->isInStock() &&
            ($this->product->stock_quantity === null || $this->product->stock_quantity >= $this->quantity);
    }

    public function getAvailableQuantity(): ?int
    {
        if ($this->variant) {
            return $this->variant->stock_quantity;
        }

        return $this->product->stock_quantity;
    }

    public function updatePriceFromProduct(): void
    {
        $priceCents = $this->variant?->display_price_cents ?? $this->product->display_price_cents;
        $this->update(['unit_price_cents' => $priceCents]);
    }

    public function getProductTitle(): string
    {
        return $this->product->getTranslation('title', app()->getLocale());
    }

    public function getVariantLabel(): ?string
    {
        return $this->variant?->getAttributeLabel();
    }

    public function getFullName(): string
    {
        $name = $this->getProductTitle();
        $label = $this->getVariantLabel();

        return $label ? "{$name} - {$label}" : $name;
    }
}
