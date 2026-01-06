<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TenantPaymentConfig;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
use App\Services\SmsPayment\SmsPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Unified Payment Webhook Controller
 *
 * Handles webhooks from all payment processors:
 * - Revolut
 * - PayPal
 * - Klarna
 * - SMS Payment (Twilio delivery status)
 */
class PaymentWebhookController extends Controller
{
    /**
     * Handle Revolut webhook
     *
     * @param Request $request
     * @param string $tenantSlug
     * @return Response
     */
    public function handleRevolut(Request $request, string $tenantSlug): Response
    {
        return $this->processWebhook($request, $tenantSlug, 'revolut');
    }

    /**
     * Handle PayPal webhook
     *
     * @param Request $request
     * @param string $tenantSlug
     * @return Response
     */
    public function handlePayPal(Request $request, string $tenantSlug): Response
    {
        return $this->processWebhook($request, $tenantSlug, 'paypal');
    }

    /**
     * Handle Klarna webhook
     *
     * @param Request $request
     * @param string $tenantSlug
     * @return Response
     */
    public function handleKlarna(Request $request, string $tenantSlug): Response
    {
        return $this->processWebhook($request, $tenantSlug, 'klarna');
    }

    /**
     * Handle Twilio SMS status webhook
     *
     * @param Request $request
     * @param string $tenantSlug
     * @return Response
     */
    public function handleTwilioStatus(Request $request, string $tenantSlug): Response
    {
        try {
            $tenant = Tenant::where('slug', $tenantSlug)->first();

            if (!$tenant) {
                Log::warning('Twilio webhook: Tenant not found', ['slug' => $tenantSlug]);
                return response('Tenant not found', 404);
            }

            $messageSid = $request->input('MessageSid');
            $messageStatus = $request->input('MessageStatus');
            $errorCode = $request->input('ErrorCode');

            Log::info('Twilio SMS status update', [
                'tenant_id' => $tenant->id,
                'message_sid' => $messageSid,
                'status' => $messageStatus,
                'error_code' => $errorCode,
            ]);

            // You could update a sms_logs table here if needed
            // SmsLog::where('message_sid', $messageSid)->update(['status' => $messageStatus]);

            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error('Twilio webhook error', [
                'error' => $e->getMessage(),
            ]);
            return response('Error', 500);
        }
    }

    /**
     * Process webhook for any payment processor
     *
     * @param Request $request
     * @param string $tenantSlug
     * @param string $processor
     * @return Response
     */
    protected function processWebhook(Request $request, string $tenantSlug, string $processor): Response
    {
        try {
            // Find tenant
            $tenant = Tenant::where('slug', $tenantSlug)->first();

            if (!$tenant) {
                Log::warning("Webhook {$processor}: Tenant not found", ['slug' => $tenantSlug]);
                return response('Tenant not found', 404);
            }

            // Get payment config
            $config = TenantPaymentConfig::where('tenant_id', $tenant->id)
                ->where('processor', $processor)
                ->where('is_active', true)
                ->first();

            if (!$config) {
                Log::warning("Webhook {$processor}: Config not found", ['tenant_id' => $tenant->id]);
                return response('Payment config not found', 404);
            }

            // Create processor instance
            $paymentProcessor = PaymentProcessorFactory::makeFromConfig($config);

            // Get headers for signature verification
            $headers = $this->getRelevantHeaders($request, $processor);

            // Get payload
            $payload = $request->all();

            // Verify signature
            if (!$paymentProcessor->verifySignature($payload, $headers)) {
                Log::warning("Webhook {$processor}: Invalid signature", [
                    'tenant_id' => $tenant->id,
                ]);
                return response('Invalid signature', 401);
            }

            // Process callback
            $result = $paymentProcessor->processCallback($payload, $headers);

            Log::info("Webhook {$processor}: Processed", [
                'tenant_id' => $tenant->id,
                'status' => $result['status'],
                'order_id' => $result['order_id'],
                'payment_id' => $result['payment_id'],
            ]);

            // Update order if found
            if (!empty($result['order_id'])) {
                $this->updateOrder($tenant->id, $result, $processor);
            }

            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error("Webhook {$processor} error", [
                'tenant_slug' => $tenantSlug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 200 to prevent retry storms for permanent errors
            // Return 500 only for transient errors
            return response('Error: ' . $e->getMessage(), 200);
        }
    }

    /**
     * Get relevant headers for signature verification
     *
     * @param Request $request
     * @param string $processor
     * @return array
     */
    protected function getRelevantHeaders(Request $request, string $processor): array
    {
        $headers = [];

        switch ($processor) {
            case 'revolut':
                $headers = [
                    'revolut-signature' => $request->header('Revolut-Signature'),
                    'revolut-request-timestamp' => $request->header('Revolut-Request-Timestamp'),
                ];
                break;

            case 'paypal':
                $headers = [
                    'paypal-auth-algo' => $request->header('PAYPAL-AUTH-ALGO'),
                    'paypal-cert-url' => $request->header('PAYPAL-CERT-URL'),
                    'paypal-transmission-id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                    'paypal-transmission-sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                    'paypal-transmission-time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                ];
                break;

            case 'klarna':
                // Klarna uses basic auth, typically validated at route level
                $headers = [
                    'authorization' => $request->header('Authorization'),
                ];
                break;
        }

        return array_filter($headers);
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
                    ->orWhere('payment_reference', $result['payment_id']);
            })
            ->first();

        if (!$order) {
            Log::warning("Order not found for webhook update", [
                'tenant_id' => $tenantId,
                'order_id' => $result['order_id'],
                'payment_id' => $result['payment_id'],
            ]);
            return;
        }

        switch ($result['status']) {
            case 'success':
                $order->update([
                    'payment_status' => 'paid',
                    'payment_reference' => $result['payment_id'],
                    'payment_processor' => $processor,
                    'paid_at' => $result['paid_at'] ?? now(),
                    'metadata' => array_merge($order->metadata ?? [], [
                        'transaction_id' => $result['transaction_id'],
                        'webhook_received_at' => now()->toIso8601String(),
                    ]),
                ]);

                // Send SMS confirmation if SMS payment was used
                if (!empty($result['metadata']['sms_payment'])) {
                    $this->sendSmsConfirmation($order);
                }

                Log::info("Order marked as paid via {$processor}", [
                    'order_id' => $order->id,
                    'payment_id' => $result['payment_id'],
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
