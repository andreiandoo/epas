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
        // Non-refundable surcharge portion actually collected so far.
        $surchargeCollected = (int) round($paid * $agreement->surcharge_cents / max(1, $agreement->customer_total_cents));
        $refundable = $paid;
        if (empty($policy['surcharge_refundable'])) {
            $refundable -= $surchargeCollected;
        }
        $refundable = max(0, $refundable);

        return $this->finalize($agreement, $refundable, 'installment_refund', $reason ?? 'customer_return');
    }

    /**
     * Organizer cancelled the event: full refund including fees.
     */
    public function eventCancelled(InstallmentAgreement $agreement, ?string $reason = null): int
    {
        $refundable = $agreement->paidCents(); // everything the customer paid
        return $this->finalize($agreement, $refundable, 'installment_event_cancelled_refund', $reason ?? 'event_cancelled', true);
    }

    protected function finalize(InstallmentAgreement $agreement, int $refundableCents, string $emailSlug, string $reason, bool $fullFault = false): int
    {
        DB::transaction(function () use ($agreement, $reason) {
            // Cancel every not-yet-paid installment and void the mandate.
            $agreement->payments()
                ->whereIn('status', [
                    InstallmentPayment::STATUS_SCHEDULED, InstallmentPayment::STATUS_DUE,
                    InstallmentPayment::STATUS_RETRYING, InstallmentPayment::STATUS_ACTION_REQUIRED,
                    InstallmentPayment::STATUS_FAILED,
                ])
                ->update(['status' => InstallmentPayment::STATUS_CANCELLED]);

            $agreement->update([
                'status' => InstallmentAgreement::STATUS_REFUNDED,
                'auto_debit_enabled' => false,
                'next_due_at' => null,
            ]);
            $agreement->log('refunded', "Refund ({$reason})", ['reason' => $reason]);
        });

        // Best-effort money-back per paid installment (each Netopia/Stripe
        // transaction credited individually via its stored reference).
        $this->attemptProcessorRefunds($agreement, $refundableCents);

        // Reverse the organizer's incrementally-credited balance for the amount
        // being clawed back (mirror of InstallmentPayoutService::creditInstallment).
        $this->reverseOrganizerPayout($agreement, $refundableCents);

        // Tickets are no longer valid.
        $this->tickets->invalidateForAgreement($agreement);

        $this->mailer->send($agreement->fresh(), $emailSlug, [
            'refund_amount' => number_format($refundableCents / 100, 2, ',', '.') . ' ' . $agreement->currency,
        ]);

        return $refundableCents;
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
    protected function reverseOrganizerPayout(InstallmentAgreement $agreement, int $refundableCents): void
    {
        try {
            if ($refundableCents <= 0 || ! $agreement->marketplace_client_id) {
                return;
            }
            $order = Order::find($agreement->order_id);
            $organizerId = $order?->marketplace_organizer_id;
            if (! $organizerId) {
                return;
            }

            // The organizer only earned on the base (ticket) price, so reverse the
            // base share of what is being refunded.
            $customerTotal = max(1, (int) $agreement->customer_total_cents);
            $baseRefundCents = (int) round($refundableCents * $agreement->base_total_cents / $customerTotal);
            if ($baseRefundCents <= 0) {
                return;
            }
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
