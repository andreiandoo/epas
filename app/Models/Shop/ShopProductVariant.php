<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

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

    // Image URL Accessor

    /**
     * Get image_url as a full storage URL
     */
    public function getImageUrlAttribute(): ?string
    {
        $value = $this->attributes['image_url'] ?? null;
        if (!$value) {
            return null;
        }
        // If it's already a full URL, return as-is
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        // Convert storage path to URL
        return Storage::disk('public')->url($value);
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

    /**
     * Get price_cents - converts decimal price to cents for API compatibility
     */
    public function getPriceCentsAttribute(): ?int
    {
        $price = $this->attributes['price'] ?? null;
        return $price !== null ? (int) round(floatval($price) * 100) : null;
    }

    /**
     * Get sale_price_cents - converts decimal sale_price to cents for API compatibility
     */
    public function getSalePriceCentsAttribute(): ?int
    {
        $salePrice = $this->attributes['sale_price'] ?? null;
        return $salePrice !== null ? (int) round(floatval($salePrice) * 100) : null;
    }

    /**
     * Get display price in cents for API
     */
    public function getDisplayPriceCentsAttribute(): int
    {
        return $this->sale_price_cents ?? $this->price_cents ?? $this->product->display_price_cents ?? 0;
    }

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
