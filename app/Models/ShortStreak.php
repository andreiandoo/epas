<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Watch streak + daily points ledger for one marketplace customer.
 */
class ShortStreak extends Model
{
    protected $fillable = [
        'marketplace_customer_id',
        'current_streak',
        'longest_streak',
        'last_watch_date',
        'total_points',
        'points_today',
        'points_today_date',
    ];

    protected $casts = [
        'current_streak' => 'integer',
        'longest_streak' => 'integer',
        'total_points' => 'integer',
        'points_today' => 'integer',
        'last_watch_date' => 'date',
        'points_today_date' => 'date',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'marketplace_customer_id');
    }
}
