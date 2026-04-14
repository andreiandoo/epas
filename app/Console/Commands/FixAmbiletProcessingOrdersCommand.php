<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAmbiletProcessingOrdersCommand extends Command
{
    protected $signature = 'fix:ambilet-processing-orders
        {files* : Path(s) to orders CSV file(s)}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}';

    protected $description = 'Fix wc-processing orders: should be completed/paid with valid tickets (not pending/cancelled)';

    public function handle(): int
    {
        $files    = $this->argument('files');
        $clientId = (int) $this->option('marketplace');
        $dryRun   = $this->option('dry-run');

        if (empty($files)) {
            $this->error('Provide at least one orders CSV file.');
            return 1;
        }

        $mapFile = storage_path('app/import_maps/orders_map.json');
        if (!file_exists($mapFile)) {
            $this->error('orders_map.json not found. Run import:ambilet-orders first.');
            return 1;
        }

        $ordersMap = json_decode(file_get_contents($mapFile), true) ?? [];
        $this->info('Loaded orders map: ' . count($ordersMap) . ' entries.');

        // Collect wc-processing orders: [tixello_order_id => paid_at]
        $toFix = [];

        foreach ($files as $file) {
            if (!file_exists($file)) {
                $this->warn("File not found: {$file} — skipping.");
                continue;
            }

            $this->info('Scanning: ' . basename($file));
            $handle = fopen($file, 'r');
            $header = fgetcsv($handle);

            $statusIdx    = array_search('order_status', $header);
            $wpIdIdx      = array_search('wp_order_id', $header);
            $createdIdx   = array_search('created_at', $header);
            $paidIdx      = array_search('paid_at', $header);

            if ($statusIdx === false || $wpIdIdx === false) {
                $this->warn("  Missing required columns (order_status, wp_order_id) — skipping.");
                fclose($handle);
                continue;
            }

            $found = 0;
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) <= max($statusIdx, $wpIdIdx)) {
                    continue;
                }

                $wcStatus = trim($row[$statusIdx], '" ');
                if ($wcStatus !== 'wc-processing' && $wcStatus !== 'processing') {
                    continue;
                }

                $wpOrderId = trim($row[$wpIdIdx], '" ');
                $tixelloId = $ordersMap[$wpOrderId] ?? null;

                if (!$tixelloId) {
                    continue;
                }

                // Use paid_at from CSV, fallback to created_at
                $paidAt = null;
                if ($paidIdx !== false && isset($row[$paidIdx]) && !empty(trim($row[$paidIdx], '" '))) {
                    $paidAt = trim($row[$paidIdx], '" ');
                }
                if (!$paidAt && $createdIdx !== false && isset($row[$createdIdx])) {
                    $paidAt = trim($row[$createdIdx], '" ');
                }

                $toFix[$tixelloId] = $paidAt;
                $found++;
            }

            fclose($handle);
            $this->line("  Found {$found} wc-processing orders.");
        }

        $total = count($toFix);
        $this->info("Total wc-processing orders to fix: {$total}");

        if (empty($toFix)) {
            $this->info('Nothing to fix.');
            return 0;
        }

        // Check current state before fixing
        $ids = array_keys($toFix);
        $currentStats = DB::table('orders')
            ->whereIn('id', $ids)
            ->where('marketplace_client_id', $clientId)
            ->selectRaw('status, payment_status, count(*) as cnt')
            ->groupBy('status', 'payment_status')
            ->get();

        $this->line('Current order statuses:');
        foreach ($currentStats as $row) {
            $this->line("  {$row->status}/{$row->payment_status}: {$row->cnt}");
        }

        $ticketStats = DB::table('tickets')
            ->whereIn('order_id', $ids)
            ->where('marketplace_client_id', $clientId)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->get();

        $this->line('Current ticket statuses:');
        foreach ($ticketStats as $row) {
            $this->line("  {$row->status}: {$row->cnt}");
        }

        if ($dryRun) {
            $ticketCount = DB::table('tickets')
                ->whereIn('order_id', $ids)
                ->where('marketplace_client_id', $clientId)
                ->count();
            $this->info("[DRY RUN] Would fix {$total} orders → completed/paid, {$ticketCount} tickets → valid.");
            return 0;
        }

        // Fix orders in chunks
        $totalOrders  = 0;
        $totalTickets = 0;

        foreach (array_chunk($ids, 500) as $chunk) {
            // Update orders — set paid_at per order from CSV data
            foreach ($chunk as $orderId) {
                $paidAt = $toFix[$orderId];
                DB::table('orders')
                    ->where('id', $orderId)
                    ->where('marketplace_client_id', $clientId)
                    ->update([
                        'status'         => 'completed',
                        'payment_status' => 'paid',
                        'paid_at'        => $paidAt,
                        'updated_at'     => now(),
                    ]);
            }

            // Update tickets
            $affected = DB::table('tickets')
                ->whereIn('order_id', $chunk)
                ->where('marketplace_client_id', $clientId)
                ->update([
                    'status'     => 'valid',
                    'updated_at' => now(),
                ]);

            $totalOrders  += count($chunk);
            $totalTickets += $affected;
        }

        $this->info("Fixed: {$totalOrders} orders → completed/paid, {$totalTickets} tickets → valid.");

        return 0;
    }
}
