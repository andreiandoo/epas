<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FacebookService
{
    protected string $appId;
    protected string $appSecret;
    protected string $accessToken;
    protected string $baseUrl = 'https://graph.facebook.com/v18.0';

    public function __construct()
    {
        // Get credentials from Settings
        $settings = \App\Models\Setting::current();
        $this->appId = $settings->facebook_app_id ?: config('services.facebook.app_id', '');
        $this->appSecret = $settings->facebook_app_secret ?: config('services.facebook.app_secret', '');
        $this->accessToken = $settings->facebook_access_token ?: config('services.facebook.access_token', '');
    }

    /**
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->accessToken);
    }

    /**
     * Get Facebook page info by page ID or username
     */
    public function getPageInfo(string $pageIdOrUsername): ?array
    {
        if (!$this->isConfigured() || empty($pageIdOrUsername)) {
            return null;
        }

        $cacheKey = "facebook_page_{$pageIdOrUsername}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($pageIdOrUsername) {
            try {
                $response = Http::get("{$this->baseUrl}/{$pageIdOrUsername}", [
                    'access_token' => $this->accessToken,
                    'fields' => 'id,name,fan_count,followers_count,about,link,picture',
                ]);

                if (!$response->successful()) {
                    Log::warning('Facebook API error', [
                        'page' => $pageIdOrUsername,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                $data = $response->json();

                return [
                    'id' => $data['id'] ?? '',
                    'name' => $data['name'] ?? '',
                    'followers' => (int) ($data['followers_count'] ?? $data['fan_count'] ?? 0),
                    'fan_count' => (int) ($data['fan_count'] ?? 0),
                    'about' => $data['about'] ?? '',
                    'link' => $data['link'] ?? '',
                    'picture' => $data['picture']['data']['url'] ?? null,
                    'fetched_at' => now()->toIso8601String(),
                ];
            } catch (\Exception $e) {
                Log::error('Facebook API exception: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Get Instagram Business account info
     * Note: Requires Instagram Business account linked to Facebook Page
     */
    public function getInstagramBusinessInfo(string $instagramUsername): ?array
    {
        if (!$this->isConfigured() || empty($instagramUsername)) {
            return null;
        }

        // Clean username (remove @ if present)
        $username = ltrim($instagramUsername, '@');

        $cacheKey = "instagram_business_{$username}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($username) {
            try {
                // Search for Instagram Business Account by username
                // This requires the instagram_basic permission and a connected Facebook page
                $response = Http::get("{$this->baseUrl}/ig_hashtag_search", [
                    'access_token' => $this->accessToken,
                    'user_id' => $username,
                ]);

                // Alternative approach: Use the Business Discovery API
                // This allows fetching public data about any Instagram Business/Creator account
                // without needing to be connected to it

                // For now, return null as this requires more complex setup
                // The user would need to have their own Instagram Business account
                // to use Business Discovery

                return null;
            } catch (\Exception $e) {
                Log::error('Instagram API exception: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Extract page ID or username from Facebook URL
     */
    public static function extractPageId(string $url): ?string
    {
        // Handle facebook.com/pagename
        if (preg_match('/facebook\.com\/([^\/\?]+)\/?/', $url, $matches)) {
            $page = $matches[1];

            // Skip common non-page paths
            $skipPaths = ['pages', 'profile.php', 'groups', 'events', 'watch', 'marketplace'];
            if (in_array(strtolower($page), $skipPaths)) {
                // Try to get the actual page name from the next path segment
                if (preg_match('/facebook\.com\/pages\/[^\/]+\/([^\/\?]+)/', $url, $subMatches)) {
                    return $subMatches[1];
                }
                return null;
            }

            return $page;
        }

        return null;
    }

    /**
     * Extract username from Instagram URL
     */
    public static function extractInstagramUsername(string $url): ?string
    {
        // Handle instagram.com/username
        if (preg_match('/instagram\.com\/([^\/\?]+)\/?/', $url, $matches)) {
            $username = $matches[1];

            // Skip common non-profile paths
            $skipPaths = ['p', 'reel', 'stories', 'explore', 'direct', 'accounts'];
            if (in_array(strtolower($username), $skipPaths)) {
                return null;
            }

            return $username;
        }

        return null;
    }

    /**
     * Fetch public page follower count (works for any public page)
     * This is a simpler approach that works without special permissions
     */
    public function getPublicPageFollowers(string $pageIdOrUrl): ?int
    {
        $pageId = $pageIdOrUrl;

        // If it's a URL, extract the page ID
        if (str_contains($pageIdOrUrl, 'facebook.com')) {
            $pageId = self::extractPageId($pageIdOrUrl);
        }

        if (empty($pageId)) {
            return null;
        }

        $pageInfo = $this->getPageInfo($pageId);

        return $pageInfo ? $pageInfo['followers'] : null;
    }

    /**
     * Clear cache for a specific page
     */
    public function clearCache(string $pageIdOrUsername): void
    {
        Cache::forget("facebook_page_{$pageIdOrUsername}");
    }

    /**
     * Clear Instagram cache
     */
    public function clearInstagramCache(string $username): void
    {
        $username = ltrim($username, '@');
        Cache::forget("instagram_business_{$username}");
    }
}
