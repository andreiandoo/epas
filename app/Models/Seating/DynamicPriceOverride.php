<?php

namespace App\Models\Seating;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicPriceOverride extends Model
{
    protected $fillable = [
        'event_seating_id',
        'seat_uid',
        'section_ref',
        'row_ref',
        'price_cents',
        'source_rule_id',
        'effective_from',
        'effective_to',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function eventSeating(): BelongsTo
    {
        return $this->belongsTo(EventSeatingLayout::class, 'event_seating_id');
    }

    public function sourceRule(): BelongsTo
    {
        return $this->belongsTo(DynamicPricingRule::class, 'source_rule_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', now());
            });
    }

    public function scopeForSeat($query, string $seatUid)
    {
        return $query->where('seat_uid', $seatUid);
    }

    public function scopeForSection($query, string $sectionRef)
    {
        return $query->where('section_ref', $sectionRef);
    }

    public function scopeForRow($query, string $rowRef)
    {
        return $query->where('row_ref', $rowRef);
    }

    /**
     * Check if override is currently active
     */
    public function isActive(): bool
    {
        $now = now();

        if ($this->effective_from > $now) {
            return false;
        }

        if ($this->effective_to !== null && $this->effective_to < $now) {
            return false;
        }

        return true;
    }
}
