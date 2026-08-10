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

    /** Cate short-uri aduse automat se tin, per artist. Fereastra glisanta. */
    public const MAX_PER_OWNER = 6;

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
                /* Butonul principal. Fara el, `cta_type` ramanea implicit
                   'none', payload-ul intorcea `cta: null` si short-ul aparea in
                   feed FARA nicio actiune — un clip de artist din care nu se
                   putea ajunge la artist. */
                'cta_type' => 'open_artist',
                'cta_label' => 'Vezi profil',
                /* Marcheaza randul ca adus de automat, nu incarcat de om. E si
                   singurul semn dupa care plafonul de mai jos stie ce are voie
                   sa stearga. Nu atrage penalizarea de „poster fara video" din
                   ranker: aceea sare peste short-urile externe (isPosterOnly). */
                'is_generated' => true,
                /* Posterul se pune ACUM, din raspunsul API-ului, nu se asteapta
                   IngestShortJob. Ingestul completeaza embed-ul si un thumbnail
                   mai bun, dar el poate esua sau sta la coada — si atunci
                   short-ul ramanea fara imagine, ceea ce se si vedea in lista:
                   unele cu cover, altele fara. Ingestul il suprascrie cand
                   reuseste. */
                'poster_path' => $video['thumbnail'] ?: "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg",
            ])->save();

            // The link is already known; the job fills embed + thumbnail.
            IngestShortJob::dispatch($short->id);
            $created++;
        }

        $trimmed = $this->trimToCap($artist);

        Log::info('PullChannelShortsJob: pulled artist shorts', [
            'artist_id' => $artist->id,
            'scanned' => count($videos),
            'created' => $created,
            'trimmed' => $trimmed,
        ]);
    }

    /**
     * Pastreaza cele mai recente MAX_PER_OWNER short-uri aduse de aici; restul
     * se sterg.
     *
     * Un canal activ ar acumula altfel zeci de short-uri pentru acelasi artist,
     * care ar sufoca feed-ul si ar transforma curatarea intr-o corvoada
     * manuala. Plafonul face preluarea sa se comporte ca o fereastra glisanta:
     * apare unul nou, pleaca cel mai vechi.
     *
     * Se sterg DOAR randurile aduse automat de aici — `source = youtube` si
     * `is_generated` — deci un short incarcat sau editat de om nu intra
     * niciodata in socoteala si nu poate fi sters de o rulare programata.
     */
    protected function trimToCap(Artist $artist): int
    {
        $keepIds = Short::query()
            ->where('owner_type', Artist::class)
            ->where('owner_id', $artist->id)
            ->where('source', ShortIngestService::PLATFORM_YOUTUBE)
            ->where('is_generated', true)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::MAX_PER_OWNER)
            ->pluck('id');

        $stale = Short::query()
            ->where('owner_type', Artist::class)
            ->where('owner_id', $artist->id)
            ->where('source', ShortIngestService::PLATFORM_YOUTUBE)
            ->where('is_generated', true)
            ->whereNotIn('id', $keepIds)
            ->get();

        foreach ($stale as $short) {
            $short->delete();
        }

        return $stale->count();
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
