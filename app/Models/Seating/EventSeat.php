<?php

namespace App\Models\Seating;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSeat extends Model
{
    protected $fillable = [
        'event_seating_id',
        'seat_uid',
        'section_name',
        'row_label',
        'seat_label',
        'price_tier_id',
        'price_cents_override',
        'status',
        'version',
        'last_change_at',
    ];

    protected $casts = [
        'price_cents_override' => 'integer',
        'version' => 'integer',
        'last_change_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'available',
        'version' => 1,
    ];

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        static::creating(function ($seat) {
            $seat->last_change_at = now();
        });

        static::updating(function ($seat) {
            $seat->last_change_at = now();
        });
    }

    /**
     * Relationships
     */
    public function eventSeating(): BelongsTo
    {
        return $this->belongsTo(EventSeatingLayout::class, 'event_seating_id');
    }

    public function priceTier(): BelongsTo
    {
        return $this->belongsTo(PriceTier::class);
    }

    /**
     * Scopes
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeHeld($query)
    {
        return $query->where('status', 'held');
    }

    public function scopeSold($query)
    {
        return $query->where('status', 'sold');
    }

    public function scopeBlocked($query)
    {
        return $query->where('status', 'blocked');
    }

    /**
     * Status checks
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isHeld(): bool
    {
        return $this->status === 'held';
    }

    public function isSold(): bool
    {
        return $this->status === 'sold';
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    /**
     * Get effective price (override or tier price)
     */
    public function getEffectivePriceCents(): int
    {
        return $this->price_cents_override ?? $this->priceTier?->price_cents ?? 0;
    }

    /**
     * Get formatted location
     */
    public function getLocationAttribute(): string
    {
        $parts = array_filter([$this->section_name, $this->row_label, $this->seat_label]);
        return implode(' - ', $parts);
    }
}
