<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAmbiletEventFieldsCommand extends Command
{
    protected $signature = 'fix:ambilet-event-fields
        {csv : Path to event_fields_fix.csv (wp_event_id, description, hero_image_url, poster_url, sold_out, website, facebook)}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}
        {--skip-description : Do not update description}
        {--skip-images : Do not update hero_image_url / poster_url}
        {--skip-urls : Do not update website_url / facebook_url}
        {--skip-sold-out : Do not update is_sold_out}';

    protected $description = 'Fix imported AmBilet events: set description, images, sold_out, website_url, facebook_url';

    public function handle(): int
    {
        $csvFile  = $this->argument('csv');
        $clientId = (int) $this->option('marketplace');
        $dryRun   = $this->option('dry-run');

        if (!file_exists($csvFile)) {
            $this->error("CSV not found: {$csvFile}");
            return 1;
        }

        // Load events_map.json: wp_event_id → tixello_event_id
        $mapFile = storage_path('app/import_maps/events_map.json');
        if (!file_exists($mapFile)) {
            $this->error('events_map.json not found.');
            return 1;
        }
        $eventsMap = json_decode(file_get_contents($mapFile), true) ?? [];
        $this->info('Loaded events map: ' . count($eventsMap) . ' entries.');

        // Parse CSV
        $this->info('Parsing CSV...');
        $fh     = fopen($csvFile, 'r');
        $header = fgetcsv($fh, 0, ',', '"', '\\');

        $updated = $skipped = $notFound = 0;

        while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }
            $data       = array_combine($header, $row);
            $wpEventId  = $data['wp_event_id'];
            $tixelloId  = $eventsMap[$wpEventId] ?? null;

            if (!$tixelloId) {
                $notFound++;
                continue;
            }

            $fields = [];

            // Description → JSON {"ro": "..."}
            if (!$this->option('skip-description')) {
                $desc = $this->n($data['description']);
                if ($desc) {
                    $fields['description'] = json_encode(['ro' => $desc], JSON_UNESCAPED_UNICODE);
                }
            }

            // Images
            if (!$this->option('skip-images')) {
                $heroUrl   = $this->n($data['hero_image_url']);
                $posterUrl = $this->n($data['poster_url']);

                // Only set if it looks like a real URL (not just a number / local path)
                if ($heroUrl && filter_var($heroUrl, FILTER_VALIDATE_URL)) {
                    $fields['hero_image_url'] = $heroUrl;
                }
                if ($posterUrl && filter_var($posterUrl, FILTER_VALIDATE_URL)) {
                    $fields['poster_url'] = $posterUrl;
                }
            }

            // sold_out
            if (!$this->option('skip-sold-out')) {
                $soldOut = trim($data['sold_out'] ?? '0');
                if ($soldOut === '1') {
                    $fields['is_sold_out'] = 1;
                }
            }

            // website_url / facebook_url
            if (!$this->option('skip-urls')) {
                $website  = $this->n($data['website']);
                $facebook = $this->n($data['facebook']);
                if ($website) {
                    $fields['website_url']  = $website;
                }
                if ($facebook) {
                    $fields['facebook_url'] = $facebook;
                }
            }

            if (empty($fields)) {
                $skipped++;
                continue;
            }

            $fields['updated_at'] = now();

            if ($dryRun) {
                $keys = implode(', ', array_keys($fields));
                $this->line("[DRY RUN] Event #{$tixelloId} (wp:{$wpEventId}) → update: {$keys}");
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
        $this->info("{$prefix}: {$updated} events | Skipped (no data): {$skipped} | Not in map: {$notFound}");

        return 0;
    }

    private function n(?string $v): ?string
    {
        return ($v !== null && $v !== '' && $v !== 'NULL') ? $v : null;
    }
}
