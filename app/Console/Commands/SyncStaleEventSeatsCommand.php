<?php

namespace App\Console\Commands;

use App\Models\Seating\EventSeatingLayout;
use App\Services\Seating\MarketplaceEventSeatingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bulk version of seating:sync-event-seats — iterates every active
 * EventSeatingLayout, compares its seat count against the current base
 * layout, and runs the additive sync for snapshots that are short on
 * seats (layout has more than snapshot — the booking-bug case).
 *
 * Snapshots with a NEGATIVE delta (snapshot has more than layout: rows
 * removed from the layout after creation) are reported only — not
 * touched, since their extra seats may still be referenced by sold
 * tickets and removing them would break the audit trail.
 *
 * Safe to re-run. The underlying sync method only INSERTS missing rows.
 */
class SyncStaleEventSeatsCommand extends Command
{
    protected $signature = 'seating:sync-stale-events
        {--dry-run : Show the report without inserting anything}
        {--layout= : Limit to one specific source layout_id}
        {--min-delta=1 : Only sync snapshots with at least this many missing seats}';

    protected $description = 'Detect and fix every event_seating_layout where the snapshot is short on seats versus the current layout.';

    public function handle(MarketplaceEventSeatingService $service): int
    {
        $layoutFilter = $this->option('layout');
        $minDelta = max(1, (int) $this->option('min-delta'));
        $isDryRun = (bool) $this->option('dry-run');

        $query = EventSeatingLayout::query()
            ->whereNotNull('layout_id')
            ->where('status', 'active');

        if ($layoutFilter) {
            $query->where('layout_id', (int) $layoutFilter);
        }

        $candidates = $query->get(['id', 'event_id', 'marketplace_event_id', 'layout_id']);

        if ($candidates->isEmpty()) {
            $this->info('No active event seatings found' . ($layoutFilter ? " for layout {$layoutFilter}" : '') . '.');
            return self::SUCCESS;
        }

        $this->line('Scanning ' . $candidates->count() . ' active event_seating_layouts...');
        $this->newLine();

        $positiveDelta = [];     // layout > snapshot — sync candidates
        $negativeDelta = [];     // snapshot > layout — informational only
        $skippedNoLayout = 0;

        foreach ($candidates as $esl) {
            $layoutSeats = DB::table('seating_seats')
                ->join('seating_rows', 'seating_rows.id', '=', 'seating_seats.row_id')
                ->join('seating_sections', 'seating_sections.id', '=', 'seating_rows.section_id')
                ->where('seating_sections.layout_id', $esl->layout_id)
                ->count();

            if ($layoutSeats === 0) {
                $skippedNoLayout++;
                continue;
            }

            $eventSeats = DB::table('event_seats')
                ->where('event_seating_id', $esl->id)
                ->count();

            $delta = $layoutSeats - $eventSeats;

            $row = [
                'esl' => $esl->id,
                'event_id' => $esl->event_id,
                'mp_event_id' => $esl->marketplace_event_id,
                'layout_id' => $esl->layout_id,
                'layout_seats' => $layoutSeats,
                'event_seats' => $eventSeats,
                'delta' => $delta,
            ];

            if ($delta >= $minDelta) {
                $positiveDelta[] = $row;
            } elseif ($delta < 0) {
                $negativeDelta[] = $row;
            }
        }

        // Sort: largest positive deltas first (most impactful to fix).
        usort($positiveDelta, fn ($a, $b) => $b['delta'] <=> $a['delta']);
        usort($negativeDelta, fn ($a, $b) => $a['delta'] <=> $b['delta']);

        $this->info('=== SNAPSHOTS WITH MISSING SEATS (sync candidates) ===');
        if (empty($positiveDelta)) {
            $this->line('  none — every snapshot is already in sync.');
        } else {
            foreach ($positiveDelta as $r) {
                $this->line(sprintf(
                    '  esl=%-5d layout=%-4d event=%-6s mp_event=%-6s  layout=%d  snapshot=%d  +%d',
                    $r['esl'],
                    $r['layout_id'],
                    $r['event_id'] ?? '-',
                    $r['mp_event_id'] ?? '-',
                    $r['layout_seats'],
                    $r['event_seats'],
                    $r['delta']
                ));
            }
            $totalMissing = array_sum(array_column($positiveDelta, 'delta'));
            $this->newLine();
            $this->info(sprintf('  %d snapshot(s) with %d total missing seats.', count($positiveDelta), $totalMissing));
        }

        if (!empty($negativeDelta)) {
            $this->newLine();
            $this->info('=== SNAPSHOTS WITH ORPHAN SEATS (informational — not touched) ===');
            foreach ($negativeDelta as $r) {
                $this->line(sprintf(
                    '  esl=%-5d layout=%-4d event=%-6s mp_event=%-6s  layout=%d  snapshot=%d  %d',
                    $r['esl'],
                    $r['layout_id'],
                    $r['event_id'] ?? '-',
                    $r['mp_event_id'] ?? '-',
                    $r['layout_seats'],
                    $r['event_seats'],
                    $r['delta']
                ));
            }
        }

        if ($skippedNoLayout > 0) {
            $this->newLine();
            $this->warn("  Skipped {$skippedNoLayout} snapshot(s) whose source layout has 0 seats.");
        }

        if ($isDryRun) {
            $this->newLine();
            $this->info('--dry-run — no changes made. Re-run without --dry-run to apply.');
            return self::SUCCESS;
        }

        if (empty($positiveDelta)) {
            return self::SUCCESS;
        }

        $this->newLine();
        if (!$this->confirm('Apply additive sync to ' . count($positiveDelta) . ' snapshot(s)?', false)) {
            $this->warn('Aborted.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Applying sync...');
        $totalAdded = 0;
        $failed = 0;

        foreach ($positiveDelta as $r) {
            $result = $service->syncMissingSeatsFromLayout($r['esl']);

            if (isset($result['error'])) {
                $this->error(sprintf('  esl=%d  FAILED: %s', $r['esl'], $result['error']));
                $failed++;
                continue;
            }

            $totalAdded += $result['added'];
            $this->line(sprintf(
                '  esl=%-5d  added=%-4d  existing=%-5d  orphan=%d',
                $r['esl'],
                $result['added'],
                $result['existing'],
                $result['orphan_in_event_seats']
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            'Sync complete. %d snapshot(s) updated, %d new event_seats rows inserted, %d failed.',
            count($positiveDelta) - $failed,
            $totalAdded,
            $failed
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
