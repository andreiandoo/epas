<?php

namespace App\Models\Leisure;

use App\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bookkeeping flags for one biweekly settlement (decont) period of a leisure
 * event. State only — amounts are computed live (SalesBreakdownService).
 * See the "Deconturi" tab.
 */
class LeisureSettlementPeriod extends Model
{
    protected $fillable = [
        'event_id',
        'period_from',
        'period_to',
        'generated_at',
        'settled_at',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'generated_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
