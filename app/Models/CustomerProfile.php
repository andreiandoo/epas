<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    protected $fillable = [
        'tenant_id',
        'customer_id',
        'purchase_count',
        'total_spent_cents',
        'avg_order_cents',
        'first_purchase_at',
        'last_purchase_at',
        'preferred_genres',
        'preferred_event_types',
        'preferred_price_range',
        'preferred_days',
        'attended_events',
        'engagement_score',
        'churn_risk',
        'page_views_30d',
        'cart_adds_30d',
        'email_opens_30d',
        'email_clicks_30d',
        'location_data',
        'last_calculated_at',
    ];

    protected $casts = [
        'preferred_genres' => 'array',
        'preferred_event_types' => 'array',
        'preferred_price_range' => 'array',
        'preferred_days' => 'array',
        'attended_events' => 'array',
        'location_data' => 'array',
        'first_purchase_at' => 'datetime',
        'last_purchase_at' => 'datetime',
        'last_calculated_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeHighEngagement($query, int $minScore = 70)
    {
        return $query->where('engagement_score', '>=', $minScore);
    }

    public function scopeAtRiskOfChurn($query, int $minRisk = 70)
    {
        return $query->where('churn_risk', '>=', $minRisk);
    }

    public function scopeHighValue($query, int $minSpentCents = 10000)
    {
        return $query->where('total_spent_cents', '>=', $minSpentCents);
    }

    public function scopeRecentPurchasers($query, int $days = 90)
    {
        return $query->where('last_purchase_at', '>=', now()->subDays($days));
    }

    public function scopeWithGenrePreference($query, string $genreSlug)
    {
        return $query->whereJsonContains('preferred_genres', ['slug' => $genreSlug]);
    }

    /**
     * Get total spent in the main currency (EUR)
     */
    public function getTotalSpentAttribute(): float
    {
        return $this->total_spent_cents / 100;
    }

    /**
     * Get average order value in the main currency
     */
    public function getAvgOrderAttribute(): float
    {
        return $this->avg_order_cents / 100;
    }

    /**
     * Get days since last purchase
     */
    public function getDaysSinceLastPurchaseAttribute(): ?int
    {
        if (!$this->last_purchase_at) {
            return null;
        }

        return $this->last_purchase_at->diffInDays(now());
    }

    /**
     * Check if customer prefers a specific genre
     */
    public function prefersGenre(string $slug, float $minWeight = 0.3): bool
    {
        $genres = $this->preferred_genres ?? [];

        foreach ($genres as $genre) {
            if (($genre['slug'] ?? '') === $slug && ($genre['weight'] ?? 0) >= $minWeight) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if customer prefers a specific event type
     */
    public function prefersEventType(string $slug, float $minWeight = 0.3): bool
    {
        $types = $this->preferred_event_types ?? [];

        foreach ($types as $type) {
            if (($type['slug'] ?? '') === $slug && ($type['weight'] ?? 0) >= $minWeight) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if price is within customer's preferred range
     */
    public function isPriceInRange(int $priceCents): bool
    {
        $range = $this->preferred_price_range;

        if (!$range) {
            return true; // No preference set
        }

        $min = $range['min'] ?? 0;
        $max = $range['max'] ?? PHP_INT_MAX;

        return $priceCents >= $min && $priceCents <= $max;
    }

    /**
     * Check if customer has attended a specific event
     */
    public function hasAttendedEvent(int $eventId): bool
    {
        return in_array($eventId, $this->attended_events ?? []);
    }
}
