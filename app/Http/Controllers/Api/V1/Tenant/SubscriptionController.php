<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Cache\MicroservicesCacheService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Tenant Subscription Controller
 *
 * Allows tenants to view their microservice subscriptions
 */
class SubscriptionController extends Controller
{
    public function __construct(
        protected MicroservicesCacheService $cacheService
    ) {}

    /**
     * Get tenant's active subscriptions
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $subscriptions = $this->cacheService->getTenantSubscriptions($tenantId);

        // Enrich with microservice details
        $catalog = $this->cacheService->getCatalog();
        $catalogMap = collect($catalog)->keyBy('id');

        $enriched = array_map(function ($subscription) use ($catalogMap) {
            $microservice = $catalogMap->get($subscription->microservice_id);

            return [
                'id' => $subscription->id,
                'microservice_id' => $subscription->microservice_id,
                'microservice_name' => $microservice->name ?? 'Unknown',
                'status' => $subscription->status,
                'activated_at' => $subscription->activated_at,
                'expires_at' => $subscription->expires_at,
                'auto_renew' => $subscription->auto_renew ?? false,
                'config' => json_decode($subscription->config, true),
            ];
        }, $subscriptions);

        return response()->json([
            'success' => true,
            'data' => $enriched,
        ]);
    }

    /**
     * Get available microservices catalog
     *
     * @return JsonResponse
     */
    public function catalog(): JsonResponse
    {
        $catalog = $this->cacheService->getCatalog();

        return response()->json([
            'success' => true,
            'data' => $catalog,
        ]);
    }

    /**
     * Get specific subscription details
     *
     * @param Request $request
     * @param string $subscriptionId
     * @return JsonResponse
     */
    public function show(Request $request, string $subscriptionId): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $subscription = DB::table('tenant_microservices')
            ->where('id', $subscriptionId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'error' => 'Subscription not found',
            ], 404);
        }

        // Get microservice details
        $microservice = $this->cacheService->getMicroservice($subscription->microservice_id);

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $subscription,
                'microservice' => $microservice,
            ],
        ]);
    }
}
