<?php

namespace App\Http\Middleware;

use App\Services\Webhooks\WebhookSignatureVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verify Webhook Signature Middleware
 *
 * Verifies incoming webhook signatures to prevent spoofing
 */
class VerifyWebhookSignature
{
    public function __construct(
        protected WebhookSignatureVerifier $verifier
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $provider  The webhook provider (twilio, stripe, github, custom)
     */
    public function handle(Request $request, Closure $next, string $provider = 'custom'): Response
    {
        $verified = match(strtolower($provider)) {
            'twilio' => $this->verifyTwilio($request),
            'stripe' => $this->verifyStripe($request),
            'github' => $this->verifyGitHub($request),
            'custom' => $this->verifyCustom($request),
            default => false,
        };

        if (!$verified) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid webhook signature',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Verify Twilio webhook
     *
     * @param Request $request
     * @return bool
     */
    protected function verifyTwilio(Request $request): bool
    {
        $signature = $request->header('X-Twilio-Signature');

        if (!$signature) {
            return false;
        }

        $url = $request->fullUrl();
        $params = $request->all();
        $authToken = config('microservices.whatsapp.twilio.auth_token');

        if (!$authToken) {
            \Log::error('Twilio auth token not configured for webhook verification');
            return false;
        }

        return $this->verifier->verifyTwilio($url, $params, $signature, $authToken);
    }

    /**
     * Verify Stripe webhook
     *
     * @param Request $request
     * @return bool
     */
    protected function verifyStripe(Request $request): bool
    {
        $signature = $request->header('Stripe-Signature');

        if (!$signature) {
            return false;
        }

        $payload = $request->getContent();
        $secret = config('services.stripe.webhook_secret');

        if (!$secret) {
            \Log::error('Stripe webhook secret not configured');
            return false;
        }

        return $this->verifier->verifyStripe($payload, $signature, $secret);
    }

    /**
     * Verify GitHub webhook
     *
     * @param Request $request
     * @return bool
     */
    protected function verifyGitHub(Request $request): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            return false;
        }

        $payload = $request->getContent();
        $secret = config('services.github.webhook_secret');

        if (!$secret) {
            \Log::error('GitHub webhook secret not configured');
            return false;
        }

        return $this->verifier->verifyGitHub($payload, $signature, $secret);
    }

    /**
     * Verify custom HMAC webhook
     *
     * @param Request $request
     * @return bool
     */
    protected function verifyCustom(Request $request): bool
    {
        // Check for signature in various common headers
        $signature = $request->header('X-Signature')
            ?? $request->header('X-Webhook-Signature')
            ?? $request->header('X-Hub-Signature');

        if (!$signature) {
            return false;
        }

        $payload = $request->getContent();
        $secret = config('microservices.webhooks.signature_secret');

        if (!$secret) {
            \Log::error('Custom webhook secret not configured');
            return false;
        }

        // Try different verification methods
        return $this->verifier->verifyHmac($payload, $signature, $secret)
            || $this->verifier->verifyHmacBase64($payload, $signature, $secret);
    }
}
