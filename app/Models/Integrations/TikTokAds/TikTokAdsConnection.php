<?php

namespace App\Models\Integrations\TikTokAds;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class TikTokAdsConnection extends Model
{
    protected $fillable = [
        'tenant_id',
        'pixel_id',
        'access_token',
        'advertiser_id',
        'test_mode',
        'test_event_code',
        'status',
        'enabled_events',
        'last_event_at',
        'metadata',
    ];

    protected $casts = [
        'test_mode' => 'boolean',
        'last_event_at' => 'datetime',
        'enabled_events' => 'array',
        'metadata' => 'array',
    ];

    protected $hidden = [
        'access_token',
    ];

    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function eventConfigs(): HasMany
    {
        return $this->hasMany(TikTokAdsEventConfig::class, 'connection_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TikTokAdsEvent::class, 'connection_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(TikTokAdsBatch::class, 'connection_id');
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(TikTokAdsAudience::class, 'connection_id');
    }

    public function isTestMode(): bool
    {
        return $this->test_mode === true;
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
