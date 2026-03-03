<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAmbiletEventImagesCommand extends Command
{
    protected $signature = 'fix:ambilet-event-images
        {file : Path to events_images.csv (wp_event_id,image_url)}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}';

    protected $description = 'Update poster_url on imported AmBilet events from a CSV mapping file';

    public function handle(): int
    {
        $file     = $this->argument('file');
        $clientId = (int) $this->option('marketplace');
        $dryRun   = $this->option('dry-run');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        // Load events map: wp_event_id => tixello_event_id
        $mapFile = storage_path('app/import_maps/events_map.json');
        if (!file_exists($mapFile)) {
            $this->error('events_map.json not found. Run import:ambilet-events first.');
            return 1;
        }

        $eventsMap = json_decode(file_get_contents($mapFile), true) ?? [];
        $this->info('Loaded events map: ' . count($eventsMap) . ' entries.');

        $handle  = fopen($file, 'r');
        $header  = fgetcsv($handle);
        $updated = $skipped = $noMap = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $data      = array_combine($header, $row);
            $wpEventId = $data['wp_event_id'];
            $imageUrl  = trim($data['image_url'] ?? '');

            if (!$imageUrl || $imageUrl === 'NULL') {
                $skipped++;
                continue;
            }

            $tixelloEventId = $eventsMap[$wpEventId] ?? null;
            if (!$tixelloEventId) {
                $noMap++;
                continue;
            }

            if ($dryRun) {
                $this->line("[DRY RUN] Event #{$tixelloEventId} (wp:{$wpEventId}) → {$imageUrl}");
                $updated++;
                continue;
            }

            $affected = DB::table('events')
                ->where('id', $tixelloEventId)
                ->where('marketplace_client_id', $clientId)
                ->whereNull('poster_url')
                ->update([
                    'poster_url'  => $imageUrl,
                    'updated_at'  => now(),
                ]);

            if ($affected) {
                $updated++;
            } else {
                $skipped++; // already has a poster_url
            }

            if ($updated % 500 === 0 && $updated > 0) {
                $this->line("Progress: {$updated} updated...");
            }
        }

        fclose($handle);

        $this->info("Done! Updated: {$updated} | Skipped: {$skipped} | Not in map: {$noMap}");

        return 0;
    }
}
