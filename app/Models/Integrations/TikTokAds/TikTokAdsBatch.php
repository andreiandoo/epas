<?php

namespace App\Models\Integrations\TikTokAds;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TikTokAdsBatch extends Model
{
    protected $fillable = [
        'connection_id',
        'event_count',
        'status',
        'events_received',
        'messages',
        'completed_at',
    ];

    protected $casts = [
        'messages' => 'array',
        'completed_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TikTokAdsConnection::class, 'connection_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
