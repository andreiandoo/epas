<?php

namespace App\Console\Commands;

use App\Models\MarketplaceTicketType;
use Illuminate\Console\Command;

class ImportAmbiletTicketTypesCommand extends Command
{
    protected $signature = 'import:ambilet-ticket-types
        {file : Path to ticket_types.csv}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}
        {--fresh : Ignore existing map and re-process all rows}';

    protected $description = 'Import AmBilet ticket types from CSV into Tixello MarketplaceTicketType';

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

        $dir           = dirname($file);
        $eventsMapFile = $dir . '/events_map.json';
        $mapFile       = $dir . '/ticket_types_map.json';

        if (!file_exists($eventsMapFile)) {
            $this->error('events_map.json not found. Run import:ambilet-events first.');
            return 1;
        }

        $eventsMap = json_decode(file_get_contents($eventsMapFile), true) ?? [];
        $this->info('Loaded events map: ' . count($eventsMap) . ' entries.');

        $map = [];
        if (!$fresh && file_exists($mapFile)) {
            $map = json_decode(file_get_contents($mapFile), true) ?? [];
            $this->info('Loaded existing ticket types map: ' . count($map) . ' entries.');
        } elseif ($fresh && file_exists($mapFile)) {
            unlink($mapFile);
            $this->info('Deleted existing ticket types map (--fresh).');
        }

        $handle  = fopen($file, 'r');
        $header  = fgetcsv($handle);
        $created = $skipped = $failed = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data        = array_combine($header, $row);
            $wpProductId = $data['wp_product_id'];

            if (isset($map[$wpProductId])) {
                $skipped++;
                continue;
            }

            $wpEventId       = $data['wp_event_id'];
            $tixelloEventId  = $eventsMap[$wpEventId] ?? null;

            if (!$tixelloEventId) {
                $this->warn("No Tixello event for wp_event_id={$wpEventId} (ticket type: {$data['name']}) — skipping.");
                $failed++;
                continue;
            }

            $price = is_numeric($data['price']) ? (float) $data['price'] : 0.0;
            $qty   = ($data['stock_qty'] !== 'NULL' && is_numeric($data['stock_qty']))
                ? (int) $data['stock_qty']
                : null;

            $ttData = [
                'marketplace_event_id'       => $tixelloEventId,
                'name'                       => $data['name'],
                'price'                      => $price,
                'currency'                   => 'RON',
                'quantity'                   => $qty,
                'quantity_sold'              => 0,
                'quantity_reserved'          => 0,
                'status'                     => 'sold_out',
                'is_visible'                 => false,
                'is_refundable'              => false,
                'sort_order'                 => 0,
                'autostart_when_previous_sold_out' => false,
            ];

            if ($dryRun) {
                $this->line("[DRY RUN] Would create ticket type: {$data['name']} for event #{$tixelloEventId}");
                $map[$wpProductId] = 0;
                $created++;
                continue;
            }

            try {
                $tt                     = MarketplaceTicketType::create($ttData);
                $map[$wpProductId]      = $tt->id;
                $created++;

                if ($created % 500 === 0) {
                    file_put_contents($mapFile, json_encode($map, JSON_PRETTY_PRINT));
                    $this->line("Progress: {$created} created, {$skipped} skipped...");
                }
            } catch (\Exception $e) {
                $this->error("Failed ticket type '{$data['name']}': " . $e->getMessage());
                $failed++;
            }
        }

        fclose($handle);
        file_put_contents($mapFile, json_encode($map, JSON_PRETTY_PRINT));

        $this->info("Done! Created: {$created} | Skipped: {$skipped} | Failed: {$failed}");
        $this->info("Map saved to: {$mapFile}");

        return 0;
    }
}
