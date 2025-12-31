<?php

namespace App\Models\Integrations\Zoom;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoomWebhookEvent extends Model
{
    protected $fillable = [
        'connection_id',
        'event_type',
        'event_ts',
        'payload',
        'status',
        'processing_error',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ZoomConnection::class, 'connection_id');
    }

    public function markAsProcessed(): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'processing_error' => $error,
            'processed_at' => now(),
        ]);
    }
}
