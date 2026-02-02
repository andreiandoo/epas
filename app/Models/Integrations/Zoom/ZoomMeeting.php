<?php

namespace App\Models\Integrations\Zoom;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZoomMeeting extends Model
{
    protected $fillable = [
        'connection_id',
        'meeting_id',
        'uuid',
        'host_id',
        'topic',
        'agenda',
        'type',
        'start_time',
        'duration',
        'timezone',
        'join_url',
        'start_url',
        'password',
        'status',
        'settings',
        'correlation_type',
        'correlation_id',
        'metadata',
    ];

    protected $casts = [
        'settings' => 'array',
        'metadata' => 'array',
        'start_time' => 'datetime',
    ];

    protected $hidden = ['password', 'start_url'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ZoomConnection::class, 'connection_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ZoomParticipant::class, 'meeting_id', 'meeting_id')
            ->where('participant_type', 'meeting');
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(ZoomRecording::class, 'meeting_id');
    }

    public function isScheduled(): bool
    {
        return $this->type === 2;
    }

    public function isRecurring(): bool
    {
        return in_array($this->type, [3, 8]);
    }

    public function isInstant(): bool
    {
        return $this->type === 1;
    }

    public function hasStarted(): bool
    {
        return $this->status === 'started';
    }

    public function hasEnded(): bool
    {
        return $this->status === 'finished';
    }
}
