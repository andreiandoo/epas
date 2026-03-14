<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorProduct extends Model
{
    protected $fillable = [
        'vendor_id',
        'festival_edition_id',
        'vendor_product_category_id',
        'name',
        'slug',
        'description',
        'price_cents',
        'currency',
        'image_url',
        'is_available',
        'sort_order',
        'variants',
        'allergens',
        'tags',
        'meta',
    ];

    protected $casts = [
        'price_cents'   => 'integer',
        'is_available'  => 'boolean',
        'variants'      => 'array',
        'allergens'     => 'array',
        'tags'          => 'array',
        'meta'          => 'array',
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
