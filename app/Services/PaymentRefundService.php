<?php

namespace App\Services;

use App\Models\MarketplaceRefundRequest;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class PaymentRefundService
{
    /**
     * Attempt to process a refund through the payment provider
     */
    public function processRefund(MarketplaceRefundRequest $refund): RefundResult
    {
        $order = $refund->order;
        $processor = $order->payment_processor ?? 'unknown';

        return match ($processor) {
            'stripe' => $this->processStripeRefund($refund, $order),
            'netopia' => $this->processNetopiaRefund($refund, $order),
            'paypal' => $this->processPayPalRefund($refund, $order),
            default => new RefundResult(
                success: false,
                error: "Automatic refund not supported for payment processor: {$processor}. Please process manually.",
                requiresManual: true
            ),
        };
    }

    /**
     * Process refund through Stripe
     */
    protected function processStripeRefund(MarketplaceRefundRequest $refund, Order $order): RefundResult
    {
        $paymentReference = $order->payment_reference;

        if (empty($paymentReference)) {
            return new RefundResult(
                success: false,
                error: 'No payment reference found for this order',
                requiresManual: true
            );
        }

        // Check if Stripe is configured
        $stripeKey = config('services.stripe.secret');
        if (empty($stripeKey)) {
            return new RefundResult(
                success: false,
                error: 'Stripe is not configured. Please process refund manually.',
                requiresManual: true
            );
        }

        try {
            \Stripe\Stripe::setApiKey($stripeKey);

            // Determine if this is a PaymentIntent or Charge
            $isPaymentIntent = str_starts_with($paymentReference, 'pi_');

            if ($isPaymentIntent) {
                $refundParams = [
                    'payment_intent' => $paymentReference,
                    'amount' => (int) ($refund->approved_amount * 100), // Convert to cents
                    'reason' => $this->mapReasonToStripe($refund->reason),
                    'metadata' => [
                        'refund_reference' => $refund->reference,
                        'marketplace_client_id' => $refund->marketplace_client_id,
                        'order_id' => $order->id,
                    ],
                ];
            } else {
                $refundParams = [
                    'charge' => $paymentReference,
                    'amount' => (int) ($refund->approved_amount * 100),
                    'reason' => $this->mapReasonToStripe($refund->reason),
                    'metadata' => [
                        'refund_reference' => $refund->reference,
                        'marketplace_client_id' => $refund->marketplace_client_id,
                        'order_id' => $order->id,
                    ],
                ];
            }

            $stripeRefund = \Stripe\Refund::create($refundParams);

            if ($stripeRefund->status === 'succeeded') {
                return new RefundResult(
                    success: true,
                    refundId: $stripeRefund->id,
                    response: $stripeRefund->toArray()
                );
            } elseif ($stripeRefund->status === 'pending') {
                return new RefundResult(
                    success: true,
                    refundId: $stripeRefund->id,
                    response: $stripeRefund->toArray(),
                    isPending: true
                );
            } else {
                return new RefundResult(
                    success: false,
                    error: "Stripe refund status: {$stripeRefund->status}",
                    refundId: $stripeRefund->id,
                    response: $stripeRefund->toArray()
                );
            }
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            Log::error("Stripe refund failed for {$refund->reference}: {$e->getMessage()}");
            return new RefundResult(
                success: false,
                error: "Stripe error: {$e->getMessage()}",
                requiresManual: true
            );
        } catch (\Exception $e) {
            Log::error("Stripe refund exception for {$refund->reference}: {$e->getMessage()}");
            return new RefundResult(
                success: false,
                error: "Unexpected error: {$e->getMessage()}",
                requiresManual: true
            );
        }
    }

    /**
     * Process refund through Netopia (Romanian payment processor)
     */
    protected function processNetopiaRefund(MarketplaceRefundRequest $refund, Order $order): RefundResult
    {
        // Netopia doesn't support automatic refunds via API for most merchants
        // This would require specific integration with their refund endpoint
        return new RefundResult(
            success: false,
            error: 'Netopia automatic refunds not yet implemented. Please process via Netopia admin panel.',
            requiresManual: true
        );
    }

    /**
     * Process refund through PayPal
     */
    protected function processPayPalRefund(MarketplaceRefundRequest $refund, Order $order): RefundResult
    {
        $paymentReference = $order->payment_reference;

        if (empty($paymentReference)) {
            return new RefundResult(
                success: false,
                error: 'No payment reference found for this order',
                requiresManual: true
            );
        }

        // Check if PayPal is configured
        $clientId = config('services.paypal.client_id');
        $clientSecret = config('services.paypal.secret');

        if (empty($clientId) || empty($clientSecret)) {
            return new RefundResult(
                success: false,
                error: 'PayPal is not configured. Please process refund manually.',
                requiresManual: true
            );
        }

        try {
            // Get access token
            $authResponse = \Http::withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post(config('services.paypal.endpoint', 'https://api-m.paypal.com') . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            if (!$authResponse->successful()) {
                return new RefundResult(
                    success: false,
                    error: 'Failed to authenticate with PayPal',
                    requiresManual: true
                );
            }

            $accessToken = $authResponse->json('access_token');

            // Process refund
            $refundResponse = \Http::withToken($accessToken)
                ->post(config('services.paypal.endpoint', 'https://api-m.paypal.com') . "/v2/payments/captures/{$paymentReference}/refund", [
                    'amount' => [
                        'value' => number_format($refund->approved_amount, 2, '.', ''),
                        'currency_code' => $order->currency ?? 'RON',
                    ],
                    'note_to_payer' => 'Refund for order #' . ($order->order_number ?? $order->id),
                ]);

            if ($refundResponse->successful()) {
                $data = $refundResponse->json();
                return new RefundResult(
                    success: true,
                    refundId: $data['id'] ?? null,
                    response: $data
                );
            } else {
                return new RefundResult(
                    success: false,
                    error: 'PayPal refund failed: ' . ($refundResponse->json('message') ?? 'Unknown error'),
                    response: $refundResponse->json(),
                    requiresManual: true
                );
            }
        } catch (\Exception $e) {
            Log::error("PayPal refund exception for {$refund->reference}: {$e->getMessage()}");
            return new RefundResult(
                success: false,
                error: "Unexpected error: {$e->getMessage()}",
                requiresManual: true
            );
        }
    }

    /**
     * Map internal refund reason to Stripe reason
     */
    protected function mapReasonToStripe(string $reason): string
    {
        return match ($reason) {
            'duplicate_purchase' => 'duplicate',
            'technical_issue' => 'fraudulent',
            default => 'requested_by_customer',
        };
    }
}

/**
 * Result object for refund operations
 */
class RefundResult
{
    public function __construct(
        public bool $success,
        public ?string $error = null,
        public ?string $refundId = null,
        public ?array $response = null,
        public bool $requiresManual = false,
        public bool $isPending = false
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'error' => $this->error,
            'refund_id' => $this->refundId,
            'response' => $this->response,
            'requires_manual' => $this->requiresManual,
            'is_pending' => $this->isPending,
        ];
    }
}
