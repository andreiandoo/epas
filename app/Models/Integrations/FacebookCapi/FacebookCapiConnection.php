<?php

namespace App\Models\Integrations\FacebookCapi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class FacebookCapiConnection extends Model
{
    use SoftDeletes;

    protected $table = 'facebook_capi_connections';

    protected $fillable = [
        'tenant_id',
        'marketplace_client_id',
        'marketplace_organizer_id',
        'pixel_id',
        'access_token',
        'business_id',
        'ad_account_id',
        'test_mode',
        'test_event_code',
        'status',
        'enabled_events',
        'last_event_at',
        'metadata',
    ];

    protected $casts = [
        'test_mode' => 'boolean',
        'enabled_events' => 'array',
        'metadata' => 'array',
        'last_event_at' => 'datetime',
    ];

    protected $hidden = ['access_token'];

    public function setAccessTokenAttribute($value): void
    {
        if (!$value) {
            $this->attributes['access_token'] = null;
            return;
        }
        try {
            Crypt::decryptString($value);
            $this->attributes['access_token'] = $value;
        } catch (\Throwable $e) {
            $this->attributes['access_token'] = Crypt::encryptString($value);
        }
    }

    public function getAccessTokenAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }

    public function eventConfigs(): HasMany
    {
        return $this->hasMany(FacebookCapiEventConfig::class, 'connection_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(FacebookCapiEvent::class, 'connection_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(FacebookCapiBatch::class, 'connection_id');
    }

    public function customAudiences(): HasMany
    {
        return $this->hasMany(FacebookCapiCustomAudience::class, 'connection_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTestMode(): bool
    {
        return $this->test_mode;
    }

    public function isEventEnabled(string $eventName): bool
    {
        return in_array($eventName, $this->enabled_events ?? []);
    }
}
