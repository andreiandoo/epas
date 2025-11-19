<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Performance Monitoring Service
 *
 * Tracks and analyzes application performance:
 * - Request/response times
 * - Database query performance
 * - API endpoint metrics
 * - Resource usage
 * - Slow query detection
 * - Performance alerts
 */
class PerformanceMonitoringService
{
    protected float $requestStartTime;
    protected array $queryLog = [];
    protected array $metrics = [];

    public function __construct()
    {
        $this->requestStartTime = microtime(true);
    }

    /**
     * Start monitoring a request
     *
     * @param string $endpoint
     * @return void
     */
    public function startRequest(string $endpoint): void
    {
        $this->requestStartTime = microtime(true);
        $this->metrics['endpoint'] = $endpoint;
        $this->metrics['method'] = request()->method();

        // Enable query log
        if (config('microservices.monitoring.track_queries', true)) {
            DB::enableQueryLog();
        }
    }

    /**
     * End monitoring and record metrics
     *
     * @param int $statusCode
     * @return void
     */
    public function endRequest(int $statusCode): void
    {
        $duration = round((microtime(true) - $this->requestStartTime) * 1000, 2);

        $this->metrics['duration_ms'] = $duration;
        $this->metrics['status_code'] = $statusCode;
        $this->metrics['memory_mb'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        // Get query metrics
        if (config('microservices.monitoring.track_queries', true)) {
            $queries = DB::getQueryLog();
            $this->metrics['query_count'] = count($queries);
            $this->metrics['query_time_ms'] = round(array_sum(array_column($queries, 'time')), 2);

            // Find slow queries
            $slowQueries = array_filter($queries, fn($q) => $q['time'] > 100);
            if (!empty($slowQueries)) {
                $this->metrics['slow_queries'] = count($slowQueries);
                $this->alertSlowQueries($slowQueries);
            }
        }

        // Record metrics
        $this->recordMetrics();

        // Check for performance issues
        $this->checkPerformanceThresholds();
    }

    /**
     * Record metrics to database
     *
     * @return void
     */
    protected function recordMetrics(): void
    {
        if (!config('microservices.metrics.enabled', true)) {
            return;
        }

        try {
            DB::table('performance_metrics')->insert([
                'tenant_id' => request()->attributes->get('tenant_id'),
                'endpoint' => $this->metrics['endpoint'] ?? null,
                'method' => $this->metrics['method'] ?? null,
                'duration_ms' => $this->metrics['duration_ms'] ?? 0,
                'status_code' => $this->metrics['status_code'] ?? 0,
                'memory_mb' => $this->metrics['memory_mb'] ?? 0,
                'query_count' => $this->metrics['query_count'] ?? 0,
                'query_time_ms' => $this->metrics['query_time_ms'] ?? 0,
                'slow_query_count' => $this->metrics['slow_queries'] ?? 0,
                'created_at' => now(),
            ]);

            // Update realtime stats in cache
            $this->updateRealtimeStats();

        } catch (\Exception $e) {
            Log::error('Failed to record performance metrics', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update realtime performance statistics
     *
     * @return void
     */
    protected function updateRealtimeStats(): void
    {
        $endpoint = $this->metrics['endpoint'] ?? 'unknown';
        $key = "perf:realtime:{$endpoint}";

        $stats = Cache::remember($key, 300, function () {
            return [
                'count' => 0,
                'total_duration' => 0,
                'max_duration' => 0,
                'min_duration' => PHP_FLOAT_MAX,
            ];
        });

        $stats['count']++;
        $stats['total_duration'] += $this->metrics['duration_ms'];
        $stats['max_duration'] = max($stats['max_duration'], $this->metrics['duration_ms']);
        $stats['min_duration'] = min($stats['min_duration'], $this->metrics['duration_ms']);
        $stats['avg_duration'] = round($stats['total_duration'] / $stats['count'], 2);

        Cache::put($key, $stats, 300);
    }

    /**
     * Check if performance thresholds are exceeded
     *
     * @return void
     */
    protected function checkPerformanceThresholds(): void
    {
        $duration = $this->metrics['duration_ms'] ?? 0;
        $queryCount = $this->metrics['query_count'] ?? 0;

        // Alert on slow requests (> 2 seconds)
        if ($duration > 2000) {
            Log::warning('Slow request detected', [
                'endpoint' => $this->metrics['endpoint'] ?? 'unknown',
                'duration_ms' => $duration,
                'query_count' => $queryCount,
            ]);

            $this->sendPerformanceAlert('slow_request', [
                'endpoint' => $this->metrics['endpoint'] ?? 'unknown',
                'duration_ms' => $duration,
            ]);
        }

        // Alert on excessive queries (N+1 problem)
        if ($queryCount > 50) {
            Log::warning('Excessive database queries detected', [
                'endpoint' => $this->metrics['endpoint'] ?? 'unknown',
                'query_count' => $queryCount,
            ]);

            $this->sendPerformanceAlert('excessive_queries', [
                'endpoint' => $this->metrics['endpoint'] ?? 'unknown',
                'query_count' => $queryCount,
            ]);
        }

        // Alert on high memory usage (> 128 MB)
        if (($this->metrics['memory_mb'] ?? 0) > 128) {
            Log::warning('High memory usage detected', [
                'endpoint' => $this->metrics['endpoint'] ?? 'unknown',
                'memory_mb' => $this->metrics['memory_mb'],
            ]);
        }
    }

    /**
     * Alert about slow queries
     *
     * @param array $slowQueries
     * @return void
     */
    protected function alertSlowQueries(array $slowQueries): void
    {
        foreach ($slowQueries as $query) {
            Log::warning('Slow query detected', [
                'sql' => $query['query'],
                'time_ms' => $query['time'],
                'bindings' => $query['bindings'],
            ]);
        }
    }

    /**
     * Send performance alert
     *
     * @param string $type
     * @param array $data
     * @return void
     */
    protected function sendPerformanceAlert(string $type, array $data): void
    {
        if (!config('microservices.alerts.enabled')) {
            return;
        }

        // Rate limit alerts (max 1 per endpoint per hour)
        $alertKey = "perf:alert:{$type}:" . ($data['endpoint'] ?? 'unknown');

        if (Cache::has($alertKey)) {
            return; // Already sent recently
        }

        Cache::put($alertKey, true, 3600);

        // Send notification (implement based on your notification system)
        // This could send to Slack, email, PagerDuty, etc.
    }

    /**
     * Get performance report for an endpoint
     *
     * @param string $endpoint
     * @param int $hours Last N hours
     * @return array
     */
    public function getEndpointReport(string $endpoint, int $hours = 24): array
    {
        $metrics = DB::table('performance_metrics')
            ->where('endpoint', $endpoint)
            ->where('created_at', '>=', now()->subHours($hours))
            ->get();

        if ($metrics->isEmpty()) {
            return [
                'endpoint' => $endpoint,
                'no_data' => true,
            ];
        }

        return [
            'endpoint' => $endpoint,
            'period_hours' => $hours,
            'total_requests' => $metrics->count(),
            'avg_duration_ms' => round($metrics->avg('duration_ms'), 2),
            'max_duration_ms' => round($metrics->max('duration_ms'), 2),
            'min_duration_ms' => round($metrics->min('duration_ms'), 2),
            'p95_duration_ms' => $this->calculatePercentile($metrics->pluck('duration_ms')->toArray(), 95),
            'p99_duration_ms' => $this->calculatePercentile($metrics->pluck('duration_ms')->toArray(), 99),
            'avg_query_count' => round($metrics->avg('query_count'), 2),
            'avg_memory_mb' => round($metrics->avg('memory_mb'), 2),
            'error_rate' => round(
                ($metrics->where('status_code', '>=', 500)->count() / $metrics->count()) * 100,
                2
            ),
            'status_distribution' => $this->getStatusDistribution($metrics),
        ];
    }

    /**
     * Calculate percentile from array of values
     *
     * @param array $values
     * @param int $percentile
     * @return float
     */
    protected function calculatePercentile(array $values, int $percentile): float
    {
        if (empty($values)) {
            return 0;
        }

        sort($values);
        $index = ceil(($percentile / 100) * count($values)) - 1;
        return round($values[$index] ?? 0, 2);
    }

    /**
     * Get status code distribution
     *
     * @param \Illuminate\Support\Collection $metrics
     * @return array
     */
    protected function getStatusDistribution($metrics): array
    {
        $distribution = [];
        $grouped = $metrics->groupBy('status_code');

        foreach ($grouped as $code => $items) {
            $distribution[$code] = [
                'count' => $items->count(),
                'percentage' => round(($items->count() / $metrics->count()) * 100, 2),
            ];
        }

        return $distribution;
    }

    /**
     * Get slowest endpoints
     *
     * @param int $hours
     * @param int $limit
     * @return array
     */
    public function getSlowestEndpoints(int $hours = 24, int $limit = 10): array
    {
        return DB::table('performance_metrics')
            ->select('endpoint', DB::raw('AVG(duration_ms) as avg_duration, COUNT(*) as request_count'))
            ->where('created_at', '>=', now()->subHours($hours))
            ->groupBy('endpoint')
            ->orderBy('avg_duration', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'endpoint' => $row->endpoint,
                    'avg_duration_ms' => round($row->avg_duration, 2),
                    'request_count' => $row->request_count,
                ];
            })
            ->toArray();
    }

    /**
     * Clean up old performance metrics
     *
     * @param int $retentionDays
     * @return int Number of deleted records
     */
    public function cleanup(int $retentionDays = 90): int
    {
        return DB::table('performance_metrics')
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->delete();
    }
}
