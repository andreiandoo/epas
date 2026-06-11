<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiseAllocation extends Model
{
    protected $fillable = [
        'tenant_id',
        'festival_edition_id',
        'merchandise_item_id',
        'vendor_id',
        'quantity_allocated',
        'quantity_returned',
        'allocated_at',
        'returned_at',
        'status',
        'notes',
        'meta',
    ];

    protected $casts = [
        'quantity_allocated' => 'decimal:3',
        'quantity_returned'  => 'decimal:3',
        'allocated_at'       => 'datetime',
        'returned_at'        => 'datetime',
        'meta'               => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(FestivalEdition::class, 'festival_edition_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MerchandiseItem::class, 'merchandise_item_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    // ── Helpers ──

    public function quantityOutstanding(): float
    {
        return (float) ($this->quantity_allocated - $this->quantity_returned);
    }

    public function markReturned(float $quantity): void
    {
        $this->quantity_returned = min(
            $this->quantity_allocated,
            $this->quantity_returned + $quantity
        );

        $this->status = $this->quantity_returned >= $this->quantity_allocated
            ? 'returned'
            : 'partial_return';

        $this->returned_at = now();
        $this->save();
    }
}
