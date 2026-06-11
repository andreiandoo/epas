<?php

namespace App\Console\Commands;

use App\Models\Seating\EventSeatingLayout;
use App\Models\Seating\SeatingLayout;
use App\Services\Seating\GeometryStorage;
use Illuminate\Console\Command;

/**
 * Rebuild the cached json_geometry blob on event_seating_layouts from the
 * current SeatingLayout / SeatingSection / SeatingRow / SeatingSeat state.
 *
 * Why this exists: json_geometry is a denormalized snapshot generated once
 * when an event's seating layout is created/published. The seating editor
 * can later rename rows, reorder seats, regenerate seat_uids — and those
 * changes do NOT propagate back into the snapshot. The customer-facing
 * checkout map and the organizer's /organizator/invitatii seat picker
 * both read from this snapshot, so stale snapshots produce visible bugs:
 *   - the same seat_uid appears twice (once at its old row label, once
 *     at the new), so the FE renders duplicates and lets users click the
 *     wrong instance — see the event 4360 incident.
 *   - row labels diverge from the canonical seating_rows.label.
 *
 *   php artisan seating:refresh-snapshots
 *     [--layout=ID]      one event_seating_layout id
 *     [--master=ID]      regen every event layout derived from this master
 *     [--event=ID]       regen every event layout for this event
 *     [--stale-only]     only regen layouts whose snapshot has duplicate
 *                        seat_uids (cheap detection, idempotent)
 *     [--dry-run]        report what would change without writing
 *
 * Default (no filters) sweeps every EventSeatingLayout. Combine with
 * --stale-only on a schedule for a hands-off self-heal.
 */
class RefreshSeatingSnapshotsCommand extends Command
{
    protected $signature = 'seating:refresh-snapshots
        {--layout= : single event_seating_layout id}
        {--master= : SeatingLayout master id; regen all derived event layouts}
        {--event= : Event id; regen all event seating layouts of this event}
        {--stale-only : only touch snapshots with duplicate seat_uids}
        {--dry-run : preview without writing}';

    protected $description = 'Rebuild event_seating_layouts.json_geometry from the current SeatingLayout DB state';

    public function handle(GeometryStorage $service): int
    {
        $dry = (bool) $this->option('dry-run');
        $staleOnly = (bool) $this->option('stale-only');

        $query = EventSeatingLayout::query();
        if ($id = $this->option('layout')) {
            $query->where('id', (int) $id);
        }
        if ($mid = $this->option('master')) {
            $query->where('layout_id', (int) $mid);
        }
        if ($eid = $this->option('event')) {
            $query->where('event_id', (int) $eid);
        }

        $checked = 0;
        $stale = 0;
        $updated = 0;
        $missingBase = 0;
        $errors = 0;

        // Cache base layouts so a master layout with many derived events
        // doesn't get reloaded for each one.
        $baseCache = [];
        $geometryCache = [];

        $query->orderBy('id')->chunk(50, function ($layouts) use (&$checked, &$stale, &$updated, &$missingBase, &$errors, &$baseCache, &$geometryCache, $service, $dry, $staleOnly) {
            foreach ($layouts as $l) {
                $checked++;

                $hasDup = $this->snapshotHasDuplicates($l);
                if ($hasDup) {
                    $stale++;
                }
                if ($staleOnly && !$hasDup) {
                    continue;
                }

                $masterId = (int) $l->layout_id;
                if (!isset($baseCache[$masterId])) {
                    $base = SeatingLayout::find($masterId);
                    if (!$base) {
                        $baseCache[$masterId] = null;
                        $geometryCache[$masterId] = null;
                    } else {
                        $base->load('sections.rows.seats');
                        $baseCache[$masterId] = $base;
                    }
                }
                $base = $baseCache[$masterId];
                if (!$base) {
                    $missingBase++;
                    $this->warn("  SKIP layout #{$l->id} — base layout {$masterId} not found");
                    continue;
                }

                try {
                    if (!isset($geometryCache[$masterId])) {
                        $geometryCache[$masterId] = $service->generateGeometrySnapshot($base);
                    }
                    if (!$dry) {
                        $l->json_geometry = $geometryCache[$masterId];
                        $l->save();
                    }
                    $updated++;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->error("  ERROR layout #{$l->id}: " . $e->getMessage());
                }
            }
        });

        $verb = $dry ? '[DRY] Would update' : 'Updated';
        $this->info(sprintf(
            'Checked: %d | Stale detected: %d | %s: %d | Missing base: %d | Errors: %d',
            $checked, $stale, $verb, $updated, $missingBase, $errors
        ));

        return self::SUCCESS;
    }

    /**
     * True when at least one seat_uid appears in more than one row of the
     * same section in the cached snapshot. Cheap canary signal that the
     * snapshot is out of sync with the current DB.
     */
    private function snapshotHasDuplicates(EventSeatingLayout $layout): bool
    {
        $g = $layout->json_geometry;
        if (is_string($g)) {
            $g = json_decode($g, true);
        }
        if (!is_array($g)) {
            return false;
        }
        foreach ($g['sections'] ?? [] as $s) {
            $uids = [];
            foreach ($s['rows'] ?? [] as $r) {
                foreach ($r['seats'] ?? [] as $seat) {
                    $u = $seat['seat_uid'] ?? null;
                    if (!$u) continue;
                    if (isset($uids[$u])) return true;
                    $uids[$u] = true;
                }
            }
        }
        return false;
    }
}
