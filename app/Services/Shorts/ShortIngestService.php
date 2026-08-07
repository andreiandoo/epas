<?php

namespace App\Services\Shorts;

use App\Services\YouTubeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns a social post link into short metadata (docs/plans/social-video-ingestion.md).
 *
 * THE RULE: metadata + thumbnail + embed code. The video file is never downloaded
 * and never re-hosted — doing so breaks the terms of service and copyright on all
 * four platforms. External shorts play through the platform's embed; only clips
 * an organiser uploads to Tixello play natively.
 *
 * YouTube uses the Data API key EPAS already holds. TikTok's oEmbed is public and
 * needs no auth. Meta (IG/FB) needs an app access token with oEmbed Read, which
 * requires app review — it degrades to "unsupported" until that token exists.
 */
class ShortIngestService
{
    public const PLATFORM_YOUTUBE = 'youtube';

    public const PLATFORM_TIKTOK = 'tiktok';

    public const PLATFORM_INSTAGRAM = 'instagram';

    public const PLATFORM_FACEBOOK = 'facebook';

    public const PLATFORM_UNKNOWN = 'unknown';

    public function __construct(private readonly YouTubeService $youtube) {}

    public function detectPlatform(string $url): string
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        return match (true) {
            str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be') => self::PLATFORM_YOUTUBE,
            str_contains($host, 'tiktok.com') => self::PLATFORM_TIKTOK,
            str_contains($host, 'instagram.com') => self::PLATFORM_INSTAGRAM,
            str_contains($host, 'facebook.com') || str_contains($host, 'fb.watch') => self::PLATFORM_FACEBOOK,
            default => self::PLATFORM_UNKNOWN,
        };
    }

    /**
     * Normalised payload, or null when the link cannot be ingested.
     *
     * @return array{source: string, source_url: string, source_video_id: string|null, embed_html: string|null, title: string|null, duration: int|null, thumbnail_remote: string|null, author: string|null}|null
     */
    public function ingest(string $url): ?array
    {
        return match ($this->detectPlatform($url)) {
            self::PLATFORM_YOUTUBE => $this->youtube($url),
            self::PLATFORM_TIKTOK => $this->tiktok($url),
            self::PLATFORM_INSTAGRAM, self::PLATFORM_FACEBOOK => $this->meta($url),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function youtube(string $url): ?array
    {
        $videoId = YouTubeService::extractVideoId($url);

        if (! $videoId) {
            return null;
        }

        // getVideosStats caches for 6h, which also keeps us inside the Data API
        // quota when an admin pastes the same link twice.
        $video = $this->youtube->getVideosStats([$videoId])[0] ?? null;

        return [
            'source' => self::PLATFORM_YOUTUBE,
            'source_url' => $url,
            'source_video_id' => $videoId,
            // youtube-nocookie: no tracking cookie is set until the viewer plays.
            'embed_html' => sprintf(
                '<iframe src="https://www.youtube-nocookie.com/embed/%s" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>',
                e($videoId),
            ),
            'title' => $video['title'] ?? null,
            'duration' => isset($video['duration']) ? $this->parseIso8601Duration($video['duration']) : null,
            'thumbnail_remote' => $video['thumbnail'] ?? "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg",
            'author' => $video['channel_title'] ?? null,
        ];
    }

    /**
     * TikTok's oEmbed is public and stable — no key, no app review.
     *
     * @return array<string, mixed>|null
     */
    protected function tiktok(string $url): ?array
    {
        $payload = Cache::remember(
            'shorts:tiktok:oembed:'.md5($url),
            now()->addHours(24),
            function () use ($url) {
                try {
                    $response = Http::timeout(10)->get('https://www.tiktok.com/oembed', ['url' => $url]);

                    return $response->successful() ? $response->json() : null;
                } catch (\Throwable $e) {
                    Log::warning('Shorts: TikTok oEmbed failed', ['url' => $url, 'error' => $e->getMessage()]);

                    return null;
                }
            },
        );

        if (! is_array($payload)) {
            return null;
        }

        return [
            'source' => self::PLATFORM_TIKTOK,
            'source_url' => $url,
            'source_video_id' => $this->tiktokVideoId($url) ?? ($payload['embed_product_id'] ?? null),
            'embed_html' => $payload['html'] ?? null,
            'title' => $payload['title'] ?? null,
            // oEmbed carries no duration.
            'duration' => null,
            'thumbnail_remote' => $payload['thumbnail_url'] ?? null,
            'author' => $payload['author_name'] ?? null,
        ];
    }

    /**
     * Instagram Reels and Facebook video, through Meta's oEmbed Read.
     *
     * TODO(owner): needs a Meta app with the oEmbed Read product approved, and its
     * app access token in services.meta.oembed_token. Until then this returns null
     * and the admin sees "this platform is not connected yet" rather than a
     * half-ingested short. Meta's policy here has changed repeatedly — confirm the
     * product is still available before starting the review.
     *
     * @return array<string, mixed>|null
     */
    protected function meta(string $url): ?array
    {
        $token = config('services.meta.oembed_token');

        if (! $token) {
            return null;
        }

        $platform = $this->detectPlatform($url);
        $endpoint = $platform === self::PLATFORM_INSTAGRAM
            ? 'https://graph.facebook.com/v19.0/instagram_oembed'
            : 'https://graph.facebook.com/v19.0/oembed_video';

        try {
            $response = Http::timeout(10)->get($endpoint, [
                'url' => $url,
                'access_token' => $token,
                'omitscript' => true,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $payload = $response->json();
        } catch (\Throwable $e) {
            Log::warning('Shorts: Meta oEmbed failed', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }

        return [
            'source' => $platform,
            'source_url' => $url,
            'source_video_id' => $payload['media_id'] ?? null,
            'embed_html' => $payload['html'] ?? null,
            'title' => $payload['title'] ?? null,
            'duration' => null,
            'thumbnail_remote' => $payload['thumbnail_url'] ?? null,
            'author' => $payload['author_name'] ?? null,
        ];
    }

    protected function tiktokVideoId(string $url): ?string
    {
        return preg_match('#/video/(\d+)#', $url, $matches) ? $matches[1] : null;
    }

    /**
     * YouTube reports duration as ISO-8601 ("PT1M13S").
     */
    public function parseIso8601Duration(?string $duration): ?int
    {
        if (! $duration) {
            return null;
        }

        if (! preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $duration, $m)) {
            return null;
        }

        return ((int) ($m[1] ?? 0)) * 86400
            + ((int) ($m[2] ?? 0)) * 3600
            + ((int) ($m[3] ?? 0)) * 60
            + ((int) ($m[4] ?? 0));
    }
}
