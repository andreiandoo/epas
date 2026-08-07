<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Tell me when tickets drop" for one customer on one short.
 */
class ShortReminder extends Model
{
    protected $fillable = [
        'marketplace_customer_id',
        'short_id',
        'event_id',
        'ticket_type_id',
        'remind_at',
        'notified_at',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function short(): BelongsTo
    {
        return $this->belongsTo(Short::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCustomer::class, 'marketplace_customer_id');
    }

    /** Due and not yet fired — exactly what FireDropRemindersJob scans for. */
    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->whereNotNull('remind_at')
            ->where('remind_at', '<=', now())
            ->whereNull('notified_at');
    }
}
