<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchandiseItem extends Model
{
    protected $fillable = [
        'tenant_id',
        'festival_edition_id',
        'merchandise_supplier_id',
        'name',
        'type',
        'unit',
        'quantity',
        'acquisition_price_cents',
        'currency',
        'vat_rate',
        'invoice_number',
        'invoice_date',
        'notes',
        'meta',
    ];

    protected $casts = [
        'quantity'                 => 'decimal:3',
        'acquisition_price_cents'  => 'integer',
        'vat_rate'                 => 'decimal:2',
        'invoice_date'             => 'date',
        'meta'                     => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(FestivalEdition::class, 'festival_edition_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(MerchandiseSupplier::class, 'merchandise_supplier_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(MerchandiseAllocation::class);
    }

    // ── Helpers ──

    public function getAcquisitionPriceAttribute(): float
    {
        return $this->acquisition_price_cents / 100;
    }

    public function getTotalValueCentsAttribute(): int
    {
        return (int) round($this->quantity * $this->acquisition_price_cents);
    }

    public function quantityAllocated(): float
    {
        return (float) $this->allocations()->sum('quantity_allocated');
    }

    public function quantityReturned(): float
    {
        return (float) $this->allocations()->sum('quantity_returned');
    }

    public function quantityAvailable(): float
    {
        return $this->quantity - $this->quantityAllocated();
    }

    public function quantityInUse(): float
    {
        return $this->quantityAllocated() - $this->quantityReturned();
    }
}
