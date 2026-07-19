<?php

namespace App\Services\Installments;

use App\Models\InstallmentAgreement;
use App\Models\InstallmentPayment;
use App\Models\MarketplaceTransaction;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Incremental organizer payout: the organizer's balance is credited as each
 * installment is COLLECTED (not upfront), so we never pay out money we haven't
 * received. Surcharge stays with the marketplace; the platform (Tixello) fee is
 * settled separately.
 */
class InstallmentPayoutService
{
    /**
     * Credit the organizer for the base-price portion of one collected
     * installment, net of the proportional marketplace commission.
     */
    public function creditInstallment(InstallmentAgreement $agreement, InstallmentPayment $payment): void
    {
        try {
            if (! $agreement->order_id || ! $agreement->marketplace_client_id) {
                return;
            }
            $order = Order::find($agreement->order_id);
            $organizerId = $order?->marketplace_organizer_id;
            if (! $organizerId) {
                return;
            }

            // The organizer earns on the base (ticket) price, not the surcharge.
            // Attribute each installment's base share by TELESCOPING over the
            // schedule sequence: share = cumulativeBase(through this seq) −
            // cumulativeBase(before this seq). Independently rounding each share
            // would let rounding error accumulate so the sum drifts a few bani
            // off base_total; the telescoping sum equals base_total exactly and
            // is deterministic regardless of the order installments settle in.
            $customerTotal = max(1, (int) $agreement->customer_total_cents);
            $baseShareCents = $this->baseShareForPayment($agreement, $payment, $customerTotal);

            $commissionRate = (float) ($order->commission_rate ?? 0);
            $commissionCents = (int) round($baseShareCents * $commissionRate / 100);

            MarketplaceTransaction::recordSale(
                (int) $agreement->marketplace_client_id,
                (int) $organizerId,
                $baseShareCents / 100,
                $commissionCents / 100,
                (int) $agreement->order_id,
                $agreement->currency
            );

            $agreement->log('payout_credited', "Organizer credited for installment {$payment->sequence}", [
                'base_share_cents' => $baseShareCents,
                'commission_cents' => $commissionCents,
            ], $payment->id);
        } catch (\Throwable $e) {
            // Payout must never break charging; log and continue.
            Log::error("Installments payout failed for agreement {$agreement->id}: {$e->getMessage()}");
        }
    }

    /**
     * Base-price portion attributable to a single installment, telescoped over
     * the schedule so the shares sum EXACTLY to base_total_cents (no drift).
     */
    protected function baseShareForPayment(InstallmentAgreement $agreement, InstallmentPayment $payment, int $customerTotal): int
    {
        $base = (int) $agreement->base_total_cents;
        $cumBefore = 0;
        foreach ($agreement->payments()->orderBy('sequence')->get() as $p) {
            if ((int) $p->id === (int) $payment->id) {
                $cumThrough = $cumBefore + (int) $p->amount_cents;
                return (int) round($cumThrough * $base / $customerTotal)
                     - (int) round($cumBefore * $base / $customerTotal);
            }
            $cumBefore += (int) $p->amount_cents;
        }
        // Fallback (payment not found in schedule): proportional share.
        return (int) round($payment->amount_cents * $base / $customerTotal);
    }
}
