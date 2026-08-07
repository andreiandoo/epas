<?php

namespace App\Jobs\Shorts;

use App\Models\Event;
use App\Models\Short;
use App\Services\Video\VideoRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fills the feed for events that have no vertical video (B3).
 *
 * Most events never get one, and an empty feed is the fastest way to kill a
 * discovery surface. This builds one out of what every event already has: a
 * poster, a hero image, a gallery, a title and a date.
 *
 * Two modes, decided by whether a render service is configured:
 *   - renderer available → a real vertical clip (Ken-Burns + title + end card);
 *   - no renderer        → a "poster short": a still played as a card in the feed.
 *
 * Both land as draft. Automatic generation is a suggestion, not curation.
 */
class GenerateShortFromEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const TEMPLATE = 'event-vertical-v1';

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(protected int $eventId) {}

    public function handle(VideoRenderer $renderer): void
    {
        $event = Event::find($this->eventId);

        if (! $event) {
            return;
        }

        if ($this->alreadyHasShort($event)) {
            return;
        }

        $images = $this->images($event);

        if ($images === []) {
            Log::info('GenerateShortFromEventJob: no usable images', ['event_id' => $event->id]);

            return;
        }

        $short = Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'owner_type' => Event::class,
            'owner_id' => $event->id,
            'event_id' => $event->id,
            'tenant_id' => $event->tenant_id,
            'title' => $event->title,
            'poster_path' => $images[0],
            'status' => Short::STATUS_DRAFT,
            'is_generated' => true,
            'cta_type' => 'buy_tickets',
            'cta_label' => 'Ia bilet',
        ]);

        if (! $renderer->isConfigured()) {
            // Poster-short MVP: no video asset, so it is "ready" as soon as it
            // exists — the feed renders the still as a card.
            $short->forceFill(['ready' => true])->save();

            return;
        }

        try {
            $jobId = $renderer->render(self::TEMPLATE, [
                'images' => $images,
                'title' => $event->title,
                'subtitle' => $event->event_date?->format('d M Y'),
            ]);

            // Recorded so a re-run cannot queue a second render for the same short.
            $short->forceFill(['render_job_id' => $jobId])->save();
        } catch (\Throwable $e) {
            Log::warning('GenerateShortFromEventJob: render failed, keeping the poster short', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            $short->forceFill(['ready' => true])->save();
        }
    }

    protected function alreadyHasShort(Event $event): bool
    {
        return Short::query()->where('event_id', $event->id)->exists();
    }

    /**
     * Poster, hero and gallery — whatever the event actually has, best first.
     *
     * @return array<int, string>
     */
    protected function images(Event $event): array
    {
        $candidates = [
            $event->poster_url ?? null,
            $event->hero_image_url ?? null,
        ];

        $gallery = $event->gallery ?? null;

        if (is_array($gallery)) {
            $candidates = array_merge($candidates, $gallery);
        }

        return array_values(array_slice(array_unique(array_filter($candidates)), 0, 5));
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateShortFromEventJob failed', ['event_id' => $this->eventId, 'error' => $e->getMessage()]);
    }
}
