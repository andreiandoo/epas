<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\VenueResource;
use App\Models\Venue;
use App\Support\VenueAnalyticsMethods;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Cache;

class VenueAnalyticsPage extends Page
{
    use VenueAnalyticsMethods;

    protected static string $resource = VenueResource::class;
    protected string $view = 'filament.tenant.pages.venue-analytics';

    public Venue $venue;
    public array $venueIds = [];
    public array $allVenues = [];
    public ?int $selectedVenueId = null;

    public array $seriesMonths = [];
    public array $seriesEvents = [];
    public array $seriesTickets = [];
    public array $seriesRevenue = [];
    public array $seriesOccupancy = [];

    public function mount(int|string $record): void
    {
        $this->venue = Venue::findOrFail($record);
        $this->selectedVenueId = $this->venue->id;
        $this->venueIds = [$this->venue->id];

        // Build all venues list (just this one for admin context)
        $name = $this->venue->getTranslation('name', 'ro') ?: $this->venue->getTranslation('name', 'en') ?: 'Venue';
        $this->allVenues = [[
            'id' => $this->venue->id,
            'name' => is_string($name) ? $name : (is_array($name) ? ($name['ro'] ?? $name['en'] ?? reset($name) ?: 'Venue') : 'Venue'),
            'city' => $this->venue->city,
            'capacity' => $this->venue->capacity ?: $this->venue->capacity_total ?: 0,
        ]];

        try {
            $eventIds = $this->venueEventIds();
            [$months, $events, $tickets, $revenue, $occupancy] = $this->buildVenueYearlySeries($eventIds);
            $this->seriesMonths = $months;
            $this->seriesEvents = $events;
            $this->seriesTickets = $tickets;
            $this->seriesRevenue = $revenue;
            $this->seriesOccupancy = $occupancy;
        } catch (\Exception $e) {
            \Log::warning('VenueAnalyticsPage: buildVenueYearlySeries failed', ['error' => $e->getMessage()]);
        }
    }

    public function getHeading(): string
    {
        $name = $this->venue->getTranslation('name', 'ro') ?: $this->venue->getTranslation('name', 'en');
        if (is_array($name)) $name = $name['ro'] ?? $name['en'] ?? reset($name) ?: 'Venue';
        return 'Analytics: ' . ($name ?: 'Venue');
    }

    public function getTitle(): string
    {
        return 'Venue Analytics';
    }

    public function getViewData(): array
    {
        $cacheKey = "venue_analytics_admin_{$this->venue->id}";
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
                \Log::info('VenueAnalytics debug', ['venue_id' => $this->venue->id, 'event_count' => count($eventIds)]);
                $orderIds = $this->venueOrderIds($eventIds);
                \Log::info('VenueAnalytics debug orders', ['order_count' => count($orderIds)]);
                $kpis = $this->computeVenueKpis($eventIds, $orderIds);
                \Log::info('VenueAnalytics debug kpis', $kpis);

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
                \Log::error('VenueAnalyticsPage: getViewData failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                return $emptyData;
            }
        });
    }

    public function switchVenueApi(int $venueId): array
    {
        return ['error' => 'Not available in admin context'];
    }

    public function simulateEventApi(string $genre, string $dayOfWeek, float $ticketPrice): array
    {
        $eventIds = $this->venueEventIds();
        $orderIds = $this->venueOrderIds($eventIds);
        return $this->simulateEvent($eventIds, $orderIds, $genre, $dayOfWeek, $ticketPrice);
    }

    public function getEventSuggestionsApi(): array
    {
        $eventIds = $this->venueEventIds();
        $orderIds = $this->venueOrderIds($eventIds);
        return $this->buildEventSuggestions($eventIds, $orderIds);
    }

    public function getCreativeCalendarApi(int $eventId): array
    {
        $eventIds = $this->venueEventIds();
        $orderIds = $this->venueOrderIds($eventIds);
        return $this->buildCreativeCalendar($eventId, $eventIds, $orderIds);
    }

    public function compareEventsApi(int $eventA, int $eventB): array
    {
        return $this->buildEventComparison($eventA, $eventB);
    }
}
