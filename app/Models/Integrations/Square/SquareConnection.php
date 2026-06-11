<?php

namespace App\Models\Integrations\Square;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class SquareConnection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'merchant_id',
        'business_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'environment',
        'location_ids',
        'status',
        'connected_at',
        'last_used_at',
        'metadata',
    ];

    protected $casts = [
        'location_ids' => 'array',
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

    public function locations(): HasMany
    {
        return $this->hasMany(SquareLocation::class, 'connection_id');
    }

    public function catalogItems(): HasMany
    {
        return $this->hasMany(SquareCatalogItem::class, 'connection_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SquareOrder::class, 'connection_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SquarePayment::class, 'connection_id');
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(SquareWebhook::class, 'connection_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }
}
