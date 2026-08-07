<?php

namespace App\Jobs\Shorts;

use App\Models\Artist;
use App\Models\Event;
use App\Models\Short;
use App\Services\Shorts\ShortIngestService;
use App\Services\YouTubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Seeds the feed from an artist's YouTube channel (B4).
 *
 * Vertical shorts from artists who already have an audience are the cheapest
 * feed coverage there is — no organiser has to do anything.
 *
 * Everything lands as draft: an automated pull is a suggestion, not editorial
 * approval. Dedup is by (source, source_video_id), so re-running is safe.
 */
class PullChannelShortsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** YouTube counts anything up to 60s as a Short. */
    private const MAX_SHORT_SECONDS = 60;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 180;

    public function __construct(
        protected int $artistId,
        protected int $limit = 10,
    ) {}

    public function handle(YouTubeService $youtube, ShortIngestService $ingest): void
    {
        $artist = Artist::find($this->artistId);

        if (! $artist || ! $artist->youtube_id) {
            return;
        }

        $videos = $youtube->getRecentVideos($artist->youtube_id, $this->limit);

        if ($videos === []) {
            return;
        }

        $eventId = $this->upcomingEventId($artist);
        $created = 0;

        foreach ($videos as $video) {
            $videoId = $video['video_id'] ?? null;

            if (! $videoId) {
                continue;
            }

            $duration = $ingest->parseIso8601Duration($video['duration'] ?? null);

            // Only actual Shorts: a 40-minute live set has no place in a
            // vertical feed, and pulling it would just create curation debt.
            if ($duration === null || $duration > self::MAX_SHORT_SECONDS) {
                continue;
            }

            $short = Short::query()->firstOrNew([
                'source' => ShortIngestService::PLATFORM_YOUTUBE,
                'source_video_id' => $videoId,
            ]);

            if ($short->exists) {
                continue;
            }

            $short->fill([
                'source_url' => "https://www.youtube.com/shorts/{$videoId}",
                'owner_type' => Artist::class,
                'owner_id' => $artist->id,
                'event_id' => $eventId,
                'title' => $video['title'] ?? null,
                'duration' => $duration,
                'status' => Short::STATUS_DRAFT,
            ])->save();

            // The link is already known; the job fills embed + thumbnail.
            IngestShortJob::dispatch($short->id);
            $created++;
        }

        Log::info('PullChannelShortsJob: pulled artist shorts', [
            'artist_id' => $artist->id,
            'scanned' => count($videos),
            'created' => $created,
        ]);
    }

    /**
     * Tie the pulled shorts to the artist's next event, when there is one — that
     * is what makes them sell rather than just fill space.
     */
    protected function upcomingEventId(Artist $artist): ?int
    {
        try {
            return Event::query()
                ->whereHas('artists', fn ($q) => $q->whereKey($artist->id))
                ->whereDate('event_date', '>=', now()->toDateString())
                ->orderBy('event_date')
                ->value('id');
        } catch (\Throwable $e) {
            Log::debug('PullChannelShortsJob: could not resolve an upcoming event', [
                'artist_id' => $artist->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('PullChannelShortsJob failed', ['artist_id' => $this->artistId, 'error' => $e->getMessage()]);
    }
}
