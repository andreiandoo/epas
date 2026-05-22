<?php

namespace App\Models;

use App\Support\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Microservice extends Model
{
    use Translatable;

    /**
     * Translatable fields
     */
    public array $translatable = ['name', 'description', 'short_description', 'features'];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'icon',
        'icon_image',
        'public_image',
        'price',
        'currency',
        'billing_cycle',
        'pricing_model',
        'is_active',
        'is_premium',
        'sort_order',
        'features',
        'category',
        'version',
        'config_schema',
        'required_env_vars',
        'dependencies',
        'documentation_url',
        'metadata',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'short_description' => 'array',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_premium' => 'boolean',
        'features' => 'array',
        'config_schema' => 'array',
        'required_env_vars' => 'array',
        'dependencies' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Flat string version of the translatable `name` field. Filament 4's
     * Table::getRecordTitle() enforces a `string` return type, so binding
     * `$recordTitleAttribute` directly to `name` (which is `array` cast)
     * throws a TypeError on AttachAction option lists and similar. This
     * accessor gives Filament + any other consumer a safe string fallback.
     *
     * Resolution order: Romanian (canonical for this project) → English →
     * first non-empty translation → empty string.
     */
    public function getDisplayLabelAttribute(): string
    {
        $name = $this->getAttributeFromArray('name');
        if (is_string($name)) {
            $decoded = json_decode($name, true);
            $name = is_array($decoded) ? $decoded : $name;
        }
        if (is_array($name)) {
            foreach (['ro', 'en'] as $locale) {
                if (!empty($name[$locale])) {
                    return (string) $name[$locale];
                }
            }
            $first = collect($name)->filter()->first();
            return $first !== null ? (string) $first : '';
        }
        return (string) ($name ?? '');
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_microservices')
            ->withPivot(['status', 'activated_at', 'expires_at', 'settings', 'usage_stats'])
            ->withTimestamps();
    }

    public function tenantMicroservices(): HasMany
    {
        return $this->hasMany(TenantMicroservice::class);
    }

    public function marketplaceClients(): BelongsToMany
    {
        return $this->belongsToMany(MarketplaceClient::class, 'marketplace_client_microservices')
            ->withPivot(['status', 'is_active', 'activated_at', 'expires_at', 'settings', 'usage_stats', 'is_default', 'sort_order'])
            ->withTimestamps();
    }

    public function marketplaceClientMicroservices(): HasMany
    {
        return $this->hasMany(MarketplaceClientMicroservice::class);
    }

    public function artistAccounts(): BelongsToMany
    {
        return $this->belongsToMany(
            MarketplaceArtistAccount::class,
            'marketplace_artist_account_microservices'
        )
            ->withPivot([
                'status',
                'granted_by',
                'granted_by_user_id',
                'service_order_id',
                'activated_at',
                'trial_ends_at',
                'expires_at',
                'cancelled_at',
                'settings',
            ])
            ->withTimestamps();
    }

    public function artistAccountMicroservices(): HasMany
    {
        return $this->hasMany(MarketplaceArtistAccountMicroservice::class);
    }

    /**
     * Scope pentru microservicii active
     */
    public function scopeActive($query)
    {
        return $query->where('microservices.is_active', true)->orderBy('sort_order');
    }
}
