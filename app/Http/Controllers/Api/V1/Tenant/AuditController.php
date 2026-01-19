<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Tenant Audit Logs Controller
 *
 * Allows tenants to view their audit logs
 */
class AuditController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get audit logs for the authenticated tenant
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $filters = [
            'tenant_id' => $tenantId,
            'action' => $request->query('action'),
            'resource_type' => $request->query('resource_type'),
            'resource_id' => $request->query('resource_id'),
            'actor_id' => $request->query('actor_id'),
            'severity' => $request->query('severity'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'limit' => min($request->query('limit', 50), 100),
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
     * Get available actions for filtering
     *
     * @return JsonResponse
     */
    public function actions(): JsonResponse
    {
        $actions = [
            'microservice.activated',
            'microservice.deactivated',
            'webhook.created',
            'webhook.updated',
            'webhook.deleted',
            'api_key.created',
            'api_key.revoked',
            'feature_flag.changed',
            'config.changed',
        ];

        return response()->json([
            'success' => true,
            'data' => $actions,
        ]);
    }
}
