<?php

namespace App\Models\Integrations\Zoom;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoomRecording extends Model
{
    protected $fillable = [
        'connection_id',
        'meeting_id',
        'recording_id',
        'meeting_uuid',
        'recording_type',
        'file_type',
        'file_size',
        'download_url',
        'play_url',
        'password',
        'status',
        'recording_start',
        'recording_end',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'recording_start' => 'datetime',
        'recording_end' => 'datetime',
    ];

    protected $hidden = ['password', 'download_url'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ZoomConnection::class, 'connection_id');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ZoomMeeting::class, 'meeting_id');
    }

    public function isVideo(): bool
    {
        return $this->file_type === 'MP4';
    }

    public function isAudio(): bool
    {
        return $this->file_type === 'M4A';
    }

    public function isTranscript(): bool
    {
        return $this->file_type === 'TRANSCRIPT';
    }

    public function getFileSizeMbAttribute(): ?float
    {
        return $this->file_size ? round($this->file_size / 1024 / 1024, 2) : null;
    }

    public function getDurationMinutesAttribute(): ?int
    {
        if (!$this->recording_start || !$this->recording_end) {
            return null;
        }
        return (int) $this->recording_start->diffInMinutes($this->recording_end);
    }
}
