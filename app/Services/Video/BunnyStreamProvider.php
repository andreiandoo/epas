<?php

namespace App\Services\Video;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Bunny Stream implementation of the managed video pipeline.
 *
 * Bunny encodes to HLS, generates thumbnails and serves everything from its own
 * pull zone, so no video bytes and no egress touch this server.
 *
 * Playback URLs are signed per request with a short TTL (pull-zone token
 * authentication) and are deliberately NOT persisted on the short.
 */
class BunnyStreamProvider implements VideoProvider
{
    /** Bunny reports status 4 once encoding finished. */
    private const STATUS_FINISHED = 4;

    /** Bunny reports 5 (failed) / 6 (upload failed) on error. */
    private const STATUS_FAILED = [5, 6];

    private string $api = 'https://video.bunnycdn.com';

    public function __construct(
        private readonly string $library,
        private readonly string $apiKey,
        private readonly string $pullZone,
        private readonly string $tokenKey,
        private readonly string $webhookSecret = '',
    ) {}

    public function name(): string
    {
        return 'bunny';
    }

    public function isConfigured(): bool
    {
        return $this->library !== '' && $this->apiKey !== '' && $this->pullZone !== '';
    }

    /**
     * Create the video object, then presign a TUS session so the client can
     * upload the file straight to Bunny.
     */
    public function createDirectUpload(array $meta): array
    {
        $this->assertConfigured();

        $guid = $this->createVideo($meta['title'] ?? 'short');

        // TUS authorisation: SHA256(libraryId + apiKey + expire + videoId).
        $expire = now()->addHour()->timestamp;
        $signature = hash('sha256', $this->library.$this->apiKey.$expire.$guid);

        return [
            'asset_id' => $guid,
            'upload_url' => 'https://video.bunnycdn.com/tusupload',
            'tus_headers' => [
                'AuthorizationSignature' => $signature,
                'AuthorizationExpire' => (string) $expire,
                'LibraryId' => $this->library,
                'VideoId' => $guid,
            ],
        ];
    }

    public function ingestFromUrl(string $url, ?string $title = null): string
    {
        $this->assertConfigured();

        $guid = $this->createVideo($title ?? 'short');

        Http::withHeaders($this->headers())
            ->post("{$this->api}/library/{$this->library}/videos/{$guid}/fetch", ['url' => $url])
            ->throw();

        return $guid;
    }

    public function getPlayback(string $assetId): array
    {
        $this->assertConfigured();

        $video = Http::withHeaders($this->headers())
            ->get("{$this->api}/library/{$this->library}/videos/{$assetId}")
            ->throw()
            ->json();

        return [
            'ready' => (int) ($video['status'] ?? 0) === self::STATUS_FINISHED,
            'duration' => isset($video['length']) ? (int) $video['length'] : null,
            'width' => isset($video['width']) ? (int) $video['width'] : null,
            'height' => isset($video['height']) ? (int) $video['height'] : null,
        ];
    }

    public function signedHls(string $assetId, int $ttl = 3600): ?string
    {
        return $this->sign("/{$assetId}/playlist.m3u8", $ttl);
    }

    public function signedPoster(string $assetId, int $ttl = 3600): ?string
    {
        return $this->sign("/{$assetId}/thumbnail.jpg", $ttl);
    }

    /**
     * Animated scrub preview (used by the player UX work in D9).
     */
    public function signedPreview(string $assetId, int $ttl = 3600): ?string
    {
        return $this->sign("/{$assetId}/preview.webp", $ttl);
    }

    public function delete(string $assetId): void
    {
        $this->assertConfigured();

        Http::withHeaders($this->headers())
            ->delete("{$this->api}/library/{$this->library}/videos/{$assetId}")
            ->throw();
    }

    /**
     * Bunny Stream webhooks are not signed, so we do two things: check the
     * shared secret carried in the query string, and treat the callback purely
     * as a trigger — getPlayback() remains the authoritative read.
     */
    public function verifyWebhook(Request $request): bool
    {
        if ($this->webhookSecret === '') {
            return false;
        }

        $provided = (string) ($request->query('secret') ?? $request->header('X-Webhook-Secret', ''));

        return hash_equals($this->webhookSecret, $provided);
    }

    public function parseWebhook(Request $request): array
    {
        $status = (int) $request->input('Status', 0);

        $state = match (true) {
            $status === self::STATUS_FINISHED => 'ready',
            in_array($status, self::STATUS_FAILED, true) => 'failed',
            default => 'pending',
        };

        return [
            'asset_id' => $request->input('VideoGuid'),
            'state' => $state,
        ];
    }

    private function createVideo(string $title): string
    {
        $guid = Http::withHeaders($this->headers())
            ->post("{$this->api}/library/{$this->library}/videos", ['title' => $title])
            ->throw()
            ->json('guid');

        if (! is_string($guid) || $guid === '') {
            Log::error('BunnyStreamProvider: video creation returned no guid', ['title' => $title]);

            throw new \RuntimeException('Bunny Stream did not return a video guid.');
        }

        return $guid;
    }

    /**
     * Bunny CDN token authentication: base64url(SHA256(key + path + expires)).
     *
     * TODO(owner): confirm the exact token scheme against the pull zone settings
     * once real credentials exist — Bunny offers a couple of variants.
     */
    private function sign(string $path, int $ttl): ?string
    {
        if ($this->pullZone === '') {
            return null;
        }

        if ($this->tokenKey === '') {
            // Token auth not enabled on the pull zone — serve the plain URL.
            return "https://{$this->pullZone}{$path}";
        }

        $expires = time() + $ttl;
        $hash = hash('sha256', $this->tokenKey.$path.$expires, true);
        $token = rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

        return "https://{$this->pullZone}{$path}?token={$token}&expires={$expires}";
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'AccessKey' => $this->apiKey,
            'accept' => 'application/json',
        ];
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException(
                'Bunny Stream is not configured. Set BUNNY_STREAM_LIBRARY_ID, BUNNY_STREAM_API_KEY and BUNNY_STREAM_PULL_ZONE.'
            );
        }
    }
}
