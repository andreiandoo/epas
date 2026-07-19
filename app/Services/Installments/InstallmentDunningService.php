<?php

namespace App\Services\Installments;

use App\Models\InstallmentAgreement;
use App\Models\InstallmentPayment;
use Illuminate\Support\Carbon;

/**
 * Dunning + default handling: escalating warnings after a failed installment,
 * and moving an agreement to `defaulted` when the grace period lapses or the
 * event deadline is reached without full payment.
 */
class InstallmentDunningService
{
    public function __construct(
        protected FlexiblePaymentMailer $mailer,
        protected TicketStateService $tickets,
    ) {}

    /**
     * Called when an installment has exhausted its retries.
     */
    public function onPaymentFailed(InstallmentAgreement $agreement, InstallmentPayment $payment): void
    {
        $payment->increment('dunning_stage');
        $agreement->log('overdue', "Installment {$payment->sequence} overdue (dunning)", [], $payment->id);

        $this->mailer->send($agreement, 'installment_overdue', $this->mailer->paymentVariables($payment, $agreement->currency));

        // If the event deadline is upon us, default immediately.
        if ($this->deadlineReached($agreement)) {
            $this->default($agreement, 'deadline_reached');
            return;
        }

        $this->mailer->send($agreement, 'installment_default_warning');
    }

    /**
     * Sweep active agreements and default any that have blown past their grace
     * window or the event deadline. Called by the scheduler.
     */
    public function sweepDefaults(): int
    {
        $count = 0;
        InstallmentAgreement::where('status', InstallmentAgreement::STATUS_ACTIVE)
            ->whereHas('payments', fn ($q) => $q->where('status', InstallmentPayment::STATUS_FAILED))
            ->with('payments')
            ->chunkById(100, function ($agreements) use (&$count) {
                foreach ($agreements as $agreement) {
                    $policy = $agreement->plan?->defaultPolicy() ?? config('installments.dunning');
                    $graceDays = (int) ($policy['grace_days'] ?? 3);

                    $failed = $agreement->payments->firstWhere('status', InstallmentPayment::STATUS_FAILED);
                    $overGrace = $failed && $failed->last_attempt_at
                        && $failed->last_attempt_at->copy()->addDays($graceDays)->isPast();

                    if ($overGrace || $this->deadlineReached($agreement)) {
                        $this->default($agreement, $overGrace ? 'grace_lapsed' : 'deadline_reached');
                        $count++;
                    }
                }
            });

        return $count;
    }

    /**
     * Manual cancellation (admin action). Distinct from default(): status
     * becomes 'cancelled' (not 'defaulted'). Stops debits, cancels the remaining
     * schedule, and invalidates tickets.
     */
    public function cancel(InstallmentAgreement $agreement, string $reason = 'manual_cancel'): void
    {
        $agreement->update([
            'status' => InstallmentAgreement::STATUS_CANCELLED,
            'auto_debit_enabled' => false,
            'next_due_at' => null,
        ]);
        $agreement->payments()
            ->whereIn('status', [
                InstallmentPayment::STATUS_SCHEDULED, InstallmentPayment::STATUS_DUE,
                InstallmentPayment::STATUS_RETRYING, InstallmentPayment::STATUS_ACTION_REQUIRED,
                InstallmentPayment::STATUS_FAILED,
            ])
            ->update(['status' => InstallmentPayment::STATUS_CANCELLED]);

        $this->tickets->invalidateForAgreement($agreement);
        $agreement->log('cancelled', "Agreement cancelled ({$reason})");
    }

    public function default(InstallmentAgreement $agreement, string $reason): void
    {
        $agreement->update(['status' => InstallmentAgreement::STATUS_DEFAULTED, 'next_due_at' => null]);
        $agreement->payments()
            ->whereIn('status', [
                InstallmentPayment::STATUS_SCHEDULED, InstallmentPayment::STATUS_DUE,
                InstallmentPayment::STATUS_RETRYING, InstallmentPayment::STATUS_ACTION_REQUIRED,
                InstallmentPayment::STATUS_FAILED,
            ])
            ->update(['status' => InstallmentPayment::STATUS_CANCELLED]);

        $this->tickets->invalidateForAgreement($agreement);
        $agreement->log('defaulted', "Agreement defaulted ({$reason})");
        $this->mailer->send($agreement->fresh(), 'installment_defaulted');
    }

    protected function deadlineReached(InstallmentAgreement $agreement): bool
    {
        if (! $agreement->event_start_date) {
            return false;
        }
        $daysBefore = (int) ($agreement->plan_snapshot['plan']['days_before_event_fully_paid'] ?? 1);
        return Carbon::now()->gte($agreement->event_start_date->copy()->subDays($daysBefore));
    }
}
