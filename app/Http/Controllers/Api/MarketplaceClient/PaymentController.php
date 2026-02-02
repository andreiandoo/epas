<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Order;
use App\Models\MarketplaceTransaction;
use App\Models\Tenant;
use App\Models\Gamification\ExperienceAction;
use App\Notifications\MarketplaceOrderNotification;
use App\Services\PaymentProcessors\PaymentProcessorFactory;
use App\Services\Gamification\ExperienceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class PaymentController extends BaseController
{
    /**
     * Initialize payment for a marketplace order
     */
    public function initiate(Request $request, int $orderId): JsonResponse
    {
        $client = $this->requireClient($request);

        $order = Order::with('event.tenant')
            ->where('id', $orderId)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        if ($order->status !== 'pending') {
            return $this->error('Order is not in pending status', 400);
        }

        if ($order->expires_at && $order->expires_at->isPast()) {
            return $this->error('Order has expired', 400);
        }

        // For marketplace orders, use the marketplace client's payment config
        // The marketplace client processes payments for tenant events they sell
        $defaultPaymentMethod = $client->getDefaultPaymentMethod();

        if (!$defaultPaymentMethod) {
            return $this->error('No payment method configured for this marketplace', 400);
        }

        $paymentConfig = $client->getPaymentMethodSettings($defaultPaymentMethod->slug);

        if (!$paymentConfig) {
            return $this->error('Payment configuration not found', 400);
        }

        // Determine processor type from microservice slug
        $processorType = match ($defaultPaymentMethod->slug) {
            'netopia', 'netopia-payments', 'payment-netopia' => 'netopia',
            'stripe', 'stripe-payments', 'payment-stripe' => 'stripe',
            'euplatesc', 'payment-euplatesc' => 'euplatesc',
            'payu', 'payment-payu' => 'payu',
            default => $defaultPaymentMethod->slug,
        };

        try {
            $processor = PaymentProcessorFactory::makeFromArray($processorType, $paymentConfig);

            // Get event title for description
            $eventTitle = $order->event?->title ?? 'Event';

            $paymentData = $processor->createPayment([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => $order->total,
                'currency' => $order->currency,
                'customer_email' => $order->customer_email,
                'customer_name' => $order->customer_name,
                'description' => "Tickets for {$eventTitle}",
                'success_url' => $request->input('return_url', $client->domain . '/order-complete'),
                'return_url' => $request->input('return_url', $client->domain . '/order-complete'),
                'cancel_url' => $request->input('cancel_url', $client->domain . '/order-cancelled'),
                'callback_url' => route('api.marketplace-client.payment.callback', [
                    'client' => $client->slug,
                ]),
                'metadata' => [
                    'marketplace_client_id' => $client->id,
                    'marketplace_client_name' => $client->name,
                    'source' => 'marketplace',
                ],
            ]);

            // Update order with payment reference
            $order->update([
                'payment_reference' => $paymentData['reference'] ?? $paymentData['payment_id'] ?? null,
                'payment_processor' => $processorType,
            ]);

            Log::channel('marketplace')->info('Payment initiated for marketplace order', [
                'order_id' => $order->id,
                'client_id' => $client->id,
                'processor' => $processorType,
            ]);

            $response = [
                'payment_url' => $paymentData['redirect_url'] ?? $paymentData['payment_url'],
                'payment_reference' => $paymentData['reference'] ?? $paymentData['payment_id'] ?? null,
                'processor' => $processorType,
            ];

            // For processors that require POST form submission (like Netopia)
            if (($paymentData['method'] ?? 'GET') === 'POST' && !empty($paymentData['form_data'])) {
                $response['method'] = 'POST';
                $response['form_data'] = $paymentData['form_data'];
            }

            return $this->success($response, 'Payment initiated');

        } catch (\Exception $e) {
            Log::channel('marketplace')->error('Failed to initiate payment', [
                'order_id' => $order->id,
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Failed to initiate payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle payment callback from payment processor
     */
    public function callback(Request $request, string $clientSlug): JsonResponse
    {
        Log::channel('marketplace')->info('Payment callback received', [
            'client_slug' => $clientSlug,
            'data' => $request->all(),
        ]);

        // Find order from callback data
        $orderId = $request->input('order_id') ?? $request->input('orderId');
        $orderNumber = $request->input('order_number') ?? $request->input('orderNumber');

        $order = Order::when($orderId, fn($q) => $q->where('id', $orderId))
            ->when($orderNumber, fn($q) => $q->where('order_number', $orderNumber))
            ->whereHas('marketplaceClient', fn($q) => $q->where('slug', $clientSlug))
            ->first();

        if (!$order) {
            Log::channel('marketplace')->error('Order not found for payment callback', [
                'client_slug' => $clientSlug,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
            ]);
            return $this->error('Order not found', 404);
        }

        try {
            // For marketplace orders, use the marketplace client's payment config
            $client = $order->marketplaceClient;
            $processorType = $order->payment_processor;

            if (!$processorType) {
                // Fallback to default payment method
                $defaultPaymentMethod = $client->getDefaultPaymentMethod();
                $processorType = match ($defaultPaymentMethod?->slug) {
                    'netopia', 'netopia-payments', 'payment-netopia' => 'netopia',
                    'stripe', 'stripe-payments', 'payment-stripe' => 'stripe',
                    'euplatesc', 'payment-euplatesc' => 'euplatesc',
                    'payu', 'payment-payu' => 'payu',
                    default => $defaultPaymentMethod?->slug ?? 'netopia',
                };
            }

            $paymentConfig = $client->getPaymentMethodSettings($processorType)
                ?? $client->getPaymentMethodSettings('netopia')
                ?? $client->getPaymentMethodSettings('netopia-payments')
                ?? $client->getPaymentMethodSettings('payment-netopia');

            if (!$paymentConfig) {
                throw new \Exception('Payment configuration not found for callback');
            }

            $processor = PaymentProcessorFactory::makeFromArray($processorType, $paymentConfig);

            // Verify and process the callback
            $result = $processor->processCallback($request->all(), $request->headers->all());

            if ($result['status'] === 'success') {
                // SECURITY FIX: Idempotency check - prevent double-spending via webhook replay
                if ($order->payment_status === 'paid') {
                    \Log::info('Payment callback received for already paid order', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                    ]);
                    return response()->json([
                        'success' => true,
                        'message' => 'Order already paid',
                        'data' => ['order_number' => $order->order_number],
                    ]);
                }

                // Payment successful - save transaction ID from processor
                $order->update([
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                    'payment_reference' => $result['transaction_id'] ?? $result['payment_id'] ?? $order->payment_reference,
                ]);

                // Activate tickets
                $order->tickets()->update(['status' => 'valid']);

                // Record financial transactions for organizer balance
                if ($order->marketplace_organizer_id && $order->marketplace_client_id) {
                    $organizer = $order->marketplaceOrganizer;
                    $commissionRate = $organizer->getEffectiveCommissionRate();
                    $grossAmount = (float) $order->total;
                    $commissionAmount = round($grossAmount * $commissionRate / 100, 2);

                    MarketplaceTransaction::recordSale(
                        $order->marketplace_client_id,
                        $order->marketplace_organizer_id,
                        $grossAmount,
                        $commissionAmount,
                        $order->id,
                        $order->currency
                    );

                    // Update organizer stats
                    $organizer->updateStats();
                }

                // Send webhook notification
                if ($order->marketplaceClient) {
                    dispatch(function () use ($order) {
                        app(\App\Services\MarketplaceWebhookService::class)->orderConfirmed(
                            $order->marketplaceClient,
                            [
                                'order_id' => $order->id,
                                'order_number' => $order->order_number,
                                'status' => 'completed',
                                'payment_status' => 'paid',
                                'paid_at' => $order->paid_at->toIso8601String(),
                            ]
                        );
                    })->afterResponse();
                }

                // Send order confirmation email to customer
                if ($order->customer_email) {
                    dispatch(function () use ($order) {
                        Notification::route('mail', $order->customer_email)
                            ->notify(new MarketplaceOrderNotification($order, 'confirmed'));
                    })->afterResponse();
                }

                // Award XP for ticket purchase (gamification)
                $this->awardPurchaseXp($order);

                Log::channel('marketplace')->info('Payment completed for marketplace order', [
                    'order_id' => $order->id,
                    'client_slug' => $clientSlug,
                ]);

                return $this->success([
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => 'completed',
                ], 'Payment successful');

            } else {
                // Payment failed or pending
                $errorMessage = $result['metadata']['error_message'] ?? $result['message'] ?? 'Payment failed';
                $order->update([
                    'payment_status' => $result['status'] === 'pending' ? 'pending' : 'failed',
                    'payment_error' => $errorMessage,
                    'payment_reference' => $result['transaction_id'] ?? $result['payment_id'] ?? $order->payment_reference,
                ]);

                Log::channel('marketplace')->warning('Payment failed/pending for marketplace order', [
                    'order_id' => $order->id,
                    'client_slug' => $clientSlug,
                    'status' => $result['status'],
                    'error' => $errorMessage,
                ]);

                return $this->error($errorMessage, 400);
            }

        } catch (\Exception $e) {
            Log::channel('marketplace')->error('Error processing payment callback', [
                'order_id' => $order->id,
                'client_slug' => $clientSlug,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Error processing payment', 500);
        }
    }

    /**
     * Check payment status for an order
     */
    public function status(Request $request, int $orderId): JsonResponse
    {
        $client = $this->requireClient($request);

        $order = Order::where('id', $orderId)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        return $this->success([
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'expires_at' => $order->expires_at?->toIso8601String(),
            'is_expired' => $order->expires_at && $order->expires_at->isPast(),
        ]);
    }

    /**
     * Award XP for ticket purchase (and first purchase bonus)
     */
    protected function awardPurchaseXp(Order $order): void
    {
        // Only award XP if we have a marketplace customer
        if (!$order->marketplace_customer_id || !$order->marketplace_client_id) {
            return;
        }

        try {
            $experienceService = app(ExperienceService::class);
            $customerId = $order->marketplace_customer_id;
            $marketplaceClientId = $order->marketplace_client_id;
            $purchaseAmount = (float) $order->total;

            // Award ticket_purchase XP (based on amount spent)
            $experienceService->awardActionXpForMarketplace(
                $marketplaceClientId,
                $customerId,
                ExperienceAction::ACTION_TICKET_PURCHASE,
                $purchaseAmount,
                [
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'description' => [
                        'en' => "Ticket purchase - Order #{$order->order_number}",
                        'ro' => "Achiziție bilete - Comanda #{$order->order_number}",
                    ],
                ]
            );

            // Check for first purchase bonus
            $previousOrders = Order::where('marketplace_customer_id', $customerId)
                ->where('marketplace_client_id', $marketplaceClientId)
                ->where('id', '!=', $order->id)
                ->where('payment_status', 'paid')
                ->count();

            if ($previousOrders === 0) {
                // This is their first successful purchase - award first_purchase bonus
                $experienceService->awardActionXpForMarketplace(
                    $marketplaceClientId,
                    $customerId,
                    ExperienceAction::ACTION_FIRST_PURCHASE,
                    $purchaseAmount,
                    [
                        'reference_type' => Order::class,
                        'reference_id' => $order->id,
                        'description' => [
                            'en' => "First purchase bonus",
                            'ro' => "Bonus primă achiziție",
                        ],
                    ]
                );
            }

            Log::channel('marketplace')->info('XP awarded for purchase', [
                'order_id' => $order->id,
                'customer_id' => $customerId,
                'amount' => $purchaseAmount,
                'is_first_purchase' => $previousOrders === 0,
            ]);

        } catch (\Exception $e) {
            // Log but don't fail the payment callback for XP issues
            Log::channel('marketplace')->warning('Failed to award XP for purchase', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
