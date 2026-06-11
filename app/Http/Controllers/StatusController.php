<?php

namespace App\Http\Controllers;

use App\Models\Microservice;
use App\Models\ServiceStatusLog;
use App\Services\Health\HealthCheckService;
use Illuminate\Http\Request;

class StatusController extends Controller
{
    public function __construct(protected HealthCheckService $healthCheckService)
    {
    }

    /**
     * Public status page
     */
    public function index()
    {
        $health = $this->healthCheckService->checkAll();

        // Get all microservices for display
        $microservices = Microservice::where('is_active', true)->get();

        // Build services array for display
        $services = $this->buildServicesArray($health, $microservices);

        // Get chart data for the last 30 days
        $chartData = [];
        foreach ($services as $service) {
            $chartData[$service['name']] = ServiceStatusLog::getDailyStatusForChart($service['name']);
        }

        return view('public.status', [
            'health' => $health,
            'services' => $services,
            'microservices' => $microservices,
            'chartData' => $chartData,
            'lastUpdated' => now(),
        ]);
    }

    /**
     * Run health check and log results
     */
    public function check()
    {
        $health = $this->healthCheckService->checkAll();

        // Log core status
        ServiceStatusLog::create([
            'service_name' => 'core',
            'service_type' => 'core',
            'is_online' => $health['checks']['app']['status'] === 'healthy',
            'response_time_ms' => $health['checks']['app']['response_time_ms'] ?? 0,
            'version' => $health['checks']['app']['laravel_version'] ?? app()->version(),
            'checked_at' => now(),
        ]);

        // Log database status
        ServiceStatusLog::create([
            'service_name' => 'database',
            'service_type' => 'infrastructure',
            'is_online' => $health['checks']['database']['status'] === 'healthy',
            'response_time_ms' => $health['checks']['database']['response_time_ms'] ?? 0,
            'version' => $health['checks']['database']['driver'] ?? 'unknown',
            'checked_at' => now(),
        ]);

        // Log API status
        ServiceStatusLog::create([
            'service_name' => 'api',
            'service_type' => 'api',
            'is_online' => $health['status'] === 'healthy',
            'response_time_ms' => 0,
            'version' => 'v1',
            'checked_at' => now(),
        ]);

        // Log microservices
        $microservices = Microservice::where('is_active', true)->get();
        foreach ($microservices as $ms) {
            ServiceStatusLog::create([
                'service_name' => $ms->slug,
                'service_type' => 'microservice',
                'is_online' => $ms->is_active,
                'response_time_ms' => 0,
                'version' => '1.0.0',
                'checked_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'health' => $health,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Build services array for display
     */
    private function buildServicesArray(array $health, $microservices): array
    {
        $services = [];

        // Core Application
        $services[] = [
            'name' => 'core',
            'display_name' => 'Core Application',
            'type' => 'core',
            'status' => $health['checks']['app']['status'] ?? 'unknown',
            'is_online' => ($health['checks']['app']['status'] ?? '') === 'healthy',
            'version' => $health['checks']['app']['laravel_version'] ?? app()->version(),
            'response_time' => $health['checks']['app']['response_time_ms'] ?? 0,
            'uptime' => ServiceStatusLog::getUptimePercentage('core'),
            'details' => [
                'php_version' => $health['checks']['app']['php_version'] ?? PHP_VERSION,
                'disk_usage' => $health['checks']['app']['disk_usage_percent'] ?? 0,
                'memory_usage' => $health['checks']['app']['memory_usage_percent'] ?? 0,
            ],
        ];

        // Database
        $services[] = [
            'name' => 'database',
            'display_name' => 'Database',
            'type' => 'infrastructure',
            'status' => $health['checks']['database']['status'] ?? 'unknown',
            'is_online' => ($health['checks']['database']['status'] ?? '') === 'healthy',
            'version' => $health['checks']['database']['driver'] ?? 'MySQL',
            'response_time' => $health['checks']['database']['response_time_ms'] ?? 0,
            'uptime' => ServiceStatusLog::getUptimePercentage('database'),
            'details' => [],
        ];

        // API
        $services[] = [
            'name' => 'api',
            'display_name' => 'Public API',
            'type' => 'api',
            'status' => $health['status'] ?? 'unknown',
            'is_online' => ($health['status'] ?? '') === 'healthy',
            'version' => 'v1',
            'response_time' => 0,
            'uptime' => ServiceStatusLog::getUptimePercentage('api'),
            'details' => [],
        ];

        // Microservices
        foreach ($microservices as $ms) {
            $msCheck = $health['checks'][$ms->slug] ?? null;

            $services[] = [
                'name' => $ms->slug,
                'display_name' => $ms->getTranslation('name', app()->getLocale()),
                'type' => 'microservice',
                'status' => $msCheck['status'] ?? ($ms->is_active ? 'healthy' : 'disabled'),
                'is_online' => $ms->is_active && ($msCheck['status'] ?? 'healthy') === 'healthy',
                'version' => '1.0.0',
                'response_time' => $msCheck['response_time_ms'] ?? 0,
                'uptime' => ServiceStatusLog::getUptimePercentage($ms->slug),
                'details' => [],
            ];
        }

        return $services;
    }
}
