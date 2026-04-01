<?php

namespace App\Services;

use App\Models\MarketplaceRefundItem;
use App\Models\MarketplaceRefundRequest;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
use Illuminate\Support\Facades\DB;
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
     * Process refund through Netopia via SOAP API
     */
    protected function processNetopiaRefund(MarketplaceRefundRequest $refund, Order $order): RefundResult
    {
        $paymentReference = $order->payment_reference ?? $order->order_number;

        if (empty($paymentReference)) {
            return new RefundResult(
                success: false,
                error: 'No payment reference found for this order.',
                requiresManual: true
            );
        }

        try {
            $client = $order->marketplaceClient;
            if (!$client) {
                return new RefundResult(success: false, error: 'Marketplace client not found.', requiresManual: true);
            }

            // Get Netopia config from marketplace microservice settings
            $netopiaMs = $client->microservices()->where('slug', 'payment-netopia')->first();
            $settings = $netopiaMs?->pivot?->settings ?? [];
            if (is_string($settings)) $settings = json_decode($settings, true) ?? [];

            if (empty($settings)) {
                return new RefundResult(success: false, error: 'Netopia payment not configured.', requiresManual: true);
            }

            $processor = PaymentProcessorFactory::makeFromArray('netopia', $settings);
            $result = $processor->refundPayment($paymentReference, (float) $refund->approved_amount, $refund->reason);

            if ($result['success'] ?? false) {
                return new RefundResult(
                    success: true,
                    refundId: $result['refund_id'] ?? null,
                    response: $result['response'] ?? null
                );
            }

            return new RefundResult(
                success: false,
                error: $result['error'] ?? 'Netopia refund failed.',
                requiresManual: $result['requires_manual'] ?? true
            );

        } catch (\Throwable $e) {
            Log::channel('marketplace')->error("Netopia refund exception for {$refund->reference}: {$e->getMessage()}");
            return new RefundResult(success: false, error: "Error: {$e->getMessage()}", requiresManual: true);
        }
    }

    /**
     * Process a ticket-level refund with per-ticket commission tracking.
     */
    public function processTicketLevelRefund(
        Order $order,
        array $ticketIds,
        bool $refundCommission,
        string $reason,
        ?string $reasonCategory = null
    ): RefundResult {
        // Validate order is paid
        if (!in_array($order->status, ['completed', 'paid', 'confirmed'])) {
            return new RefundResult(success: false, error: 'Comanda nu este plătită. Refund imposibil.');
        }

        // Load tickets
        $tickets = $order->tickets()->whereIn('id', $ticketIds)->get();
        if ($tickets->isEmpty()) {
            return new RefundResult(success: false, error: 'Niciun bilet valid selectat.');
        }

        // Check for already refunded
        $alreadyRefunded = $tickets->filter(fn (Ticket $t) => $t->isRefunded());
        if ($alreadyRefunded->isNotEmpty()) {
            return new RefundResult(success: false, error: 'Unele bilete sunt deja rambursate: ' . $alreadyRefunded->pluck('code')->implode(', '));
        }

        // Calculate amounts per ticket using stored order values
        $orderSubtotal = (float) ($order->subtotal ?? 0);
        $orderDiscount = (float) ($order->discount_amount ?? 0);
        $orderCommission = (float) ($order->commission_amount ?? 0);
        $discountRatio = ($orderSubtotal > 0 && $orderDiscount > 0) ? ($orderDiscount / $orderSubtotal) : 0;

        $items = [];
        $totalRefund = 0;

        foreach ($tickets as $ticket) {
            $originalPrice = (float) ($ticket->price ?? 0);
            $ticketDiscount = round($originalPrice * $discountRatio, 2);
            $faceValue = round($originalPrice - $ticketDiscount, 2); // price after discount
            // Proportional commission from stored order commission
            $commission = ($orderSubtotal > 0)
                ? round($orderCommission * ($originalPrice / $orderSubtotal), 2)
                : 0;
            $refundAmount = $refundCommission ? ($faceValue + $commission) : $faceValue;

            $items[] = [
                'ticket' => $ticket,
                'face_value' => round($faceValue, 2),
                'commission_amount' => round($commission, 2),
                'refund_amount' => round($refundAmount, 2),
                'commission_refunded' => $refundCommission,
            ];
            $totalRefund += $refundAmount;
        }

        $totalRefund = round($totalRefund, 2);

        // Validate total doesn't exceed remaining
        $alreadyRefundedAmount = (float) ($order->refunded_amount ?? 0);
        $maxRefundable = (float) $order->total - $alreadyRefundedAmount;
        if ($totalRefund > $maxRefundable + 0.01) {
            return new RefundResult(success: false, error: "Suma de refund ({$totalRefund}) depășește maximul disponibil ({$maxRefundable}).");
        }

        // Determine if this is full or partial
        $allOrderTicketIds = $order->tickets()->pluck('id')->toArray();
        $nonRefundedTicketIds = $order->tickets()->where('refund_status', '!=', 'refunded')->pluck('id')->toArray();
        $isFullRefund = count($ticketIds) >= count($nonRefundedTicketIds);

        $processorResult = null;

        DB::beginTransaction();

        try {
            // Create refund request
            $refundRequest = MarketplaceRefundRequest::create([
                'reference' => 'REF-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6)),
                'marketplace_client_id' => $order->marketplace_client_id,
                'marketplace_organizer_id' => $order->marketplace_organizer_id,
                'marketplace_customer_id' => $order->marketplace_customer_id,
                'order_id' => $order->id,
                'marketplace_event_id' => $order->marketplace_event_id ?? $order->event_id,
                'type' => $isFullRefund ? 'full_refund' : 'partial_refund',
                'reason' => $reason,
                'reason_category' => $reasonCategory,
                'ticket_ids' => $ticketIds,
                'requested_amount' => $totalRefund,
                'approved_amount' => $totalRefund,
                'currency' => $order->currency ?? 'RON',
                'status' => 'processing',
                'refund_method' => 'original_payment',
                'payment_processor' => $order->payment_processor,
                'commission_refund' => $refundCommission ? collect($items)->sum('commission_amount') : 0,
                'organizer_deduction' => collect($items)->sum('face_value'),
                'requested_at' => now(),
            ]);

            // Create refund items
            foreach ($items as $item) {
                MarketplaceRefundItem::create([
                    'refund_request_id' => $refundRequest->id,
                    'ticket_id' => $item['ticket']->id,
                    'ticket_type_id' => $item['ticket']->ticket_type_id,
                    'face_value' => $item['face_value'],
                    'commission_amount' => $item['commission_amount'],
                    'refund_amount' => $item['refund_amount'],
                    'commission_refunded' => $item['commission_refunded'],
                    'status' => 'pending',
                ]);
            }

            // Call payment processor
            $processorResult = $this->processRefund($refundRequest);

            if (!$processorResult->success && !$processorResult->requiresManual) {
                DB::rollBack();
                return $processorResult;
            }

            // If success or requires manual processing, update records
            if ($processorResult->success) {
                $refundRequest->update([
                    'status' => $isFullRefund ? 'refunded' : 'partially_refunded',
                    'payment_refund_id' => $processorResult->refundId,
                    'payment_response' => $processorResult->response,
                    'is_automatic' => true,
                    'processed_at' => now(),
                    'completed_at' => now(),
                ]);

                // Update refund items
                $refundRequest->refundItems()->update(['status' => 'refunded']);
            } else {
                // Manual processing needed
                $refundRequest->update([
                    'status' => 'approved',
                    'admin_notes' => 'Automatic refund requires manual processing: ' . ($processorResult->error ?? ''),
                ]);
            }

            // Update tickets
            foreach ($tickets as $ticket) {
                $ticket->update([
                    'status' => 'refunded',
                    'refund_status' => 'refunded',
                    'is_cancelled' => true,
                    'cancelled_at' => now(),
                    'cancellation_reason' => "Refund: {$refundRequest->reference}",
                    'refund_request_id' => $refundRequest->id,
                ]);
            }

            // Update order
            $newRefundedAmount = $alreadyRefundedAmount + $totalRefund;
            $refundStatus = $isFullRefund ? 'full' : 'partial';
            $order->update([
                'refund_status' => $refundStatus,
                'refunded_amount' => round($newRefundedAmount, 2),
                'refunded_at' => now(),
                'refund_amount' => round($newRefundedAmount, 2),
                'refund_reason' => $reason,
            ]);

            if ($isFullRefund) {
                $order->update(['status' => 'refunded']);
            }

            // Restore stock for affected tickets
            $order->releaseStockForTickets($tickets);

            DB::commit();

            return $processorResult;

        } catch (\Throwable $e) {
            DB::rollBack();

            // If processor already succeeded but DB failed — critical discrepancy
            if ($processorResult?->success) {
                Log::channel('marketplace')->critical('REFUND DISCREPANCY: Processor refunded but DB failed', [
                    'order_id' => $order->id,
                    'refund_id' => $processorResult->refundId,
                    'amount' => $totalRefund,
                    'error' => $e->getMessage(),
                ]);

                // Attempt minimal recovery record
                try {
                    MarketplaceRefundRequest::create([
                        'reference' => 'REF-RECOVERY-' . now()->format('YmdHis'),
                        'marketplace_client_id' => $order->marketplace_client_id,
                        'order_id' => $order->id,
                        'type' => 'full_refund',
                        'reason' => 'AUTO-RECOVERY: DB failed after processor refund',
                        'requested_amount' => $totalRefund,
                        'approved_amount' => $totalRefund,
                        'currency' => $order->currency ?? 'RON',
                        'status' => 'refunded',
                        'payment_refund_id' => $processorResult->refundId,
                        'admin_notes' => "CRITICAL: Processor refunded {$totalRefund} but DB transaction failed: {$e->getMessage()}",
                        'requested_at' => now(),
                        'completed_at' => now(),
                    ]);
                } catch (\Throwable $inner) {
                    Log::channel('marketplace')->emergency('REFUND DISCREPANCY: Recovery record also failed', [
                        'order_id' => $order->id,
                        'refund_id' => $processorResult->refundId,
                        'amount' => $totalRefund,
                    ]);
                }
            }

            Log::channel('marketplace')->error('Refund processing exception', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return new RefundResult(success: false, error: 'Eroare la procesare: ' . $e->getMessage());
        }
    }

    /**
     * Process a full order refund (all non-refunded tickets).
     */
    public function processOrderLevelRefund(Order $order, bool $refundCommission, string $reason, ?string $reasonCategory = null): RefundResult
    {
        $ticketIds = $order->tickets()
            ->where('refund_status', '!=', 'refunded')
            ->pluck('id')
            ->toArray();

        if (empty($ticketIds)) {
            return new RefundResult(success: false, error: 'Toate biletele sunt deja rambursate.');
        }

        return $this->processTicketLevelRefund($order, $ticketIds, $refundCommission, $reason, $reasonCategory);
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
