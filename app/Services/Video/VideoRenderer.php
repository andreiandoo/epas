<?php

namespace App\Services\Video;

use Illuminate\Http\Request;

/**
 * Managed video rendering — turns images + text into a vertical clip (B3).
 *
 * Separate from VideoProvider: that one stores and serves finished assets, this
 * one produces them. The rendered mp4 is then handed to the provider through
 * ingestFromUrl(), so both halves stay swappable independently.
 *
 * Implementations: NullVideoRenderer (no service configured — the "poster short"
 * MVP path). ShotstackRenderer/CreatomateRenderer plug in here.
 */
interface VideoRenderer
{
    public function isConfigured(): bool;

    /**
     * Start a render. Returns a job id to correlate the webhook with.
     *
     * @param  array<string, mixed>  $payload
     */
    public function render(string $template, array $payload): string;

    public function verifyWebhook(Request $request): bool;

    /**
     * @return array{job_id: string|null, state: string, url: string|null}
     */
    public function parseWebhook(Request $request): array;
}
