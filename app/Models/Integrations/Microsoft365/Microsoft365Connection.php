<?php

namespace App\Models\Integrations\Microsoft365;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Microsoft365Connection extends Model
{
    use SoftDeletes;

    protected $table = 'microsoft365_connections';

    protected $fillable = [
        'tenant_id', 'microsoft_user_id', 'email', 'display_name', 'access_token',
        'refresh_token', 'token_expires_at', 'scopes', 'enabled_services', 'status',
        'connected_at', 'last_used_at', 'metadata',
    ];

    protected $casts = [
        'scopes' => 'array',
        'enabled_services' => 'array',
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

    public function onedriveFiles(): HasMany
    {
        return $this->hasMany(MicrosoftOnedriveFile::class, 'connection_id');
    }

    public function outlookMessages(): HasMany
    {
        return $this->hasMany(MicrosoftOutlookMessage::class, 'connection_id');
    }

    public function teamsMessages(): HasMany
    {
        return $this->hasMany(MicrosoftTeamsMessage::class, 'connection_id');
    }
}
