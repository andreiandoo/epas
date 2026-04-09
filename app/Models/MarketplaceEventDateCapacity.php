<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceEventDateCapacity extends Model
{
    protected $fillable = [
        'marketplace_event_id',
        'marketplace_ticket_type_id',
        'visit_date',
        'capacity',
        'sold',
        'reserved',
        'is_closed',
        'price_override',
        'notes',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'is_closed' => 'boolean',
        'price_override' => 'decimal:2',
    ];

    // =========================================
    // Relationships
    // =========================================

    public function event(): BelongsTo
    {
        return $this->belongsTo(MarketplaceEvent::class, 'marketplace_event_id');
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(MarketplaceTicketType::class, 'marketplace_ticket_type_id');
    }

    // =========================================
    // Computed
    // =========================================

    public function getAvailableAttribute(): int
    {
        return max(0, $this->capacity - $this->sold - $this->reserved);
    }

    public function isAvailable(): bool
    {
        return !$this->is_closed && $this->available > 0;
    }

    // =========================================
    // Static helpers
    // =========================================

    /**
     * Get or create a capacity row for a specific date + ticket type.
     * Uses the ticket type's daily_capacity as default.
     */
    public static function getOrCreate(int $eventId, int $ticketTypeId, string $date, int $defaultCapacity): self
    {
        return self::firstOrCreate(
            [
                'marketplace_event_id' => $eventId,
                'marketplace_ticket_type_id' => $ticketTypeId,
                'visit_date' => $date,
            ],
            [
                'capacity' => $defaultCapacity,
                'sold' => 0,
                'reserved' => 0,
            ]
        );
    }

    /**
     * Get availability summary for a date range (used by calendar month view).
     * Returns array keyed by date string.
     */
    public static function getAvailabilitySummary(int $eventId, string $startDate, string $endDate): array
    {
        return self::where('marketplace_event_id', $eventId)
            ->whereBetween('visit_date', [$startDate, $endDate])
            ->get()
            ->groupBy(fn ($row) => $row->visit_date->format('Y-m-d'))
            ->map(function ($rows) {
                $totalCapacity = $rows->sum('capacity');
                $totalSold = $rows->sum('sold') + $rows->sum('reserved');
                $available = max(0, $totalCapacity - $totalSold);
                $isClosed = $rows->every(fn ($r) => $r->is_closed);

                if ($isClosed) return 'closed';
                if ($available <= 0) return 'sold_out';
                if ($totalCapacity > 0 && ($available / $totalCapacity) < 0.3) return 'limited';
                return 'available';
            })
            ->toArray();
    }
}
