<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class DownloadAmbiletImagesCommand extends Command
{
    protected $signature = 'fix:download-ambilet-images
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}
        {--timeout=30 : HTTP timeout in seconds per image}
        {--concurrency=5 : Not used yet, reserved}';

    protected $description = 'Download external AmBilet event poster images to local storage before ambilet.ro shuts down';

    public function handle(): int
    {
        $clientId = (int) $this->option('marketplace');
        $dryRun   = $this->option('dry-run');
        $timeout  = (int) $this->option('timeout');

        // Find all events with external poster URLs
        $events = DB::table('events')
            ->where('marketplace_client_id', $clientId)
            ->where('poster_url', 'LIKE', 'http%')
            ->select('id', 'poster_url')
            ->get();

        $this->info("Found {$events->count()} events with external poster URLs.");

        if ($events->isEmpty()) {
            $this->info('Nothing to download.');
            return 0;
        }

        // Ensure target directory exists
        $directory = 'events/posters';
        if (!$dryRun) {
            Storage::disk('public')->makeDirectory($directory);
        }

        $downloaded = 0;
        $skipped    = 0;
        $failed     = 0;
        $failedList = [];

        $bar = $this->output->createProgressBar($events->count());
        $bar->start();

        foreach ($events as $event) {
            $url = $event->poster_url;

            // Determine file extension from URL
            $pathInfo  = pathinfo(parse_url($url, PHP_URL_PATH));
            $extension = strtolower($pathInfo['extension'] ?? 'jpg');

            // Sanitize extension
            if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $extension = 'jpg';
            }

            $filename     = "ambilet-{$event->id}.{$extension}";
            $relativePath = "{$directory}/{$filename}";

            // Skip if already downloaded
            if (!$dryRun && Storage::disk('public')->exists($relativePath)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            if ($dryRun) {
                $this->line('');
                $this->line("[DRY RUN] Event #{$event->id}: {$url} → {$relativePath}");
                $downloaded++;
                $bar->advance();
                continue;
            }

            try {
                $response = Http::timeout($timeout)
                    ->withOptions(['verify' => false])
                    ->get($url);

                if (!$response->successful()) {
                    $failed++;
                    $failedList[] = "Event #{$event->id}: HTTP {$response->status()} — {$url}";
                    $bar->advance();
                    continue;
                }

                $contentType = $response->header('Content-Type');
                if ($contentType && !str_starts_with($contentType, 'image/')) {
                    $failed++;
                    $failedList[] = "Event #{$event->id}: Not an image ({$contentType}) — {$url}";
                    $bar->advance();
                    continue;
                }

                // Store the image
                Storage::disk('public')->put($relativePath, $response->body());

                // Update the event poster_url to local path
                DB::table('events')
                    ->where('id', $event->id)
                    ->update([
                        'poster_url' => $relativePath,
                        'updated_at' => now(),
                    ]);

                $downloaded++;

            } catch (\Exception $e) {
                $failed++;
                $failedList[] = "Event #{$event->id}: {$e->getMessage()} — {$url}";
            }

            $bar->advance();

            // Progress report every 100
            if ($downloaded > 0 && $downloaded % 100 === 0) {
                $this->line('');
                $this->info("Progress: {$downloaded} downloaded, {$failed} failed...");
            }
        }

        $bar->finish();
        $this->line('');
        $this->line('');

        $this->info("Done! Downloaded: {$downloaded} | Skipped (already local): {$skipped} | Failed: {$failed}");

        if (!empty($failedList)) {
            $this->line('');
            $this->warn('Failed downloads:');
            foreach ($failedList as $msg) {
                $this->line("  - {$msg}");
            }

            // Save failed list to file for retry
            $failedLog = storage_path('app/import_maps/failed_image_downloads.txt');
            file_put_contents($failedLog, implode("\n", $failedList));
            $this->info("Failed list saved to: {$failedLog}");
        }

        return 0;
    }
}
