<?php

namespace App\Models\Integrations\FacebookCapi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookCapiEvent extends Model
{
    protected $table = 'facebook_capi_events';

    protected $fillable = [
        'connection_id',
        'event_id',
        'event_name',
        'event_time',
        'event_source_url',
        'action_source',
        'user_data',
        'custom_data',
        'correlation_type',
        'correlation_id',
        'status',
        'fbtrace_id',
        'events_received',
        'messages',
        'error_message',
        'is_test_event',
        'sent_at',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'user_data' => 'array',
        'custom_data' => 'array',
        'messages' => 'array',
        'is_test_event' => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(FacebookCapiConnection::class, 'connection_id');
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function markAsSent(string $fbtraceId, int $eventsReceived, ?array $messages = null): void
    {
        $this->update([
            'status' => 'sent',
            'fbtrace_id' => $fbtraceId,
            'events_received' => $eventsReceived,
            'messages' => $messages,
            'sent_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'sent_at' => now(),
        ]);
    }
}
