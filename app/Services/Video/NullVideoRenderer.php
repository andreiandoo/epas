<?php

namespace App\Services\Video;

use Illuminate\Http\Request;

/**
 * No render service configured.
 *
 * This is the state the plan calls the "poster short" MVP: instead of rendering
 * video from an event's images, GenerateShortFromEventJob produces a still-image
 * short that the feed plays as a card. It fills the feed today, and swapping in a
 * real renderer later changes nothing else.
 */
class NullVideoRenderer implements VideoRenderer
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function render(string $template, array $payload): string
    {
        throw new \RuntimeException('No video renderer configured. Set services.render.driver and its credentials.');
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function parseWebhook(Request $request): array
    {
        return ['job_id' => null, 'state' => 'pending', 'url' => null];
    }
}
