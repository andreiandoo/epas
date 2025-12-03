<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRecommendation extends Model
{
    protected $fillable = [
        'tenant_id',
        'event_id',
        'customer_id',
        'match_score',
        'match_reasons',
        'notified_via',
        'notified_at',
        'clicked_at',
        'converted_at',
        'order_id',
    ];

    protected $casts = [
        'match_reasons' => 'array',
        'notified_via' => 'array',
        'notified_at' => 'datetime',
        'clicked_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeHighMatch($query, int $minScore = 70)
    {
        return $query->where('match_score', '>=', $minScore);
    }

    public function scopeNotNotified($query)
    {
        return $query->whereNull('notified_at');
    }

    public function scopeNotified($query)
    {
        return $query->whereNotNull('notified_at');
    }

    public function scopeConverted($query)
    {
        return $query->whereNotNull('converted_at');
    }

    public function scopeNotConverted($query)
    {
        return $query->whereNull('converted_at');
    }

    /**
     * Check if customer has been notified
     */
    public function isNotified(): bool
    {
        return $this->notified_at !== null;
    }

    /**
     * Check if recommendation led to a conversion
     */
    public function isConverted(): bool
    {
        return $this->converted_at !== null;
    }

    /**
     * Check if customer clicked on the recommendation
     */
    public function wasClicked(): bool
    {
        return $this->clicked_at !== null;
    }

    /**
     * Mark as notified via a specific channel
     */
    public function markNotified(string $channel): void
    {
        $channels = $this->notified_via ?? [];

        if (!in_array($channel, $channels)) {
            $channels[] = $channel;
        }

        $this->update([
            'notified_via' => $channels,
            'notified_at' => $this->notified_at ?? now(),
        ]);
    }

    /**
     * Mark as clicked
     */
    public function markClicked(): void
    {
        if (!$this->clicked_at) {
            $this->update(['clicked_at' => now()]);
        }
    }

    /**
     * Mark as converted
     */
    public function markConverted(int $orderId): void
    {
        $this->update([
            'converted_at' => now(),
            'order_id' => $orderId,
        ]);
    }

    /**
     * Get the top match reason
     */
    public function getTopMatchReasonAttribute(): ?string
    {
        $reasons = $this->match_reasons ?? [];

        if (empty($reasons)) {
            return null;
        }

        usort($reasons, fn ($a, $b) => ($b['weight'] ?? 0) - ($a['weight'] ?? 0));

        return $reasons[0]['reason'] ?? null;
    }

    /**
     * Get match reasons as human-readable strings
     */
    public function getMatchReasonsDisplayAttribute(): array
    {
        $displays = [];
        $reasons = $this->match_reasons ?? [];

        foreach ($reasons as $reason) {
            $displays[] = match ($reason['reason'] ?? '') {
                'genre_match' => 'Matches preferred genre',
                'type_match' => 'Matches preferred event type',
                'artist_match' => 'Matches liked artist',
                'price_fit' => 'Price within usual range',
                'location_proximity' => 'Near preferred location',
                'similar_event' => 'Similar to past attendance',
                'high_engagement' => 'Highly engaged customer',
                'watchlist' => 'Event is on watchlist',
                default => ucfirst(str_replace('_', ' ', $reason['reason'] ?? '')),
            };
        }

        return $displays;
    }
}
