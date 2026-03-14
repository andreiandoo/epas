<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorPosDevice extends Model
{
    protected $fillable = [
        'tenant_id',
        'vendor_id',
        'festival_edition_id',
        'device_uid',
        'name',
        'status',
        'last_seen_at',
        'meta',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'meta'         => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(FestivalEdition::class, 'festival_edition_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(VendorSaleItem::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function heartbeat(): void
    {
        $this->update(['last_seen_at' => now()]);
    }
}
