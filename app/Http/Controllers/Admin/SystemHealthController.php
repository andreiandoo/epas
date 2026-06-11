<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Health\HealthCheckService;
use App\Services\Cache\MicroservicesCacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Admin System Health Controller
 *
 * Provides system-wide health monitoring for administrators
 */
class SystemHealthController extends Controller
{
    public function __construct(
        protected HealthCheckService $healthService,
        protected MicroservicesCacheService $cacheService
    ) {}

    /**
     * Get comprehensive system health overview
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $health = $this->healthService->checkAll();

        // Add additional admin-only stats
        $stats = [
            'total_tenants' => DB::table('tenants')->count(),
            'active_subscriptions' => DB::table('tenant_microservices')
                ->where('status', 'active')
                ->count(),
            'pending_webhooks' => DB::table('tenant_webhook_deliveries')
                ->where('status', 'pending')
                ->count(),
            'failed_webhooks_24h' => DB::table('tenant_webhook_deliveries')
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'cache_stats' => $this->cacheService->getStats(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'health' => $health,
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Get health history
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function history(Request $request): JsonResponse
    {
        $hours = min($request->query('hours', 24), 168); // Max 7 days

        // This would require storing health check results
        // For now, return current status
        $history = [
            [
                'timestamp' => now()->toIso8601String(),
                'status' => $this->healthService->checkAll()['status'],
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $history,
            'meta' => [
                'hours' => $hours,
            ],
        ]);
    }

    /**
     * Get microservice-specific health details
     *
     * @param string $service
     * @return JsonResponse
     */
    public function service(string $service): JsonResponse
    {
        $method = 'check' . ucfirst($service);

        if (!method_exists($this->healthService, $method)) {
            return response()->json([
                'success' => false,
                'error' => 'Unknown service',
            ], 404);
        }

        $result = $this->healthService->$method();

        // Add usage stats for this service
        $usageStats = DB::table('tenant_microservices')
            ->where('microservice_id', $service)
            ->selectRaw('
                status,
                COUNT(*) as count
            ')
            ->groupBy('status')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'health' => $result,
                'usage' => $usageStats,
            ],
        ]);
    }
}
