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
        {--quality=82 : WebP quality (1-100)}
        {--fix-paths : Only fix DB paths — no downloads, just resolve external URLs to existing local files}';

    protected $description = 'Download external AmBilet event images, convert to WebP and store locally';

    public function handle(): int
    {
        $clientId   = (int) $this->option('marketplace');
        $dryRun     = $this->option('dry-run');
        $force      = $this->option('force');
        $quality    = (int) $this->option('quality');
        $heroOnly   = $this->option('hero-only');
        $posterOnly = $this->option('poster-only');
        $fixPaths   = $this->option('fix-paths');

        if ($fixPaths) {
            return $this->handleFixPaths($clientId, $dryRun);
        }

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

        // Download (using Laravel HTTP client for proper redirect following)
        try {
            $response = \Illuminate\Support\Facades\Http::withOptions([
                'verify'  => false,
                'timeout' => 30,
            ])->withUserAgent('Mozilla/5.0 (compatible; Tixello/1.0)')
              ->get($externalUrl);

            if (! $response->successful()) {
                return false;
            }

            $raw = $response->body();
        } catch (\Exception $e) {
            return false;
        }

        if (strlen($raw) < 512) {
            return false;
        }

        // Create GD image
        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            return false;
        }

        // Palette/indexed images must be converted to true color before WebP
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
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

    /**
     * Fix DB paths only — resolve external URLs to existing local WebP files.
     * No downloads, no conversions. Just checks if the local file exists by md5 hash.
     * If local file exists → update DB. If not → set to NULL.
     */
    private function handleFixPaths(int $clientId, bool $dryRun): int
    {
        $events = DB::table('events')
            ->where('marketplace_client_id', $clientId)
            ->where(function ($q) {
                $q->where('hero_image_url', 'like', 'http%')
                  ->orWhere('poster_url', 'like', 'http%');
            })
            ->select('id', 'hero_image_url', 'poster_url')
            ->get();

        $this->info("Found {$events->count()} events with external URLs in DB.");

        $fixed = $cleared = $alreadyLocal = 0;

        foreach ($events as $event) {
            $updates = [];

            // Hero image
            if ($event->hero_image_url && str_starts_with($event->hero_image_url, 'http')) {
                $localPath = 'events/hero/' . md5($event->hero_image_url) . '.webp';
                if (Storage::disk('public')->exists($localPath)) {
                    $updates['hero_image_url'] = $localPath;
                    $fixed++;
                    $this->line("  [HERO FIX] #{$event->id} → {$localPath}");
                } else {
                    $updates['hero_image_url'] = null;
                    $cleared++;
                    $this->warn("  [HERO CLEAR] #{$event->id} — local file not found");
                }
            }

            // Poster
            if ($event->poster_url && str_starts_with($event->poster_url, 'http')) {
                $localPath = 'events/posters/' . md5($event->poster_url) . '.webp';
                if (Storage::disk('public')->exists($localPath)) {
                    $updates['poster_url'] = $localPath;
                    $fixed++;
                    $this->line("  [POSTER FIX] #{$event->id} → {$localPath}");
                } else {
                    $updates['poster_url'] = null;
                    $cleared++;
                    $this->warn("  [POSTER CLEAR] #{$event->id} — local file not found");
                }
            }

            if (!$dryRun && !empty($updates)) {
                $updates['updated_at'] = now();
                DB::table('events')->where('id', $event->id)->update($updates);
            }
        }

        $prefix = $dryRun ? '[DRY RUN] Would fix' : 'Fixed';
        $this->info("{$prefix}: {$fixed} paths | Cleared (no local file): {$cleared}");

        return 0;
    }
}
