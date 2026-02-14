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

        // Log payment config for debugging (mask sensitive values)
        Log::channel('marketplace')->info('Payment config loaded', [
            'order_id' => $order->id,
            'client_id' => $client->id,
            'microservice_slug' => $defaultPaymentMethod->slug,
            'processor_type' => $processorType,
            'config_keys' => array_keys($paymentConfig),
            'has_signature' => !empty($paymentConfig['netopia_signature'] ?? $paymentConfig['signature'] ?? null),
            'has_public_key' => !empty($paymentConfig['netopia_public_key'] ?? $paymentConfig['public_key'] ?? null),
            'has_api_key' => !empty($paymentConfig['netopia_api_key'] ?? $paymentConfig['private_key'] ?? null),
            'mode' => $paymentConfig['mode'] ?? 'not set',
        ]);

        try {
            $processor = PaymentProcessorFactory::makeFromArray($processorType, $paymentConfig);

            // Get event title for description (title is a translatable JSON field)
            $event = $order->event;
            $eventTitle = 'Event';
            if ($event) {
                $title = $event->getTranslation('title', 'ro');
                if (is_string($title) && $title !== '') {
                    $eventTitle = $title;
                } elseif (is_array($title)) {
                    $eventTitle = $title['ro'] ?? $title['en'] ?? reset($title) ?: 'Event';
                }
            }

            $paymentData = $processor->createPayment([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'amount' => $order->total,
                'currency' => $order->currency,
                'customer_email' => $order->customer_email,
                'customer_name' => $order->customer_name,
                'description' => "Bilete pentru {$eventTitle}",
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
                'processor' => $processorType,
                'error' => $e->getMessage(),
                'config_keys_present' => array_keys($paymentConfig),
            ]);

            return $this->error('Failed to initiate payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Handle payment callback from payment processor
     */
    public function callback(Request $request, string $clientSlug): JsonResponse|\Illuminate\Http\Response
    {
        Log::channel('marketplace')->info('Payment callback received', [
            'client_slug' => $clientSlug,
            'request_keys' => array_keys($request->all()),
        ]);

        try {
            // Find marketplace client first (needed to decrypt Netopia callbacks)
            $client = \App\Models\MarketplaceClient::where('slug', $clientSlug)->first();
            if (!$client) {
                Log::channel('marketplace')->error('Marketplace client not found for callback', [
                    'client_slug' => $clientSlug,
                ]);
                return $this->error('Client not found', 404);
            }

            // Determine processor type
            $defaultPaymentMethod = $client->getDefaultPaymentMethod();
            if (!$defaultPaymentMethod) {
                throw new \Exception('No payment method configured for client: ' . $clientSlug);
            }

            $processorType = match ($defaultPaymentMethod->slug) {
                'netopia', 'netopia-payments', 'payment-netopia' => 'netopia',
                'stripe', 'stripe-payments', 'payment-stripe' => 'stripe',
                'euplatesc', 'payment-euplatesc' => 'euplatesc',
                'payu', 'payment-payu' => 'payu',
                default => $defaultPaymentMethod->slug,
            };

            $paymentConfig = $client->getPaymentMethodSettings($defaultPaymentMethod->slug);
            if (!$paymentConfig) {
                throw new \Exception('Payment configuration not found for callback');
            }

            $processor = PaymentProcessorFactory::makeFromArray($processorType, $paymentConfig);

            // Process the callback (decrypt for Netopia, verify for others)
            try {
                $result = $processor->processCallback($request->all(), $request->headers->all());
            } catch (\Throwable $decryptError) {
                Log::channel('marketplace')->error('Callback: processCallback failed', [
                    'error' => $decryptError->getMessage(),
                    'error_class' => get_class($decryptError),
                    'file' => $decryptError->getFile() . ':' . $decryptError->getLine(),
                ]);
                throw $decryptError;
            }

            Log::channel('marketplace')->info('Payment callback processed', [
                'client_slug' => $clientSlug,
                'processor' => $processorType,
                'status' => $result['status'],
                'order_id_from_callback' => $result['order_id'] ?? $result['payment_id'] ?? 'unknown',
            ]);

            // Find order using the order ID from the decrypted callback data
            $callbackOrderId = $result['order_id'] ?? $result['payment_id'] ?? null;
            $orderId = $request->input('order_id') ?? $request->input('orderId');
            $orderNumber = $request->input('order_number') ?? $request->input('orderNumber');

            $order = Order::where(function ($q) use ($callbackOrderId, $orderId, $orderNumber) {
                    // Try callback order ID as order_number first (Netopia uses our order_number as ID)
                    if ($callbackOrderId) {
                        $q->where('order_number', $callbackOrderId)
                          ->orWhere('payment_reference', $callbackOrderId);
                    }
                    if ($orderId) {
                        $q->orWhere('id', $orderId);
                    }
                    if ($orderNumber) {
                        $q->orWhere('order_number', $orderNumber);
                    }
                })
                ->where('marketplace_client_id', $client->id)
                ->first();

            if (!$order) {
                Log::channel('marketplace')->error('Order not found for payment callback', [
                    'client_slug' => $clientSlug,
                    'callback_order_id' => $callbackOrderId,
                    'request_order_id' => $orderId,
                    'request_order_number' => $orderNumber,
                ]);
                return $this->error('Order not found', 404);
            }

            Log::channel('marketplace')->info('Order found for callback', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'current_status' => $order->status,
                'callback_status' => $result['status'],
            ]);

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

                // Confirm seat purchases (held → sold) now that payment is confirmed
                $seatedItems = $order->meta['seated_items'] ?? [];
                if (!empty($seatedItems)) {
                    $seatHoldService = app(\App\Services\Seating\SeatHoldService::class);

                    foreach ($seatedItems as $seatedItem) {
                        try {
                            $confirmResult = $seatHoldService->confirmPurchase(
                                $seatedItem['event_seating_id'],
                                $seatedItem['seat_uids'],
                                'payment-confirmed',
                                (int) ($order->total * 100)
                            );

                            if (!empty($confirmResult['failed'])) {
                                Log::channel('marketplace')->warning('Some seats could not be confirmed after payment', [
                                    'order_id' => $order->id,
                                    'event_seating_id' => $seatedItem['event_seating_id'],
                                    'failed' => $confirmResult['failed'],
                                    'confirmed' => $confirmResult['confirmed'],
                                ]);
                            } else {
                                Log::channel('marketplace')->info('Seats confirmed after payment', [
                                    'order_id' => $order->id,
                                    'event_seating_id' => $seatedItem['event_seating_id'],
                                    'confirmed_count' => count($confirmResult['confirmed']),
                                ]);
                            }
                        } catch (\Exception $e) {
                            // Log but don't fail the payment callback - payment is already confirmed
                            Log::channel('marketplace')->error('Failed to confirm seats after payment', [
                                'order_id' => $order->id,
                                'event_seating_id' => $seatedItem['event_seating_id'],
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

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

                // Send order confirmation email synchronously (IPN callback — no user waiting)
                if ($order->customer_email && $order->marketplaceClient) {
                    try {
                        $marketplace = $order->marketplaceClient;
                        $customerEmail = $order->customer_email;

                        Log::channel('marketplace')->info('Sending order confirmation email', [
                            'order_id' => $order->id,
                            'customer_email' => $customerEmail,
                            'marketplace_id' => $marketplace->id,
                        ]);

                        // Build email content
                        $notification = new MarketplaceOrderNotification($order, 'confirmed');
                        $mailMessage = $notification->toMail($order);
                        $subject = $mailMessage->subject ?? "Order Confirmed - {$order->order_number}";

                        // Try render() first, fall back to manual HTML
                        $html = null;
                        try {
                            $html = (string) $mailMessage->render();
                            Log::channel('marketplace')->info('Email rendered via MailMessage::render()', [
                                'order_id' => $order->id,
                                'html_length' => strlen($html),
                            ]);
                        } catch (\Throwable $renderError) {
                            Log::channel('marketplace')->warning('MailMessage::render() failed, building HTML manually', [
                                'order_id' => $order->id,
                                'error' => $renderError->getMessage(),
                            ]);
                        }

                        // Manual HTML fallback
                        if (!$html) {
                            $greeting = $mailMessage->greeting ?? 'Hello!';
                            $lines = array_merge($mailMessage->introLines ?? [], $mailMessage->outroLines ?? []);
                            $actionText = $mailMessage->actionText ?? null;
                            $actionUrl = $mailMessage->actionUrl ?? null;

                            $bodyHtml = "<h2 style=\"color:#1a1a2e;margin-bottom:16px\">{$greeting}</h2>";
                            foreach ($lines as $line) {
                                $bodyHtml .= "<p style=\"color:#333;line-height:1.6;margin:8px 0\">{$line}</p>";
                            }
                            if ($actionText && $actionUrl) {
                                $bodyHtml .= "<p style=\"margin:24px 0;text-align:center\"><a href=\"{$actionUrl}\" style=\"display:inline-block;padding:12px 24px;background:#A51C30;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold\">{$actionText}</a></p>";
                            }

                            $html = "<!DOCTYPE html><html><head><meta charset=\"utf-8\"></head><body style=\"font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:600px;margin:0 auto;padding:20px;background:#f8fafc\">"
                                . "<div style=\"background:#fff;border-radius:8px;padding:32px;border:1px solid #e2e8f0\">{$bodyHtml}</div>"
                                . "<p style=\"text-align:center;color:#94a3b8;font-size:12px;margin-top:16px\">{$marketplace->name}</p>"
                                . "</body></html>";

                            Log::channel('marketplace')->info('Email built with manual HTML', [
                                'order_id' => $order->id,
                                'html_length' => strlen($html),
                            ]);
                        }

                        // Try marketplace-specific transport first
                        $sent = false;
                        if ($marketplace->hasMailConfigured()) {
                            try {
                                $transport = $marketplace->getMailTransport();
                                if ($transport) {
                                    $fromAddress = $marketplace->getEmailFromAddress();
                                    $fromName = $marketplace->getEmailFromName();

                                    Log::channel('marketplace')->info('Sending via marketplace transport', [
                                        'order_id' => $order->id,
                                        'from' => $fromAddress,
                                        'from_name' => $fromName,
                                    ]);

                                    $email = (new \Symfony\Component\Mime\Email())
                                        ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
                                        ->to($customerEmail)
                                        ->subject($subject)
                                        ->html($html);

                                    $transport->send($email);
                                    $sent = true;

                                    Log::channel('marketplace')->info('Order confirmation email sent via marketplace transport', [
                                        'order_id' => $order->id,
                                        'customer_email' => $customerEmail,
                                    ]);
                                } else {
                                    Log::channel('marketplace')->warning('Marketplace transport returned null', [
                                        'order_id' => $order->id,
                                        'marketplace_id' => $marketplace->id,
                                    ]);
                                }
                            } catch (\Throwable $transportError) {
                                Log::channel('marketplace')->error('Marketplace transport failed, will try fallback', [
                                    'order_id' => $order->id,
                                    'error' => $transportError->getMessage(),
                                ]);
                            }
                        } else {
                            Log::channel('marketplace')->info('No marketplace mail configured, using Laravel default', [
                                'order_id' => $order->id,
                                'marketplace_id' => $marketplace->id,
                            ]);
                        }

                        // Fallback: use Laravel's default mailer
                        if (!$sent) {
                            \Illuminate\Support\Facades\Mail::html($html, function ($message) use ($customerEmail, $subject, $marketplace) {
                                $message->to($customerEmail)
                                        ->subject($subject)
                                        ->from(
                                            $marketplace->getEmailFromAddress(),
                                            $marketplace->getEmailFromName()
                                        );
                            });

                            Log::channel('marketplace')->info('Order confirmation email sent via Laravel default mailer', [
                                'order_id' => $order->id,
                                'customer_email' => $customerEmail,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        Log::channel('marketplace')->error('Failed to send order confirmation email', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                            'file' => $e->getFile() . ':' . $e->getLine(),
                        ]);
                    }
                } else {
                    Log::channel('marketplace')->warning('Cannot send order confirmation email - missing data', [
                        'order_id' => $order->id,
                        'has_email' => (bool) $order->customer_email,
                        'has_client' => (bool) $order->marketplaceClient,
                    ]);
                }

                // Award XP for ticket purchase (gamification)
                $this->awardPurchaseXp($order);

                Log::channel('marketplace')->info('Payment completed for marketplace order', [
                    'order_id' => $order->id,
                    'client_slug' => $clientSlug,
                ]);

                return $this->netopiaResponse(0);

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

                return $this->netopiaResponse(0);
            }

        } catch (\Throwable $e) {
            Log::channel('marketplace')->error('Error processing payment callback', [
                'client_slug' => $clientSlug,
                'order_id' => isset($order) ? $order->id : null,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return temporary error so Netopia retries
            return $this->netopiaResponse(1, $e->getMessage());
        }
    }

    /**
     * Return Netopia-expected XML response for IPN
     * error_code 0 = OK, error_type 1 = temp error (retry), 2 = permanent error
     */
    protected function netopiaResponse(int $errorCode = 0, string $message = 'OK'): \Illuminate\Http\Response
    {
        $errorType = $errorCode === 0 ? '1' : '1'; // type 1 = temporary (allows retry)
        $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n"
             . '<crc error_type="' . $errorType . '" error_code="' . $errorCode . '">' . htmlspecialchars($message) . '</crc>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
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
