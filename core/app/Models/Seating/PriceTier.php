<?php

namespace App\Models\Seating;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceTier extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'tier_code',
        'currency',
        'price_cents',
        'color',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'currency' => 'USD',
        'color' => '#10B981',
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($tier) {
            if (!$tier->tenant_id && auth()->check() && isset(auth()->user()->tenant_id)) {
                $tier->tenant_id = auth()->user()->tenant_id;
            }
        });
    }

    /**
     * Relationships
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price_cents / 100, 2) . ' ' . $this->currency;
    }

    /**
     * Convert cents to dollars/euros
     */
    public function getPriceAttribute(): float
    {
        return $this->price_cents / 100;
    }
}
