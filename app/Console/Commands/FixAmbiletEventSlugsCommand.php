<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAmbiletEventSlugsCommand extends Command
{
    protected $signature = 'fix:ambilet-event-slugs
        {file : Path to events CSV with wp_event_id and wp_slug columns}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}';

    protected $description = 'Fix imported AmBilet event slugs by restoring original WordPress slugs';

    public function handle(): int
    {
        $file     = $this->argument('file');
        $clientId = (int) $this->option('marketplace');
        $dryRun   = $this->option('dry-run');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        // Load events map (wp_event_id => tixello_event_id)
        $mapFile = storage_path('app/import_maps/events_map.json');
        if (!file_exists($mapFile)) {
            $this->error("Events map not found: {$mapFile}");
            return 1;
        }

        $eventsMap = json_decode(file_get_contents($mapFile), true);
        $this->info("Loaded events map: " . count($eventsMap) . " entries.");

        // Read CSV
        $handle = fopen($file, 'r');
        $headers = fgetcsv($handle);

        $slugCol    = array_search('wp_slug', $headers);
        $wpIdCol    = array_search('wp_event_id', $headers);

        if ($slugCol === false || $wpIdCol === false) {
            $this->error("CSV must have 'wp_event_id' and 'wp_slug' columns.");
            fclose($handle);
            return 1;
        }

        // Collect all slugs from CSV
        $csvSlugs = [];
        while (($row = fgetcsv($handle)) !== false) {
            $wpId = trim($row[$wpIdCol]);
            $slug = trim($row[$slugCol]);
            if ($wpId && $slug) {
                $csvSlugs[$wpId] = $slug;
            }
        }
        fclose($handle);

        $this->info("CSV contains " . count($csvSlugs) . " events with slugs.");

        // Get existing slugs in DB to check for conflicts
        $existingSlugs = DB::table('events')
            ->pluck('slug', 'id')
            ->toArray();

        $slugToId = array_flip($existingSlugs);

        $updated    = 0;
        $skipped    = 0;
        $conflicts  = 0;
        $notInMap   = 0;

        foreach ($csvSlugs as $wpId => $wpSlug) {
            $tixelloId = $eventsMap[$wpId] ?? null;

            if (!$tixelloId) {
                $notInMap++;
                continue;
            }

            $currentSlug = $existingSlugs[$tixelloId] ?? null;

            // Already has the correct slug
            if ($currentSlug === $wpSlug) {
                $skipped++;
                continue;
            }

            // Check for slug conflict with another event
            $conflictId = $slugToId[$wpSlug] ?? null;
            if ($conflictId && $conflictId != $tixelloId) {
                // Another event already has this slug - append wp_id to make unique
                $wpSlug = $wpSlug . '-' . $wpId;

                // Check again
                if (isset($slugToId[$wpSlug])) {
                    $conflicts++;
                    if ($dryRun) {
                        $this->warn("[CONFLICT] Event #{$tixelloId} (wp:{$wpId}): slug '{$wpSlug}' still conflicts, skipping.");
                    }
                    continue;
                }

                $conflicts++;
                if ($dryRun) {
                    $this->warn("[CONFLICT RESOLVED] Event #{$tixelloId} (wp:{$wpId}): appended wp_id → '{$wpSlug}'");
                }
            }

            if (!$dryRun) {
                DB::table('events')
                    ->where('id', $tixelloId)
                    ->where('marketplace_client_id', $clientId)
                    ->update([
                        'slug'       => $wpSlug,
                        'updated_at' => now(),
                    ]);

                // Update lookup maps
                if ($currentSlug) {
                    unset($slugToId[$currentSlug]);
                }
                $slugToId[$wpSlug]          = $tixelloId;
                $existingSlugs[$tixelloId]  = $wpSlug;
            }

            $updated++;
        }

        $prefix = $dryRun ? '[DRY RUN] ' : '';
        $this->info("{$prefix}Updated: {$updated} | Already correct: {$skipped} | Conflicts resolved: {$conflicts} | Not in map: {$notInMap}");

        return 0;
    }
}
