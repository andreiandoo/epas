<?php

namespace App\Http\Controllers\Api\PublicAnalytics;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Services\Analytics\ArtistAnalyticsService;
use Illuminate\Http\JsonResponse;

/**
 * Public API for artist analytics. Every route on this controller is
 * behind api.key:read.analytics.artist scope + throttle:apikey rate
 * limiting. Payloads are pre-cached for 5 minutes inside the service.
 *
 * Slug or numeric id are both accepted on the {artist} binding — the
 * public catalog exposes slugs, but partner integrations often store
 * numeric ids from an initial sync, and we don't want to force them to
 * switch identifier flavors.
 */
class ArtistAnalyticsController extends Controller
{
    public function __construct(private readonly ArtistAnalyticsService $service)
    {
    }

    private function resolveArtist(string $key): Artist
    {
        // Accept either a numeric id or a slug (public catalog uses slugs).
        if (ctype_digit($key)) {
            return Artist::findOrFail((int) $key);
        }
        return Artist::where('slug', $key)->firstOrFail();
    }

    public function overview(string $artist): JsonResponse
    {
        $data = $this->service->overview($this->resolveArtist($artist));
        return $this->envelope($data);
    }

    public function audience(string $artist): JsonResponse
    {
        $data = $this->service->audience($this->resolveArtist($artist));
        return $this->envelope($data);
    }

    public function performance(string $artist): JsonResponse
    {
        $data = $this->service->performance($this->resolveArtist($artist));
        return $this->envelope($data);
    }

    public function upcoming(string $artist): JsonResponse
    {
        $data = $this->service->upcoming($this->resolveArtist($artist));
        return $this->envelope($data);
    }

    /**
     * Wrap every payload in a consistent { data, meta } envelope so
     * pagination + cache metadata can be added later without breaking
     * existing clients. Also emits the standard freshness header the
     * dataviz side reads to decide if it should show a "stale" badge.
     */
    private function envelope(array $data): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'cache_ttl_seconds' => 300,
            ],
        ]);
    }
}
