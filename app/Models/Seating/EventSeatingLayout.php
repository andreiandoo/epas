<?php

namespace App\Models\Seating;

use App\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSeatingLayout extends Model
{
    protected $fillable = [
        'event_id',
        'layout_id',
        'json_geometry',
        'status',
        'published_at',
        'archived_at',
        'notes',
    ];

    protected $casts = [
        'json_geometry' => 'array',
        'notes' => 'array',
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sourceLayout(): BelongsTo
    {
        return $this->belongsTo(SeatingLayout::class, 'layout_id');
    }

    public function baseLayout(): BelongsTo
    {
        return $this->belongsTo(SeatingLayout::class, 'layout_id');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(EventSeat::class, 'event_seating_id');
    }

    public function eventSeats(): HasMany
    {
        return $this->hasMany(EventSeat::class, 'event_seating_id');
    }

    public function holds(): HasMany
    {
        return $this->hasMany(SeatHold::class, 'event_seating_id');
    }

    public function priceOverrides(): HasMany
    {
        return $this->hasMany(DynamicPriceOverride::class, 'event_seating_id');
    }

    /**
     * Scopes
     */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * Check if published
     */
    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * Publish the snapshot
     */
    public function publish(): bool
    {
        $this->published_at = now();
        return $this->save();
    }

    /**
     * Get seat count by status
     */
    public function getSeatCountByStatus(string $status): int
    {
        return $this->seats()->where('status', $status)->count();
    }

    /**
     * Get all seat counts
     */
    public function getSeatStatusCounts(): array
    {
        $available = $this->getSeatCountByStatus('available');
        $held = $this->getSeatCountByStatus('held');
        $sold = $this->getSeatCountByStatus('sold');
        $blocked = $this->getSeatCountByStatus('blocked');
        $disabled = $this->getSeatCountByStatus('disabled');

        return [
            'total' => $available + $held + $sold + $blocked + $disabled,
            'available' => $available,
            'held' => $held,
            'sold' => $sold,
            'blocked' => $blocked,
            'disabled' => $disabled,
        ];
    }
}
