<?php

namespace App\Models\Integrations\GoogleWorkspace;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleCalendarEvent extends Model
{
    protected $fillable = [
        'connection_id', 'event_id', 'calendar_id', 'summary', 'description',
        'location', 'start_time', 'end_time', 'is_all_day', 'attendees',
        'status', 'correlation_ref',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_all_day' => 'boolean',
        'attendees' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(GoogleWorkspaceConnection::class, 'connection_id');
    }
}
