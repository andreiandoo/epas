<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class YouTubeService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://www.googleapis.com/youtube/v3';

    public function __construct()
    {
        // Try to get from Settings first, then fallback to env
        $settings = \App\Models\Setting::current();
        $this->apiKey = $settings->youtube_api_key ?: config('services.youtube.api_key', '');
    }

    /**
     * Get channel statistics by channel ID
     */
    public function getChannelStats(string $channelId): ?array
    {
        if (empty($this->apiKey) || empty($channelId)) {
            return null;
        }

        $cacheKey = "youtube_channel_stats_{$channelId}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($channelId) {
            try {
                $response = Http::get("{$this->baseUrl}/channels", [
                    'key' => $this->apiKey,
                    'id' => $channelId,
                    'part' => 'statistics,snippet,brandingSettings',
                ]);

                if (!$response->successful()) {
                    return null;
                }

                $data = $response->json();

                if (empty($data['items'][0])) {
                    return null;
                }

                $channel = $data['items'][0];
                $stats = $channel['statistics'] ?? [];
                $snippet = $channel['snippet'] ?? [];

                return [
                    'channel_id' => $channelId,
                    'title' => $snippet['title'] ?? '',
                    'description' => $snippet['description'] ?? '',
                    'thumbnail' => $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['default']['url'] ?? '',
                    'custom_url' => $snippet['customUrl'] ?? '',
                    'country' => $snippet['country'] ?? '',
                    'published_at' => $snippet['publishedAt'] ?? '',
                    'subscribers' => (int) ($stats['subscriberCount'] ?? 0),
                    'views' => (int) ($stats['viewCount'] ?? 0),
                    'videos' => (int) ($stats['videoCount'] ?? 0),
                    'hidden_subscriber_count' => $stats['hiddenSubscriberCount'] ?? false,
                    'fetched_at' => now()->toIso8601String(),
                ];
            } catch (\Exception $e) {
                \Log::error('YouTube API error: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Get video details by video IDs
     */
    public function getVideosStats(array $videoIds): array
    {
        if (empty($this->apiKey) || empty($videoIds)) {
            return [];
        }

        $cacheKey = "youtube_videos_stats_" . md5(implode(',', $videoIds));

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($videoIds) {
            try {
                $response = Http::get("{$this->baseUrl}/videos", [
                    'key' => $this->apiKey,
                    'id' => implode(',', array_slice($videoIds, 0, 50)), // Max 50 videos per request
                    'part' => 'statistics,snippet,contentDetails',
                ]);

                if (!$response->successful()) {
                    return [];
                }

                $data = $response->json();
                $videos = [];

                foreach ($data['items'] ?? [] as $video) {
                    $stats = $video['statistics'] ?? [];
                    $snippet = $video['snippet'] ?? [];
                    $content = $video['contentDetails'] ?? [];

                    $videos[] = [
                        'video_id' => $video['id'],
                        'title' => $snippet['title'] ?? '',
                        'description' => $snippet['description'] ?? '',
                        'thumbnail' => $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['default']['url'] ?? '',
                        'published_at' => $snippet['publishedAt'] ?? '',
                        'duration' => $content['duration'] ?? '',
                        'views' => (int) ($stats['viewCount'] ?? 0),
                        'likes' => (int) ($stats['likeCount'] ?? 0),
                        'comments' => (int) ($stats['commentCount'] ?? 0),
                    ];
                }

                return $videos;
            } catch (\Exception $e) {
                \Log::error('YouTube Videos API error: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Get recent videos from a channel
     */
    public function getRecentVideos(string $channelId, int $maxResults = 10): array
    {
        if (empty($this->apiKey) || empty($channelId)) {
            return [];
        }

        $cacheKey = "youtube_recent_videos_{$channelId}_{$maxResults}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($channelId, $maxResults) {
            try {
                // First get the uploads playlist ID
                $channelResponse = Http::get("{$this->baseUrl}/channels", [
                    'key' => $this->apiKey,
                    'id' => $channelId,
                    'part' => 'contentDetails',
                ]);

                if (!$channelResponse->successful()) {
                    return [];
                }

                $channelData = $channelResponse->json();
                $uploadsPlaylistId = $channelData['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? null;

                if (!$uploadsPlaylistId) {
                    return [];
                }

                // Get videos from uploads playlist
                $playlistResponse = Http::get("{$this->baseUrl}/playlistItems", [
                    'key' => $this->apiKey,
                    'playlistId' => $uploadsPlaylistId,
                    'part' => 'snippet',
                    'maxResults' => $maxResults,
                ]);

                if (!$playlistResponse->successful()) {
                    return [];
                }

                $playlistData = $playlistResponse->json();
                $videoIds = [];

                foreach ($playlistData['items'] ?? [] as $item) {
                    $videoIds[] = $item['snippet']['resourceId']['videoId'] ?? '';
                }

                $videoIds = array_filter($videoIds);

                if (empty($videoIds)) {
                    return [];
                }

                return $this->getVideosStats($videoIds);
            } catch (\Exception $e) {
                \Log::error('YouTube Recent Videos API error: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Extract channel ID from YouTube URL
     */
    public static function extractChannelId(string $url): ?string
    {
        // Handle @username format
        if (preg_match('/youtube\.com\/@([^\/\?]+)/', $url, $matches)) {
            // For @ handles, we'd need to resolve to channel ID via API
            return null; // Would need separate API call
        }

        // Handle channel/CHANNEL_ID format
        if (preg_match('/youtube\.com\/channel\/([^\/\?]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Handle c/customname format
        if (preg_match('/youtube\.com\/c\/([^\/\?]+)/', $url, $matches)) {
            return null; // Would need separate API call
        }

        // Handle user/username format
        if (preg_match('/youtube\.com\/user\/([^\/\?]+)/', $url, $matches)) {
            return null; // Would need separate API call
        }

        return null;
    }

    /**
     * Extract video ID from YouTube URL
     */
    public static function extractVideoId(string $url): ?string
    {
        // Standard watch URL
        if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Short URL
        if (preg_match('/youtu\.be\/([^\/\?]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Embed URL
        if (preg_match('/youtube\.com\/embed\/([^\/\?]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Shorts URL
        if (preg_match('/youtube\.com\/shorts\/([^\/\?]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Clear cache for a specific channel
     */
    public function clearCache(string $channelId): void
    {
        Cache::forget("youtube_channel_stats_{$channelId}");
        Cache::forget("youtube_recent_videos_{$channelId}_10");
    }
}
