<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EventAnalyticsDaily extends Model
{
    use HasFactory;

    protected $table = 'event_analytics_daily';

    protected $fillable = [
        'event_id',
        'tenant_id',
        'date',
        'page_views',
        'unique_visitors',
        'sessions',
        'avg_session_duration',
        'bounce_rate',
        'add_to_cart_count',
        'checkout_started_count',
        'purchases_count',
        'conversion_rate',
        'revenue',
        'tickets_sold',
        'avg_order_value',
        'traffic_sources',
        'top_locations',
        'ticket_breakdown',
        'device_breakdown',
        'hourly_visits',
        'hourly_sales',
    ];

    protected $casts = [
        'date' => 'date',
        'page_views' => 'integer',
        'unique_visitors' => 'integer',
        'sessions' => 'integer',
        'avg_session_duration' => 'decimal:2',
        'bounce_rate' => 'decimal:2',
        'add_to_cart_count' => 'integer',
        'checkout_started_count' => 'integer',
        'purchases_count' => 'integer',
        'conversion_rate' => 'decimal:2',
        'revenue' => 'decimal:2',
        'tickets_sold' => 'integer',
        'avg_order_value' => 'decimal:2',
        'traffic_sources' => 'array',
        'top_locations' => 'array',
        'ticket_breakdown' => 'array',
        'device_breakdown' => 'array',
        'hourly_visits' => 'array',
        'hourly_sales' => 'array',
    ];

    /* Relations */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /* Scopes */
    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeLastDays($query, int $days)
    {
        return $query->where('date', '>=', now()->subDays($days)->toDateString());
    }

    /* Aggregation helpers */

    /**
     * Get sum of a metric for a date range
     */
    public static function sumMetric(int $eventId, string $metric, $startDate, $endDate)
    {
        return static::forEvent($eventId)
            ->inDateRange($startDate, $endDate)
            ->sum($metric);
    }

    /**
     * Get average of a metric for a date range
     */
    public static function avgMetric(int $eventId, string $metric, $startDate, $endDate)
    {
        return static::forEvent($eventId)
            ->inDateRange($startDate, $endDate)
            ->avg($metric);
    }

    /**
     * Aggregate traffic sources across multiple days
     */
    public static function aggregateTrafficSources(int $eventId, $startDate, $endDate): array
    {
        $records = static::forEvent($eventId)
            ->inDateRange($startDate, $endDate)
            ->whereNotNull('traffic_sources')
            ->pluck('traffic_sources');

        $aggregated = [];

        foreach ($records as $sources) {
            if (!is_array($sources)) continue;

            foreach ($sources as $source) {
                $key = $source['source'] ?? 'unknown';
                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'source' => $key,
                        'visitors' => 0,
                        'revenue' => 0,
                        'conversions' => 0,
                    ];
                }
                $aggregated[$key]['visitors'] += $source['visitors'] ?? 0;
                $aggregated[$key]['revenue'] += $source['revenue'] ?? 0;
                $aggregated[$key]['conversions'] += $source['conversions'] ?? 0;
            }
        }

        // Sort by visitors descending
        uasort($aggregated, fn($a, $b) => $b['visitors'] <=> $a['visitors']);

        return array_values($aggregated);
    }

    /**
     * Aggregate top locations across multiple days
     */
    public static function aggregateTopLocations(int $eventId, $startDate, $endDate, int $limit = 10): array
    {
        $records = static::forEvent($eventId)
            ->inDateRange($startDate, $endDate)
            ->whereNotNull('top_locations')
            ->pluck('top_locations');

        $aggregated = [];

        foreach ($records as $locations) {
            if (!is_array($locations)) continue;

            foreach ($locations as $location) {
                $key = ($location['city'] ?? 'Unknown') . '|' . ($location['country'] ?? 'Unknown');
                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'city' => $location['city'] ?? 'Unknown',
                        'country' => $location['country'] ?? 'Unknown',
                        'country_code' => $location['country_code'] ?? null,
                        'visitors' => 0,
                        'tickets' => 0,
                        'revenue' => 0,
                    ];
                }
                $aggregated[$key]['visitors'] += $location['visitors'] ?? 0;
                $aggregated[$key]['tickets'] += $location['tickets'] ?? 0;
                $aggregated[$key]['revenue'] += $location['revenue'] ?? 0;
            }
        }

        // Sort by tickets descending
        uasort($aggregated, fn($a, $b) => $b['tickets'] <=> $a['tickets']);

        return array_slice(array_values($aggregated), 0, $limit);
    }

    /**
     * Aggregate ticket breakdown across multiple days
     */
    public static function aggregateTicketBreakdown(int $eventId, $startDate, $endDate): array
    {
        $records = static::forEvent($eventId)
            ->inDateRange($startDate, $endDate)
            ->whereNotNull('ticket_breakdown')
            ->pluck('ticket_breakdown');

        $aggregated = [];

        foreach ($records as $tickets) {
            if (!is_array($tickets)) continue;

            foreach ($tickets as $ticket) {
                $key = $ticket['ticket_type_id'] ?? $ticket['name'] ?? 'unknown';
                if (!isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'ticket_type_id' => $ticket['ticket_type_id'] ?? null,
                        'name' => $ticket['name'] ?? 'Unknown',
                        'sold' => 0,
                        'revenue' => 0,
                    ];
                }
                $aggregated[$key]['sold'] += $ticket['sold'] ?? 0;
                $aggregated[$key]['revenue'] += $ticket['revenue'] ?? 0;
            }
        }

        // Sort by revenue descending
        uasort($aggregated, fn($a, $b) => $b['revenue'] <=> $a['revenue']);

        return array_values($aggregated);
    }

    /**
     * Get chart data for a metric over time
     */
    public static function getChartData(int $eventId, string $metric, $startDate, $endDate): array
    {
        return static::forEvent($eventId)
            ->inDateRange($startDate, $endDate)
            ->orderBy('date')
            ->get(['date', $metric])
            ->map(fn($record) => [
                'date' => $record->date->format('Y-m-d'),
                'value' => $record->{$metric},
            ])
            ->toArray();
    }

    /**
     * Get multiple metrics chart data
     */
    public static function getMultiMetricChartData(int $eventId, array $metrics, $startDate, $endDate): array
    {
        $records = static::forEvent($eventId)
            ->inDateRange($startDate, $endDate)
            ->orderBy('date')
            ->get(array_merge(['date'], $metrics));

        $data = [];
        foreach ($records as $record) {
            $item = ['date' => $record->date->format('Y-m-d')];
            foreach ($metrics as $metric) {
                $item[$metric] = $record->{$metric};
            }
            $data[] = $item;
        }

        return $data;
    }

    /**
     * Calculate total funnel metrics
     */
    public static function getFunnelMetrics(int $eventId, $startDate, $endDate): array
    {
        $totals = static::forEvent($eventId)
            ->inDateRange($startDate, $endDate)
            ->selectRaw('
                SUM(page_views) as total_views,
                SUM(unique_visitors) as total_visitors,
                SUM(add_to_cart_count) as total_add_to_cart,
                SUM(checkout_started_count) as total_checkout_started,
                SUM(purchases_count) as total_purchases
            ')
            ->first();

        $views = $totals->total_views ?? 0;
        $visitors = $totals->total_visitors ?? 0;
        $addToCart = $totals->total_add_to_cart ?? 0;
        $checkoutStarted = $totals->total_checkout_started ?? 0;
        $purchases = $totals->total_purchases ?? 0;

        return [
            'views' => $views,
            'visitors' => $visitors,
            'add_to_cart' => $addToCart,
            'checkout_started' => $checkoutStarted,
            'purchases' => $purchases,
            'view_to_cart_rate' => $visitors > 0 ? round(($addToCart / $visitors) * 100, 2) : 0,
            'cart_to_checkout_rate' => $addToCart > 0 ? round(($checkoutStarted / $addToCart) * 100, 2) : 0,
            'checkout_to_purchase_rate' => $checkoutStarted > 0 ? round(($purchases / $checkoutStarted) * 100, 2) : 0,
            'overall_conversion_rate' => $visitors > 0 ? round(($purchases / $visitors) * 100, 2) : 0,
        ];
    }
}
