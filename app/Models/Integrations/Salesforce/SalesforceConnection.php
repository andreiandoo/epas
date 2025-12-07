<?php

namespace App\Models\Integrations\Salesforce;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class SalesforceConnection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'org_id', 'instance_url', 'access_token', 'refresh_token',
        'token_expires_at', 'status', 'connected_at', 'last_used_at',
        'last_synced_at', 'metadata',
    ];

    protected $casts = [
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
        return $this->hasMany(SalesforceSyncLog::class, 'connection_id');
    }

    public function fieldMappings(): HasMany
    {
        return $this->hasMany(SalesforceFieldMapping::class, 'connection_id');
    }
}
