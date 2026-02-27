<?php

namespace App\Services\Seating;

use App\Models\Event;
use App\Models\MarketplaceEvent;
use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\EventSeat;
use App\Models\Seating\SeatingLayout;
use App\Models\Ticket;
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
            // If event seating exists but has no seats, delete and recreate
            // This handles cases where the base layout had no seats when first created
            if ($existing->seats()->count() === 0) {
                Log::warning('MarketplaceEventSeatingService: EventSeatingLayout has 0 seats, deleting to recreate', [
                    'marketplace_event_id' => $marketplaceEventId,
                    'event_seating_id' => $existing->id,
                ]);
                $existing->seats()->delete();
                $existing->delete();
            } else {
                return $existing;
            }
        }

        // Get marketplace event with venue
        $event = MarketplaceEvent::with(['venue'])->find($marketplaceEventId);

        if (!$event || !$event->venue) {
            Log::warning('MarketplaceEventSeatingService: No venue found', [
                'marketplace_event_id' => $marketplaceEventId,
                'venue_id' => $event?->venue_id,
            ]);
            return null;
        }

        // Load seating layout separately with withoutGlobalScopes to bypass TenantScope
        $layout = SeatingLayout::withoutGlobalScopes()
            ->where('venue_id', $event->venue_id)
            ->where('status', 'published')
            ->first();

        if (!$layout) {
            Log::warning('MarketplaceEventSeatingService: No published SeatingLayout for venue', [
                'marketplace_event_id' => $marketplaceEventId,
                'venue_id' => $event->venue_id,
            ]);
            return null;
        }

        // Explicitly load sections with rows and seats (bypass any potential scope issues)
        $layout->load(['sections.rows.seats']);

        Log::info('MarketplaceEventSeatingService: Found layout with sections', [
            'layout_id' => $layout->id,
            'sections_count' => $layout->sections->count(),
            'total_rows' => $layout->sections->sum(fn ($s) => $s->rows->count()),
            'total_seats' => $layout->sections->sum(fn ($s) => $s->rows->sum(fn ($r) => $r->seats->count())),
        ]);

        if ($layout->sections->isEmpty()) {
            Log::warning('MarketplaceEventSeatingService: Layout has no sections', [
                'layout_id' => $layout->id,
            ]);
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

                        EventSeat::updateOrCreate(
                            [
                                'event_seating_id' => $eventSeating->id,
                                'seat_uid' => $seat->seat_uid,
                            ],
                            [
                                'section_name' => $section->name,
                                'row_label' => $row->label,
                                'seat_label' => $seat->label,
                                'status' => $eventSeatStatus,
                                'version' => 1,
                            ]
                        );
                    }
                }
            }

            // Mark seats as 'sold' if there are already purchased tickets for this event
            $soldSeatUids = Ticket::where('marketplace_event_id', $event->id)
                ->whereIn('status', ['valid', 'used', 'pending'])
                ->whereNotNull('meta')
                ->get()
                ->pluck('meta.seat_uid')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (!empty($soldSeatUids)) {
                EventSeat::where('event_seating_id', $eventSeating->id)
                    ->whereIn('seat_uid', $soldSeatUids)
                    ->update([
                        'status' => 'sold',
                        'version' => DB::raw('version + 1'),
                    ]);
            }

            Log::info('MarketplaceEventSeatingService: Created event seating', [
                'marketplace_event_id' => $event->id,
                'event_seating_id' => $eventSeating->id,
                'seat_count' => $eventSeating->seats()->count(),
                'sold_seats_restored' => count($soldSeatUids),
            ]);

            return $eventSeating;
        });
    }

    /**
     * Get or create EventSeatingLayout for an Event (from events table)
     *
     * @param int $eventId
     * @return EventSeatingLayout|null
     */
    public function getOrCreateEventSeatingByEventId(int $eventId): ?EventSeatingLayout
    {
        // Check if event seating already exists using event_id
        $existing = EventSeatingLayout::where('event_id', $eventId)
            ->published()
            ->first();

        if ($existing) {
            if ($existing->seats()->count() === 0) {
                Log::warning('MarketplaceEventSeatingService: EventSeatingLayout (by event_id) has 0 seats, deleting to recreate', [
                    'event_id' => $eventId,
                    'event_seating_id' => $existing->id,
                ]);
                $existing->seats()->delete();
                $existing->delete();
            } else {
                return $existing;
            }
        }

        // Get Event with venue
        $event = Event::with(['venue'])->find($eventId);

        if (!$event || !$event->venue) {
            Log::warning('MarketplaceEventSeatingService: No venue found for event', [
                'event_id' => $eventId,
                'venue_id' => $event?->venue_id,
            ]);
            return null;
        }

        // Load seating layout (bypass TenantScope)
        $layout = SeatingLayout::withoutGlobalScopes()
            ->where('venue_id', $event->venue_id)
            ->where('status', 'published')
            ->first();

        if (!$layout) {
            Log::warning('MarketplaceEventSeatingService: No published SeatingLayout for venue (event_id lookup)', [
                'event_id' => $eventId,
                'venue_id' => $event->venue_id,
            ]);
            return null;
        }

        $layout->load(['sections.rows.seats']);

        if ($layout->sections->isEmpty()) {
            return null;
        }

        return $this->createEventSeatingFromEvent($event, $layout);
    }

    /**
     * Create EventSeatingLayout from an Event model (events table)
     */
    protected function createEventSeatingFromEvent(Event $event, SeatingLayout $layout): EventSeatingLayout
    {
        return DB::transaction(function () use ($event, $layout) {
            $geometry = $this->geometry->generateGeometrySnapshot($layout);

            $eventSeating = EventSeatingLayout::create([
                'event_id' => $event->id,
                'layout_id' => $layout->id,
                'marketplace_client_id' => $event->marketplace_client_id,
                'is_partner' => $layout->is_partner ?? false,
                'partner_notes' => $layout->partner_notes,
                'json_geometry' => $geometry,
                'status' => 'active',
                'published_at' => now(),
            ]);

            foreach ($layout->sections as $section) {
                foreach ($section->rows as $row) {
                    foreach ($row->seats as $seat) {
                        $baseStatus = $seat->status ?? 'active';
                        $eventSeatStatus = ($baseStatus === 'imposibil') ? 'disabled' : 'available';

                        EventSeat::updateOrCreate(
                            [
                                'event_seating_id' => $eventSeating->id,
                                'seat_uid' => $seat->seat_uid,
                            ],
                            [
                                'section_name' => $section->name,
                                'row_label' => $row->label,
                                'seat_label' => $seat->label,
                                'status' => $eventSeatStatus,
                                'version' => 1,
                            ]
                        );
                    }
                }
            }

            // Mark seats as 'sold' if there are already purchased tickets
            $soldSeatUids = Ticket::where('event_id', $event->id)
                ->whereIn('status', ['valid', 'used', 'pending'])
                ->whereNotNull('meta')
                ->get()
                ->pluck('meta.seat_uid')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (!empty($soldSeatUids)) {
                EventSeat::where('event_seating_id', $eventSeating->id)
                    ->whereIn('seat_uid', $soldSeatUids)
                    ->update([
                        'status' => 'sold',
                        'version' => DB::raw('version + 1'),
                    ]);
            }

            Log::info('MarketplaceEventSeatingService: Created event seating (by event_id)', [
                'event_id' => $event->id,
                'event_seating_id' => $eventSeating->id,
                'seat_count' => $eventSeating->seats()->count(),
                'sold_seats_restored' => count($soldSeatUids),
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

        // Check if venue has a published seating layout (bypass TenantScope)
        $event = MarketplaceEvent::find($marketplaceEventId);
        if (!$event || !$event->venue_id) {
            return false;
        }

        return SeatingLayout::withoutGlobalScopes()
            ->where('venue_id', $event->venue_id)
            ->where('status', 'published')
            ->exists();
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
