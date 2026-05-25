<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-date override of the weekly schedule.
 *
 * - is_closed = true → activity is closed that day (open/close ignored)
 * - is_closed = false + open_time/close_time set → custom hours that day
 *
 * SlotResolver checks this table first; if a matching row exists for the
 * requested date, it replaces the weekly schedule for that day.
 */
class ActivityScheduleException extends Model
{
    protected $fillable = [
        'activity_id',
        'exception_date',
        'is_closed',
        'open_time',
        'close_time',
        'reason',
    ];

    protected $casts = [
        'exception_date' => 'date',
        'is_closed' => 'boolean',
        'open_time' => 'datetime:H:i:s',
        'close_time' => 'datetime:H:i:s',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
