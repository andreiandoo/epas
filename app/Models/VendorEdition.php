<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorEdition extends Model
{
    protected $table = 'vendor_edition';

    protected $fillable = [
        'vendor_id',
        'festival_edition_id',
        'location',
        'location_coordinates',
        'vendor_type',
        'commission_rate',
        'commission_mode',
        'fixed_commission_cents',
        'status',
        'operating_hours',
        'meta',
    ];

    protected $casts = [
        'commission_rate'        => 'decimal:2',
        'fixed_commission_cents' => 'integer',
        'operating_hours'        => 'array',
        'meta'                   => 'array',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(FestivalEdition::class, 'festival_edition_id');
    }

    public function calculateCommission(int $amountCents): int
    {
        if ($this->commission_mode === 'fixed_per_transaction') {
            return $this->fixed_commission_cents ?? 0;
        }

        return (int) round($amountCents * $this->commission_rate / 100);
    }
}
