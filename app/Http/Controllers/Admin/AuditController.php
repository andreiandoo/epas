<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Admin Audit Logs Controller
 *
 * Provides system-wide audit log access for administrators
 */
class AuditController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get audit logs with filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'tenant_id' => $request->query('tenant_id'),
            'action' => $request->query('action'),
            'resource_type' => $request->query('resource_type'),
            'resource_id' => $request->query('resource_id'),
            'actor_id' => $request->query('actor_id'),
            'severity' => $request->query('severity'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'limit' => min($request->query('limit', 50), 500), // Admin can get more
            'offset' => $request->query('offset', 0),
        ];

        // Remove null filters
        $filters = array_filter($filters, fn($value) => $value !== null);

        $logs = $this->auditService->getLogs($filters);

        return response()->json([
            'success' => true,
            'data' => $logs,
            'meta' => [
                'count' => count($logs),
                'limit' => $filters['limit'] ?? 50,
                'offset' => $filters['offset'] ?? 0,
            ],
        ]);
    }

    /**
     * Get audit statistics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        $from = $request->query('from', now()->subDays(30)->toDateTimeString());
        $to = $request->query('to', now()->toDateTimeString());

        $stats = [
            'by_action' => DB::table('audit_logs')
                ->whereBetween('created_at', [$from, $to])
                ->select('action', DB::raw('COUNT(*) as count'))
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),

            'by_severity' => DB::table('audit_logs')
                ->whereBetween('created_at', [$from, $to])
                ->select('severity', DB::raw('COUNT(*) as count'))
                ->groupBy('severity')
                ->get(),

            'by_resource_type' => DB::table('audit_logs')
                ->whereBetween('created_at', [$from, $to])
                ->select('resource_type', DB::raw('COUNT(*) as count'))
                ->groupBy('resource_type')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),

            'total_logs' => DB::table('audit_logs')
                ->whereBetween('created_at', [$from, $to])
                ->count(),

            'critical_events' => DB::table('audit_logs')
                ->whereBetween('created_at', [$from, $to])
                ->where('severity', 'critical')
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'meta' => [
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    /**
     * Export audit logs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function export(Request $request): JsonResponse
    {
        $filters = [
            'tenant_id' => $request->query('tenant_id'),
            'from' => $request->query('from', now()->subDays(30)->toDateTimeString()),
            'to' => $request->query('to', now()->toDateTimeString()),
            'limit' => min($request->query('limit', 1000), 10000),
        ];

        $filters = array_filter($filters, fn($value) => $value !== null);

        $logs = $this->auditService->getLogs($filters);

        // In a real implementation, you'd generate a CSV or Excel file
        // For now, return JSON with download instructions
        return response()->json([
            'success' => true,
            'message' => 'Export prepared',
            'data' => $logs,
            'meta' => [
                'format' => 'json',
                'count' => count($logs),
            ],
        ]);
    }
}
