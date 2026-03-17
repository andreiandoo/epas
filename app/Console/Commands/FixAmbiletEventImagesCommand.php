<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FixAmbiletEventImagesCommand extends Command
{
    protected $signature = 'fix:ambilet-event-images
        {--marketplace=1 : marketplace_client_id}
        {--dry-run}
        {--force : Re-download even if local file already exists}
        {--hero-only : Only process hero images}
        {--poster-only : Only process poster images}
        {--quality=82 : WebP quality (1-100)}';

    protected $description = 'Download external AmBilet event images, convert to WebP and store locally';

    public function handle(): int
    {
        $clientId   = (int) $this->option('marketplace');
        $dryRun     = $this->option('dry-run');
        $force      = $this->option('force');
        $quality    = (int) $this->option('quality');
        $heroOnly   = $this->option('hero-only');
        $posterOnly = $this->option('poster-only');

        if (!extension_loaded('gd')) {
            $this->error('GD extension is required for WebP conversion.');
            return 1;
        }

        $query = DB::table('events')
            ->where('marketplace_client_id', $clientId)
            ->where(function ($q) use ($heroOnly, $posterOnly) {
                if ($posterOnly) {
                    $q->where('poster_url', 'like', 'http%');
                } elseif ($heroOnly) {
                    $q->where('hero_image_url', 'like', 'http%');
                } else {
                    $q->where('hero_image_url', 'like', 'http%')
                      ->orWhere('poster_url', 'like', 'http%');
                }
            })
            ->select('id', 'hero_image_url', 'poster_url');

        $total = $query->count();
        $this->info("Found {$total} events with external image URLs.");

        if ($total === 0) {
            $this->info('Nothing to process.');
            return 0;
        }

        $processed = $failed = $skipped = 0;

        $query->orderBy('id')->chunk(50, function ($events) use (
            $dryRun, $force, $quality, $heroOnly, $posterOnly,
            &$processed, &$failed, &$skipped
        ) {
            foreach ($events as $event) {
                // Hero image
                if (!$posterOnly && $event->hero_image_url && str_starts_with($event->hero_image_url, 'http')) {
                    $result = $this->processImage($event->hero_image_url, 'events/hero', $dryRun, $force, $quality);
                    if ($result === null) {
                        $skipped++;
                    } elseif ($result === false) {
                        $failed++;
                        $this->warn("  [HERO FAIL] Event #{$event->id}: {$event->hero_image_url}");
                    } else {
                        if (!$dryRun) {
                            DB::table('events')->where('id', $event->id)
                                ->update(['hero_image_url' => $result, 'updated_at' => now()]);
                        }
                        $processed++;
                        $this->line("  [HERO] #{$event->id} → {$result}");
                    }
                }

                // Poster image
                if (!$heroOnly && $event->poster_url && str_starts_with($event->poster_url, 'http')) {
                    $result = $this->processImage($event->poster_url, 'events/posters', $dryRun, $force, $quality);
                    if ($result === null) {
                        $skipped++;
                    } elseif ($result === false) {
                        $failed++;
                        $this->warn("  [POSTER FAIL] Event #{$event->id}: {$event->poster_url}");
                    } else {
                        if (!$dryRun) {
                            DB::table('events')->where('id', $event->id)
                                ->update(['poster_url' => $result, 'updated_at' => now()]);
                        }
                        $processed++;
                        $this->line("  [POSTER] #{$event->id} → {$result}");
                    }
                }
            }

            $total = $processed + $failed + $skipped;
            $this->line("Progress: {$total} — {$processed} ok | {$failed} failed | {$skipped} already local");
        });

        $prefix = $dryRun ? '[DRY RUN]' : 'Done.';
        $this->info("{$prefix} Processed: {$processed} | Failed: {$failed} | Already local: {$skipped}");

        return $failed > 0 ? 1 : 0;
    }

    /**
     * Download external URL, convert to WebP, store on public disk.
     *
     * Returns local path string on success, false on error, null if already exists (skip).
     */
    private function processImage(
        string $externalUrl,
        string $directory,
        bool $dryRun,
        bool $force,
        int $quality
    ): string|false|null {
        // Deterministic filename from URL hash — idempotent across re-runs
        $hash      = md5($externalUrl);
        $localPath = "{$directory}/{$hash}.webp";

        if (!$force && Storage::disk('public')->exists($localPath)) {
            return null;
        }

        if ($dryRun) {
            return $localPath;
        }

        // Download
        $context = stream_context_create([
            'http' => [
                'timeout'         => 20,
                'follow_location' => true,
                'user_agent'      => 'Mozilla/5.0 (compatible; Tixello/1.0)',
            ],
            'ssl' => ['verify_peer' => false],
        ]);

        $raw = @file_get_contents($externalUrl, false, $context);
        if ($raw === false || strlen($raw) < 512) {
            return false;
        }

        // Create GD image
        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            return false;
        }

        // Preserve transparency
        imagesavealpha($image, true);

        // Convert to WebP in memory
        ob_start();
        $ok      = imagewebp($image, null, $quality);
        $webpData = ob_get_clean();
        imagedestroy($image);

        if (!$ok || empty($webpData)) {
            return false;
        }

        // Storage::put() creates intermediate directories automatically
        Storage::disk('public')->put($localPath, $webpData);

        return $localPath;
    }
}
