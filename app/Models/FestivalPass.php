<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class FestivalPass extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'name',
        'slug',
        'description',
        'pass_type',
        'price_cents',
        'compare_at_price_cents',
        'currency',
        'included_day_ids',
        'included_stage_ids',
        'included_addon_ids',
        'quota_total',
        'quota_sold',
        'sales_start_at',
        'sales_end_at',
        'status',
        'is_refundable',
        'sort_order',
        'perks',
        'meta',
    ];

    protected $casts = [
        'price_cents'             => 'integer',
        'compare_at_price_cents'  => 'integer',
        'included_day_ids'        => 'array',
        'included_stage_ids'      => 'array',
        'included_addon_ids'      => 'array',
        'quota_total'             => 'integer',
        'quota_sold'              => 'integer',
        'sales_start_at'          => 'datetime',
        'sales_end_at'            => 'datetime',
        'is_refundable'           => 'boolean',
        'perks'                   => 'array',
        'meta'                    => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function incrementalOffers(): MorphMany
    {
        return $this->morphMany(FestivalIncrementalOffer::class, 'offerable');
    }

    public function getPriceAttribute(): float
    {
        return $this->price_cents / 100;
    }

    public function isOnSale(): bool
    {
        return $this->compare_at_price_cents && $this->compare_at_price_cents > $this->price_cents;
    }

    public function getDiscountPercentageAttribute(): ?int
    {
        if (!$this->isOnSale()) {
            return null;
        }

        return (int) round(100 - ($this->price_cents / $this->compare_at_price_cents * 100));
    }

    /**
     * Get the current effective price considering incremental offer tiers.
     */
    public function getCurrentPrice(): int
    {
        $activeOffer = $this->incrementalOffers()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(fn ($q) => $q->whereNull('quota')->orWhereColumn('quota_sold', '<', 'quota'))
            ->orderBy('sort_order')
            ->first();

        return $activeOffer?->price_cents ?? $this->price_cents;
    }

    public function isAvailable(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->sales_start_at && $this->sales_start_at->isFuture()) {
            return false;
        }

        if ($this->sales_end_at && $this->sales_end_at->isPast()) {
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
            ->where(fn ($q) => $q->whereNull('sales_start_at')->orWhere('sales_start_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('sales_end_at')->orWhere('sales_end_at', '>=', now()))
            ->where(fn ($q) => $q->whereNull('quota_total')->orWhereColumn('quota_sold', '<', 'quota_total'));
    }
}
