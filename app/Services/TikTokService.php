<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * TikTok API Service
 *
 * NOTE: TikTok's Open API does NOT provide public access to user follower counts.
 * Unlike YouTube (public channel API) and Spotify (public artist API), TikTok requires:
 * - OAuth2 user authorization for each user whose data you want to access
 * - The client_credentials flow only works for limited endpoints (not user stats)
 *
 * Possible alternatives:
 * 1. Social Blade API (paid) - https://socialblade.com/api
 * 2. Manual entry by artists
 * 3. Custom OAuth flow where artists authorize the app
 */
class TikTokService
{
    protected string $clientKey;
    protected string $clientSecret;
    protected string $baseUrl = 'https://open.tiktokapis.com/v2';

    public function __construct()
    {
        $settings = \App\Models\Setting::current();
        $this->clientKey = $settings->tiktok_client_key ?: config('services.tiktok.client_key', '');
        $this->clientSecret = $settings->tiktok_client_secret ?: config('services.tiktok.client_secret', '');
    }

    /**
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->clientKey) && !empty($this->clientSecret);
    }

    /**
     * Get access token using client credentials flow
     * Note: This only provides access to limited endpoints, NOT user stats
     */
    protected function getAccessToken(): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $cacheKey = 'tiktok_access_token';

        return Cache::remember($cacheKey, now()->addMinutes(110), function () {
            try {
                $response = Http::asForm()
                    ->post('https://open.tiktokapis.com/v2/oauth/token/', [
                        'client_key' => $this->clientKey,
                        'client_secret' => $this->clientSecret,
                        'grant_type' => 'client_credentials',
                    ]);

                if (!$response->successful()) {
                    Log::warning('TikTok auth error', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                return $response->json('access_token');
            } catch (\Exception $e) {
                Log::error('TikTok Auth exception: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Get user info - REQUIRES USER AUTHORIZATION
     *
     * This method is a placeholder. TikTok does not allow fetching public user data
     * without the user authorizing the application via OAuth2.
     *
     * @param string $username TikTok username
     * @return array|null Returns null because API doesn't support this without user auth
     */
    public function getUserInfo(string $username): ?array
    {
        // TikTok API does NOT support fetching public user info by username
        // The /v2/user/info/ endpoint requires user authorization (access_token from user OAuth)
        // This is different from Spotify/YouTube which have public artist/channel APIs

        Log::debug("TikTokService: Cannot fetch stats for @{$username} - TikTok requires user OAuth authorization");

        return null;
    }

    /**
     * Get follower count for a TikTok user
     *
     * @param string $usernameOrUrl TikTok username or full URL
     * @return int|null Returns null because API doesn't support this without user auth
     */
    public function getFollowerCount(string $usernameOrUrl): ?int
    {
        $username = self::extractUsername($usernameOrUrl);

        if (empty($username)) {
            return null;
        }

        // Cannot fetch public follower counts via TikTok API
        // Would need third-party service like Social Blade

        return null;
    }

    /**
     * Extract username from TikTok URL
     *
     * @param string $url TikTok URL or username
     * @return string|null Extracted username without @ symbol
     */
    public static function extractUsername(string $url): ?string
    {
        // Clean input
        $url = trim($url);

        // If it's just a username (with or without @)
        if (!str_contains($url, 'tiktok.com')) {
            return ltrim($url, '@');
        }

        // Handle tiktok.com/@username or tiktok.com/username
        if (preg_match('/tiktok\.com\/@?([a-zA-Z0-9_.]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Clear cache for a specific user
     */
    public function clearCache(string $username): void
    {
        $username = ltrim($username, '@');
        Cache::forget("tiktok_user_{$username}");
    }

    /**
     * Generate embed HTML for a TikTok video
     */
    public static function getVideoEmbedHtml(string $videoUrl, int $width = 325, int $height = 580): string
    {
        // Extract video ID from URL
        if (preg_match('/tiktok\.com\/@[^\/]+\/video\/(\d+)/', $videoUrl, $matches)) {
            $videoId = $matches[1];
            return sprintf(
                '<blockquote class="tiktok-embed" cite="%s" data-video-id="%s" style="max-width: %dpx; min-width: 325px;">
                    <section></section>
                </blockquote>
                <script async src="https://www.tiktok.com/embed.js"></script>',
                htmlspecialchars($videoUrl),
                $videoId,
                $width
            );
        }

        return '';
    }
}
