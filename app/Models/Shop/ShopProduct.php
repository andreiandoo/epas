<?php

namespace App\Models\Shop;

use App\Models\MarketplaceClient;

use App\Models\Tenant;
use App\Models\Event;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use App\Support\Translatable;

class ShopProduct extends Model
{
    use HasUuids, Translatable, SoftDeletes;

    protected $table = 'shop_products';

    public array $translatable = ['title', 'description', 'short_description'];

    protected $fillable = [
        'marketplace_client_id',
        'tenant_id',
        'category_id',
        'title',
        'slug',
        'description',
        'short_description',
        'type',
        'sku',
        'price',
        'sale_price',
        'cost',
        'currency',
        'tax_rate',
        'tax_mode',
        'stock_quantity',
        'low_stock_threshold',
        'track_inventory',
        'weight_grams',
        'dimensions',
        'image_url',
        'gallery',
        'digital_file_url',
        'digital_download_limit',
        'digital_download_expiry_days',
        'status',
        'is_featured',
        'is_visible',
        'reviews_enabled',
        'average_rating',
        'review_count',
        'related_product_ids',
        'meta',
        'seo',
    ];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'short_description' => 'array',
        'price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'cost' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'stock_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'track_inventory' => 'boolean',
        'weight_grams' => 'integer',
        'dimensions' => 'array',
        'gallery' => 'array',
        'digital_download_limit' => 'integer',
        'digital_download_expiry_days' => 'integer',
        'is_featured' => 'boolean',
        'is_visible' => 'boolean',
        'reviews_enabled' => 'boolean',
        'average_rating' => 'decimal:1',
        'review_count' => 'integer',
        'related_product_ids' => 'array',
        'meta' => 'array',
        'seo' => 'array',
    ];

    // Relationships

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ShopCategory::class, 'category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ShopProductVariant::class, 'product_id')->orderBy('sort_order');
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(ShopProductVariant::class, 'product_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(ShopAttribute::class, 'shop_product_attribute', 'product_id', 'attribute_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(ShopOrderItem::class, 'product_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ShopReview::class, 'product_id');
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', 'approved');
    }

    public function eventProducts(): HasMany
    {
        return $this->hasMany(ShopEventProduct::class, 'product_id');
    }

    public function stockAlerts(): HasMany
    {
        return $this->hasMany(ShopStockAlert::class, 'product_id');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('track_inventory', false)
                ->orWhere('stock_quantity', '>', 0)
                ->orWhereNull('stock_quantity');
        });
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopePhysical(Builder $query): Builder
    {
        return $query->where('type', 'physical');
    }

    public function scopeDigital(Builder $query): Builder
    {
        return $query->where('type', 'digital');
    }

    public function scopeByCategory(Builder $query, string $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('track_inventory', true)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->where('stock_quantity', '>', 0);
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('track_inventory', true)
            ->where('stock_quantity', '<=', 0);
    }

    // Image URL Accessors
    // FileUpload stores relative paths, we need to convert to full URLs

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

    /**
     * Get gallery as array of full storage URLs
     */
    public function getGalleryAttribute(): ?array
    {
        $value = $this->attributes['gallery'] ?? null;
        if (!$value) {
            return null;
        }

        $gallery = is_string($value) ? json_decode($value, true) : $value;
        if (!is_array($gallery)) {
            return null;
        }

        return array_map(function ($path) {
            if (!$path) return null;
            // If it's already a full URL, return as-is
            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
            // Convert storage path to URL
            return Storage::disk('public')->url($path);
        }, $gallery);
    }

    // Price Accessors
    // Note: Database uses 'price' (decimal) not 'price_cents' (integer)

    /**
     * Get price_cents - converts decimal price to cents for API compatibility
     */
    public function getPriceCentsAttribute(): int
    {
        $price = $this->attributes['price'] ?? 0;
        return (int) round(floatval($price) * 100);
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
     * Get display price (sale price if on sale, otherwise regular price)
     */
    public function getDisplayPriceAttribute(): float
    {
        return floatval($this->attributes['sale_price'] ?? $this->attributes['price'] ?? 0);
    }

    /**
     * Get display price in cents for API
     */
    public function getDisplayPriceCentsAttribute(): int
    {
        return $this->sale_price_cents ?? $this->price_cents ?? 0;
    }

    public function isOnSale(): bool
    {
        $salePrice = $this->attributes['sale_price'] ?? null;
        $price = $this->attributes['price'] ?? 0;
        return $salePrice !== null && floatval($salePrice) > 0 && floatval($salePrice) < floatval($price);
    }

    public function getDiscountPercentage(): ?float
    {
        if (!$this->isOnSale() || !$this->price) {
            return null;
        }

        return round((1 - ($this->sale_price / $this->price)) * 100, 1);
    }

    // Tax Methods

    public function getEffectiveTaxRate(): float
    {
        if ($this->tax_rate !== null) {
            return (float) $this->tax_rate;
        }

        // Get store default from tenant microservice configuration
        $config = $this->tenant?->microservices()
            ->where('slug', 'shop')
            ->first()
            ?->pivot
            ?->configuration;

        return (float) ($config['tax_rate'] ?? 19); // Default 19% VAT
    }

    public function getEffectiveTaxMode(): string
    {
        if ($this->tax_mode !== null) {
            return $this->tax_mode;
        }

        $config = $this->tenant?->microservices()
            ->where('slug', 'shop')
            ->first()
            ?->pivot
            ?->configuration;

        return $config['tax_mode'] ?? 'included';
    }

    public function calculateTax(float $price): float
    {
        $taxRate = $this->getEffectiveTaxRate();
        $taxMode = $this->getEffectiveTaxMode();

        if ($taxMode === 'included') {
            // Tax is included, extract it
            return round($price - ($price / (1 + $taxRate / 100)), 2);
        }

        // Tax is added on top
        return round($price * ($taxRate / 100), 2);
    }

    public function getPriceWithTax(): float
    {
        $price = $this->display_price;
        $taxMode = $this->getEffectiveTaxMode();

        if ($taxMode === 'included') {
            return $price;
        }

        // Add tax on top
        return round($price + $this->calculateTax($price), 2);
    }

    // Inventory Methods

    public function hasVariants(): bool
    {
        return $this->variants()->exists();
    }

    public function isInStock(): bool
    {
        if (!$this->track_inventory) {
            return true;
        }

        if ($this->hasVariants()) {
            return $this->activeVariants()->where('stock_quantity', '>', 0)->exists();
        }

        return $this->stock_quantity === null || $this->stock_quantity > 0;
    }

    public function getTotalStock(): ?int
    {
        if (!$this->track_inventory) {
            return null;
        }

        if ($this->hasVariants()) {
            return $this->activeVariants()->sum('stock_quantity');
        }

        return $this->stock_quantity;
    }

    public function isLowStock(): bool
    {
        if (!$this->track_inventory) {
            return false;
        }

        $totalStock = $this->getTotalStock();
        return $totalStock !== null && $totalStock <= $this->low_stock_threshold && $totalStock > 0;
    }

    public function decrementStock(int $quantity, ?string $variantId = null): bool
    {
        if (!$this->track_inventory) {
            return true;
        }

        if ($variantId) {
            $variant = $this->variants()->find($variantId);
            if ($variant && $variant->stock_quantity !== null) {
                if ($variant->stock_quantity < $quantity) {
                    return false;
                }
                $variant->decrement('stock_quantity', $quantity);
                return true;
            }
        }

        if ($this->stock_quantity !== null) {
            if ($this->stock_quantity < $quantity) {
                return false;
            }
            $this->decrement('stock_quantity', $quantity);
        }

        // Update status if out of stock
        if ($this->track_inventory && $this->getTotalStock() <= 0) {
            $this->update(['status' => 'out_of_stock']);
        }

        return true;
    }

    public function incrementStock(int $quantity, ?string $variantId = null): void
    {
        if (!$this->track_inventory) {
            return;
        }

        if ($variantId) {
            $variant = $this->variants()->find($variantId);
            if ($variant && $variant->stock_quantity !== null) {
                $variant->increment('stock_quantity', $quantity);
                return;
            }
        }

        if ($this->stock_quantity !== null) {
            $this->increment('stock_quantity', $quantity);
        }

        // Update status if back in stock
        if ($this->status === 'out_of_stock' && $this->getTotalStock() > 0) {
            $this->update(['status' => 'active']);
        }
    }

    // Digital Product Methods

    public function isDigital(): bool
    {
        return $this->type === 'digital';
    }

    public function isPhysical(): bool
    {
        return $this->type === 'physical';
    }

    // Related Products

    public function getRelatedProducts(): \Illuminate\Database\Eloquent\Collection
    {
        if (!empty($this->related_product_ids)) {
            return static::whereIn('id', $this->related_product_ids)
                ->active()
                ->visible()
                ->get();
        }

        // Auto-suggest based on same category
        return static::where('tenant_id', $this->tenant_id)
            ->where('id', '!=', $this->id)
            ->where('category_id', $this->category_id)
            ->active()
            ->visible()
            ->limit(4)
            ->inRandomOrder()
            ->get();
    }

    // Reviews

    public function updateReviewStats(): void
    {
        $stats = $this->approvedReviews()
            ->selectRaw('COUNT(*) as count, AVG(rating) as average')
            ->first();

        $this->update([
            'review_count' => $stats->count ?? 0,
            'average_rating' => $stats->average ? round($stats->average, 1) : null,
        ]);
    }
    /**
     * Get the marketplace client that owns this record
     */
    public function marketplaceClient()
    {
        return $this->belongsTo(MarketplaceClient::class);
    }

}
