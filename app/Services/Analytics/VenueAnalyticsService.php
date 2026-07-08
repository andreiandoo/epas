<?php

namespace App\Services\Analytics;

use App\Models\Venue;
use App\Support\PiiMask;
use App\Support\VenueAnalyticsMethods;
use Illuminate\Support\Facades\Cache;

/**
 * Public API surface for venue analytics. Wraps the same
 * VenueAnalyticsMethods trait that powers the internal admin page
 * (VenueAnalyticsPage), so the metrics stay identical across UI + API,
 * with two adaptations:
 *
 *   1. PII masking on the superfans / customer-level rows before
 *      they leave the platform.
 *   2. Public endpoints only expose read-only aggregates. The simulator
 *      is safe to expose (it doesn't touch PII); customer export lives
 *      on a separate, more heavily scoped route (not built here).
 *
 * Each public method returns primitives ready for JSON serialization
 * and caches for 5 minutes — same window as the admin UI.
 */
class VenueAnalyticsService
{
    use VenueAnalyticsMethods;

    private const CACHE_TTL = 300;

    /**
     * The trait's queries read $this->venueIds + $this->venue, so we
     * seed those from the passed Venue on every call. The service is
     * still stateless from the caller's perspective — no prior
     * "attach venue" step needed.
     */
    public Venue $venue;
    public array $venueIds = [];
    public ?int $selectedVenueId = null;

    private function bind(Venue $venue): void
    {
        $this->venue = $venue;
        $this->venueIds = [$venue->id];
        $this->selectedVenueId = $venue->id;
    }

    /**
     * Overview: KPIs + venue health score + monthly momentum + 12-month
     * time series. Everything shown at the top of the admin analytics
     * page, in one payload.
     */
    public function overview(Venue $venue): array
    {
        return Cache::remember("api:analytics:venue:{$venue->id}:overview", self::CACHE_TTL, function () use ($venue) {
            $this->bind($venue);
            $eventIds = $this->venueEventIds();
            $orderIds = $this->venueOrderIds($eventIds);

            [$months, $events, $tickets, $revenue, $occupancy] = $this->buildVenueYearlySeries($eventIds);

            return [
                'venue_id' => $venue->id,
                'venue_name' => $this->decodeJsonName($venue->name),
                'city' => $venue->city,
                'kpis' => $this->computeVenueKpis($eventIds, $orderIds),
                'health_score' => $this->buildVenueHealthScore($eventIds, $orderIds),
                'monthly_momentum' => $this->buildMonthlyMomentum($eventIds, $orderIds),
                'monthly' => [
                    'months' => $months,
                    'events' => $events,
                    'tickets' => $tickets,
                    'revenue' => $revenue,
                    'avg_occupancy_pct' => $occupancy,
                ],
            ];
        });
    }

    /**
     * Event-level slice: recent past performance table + upcoming
     * events with sell-through forecasts.
     */
    public function events(Venue $venue): array
    {
        return Cache::remember("api:analytics:venue:{$venue->id}:events", self::CACHE_TTL, function () use ($venue) {
            $this->bind($venue);
            $eventIds = $this->venueEventIds();

            return [
                'venue_id' => $venue->id,
                'events' => $this->buildEventPerformanceTable($eventIds),
                'upcoming' => $this->buildUpcomingVenueEvents($eventIds),
            ];
        });
    }

    /**
     * Financial slice: revenue breakdown (genre / channel / day type),
     * pricing intelligence with sweet-spot detection, refund analysis,
     * revenue-per-seat, and revenue forecast.
     */
    public function revenue(Venue $venue): array
    {
        return Cache::remember("api:analytics:venue:{$venue->id}:revenue", self::CACHE_TTL, function () use ($venue) {
            $this->bind($venue);
            $eventIds = $this->venueEventIds();
            $orderIds = $this->venueOrderIds($eventIds);

            return [
                'venue_id' => $venue->id,
                'breakdown' => $this->buildRevenueBreakdown($eventIds, $orderIds),
                'pricing' => $this->buildPricingIntelligence($eventIds),
                'refunds' => $this->buildRefundAnalysis($eventIds),
                'revenue_per_seat' => $this->buildRevenuePerSeat($eventIds),
                'forecast' => $this->buildRevenueForecast($eventIds),
            ];
        });
    }

    /**
     * Audience slice: personas, customer loyalty, geographic origin,
     * check-in arrival times. Superfans are masked to buyer_hash /
     * city / event count only.
     */
    public function audience(Venue $venue): array
    {
        return Cache::remember("api:analytics:venue:{$venue->id}:audience", self::CACHE_TTL, function () use ($venue) {
            $this->bind($venue);
            $eventIds = $this->venueEventIds();
            $orderIds = $this->venueOrderIds($eventIds);

            $loyalty = $this->buildVenueCustomerLoyalty($eventIds, $orderIds);

            // Superfans on the loyalty payload carry customer name/email
            // in the admin UI — strip and hash for the API surface.
            if (isset($loyalty['superfan_details']) && is_array($loyalty['superfan_details'])) {
                $loyalty['superfan_details'] = array_map(function ($s) {
                    return [
                        'buyer_hash' => PiiMask::buyerHash($s['email'] ?? $s['name'] ?? ''),
                        'city' => $s['city'] ?? null,
                        'events' => (int) ($s['events'] ?? 0),
                        'orders' => (int) ($s['orders'] ?? 0),
                        'total_spent' => (float) ($s['total_spent'] ?? 0),
                    ];
                }, $loyalty['superfan_details']);
            }

            return [
                'venue_id' => $venue->id,
                'personas' => $this->buildVenueAudiencePersonas($orderIds),
                'loyalty' => $loyalty,
                'geographic_origin' => $this->buildGeographicOrigin($orderIds),
                'checkin_analysis' => $this->buildCheckinTimeAnalysis($eventIds),
                'genre_loyalty' => $this->buildGenreLoyalty($eventIds, $orderIds),
            ];
        });
    }

    /**
     * Programming slice: artist performance at venue, genre performance,
     * booking suggestions (never-played artists), scheduling heatmap,
     * day-of-week analysis, seasonality, idle days.
     */
    public function programming(Venue $venue): array
    {
        return Cache::remember("api:analytics:venue:{$venue->id}:programming", self::CACHE_TTL, function () use ($venue) {
            $this->bind($venue);
            $eventIds = $this->venueEventIds();

            return [
                'venue_id' => $venue->id,
                'artist_performance' => $this->buildArtistPerformanceAtVenue($eventIds),
                'genre_performance' => $this->buildGenrePerformance($eventIds),
                'never_played_artists' => $this->buildNeverPlayedArtists($eventIds),
                'scheduling_heatmap' => $this->buildSchedulingHeatmap($eventIds),
                'day_of_week' => $this->buildDayOfWeekAnalysis($eventIds),
                'seasonality' => $this->buildSeasonalityAnalysis($eventIds),
                'idle_days' => $this->buildIdleDaysAnalysis($eventIds),
            ];
        });
    }

    /**
     * Actionable slice: churn risk alerts + prioritized action list
     * + opportunities engine recommendations.
     */
    public function actions(Venue $venue): array
    {
        return Cache::remember("api:analytics:venue:{$venue->id}:actions", self::CACHE_TTL, function () use ($venue) {
            $this->bind($venue);
            $eventIds = $this->venueEventIds();
            $orderIds = $this->venueOrderIds($eventIds);

            return [
                'venue_id' => $venue->id,
                'churn_alerts' => $this->buildChurnRiskAlerts($eventIds, $orderIds),
                'opportunities' => $this->buildOpportunities($eventIds, $orderIds),
                'action_priority' => $this->buildActionPriority($eventIds, $orderIds),
            ];
        });
    }

    /**
     * Forecast slice: revenue forecast + event suggestions
     * (recommended future bookings) + promotion planner.
     */
    public function forecast(Venue $venue): array
    {
        return Cache::remember("api:analytics:venue:{$venue->id}:forecast", self::CACHE_TTL, function () use ($venue) {
            $this->bind($venue);
            $eventIds = $this->venueEventIds();
            $orderIds = $this->venueOrderIds($eventIds);

            return [
                'venue_id' => $venue->id,
                'revenue_forecast' => $this->buildRevenueForecast($eventIds),
                'event_suggestions' => $this->buildEventSuggestions($eventIds, $orderIds),
                'promotion_planner' => $this->buildPromotionPlanner($eventIds, $orderIds),
            ];
        });
    }

    /**
     * Event simulator: given a hypothetical (genre, day of week, ticket
     * price), predict sell-through and revenue based on historical
     * patterns at this venue. Not cached because it's parametric on
     * caller input.
     */
    public function simulate(Venue $venue, string $genre, string $dayOfWeek, float $ticketPrice): array
    {
        $this->bind($venue);
        $eventIds = $this->venueEventIds();
        $orderIds = $this->venueOrderIds($eventIds);

        return array_merge(
            ['venue_id' => $venue->id],
            $this->simulateEvent($eventIds, $orderIds, $genre, $dayOfWeek, $ticketPrice)
        );
    }
}
