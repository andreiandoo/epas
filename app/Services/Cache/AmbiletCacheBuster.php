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
 * When a seating layout — or one of its sections — is saved on Tixello
 * admin, the ambilet front-end's page-cache (cached HTML) and
 * api_cached() preload (`event_preload_<slug>` JSON in /tmp) keep
 * serving stale data until their TTL expires. This service tells
 * ambilet to drop those entries for every event that uses the changed
 * layout, so the next visitor gets the fresh page.
 *
 * Sync HTTP with a 3s timeout — fire-and-forget. If ambilet is down or
 * the request times out, we just log it; the worst case is the existing
 * 5-min page-cache TTL still applies.
 */
class AmbiletCacheBuster
{
    /**
     * Bust caches for every event that uses the given seating layout.
     *
     * Debounced via the Cache facade: repeated calls within
     * BUST_DEBOUNCE_SECONDS coalesce into a single HTTP round-trip, so
     * a designer save that updates 50 sections in a transaction only
     * fires one webhook.
     */
    public const BUST_DEBOUNCE_SECONDS = 5;

    public function bustLayout(int $layoutId): void
    {
        $url = config('services.ambilet.cache_bust_url');
        $token = config('services.ambilet.cache_bust_token');
        if (!$url || !$token) {
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
                ->post($url, [
                    'token' => $token,
                    'slugs' => $slugs,
                ]);

            if (!$response->successful()) {
                Log::channel('marketplace')->warning('Ambilet cache bust returned non-2xx', [
                    'layout_id' => $layoutId,
                    'slugs' => $slugs,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('marketplace')->warning('Ambilet cache bust failed', [
                'layout_id' => $layoutId,
                'slugs_count' => count($slugs),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
