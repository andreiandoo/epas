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
        'unit_price',
        'meta',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
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

    public function getUnitPriceCentsAttribute(): int
    {
        return (int) round(floatval($this->attributes['unit_price'] ?? 0) * 100);
    }

    public function getTotalCentsAttribute(): int
    {
        return $this->unit_price_cents * $this->quantity;
    }

    public function getTotalAttribute(): float
    {
        return floatval($this->attributes['unit_price'] ?? 0) * $this->quantity;
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
        $price = $this->variant?->display_price ?? $this->product->display_price;
        $this->update(['unit_price' => $price]);
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
