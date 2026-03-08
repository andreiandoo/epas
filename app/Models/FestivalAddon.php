<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class FestivalAddon extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'name',
        'slug',
        'description',
        'category',
        'addon_type',
        'price_cents',
        'compare_at_price_cents',
        'currency',
        'quota_total',
        'quota_sold',
        'max_per_order',
        'options',
        'included_day_ids',
        'image_url',
        'status',
        'requires_pass',
        'sort_order',
        'meta',
    ];

    protected $casts = [
        'price_cents'            => 'integer',
        'compare_at_price_cents' => 'integer',
        'quota_total'            => 'integer',
        'quota_sold'             => 'integer',
        'max_per_order'          => 'integer',
        'options'                => 'array',
        'included_day_ids'       => 'array',
        'requires_pass'          => 'boolean',
        'meta'                   => 'array',
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

    public function isAvailable(): bool
    {
        if ($this->status !== 'active') {
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

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get all available addon categories with labels.
     */
    public static function categoryLabels(): array
    {
        return [
            'camping'      => 'Camping',
            'parking'      => 'Parking',
            'accommodation'=> 'Accommodation',
            'food'         => 'Food & Drink',
            'merch'        => 'Merchandise',
            'transport'    => 'Transport / Shuttle',
            'locker'       => 'Lockers',
            'shower'       => 'Showers & Hygiene',
            'vip_upgrade'  => 'VIP Upgrade',
            'experience'   => 'Experiences',
        ];
    }
}
