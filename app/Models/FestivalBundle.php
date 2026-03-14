<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FestivalBundle extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'name',
        'slug',
        'description',
        'bundle_price_cents',
        'original_price_cents',
        'currency',
        'items',
        'quota_total',
        'quota_sold',
        'available_from',
        'available_until',
        'status',
        'image_url',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'bundle_price_cents'   => 'integer',
        'original_price_cents' => 'integer',
        'items'                => 'array',
        'quota_total'          => 'integer',
        'quota_sold'           => 'integer',
        'available_from'       => 'datetime',
        'available_until'      => 'datetime',
        'meta'                 => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function getBundlePriceAttribute(): float
    {
        return $this->bundle_price_cents / 100;
    }

    public function getOriginalPriceAttribute(): float
    {
        return $this->original_price_cents / 100;
    }

    public function getSavingsAttribute(): float
    {
        return ($this->original_price_cents - $this->bundle_price_cents) / 100;
    }

    public function getSavingsPercentageAttribute(): int
    {
        if ($this->original_price_cents <= 0) {
            return 0;
        }

        return (int) round(100 - ($this->bundle_price_cents / $this->original_price_cents * 100));
    }

    public function isAvailable(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->available_from && $this->available_from->isFuture()) {
            return false;
        }

        if ($this->available_until && $this->available_until->isPast()) {
            return false;
        }

        if ($this->quota_total && $this->quota_sold >= $this->quota_total) {
            return false;
        }

        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAvailable($query)
    {
        return $query->active()
            ->where(fn ($q) => $q->whereNull('available_from')->orWhere('available_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('available_until')->orWhere('available_until', '>=', now()))
            ->where(fn ($q) => $q->whereNull('quota_total')->orWhereColumn('quota_sold', '<', 'quota_total'));
    }
}
