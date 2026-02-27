<?php

namespace App\Console\Commands;

use App\Models\MarketplaceCustomer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportAmbiletOrdersCommand extends Command
{
    protected $signature = 'import:ambilet-orders
        {files* : Path(s) to orders CSV file(s) — can pass multiple files}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}
        {--fresh : Ignore existing maps and re-process all rows}';

    protected $description = 'Import AmBilet orders + customers from CSV into Tixello';

    public function handle(): int
    {
        $files    = $this->argument('files');
        $clientId = (int) $this->option('marketplace');
        $dryRun   = $this->option('dry-run');
        $fresh    = $this->option('fresh');

        if (empty($files)) {
            $this->error('Provide at least one orders CSV file.');
            return 1;
        }

        $mapDir = storage_path('app/import_maps');
        if (!is_dir($mapDir)) {
            mkdir($mapDir, 0755, true);
        }
        $ordersMapFile    = $mapDir . '/orders_map.json';
        $customersMapFile = $mapDir . '/customers_map.json';

        $ordersMap    = [];
        $customersMap = [];

        if (!$fresh) {
            if (file_exists($ordersMapFile)) {
                $ordersMap = json_decode(file_get_contents($ordersMapFile), true) ?? [];
                $this->info('Loaded orders map: ' . count($ordersMap) . ' entries.');
            }
            if (file_exists($customersMapFile)) {
                $customersMap = json_decode(file_get_contents($customersMapFile), true) ?? [];
                $this->info('Loaded customers map: ' . count($customersMap) . ' entries.');
            }
        } else {
            if (file_exists($ordersMapFile)) {
                unlink($ordersMapFile);
            }
            if (file_exists($customersMapFile)) {
                unlink($customersMapFile);
            }
            $this->info('Deleted existing maps (--fresh).');
        }

        $created = $skipped = $failed = 0;

        foreach ($files as $file) {
            if (!file_exists($file)) {
                $this->warn("File not found: {$file} — skipping.");
                continue;
            }

            $this->info('Processing: ' . basename($file));

            $handle = fopen($file, 'r');
            $header = fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                $data      = array_combine($header, $row);
                $wpOrderId = $data['wp_order_id'];

                if (isset($ordersMap[$wpOrderId])) {
                    $skipped++;
                    continue;
                }

                $email = strtolower(trim($data['customer_email'] ?? ''));
                if (!$email) {
                    $this->warn("Skipping order {$wpOrderId}: no customer email.");
                    $failed++;
                    continue;
                }

                // first_name and last_name are NOT NULL in marketplace_customers — use '-' as fallback
                $firstName = $this->n($data['billing_first_name']) ?? '-';
                $lastName  = $this->n($data['billing_last_name'])  ?? '-';

                // Upsert MarketplaceCustomer by email
                $customerId = $customersMap[$email] ?? null;
                if (!$customerId && !$dryRun) {
                    $customer = MarketplaceCustomer::firstOrCreate(
                        ['marketplace_client_id' => $clientId, 'email' => $email],
                        [
                            'first_name'        => $firstName,
                            'last_name'         => $lastName,
                            'phone'             => $this->n($data['billing_phone']),
                            'status'            => 'active',
                            'email_verified_at' => now(),
                            // No password — historical customers use "forgot password" to activate
                        ]
                    );
                    $customerId           = $customer->id;
                    $customersMap[$email] = $customerId;
                } elseif (!$customerId && $dryRun) {
                    $customerId           = 0;
                    $customersMap[$email] = 0;
                }

                $paidAt    = $this->parseDate($data['paid_at']) ?? $this->parseDate($data['created_at']);
                $createdAt = $this->parseDate($data['created_at']) ?? now()->toDateTimeString();

                $customerName = trim($firstName . ' ' . $lastName);
                if ($customerName === '- -') {
                    $customerName = null;
                }

                // Note: marketplace_organizer_id and marketplace_event_id are populated
                // in a post-import step at the end of import:ambilet-tickets,
                // once we know which tickets (→ events → organizers) belong to each order.
                $orderData = [
                    'marketplace_client_id'   => $clientId,
                    'marketplace_customer_id' => $customerId,
                    'order_number'            => 'AMB-' . $wpOrderId,
                    'customer_email'          => $email,
                    'customer_name'           => $customerName,
                    'customer_phone'          => $this->n($data['billing_phone']),
                    'total'                   => is_numeric($data['order_total']) ? (float) $data['order_total'] : 0,
                    'subtotal'                => is_numeric($data['order_total']) ? (float) $data['order_total'] : 0,
                    'discount_amount'         => 0,
                    'commission_rate'         => 0,
                    'commission_amount'       => 0,
                    'currency'                => 'RON',
                    'status'                  => 'completed',
                    'payment_status'          => 'paid',
                    'payment_processor'       => $this->n($data['payment_method']),
                    'source'                  => 'legacy_import',
                    'paid_at'                 => $paidAt,
                    'metadata'                => json_encode([
                        'wp_order_id'   => $wpOrderId,
                        'imported_from' => 'ambilet',
                    ]),
                    'created_at'              => $createdAt,
                    'updated_at'              => $createdAt,
                ];

                if ($dryRun) {
                    $this->line("[DRY RUN] Order AMB-{$wpOrderId} for {$email} ({$createdAt})");
                    $ordersMap[$wpOrderId] = 0;
                    $created++;
                    continue;
                }

                try {
                    $orderId               = DB::table('orders')->insertGetId($orderData);
                    $ordersMap[$wpOrderId] = $orderId;
                    $created++;

                    if ($created % 500 === 0) {
                        file_put_contents($ordersMapFile, json_encode($ordersMap, JSON_PRETTY_PRINT));
                        file_put_contents($customersMapFile, json_encode($customersMap, JSON_PRETTY_PRINT));
                        $this->line("Progress: {$created} created, {$skipped} skipped...");
                    }
                } catch (\Exception $e) {
                    $this->error("Failed order {$wpOrderId}: " . $e->getMessage());
                    $failed++;
                }
            }

            fclose($handle);
            $this->line('Finished: ' . basename($file));
        }

        file_put_contents($ordersMapFile, json_encode($ordersMap, JSON_PRETTY_PRINT));
        file_put_contents($customersMapFile, json_encode($customersMap, JSON_PRETTY_PRINT));

        $this->info("Done! Created: {$created} | Skipped: {$skipped} | Failed: {$failed}");

        // Update customer cached stats: total_orders, total_spent
        if (!$dryRun && $created > 0) {
            $this->info('Updating customer stats (total_orders, total_spent)...');
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
            $this->info('Customer stats updated.');
        }

        return 0;
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
