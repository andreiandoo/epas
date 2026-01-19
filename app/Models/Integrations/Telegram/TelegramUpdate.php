<?php

namespace App\Models\Integrations\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramUpdate extends Model
{
    protected $fillable = [
        'connection_id',
        'update_id',
        'update_type',
        'payload',
        'status',
        'processing_error',
        'processed_at',
    ];

    protected $casts = [
        'update_id' => 'integer',
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TelegramBotConnection::class, 'connection_id');
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
