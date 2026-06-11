<?php

namespace App\Models\Cashless;

use App\Enums\ProductType;
use App\Models\FestivalEdition;
use App\Models\MerchandiseSupplier;
use App\Models\Tenant;
use App\Models\VendorProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierProduct extends Model
{
    protected $fillable = [
        'tenant_id',
        'merchandise_supplier_id',
        'supplier_brand_id',
        'festival_edition_id',
        'name',
        'sku',
        'type',
        'unit_measure',
        'weight_volume',
        'base_price_cents',
        'vat_rate',
        'price_with_vat_cents',
        'packaging_type',
        'packaging_units',
        'barcode',
        'is_age_restricted',
        'min_age',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'type'               => ProductType::class,
        'weight_volume'      => 'decimal:2',
        'base_price_cents'   => 'integer',
        'vat_rate'           => 'decimal:2',
        'price_with_vat_cents' => 'integer',
        'packaging_units'    => 'integer',
        'is_age_restricted'  => 'boolean',
        'min_age'            => 'integer',
        'is_active'          => 'boolean',
        'meta'               => 'array',
    ];

    // ── Relationships ──

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(MerchandiseSupplier::class, 'merchandise_supplier_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(SupplierBrand::class, 'supplier_brand_id');
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(FestivalEdition::class, 'festival_edition_id');
    }

    public function vendorProducts(): HasMany
    {
        return $this->hasMany(VendorProduct::class, 'supplier_product_id');
    }

    public function inventoryStocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    // ── Helpers ──

    public function getMarkupCents(int $salePriceCents): int
    {
        return $salePriceCents - $this->price_with_vat_cents;
    }

    public function getMarkupPercentage(int $salePriceCents): float
    {
        if ($this->price_with_vat_cents <= 0) {
            return 0;
        }

        return round(($salePriceCents - $this->price_with_vat_cents) / $this->price_with_vat_cents * 100, 2);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEdition($query, int $editionId)
    {
        return $query->where('festival_edition_id', $editionId);
    }
}
