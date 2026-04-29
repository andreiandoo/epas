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

    /**
     * Scope pentru microservicii active
     */
    public function scopeActive($query)
    {
        return $query->where('microservices.is_active', true)->orderBy('sort_order');
    }
}
