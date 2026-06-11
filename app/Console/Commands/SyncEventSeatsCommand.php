<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\MarketplaceEvent;
use App\Models\Seating\EventSeatingLayout;
use App\Services\Seating\MarketplaceEventSeatingService;
use Illuminate\Console\Command;

/**
 * Add missing event_seats rows for an event from its current base layout.
 *
 * Use when an admin added rows/seats to a SeatingLayout AFTER the event's
 * per-event snapshot was already created (the divergence that bit us on
 * event 4360 / layout 14: 500 seats in the layout, 430 in event_seats →
 * customers see new seats as available on the map but the booking endpoint
 * rejects them because the inventory row is missing).
 *
 * Safe to re-run. Only INSERTS missing rows; never updates statuses or
 * deletes existing rows. Sold/blocked/held seats are preserved.
 *
 * Accepts either an Event id or a MarketplaceEvent id — both lookups are
 * tried in order, matching the resolution order the API uses.
 */
class SyncEventSeatsCommand extends Command
{
    protected $signature = 'seating:sync-event-seats
        {event_id : The Event or MarketplaceEvent id}
        {--dry-run : Only report the delta without inserting}';

    protected $description = 'Add missing event_seats rows from the current layout (does not touch existing rows).';

    public function handle(MarketplaceEventSeatingService $service): int
    {
        $eventId = (int) $this->argument('event_id');

        // Resolve: try EventSeatingLayout by event_id first, then by marketplace_event_id.
        $eventSeating = EventSeatingLayout::where('event_id', $eventId)->first()
            ?? EventSeatingLayout::where('marketplace_event_id', $eventId)->first();

        if (!$eventSeating) {
            $this->error("No EventSeatingLayout found for event id {$eventId}.");
            $this->line('Either the event has no seating, or the per-event snapshot was never created.');
            $this->line('Visiting the event page once should create it via the service.');
            return self::FAILURE;
        }

        $this->info("Found event_seating_id={$eventSeating->id} (layout_id={$eventSeating->layout_id}).");
        $this->line("  event_id={$eventSeating->event_id}, marketplace_event_id={$eventSeating->marketplace_event_id}");

        if ($this->option('dry-run')) {
            // Reproduce the count comparison without inserting.
            $layout = \App\Models\Seating\SeatingLayout::withoutGlobalScopes()
                ->with(['sections.rows.seats'])
                ->find($eventSeating->layout_id);

            if (!$layout) {
                $this->error('Source layout not found.');
                return self::FAILURE;
            }

            $layoutSeats = $layout->sections->sum(fn ($s) => $s->rows->sum(fn ($r) => $r->seats->count()));
            $eventSeats = $eventSeating->seats()->count();
            $this->line("  layout has {$layoutSeats} seats; event_seats has {$eventSeats}");
            $this->line('  (dry-run — no changes made)');
            return self::SUCCESS;
        }

        $result = $service->syncMissingSeatsFromLayout($eventSeating->id);

        if (isset($result['error'])) {
            $this->error('Sync failed: ' . $result['error']);
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Sync complete.');
        $this->line("  Added:    {$result['added']}  (new event_seats rows inserted, status='available')");
        $this->line("  Existing: {$result['existing']}  (left untouched — status, holds, sold links preserved)");
        $this->line("  Orphan:   {$result['orphan_in_event_seats']}  (UIDs in event_seats no longer in layout — kept for sold-ticket reference)");

        if ($result['added'] === 0) {
            $this->newLine();
            $this->info('Inventory was already in sync. Nothing to do.');
        }

        return self::SUCCESS;
    }
}
