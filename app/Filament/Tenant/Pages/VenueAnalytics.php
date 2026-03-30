<?php

namespace App\Filament\Tenant\Pages;

use App\Models\Venue;
use App\Support\VenueAnalyticsMethods;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VenueAnalytics extends Page
{
    use VenueAnalyticsMethods;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationLabel = 'Venue Analytics';
    protected static \UnitEnum|string|null $navigationGroup = 'Venue';
    protected static ?int $navigationSort = 51;
    protected string $view = 'filament.tenant.pages.venue-analytics';

    public Venue $venue;
    public array $venueIds = [];
    public array $allVenues = [];
    public ?int $selectedVenueId = null;

    // Series for charts
    public array $seriesMonths = [];
    public array $seriesEvents = [];
    public array $seriesTickets = [];
    public array $seriesRevenue = [];
    public array $seriesOccupancy = [];

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        return $tenant?->ownsVenues() ?? false;
    }

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;
        $venues = $tenant->venues()->orderBy('name')->get();

        if ($venues->isEmpty()) {
            abort(404, 'No venues linked to this tenant.');
        }

        $this->allVenues = $venues->map(function ($v) {
            $name = is_array($v->name) ? ($v->name['en'] ?? $v->name['ro'] ?? reset($v->name) ?: '—') : $v->name;
            if (is_string($name) && str_starts_with($name, '{')) { $d = json_decode($name, true); $name = $d['en'] ?? $d['ro'] ?? reset($d) ?: $name; }
            return ['id' => $v->id, 'name' => $name, 'city' => $v->city, 'capacity' => $v->capacity ?: $v->capacity_total ?: 0];
        })->toArray();
        $this->venue = $venues->first();
        $this->selectedVenueId = $this->venue->id;
        $this->venueIds = $venues->pluck('id')->toArray();

        try {
            $eventIds = $this->venueEventIds();
            [$months, $events, $tickets, $revenue, $occupancy] = $this->buildVenueYearlySeries($eventIds);
            $this->seriesMonths = $months;
            $this->seriesEvents = $events;
            $this->seriesTickets = $tickets;
            $this->seriesRevenue = $revenue;
            $this->seriesOccupancy = $occupancy;
        } catch (\Exception $e) {
            \Log::warning('VenueAnalytics: buildVenueYearlySeries failed', ['error' => $e->getMessage()]);
        }
    }

    public function getHeading(): string
    {
        $name = $this->venue->name;
        if (is_array($name)) $name = $name['en'] ?? $name['ro'] ?? reset($name) ?: 'Venue';
        return $name;
    }

    public function getTitle(): string
    {
        return 'Venue Analytics';
    }

    public function getViewData(): array
    {
        $cacheKey = "venue_analytics_tenant_" . implode('_', $this->venueIds);
        if (request()->has('refresh_analytics')) {
            Cache::forget($cacheKey);
        }

        $emptyData = [
            'kpis' => ['total_events' => 0, 'total_tickets' => 0, 'total_revenue' => 0, 'avg_occupancy' => 0, 'avg_revenue_per_event' => 0, 'avg_ticket_price' => 0],
            'eventPerformance' => [], 'revenueBreakdown' => ['revenue_by_genre' => [], 'revenue_by_channel' => [], 'revenue_by_day_type' => [], 'top_artists_by_revenue' => [], 'yoy' => []],
            'pricingIntelligence' => ['price_buckets' => [], 'sweet_spot' => null, 'underpriced' => [], 'overpriced' => []], 'competitorBenchmark' => [], 'churnAlerts' => [], 'revenuePerSeat' => [], 'genreLoyalty' => [], 'checkinAnalysis' => [],
            'venueHealthScore' => ['score' => 0, 'label' => 'No Data', 'color' => '#ef4444', 'components' => []], 'refundAnalysis' => [], 'monthlyMomentum' => [], 'actionPriority' => [],
            'audiencePersonas' => ['personas' => [], 'totals' => ['total_customers' => 0, 'with_demographics' => 0, 'age_distribution' => [], 'gender_overall' => []]],
            'customerLoyalty' => ['one_time' => 0, 'repeat' => 0, 'regulars' => 0, 'superfan' => 0, 'repeat_rate' => 0, 'total' => 0, 'superfan_details' => []],
            'geographicOrigin' => ['cities' => [], 'out_of_town_ratio' => 0],
            'artistPerformance' => [], 'genrePerformance' => [], 'neverPlayed' => [],
            'schedulingHeatmap' => [], 'dayOfWeek' => [], 'seasonality' => [],
            'idleDays' => ['total_idle_weekend_days' => 0, 'avg_revenue_per_event' => 0, 'estimated_lost_revenue' => 0, 'idle_by_month' => []],
            'salesIntelligence' => ['purchase_timing' => [], 'avg_lead_days' => 0, 'velocity_curves' => [], 'optimal_frequency' => []],
            'opportunities' => ['recommendations' => []], 'promotionPlanner' => [],
            'revenueForecast' => ['monthly_trend' => [], 'forecast' => [], 'scenarios' => []],
            'upcomingEvents' => [],
        ];

        return Cache::remember($cacheKey, 300, function () use ($emptyData) {
            try {
                $eventIds = $this->venueEventIds();
                $orderIds = $this->venueOrderIds($eventIds);
                $kpis = $this->computeVenueKpis($eventIds, $orderIds);

                return [
                    'kpis' => $kpis,
                    'eventPerformance' => $this->buildEventPerformanceTable($eventIds),
                    'revenueBreakdown' => $this->buildRevenueBreakdown($eventIds, $orderIds),
                    'pricingIntelligence' => $this->buildPricingIntelligence($eventIds),
                    'audiencePersonas' => $this->buildVenueAudiencePersonas($orderIds),
                    'customerLoyalty' => $this->buildVenueCustomerLoyalty($eventIds, $orderIds),
                    'geographicOrigin' => $this->buildGeographicOrigin($orderIds),
                    'artistPerformance' => $this->buildArtistPerformanceAtVenue($eventIds),
                    'genrePerformance' => $this->buildGenrePerformance($eventIds),
                    'neverPlayed' => $this->buildNeverPlayedArtists($eventIds),
                    'schedulingHeatmap' => $this->buildSchedulingHeatmap($eventIds),
                    'dayOfWeek' => $this->buildDayOfWeekAnalysis($eventIds),
                    'seasonality' => $this->buildSeasonalityAnalysis($eventIds),
                    'idleDays' => $this->buildIdleDaysAnalysis($eventIds),
                    'salesIntelligence' => $this->buildSalesIntelligence($eventIds, $orderIds),
                    'opportunities' => $this->buildOpportunities($eventIds, $orderIds),
                    'promotionPlanner' => $this->buildPromotionPlanner($eventIds, $orderIds),
                    'revenueForecast' => $this->buildRevenueForecast($eventIds),
                    'upcomingEvents' => $this->buildUpcomingVenueEvents($eventIds),
                    'competitorBenchmark' => $this->buildCompetitorBenchmark($eventIds),
                    'churnAlerts' => $this->buildChurnRiskAlerts($eventIds, $orderIds),
                    'revenuePerSeat' => $this->buildRevenuePerSeat($eventIds),
                    'genreLoyalty' => $this->buildGenreLoyalty($eventIds, $orderIds),
                    'checkinAnalysis' => $this->buildCheckinTimeAnalysis($eventIds),
                    'venueHealthScore' => $this->buildVenueHealthScore($eventIds, $orderIds),
                    'refundAnalysis' => $this->buildRefundAnalysis($eventIds),
                    'monthlyMomentum' => $this->buildMonthlyMomentum($eventIds, $orderIds),
                    'actionPriority' => $this->buildActionPriority($eventIds, $orderIds),
                ];
            } catch (\Exception $e) {
                \Log::error('VenueAnalytics: getViewData failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                return $emptyData;
            }
        });
    }

    /**
     * Switch to a specific venue (for multi-venue tenants).
     */
    public function switchVenueApi(int $venueId): array
    {
        $tenant = auth()->user()->tenant;
        $newVenue = $tenant->venues()->find($venueId);
        if (!$newVenue) return ['error' => 'Venue not found'];

        $this->venue = $newVenue;
        $this->selectedVenueId = $newVenue->id;
        $this->venueIds = [$newVenue->id];

        // Clear cache
        Cache::forget("venue_analytics_tenant_{$newVenue->id}");

        // Rebuild series
        $eventIds = $this->venueEventIds();
        [$months, $events, $tickets, $revenue, $occupancy] = $this->buildVenueYearlySeries($eventIds);
        $this->seriesMonths = $months;
        $this->seriesEvents = $events;
        $this->seriesTickets = $tickets;
        $this->seriesRevenue = $revenue;
        $this->seriesOccupancy = $occupancy;

        return ['success' => true, 'venue_id' => $newVenue->id];
    }

    /**
     * Event Simulator: predict performance for a hypothetical event.
     */
    public function simulateEventApi(string $genre, string $dayOfWeek, float $ticketPrice): array
    {
        $eventIds = $this->venueEventIds();
        $orderIds = $this->venueOrderIds($eventIds);
        return $this->simulateEvent($eventIds, $orderIds, $genre, $dayOfWeek, $ticketPrice);
    }

    /**
     * Generate event suggestions based on venue data.
     */
    public function getEventSuggestionsApi(): array
    {
        $eventIds = $this->venueEventIds();
        $orderIds = $this->venueOrderIds($eventIds);
        return $this->buildEventSuggestions($eventIds, $orderIds);
    }

    /**
     * Generate creative calendar for an upcoming event.
     */
    public function getCreativeCalendarApi(int $eventId): array
    {
        $eventIds = $this->venueEventIds();
        $orderIds = $this->venueOrderIds($eventIds);
        return $this->buildCreativeCalendar($eventId, $eventIds, $orderIds);
    }

    /**
     * Compare two events side-by-side.
     */
    public function compareEventsApi(int $eventA, int $eventB): array
    {
        return $this->buildEventComparison($eventA, $eventB);
    }
}
