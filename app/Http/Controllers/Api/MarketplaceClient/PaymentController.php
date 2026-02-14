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

                // Send order confirmation email with embedded tickets
                if ($order->customer_email && $order->marketplaceClient) {
                    try {
                        $this->sendOrderConfirmationEmail($order);
                    } catch (\Throwable $e) {
                        Log::channel('marketplace')->error('Failed to send order confirmation email', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                            'file' => $e->getFile() . ':' . $e->getLine(),
                        ]);
                    }
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
     * Send order confirmation email with embedded ticket details
     */
    protected function sendOrderConfirmationEmail(Order $order): void
    {
        $marketplace = $order->marketplaceClient;
        $order->load(['tickets.marketplaceEvent', 'tickets.marketplaceTicketType']);

        $customerName = $order->customer_name ?? 'Client';
        $customerEmail = $order->customer_email;
        $marketplaceName = $marketplace->name;
        $orderNumber = $order->order_number;
        $totalAmount = number_format($order->total, 2, ',', '.') . ' ' . ($order->currency ?? 'RON');

        // Group tickets by marketplace event
        $ticketsByEvent = [];
        foreach ($order->tickets as $ticket) {
            $eventId = $ticket->marketplace_event_id ?? 0;
            if (!isset($ticketsByEvent[$eventId])) {
                $ticketsByEvent[$eventId] = [
                    'event' => $ticket->marketplaceEvent,
                    'tickets' => [],
                ];
            }
            $ticketsByEvent[$eventId]['tickets'][] = $ticket;
        }

        // Build tickets HTML
        $ticketsHtml = '';
        foreach ($ticketsByEvent as $group) {
            $event = $group['event'];
            $eventName = $event->name ?? 'Eveniment';
            $eventDate = $event?->starts_at?->format('d.m.Y') ?? '';
            $eventStartTime = $event?->starts_at?->format('H:i') ?? '';
            $doorsOpenTime = $event?->doors_open_at?->format('H:i') ?? '';
            $venueName = $event->venue_name ?? '';
            $venueCity = $event->venue_city ?? '';

            $ticketsHtml .= '<div style="margin-bottom:30px;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;">';

            // Event header
            $ticketsHtml .= '<div style="background:#1a1a2e;color:#ffffff;padding:20px 24px;">';
            $ticketsHtml .= '<h2 style="margin:0 0 8px;font-size:20px;font-weight:700;">' . e($eventName) . '</h2>';
            $locationParts = array_filter([$venueName, $venueCity]);
            if ($locationParts) {
                $ticketsHtml .= '<p style="margin:0 0 4px;font-size:14px;color:#b0b0cc;">' . e(implode(', ', $locationParts)) . '</p>';
            }
            $dateParts = [];
            if ($eventDate) $dateParts[] = $eventDate;
            if ($doorsOpenTime) $dateParts[] = 'Deschidere porți: ' . $doorsOpenTime;
            if ($eventStartTime) $dateParts[] = 'Ora început: ' . $eventStartTime;
            if ($dateParts) {
                $ticketsHtml .= '<p style="margin:0;font-size:14px;color:#b0b0cc;">' . e(implode(' | ', $dateParts)) . '</p>';
            }
            $ticketsHtml .= '</div>';

            // Individual tickets
            foreach ($group['tickets'] as $ticket) {
                $ticketCode = $ticket->code ?? $ticket->barcode ?? '';
                $attendeeName = $ticket->attendee_name ?? $customerName;
                $ticketTypeName = $ticket->marketplaceTicketType?->name ?? '';
                $ticketPrice = number_format($ticket->price ?? 0, 2, ',', '.') . ' ' . ($order->currency ?? 'RON');
                $seatDetails = $ticket->getSeatDetails();

                $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
                    'size' => '180x180',
                    'data' => $ticketCode,
                    'color' => '1a1a2e',
                    'margin' => '0',
                    'format' => 'png',
                ]);

                // Use table layout for email client compatibility (Outlook, Gmail don't support flexbox)
                $ticketsHtml .= '<table style="width:100%;border-top:1px dashed #d0d0d0;" cellpadding="0" cellspacing="0"><tr>';

                // Left: QR code
                $ticketsHtml .= '<td style="padding:20px 20px 20px 24px;width:170px;vertical-align:top;text-align:center;">';
                $ticketsHtml .= '<img src="' . $qrUrl . '" alt="QR Code" width="150" height="150" style="display:block;border:1px solid #eee;border-radius:8px;" />';
                $ticketsHtml .= '<p style="margin:6px 0 0;font-size:12px;color:#666;font-family:monospace;">' . e($ticketCode) . '</p>';
                $ticketsHtml .= '</td>';

                // Right: ticket details
                $ticketsHtml .= '<td style="padding:20px 24px 20px 0;vertical-align:top;">';
                if ($ticketTypeName) {
                    $ticketsHtml .= '<p style="margin:0 0 6px;font-size:16px;font-weight:700;color:#1a1a2e;">' . e($ticketTypeName) . '</p>';
                }
                $ticketsHtml .= '<table style="border-collapse:collapse;font-size:14px;color:#333;" cellpadding="0" cellspacing="0">';

                $ticketsHtml .= '<tr><td style="padding:3px 12px 3px 0;color:#888;">Beneficiar:</td><td style="padding:3px 0;font-weight:600;">' . e($attendeeName) . '</td></tr>';
                $ticketsHtml .= '<tr><td style="padding:3px 12px 3px 0;color:#888;">Preț:</td><td style="padding:3px 0;">' . $ticketPrice . '</td></tr>';

                if ($seatDetails) {
                    $seatParts = [];
                    if (!empty($seatDetails['section_name'])) $seatParts[] = $seatDetails['section_name'];
                    if (!empty($seatDetails['row_label'])) $seatParts[] = 'Rând ' . $seatDetails['row_label'];
                    if (!empty($seatDetails['seat_number'])) $seatParts[] = 'Loc ' . $seatDetails['seat_number'];
                    if ($seatParts) {
                        $ticketsHtml .= '<tr><td style="padding:3px 12px 3px 0;color:#888;">Loc:</td><td style="padding:3px 0;font-weight:600;">' . e(implode(' / ', $seatParts)) . '</td></tr>';
                    }
                }

                $ticketsHtml .= '</table>';
                $ticketsHtml .= '</td>';
                $ticketsHtml .= '</tr></table>';
            }

            $ticketsHtml .= '</div>';
        }

        // Build full email HTML
        $subject = "Confirmare comandă #{$orderNumber} — {$marketplaceName}";

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>';
        $html .= '<body style="margin:0;padding:0;background:#f4f4f8;font-family:Arial,Helvetica,sans-serif;">';
        $html .= '<div style="max-width:640px;margin:0 auto;padding:24px 16px;">';

        // Header
        $html .= '<div style="text-align:center;padding:20px 0;">';
        $html .= '<h1 style="margin:0;font-size:24px;color:#1a1a2e;">' . e($marketplaceName) . '</h1>';
        $html .= '</div>';

        // Greeting
        $html .= '<div style="background:#ffffff;border-radius:12px;padding:24px;margin-bottom:20px;">';
        $html .= '<p style="margin:0 0 12px;font-size:16px;color:#333;">Salut, <strong>' . e($customerName) . '</strong>!</p>';
        $html .= '<p style="margin:0 0 12px;font-size:15px;color:#555;">Mulțumim pentru achiziție! Comanda ta <strong>#' . e($orderNumber) . '</strong> a fost confirmată.</p>';
        $html .= '<p style="margin:0;font-size:15px;color:#555;">Mai jos găsești biletele tale. Prezintă codul QR la intrarea în eveniment.</p>';
        $html .= '</div>';

        // Tickets
        $html .= $ticketsHtml;

        // Order summary
        $html .= '<div style="background:#ffffff;border-radius:12px;padding:20px 24px;margin-top:20px;">';
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:14px;" cellpadding="0" cellspacing="0">';
        $html .= '<tr><td style="padding:6px 0;color:#888;">Comandă:</td><td style="padding:6px 0;text-align:right;font-weight:600;">#' . e($orderNumber) . '</td></tr>';
        $html .= '<tr><td style="padding:6px 0;color:#888;">Bilete:</td><td style="padding:6px 0;text-align:right;">' . $order->tickets->count() . '</td></tr>';
        $html .= '<tr style="border-top:1px solid #eee;"><td style="padding:10px 0 6px;font-weight:700;font-size:16px;">Total:</td><td style="padding:10px 0 6px;text-align:right;font-weight:700;font-size:16px;color:#1a1a2e;">' . $totalAmount . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';

        // Footer
        $html .= '<div style="text-align:center;padding:24px 0;font-size:12px;color:#999;">';
        $html .= '<p style="margin:0;">Acest email a fost trimis de ' . e($marketplaceName) . '</p>';
        $html .= '</div>';

        $html .= '</div></body></html>';

        // Try to use marketplace email template for subject customization
        $template = \App\Models\MarketplaceEmailTemplate::where('marketplace_client_id', $marketplace->id)
            ->where('slug', 'ticket_purchase')
            ->where('is_active', true)
            ->first();

        if ($template) {
            $vars = [
                'customer_name' => $customerName,
                'order_number' => $orderNumber,
                'total_amount' => $totalAmount,
                'marketplace_name' => $marketplaceName,
                'tickets_list' => $ticketsHtml,
            ];
            $rendered = $template->render($vars);
            if (!empty($rendered['subject'])) {
                $subject = $rendered['subject'];
            }
            // If template has body_html, use it (with tickets_list injected via variable)
            if (!empty($rendered['body_html'])) {
                $html = $rendered['body_html'];
            }
        }

        // Send email
        $fromAddress = $marketplace->getEmailFromAddress();
        $fromName = $marketplace->getEmailFromName();

        // Create email log
        $log = \App\Models\MarketplaceEmailLog::create([
            'marketplace_client_id' => $marketplace->id,
            'marketplace_customer_id' => $order->marketplace_customer_id,
            'order_id' => $order->id,
            'template_slug' => 'ticket_purchase',
            'to_email' => $customerEmail,
            'to_name' => $customerName,
            'from_email' => $fromAddress,
            'from_name' => $fromName,
            'subject' => $subject,
            'body_html' => $html,
            'status' => 'pending',
        ]);

        try {
            if ($marketplace->hasMailConfigured()) {
                $transport = $marketplace->getMailTransport();
                if ($transport) {
                    $email = (new \Symfony\Component\Mime\Email())
                        ->from(new \Symfony\Component\Mime\Address($fromAddress, $fromName))
                        ->to(new \Symfony\Component\Mime\Address($customerEmail, $customerName))
                        ->subject($subject)
                        ->html($html);

                    $transport->send($email);
                    $log->markSent();

                    Log::channel('marketplace')->info('Order confirmation email sent via marketplace transport', [
                        'order_id' => $order->id,
                        'to' => $customerEmail,
                    ]);
                    return;
                }
            }

            // Fallback to Laravel default mailer
            \Illuminate\Support\Facades\Mail::html($html, function ($message) use ($customerEmail, $customerName, $subject) {
                $message->to($customerEmail, $customerName)
                        ->subject($subject);
            });

            $log->markSent();

            Log::channel('marketplace')->info('Order confirmation email sent via Laravel default mailer', [
                'order_id' => $order->id,
                'to' => $customerEmail,
            ]);

        } catch (\Exception $e) {
            $log->markFailed($e->getMessage());
            throw $e;
        }
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
