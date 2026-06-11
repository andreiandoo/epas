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
        // Load Event first so we can see which seating_layout_id it has selected.
        // The venue may have multiple published layouts; the event's own selection is authoritative.
        $event = Event::with(['venue'])->find($eventId);

        if (!$event || !$event->venue) {
            Log::warning('MarketplaceEventSeatingService: No venue found for event', [
                'event_id' => $eventId,
                'venue_id' => $event?->venue_id,
            ]);
            return null;
        }

        // Check if a published EventSeatingLayout already exists for this event
        $existing = EventSeatingLayout::where('event_id', $eventId)
            ->published()
            ->first();

        if ($existing) {
            $seatCount = $existing->seats()->count();
            // Stale if either: has no seats (incomplete build) OR its source layout_id
            // no longer matches the event's currently selected seating_layout_id.
            $isStale = $seatCount === 0
                || ($event->seating_layout_id && $existing->layout_id !== $event->seating_layout_id);

            if ($isStale) {
                Log::warning('MarketplaceEventSeatingService: EventSeatingLayout (by event_id) is stale, deleting to recreate', [
                    'event_id' => $eventId,
                    'event_seating_id' => $existing->id,
                    'snapshot_layout_id' => $existing->layout_id,
                    'event_selected_layout_id' => $event->seating_layout_id,
                    'seat_count' => $seatCount,
                ]);
                $existing->seats()->delete();
                $existing->delete();
            } else {
                return $existing;
            }
        }

        // Pick the layout: prefer the event's explicit selection, fall back to the
        // first published layout for the venue (legacy behavior for events that
        // never got a seating_layout_id assigned).
        $layoutQuery = SeatingLayout::withoutGlobalScopes()
            ->where('status', 'published');

        if ($event->seating_layout_id) {
            $layoutQuery->where('id', $event->seating_layout_id);
        } else {
            $layoutQuery->where('venue_id', $event->venue_id);
        }

        $layout = $layoutQuery->first();

        if (!$layout) {
            Log::warning('MarketplaceEventSeatingService: No published SeatingLayout found (event_id lookup)', [
                'event_id' => $eventId,
                'venue_id' => $event->venue_id,
                'event_seating_layout_id' => $event->seating_layout_id,
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
    /**
     * Re-sync section_name / row_label / seat_label on event_seats from the
     * current base layout (seating_sections / seating_rows / seating_seats).
     *
     * Why this exists: the snapshot taken at event_seating creation freezes
     * labels at that moment. If an admin later renames a row or section in
     * the layout designer, the event's blocked-seats / sales views keep
     * showing the OLD label even though the source has changed. This
     * method preserves status (blocked, sold, held, ...) and the seat_uid,
     * but refreshes the human-readable labels.
     *
     * Returns the number of event_seats rows whose labels actually moved.
     */
    public function resyncLabelsFromLayout(int $eventSeatingId): int
    {
        $eventSeating = EventSeatingLayout::find($eventSeatingId);
        if (!$eventSeating || !$eventSeating->layout_id) {
            return 0;
        }

        // Build seat_uid → (section_name, row_label, seat_label) from the
        // current base layout. One JOIN trip beats N queries per seat.
        $current = \App\Models\Seating\SeatingSeat::query()
            ->join('seating_rows', 'seating_rows.id', '=', 'seating_seats.row_id')
            ->join('seating_sections', 'seating_sections.id', '=', 'seating_rows.section_id')
            ->where('seating_sections.layout_id', $eventSeating->layout_id)
            ->get([
                'seating_seats.seat_uid',
                'seating_rows.label as r_label',
                'seating_seats.label as s_label',
                'seating_sections.name as sec_name',
            ])
            ->keyBy('seat_uid');

        $updated = 0;
        foreach (EventSeat::where('event_seating_id', $eventSeatingId)->get() as $es) {
            $src = $current->get($es->seat_uid);
            if (!$src) {
                continue;
            }
            if (
                (string) $es->row_label !== (string) $src->r_label
                || (string) $es->seat_label !== (string) $src->s_label
                || (string) $es->section_name !== (string) $src->sec_name
            ) {
                $es->update([
                    'section_name' => $src->sec_name,
                    'row_label' => $src->r_label,
                    'seat_label' => $src->s_label,
                ]);
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * Add missing event_seats rows from the current base layout WITHOUT
     * touching existing rows. Use when an admin added rows/seats to a layout
     * AFTER the per-event snapshot was already created — the snapshot keeps
     * working for the seats it knows about, but new seats in the layout
     * never get an event_seats record, so the booking endpoint can't find
     * them and customers see them as "unavailable" while the public map
     * defaults the unknown UIDs to "available" (the divergence that bit us
     * on event 4360 / layout 14).
     *
     * The destructive recreate path in getOrCreateEventSeatingByEventId()
     * would also work, but it deletes existing rows and resurrects 'sold'
     * status from tickets meta — risky on a live event with active orders.
     * This method is purely additive: layout UID exists, event_seats row
     * missing → insert as 'available' (or 'disabled' if base seat is
     * marked 'imposibil'). Existing rows are left alone.
     *
     * Returns ['added' => N, 'existing' => M, 'orphan_in_event_seats' => K]
     * where orphan = event_seats UIDs no longer present in the layout
     * (informational only — we never delete them, since a sold ticket may
     * reference a UID that was later removed from the layout).
     */
    public function syncMissingSeatsFromLayout(int $eventSeatingId): array
    {
        $eventSeating = EventSeatingLayout::find($eventSeatingId);
        if (!$eventSeating || !$eventSeating->layout_id) {
            return ['added' => 0, 'existing' => 0, 'orphan_in_event_seats' => 0, 'error' => 'event_seating not found or has no layout_id'];
        }

        $layout = SeatingLayout::withoutGlobalScopes()
            ->with(['sections.rows.seats'])
            ->find($eventSeating->layout_id);

        if (!$layout) {
            return ['added' => 0, 'existing' => 0, 'orphan_in_event_seats' => 0, 'error' => 'source layout not found'];
        }

        $existingUids = EventSeat::where('event_seating_id', $eventSeatingId)
            ->pluck('seat_uid')
            ->all();
        $existingUidSet = array_flip($existingUids);

        $added = 0;
        $existing = 0;
        $layoutUidSet = [];

        DB::transaction(function () use ($layout, $eventSeating, $eventSeatingId, &$added, &$existing, &$layoutUidSet, $existingUidSet) {
            foreach ($layout->sections as $section) {
                foreach ($section->rows as $row) {
                    foreach ($row->seats as $seat) {
                        $layoutUidSet[$seat->seat_uid] = true;

                        if (isset($existingUidSet[$seat->seat_uid])) {
                            $existing++;
                            continue;
                        }

                        $baseStatus = $seat->status ?? 'active';
                        $eventSeatStatus = ($baseStatus === 'imposibil') ? 'disabled' : 'available';

                        EventSeat::create([
                            'event_seating_id' => $eventSeatingId,
                            'seat_uid' => $seat->seat_uid,
                            'section_name' => $section->name,
                            'row_label' => $row->label,
                            'seat_label' => $seat->label,
                            'status' => $eventSeatStatus,
                            'version' => 1,
                        ]);
                        $added++;
                    }
                }
            }
        });

        // Orphans: in event_seats but not in current layout. Informational
        // only — we don't delete because a sold ticket may still reference
        // such a UID (e.g. seat removed from layout AFTER the sale).
        $orphan = 0;
        foreach ($existingUids as $uid) {
            if (!isset($layoutUidSet[$uid])) {
                $orphan++;
            }
        }

        Log::info('MarketplaceEventSeatingService: syncMissingSeatsFromLayout completed', [
            'event_seating_id' => $eventSeatingId,
            'layout_id' => $eventSeating->layout_id,
            'added' => $added,
            'existing' => $existing,
            'orphan_in_event_seats' => $orphan,
        ]);

        return [
            'added' => $added,
            'existing' => $existing,
            'orphan_in_event_seats' => $orphan,
        ];
    }

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
