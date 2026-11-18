<?php

namespace App\Models\Sanius;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/** Tura unui sofer de tractare pe un telefon anume. */
class SaniusTowShift extends Model
{
    protected $table = 'sanius_tow_shifts';

    protected $fillable = [
        'event_id', 'member_id', 'device_id', 'started_at', 'ended_at', 'last_ping_at', 'pending_count',
        'clock_offset_ms', 'force_unlocked_at', 'force_unlocked_by_member_id', 'force_unlock_reason', 'is_test',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'last_ping_at' => 'datetime',
        'force_unlocked_at' => 'datetime',
        'pending_count' => 'integer',
        'clock_offset_ms' => 'integer',
        'is_test' => 'boolean',
    ];

    /**
     * Telefonul poate avea tractari care n-au ajuns pe server: e in tura si
     * fie n-a mai dat ping de mai mult de $timeoutSeconds (e jos, fara semnal),
     * fie la ultimul ping a spus ca mai are scanari de trimis.
     * O deblocare fortata a managerului tine pana la urmatorul ping.
     */
    public function isBlocking(Carbon $now, int $timeoutSeconds): bool
    {
        if ($this->ended_at) {
            return false;
        }
        if ($this->force_unlocked_at && (! $this->last_ping_at || $this->force_unlocked_at->gte($this->last_ping_at))) {
            return false;
        }
        if (! $this->last_ping_at) {
            return true;
        }
        if ($this->pending_count > 0) {
            return true;
        }

        return $this->last_ping_at->lt($now->copy()->subSeconds(max(5, $timeoutSeconds)));
    }
}
