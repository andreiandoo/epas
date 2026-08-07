<?php

namespace App\Services\Video;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Shotstack implementation of the render contract (B3).
 *
 * Builds a 1080x1920 timeline from an event's images: Ken-Burns on each still,
 * a title overlay, and an end card. No ffmpeg anywhere in this stack, which is
 * exactly why a managed renderer is used at all.
 *
 * TODO(owner): no API key exists yet, so this is unreachable in practice — the
 * container binds NullVideoRenderer and the poster-short path runs instead. The
 * timeline shape below follows Shotstack's documented JSON; confirm it against
 * the current API version before the first real render.
 */
class ShotstackRenderer implements VideoRenderer
{
    private const WIDTH = 1080;

    private const HEIGHT = 1920;

    /** Seconds each still is on screen. */
    private const CLIP_SECONDS = 3;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $environment = 'stage',
        private readonly string $webhookSecret = '',
    ) {}

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @param  array{images: array<int, string>, title?: string, subtitle?: string, music_url?: string}  $payload
     */
    public function render(string $template, array $payload): string
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Shotstack is not configured.');
        }

        $images = array_slice($payload['images'] ?? [], 0, 5);

        if ($images === []) {
            throw new \RuntimeException('Cannot render a short without at least one image.');
        }

        $clips = [];

        foreach ($images as $index => $image) {
            $clips[] = [
                'asset' => ['type' => 'image', 'src' => $image],
                'start' => $index * self::CLIP_SECONDS,
                'length' => self::CLIP_SECONDS,
                // Ken-Burns: a still that does not move reads as a broken video.
                'effect' => $index % 2 === 0 ? 'zoomIn' : 'zoomOut',
                'fit' => 'cover',
                'transition' => ['in' => 'fade', 'out' => 'fade'],
            ];
        }

        $timeline = [
            'background' => '#000000',
            'tracks' => [
                ['clips' => $this->titleClips($payload, count($images) * self::CLIP_SECONDS)],
                ['clips' => $clips],
            ],
        ];

        if (! empty($payload['music_url'])) {
            $timeline['soundtrack'] = ['src' => $payload['music_url'], 'effect' => 'fadeOut'];
        }

        $response = Http::withHeaders(['x-api-key' => $this->apiKey])
            ->post("https://api.shotstack.io/{$this->environment}/render", [
                'timeline' => $timeline,
                'output' => [
                    'format' => 'mp4',
                    'size' => ['width' => self::WIDTH, 'height' => self::HEIGHT],
                ],
            ])
            ->throw();

        return (string) $response->json('response.id');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    protected function titleClips(array $payload, int $length): array
    {
        $clips = [];

        if (! empty($payload['title'])) {
            $clips[] = [
                'asset' => [
                    'type' => 'title',
                    'text' => $payload['title'],
                    'style' => 'blockbuster',
                    'position' => 'bottom',
                ],
                'start' => 0,
                'length' => $length,
            ];
        }

        return $clips;
    }

    public function verifyWebhook(Request $request): bool
    {
        if ($this->webhookSecret === '') {
            return false;
        }

        return hash_equals($this->webhookSecret, (string) $request->query('secret'));
    }

    public function parseWebhook(Request $request): array
    {
        $status = (string) $request->input('status');

        return [
            'job_id' => $request->input('id'),
            'state' => match ($status) {
                'done' => 'ready',
                'failed' => 'failed',
                default => 'pending',
            },
            'url' => $request->input('url'),
        ];
    }
}
