<?php

namespace App\Services\Installments;

use App\Models\InstallmentAgreement;
use App\Models\InstallmentPayment;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceTransaction;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fault-based refunds for flexible-payment agreements.
 *
 *  - Customer return: refund what was actually paid, MINUS non-refundable fees
 *    (surcharge + platform fee) per the plan's refund_policy.
 *  - Event cancelled by organizer: customer made whole (full refund incl. fees);
 *    the organizer bears the fees/commissions per the existing refund flow.
 *
 * Cancels the remaining schedule, invalidates tickets, voids the mandate, and
 * emails the customer. Actual money-back is attempted per paid installment via
 * the marketplace processor (best-effort; logged on failure).
 */
class InstallmentRefundService
{
    public function __construct(
        protected ProcessorResolver $resolver,
        protected TicketStateService $tickets,
        protected FlexiblePaymentMailer $mailer,
    ) {}

    /**
     * Customer-initiated return: taxes non-refundable.
     */
    public function customerReturn(InstallmentAgreement $agreement, ?string $reason = null): int
    {
        $policy = $agreement->plan?->refundPolicy() ?? ['surcharge_refundable' => false, 'platform_fee_refundable' => false];

        $paid = $agreement->paidCents();
        $customerTotal = max(1, (int) $agreement->customer_total_cents);
        // Base-price portion actually collected (what the organizer earned).
        $baseCollected = (int) round($paid * $agreement->base_total_cents / $customerTotal);
        // Non-refundable surcharge portion actually collected so far.
        $surchargeCollected = (int) round($paid * $agreement->surcharge_cents / $customerTotal);

        $refundable = $paid;
        $organizerClawback = $baseCollected;
        if (empty($policy['surcharge_refundable'])) {
            $refundable -= $surchargeCollected; // withhold surcharge → refund ≈ base portion
        }
        $refundable = max(0, $refundable);

        return $this->finalize($agreement, $refundable, $organizerClawback, 'installment_refund', $reason ?? 'customer_return');
    }

    /**
     * Organizer cancelled the event: full refund including fees.
     */
    public function eventCancelled(InstallmentAgreement $agreement, ?string $reason = null): int
    {
        $paid = $agreement->paidCents(); // everything the customer paid (customer-total scale)
        $customerTotal = max(1, (int) $agreement->customer_total_cents);
        $organizerClawback = (int) round($paid * $agreement->base_total_cents / $customerTotal);

        return $this->finalize($agreement, $paid, $organizerClawback, 'installment_event_cancelled_refund', $reason ?? 'event_cancelled');
    }

    /**
     * A refund was initiated OUTSIDE the app (e.g. straight in the Stripe/Netopia
     * dashboard on the down-payment charge). The money already moved at the
     * gateway, so we must NOT re-issue processor refunds — we only unwind the
     * plan: stop auto-debit, cancel the remaining schedule, claw back the
     * organizer's base-portion payout, and invalidate the tickets. Any half-paid
     * plan whose down payment was refunded must not keep debiting the customer.
     *
     * @param int $gatewayRefundedCents amount the gateway reported as refunded (customer-total scale)
     */
    public function externalRefund(InstallmentAgreement $agreement, int $gatewayRefundedCents, ?string $reason = null): int
    {
        $customerTotal = max(1, (int) $agreement->customer_total_cents);
        $organizerClawback = (int) round($gatewayRefundedCents * $agreement->base_total_cents / $customerTotal);

        return $this->finalize(
            $agreement,
            $gatewayRefundedCents,
            $organizerClawback,
            'installment_refund',
            $reason ?? 'gateway_refund',
            attemptGatewayRefund: false,
        );
    }

    /**
     * @param int $refundableCents      what is refunded to the CUSTOMER (best-effort at gateway)
     * @param int $organizerClawbackCents base-price portion to debit from the organizer's balance
     */
    protected function finalize(InstallmentAgreement $agreement, int $refundableCents, int $organizerClawbackCents, string $emailSlug, string $reason, bool $attemptGatewayRefund = true): int
    {
        // Idempotency: atomically claim the agreement by flipping it to REFUNDED
        // only if it is not already REFUNDED. A conditional UPDATE returning 0
        // affected rows means a concurrent/replayed refund already won the race,
        // so we bail before running any side effect (money-back, clawback).
        $claimed = InstallmentAgreement::query()
            ->where('id', $agreement->id)
            ->where('status', '!=', InstallmentAgreement::STATUS_REFUNDED)
            ->update([
                'status' => InstallmentAgreement::STATUS_REFUNDED,
                'auto_debit_enabled' => false,
                'next_due_at' => null,
            ]);
        if ($claimed === 0) {
            return 0;
        }
        $agreement->refresh();

        DB::transaction(function () use ($agreement, $reason) {
            // Cancel every not-yet-paid installment (status already flipped above).
            $agreement->payments()
                ->whereIn('status', [
                    InstallmentPayment::STATUS_SCHEDULED, InstallmentPayment::STATUS_DUE,
                    InstallmentPayment::STATUS_RETRYING, InstallmentPayment::STATUS_ACTION_REQUIRED,
                    InstallmentPayment::STATUS_FAILED,
                ])
                ->update(['status' => InstallmentPayment::STATUS_CANCELLED]);

            $agreement->log('refunded', "Refund ({$reason})", ['reason' => $reason]);
        });

        // Best-effort money-back per paid installment (each Netopia/Stripe
        // transaction credited individually via its stored reference). Skipped
        // when the refund already happened at the gateway (externalRefund).
        if ($attemptGatewayRefund) {
            $this->attemptProcessorRefunds($agreement, $refundableCents);
        } else {
            // Mark paid rows refunded for bookkeeping without calling the gateway.
            $agreement->payments()->where('status', 'paid')
                ->update(['status' => InstallmentPayment::STATUS_REFUNDED]);
        }

        // Reverse the organizer's incrementally-credited balance (base-price
        // portion clawed back). Amount already at base scale — no re-ratio.
        $this->reverseOrganizerPayout($agreement, $organizerClawbackCents);

        // Tickets are no longer valid.
        $this->tickets->invalidateForAgreement($agreement);

        // Keep the Order in sync so it doesn't still read as fully paid.
        $this->syncRefundedOrder($agreement, $refundableCents);

        $this->mailer->send($agreement->fresh(), $emailSlug, [
            'refund_amount' => number_format($refundableCents / 100, 2, ',', '.') . ' ' . $agreement->currency,
        ]);

        return $refundableCents;
    }

    protected function syncRefundedOrder(InstallmentAgreement $agreement, int $refundedCents): void
    {
        if (! $agreement->order_id) {
            return;
        }
        $order = Order::find($agreement->order_id);
        if (! $order) {
            return;
        }
        $order->forceFill([
            'status' => 'refunded',
            'payment_status' => 'refunded',
            'refund_status' => 'full',
            'refunded_amount' => $refundedCents / 100,
            'refunded_at' => now(),
            'outstanding_cents' => 0,
        ])->save();
    }

    protected function attemptProcessorRefunds(InstallmentAgreement $agreement, int $refundableCents): void
    {
        if ($refundableCents <= 0 || ! $agreement->marketplace_client_id) {
            return;
        }
        $client = MarketplaceClient::find($agreement->marketplace_client_id);
        $resolved = $client ? $this->resolver->forMarketplaceClient($client) : null;
        if (! $resolved) {
            Log::warning("Installment refund: no processor for agreement {$agreement->id}; manual refund needed ({$refundableCents} bani).");
            return;
        }

        $processor = $resolved['processor'];
        $remaining = $refundableCents;

        foreach ($agreement->payments()->where('status', 'paid')->orderBy('sequence')->get() as $payment) {
            if ($remaining <= 0) {
                break;
            }
            if (! $payment->payment_reference) {
                continue;
            }
            $amount = min($remaining, (int) $payment->paid_amount_cents);
            try {
                $processor->refundPayment($payment->payment_reference, $amount / 100, 'Installment refund');
                $payment->update(['status' => InstallmentPayment::STATUS_REFUNDED]);
                $remaining -= $amount;
            } catch (\Throwable $e) {
                Log::error("Installment refund failed for payment {$payment->id}: {$e->getMessage()}");
            }
        }

        if ($remaining > 0) {
            Log::warning("Installment refund partial for agreement {$agreement->id}: {$remaining} bani still to refund manually.");
        }
    }

    /**
     * Debit back the organizer's balance for the base-price portion of the
     * refunded amount (and credit the commission back), reversing the
     * incremental payout. Best-effort — a refund must never fail on ledger issues.
     */
    protected function reverseOrganizerPayout(InstallmentAgreement $agreement, int $baseRefundCents): void
    {
        try {
            if ($baseRefundCents <= 0 || ! $agreement->marketplace_client_id) {
                return;
            }
            $order = Order::find($agreement->order_id);
            $organizerId = $order?->marketplace_organizer_id;
            if (! $organizerId) {
                return;
            }

            // $baseRefundCents is already the base-price portion to claw back.
            $commissionRate = (float) ($order->commission_rate ?? 0);
            $commissionRefundCents = (int) round($baseRefundCents * $commissionRate / 100);

            MarketplaceTransaction::recordRefund(
                (int) $agreement->marketplace_client_id,
                (int) $organizerId,
                $baseRefundCents / 100,
                $commissionRefundCents / 100,
                (int) $agreement->order_id,
                $agreement->currency
            );

            $agreement->log('payout_reversed', 'Organizer balance reversed for refund', [
                'base_refund_cents' => $baseRefundCents,
                'commission_refund_cents' => $commissionRefundCents,
            ]);
        } catch (\Throwable $e) {
            Log::error("Installments payout reversal failed for agreement {$agreement->id}: {$e->getMessage()}");
        }
    }
}
