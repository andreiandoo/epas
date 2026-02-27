<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportAmbiletTicketsCommand extends Command
{
    protected $signature = 'import:ambilet-tickets
        {file : Path to ticket_instances.csv}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}
        {--fresh : Ignore existing map and re-process all rows}';

    protected $description = 'Import AmBilet ticket instances from CSV into Tixello tickets';

    public function handle(): int
    {
        $file     = $this->argument('file');
        $clientId = (int) $this->option('marketplace');
        $dryRun   = $this->option('dry-run');
        $fresh    = $this->option('fresh');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $dir    = dirname($file);
        $mapDir = storage_path('app/import_maps');
        if (!is_dir($mapDir)) {
            mkdir($mapDir, 0755, true);
        }
        $ticketsMapFile = $mapDir . '/tickets_map.json';

        // Load prerequisite maps (from storage, written by previous import commands)
        // events_map: wp_event_id => events.id
        // ticket_types_map: wp_product_id => ticket_types.id
        // orders_map: wp_order_id => orders.id
        $eventsMap = $this->loadJsonMap($mapDir . '/events_map.json', 'events');
        $ttMap     = $this->loadJsonMap($mapDir . '/ticket_types_map.json', 'ticket types');
        $ordersMap = $this->loadJsonMap($mapDir . '/orders_map.json', 'orders');

        // Load order_item_map.csv from CSV source directory (not storage)
        $orderItemMapFile = $dir . '/order_item_map.csv';
        if (!file_exists($orderItemMapFile)) {
            $this->error("order_item_map.csv not found in: {$dir}");
            return 1;
        }

        $this->info('Loading order item map...');
        $itemToOrderId = [];
        $fh = fopen($orderItemMapFile, 'r');
        fgetcsv($fh); // skip header
        while (($r = fgetcsv($fh)) !== false) {
            $itemToOrderId[$r[0]] = $r[1]; // order_item_id => wp_order_id
        }
        fclose($fh);
        $this->info('Loaded ' . count($itemToOrderId) . ' order item mappings.');

        // Idempotency map for tickets
        $ticketsMap = [];
        if (!$fresh && file_exists($ticketsMapFile)) {
            $ticketsMap = json_decode(file_get_contents($ticketsMapFile), true) ?? [];
            $this->info('Loaded tickets map: ' . count($ticketsMap) . ' entries.');
        } elseif ($fresh && file_exists($ticketsMapFile)) {
            unlink($ticketsMapFile);
            $this->info('Deleted existing tickets map (--fresh).');
        }

        $handle  = fopen($file, 'r');
        $header  = fgetcsv($handle);
        $created = $skipped = $failed = $noOrder = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data       = array_combine($header, $row);
            $wpTicketId = $data['wp_ticket_id'];

            if (isset($ticketsMap[$wpTicketId])) {
                $skipped++;
                continue;
            }

            // Resolve Tixello event (events.id) and ticket type (ticket_types.id)
            $tixelloEventId = $eventsMap[$data['wp_event_id']] ?? null;
            $tixelloTtId    = $ttMap[$data['wp_product_id']] ?? null;

            // Resolve order via wc_order_item_id → order_item_map → orders_map
            $tixelloOrderId = null;
            $wcItemId       = $this->n($data['wc_order_item_id']);
            if ($wcItemId) {
                $wpOrderId      = $itemToOrderId[$wcItemId] ?? null;
                $tixelloOrderId = $wpOrderId ? ($ordersMap[$wpOrderId] ?? null) : null;
                if (!$tixelloOrderId) {
                    $noOrder++;
                }
            } else {
                $noOrder++;
            }

            // Parse check-in from PHP serialized data.
            // fgetcsv() already unescapes doubled quotes ("" → ") inside CSV fields.
            $checkedInAt = null;
            $checkinRaw  = $this->n($data['checkin_data']);
            if ($checkinRaw) {
                $parsed = @unserialize($checkinRaw);
                if (is_array($parsed) && isset($parsed[0]['date_checked'])) {
                    $ts = (int) $parsed[0]['date_checked'];
                    if ($ts > 0) {
                        $checkedInAt = date('Y-m-d H:i:s', $ts);
                    }
                }
            }

            $createdAt = $this->parseDate($data['created_at']) ?? now()->toDateTimeString();

            // Now using event_id (FK to events) and ticket_type_id (FK to ticket_types)
            // instead of marketplace_event_id / marketplace_ticket_type_id.
            $ticketData = [
                'ticket_type_id'             => $tixelloTtId,
                'event_id'                   => $tixelloEventId,
                'marketplace_client_id'      => $clientId,
                'order_id'                   => $tixelloOrderId,
                'code'                       => Str::random(64),
                'barcode'                    => $this->n($data['ticket_code']),
                'status'                     => 'valid',
                'seat_label'                 => $this->n($data['seat_label']),
                'checked_in_at'              => $checkedInAt,
                'meta'                       => json_encode([
                    'wp_ticket_id'  => $wpTicketId,
                    'imported_from' => 'ambilet',
                ]),
                'created_at'                 => $createdAt,
                'updated_at'                 => $createdAt,
            ];

            if ($dryRun) {
                $this->line("[DRY RUN] Ticket: {$data['ticket_code']} | event: {$tixelloEventId} | order: {$tixelloOrderId} | checkin: {$checkedInAt}");
                $ticketsMap[$wpTicketId] = 0;
                $created++;
                continue;
            }

            try {
                $ticketId                = DB::table('tickets')->insertGetId($ticketData);
                $ticketsMap[$wpTicketId] = $ticketId;
                $created++;

                if ($created % 1000 === 0) {
                    file_put_contents($ticketsMapFile, json_encode($ticketsMap, JSON_PRETTY_PRINT));
                    $this->line("Progress: {$created} created | {$skipped} skipped | {$failed} failed | {$noOrder} no-order");
                }
            } catch (\Exception $e) {
                $this->error("Failed ticket {$wpTicketId} ({$data['ticket_code']}): " . $e->getMessage());
                $failed++;
            }
        }

        fclose($handle);
        file_put_contents($ticketsMapFile, json_encode($ticketsMap, JSON_PRETTY_PRINT));

        $this->info("Done! Created: {$created} | Skipped: {$skipped} | Failed: {$failed} | No order link: {$noOrder}");
        $this->info("Map saved to: {$ticketsMapFile}");

        if ($dryRun) {
            return 0;
        }

        // =====================================================================
        // POST-IMPORT ANALYTICS UPDATES
        // Run after all tickets are inserted. Does NOT touch available_balance.
        // Uses events table (not marketplace_events) and ticket_types (not marketplace_ticket_types).
        // =====================================================================

        $this->info('');
        $this->info('Running post-import analytics updates...');

        // 1. Set ticket_types.quota_sold from actual ticket count
        $this->info('  [1/3] Updating ticket type quota_sold...');
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

        // 2. Set orders.event_id and orders.marketplace_organizer_id
        //    using the first ticket in each order.
        //    This enables per-organizer and per-event order filtering.
        //    NOTE: Does NOT touch available_balance (already paid out in old platform).
        $this->info('  [2/3] Linking orders to events and organizers...');
        DB::statement("
            UPDATE orders o
            INNER JOIN (
                SELECT
                    t.order_id,
                    t.event_id,
                    e.marketplace_organizer_id
                FROM tickets t
                INNER JOIN events e ON t.event_id = e.id
                WHERE t.marketplace_client_id = {$clientId}
                  AND t.order_id IS NOT NULL
                GROUP BY t.order_id
            ) sub ON o.id = sub.order_id
            SET
                o.event_id                  = sub.event_id,
                o.marketplace_organizer_id  = sub.marketplace_organizer_id
            WHERE o.marketplace_client_id = {$clientId}
              AND o.event_id IS NULL
        ");

        // 3. Update marketplace_organizers cached stats: total_events, total_tickets_sold, total_revenue
        //    NOTE: available_balance, pending_balance, total_paid_out are NOT touched —
        //    historical funds were already paid out through the old platform.
        $this->info('  [3/3] Updating organizer stats (total_events, total_tickets_sold, total_revenue)...');
        DB::statement("
            UPDATE marketplace_organizers mo
            SET
                total_events = (
                    SELECT COUNT(*) FROM events e
                    WHERE e.marketplace_organizer_id = mo.id
                      AND e.marketplace_client_id = {$clientId}
                ),
                total_tickets_sold = (
                    SELECT COUNT(*) FROM tickets t
                    INNER JOIN events e ON t.event_id = e.id
                    WHERE e.marketplace_organizer_id = mo.id
                      AND t.marketplace_client_id = {$clientId}
                      AND t.status = 'valid'
                ),
                total_revenue = (
                    SELECT COALESCE(SUM(o.total), 0) FROM orders o
                    WHERE o.marketplace_organizer_id = mo.id
                      AND o.marketplace_client_id = {$clientId}
                      AND o.status = 'completed'
                )
            WHERE mo.marketplace_client_id = {$clientId}
        ");

        $this->info('Post-import analytics complete.');

        return 0;
    }

    private function loadJsonMap(string $path, string $label): array
    {
        if (!file_exists($path)) {
            $this->warn("Map file not found for {$label}: {$path}");
            return [];
        }
        $data = json_decode(file_get_contents($path), true) ?? [];
        $this->info("Loaded {$label} map: " . count($data) . ' entries.');
        return $data;
    }

    private function n(?string $v): ?string
    {
        return ($v !== null && $v !== '' && $v !== 'NULL') ? $v : null;
    }

    private function parseDate(?string $v): ?string
    {
        if (!$v || $v === 'NULL' || $v === '0000-00-00 00:00:00' || $v === '0000-00-00') {
            return null;
        }
        return $v;
    }
}
