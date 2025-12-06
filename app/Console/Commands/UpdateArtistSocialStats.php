<?php

namespace App\Console\Commands;

use App\Models\Artist;
use App\Services\YouTubeService;
use App\Services\SpotifyService;
use App\Services\FacebookService;
use App\Jobs\FetchArtistSocialStats;
use Illuminate\Console\Command;

class UpdateArtistSocialStats extends Command
{
    protected $signature = 'artists:update-social-stats
                            {--artist= : Update specific artist by ID}
                            {--force : Force update even if recently updated}
                            {--queue : Use queue jobs instead of synchronous processing}
                            {--batch-size=100 : Number of artists per batch when using queue}
                            {--limit= : Limit total number of artists to process}
                            {--skip-updated= : Skip artists updated within N days (default: 7)}
                            {--platform= : Only fetch specific platform (youtube, spotify, facebook, all)}
                            {--dry-run : Show what would be processed without actually fetching}';

    protected $description = 'Fetch and update social media stats (YouTube, Spotify, Facebook) for artists';

    protected YouTubeService $youtubeService;
    protected SpotifyService $spotifyService;
    protected FacebookService $facebookService;

    public function handle(): int
    {
        $this->youtubeService = new YouTubeService();
        $this->spotifyService = new SpotifyService();
        $this->facebookService = new FacebookService();

        $platform = $this->option('platform') ?? 'all';
        $useQueue = $this->option('queue');
        $isDryRun = $this->option('dry-run');
        $skipDays = $this->option('skip-updated') ?? ($this->option('force') ? 0 : 7);
        $limit = $this->option('limit');
        $batchSize = (int) $this->option('batch-size');

        // Build query
        $query = $this->buildQuery($platform, $skipDays);

        if ($artistId = $this->option('artist')) {
            $query->where('id', $artistId);
        }

        if ($limit) {
            $query->limit((int) $limit);
        }

        $totalCount = (clone $query)->count();

        if ($totalCount === 0) {
            $this->info('No artists found matching criteria.');
            return 0;
        }

        $this->info("Found {$totalCount} artists to process.");

        if ($isDryRun) {
            $this->showDryRunStats($query, $platform);
            return 0;
        }

        if ($useQueue) {
            return $this->processWithQueue($query, $batchSize, $totalCount);
        }

        return $this->processSynchronously($query, $platform, $totalCount);
    }

    protected function buildQuery(string $platform, int $skipDays): \Illuminate\Database\Eloquent\Builder
    {
        $query = Artist::query();

        // Filter by platform
        if ($platform === 'youtube') {
            $query->where(function ($q) {
                $q->whereNotNull('youtube_id')->where('youtube_id', '!=', '');
            });
        } elseif ($platform === 'spotify') {
            $query->where(function ($q) {
                $q->whereNotNull('spotify_id')->where('spotify_id', '!=', '');
            });
        } elseif ($platform === 'facebook') {
            $query->where(function ($q) {
                $q->whereNotNull('facebook_url')->where('facebook_url', '!=', '');
            });
        } else {
            // All platforms - artist must have at least one social profile
            $query->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNotNull('youtube_id')->where('youtube_id', '!=', '');
                })->orWhere(function ($sub) {
                    $sub->whereNotNull('spotify_id')->where('spotify_id', '!=', '');
                })->orWhere(function ($sub) {
                    $sub->whereNotNull('facebook_url')->where('facebook_url', '!=', '');
                });
            });
        }

        // Skip recently updated artists
        if ($skipDays > 0) {
            $query->where(function ($q) use ($skipDays) {
                $q->whereNull('social_stats_updated_at')
                  ->orWhere('social_stats_updated_at', '<', now()->subDays($skipDays));
            });
        }

        return $query;
    }

    protected function showDryRunStats($query, string $platform): void
    {
        $this->info("\n--- DRY RUN MODE ---\n");

        $artists = $query->get();

        $stats = [
            'youtube' => 0,
            'spotify' => 0,
            'facebook' => 0,
            'total' => $artists->count(),
        ];

        foreach ($artists as $artist) {
            if (!empty($artist->youtube_id)) $stats['youtube']++;
            if (!empty($artist->spotify_id)) $stats['spotify']++;
            if (!empty($artist->facebook_url)) $stats['facebook']++;
        }

        $this->table(
            ['Platform', 'Artists with Profile'],
            [
                ['YouTube', $stats['youtube']],
                ['Spotify', $stats['spotify']],
                ['Facebook', $stats['facebook']],
                ['---', '---'],
                ['Total Unique Artists', $stats['total']],
            ]
        );

        $this->newLine();
        $this->info("Use --queue to process via jobs (recommended for large datasets)");
        $this->info("Use --batch-size=N to control job batch size (default: 100)");
    }

    protected function processWithQueue($query, int $batchSize, int $totalCount): int
    {
        $this->info("Queueing artists in batches of {$batchSize}...");

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $queued = 0;

        $query->chunkById($batchSize, function ($artists) use (&$queued, $bar) {
            foreach ($artists as $artist) {
                FetchArtistSocialStats::dispatch($artist->id);
                $queued++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Queued {$queued} artists for processing.");
        $this->info("Run 'php artisan queue:work --queue=social-stats' to process the jobs.");

        return 0;
    }

    protected function processSynchronously($query, string $platform, int $totalCount): int
    {
        $this->info("Processing artists synchronously...");

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $updated = 0;
        $errors = 0;

        $query->chunkById(100, function ($artists) use (&$updated, &$errors, $bar, $platform) {
            foreach ($artists as $artist) {
                $result = $this->fetchArtistStats($artist, $platform);

                if ($result['updated']) {
                    $updated++;
                }
                if ($result['errors'] > 0) {
                    $errors += $result['errors'];
                }

                $bar->advance();

                // Rate limiting delay
                usleep(200000); // 200ms
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Updated {$updated} artists.");
        if ($errors > 0) {
            $this->warn("{$errors} errors occurred. Check logs for details.");
        }

        return 0;
    }

    protected function fetchArtistStats(Artist $artist, string $platform): array
    {
        $changes = [];
        $errorCount = 0;

        // YouTube
        if (($platform === 'all' || $platform === 'youtube') && !empty($artist->youtube_id)) {
            try {
                $this->youtubeService->clearCache($artist->youtube_id);
                $ytStats = $this->youtubeService->getChannelStats($artist->youtube_id);

                if ($ytStats) {
                    if (isset($ytStats['subscribers'])) {
                        $changes['followers_youtube'] = $ytStats['subscribers'];
                    }
                    if (isset($ytStats['views'])) {
                        $changes['youtube_total_views'] = $ytStats['views'];
                    }
                }
            } catch (\Exception $e) {
                $this->error("\nYouTube error for {$artist->name}: {$e->getMessage()}");
                $errorCount++;
            }
        }

        // Spotify
        if (($platform === 'all' || $platform === 'spotify') && !empty($artist->spotify_id)) {
            try {
                $this->spotifyService->clearCache($artist->spotify_id);
                $spStats = $this->spotifyService->getArtist($artist->spotify_id);

                if ($spStats) {
                    if (isset($spStats['followers'])) {
                        $changes['spotify_monthly_listeners'] = $spStats['followers'];
                    }
                    if (isset($spStats['popularity'])) {
                        $changes['spotify_popularity'] = $spStats['popularity'];
                    }
                }
            } catch (\Exception $e) {
                $this->error("\nSpotify error for {$artist->name}: {$e->getMessage()}");
                $errorCount++;
            }
        }

        // Facebook
        if (($platform === 'all' || $platform === 'facebook') && !empty($artist->facebook_url)) {
            if ($this->facebookService->isConfigured()) {
                try {
                    $pageId = FacebookService::extractPageId($artist->facebook_url);

                    if ($pageId) {
                        $this->facebookService->clearCache($pageId);
                        $fbStats = $this->facebookService->getPageInfo($pageId);

                        if ($fbStats && isset($fbStats['followers'])) {
                            $changes['followers_facebook'] = $fbStats['followers'];
                        }
                    }
                } catch (\Exception $e) {
                    $this->error("\nFacebook error for {$artist->name}: {$e->getMessage()}");
                    $errorCount++;
                }
            }
        }

        $wasUpdated = false;
        if (!empty($changes)) {
            $changes['social_stats_updated_at'] = now();
            $artist->update($changes);
            $wasUpdated = true;
        }

        return [
            'updated' => $wasUpdated,
            'errors' => $errorCount,
        ];
    }
}
