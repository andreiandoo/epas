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

        $this->venue = $venues->first();
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
            'pricingIntelligence' => ['price_buckets' => [], 'sweet_spot' => null, 'underpriced' => [], 'overpriced' => []],
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
                ];
            } catch (\Exception $e) {
                \Log::error('VenueAnalytics: getViewData failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                return $emptyData;
            }
        });
    }
}
