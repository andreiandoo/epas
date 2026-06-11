<?php

namespace App\Models\Integrations\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramChat extends Model
{
    protected $fillable = [
        'connection_id',
        'chat_id',
        'chat_type',
        'title',
        'username',
        'is_active',
        'permissions',
        'metadata',
    ];

    protected $casts = [
        'chat_id' => 'integer',
        'is_active' => 'boolean',
        'permissions' => 'array',
        'metadata' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TelegramBotConnection::class, 'connection_id');
    }

    public function isGroup(): bool
    {
        return in_array($this->chat_type, ['group', 'supergroup']);
    }

    public function isChannel(): bool
    {
        return $this->chat_type === 'channel';
    }

    public function isPrivate(): bool
    {
        return $this->chat_type === 'private';
    }
}
