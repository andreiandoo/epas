<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Tenant;

class IntelligenceMonitor extends Component
{
    public bool $isStreaming = true;
    public string $selectedTenant = 'all';
    public array $recentEvents = [];
    public array $recentAlerts = [];
    public array $recentActions = [];
    public array $journeyTransitions = [];
    public array $systemStats = [];
    public array $eventTypeStats = [];
    public array $tenants = [];
    public array $aiInsights = [];
    public array $historicalTrends = [];
    public array $geoData = [];
    public array $revenueForecast = [];

    public function mount(): void
    {
        $this->tenants = Tenant::pluck('name', 'id')->toArray();
        $this->loadData();
    }

    public function toggleStreaming(): void
    {
        $this->isStreaming = !$this->isStreaming;
    }

    public function refreshData(): void
    {
        $this->loadData();
    }

    protected function loadData(): void
    {
        $tenantFilter = $this->selectedTenant !== 'all' ? (int) $this->selectedTenant : null;

        // Load recent tracking events (last 50)
        $this->recentEvents = $this->getRecentEvents($tenantFilter);

        // Load recent alerts
        $this->recentAlerts = $this->getRecentAlerts($tenantFilter);

        // Load recent intelligence actions/outputs
        $this->recentActions = $this->getRecentActions($tenantFilter);

        // Load journey transitions
        $this->journeyTransitions = $this->getJourneyTransitions($tenantFilter);

        // Load system stats
        $this->systemStats = $this->getSystemStats($tenantFilter);

        // Load event type distribution
        $this->eventTypeStats = $this->getEventTypeStats($tenantFilter);

        // Load AI insights
        $this->aiInsights = $this->generateAIInsights($tenantFilter);

        // Load historical trends
        $this->historicalTrends = $this->getHistoricalTrends($tenantFilter);

        // Load geographic data
        $this->geoData = $this->getGeographicData($tenantFilter);

        // Load revenue forecast
        $this->revenueForecast = $this->getRevenueForecast($tenantFilter);
    }

    protected function generateAIInsights(?int $tenantId): array
    {
        $insights = [];
        $today = now()->startOfDay();
        $yesterday = now()->subDay()->startOfDay();
        $lastWeek = now()->subWeek()->startOfDay();

        // Revenue comparison
        $revenueToday = DB::table('core_customer_events')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', $today)
            ->where('event_type', 'purchase')
            ->sum('event_value') ?? 0;

        $revenueYesterday = DB::table('core_customer_events')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('occurred_at', [$yesterday, $today])
            ->where('event_type', 'purchase')
            ->sum('event_value') ?? 0;

        if ($revenueYesterday > 0) {
            $revenueChange = (($revenueToday - $revenueYesterday) / $revenueYesterday) * 100;
            $insights[] = [
                'type' => $revenueChange >= 0 ? 'positive' : 'negative',
                'icon' => $revenueChange >= 0 ? 'trending-up' : 'trending-down',
                'message' => 'Revenue is ' . abs(round($revenueChange)) . '% ' . ($revenueChange >= 0 ? 'above' : 'below') . ' yesterday',
                'detail' => '€' . number_format($revenueToday, 0) . ' vs €' . number_format($revenueYesterday, 0),
                'priority' => abs($revenueChange) > 20 ? 'high' : 'medium',
            ];
        }

        // Conversion rate analysis
        $visitsToday = DB::table('core_customer_events')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', $today)
            ->where('event_type', 'pageview')
            ->count();

        $purchasesToday = DB::table('core_customer_events')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', $today)
            ->where('event_type', 'purchase')
            ->count();

        if ($visitsToday > 0) {
            $conversionRate = ($purchasesToday / $visitsToday) * 100;
            $insights[] = [
                'type' => $conversionRate >= 2 ? 'positive' : ($conversionRate >= 1 ? 'neutral' : 'warning'),
                'icon' => 'chart-pie',
                'message' => 'Conversion rate at ' . number_format($conversionRate, 2) . '%',
                'detail' => $purchasesToday . ' purchases from ' . number_format($visitsToday) . ' visits',
                'priority' => 'medium',
            ];
        }

        // Cart abandonment detection
        $cartsToday = DB::table('core_customer_events')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', $today)
            ->where('event_type', 'add_to_cart')
            ->count();

        $checkoutsToday = DB::table('core_customer_events')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', $today)
            ->where('event_type', 'begin_checkout')
            ->count();

        if ($cartsToday > 0) {
            $abandonRate = (($cartsToday - $checkoutsToday) / $cartsToday) * 100;
            if ($abandonRate > 70) {
                $insights[] = [
                    'type' => 'warning',
                    'icon' => 'shopping-cart',
                    'message' => 'High cart abandonment detected: ' . round($abandonRate) . '%',
                    'detail' => ($cartsToday - $checkoutsToday) . ' carts abandoned today',
                    'priority' => 'high',
                ];
            }
        }

        // Traffic anomaly detection
        $avgHourlyEvents = DB::table('core_customer_events')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('occurred_at', [$lastWeek, $today])
            ->count() / (7 * 24);

        $eventsLastHour = DB::table('core_customer_events')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', now()->subHour())
            ->count();

        if ($avgHourlyEvents > 0) {
            $trafficChange = (($eventsLastHour - $avgHourlyEvents) / $avgHourlyEvents) * 100;
            if (abs($trafficChange) > 50) {
                $insights[] = [
                    'type' => $trafficChange > 0 ? 'positive' : 'warning',
                    'icon' => 'bolt',
                    'message' => 'Traffic ' . ($trafficChange > 0 ? 'surge' : 'drop') . ' detected: ' . abs(round($trafficChange)) . '%',
                    'detail' => number_format($eventsLastHour) . ' events in the last hour',
                    'priority' => 'high',
                ];
            }
        }

        // At-risk customer alert
        $atRiskCount = DB::table('core_customers')
            ->when($tenantId, fn($q) => $q->whereJsonContains('tenant_ids', $tenantId))
            ->where('journey_stage', 'at_risk')
            ->count();

        $lapsedCount = DB::table('core_customers')
            ->when($tenantId, fn($q) => $q->whereJsonContains('tenant_ids', $tenantId))
            ->where('journey_stage', 'lapsed')
            ->count();

        if ($atRiskCount > 0) {
            $insights[] = [
                'type' => 'warning',
                'icon' => 'user-minus',
                'message' => $atRiskCount . ' customers at risk of churning',
                'detail' => 'Plus ' . $lapsedCount . ' already lapsed',
                'priority' => $atRiskCount > 10 ? 'high' : 'medium',
            ];
        }

        // Peak hour detection
        $currentHour = now()->hour;
        $hourlyData = DB::table('core_customer_events')
            ->selectRaw('EXTRACT(HOUR FROM occurred_at) as hour, COUNT(*) as count')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', $today)
            ->groupByRaw('EXTRACT(HOUR FROM occurred_at)')
            ->orderByDesc('count')
            ->first();

        if ($hourlyData && $hourlyData->hour == $currentHour) {
            $insights[] = [
                'type' => 'positive',
                'icon' => 'fire',
                'message' => 'Peak activity hour in progress',
                'detail' => number_format($hourlyData->count) . ' events this hour',
                'priority' => 'low',
            ];
        }

        // Top performing content
        $topContent = DB::table('core_customer_events')
            ->select('content_name', DB::raw('COUNT(*) as views'))
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', $today)
            ->whereNotNull('content_name')
            ->where('event_type', 'view_item')
            ->groupBy('content_name')
            ->orderByDesc('views')
            ->first();

        if ($topContent) {
            $insights[] = [
                'type' => 'positive',
                'icon' => 'star',
                'message' => 'Top event: ' . $topContent->content_name,
                'detail' => number_format($topContent->views) . ' views today',
                'priority' => 'low',
            ];
        }

        // Sort by priority
        usort($insights, function ($a, $b) {
            $priorityOrder = ['high' => 0, 'medium' => 1, 'low' => 2];
            return ($priorityOrder[$a['priority']] ?? 2) <=> ($priorityOrder[$b['priority']] ?? 2);
        });

        return array_slice($insights, 0, 6);
    }

    protected function getHistoricalTrends(?int $tenantId): array
    {
        $trends = [];

        // Get hourly data for today and yesterday
        for ($i = 23; $i >= 0; $i--) {
            $hourStart = now()->subHours($i)->startOfHour();
            $hourEnd = now()->subHours($i)->endOfHour();

            $events = DB::table('core_customer_events')
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->whereBetween('occurred_at', [$hourStart, $hourEnd])
                ->count();

            $revenue = DB::table('core_customer_events')
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->whereBetween('occurred_at', [$hourStart, $hourEnd])
                ->where('event_type', 'purchase')
                ->sum('event_value') ?? 0;

            $conversions = DB::table('core_customer_events')
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->whereBetween('occurred_at', [$hourStart, $hourEnd])
                ->where('event_type', 'purchase')
                ->count();

            $trends[] = [
                'hour' => $hourStart->format('H:i'),
                'label' => $hourStart->format('ga'),
                'events' => $events,
                'revenue' => $revenue,
                'conversions' => $conversions,
                'is_today' => $hourStart->isToday(),
            ];
        }

        // Calculate comparisons
        $todayTotal = collect($trends)->where('is_today', true)->sum('events');
        $yesterdayTotal = collect($trends)->where('is_today', false)->sum('events');

        $todayRevenue = collect($trends)->where('is_today', true)->sum('revenue');
        $yesterdayRevenue = collect($trends)->where('is_today', false)->sum('revenue');

        return [
            'hourly' => $trends,
            'summary' => [
                'events_today' => $todayTotal,
                'events_yesterday' => $yesterdayTotal,
                'events_change' => $yesterdayTotal > 0 ? round((($todayTotal - $yesterdayTotal) / $yesterdayTotal) * 100, 1) : 0,
                'revenue_today' => $todayRevenue,
                'revenue_yesterday' => $yesterdayRevenue,
                'revenue_change' => $yesterdayRevenue > 0 ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1) : 0,
            ],
        ];
    }

    protected function getGeographicData(?int $tenantId): array
    {
        $geoStats = DB::table('core_customer_events')
            ->select('country_code', 'city', DB::raw('COUNT(*) as events'), DB::raw('SUM(CASE WHEN event_type = \'purchase\' THEN event_value ELSE 0 END) as revenue'))
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', now()->startOfDay())
            ->whereNotNull('country_code')
            ->groupBy('country_code', 'city')
            ->orderByDesc('events')
            ->limit(50)
            ->get();

        // Aggregate by country
        $countries = [];
        foreach ($geoStats as $stat) {
            $code = $stat->country_code;
            if (!isset($countries[$code])) {
                $countries[$code] = [
                    'code' => $code,
                    'name' => $this->getCountryName($code),
                    'events' => 0,
                    'revenue' => 0,
                    'cities' => [],
                ];
            }
            $countries[$code]['events'] += $stat->events;
            $countries[$code]['revenue'] += $stat->revenue;
            if ($stat->city) {
                $countries[$code]['cities'][] = [
                    'name' => $stat->city,
                    'events' => $stat->events,
                    'revenue' => $stat->revenue,
                ];
            }
        }

        // Sort by events
        usort($countries, fn($a, $b) => $b['events'] <=> $a['events']);

        // Calculate total for percentages
        $totalEvents = array_sum(array_column($countries, 'events'));

        // Add percentages
        foreach ($countries as &$country) {
            $country['percentage'] = $totalEvents > 0 ? round(($country['events'] / $totalEvents) * 100, 1) : 0;
            // Sort cities by events
            usort($country['cities'], fn($a, $b) => $b['events'] <=> $a['events']);
            $country['cities'] = array_slice($country['cities'], 0, 5);
        }

        return [
            'countries' => array_slice($countries, 0, 10),
            'total_events' => $totalEvents,
            'total_countries' => count($countries),
        ];
    }

    protected function getRevenueForecast(?int $tenantId): array
    {
        // Get hourly revenue for the past 7 days
        $hourlyRevenue = [];
        for ($day = 6; $day >= 0; $day--) {
            $date = now()->subDays($day);
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            $dayRevenue = DB::table('core_customer_events')
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->whereBetween('occurred_at', [$dayStart, $dayEnd])
                ->where('event_type', 'purchase')
                ->sum('event_value') ?? 0;

            $hourlyRevenue[] = [
                'date' => $date->format('M d'),
                'day' => $date->format('D'),
                'revenue' => $dayRevenue,
                'is_today' => $day === 0,
            ];
        }

        // Calculate today's projected revenue based on current run rate
        $todayStart = now()->startOfDay();
        $hoursElapsed = now()->diffInHours($todayStart) ?: 1;
        $revenueToday = DB::table('core_customer_events')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', $todayStart)
            ->where('event_type', 'purchase')
            ->sum('event_value') ?? 0;

        $hourlyRate = $revenueToday / $hoursElapsed;
        $hoursRemaining = 24 - $hoursElapsed;
        $projectedTotal = $revenueToday + ($hourlyRate * $hoursRemaining);

        // Calculate 7-day average
        $avgDailyRevenue = collect($hourlyRevenue)->where('is_today', false)->avg('revenue') ?? 0;

        // Simple linear forecast for next 3 days
        $forecast = [];
        $trend = $avgDailyRevenue > 0 ? ($projectedTotal / $avgDailyRevenue - 1) : 0;

        for ($i = 1; $i <= 3; $i++) {
            $forecastDate = now()->addDays($i);
            $forecastRevenue = $avgDailyRevenue * (1 + ($trend * 0.5)); // Dampen the trend

            $forecast[] = [
                'date' => $forecastDate->format('M d'),
                'day' => $forecastDate->format('D'),
                'revenue' => round($forecastRevenue, 2),
                'is_forecast' => true,
                'confidence' => max(60, 90 - ($i * 10)), // Decreasing confidence
            ];
        }

        return [
            'historical' => $hourlyRevenue,
            'forecast' => $forecast,
            'today' => [
                'actual' => $revenueToday,
                'projected' => round($projectedTotal, 2),
                'hourly_rate' => round($hourlyRate, 2),
                'hours_remaining' => $hoursRemaining,
            ],
            'summary' => [
                'avg_daily' => round($avgDailyRevenue, 2),
                'trend_percent' => round($trend * 100, 1),
                'best_day' => collect($hourlyRevenue)->sortByDesc('revenue')->first(),
                'total_7_days' => collect($hourlyRevenue)->sum('revenue'),
            ],
        ];
    }

    public function getCountryFlag(string $code): string
    {
        // Convert country code to regional indicator symbols (flag emoji)
        $code = strtoupper($code);
        if (strlen($code) !== 2) {
            return '🌍';
        }

        $flag = '';
        foreach (str_split($code) as $char) {
            $flag .= mb_chr(ord($char) - ord('A') + 0x1F1E6);
        }
        return $flag;
    }

    protected function getCountryName(string $code): string
    {
        $countries = [
            'US' => 'United States', 'GB' => 'United Kingdom', 'DE' => 'Germany', 'FR' => 'France',
            'ES' => 'Spain', 'IT' => 'Italy', 'NL' => 'Netherlands', 'BE' => 'Belgium', 'AT' => 'Austria',
            'CH' => 'Switzerland', 'PL' => 'Poland', 'RO' => 'Romania', 'CZ' => 'Czech Republic',
            'PT' => 'Portugal', 'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland',
            'IE' => 'Ireland', 'GR' => 'Greece', 'HU' => 'Hungary', 'SK' => 'Slovakia', 'BG' => 'Bulgaria',
            'HR' => 'Croatia', 'SI' => 'Slovenia', 'LT' => 'Lithuania', 'LV' => 'Latvia', 'EE' => 'Estonia',
            'CA' => 'Canada', 'AU' => 'Australia', 'NZ' => 'New Zealand', 'JP' => 'Japan', 'KR' => 'South Korea',
            'CN' => 'China', 'IN' => 'India', 'BR' => 'Brazil', 'MX' => 'Mexico', 'AR' => 'Argentina',
        ];

        return $countries[$code] ?? $code;
    }

    protected function getRecentEvents(?int $tenantId): array
    {
        $query = DB::table('core_customer_events')
            ->select([
                'core_customer_events.id',
                'core_customer_events.event_type',
                'core_customer_events.event_category',
                'core_customer_events.page_url',
                'core_customer_events.content_name',
                'core_customer_events.event_value',
                'core_customer_events.device_type',
                'core_customer_events.city',
                'core_customer_events.country_code',
                'core_customer_events.occurred_at',
                'core_customer_events.tenant_id',
                'core_customer_events.customer_id',
                'tenants.name as tenant_name',
            ])
            ->leftJoin('tenants', 'core_customer_events.tenant_id', '=', 'tenants.id')
            ->orderByDesc('occurred_at')
            ->limit(50);

        if ($tenantId) {
            $query->where('core_customer_events.tenant_id', $tenantId);
        }

        return $query->get()->map(function ($event) {
            return [
                'id' => $event->id,
                'type' => $event->event_type,
                'category' => $event->event_category ?? 'general',
                'page' => $event->page_url ? basename(parse_url($event->page_url, PHP_URL_PATH) ?: '/') : null,
                'content' => $event->content_name,
                'value' => $event->event_value,
                'device' => $event->device_type ?? 'unknown',
                'location' => $event->city ? "{$event->city}, {$event->country_code}" : ($event->country_code ?? 'Unknown'),
                'tenant' => $event->tenant_name ?? 'Platform',
                'tenant_id' => $event->tenant_id,
                'customer_id' => $event->customer_id,
                'time' => $event->occurred_at,
                'time_ago' => $this->timeAgo($event->occurred_at),
                'icon' => $this->getEventIcon($event->event_type),
                'color' => $this->getEventColor($event->event_type),
            ];
        })->toArray();
    }

    protected function getRecentAlerts(?int $tenantId): array
    {
        if (!DB::getSchemaBuilder()->hasTable('tracking_alerts')) {
            return [];
        }

        $query = DB::table('tracking_alerts')
            ->select([
                'tracking_alerts.*',
                'tenants.name as tenant_name',
            ])
            ->leftJoin('tenants', 'tracking_alerts.tenant_id', '=', 'tenants.id')
            ->orderByDesc('created_at')
            ->limit(30);

        if ($tenantId) {
            $query->where('tracking_alerts.tenant_id', $tenantId);
        }

        return $query->get()->map(function ($alert) {
            $data = json_decode($alert->data ?? '{}', true);
            return [
                'id' => $alert->id,
                'type' => $alert->type,
                'category' => $alert->category,
                'priority' => $alert->priority,
                'status' => $alert->status,
                'tenant' => $alert->tenant_name ?? 'Platform',
                'person_id' => $alert->person_id,
                'data' => $data,
                'message' => $this->getAlertMessage($alert->type, $data),
                'time' => $alert->created_at,
                'time_ago' => $this->timeAgo($alert->created_at),
                'icon' => $this->getAlertIcon($alert->priority),
                'color' => $this->getPriorityColor($alert->priority),
            ];
        })->toArray();
    }

    protected function getRecentActions(?int $tenantId): array
    {
        $actions = [];

        // Win-back conversions
        if (DB::getSchemaBuilder()->hasTable('winback_conversions')) {
            $winbacks = DB::table('winback_conversions')
                ->select([
                    'winback_conversions.*',
                    'tenants.name as tenant_name',
                ])
                ->leftJoin('tenants', 'winback_conversions.tenant_id', '=', 'tenants.id')
                ->orderByDesc('converted_at')
                ->limit(10);

            if ($tenantId) {
                $winbacks->where('winback_conversions.tenant_id', $tenantId);
            }

            foreach ($winbacks->get() as $wb) {
                $actions[] = [
                    'type' => 'winback_conversion',
                    'action' => 'Win-Back Success',
                    'description' => "Customer #{$wb->person_id} converted! €" . number_format($wb->order_value, 2),
                    'tenant' => $wb->tenant_name ?? 'Platform',
                    'time' => $wb->converted_at,
                    'time_ago' => $this->timeAgo($wb->converted_at),
                    'icon' => 'trophy',
                    'color' => 'emerald',
                ];
            }
        }

        // Demand forecasts generated
        if (DB::getSchemaBuilder()->hasTable('demand_forecasts')) {
            $forecasts = DB::table('demand_forecasts')
                ->select([
                    'demand_forecasts.*',
                    'tenants.name as tenant_name',
                ])
                ->leftJoin('tenants', 'demand_forecasts.tenant_id', '=', 'tenants.id')
                ->orderByDesc('updated_at')
                ->limit(10);

            if ($tenantId) {
                $forecasts->where('demand_forecasts.tenant_id', $tenantId);
            }

            foreach ($forecasts->get() as $fc) {
                $actions[] = [
                    'type' => 'forecast_update',
                    'action' => 'Demand Forecast',
                    'description' => "Event #{$fc->event_id}: {$fc->sellout_risk} risk, " . round($fc->sellout_probability * 100) . "% sellout probability",
                    'tenant' => $fc->tenant_name ?? 'Platform',
                    'time' => $fc->updated_at,
                    'time_ago' => $this->timeAgo($fc->updated_at),
                    'icon' => 'chart-bar',
                    'color' => $fc->sellout_risk === 'high' ? 'amber' : 'cyan',
                ];
            }
        }

        // Lookalike audiences created
        if (DB::getSchemaBuilder()->hasTable('lookalike_audiences')) {
            $lookalikes = DB::table('lookalike_audiences')
                ->select([
                    'lookalike_audiences.*',
                    'tenants.name as tenant_name',
                ])
                ->leftJoin('tenants', 'lookalike_audiences.tenant_id', '=', 'tenants.id')
                ->orderByDesc('created_at')
                ->limit(5);

            if ($tenantId) {
                $lookalikes->where('lookalike_audiences.tenant_id', $tenantId);
            }

            foreach ($lookalikes->get() as $la) {
                $actions[] = [
                    'type' => 'lookalike_created',
                    'action' => 'Lookalike Audience',
                    'description' => "\"{$la->name}\" - {$la->lookalike_count} similar profiles found",
                    'tenant' => $la->tenant_name ?? 'Platform',
                    'time' => $la->created_at,
                    'time_ago' => $this->timeAgo($la->created_at),
                    'icon' => 'users',
                    'color' => 'violet',
                ];
            }
        }

        // Sort by time
        usort($actions, fn($a, $b) => strtotime($b['time']) - strtotime($a['time']));

        return array_slice($actions, 0, 20);
    }

    protected function getJourneyTransitions(?int $tenantId): array
    {
        if (!DB::getSchemaBuilder()->hasTable('customer_journey_transitions')) {
            return [];
        }

        $query = DB::table('customer_journey_transitions')
            ->select([
                'customer_journey_transitions.*',
                'tenants.name as tenant_name',
            ])
            ->leftJoin('tenants', 'customer_journey_transitions.tenant_id', '=', 'tenants.id')
            ->orderByDesc('created_at')
            ->limit(20);

        if ($tenantId) {
            $query->where('customer_journey_transitions.tenant_id', $tenantId);
        }

        return $query->get()->map(function ($transition) {
            $isPositive = $this->isPositiveTransition($transition->from_stage, $transition->to_stage);
            return [
                'id' => $transition->id,
                'person_id' => $transition->person_id,
                'from' => $transition->from_stage,
                'to' => $transition->to_stage,
                'trigger' => $transition->trigger,
                'tenant' => $transition->tenant_name ?? 'Platform',
                'time' => $transition->created_at,
                'time_ago' => $this->timeAgo($transition->created_at),
                'direction' => $isPositive ? 'up' : 'down',
                'color' => $isPositive ? 'emerald' : 'rose',
            ];
        })->toArray();
    }

    protected function getSystemStats(?int $tenantId): array
    {
        $today = now()->startOfDay();
        $thisHour = now()->startOfHour();

        // Events today
        $eventsToday = DB::table('core_customer_events')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', $today)
            ->count();

        // Events this hour
        $eventsThisHour = DB::table('core_customer_events')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', $thisHour)
            ->count();

        // Unique visitors today
        $visitorsToday = DB::table('core_customer_events')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', $today)
            ->distinct('visitor_id')
            ->count('visitor_id');

        // Conversions today
        $conversionsToday = DB::table('core_customer_events')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', $today)
            ->where('event_type', 'purchase')
            ->count();

        // Revenue today
        $revenueToday = DB::table('core_customer_events')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', $today)
            ->where('event_type', 'purchase')
            ->sum('event_value') ?? 0;

        // Active alerts
        $activeAlerts = 0;
        if (DB::getSchemaBuilder()->hasTable('tracking_alerts')) {
            $activeAlerts = DB::table('tracking_alerts')
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->where('status', 'pending')
                ->count();
        }

        // At-risk customers
        $atRiskCustomers = DB::table('core_customers')
            ->when($tenantId, fn($q) => $q->whereJsonContains('tenant_ids', $tenantId))
            ->where('journey_stage', 'at_risk')
            ->count();

        // Events per minute (last 5 minutes)
        $eventsPerMinute = DB::table('core_customer_events')
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', now()->subMinutes(5))
            ->count() / 5;

        return [
            'events_today' => $eventsToday,
            'events_this_hour' => $eventsThisHour,
            'events_per_minute' => round($eventsPerMinute, 1),
            'visitors_today' => $visitorsToday,
            'conversions_today' => $conversionsToday,
            'revenue_today' => $revenueToday,
            'active_alerts' => $activeAlerts,
            'at_risk_customers' => $atRiskCustomers,
        ];
    }

    protected function getEventTypeStats(?int $tenantId): array
    {
        $today = now()->startOfDay();

        return DB::table('core_customer_events')
            ->select('event_type', DB::raw('COUNT(*) as count'))
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('occurred_at', '>=', $today)
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn($row) => [
                'type' => $row->event_type,
                'count' => $row->count,
                'icon' => $this->getEventIcon($row->event_type),
                'color' => $this->getEventColor($row->event_type),
            ])
            ->toArray();
    }

    protected function timeAgo($timestamp): string
    {
        $time = strtotime($timestamp);
        $diff = time() - $time;

        if ($diff < 5) return 'just now';
        if ($diff < 60) return $diff . 's ago';
        if ($diff < 3600) return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        return floor($diff / 86400) . 'd ago';
    }

    protected function getEventIcon(string $type): string
    {
        return match ($type) {
            'pageview' => 'eye',
            'view_item' => 'ticket',
            'add_to_cart' => 'shopping-cart',
            'remove_from_cart' => 'x-circle',
            'begin_checkout' => 'credit-card',
            'purchase' => 'check-circle',
            'search' => 'magnifying-glass',
            'click' => 'cursor-arrow-rays',
            'scroll' => 'arrows-up-down',
            'form_submit' => 'document-text',
            'email_open' => 'envelope-open',
            'email_click' => 'link',
            'artist_follow' => 'heart',
            'event_save' => 'bookmark',
            'share' => 'share',
            default => 'signal',
        };
    }

    protected function getEventColor(string $type): string
    {
        return match ($type) {
            'purchase' => 'emerald',
            'add_to_cart', 'begin_checkout' => 'amber',
            'view_item' => 'cyan',
            'pageview' => 'slate',
            'search' => 'violet',
            'email_open', 'email_click' => 'blue',
            'artist_follow', 'event_save' => 'rose',
            'remove_from_cart' => 'red',
            default => 'gray',
        };
    }

    protected function getAlertIcon(string $priority): string
    {
        return match ($priority) {
            'critical' => 'exclamation-triangle',
            'high' => 'bell-alert',
            'medium' => 'bell',
            'low' => 'information-circle',
            default => 'bell',
        };
    }

    protected function getPriorityColor(string $priority): string
    {
        return match ($priority) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'amber',
            'low' => 'cyan',
            default => 'gray',
        };
    }

    protected function getAlertMessage(string $type, array $data): string
    {
        return match ($type) {
            'high_value_cart_abandon' => "High-value cart abandoned: €" . number_format($data['cart_value'] ?? 0, 2),
            'churn_risk_spike' => "Churn risk increased to " . ($data['risk_score'] ?? 'high'),
            'vip_activity' => "VIP customer active - " . ($data['action'] ?? 'browsing'),
            'purchase_anomaly' => "Unusual purchase: " . ($data['anomaly'] ?? 'detected'),
            'milestone_reached' => "Customer milestone: " . ($data['milestone'] ?? 'achieved'),
            'rapid_engagement_drop' => "Engagement dropped " . ($data['drop_percent'] ?? 0) . "%",
            'competitor_comparison' => "Customer viewed competitors",
            'price_threshold_passed' => "Price threshold triggered",
            'cart_value_increase' => "Cart value increased to €" . number_format($data['new_value'] ?? 0, 2),
            'repeat_visitor_no_purchase' => "Repeat visitor, no purchase yet",
            'email_fatigue_detected' => "Email fatigue detected",
            'reactivation_opportunity' => "Reactivation window open",
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    protected function isPositiveTransition(string $from, string $to): bool
    {
        $stages = ['anonymous', 'aware', 'interested', 'considering', 'converted', 'retained', 'loyal', 'advocate'];
        $fromIndex = array_search($from, $stages);
        $toIndex = array_search($to, $stages);

        if ($fromIndex === false || $toIndex === false) {
            return !in_array($to, ['at_risk', 'lapsed']);
        }

        return $toIndex > $fromIndex;
    }

    public function render()
    {
        return view('livewire.intelligence-monitor');
    }
}
