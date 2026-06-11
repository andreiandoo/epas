<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Api\TenantApiKeyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Admin API Usage Monitoring Controller
 *
 * Provides API usage analytics for administrators
 */
class ApiUsageController extends Controller
{
    public function __construct(
        protected TenantApiKeyService $apiKeyService
    ) {}

    /**
     * Get API usage overview
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $days = min($request->query('days', 7), 90);

        $stats = [
            'total_api_keys' => DB::table('tenant_api_keys')->count(),
            'active_api_keys' => DB::table('tenant_api_keys')
                ->where('status', 'active')
                ->count(),
            'revoked_api_keys' => DB::table('tenant_api_keys')
                ->where('status', 'revoked')
                ->count(),
        ];

        if (config('microservices.api.track_detailed_usage', false)) {
            $since = now()->subDays($days);

            $stats['total_requests'] = DB::table('tenant_api_usage')
                ->where('created_at', '>=', $since)
                ->count();

            $stats['requests_by_day'] = DB::table('tenant_api_usage')
                ->where('created_at', '>=', $since)
                ->selectRaw('DATE(created_at) as date, COUNT(*) as requests')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->get();

            $stats['top_endpoints'] = DB::table('tenant_api_usage')
                ->where('created_at', '>=', $since)
                ->select('endpoint', DB::raw('COUNT(*) as requests'))
                ->groupBy('endpoint')
                ->orderByDesc('requests')
                ->limit(10)
                ->get();

            $stats['error_rate'] = DB::table('tenant_api_usage')
                ->where('created_at', '>=', $since)
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN response_status >= 400 THEN 1 ELSE 0 END) as errors,
                    ROUND(SUM(CASE WHEN response_status >= 400 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as error_percentage
                ')
                ->first();
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
            'meta' => [
                'days' => $days,
                'detailed_tracking' => config('microservices.api.track_detailed_usage', false),
            ],
        ]);
    }

    /**
     * Get usage by tenant
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function byTenant(Request $request): JsonResponse
    {
        $days = min($request->query('days', 7), 90);

        $usage = DB::table('tenant_api_keys')
            ->join('tenants', 'tenant_api_keys.tenant_id', '=', 'tenants.id')
            ->select(
                'tenants.id',
                'tenants.name',
                DB::raw('COUNT(tenant_api_keys.id) as api_keys'),
                DB::raw('SUM(tenant_api_keys.total_requests) as total_requests')
            )
            ->groupBy('tenants.id', 'tenants.name')
            ->orderByDesc('total_requests')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $usage,
        ]);
    }

    /**
     * Get usage for a specific API key
     *
     * @param string $keyId
     * @return JsonResponse
     */
    public function show(string $keyId): JsonResponse
    {
        $key = DB::table('tenant_api_keys')
            ->where('id', $keyId)
            ->first();

        if (!$key) {
            return response()->json([
                'success' => false,
                'error' => 'API key not found',
            ], 404);
        }

        $stats = $this->apiKeyService->getUsageStats($keyId, 30);

        return response()->json([
            'success' => true,
            'data' => [
                'key' => [
                    'id' => $key->id,
                    'tenant_id' => $key->tenant_id,
                    'name' => $key->name,
                    'status' => $key->status,
                    'created_at' => $key->created_at,
                ],
                'stats' => $stats,
            ],
        ]);
    }

    /**
     * Get rate limit violations
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function rateLimitViolations(Request $request): JsonResponse
    {
        if (!config('microservices.api.track_detailed_usage', false)) {
            return response()->json([
                'success' => false,
                'error' => 'Detailed usage tracking is not enabled',
            ], 400);
        }

        $hours = min($request->query('hours', 24), 168);
        $since = now()->subHours($hours);

        // Get 429 responses (rate limit exceeded)
        $violations = DB::table('tenant_api_usage')
            ->join('tenant_api_keys', 'tenant_api_usage.api_key_id', '=', 'tenant_api_keys.id')
            ->where('tenant_api_usage.response_status', 429)
            ->where('tenant_api_usage.created_at', '>=', $since)
            ->select(
                'tenant_api_keys.tenant_id',
                'tenant_api_keys.name as api_key_name',
                DB::raw('COUNT(*) as violation_count'),
                DB::raw('MAX(tenant_api_usage.created_at) as last_violation')
            )
            ->groupBy('tenant_api_keys.tenant_id', 'tenant_api_keys.name')
            ->orderByDesc('violation_count')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $violations,
            'meta' => [
                'hours' => $hours,
            ],
        ]);
    }
}
