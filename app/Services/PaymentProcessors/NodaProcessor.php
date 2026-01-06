<?php

namespace App\Services\PaymentProcessors;

use App\Models\TenantPaymentConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Noda Open Banking Payment Processor
 *
 * Integrates with Noda's Open Banking API for account-to-account payments.
 * Supports SEPA Instant (EUR) and local instant payments including Romania's Plăți Instant (RON).
 * Covers 2,000+ banks across 28 European countries.
 *
 * Key benefits:
 * - Ultra-low fees (~0.1% vs 1.5-2.5% for cards)
 * - Instant settlement (10 seconds)
 * - No chargebacks
 * - PSD2 compliant
 *
 * @see https://docs.noda.live
 * @see https://noda.live/open-banking
 */
class NodaProcessor implements PaymentProcessorInterface
{
    protected TenantPaymentConfig $config;
    protected array $keys;
    protected string $baseUrl;

    /**
     * Noda API endpoints
     */
    private const SANDBOX_URL = 'https://api.sandbox.noda.live/api/v1';
    private const PRODUCTION_URL = 'https://api.noda.live/api/v1';

    /**
     * Sandbox test API key
     */
    private const SANDBOX_API_KEY = '24d0034-5a83-47d5-afa0-cca47298c516';

    /**
     * Supported currencies
     */
    private const SUPPORTED_CURRENCIES = [
        'EUR', // SEPA countries
        'RON', // Romania (Plăți Instant)
        'GBP', // UK (Faster Payments)
        'PLN', // Poland
        'CZK', // Czech Republic
        'BGN', // Bulgaria
        'HUF', // Hungary
        'SEK', // Sweden
        'DKK', // Denmark
        'NOK', // Norway
        'CHF', // Switzerland
    ];

    public function __construct(TenantPaymentConfig $config)
    {
        $this->config = $config;
        $this->keys = $config->getActiveKeys();

        $isSandbox = ($config->additional_config['sandbox'] ?? true) === true;
        $this->baseUrl = $isSandbox ? self::SANDBOX_URL : self::PRODUCTION_URL;
    }

    /**
     * Create an Open Banking payment session
     *
     * Flow:
     * 1. Customer selects "Pay by Bank"
     * 2. Redirect to Noda's bank selection page
     * 3. Customer authenticates with their bank (SCA)
     * 4. Payment initiated via instant payment rails
     * 5. Webhook confirms payment (~10 seconds)
     *
     * @param array $data Payment data
     * @return array Payment details with redirect URL
     */
    public function createPayment(array $data): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Noda Open Banking is not properly configured');
        }

        $currency = strtoupper($data['currency'] ?? 'EUR');

        if (!in_array($currency, self::SUPPORTED_CURRENCIES)) {
            throw new \Exception("Currency {$currency} is not supported by Noda Open Banking");
        }

        // Noda uses minor units (cents/bani)
        $amountInMinorUnits = (int) round($data['amount'] * 100);

        $paymentData = [
            'amount' => $amountInMinorUnits,
            'currency' => $currency,
            'description' => $data['description'] ?? 'Payment',
            'externalId' => $data['order_id'] ?? uniqid('noda_'),
            'shopId' => $this->keys['shop_id'] ?? null,
            'customerId' => $data['customer_email'] ?? null,
            'email' => $data['customer_email'] ?? null,
            'returnUrl' => $data['success_url'] ?? null,
            'webhookUrl' => $this->getWebhookUrl(),
            'metadata' => array_merge(
                [
                    'order_id' => $data['order_id'] ?? null,
                    'customer_name' => $data['customer_name'] ?? null,
                    'tenant_id' => $this->config->tenant_id,
                ],
                $data['metadata'] ?? []
            ),
        ];

        // Add country hint for better bank filtering
        if (!empty($data['country'])) {
            $paymentData['country'] = strtoupper($data['country']);
        } else {
            // Default to Romania for RON, or detect from currency
            $paymentData['country'] = $this->getCountryFromCurrency($currency);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->keys['api_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Api-Version' => '2024-01',
            ])->post($this->baseUrl . '/payments', $paymentData);

            if (!$response->successful()) {
                Log::error('Noda payment creation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'currency' => $currency,
                ]);
                throw new \Exception('Failed to create Noda payment: ' . ($response->json('message') ?? $response->json('error') ?? 'Unknown error'));
            }

            $payment = $response->json();

            Log::info('Noda payment created', [
                'payment_id' => $payment['id'] ?? $payment['paymentId'] ?? null,
                'currency' => $currency,
                'amount' => $data['amount'],
            ]);

            return [
                'payment_id' => $payment['id'] ?? $payment['paymentId'],
                'redirect_url' => $payment['url'] ?? $payment['checkoutUrl'] ?? $payment['redirectUrl'],
                'additional_data' => [
                    'payment_id' => $payment['id'] ?? $payment['paymentId'],
                    'status' => $payment['status'] ?? 'PENDING',
                    'external_id' => $paymentData['externalId'],
                    'currency' => $currency,
                    'payment_method' => 'open_banking',
                    'instant_payment' => true,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Noda payment error', [
                'error' => $e->getMessage(),
                'currency' => $currency,
            ]);
            throw $e;
        }
    }

    /**
     * Process Noda webhook callback
     *
     * Noda sends webhooks for payment status updates:
     * - PROCESSING: Payment initiated, awaiting bank confirmation
     * - DONE/COMPLETED: Payment successful - release tickets!
     * - FAILED: Payment failed
     * - CANCELLED: User cancelled
     *
     * @param array $payload Webhook payload
     * @param array $headers Request headers
     * @return array Normalized payment result
     */
    public function processCallback(array $payload, array $headers = []): array
    {
        // Verify signature if configured
        if (!empty($this->keys['signature_key'])) {
            if (!$this->verifySignature($payload, $headers)) {
                throw new \Exception('Invalid Noda webhook signature');
            }
        }

        $status = strtoupper($payload['status'] ?? $payload['paymentStatus'] ?? 'UNKNOWN');
        $paymentId = $payload['id'] ?? $payload['paymentId'] ?? null;
        $externalId = $payload['externalId'] ?? $payload['merchantOrderId'] ?? null;

        // Extract amount (convert from minor units)
        $amountMinor = $payload['amount'] ?? $payload['paymentAmount'] ?? 0;
        $amount = is_numeric($amountMinor) ? $amountMinor / 100 : 0;

        $currency = strtoupper($payload['currency'] ?? 'EUR');
        $metadata = $payload['metadata'] ?? [];

        // Map Noda status to our standard statuses
        $statusMap = [
            'PENDING' => 'pending',
            'PROCESSING' => 'pending',
            'CREATED' => 'pending',
            'IN_PROGRESS' => 'pending',
            'DONE' => 'success',
            'COMPLETED' => 'success',
            'SUCCESS' => 'success',
            'PAID' => 'success',
            'FAILED' => 'failed',
            'ERROR' => 'failed',
            'REJECTED' => 'failed',
            'CANCELLED' => 'cancelled',
            'CANCELED' => 'cancelled',
            'EXPIRED' => 'cancelled',
        ];

        $normalizedStatus = $statusMap[$status] ?? 'pending';

        Log::info('Noda webhook processed', [
            'payment_id' => $paymentId,
            'status' => $status,
            'normalized_status' => $normalizedStatus,
            'amount' => $amount,
            'currency' => $currency,
        ]);

        return [
            'status' => $normalizedStatus,
            'payment_id' => $paymentId,
            'order_id' => $externalId ?? ($metadata['order_id'] ?? null),
            'amount' => $amount,
            'currency' => $currency,
            'transaction_id' => $payload['transactionId'] ?? $payload['bankTransactionId'] ?? $paymentId,
            'paid_at' => $normalizedStatus === 'success'
                ? ($payload['completedAt'] ?? $payload['paidAt'] ?? date('c'))
                : null,
            'metadata' => array_merge($metadata, [
                'bank_name' => $payload['bankName'] ?? null,
                'payment_method' => 'open_banking',
                'instant_payment' => true,
            ]),
        ];
    }

    /**
     * Verify Noda webhook signature
     *
     * Noda uses HMAC-SHA256 for webhook signatures
     *
     * @param array $payload Raw payload
     * @param array $headers Request headers
     * @return bool
     */
    public function verifySignature(array $payload, array $headers): bool
    {
        if (empty($this->keys['signature_key'])) {
            return true; // Skip verification if no key configured
        }

        // Get signature from headers (various possible header names)
        $signature = $headers['x-noda-signature']
            ?? $headers['X-Noda-Signature']
            ?? $headers['noda-signature']
            ?? $headers['Noda-Signature']
            ?? $headers['x-signature']
            ?? $headers['X-Signature']
            ?? null;

        if (!$signature) {
            Log::warning('Noda webhook missing signature header');
            return false;
        }

        // Build payload string for signature
        $payloadString = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_SLASHES);

        // Calculate expected signature
        $expectedSignature = hash_hmac('sha256', $payloadString, $this->keys['signature_key']);

        // Compare signatures (timing-safe)
        if (hash_equals($expectedSignature, $signature)) {
            return true;
        }

        // Try with base64 encoded signature
        if (hash_equals(base64_encode(hex2bin($expectedSignature)), $signature)) {
            return true;
        }

        Log::warning('Noda webhook signature mismatch', [
            'received' => substr($signature, 0, 20) . '...',
        ]);

        return false;
    }

    /**
     * Get payment status from Noda
     *
     * @param string $paymentId Noda payment ID
     * @return array Payment status details
     */
    public function getPaymentStatus(string $paymentId): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Noda Open Banking is not properly configured');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->keys['api_key'],
                'Accept' => 'application/json',
                'X-Api-Version' => '2024-01',
            ])->get($this->baseUrl . '/payments/' . $paymentId);

            if (!$response->successful()) {
                throw new \Exception('Failed to retrieve payment status: ' . $response->body());
            }

            $payment = $response->json();

            $statusMap = [
                'PENDING' => 'pending',
                'PROCESSING' => 'pending',
                'CREATED' => 'pending',
                'DONE' => 'success',
                'COMPLETED' => 'success',
                'SUCCESS' => 'success',
                'PAID' => 'success',
                'FAILED' => 'failed',
                'CANCELLED' => 'cancelled',
                'CANCELED' => 'cancelled',
            ];

            $status = strtoupper($payment['status'] ?? 'PENDING');
            $amount = ($payment['amount'] ?? 0) / 100;

            return [
                'status' => $statusMap[$status] ?? 'pending',
                'amount' => $amount,
                'currency' => strtoupper($payment['currency'] ?? 'EUR'),
                'paid_at' => in_array($status, ['DONE', 'COMPLETED', 'SUCCESS', 'PAID'])
                    ? ($payment['completedAt'] ?? $payment['paidAt'] ?? date('c'))
                    : null,
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to retrieve payment status: {$e->getMessage()}");
        }
    }

    /**
     * Refund a Noda payment
     *
     * Note: Open banking refunds may require manual processing
     * depending on the bank and payment rails used.
     *
     * @param string $paymentId Noda payment ID
     * @param float|null $amount Amount to refund (null for full)
     * @param string|null $reason Refund reason
     * @return array Refund result
     */
    public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Noda Open Banking is not properly configured');
        }

        try {
            $refundData = [
                'reason' => $reason ?? 'Customer requested refund',
            ];

            if ($amount !== null) {
                $refundData['amount'] = (int) round($amount * 100);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->keys['api_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Api-Version' => '2024-01',
            ])->post($this->baseUrl . '/payments/' . $paymentId . '/refund', $refundData);

            if (!$response->successful()) {
                // Open banking refunds might not be supported for all banks
                $error = $response->json('message') ?? $response->json('error') ?? 'Refund not supported';
                throw new \Exception('Failed to process refund: ' . $error);
            }

            $refund = $response->json();

            return [
                'refund_id' => $refund['id'] ?? $refund['refundId'] ?? $paymentId . '_refund',
                'status' => 'success',
                'amount' => ($refund['amount'] ?? ($amount ? $amount * 100 : 0)) / 100,
            ];
        } catch (\Exception $e) {
            Log::warning('Noda refund failed', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
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
        return 'Noda Open Banking';
    }

    /**
     * Get shop ID
     *
     * @return string|null
     */
    public function getShopId(): ?string
    {
        return $this->keys['shop_id'] ?? null;
    }

    /**
     * Check if in sandbox mode
     *
     * @return bool
     */
    public function isSandbox(): bool
    {
        return ($this->config->additional_config['sandbox'] ?? true) === true;
    }

    /**
     * Get supported currencies
     *
     * @return array
     */
    public static function getSupportedCurrencies(): array
    {
        return self::SUPPORTED_CURRENCIES;
    }

    /**
     * Get webhook URL for this tenant
     *
     * @return string
     */
    protected function getWebhookUrl(): string
    {
        $tenant = $this->config->tenant;
        if ($tenant && $tenant->domain) {
            return "https://{$tenant->domain}/payment/webhook/noda";
        }

        return config('app.url') . '/payment/webhook/noda';
    }

    /**
     * Get default country from currency
     *
     * @param string $currency
     * @return string
     */
    protected function getCountryFromCurrency(string $currency): string
    {
        $currencyCountryMap = [
            'RON' => 'RO', // Romania
            'GBP' => 'GB', // United Kingdom
            'PLN' => 'PL', // Poland
            'CZK' => 'CZ', // Czech Republic
            'BGN' => 'BG', // Bulgaria
            'HUF' => 'HU', // Hungary
            'SEK' => 'SE', // Sweden
            'DKK' => 'DK', // Denmark
            'NOK' => 'NO', // Norway
            'CHF' => 'CH', // Switzerland
            'EUR' => 'DE', // Default to Germany for EUR
        ];

        return $currencyCountryMap[$currency] ?? 'DE';
    }

    /**
     * Get available banks for a country
     *
     * @param string $countryCode ISO country code
     * @param string $currency Currency code
     * @return array List of available banks
     */
    public function getAvailableBanks(string $countryCode, string $currency = 'EUR'): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Noda Open Banking is not properly configured');
        }

        $cacheKey = "noda_banks_{$countryCode}_{$currency}";

        return Cache::remember($cacheKey, 3600, function () use ($countryCode, $currency) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->keys['api_key'],
                    'Accept' => 'application/json',
                    'X-Api-Version' => '2024-01',
                ])->get($this->baseUrl . '/banks', [
                    'country' => $countryCode,
                    'currency' => $currency,
                ]);

                if ($response->successful()) {
                    return $response->json('banks') ?? $response->json() ?? [];
                }

                return [];
            } catch (\Exception $e) {
                Log::warning('Failed to fetch Noda banks', [
                    'country' => $countryCode,
                    'error' => $e->getMessage(),
                ]);
                return [];
            }
        });
    }

    /**
     * Get Romanian banks supporting instant payments
     *
     * @return array
     */
    public function getRomanianBanks(): array
    {
        return $this->getAvailableBanks('RO', 'RON');
    }
}
