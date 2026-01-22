<?php

namespace App\Services\Seating;

use App\Models\MarketplaceEvent;
use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\EventSeat;
use App\Models\Seating\SeatingLayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MarketplaceEventSeatingService
 *
 * Manages per-event seating instances for marketplace events.
 * Creates EventSeatingLayout and EventSeat records from SeatingLayout templates.
 */
class MarketplaceEventSeatingService
{
    public function __construct(
        private GeometryStorage $geometry
    ) {}

    /**
     * Get or create EventSeatingLayout for a marketplace event
     *
     * @param int $marketplaceEventId
     * @return EventSeatingLayout|null
     */
    public function getOrCreateEventSeating(int $marketplaceEventId): ?EventSeatingLayout
    {
        // Check if event seating already exists
        $existing = EventSeatingLayout::where('marketplace_event_id', $marketplaceEventId)
            ->published()
            ->first();

        if ($existing) {
            return $existing;
        }

        // Get marketplace event with venue and seating layout
        $event = MarketplaceEvent::with(['venue.seatingLayouts' => function ($q) {
            $q->where('status', 'published')->with(['sections.rows.seats']);
        }])->find($marketplaceEventId);

        if (!$event || !$event->venue) {
            return null;
        }

        $layout = $event->venue->seatingLayouts->first();
        if (!$layout) {
            return null;
        }

        // Create the event seating
        return $this->createEventSeatingFromLayout($event, $layout);
    }

    /**
     * Create EventSeatingLayout and EventSeat records from a SeatingLayout template
     *
     * @param MarketplaceEvent $event
     * @param SeatingLayout $layout
     * @return EventSeatingLayout
     */
    protected function createEventSeatingFromLayout(MarketplaceEvent $event, SeatingLayout $layout): EventSeatingLayout
    {
        return DB::transaction(function () use ($event, $layout) {
            // Generate geometry snapshot
            $geometry = $this->geometry->generateGeometrySnapshot($layout);

            // Create EventSeatingLayout
            $eventSeating = EventSeatingLayout::create([
                'layout_id' => $layout->id,
                'marketplace_client_id' => $event->marketplace_client_id,
                'marketplace_event_id' => $event->id,
                'is_partner' => $layout->is_partner ?? false,
                'partner_notes' => $layout->partner_notes,
                'json_geometry' => $geometry,
                'status' => 'active',
                'published_at' => now(),
            ]);

            // Create EventSeat records for each seat
            foreach ($layout->sections as $section) {
                foreach ($section->rows as $row) {
                    foreach ($row->seats as $seat) {
                        // Check if seat is marked as 'imposibil' in base layout
                        $baseStatus = $seat->status ?? 'active';
                        $eventSeatStatus = ($baseStatus === 'imposibil') ? 'disabled' : 'available';

                        EventSeat::create([
                            'event_seating_id' => $eventSeating->id,
                            'seat_uid' => $seat->seat_uid,
                            'section_name' => $section->name,
                            'row_label' => $row->label,
                            'seat_label' => $seat->label,
                            'status' => $eventSeatStatus,
                            'version' => 1,
                        ]);
                    }
                }
            }

            Log::info('MarketplaceEventSeatingService: Created event seating', [
                'marketplace_event_id' => $event->id,
                'event_seating_id' => $eventSeating->id,
                'seat_count' => $eventSeating->seats()->count(),
            ]);

            return $eventSeating;
        });
    }

    /**
     * Get EventSeatingLayout ID for a marketplace event (or null if no seating)
     *
     * @param int $marketplaceEventId
     * @return int|null
     */
    public function getEventSeatingId(int $marketplaceEventId): ?int
    {
        $eventSeating = $this->getOrCreateEventSeating($marketplaceEventId);
        return $eventSeating?->id;
    }

    /**
     * Check if an event has seating enabled
     *
     * @param int $marketplaceEventId
     * @return bool
     */
    public function hasSeating(int $marketplaceEventId): bool
    {
        // First check if EventSeatingLayout exists
        $existing = EventSeatingLayout::where('marketplace_event_id', $marketplaceEventId)
            ->published()
            ->exists();

        if ($existing) {
            return true;
        }

        // Check if venue has a published seating layout
        $event = MarketplaceEvent::with(['venue.seatingLayouts' => function ($q) {
            $q->where('status', 'published');
        }])->find($marketplaceEventId);

        return $event?->venue?->seatingLayouts?->isNotEmpty() ?? false;
    }

    /**
     * Convert seat IDs to seat UIDs
     *
     * @param int $eventSeatingId
     * @param array $seatIds Array of SeatingSeat IDs
     * @return array Array of seat_uids
     */
    public function seatIdsToUids(int $eventSeatingId, array $seatIds): array
    {
        $eventSeating = EventSeatingLayout::find($eventSeatingId);
        if (!$eventSeating) {
            return [];
        }

        // Get the source layout
        $layout = $eventSeating->sourceLayout;
        if (!$layout) {
            return [];
        }

        // Map seat IDs to UIDs
        $seatUids = [];
        foreach ($layout->sections as $section) {
            foreach ($section->rows as $row) {
                foreach ($row->seats as $seat) {
                    if (in_array($seat->id, $seatIds)) {
                        $seatUids[] = $seat->seat_uid;
                    }
                }
            }
        }

        return $seatUids;
    }

    /**
     * Get seat details by UIDs
     *
     * @param int $eventSeatingId
     * @param array $seatUids
     * @return array
     */
    public function getSeatsByUids(int $eventSeatingId, array $seatUids): array
    {
        return EventSeat::where('event_seating_id', $eventSeatingId)
            ->whereIn('seat_uid', $seatUids)
            ->get()
            ->map(fn ($seat) => [
                'seat_uid' => $seat->seat_uid,
                'section_name' => $seat->section_name,
                'row_label' => $seat->row_label,
                'seat_label' => $seat->seat_label,
                'status' => $seat->status,
            ])
            ->toArray();
    }

    /**
     * Block seats for an event (prevent purchase)
     *
     * @param int $eventSeatingId
     * @param array $seatUids
     * @param string|null $reason Optional reason for blocking
     * @return int Number of seats blocked
     */
    public function blockSeats(int $eventSeatingId, array $seatUids, ?string $reason = null): int
    {
        return EventSeat::where('event_seating_id', $eventSeatingId)
            ->whereIn('seat_uid', $seatUids)
            ->whereIn('status', ['available']) // Only block available seats
            ->update([
                'status' => 'blocked',
                'version' => DB::raw('version + 1'),
            ]);
    }

    /**
     * Unblock seats for an event (make available again)
     *
     * @param int $eventSeatingId
     * @param array $seatUids
     * @return int Number of seats unblocked
     */
    public function unblockSeats(int $eventSeatingId, array $seatUids): int
    {
        return EventSeat::where('event_seating_id', $eventSeatingId)
            ->whereIn('seat_uid', $seatUids)
            ->where('status', 'blocked')
            ->update([
                'status' => 'available',
                'version' => DB::raw('version + 1'),
            ]);
    }

    /**
     * Get all blocked seats for an event
     *
     * @param int $eventSeatingId
     * @return array
     */
    public function getBlockedSeats(int $eventSeatingId): array
    {
        return EventSeat::where('event_seating_id', $eventSeatingId)
            ->where('status', 'blocked')
            ->get()
            ->map(fn ($seat) => [
                'seat_uid' => $seat->seat_uid,
                'section_name' => $seat->section_name,
                'row_label' => $seat->row_label,
                'seat_label' => $seat->seat_label,
            ])
            ->toArray();
    }

    /**
     * Block seats by section and seat range
     *
     * @param int $eventSeatingId
     * @param string $sectionName
     * @param string $rowLabel
     * @param array $seatLabels
     * @return int Number of seats blocked
     */
    public function blockSeatsByLocation(int $eventSeatingId, string $sectionName, string $rowLabel, array $seatLabels): int
    {
        return EventSeat::where('event_seating_id', $eventSeatingId)
            ->where('section_name', $sectionName)
            ->where('row_label', $rowLabel)
            ->whereIn('seat_label', $seatLabels)
            ->whereIn('status', ['available'])
            ->update([
                'status' => 'blocked',
                'version' => DB::raw('version + 1'),
            ]);
    }
}
