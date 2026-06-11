<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantPaymentConfig;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
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

            // TODO: Process the payment result
            // This is where you would update orders, create transactions, etc.
            // For now, we just log it

            // Example:
            // if ($result['status'] === 'success') {
            //     $order = Order::where('reference', $result['order_id'])->first();
            //     if ($order) {
            //         $order->update([
            //             'status' => 'paid',
            //             'paid_at' => $result['paid_at'],
            //             'payment_transaction_id' => $result['transaction_id'],
            //         ]);
            //     }
            // }

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
}
