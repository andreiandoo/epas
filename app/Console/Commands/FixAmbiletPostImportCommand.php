<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAmbiletPostImportCommand extends Command
{
    protected $signature = 'fix:ambilet-post-import
        {files* : Path(s) to orders CSV file(s)}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}';

    protected $description = 'Post-import fixes: tickets→used, wc-processing→failed, newsletter for registered customers';

    public function handle(): int
    {
        $files    = $this->argument('files');
        $clientId = (int) $this->option('marketplace');
        $dryRun   = $this->option('dry-run');

        if (empty($files)) {
            $this->error('Provide at least one orders CSV file.');
            return 1;
        }

        foreach ($files as $f) {
            if (!file_exists($f)) {
                $this->error("File not found: {$f}");
                return 1;
            }
        }

        // =====================================================================
        // STEP 1: Mark all imported tickets as 'used' (events are historical)
        // =====================================================================
        $this->info('');
        $this->info('=== STEP 1: Tickets valid → used ===');

        if ($dryRun) {
            $count = DB::table('tickets')
                ->where('marketplace_client_id', $clientId)
                ->where('status', 'valid')
                ->whereRaw("JSON_EXTRACT(meta, '$.imported_from') = ?", ['ambilet'])
                ->count();
            $this->info("[DRY RUN] Would update {$count} tickets from 'valid' to 'used'.");
        } else {
            $affected = DB::table('tickets')
                ->where('marketplace_client_id', $clientId)
                ->where('status', 'valid')
                ->whereRaw("JSON_EXTRACT(meta, '$.imported_from') = ?", ['ambilet'])
                ->update(['status' => 'used', 'updated_at' => now()]);
            $this->info("Updated {$affected} tickets to 'used'.");
        }

        // =====================================================================
        // STEP 2: Mark wc-processing orders as 'failed' + their tickets 'cancelled'
        // =====================================================================
        $this->info('');
        $this->info('=== STEP 2: wc-processing orders → failed ===');

        // Load orders map for wp_order_id → tixello_order_id
        $mapFile = storage_path('app/import_maps/orders_map.json');
        if (!file_exists($mapFile)) {
            $this->warn('orders_map.json not found — skipping step 2.');
        } else {
            $ordersMap = json_decode(file_get_contents($mapFile), true) ?? [];
            $this->info('Loaded orders map: ' . count($ordersMap) . ' entries.');

            // Collect wp_order_ids where order_status is wc-processing
            $processingWpIds = [];
            foreach ($files as $file) {
                $handle = fopen($file, 'r');
                $header = fgetcsv($handle);
                while (($row = fgetcsv($handle)) !== false) {
                    $data = array_combine($header, $row);
                    $status = trim($data['order_status'] ?? '', '"');
                    if (stripos($status, 'processing') !== false) {
                        $processingWpIds[] = $data['wp_order_id'];
                    }
                }
                fclose($handle);
            }

            $this->info('Found ' . count($processingWpIds) . ' wc-processing orders in CSVs.');

            // Map to tixello order IDs
            $failedOrderIds = [];
            $unmapped = 0;
            foreach ($processingWpIds as $wpId) {
                if (isset($ordersMap[$wpId])) {
                    $failedOrderIds[] = $ordersMap[$wpId];
                } else {
                    $unmapped++;
                }
            }

            if ($unmapped > 0) {
                $this->warn("{$unmapped} wc-processing orders not found in orders_map.");
            }

            $this->info(count($failedOrderIds) . ' orders to mark as failed.');

            if (!empty($failedOrderIds)) {
                if ($dryRun) {
                    $this->info("[DRY RUN] Would set " . count($failedOrderIds) . " orders to 'failed'.");
                    $ticketCount = DB::table('tickets')
                        ->whereIn('order_id', $failedOrderIds)
                        ->where('marketplace_client_id', $clientId)
                        ->count();
                    $this->info("[DRY RUN] Would cancel {$ticketCount} tickets from those orders.");
                } else {
                    // Update orders in chunks
                    foreach (array_chunk($failedOrderIds, 500) as $chunk) {
                        DB::table('orders')
                            ->whereIn('id', $chunk)
                            ->where('marketplace_client_id', $clientId)
                            ->update([
                                'status'         => 'failed',
                                'payment_status' => 'failed',
                                'updated_at'     => now(),
                            ]);
                    }
                    $this->info('Orders updated to failed.');

                    // Cancel their tickets
                    $cancelledTickets = 0;
                    foreach (array_chunk($failedOrderIds, 500) as $chunk) {
                        $cancelledTickets += DB::table('tickets')
                            ->whereIn('order_id', $chunk)
                            ->where('marketplace_client_id', $clientId)
                            ->update([
                                'status'     => 'cancelled',
                                'updated_at' => now(),
                            ]);
                    }
                    $this->info("Cancelled {$cancelledTickets} tickets from failed orders.");
                }
            }
        }

        // =====================================================================
        // STEP 3: Enable newsletter for registered AmBilet customers
        // =====================================================================
        $this->info('');
        $this->info('=== STEP 3: Newsletter for registered customers ===');

        // Collect unique emails where customer_wp_user_id > 0
        $registeredEmails = [];
        foreach ($files as $file) {
            $handle = fopen($file, 'r');
            $header = fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                $data   = array_combine($header, $row);
                $wpUserId = (int) ($data['customer_wp_user_id'] ?? 0);
                if ($wpUserId > 0) {
                    $email = strtolower(trim($data['customer_email'] ?? ''));
                    if ($email) {
                        $registeredEmails[$email] = true;
                    }
                }
            }
            fclose($handle);
        }

        $emailList = array_keys($registeredEmails);
        $this->info('Found ' . count($emailList) . ' unique registered customer emails.');

        $notificationPrefs = json_encode([
            'reminders'  => true,
            'newsletter' => true,
            'favorites'  => true,
            'history'    => true,
            'marketing'  => true,
        ]);

        if ($dryRun) {
            // Count how many exist in marketplace_customers
            $existCount = 0;
            foreach (array_chunk($emailList, 500) as $chunk) {
                $existCount += DB::table('marketplace_customers')
                    ->whereIn('email', $chunk)
                    ->where('marketplace_client_id', $clientId)
                    ->count();
            }
            $this->info("[DRY RUN] Would enable newsletter for {$existCount} marketplace customers.");
        } else {
            $updated = 0;
            foreach (array_chunk($emailList, 500) as $chunk) {
                $updated += DB::table('marketplace_customers')
                    ->whereIn('email', $chunk)
                    ->where('marketplace_client_id', $clientId)
                    ->update([
                        'accepts_marketing'    => 1,
                        'marketing_consent_at' => DB::raw('created_at'),
                        'settings'             => DB::raw(
                            "JSON_SET(COALESCE(settings, '{}'), '$.notification_preferences', CAST('{$notificationPrefs}' AS JSON))"
                        ),
                        'updated_at'           => now(),
                    ]);
            }
            $this->info("Newsletter enabled for {$updated} customers.");
        }

        // =====================================================================
        // STEP 4: Unify 'confirmed' orders → 'completed'
        // =====================================================================
        $this->info('');
        $this->info('=== STEP 4: Confirmed orders → completed ===');

        if ($dryRun) {
            $count = DB::table('orders')
                ->where('marketplace_client_id', $clientId)
                ->where('status', 'confirmed')
                ->count();
            $this->info("[DRY RUN] Would update {$count} confirmed orders to 'completed'.");
        } else {
            $affected = DB::table('orders')
                ->where('marketplace_client_id', $clientId)
                ->where('status', 'confirmed')
                ->update(['status' => 'completed', 'updated_at' => now()]);
            $this->info("Updated {$affected} confirmed orders to 'completed'.");
        }

        // =====================================================================
        // STEP 5: Mark all imported customers with settings.imported_from
        // =====================================================================
        $this->info('');
        $this->info('=== STEP 5: Mark imported customers ===');

        if ($dryRun) {
            $count = DB::table('marketplace_customers')
                ->where('marketplace_client_id', $clientId)
                ->whereRaw("JSON_EXTRACT(meta, '$.imported_from') IS NULL OR JSON_EXTRACT(settings, '$.imported_from') IS NULL")
                ->whereRaw("EXISTS (SELECT 1 FROM orders o WHERE o.marketplace_customer_id = marketplace_customers.id AND o.source = 'legacy_import')")
                ->count();
            $this->info("[DRY RUN] Would mark ~{$count} customers as imported from ambilet.");
        } else {
            // Mark customers that have at least one legacy_import order
            $affected = DB::statement("
                UPDATE marketplace_customers mc
                SET mc.settings = JSON_SET(COALESCE(mc.settings, '{}'), '$.imported_from', 'ambilet'),
                    mc.updated_at = NOW()
                WHERE mc.marketplace_client_id = {$clientId}
                  AND EXISTS (
                      SELECT 1 FROM orders o
                      WHERE o.marketplace_customer_id = mc.id
                        AND o.source = 'legacy_import'
                  )
            ");
            // Count how many were marked
            $marked = DB::table('marketplace_customers')
                ->where('marketplace_client_id', $clientId)
                ->whereRaw("JSON_EXTRACT(settings, '$.imported_from') = ?", ['ambilet'])
                ->count();
            $this->info("Marked {$marked} customers as imported from ambilet.");
        }

        $this->info('');
        $this->info('All post-import fixes complete.');

        return 0;
    }
}
