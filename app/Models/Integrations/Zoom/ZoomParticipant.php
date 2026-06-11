<?php

namespace App\Models\Integrations\Zoom;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoomParticipant extends Model
{
    protected $fillable = [
        'connection_id',
        'participant_type',
        'meeting_id',
        'registrant_id',
        'participant_id',
        'email',
        'first_name',
        'last_name',
        'status',
        'join_url',
        'registered_at',
        'joined_at',
        'left_at',
        'duration_seconds',
        'local_type',
        'local_id',
        'custom_questions',
        'metadata',
    ];

    protected $casts = [
        'custom_questions' => 'array',
        'metadata' => 'array',
        'registered_at' => 'datetime',
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ZoomConnection::class, 'connection_id');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function hasAttended(): bool
    {
        return $this->status === 'attended' || $this->joined_at !== null;
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function getDurationMinutesAttribute(): ?int
    {
        return $this->duration_seconds ? (int) ceil($this->duration_seconds / 60) : null;
    }
}
