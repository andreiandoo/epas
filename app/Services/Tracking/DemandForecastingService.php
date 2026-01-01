<?php

namespace App\Services\Tracking;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DemandForecastingService
{
    protected int $tenantId;

    // Forecasting parameters
    protected const VELOCITY_WINDOW_DAYS = 7;
    protected const SIMILAR_EVENT_LOOKBACK_DAYS = 365;

    // Risk levels for sellout
    protected const SELLOUT_RISK = [
        'very_high' => 0.9,
        'high' => 0.75,
        'medium' => 0.5,
        'low' => 0.25,
    ];

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    public static function forTenant(int $tenantId): self
    {
        return new self($tenantId);
    }

    /**
     * Forecast demand for an event
     */
    public function forecastEvent(int $eventId): array
    {
        $cacheKey = "forecast:event:{$this->tenantId}:{$eventId}";

        return Cache::remember($cacheKey, 1800, function () use ($eventId) {
            $event = $this->getEventData($eventId);

            if (!$event) {
                return ['error' => 'Event not found'];
            }

            // Get current sales data
            $salesData = $this->getSalesData($eventId);

            // Get similar events for comparison
            $similarEvents = $this->findSimilarEvents($event);

            // Calculate velocity
            $velocity = $this->calculateSalesVelocity($eventId);

            // Get interest signals
            $interestSignals = $this->getInterestSignals($eventId);

            // Build forecast
            $forecast = $this->buildForecast($event, $salesData, $similarEvents, $velocity, $interestSignals);

            return [
                'event_id' => $eventId,
                'event_name' => $event->name,
                'event_date' => $event->start_date,
                'days_until_event' => max(0, now()->diffInDays($event->start_date, false)),

                // Current status
                'current' => [
                    'tickets_sold' => $salesData['total_sold'],
                    'revenue' => $salesData['total_revenue'],
                    'capacity' => $event->capacity,
                    'sold_percentage' => $event->capacity > 0
                        ? round($salesData['total_sold'] / $event->capacity * 100, 1)
                        : 0,
                ],

                // Velocity metrics
                'velocity' => $velocity,

                // Interest signals
                'interest' => $interestSignals,

                // Forecast
                'forecast' => $forecast,

                // Recommendations
                'recommendations' => $this->generateRecommendations($forecast, $event),

                'generated_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Get demand forecast for all upcoming events
     */
    public function forecastAllUpcoming(int $daysAhead = 90): array
    {
        $events = DB::table('events')
            ->where('tenant_id', $this->tenantId)
            ->where('start_date', '>', now())
            ->where('start_date', '<=', now()->addDays($daysAhead))
            ->where('status', 'published')
            ->orderBy('start_date')
            ->get();

        $forecasts = [];
        foreach ($events as $event) {
            $forecasts[] = $this->forecastEvent($event->id);
        }

        // Categorize by sellout risk
        $byRisk = [
            'very_high' => [],
            'high' => [],
            'medium' => [],
            'low' => [],
        ];

        foreach ($forecasts as $forecast) {
            $risk = $forecast['forecast']['sellout_risk'] ?? 'low';
            $byRisk[$risk][] = $forecast;
        }

        return [
            'total_events' => count($forecasts),
            'by_risk' => $byRisk,
            'summary' => [
                'very_high_risk' => count($byRisk['very_high']),
                'high_risk' => count($byRisk['high']),
                'medium_risk' => count($byRisk['medium']),
                'low_risk' => count($byRisk['low']),
                'total_projected_revenue' => collect($forecasts)->sum('forecast.projected_revenue'),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get pricing optimization suggestions
     */
    public function getPricingRecommendations(int $eventId): array
    {
        $forecast = $this->forecastEvent($eventId);

        if (isset($forecast['error'])) {
            return $forecast;
        }

        $selloutProb = $forecast['forecast']['sellout_probability'] ?? 0;
        $daysUntil = $forecast['days_until_event'];
        $soldPct = $forecast['current']['sold_percentage'];
        $velocity = $forecast['velocity']['daily_average'] ?? 0;

        $recommendations = [];

        // Dynamic pricing suggestions
        if ($selloutProb > 0.9 && $daysUntil > 14) {
            $recommendations[] = [
                'type' => 'price_increase',
                'confidence' => 'high',
                'suggestion' => 'Consider increasing prices by 10-15%',
                'reason' => 'High sellout probability with time remaining',
                'expected_impact' => '+10-15% revenue per ticket',
            ];
        } elseif ($selloutProb < 0.3 && $daysUntil < 14) {
            $recommendations[] = [
                'type' => 'promotion',
                'confidence' => 'high',
                'suggestion' => 'Consider running a promotion or discount',
                'reason' => 'Low demand with event approaching',
                'expected_impact' => '+20-30% ticket sales',
            ];
        }

        // Tier suggestions
        if ($soldPct > 50 && $daysUntil > 7) {
            $recommendations[] = [
                'type' => 'tier_transition',
                'confidence' => 'medium',
                'suggestion' => 'Move to next pricing tier',
                'reason' => 'Strong demand justifies price increase',
            ];
        }

        // Urgency marketing
        if ($selloutProb > 0.7) {
            $recommendations[] = [
                'type' => 'urgency_marketing',
                'confidence' => 'high',
                'suggestion' => 'Activate urgency messaging ("selling fast", "limited availability")',
                'reason' => 'Creates FOMO effect to accelerate purchases',
            ];
        }

        return [
            'event_id' => $eventId,
            'current_analysis' => [
                'sellout_probability' => $selloutProb,
                'days_until_event' => $daysUntil,
                'sold_percentage' => $soldPct,
                'daily_velocity' => $velocity,
            ],
            'recommendations' => $recommendations,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get event data
     */
    protected function getEventData(int $eventId): ?object
    {
        return DB::table('events')
            ->where('tenant_id', $this->tenantId)
            ->where('id', $eventId)
            ->first();
    }

    /**
     * Get sales data for event
     */
    protected function getSalesData(int $eventId): array
    {
        $sales = DB::table('orders')
            ->where('tenant_id', $this->tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'completed')
            ->selectRaw('
                COUNT(*) as order_count,
                SUM(quantity) as total_sold,
                SUM(total) as total_revenue,
                MIN(created_at) as first_sale,
                MAX(created_at) as last_sale
            ')
            ->first();

        // Get daily breakdown
        $dailySales = DB::table('orders')
            ->where('tenant_id', $this->tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'completed')
            ->selectRaw('DATE(created_at) as date, SUM(quantity) as sold, SUM(total) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'order_count' => $sales->order_count ?? 0,
            'total_sold' => $sales->total_sold ?? 0,
            'total_revenue' => $sales->total_revenue ?? 0,
            'first_sale' => $sales->first_sale,
            'last_sale' => $sales->last_sale,
            'daily_breakdown' => $dailySales,
        ];
    }

    /**
     * Calculate sales velocity
     */
    protected function calculateSalesVelocity(int $eventId): array
    {
        $recentSales = DB::table('orders')
            ->where('tenant_id', $this->tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(self::VELOCITY_WINDOW_DAYS))
            ->selectRaw('
                SUM(quantity) as sold,
                COUNT(*) as orders,
                SUM(total) as revenue
            ')
            ->first();

        $previousPeriod = DB::table('orders')
            ->where('tenant_id', $this->tenantId)
            ->where('event_id', $eventId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [
                now()->subDays(self::VELOCITY_WINDOW_DAYS * 2),
                now()->subDays(self::VELOCITY_WINDOW_DAYS),
            ])
            ->selectRaw('SUM(quantity) as sold')
            ->first();

        $currentVelocity = ($recentSales->sold ?? 0) / self::VELOCITY_WINDOW_DAYS;
        $previousVelocity = ($previousPeriod->sold ?? 0) / self::VELOCITY_WINDOW_DAYS;

        $acceleration = $previousVelocity > 0
            ? (($currentVelocity - $previousVelocity) / $previousVelocity) * 100
            : 0;

        return [
            'window_days' => self::VELOCITY_WINDOW_DAYS,
            'tickets_sold' => $recentSales->sold ?? 0,
            'orders' => $recentSales->orders ?? 0,
            'revenue' => $recentSales->revenue ?? 0,
            'daily_average' => round($currentVelocity, 2),
            'previous_daily_average' => round($previousVelocity, 2),
            'acceleration_percent' => round($acceleration, 1),
            'trend' => $acceleration > 10 ? 'accelerating'
                : ($acceleration < -10 ? 'decelerating' : 'stable'),
        ];
    }

    /**
     * Get interest signals (views, carts, etc.)
     */
    protected function getInterestSignals(int $eventId): array
    {
        $last7Days = DB::table('core_customer_events')
            ->where('tenant_id', $this->tenantId)
            ->where('event_id', $eventId)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw("
                SUM(CASE WHEN event_type = 'view_item' THEN 1 ELSE 0 END) as views,
                SUM(CASE WHEN event_type = 'add_to_cart' THEN 1 ELSE 0 END) as carts,
                SUM(CASE WHEN event_type = 'begin_checkout' THEN 1 ELSE 0 END) as checkouts,
                COUNT(DISTINCT person_id) as unique_visitors
            ")
            ->first();

        $previous7Days = DB::table('core_customer_events')
            ->where('tenant_id', $this->tenantId)
            ->where('event_id', $eventId)
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->selectRaw("
                SUM(CASE WHEN event_type = 'view_item' THEN 1 ELSE 0 END) as views,
                COUNT(DISTINCT person_id) as unique_visitors
            ")
            ->first();

        $viewsTrend = ($previous7Days->views ?? 0) > 0
            ? (($last7Days->views - $previous7Days->views) / $previous7Days->views) * 100
            : 0;

        // Calculate conversion funnel
        $views = $last7Days->views ?? 1;
        $cartRate = $views > 0 ? ($last7Days->carts ?? 0) / $views * 100 : 0;
        $checkoutRate = ($last7Days->carts ?? 0) > 0
            ? ($last7Days->checkouts ?? 0) / $last7Days->carts * 100
            : 0;

        return [
            'views_7d' => $last7Days->views ?? 0,
            'views_trend_percent' => round($viewsTrend, 1),
            'unique_visitors_7d' => $last7Days->unique_visitors ?? 0,
            'carts_7d' => $last7Days->carts ?? 0,
            'checkouts_7d' => $last7Days->checkouts ?? 0,
            'view_to_cart_rate' => round($cartRate, 2),
            'cart_to_checkout_rate' => round($checkoutRate, 2),
            'interest_score' => $this->calculateInterestScore($last7Days, $previous7Days),
        ];
    }

    /**
     * Calculate interest score (0-100)
     */
    protected function calculateInterestScore($current, $previous): float
    {
        $score = 0;

        // Base score from views
        $views = $current->views ?? 0;
        $score += min(30, $views / 10);

        // Trend bonus
        $viewsTrend = ($previous->views ?? 0) > 0
            ? (($views - $previous->views) / $previous->views)
            : 0;
        if ($viewsTrend > 0) {
            $score += min(20, $viewsTrend * 20);
        }

        // Conversion signals
        $cartRate = $views > 0 ? ($current->carts ?? 0) / $views : 0;
        $score += min(25, $cartRate * 100);

        // Unique visitors
        $score += min(25, ($current->unique_visitors ?? 0) / 20);

        return min(100, round($score, 1));
    }

    /**
     * Find similar historical events
     */
    protected function findSimilarEvents(object $event): Collection
    {
        return DB::table('events')
            ->where('tenant_id', $this->tenantId)
            ->where('id', '!=', $event->id)
            ->where('start_date', '<', now())
            ->where('start_date', '>=', now()->subDays(self::SIMILAR_EVENT_LOOKBACK_DAYS))
            ->where(function ($q) use ($event) {
                // Same artist or genre
                if ($event->artist_id) {
                    $q->where('artist_id', $event->artist_id);
                }
                if ($event->genre_id) {
                    $q->orWhere('genre_id', $event->genre_id);
                }
                if ($event->venue_id) {
                    $q->orWhere('venue_id', $event->venue_id);
                }
            })
            ->limit(10)
            ->get()
            ->map(function ($e) {
                $sales = DB::table('orders')
                    ->where('tenant_id', $this->tenantId)
                    ->where('event_id', $e->id)
                    ->where('status', 'completed')
                    ->selectRaw('SUM(quantity) as sold, SUM(total) as revenue')
                    ->first();

                return (object) [
                    'id' => $e->id,
                    'name' => $e->name,
                    'date' => $e->start_date,
                    'capacity' => $e->capacity,
                    'sold' => $sales->sold ?? 0,
                    'revenue' => $sales->revenue ?? 0,
                    'fill_rate' => $e->capacity > 0 ? ($sales->sold ?? 0) / $e->capacity : 0,
                ];
            });
    }

    /**
     * Build forecast from all data
     */
    protected function buildForecast(
        object $event,
        array $salesData,
        Collection $similarEvents,
        array $velocity,
        array $interest
    ): array {
        $daysUntilEvent = max(0, now()->diffInDays($event->start_date, false));
        $ticketsSold = $salesData['total_sold'];
        $capacity = $event->capacity ?? 1;
        $soldPercentage = $ticketsSold / $capacity;

        // Calculate projected final sales
        $dailyVelocity = $velocity['daily_average'];

        // Simple projection: current + (velocity * days remaining)
        $projectedFromVelocity = $ticketsSold + ($dailyVelocity * $daysUntilEvent);

        // Adjust based on typical sales curve (40% early, 40% middle, 20% last week)
        if ($daysUntilEvent > 14) {
            $curveMultiplier = 1.0; // Still in early/middle period
        } elseif ($daysUntilEvent > 7) {
            $curveMultiplier = 1.3; // Accelerating period
        } else {
            $curveMultiplier = 1.5; // Last week surge
        }

        $projectedSales = min($capacity, $projectedFromVelocity * $curveMultiplier);

        // Factor in similar events
        $avgSimilarFillRate = $similarEvents->avg('fill_rate') ?? 0.7;
        $similarProjection = $capacity * $avgSimilarFillRate;

        // Weighted projection
        $finalProjection = ($projectedSales * 0.6) + ($similarProjection * 0.4);

        // Calculate sellout probability
        $selloutProbability = $this->calculateSelloutProbability(
            $soldPercentage,
            $daysUntilEvent,
            $velocity['trend'],
            $interest['interest_score'],
            $avgSimilarFillRate
        );

        // Determine risk level
        $selloutRisk = match (true) {
            $selloutProbability >= self::SELLOUT_RISK['very_high'] => 'very_high',
            $selloutProbability >= self::SELLOUT_RISK['high'] => 'high',
            $selloutProbability >= self::SELLOUT_RISK['medium'] => 'medium',
            default => 'low',
        };

        // Projected revenue
        $avgTicketPrice = $salesData['total_sold'] > 0
            ? $salesData['total_revenue'] / $salesData['total_sold']
            : ($event->min_price ?? 50);
        $projectedRevenue = $finalProjection * $avgTicketPrice;

        return [
            'projected_sales' => round($finalProjection),
            'projected_fill_rate' => round($finalProjection / $capacity * 100, 1),
            'projected_revenue' => round($projectedRevenue, 2),
            'sellout_probability' => round($selloutProbability, 3),
            'sellout_risk' => $selloutRisk,
            'confidence' => $this->calculateConfidence($similarEvents->count(), $daysUntilEvent),
            'similar_events_used' => $similarEvents->count(),
            'avg_similar_fill_rate' => round($avgSimilarFillRate * 100, 1),
            'projection_method' => 'hybrid_velocity_similar',
        ];
    }

    /**
     * Calculate sellout probability
     */
    protected function calculateSelloutProbability(
        float $soldPct,
        int $daysUntil,
        string $trend,
        float $interestScore,
        float $similarFillRate
    ): float {
        // Base probability from current sales
        $baseProbability = $soldPct;

        // Adjust for time remaining
        if ($daysUntil > 30) {
            $timeMultiplier = 0.8; // Lots of time, lower probability
        } elseif ($daysUntil > 14) {
            $timeMultiplier = 1.0;
        } elseif ($daysUntil > 7) {
            $timeMultiplier = 1.1;
        } else {
            $timeMultiplier = 0.9; // Last week, hard to sell out if not already close
        }

        // Adjust for trend
        $trendMultiplier = match ($trend) {
            'accelerating' => 1.2,
            'stable' => 1.0,
            'decelerating' => 0.8,
            default => 1.0,
        };

        // Adjust for interest
        $interestMultiplier = 1 + (($interestScore - 50) / 200); // 0.75 to 1.25

        // Factor in similar events
        $similarMultiplier = $similarFillRate > 0.9 ? 1.2 : ($similarFillRate > 0.7 ? 1.0 : 0.8);

        $probability = $baseProbability * $timeMultiplier * $trendMultiplier * $interestMultiplier * $similarMultiplier;

        return min(1.0, max(0, $probability));
    }

    /**
     * Calculate forecast confidence
     */
    protected function calculateConfidence(int $similarEventsCount, int $daysUntil): string
    {
        $score = 0;

        // More similar events = higher confidence
        $score += min(40, $similarEventsCount * 10);

        // Closer to event = higher confidence
        if ($daysUntil < 7) {
            $score += 40;
        } elseif ($daysUntil < 14) {
            $score += 30;
        } elseif ($daysUntil < 30) {
            $score += 20;
        } else {
            $score += 10;
        }

        return match (true) {
            $score >= 60 => 'high',
            $score >= 40 => 'medium',
            default => 'low',
        };
    }

    /**
     * Generate actionable recommendations
     */
    protected function generateRecommendations(array $forecast, object $event): array
    {
        $recommendations = [];
        $selloutRisk = $forecast['sellout_risk'];
        $projectedFillRate = $forecast['projected_fill_rate'];

        if ($selloutRisk === 'very_high') {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'consider_capacity_increase',
                'message' => 'Event is likely to sell out. Consider adding capacity or similar event.',
            ];
            $recommendations[] = [
                'priority' => 'medium',
                'action' => 'dynamic_pricing',
                'message' => 'Strong demand supports price increases.',
            ];
        } elseif ($selloutRisk === 'low' && $projectedFillRate < 50) {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'marketing_push',
                'message' => 'Low projected fill rate. Increase marketing efforts.',
            ];
            $recommendations[] = [
                'priority' => 'medium',
                'action' => 'promotional_offer',
                'message' => 'Consider promotional pricing or bundles.',
            ];
        }

        // Always add targeting recommendation
        $recommendations[] = [
            'priority' => 'medium',
            'action' => 'targeted_campaign',
            'message' => 'Use lookalike audiences from similar past events.',
        ];

        return $recommendations;
    }

    /**
     * Invalidate forecast cache
     */
    public function invalidateCache(int $eventId): void
    {
        Cache::forget("forecast:event:{$this->tenantId}:{$eventId}");
    }
}
