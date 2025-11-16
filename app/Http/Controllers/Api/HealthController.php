<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Health\HealthCheckService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Enhanced Health Check Controller
 *
 * Provides detailed health status for monitoring tools
 */
class HealthController extends Controller
{
    public function __construct(protected HealthCheckService $healthCheckService)
    {
    }

    /**
     * Get comprehensive health check
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $detailed = $request->query('detailed', true);

        try {
            $health = $this->healthCheckService->checkAll();

            $statusCode = match ($health['status']) {
                'healthy' => 200,
                'degraded' => 200,
                'unhealthy' => 503,
                default => 500,
            };

            if ($detailed) {
                // Return full health check with all details
                return response()->json($health, $statusCode);
            }

            // Return simplified health status
            return response()->json([
                'status' => $health['status'],
                'timestamp' => $health['timestamp'],
            ], $statusCode);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'error' => 'Health check failed',
                'timestamp' => now()->toIso8601String(),
            ], 503);
        }
    }

    /**
     * Simple ping endpoint for uptime monitoring
     *
     * @return JsonResponse
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
