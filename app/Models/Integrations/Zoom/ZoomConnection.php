<?php

namespace App\Models\Integrations\Zoom;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class ZoomConnection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'account_id',
        'user_id',
        'email',
        'display_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'account_type',
        'status',
        'connected_at',
        'last_used_at',
        'metadata',
    ];

    protected $casts = [
        'scopes' => 'array',
        'metadata' => 'array',
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

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

    public function meetings(): HasMany
    {
        return $this->hasMany(ZoomMeeting::class, 'connection_id');
    }

    public function webinars(): HasMany
    {
        return $this->hasMany(ZoomWebinar::class, 'connection_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ZoomParticipant::class, 'connection_id');
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(ZoomRecording::class, 'connection_id');
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(ZoomWebhookEvent::class, 'connection_id');
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
