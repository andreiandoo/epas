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

        $dir          = dirname($file);
        $ticketsMapFile = $dir . '/tickets_map.json';

        // --- Load prerequisite maps ---
        $eventsMap    = $this->loadJsonMap($dir . '/events_map.json', 'events');
        $ttMap        = $this->loadJsonMap($dir . '/ticket_types_map.json', 'ticket types');
        $ordersMap    = $this->loadJsonMap($dir . '/orders_map.json', 'orders');

        // --- Load order_item_map.csv: order_item_id => wp_order_id ---
        $orderItemMapFile = $dir . '/order_item_map.csv';
        if (!file_exists($orderItemMapFile)) {
            $this->error("order_item_map.csv not found in: {$dir}");
            return 1;
        }

        $this->info('Loading order item map (order_item_id → wp_order_id)...');
        $itemToOrderId = [];
        $fh = fopen($orderItemMapFile, 'r');
        fgetcsv($fh); // skip header
        while (($r = fgetcsv($fh)) !== false) {
            // order_item_id, wp_order_id
            $itemToOrderId[$r[0]] = $r[1];
        }
        fclose($fh);
        $this->info('Loaded ' . count($itemToOrderId) . ' order item mappings.');

        // --- Load tickets idempotency map ---
        $ticketsMap = [];
        if (!$fresh && file_exists($ticketsMapFile)) {
            $ticketsMap = json_decode(file_get_contents($ticketsMapFile), true) ?? [];
            $this->info('Loaded tickets map: ' . count($ticketsMap) . ' entries.');
        } elseif ($fresh && file_exists($ticketsMapFile)) {
            unlink($ticketsMapFile);
            $this->info('Deleted existing tickets map (--fresh).');
        }

        // --- Process ticket_instances.csv ---
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

            // Resolve Tixello event and ticket type
            $tixelloEventId = $eventsMap[$data['wp_event_id']] ?? null;
            $tixelloTtId    = $ttMap[$data['wp_product_id']] ?? null;

            // Resolve order via wc_order_item_id
            $tixelloOrderId = null;
            $wcItemId = $this->n($data['wc_order_item_id']);
            if ($wcItemId) {
                $wpOrderId      = $itemToOrderId[$wcItemId] ?? null;
                $tixelloOrderId = $wpOrderId ? ($ordersMap[$wpOrderId] ?? null) : null;
                if (!$tixelloOrderId) {
                    $noOrder++;
                }
            } else {
                $noOrder++;
            }

            // Parse check-in from PHP serialized data
            // fgetcsv already unescapes "" → " inside quoted fields
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

            $ticketData = [
                'marketplace_client_id'      => $clientId,
                'marketplace_event_id'       => $tixelloEventId,
                'marketplace_ticket_type_id' => $tixelloTtId,
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
                'created_at'                 => $this->parseDate($data['created_at']),
                'updated_at'                 => $this->parseDate($data['created_at']),
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
                $this->error("Failed ticket {$wpTicketId}: " . $e->getMessage());
                $failed++;
            }
        }

        fclose($handle);
        file_put_contents($ticketsMapFile, json_encode($ticketsMap, JSON_PRETTY_PRINT));

        $this->info("Done! Created: {$created} | Skipped: {$skipped} | Failed: {$failed} | No order link: {$noOrder}");
        $this->info("Map saved to: {$ticketsMapFile}");

        // Update quantity_sold on MarketplaceTicketType for all imported ticket types
        if (!$dryRun && $created > 0) {
            $this->info('Updating quantity_sold on ticket types...');
            DB::statement("
                UPDATE marketplace_ticket_types mtt
                SET quantity_sold = (
                    SELECT COUNT(*) FROM tickets t
                    WHERE t.marketplace_ticket_type_id = mtt.id AND t.status = 'valid'
                )
                WHERE mtt.marketplace_event_id IN (
                    SELECT id FROM marketplace_events WHERE marketplace_client_id = {$clientId}
                )
            ");
            $this->info('quantity_sold updated.');
        }

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
