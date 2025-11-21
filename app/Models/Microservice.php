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
    public array $translatable = ['name', 'description', 'short_description'];

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
        'sort_order',
        'features',
        'category',
        'documentation_url',
        'metadata',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'short_description' => 'array',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'features' => 'array',
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

    /**
     * Scope pentru microservicii active
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
