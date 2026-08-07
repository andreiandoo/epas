<?php

namespace App\Services\Video;

use Illuminate\Http\Request;

/**
 * No-op provider used when no managed video service is configured (dev boxes,
 * CI, and any environment still running on placeholder Bunny keys).
 *
 * Reads degrade to null so the feed keeps rendering self-hosted and external
 * shorts; writes fail loudly so a misconfiguration is never silent.
 */
class NullVideoProvider implements VideoProvider
{
    public function name(): string
    {
        return 'self';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function createDirectUpload(array $meta): array
    {
        throw new \RuntimeException('No video provider configured. Set services.video.driver and the provider credentials.');
    }

    public function ingestFromUrl(string $url, ?string $title = null): string
    {
        throw new \RuntimeException('No video provider configured. Set services.video.driver and the provider credentials.');
    }

    public function getPlayback(string $assetId): array
    {
        return ['ready' => false, 'duration' => null, 'width' => null, 'height' => null];
    }

    public function signedHls(string $assetId, int $ttl = 3600): ?string
    {
        return null;
    }

    public function signedPoster(string $assetId, int $ttl = 3600): ?string
    {
        return null;
    }

    public function delete(string $assetId): void
    {
        // Nothing hosted anywhere — nothing to delete.
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function parseWebhook(Request $request): array
    {
        return ['asset_id' => null, 'state' => 'pending'];
    }
}
