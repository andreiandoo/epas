<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Api\TenantApiKeyService;
use App\Services\Audit\AuditService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * Tenant API Key Management Controller
 *
 * Allows tenants to manage their API keys for microservices access
 */
class ApiKeyController extends Controller
{
    public function __construct(
        protected TenantApiKeyService $apiKeyService,
        protected AuditService $auditService
    ) {}

    /**
     * List all API keys for the authenticated tenant
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $keys = $this->apiKeyService->getKeys($tenantId);

        // Remove sensitive data
        $keys = array_map(function ($key) {
            unset($key['api_key']); // Never expose the hashed key
            return $key;
        }, $keys);

        return response()->json([
            'success' => true,
            'data' => $keys,
        ]);
    }

    /**
     * Create a new API key
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'scopes' => 'array',
            'scopes.*' => 'string',
            'rate_limit' => 'integer|min:1|max:10000',
            'allowed_ips' => 'array',
            'allowed_ips.*' => 'ip',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Get available scopes and validate
        $availableScopes = array_keys($this->apiKeyService->getAvailableScopes());
        $requestedScopes = $data['scopes'] ?? ['*'];

        foreach ($requestedScopes as $scope) {
            if (!in_array($scope, $availableScopes)) {
                return response()->json([
                    'success' => false,
                    'error' => "Invalid scope: {$scope}",
                ], 422);
            }
        }

        // Generate the API key
        $result = $this->apiKeyService->generateKey($tenantId, [
            'name' => $data['name'],
            'scopes' => $requestedScopes,
            'rate_limit' => $data['rate_limit'] ?? config('microservices.api.default_rate_limit', 1000),
            'allowed_ips' => $data['allowed_ips'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        // Log audit trail
        $this->auditService->logApiKeyCreation(
            $tenantId,
            $result['key_id'],
            [
                'type' => 'api_key',
                'id' => $request->attributes->get('api_key_id'),
                'name' => $request->attributes->get('api_key_name'),
            ],
            $requestedScopes,
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'API key created successfully. Please save the key - it will not be shown again.',
            'data' => [
                'key_id' => $result['key_id'],
                'api_key' => $result['api_key'], // Only shown once
                'name' => $data['name'],
                'scopes' => $requestedScopes,
            ],
        ], 201);
    }

    /**
     * Get API key details
     *
     * @param Request $request
     * @param string $keyId
     * @return JsonResponse
     */
    public function show(Request $request, string $keyId): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $keys = $this->apiKeyService->getKeys($tenantId);

        $key = collect($keys)->firstWhere('id', $keyId);

        if (!$key) {
            return response()->json([
                'success' => false,
                'error' => 'API key not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $key,
        ]);
    }

    /**
     * Update API key scopes or settings
     *
     * @param Request $request
     * @param string $keyId
     * @return JsonResponse
     */
    public function update(Request $request, string $keyId): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        // Verify ownership
        $keys = $this->apiKeyService->getKeys($tenantId);
        $key = collect($keys)->firstWhere('id', $keyId);

        if (!$key) {
            return response()->json([
                'success' => false,
                'error' => 'API key not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'scopes' => 'array',
            'scopes.*' => 'string',
            'rate_limit' => 'integer|min:1|max:10000',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Update scopes if provided
        if (isset($data['scopes'])) {
            $this->apiKeyService->updateScopes($keyId, $data['scopes']);
        }

        // Update rate limit if provided
        if (isset($data['rate_limit'])) {
            $this->apiKeyService->updateRateLimit($keyId, $data['rate_limit']);
        }

        // Update allowed IPs if provided
        if (array_key_exists('allowed_ips', $data)) {
            $this->apiKeyService->updateAllowedIps($keyId, $data['allowed_ips']);
        }

        return response()->json([
            'success' => true,
            'message' => 'API key updated successfully',
        ]);
    }

    /**
     * Revoke an API key
     *
     * @param Request $request
     * @param string $keyId
     * @return JsonResponse
     */
    public function destroy(Request $request, string $keyId): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        // Verify ownership
        $keys = $this->apiKeyService->getKeys($tenantId);
        $key = collect($keys)->firstWhere('id', $keyId);

        if (!$key) {
            return response()->json([
                'success' => false,
                'error' => 'API key not found',
            ], 404);
        }

        $this->apiKeyService->revokeKey($keyId);

        // Log audit trail
        $this->auditService->logApiKeyRevocation(
            $tenantId,
            $keyId,
            [
                'type' => 'api_key',
                'id' => $request->attributes->get('api_key_id'),
                'name' => $request->attributes->get('api_key_name'),
            ],
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'API key revoked successfully',
        ]);
    }

    /**
     * Get usage statistics for an API key
     *
     * @param Request $request
     * @param string $keyId
     * @return JsonResponse
     */
    public function usage(Request $request, string $keyId): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        // Verify ownership
        $keys = $this->apiKeyService->getKeys($tenantId);
        $key = collect($keys)->firstWhere('id', $keyId);

        if (!$key) {
            return response()->json([
                'success' => false,
                'error' => 'API key not found',
            ], 404);
        }

        $days = $request->query('days', 7);

        $stats = $this->apiKeyService->getUsageStats($keyId, $days);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get available scopes
     *
     * @return JsonResponse
     */
    public function scopes(): JsonResponse
    {
        $scopes = $this->apiKeyService->getAvailableScopes();

        return response()->json([
            'success' => true,
            'data' => $scopes,
        ]);
    }
}
