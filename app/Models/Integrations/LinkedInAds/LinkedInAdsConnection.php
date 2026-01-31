<?php

namespace App\Models\Integrations\LinkedInAds;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class LinkedInAdsConnection extends Model
{
    protected $fillable = [
        'tenant_id',
        'ad_account_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'insight_tag_id',
        'status',
        'enabled_conversions',
        'last_event_at',
        'metadata',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_event_at' => 'datetime',
        'enabled_conversions' => 'array',
        'metadata' => 'array',
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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function conversionRules(): HasMany
    {
        return $this->hasMany(LinkedInAdsConversionRule::class, 'connection_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(LinkedInAdsConversion::class, 'connection_id');
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(LinkedInAdsAudience::class, 'connection_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(LinkedInAdsBatch::class, 'connection_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
