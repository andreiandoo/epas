<?php

namespace App\Services\Cache;

use App\Models\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cross-host cache invalidator for ambilet.ro (and any other marketplace
 * that opts into the same protocol).
 *
 * When a seating layout — or one of its sections — or an event itself is
 * saved on Tixello admin, the ambilet front-end's page-cache (cached HTML)
 * and api_cached() preload (`event_preload_<slug>` JSON in /tmp) keep
 * serving stale data until their TTL expires (5 min / 30 min). This
 * service tells ambilet to drop those entries for the affected events, so
 * the next visitor gets the fresh page — no waiting, no ?preview=1.
 *
 * Sync HTTP with a 3s timeout — fire-and-forget. If ambilet is down or
 * the request times out, we just log it; the worst case is the existing
 * TTLs still apply.
 */
class AmbiletCacheBuster
{
    /**
     * Debounce for layout busts: repeated calls within this window coalesce
     * into a single HTTP round-trip, so a designer save that updates 50
     * sections in a transaction only fires one webhook.
     */
    public const BUST_DEBOUNCE_SECONDS = 5;

    /**
     * Bust caches for every event that uses the given seating layout.
     */
    public function bustLayout(int $layoutId): void
    {
        if (!$this->configured()) {
            // No bust target configured — silently skip. Marketplaces
            // without a separate front-end (Filament-only deploys) hit
            // this branch.
            return;
        }

        $debounceKey = 'ambilet_bust_pending_layout_' . $layoutId;
        if (Cache::has($debounceKey)) {
            return;
        }
        Cache::put($debounceKey, 1, self::BUST_DEBOUNCE_SECONDS);

        $slugs = Event::query()
            ->where('seating_layout_id', $layoutId)
            ->whereNotNull('slug')
            ->pluck('slug')
            ->all();

        $this->postSlugs($slugs, ['layout_id' => $layoutId]);
    }

    /**
     * Bust caches for one event (plus its parent, so a performance child
     * page refreshes too). Called after an admin saves the event. Not
     * debounced: each admin save is a single deliberate action and a
     * skipped second save would leave its changes stale for 30 minutes.
     */
    public function bustEvent(Event $event): void
    {
        if (!$event->id || !$this->configured()) {
            return;
        }

        $slugs = Event::query()
            ->whereIn('id', array_filter([$event->id, $event->parent_id]))
            ->whereNotNull('slug')
            ->pluck('slug')
            ->all();

        $this->postSlugs($slugs, ['event_id' => $event->id]);
    }

    protected function configured(): bool
    {
        return (bool) config('services.ambilet.cache_bust_url')
            && (bool) config('services.ambilet.cache_bust_token');
    }

    /**
     * POST the slugs to ambilet's cache-bust endpoint. Translatable slugs
     * (arrays) are flattened; ambilet itself rejects anything that isn't
     * [a-z0-9-].
     */
    protected function postSlugs(array $slugs, array $context): void
    {
        $slugs = collect($slugs)
            ->flatMap(fn ($s) => is_array($s) ? array_values($s) : [$s])
            ->filter(fn ($s) => is_string($s) && $s !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($slugs)) {
            return;
        }

        try {
            $response = Http::timeout(3)
                ->connectTimeout(2)
                ->acceptJson()
                ->post(config('services.ambilet.cache_bust_url'), [
                    'token' => config('services.ambilet.cache_bust_token'),
                    'slugs' => $slugs,
                ]);

            if (!$response->successful()) {
                Log::channel('marketplace')->warning('Ambilet cache bust returned non-2xx', $context + [
                    'slugs' => $slugs,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('marketplace')->warning('Ambilet cache bust failed', $context + [
                'slugs_count' => count($slugs),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
