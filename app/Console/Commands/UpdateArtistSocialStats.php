<?php

namespace App\Console\Commands;

use App\Models\Artist;
use App\Services\YouTubeService;
use App\Services\SpotifyService;
use Illuminate\Console\Command;

class UpdateArtistSocialStats extends Command
{
    protected $signature = 'artists:update-social-stats
                            {--artist= : Update specific artist by ID}
                            {--force : Force update even if recently updated}';

    protected $description = 'Fetch and update social media stats (YouTube subscribers, Spotify followers) for artists';

    public function handle(): int
    {
        $youtubeService = new YouTubeService();
        $spotifyService = new SpotifyService();

        $query = Artist::query()
            ->where(function ($q) {
                $q->whereNotNull('youtube_id')
                  ->where('youtube_id', '!=', '')
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('spotify_id')
                         ->where('spotify_id', '!=', '');
                  });
            });

        if ($artistId = $this->option('artist')) {
            $query->where('id', $artistId);
        }

        $artists = $query->get();

        if ($artists->isEmpty()) {
            $this->info('No artists with YouTube/Spotify IDs found.');
            return 0;
        }

        $this->info("Updating social stats for {$artists->count()} artists...");
        $bar = $this->output->createProgressBar($artists->count());
        $bar->start();

        $updated = 0;
        $errors = 0;

        foreach ($artists as $artist) {
            $changes = [];

            // YouTube stats (subscribers, views)
            if (!empty($artist->youtube_id)) {
                try {
                    // Clear cache for fresh data
                    $youtubeService->clearCache($artist->youtube_id);

                    $ytStats = $youtubeService->getChannelStats($artist->youtube_id);
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
                    $errors++;
                }
            }

            // Spotify stats (followers, popularity)
            if (!empty($artist->spotify_id)) {
                try {
                    // Clear cache for fresh data
                    $spotifyService->clearCache($artist->spotify_id);

                    $spStats = $spotifyService->getArtist($artist->spotify_id);
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
                    $errors++;
                }
            }

            if (!empty($changes)) {
                $changes['social_stats_updated_at'] = now();
                $artist->update($changes);
                $updated++;
            }

            $bar->advance();

            // Small delay to avoid rate limiting
            usleep(200000); // 200ms
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Updated {$updated} artists.");
        if ($errors > 0) {
            $this->warn("{$errors} errors occurred.");
        }

        return 0;
    }
}
