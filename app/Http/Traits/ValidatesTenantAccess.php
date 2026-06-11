<?php

namespace App\Http\Traits;

use App\Models\Platform\CoreCustomer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Trait for validating tenant access in API controllers
 *
 * Ensures requests can only access data for their authorized tenant,
 * preventing IDOR (Insecure Direct Object Reference) vulnerabilities.
 */
trait ValidatesTenantAccess
{
    /**
     * Allowed columns for order_by to prevent SQL injection
     */
    protected array $allowedOrderByColumns = [
        'total_spent',
        'total_orders',
        'rfm_score',
        'lifetime_value',
        'health_score',
        'engagement_score',
        'created_at',
        'last_seen_at',
        'first_seen_at',
    ];

    /**
     * Allowed risk levels for churn filtering
     */
    protected array $allowedRiskLevels = [
        'critical',
        'high',
        'medium',
        'low',
        'minimal',
    ];

    /**
     * Allowed cohort types
     */
    protected array $allowedCohortTypes = [
        'month',
        'week',
        'quarter',
    ];

    /**
     * Get the authorized tenant ID from the request
     *
     * This retrieves the tenant_id from the authenticated API key,
     * NOT from user input. This prevents IDOR attacks.
     */
    protected function getAuthorizedTenantId(Request $request): ?int
    {
        // First check if tenant_id was set by AuthenticateTenantApi middleware
        $tenantId = $request->attributes->get('tenant_id');

        if ($tenantId) {
            return (int) $tenantId;
        }

        // Fallback: Check if api_key was set by VerifyApiKey middleware
        $apiKey = $request->attributes->get('api_key');

        if ($apiKey && isset($apiKey->tenant_id)) {
            return (int) $apiKey->tenant_id;
        }

        // If no tenant context, check if this is an admin request
        // Admin users can optionally specify tenant_id
        if ($this->isAdminRequest($request)) {
            return $request->input('tenant_id') ? (int) $request->input('tenant_id') : null;
        }

        return null;
    }

    /**
     * Check if the request is from an admin user
     */
    protected function isAdminRequest(Request $request): bool
    {
        $apiKey = $request->attributes->get('api_key');

        if ($apiKey) {
            $permissions = $apiKey->permissions ?? [];
            return in_array('admin', $permissions) || in_array('*', $permissions);
        }

        return false;
    }

    /**
     * Verify a customer belongs to the authorized tenant
     */
    protected function verifyCustomerAccess(CoreCustomer $customer, Request $request): bool
    {
        $tenantId = $this->getAuthorizedTenantId($request);

        // If no tenant context (admin), allow access
        if ($tenantId === null && $this->isAdminRequest($request)) {
            return true;
        }

        // Verify customer belongs to tenant
        return $customer->primary_tenant_id === $tenantId;
    }

    /**
     * Find customer with tenant verification
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function findCustomerWithTenantCheck(int $customerId, Request $request): ?CoreCustomer
    {
        $tenantId = $this->getAuthorizedTenantId($request);

        $query = CoreCustomer::where('id', $customerId);

        // Apply tenant filter unless admin with no tenant context
        if ($tenantId !== null) {
            $query->where('primary_tenant_id', $tenantId);
        } elseif (!$this->isAdminRequest($request)) {
            // Non-admin without tenant context - deny access
            return null;
        }

        return $query->first();
    }

    /**
     * Create unauthorized tenant access response
     */
    protected function unauthorizedTenantResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Unauthorized',
            'message' => 'You do not have access to this resource',
        ], 403);
    }

    /**
     * Create customer not found response
     */
    protected function customerNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'Customer not found',
        ], 404);
    }

    /**
     * Validate order_by parameter against whitelist
     */
    protected function validateOrderBy(string $orderBy): bool
    {
        return in_array($orderBy, $this->allowedOrderByColumns, true);
    }

    /**
     * Get safe order_by value (returns default if invalid)
     */
    protected function getSafeOrderBy(Request $request, string $default = 'total_spent'): string
    {
        $orderBy = $request->input('order_by', $default);

        return $this->validateOrderBy($orderBy) ? $orderBy : $default;
    }

    /**
     * Validate risk level parameter
     */
    protected function validateRiskLevel(string $riskLevel): bool
    {
        return in_array($riskLevel, $this->allowedRiskLevels, true);
    }

    /**
     * Get safe risk level (returns default if invalid)
     */
    protected function getSafeRiskLevel(Request $request, string $default = 'high'): string
    {
        $riskLevel = $request->input('min_risk', $default);

        return $this->validateRiskLevel($riskLevel) ? $riskLevel : $default;
    }

    /**
     * Validate cohort type parameter
     */
    protected function validateCohortType(string $cohortType): bool
    {
        return in_array($cohortType, $this->allowedCohortTypes, true);
    }

    /**
     * Get safe cohort type (returns default if invalid)
     */
    protected function getSafeCohortType(Request $request, string $default = 'month'): string
    {
        $cohortType = $request->input('type', $default);

        return $this->validateCohortType($cohortType) ? $cohortType : $default;
    }

    /**
     * Get validated limit parameter (bounded between 1 and max)
     */
    protected function getSafeLimit(Request $request, int $default = 50, int $max = 1000): int
    {
        $limit = (int) $request->input('limit', $default);

        return max(1, min($limit, $max));
    }

    /**
     * Get validated threshold parameter (bounded between 0 and 1)
     */
    protected function getSafeThreshold(Request $request, float $default = 0.7): float
    {
        $threshold = (float) $request->input('threshold', $default);

        return max(0.0, min($threshold, 1.0));
    }

    /**
     * Get validated cohorts count (bounded between 1 and max)
     */
    protected function getSafeCohorts(Request $request, int $default = 6, int $max = 24): int
    {
        $cohorts = (int) $request->input('cohorts', $default);

        return max(1, min($cohorts, $max));
    }
}
