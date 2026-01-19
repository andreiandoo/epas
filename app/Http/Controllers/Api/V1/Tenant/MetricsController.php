<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Metrics\MetricsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Tenant Metrics Controller
 *
 * Allows tenants to view their microservices usage metrics
 */
class MetricsController extends Controller
{
    public function __construct(
        protected MetricsService $metricsService
    ) {}

    /**
     * Get metrics for the authenticated tenant
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $microserviceId = $request->query('microservice_id');
        $metricType = $request->query('metric_type');
        $from = $request->query('from', now()->subDays(7)->toDateTimeString());
        $to = $request->query('to', now()->toDateTimeString());

        $metrics = $this->metricsService->getMetrics(
            $tenantId,
            $microserviceId,
            $metricType,
            $from,
            $to
        );

        return response()->json([
            'success' => true,
            'data' => $metrics,
            'meta' => [
                'tenant_id' => $tenantId,
                'microservice_id' => $microserviceId,
                'metric_type' => $metricType,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    /**
     * Get aggregated metrics summary
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function summary(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $period = $request->query('period', 'last_7_days');

        $summary = $this->metricsService->getSummary($tenantId, $period);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get usage breakdown by microservice
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function breakdown(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $from = $request->query('from', now()->subDays(30)->toDateTimeString());
        $to = $request->query('to', now()->toDateTimeString());

        $breakdown = $this->metricsService->getBreakdown($tenantId, $from, $to);

        return response()->json([
            'success' => true,
            'data' => $breakdown,
        ]);
    }
}
