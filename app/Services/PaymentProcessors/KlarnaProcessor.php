<?php

namespace App\Services\PaymentProcessors;

use App\Models\TenantPaymentConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klarna Payment Processor
 *
 * Integrates with Klarna Payments API for Buy Now Pay Later solutions.
 * Supports Pay in 3, Pay in 30 days, and financing options.
 *
 * @see https://docs.klarna.com/klarna-payments/
 */
class KlarnaProcessor implements PaymentProcessorInterface
{
    protected TenantPaymentConfig $config;
    protected array $keys;
    protected string $baseUrl;

    /**
     * Klarna API endpoints by region
     */
    private const ENDPOINTS = [
        'eu' => [
            'sandbox' => 'https://api.playground.klarna.com',
            'production' => 'https://api.klarna.com',
        ],
        'na' => [
            'sandbox' => 'https://api-na.playground.klarna.com',
            'production' => 'https://api-na.klarna.com',
        ],
        'oc' => [
            'sandbox' => 'https://api-oc.playground.klarna.com',
            'production' => 'https://api-oc.klarna.com',
        ],
    ];

    public function __construct(TenantPaymentConfig $config)
    {
        $this->config = $config;
        $this->keys = $config->getActiveKeys();

        $region = $this->keys['region'] ?? 'eu';
        $mode = $config->mode === 'live' ? 'production' : 'sandbox';
        $this->baseUrl = self::ENDPOINTS[$region][$mode] ?? self::ENDPOINTS['eu'][$mode];
    }

    /**
     * Create a Klarna payment session
     *
     * @param array $data Payment data
     * @return array Payment details with redirect URL
     */
    public function createPayment(array $data): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Klarna is not properly configured');
        }

        $currency = strtoupper($data['currency'] ?? 'EUR');
        // Klarna uses minor units (cents)
        $amountInCents = (int) round($data['amount'] * 100);

        // Build order lines
        $orderLines = [
            [
                'type' => 'physical', // or 'digital', 'shipping_fee', 'discount'
                'reference' => $data['order_id'] ?? uniqid('item_'),
                'name' => $data['description'] ?? 'Payment',
                'quantity' => 1,
                'unit_price' => $amountInCents,
                'total_amount' => $amountInCents,
                'tax_rate' => 0,
                'total_tax_amount' => 0,
            ],
        ];

        $sessionData = [
            'purchase_country' => $this->getPurchaseCountry($currency),
            'purchase_currency' => $currency,
            'locale' => $this->getLocale(),
            'order_amount' => $amountInCents,
            'order_tax_amount' => 0,
            'order_lines' => $orderLines,
            'merchant_reference1' => $data['order_id'] ?? null,
            'merchant_urls' => [
                'confirmation' => $data['success_url'],
                'notification' => $data['webhook_url'] ?? ($data['success_url'] . '?notification=true'),
            ],
        ];

        // Add billing address if customer info provided
        if (!empty($data['customer_email'])) {
            $sessionData['billing_address'] = [
                'email' => $data['customer_email'],
            ];
            if (!empty($data['customer_name'])) {
                $nameParts = explode(' ', $data['customer_name'], 2);
                $sessionData['billing_address']['given_name'] = $nameParts[0];
                $sessionData['billing_address']['family_name'] = $nameParts[1] ?? '';
            }
        }

        try {
            // First create a payment session
            $response = Http::withBasicAuth(
                $this->keys['api_username'],
                $this->keys['api_password']
            )->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/payments/v1/sessions', $sessionData);

            if (!$response->successful()) {
                Log::error('Klarna session creation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \Exception('Failed to create Klarna session: ' . ($response->json('error_messages')[0] ?? $response->body()));
            }

            $session = $response->json();

            // Now create a Hosted Payment Page order for redirect
            $hppData = array_merge($sessionData, [
                'merchant_urls' => [
                    'success' => $data['success_url'],
                    'cancel' => $data['cancel_url'],
                    'back' => $data['cancel_url'],
                    'failure' => $data['cancel_url'],
                    'status_update' => $data['webhook_url'] ?? $data['success_url'],
                ],
            ]);

            $hppResponse = Http::withBasicAuth(
                $this->keys['api_username'],
                $this->keys['api_password']
            )->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/hpp/v1/sessions', $hppData);

            if (!$hppResponse->successful()) {
                Log::error('Klarna HPP creation failed', [
                    'status' => $hppResponse->status(),
                    'body' => $hppResponse->body(),
                ]);
                throw new \Exception('Failed to create Klarna payment page: ' . ($hppResponse->json('error_messages')[0] ?? $hppResponse->body()));
            }

            $hpp = $hppResponse->json();

            return [
                'payment_id' => $session['session_id'],
                'redirect_url' => $hpp['redirect_url'],
                'additional_data' => [
                    'session_id' => $session['session_id'],
                    'client_token' => $session['client_token'],
                    'hpp_session_id' => $hpp['session_id'],
                    'payment_method_categories' => $session['payment_method_categories'] ?? [],
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Klarna payment error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Process Klarna webhook/callback
     *
     * @param array $payload Webhook payload
     * @param array $headers Request headers
     * @return array Normalized payment result
     */
    public function processCallback(array $payload, array $headers = []): array
    {
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

        $eventType = $payload['event_type'] ?? $payload['status'] ?? null;

        // Handle different Klarna callback formats
        if (isset($payload['order_id'])) {
            // HPP callback or Order callback
            $orderId = $payload['order_id'];

            try {
                $orderDetails = $this->getOrderDetails($orderId);

                $statusMap = [
                    'AUTHORIZED' => 'success',
                    'CAPTURED' => 'success',
                    'PART_CAPTURED' => 'success',
                    'CANCELLED' => 'cancelled',
                    'EXPIRED' => 'cancelled',
                    'CLOSED' => 'success',
                ];

                $result = [
                    'status' => $statusMap[$orderDetails['status']] ?? 'pending',
                    'payment_id' => $orderId,
                    'order_id' => $orderDetails['merchant_reference1'] ?? null,
                    'amount' => ($orderDetails['order_amount'] ?? 0) / 100,
                    'currency' => strtoupper($orderDetails['purchase_currency'] ?? 'EUR'),
                    'transaction_id' => $orderId,
                    'paid_at' => in_array($orderDetails['status'], ['AUTHORIZED', 'CAPTURED', 'PART_CAPTURED', 'CLOSED'])
                        ? date('c')
                        : null,
                    'metadata' => [],
                ];

                // Auto-capture if authorized (you may want this configurable)
                if ($orderDetails['status'] === 'AUTHORIZED') {
                    $this->captureOrder($orderId, $orderDetails['order_amount']);
                }
            } catch (\Exception $e) {
                Log::error('Klarna order lookup failed', ['error' => $e->getMessage()]);
            }
        } elseif (isset($payload['authorization_token'])) {
            // Payment session authorized - create order
            try {
                $orderResult = $this->createOrder($payload);
                $result = [
                    'status' => 'success',
                    'payment_id' => $orderResult['order_id'],
                    'order_id' => $payload['merchant_reference1'] ?? null,
                    'amount' => ($payload['order_amount'] ?? 0) / 100,
                    'currency' => strtoupper($payload['purchase_currency'] ?? 'EUR'),
                    'transaction_id' => $orderResult['order_id'],
                    'paid_at' => date('c'),
                    'metadata' => [],
                ];
            } catch (\Exception $e) {
                Log::error('Klarna order creation failed', ['error' => $e->getMessage()]);
            }
        }

        return $result;
    }

    /**
     * Create a Klarna order from authorization
     *
     * @param array $authData Authorization data
     * @return array Order result
     */
    protected function createOrder(array $authData): array
    {
        $response = Http::withBasicAuth(
            $this->keys['api_username'],
            $this->keys['api_password']
        )->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/payments/v1/authorizations/' . $authData['authorization_token'] . '/order', [
            'purchase_country' => $authData['purchase_country'] ?? 'DE',
            'purchase_currency' => $authData['purchase_currency'] ?? 'EUR',
            'order_amount' => $authData['order_amount'],
            'order_tax_amount' => $authData['order_tax_amount'] ?? 0,
            'order_lines' => $authData['order_lines'] ?? [],
            'merchant_reference1' => $authData['merchant_reference1'] ?? null,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create Klarna order: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get order details from Klarna
     *
     * @param string $orderId Klarna order ID
     * @return array Order details
     */
    protected function getOrderDetails(string $orderId): array
    {
        $response = Http::withBasicAuth(
            $this->keys['api_username'],
            $this->keys['api_password']
        )->get($this->baseUrl . '/ordermanagement/v1/orders/' . $orderId);

        if (!$response->successful()) {
            throw new \Exception('Failed to get Klarna order: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Capture a Klarna order
     *
     * @param string $orderId Klarna order ID
     * @param int $amount Amount in minor units
     * @return array Capture result
     */
    protected function captureOrder(string $orderId, int $amount): array
    {
        $response = Http::withBasicAuth(
            $this->keys['api_username'],
            $this->keys['api_password']
        )->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl . '/ordermanagement/v1/orders/' . $orderId . '/captures', [
            'captured_amount' => $amount,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to capture Klarna order: ' . $response->body());
        }

        return $response->json() ?: ['status' => 'captured'];
    }

    /**
     * Verify Klarna webhook signature (Klarna uses basic auth, not signatures)
     *
     * @param array $payload Raw payload
     * @param array $headers Request headers
     * @return bool
     */
    public function verifySignature(array $payload, array $headers): bool
    {
        // Klarna primarily uses basic auth for webhook endpoints
        // You would typically verify by checking your endpoint is protected
        // or by validating the order via API call
        return true;
    }

    /**
     * Get payment status from Klarna
     *
     * @param string $paymentId Klarna order ID
     * @return array Payment status details
     */
    public function getPaymentStatus(string $paymentId): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Klarna is not properly configured');
        }

        try {
            $orderDetails = $this->getOrderDetails($paymentId);

            $statusMap = [
                'AUTHORIZED' => 'success',
                'CAPTURED' => 'success',
                'PART_CAPTURED' => 'success',
                'CANCELLED' => 'cancelled',
                'EXPIRED' => 'cancelled',
                'CLOSED' => 'success',
                'PENDING' => 'pending',
            ];

            return [
                'status' => $statusMap[$orderDetails['status']] ?? 'pending',
                'amount' => ($orderDetails['order_amount'] ?? 0) / 100,
                'currency' => strtoupper($orderDetails['purchase_currency'] ?? 'EUR'),
                'paid_at' => in_array($orderDetails['status'], ['AUTHORIZED', 'CAPTURED', 'PART_CAPTURED', 'CLOSED'])
                    ? date('c')
                    : null,
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to retrieve payment status: {$e->getMessage()}");
        }
    }

    /**
     * Refund a Klarna payment
     *
     * @param string $paymentId Klarna order ID
     * @param float|null $amount Amount to refund (null for full)
     * @param string|null $reason Refund reason
     * @return array Refund result
     */
    public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Klarna is not properly configured');
        }

        try {
            // Get order to determine refund amount if not specified
            $orderDetails = $this->getOrderDetails($paymentId);
            $refundAmount = $amount !== null
                ? (int) round($amount * 100)
                : $orderDetails['captured_amount'] ?? $orderDetails['order_amount'];

            $refundData = [
                'refunded_amount' => $refundAmount,
            ];

            if ($reason) {
                $refundData['description'] = $reason;
            }

            $response = Http::withBasicAuth(
                $this->keys['api_username'],
                $this->keys['api_password']
            )->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/ordermanagement/v1/orders/' . $paymentId . '/refunds', $refundData);

            if (!$response->successful()) {
                throw new \Exception('Failed to process refund: ' . $response->body());
            }

            $refund = $response->json() ?: [];

            return [
                'refund_id' => $refund['refund_id'] ?? $paymentId . '_refund',
                'status' => 'success',
                'amount' => $refundAmount / 100,
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
        return !empty($this->keys['api_username']) && !empty($this->keys['api_password']);
    }

    /**
     * Get processor name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'Klarna';
    }

    /**
     * Get purchase country based on currency
     *
     * @param string $currency
     * @return string Country code
     */
    protected function getPurchaseCountry(string $currency): string
    {
        return match ($currency) {
            'EUR' => 'DE',
            'SEK' => 'SE',
            'NOK' => 'NO',
            'DKK' => 'DK',
            'GBP' => 'GB',
            'USD' => 'US',
            'CHF' => 'CH',
            'PLN' => 'PL',
            'AUD' => 'AU',
            'NZD' => 'NZ',
            'CAD' => 'CA',
            default => 'DE',
        };
    }

    /**
     * Get locale string
     *
     * @return string
     */
    protected function getLocale(): string
    {
        $locale = app()->getLocale() ?? 'en';
        $country = $this->getPurchaseCountry($this->config->tenant?->currency ?? 'EUR');

        return $locale . '-' . $country;
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

    /**
     * Get available Klarna payment methods
     *
     * @return array
     */
    public function getPaymentMethods(): array
    {
        return [
            'pay_later' => 'Pay in 30 days',
            'pay_over_time' => 'Financing',
            'pay_now' => 'Pay Now',
            'direct_debit' => 'Direct Debit',
            'direct_bank_transfer' => 'Bank Transfer',
        ];
    }
}
