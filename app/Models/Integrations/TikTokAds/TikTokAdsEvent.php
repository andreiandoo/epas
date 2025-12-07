<?php

namespace App\Models\Integrations\TikTokAds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TikTokAdsEvent extends Model
{
    protected $fillable = [
        'connection_id',
        'event_id',
        'event_name',
        'event_time',
        'event_source_url',
        'user_data',
        'properties',
        'contents',
        'ttclid',
        'ttp',
        'status',
        'error_message',
        'sent_at',
        'api_response',
        'correlation_type',
        'correlation_id',
        'is_test_event',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'user_data' => 'array',
        'properties' => 'array',
        'contents' => 'array',
        'sent_at' => 'datetime',
        'api_response' => 'array',
        'is_test_event' => 'boolean',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TikTokAdsConnection::class, 'connection_id');
    }

    public function markAsSent(int $eventsReceived, ?string $message = null): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'api_response' => [
                'events_received' => $eventsReceived,
                'message' => $message,
            ],
        ]);
    }

    public function markAsFailed(string $message): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $message,
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }
}
