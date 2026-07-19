<?php

namespace App\Services\PaymentProcessors;

use App\Models\TenantPaymentConfig;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeProcessor implements PaymentProcessorInterface, SupportsTokenizedPayments
{
    /** Tenant context — null when this processor is built from a marketplace pivot. */
    protected ?TenantPaymentConfig $config = null;
    protected array $keys;

    /**
     * Two call shapes:
     *   - Tenant flow:     new StripeProcessor($tenantPaymentConfig)
     *   - Marketplace flow: new StripeProcessor(null, $pivotSettingsArray)
     *
     * The factory's `makeFromArray('stripe', $config)` uses the second form
     * (the marketplace pivot stores settings as an array keyed by
     * test_publishable_key / test_secret_key / test_webhook_secret / test_mode
     * plus their `live_*` equivalents, NOT the flat secret_key/publishable_key
     * pair that TenantPaymentConfig::getActiveKeys returns). Previously the
     * constructor signature was `(TenantPaymentConfig $config)` so the
     * marketplace call path threw a TypeError → 500 the moment a customer
     * clicked Plătește on bilete.online.
     */
    public function __construct(?TenantPaymentConfig $config = null, ?array $arrayConfig = null)
    {
        if ($config) {
            $this->config = $config;
            $this->keys = $config->getActiveKeys();
        } elseif ($arrayConfig !== null) {
            $this->keys = $this->normalizeMarketplaceSettings($arrayConfig);
        } else {
            throw new \InvalidArgumentException(
                'StripeProcessor requires either a TenantPaymentConfig or a marketplace pivot settings array.'
            );
        }

        // Initialize Stripe with secret key
        if ($this->isConfigured()) {
            Stripe::setApiKey($this->keys['secret_key']);
        }
    }

    /**
     * Flatten the marketplace_client_microservices.settings JSON shape into
     * the {secret_key, publishable_key, webhook_secret} triple the rest of
     * this class consumes.
     *
     * The pivot stores both test and live credentials side-by-side and a
     * `test_mode` boolean (defaults to true — safer) picks which to use.
     */
    protected function normalizeMarketplaceSettings(array $settings): array
    {
        $isTest = (bool) ($settings['test_mode'] ?? true);
        $prefix = $isTest ? 'test_' : 'live_';

        return [
            'publishable_key' => $settings[$prefix . 'publishable_key'] ?? null,
            'secret_key'      => $settings[$prefix . 'secret_key']      ?? null,
            'webhook_secret'  => $settings[$prefix . 'webhook_secret']  ?? null,
            'test_mode'       => $isTest,
        ];
    }

    public function createPayment(array $data): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe is not properly configured');
        }

        // Convert amount to cents (Stripe uses smallest currency unit)
        $amountInCents = (int) round($data['amount'] * 100);

        $sessionData = [
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($data['currency'] ?? 'eur'),
                    'product_data' => [
                        'name' => $data['description'] ?? 'Payment',
                    ],
                    'unit_amount' => $amountInCents,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $data['success_url'],
            'cancel_url' => $data['cancel_url'],
            'customer_email' => $data['customer_email'] ?? null,
            'client_reference_id' => $data['order_id'] ?? null,
            'metadata' => array_merge(
                ['order_id' => $data['order_id'] ?? null],
                $data['metadata'] ?? []
            ),
        ];

        // Add customer name if provided
        if (!empty($data['customer_name'])) {
            $sessionData['metadata']['customer_name'] = $data['customer_name'];
        }

        $session = Session::create($sessionData);

        return [
            'payment_id' => $session->id,
            'redirect_url' => $session->url,
            'additional_data' => [
                'payment_intent' => $session->payment_intent,
                'customer' => $session->customer,
            ],
        ];
    }

    public function processCallback(array $payload, array $headers = []): array
    {
        // For Stripe, payload is usually the raw request body as string
        // But we'll handle both cases
        if (is_array($payload) && isset($payload['type'])) {
            $event = (object) $payload;
        } else {
            // Verify signature if webhook secret is configured
            if (!empty($this->keys['webhook_secret']) && !empty($headers['stripe-signature'])) {
                if (!$this->verifySignature($payload, $headers)) {
                    throw new \Exception('Invalid webhook signature');
                }
            }

            $event = json_decode(is_string($payload) ? $payload : json_encode($payload));
        }

        $status = 'pending';
        $result = [
            'status' => 'pending',
            'payment_id' => null,
            'order_id' => null,
            'amount' => 0,
            'currency' => 'eur',
            'transaction_id' => null,
            'paid_at' => null,
            'metadata' => [],
        ];

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;

                $result = [
                    'status' => 'success',
                    'payment_id' => $session->id,
                    'order_id' => $session->client_reference_id ?? $session->metadata->order_id ?? null,
                    'amount' => $session->amount_total / 100,
                    'currency' => strtoupper($session->currency),
                    'transaction_id' => $session->payment_intent,
                    'paid_at' => date('c', $session->created),
                    'metadata' => (array) ($session->metadata ?? []),
                ];
                break;

            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;

                $result = [
                    'status' => 'success',
                    'payment_id' => $paymentIntent->id,
                    'order_id' => $paymentIntent->metadata->order_id ?? null,
                    'amount' => $paymentIntent->amount / 100,
                    'currency' => strtoupper($paymentIntent->currency),
                    'transaction_id' => $paymentIntent->id,
                    'paid_at' => date('c', $paymentIntent->created),
                    'metadata' => (array) ($paymentIntent->metadata ?? []),
                ];
                break;

            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;

                $result = [
                    'status' => 'failed',
                    'payment_id' => $paymentIntent->id,
                    'order_id' => $paymentIntent->metadata->order_id ?? null,
                    'amount' => $paymentIntent->amount / 100,
                    'currency' => strtoupper($paymentIntent->currency),
                    'transaction_id' => $paymentIntent->id,
                    'paid_at' => null,
                    'metadata' => (array) ($paymentIntent->metadata ?? []),
                ];
                break;

            case 'checkout.session.expired':
                $session = $event->data->object;

                $result = [
                    'status' => 'cancelled',
                    'payment_id' => $session->id,
                    'order_id' => $session->client_reference_id ?? $session->metadata->order_id ?? null,
                    'amount' => $session->amount_total / 100,
                    'currency' => strtoupper($session->currency),
                    'transaction_id' => null,
                    'paid_at' => null,
                    'metadata' => (array) ($session->metadata ?? []),
                ];
                break;
        }

        return $result;
    }

    public function verifySignature(array $payload, array $headers): bool
    {
        // SECURITY FIX: If no webhook secret, REJECT the webhook instead of accepting
        if (empty($this->keys['webhook_secret'])) {
            \Log::critical('Stripe webhook rejected: webhook_secret not configured', [
                'ip' => request()->ip(),
            ]);
            return false;
        }

        try {
            $signature = $headers['stripe-signature'] ?? $headers['Stripe-Signature'] ?? null;

            if (!$signature) {
                return false;
            }

            // Payload should be raw request body as string
            $payloadString = is_string($payload) ? $payload : json_encode($payload);

            Webhook::constructEvent(
                $payloadString,
                $signature,
                $this->keys['webhook_secret']
            );

            return true;
        } catch (SignatureVerificationException $e) {
            return false;
        }
    }

    public function getPaymentStatus(string $paymentId): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe is not properly configured');
        }

        try {
            // Try as checkout session first
            if (str_starts_with($paymentId, 'cs_')) {
                $session = Session::retrieve($paymentId);

                return [
                    'status' => $session->payment_status === 'paid' ? 'success' : 'pending',
                    'amount' => $session->amount_total / 100,
                    'currency' => strtoupper($session->currency),
                    'paid_at' => $session->payment_status === 'paid' ? date('c', $session->created) : null,
                ];
            }

            // Try as payment intent
            $paymentIntent = PaymentIntent::retrieve($paymentId);

            $statusMap = [
                'succeeded' => 'success',
                'processing' => 'pending',
                'requires_payment_method' => 'pending',
                'requires_confirmation' => 'pending',
                'requires_action' => 'pending',
                'requires_capture' => 'pending',
                'canceled' => 'cancelled',
                'failed' => 'failed',
            ];

            return [
                'status' => $statusMap[$paymentIntent->status] ?? 'pending',
                'amount' => $paymentIntent->amount / 100,
                'currency' => strtoupper($paymentIntent->currency),
                'paid_at' => $paymentIntent->status === 'succeeded'
                    ? date('c', $paymentIntent->created)
                    : null,
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to retrieve payment status: {$e->getMessage()}");
        }
    }

    public function refundPayment(string $paymentId, ?float $amount = null, ?string $reason = null): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe is not properly configured');
        }

        try {
            $refundData = [
                'payment_intent' => $paymentId,
            ];

            if ($amount !== null) {
                $refundData['amount'] = (int) round($amount * 100);
            }

            if ($reason) {
                $refundData['reason'] = $reason;
            }

            $refund = Refund::create($refundData);

            $statusMap = [
                'succeeded' => 'success',
                'pending' => 'pending',
                'failed' => 'failed',
                'canceled' => 'failed',
            ];

            return [
                'refund_id' => $refund->id,
                'status' => $statusMap[$refund->status] ?? 'pending',
                'amount' => $refund->amount / 100,
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to process refund: {$e->getMessage()}");
        }
    }

    public function isConfigured(): bool
    {
        return !empty($this->keys['secret_key']) && !empty($this->keys['publishable_key']);
    }

    public function getName(): string
    {
        return 'Stripe';
    }

    // =========================================================================
    // SupportsTokenizedPayments — installments / BNPL auto-debit (MIT)
    // =========================================================================

    public function supportsTokenization(): bool
    {
        return $this->isConfigured();
    }

    /**
     * On-session payment (down payment or BNPL 1-RON capture) that also stores
     * the card for off-session reuse. Uses a Checkout Session in `payment` mode
     * with `setup_future_usage=off_session`, which performs SCA now and saves
     * the PaymentMethod to a Customer for later MIT charges.
     */
    public function createPaymentWithMandate(array $data): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe is not properly configured');
        }

        $amountInCents = (int) round(($data['amount'] ?? 0) * 100);

        // Ensure a Customer exists so the saved PaymentMethod is reusable.
        $customerId = $data['customer_reference'] ?? null;
        if (!$customerId) {
            $customer = \Stripe\Customer::create(array_filter([
                'email' => $data['customer_email'] ?? null,
                'name'  => $data['customer_name'] ?? null,
            ]));
            $customerId = $customer->id;
        }

        $session = Session::create([
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'customer' => $customerId,
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($data['currency'] ?? 'ron'),
                    'product_data' => ['name' => $data['description'] ?? 'Payment'],
                    'unit_amount' => $amountInCents,
                ],
                'quantity' => 1,
            ]],
            // Save the card for future off-session (MIT) installment charges.
            'payment_intent_data' => [
                'setup_future_usage' => 'off_session',
                'metadata' => array_merge(
                    ['order_id' => $data['order_id'] ?? null],
                    $data['metadata'] ?? []
                ),
            ],
            'success_url' => $data['success_url'],
            'cancel_url' => $data['cancel_url'],
            'client_reference_id' => $data['order_id'] ?? null,
            'metadata' => array_merge(
                ['order_id' => $data['order_id'] ?? null],
                $data['metadata'] ?? []
            ),
        ]);

        return [
            'payment_id' => $session->id,
            'redirect_url' => $session->url,
            // The mandate (PaymentMethod) is confirmed at the callback; we return
            // the customer here and resolve the payment_method in processCallback.
            'mandate_reference' => $customerId,
            'additional_data' => [
                'customer' => $customerId,
                'payment_intent' => $session->payment_intent,
            ],
        ];
    }

    /**
     * Off-session MIT charge against a saved card.
     *
     * `$mandateReference` is "customer_id|payment_method_id" (or just the
     * customer id, in which case we use the customer's default PM).
     */
    public function chargeWithToken(string $mandateReference, array $data): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe is not properly configured');
        }

        [$customerId, $paymentMethodId] = array_pad(explode('|', $mandateReference, 2), 2, null);

        $amountInCents = (int) round(($data['amount'] ?? 0) * 100);

        try {
            $intentData = [
                'amount' => $amountInCents,
                'currency' => strtolower($data['currency'] ?? 'ron'),
                'customer' => $customerId,
                'confirm' => true,
                'off_session' => true,
                'description' => $data['description'] ?? 'Installment payment',
                'metadata' => array_merge(
                    ['order_id' => $data['order_id'] ?? null],
                    $data['metadata'] ?? []
                ),
            ];
            if ($paymentMethodId) {
                $intentData['payment_method'] = $paymentMethodId;
            }

            $opts = [];
            if (!empty($data['idempotency_key'])) {
                $opts['idempotency_key'] = 'inst_' . $data['idempotency_key'];
            }

            $intent = PaymentIntent::create($intentData, $opts);

            return [
                'status' => $intent->status === 'succeeded' ? 'success' : 'pending',
                'payment_id' => $intent->id,
                'amount' => $intent->amount / 100,
                'currency' => strtoupper($intent->currency),
                'action_url' => null,
                'decline_code' => null,
                'hard_decline' => false,
                'error' => null,
            ];
        } catch (\Stripe\Exception\CardException $e) {
            // Off-session charge needs authentication (SCA) or was declined.
            $err = $e->getError();
            $code = $err->code ?? null;
            $declineCode = $err->decline_code ?? null;
            $needsAuth = ($code === 'authentication_required');

            // Do-not-retry decline codes.
            $hardDeclines = ['stolen_card', 'lost_card', 'pickup_card', 'card_velocity_exceeded'];

            return [
                'status' => $needsAuth ? 'action_required' : 'failed',
                'payment_id' => $err->payment_intent->id ?? null,
                'amount' => $data['amount'] ?? 0,
                'currency' => strtoupper($data['currency'] ?? 'RON'),
                'action_url' => null, // resolved by portal via /pay/{token} on the PaymentIntent
                'decline_code' => $declineCode ?? $code,
                'hard_decline' => in_array($declineCode, $hardDeclines, true),
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'payment_id' => null,
                'amount' => $data['amount'] ?? 0,
                'currency' => strtoupper($data['currency'] ?? 'RON'),
                'action_url' => null,
                'decline_code' => null,
                'hard_decline' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get publishable key for frontend
     */
    public function getPublishableKey(): ?string
    {
        return $this->keys['publishable_key'] ?? null;
    }

    /**
     * Create a Payment Intent for inline checkout (Stripe Elements)
     * Supports card, Apple Pay, and Google Pay
     */
    public function createPaymentIntent(array $data): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe is not properly configured');
        }

        // Convert amount to cents (Stripe uses smallest currency unit)
        $amountInCents = (int) round($data['amount'] * 100);

        $paymentIntentData = [
            'amount' => $amountInCents,
            'currency' => strtolower($data['currency'] ?? 'ron'),
            // Enable automatic payment methods (card, Apple Pay, Google Pay)
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
            'description' => $data['description'] ?? 'Payment',
            'metadata' => array_merge(
                ['order_id' => $data['order_id'] ?? null],
                $data['metadata'] ?? []
            ),
        ];

        // Add customer email if provided
        if (!empty($data['customer_email'])) {
            $paymentIntentData['receipt_email'] = $data['customer_email'];
        }

        // Add customer name to metadata
        if (!empty($data['customer_name'])) {
            $paymentIntentData['metadata']['customer_name'] = $data['customer_name'];
        }

        $paymentIntent = PaymentIntent::create($paymentIntentData);

        return [
            'payment_intent_id' => $paymentIntent->id,
            'client_secret' => $paymentIntent->client_secret,
            'publishable_key' => $this->getPublishableKey(),
            'amount' => $amountInCents,
            'currency' => strtolower($data['currency'] ?? 'ron'),
        ];
    }

    /**
     * Confirm a Payment Intent (for server-side confirmation)
     */
    public function confirmPaymentIntent(string $paymentIntentId): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Stripe is not properly configured');
        }

        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);

            $statusMap = [
                'succeeded' => 'success',
                'processing' => 'processing',
                'requires_payment_method' => 'requires_payment',
                'requires_confirmation' => 'requires_confirmation',
                'requires_action' => 'requires_action',
                'requires_capture' => 'requires_capture',
                'canceled' => 'cancelled',
            ];

            return [
                'status' => $statusMap[$paymentIntent->status] ?? 'pending',
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount / 100,
                'currency' => strtoupper($paymentIntent->currency),
                'order_id' => $paymentIntent->metadata->order_id ?? null,
            ];
        } catch (\Exception $e) {
            throw new \Exception("Failed to confirm payment: {$e->getMessage()}");
        }
    }
}
