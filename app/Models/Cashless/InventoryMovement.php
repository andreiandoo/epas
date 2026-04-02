<?php

namespace App\Models\Cashless;

use App\Enums\StockMovementType;
use App\Models\FestivalEdition;
use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'tenant_id',
        'festival_edition_id',
        'inventory_stock_id',
        'supplier_product_id',
        'movement_type',
        'from_vendor_id',
        'to_vendor_id',
        'quantity',
        'unit_measure',
        'reference',
        'notes',
        'performed_by',
        'performed_at',
        'meta',
    ];

    protected $casts = [
        'movement_type' => StockMovementType::class,
        'quantity'       => 'decimal:3',
        'performed_at'   => 'datetime',
        'meta'           => 'array',
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

    public function stock(): BelongsTo
    {
        return $this->belongsTo(InventoryStock::class, 'inventory_stock_id');
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function fromVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'from_vendor_id');
    }

    public function toVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'to_vendor_id');
    }

    // ── Scopes ──

    public function scopeDeliveries($query)
    {
        return $query->where('movement_type', StockMovementType::Delivery);
    }

    public function scopeAllocations($query)
    {
        return $query->where('movement_type', StockMovementType::Allocation);
    }

    public function scopeSales($query)
    {
        return $query->where('movement_type', StockMovementType::Sale);
    }

    public function scopeForEdition($query, int $editionId)
    {
        return $query->where('festival_edition_id', $editionId);
    }
}
