<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement on an advertiser's prepaid balance (D3).
 *
 * Append-only, and deliberately separate from short_promotion_events: those are
 * the evidence for *what was served*, these are the evidence for *what was
 * charged*. Keeping them apart means the telemetry pruning job can never delete
 * a billing record.
 */
class ShortAdvertiserTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'short_advertiser_id',
        'short_promotion_id',
        'type',
        'amount_cents',
        'balance_after_cents',
        'reference',
        'note',
        'created_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'balance_after_cents' => 'integer',
        'created_at' => 'datetime',
    ];

    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(ShortAdvertiser::class, 'short_advertiser_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(ShortPromotion::class, 'short_promotion_id');
    }
}
