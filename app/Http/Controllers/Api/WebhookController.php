<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Microservices\CreateWebhookRequest;
use App\Services\Webhooks\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    public function __construct(protected WebhookService $webhookService)
    {
    }

    /**
     * List all webhooks for a tenant
     */
    public function index(string $tenantId): JsonResponse
    {
        $webhooks = DB::table('tenant_webhooks')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'webhooks' => $webhooks,
        ]);
    }

    /**
     * Create a new webhook
     */
    public function store(CreateWebhookRequest $request, string $tenantId): JsonResponse
    {
        $result = $this->webhookService->createWebhook($tenantId, $request->validated());

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * Update a webhook
     */
    public function update(Request $request, string $tenantId, int $webhookId): JsonResponse
    {
        $result = $this->webhookService->updateWebhook($webhookId, $tenantId, $request->all());

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Delete a webhook
     */
    public function destroy(string $tenantId, int $webhookId): JsonResponse
    {
        $result = $this->webhookService->deleteWebhook($webhookId, $tenantId);

        return response()->json($result, $result['success'] ? 200 : 404);
    }

    /**
     * Get webhook deliveries
     */
    public function deliveries(string $tenantId, int $webhookId): JsonResponse
    {
        $deliveries = DB::table('tenant_webhook_deliveries')
            ->where('webhook_id', $webhookId)
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'deliveries' => $deliveries,
        ]);
    }

    /**
     * Retry a failed delivery
     */
    public function retryDelivery(string $tenantId, int $deliveryId): JsonResponse
    {
        $delivery = DB::table('tenant_webhook_deliveries')
            ->where('id', $deliveryId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$delivery) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery not found',
            ], 404);
        }

        $result = $this->webhookService->deliverWebhook($deliveryId);

        return response()->json($result);
    }
}
