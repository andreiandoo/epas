<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportAmbiletOrderAttributionCommand extends Command
{
    protected $signature = 'import:ambilet-order-attribution
        {file : Path to order_attribution.csv}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}';

    protected $description = 'Enrich imported AmBilet orders with WooCommerce attribution data (device, UTM, referrer, IP)';

    public function handle(): int
    {
        $file     = $this->argument('file');
        $clientId = (int) $this->option('marketplace');
        $dryRun   = $this->option('dry-run');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        // Load orders map: wp_order_id => tixello_order_id
        $mapFile = storage_path('app/import_maps/orders_map.json');
        if (! file_exists($mapFile)) {
            $this->error('orders_map.json not found.');
            return 1;
        }
        $ordersMap = json_decode(file_get_contents($mapFile), true) ?? [];
        $this->info('Loaded orders map: ' . count($ordersMap) . ' entries.');

        $fh     = fopen($file, 'r');
        $header = fgetcsv($fh, 0, ',', '"', '\\');

        $updated = $skipped = $notFound = $noData = 0;

        while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }

            $data      = array_combine($header, $row);
            $wpOrderId = $data['wp_order_id'];
            $tixelloId = $ordersMap[$wpOrderId] ?? null;

            if (! $tixelloId) {
                $notFound++;
                continue;
            }

            // Build attribution data from non-NULL fields
            $attribution = [];
            $fields = [
                'device_type', 'source_type', 'utm_source', 'utm_medium',
                'utm_content', 'referrer', 'landing_page', 'session_count',
                'session_pages', 'ip_address', 'user_agent',
            ];

            foreach ($fields as $field) {
                $value = $data[$field] ?? null;
                if ($value !== null && $value !== '' && $value !== 'NULL') {
                    $attribution[$field] = is_numeric($value) ? (int) $value : $value;
                }
            }

            if (empty($attribution)) {
                $noData++;
                continue;
            }

            if (! $dryRun) {
                // Merge into existing meta.attribution
                $order = DB::table('orders')->where('id', $tixelloId)->select('meta')->first();
                $meta = $order ? json_decode($order->meta ?? '{}', true) : [];
                $meta['attribution'] = $attribution;

                DB::table('orders')->where('id', $tixelloId)->update([
                    'meta'       => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
            }

            $updated++;

            if ($updated % 5000 === 0) {
                $this->line("Progress: {$updated} enriched...");
            }
        }

        fclose($fh);

        $prefix = $dryRun ? '[DRY RUN] Would enrich' : 'Enriched';
        $this->info("{$prefix}: {$updated} | No attribution data: {$noData} | Not in map: {$notFound}");

        return 0;
    }
}
