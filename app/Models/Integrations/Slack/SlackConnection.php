<?php

namespace App\Models\Integrations\Slack;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class SlackConnection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'workspace_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'bot_info',
        'status',
        'connected_at',
        'last_used_at',
        'metadata',
    ];

    protected $casts = [
        'scopes' => 'array',
        'bot_info' => 'array',
        'metadata' => 'array',
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function channels(): HasMany
    {
        return $this->hasMany(SlackChannel::class, 'connection_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SlackMessage::class, 'connection_id');
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(SlackWebhook::class, 'connection_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }
}
