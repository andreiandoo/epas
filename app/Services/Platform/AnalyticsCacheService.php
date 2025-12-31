<?php

namespace App\Services\Platform;

use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Models\Platform\PlatformConversion;
use App\Models\Platform\CohortMetric;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsCacheService
{
    // Cache TTLs in seconds
    const TTL_SHORT = 300;      // 5 minutes - real-time data
    const TTL_MEDIUM = 1800;    // 30 minutes - aggregated stats
    const TTL_LONG = 3600;      // 1 hour - daily summaries
    const TTL_DAILY = 86400;    // 24 hours - historical data

    protected string $prefix = 'analytics:';

    /**
     * Get dashboard overview stats with caching
     */
    public function getDashboardStats(?int $tenantId = null): array
    {
        $cacheKey = $this->key('dashboard:overview', $tenantId);

        return Cache::remember($cacheKey, self::TTL_SHORT, function () use ($tenantId) {
            $today = Carbon::today();
            $yesterday = Carbon::yesterday();

            $todayQuery = CoreCustomerEvent::query()
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->whereDate('created_at', $today);

            $yesterdayQuery = CoreCustomerEvent::query()
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->whereDate('created_at', $yesterday);

            return [
                'events_today' => $todayQuery->count(),
                'events_yesterday' => $yesterdayQuery->clone()->count(),
                'conversions_today' => $todayQuery->clone()->where('is_converted', true)->count(),
                'conversions_yesterday' => $yesterdayQuery->clone()->where('is_converted', true)->count(),
                'revenue_today' => $todayQuery->clone()->where('is_converted', true)->sum('conversion_value'),
                'revenue_yesterday' => $yesterdayQuery->clone()->where('is_converted', true)->sum('conversion_value'),
                'unique_visitors_today' => CoreSession::query()
                    ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                    ->whereDate('started_at', $today)
                    ->distinct('core_customer_id')
                    ->count('core_customer_id'),
                'cached_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Get conversion funnel data with caching
     */
    public function getConversionFunnel(
        ?int $tenantId = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $startDate ??= Carbon::now()->subDays(30);
        $endDate ??= Carbon::now();

        $cacheKey = $this->key("funnel:{$startDate->format('Y-m-d')}:{$endDate->format('Y-m-d')}", $tenantId);

        return Cache::remember($cacheKey, self::TTL_MEDIUM, function () use ($tenantId, $startDate, $endDate) {
            $baseQuery = CoreCustomerEvent::query()
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->whereBetween('created_at', [$startDate, $endDate]);

            $pageViews = $baseQuery->clone()->where('event_type', 'page_view')->count();
            $productViews = $baseQuery->clone()->where('event_type', 'view_item')->count();
            $addToCarts = $baseQuery->clone()->where('event_type', 'add_to_cart')->count();
            $checkouts = $baseQuery->clone()->where('event_type', 'begin_checkout')->count();
            $purchases = $baseQuery->clone()->where('event_type', 'purchase')->count();

            return [
                'steps' => [
                    ['name' => 'Page Views', 'count' => $pageViews, 'rate' => 100],
                    ['name' => 'Product Views', 'count' => $productViews, 'rate' => $pageViews > 0 ? round(($productViews / $pageViews) * 100, 1) : 0],
                    ['name' => 'Add to Cart', 'count' => $addToCarts, 'rate' => $productViews > 0 ? round(($addToCarts / $productViews) * 100, 1) : 0],
                    ['name' => 'Checkout', 'count' => $checkouts, 'rate' => $addToCarts > 0 ? round(($checkouts / $addToCarts) * 100, 1) : 0],
                    ['name' => 'Purchase', 'count' => $purchases, 'rate' => $checkouts > 0 ? round(($purchases / $checkouts) * 100, 1) : 0],
                ],
                'overall_conversion_rate' => $pageViews > 0 ? round(($purchases / $pageViews) * 100, 2) : 0,
                'cached_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Get customer segment distribution with caching
     */
    public function getCustomerSegments(?int $tenantId = null): array
    {
        $cacheKey = $this->key('segments:distribution', $tenantId);

        return Cache::remember($cacheKey, self::TTL_MEDIUM, function () use ($tenantId) {
            $query = CoreCustomer::query()
                ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
                ->notMerged()
                ->notAnonymized()
                ->whereNotNull('customer_segment');

            $segments = $query->groupBy('customer_segment')
                ->selectRaw('customer_segment, COUNT(*) as count, SUM(total_spent) as total_spent, AVG(rfm_score) as avg_rfm')
                ->get()
                ->keyBy('customer_segment')
                ->toArray();

            $total = array_sum(array_column($segments, 'count'));

            return [
                'segments' => collect($segments)->map(function ($segment) use ($total) {
                    return [
                        'name' => $segment['customer_segment'],
                        'count' => $segment['count'],
                        'percentage' => $total > 0 ? round(($segment['count'] / $total) * 100, 1) : 0,
                        'total_spent' => round($segment['total_spent'], 2),
                        'avg_rfm' => round($segment['avg_rfm'], 1),
                    ];
                })->values()->toArray(),
                'total_customers' => $total,
                'cached_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Get platform conversion stats with caching
     */
    public function getPlatformConversionStats(
        ?int $tenantId = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $startDate ??= Carbon::now()->subDays(30);
        $endDate ??= Carbon::now();

        $cacheKey = $this->key("conversions:platform:{$startDate->format('Y-m-d')}:{$endDate->format('Y-m-d')}", $tenantId);

        return Cache::remember($cacheKey, self::TTL_MEDIUM, function () use ($tenantId, $startDate, $endDate) {
            $results = PlatformConversion::query()
                ->join('platform_ad_accounts', 'platform_conversions.platform_ad_account_id', '=', 'platform_ad_accounts.id')
                ->when($tenantId, fn($q) => $q->where('platform_conversions.tenant_id', $tenantId))
                ->whereBetween('platform_conversions.created_at', [$startDate, $endDate])
                ->groupBy('platform_ad_accounts.platform')
                ->selectRaw('platform_ad_accounts.platform,
                    COUNT(*) as total,
                    SUM(CASE WHEN platform_conversions.status = "confirmed" THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN platform_conversions.status = "failed" THEN 1 ELSE 0 END) as failed,
                    SUM(platform_conversions.value) as total_value')
                ->get();

            return [
                'platforms' => $results->map(function ($row) {
                    return [
                        'platform' => $row->platform,
                        'total' => $row->total,
                        'confirmed' => $row->confirmed,
                        'failed' => $row->failed,
                        'success_rate' => $row->total > 0 ? round(($row->confirmed / $row->total) * 100, 1) : 0,
                        'total_value' => round($row->total_value, 2),
                    ];
                })->toArray(),
                'cached_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Get daily metrics for charts with caching
     */
    public function getDailyMetrics(
        int $days = 30,
        ?int $tenantId = null
    ): array {
        $cacheKey = $this->key("daily:metrics:{$days}", $tenantId);

        return Cache::remember($cacheKey, self::TTL_LONG, function () use ($days, $tenantId) {
            $startDate = Carbon::now()->subDays($days);

            $events = CoreCustomerEvent::query()
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->where('created_at', '>=', $startDate)
                ->groupBy('date')
                ->selectRaw('DATE(created_at) as date,
                    COUNT(*) as events,
                    SUM(CASE WHEN is_converted = 1 THEN 1 ELSE 0 END) as conversions,
                    SUM(CASE WHEN is_converted = 1 THEN conversion_value ELSE 0 END) as revenue')
                ->orderBy('date')
                ->get();

            $sessions = CoreSession::query()
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->where('started_at', '>=', $startDate)
                ->groupBy('date')
                ->selectRaw('DATE(started_at) as date,
                    COUNT(*) as sessions,
                    COUNT(DISTINCT core_customer_id) as unique_visitors')
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            return [
                'dates' => $events->pluck('date')->toArray(),
                'events' => $events->pluck('events')->toArray(),
                'conversions' => $events->pluck('conversions')->toArray(),
                'revenue' => $events->pluck('revenue')->toArray(),
                'sessions' => $events->map(fn($e) => $sessions->get($e->date)?->sessions ?? 0)->toArray(),
                'unique_visitors' => $events->map(fn($e) => $sessions->get($e->date)?->unique_visitors ?? 0)->toArray(),
                'cached_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Get cohort retention data with caching
     */
    public function getCohortRetention(
        string $cohortType = 'month',
        int $cohorts = 6,
        ?int $tenantId = null
    ): array {
        $cacheKey = $this->key("cohort:retention:{$cohortType}:{$cohorts}", $tenantId);

        return Cache::remember($cacheKey, self::TTL_DAILY, function () use ($cohortType, $cohorts, $tenantId) {
            $metrics = CohortMetric::query()
                ->where('cohort_type', $cohortType)
                ->orderByDesc('cohort_period')
                ->limit($cohorts * 12) // Get enough data for all periods
                ->get();

            if ($metrics->isEmpty()) {
                return [
                    'cohorts' => [],
                    'cached_at' => now()->toIso8601String(),
                ];
            }

            $grouped = $metrics->groupBy('cohort_period');

            return [
                'cohorts' => $grouped->map(function ($periods, $cohort) {
                    $baseCustomers = $periods->firstWhere('period_offset', 0)?->customers_count ?? 0;

                    return [
                        'period' => $cohort,
                        'customers' => $baseCustomers,
                        'retention' => $periods->sortBy('period_offset')->map(function ($p) use ($baseCustomers) {
                            return [
                                'offset' => $p->period_offset,
                                'active' => $p->active_customers,
                                'rate' => $baseCustomers > 0 ? round(($p->active_customers / $baseCustomers) * 100, 1) : 0,
                                'revenue' => round($p->total_revenue, 2),
                            ];
                        })->values()->toArray(),
                    ];
                })->values()->toArray(),
                'cached_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Get top customers with caching
     */
    public function getTopCustomers(
        int $limit = 10,
        string $orderBy = 'total_spent',
        ?int $tenantId = null
    ): array {
        $cacheKey = $this->key("customers:top:{$limit}:{$orderBy}", $tenantId);

        return Cache::remember($cacheKey, self::TTL_MEDIUM, function () use ($limit, $orderBy, $tenantId) {
            $customers = CoreCustomer::query()
                ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
                ->notMerged()
                ->notAnonymized()
                ->where($orderBy, '>', 0)
                ->orderByDesc($orderBy)
                ->limit($limit)
                ->get(['uuid', 'email_hash', 'total_spent', 'total_orders', 'rfm_score', 'customer_segment', 'last_seen_at', 'first_name', 'last_name']);

            return [
                'customers' => $customers->map(function ($c) {
                    return [
                        'uuid' => $c->uuid,
                        'display_name' => $c->getDisplayName(),
                        'total_spent' => round($c->total_spent, 2),
                        'total_orders' => $c->total_orders,
                        'rfm_score' => $c->rfm_score,
                        'segment' => $c->customer_segment,
                        'last_seen' => $c->last_seen_at?->diffForHumans(),
                    ];
                })->toArray(),
                'cached_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Get traffic source breakdown with caching
     */
    public function getTrafficSources(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?int $tenantId = null
    ): array {
        $startDate ??= Carbon::now()->subDays(30);
        $endDate ??= Carbon::now();

        $cacheKey = $this->key("traffic:sources:{$startDate->format('Y-m-d')}:{$endDate->format('Y-m-d')}", $tenantId);

        return Cache::remember($cacheKey, self::TTL_MEDIUM, function () use ($tenantId, $startDate, $endDate) {
            $sessions = CoreSession::query()
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->whereBetween('started_at', [$startDate, $endDate])
                ->groupBy('utm_source')
                ->selectRaw('COALESCE(utm_source, "direct") as source,
                    COUNT(*) as sessions,
                    SUM(CASE WHEN is_converted = 1 THEN 1 ELSE 0 END) as conversions,
                    SUM(CASE WHEN is_converted = 1 THEN total_value ELSE 0 END) as revenue')
                ->orderByDesc('sessions')
                ->limit(10)
                ->get();

            $total = $sessions->sum('sessions');

            return [
                'sources' => $sessions->map(function ($s) use ($total) {
                    return [
                        'source' => $s->source,
                        'sessions' => $s->sessions,
                        'percentage' => $total > 0 ? round(($s->sessions / $total) * 100, 1) : 0,
                        'conversions' => $s->conversions,
                        'conversion_rate' => $s->sessions > 0 ? round(($s->conversions / $s->sessions) * 100, 2) : 0,
                        'revenue' => round($s->revenue, 2),
                    ];
                })->toArray(),
                'cached_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Get geographic breakdown with caching
     */
    public function getGeographicBreakdown(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
        ?int $tenantId = null
    ): array {
        $startDate ??= Carbon::now()->subDays(30);
        $endDate ??= Carbon::now();

        $cacheKey = $this->key("geo:breakdown:{$startDate->format('Y-m-d')}:{$endDate->format('Y-m-d')}", $tenantId);

        return Cache::remember($cacheKey, self::TTL_LONG, function () use ($tenantId, $startDate, $endDate) {
            $sessions = CoreSession::query()
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->whereBetween('started_at', [$startDate, $endDate])
                ->whereNotNull('country_code')
                ->groupBy('country_code')
                ->selectRaw('country_code,
                    COUNT(*) as sessions,
                    COUNT(DISTINCT core_customer_id) as unique_visitors,
                    SUM(CASE WHEN is_converted = 1 THEN 1 ELSE 0 END) as conversions,
                    SUM(CASE WHEN is_converted = 1 THEN total_value ELSE 0 END) as revenue')
                ->orderByDesc('sessions')
                ->limit(20)
                ->get();

            return [
                'countries' => $sessions->map(function ($s) {
                    return [
                        'country' => $s->country_code,
                        'sessions' => $s->sessions,
                        'unique_visitors' => $s->unique_visitors,
                        'conversions' => $s->conversions,
                        'conversion_rate' => $s->sessions > 0 ? round(($s->conversions / $s->sessions) * 100, 2) : 0,
                        'revenue' => round($s->revenue, 2),
                    ];
                })->toArray(),
                'cached_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Calculate health scores for all active customers
     */
    public function calculateAllHealthScores(?int $tenantId = null): array
    {
        $updated = 0;
        $errors = 0;

        CoreCustomer::query()
            ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
            ->notMerged()
            ->notAnonymized()
            ->chunk(500, function ($customers) use (&$updated, &$errors) {
                foreach ($customers as $customer) {
                    try {
                        $score = $this->calculateHealthScore($customer);
                        $customer->update([
                            'health_score' => $score['score'],
                            'health_score_breakdown' => $score['breakdown'],
                            'health_score_calculated_at' => now(),
                        ]);
                        $updated++;
                    } catch (\Exception $e) {
                        $errors++;
                    }
                }
            });

        return [
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Calculate health score for a single customer
     */
    protected function calculateHealthScore(CoreCustomer $customer): array
    {
        $breakdown = [];
        $totalWeight = 0;
        $weightedScore = 0;

        // Recency score (40% weight) - based on days since last seen
        $daysSinceLastSeen = $customer->last_seen_at
            ? $customer->last_seen_at->diffInDays(now())
            : 365;
        $recencyScore = max(0, 100 - ($daysSinceLastSeen * 1.5));
        $breakdown['recency'] = ['score' => round($recencyScore), 'weight' => 40];
        $weightedScore += $recencyScore * 0.4;
        $totalWeight += 40;

        // Frequency score (25% weight) - based on total orders
        $frequencyScore = min(100, $customer->total_orders * 10);
        $breakdown['frequency'] = ['score' => round($frequencyScore), 'weight' => 25];
        $weightedScore += $frequencyScore * 0.25;
        $totalWeight += 25;

        // Monetary score (20% weight) - based on total spent
        $monetaryScore = min(100, ($customer->total_spent / 100) * 10);
        $breakdown['monetary'] = ['score' => round($monetaryScore), 'weight' => 20];
        $weightedScore += $monetaryScore * 0.2;
        $totalWeight += 20;

        // Engagement score (15% weight) - based on existing engagement_score
        $engagementScore = $customer->engagement_score ?? 50;
        $breakdown['engagement'] = ['score' => round($engagementScore), 'weight' => 15];
        $weightedScore += $engagementScore * 0.15;
        $totalWeight += 15;

        return [
            'score' => round($weightedScore),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Clear all analytics cache
     */
    public function clearAll(?int $tenantId = null): void
    {
        if ($tenantId) {
            Cache::forget($this->key('*', $tenantId));
        }

        // Clear common cache keys
        $keys = [
            'dashboard:overview',
            'segments:distribution',
            'daily:metrics:30',
            'daily:metrics:7',
            'cohort:retention:month:6',
            'cohort:retention:week:12',
            'customers:top:10:total_spent',
        ];

        foreach ($keys as $key) {
            Cache::forget($this->key($key, $tenantId));
        }
    }

    /**
     * Clear cache for specific report
     */
    public function clearReport(string $report, ?int $tenantId = null): void
    {
        Cache::forget($this->key($report, $tenantId));
    }

    /**
     * Generate cache key
     */
    protected function key(string $suffix, ?int $tenantId = null): string
    {
        $key = $this->prefix . $suffix;

        if ($tenantId) {
            $key = "tenant:{$tenantId}:{$key}";
        }

        return $key;
    }

    /**
     * Warm up cache for common queries
     */
    public function warmUp(?int $tenantId = null): void
    {
        $this->getDashboardStats($tenantId);
        $this->getCustomerSegments($tenantId);
        $this->getDailyMetrics(30, $tenantId);
        $this->getConversionFunnel($tenantId);
        $this->getTopCustomers(10, 'total_spent', $tenantId);
        $this->getTrafficSources(null, null, $tenantId);
    }
}
