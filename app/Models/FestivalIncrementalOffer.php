<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FestivalIncrementalOffer extends Model
{
    protected $fillable = [
        'tenant_id',
        'offerable_type',
        'offerable_id',
        'tier_name',
        'price_cents',
        'currency',
        'quota',
        'quota_sold',
        'starts_at',
        'ends_at',
        'is_active',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'quota'       => 'integer',
        'quota_sold'  => 'integer',
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'is_active'   => 'boolean',
        'meta'        => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function offerable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getPriceAttribute(): float
    {
        return $this->price_cents / 100;
    }

    /**
     * Check if this tier is currently active and has availability.
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        if ($this->quota && $this->quota_sold >= $this->quota) {
            return false;
        }

        return true;
    }

    /**
     * Check if this tier is sold out.
     */
    public function isSoldOut(): bool
    {
        return $this->quota && $this->quota_sold >= $this->quota;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCurrent($query)
    {
        return $query->active()
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(fn ($q) => $q->whereNull('quota')->orWhereColumn('quota_sold', '<', 'quota'));
    }
}
