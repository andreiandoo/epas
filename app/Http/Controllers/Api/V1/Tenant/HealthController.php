<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Health\HealthCheckService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Tenant Health Check Controller
 *
 * Allows tenants to check the health of their microservices
 */
class HealthController extends Controller
{
    public function __construct(
        protected HealthCheckService $healthService
    ) {}

    /**
     * Get overall system health
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $health = $this->healthService->checkAll();

        $statusCode = match($health['status']) {
            'healthy' => 200,
            'degraded' => 200,
            'unhealthy' => 503,
            default => 200,
        };

        return response()->json([
            'success' => true,
            'data' => $health,
        ], $statusCode);
    }

    /**
     * Check specific microservice health
     *
     * @param Request $request
     * @param string $service
     * @return JsonResponse
     */
    public function show(Request $request, string $service): JsonResponse
    {
        $method = 'check' . ucfirst($service);

        if (!method_exists($this->healthService, $method)) {
            return response()->json([
                'success' => false,
                'error' => 'Unknown service',
            ], 404);
        }

        $result = $this->healthService->$method();

        $statusCode = match($result['status']) {
            'healthy' => 200,
            'degraded' => 200,
            'unhealthy' => 503,
            default => 200,
        };

        return response()->json([
            'success' => true,
            'data' => $result,
        ], $statusCode);
    }
}
