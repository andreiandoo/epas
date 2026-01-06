<?php

namespace App\Services\PaymentProcessors;

use App\Models\TenantPaymentConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Revolut Payment Processor
 *
 * Integrates with Revolut Merchant API for payment processing.
 * Supports card payments, Apple Pay, Google Pay, and Revolut Pay.
 *
 * @see https://developer.revolut.com/docs/merchant
 */
class RevolutProcessor implements PaymentProcessorInterface
{
    protected TenantPaymentConfig $config;
    protected array $keys;
    protected string $baseUrl;

    /**
     * Revolut API endpoints
     */
    private const SANDBOX_URL = 'https://sandbox-merchant.revolut.com/api/1.0';
    private const PRODUCTION_URL = 'https://merchant.revolut.com/api/1.0';

    public function __construct(TenantPaymentConfig $config)
    {
        $this->config = $config;
        $this->keys = $config->getActiveKeys();
        $this->baseUrl = $config->mode === 'live' ? self::PRODUCTION_URL : self::SANDBOX_URL;
    }

    /**
     * Create a payment order with Revolut
     *
     * @param array $data Payment data
     * @return array Payment details with redirect URL
     */
    public function createPayment(array $data): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Revolut is not properly configured');
        }

        // Revolut uses minor units (cents/pence)
        $amountInMinorUnits = (int) round($data['amount'] * 100);

        $orderData = [
            'amount' => $amountInMinorUnits,
            'currency' => strtoupper($data['currency'] ?? 'EUR'),
            'description' => $data['description'] ?? 'Payment',
            'merchant_order_ext_ref' => $data['order_id'] ?? uniqid('order_'),
            'customer_email' => $data['customer_email'] ?? null,
            'settle_on_complete' => true,
            'metadata' => array_merge(
                ['order_id' => $data['order_id'] ?? null],
                $data['metadata'] ?? []
            ),
        ];

        // Add redirect URLs for hosted checkout
        if (!empty($data['success_url'])) {
            $orderData['redirect_urls'] = [
                'success_url' => $data['success_url'],
                'failure_url' => $data['cancel_url'] ?? $data['success_url'],
                'cancel_url' => $data['cancel_url'] ?? $data['success_url'],
            ];
        }

        // Add customer name if provided
        if (!empty($data['customer_name'])) {
            $orderData['metadata']['customer_name'] = $data['customer_name'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->keys['api_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($this->baseUrl . '/orders', $orderData);

            if (!$response->successful()) {
                Log::error('Revolut payment creation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Failed to create Revolut payment: ' . ($response->json('message') ?? 'Unknown error'));
            }

            $order = $response->json();

            return [
                'payment_id' => $order['id'],
                'redirect_url' => $order['checkout_url'] ?? null,
                'additional_data' => [
                    'order_id' => $order['id'],
                    'state' => $order['state'],
                    'public_id' => $order['public_id'] ?? null,
                    'merchant_order_ref' => $order['merchant_order_ext_ref'] ?? null,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Revolut payment error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Process Revolut webhook callback
     *
     * @param array $payload Webhook payload
     * @param array $headers Request headers
     * @return array Normalized payment result
     */
    public function processCallback(array $payload, array $headers = []): array
    {
        // Verify signature if webhook secret is configured
        if (!empty($this->keys['webhook_secret'])) {
            if (!$this->verifySignature($payload, $headers)) {
                throw new \Exception('Invalid webhook signature');
            }
        }

        $result = [
            'status' => 'pending',
            'payment_id' => null,
            'order_id' => null,
            'amount' => 0,
            'currency' => 'EUR',
            'transaction_id' => null,
            'paid_at' => null,
            'metadata' => [],
        ];

        $eventType = $payload['event'] ?? null;
        $order = $payload['order'] ?? $payload;

        switch ($eventType) {
            case 'ORDER_COMPLETED':
                $result = [
                    'status' => 'success',
                    'payment_id' => $order['id'] ?? null,
                    'order_id' => $order['merchant_order_ext_ref'] ?? ($order['metadata']['order_id'] ?? null),
                    'amount' => ($order['order_amount']['value'] ?? 0) / 100,
                    'currency' => strtoupper($order['order_amount']['currency'] ?? 'EUR'),
                    'transaction_id' => $order['id'] ?? null,
                    'paid_at' => $order['completed_at'] ?? date('c'),
                    'metadata' => (array) ($order['metadata'] ?? []),
                ];
                break;

            case 'ORDER_AUTHORISED':
                $result = [
                    'status' => 'success',
                    'payment_id' => $order['id'] ?? null,
                    'order_id' => $order['merchant_order_ext_ref'] ?? ($order['metadata']['order_id'] ?? null),
                    'amount' => ($order['order_amount']['value'] ?? 0) / 100,
                    'currency' => strtoupper($order['order_amount']['currency'] ?? 'EUR'),
                    'transaction_id' => $order['id'] ?? null,
                    'paid_at' => date('c'),
                    'metadata' => (array) ($order['metadata'] ?? []),
                ];
                break;

            case 'ORDER_PAYMENT_DECLINED':
            case 'ORDER_PAYMENT_FAILED':
                $result = [
                    'status' => 'failed',
                    'payment_id' => $order['id'] ?? null,
                    'order_id' => $order['merchant_order_ext_ref'] ?? ($order['metadata']['order_id'] ?? null),
                    'amount' => ($order['order_amount']['value'] ?? 0) / 100,
                    'currency' => strtoupper($order['order_amount']['currency'] ?? 'EUR'),
                    'transaction_id' => null,
                    'paid_at' => null,
                    'metadata' => (array) ($order['metadata'] ?? []),
                ];
                break;

            case 'ORDER_CANCELLED':
                $result = [
                    'status' => 'cancelled',
                    'payment_id' => $order['id'] ?? null,
                    'order_id' => $order['merchant_order_ext_ref'] ?? ($order['metadata']['order_id'] ?? null),
                    'amount' => ($order['order_amount']['value'] ?? 0) / 100,
                    'currency' => strtoupper($order['order_amount']['currency'] ?? 'EUR'),
                    'transaction_id' => null,
                    'paid_at' => null,
                    'metadata' => (array) ($order['metadata'] ?? []),
                ];
                break;
        }

        return $result;
    }

    /**
     * Verify Revolut webhook signature
     *
     * @param array $payload Raw payload
     * @param array $headers Request headers
     * @return bool
     */
    public function verifySignature(array $payload, array $headers): bool
    {
        if (empty($this->keys['webhook_secret'])) {
            return true;
        }

        $signature = $headers['revolut-signature'] ?? $headers['Revolut-Signature'] ?? null;
        $timestamp = $headers['revolut-request-timestamp'] ?? $headers['Revolut-Request-Timestamp'] ?? null;

        if (!$signature || !$timestamp) {
            return false;
        }

        // Parse signature components
        $signatureParts = [];
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = explode('=', $part, 2);
            $signatureParts[$key] = $value;
        }

        // Build signed payload string
        $payloadString = is_string($payload) ? $payload : json_encode($payload);
        $signedPayload = $timestamp . '.' . $payloadString;

        // Calculate expected signature
        $expectedSignature = hash_hmac('sha256', $signedPayload, $this->keys['webhook_secret']);

        return hash_equals($expectedSignature, $signatureParts['v1'] ?? '');
    }

    /**
     * Get payment status from Revolut
     *
     * @param string $paymentId Revolut order ID
     * @return array Payment status details
     */
    public function getPaymentStatus(string $paymentId): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Revolut is not properly configured');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->keys['api_key'],
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/orders/' . $paymentId);

            if (!$response->successful()) {
                throw new \Exception('Failed to retrieve payment status: ' . $response->body());
            }

            $order = $response->json();

            $statusMap = [
                'PENDING' => 'pending',
                'PROCESSING' => 'pending',
                'AUTHORISED' => 'success',
                'COMPLETED' => 'success',
                'CANCELLED' => 'cancelled',
                'FAILED' => 'failed',
            ];

            return [
                'status' => $statusMap[$order['state']] ?? 'pending',
                'amount' => ($order['order_amount']['value'] ?? 0) / 100,
                'currency' => strtoupper($order['order_amount']['currency'] ?? 'EUR'),
                'paid_at' => in_array($order['state'], ['AUTHORISED', 'COMPLETED'])
                    ? ($order['completed_at'] ?? date('c'))
                    : null,
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to retrieve payment status: {$e->getMessage()}");
        }
    }

    /**
     * Refund a Revolut payment
     *
     * @param string $paymentId Revolut order ID
     * @param float|null $amount Amount to refund (null for full)
     * @param string|null $reason Refund reason
     * @return array Refund result
     */
    public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Revolut is not properly configured');
        }

        try {
            $refundData = [
                'description' => $reason ?? 'Refund',
            ];

            if ($amount !== null) {
                $refundData['amount'] = (int) round($amount * 100);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->keys['api_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($this->baseUrl . '/orders/' . $paymentId . '/refund', $refundData);

            if (!$response->successful()) {
                throw new \Exception('Failed to process refund: ' . $response->body());
            }

            $refund = $response->json();

            return [
                'refund_id' => $refund['id'] ?? $paymentId . '_refund',
                'status' => 'success',
                'amount' => ($refund['refunded_amount']['value'] ?? ($amount ? $amount * 100 : 0)) / 100,
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to process refund: {$e->getMessage()}");
        }
    }

    /**
     * Check if processor is configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->keys['api_key']);
    }

    /**
     * Get processor name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Revolut';
    }

    /**
     * Get public ID for frontend widget
     *
     * @return string|null
     */
    public function getMerchantId(): ?string
    {
        return $this->keys['merchant_id'] ?? null;
    }

    /**
     * Check if in sandbox mode
     *
     * @return bool
     */
    public function isSandbox(): bool
    {
        return $this->config->mode === 'test';
    }
}
