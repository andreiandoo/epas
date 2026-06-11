<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time cleanup of `events` rows where the date / time / slot columns
 * don't match the row's `duration_mode`. Typically caused by
 * "duplicate event then change mode" flows that left the original
 * single-day `event_date` populated on what is now a range or multi-day
 * event, surfacing stale dates in lists / homepage / emails.
 *
 *   php artisan events:fix-duration-mode-fields                      # dry-run, all events
 *   php artisan events:fix-duration-mode-fields --apply              # commit
 *   php artisan events:fix-duration-mode-fields --event=4506         # just one
 *   php artisan events:fix-duration-mode-fields --mode=range         # only range events
 *   php artisan events:fix-duration-mode-fields --verbose-changes    # list every row
 *
 * Same field map as the model saving hook — keeps both code paths in
 * sync. Each save bypasses the Event::saving lifecycle (uses query
 * builder) to avoid touching unrelated logic; the goal is a precise
 * column reset on existing rows. New saves are guarded by the model
 * hook going forward.
 */
class EventsFixDurationModeFields extends Command
{
    protected $signature = 'events:fix-duration-mode-fields
        {--apply : Commit changes (default is dry-run only)}
        {--dry-run : Alias for the default dry-run mode (no-op flag)}
        {--event= : Restrict to one events.id}
        {--mode= : Restrict to one duration_mode (single_day / range / multi_day)}
        {--verbose-changes : Print every row instead of the first 50}';

    protected $description = 'Null out date/time/slot columns that do not belong to an event row\'s duration_mode (dry-run by default).';

    private array $fieldMap = [
        'single_day' => [
            'range_start_date', 'range_end_date',
            'range_start_time', 'range_end_time',
            'multi_slots',
        ],
        'range' => [
            'event_date', 'start_time', 'door_time', 'end_time',
            'multi_slots',
        ],
        'multi_day' => [
            'event_date', 'start_time', 'door_time', 'end_time',
            'range_start_date', 'range_end_date',
            'range_start_time', 'range_end_time',
        ],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $eventId = $this->option('event');
        $modeFilter = $this->option('mode');
        $verbose = (bool) $this->option('verbose-changes');

        $this->line('');
        $this->info($apply ? '=== APPLY MODE — changes will be committed ===' : '=== DRY-RUN — no writes ===');
        $this->line('event=' . ($eventId ?: '*') . '  mode=' . ($modeFilter ?: '*'));
        $this->line('');

        $query = Event::query()
            ->whereIn('duration_mode', array_keys($this->fieldMap))
            ->when($eventId, fn ($q) => $q->where('id', (int) $eventId))
            ->when($modeFilter, fn ($q) => $q->where('duration_mode', $modeFilter))
            ->orderBy('id');

        $stats = ['scanned' => 0, 'rows_with_stale' => 0, 'fields_nulled' => 0];
        $perMode = [];
        $changes = [];

        $query->chunkById(500, function ($events) use (&$stats, &$perMode, &$changes) {
            foreach ($events as $e) {
                $stats['scanned']++;
                $fields = $this->fieldMap[$e->duration_mode] ?? null;
                if (! $fields) {
                    continue;
                }

                $stale = [];
                foreach ($fields as $f) {
                    if ($e->getAttribute($f) !== null) {
                        $stale[$f] = $e->getAttribute($f);
                    }
                }
                if (! $stale) {
                    continue;
                }

                $stats['rows_with_stale']++;
                $stats['fields_nulled'] += count($stale);
                $perMode[$e->duration_mode] = ($perMode[$e->duration_mode] ?? 0) + 1;

                $changes[] = [
                    'id' => $e->id,
                    'mode' => $e->duration_mode,
                    'fields' => $stale,
                ];
            }
        });

        // Apply (raw query builder so we bypass the model hook recursion).
        if ($apply && $changes) {
            DB::transaction(function () use ($changes) {
                foreach ($changes as $c) {
                    DB::table('events')
                        ->where('id', $c['id'])
                        ->update(array_fill_keys(array_keys($c['fields']), null));
                }
            });
        }

        // Report
        $this->line('');
        $this->info('Proposed nulls:');
        $sample = $verbose ? $changes : array_slice($changes, 0, 50);
        if ($sample) {
            $rows = array_map(fn ($c) => [
                $c['id'],
                $c['mode'],
                implode(', ', array_map(
                    fn ($v, $k) => $k . '=' . (is_scalar($v) ? mb_substr((string) $v, 0, 22) : '[…]'),
                    $c['fields'],
                    array_keys($c['fields'])
                )),
            ], $sample);
            $this->table(['id', 'mode', 'fields (current values → null)'], $rows);
            if (! $verbose && count($changes) > count($sample)) {
                $this->line('  ... ' . (count($changes) - count($sample)) . ' more (--verbose-changes to list all)');
            }
        } else {
            $this->line('  (none — every row is clean)');
        }

        $this->line('');
        $this->info('Summary:');
        $this->line('  scanned          ' . $stats['scanned']);
        $this->line('  rows_with_stale  ' . $stats['rows_with_stale']);
        $this->line('  fields_nulled    ' . $stats['fields_nulled']);
        foreach ($perMode as $mode => $cnt) {
            $this->line('  by ' . str_pad($mode, 12) . '  ' . $cnt);
        }

        $this->line('');
        if ($apply) {
            $this->info("Applied {$stats['fields_nulled']} field resets across {$stats['rows_with_stale']} events.");
        } elseif ($changes) {
            $this->comment('Dry-run only — nothing changed. Re-run with --apply to commit.');
        }
        $this->line('');

        return self::SUCCESS;
    }
}
