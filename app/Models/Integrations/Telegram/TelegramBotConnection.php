<?php

namespace App\Models\Integrations\Telegram;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class TelegramBotConnection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'bot_token',
        'bot_username',
        'bot_id',
        'bot_name',
        'webhook_url',
        'webhook_secret',
        'status',
        'commands',
        'metadata',
    ];

    protected $casts = [
        'commands' => 'array',
        'metadata' => 'array',
    ];

    protected $hidden = ['bot_token'];

    public function setBotTokenAttribute($value): void
    {
        $this->attributes['bot_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getBotTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function chats(): HasMany
    {
        return $this->hasMany(TelegramChat::class, 'connection_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class, 'connection_id');
    }

    public function subscribers(): HasMany
    {
        return $this->hasMany(TelegramSubscriber::class, 'connection_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(TelegramUpdate::class, 'connection_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
