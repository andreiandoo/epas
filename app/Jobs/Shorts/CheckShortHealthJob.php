<?php

namespace App\Jobs\Shorts;

use App\Models\Short;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Keeps dead and expired shorts out of the feed (§14, B8).
 *
 * Three things go stale on their own:
 *   - stories, which expire by design after 24h;
 *   - anything past its expires_at;
 *   - external embeds whose source video was deleted or made private.
 *
 * The third is the reason this runs at all: an embed that 404s renders as a
 * broken frame in the middle of an infinite feed, and nobody reports it.
 */
class CheckShortHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** External shorts re-checked per run — the API budget, not a hard limit. */
    private const EXTERNAL_BATCH = 100;

    public int $tries = 1;

    public int $timeout = 600;

    public function handle(): void
    {
        $expired = $this->archiveExpired();
        $dead = $this->archiveDeadEmbeds();

        if ($expired > 0 || $dead > 0) {
            Log::info('CheckShortHealthJob: archived stale shorts', [
                'expired' => $expired,
                'dead_embeds' => $dead,
            ]);
        }
    }

    /**
     * Anything past its window, stories included.
     */
    protected function archiveExpired(): int
    {
        return Short::query()
            ->whereIn('status', [Short::STATUS_PUBLISHED, Short::STATUS_PENDING_REVIEW])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => Short::STATUS_ARCHIVED]);
    }

    /**
     * External embeds whose source is gone. Only YouTube is checked here: its
     * thumbnail endpoint is a cheap, unauthenticated liveness probe. TikTok and
     * Meta have no equivalent that does not burn quota or need a token.
     *
     * TODO(owner): once the Meta token exists, add an oEmbed probe for IG/FB.
     */
    protected function archiveDeadEmbeds(): int
    {
        $candidates = Short::query()
            ->where('status', Short::STATUS_PUBLISHED)
            ->where('source', 'youtube')
            ->whereNotNull('source_video_id')
            ->orderBy('updated_at')
            ->limit(self::EXTERNAL_BATCH)
            ->get(['id', 'source_video_id']);

        $archived = 0;

        foreach ($candidates as $short) {
            if ($this->youtubeVideoIsGone($short->source_video_id)) {
                Short::query()->whereKey($short->id)->update(['status' => Short::STATUS_ARCHIVED]);
                $archived++;
            }
        }

        return $archived;
    }

    /**
     * YouTube serves the hq thumbnail for any live video and 404s for removed
     * ones. A network error is NOT treated as death — a blip must not archive
     * a healthy short.
     */
    protected function youtubeVideoIsGone(string $videoId): bool
    {
        try {
            $response = Http::timeout(10)->head("https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg");

            return $response->status() === 404;
        } catch (\Throwable) {
            return false;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('CheckShortHealthJob failed', ['error' => $e->getMessage()]);
    }
}
