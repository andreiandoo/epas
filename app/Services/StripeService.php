<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting;
use App\Models\Microservice;
use App\Models\Tenant;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\PaymentLink;
use Stripe\Price;
use Stripe\Product;
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
        $paymentProcessorSlugs = ['payment-stripe', 'payment-netopia', 'payment-payu', 'payment-euplatesc'];

        foreach ($microserviceIds as $microserviceId) {
            $microservice = Microservice::find($microserviceId);

            if (!$microservice) {
                continue;
            }

            // If this is a payment processor, deactivate any existing payment processors
            if (in_array($microservice->slug, $paymentProcessorSlugs)) {
                $tenant->microservices()
                    ->whereIn('slug', $paymentProcessorSlugs)
                    ->where('microservice_id', '!=', $microservice->id)
                    ->each(function ($existingProcessor) use ($tenant) {
                        $tenant->microservices()->updateExistingPivot($existingProcessor->id, [
                            'is_active' => false,
                        ]);
                    });
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
            'microservices' => collect($activatedServices),
            'session' => $session,
            'amount_total' => $session->amount_total / 100, // Convert from cents
            'currency' => strtoupper($session->currency),
        ];
    }

    /**
     * Get default configuration for a microservice
     */
    public function getDefaultConfiguration(Microservice $microservice): array
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

    /**
     * Create a checkout session for invoice payment
     */
    public function createInvoiceCheckoutSession(Invoice $invoice, string $successUrl, string $cancelUrl): Session
    {
        $this->ensureInitialized();

        $tenant = $invoice->tenant;
        $tenantName = $tenant->public_name ?? $tenant->name;
        $invoiceType = $invoice->isProforma() ? 'Factura Proforma' : 'Factura Fiscala';

        // Create checkout session
        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => strtolower($invoice->currency ?? 'ron'),
                        'product_data' => [
                            'name' => "{$invoiceType} #{$invoice->number}",
                            'description' => $invoice->description,
                        ],
                        'unit_amount' => (int) round($invoice->amount * 100), // Convert to cents
                    ],
                    'quantity' => 1,
                ],
            ],
            'mode' => 'payment',
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $invoice->id,
            'customer_email' => $tenant->contact_email ?? $tenant->owner?->email,
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
                'tenant_id' => $tenant->id,
                'payment_type' => 'invoice',
            ],
            'billing_address_collection' => 'required',
            'invoice_creation' => [
                'enabled' => false, // We handle our own invoices
            ],
        ]);

        // Update invoice with checkout session info
        $invoice->update([
            'stripe_checkout_session_id' => $session->id,
            'stripe_payment_link_url' => $session->url,
        ]);

        return $session;
    }

    /**
     * Create a reusable payment link for an invoice
     */
    public function createInvoicePaymentLink(Invoice $invoice): PaymentLink
    {
        $this->ensureInitialized();

        $tenant = $invoice->tenant;
        $invoiceType = $invoice->isProforma() ? 'Factura Proforma' : 'Factura Fiscala';

        // First, create a product for this invoice
        $product = Product::create([
            'name' => "{$invoiceType} #{$invoice->number}",
            'description' => $invoice->description,
            'metadata' => [
                'invoice_id' => $invoice->id,
                'tenant_id' => $tenant->id,
            ],
        ]);

        // Create a price for the product
        $price = Price::create([
            'product' => $product->id,
            'unit_amount' => (int) round($invoice->amount * 100), // Convert to cents
            'currency' => strtolower($invoice->currency ?? 'ron'),
        ]);

        // Create the payment link
        $paymentLink = PaymentLink::create([
            'line_items' => [
                [
                    'price' => $price->id,
                    'quantity' => 1,
                ],
            ],
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->number,
                'tenant_id' => $tenant->id,
                'payment_type' => 'invoice',
            ],
            'after_completion' => [
                'type' => 'redirect',
                'redirect' => [
                    'url' => config('app.url') . '/invoice-payment/success?invoice=' . $invoice->id,
                ],
            ],
        ]);

        // Update invoice with payment link info
        $invoice->update([
            'stripe_payment_link_id' => $paymentLink->id,
            'stripe_payment_link_url' => $paymentLink->url,
        ]);

        return $paymentLink;
    }

    /**
     * Process invoice payment completed (from webhook)
     */
    public function processInvoicePaymentCompleted(Session $session): ?Invoice
    {
        $metadata = $session->metadata->toArray();

        // Check if this is an invoice payment
        if (($metadata['payment_type'] ?? '') !== 'invoice') {
            return null;
        }

        $invoiceId = $metadata['invoice_id'] ?? $session->client_reference_id;

        if (!$invoiceId) {
            throw new \Exception('Invoice ID not found in session metadata');
        }

        $invoice = Invoice::find($invoiceId);

        if (!$invoice) {
            throw new \Exception("Invoice not found: {$invoiceId}");
        }

        // Mark invoice as paid
        $invoice->markAsPaid('stripe', $session->id);

        return $invoice;
    }
}
