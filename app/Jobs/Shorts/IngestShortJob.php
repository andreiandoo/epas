<?php

namespace App\Jobs\Shorts;

use App\Models\Short;
use App\Services\Shorts\ShortIngestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fills a short from its source link (docs/plans/social-video-ingestion.md §3.2).
 *
 * The video file is never fetched — only metadata, the embed code and the
 * thumbnail, which is cached locally so the feed does not hotlink a URL the
 * platform may expire.
 *
 * The short stays in draft: ingestion is not curation.
 */
class IngestShortJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

    public int $timeout = 120;

    public function __construct(protected int $shortId) {}

    public function handle(ShortIngestService $ingest): void
    {
        $short = Short::find($this->shortId);

        if (! $short || ! $short->source_url) {
            return;
        }

        $payload = $ingest->ingest($short->source_url);

        if (! $payload) {
            Log::warning('IngestShortJob: nothing could be read from the link', [
                'short_id' => $short->id,
                'url' => $short->source_url,
            ]);

            return;
        }

        $attributes = [
            'source' => $payload['source'],
            'source_video_id' => $payload['source_video_id'],
            'embed_html' => $payload['embed_html'],
            'duration' => $payload['duration'],
        ];

        // Never overwrite a title an admin already wrote by hand.
        if (! $short->title && $payload['title']) {
            $attributes['title'] = $payload['title'];
        }

        if (! $short->poster_path && $payload['thumbnail_remote']) {
            $stored = $this->cacheThumbnail($short, $payload['thumbnail_remote']);

            if ($stored) {
                $attributes['poster_path'] = $stored;
            }
        }

        $short->forceFill(array_filter($attributes, fn ($v) => $v !== null))->save();

        // A cached thumbnail is what the LQIP is computed from.
        if (! empty($attributes['poster_path'])) {
            GenerateBlurhashJob::dispatch($short->id);
        }
    }

    /**
     * Copy the platform thumbnail onto our own disk.
     *
     * Caching thumbnails is accepted practice (unlike re-hosting the video) and
     * it keeps the feed from breaking when a platform rotates its CDN URLs.
     */
    protected function cacheThumbnail(Short $short, string $url): ?string
    {
        try {
            $response = Http::timeout(20)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $extension = match ($response->header('Content-Type')) {
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg',
            };

            $path = "shorts/thumbnails/{$short->id}-".Str::lower(Str::random(8)).".{$extension}";
            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (\Throwable $e) {
            Log::warning('IngestShortJob: thumbnail fetch failed', [
                'short_id' => $short->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('IngestShortJob failed', ['short_id' => $this->shortId, 'error' => $e->getMessage()]);
    }
}
