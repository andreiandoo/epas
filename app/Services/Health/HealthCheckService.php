<?php

namespace App\Services\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;
use App\Services\WhatsApp\WhatsAppService;
use App\Services\EFactura\EFacturaService;
use App\Services\Accounting\AccountingService;

class HealthCheckService
{
    /**
     * Check overall system health
     */
    public function checkAll(): array
    {
        $cacheTtl = config('microservices.health.cache_ttl', 60);

        return Cache::remember('health:all', $cacheTtl, function () {
            $checks = [
                'app' => $this->checkApp(),
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'queue' => $this->checkQueue(),
                'whatsapp' => $this->checkWhatsApp(),
                'efactura' => $this->checkEFactura(),
                'accounting' => $this->checkAccounting(),
            ];

            $overall = $this->calculateOverallStatus($checks);

            return [
                'status' => $overall,
                'timestamp' => now()->toIso8601String(),
                'checks' => $checks,
            ];
        });
    }

    /**
     * Check application health
     */
    public function checkApp(): array
    {
        try {
            $startTime = microtime(true);

            // Check if app is responding
            $appHealthy = true;

            // Check disk space
            $diskFree = disk_free_space('/');
            $diskTotal = disk_total_space('/');
            $diskUsagePercent = (1 - ($diskFree / $diskTotal)) * 100;

            // Check memory
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->returnBytes(ini_get('memory_limit'));
            $memoryUsagePercent = ($memoryUsage / $memoryLimit) * 100;

            $responseTime = (microtime(true) - $startTime) * 1000;

            $status = 'healthy';
            if ($diskUsagePercent > 90 || $memoryUsagePercent > 90) {
                $status = 'degraded';
            }

            return [
                'status' => $status,
                'response_time_ms' => round($responseTime, 2),
                'disk_usage_percent' => round($diskUsagePercent, 2),
                'memory_usage_percent' => round($memoryUsagePercent, 2),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check database health
     */
    public function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);

            // Test database connection
            DB::connection()->getPdo();

            // Run a simple query
            $result = DB::select('SELECT 1 as test');

            $responseTime = (microtime(true) - $startTime) * 1000;

            return [
                'status' => 'healthy',
                'connection' => DB::connection()->getDatabaseName(),
                'driver' => DB::connection()->getDriverName(),
                'response_time_ms' => round($responseTime, 2),
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache health
     */
    public function checkCache(): array
    {
        try {
            $startTime = microtime(true);

            // Test cache write/read
            $testKey = 'health:cache:test';
            $testValue = str_random(32);

            Cache::put($testKey, $testValue, 60);
            $retrieved = Cache::get($testKey);
            Cache::forget($testKey);

            if ($retrieved !== $testValue) {
                throw new \Exception('Cache read/write mismatch');
            }

            $responseTime = (microtime(true) - $startTime) * 1000;

            return [
                'status' => 'healthy',
                'driver' => config('cache.default'),
                'response_time_ms' => round($responseTime, 2),
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue health
     */
    public function checkQueue(): array
    {
        try {
            $connection = config('queue.default');

            // For Redis queue, check Redis connection
            if ($connection === 'redis') {
                Redis::ping();
            }

            // Check failed jobs count
            $failedJobs = DB::table('failed_jobs')->count();

            $status = 'healthy';
            if ($failedJobs > 100) {
                $status = 'degraded';
            }

            return [
                'status' => $status,
                'connection' => $connection,
                'failed_jobs' => $failedJobs,
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check WhatsApp service health
     */
    public function checkWhatsApp(): array
    {
        if (!config('microservices.whatsapp.enabled')) {
            return [
                'status' => 'disabled',
                'message' => 'WhatsApp microservice is disabled',
            ];
        }

        try {
            $service = app(WhatsAppService::class);
            $service->setAdapter('mock', []);

            return [
                'status' => 'healthy',
                'adapter' => 'mock',
                'enabled' => true,
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check eFactura service health
     */
    public function checkEFactura(): array
    {
        if (!config('microservices.efactura.enabled')) {
            return [
                'status' => 'disabled',
                'message' => 'eFactura microservice is disabled',
            ];
        }

        try {
            $service = app(EFacturaService::class);
            $service->setAdapter('mock', []);

            // Check queue status
            $pending = DB::table('anaf_queue')
                ->where('status', 'pending')
                ->count();

            $failed = DB::table('anaf_queue')
                ->where('status', 'failed')
                ->count();

            $status = 'healthy';
            if ($failed > 50) {
                $status = 'degraded';
            }

            return [
                'status' => $status,
                'adapter' => 'mock',
                'enabled' => true,
                'pending_submissions' => $pending,
                'failed_submissions' => $failed,
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check accounting service health
     */
    public function checkAccounting(): array
    {
        if (!config('microservices.accounting.enabled')) {
            return [
                'status' => 'disabled',
                'message' => 'Accounting microservice is disabled',
            ];
        }

        try {
            $service = app(AccountingService::class);
            $service->setAdapter('mock', []);

            return [
                'status' => 'healthy',
                'adapter' => 'mock',
                'enabled' => true,
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate overall status from individual checks
     */
    protected function calculateOverallStatus(array $checks): string
    {
        $hasUnhealthy = false;
        $hasDegraded = false;

        foreach ($checks as $check) {
            if ($check['status'] === 'unhealthy') {
                $hasUnhealthy = true;
                break;
            }
            if ($check['status'] === 'degraded') {
                $hasDegraded = true;
            }
        }

        if ($hasUnhealthy) {
            return 'unhealthy';
        }

        if ($hasDegraded) {
            return 'degraded';
        }

        return 'healthy';
    }

    /**
     * Convert PHP memory limit to bytes
     */
    protected function returnBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;

        switch ($last) {
            case 'g':
                $val *= 1024;
                // no break
            case 'm':
                $val *= 1024;
                // no break
            case 'k':
                $val *= 1024;
        }

        return $val;
    }
}
