<?php

namespace App\Models\Integrations\HubSpot;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class HubSpotConnection extends Model
{
    use SoftDeletes;

    protected $table = 'hubspot_connections';

    protected $fillable = [
        'tenant_id', 'hub_id', 'hub_domain', 'access_token', 'refresh_token',
        'token_expires_at', 'scopes', 'status', 'connected_at', 'last_used_at',
        'last_synced_at', 'metadata',
    ];

    protected $casts = [
        'scopes' => 'array',
        'metadata' => 'array',
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
        'last_used_at' => 'datetime',
        'last_synced_at' => 'datetime',
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

    public function syncLogs(): HasMany
    {
        return $this->hasMany(HubSpotSyncLog::class, 'connection_id');
    }

    public function propertyMappings(): HasMany
    {
        return $this->hasMany(HubSpotPropertyMapping::class, 'connection_id');
    }
}
