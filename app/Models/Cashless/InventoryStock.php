<?php

namespace App\Models\Cashless;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryStock extends Model
{
    protected $fillable = [
        'tenant_id',
        'festival_edition_id',
        'supplier_product_id',
        'vendor_id',
        'quantity_total',
        'quantity_allocated',
        'quantity_sold',
        'quantity_returned',
        'quantity_wasted',
        'unit_measure',
        'last_movement_at',
        'meta',
    ];

    protected $casts = [
        'quantity_total'     => 'decimal:3',
        'quantity_allocated' => 'decimal:3',
        'quantity_sold'      => 'decimal:3',
        'quantity_returned'  => 'decimal:3',
        'quantity_wasted'    => 'decimal:3',
        'last_movement_at'   => 'datetime',
        'meta'               => 'array',
    ];

    // ── Relationships ──

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(FestivalEdition::class, 'festival_edition_id');
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    // ── Computed ──

    public function getQuantityAvailableAttribute(): float
    {
        return $this->quantity_total
            - $this->quantity_allocated
            - $this->quantity_sold
            - $this->quantity_returned
            - $this->quantity_wasted;
    }

    public function isFestivalStock(): bool
    {
        return $this->vendor_id === null;
    }

    public function isLow(float $threshold = 0.2): bool
    {
        if ($this->quantity_total <= 0) {
            return false;
        }

        return ($this->quantityAvailable / $this->quantity_total) <= $threshold;
    }

    public function isExhausted(): bool
    {
        return $this->quantityAvailable <= 0;
    }

    // ── Scopes ──

    public function scopeFestivalLevel($query)
    {
        return $query->whereNull('vendor_id');
    }

    public function scopeVendorLevel($query)
    {
        return $query->whereNotNull('vendor_id');
    }

    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeForEdition($query, int $editionId)
    {
        return $query->where('festival_edition_id', $editionId);
    }
}
