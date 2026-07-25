<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscriptionPlan extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'subtitle',
        'price_cents',
        'currency',
        'benefits',
        'description',
        'image',
        'is_featured',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'benefits'    => 'array',
        'is_featured' => 'boolean',
        'is_active'   => 'boolean',
        'price_cents' => 'integer',
        'sort_order'  => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getPriceAttribute(): float
    {
        return ($this->price_cents ?? 0) / 100;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
