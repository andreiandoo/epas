<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAmbiletOrphanTicketTypesCommand extends Command
{
    protected $signature = 'fix:ambilet-orphan-ticket-types
        {csv : Path to ticket_instances.csv (columns: wp_ticket_id, wp_event_id, wp_product_id, ...)}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}';

    protected $description = 'Backfill ticket_type_id (and event_id when missing) on imported tickets that were created with NULL FKs because wp_product_id was not in ticket_types_map at the time of import.';

    public function handle(): int
    {
        $csvFile  = $this->argument('csv');
        $clientId = (int) $this->option('marketplace');
        $dryRun   = (bool) $this->option('dry-run');

        if (!file_exists($csvFile)) {
            $this->error("CSV not found: {$csvFile}");
            return 1;
        }

        $mapDir        = storage_path('app/import_maps');
        $ttMapFile     = $mapDir . '/ticket_types_map.json';
        $eventsMapFile = $mapDir . '/events_map.json';

        if (!file_exists($ttMapFile)) {
            $this->error("Missing {$ttMapFile}.");
            return 1;
        }
        if (!file_exists($eventsMapFile)) {
            $this->error("Missing {$eventsMapFile}.");
            return 1;
        }

        $ttMap     = json_decode(file_get_contents($ttMapFile), true) ?? [];
        $eventsMap = json_decode(file_get_contents($eventsMapFile), true) ?? [];
        $this->info('Loaded ticket_types_map: ' . count($ttMap) . ' | events_map: ' . count($eventsMap));

        // Index CSV by wp_ticket_id. File is ~240k rows; keep only needed cols in memory.
        $this->info('Indexing ticket_instances.csv...');
        $byWpTicket = [];
        $fh         = fopen($csvFile, 'r');
        $header     = fgetcsv($fh);
        $colMap     = array_flip($header);
        $needed     = ['wp_ticket_id', 'wp_event_id', 'wp_product_id'];
        foreach ($needed as $c) {
            if (!isset($colMap[$c])) {
                fclose($fh);
                $this->error("CSV missing required column: {$c}");
                return 1;
            }
        }
        while (($row = fgetcsv($fh)) !== false) {
            $wpTicket = $row[$colMap['wp_ticket_id']] ?? null;
            if (!$wpTicket) {
                continue;
            }
            $byWpTicket[$wpTicket] = [
                'wp_event_id'   => $row[$colMap['wp_event_id']] ?? null,
                'wp_product_id' => $row[$colMap['wp_product_id']] ?? null,
            ];
        }
        fclose($fh);
        $this->info('Indexed ' . count($byWpTicket) . ' rows from CSV.');

        // Pull orphan tickets: ticket_type_id NULL, have wp_ticket_id in meta, on this marketplace.
        $this->info('Fetching orphan tickets from DB...');
        $driver = DB::connection()->getDriverName();
        $orphanQuery = DB::table('tickets')
            ->whereNull('ticket_type_id')
            ->where('marketplace_client_id', $clientId);
        if ($driver === 'pgsql') {
            $orphanQuery->whereRaw("meta->>'wp_ticket_id' IS NOT NULL");
        } else {
            $orphanQuery->whereRaw("JSON_EXTRACT(meta, '$.wp_ticket_id') IS NOT NULL");
        }
        $orphans = $orphanQuery->select('id', 'meta', 'event_id', 'ticket_type_id')->get();
        $this->info('Orphan tickets: ' . $orphans->count());

        $toSetTypeAndEvent = [];
        $toSetEventOnly    = [];
        $noWpProduct       = 0;
        $productNotMapped  = 0;
        $csvMiss           = 0;

        foreach ($orphans as $t) {
            $meta     = json_decode($t->meta, true) ?: [];
            $wpTicket = $meta['wp_ticket_id'] ?? null;
            if (!$wpTicket) {
                continue;
            }
            $csvRow = $byWpTicket[$wpTicket] ?? null;
            if (!$csvRow) {
                $csvMiss++;
                continue;
            }
            $wpProduct = $csvRow['wp_product_id'] ?: null;
            $wpEvent   = $csvRow['wp_event_id'] ?: null;
            if (!$wpProduct) {
                $noWpProduct++;
                continue;
            }
            $ttId    = $ttMap[$wpProduct] ?? null;
            $eventId = $wpEvent ? ($eventsMap[$wpEvent] ?? null) : null;

            if (!$ttId) {
                $productNotMapped++;
                if ($eventId && !$t->event_id) {
                    $toSetEventOnly[$eventId][] = $t->id;
                }
                continue;
            }

            $toSetTypeAndEvent[] = [
                'ticket_id'      => $t->id,
                'ticket_type_id' => $ttId,
                'event_id'       => $eventId,
            ];
        }

        $this->info('Resolvable (ticket_type set): ' . count($toSetTypeAndEvent));
        $this->info('Event only (ticket_type still unmapped): ' . array_sum(array_map('count', $toSetEventOnly)));
        $this->line('  Reasons for unresolvable:');
        $this->line('    wp_ticket_id not in CSV: ' . $csvMiss);
        $this->line('    No wp_product_id in CSV row: ' . $noWpProduct);
        $this->line('    wp_product_id not in ticket_types_map: ' . $productNotMapped);

        if ($dryRun) {
            $this->info('[DRY RUN] Nothing written.');
            return 0;
        }

        if (empty($toSetTypeAndEvent) && empty($toSetEventOnly)) {
            $this->info('Nothing to update.');
            return 0;
        }

        // Group by (ticket_type_id, event_id) for efficient bulk UPDATE.
        $byGroup = [];
        foreach ($toSetTypeAndEvent as $row) {
            $key = ($row['ticket_type_id'] ?? 'null') . '|' . ($row['event_id'] ?? 'null');
            $byGroup[$key][] = $row['ticket_id'];
        }

        // Two-phase UPDATE, each guarded by whereNull on the column being written.
        // This makes the command fully idempotent: a ticket that already has a
        // non-null value for a column is never overwritten, regardless of what
        // the CSV says. The only columns we ever touch are ticket_type_id,
        // event_id and updated_at.
        $ttUpdated    = 0;
        $evUpdated    = 0;
        foreach ($byGroup as $key => $ticketIds) {
            [$ttId, $evId] = explode('|', $key);
            $ttId = $ttId === 'null' ? null : (int) $ttId;
            $evId = $evId === 'null' ? null : (int) $evId;

            foreach (array_chunk($ticketIds, 500) as $chunk) {
                // Phase 1 — set ticket_type_id only where it is still NULL.
                $ttUpdated += DB::table('tickets')
                    ->whereIn('id', $chunk)
                    ->whereNull('ticket_type_id')
                    ->update(['ticket_type_id' => $ttId, 'updated_at' => now()]);

                // Phase 2 — set event_id only where it is still NULL.
                if ($evId !== null) {
                    $evUpdated += DB::table('tickets')
                        ->whereIn('id', $chunk)
                        ->whereNull('event_id')
                        ->update(['event_id' => $evId, 'updated_at' => now()]);
                }
            }
        }
        $this->info("Set ticket_type_id on {$ttUpdated} tickets.");
        if ($evUpdated) {
            $this->info("Set event_id (was NULL) on {$evUpdated} tickets.");
        }

        $eventOnlyUpdated = 0;
        foreach ($toSetEventOnly as $eventId => $ticketIds) {
            foreach (array_chunk($ticketIds, 500) as $chunk) {
                DB::table('tickets')
                    ->whereIn('id', $chunk)
                    ->whereNull('event_id')
                    ->update(['event_id' => $eventId, 'updated_at' => now()]);
                $eventOnlyUpdated += count($chunk);
            }
        }
        if ($eventOnlyUpdated) {
            $this->info("Updated event_id only on {$eventOnlyUpdated} tickets (ticket_type still unknown).");
        }

        // Recalc ticket_types.quota_sold for affected events.
        $this->info('Recalculating ticket_types.quota_sold...');
        DB::statement("
            UPDATE ticket_types tt
            SET quota_sold = (
                SELECT COUNT(*) FROM tickets t
                WHERE t.ticket_type_id = tt.id AND t.status = 'valid'
            )
            WHERE tt.event_id IN (
                SELECT id FROM events WHERE marketplace_client_id = {$clientId}
            )
        ");

        $this->info('Done.');
        return 0;
    }
}
