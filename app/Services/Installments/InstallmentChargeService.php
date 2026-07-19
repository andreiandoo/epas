<?php

namespace App\Services\Installments;

use App\Models\InstallmentAgreement;
use App\Models\InstallmentPayment;
use App\Models\MarketplaceClient;
use App\Models\PaymentLink;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Charges a single due installment off-session (MIT) against the stored
 * mandate. Idempotent ("first to pay wins"), handles 3DS (action_required),
 * hard/soft declines with retry/backoff, and completion.
 */
class InstallmentChargeService
{
    public function __construct(
        protected ProcessorResolver $resolver,
        protected FlexiblePaymentMailer $mailer,
        protected InstallmentAgreementService $agreements,
    ) {}

    /**
     * @return array{status: string, message?: string}
     */
    public function charge(InstallmentPayment $payment): array
    {
        // Idempotency: lock and re-check inside a transaction.
        $payment = DB::transaction(function () use ($payment) {
            $locked = InstallmentPayment::whereKey($payment->id)->lockForUpdate()->first();
            if ($locked && $locked->isPayable()) {
                $locked->update(['status' => InstallmentPayment::STATUS_PROCESSING, 'last_attempt_at' => now()]);
            }
            return $locked;
        });

        if (! $payment || $payment->status !== InstallmentPayment::STATUS_PROCESSING) {
            return ['status' => 'skipped', 'message' => 'Not payable (already settled or locked)'];
        }

        $agreement = $payment->agreement;
        if (! $agreement || ! $agreement->isActive()) {
            $this->revert($payment, InstallmentPayment::STATUS_SCHEDULED);
            return ['status' => 'skipped', 'message' => 'Agreement not active'];
        }

        if (! $agreement->mandate_reference) {
            $this->fail($payment, $agreement, 'No mandate on file', false);
            return ['status' => 'failed', 'message' => 'No mandate'];
        }

        $client = $agreement->marketplace_client_id ? MarketplaceClient::find($agreement->marketplace_client_id) : null;
        $processor = $client ? $this->resolver->tokenizableForMarketplaceClient($client) : null;
        if (! $processor) {
            $this->revert($payment, InstallmentPayment::STATUS_SCHEDULED);
            Log::warning("Installments: no tokenizable processor for agreement {$agreement->id}");
            return ['status' => 'skipped', 'message' => 'No tokenizable processor'];
        }

        try {
            $result = $processor->chargeWithToken($agreement->mandate_reference, [
                'amount' => $payment->amount_cents / 100,
                'currency' => $agreement->currency,
                'description' => 'Rata ' . $payment->sequence . ' — comanda #' . $agreement->order_id,
                'order_id' => (string) $agreement->order_id,
                'idempotency_key' => (string) $payment->id,
                'metadata' => ['agreement_id' => $agreement->id, 'sequence' => $payment->sequence],
            ]);
        } catch (\Throwable $e) {
            $this->fail($payment, $agreement, $e->getMessage(), false);
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }

        return match ($result['status'] ?? 'failed') {
            'success' => $this->succeed($payment, $agreement, $result),
            'action_required' => $this->actionRequired($payment, $agreement, $result),
            default => $this->fail(
                $payment,
                $agreement,
                $result['error'] ?? 'declined',
                (bool) ($result['hard_decline'] ?? false),
                $result['decline_code'] ?? null
            ),
        };
    }

    protected function succeed(InstallmentPayment $payment, InstallmentAgreement $agreement, array $result): array
    {
        DB::transaction(function () use ($payment, $agreement, $result) {
            $payment->update([
                'status' => InstallmentPayment::STATUS_PAID,
                'paid_at' => now(),
                'paid_amount_cents' => $payment->amount_cents,
                'payment_reference' => $result['payment_id'] ?? null,
            ]);
            $agreement->increment('paid_installments_count');
            $agreement->log('charged', "Installment {$payment->sequence} charged", [
                'reference' => $result['payment_id'] ?? null,
            ], $payment->id);
            $agreement->recomputeNextDue();
        });

        // Incremental organizer payout (Phase 5 hook).
        app(InstallmentPayoutService::class)->creditInstallment($agreement, $payment);

        $this->mailer->send($agreement, 'installment_payment_receipt', $this->mailer->paymentVariables($payment, $agreement->currency));

        // Completion → ticket valid.
        if ($this->allSettled($agreement)) {
            $this->agreements->complete($agreement->fresh());
            app(TicketStateService::class)->markValidForAgreement($agreement);
            $this->mailer->send($agreement->fresh(), 'installment_plan_completed');
        }

        return ['status' => 'success'];
    }

    protected function actionRequired(InstallmentPayment $payment, InstallmentAgreement $agreement, array $result): array
    {
        $link = $this->ensurePayLink($payment, $agreement);
        $payment->update([
            'status' => InstallmentPayment::STATUS_ACTION_REQUIRED,
            'pay_link_token' => $link->token,
            'last_error' => 'authentication_required',
        ]);
        $agreement->log('action_required', 'Installment needs SCA authentication', [
            'action_url' => $result['action_url'] ?? null,
        ], $payment->id);

        $this->mailer->send($agreement, 'installment_action_required', $this->mailer->paymentVariables($payment->fresh(), $agreement->currency));

        return ['status' => 'action_required'];
    }

    protected function fail(InstallmentPayment $payment, InstallmentAgreement $agreement, string $error, bool $hard, ?string $declineCode = null): array
    {
        $policy = $agreement->plan?->defaultPolicy() ?? config('installments.dunning');
        $maxRetries = (int) ($policy['max_retries'] ?? 3);
        $backoff = $policy['retry_backoff_days'] ?? [1, 3, 5];

        $payment->increment('attempts');
        $payment->refresh();

        // Hard declines (stolen/closed card) or exhausted retries → hand to dunning.
        if ($hard || $payment->attempts > $maxRetries) {
            $payment->update([
                'status' => InstallmentPayment::STATUS_FAILED,
                'last_error' => $error,
            ]);
            $agreement->log('failed', "Installment {$payment->sequence} failed permanently", [
                'error' => $error, 'decline_code' => $declineCode, 'hard' => $hard,
            ], $payment->id);

            $link = $this->ensurePayLink($payment, $agreement);
            $payment->update(['pay_link_token' => $link->token]);
            app(InstallmentDunningService::class)->onPaymentFailed($agreement->fresh(), $payment->fresh());

            return ['status' => 'failed', 'message' => $error];
        }

        // Schedule a retry with backoff.
        $delayDays = $backoff[min($payment->attempts - 1, count($backoff) - 1)] ?? 3;
        $link = $this->ensurePayLink($payment, $agreement);
        $payment->update([
            'status' => InstallmentPayment::STATUS_RETRYING,
            'due_date' => Carbon::now()->addDays($delayDays),
            'last_error' => $error,
            'pay_link_token' => $link->token,
        ]);
        $agreement->log('retried', "Installment {$payment->sequence} retry in {$delayDays}d", [
            'attempt' => $payment->attempts, 'error' => $error,
        ], $payment->id);
        $agreement->recomputeNextDue();

        $this->mailer->send($agreement, 'installment_payment_failed', array_merge(
            $this->mailer->paymentVariables($payment->fresh(), $agreement->currency),
            ['retry_days' => (string) $delayDays]
        ));

        return ['status' => 'retrying', 'message' => $error];
    }

    protected function ensurePayLink(InstallmentPayment $payment, InstallmentAgreement $agreement): PaymentLink
    {
        if ($payment->pay_link_token) {
            $existing = PaymentLink::where('token', $payment->pay_link_token)->first();
            if ($existing && $existing->isActive()) {
                return $existing;
            }
        }

        return PaymentLink::create([
            'purpose' => PaymentLink::PURPOSE_INSTALLMENT,
            'marketplace_client_id' => $agreement->marketplace_client_id,
            'tenant_id' => $agreement->tenant_id,
            'order_id' => $agreement->order_id,
            'installment_payment_id' => $payment->id,
            'amount_cents' => $payment->amount_cents,
            'currency' => $agreement->currency,
            'expires_at' => now()->addDays(7),
            'payer_email' => $agreement->customer_email,
        ]);
    }

    /**
     * Early payoff: charge the full remaining balance in one MIT and settle all
     * outstanding installments at once. Ticket becomes valid immediately.
     */
    public function chargeRemaining(InstallmentAgreement $agreement): array
    {
        if (! $agreement->isActive() || ! $agreement->mandate_reference) {
            return ['status' => 'skipped', 'message' => 'Not active or no mandate'];
        }

        $outstanding = $agreement->outstandingCents();
        if ($outstanding <= 0) {
            return ['status' => 'skipped', 'message' => 'Nothing outstanding'];
        }

        $client = $agreement->marketplace_client_id ? MarketplaceClient::find($agreement->marketplace_client_id) : null;
        $processor = $client ? $this->resolver->tokenizableForMarketplaceClient($client) : null;
        if (! $processor) {
            return ['status' => 'skipped', 'message' => 'No tokenizable processor'];
        }

        $result = $processor->chargeWithToken($agreement->mandate_reference, [
            'amount' => $outstanding / 100,
            'currency' => $agreement->currency,
            'description' => 'Plată anticipată — comanda #' . $agreement->order_id,
            'order_id' => (string) $agreement->order_id,
            'idempotency_key' => 'payoff-' . $agreement->id,
            'metadata' => ['agreement_id' => $agreement->id, 'early_payoff' => true],
        ]);

        if (($result['status'] ?? null) !== 'success') {
            return ['status' => $result['status'] ?? 'failed', 'message' => $result['error'] ?? 'declined'];
        }

        DB::transaction(function () use ($agreement, $result) {
            foreach ($agreement->payments()->where('sequence', '>', 0)->whereNotIn('status', ['paid', 'waived'])->get() as $p) {
                $p->update([
                    'status' => InstallmentPayment::STATUS_PAID,
                    'paid_at' => now(),
                    'paid_amount_cents' => $p->amount_cents,
                    'payment_reference' => $result['payment_id'] ?? null,
                ]);
            }
            $agreement->update([
                'paid_installments_count' => $agreement->payments()->where('status', 'paid')->count(),
            ]);
            $agreement->log('early_payoff', 'Remaining balance paid early', ['reference' => $result['payment_id'] ?? null]);
        });

        $this->agreements->complete($agreement->fresh());
        app(TicketStateService::class)->markValidForAgreement($agreement);
        $this->mailer->send($agreement->fresh(), 'installment_early_payoff_receipt');

        return ['status' => 'success'];
    }

    protected function allSettled(InstallmentAgreement $agreement): bool
    {
        return ! $agreement->payments()
            ->whereNotIn('status', [InstallmentPayment::STATUS_PAID, InstallmentPayment::STATUS_WAIVED])
            ->exists();
    }

    protected function revert(InstallmentPayment $payment, string $status): void
    {
        $payment->update(['status' => $status]);
    }
}
