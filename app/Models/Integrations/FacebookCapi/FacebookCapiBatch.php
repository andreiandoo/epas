<?php

namespace App\Models\Integrations\FacebookCapi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookCapiBatch extends Model
{
    protected $table = 'facebook_capi_batches';

    protected $fillable = [
        'connection_id',
        'event_count',
        'status',
        'events_received',
        'fbtrace_id',
        'messages',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'messages' => 'array',
        'sent_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(FacebookCapiConnection::class, 'connection_id');
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

    public function hasPartialSuccess(): bool
    {
        return $this->status === 'partial';
    }
}
