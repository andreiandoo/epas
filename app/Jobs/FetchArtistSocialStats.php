<?php

namespace App\Jobs;

use App\Models\Artist;
use App\Services\YouTubeService;
use App\Services\SpotifyService;
use App\Services\FacebookService;
use App\Services\TikTokService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchArtistSocialStats implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // seconds between retries
    public int $timeout = 120; // 2 minutes max

    protected int $artistId;

    public function __construct(int $artistId)
    {
        $this->artistId = $artistId;
    }

    public function handle(): void
    {
        $artist = Artist::find($this->artistId);

        if (!$artist) {
            Log::warning("FetchArtistSocialStats: Artist {$this->artistId} not found");
            return;
        }

        $changes = [];
        $errors = [];

        // YouTube stats
        if (!empty($artist->youtube_id)) {
            try {
                $youtubeService = new YouTubeService();
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
                $errors[] = "YouTube: {$e->getMessage()}";
                Log::error("FetchArtistSocialStats YouTube error for {$artist->name}: {$e->getMessage()}");
            }
        }

        // Spotify stats
        if (!empty($artist->spotify_id)) {
            try {
                $spotifyService = new SpotifyService();
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
                $errors[] = "Spotify: {$e->getMessage()}";
                Log::error("FetchArtistSocialStats Spotify error for {$artist->name}: {$e->getMessage()}");
            }
        }

        // Facebook stats
        if (!empty($artist->facebook_url)) {
            try {
                $facebookService = new FacebookService();

                if ($facebookService->isConfigured()) {
                    $pageId = FacebookService::extractPageId($artist->facebook_url);

                    if ($pageId) {
                        $facebookService->clearCache($pageId);
                        $fbStats = $facebookService->getPageInfo($pageId);

                        if ($fbStats && isset($fbStats['followers'])) {
                            $changes['followers_facebook'] = $fbStats['followers'];
                        }
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Facebook: {$e->getMessage()}";
                Log::error("FetchArtistSocialStats Facebook error for {$artist->name}: {$e->getMessage()}");
            }
        }

        // TikTok stats
        // Note: TikTok API does NOT provide public access to user follower counts
        // This will only work if TikTokService is extended with a third-party API (e.g., Social Blade)
        if (!empty($artist->tiktok_url)) {
            try {
                $tiktokService = new TikTokService();

                if ($tiktokService->isConfigured()) {
                    $username = TikTokService::extractUsername($artist->tiktok_url);

                    if ($username) {
                        $tiktokService->clearCache($username);
                        $followers = $tiktokService->getFollowerCount($username);

                        if ($followers !== null) {
                            $changes['followers_tiktok'] = $followers;
                        }
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "TikTok: {$e->getMessage()}";
                Log::error("FetchArtistSocialStats TikTok error for {$artist->name}: {$e->getMessage()}");
            }
        }

        // Instagram stats (via Facebook Graph API if available)
        // Note: Instagram requires Business Discovery API which needs specific setup
        // For now, we skip automatic Instagram fetching
        // Users can manually enter Instagram followers

        // Update artist if we have any changes
        if (!empty($changes)) {
            $changes['social_stats_updated_at'] = now();
            $artist->update($changes);

            Log::info("FetchArtistSocialStats: Updated {$artist->name}", [
                'artist_id' => $artist->id,
                'changes' => array_keys($changes),
            ]);
        }

        if (!empty($errors)) {
            Log::warning("FetchArtistSocialStats: Errors for {$artist->name}", [
                'artist_id' => $artist->id,
                'errors' => $errors,
            ]);
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("FetchArtistSocialStats job failed for artist {$this->artistId}: {$exception->getMessage()}");
    }

    /**
     * Determine the time at which the job should timeout
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(1);
    }
}
