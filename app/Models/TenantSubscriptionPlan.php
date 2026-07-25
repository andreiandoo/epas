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
        'shows_included',
        'tickets_included',
        'seat_mode',
        'allowed_sections',
        'validity_mode',
        'valid_from',
        'valid_until',
        'priority_access',
        'benefits',
        'description',
        'image',
        'is_featured',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'benefits'         => 'array',
        'allowed_sections' => 'array',
        'is_featured'      => 'boolean',
        'is_active'        => 'boolean',
        'priority_access'  => 'boolean',
        'price_cents'      => 'integer',
        'shows_included'   => 'integer',
        'tickets_included' => 'integer',
        'sort_order'       => 'integer',
        'valid_from'       => 'date',
        'valid_until'      => 'date',
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
