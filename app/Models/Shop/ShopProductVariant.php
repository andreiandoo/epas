<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class ShopProductVariant extends Model
{
    use HasUuids;

    protected $table = 'shop_product_variants';

    protected $fillable = [
        'product_id',
        'sku',
        'price_cents',
        'sale_price_cents',
        'stock_quantity',
        'weight_grams',
        'image_url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'sale_price_cents' => 'integer',
        'stock_quantity' => 'integer',
        'weight_grams' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'product_id');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ShopAttributeValue::class,
            'shop_variant_attribute_value',
            'variant_id',
            'attribute_value_id'
        );
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('stock_quantity', '>', 0)
                ->orWhereNull('stock_quantity');
        });
    }

    // Price Accessors

    public function getPriceAttribute(): float
    {
        $cents = $this->price_cents ?? $this->product->price_cents;
        return $cents / 100;
    }

    public function getSalePriceAttribute(): ?float
    {
        $cents = $this->sale_price_cents ?? $this->product->sale_price_cents;
        return $cents ? $cents / 100 : null;
    }

    public function getDisplayPriceAttribute(): float
    {
        $saleCents = $this->sale_price_cents ?? $this->product->sale_price_cents;
        $priceCents = $this->price_cents ?? $this->product->price_cents;

        return ($saleCents ?? $priceCents) / 100;
    }

    public function getDisplayPriceCentsAttribute(): int
    {
        $saleCents = $this->sale_price_cents ?? $this->product->sale_price_cents;
        $priceCents = $this->price_cents ?? $this->product->price_cents;

        return $saleCents ?? $priceCents;
    }

    public function getEffectivePriceCentsAttribute(): int
    {
        return $this->price_cents ?? $this->product->price_cents;
    }

    public function getEffectiveSalePriceCentsAttribute(): ?int
    {
        return $this->sale_price_cents ?? $this->product->sale_price_cents;
    }

    // Inventory Methods

    public function isInStock(): bool
    {
        if (!$this->product->track_inventory) {
            return true;
        }

        return $this->stock_quantity === null || $this->stock_quantity > 0;
    }

    public function getEffectiveStockAttribute(): ?int
    {
        if (!$this->product->track_inventory) {
            return null;
        }

        return $this->stock_quantity;
    }

    public function getEffectiveWeightAttribute(): ?int
    {
        return $this->weight_grams ?? $this->product->weight_grams;
    }

    // Attribute Label

    public function getAttributeLabel(): string
    {
        $parts = [];
        $locale = app()->getLocale();

        foreach ($this->attributeValues as $value) {
            $parts[] = $value->getTranslation('value', $locale);
        }

        return implode(' / ', $parts);
    }

    public function getFullName(): string
    {
        $productName = $this->product->getTranslation('title', app()->getLocale());
        $variantLabel = $this->getAttributeLabel();

        return $variantLabel ? "{$productName} - {$variantLabel}" : $productName;
    }

    // Match variant by attribute values

    public static function findByAttributeValues(string $productId, array $attributeValueIds): ?self
    {
        $count = count($attributeValueIds);

        return static::where('product_id', $productId)
            ->where('is_active', true)
            ->whereHas('attributeValues', function ($query) use ($attributeValueIds) {
                $query->whereIn('shop_attribute_values.id', $attributeValueIds);
            }, '=', $count)
            ->first();
    }
}
