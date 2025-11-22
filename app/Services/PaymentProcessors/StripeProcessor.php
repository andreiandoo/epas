<?php

namespace App\Services\PaymentProcessors;

use App\Models\TenantPaymentConfig;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class StripeProcessor implements PaymentProcessorInterface
{
    protected TenantPaymentConfig $config;
    protected array $keys;

    public function __construct(TenantPaymentConfig $config)
    {
        $this->config = $config;
        $this->keys = $config->getActiveKeys();

        // Initialize Stripe with secret key
        if ($this->isConfigured()) {
            Stripe::setApiKey($this->keys['secret_key']);
        }
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
        if (empty($this->keys['webhook_secret'])) {
            // If no webhook secret configured, we can't verify
            return true;
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

    /**
     * Get publishable key for frontend
     */
    public function getPublishableKey(): ?string
    {
        return $this->keys['publishable_key'] ?? null;
    }
}
