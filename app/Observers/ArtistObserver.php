<?php

namespace App\Observers;

use App\Models\Artist;
use App\Services\YouTubeService;
use App\Services\SpotifyService;

class ArtistObserver
{
    /**
     * Handle the Artist "saved" event.
     * Auto-fetch social stats when IDs are added/changed.
     */
    public function saved(Artist $artist): void
    {
        $changes = [];
        $shouldFetch = false;

        // Check if youtube_id was added or changed
        if ($artist->wasChanged('youtube_id') && !empty($artist->youtube_id)) {
            $shouldFetch = true;
        }

        // Check if spotify_id was added or changed
        if ($artist->wasChanged('spotify_id') && !empty($artist->spotify_id)) {
            $shouldFetch = true;
        }

        if (!$shouldFetch) {
            return;
        }

        // Fetch YouTube stats
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
                \Log::error("YouTube stats fetch error for artist {$artist->id}: {$e->getMessage()}");
            }
        }

        // Fetch Spotify stats
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
                \Log::error("Spotify stats fetch error for artist {$artist->id}: {$e->getMessage()}");
            }
        }

        // Update without triggering observer again
        if (!empty($changes)) {
            $changes['social_stats_updated_at'] = now();
            $artist->updateQuietly($changes);
        }
    }
}
