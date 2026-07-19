<?php

namespace App\Http\Controllers;

use App\Models\InstallmentAgreement;
use App\Models\InstallmentPayment;
use App\Models\MarketplaceClient;
use App\Models\Order;
use App\Models\PaymentLink;
use App\Services\Installments\DelegatedPaymentService;
use App\Services\Installments\InstallmentChargeService;
use App\Services\Installments\ProcessorResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public surface for flexible payments: customer portal (schedule, early
 * payoff) and the shared pay-link (installment 3DS / manual, BNPL, delegated).
 * JSON-first to match the API-driven storefront; the processor's hosted page
 * handles actual card entry via the returned redirect_url.
 */
class FlexiblePaymentController extends Controller
{
    public function __construct(
        protected ProcessorResolver $resolver,
        protected InstallmentChargeService $charger,
        protected DelegatedPaymentService $delegated,
    ) {}

    /** Customer portal: agreement summary + schedule. */
    public function portal(int $agreement): JsonResponse
    {
        $a = InstallmentAgreement::with('payments')->findOrFail($agreement);

        return response()->json([
            'id' => $a->id,
            'status' => $a->status,
            'plan_type' => $a->plan_type,
            'currency' => $a->currency,
            'base_total' => $a->base_total_cents / 100,
            'surcharge' => $a->surcharge_cents / 100,
            'customer_total' => $a->customer_total_cents / 100,
            'outstanding' => $a->outstandingCents() / 100,
            'next_due_at' => $a->next_due_at,
            'event_start_date' => $a->event_start_date,
            'can_early_payoff' => $a->isActive() && $a->outstandingCents() > 0,
            'schedule' => $a->payments->map(fn ($p) => [
                'sequence' => $p->sequence,
                'label' => $p->sequence === 0 ? 'Avans' : "Rata {$p->sequence}",
                'due_date' => $p->due_date->toDateString(),
                'amount' => $p->amount_cents / 100,
                'status' => $p->status,
                'paid_at' => $p->paid_at,
                'pay_link' => $p->pay_link_token ? url("/pay/{$p->pay_link_token}") : null,
            ]),
        ]);
    }

    /** Early payoff: charge the remaining balance now. */
    public function earlyPayoff(int $agreement): JsonResponse
    {
        $a = InstallmentAgreement::findOrFail($agreement);
        $result = $this->charger->chargeRemaining($a);

        return response()->json([
            'ok' => ($result['status'] ?? null) === 'success',
            'result' => $result,
        ], ($result['status'] ?? null) === 'success' ? 200 : 422);
    }

    /** Pay-link details (installment / BNPL / delegated). */
    public function showLink(string $token): JsonResponse
    {
        $link = PaymentLink::where('token', $token)->firstOrFail();

        return response()->json([
            'token' => $link->token,
            'purpose' => $link->purpose,
            'amount' => $link->getAmount(),
            'currency' => $link->currency,
            'status' => $link->status,
            'expired' => $link->isExpired(),
            'payable' => $link->isActive(),
        ]);
    }

    /** Start on-session payment for a link → returns the processor redirect URL. */
    public function payLink(string $token): JsonResponse
    {
        $link = PaymentLink::where('token', $token)->firstOrFail();
        if (! $link->isActive()) {
            return response()->json(['error' => 'Link inactive or expired'], 422);
        }

        $client = $link->marketplace_client_id ? MarketplaceClient::find($link->marketplace_client_id) : null;
        $resolved = $client ? $this->resolver->forMarketplaceClient($client) : null;
        if (! $resolved) {
            return response()->json(['error' => 'No payment processor available'], 422);
        }

        $payment = $resolved['processor']->createPayment([
            'amount' => $link->getAmount(),
            'currency' => $link->currency,
            'description' => 'Plată — ' . $link->purpose,
            'order_id' => (string) ($link->order_id ?? $link->id),
            'customer_email' => $link->payer_email ?? '',
            'success_url' => url("/pay/{$token}/confirm"),
            'cancel_url' => url("/pay/{$token}"),
            'metadata' => ['payment_link_id' => $link->id],
        ]);

        return response()->json(['redirect_url' => $payment['redirect_url'] ?? null]);
    }

    /** Return handler after the hosted page: verify and settle. */
    public function confirmLink(string $token): JsonResponse
    {
        $link = PaymentLink::where('token', $token)->firstOrFail();
        if ($link->status === PaymentLink::STATUS_PAID) {
            return response()->json(['ok' => true, 'already' => true]);
        }

        // Mark paid (webhook is the source of truth; this is the UX return).
        $link->markPaid();

        if ($link->purpose === PaymentLink::PURPOSE_DELEGATED) {
            $this->delegated->confirm($link);
        } elseif ($link->purpose === PaymentLink::PURPOSE_INSTALLMENT && $link->installment_payment_id) {
            $p = InstallmentPayment::find($link->installment_payment_id);
            if ($p && $p->isPayable()) {
                $p->update([
                    'status' => InstallmentPayment::STATUS_PAID,
                    'paid_at' => now(),
                    'paid_amount_cents' => $p->amount_cents,
                ]);
                $agreement = $p->agreement;
                $agreement?->increment('paid_installments_count');
                $agreement?->recomputeNextDue();
            }
        }

        return response()->json(['ok' => true]);
    }

    /** Create a delegated-payment ("someone else pays") link for a reserved order. */
    public function createDelegated(Request $request, int $order): JsonResponse
    {
        $orderModel = Order::findOrFail($order);
        $link = $this->delegated->createLink($orderModel, [
            'payer_email' => $request->input('payer_email'),
            'payer_name' => $request->input('payer_name'),
        ]);

        return response()->json([
            'token' => $link->token,
            'pay_url' => url("/pay/{$link->token}"),
            'expires_at' => $link->expires_at,
        ]);
    }
}
