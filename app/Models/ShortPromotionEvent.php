<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Billable impression or click on a promoted short (D3).
 *
 * Deliberately not folded into short_events: that table is pruned on a retention
 * window, and billing evidence has to outlive analytics.
 */
class ShortPromotionEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'short_promotion_id',
        'marketplace_customer_id',
        'type',
        'charged_cents',
        'created_at',
    ];

    protected $casts = [
        'charged_cents' => 'integer',
        'created_at' => 'datetime',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(ShortPromotion::class, 'short_promotion_id');
    }
}
