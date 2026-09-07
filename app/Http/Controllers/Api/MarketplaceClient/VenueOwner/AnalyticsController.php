<?php

namespace App\Http\Controllers\Api\MarketplaceClient\VenueOwner;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Services\VenueOwner\VenueOwnerAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Analytics API for the /venue/analiza web shell on ambilet.ro.
 *
 * Each action fetches ONE tab worth of data from
 * VenueOwnerAnalyticsService — the shell polls per tab as the user
 * navigates, so we don't waste queries computing everything up-front.
 *
 * All routes live under /api/marketplace-client/venue-owner/analytics
 * and inherit auth:sanctum + venue.owner middleware from the parent
 * group in routes/api.php. The middleware guarantees the caller is a
 * User(role=tenant) whose Tenant(tenant_type=venue) has at least one
 * venue partnered with the current marketplace client — so we only
 * need to resolve WHICH venues to scope to.
 *
 * The venue-id resolution restricts to venues partnered with the
 * calling marketplace client (matches the middleware's authorization
 * check + prevents leaking analytics for venues at other marketplaces).
 * Optional ?venue_id={id} query param narrows a multi-venue owner to
 * one location; unknown / unowned ids fall back to the full set.
 */
class AnalyticsController extends Controller
{
    private function makeService(Request $request): ?VenueOwnerAnalyticsService
    {
        $tenant = $request->attributes->get('venue_owner_tenant');
        $client = $request->attributes->get('marketplace_client');

        if (!$tenant || !$client) {
            return null;
        }

        $venueIds = $tenant->venues()
            ->partnerOfMarketplace($client->id)
            ->pluck('venues.id')
            ->toArray();

        if (empty($venueIds)) {
            return null;
        }

        // Optional single-venue focus (?venue_id=). Ignore silently if the
        // caller requests a venue they don't own — same failure mode as
        // omitting the param, which returns cross-venue aggregates.
        $focusedVenue = null;
        if ($request->filled('venue_id')) {
            $focusedId = (int) $request->query('venue_id');
            if (in_array($focusedId, $venueIds, true)) {
                $focusedVenue = Venue::find($focusedId);
                if ($focusedVenue) {
                    $venueIds = [$focusedId];
                }
            }
        }

        return new VenueOwnerAnalyticsService($venueIds, $focusedVenue);
    }

    private function respond(callable $fn): JsonResponse
    {
        try {
            $data = $fn();
            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (\Throwable $e) {
            Log::channel('marketplace')->error('VenueOwner analytics failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Analytics could not be computed. Please try again.',
            ], 500);
        }
    }

    private function empty(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'No partnered venues available for this account on this marketplace.',
        ], 404);
    }

    // ─── Tab endpoints ──────────────────────────────────────────────

    public function overview(Request $request): JsonResponse
    {
        $service = $this->makeService($request);
        if (!$service) return $this->empty();
        return $this->respond(fn () => $service->overviewTab());
    }

    public function financial(Request $request): JsonResponse
    {
        $service = $this->makeService($request);
        if (!$service) return $this->empty();
        return $this->respond(fn () => $service->financialTab());
    }

    public function audience(Request $request): JsonResponse
    {
        $service = $this->makeService($request);
        if (!$service) return $this->empty();
        return $this->respond(fn () => $service->audienceTab());
    }

    public function artists(Request $request): JsonResponse
    {
        $service = $this->makeService($request);
        if (!$service) return $this->empty();
        return $this->respond(fn () => $service->artistsTab());
    }

    public function scheduling(Request $request): JsonResponse
    {
        $service = $this->makeService($request);
        if (!$service) return $this->empty();
        return $this->respond(fn () => $service->schedulingTab());
    }

    public function opportunities(Request $request): JsonResponse
    {
        $service = $this->makeService($request);
        if (!$service) return $this->empty();
        return $this->respond(fn () => $service->opportunitiesTab());
    }

    public function promotion(Request $request): JsonResponse
    {
        $service = $this->makeService($request);
        if (!$service) return $this->empty();
        return $this->respond(fn () => $service->promotionTab());
    }

    public function upcoming(Request $request): JsonResponse
    {
        $service = $this->makeService($request);
        if (!$service) return $this->empty();
        return $this->respond(fn () => $service->upcomingTab());
    }

    public function actions(Request $request): JsonResponse
    {
        $service = $this->makeService($request);
        if (!$service) return $this->empty();
        return $this->respond(fn () => $service->actionsTab());
    }

    // ─── Interactive endpoints ──────────────────────────────────────

    /**
     * Event simulator. Body: { genre: string, day_of_week: string, ticket_price: number }.
     * Ports the Filament page's simulateEventApi to a plain POST.
     */
    public function simulate(Request $request): JsonResponse
    {
        $service = $this->makeService($request);
        if (!$service) return $this->empty();

        $validated = $request->validate([
            'genre'        => 'required|string|max:80',
            'day_of_week'  => 'required|string|max:20',
            'ticket_price' => 'required|numeric|min:0|max:100000',
        ]);

        return $this->respond(fn () => $service->simulate(
            $validated['genre'],
            $validated['day_of_week'],
            (float) $validated['ticket_price']
        ));
    }

    /**
     * Booking suggestions for the venue based on audience/genre history.
     * Same shape as Filament's getEventSuggestionsApi.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $service = $this->makeService($request);
        if (!$service) return $this->empty();
        return $this->respond(fn () => $service->eventSuggestions());
    }

    /**
     * Creative calendar for a specific event id. Same shape as the
     * Filament getCreativeCalendarApi.
     */
    public function creativeCalendar(Request $request, int $event): JsonResponse
    {
        $service = $this->makeService($request);
        if (!$service) return $this->empty();
        return $this->respond(fn () => $service->creativeCalendar($event));
    }

    /**
     * Side-by-side comparison of two events. Body: { event_a: int, event_b: int }.
     */
    public function compare(Request $request): JsonResponse
    {
        $service = $this->makeService($request);
        if (!$service) return $this->empty();

        $validated = $request->validate([
            'event_a' => 'required|integer|min:1',
            'event_b' => 'required|integer|min:1',
        ]);

        return $this->respond(fn () => $service->eventComparison(
            $validated['event_a'],
            $validated['event_b']
        ));
    }
}
