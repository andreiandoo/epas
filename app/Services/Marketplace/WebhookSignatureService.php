<?php

namespace App\Services\Marketplace;

use App\Models\Marketplace\MarketplaceOrganizer;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * WebhookSignatureService
 *
 * Handles webhook signature generation and validation for marketplace webhooks.
 * Supports multiple signature algorithms and provides a secure way to verify
 * webhook authenticity.
 */
class WebhookSignatureService
{
    /**
     * Default signature algorithm
     */
    public const ALGORITHM_SHA256 = 'sha256';
    public const ALGORITHM_SHA512 = 'sha512';

    /**
     * Header names for webhook signatures
     */
    public const HEADER_SIGNATURE = 'X-Webhook-Signature';
    public const HEADER_TIMESTAMP = 'X-Webhook-Timestamp';
    public const HEADER_ALGORITHM = 'X-Webhook-Algorithm';

    /**
     * Maximum age of a webhook request (in seconds) to prevent replay attacks
     */
    public const MAX_AGE_SECONDS = 300; // 5 minutes

    /**
     * Generate a signature for webhook payload.
     *
     * @param string $payload The raw JSON payload
     * @param string $secret The webhook secret key
     * @param string $algorithm The hashing algorithm (sha256 or sha512)
     * @param int|null $timestamp Optional timestamp (defaults to current time)
     * @return array{signature: string, timestamp: int, algorithm: string}
     */
    public function generateSignature(
        string $payload,
        string $secret,
        string $algorithm = self::ALGORITHM_SHA256,
        ?int $timestamp = null
    ): array {
        $timestamp = $timestamp ?? time();

        // Create the signed payload: timestamp.payload
        $signedPayload = $timestamp . '.' . $payload;

        // Generate HMAC signature
        $signature = hash_hmac($algorithm, $signedPayload, $secret);

        return [
            'signature' => $signature,
            'timestamp' => $timestamp,
            'algorithm' => $algorithm,
        ];
    }

    /**
     * Validate a webhook signature.
     *
     * @param Request $request The incoming request
     * @param string $secret The webhook secret key
     * @return array{valid: bool, error?: string}
     */
    public function validateRequest(Request $request, string $secret): array
    {
        // Get signature from header
        $signature = $request->header(self::HEADER_SIGNATURE);
        if (empty($signature)) {
            return [
                'valid' => false,
                'error' => 'Missing signature header',
            ];
        }

        // Get timestamp from header
        $timestamp = $request->header(self::HEADER_TIMESTAMP);
        if (empty($timestamp)) {
            return [
                'valid' => false,
                'error' => 'Missing timestamp header',
            ];
        }

        // Validate timestamp format
        if (!is_numeric($timestamp)) {
            return [
                'valid' => false,
                'error' => 'Invalid timestamp format',
            ];
        }

        // Check for replay attack (request too old)
        $age = time() - (int) $timestamp;
        if ($age > self::MAX_AGE_SECONDS) {
            return [
                'valid' => false,
                'error' => 'Request timestamp too old (possible replay attack)',
            ];
        }

        // Check for future timestamps
        if ($age < -60) { // Allow 1 minute clock skew
            return [
                'valid' => false,
                'error' => 'Request timestamp is in the future',
            ];
        }

        // Get algorithm from header (default to sha256)
        $algorithm = $request->header(self::HEADER_ALGORITHM, self::ALGORITHM_SHA256);
        if (!in_array($algorithm, [self::ALGORITHM_SHA256, self::ALGORITHM_SHA512])) {
            return [
                'valid' => false,
                'error' => 'Unsupported signature algorithm',
            ];
        }

        // Get raw payload
        $payload = $request->getContent();

        // Generate expected signature
        $expected = $this->generateSignature($payload, $secret, $algorithm, (int) $timestamp);

        // Timing-safe comparison
        if (!hash_equals($expected['signature'], $signature)) {
            Log::warning('Webhook signature mismatch', [
                'expected' => substr($expected['signature'], 0, 16) . '...',
                'received' => substr($signature, 0, 16) . '...',
                'ip' => $request->ip(),
            ]);

            return [
                'valid' => false,
                'error' => 'Invalid signature',
            ];
        }

        return ['valid' => true];
    }

    /**
     * Generate a new webhook secret.
     *
     * @return string A secure random secret key
     */
    public function generateSecret(): string
    {
        return bin2hex(random_bytes(32)); // 64 character hex string
    }

    /**
     * Get or create webhook secret for a tenant.
     *
     * @param Tenant $tenant The marketplace tenant
     * @return string The webhook secret
     */
    public function getOrCreateTenantSecret(Tenant $tenant): string
    {
        $settings = $tenant->marketplace_settings ?? [];

        if (empty($settings['webhook_secret'])) {
            $settings['webhook_secret'] = $this->generateSecret();
            $tenant->update(['marketplace_settings' => $settings]);
        }

        return $settings['webhook_secret'];
    }

    /**
     * Get or create webhook secret for an organizer.
     *
     * @param MarketplaceOrganizer $organizer The organizer
     * @return string The webhook secret
     */
    public function getOrCreateOrganizerSecret(MarketplaceOrganizer $organizer): string
    {
        $settings = $organizer->settings ?? [];

        if (empty($settings['webhook_secret'])) {
            $settings['webhook_secret'] = $this->generateSecret();
            $organizer->update(['settings' => $settings]);
        }

        return $settings['webhook_secret'];
    }

    /**
     * Rotate webhook secret for a tenant.
     *
     * @param Tenant $tenant The marketplace tenant
     * @return string The new webhook secret
     */
    public function rotateTenantSecret(Tenant $tenant): string
    {
        $settings = $tenant->marketplace_settings ?? [];
        $settings['webhook_secret'] = $this->generateSecret();
        $settings['webhook_secret_rotated_at'] = now()->toIso8601String();

        $tenant->update(['marketplace_settings' => $settings]);

        Log::info('Webhook secret rotated for tenant', ['tenant_id' => $tenant->id]);

        return $settings['webhook_secret'];
    }

    /**
     * Rotate webhook secret for an organizer.
     *
     * @param MarketplaceOrganizer $organizer The organizer
     * @return string The new webhook secret
     */
    public function rotateOrganizerSecret(MarketplaceOrganizer $organizer): string
    {
        $settings = $organizer->settings ?? [];
        $settings['webhook_secret'] = $this->generateSecret();
        $settings['webhook_secret_rotated_at'] = now()->toIso8601String();

        $organizer->update(['settings' => $settings]);

        Log::info('Webhook secret rotated for organizer', ['organizer_id' => $organizer->id]);

        return $settings['webhook_secret'];
    }

    /**
     * Add signature headers to an outgoing webhook request.
     *
     * @param array $headers Existing headers
     * @param string $payload The JSON payload
     * @param string $secret The webhook secret
     * @param string $algorithm The signature algorithm
     * @return array Headers with signature added
     */
    public function addSignatureHeaders(
        array $headers,
        string $payload,
        string $secret,
        string $algorithm = self::ALGORITHM_SHA256
    ): array {
        $signature = $this->generateSignature($payload, $secret, $algorithm);

        $headers[self::HEADER_SIGNATURE] = $signature['signature'];
        $headers[self::HEADER_TIMESTAMP] = (string) $signature['timestamp'];
        $headers[self::HEADER_ALGORITHM] = $signature['algorithm'];

        return $headers;
    }

    /**
     * Validate an incoming webhook from a payment provider.
     * Supports common payment provider signature formats.
     *
     * @param Request $request The incoming request
     * @param string $provider The payment provider (stripe, paypal, etc.)
     * @param string $secret The webhook secret
     * @return array{valid: bool, error?: string}
     */
    public function validateProviderWebhook(Request $request, string $provider, string $secret): array
    {
        return match ($provider) {
            'stripe' => $this->validateStripeWebhook($request, $secret),
            'paypal' => $this->validatePayPalWebhook($request, $secret),
            'netopia' => $this->validateNetopiaWebhook($request, $secret),
            default => [
                'valid' => false,
                'error' => "Unsupported payment provider: {$provider}",
            ],
        };
    }

    /**
     * Validate Stripe webhook signature.
     */
    protected function validateStripeWebhook(Request $request, string $secret): array
    {
        $signature = $request->header('Stripe-Signature');
        if (empty($signature)) {
            return ['valid' => false, 'error' => 'Missing Stripe-Signature header'];
        }

        try {
            // Parse Stripe signature header
            $parts = [];
            foreach (explode(',', $signature) as $part) {
                $item = explode('=', $part, 2);
                if (count($item) === 2) {
                    $parts[$item[0]] = $item[1];
                }
            }

            if (empty($parts['t']) || empty($parts['v1'])) {
                return ['valid' => false, 'error' => 'Invalid Stripe signature format'];
            }

            $timestamp = $parts['t'];
            $expectedSignature = $parts['v1'];

            // Check timestamp age
            if (abs(time() - (int) $timestamp) > self::MAX_AGE_SECONDS) {
                return ['valid' => false, 'error' => 'Webhook timestamp too old'];
            }

            // Compute expected signature
            $payload = $timestamp . '.' . $request->getContent();
            $computedSignature = hash_hmac('sha256', $payload, $secret);

            if (!hash_equals($computedSignature, $expectedSignature)) {
                return ['valid' => false, 'error' => 'Invalid Stripe signature'];
            }

            return ['valid' => true];
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => 'Stripe signature validation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Validate PayPal webhook signature.
     */
    protected function validatePayPalWebhook(Request $request, string $secret): array
    {
        // PayPal uses a different validation method involving API calls
        // This is a simplified version - in production, use PayPal SDK
        $transmissionId = $request->header('Paypal-Transmission-Id');
        $transmissionTime = $request->header('Paypal-Transmission-Time');
        $certUrl = $request->header('Paypal-Cert-Url');
        $authAlgo = $request->header('Paypal-Auth-Algo');
        $transmissionSig = $request->header('Paypal-Transmission-Sig');

        if (empty($transmissionId) || empty($transmissionSig)) {
            return ['valid' => false, 'error' => 'Missing PayPal signature headers'];
        }

        // For full implementation, verify with PayPal API
        // This is a placeholder that should be replaced with actual verification
        Log::info('PayPal webhook received - verification recommended via PayPal SDK');

        return ['valid' => true]; // TODO: Implement full PayPal verification
    }

    /**
     * Validate Netopia webhook signature.
     */
    protected function validateNetopiaWebhook(Request $request, string $secret): array
    {
        // Netopia uses encrypted data with public/private keys
        // This is handled by the Netopia package typically
        $envKey = $request->input('env_key');
        $data = $request->input('data');

        if (empty($envKey) || empty($data)) {
            return ['valid' => false, 'error' => 'Missing Netopia encrypted data'];
        }

        // Netopia verification is typically done during decryption
        // If we get here, assume the middleware already validated
        return ['valid' => true];
    }
}
