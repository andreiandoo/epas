<?php

namespace App\Services\Webhooks;

use Illuminate\Support\Facades\Log;

/**
 * Webhook Signature Verification Service
 *
 * Verifies webhook signatures from various providers to prevent spoofing
 * Supports: Twilio, Stripe, GitHub, and custom HMAC verification
 */
class WebhookSignatureVerifier
{
    /**
     * Verify Twilio webhook signature
     *
     * @param string $url The full URL of the webhook endpoint
     * @param array $params POST parameters from the webhook
     * @param string $signature The X-Twilio-Signature header
     * @param string $authToken Your Twilio auth token
     * @return bool
     */
    public function verifyTwilio(string $url, array $params, string $signature, string $authToken): bool
    {
        // Sort parameters alphabetically
        ksort($params);

        // Create the signature string
        $data = $url;
        foreach ($params as $key => $value) {
            $data .= $key . $value;
        }

        // Compute the HMAC
        $expectedSignature = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        $isValid = hash_equals($expectedSignature, $signature);

        if (!$isValid) {
            Log::warning('Twilio webhook signature verification failed', [
                'url' => $url,
                'expected' => $expectedSignature,
                'received' => $signature,
            ]);
        }

        return $isValid;
    }

    /**
     * Verify Stripe webhook signature
     *
     * @param string $payload The raw request body
     * @param string $signature The Stripe-Signature header
     * @param string $secret Your Stripe webhook secret
     * @return bool
     */
    public function verifyStripe(string $payload, string $signature, string $secret): bool
    {
        // Parse the signature header
        $elements = explode(',', $signature);
        $signatureData = [];

        foreach ($elements as $element) {
            [$key, $value] = explode('=', $element, 2);
            $signatureData[$key] = $value;
        }

        if (!isset($signatureData['t']) || !isset($signatureData['v1'])) {
            Log::warning('Stripe webhook signature missing required fields');
            return false;
        }

        $timestamp = $signatureData['t'];
        $receivedSignature = $signatureData['v1'];

        // Check if timestamp is within tolerance (5 minutes)
        $tolerance = 300; // seconds
        if (abs(time() - $timestamp) > $tolerance) {
            Log::warning('Stripe webhook timestamp outside tolerance', [
                'timestamp' => $timestamp,
                'current' => time(),
                'difference' => abs(time() - $timestamp),
            ]);
            return false;
        }

        // Compute expected signature
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        $isValid = hash_equals($expectedSignature, $receivedSignature);

        if (!$isValid) {
            Log::warning('Stripe webhook signature verification failed');
        }

        return $isValid;
    }

    /**
     * Verify GitHub webhook signature
     *
     * @param string $payload The raw request body
     * @param string $signature The X-Hub-Signature-256 header
     * @param string $secret Your GitHub webhook secret
     * @return bool
     */
    public function verifyGitHub(string $payload, string $signature, string $secret): bool
    {
        // Remove 'sha256=' prefix if present
        if (str_starts_with($signature, 'sha256=')) {
            $signature = substr($signature, 7);
        }

        // Compute expected signature
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        $isValid = hash_equals($expectedSignature, $signature);

        if (!$isValid) {
            Log::warning('GitHub webhook signature verification failed');
        }

        return $isValid;
    }

    /**
     * Verify generic HMAC signature
     *
     * @param string $payload The data that was signed
     * @param string $signature The signature to verify
     * @param string $secret The shared secret
     * @param string $algorithm Hash algorithm (sha256, sha512, etc.)
     * @return bool
     */
    public function verifyHmac(string $payload, string $signature, string $secret, string $algorithm = 'sha256'): bool
    {
        $expectedSignature = hash_hmac($algorithm, $payload, $secret);

        $isValid = hash_equals($expectedSignature, $signature);

        if (!$isValid) {
            Log::warning('HMAC signature verification failed', [
                'algorithm' => $algorithm,
            ]);
        }

        return $isValid;
    }

    /**
     * Verify custom webhook signature with base64 encoding
     *
     * @param string $payload The data that was signed
     * @param string $signature The signature to verify
     * @param string $secret The shared secret
     * @param string $algorithm Hash algorithm
     * @return bool
     */
    public function verifyHmacBase64(string $payload, string $signature, string $secret, string $algorithm = 'sha256'): bool
    {
        $expectedSignature = base64_encode(hash_hmac($algorithm, $payload, $secret, true));

        $isValid = hash_equals($expectedSignature, $signature);

        if (!$isValid) {
            Log::warning('HMAC (base64) signature verification failed', [
                'algorithm' => $algorithm,
            ]);
        }

        return $isValid;
    }

    /**
     * Generate HMAC signature for outgoing webhooks
     *
     * @param string $payload The data to sign
     * @param string $secret The shared secret
     * @param string $algorithm Hash algorithm
     * @return string
     */
    public function generateHmac(string $payload, string $secret, string $algorithm = 'sha256'): string
    {
        return hash_hmac($algorithm, $payload, $secret);
    }

    /**
     * Generate HMAC signature with base64 encoding
     *
     * @param string $payload The data to sign
     * @param string $secret The shared secret
     * @param string $algorithm Hash algorithm
     * @return string
     */
    public function generateHmacBase64(string $payload, string $secret, string $algorithm = 'sha256'): string
    {
        return base64_encode(hash_hmac($algorithm, $payload, $secret, true));
    }

    /**
     * Verify webhook signature based on provider
     *
     * @param string $provider Provider name (twilio, stripe, github, custom)
     * @param array $data Verification data
     * @return bool
     */
    public function verify(string $provider, array $data): bool
    {
        return match(strtolower($provider)) {
            'twilio' => $this->verifyTwilio(
                $data['url'] ?? '',
                $data['params'] ?? [],
                $data['signature'] ?? '',
                $data['secret'] ?? ''
            ),
            'stripe' => $this->verifyStripe(
                $data['payload'] ?? '',
                $data['signature'] ?? '',
                $data['secret'] ?? ''
            ),
            'github' => $this->verifyGitHub(
                $data['payload'] ?? '',
                $data['signature'] ?? '',
                $data['secret'] ?? ''
            ),
            'hmac' => $this->verifyHmac(
                $data['payload'] ?? '',
                $data['signature'] ?? '',
                $data['secret'] ?? '',
                $data['algorithm'] ?? 'sha256'
            ),
            'hmac_base64' => $this->verifyHmacBase64(
                $data['payload'] ?? '',
                $data['signature'] ?? '',
                $data['secret'] ?? '',
                $data['algorithm'] ?? 'sha256'
            ),
            default => false,
        };
    }
}
