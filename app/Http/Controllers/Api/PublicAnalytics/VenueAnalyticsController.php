<?php

namespace App\Http\Controllers\Api\PublicAnalytics;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Services\Analytics\VenueAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public API for venue analytics. Behind api.key:read.analytics.venue.
 * Payloads mirror the admin VenueAnalyticsPage sections (overview,
 * events, revenue, audience, programming, actions, forecast) and are
 * cached for 5 minutes inside the service. The event simulator is
 * exposed via POST since it takes body params (genre, day, price).
 */
class VenueAnalyticsController extends Controller
{
    public function __construct(private readonly VenueAnalyticsService $service)
    {
    }

    private function resolveVenue(string $key): Venue
    {
        if (ctype_digit($key)) {
            return Venue::findOrFail((int) $key);
        }
        return Venue::where('slug', $key)->firstOrFail();
    }

    public function overview(string $venue): JsonResponse
    {
        return $this->envelope($this->service->overview($this->resolveVenue($venue)));
    }

    public function events(string $venue): JsonResponse
    {
        return $this->envelope($this->service->events($this->resolveVenue($venue)));
    }

    public function revenue(string $venue): JsonResponse
    {
        return $this->envelope($this->service->revenue($this->resolveVenue($venue)));
    }

    public function audience(string $venue): JsonResponse
    {
        return $this->envelope($this->service->audience($this->resolveVenue($venue)));
    }

    public function programming(string $venue): JsonResponse
    {
        return $this->envelope($this->service->programming($this->resolveVenue($venue)));
    }

    public function actions(string $venue): JsonResponse
    {
        return $this->envelope($this->service->actions($this->resolveVenue($venue)));
    }

    public function forecast(string $venue): JsonResponse
    {
        return $this->envelope($this->service->forecast($this->resolveVenue($venue)));
    }

    /**
     * Event simulator. Body params validated inline so the API returns
     * 422 with a clear message instead of a 500 when the caller sends
     * junk. Not cached — the return depends on caller inputs.
     */
    public function simulate(string $venue, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'genre' => 'required|string|max:120',
            'day_of_week' => 'required|string|max:20',
            'ticket_price' => 'required|numeric|min:0|max:10000',
        ]);

        $data = $this->service->simulate(
            $this->resolveVenue($venue),
            $validated['genre'],
            $validated['day_of_week'],
            (float) $validated['ticket_price']
        );

        return $this->envelope($data);
    }

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
