<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One weekly recurring open interval for an Activity.
 *
 * `day_of_week` is ISO-style: 1 = Monday … 7 = Sunday. (Carbon's
 * `dayOfWeekIso` returns the same range, which is what SlotResolver
 * uses to match a calendar date to its schedule rows.)
 */
class ActivitySchedule extends Model
{
    protected $fillable = [
        'activity_id',
        'day_of_week',
        'open_time',
        'close_time',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'open_time' => 'datetime:H:i:s',
        'close_time' => 'datetime:H:i:s',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
