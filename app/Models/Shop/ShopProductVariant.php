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
        'price',
        'sale_price',
        'stock_quantity',
        'weight_grams',
        'image_url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
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

    public function getEffectivePriceAttribute(): float
    {
        return $this->price ?? $this->product->price ?? 0;
    }

    public function getEffectiveSalePriceAttribute(): ?float
    {
        return $this->sale_price ?? $this->product->sale_price;
    }

    public function getDisplayPriceAttribute(): float
    {
        $salePrice = $this->sale_price ?? $this->product->sale_price;
        $price = $this->price ?? $this->product->price;

        return $salePrice ?? $price ?? 0;
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
