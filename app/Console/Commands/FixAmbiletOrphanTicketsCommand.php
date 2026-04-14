<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAmbiletOrphanTicketsCommand extends Command
{
    protected $signature = 'fix:ambilet-orphan-tickets
        {csv : Path to ticket_order_map_historical.csv (columns: wp_ticket_id, wp_order_id)}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}';

    protected $description = 'Link orphaned tickets (order_id IS NULL) to their imported orders and fix ticket statuses';

    public function handle(): int
    {
        $csvFile  = $this->argument('csv');
        $clientId = (int) $this->option('marketplace');
        $dryRun   = $this->option('dry-run');

        if (!file_exists($csvFile)) {
            $this->error("CSV not found: {$csvFile}");
            return 1;
        }

        // Load orders_map.json: wp_order_id → tixello_order_id
        $mapDir  = storage_path('app/import_maps');
        $mapFile = $mapDir . '/orders_map.json';
        if (!file_exists($mapFile)) {
            $this->error('orders_map.json not found.');
            return 1;
        }
        $ordersMap = json_decode(file_get_contents($mapFile), true) ?? [];
        $this->info('Loaded orders map: ' . count($ordersMap) . ' entries.');

        // Load CSV: wp_ticket_id → wp_order_id
        $this->info('Loading ticket→order CSV...');
        $ticketToWpOrder = [];
        $fh     = fopen($csvFile, 'r');
        $header = fgetcsv($fh);
        while (($row = fgetcsv($fh)) !== false) {
            if (count($row) < 2) {
                continue;
            }
            $data                              = array_combine($header, $row);
            $ticketToWpOrder[$data['wp_ticket_id']] = $data['wp_order_id'];
        }
        fclose($fh);
        $this->info('Loaded ' . count($ticketToWpOrder) . ' ticket→order mappings from CSV.');

        // Get all orphaned tickets from DB
        $this->info('Fetching orphaned tickets from DB...');
        $orphaned = DB::table('tickets')
            ->whereNull('order_id')
            ->where('marketplace_client_id', $clientId)
            ->whereRaw(
                DB::getDriverName() === 'pgsql'
                    ? "meta->>'imported_from' = 'ambilet'"
                    : "JSON_EXTRACT(meta, '$.imported_from') = 'ambilet'"
            )
            ->select('id', 'meta')
            ->get();

        $this->info('Found ' . $orphaned->count() . ' orphaned tickets.');

        // Build mapping: tixello_ticket_id → tixello_order_id
        $toUpdate = [];  // ticket_id => order_id
        $notFound = 0;

        foreach ($orphaned as $ticket) {
            $meta       = json_decode($ticket->meta, true);
            $wpTicketId = $meta['wp_ticket_id'] ?? null;
            if (!$wpTicketId) {
                $notFound++;
                continue;
            }

            $wpOrderId = $ticketToWpOrder[$wpTicketId] ?? null;
            if (!$wpOrderId) {
                $notFound++;
                continue;
            }

            $tixelloOrderId = $ordersMap[$wpOrderId] ?? null;
            if (!$tixelloOrderId) {
                $notFound++;
                continue;
            }

            $toUpdate[$ticket->id] = $tixelloOrderId;
        }

        $this->info('Can link: ' . count($toUpdate) . ' | Cannot resolve: ' . $notFound);

        if (empty($toUpdate)) {
            $this->info('Nothing to update.');
            return 0;
        }

        if ($dryRun) {
            $this->info('[DRY RUN] Would link ' . count($toUpdate) . ' tickets to their orders.');
            return 0;
        }

        // Bulk update tickets.order_id — group by order_id for efficiency
        $byOrder = [];
        foreach ($toUpdate as $ticketId => $orderId) {
            $byOrder[$orderId][] = $ticketId;
        }

        $linked = 0;
        foreach ($byOrder as $orderId => $ticketIds) {
            foreach (array_chunk($ticketIds, 500) as $chunk) {
                DB::table('tickets')
                    ->whereIn('id', $chunk)
                    ->update(['order_id' => $orderId, 'updated_at' => now()]);
                $linked += count($chunk);
            }
        }
        $this->info("Linked: {$linked} tickets to orders.");

        // Fix ticket statuses based on order status
        $this->info('Fixing ticket statuses based on order status...');

        $orderStatuses = DB::table('orders')
            ->whereIn('id', array_unique(array_values($toUpdate)))
            ->where('marketplace_client_id', $clientId)
            ->pluck('status', 'id');

        $byTicketStatus = ['cancelled' => [], 'void' => []];

        foreach ($byOrder as $orderId => $ticketIds) {
            $orderStatus  = $orderStatuses[$orderId] ?? null;
            $ticketStatus = match ($orderStatus) {
                'cancelled', 'failed' => 'cancelled',
                'refunded'            => 'void',
                default               => null,  // pending/completed → leave as valid
            };
            if ($ticketStatus) {
                $byTicketStatus[$ticketStatus] = array_merge($byTicketStatus[$ticketStatus], $ticketIds);
            }
        }

        $statusFixed = 0;
        foreach ($byTicketStatus as $ticketStatus => $ticketIds) {
            if (empty($ticketIds)) {
                continue;
            }
            foreach (array_chunk($ticketIds, 500) as $chunk) {
                DB::table('tickets')
                    ->whereIn('id', $chunk)
                    ->update(['status' => $ticketStatus, 'updated_at' => now()]);
                $statusFixed += count($chunk);
            }
            $this->line("  Set '{$ticketStatus}': " . count($ticketIds) . ' tickets.');
        }
        $this->info("Ticket statuses fixed: {$statusFixed}.");

        // Post-analytics: set orders.event_id + marketplace_organizer_id for newly linked orders
        $this->info('Linking orders to events and organizers...');
        DB::statement("
            UPDATE orders
            SET
                event_id                 = sub.event_id,
                marketplace_organizer_id = sub.marketplace_organizer_id
            FROM (
                SELECT
                    t.order_id,
                    MIN(t.event_id)                  AS event_id,
                    MIN(e.marketplace_organizer_id)  AS marketplace_organizer_id
                FROM tickets t
                INNER JOIN events e ON t.event_id = e.id
                WHERE t.marketplace_client_id = {$clientId}
                  AND t.order_id IS NOT NULL
                GROUP BY t.order_id
            ) sub
            WHERE orders.id = sub.order_id
              AND orders.marketplace_client_id = {$clientId}
              AND orders.event_id IS NULL
        ");

        // Recalculate quota_sold for all ticket types
        $this->info('Recalculating quota_sold for all ticket types...');
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

        // Update organizer cached stats
        $this->info('Updating organizer stats...');
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

        // Update customer cached stats
        $this->info('Updating customer stats...');
        DB::statement("
            UPDATE marketplace_customers mc
            SET
                total_orders = (
                    SELECT COUNT(*) FROM orders o
                    WHERE o.marketplace_customer_id = mc.id AND o.status = 'completed'
                ),
                total_spent = (
                    SELECT COALESCE(SUM(o.total), 0) FROM orders o
                    WHERE o.marketplace_customer_id = mc.id AND o.status = 'completed'
                )
            WHERE mc.marketplace_client_id = {$clientId}
        ");

        $this->info('All done!');
        return 0;
    }
}
