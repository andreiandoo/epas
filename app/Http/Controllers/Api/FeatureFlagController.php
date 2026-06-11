<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Microservices\CreateFeatureFlagRequest;
use App\Services\FeatureFlags\FeatureFlagService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FeatureFlagController extends Controller
{
    public function __construct(protected FeatureFlagService $featureFlagService)
    {
    }

    /**
     * List all feature flags
     */
    public function index(): JsonResponse
    {
        $flags = DB::table('feature_flags')
            ->orderBy('key')
            ->get();

        return response()->json([
            'success' => true,
            'feature_flags' => $flags,
        ]);
    }

    /**
     * Create a new feature flag
     */
    public function store(CreateFeatureFlagRequest $request): JsonResponse
    {
        $result = $this->featureFlagService->createFeature($request->validated());

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * Update a feature flag
     */
    public function update(Request $request, string $featureKey): JsonResponse
    {
        $success = $this->featureFlagService->updateFeature($featureKey, $request->all());

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Feature flag updated' : 'Failed to update feature flag',
        ]);
    }

    /**
     * Enable a feature flag
     */
    public function enable(string $featureKey): JsonResponse
    {
        $success = $this->featureFlagService->enableFeature($featureKey);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Feature enabled' : 'Failed to enable feature',
        ]);
    }

    /**
     * Disable a feature flag
     */
    public function disable(string $featureKey): JsonResponse
    {
        $success = $this->featureFlagService->disableFeature($featureKey);

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Feature disabled' : 'Failed to disable feature',
        ]);
    }

    /**
     * Check if feature is enabled for tenant
     */
    public function check(Request $request, string $featureKey): JsonResponse
    {
        $tenantId = $request->input('tenant_id');
        $context = $request->input('context', []);

        $isEnabled = $this->featureFlagService->isEnabled($featureKey, $tenantId, $context);

        return response()->json([
            'success' => true,
            'feature_key' => $featureKey,
            'is_enabled' => $isEnabled,
        ]);
    }

    /**
     * Enable feature for specific tenant
     */
    public function enableForTenant(Request $request, string $featureKey, string $tenantId): JsonResponse
    {
        $success = $this->featureFlagService->enableForTenant(
            $tenantId,
            $featureKey,
            $request->user()?->id
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Feature enabled for tenant' : 'Failed to enable feature',
        ]);
    }

    /**
     * Disable feature for specific tenant
     */
    public function disableForTenant(Request $request, string $featureKey, string $tenantId): JsonResponse
    {
        $success = $this->featureFlagService->disableForTenant(
            $tenantId,
            $featureKey,
            $request->user()?->id
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Feature disabled for tenant' : 'Failed to disable feature',
        ]);
    }
}
