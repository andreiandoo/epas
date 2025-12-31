<?php

namespace App\Models\Integrations\Jira;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class JiraConnection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'cloud_id', 'site_url', 'access_token', 'refresh_token',
        'token_expires_at', 'scopes', 'accessible_resources', 'status',
        'connected_at', 'last_used_at', 'metadata',
    ];

    protected $casts = [
        'scopes' => 'array',
        'accessible_resources' => 'array',
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

    public function projects(): HasMany
    {
        return $this->hasMany(JiraProject::class, 'connection_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(JiraIssue::class, 'connection_id');
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(JiraWebhook::class, 'connection_id');
    }
}
