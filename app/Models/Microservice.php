<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Microservice extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'icon',
        'icon_image',
        'public_image',
        'price',
        'pricing_model',
        'is_active',
        'sort_order',
        'features',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'features' => 'array',
    ];

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_microservice')
            ->withPivot(['is_active', 'activated_at', 'expires_at', 'configuration'])
            ->withTimestamps();
    }

    /**
     * Scope pentru microservicii active
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
