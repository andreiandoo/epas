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
        if (!$tenant) return false;

        // Show for Venue tenant types OR any tenant that owns venues
        $venueTypes = ['venue', 'stadium-arena', 'philharmonic', 'opera', 'theater', 'museum'];
        $isVenueType = $tenant->tenant_type && in_array($tenant->tenant_type->value, $venueTypes);

        return $isVenueType || $tenant->ownsVenues();
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

        try {
            $eventIds = $this->venueEventIds();
            $orderIds = $this->venueOrderIds($eventIds);
            $kpis = $this->computeVenueKpis($eventIds, $orderIds);

            $data = ['kpis' => $kpis];

            $sections = [
                'eventPerformance' => fn() => $this->buildEventPerformanceTable($eventIds),
                'revenueBreakdown' => fn() => $this->buildRevenueBreakdown($eventIds, $orderIds),
                'pricingIntelligence' => fn() => $this->buildPricingIntelligence($eventIds),
                'audiencePersonas' => fn() => $this->buildVenueAudiencePersonas($orderIds),
                'customerLoyalty' => fn() => $this->buildVenueCustomerLoyalty($eventIds, $orderIds),
                'geographicOrigin' => fn() => $this->buildGeographicOrigin($orderIds),
                'artistPerformance' => fn() => $this->buildArtistPerformanceAtVenue($eventIds),
                'genrePerformance' => fn() => $this->buildGenrePerformance($eventIds),
                'neverPlayed' => fn() => $this->buildNeverPlayedArtists($eventIds),
                'schedulingHeatmap' => fn() => $this->buildSchedulingHeatmap($eventIds),
                'dayOfWeek' => fn() => $this->buildDayOfWeekAnalysis($eventIds),
                'seasonality' => fn() => $this->buildSeasonalityAnalysis($eventIds),
                'idleDays' => fn() => $this->buildIdleDaysAnalysis($eventIds),
                'salesIntelligence' => fn() => $this->buildSalesIntelligence($eventIds, $orderIds),
                'opportunities' => fn() => $this->buildOpportunities($eventIds, $orderIds),
                'promotionPlanner' => fn() => $this->buildPromotionPlanner($eventIds, $orderIds),
                'revenueForecast' => fn() => $this->buildRevenueForecast($eventIds),
                'upcomingEvents' => fn() => $this->buildUpcomingVenueEvents($eventIds),
                'competitorBenchmark' => fn() => $this->buildCompetitorBenchmark($eventIds),
                'churnAlerts' => fn() => $this->buildChurnRiskAlerts($eventIds, $orderIds),
                'revenuePerSeat' => fn() => $this->buildRevenuePerSeat($eventIds),
                'genreLoyalty' => fn() => $this->buildGenreLoyalty($eventIds, $orderIds),
                'checkinAnalysis' => fn() => $this->buildCheckinTimeAnalysis($eventIds),
                'venueHealthScore' => fn() => $this->buildVenueHealthScore($eventIds, $orderIds),
                'refundAnalysis' => fn() => $this->buildRefundAnalysis($eventIds),
                'monthlyMomentum' => fn() => $this->buildMonthlyMomentum($eventIds, $orderIds),
                'actionPriority' => fn() => $this->buildActionPriority($eventIds, $orderIds),
            ];

            foreach ($sections as $key => $builder) {
                try {
                    $data[$key] = $builder();
                } catch (\Exception $e) {
                    \Log::warning("TenantVenueAnalytics section '{$key}' failed", ['error' => $e->getMessage()]);
                    $data[$key] = $emptyData[$key] ?? [];
                }
            }

            return $data;
        } catch (\Exception $e) {
            \Log::error('TenantVenueAnalytics: getViewData failed', ['error' => $e->getMessage()]);
            return $emptyData;
        }
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
