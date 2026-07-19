<?php

namespace App\Services\Installments;

use App\Models\Order;
use App\Models\PaymentLink;
use Illuminate\Support\Facades\Log;

/**
 * "Buy Now, Someone Else Pays": the buyer reserves the tickets and a third
 * party pays via a secure 24h link. Not credit — a single normal payment.
 *
 * Reuses the shared PaymentLink primitive; on-session payment is handled by
 * the public /pay/{token} controller (the marketplace's normal processor).
 */
class DelegatedPaymentService
{
    /**
     * Create a delegated-payment link for a reserved order.
     *
     * @param array $opts hold_hours, payer_email, payer_name, created_by_customer_id
     */
    public function createLink(Order $order, array $opts = []): PaymentLink
    {
        $holdHours = (int) ($opts['hold_hours'] ?? config('installments.delegated_hold_hours', 24));

        $order->forceFill([
            'payment_method_kind' => 'delegated_pay',
            'outstanding_cents' => (int) round(($order->total ?? 0) * 100),
        ])->save();

        return PaymentLink::create([
            'purpose' => PaymentLink::PURPOSE_DELEGATED,
            'marketplace_client_id' => $order->marketplace_client_id,
            'tenant_id' => $order->tenant_id,
            'order_id' => $order->id,
            'amount_cents' => (int) round(($order->total ?? 0) * 100),
            'currency' => $order->currency ?? 'RON',
            'expires_at' => now()->addHours(min($holdHours, 24)),
            'payer_email' => $opts['payer_email'] ?? null,
            'payer_name' => $opts['payer_name'] ?? null,
            'created_by_customer_id' => $opts['created_by_customer_id'] ?? ($order->marketplace_customer_id ?? null),
        ]);
    }

    /**
     * Called by the /pay/{token} controller after a successful third-party payment.
     */
    public function confirm(PaymentLink $link, array $result = []): void
    {
        $link->markPaid($result['payment_id'] ?? null);

        $order = $link->order;
        if ($order) {
            $order->forceFill([
                'status' => 'completed',
                'payment_status' => 'paid',
                'outstanding_cents' => 0,
                'paid_at' => now(),
                'payment_reference' => $result['payment_id'] ?? $order->payment_reference,
            ])->save();

            // Flip reserved tickets to valid (matches the standard paid-callback
            // pattern; DB::table avoids re-triggering observers and is idempotent).
            \Illuminate\Support\Facades\DB::table('tickets')
                ->where('order_id', $order->id)
                ->whereIn('status', ['pending', 'pending_installments'])
                ->update(['status' => 'valid', 'updated_at' => now()]);

            // Payment cleared → confirm the held seats (held → sold) so they are
            // no longer subject to the pending-order auto-release sweep.
            app(TicketStateService::class)->confirmSeatsForOrder($order);
        }
    }

    /**
     * Called when the 24h window lapses without payment: release the hold,
     * cancel the order, and (rescue) let the buyer pay it themselves.
     */
    public function onLinkExpired(PaymentLink $link): void
    {
        $link->markExpired();

        $order = $link->order;
        if ($order && ! in_array($order->status, ['paid', 'completed'], true)) {
            $order->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'outstanding_cents' => 0,
            ])->save();
            // Release the held seats + restore quota so the inventory frees up.
            app(TicketStateService::class)->releaseInventoryForOrder($order);
            Log::info("Delegated pay link {$link->id} expired; order {$order->id} cancelled, inventory released.");
            // A rescue "pay it yourself" email to the buyer can reuse a fresh link.
        }
    }
}
