<?php

namespace App\Services\Leisure;

use App\Models\Leisure\TicketTypeCapacity;
use App\Models\TicketType;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Read + reserve/confirm/release operations on TicketTypeCapacity rows.
 *
 * Reservation lifecycle:
 *   reserve(N)   → reserved += N        (cart add)
 *   confirm(N)   → reserved -= N; sold += N   (payment complete)
 *   release(N)   → reserved -= N        (cart abandon / expire)
 *
 * All mutating operations take a row-level lock to prevent oversell under
 * concurrent checkout.
 */
class CapacityAvailabilityService
{
    public function __construct(
        private LeisurePricingResolver $pricing,
    ) {}

    /**
     * Aggregate availability per date for a month, used by calendar pickers.
     * If $ticketTypeId is null, aggregates across all tickets of the tenant.
     *
     * @return array<string, array{status: string, remaining: int, min_price_cents: ?int, slot_count: int}>
     */
    public function getAvailabilityForMonth(
        int $tenantId,
        DateTimeInterface $monthStart,
        ?int $ticketTypeId = null,
    ): array {
        $start = CarbonImmutable::instance($monthStart)->startOfMonth();
        $end = $start->endOfMonth();

        $rows = TicketTypeCapacity::query()
            ->where('tenant_id', $tenantId)
            ->when($ticketTypeId, fn ($q) => $q->where('ticket_type_id', $ticketTypeId))
            ->whereBetween('capacity_date', [$start->toDateString(), $end->toDateString()])
            ->with('ticketType:id,price_cents,leisure_pricing_rules,leisure_seasons')
            ->get();

        $byDate = $rows->groupBy(fn ($r) => $r->capacity_date->toDateString());

        $result = [];
        foreach ($byDate as $date => $items) {
            $totalRemaining = (int) $items->sum(fn ($r) => $r->remaining);
            $totalCapacity = (int) $items->sum('capacity');
            $allClosed = $items->every(fn ($r) => $r->is_closed);

            $minPrice = null;
            foreach ($items as $row) {
                if ($row->price_override_cents !== null) {
                    $price = $row->price_override_cents;
                } elseif ($row->ticketType !== null) {
                    $price = $this->pricing->resolvePrice(
                        $row->ticketType,
                        CarbonImmutable::parse($date),
                    );
                } else {
                    continue;
                }
                if ($minPrice === null || $price < $minPrice) {
                    $minPrice = $price;
                }
            }

            $result[$date] = [
                'status' => $this->aggregateStatus($totalRemaining, $totalCapacity, $allClosed),
                'remaining' => $totalRemaining,
                'min_price_cents' => $minPrice,
                'slot_count' => $items->whereNotNull('time_slot_start')->count(),
            ];
        }

        return $result;
    }

    /**
     * Detailed per-slot availability for a single date — picker view inside a day.
     *
     * @return array<int, array{id:int, time_slot_start:?string, time_slot_end:?string, status:string, remaining:int, price_cents:int}>
     */
    public function getSlotsForDate(
        int $tenantId,
        int $ticketTypeId,
        DateTimeInterface $date,
    ): array {
        $rows = TicketTypeCapacity::query()
            ->where('tenant_id', $tenantId)
            ->where('ticket_type_id', $ticketTypeId)
            ->whereDate('capacity_date', $date)
            ->orderBy('time_slot_start')
            ->with('ticketType:id,price_cents,leisure_pricing_rules,leisure_seasons')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $price = $row->price_override_cents
                ?? ($row->ticketType
                    ? $this->pricing->resolvePrice($row->ticketType, CarbonImmutable::instance($date))
                    : 0);

            $out[] = [
                'id' => $row->id,
                'time_slot_start' => $row->time_slot_start?->format('H:i'),
                'time_slot_end' => $row->time_slot_end?->format('H:i'),
                'status' => $row->status,
                'remaining' => $row->remaining,
                'price_cents' => $price,
            ];
        }
        return $out;
    }

    /**
     * Reserve $quantity slots on $capacityId. Returns true on success, false
     * if not enough remaining. Uses pessimistic row lock to avoid oversell.
     */
    public function reserve(int $capacityId, int $quantity): bool
    {
        if ($quantity <= 0) {
            throw new RuntimeException('Quantity must be > 0');
        }

        return DB::transaction(function () use ($capacityId, $quantity) {
            $row = TicketTypeCapacity::lockForUpdate()->find($capacityId);
            if (! $row || $row->is_closed) {
                return false;
            }
            if ($row->remaining < $quantity) {
                return false;
            }
            $row->increment('reserved', $quantity);
            return true;
        });
    }

    /**
     * Confirm a reservation (called after payment success): move from reserved → sold.
     */
    public function confirm(int $capacityId, int $quantity): void
    {
        DB::transaction(function () use ($capacityId, $quantity) {
            $row = TicketTypeCapacity::lockForUpdate()->findOrFail($capacityId);
            $reserved = (int) $row->reserved;
            $take = min($reserved, $quantity);
            if ($take > 0) {
                $row->decrement('reserved', $take);
            }
            $row->increment('sold', $quantity);
        });
    }

    /**
     * Release a previously-reserved quantity back to the pool (cart abandon).
     */
    public function release(int $capacityId, int $quantity): void
    {
        DB::transaction(function () use ($capacityId, $quantity) {
            $row = TicketTypeCapacity::lockForUpdate()->findOrFail($capacityId);
            $current = (int) $row->reserved;
            $release = min($current, $quantity);
            if ($release > 0) {
                $row->decrement('reserved', $release);
            }
        });
    }

    protected function aggregateStatus(int $remaining, int $capacity, bool $allClosed): string
    {
        if ($allClosed) {
            return 'closed';
        }
        if ($capacity <= 0) {
            return 'unavailable';
        }
        if ($remaining === 0) {
            return 'sold_out';
        }
        if ($remaining < ($capacity * 0.2)) {
            return 'limited';
        }
        return 'available';
    }
}
