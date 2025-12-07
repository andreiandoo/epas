<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class PlatformAdAccount extends Model
{
    protected $fillable = [
        'platform',
        'account_id',
        'account_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'pixel_id',
        'conversion_action_ids',
        'settings',
        'is_active',
        'last_sync_at',
        'sync_errors',
    ];

    protected $casts = [
        'conversion_action_ids' => 'array',
        'settings' => 'array',
        'sync_errors' => 'array',
        'is_active' => 'boolean',
        'token_expires_at' => 'datetime',
        'last_sync_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    // Supported platforms
    const PLATFORM_GOOGLE_ADS = 'google_ads';
    const PLATFORM_FACEBOOK = 'facebook';
    const PLATFORM_TIKTOK = 'tiktok';
    const PLATFORM_LINKEDIN = 'linkedin';

    const PLATFORMS = [
        self::PLATFORM_GOOGLE_ADS => 'Google Ads',
        self::PLATFORM_FACEBOOK => 'Facebook/Meta',
        self::PLATFORM_TIKTOK => 'TikTok Ads',
        self::PLATFORM_LINKEDIN => 'LinkedIn Ads',
    ];

    // Encrypt tokens on set
    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    // Decrypt tokens on get
    public function getAccessTokenAttribute($value): ?string
    {
        try {
            return $value ? Crypt::decryptString($value) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getRefreshTokenAttribute($value): ?string
    {
        try {
            return $value ? Crypt::decryptString($value) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(PlatformConversion::class, 'platform_ad_account_id');
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(PlatformAudience::class, 'platform_ad_account_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeGoogle($query)
    {
        return $query->where('platform', self::PLATFORM_GOOGLE_ADS);
    }

    public function scopeFacebook($query)
    {
        return $query->where('platform', self::PLATFORM_FACEBOOK);
    }

    public function scopeTiktok($query)
    {
        return $query->where('platform', self::PLATFORM_TIKTOK);
    }

    public function scopeLinkedin($query)
    {
        return $query->where('platform', self::PLATFORM_LINKEDIN);
    }

    public function scopeNeedsTokenRefresh($query)
    {
        return $query->where('token_expires_at', '<=', now()->addHours(1));
    }

    // Token management
    public function isTokenExpired(): bool
    {
        return $this->token_expires_at && $this->token_expires_at <= now();
    }

    public function isTokenExpiringSoon(): bool
    {
        return $this->token_expires_at && $this->token_expires_at <= now()->addHours(1);
    }

    public function updateTokens(string $accessToken, ?string $refreshToken = null, ?\DateTime $expiresAt = null): void
    {
        $data = ['access_token' => $accessToken];

        if ($refreshToken) {
            $data['refresh_token'] = $refreshToken;
        }

        if ($expiresAt) {
            $data['token_expires_at'] = $expiresAt;
        }

        $this->update($data);
    }

    public function recordSyncSuccess(): void
    {
        $this->update([
            'last_sync_at' => now(),
            'sync_errors' => null,
        ]);
    }

    public function recordSyncError(string $error): void
    {
        $errors = $this->sync_errors ?? [];
        $errors[] = [
            'error' => $error,
            'timestamp' => now()->toIso8601String(),
        ];

        // Keep only last 10 errors
        $errors = array_slice($errors, -10);

        $this->update(['sync_errors' => $errors]);
    }

    // Platform helpers
    public function getPlatformLabel(): string
    {
        return self::PLATFORMS[$this->platform] ?? ucfirst($this->platform);
    }

    public function isGoogle(): bool
    {
        return $this->platform === self::PLATFORM_GOOGLE_ADS;
    }

    public function isFacebook(): bool
    {
        return $this->platform === self::PLATFORM_FACEBOOK;
    }

    public function isTiktok(): bool
    {
        return $this->platform === self::PLATFORM_TIKTOK;
    }

    public function isLinkedin(): bool
    {
        return $this->platform === self::PLATFORM_LINKEDIN;
    }

    // Settings helpers
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    // Conversion action management
    public function addConversionAction(string $actionId): void
    {
        $actions = $this->conversion_action_ids ?? [];
        if (!in_array($actionId, $actions)) {
            $actions[] = $actionId;
            $this->update(['conversion_action_ids' => $actions]);
        }
    }

    public function removeConversionAction(string $actionId): void
    {
        $actions = $this->conversion_action_ids ?? [];
        $actions = array_filter($actions, fn($id) => $id !== $actionId);
        $this->update(['conversion_action_ids' => array_values($actions)]);
    }

    // Static helpers
    public static function getActiveForPlatform(string $platform): ?self
    {
        return static::active()->forPlatform($platform)->first();
    }

    public static function getAllActive(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()->get();
    }
}
