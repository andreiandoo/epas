<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Microservice;
use App\Models\Tenant;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeService
{
    protected Setting $settings;
    protected bool $initialized = false;

    public function __construct()
    {
        $this->settings = Setting::current();
    }

    /**
     * Ensure Stripe is initialized before making API calls
     */
    protected function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        $secretKey = $this->settings->getStripeSecretKey();

        if (!$secretKey) {
            throw new \Exception('Stripe is not configured. Please add your API keys in Settings > Connections.');
        }

        Stripe::setApiKey($secretKey);
        $this->initialized = true;
    }

    /**
     * Create a checkout session for microservice purchase
     *
     * @param Tenant $tenant
     * @param array $microserviceIds Array of microservice IDs to purchase
     * @param string $successUrl
     * @param string $cancelUrl
     * @return Session
     */
    public function createCheckoutSession(
        Tenant $tenant,
        array $microserviceIds,
        string $successUrl,
        string $cancelUrl
    ): Session {
        $this->ensureInitialized();

        $microservices = Microservice::whereIn('id', $microserviceIds)
            ->where('is_active', true)
            ->get();

        if ($microservices->isEmpty()) {
            throw new \Exception('No valid microservices selected');
        }

        // Build line items for Stripe checkout
        $lineItems = [];
        $metadata = [
            'tenant_id' => $tenant->id,
            'tenant_email' => $tenant->contact_email ?? $tenant->owner->email ?? '',
            'microservice_ids' => implode(',', $microserviceIds),
        ];

        // Use currency from first microservice (all should have same currency for Stripe)
        $checkoutCurrency = strtolower($microservices->first()->currency ?? $this->settings->default_currency ?? 'eur');

        foreach ($microservices as $microservice) {
            $lineItem = [
                'price_data' => [
                    'currency' => $checkoutCurrency,
                    'product_data' => [
                        'name' => $microservice->getTranslation('name', app()->getLocale()) ?: $microservice->getTranslation('name', 'en'),
                        'description' => $microservice->getTranslation('short_description', app()->getLocale())
                            ?: $microservice->getTranslation('description', app()->getLocale())
                            ?: $microservice->getTranslation('short_description', 'en')
                            ?: $microservice->getTranslation('description', 'en'),
                    ],
                ],
                'quantity' => 1,
            ];

            // Handle different pricing models
            if ($microservice->pricing_model === 'one-time') {
                $lineItem['price_data']['unit_amount'] = (int)($microservice->price * 100); // Convert to cents
            } elseif (in_array($microservice->pricing_model, ['monthly', 'yearly'])) {
                $lineItem['price_data']['unit_amount'] = (int)($microservice->price * 100);
                $lineItem['price_data']['recurring'] = [
                    'interval' => $microservice->pricing_model === 'monthly' ? 'month' : 'year',
                ];
            } else {
                // per-use or other models - treat as one-time for now
                $lineItem['price_data']['unit_amount'] = (int)($microservice->price * 100);
            }

            $lineItems[] = $lineItem;
        }

        // Create checkout session
        $sessionParams = [
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => $this->determineCheckoutMode($microservices),
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'client_reference_id' => $tenant->id,
            'customer_email' => $metadata['tenant_email'],
            'metadata' => $metadata,
            'billing_address_collection' => 'required',
        ];

        // Add subscription-specific settings if needed
        if ($sessionParams['mode'] === 'subscription') {
            $sessionParams['subscription_data'] = [
                'metadata' => $metadata,
            ];
        }

        return Session::create($sessionParams);
    }

    /**
     * Determine checkout mode based on microservices
     */
    protected function determineCheckoutMode($microservices): string
    {
        foreach ($microservices as $microservice) {
            if (in_array($microservice->pricing_model, ['monthly', 'yearly'])) {
                return 'subscription';
            }
        }

        return 'payment'; // one-time payment
    }

    /**
     * Retrieve a checkout session
     */
    public function retrieveSession(string $sessionId): Session
    {
        $this->ensureInitialized();

        return Session::retrieve($sessionId);
    }

    /**
     * Verify webhook signature and construct event
     */
    public function constructWebhookEvent(string $payload, string $signature): \Stripe\Event
    {
        $this->ensureInitialized();

        $webhookSecret = $this->settings->stripe_webhook_secret;

        if (!$webhookSecret) {
            throw new \Exception('Stripe webhook secret is not configured');
        }

        try {
            return Webhook::constructEvent($payload, $signature, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            throw new \Exception('Invalid webhook signature: ' . $e->getMessage());
        }
    }

    /**
     * Process checkout session completed event
     */
    public function processCheckoutCompleted(Session $session): array
    {
        $metadata = $session->metadata->toArray();
        $tenantId = $metadata['tenant_id'] ?? $session->client_reference_id;
        $microserviceIds = explode(',', $metadata['microservice_ids'] ?? '');

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            throw new \Exception("Tenant not found: {$tenantId}");
        }

        $activatedServices = [];

        foreach ($microserviceIds as $microserviceId) {
            $microservice = Microservice::find($microserviceId);

            if (!$microservice) {
                continue;
            }

            // Check if already activated
            if ($tenant->microservices()->where('microservice_id', $microservice->id)->exists()) {
                // Update existing relationship
                $tenant->microservices()->updateExistingPivot($microservice->id, [
                    'is_active' => true,
                    'activated_at' => now(),
                ]);
            } else {
                // Attach new microservice
                $tenant->microservices()->attach($microservice->id, [
                    'is_active' => true,
                    'activated_at' => now(),
                    'configuration' => $this->getDefaultConfiguration($microservice),
                ]);
            }

            $activatedServices[] = $microservice;
        }

        return [
            'tenant' => $tenant,
            'microservices' => $activatedServices,
            'session' => $session,
            'amount_total' => $session->amount_total / 100, // Convert from cents
            'currency' => strtoupper($session->currency),
        ];
    }

    /**
     * Get default configuration for a microservice
     */
    protected function getDefaultConfiguration(Microservice $microservice): array
    {
        // Return default config based on microservice type
        if ($microservice->slug === 'affiliate-tracking') {
            return [
                'cookie_name' => 'aff_ref',
                'cookie_duration_days' => 90,
                'commission_type' => 'percent',
                'commission_value' => 10.00,
                'self_purchase_guard' => true,
                'exclude_taxes_from_commission' => true,
            ];
        }

        return [];
    }

    /**
     * Create a Stripe customer for tenant
     */
    public function createCustomer(Tenant $tenant): Customer
    {
        $this->ensureInitialized();

        return Customer::create([
            'email' => $tenant->contact_email ?? $tenant->owner->email,
            'name' => $tenant->public_name ?? $tenant->name,
            'metadata' => [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
            ],
        ]);
    }

    /**
     * Check if Stripe is configured
     */
    public function isConfigured(): bool
    {
        return $this->settings->isStripeConfigured();
    }

    /**
     * Get publishable key for frontend
     */
    public function getPublishableKey(): ?string
    {
        return $this->settings->getStripePublicKey();
    }
}
