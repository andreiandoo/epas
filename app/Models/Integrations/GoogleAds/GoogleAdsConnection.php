<?php

namespace App\Models\Integrations\GoogleAds;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class GoogleAdsConnection extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'refresh_token',
        'access_token',
        'token_expires_at',
        'conversion_action_id',
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
        'refresh_token',
        'access_token',
    ];

    // Encrypt refresh token
    public function setRefreshTokenAttribute($value): void
    {
        $this->attributes['refresh_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getRefreshTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // Encrypt access token
    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAttribute($value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // Format customer ID with dashes
    public function getFormattedCustomerIdAttribute(): string
    {
        $id = $this->customer_id;
        if (strlen($id) === 10) {
            return substr($id, 0, 3) . '-' . substr($id, 3, 3) . '-' . substr($id, 6, 4);
        }
        return $id;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function conversionActions(): HasMany
    {
        return $this->hasMany(GoogleAdsConversionAction::class, 'connection_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(GoogleAdsConversion::class, 'connection_id');
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(GoogleAdsAudience::class, 'connection_id');
    }

    public function uploadBatches(): HasMany
    {
        return $this->hasMany(GoogleAdsUploadBatch::class, 'connection_id');
    }

    public function primaryConversionAction(): ?GoogleAdsConversionAction
    {
        return $this->conversionActions()->where('is_primary', true)->first();
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
