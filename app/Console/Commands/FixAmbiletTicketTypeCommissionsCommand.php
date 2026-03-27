<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAmbiletTicketTypeCommissionsCommand extends Command
{
    protected $signature = 'fix:ambilet-ticket-type-commissions
        {file : Path to CSV with wp_product_id,fee_amount,min_quantity,max_quantity}
        {--dry-run}';

    protected $description = 'Update ticket type commissions and order quantity limits from ambilet product data';

    public function handle(): int
    {
        $file   = $this->argument('file');
        $dryRun = $this->option('dry-run');

        $mapFile = storage_path('app/import_maps/ticket_types_map.json');
        if (! file_exists($mapFile)) {
            $this->error('ticket_types_map.json not found.');
            return 1;
        }
        $ttMap = json_decode(file_get_contents($mapFile), true);

        $handle = fopen(base_path($file), 'r');
        if (! $handle) {
            $this->error("Cannot open file: {$file}");
            return 1;
        }

        fgetcsv($handle); // skip header

        $updated = $skipped = $notFound = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $wpProductId = trim($row[0] ?? '');
            $feeAmount   = trim($row[1] ?? '');
            $minQty      = trim($row[2] ?? '');
            $maxQty      = trim($row[3] ?? '');

            if (! $wpProductId) {
                continue;
            }

            $tixelloTtId = $ttMap[$wpProductId] ?? null;
            if (! $tixelloTtId) {
                $notFound++;
                continue;
            }

            $fields = [];

            // Parse commission
            if ($feeAmount !== '') {
                if (str_contains($feeAmount, '%')) {
                    // Percentage: "6%" or "5.5%"
                    $rate = (float) str_replace(['%', ','], ['', '.'], $feeAmount);
                    $fields['commission_type']  = 'percentage';
                    $fields['commission_rate']  = $rate;
                    $fields['commission_fixed'] = null;
                    $fields['commission_mode']  = 'added_on_top';
                } else {
                    // Fixed: "2,5" or "3.50"
                    $fixed = (float) str_replace(',', '.', $feeAmount);
                    $fields['commission_type']  = 'fixed';
                    $fields['commission_rate']  = null;
                    $fields['commission_fixed'] = $fixed;
                    $fields['commission_mode']  = 'added_on_top';
                }
            }

            // Parse min/max quantity
            if ($minQty !== '') {
                $fields['min_per_order'] = max(1, (int) $minQty);
            }
            if ($maxQty !== '') {
                $fields['max_per_order'] = max(1, (int) $maxQty);
            }

            if (empty($fields)) {
                $skipped++;
                continue;
            }

            $fields['updated_at'] = now();

            if (! $dryRun) {
                DB::table('ticket_types')->where('id', $tixelloTtId)->update($fields);
            }

            $updated++;

            if ($updated % 500 === 0) {
                $this->line("Progress: {$updated} updated...");
            }
        }

        fclose($handle);

        $prefix = $dryRun ? '[DRY RUN] Would update' : 'Updated';
        $this->info("{$prefix}: {$updated} | Skipped (no data): {$skipped} | Not in map: {$notFound}");

        return 0;
    }
}
