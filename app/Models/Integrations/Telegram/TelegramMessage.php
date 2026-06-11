<?php

namespace App\Models\Integrations\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramMessage extends Model
{
    protected $fillable = [
        'connection_id',
        'chat_id',
        'message_id',
        'direction',
        'message_type',
        'content',
        'entities',
        'media',
        'keyboard',
        'reply_to_message_id',
        'status',
        'error_message',
        'correlation_type',
        'correlation_id',
        'sent_at',
    ];

    protected $casts = [
        'chat_id' => 'integer',
        'message_id' => 'integer',
        'entities' => 'array',
        'media' => 'array',
        'keyboard' => 'array',
        'reply_to_message_id' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TelegramBotConnection::class, 'connection_id');
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }
}
