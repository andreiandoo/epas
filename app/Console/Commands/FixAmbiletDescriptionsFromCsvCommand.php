<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixAmbiletDescriptionsFromCsvCommand extends Command
{
    protected $signature = 'fix:ambilet-descriptions-csv
        {file : Path to CSV with wp_event_id,description}
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}';

    protected $description = 'Import event descriptions from CSV preserving line breaks, converting to proper HTML paragraphs';

    public function handle(): int
    {
        $file    = $this->argument('file');
        $dryRun  = $this->option('dry-run');

        $mapFile = storage_path('app/import_maps/events_map.json');
        if (! file_exists($mapFile)) {
            $this->error('events_map.json not found.');
            return 1;
        }
        $eventsMap = json_decode(file_get_contents($mapFile), true);

        $handle = fopen(base_path($file), 'r');
        if (! $handle) {
            $this->error("Cannot open file: {$file}");
            return 1;
        }

        $header = fgetcsv($handle, 0, ',', '"', '\\');

        $updated = $skipped = $notFound = $empty = 0;

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if (count($row) < 2) {
                continue;
            }

            // CSV has only 2 columns: wp_event_id, description
            // But description may contain commas, so fgetcsv may split into more columns
            // First element is always wp_event_id, everything else is the description
            $wpEventId = $row[0];
            $rawDesc   = count($row) === 2 ? $row[1] : implode(',', array_slice($row, 1));

            if (! $wpEventId) {
                continue;
            }

            $tixelloId = $eventsMap[$wpEventId] ?? null;
            if (! $tixelloId) {
                $notFound++;
                continue;
            }

            $rawDesc = trim($rawDesc);
            if ($rawDesc === '' || $rawDesc === 'NULL') {
                $empty++;
                continue;
            }

            $html = $this->convertToHtml($rawDesc);

            $jsonEncoded = json_encode(['ro' => $html], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($jsonEncoded === false) {
                $this->warn("JSON encode failed for WP#{$wpEventId} (Tixello #{$tixelloId}) — skipping.");
                $skipped++;
                continue;
            }

            if (! $dryRun) {
                DB::table('events')->where('id', $tixelloId)->update([
                    'description' => $jsonEncoded,
                    'updated_at'  => now(),
                ]);
            }

            $updated++;

            if ($updated % 200 === 0) {
                $this->line("Progress: {$updated} updated...");
            }
        }

        fclose($handle);

        $prefix = $dryRun ? '[DRY RUN] Would update' : 'Updated';
        $this->info("{$prefix}: {$updated} | Empty: {$empty} | Not in map: {$notFound} | Skipped: {$skipped}");

        return 0;
    }

    private function convertToHtml(string $text): string
    {
        // Remove separator lines (====, ----)
        $text = preg_replace('/={3,}/', '', $text);
        $text = preg_replace('/-{3,}/', '', $text);

        // Normalize non-breaking spaces
        $text = str_replace(["\xc2\xa0", "\xa0"], ' ', $text);
        $text = str_replace('&nbsp;', ' ', $text);

        // Convert existing <br> tags to newlines for uniform processing
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);

        // Normalize \r\n to \n
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        // Collapse 3+ newlines into 2 (paragraph break)
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        // Split on double newlines into paragraphs
        $paragraphs = preg_split('/\n{2}/', $text);

        $html = '';
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            // Convert single newlines within a paragraph to <br>
            $p = str_replace("\n", '<br>', $p);
            // Clean up multiple <br> in a row
            $p = preg_replace('/(<br>){3,}/', '<br><br>', $p);
            // Remove trailing/leading <br>
            $p = preg_replace('/^(<br>)+/', '', $p);
            $p = preg_replace('/(<br>)+$/', '', $p);
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            $html .= "<p>{$p}</p>";
        }

        return $html ?: "<p>{$text}</p>";
    }
}
