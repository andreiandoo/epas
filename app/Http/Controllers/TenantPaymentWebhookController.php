<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantPaymentConfig;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
use App\Services\SmsPayment\SmsPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TenantPaymentWebhookController extends Controller
{
    /**
     * Handle webhook/callback from payment processor
     */
    public function handle(Request $request, string $tenantId, string $processor)
    {
        try {
            // Find tenant
            $tenant = Tenant::findOrFail($tenantId);

            // Verify processor matches
            if ($tenant->payment_processor !== $processor) {
                Log::warning('Payment processor mismatch', [
                    'tenant_id' => $tenantId,
                    'expected' => $tenant->payment_processor,
                    'received' => $processor,
                ]);
                return response()->json(['error' => 'Invalid processor'], 400);
            }

            // Get payment config
            $config = TenantPaymentConfig::where('tenant_id', $tenant->id)
                ->where('processor', $processor)
                ->where('is_active', true)
                ->first();

            if (!$config) {
                Log::error('No active payment config found', [
                    'tenant_id' => $tenantId,
                    'processor' => $processor,
                ]);
                return response()->json(['error' => 'No configuration found'], 404);
            }

            // Get processor instance
            $processorInstance = PaymentProcessorFactory::makeFromConfig($config);

            // Get payload and headers
            $payload = $request->all();
            $headers = $request->header();

            // Verify signature
            if (!$processorInstance->verifySignature($payload, $headers)) {
                Log::warning('Invalid webhook signature', [
                    'tenant_id' => $tenantId,
                    'processor' => $processor,
                ]);
                return response()->json(['error' => 'Invalid signature'], 403);
            }

            // Process callback
            $result = $processorInstance->processCallback($payload, $headers);

            // Log the webhook
            Log::info('Payment webhook received', [
                'tenant_id' => $tenantId,
                'processor' => $processor,
                'status' => $result['status'],
                'order_id' => $result['order_id'] ?? null,
                'amount' => $result['amount'] ?? null,
            ]);

            // Process the payment result - update orders
            if (!empty($result['order_id'])) {
                $this->updateOrder($tenant->id, $result, $processor);
            }

            return response()->json(['success' => true, 'status' => $result['status']]);

        } catch (\Exception $e) {
            Log::error('Payment webhook error', [
                'tenant_id' => $tenantId,
                'processor' => $processor,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Update order based on webhook result
     *
     * @param int $tenantId
     * @param array $result
     * @param string $processor
     */
    protected function updateOrder(int $tenantId, array $result, string $processor): void
    {
        // Try to find order by various references
        $order = Order::where('tenant_id', $tenantId)
            ->where(function ($query) use ($result) {
                $query->where('id', $result['order_id'])
                    ->orWhere('order_number', $result['order_id'])
                    ->orWhere('uuid', $result['order_id'])
                    ->orWhere('payment_reference', $result['payment_id'] ?? null);
            })
            ->first();

        if (!$order) {
            Log::warning("Order not found for webhook update", [
                'tenant_id' => $tenantId,
                'order_id' => $result['order_id'],
                'payment_id' => $result['payment_id'] ?? null,
            ]);
            return;
        }

        switch ($result['status']) {
            case 'success':
                $order->update([
                    'payment_status' => 'paid',
                    'payment_reference' => $result['payment_id'] ?? $order->payment_reference,
                    'payment_processor' => $processor,
                    'paid_at' => $result['paid_at'] ?? now(),
                    'metadata' => array_merge($order->metadata ?? [], [
                        'transaction_id' => $result['transaction_id'] ?? null,
                        'webhook_received_at' => now()->toIso8601String(),
                    ]),
                ]);

                // Send SMS confirmation if SMS payment was used
                if (!empty($result['metadata']['sms_payment'])) {
                    $this->sendSmsConfirmation($order);
                }

                Log::info("Order marked as paid via {$processor}", [
                    'order_id' => $order->id,
                    'payment_id' => $result['payment_id'] ?? null,
                ]);
                break;

            case 'failed':
                $order->update([
                    'payment_status' => 'failed',
                    'payment_error' => $result['error_message'] ?? 'Payment failed',
                    'payment_processor' => $processor,
                ]);

                Log::info("Order payment failed via {$processor}", [
                    'order_id' => $order->id,
                ]);
                break;

            case 'cancelled':
                $order->update([
                    'payment_status' => 'cancelled',
                    'cancelled_at' => now(),
                    'payment_processor' => $processor,
                ]);

                Log::info("Order cancelled via {$processor}", [
                    'order_id' => $order->id,
                ]);
                break;
        }
    }

    /**
     * Send SMS payment confirmation
     *
     * @param Order $order
     */
    protected function sendSmsConfirmation(Order $order): void
    {
        try {
            $smsService = new SmsPaymentService($order->tenant);

            if ($smsService->isAvailable()) {
                $smsService->sendPaymentConfirmation($order);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send SMS confirmation', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
