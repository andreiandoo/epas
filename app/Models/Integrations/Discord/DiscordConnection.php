<?php

namespace App\Models\Integrations\Discord;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class DiscordConnection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'guild_id', 'guild_name', 'access_token', 'refresh_token',
        'token_expires_at', 'bot_token', 'scopes', 'status', 'connected_at',
        'last_used_at', 'metadata',
    ];

    protected $casts = [
        'scopes' => 'array',
        'metadata' => 'array',
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = ['access_token', 'refresh_token', 'bot_token'];

    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setBotTokenAttribute($value): void
    {
        $this->attributes['bot_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getBotTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(DiscordWebhook::class, 'connection_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DiscordMessage::class, 'connection_id');
    }
}
