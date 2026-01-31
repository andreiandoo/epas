<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Alerts\AlertService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * Admin Alert Management Controller
 *
 * Allows administrators to manage system alerts
 */
class AlertController extends Controller
{
    public function __construct(
        protected AlertService $alertService
    ) {}

    /**
     * Get alert configuration
     *
     * @return JsonResponse
     */
    public function config(): JsonResponse
    {
        $config = [
            'enabled' => config('microservices.alerts.enabled'),
            'email' => config('microservices.alerts.email'),
            'slack' => [
                'enabled' => config('microservices.alerts.slack.enabled'),
                'webhook_url' => config('microservices.alerts.slack.webhook_url') ? '***configured***' : null,
            ],
            'recipients' => config('microservices.alerts.recipients'),
        ];

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Test alert delivery
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function test(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:health,microservice_expiring,webhook_failure',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $type = $request->input('type');

        // Create test data based on type
        $testData = match($type) {
            'health' => [
                'status' => 'degraded',
                'timestamp' => now()->toIso8601String(),
                'checks' => [
                    'app' => ['status' => 'healthy'],
                    'database' => ['status' => 'degraded', 'message' => 'Test alert'],
                ],
            ],
            'microservice_expiring' => [
                'tenant_id' => 'test-tenant',
                'microservice_id' => 'test-service',
                'expires_at' => now()->addDays(3)->toDateTimeString(),
            ],
            'webhook_failure' => [
                'tenant_id' => 'test-tenant',
                'event_type' => 'test.event',
                'webhook_url' => 'https://example.com/webhook',
                'attempts' => 5,
                'last_error' => 'Test error',
            ],
            default => [],
        };

        try {
            $result = match($type) {
                'health' => $this->alertService->sendHealthAlert($testData),
                default => ['sent' => false],
            };

            return response()->json([
                'success' => true,
                'message' => 'Test alert sent',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to send test alert: ' . $e->getMessage(),
            ], 500);
        }
    }
}
