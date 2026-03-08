<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchProduct extends Model
{
    use Translatable;

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'sku',
        'price_cents',
        'currency',
        'compare_at_price_cents',
        'stock_quantity',
        'stock_status',
        'track_stock',
        'category',
        'variants',
        'variant_stock',
        'images',
        'weight_grams',
        'is_digital',
        'digital_file_url',
        'is_active',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'variants' => 'array',
        'variant_stock' => 'array',
        'images' => 'array',
        'meta' => 'array',
        'price_cents' => 'integer',
        'compare_at_price_cents' => 'integer',
        'stock_quantity' => 'integer',
        'weight_grams' => 'integer',
        'track_stock' => 'boolean',
        'is_digital' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(MerchOrderItem::class);
    }

    /**
     * Get price in major currency units.
     */
    public function getPriceAttribute(): float
    {
        return $this->price_cents / 100;
    }

    /**
     * Get compare-at price in major currency units.
     */
    public function getCompareAtPriceAttribute(): ?float
    {
        return $this->compare_at_price_cents ? $this->compare_at_price_cents / 100 : null;
    }

    /**
     * Check if the product is on sale.
     */
    public function isOnSale(): bool
    {
        return $this->compare_at_price_cents && $this->compare_at_price_cents > $this->price_cents;
    }

    /**
     * Check if product is in stock (considering variants).
     */
    public function isInStock(?array $variant = null): bool
    {
        if (!$this->track_stock) {
            return $this->stock_status !== 'out_of_stock';
        }

        if ($variant && !empty($this->variant_stock)) {
            $key = implode('-', array_values($variant));
            return ($this->variant_stock[$key] ?? 0) > 0;
        }

        return $this->stock_quantity > 0;
    }

    /**
     * Decrement stock for a purchase.
     */
    public function decrementStock(int $quantity = 1, ?array $variant = null): void
    {
        if (!$this->track_stock) {
            return;
        }

        if ($variant && !empty($this->variant_stock)) {
            $key = implode('-', array_values($variant));
            $stock = $this->variant_stock;
            $stock[$key] = max(0, ($stock[$key] ?? 0) - $quantity);
            $this->update(['variant_stock' => $stock]);
        }

        $this->decrement('stock_quantity', $quantity);

        if ($this->stock_quantity <= 0) {
            $this->update(['stock_status' => 'out_of_stock']);
        }
    }

    /**
     * Get display name from translatable.
     */
    public function getDisplayNameAttribute(): string
    {
        $name = $this->name;
        if (is_array($name)) {
            return $name['ro'] ?? $name['en'] ?? reset($name) ?? '';
        }
        return (string) ($name ?? '');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_status', '!=', 'out_of_stock');
    }
}
