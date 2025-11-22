<?php

namespace App\Repositories;

use App\Models\Seating\EventSeat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SeatInventoryRepository
 *
 * Handles atomic seat status updates and inventory queries
 */
class SeatInventoryRepository
{
    /**
     * Get seats by status
     */
    public function getSeatsByStatus(int $eventSeatingId, string $status): Collection
    {
        return EventSeat::where('event_seating_id', $eventSeatingId)
            ->where('status', $status)
            ->get();
    }

    /**
     * Get all seats with price information
     */
    public function getSeatsWithPricing(int $eventSeatingId): Collection
    {
        return EventSeat::where('event_seating_id', $eventSeatingId)
            ->with('priceTier')
            ->get();
    }

    /**
     * Get specific seats by UIDs
     */
    public function getSeatsByUids(int $eventSeatingId, array $seatUids): Collection
    {
        return EventSeat::where('event_seating_id', $eventSeatingId)
            ->whereIn('seat_uid', $seatUids)
            ->get();
    }

    /**
     * Atomic update of seat status with version check
     *
     * Returns number of rows affected (0 if seats were modified by another process)
     */
    public function atomicUpdateSeatsStatus(
        int $eventSeatingId,
        array $seatUids,
        string $fromStatus,
        string $toStatus
    ): int {
        return EventSeat::where('event_seating_id', $eventSeatingId)
            ->whereIn('seat_uid', $seatUids)
            ->where('status', $fromStatus)
            ->update([
                'status' => $toStatus,
                'version' => DB::raw('version + 1'),
                'last_change_at' => now(),
            ]);
    }

    /**
     * Atomic update with version check (optimistic locking)
     */
    public function atomicUpdateWithVersion(
        int $eventSeatingId,
        string $seatUid,
        int $expectedVersion,
        array $updates
    ): bool {
        $updates['version'] = DB::raw('version + 1');
        $updates['last_change_at'] = now();

        $affected = EventSeat::where('event_seating_id', $eventSeatingId)
            ->where('seat_uid', $seatUid)
            ->where('version', $expectedVersion)
            ->update($updates);

        return $affected > 0;
    }

    /**
     * Get seat count by status
     */
    public function getCountByStatus(int $eventSeatingId, string $status): int
    {
        return EventSeat::where('event_seating_id', $eventSeatingId)
            ->where('status', $status)
            ->count();
    }

    /**
     * Get all status counts in one query
     */
    public function getAllStatusCounts(int $eventSeatingId): array
    {
        $counts = EventSeat::where('event_seating_id', $eventSeatingId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'available' => $counts['available'] ?? 0,
            'held' => $counts['held'] ?? 0,
            'sold' => $counts['sold'] ?? 0,
            'blocked' => $counts['blocked'] ?? 0,
            'disabled' => $counts['disabled'] ?? 0,
        ];
    }

    /**
     * Bulk create seats from geometry
     */
    public function bulkCreateFromGeometry(int $eventSeatingId, array $seatsData): int
    {
        $created = 0;

        foreach (array_chunk($seatsData, 500) as $chunk) {
            EventSeat::insert($chunk);
            $created += count($chunk);
        }

        return $created;
    }

    /**
     * Block/unblock seats
     */
    public function blockSeats(int $eventSeatingId, array $seatUids): int
    {
        return $this->atomicUpdateSeatsStatus($eventSeatingId, $seatUids, 'available', 'blocked');
    }

    public function unblockSeats(int $eventSeatingId, array $seatUids): int
    {
        return $this->atomicUpdateSeatsStatus($eventSeatingId, $seatUids, 'blocked', 'available');
    }

    /**
     * Get seats modified after a specific timestamp (for polling)
     */
    public function getSeatsModifiedAfter(int $eventSeatingId, \DateTime $since): Collection
    {
        return EventSeat::where('event_seating_id', $eventSeatingId)
            ->where('last_change_at', '>', $since)
            ->get();
    }

    /**
     * Initialize EventSeat records from EventSeatingLayout geometry
     *
     * Creates all seat records from the JSONB geometry snapshot
     */
    public function initializeEventSeats(int $eventSeatingId): int
    {
        $eventLayout = \App\Models\Seating\EventSeatingLayout::find($eventSeatingId);

        if (!$eventLayout) {
            throw new \Exception("EventSeatingLayout {$eventSeatingId} not found");
        }

        // Check if seats already exist
        $existingCount = EventSeat::where('event_seating_id', $eventSeatingId)->count();

        if ($existingCount > 0) {
            throw new \Exception("Seats already exist for this event seating layout. Delete existing seats first.");
        }

        $geometry = $eventLayout->geometry;

        if (!$geometry || !isset($geometry['sections'])) {
            throw new \Exception("No geometry data found. Generate a snapshot first.");
        }

        $seatsData = [];
        $now = now();

        foreach ($geometry['sections'] as $section) {
            foreach ($section['rows'] ?? [] as $row) {
                foreach ($row['seats'] ?? [] as $seat) {
                    $seatsData[] = [
                        'event_seating_id' => $eventSeatingId,
                        'tenant_id' => $eventLayout->tenant_id,
                        'seat_uid' => $seat['seat_uid'],
                        'section_code' => $section['section_code'],
                        'row_label' => $row['row_label'],
                        'seat_number' => $seat['seat_number'],
                        'price_tier_id' => $seat['price_tier_id'] ?? null,
                        'price_cents_override' => $seat['price_cents_override'] ?? null,
                        'status' => 'available',
                        'version' => 1,
                        'last_change_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        return $this->bulkCreateFromGeometry($eventSeatingId, $seatsData);
    }
}
