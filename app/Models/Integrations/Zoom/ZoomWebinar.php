<?php

namespace App\Models\Integrations\Zoom;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ZoomWebinar extends Model
{
    protected $fillable = [
        'connection_id',
        'webinar_id',
        'uuid',
        'host_id',
        'topic',
        'agenda',
        'type',
        'start_time',
        'duration',
        'timezone',
        'join_url',
        'registration_url',
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

    protected $hidden = ['password'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ZoomConnection::class, 'connection_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ZoomParticipant::class, 'meeting_id', 'webinar_id')
            ->where('participant_type', 'webinar');
    }

    public function isRecurring(): bool
    {
        return in_array($this->type, [6, 9]);
    }

    public function requiresRegistration(): bool
    {
        return !empty($this->registration_url);
    }
}
