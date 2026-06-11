<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot fix for ticket_types where the series_start / series_end use
 * the SKU (or some other slug) in the identifier slot instead of the
 * ticket type id. Example:
 *   AMB-4402-ACCES-00001  →  AMB-4402-10553-00001
 *
 * Caused by EventResource form falling back to $get('sku') when the
 * Filament repeater hadn't persisted the row yet (so $get('id') was
 * null). Form has been fixed to ID-only since, and TicketType::saved()
 * now normalizes on every write — this command catches the legacy rows.
 *
 * Skips rows whose series_start doesn't match the standard
 * "{event_series}-{identifier}-{NNNNN}" pattern (operator-entered
 * custom formats stay untouched).
 */
class NormalizeTicketTypeSeriesIdsCommand extends Command
{
    protected $signature = 'fix:ticket-type-series-ids
        {--marketplace= : limit to a specific marketplace_client_id}
        {--dry-run : preview without writing}';

    protected $description = 'Backfill ticket_types.series_start / series_end so the identifier slot uses the row id instead of a SKU/slug.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $clientId = $this->option('marketplace');

        $eventsQ = DB::table('events')
            ->select('id', 'event_series', 'marketplace_client_id');
        if ($clientId !== null) {
            $eventsQ->where('marketplace_client_id', (int) $clientId);
        }
        $eventSeriesMap = $eventsQ->pluck('event_series', 'id')->all();
        $eventIds = array_keys($eventSeriesMap);

        if (empty($eventIds)) {
            $this->warn('No events matched.');
            return self::SUCCESS;
        }

        $rows = DB::table('ticket_types')
            ->whereIn('event_id', $eventIds)
            ->select('id', 'event_id', 'series_start', 'series_end')
            ->cursor();

        $fixed = 0;
        $skipped = 0;
        $samples = [];

        $totalTicketsFixed = 0;
        $driver = DB::connection()->getDriverName();

        foreach ($rows as $tt) {
            $eventSeries = $eventSeriesMap[$tt->event_id] ?? null;
            if (!$eventSeries) {
                $skipped++;
                continue;
            }

            $prefix = $eventSeries . '-';
            $idStr = (string) $tt->id;
            $updates = [];
            $oldIdentifier = null; // the slug/text we're replacing

            foreach (['series_start', 'series_end'] as $field) {
                $value = $tt->{$field};
                if (empty($value)) continue;
                if (!str_starts_with($value, $prefix)) continue; // custom format
                $tail = substr($value, strlen($prefix));
                if (!preg_match('/^(.+)-(\d+)$/', $tail, $m)) continue;
                if ($m[1] === $idStr) continue;
                $updates[$field] = $prefix . $idStr . '-' . $m[2];
                if ($oldIdentifier === null) {
                    $oldIdentifier = $m[1]; // captured from whichever field hit first
                }
            }

            if (empty($updates) || $oldIdentifier === null) {
                $skipped++;
                continue;
            }

            $oldFullPrefix = $prefix . $oldIdentifier . '-'; // e.g. "AMB-4370-PRESALE-"
            $newFullPrefix = $prefix . $idStr . '-';         // e.g. "AMB-4370-10410-"

            // Count already-issued tickets whose stored series uses the old
            // prefix — these are what need to be rewritten so the printed
            // series numbers on tickets stay consistent with the new
            // ticket_types.series_start/end. Without this, the series
            // would jump mid-sequence after the fix.
            $ticketsToFix = DB::table('tickets')
                ->where('ticket_type_id', $tt->id)
                ->where('meta->ticket_series', 'like', $oldFullPrefix . '%')
                ->count();

            $fixed++;
            $totalTicketsFixed += $ticketsToFix;

            if (count($samples) < 10) {
                $samples[] = [
                    'id' => $tt->id,
                    'event_id' => $tt->event_id,
                    'before' => "{$tt->series_start} … {$tt->series_end}",
                    'after' => ($updates['series_start'] ?? $tt->series_start) . ' … ' . ($updates['series_end'] ?? $tt->series_end),
                    'tickets' => $ticketsToFix,
                ];
            }

            if (!$dry) {
                // 1) Rewrite ticket_types row.
                $updates['updated_at'] = now();
                DB::table('ticket_types')->where('id', $tt->id)->update($updates);

                // 2) Rewrite tickets.meta.ticket_series in bulk.
                if ($ticketsToFix > 0) {
                    if ($driver === 'pgsql') {
                        // Single-pass UPDATE using jsonb_set so we keep the
                        // trailing number untouched. The replace() only
                        // hits the head of the string by virtue of the
                        // LIKE clause filtering rows that start with the
                        // old prefix.
                        DB::statement(
                            "UPDATE tickets "
                            . "SET meta = jsonb_set(meta::jsonb, '{ticket_series}', "
                            . "to_jsonb(replace(meta->>'ticket_series', ?, ?)::text)) "
                            . "WHERE ticket_type_id = ? "
                            . "AND meta->>'ticket_series' LIKE ?",
                            [$oldFullPrefix, $newFullPrefix, $tt->id, $oldFullPrefix . '%']
                        );
                    } else {
                        // SQLite / others — fall back to row-by-row through
                        // the Eloquent model so JSON casts handle encoding.
                        \App\Models\Ticket::where('ticket_type_id', $tt->id)
                            ->whereNotNull('meta->ticket_series')
                            ->chunkById(500, function ($tickets) use ($oldFullPrefix, $newFullPrefix) {
                                foreach ($tickets as $ticket) {
                                    $meta = is_array($ticket->meta) ? $ticket->meta : [];
                                    $series = $meta['ticket_series'] ?? null;
                                    if ($series && str_starts_with($series, $oldFullPrefix)) {
                                        $meta['ticket_series'] = $newFullPrefix . substr($series, strlen($oldFullPrefix));
                                        $ticket->meta = $meta;
                                        $ticket->saveQuietly();
                                    }
                                }
                            });
                    }
                }
            }
        }

        $tag = $dry ? '[DRY RUN] Would fix' : 'Fixed';
        $this->info("{$tag}: {$fixed} ticket_types · {$totalTicketsFixed} tickets · Skipped: {$skipped}");

        if (!empty($samples)) {
            $this->line('Sample:');
            foreach ($samples as $s) {
                $this->line("  tt={$s['id']} event={$s['event_id']} tickets={$s['tickets']}");
                $this->line("    BEFORE: {$s['before']}");
                $this->line("    AFTER : {$s['after']}");
            }
        }

        return self::SUCCESS;
    }
}
