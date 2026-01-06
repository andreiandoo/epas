<?php

namespace App\Services\PaymentProcessors;

use App\Models\TenantPaymentConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * PayPal Payment Processor
 *
 * Integrates with PayPal REST API v2 for payment processing.
 * Supports PayPal Checkout, Credit/Debit Cards, Pay Later options.
 *
 * @see https://developer.paypal.com/docs/api/overview/
 */
class PayPalProcessor implements PaymentProcessorInterface
{
    protected TenantPaymentConfig $config;
    protected array $keys;
    protected string $baseUrl;

    /**
     * PayPal API endpoints
     */
    private const SANDBOX_URL = 'https://api-m.sandbox.paypal.com';
    private const PRODUCTION_URL = 'https://api-m.paypal.com';

    public function __construct(TenantPaymentConfig $config)
    {
        $this->config = $config;
        $this->keys = $config->getActiveKeys();
        $this->baseUrl = $config->mode === 'live' ? self::PRODUCTION_URL : self::SANDBOX_URL;
    }

    /**
     * Get OAuth access token from PayPal
     *
     * @return string Access token
     */
    protected function getAccessToken(): string
    {
        $cacheKey = 'paypal_token_' . $this->config->id;

        return Cache::remember($cacheKey, 3500, function () {
            $response = Http::withBasicAuth(
                $this->keys['client_id'],
                $this->keys['client_secret']
            )->asForm()->post($this->baseUrl . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to obtain PayPal access token: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }

    /**
     * Create a PayPal order/checkout
     *
     * @param array $data Payment data
     * @return array Payment details with redirect URL
     */
    public function createPayment(array $data): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('PayPal is not properly configured');
        }

        $currency = strtoupper($data['currency'] ?? 'EUR');
        $amount = number_format($data['amount'], 2, '.', '');

        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $data['order_id'] ?? uniqid('order_'),
                    'description' => $data['description'] ?? 'Payment',
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $amount,
                    ],
                    'custom_id' => $data['order_id'] ?? null,
                ],
            ],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'payment_method_preference' => 'IMMEDIATE_PAYMENT_REQUIRED',
                        'brand_name' => config('app.name', 'Payment'),
                        'locale' => app()->getLocale() ?? 'en-US',
                        'landing_page' => 'LOGIN',
                        'shipping_preference' => 'NO_SHIPPING',
                        'user_action' => 'PAY_NOW',
                        'return_url' => $data['success_url'],
                        'cancel_url' => $data['cancel_url'],
                    ],
                ],
            ],
        ];

        // Add payer info if provided
        if (!empty($data['customer_email'])) {
            $orderData['payment_source']['paypal']['email_address'] = $data['customer_email'];
        }

        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
                'PayPal-Request-Id' => uniqid('req_'),
            ])->post($this->baseUrl . '/v2/checkout/orders', $orderData);

            if (!$response->successful()) {
                Log::error('PayPal payment creation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Failed to create PayPal payment: ' . ($response->json('message') ?? $response->body()));
            }

            $order = $response->json();

            // Find approval URL
            $approveUrl = null;
            foreach ($order['links'] ?? [] as $link) {
                if ($link['rel'] === 'payer-action') {
                    $approveUrl = $link['href'];
                    break;
                }
            }

            return [
                'payment_id' => $order['id'],
                'redirect_url' => $approveUrl,
                'additional_data' => [
                    'order_id' => $order['id'],
                    'status' => $order['status'],
                    'create_time' => $order['create_time'] ?? null,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('PayPal payment error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Process PayPal webhook callback
     *
     * @param array $payload Webhook payload
     * @param array $headers Request headers
     * @return array Normalized payment result
     */
    public function processCallback(array $payload, array $headers = []): array
    {
        // Verify signature if webhook ID is configured
        if (!empty($this->keys['webhook_id'])) {
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

        $eventType = $payload['event_type'] ?? null;
        $resource = $payload['resource'] ?? [];

        switch ($eventType) {
            case 'CHECKOUT.ORDER.APPROVED':
                // Customer approved, now capture the payment
                $orderId = $resource['id'] ?? null;
                if ($orderId) {
                    try {
                        $captureResult = $this->captureOrder($orderId);
                        if ($captureResult['status'] === 'COMPLETED') {
                            $capture = $captureResult['purchase_units'][0]['payments']['captures'][0] ?? [];
                            $result = [
                                'status' => 'success',
                                'payment_id' => $orderId,
                                'order_id' => $resource['purchase_units'][0]['custom_id'] ?? $resource['purchase_units'][0]['reference_id'] ?? null,
                                'amount' => (float) ($capture['amount']['value'] ?? 0),
                                'currency' => strtoupper($capture['amount']['currency_code'] ?? 'EUR'),
                                'transaction_id' => $capture['id'] ?? $orderId,
                                'paid_at' => $capture['create_time'] ?? date('c'),
                                'metadata' => [],
                            ];
                        }
                    } catch (\Exception $e) {
                        Log::error('PayPal capture failed', ['error' => $e->getMessage()]);
                    }
                }
                break;

            case 'PAYMENT.CAPTURE.COMPLETED':
                $result = [
                    'status' => 'success',
                    'payment_id' => $resource['supplementary_data']['related_ids']['order_id'] ?? $resource['id'],
                    'order_id' => $resource['custom_id'] ?? $resource['invoice_id'] ?? null,
                    'amount' => (float) ($resource['amount']['value'] ?? 0),
                    'currency' => strtoupper($resource['amount']['currency_code'] ?? 'EUR'),
                    'transaction_id' => $resource['id'],
                    'paid_at' => $resource['create_time'] ?? date('c'),
                    'metadata' => [],
                ];
                break;

            case 'PAYMENT.CAPTURE.DENIED':
            case 'PAYMENT.CAPTURE.DECLINED':
                $result = [
                    'status' => 'failed',
                    'payment_id' => $resource['supplementary_data']['related_ids']['order_id'] ?? $resource['id'],
                    'order_id' => $resource['custom_id'] ?? $resource['invoice_id'] ?? null,
                    'amount' => (float) ($resource['amount']['value'] ?? 0),
                    'currency' => strtoupper($resource['amount']['currency_code'] ?? 'EUR'),
                    'transaction_id' => null,
                    'paid_at' => null,
                    'metadata' => [],
                ];
                break;

            case 'CHECKOUT.ORDER.COMPLETED':
                $purchase = $resource['purchase_units'][0] ?? [];
                $capture = $purchase['payments']['captures'][0] ?? [];
                $result = [
                    'status' => 'success',
                    'payment_id' => $resource['id'],
                    'order_id' => $purchase['custom_id'] ?? $purchase['reference_id'] ?? null,
                    'amount' => (float) ($capture['amount']['value'] ?? $purchase['amount']['value'] ?? 0),
                    'currency' => strtoupper($capture['amount']['currency_code'] ?? $purchase['amount']['currency_code'] ?? 'EUR'),
                    'transaction_id' => $capture['id'] ?? $resource['id'],
                    'paid_at' => $resource['update_time'] ?? date('c'),
                    'metadata' => [],
                ];
                break;
        }

        return $result;
    }

    /**
     * Capture an approved PayPal order
     *
     * @param string $orderId PayPal order ID
     * @return array Capture result
     */
    public function captureOrder(string $orderId): array
    {
        $accessToken = $this->getAccessToken();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/v2/checkout/orders/' . $orderId . '/capture', []);

        if (!$response->successful()) {
            throw new \Exception('Failed to capture PayPal order: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Verify PayPal webhook signature
     *
     * @param array $payload Raw payload
     * @param array $headers Request headers
     * @return bool
     */
    public function verifySignature(array $payload, array $headers): bool
    {
        if (empty($this->keys['webhook_id'])) {
            return true;
        }

        try {
            $accessToken = $this->getAccessToken();

            $verificationData = [
                'auth_algo' => $headers['paypal-auth-algo'] ?? $headers['PAYPAL-AUTH-ALGO'] ?? '',
                'cert_url' => $headers['paypal-cert-url'] ?? $headers['PAYPAL-CERT-URL'] ?? '',
                'transmission_id' => $headers['paypal-transmission-id'] ?? $headers['PAYPAL-TRANSMISSION-ID'] ?? '',
                'transmission_sig' => $headers['paypal-transmission-sig'] ?? $headers['PAYPAL-TRANSMISSION-SIG'] ?? '',
                'transmission_time' => $headers['paypal-transmission-time'] ?? $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
                'webhook_id' => $this->keys['webhook_id'],
                'webhook_event' => $payload,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/v1/notifications/verify-webhook-signature', $verificationData);

            if (!$response->successful()) {
                return false;
            }

            return $response->json('verification_status') === 'SUCCESS';
        } catch (\Exception $e) {
            Log::error('PayPal signature verification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get payment status from PayPal
     *
     * @param string $paymentId PayPal order ID
     * @return array Payment status details
     */
    public function getPaymentStatus(string $paymentId): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('PayPal is not properly configured');
        }

        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ])->get($this->baseUrl . '/v2/checkout/orders/' . $paymentId);

            if (!$response->successful()) {
                throw new \Exception('Failed to retrieve payment status: ' . $response->body());
            }

            $order = $response->json();

            $statusMap = [
                'CREATED' => 'pending',
                'SAVED' => 'pending',
                'APPROVED' => 'pending',
                'VOIDED' => 'cancelled',
                'COMPLETED' => 'success',
                'PAYER_ACTION_REQUIRED' => 'pending',
            ];

            $purchaseUnit = $order['purchase_units'][0] ?? [];
            $amount = $purchaseUnit['amount'] ?? [];

            return [
                'status' => $statusMap[$order['status']] ?? 'pending',
                'amount' => (float) ($amount['value'] ?? 0),
                'currency' => strtoupper($amount['currency_code'] ?? 'EUR'),
                'paid_at' => $order['status'] === 'COMPLETED'
                    ? ($order['update_time'] ?? date('c'))
                    : null,
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to retrieve payment status: {$e->getMessage()}");
        }
    }

    /**
     * Refund a PayPal payment
     *
     * @param string $paymentId PayPal capture ID
     * @param float|null $amount Amount to refund (null for full)
     * @param string|null $reason Refund reason
     * @return array Refund result
     */
    public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('PayPal is not properly configured');
        }

        try {
            $accessToken = $this->getAccessToken();

            // First, get the order to find the capture ID
            $orderResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
            ])->get($this->baseUrl . '/v2/checkout/orders/' . $paymentId);

            if (!$orderResponse->successful()) {
                throw new \Exception('Failed to retrieve order for refund');
            }

            $order = $orderResponse->json();
            $captureId = $order['purchase_units'][0]['payments']['captures'][0]['id'] ?? $paymentId;
            $currency = $order['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] ?? 'EUR';

            $refundData = [];
            if ($amount !== null) {
                $refundData['amount'] = [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency_code' => $currency,
                ];
            }
            if ($reason) {
                $refundData['note_to_payer'] = $reason;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/v2/payments/captures/' . $captureId . '/refund', $refundData);

            if (!$response->successful()) {
                throw new \Exception('Failed to process refund: ' . $response->body());
            }

            $refund = $response->json();

            $statusMap = [
                'COMPLETED' => 'success',
                'PENDING' => 'pending',
                'FAILED' => 'failed',
                'CANCELLED' => 'failed',
            ];

            return [
                'refund_id' => $refund['id'],
                'status' => $statusMap[$refund['status']] ?? 'pending',
                'amount' => (float) ($refund['amount']['value'] ?? $amount ?? 0),
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
        return !empty($this->keys['client_id']) && !empty($this->keys['client_secret']);
    }

    /**
     * Get processor name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'PayPal';
    }

    /**
     * Get client ID for frontend SDK
     *
     * @return string|null
     */
    public function getClientId(): ?string
    {
        return $this->keys['client_id'] ?? null;
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
