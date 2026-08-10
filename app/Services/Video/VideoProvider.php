<?php

namespace App\Services\Video;

use Illuminate\Http\Request;

/**
 * Managed video pipeline behind a swappable contract.
 *
 * The uploaded file never passes through Laravel: the client asks for a direct
 * upload session, uploads straight to the provider, and the provider calls the
 * webhook once the asset is transcoded.
 *
 * Implementations: BunnyStreamProvider (chosen — docs/plans/shorts.md §C),
 * NullVideoProvider (unconfigured/dev fallback).
 */
interface VideoProvider
{
    /**
     * Provider key persisted on shorts.video_provider.
     */
    public function name(): string;

    /**
     * True when the provider has real credentials. Placeholder config in dev
     * yields false so callers can degrade instead of firing doomed HTTP calls.
     */
    public function isConfigured(): bool;

    /**
     * @return array{asset_id: string, upload_url: string, tus_headers?: array<string, string>}
     */
    public function createDirectUpload(array $meta): array;

    /**
     * Server-side ingest from a public URL (used by the auto-generation pipeline).
     */
    public function ingestFromUrl(string $url, ?string $title = null): string;

    /**
     * @return array{ready: bool, duration: int|null, width: int|null, height: int|null}
     */
    public function getPlayback(string $assetId): array;

    /**
     * Short-TTL signed playback URL. Never persist the result — it expires.
     */
    public function signedHls(string $assetId, int $ttl = 3600): ?string;

    public function signedPoster(string $assetId, int $ttl = 3600): ?string;

    public function delete(string $assetId): void;

    public function verifyWebhook(Request $request): bool;

    /**
     * Normalise a provider webhook payload into ['asset_id' => ..., 'state' => ready|failed|pending].
     *
     * @return array{asset_id: string|null, state: string}
     */
    public function parseWebhook(Request $request): array;
}
