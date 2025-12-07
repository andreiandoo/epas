<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CohortMetric;
use App\Services\Platform\AnalyticsCacheService;
use App\Services\Platform\AttributionModelService;
use App\Services\Platform\ChurnPredictionService;
use App\Services\Platform\DuplicateDetectionService;
use App\Services\Platform\AnalyticsExportService;
use App\Services\Platform\LtvPredictionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsCacheService $cacheService,
        protected AttributionModelService $attributionService,
        protected ChurnPredictionService $churnService,
        protected DuplicateDetectionService $duplicateService,
        protected AnalyticsExportService $exportService,
        protected LtvPredictionService $ltvService
    ) {}

    /**
     * Get dashboard overview
     *
     * @OA\Get(
     *     path="/api/v1/analytics/dashboard",
     *     summary="Get analytics dashboard overview",
     *     tags={"Analytics"},
     *     @OA\Response(response=200, description="Dashboard data")
     * )
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');

        $data = [
            'overview' => $this->cacheService->getDashboardStats($tenantId),
            'segments' => $this->cacheService->getCustomerSegments($tenantId),
            'daily_metrics' => $this->cacheService->getDailyMetrics(30, $tenantId),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get conversion funnel
     */
    public function funnel(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : null;
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : null;

        $data = $this->cacheService->getConversionFunnel($tenantId, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get customer segments
     */
    public function segments(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $data = $this->cacheService->getCustomerSegments($tenantId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get cohort retention data
     */
    public function cohorts(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $cohortType = $request->input('type', 'month');
        $cohorts = $request->input('cohorts', 6);

        $data = $this->cacheService->getCohortRetention($cohortType, $cohorts, $tenantId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get traffic sources
     */
    public function trafficSources(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : null;
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : null;

        $data = $this->cacheService->getTrafficSources($startDate, $endDate, $tenantId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get geographic breakdown
     */
    public function geography(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $startDate = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))
            : null;
        $endDate = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))
            : null;

        $data = $this->cacheService->getGeographicBreakdown($startDate, $endDate, $tenantId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get top customers
     */
    public function topCustomers(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $limit = $request->input('limit', 10);
        $orderBy = $request->input('order_by', 'total_spent');

        $data = $this->cacheService->getTopCustomers($limit, $orderBy, $tenantId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Compare attribution models
     */
    public function attributionComparison(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->input('start_date'));
        $endDate = Carbon::parse($request->input('end_date'));
        $tenantId = $request->input('tenant_id');

        if ($request->input('attribution_window')) {
            $this->attributionService->setAttributionWindow($request->input('attribution_window'));
        }

        $data = $this->attributionService->compareModels($startDate, $endDate, $tenantId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get channel attribution report
     */
    public function channelAttribution(Request $request): JsonResponse
    {
        $request->validate([
            'model' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $model = $request->input('model');
        $startDate = Carbon::parse($request->input('start_date'));
        $endDate = Carbon::parse($request->input('end_date'));
        $tenantId = $request->input('tenant_id');

        $data = $this->attributionService->getChannelAttributionReport(
            $model,
            $startDate,
            $endDate,
            $tenantId
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get customer journey
     */
    public function customerJourney(Request $request, int $customerId): JsonResponse
    {
        $data = $this->attributionService->analyzeCustomerJourney($customerId);

        if (isset($data['error'])) {
            return response()->json([
                'success' => false,
                'error' => $data['error'],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get available attribution models
     */
    public function attributionModels(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => AttributionModelService::getAvailableModels(),
        ]);
    }

    /**
     * Get churn prediction dashboard
     */
    public function churnDashboard(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $data = $this->churnService->getChurnDashboard($tenantId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get at-risk customers
     */
    public function atRiskCustomers(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $minRisk = $request->input('min_risk', 'high');
        $limit = $request->input('limit', 50);

        $data = $this->churnService->getAtRiskCustomers($minRisk, $limit, $tenantId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Predict churn for a specific customer
     */
    public function predictCustomerChurn(Request $request, int $customerId): JsonResponse
    {
        $customer = CoreCustomer::find($customerId);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'error' => 'Customer not found',
            ], 404);
        }

        $data = $this->churnService->predictChurn($customer);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get churn stats by segment
     */
    public function churnBySegment(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $data = $this->churnService->getChurnStatsBySegment($tenantId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get cohort churn analysis
     */
    public function cohortChurnAnalysis(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $cohortType = $request->input('type', 'month');
        $cohortsBack = $request->input('cohorts', 12);

        $data = $this->churnService->getCohortChurnAnalysis($cohortType, $cohortsBack, $tenantId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get duplicate detection statistics
     */
    public function duplicateStats(Request $request): JsonResponse
    {
        $data = $this->duplicateService->getStatistics();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Find duplicate candidates
     */
    public function duplicates(Request $request): JsonResponse
    {
        $threshold = $request->input('threshold', 0.7);
        $limit = $request->input('limit', 50);

        $data = $this->duplicateService->findAllDuplicates($threshold, $limit);

        return response()->json([
            'success' => true,
            'data' => $data->map(function ($group) {
                return [
                    'type' => $group['type'],
                    'confidence' => $group['confidence'],
                    'score' => round($group['score'], 3),
                    'customer_count' => $group['customers']->count(),
                    'customers' => $group['customers']->map(fn($c) => [
                        'id' => $c->id,
                        'uuid' => $c->uuid,
                        'display_name' => $c->getDisplayName(),
                        'email_hash' => substr($c->email_hash ?? '', 0, 8) . '...',
                        'total_orders' => $c->total_orders,
                        'total_spent' => $c->total_spent,
                    ])->toArray(),
                    'recommended_primary_id' => $group['recommended_primary']->id,
                ];
            })->toArray(),
        ]);
    }

    /**
     * Find duplicates for a specific customer
     */
    public function customerDuplicates(Request $request, int $customerId): JsonResponse
    {
        $customer = CoreCustomer::find($customerId);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'error' => 'Customer not found',
            ], 404);
        }

        $threshold = $request->input('threshold', 0.5);
        $data = $this->duplicateService->findDuplicatesFor($customer, $threshold);

        return response()->json([
            'success' => true,
            'data' => $data->map(fn($d) => [
                'customer_id' => $d['customer']->id,
                'customer_uuid' => $d['customer']->uuid,
                'display_name' => $d['customer']->getDisplayName(),
                'score' => round($d['score'], 3),
                'match_type' => $d['match_type'],
                'confidence' => $d['confidence'],
            ])->toArray(),
        ]);
    }

    /**
     * Get customer profile with analytics
     */
    public function customerProfile(Request $request, int $customerId): JsonResponse
    {
        $customer = CoreCustomer::find($customerId);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'error' => 'Customer not found',
            ], 404);
        }

        $churnPrediction = $this->churnService->predictChurn($customer);
        $journey = $this->attributionService->analyzeCustomerJourney($customerId);
        $duplicates = $this->duplicateService->findDuplicatesFor($customer);

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => [
                    'id' => $customer->id,
                    'uuid' => $customer->uuid,
                    'display_name' => $customer->getDisplayName(),
                    'segment' => $customer->customer_segment,
                    'rfm_segment' => $customer->rfm_segment,
                    'rfm_score' => $customer->rfm_score,
                    'health_score' => $customer->health_score,
                    'engagement_score' => $customer->engagement_score,
                    'lifetime_value' => $customer->lifetime_value,
                    'total_orders' => $customer->total_orders,
                    'total_spent' => $customer->total_spent,
                    'first_seen_at' => $customer->first_seen_at?->toIso8601String(),
                    'last_seen_at' => $customer->last_seen_at?->toIso8601String(),
                    'cohort_month' => $customer->cohort_month,
                ],
                'churn_prediction' => $churnPrediction,
                'journey_summary' => [
                    'total_touchpoints' => $journey['total_touchpoints'] ?? 0,
                    'total_conversions' => $journey['total_conversions'] ?? 0,
                    'channel_distribution' => $journey['channel_distribution'] ?? [],
                ],
                'potential_duplicates' => $duplicates->count(),
            ],
        ]);
    }

    /**
     * Get LTV prediction dashboard
     */
    public function ltvDashboard(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $data = $this->ltvService->getLtvDashboard($tenantId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Predict LTV for a specific customer
     */
    public function predictCustomerLtv(Request $request, int $customerId): JsonResponse
    {
        $customer = CoreCustomer::find($customerId);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'error' => 'Customer not found',
            ], 404);
        }

        $data = $this->ltvService->predictLtv($customer);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get high-potential customers
     */
    public function highPotentialCustomers(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $limit = $request->input('limit', 50);

        $data = $this->ltvService->getHighPotentialCustomers($limit, $tenantId);

        return response()->json([
            'success' => true,
            'data' => $data->toArray(),
        ]);
    }

    /**
     * Get LTV by segment
     */
    public function ltvBySegment(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $data = $this->ltvService->getLtvBySegment($tenantId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get LTV by cohort
     */
    public function ltvByCohort(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $cohortType = $request->input('type', 'month');
        $cohortsBack = $request->input('cohorts', 12);

        $data = $this->ltvService->getLtvByCohort($cohortType, $cohortsBack, $tenantId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get LTV tier distribution
     */
    public function ltvTiers(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $data = $this->ltvService->getLtvTierDistribution($tenantId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Export analytics data
     *
     * @OA\Get(
     *     path="/api/v1/analytics/export",
     *     summary="Export analytics data in various formats",
     *     tags={"Analytics"},
     *     @OA\Parameter(name="type", in="query", required=true, description="Export type"),
     *     @OA\Parameter(name="format", in="query", description="Export format (csv, xlsx, json)"),
     *     @OA\Response(response=200, description="Exported data")
     * )
     */
    public function export(Request $request): JsonResponse|StreamedResponse
    {
        $request->validate([
            'type' => 'required|in:customers,churn_report,cohort_retention,attribution,segments,conversions,traffic_sources',
            'format' => 'in:csv,xlsx,json',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $type = $request->input('type');
        $format = $request->input('format', 'csv');
        $tenantId = $request->input('tenant_id');
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : null;
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $this->exportService
            ->setTenantId($tenantId)
            ->setDateRange($startDate, $endDate);

        $result = $this->exportService->export($type, $format);

        // If JSON format, wrap in standard response
        if ($format === 'json') {
            return response()->json([
                'success' => true,
                'export_type' => $type,
                'format' => $format,
                'data' => $result,
            ]);
        }

        // For CSV/XLSX, return the streamed response directly
        return $result;
    }

    /**
     * Get available export types and formats
     */
    public function exportOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'types' => AnalyticsExportService::getAvailableTypes(),
                'formats' => AnalyticsExportService::getAvailableFormats(),
            ],
        ]);
    }
}
