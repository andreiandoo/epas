<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAmbiletOrderStatusesCommand extends Command
{
    protected $signature = 'fix:ambilet-order-statuses
        {files* : Path(s) to orders CSV file(s)}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}';

    protected $description = 'Fix historical AmBilet order statuses: wc-failed/cancelled/refunded/processing were imported as completed';

    // WC status → [order_status, payment_status, ticket_status]
    // ticket_status null = leave as-is (already correct)
    private array $statusMap = [
        'wc-failed'     => ['failed',    'failed',   'cancelled'],
        'wc-cancelled'  => ['cancelled', 'failed',   'cancelled'],
        'wc-refunded'   => ['refunded',  'refunded', 'void'],
        'wc-processing' => ['completed', 'paid',     'valid'],
        'wc-on-hold'    => ['pending',   'pending',  'pending'],
        'wc-pending'    => ['pending',   'pending',  'pending'],
    ];

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

        // Collect all orders that need fixing: [tixello_order_id => [order_status, payment_status, ticket_status]]
        $toFix = [];

        foreach ($files as $file) {
            if (!file_exists($file)) {
                $this->warn("File not found: {$file} — skipping.");
                continue;
            }

            $this->info('Scanning: ' . basename($file));
            $handle = fopen($file, 'r');
            $header = fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== count($header)) {
                    continue;
                }
                $data     = array_combine($header, $row);
                $wcStatus = trim($data['order_status'] ?? '', '"');

                if (!isset($this->statusMap[$wcStatus])) {
                    continue; // wc-completed and others — skip
                }

                $wpOrderId     = $data['wp_order_id'];
                $tixelloId     = $ordersMap[$wpOrderId] ?? null;

                if (!$tixelloId) {
                    continue; // not imported
                }

                $toFix[$tixelloId] = $this->statusMap[$wcStatus];
            }

            fclose($handle);
        }

        $this->info('Orders to fix: ' . count($toFix));

        if (empty($toFix)) {
            $this->info('Nothing to fix.');
            return 0;
        }

        // Group by [order_status, payment_status, ticket_status] for bulk updates
        $groups = [];
        foreach ($toFix as $tixelloId => [$orderStatus, $paymentStatus, $ticketStatus]) {
            $key = "{$orderStatus}|{$paymentStatus}|{$ticketStatus}";
            $groups[$key]['order_status']   = $orderStatus;
            $groups[$key]['payment_status'] = $paymentStatus;
            $groups[$key]['ticket_status']  = $ticketStatus;
            $groups[$key]['ids'][]          = $tixelloId;
        }

        $totalOrders  = 0;
        $totalTickets = 0;

        foreach ($groups as $key => $group) {
            $ids          = $group['ids'];
            $orderStatus  = $group['order_status'];
            $paymentStatus = $group['payment_status'];
            $ticketStatus = $group['ticket_status'];

            $this->line("  [{$orderStatus}/{$paymentStatus}] " . count($ids) . " orders → tickets: {$ticketStatus}");

            if ($dryRun) {
                $ticketCount = DB::table('tickets')
                    ->whereIn('order_id', $ids)
                    ->where('marketplace_client_id', $clientId)
                    ->count();
                $this->line("    [DRY RUN] Would update " . count($ids) . " orders, {$ticketCount} tickets.");
                $totalOrders  += count($ids);
                $totalTickets += $ticketCount;
                continue;
            }

            foreach (array_chunk($ids, 500) as $chunk) {
                DB::table('orders')
                    ->whereIn('id', $chunk)
                    ->where('marketplace_client_id', $clientId)
                    ->update([
                        'status'         => $orderStatus,
                        'payment_status' => $paymentStatus,
                        'paid_at'        => null,
                        'updated_at'     => now(),
                    ]);

                $affected = DB::table('tickets')
                    ->whereIn('order_id', $chunk)
                    ->where('marketplace_client_id', $clientId)
                    ->update([
                        'status'     => $ticketStatus,
                        'updated_at' => now(),
                    ]);

                $totalOrders  += count($chunk);
                $totalTickets += $affected;
            }
        }

        $prefix = $dryRun ? '[DRY RUN] Would fix' : 'Fixed';
        $this->info("{$prefix}: {$totalOrders} orders, {$totalTickets} tickets.");

        return 0;
    }
}
