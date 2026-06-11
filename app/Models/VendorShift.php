<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorShift extends Model
{
    protected $fillable = [
        'tenant_id',
        'vendor_id',
        'vendor_employee_id',
        'festival_edition_id',
        'vendor_pos_device_id',
        'started_at',
        'ended_at',
        'status',
        'sales_count',
        'sales_total_cents',
        'notes',
        'meta',
    ];

    protected $casts = [
        'started_at'        => 'datetime',
        'ended_at'          => 'datetime',
        'sales_count'       => 'integer',
        'sales_total_cents' => 'integer',
        'meta'              => 'array',
    ];

    // ── Relationships ──

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(VendorEmployee::class, 'vendor_employee_id');
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(FestivalEdition::class, 'festival_edition_id');
    }

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(VendorPosDevice::class, 'vendor_pos_device_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(VendorSaleItem::class);
    }

    // ── Helpers ──

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function durationMinutes(): ?int
    {
        if (! $this->ended_at) {
            return (int) $this->started_at->diffInMinutes(now());
        }
        return (int) $this->started_at->diffInMinutes($this->ended_at);
    }

    public function recordSale(int $totalCents): void
    {
        $this->increment('sales_count');
        $this->increment('sales_total_cents', $totalCents);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForEdition($query, int $editionId)
    {
        return $query->where('festival_edition_id', $editionId);
    }
}
