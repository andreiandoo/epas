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
     * $tickets plus the order's free-with-code tickets when $tickets takes the
     * order's last paid ticket; otherwise $tickets unchanged.
     */
    protected function withOrphanedFreeTickets(Order $order, $tickets)
    {
        $remaining = $order->tickets()->with('ticketType')
            ->whereNotIn('id', $tickets->pluck('id')->all())
            ->get()
            ->reject(fn (Ticket $t) => $t->is_cancelled || $t->isRefunded());
        $freeLeft = $remaining->filter(fn (Ticket $t) => $t->ticketType?->isFreeWithCode());
        if ($freeLeft->isEmpty()) {
            return $tickets;
        }
        $paidLeft = $remaining->reject(fn (Ticket $t) => $t->ticketType?->isFreeWithCode())
            ->contains(fn (Ticket $t) => (float) ($t->price ?? 0) > 0);

        return $paidLeft ? $tickets : $tickets->concat($freeLeft)->values();
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

        // A refund the processor refused must be finished (or retried) first,
        // otherwise the same money could be requested twice.
        if ($pending = $this->pendingManualRefundFor($order)) {
            return new RefundResult(
                success: false,
                error: "Există deja o rambursare neterminată ({$pending->reference}). Finalizeaz-o din pagina comenzii.",
                refundRequestId: $pending->id
            );
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

        // "Bilet gratuit cu cod": free tickets stay valid only while the order
        // still holds a paid ticket. When this refund takes the last paid one,
        // the order's free tickets join it (0 lei) so their seats and stock go
        // back through the very same full/partial release below.
        $tickets = $this->withOrphanedFreeTickets($order, $tickets);
        $ticketIds = $tickets->pluck('id')->all();

        // Calculate amounts per ticket using stored order values
        $orderSubtotal = (float) ($order->subtotal ?? 0);
        $orderDiscount = (float) ($order->discount_amount ?? 0);
        $orderCommission = (float) ($order->commission_amount ?? 0);
        $discountRatio = ($orderSubtotal > 0 && $orderDiscount > 0) ? ($orderDiscount / $orderSubtotal) : 0;

        // Build per-ticket-type commission map from stored order data
        $commissionDetails = $order->meta['commission_details'] ?? [];
        $commissionByType = [];
        foreach ($commissionDetails as $cd) {
            $key = $cd['ticket_type'] ?? '';
            $mode = $cd['commission_mode'] ?? null;
            $commissionByType[$key] = [
                'amount_per_unit' => (float) ($cd['commission_amount'] ?? 0) / max(1, (int) ($cd['quantity'] ?? 1)),
                // Marketplace VAT the customer paid on this on-top commission
                // (stored at checkout; absent = none, e.g. before the VAT start date)
                'vat_per_unit' => (float) ($cd['commission_vat'] ?? 0) / max(1, (int) ($cd['quantity'] ?? 1)),
                'mode' => in_array($mode, ['on_top', 'add_on_top', 'added_on_top'], true) ? 'on_top' : 'included',
            ];
        }
        $fallbackMode = $order->getOrderFallbackCommissionMode();

        $items = [];
        $totalRefund = 0;
        $totalVatRefunded = 0.0;

        foreach ($tickets as $ticket) {
            $originalPrice = (float) ($ticket->price ?? 0);
            $ticketDiscount = round($originalPrice * $discountRatio, 2);
            $grossAfterDiscount = round($originalPrice - $ticketDiscount, 2);
            // Get exact commission from stored commission_details per ticket type
            $typeName = $ticket->ticketType?->name ?? '';
            $cd = $commissionByType[$typeName] ?? null;
            $commission = $cd ? round($cd['amount_per_unit'], 2) : 0;
            $ticketMode = $cd['mode'] ?? $fallbackMode;
            // For "included" tickets ticket.price is the GROSS amount the
            // customer paid (commission baked in), so face_value (organizer
            // share) = gross - commission. For "on_top" tickets ticket.price
            // IS the net face value and commission was charged on top.
            $faceValue = $ticketMode === 'included'
                ? round($grossAfterDiscount - $commission, 2)
                : $grossAfterDiscount;
            // Included tickets MUST refund with commission (face+commission = gross
            // customer paid); on_top tickets follow the operator's choice.
            $effectiveRefundCommission = ($ticketMode === 'included') ? true : $refundCommission;
            // The VAT on an on-top commission goes back with the commission (and
            // stays with the marketplace when the commission is kept).
            $commissionVat = ($ticketMode === 'on_top' && $effectiveRefundCommission && $cd)
                ? round($cd['vat_per_unit'] ?? 0, 2)
                : 0.0;
            $refundAmount = $effectiveRefundCommission ? ($faceValue + $commission + $commissionVat) : $faceValue;
            $totalVatRefunded += $commissionVat;

            $items[] = [
                'ticket' => $ticket,
                'face_value' => round($faceValue, 2),
                'commission_amount' => round($commission, 2),
                'refund_amount' => round($refundAmount, 2),
                'commission_refunded' => $effectiveRefundCommission,
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
                'marketplace_event_id' => $order->marketplace_event_id,
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
                'commission_refund' => collect($items)->filter(fn ($i) => $i['commission_refunded'])->sum('commission_amount'),
                // Marketplace VAT returned with the on-top commission (part of
                // approved_amount; reversed on the general-client invoice)
                'fees_refund' => round($totalVatRefunded, 2),
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

            $processorResult->refundRequestId = $refundRequest->id;

            if (!$processorResult->success) {
                // The processor refused: no money left, so the order, tickets and
                // stock stay as they are. The request waits for the operator to
                // retry or to refund by hand and confirm it.
                $refundRequest->update([
                    'status' => 'approved',
                    'is_automatic' => false,
                    'admin_notes' => 'Rambursarea automată a fost refuzată de procesator: ' . ($processorResult->error ?? ''),
                ]);
                DB::commit();

                Log::channel('marketplace')->warning('Refund refused by processor, waiting for manual completion', [
                    'order_id' => $order->id,
                    'refund_request_id' => $refundRequest->id,
                    'error' => $processorResult->error,
                ]);

                return $processorResult;
            }

            $this->applyRefund($refundRequest, $tickets, $isFullRefund, [
                'payment_refund_id' => $processorResult->refundId,
                'payment_response' => $processorResult->response,
                'is_automatic' => true,
            ]);

            DB::commit();

            $this->sendRefundEmail($refundRequest);

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
     * Operator-started refund the processor refused, still waiting for the
     * money to actually go out.
     */
    public function pendingManualRefundFor(Order $order): ?MarketplaceRefundRequest
    {
        return MarketplaceRefundRequest::where('order_id', $order->id)
            ->where('status', MarketplaceRefundRequest::STATUS_APPROVED)
            ->whereHas('refundItems', fn ($q) => $q->where('status', 'pending'))
            ->latest('id')
            ->first();
    }

    public function isPendingManualRefund(MarketplaceRefundRequest $refund): bool
    {
        return $refund->status === MarketplaceRefundRequest::STATUS_APPROVED
            && $refund->refundItems()->where('status', 'pending')->exists();
    }

    /**
     * Send a refused refund to the processor again; on success it is applied
     * exactly like a first-time automatic refund.
     */
    public function retryPendingRefund(MarketplaceRefundRequest $refund): RefundResult
    {
        if (!$this->isPendingManualRefund($refund)) {
            return new RefundResult(success: false, error: 'Rambursarea nu mai este în așteptare.');
        }

        $result = $this->processRefund($refund);
        $result->refundRequestId = $refund->id;

        if (!$result->success) {
            $refund->update([
                'admin_notes' => trim(($refund->admin_notes ?? '') . "\n\nReîncercare " . now()->format('Y-m-d H:i') . ': ' . ($result->error ?? '')),
            ]);
            return $result;
        }

        DB::transaction(fn () => $this->applyRefund($refund, $this->refundTickets($refund), $refund->type === 'full_refund', [
            'payment_refund_id' => $result->refundId,
            'payment_response' => $result->response,
            'is_automatic' => true,
        ]));

        $this->sendRefundEmail($refund);

        return $result;
    }

    /**
     * The operator sent the money by hand (processor panel, bank transfer...)
     * and confirms it here, so the order and tickets become refunded.
     */
    public function completeManualRefund(
        MarketplaceRefundRequest $refund,
        string $method,
        ?string $reference,
        ?int $processedBy = null,
        bool $notifyCustomer = true
    ): RefundResult {
        if (!$this->isPendingManualRefund($refund)) {
            return new RefundResult(success: false, error: 'Rambursarea nu mai este în așteptare.');
        }

        $note = 'Finalizată manual (' . $method . ')' . ($reference ? ", referință {$reference}" : '') . ' la ' . now()->format('Y-m-d H:i');

        DB::transaction(fn () => $this->applyRefund($refund, $this->refundTickets($refund), $refund->type === 'full_refund', [
            'payment_refund_id' => $reference ?: null,
            'is_automatic' => false,
            'refund_method' => $method,
            'processed_by' => $processedBy,
            'admin_notes' => trim(($refund->admin_notes ?? '') . "\n\n" . $note),
        ]));

        if ($notifyCustomer) {
            $this->sendRefundEmail($refund);
        }

        return new RefundResult(success: true, refundId: $reference, refundRequestId: $refund->id);
    }

    protected function refundTickets(MarketplaceRefundRequest $refund)
    {
        return Ticket::whereIn('id', $refund->ticket_ids ?? [])->get();
    }

    /**
     * Mark the request, its items, the tickets and the order as refunded and
     * release the stock. Runs inside the caller's transaction.
     */
    protected function applyRefund(MarketplaceRefundRequest $refund, $tickets, bool $isFullRefund, array $requestFields): void
    {
        $order = $refund->order()->lockForUpdate()->first();

        $refund->update(array_merge([
            'status' => $isFullRefund ? 'refunded' : 'partially_refunded',
            'processed_at' => now(),
            'completed_at' => now(),
        ], $requestFields));

        $refund->refundItems()->update(['status' => 'refunded']);

        // Refunds recorded before 2026-10-22 already flipped the tickets and the
        // order even when the processor refused; don't count those twice.
        $tickets = $tickets->reject(fn (Ticket $t) => (int) $t->refund_request_id === (int) $refund->id && $t->isRefunded())->values();
        if ($tickets->isEmpty()) {
            return;
        }

        foreach ($tickets as $ticket) {
            $ticket->update([
                'status' => 'refunded',
                'refund_status' => 'refunded',
                'is_cancelled' => true,
                'cancelled_at' => now(),
                'cancellation_reason' => "Refund: {$refund->reference}",
                'refund_request_id' => $refund->id,
            ]);
        }

        $newRefundedAmount = (float) ($order->refunded_amount ?? 0) + (float) $refund->approved_amount;
        $order->update([
            'refund_status' => $isFullRefund ? 'full' : 'partial',
            'refunded_amount' => round($newRefundedAmount, 2),
            'refunded_at' => now(),
            'refund_amount' => round($newRefundedAmount, 2),
            'refund_reason' => $refund->reason,
        ]);

        if ($isFullRefund) {
            // Full refund: the Order::saved observer for status='refunded'
            // invokes releaseSeatsAndRestoreStock() which decrements
            // quota_sold for every order item AND stamps
            // meta.stock_released_at so a repeat can't double-decrement.
            // Calling releaseStockForTickets() here too would drop the
            // counter twice for the same tickets (no guard on that path),
            // producing OVER-count drift that inflates apparent
            // availability and can enable oversell later.
            $order->update(['status' => 'refunded']);
        } else {
            // Partial refund: the Order::saved observer branch for
            // status='partially_refunded' is intentionally a no-op
            // because only SOME tickets in the order are being
            // refunded — we can't drop stock for the whole order.
            // releaseStockForTickets() decrements only the ticket
            // types of the specific $tickets being refunded (once).
            $order->update(['status' => 'partially_refunded']);
            $order->releaseStockForTickets($tickets);
        }
    }

    protected function sendRefundEmail(MarketplaceRefundRequest $refund): void
    {
        // After commit, non-blocking
        try {
            $client = $refund->order?->marketplaceClient;
            if ($client) {
                $emailService = new \App\Services\MarketplaceEmailService($client);
                $emailService->sendRefundProcessedEmail($refund);
            }
        } catch (\Throwable $emailError) {
            Log::channel('marketplace')->warning('Failed to send refund email', [
                'order_id' => $refund->order_id,
                'refund_id' => $refund->id,
                'error' => $emailError->getMessage(),
            ]);
        }
    }

    /**
     * What the operator has to do by hand when the processor refused.
     */
    public function manualRefundInstructions(MarketplaceRefundRequest $refund): string
    {
        $order = $refund->order;
        $amount = number_format((float) $refund->approved_amount, 2) . ' ' . ($refund->currency ?? 'RON');
        $orderRef = e($order?->payment_reference ?: $order?->order_number);
        $processor = $refund->payment_processor ?? $order?->payment_processor;

        $panelStep = match ($processor) {
            'netopia' => "Intră în panoul Netopia (admin.netopia-payments.com) → Tranzacții → caută comanda <strong>{$orderRef}</strong> → Rambursare, suma <strong>{$amount}</strong>. Dacă Netopia nu permite, trimite banii prin transfer bancar în contul clientului.",
            'stripe' => "Intră în Stripe → Payments → caută plata comenzii <strong>{$orderRef}</strong> → Refund, suma <strong>{$amount}</strong>.",
            default => "Trimite clientului <strong>{$amount}</strong> pe aceeași cale prin care a plătit sau prin transfer bancar.",
        };

        return '<ol style="margin:0;padding-left:18px;font-size:13px;line-height:1.6;">'
            . '<li>Încearcă întâi <strong>„Reîncearcă rambursarea automată”</strong> (uneori refuzul e temporar).</li>'
            . '<li>Dacă tot nu merge: ' . $panelStep . '</li>'
            . '<li>După ce banii au plecat, apasă <strong>„Finalizează rambursarea manuală”</strong> și trece referința tranzacției. Abia atunci biletele se anulează și clientul primește emailul.</li>'
            . '</ol>';
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
        public bool $isPending = false,
        public ?int $refundRequestId = null
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
