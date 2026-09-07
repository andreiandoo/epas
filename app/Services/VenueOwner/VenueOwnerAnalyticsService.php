<?php

namespace App\Services\VenueOwner;

use App\Models\Venue;
use App\Support\VenueAnalyticsMethods;

/**
 * Analytics service for the authenticated-venue-owner surface (the new
 * ambilet.ro /venue/analiza web shell and its /api/marketplace-client/
 * venue-owner/analytics/* controllers).
 *
 * A separate class from App\Services\Analytics\VenueAnalyticsService
 * on purpose — that one is the PUBLIC API surface with PII masking on
 * customer-level rows + 5-minute Cache::remember. Here the caller has
 * already been authenticated as a venue owner via EnsureVenueOwner
 * middleware, so:
 *
 *   - Superfans / attendee rows return real names/emails (private view).
 *   - No cache layer — the shell already coordinates its own refresh
 *     cadence per tab, and staleness here would leak between owners
 *     who share a marketplace client's cache namespace.
 *
 * Both services reuse the same 1544-line VenueAnalyticsMethods trait,
 * so metric definitions stay identical across public + owner views
 * — only the wrapping (masking, caching) differs.
 *
 * Public methods below wrap the trait's private builders. Grouped by
 * the same tab structure the Filament page uses so consumers can fetch
 * one tab's payload per request rather than the whole 28-section blob.
 */
class VenueOwnerAnalyticsService
{
    use VenueAnalyticsMethods;

    public array $venueIds;
    public ?Venue $venue;

    /**
     * Cached derivations reused across public methods so we don't
     * hit venueEventIds() / venueOrderIds() multiple times per HTTP
     * request. Populated lazily on first access via preload().
     */
    private ?array $cachedEventIds = null;
    private ?array $cachedOrderIds = null;

    public function __construct(array $venueIds, ?Venue $venue = null)
    {
        $this->venueIds = array_values(array_filter(array_map('intval', $venueIds)));
        $this->venue = $venue;
    }

    private function eventIds(): array
    {
        return $this->cachedEventIds ??= $this->venueEventIds();
    }

    private function orderIds(): array
    {
        return $this->cachedOrderIds ??= $this->venueOrderIds($this->eventIds());
    }

    // ─── Overview tab ───────────────────────────────────────────────

    public function kpis(): array
    {
        return $this->computeVenueKpis($this->eventIds(), $this->orderIds());
    }

    public function healthScore(): array
    {
        return $this->buildVenueHealthScore($this->eventIds(), $this->orderIds());
    }

    public function monthlyMomentum(): array
    {
        return $this->buildMonthlyMomentum($this->eventIds(), $this->orderIds());
    }

    public function yearlySeries(): array
    {
        [$months, $events, $tickets, $revenue, $occupancy] = $this->buildVenueYearlySeries($this->eventIds());
        return compact('months', 'events', 'tickets', 'revenue', 'occupancy');
    }

    public function eventPerformance(): array
    {
        return $this->buildEventPerformanceTable($this->eventIds());
    }

    public function competitorBenchmark(): array
    {
        return $this->buildCompetitorBenchmark($this->eventIds());
    }

    // ─── Financial tab ──────────────────────────────────────────────

    public function revenueBreakdown(): array
    {
        return $this->buildRevenueBreakdown($this->eventIds(), $this->orderIds());
    }

    public function pricingIntelligence(): array
    {
        return $this->buildPricingIntelligence($this->eventIds());
    }

    public function refundAnalysis(): array
    {
        return $this->buildRefundAnalysis($this->eventIds());
    }

    public function revenueForecast(): array
    {
        return $this->buildRevenueForecast($this->eventIds());
    }

    public function revenuePerSeat(): array
    {
        return $this->buildRevenuePerSeat($this->eventIds());
    }

    // ─── Audience tab ───────────────────────────────────────────────

    public function audiencePersonas(): array
    {
        return $this->buildVenueAudiencePersonas($this->orderIds());
    }

    public function customerLoyalty(): array
    {
        return $this->buildVenueCustomerLoyalty($this->eventIds(), $this->orderIds());
    }

    public function geographicOrigin(): array
    {
        return $this->buildGeographicOrigin($this->orderIds());
    }

    public function genreLoyalty(): array
    {
        return $this->buildGenreLoyalty($this->eventIds(), $this->orderIds());
    }

    public function checkinAnalysis(): array
    {
        return $this->buildCheckinTimeAnalysis($this->eventIds());
    }

    // ─── Artists tab ────────────────────────────────────────────────

    public function artistPerformance(): array
    {
        return $this->buildArtistPerformanceAtVenue($this->eventIds());
    }

    public function genrePerformance(): array
    {
        return $this->buildGenrePerformance($this->eventIds());
    }

    public function neverPlayedArtists(): array
    {
        return $this->buildNeverPlayedArtists($this->eventIds());
    }

    // ─── Scheduling tab ─────────────────────────────────────────────

    public function schedulingHeatmap(): array
    {
        return $this->buildSchedulingHeatmap($this->eventIds());
    }

    public function dayOfWeekAnalysis(): array
    {
        return $this->buildDayOfWeekAnalysis($this->eventIds());
    }

    public function seasonalityAnalysis(): array
    {
        return $this->buildSeasonalityAnalysis($this->eventIds());
    }

    public function idleDaysAnalysis(): array
    {
        return $this->buildIdleDaysAnalysis($this->eventIds());
    }

    public function salesIntelligence(): array
    {
        return $this->buildSalesIntelligence($this->eventIds(), $this->orderIds());
    }

    // ─── Opportunities tab ──────────────────────────────────────────

    public function opportunities(): array
    {
        return $this->buildOpportunities($this->eventIds(), $this->orderIds());
    }

    public function churnRiskAlerts(): array
    {
        return $this->buildChurnRiskAlerts($this->eventIds(), $this->orderIds());
    }

    /**
     * Event simulator — see what the numbers would look like for a
     * hypothetical event with the given genre, day-of-week and price.
     * Wraps the trait's private simulateEvent().
     */
    public function simulate(string $genre, string $dayOfWeek, float $ticketPrice): array
    {
        return $this->simulateEvent($this->eventIds(), $this->orderIds(), $genre, $dayOfWeek, $ticketPrice);
    }

    public function eventSuggestions(): array
    {
        return $this->buildEventSuggestions($this->eventIds(), $this->orderIds());
    }

    public function eventComparison(int $eventIdA, int $eventIdB): array
    {
        return $this->buildEventComparison($eventIdA, $eventIdB);
    }

    // ─── Promotion tab ──────────────────────────────────────────────

    public function promotionPlanner(): array
    {
        return $this->buildPromotionPlanner($this->eventIds(), $this->orderIds());
    }

    public function creativeCalendar(int $eventId): array
    {
        return $this->buildCreativeCalendar($eventId, $this->eventIds(), $this->orderIds());
    }

    // ─── Upcoming tab ───────────────────────────────────────────────

    public function upcomingEvents(): array
    {
        return $this->buildUpcomingVenueEvents($this->eventIds());
    }

    // ─── Actions tab ────────────────────────────────────────────────

    public function actionPriority(): array
    {
        return $this->buildActionPriority($this->eventIds(), $this->orderIds());
    }

    // ─── Bundle payloads (one tab worth of data per method) ────────

    /**
     * Overview tab — everything above the tabs strip on the Filament
     * page: KPIs, health score, monthly momentum, 12-month time series,
     * event-performance table, competitor benchmark. One HTTP call
     * paints the whole first tab.
     */
    public function overviewTab(): array
    {
        return [
            'kpis' => $this->kpis(),
            'health_score' => $this->healthScore(),
            'monthly_momentum' => $this->monthlyMomentum(),
            'yearly_series' => $this->yearlySeries(),
            'event_performance' => $this->eventPerformance(),
            'competitor_benchmark' => $this->competitorBenchmark(),
        ];
    }

    public function financialTab(): array
    {
        return [
            'revenue_breakdown' => $this->revenueBreakdown(),
            'pricing_intelligence' => $this->pricingIntelligence(),
            'refund_analysis' => $this->refundAnalysis(),
            'revenue_forecast' => $this->revenueForecast(),
            'revenue_per_seat' => $this->revenuePerSeat(),
        ];
    }

    public function audienceTab(): array
    {
        return [
            'audience_personas' => $this->audiencePersonas(),
            'customer_loyalty' => $this->customerLoyalty(),
            'geographic_origin' => $this->geographicOrigin(),
            'genre_loyalty' => $this->genreLoyalty(),
            'checkin_analysis' => $this->checkinAnalysis(),
        ];
    }

    public function artistsTab(): array
    {
        return [
            'artist_performance' => $this->artistPerformance(),
            'genre_performance' => $this->genrePerformance(),
            'never_played_artists' => $this->neverPlayedArtists(),
        ];
    }

    public function schedulingTab(): array
    {
        return [
            'scheduling_heatmap' => $this->schedulingHeatmap(),
            'day_of_week' => $this->dayOfWeekAnalysis(),
            'seasonality' => $this->seasonalityAnalysis(),
            'idle_days' => $this->idleDaysAnalysis(),
            'sales_intelligence' => $this->salesIntelligence(),
        ];
    }

    public function opportunitiesTab(): array
    {
        return [
            'opportunities' => $this->opportunities(),
            'churn_alerts' => $this->churnRiskAlerts(),
        ];
    }

    public function promotionTab(): array
    {
        return [
            'promotion_planner' => $this->promotionPlanner(),
        ];
    }

    public function upcomingTab(): array
    {
        return [
            'upcoming' => $this->upcomingEvents(),
        ];
    }

    public function actionsTab(): array
    {
        return [
            'action_priority' => $this->actionPriority(),
        ];
    }
}
