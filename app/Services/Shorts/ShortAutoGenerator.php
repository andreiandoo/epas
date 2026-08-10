<?php

namespace App\Services\Shorts;

use App\Models\Artist;
use App\Models\Event;
use App\Models\Short;
use App\Models\Venue;
use App\Services\Video\VideoRenderer;
use App\Support\PlainText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Builds a short for anything that has pictures but no video (B3).
 *
 * Most events never get a vertical clip, and almost no artist or venue does. An
 * empty feed is the fastest way to kill a discovery surface, so this fills it
 * from what the catalogue already has: a poster, a hero image, a gallery, a
 * name and a date.
 *
 * Two modes, decided by whether a render service is configured:
 *   - renderer available → a real vertical clip (Ken-Burns + title + end card);
 *   - no renderer        → a "poster short": the still played as a card.
 *
 * The poster short is not a placeholder waiting to be replaced. It is the
 * product for the majority of the catalogue, which is why it is marked ready
 * and served rather than parked in a queue — but it is also marked
 * `is_generated`, so the ranker can prefer real video and an organiser can tell
 * at a glance what they never uploaded themselves.
 */
class ShortAutoGenerator
{
    private const TEMPLATE = 'event-vertical-v1';

    public function __construct(private readonly VideoRenderer $renderer) {}

    /**
     * Generate for one model, or null when there is nothing to build from.
     *
     * Idempotent: an owner that already has a short is skipped, so the sweep can
     * run as often as it likes.
     */
    public function generate(Model $owner): ?Short
    {
        $plan = match (true) {
            $owner instanceof Event => $this->planForEvent($owner),
            $owner instanceof Artist => $this->planForArtist($owner),
            $owner instanceof Venue => $this->planForVenue($owner),
            default => null,
        };

        if (! $plan) {
            return null;
        }

        if ($this->alreadyHasShort($owner, $plan['event_id'] ?? null)) {
            return null;
        }

        if ($plan['images'] === []) {
            Log::info('ShortAutoGenerator: nothing to build from', [
                'owner' => $owner::class,
                'owner_id' => $owner->getKey(),
            ]);

            return null;
        }

        $short = Short::create([
            'source' => Short::SOURCE_UPLOAD,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'event_id' => $plan['event_id'],
            'tenant_id' => $plan['tenant_id'],
            'title' => $plan['title'],
            'poster_path' => $plan['images'][0],
            'status' => $this->initialStatus(),
            'published_at' => $this->initialStatus() === Short::STATUS_PUBLISHED ? now() : null,
            'is_generated' => true,
            'cta_type' => $plan['cta_type'],
            'cta_label' => $plan['cta_label'],
        ]);

        $this->attachMedia($short, $plan);

        return $short;
    }

    /**
     * Draft or straight into the feed.
     *
     * Default is published, and that is a deliberate reversal of how UGC is
     * handled. The images here are the organiser's own artwork, already shown
     * publicly on the event, artist or venue page — putting the same picture in
     * the feed adds no moderation surface that did not already exist. Leaving it
     * as draft would mean the feature does nothing at all unless somebody
     * manually publishes thousands of rows, which is the same as not shipping it.
     *
     * Set SHORTS_AUTOGEN_PUBLISH=false to route them through review instead.
     */
    protected function initialStatus(): string
    {
        return config('shorts.autogen.publish', true)
            ? Short::STATUS_PUBLISHED
            : Short::STATUS_DRAFT;
    }

    protected function attachMedia(Short $short, array $plan): void
    {
        if (! $this->renderer->isConfigured()) {
            // Poster short: there is no video asset, so it is ready the moment
            // it exists — the client renders the still as a card.
            $short->forceFill(['ready' => true])->save();

            return;
        }

        try {
            $jobId = $this->renderer->render(self::TEMPLATE, [
                'images' => $plan['images'],
                'title' => $plan['title'],
                'subtitle' => $plan['subtitle'],
            ]);

            // Recorded so a re-run cannot queue a second render for the same short.
            $short->forceFill(['render_job_id' => $jobId])->save();
        } catch (\Throwable $e) {
            Log::warning('ShortAutoGenerator: render failed, keeping the poster short', [
                'short_id' => $short->id,
                'error' => $e->getMessage(),
            ]);

            $short->forceFill(['ready' => true])->save();
        }
    }

    /**
     * @return array{images: array<int, string>, title: ?string, subtitle: ?string, event_id: ?int, tenant_id: ?int, cta_type: string, cta_label: string}
     */
    protected function planForEvent(Event $event): array
    {
        return [
            'images' => $this->pickImages([
                $event->poster_url,
                $event->hero_image_url,
                ...$this->arrayOf($event->gallery),
            ]),
            'title' => PlainText::of($event->title),
            'subtitle' => $event->event_date?->format('d M Y'),
            'event_id' => $event->getKey(),
            'tenant_id' => $event->tenant_id,
            'cta_type' => 'buy_tickets',
            'cta_label' => 'Ia bilet',
        ];
    }

    /**
     * An artist short points at their next gig when there is one, and at their
     * profile otherwise — a "buy tickets" button with nothing to sell is a dead
     * end, and the feed is the worst place to put one.
     *
     * @return array{images: array<int, string>, title: ?string, subtitle: ?string, event_id: ?int, tenant_id: ?int, cta_type: string, cta_label: string}
     */
    protected function planForArtist(Artist $artist): array
    {
        $nextEvent = $this->nextEventFor($artist);

        return [
            'images' => $this->pickImages([
                $artist->portrait_url,
                $artist->main_image_url,
                $artist->logo_url,
            ]),
            'title' => PlainText::of($artist->name),
            'subtitle' => $nextEvent?->event_date?->format('d M Y'),
            'event_id' => $nextEvent?->getKey(),
            'tenant_id' => $nextEvent?->tenant_id,
            'cta_type' => $nextEvent ? 'buy_tickets' : 'open_artist',
            'cta_label' => $nextEvent ? 'Ia bilet' : 'Vezi artistul',
        ];
    }

    /**
     * @return array{images: array<int, string>, title: ?string, subtitle: ?string, event_id: ?int, tenant_id: ?int, cta_type: string, cta_label: string}
     */
    protected function planForVenue(Venue $venue): array
    {
        return [
            'images' => $this->pickImages([
                $venue->image_url,
                ...$this->arrayOf($venue->gallery),
            ]),
            'title' => PlainText::of($venue->name),
            'subtitle' => $venue->city,
            'event_id' => null,
            'tenant_id' => $venue->tenant_id,
            'cta_type' => 'open_event',
            'cta_label' => 'Vezi evenimentele',
        ];
    }

    protected function nextEventFor(Artist $artist): ?Event
    {
        try {
            return $artist->events()
                ->whereDate('event_date', '>=', now()->toDateString())
                ->orderBy('event_date')
                ->first();
        } catch (\Throwable) {
            // The artist↔event relation is not guaranteed on every deployment;
            // an artist short without a gig attached is still a valid short.
            return null;
        }
    }

    /**
     * A short already exists for this owner — or, for an event, for the event
     * itself however it was attributed. An artist-owned short that carries the
     * event's id is still that event's coverage.
     */
    protected function alreadyHasShort(Model $owner, ?int $eventId): bool
    {
        $query = Short::query()->where(function ($q) use ($owner) {
            $q->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey());
        });

        if ($owner instanceof Event && $eventId) {
            $query->orWhere('event_id', $eventId);
        }

        return $query->exists();
    }

    /**
     * Whatever the record actually has, best first, deduplicated and capped.
     *
     * @param  array<int, mixed>  $candidates
     * @return array<int, string>
     */
    protected function pickImages(array $candidates): array
    {
        $urls = array_filter(
            array_map(fn ($value) => is_string($value) ? trim($value) : null, $candidates),
            fn (?string $value) => $value !== null && $value !== '',
        );

        return array_values(array_slice(array_unique($urls), 0, 5));
    }

    /**
     * `gallery` is a json column on both Event and Venue, but deployments differ
     * on whether it holds bare URLs or objects with a url/path key.
     *
     * @return array<int, string>
     */
    protected function arrayOf(mixed $gallery): array
    {
        if (is_string($gallery)) {
            $gallery = json_decode($gallery, true);
        }

        if (! is_array($gallery)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($item) {
            if (is_string($item)) {
                return $item;
            }

            if (is_array($item)) {
                return $item['url'] ?? $item['path'] ?? $item['src'] ?? null;
            }

            return null;
        }, $gallery)));
    }
}
