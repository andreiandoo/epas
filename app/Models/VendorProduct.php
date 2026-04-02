<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorProduct extends Model
{
    protected $fillable = [
        'vendor_id',
        'festival_edition_id',
        'vendor_product_category_id',
        'type',
        'name',
        'slug',
        'description',
        'unit_measure',
        'weight_volume',
        'supplier_product_id',
        'base_price_cents',
        'sale_price_cents',
        'price_cents',
        'currency',
        'image_url',
        'is_available',
        'is_age_restricted',
        'min_age',
        'sgr_cents',
        'vat_rate',
        'vat_included',
        'sku',
        'sort_order',
        'variants',
        'allergens',
        'tags',
        'meta',
    ];

    protected $casts = [
        'type'              => ProductType::class,
        'price_cents'       => 'integer',
        'base_price_cents'  => 'integer',
        'sale_price_cents'  => 'integer',
        'weight_volume'     => 'decimal:2',
        'is_available'      => 'boolean',
        'is_age_restricted' => 'boolean',
        'min_age'           => 'integer',
        'sgr_cents'         => 'integer',
        'vat_rate'          => 'decimal:2',
        'vat_included'      => 'boolean',
        'variants'          => 'array',
        'allergens'         => 'array',
        'tags'              => 'array',
        'meta'              => 'array',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(FestivalEdition::class, 'festival_edition_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VendorProductCategory::class, 'vendor_product_category_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(VendorSaleItem::class);
    }

    public function getPriceAttribute(): float
    {
        return $this->price_cents / 100;
    }

    public function totalSold(int $editionId = null): int
    {
        $query = $this->saleItems();
        if ($editionId) {
            $query->where('festival_edition_id', $editionId);
        }
        return $query->sum('quantity');
    }

    public function totalRevenueCents(int $editionId = null): int
    {
        $query = $this->saleItems();
        if ($editionId) {
            $query->where('festival_edition_id', $editionId);
        }
        return $query->sum('total_cents');
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }
}
