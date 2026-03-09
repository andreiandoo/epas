<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSaleItem extends Model
{
    protected $fillable = [
        'vendor_id',
        'festival_edition_id',
        'vendor_product_id',
        'wristband_transaction_id',
        'vendor_pos_device_id',
        'product_name',
        'category_name',
        'variant_name',
        'quantity',
        'unit_price_cents',
        'total_cents',
        'currency',
        'commission_cents',
        'commission_rate',
        'operator',
        'meta',
    ];

    protected $casts = [
        'quantity'          => 'integer',
        'unit_price_cents'  => 'integer',
        'total_cents'       => 'integer',
        'commission_cents'  => 'integer',
        'commission_rate'   => 'decimal:2',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(VendorProduct::class, 'vendor_product_id');
    }

    public function wristbandTransaction(): BelongsTo
    {
        return $this->belongsTo(WristbandTransaction::class);
    }

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(VendorPosDevice::class, 'vendor_pos_device_id');
    }

    public function getNetVendorCentsAttribute(): int
    {
        return $this->total_cents - $this->commission_cents;
    }

    public function scopeForDay($query, string $date)
    {
        return $query->whereDate('created_at', $date);
    }

    public function scopeForVendor($query, int $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }
}
