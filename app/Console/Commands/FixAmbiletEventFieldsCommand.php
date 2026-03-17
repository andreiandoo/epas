<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAmbiletEventFieldsCommand extends Command
{
    protected $signature = 'fix:ambilet-event-fields
        {csv : Path to event_fields_fix.csv}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}
        {--skip-description : Do not update description}
        {--skip-images : Do not update hero_image_url / poster_url}
        {--skip-urls : Do not update website_url / facebook_url}
        {--skip-booleans : Do not update is_sold_out / is_postponed / is_cancelled / door_sales_only}';

    protected $description = 'Fix imported AmBilet events: description (HTML), images, URLs, and boolean flags';

    public function handle(): int
    {
        $csvFile  = $this->argument('csv');
        $clientId = (int) $this->option('marketplace');
        $dryRun   = $this->option('dry-run');

        if (!file_exists($csvFile)) {
            $this->error("CSV not found: {$csvFile}");
            return 1;
        }

        $mapFile = storage_path('app/import_maps/events_map.json');
        if (!file_exists($mapFile)) {
            $this->error('events_map.json not found.');
            return 1;
        }
        $eventsMap = json_decode(file_get_contents($mapFile), true) ?? [];
        $this->info('Loaded events map: ' . count($eventsMap) . ' entries.');

        $this->info('Parsing CSV...');
        $fh     = fopen($csvFile, 'r');
        $header = fgetcsv($fh, 0, ',', '"', '\\');

        $updated = $skipped = $notFound = 0;

        while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }
            $data      = array_combine($header, $row);
            $wpEventId = $data['wp_event_id'];
            $tixelloId = $eventsMap[$wpEventId] ?? null;

            if (!$tixelloId) {
                $notFound++;
                continue;
            }

            $fields = [];

            // Description — stored as HTML, wrapped in JSON {"ro": "..."}
            if (!$this->option('skip-description')) {
                $desc = $this->n($data['description'] ?? null);
                if ($desc) {
                    $fields['description'] = json_encode(['ro' => $desc], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            // Images — only accept real URLs, skip bare IDs or empty values
            if (!$this->option('skip-images')) {
                $heroUrl   = $this->n($data['hero_image_url'] ?? null);
                $posterUrl = $this->n($data['poster_url'] ?? null);
                if ($heroUrl && filter_var($heroUrl, FILTER_VALIDATE_URL)) {
                    $fields['hero_image_url'] = $heroUrl;
                }
                if ($posterUrl && filter_var($posterUrl, FILTER_VALIDATE_URL)) {
                    $fields['poster_url'] = $posterUrl;
                }
            }

            // website_url / facebook_url
            if (!$this->option('skip-urls')) {
                $website  = $this->n($data['website'] ?? null);
                $facebook = $this->n($data['facebook'] ?? null);
                if ($website) {
                    $fields['website_url']  = $website;
                }
                if ($facebook) {
                    $fields['facebook_url'] = $facebook;
                }
            }

            // Boolean flags — only set to 1, never reset existing 1→0
            if (!$this->option('skip-booleans')) {
                if (trim($data['sold_out']              ?? '0') === '1') { $fields['is_sold_out']     = 1; }
                if (trim($data['amanat']                ?? '0') === '1') { $fields['is_postponed']    = 1; }
                if (trim($data['anulat']                ?? '0') === '1') { $fields['is_cancelled']    = 1; }
                if (trim($data['bilete_doar_la_intrare'] ?? '0') === '1') { $fields['door_sales_only'] = 1; }
            }

            if (empty($fields)) {
                $skipped++;
                continue;
            }

            $fields['updated_at'] = now();

            if ($dryRun) {
                $keys = implode(', ', array_keys($fields));
                $this->line("[DRY RUN] Event #{$tixelloId} (wp:{$wpEventId}) → {$keys}");
                $updated++;
                continue;
            }

            DB::table('events')
                ->where('id', $tixelloId)
                ->where('marketplace_client_id', $clientId)
                ->update($fields);

            $updated++;

            if ($updated % 100 === 0) {
                $this->line("Progress: {$updated} updated...");
            }
        }

        fclose($fh);

        $prefix = $dryRun ? '[DRY RUN] Would update' : 'Updated';
        $this->info("{$prefix}: {$updated} | Skipped (no data): {$skipped} | Not in map: {$notFound}");

        return 0;
    }

    private function n(?string $v): ?string
    {
        return ($v !== null && $v !== '' && $v !== 'NULL') ? $v : null;
    }
}
