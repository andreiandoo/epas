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
use Illuminate\Support\Facades\DB;
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

            // Check if this callback is for a service order (SVC- prefix)
            $callbackOrderId = $result['order_id'] ?? $result['payment_id'] ?? null;
            if ($callbackOrderId && str_starts_with((string) $callbackOrderId, 'SVC-')) {
                return $this->handleServiceOrderCallback($result, $client, $callbackOrderId);
            }

            // Find order using the order ID from the decrypted callback data
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
                // SECURITY FIX: Idempotency check - prevent double-spending via webhook replay.
                //
                // Must return Netopia's XML success ack here, not a JSON 200.
                // Netopia treats any non-XML body (or any error_code != 0) as
                // a temporary failure and retries the callback indefinitely
                // — that's why orders showed "Plătită" (paid, but pending
                // confirmation in Netopia's view) instead of "Confirmată".
                // Same XML ack as the happy path below.
                if ($order->payment_status === 'paid') {
                    \Log::info('Payment callback received for already paid order', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                    ]);
                    return $this->netopiaResponse(0);
                }

                // Activate tickets FIRST so a misbehaving OrderObserver can't
                // strand them in 'pending'. The order update below fires the
                // observer chain (notifySale, trackPurchase, ...); if any
                // listener throws and isn't caught, code after the update
                // doesn't run. We saw exactly that on 78 paid orders where
                // notifySale threw on a typo'd relation. Bypass the observer
                // for the ticket flip — DB::table avoids triggering Ticket
                // observers and is idempotent under retries.
                DB::table('tickets')
                    ->where('order_id', $order->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'valid', 'updated_at' => now()]);

                // Payment successful - save transaction ID from processor
                $order->update([
                    'status' => 'completed',
                    'payment_status' => 'paid',
                    'paid_at' => now(),
                    'payment_reference' => $result['transaction_id'] ?? $result['payment_id'] ?? $order->payment_reference,
                ]);

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

                    // Send individual ticket emails to beneficiaries (attendees with different email)
                    try {
                        $this->sendBeneficiaryEmails($order);
                    } catch (\Throwable $e) {
                        Log::channel('marketplace')->error('Failed to send beneficiary emails', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Award XP for ticket purchase (gamification)
                $this->awardPurchaseXp($order);

                // Send SMS ticket confirmation (if marketplace has SMS service active)
                if ($order->customer_phone && $order->marketplaceClient) {
                    \App\Jobs\SendTicketConfirmationSmsJob::dispatch($order->id);
                }

                Log::channel('marketplace')->info('Payment completed for marketplace order', [
                    'order_id' => $order->id,
                    'client_slug' => $clientSlug,
                ]);

                return $this->netopiaResponse(0);

            } else {
                // Payment failed or pending
                $errorMessage = $result['metadata']['error_message'] ?? $result['message'] ?? 'Payment failed';
                $isFailed = $result['status'] !== 'pending';
                $order->update([
                    'status' => $isFailed ? 'failed' : $order->status,
                    'payment_status' => $isFailed ? 'failed' : 'pending',
                    'payment_error' => $errorMessage,
                    'payment_reference' => $result['transaction_id'] ?? $result['payment_id'] ?? $order->payment_reference,
                ]);

                // info, not warning — payment failures (3DS rejection,
                // insufficient funds, declined cards) are normal business
                // events. Customer sees the message and can retry. Keep
                // the audit trail in marketplace.log but don't surface
                // these in the system_errors dashboard.
                Log::channel('marketplace')->info('Payment failed/pending for marketplace order', [
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
        // Netopia error_type semantics:
        //   0 = success (no retry, mark transaction Confirmată)
        //   1 = temporary error (retry the callback)
        //   2 = permanent error (no retry, mark Eșuată)
        // The previous "0 ? '1' : '1'" was a copy-paste bug that made every
        // success response look temporary to Netopia, so callbacks looped
        // forever and orders stayed "Plătită" instead of being acked into
        // "Confirmată".
        $errorType = $errorCode === 0 ? '0' : '1';
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
    public function sendOrderConfirmationEmail(Order $order): void
    {
        $marketplace = $order->marketplaceClient;
        $order->load(['tickets.marketplaceEvent', 'tickets.marketplaceTicketType', 'tickets.ticketType', 'marketplaceEvent']);

        $customerName = $order->customer_name ?? 'Client';
        $customerEmail = $order->customer_email;
        $marketplaceName = $marketplace->name;
        $orderNumber = $order->order_number;
        $currency = $order->currency ?? 'RON';

        // Calculate total including insurance
        $insuranceAmount = $order->meta['insurance_amount'] ?? 0;
        $totalAmount = number_format($order->total, 2, ',', '.') . ' ' . $currency;

        // Resolve event data — fallback chain: ticket → order → Event model
        $resolveEvent = function ($ticket) use ($order) {
            // 1. From ticket's marketplace_event_id
            if ($ticket->marketplaceEvent) {
                return $ticket->marketplaceEvent;
            }
            // 2. From order's marketplace_event_id
            if ($order->marketplaceEvent) {
                return $order->marketplaceEvent;
            }
            // 3. From ticket's event_id via Event model → build a fake object with matching fields
            $eventId = $ticket->event_id ?? $order->event_id;
            if ($eventId) {
                $event = \App\Models\Event::find($eventId);
                if ($event) {
                    // Check if there's a MarketplaceEvent with same event_id
                    $mke = \App\Models\MarketplaceEvent::where('marketplace_client_id', $order->marketplace_client_id)
                        ->where('id', $eventId)
                        ->first();
                    if ($mke) {
                        return $mke;
                    }
                    // Build starts_at from event_date + start_time
                    $startsAt = null;
                    $eventDate = $event->event_date ?? $event->range_start_date;
                    $startTime = $event->start_time ?? $event->range_start_time ?? '00:00';
                    if ($eventDate) {
                        $dateStr = $eventDate instanceof \Carbon\Carbon ? $eventDate->format('Y-m-d') : $eventDate;
                        $startsAt = \Carbon\Carbon::parse($dateStr . ' ' . $startTime);
                    }
                    $doorsOpenAt = null;
                    if ($eventDate && $event->door_time) {
                        $dateStr = $eventDate instanceof \Carbon\Carbon ? $eventDate->format('Y-m-d') : $eventDate;
                        $doorsOpenAt = \Carbon\Carbon::parse($dateStr . ' ' . $event->door_time);
                    }

                    $venueName = '';
                    if ($event->venue) {
                        $vName = $event->venue->name;
                        $venueName = is_array($vName) ? ($vName['ro'] ?? $vName['en'] ?? reset($vName) ?: '') : ($vName ?? '');
                    }

                    return (object) [
                        'name' => is_array($event->title) ? ($event->title['ro'] ?? $event->title['en'] ?? reset($event->title)) : ($event->title ?? $event->name ?? 'Eveniment'),
                        'starts_at' => $startsAt,
                        'ends_at' => $event->ends_at ?? null,
                        'doors_open_at' => $doorsOpenAt,
                        'venue_name' => $venueName,
                        'venue_city' => $event->venue?->city ?? '',
                    ];
                }
            }
            return null;
        };

        // Group tickets by marketplace event
        $ticketsByEvent = [];
        foreach ($order->tickets as $ticket) {
            $event = $resolveEvent($ticket);
            $eventKey = $ticket->marketplace_event_id ?? $ticket->event_id ?? 0;
            if (!isset($ticketsByEvent[$eventKey])) {
                $ticketsByEvent[$eventKey] = [
                    'event' => $event,
                    'tickets' => [],
                ];
            }
            $ticketsByEvent[$eventKey]['tickets'][] = $ticket;
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
                $ttName = $ticket->marketplaceTicketType?->name ?? $ticket->ticketType?->name ?? '';
                $ticketTypeName = is_array($ttName) ? ($ttName['ro'] ?? $ttName['en'] ?? reset($ttName) ?: '') : ($ttName ?: 'Bilet');
                $ticketPrice = number_format($ticket->price ?? 0, 2, ',', '.') . ' ' . ($order->currency ?? 'RON');
                $seatDetails = $ticket->getSeatDetails();
                $ticketSeries = $ticket->meta['ticket_series'] ?? null;

                $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
                    'size' => '180x180',
                    'data' => $ticket->getVerifyUrl(),
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
                if ($ticketSeries) {
                    $ticketsHtml .= '<p style="margin:2px 0 0;font-size:11px;color:#888;font-family:monospace;">Serie: ' . e($ticketSeries) . '</p>';
                }
                $ticketsHtml .= '</td>';

                // Right: ticket details
                $ticketsHtml .= '<td style="padding:20px 24px 20px 0;vertical-align:top;">';
                if ($ticketTypeName) {
                    $ticketsHtml .= '<p style="margin:0 0 6px;font-size:16px;font-weight:700;color:#1a1a2e;">' . e($ticketTypeName) . '</p>';
                }
                $ticketsHtml .= '<table style="border-collapse:collapse;font-size:14px;color:#333;" cellpadding="0" cellspacing="0">';

                $ticketsHtml .= '<tr><td style="padding:3px 12px 3px 0;color:#888;">Beneficiar:</td><td style="padding:3px 0;font-weight:600;">' . e($attendeeName) . '</td></tr>';
                $ticketsHtml .= '<tr><td style="padding:3px 12px 3px 0;color:#888;">Preț:</td><td style="padding:3px 0;">' . $ticketPrice . '</td></tr>';

                if ($ticketSeries) {
                    $ticketsHtml .= '<tr><td style="padding:3px 12px 3px 0;color:#888;">Serie:</td><td style="padding:3px 0;font-weight:600;font-family:monospace;">' . e($ticketSeries) . '</td></tr>';
                }

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

        // Collect first event info for template variables
        $firstGroup = reset($ticketsByEvent);
        $firstEvent = $firstGroup['event'] ?? null;
        $firstEventName = $firstEvent->name ?? 'Eveniment';
        $firstEventDate = $firstEvent?->starts_at?->format('d.m.Y') ?? '';
        $firstEventTime = $firstEvent?->starts_at?->format('H:i') ?? '';
        $firstVenueName = $firstEvent->venue_name ?? '';
        $firstVenueCity = $firstEvent->venue_city ?? '';
        $firstVenueLocation = implode(', ', array_filter([$firstVenueName, $firstVenueCity]));
        $ticketCount = $order->tickets->count();

        // Build download URL — direct PDF download via proxy
        $marketplaceDomain = rtrim($marketplace->domain ?? '', '/');
        if ($marketplaceDomain && !str_starts_with($marketplaceDomain, 'http')) {
            $marketplaceDomain = 'https://' . $marketplaceDomain;
        }
        $downloadUrl = $marketplaceDomain
            ? $marketplaceDomain . '/api/proxy.php?action=order.download-tickets-pdf&order=' . urlencode($orderNumber)
            : '';

        // Build full email HTML (fallback when no template configured)
        $subject = "Confirmare comandă #{$orderNumber} — {$firstEventName}";

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

        // Event info summary
        $html .= '<div style="background:#ffffff;border-radius:12px;padding:20px 24px;margin-bottom:20px;">';
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:14px;" cellpadding="0" cellspacing="0">';
        $html .= '<tr><td style="padding:6px 0;color:#888;width:100px;">Eveniment:</td><td style="padding:6px 0;font-weight:600;">' . e($firstEventName) . '</td></tr>';
        if ($firstEventDate) {
            $html .= '<tr><td style="padding:6px 0;color:#888;">Data:</td><td style="padding:6px 0;">' . e($firstEventDate) . ($firstEventTime ? ' · ' . e($firstEventTime) : '') . '</td></tr>';
        }
        if ($firstVenueLocation) {
            $html .= '<tr><td style="padding:6px 0;color:#888;">Locație:</td><td style="padding:6px 0;">' . e($firstVenueLocation) . '</td></tr>';
        }
        $html .= '</table>';
        $html .= '</div>';

        // Tickets
        $html .= $ticketsHtml;

        // Download button
        if ($downloadUrl) {
            $html .= '<div style="text-align:center;margin:24px 0;">';
            $html .= '<a href="' . e($downloadUrl) . '" style="display:inline-block;background:#1a1a2e;color:#ffffff;font-size:16px;font-weight:700;padding:14px 32px;border-radius:8px;text-decoration:none;">Descarcă biletele</a>';
            $html .= '</div>';
        }

        // Order details section
        $discountAmount = (float) ($order->discount_amount ?? 0);
        $promoCodeMeta = $order->meta['promo_code'] ?? null;
        $commissionAmount = (float) ($order->commission_amount ?? 0);
        $commissionMode = $order->meta['commission_mode'] ?? 'included';
        $subtotalFormatted = number_format((float) $order->subtotal, 2, ',', '.') . ' ' . $currency;
        $orderDate = $order->paid_at ?? $order->created_at;

        $html .= '<div style="background:#ffffff;border-radius:12px;padding:20px 24px;margin-top:20px;">';
        $html .= '<h3 style="margin:0 0 12px;font-size:16px;font-weight:700;color:#1a1a2e;">Detalii comandă</h3>';
        $html .= '<table style="width:100%;border-collapse:collapse;font-size:14px;" cellpadding="0" cellspacing="0">';
        $html .= '<tr><td style="padding:6px 0;color:#888;width:150px;">Nr. comandă:</td><td style="padding:6px 0;font-weight:600;">#' . e($orderNumber) . '</td></tr>';
        $html .= '<tr><td style="padding:6px 0;color:#888;">Data comenzii:</td><td style="padding:6px 0;">' . $orderDate->format('d.m.Y H:i') . '</td></tr>';
        $html .= '<tr><td style="padding:6px 0;color:#888;">Nr. bilete:</td><td style="padding:6px 0;">' . $ticketCount . '</td></tr>';

        // Items breakdown
        $commissionDetails = $order->meta['commission_details'] ?? [];
        if (!empty($commissionDetails)) {
            $html .= '<tr><td colspan="2" style="padding:10px 0 4px;"><hr style="border:none;border-top:1px solid #eee;margin:0;"></td></tr>';
            foreach ($commissionDetails as $detail) {
                $itemName = $detail['ticket_type'] ?? 'Bilet';
                $itemQty = $detail['quantity'] ?? 1;
                $itemTotal = number_format($detail['total'] ?? 0, 2, ',', '.') . ' ' . $currency;
                $html .= '<tr><td style="padding:3px 0;color:#555;">' . e($itemName) . ' × ' . $itemQty . '</td><td style="padding:3px 0;text-align:right;">' . $itemTotal . '</td></tr>';
            }
        }

        $html .= '<tr><td colspan="2" style="padding:10px 0 4px;"><hr style="border:none;border-top:1px solid #eee;margin:0;"></td></tr>';
        $html .= '<tr><td style="padding:4px 0;color:#888;">Subtotal:</td><td style="padding:4px 0;text-align:right;">' . $subtotalFormatted . '</td></tr>';

        // Commission
        if ($commissionAmount > 0 && $commissionMode !== 'included') {
            $commissionFormatted = number_format($commissionAmount, 2, ',', '.') . ' ' . $currency;
            $html .= '<tr><td style="padding:4px 0;color:#888;">Comision serviciu:</td><td style="padding:4px 0;text-align:right;">' . $commissionFormatted . '</td></tr>';
        }

        // Insurance
        if ($insuranceAmount > 0) {
            $insuranceFormatted = number_format($insuranceAmount, 2, ',', '.') . ' ' . $currency;
            $html .= '<tr><td style="padding:4px 0;color:#888;">Asigurare retur:</td><td style="padding:4px 0;text-align:right;">' . $insuranceFormatted . '</td></tr>';
        }

        // Discount
        if ($discountAmount > 0) {
            $discountFormatted = '-' . number_format($discountAmount, 2, ',', '.') . ' ' . $currency;
            $discountLabel = 'Reducere';
            if ($promoCodeMeta && isset($promoCodeMeta['code'])) {
                $promoValue = '';
                if (($promoCodeMeta['type'] ?? '') === 'percentage') {
                    $promoValue = ' (' . ((float) ($promoCodeMeta['value'] ?? 0)) . '%)';
                }
                $discountLabel = 'Reducere cod ' . e($promoCodeMeta['code']) . $promoValue;
            }
            $html .= '<tr><td style="padding:4px 0;color:#16a34a;">' . $discountLabel . '</td><td style="padding:4px 0;text-align:right;color:#16a34a;">' . $discountFormatted . '</td></tr>';
        }

        // Total
        $html .= '<tr style="border-top:2px solid #1a1a2e;"><td style="padding:10px 0 6px;font-weight:700;font-size:16px;">Total plătit:</td><td style="padding:10px 0 6px;text-align:right;font-weight:700;font-size:16px;color:#1a1a2e;">' . $totalAmount . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';

        // Footer
        $html .= '<div style="text-align:center;padding:24px 0;font-size:12px;color:#999;">';
        $html .= '<p style="margin:0;">Acest email a fost trimis de ' . e($marketplaceName) . '</p>';
        $html .= '</div>';

        $html .= '</div></body></html>';

        // Try to use marketplace email template
        $template = \App\Models\MarketplaceEmailTemplate::where('marketplace_client_id', $marketplace->id)
            ->where('slug', 'ticket_purchase')
            ->where('is_active', true)
            ->first();

        if ($template) {
            // Build total_amount that includes insurance for template display
            $templateTotalAmount = $totalAmount;
            $insuranceLabel = '';
            if ($insuranceAmount > 0) {
                $insuranceLabel = number_format($insuranceAmount, 2, ',', '.') . ' ' . $currency;
            }

            $vars = [
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'order_number' => $orderNumber,
                'event_name' => $firstEventName,
                'event_date' => $firstEventDate . ($firstEventTime ? ' · ' . $firstEventTime : ''),
                'venue_name' => $firstVenueName,
                'venue_city' => $firstVenueCity,
                'venue_location' => $firstVenueLocation,
                'ticket_count' => (string) $ticketCount,
                'total_amount' => $templateTotalAmount,
                'insurance_amount' => $insuranceLabel,
                'marketplace_name' => $marketplaceName,
                'tickets_list' => $ticketsHtml,
                'download_url' => $downloadUrl,
            ];
            $rendered = $template->render($vars);
            if (!empty($rendered['subject'])) {
                $subject = $rendered['subject'];
            }
            if (!empty($rendered['body_html'])) {
                $html = $rendered['body_html'];
            }
        }

        // Send email via shared helper
        $this->sendMarketplaceEmail($marketplace, $customerEmail, $customerName, $subject, $html, [
            'marketplace_customer_id' => $order->marketplace_customer_id,
            'order_id' => $order->id,
            'template_slug' => 'ticket_purchase',
        ]);

        // Notify marketplace admin (if configured) — sends the same full email
        // the customer receives, prefixed with an admin banner that lists the
        // event/venue/customer/order details and a link to the dashboard.
        try {
            $eventTitle = '';
            $eventDateForBanner = $firstEventDate . ($firstEventTime ? ' · ' . $firstEventTime : '');
            if ($order->event) {
                $eventTitle = is_array($order->event->title)
                    ? ($order->event->title['ro'] ?? $order->event->title['en'] ?? reset($order->event->title) ?: '')
                    : ($order->event->title ?? '');
            }
            if (!$eventTitle) {
                $eventTitle = $firstEventName ?? '';
            }

            $row = fn (string $label, string $value) =>
                '<tr><td style="padding:4px 12px 4px 0;color:#6b7280;white-space:nowrap;">' . e($label) . '</td>'
                . '<td style="padding:4px 0;"><strong>' . $value . '</strong></td></tr>';

            $bannerRows = '';
            $bannerRows .= $row('Comandă', e($order->order_number ?? (string) $order->id));
            $bannerRows .= $row('Status', e($order->status ?? '-') . ' / ' . e($order->payment_status ?? '-'));
            $bannerRows .= $row('Plătită la', $order->paid_at ? e($order->paid_at->format('d.m.Y H:i')) : '-');
            $bannerRows .= $row('Total', e(number_format((float) ($order->total ?? 0), 2)) . ' ' . e($order->currency ?? 'RON'));
            $bannerRows .= $row('Bilete', (string) $order->tickets()->count());
            $bannerRows .= $row('Plată', e($order->payment_processor ?? $order->payment_method ?? '-')
                . ($order->payment_reference ? ' · ref ' . e($order->payment_reference) : ''));
            $bannerRows .= $row('Eveniment', e($eventTitle));
            $bannerRows .= $row('Data eveniment', e($eventDateForBanner ?: '-'));
            $bannerRows .= $row('Venue', e(($firstVenueName ?? '') . ($firstVenueCity ? ' · ' . $firstVenueCity : '')));
            $bannerRows .= $row('Client', e($customerName ?: '-'));
            $bannerRows .= $row('Email client', e($customerEmail ?: '-'));
            if (!empty($order->customer_phone)) {
                $bannerRows .= $row('Telefon', e($order->customer_phone));
            }

            $viewUrl = url("/marketplace/orders/{$order->id}");
            $banner = '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px 20px;margin:0 0 24px;font-family:Arial,sans-serif;color:#1f2937;">'
                . '<div style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#2563eb;font-weight:700;margin-bottom:8px;">Notificare admin · comandă nouă plătită</div>'
                . '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;width:100%;">' . $bannerRows . '</table>'
                . '<p style="margin:14px 0 0;"><a href="' . e($viewUrl) . '" style="display:inline-block;background:#2563eb;color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-size:13px;font-weight:600;">Vezi comanda în dashboard</a></p>'
                . '<hr style="border:none;border-top:1px solid #bfdbfe;margin:16px 0 0;">'
                . '<p style="margin:8px 0 0;font-size:11px;color:#64748b;">Mai jos găsești emailul exact pe care l-a primit clientul.</p>'
                . '</div>';

            $adminHtml = $banner . $html;

            $emailService = new \App\Services\MarketplaceEmailService($marketplace);
            $emailService->sendAdminNotification(
                slug: 'admin_new_order',
                settingKey: 'orders_email',
                variables: [
                    'order_number' => $order->order_number ?? $order->id,
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'total_amount' => number_format((float) ($order->total ?? 0), 2),
                    'currency' => $order->currency ?? 'RON',
                    'tickets_count' => $order->tickets()->count(),
                    'event_name' => $eventTitle,
                    'view_url' => $viewUrl,
                ],
                fallbackSubject: '[Admin] Comandă nouă: ' . ($order->order_number ?? $order->id) . ' · ' . ($eventTitle ?: 'eveniment'),
                fallbackHtml: $adminHtml,
            );
        } catch (\Throwable $e) {
            Log::warning('Admin order notification failed: ' . $e->getMessage(), ['order_id' => $order->id]);
        }
    }

    /**
     * Send individual ticket emails to beneficiaries (attendees with a different email from the order customer)
     */
    public function sendBeneficiaryEmails(Order $order): void
    {
        $marketplace = $order->marketplaceClient;
        if (!$marketplace) {
            return;
        }

        $order->load(['tickets.marketplaceEvent', 'tickets.marketplaceTicketType']);
        $customerEmail = strtolower(trim($order->customer_email ?? ''));
        $marketplaceName = $marketplace->name;
        $sentEmails = []; // Track already-sent emails to avoid duplicates

        foreach ($order->tickets as $ticket) {
            $attendeeEmail = strtolower(trim($ticket->attendee_email ?? ''));

            // Skip tickets without attendee email or where attendee = customer
            if (!$attendeeEmail || $attendeeEmail === $customerEmail || isset($sentEmails[$attendeeEmail])) {
                continue;
            }

            $event = $ticket->marketplaceEvent;
            $eventName = $event->name ?? 'Eveniment';
            $eventDate = $event?->starts_at?->format('d.m.Y') ?? '';
            $eventStartTime = $event?->starts_at?->format('H:i') ?? '';
            $venueName = $event->venue_name ?? '';
            $venueCity = $event->venue_city ?? '';
            $attendeeName = $ticket->attendee_name ?? 'Participant';
            $ticketTypeName = $ticket->marketplaceTicketType?->name ?? 'Bilet';
            $ticketCode = $ticket->code ?? $ticket->barcode ?? '';
            $ticketSeries = $ticket->meta['ticket_series'] ?? null;
            $seatDetails = $ticket->getSeatDetails();

            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
                'size' => '180x180',
                'data' => $ticket->getVerifyUrl(),
                'color' => '1a1a2e',
                'margin' => '0',
                'format' => 'png',
            ]);

            $locationLine = '';
            $locationParts = array_filter([$venueName, $venueCity]);
            if ($locationParts) {
                $locationLine = '<p style="margin:0 0 4px;font-size:14px;color:#b0b0cc;">' . e(implode(', ', $locationParts)) . '</p>';
            }

            $dateLine = '';
            $dateParts = [];
            if ($eventDate) $dateParts[] = $eventDate;
            if ($eventStartTime) $dateParts[] = 'Ora: ' . $eventStartTime;
            if ($dateParts) {
                $dateLine = '<p style="margin:0;font-size:14px;color:#b0b0cc;">' . e(implode(' | ', $dateParts)) . '</p>';
            }

            $seatLine = '';
            if ($seatDetails) {
                $sp = [];
                if (!empty($seatDetails['section_name'])) $sp[] = $seatDetails['section_name'];
                if (!empty($seatDetails['row_label'])) $sp[] = 'Rând ' . $seatDetails['row_label'];
                if (!empty($seatDetails['seat_number'])) $sp[] = 'Loc ' . $seatDetails['seat_number'];
                if ($sp) {
                    $seatLine = '<tr><td style="padding:3px 12px 3px 0;color:#888;">Loc:</td><td style="padding:3px 0;font-weight:600;">' . e(implode(' / ', $sp)) . '</td></tr>';
                }
            }

            $seriesLine = '';
            if ($ticketSeries) {
                $seriesLine = '<tr><td style="padding:3px 12px 3px 0;color:#888;">Serie:</td><td style="padding:3px 0;font-weight:600;font-family:monospace;">' . e($ticketSeries) . '</td></tr>';
            }

            $seriesQrLine = $ticketSeries ? '<p style="margin:2px 0 0;font-size:11px;color:#888;font-family:monospace;">Serie: ' . e($ticketSeries) . '</p>' : '';

            $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
                . '<body style="margin:0;padding:0;background:#f4f4f8;font-family:Arial,Helvetica,sans-serif;">'
                . '<div style="max-width:640px;margin:0 auto;padding:24px 16px;">'
                . '<div style="text-align:center;padding:20px 0;"><h1 style="margin:0;font-size:24px;color:#1a1a2e;">' . e($marketplaceName) . '</h1></div>'
                . '<div style="background:#ffffff;border-radius:12px;padding:24px;margin-bottom:20px;">'
                . '<p style="margin:0 0 12px;font-size:16px;color:#333;">Salut, <strong>' . e($attendeeName) . '</strong>!</p>'
                . '<p style="margin:0 0 16px;font-size:15px;color:#555;">Ai primit un bilet pentru evenimentul de mai jos. Prezintă codul QR la intrare.</p>'
                . '</div>'
                . '<div style="margin-bottom:20px;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;">'
                . '<div style="background:#1a1a2e;color:#ffffff;padding:20px 24px;">'
                . '<h2 style="margin:0 0 8px;font-size:20px;font-weight:700;">' . e($eventName) . '</h2>'
                . $locationLine . $dateLine
                . '</div>'
                . '<table style="width:100%;" cellpadding="0" cellspacing="0"><tr>'
                . '<td style="padding:20px 20px 20px 24px;width:170px;vertical-align:top;text-align:center;">'
                . '<img src="' . $qrUrl . '" alt="QR Code" width="150" height="150" style="display:block;border:1px solid #eee;border-radius:8px;" />'
                . '<p style="margin:6px 0 0;font-size:12px;color:#666;font-family:monospace;">' . e($ticketCode) . '</p>'
                . $seriesQrLine
                . '</td>'
                . '<td style="padding:20px 24px 20px 0;vertical-align:top;">'
                . '<p style="margin:0 0 6px;font-size:16px;font-weight:700;color:#1a1a2e;">' . e($ticketTypeName) . '</p>'
                . '<table style="border-collapse:collapse;font-size:14px;color:#333;" cellpadding="0" cellspacing="0">'
                . '<tr><td style="padding:3px 12px 3px 0;color:#888;">Beneficiar:</td><td style="padding:3px 0;font-weight:600;">' . e($attendeeName) . '</td></tr>'
                . $seriesLine
                . $seatLine
                . '</table>'
                . '</td>'
                . '</tr></table>'
                . '</div>'
                . '<div style="text-align:center;padding:16px 0;font-size:12px;color:#999;"><p style="margin:0;">Acest email a fost trimis de ' . e($marketplaceName) . '</p></div>'
                . '</div></body></html>';

            try {
                $this->sendMarketplaceEmail($marketplace, $attendeeEmail, $attendeeName, "Biletul tău pentru {$eventName}", $html, [
                    'order_id' => $order->id,
                    'template_slug' => 'beneficiary_ticket',
                ]);
                $sentEmails[$attendeeEmail] = true;
            } catch (\Throwable $e) {
                Log::channel('marketplace')->error('Failed to send beneficiary email', [
                    'order_id' => $order->id,
                    'attendee_email' => $attendeeEmail,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Handle payment callback for service orders (SVC- prefix)
     */
    protected function handleServiceOrderCallback(array $result, \App\Models\MarketplaceClient $client, string $reference): \Illuminate\Http\Response
    {
        $serviceOrder = \App\Models\ServiceOrder::where('order_number', $reference)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (! $serviceOrder) {
            Log::channel('marketplace')->error('ServiceOrder not found for callback', [
                'reference' => $reference,
                'client_id' => $client->id,
            ]);
            return $this->netopiaResponse(0); // ACK to avoid retries
        }

        if ($result['status'] === 'success') {
            // Idempotency: don't process twice
            if ($serviceOrder->payment_status === \App\Models\ServiceOrder::PAYMENT_PAID) {
                return $this->netopiaResponse(0);
            }

            $serviceOrder->markAsPaid($result['transaction_id'] ?? $result['payment_id'] ?? null);
            $serviceOrder->activate(); // marks event as featured

            Log::channel('marketplace')->info('ServiceOrder payment confirmed and activated', [
                'order_number' => $reference,
                'client_id'    => $client->id,
            ]);
        } else {
            $serviceOrder->update([
                'payment_status' => \App\Models\ServiceOrder::PAYMENT_FAILED,
            ]);

            Log::channel('marketplace')->warning('ServiceOrder payment failed', [
                'order_number' => $reference,
                'status'       => $result['status'],
            ]);
        }

        return $this->netopiaResponse(0);
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
