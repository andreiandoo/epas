<?php

namespace App\Console\Commands;

use App\Models\InstallmentPayment;
use App\Services\Installments\FlexiblePaymentMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Sends "upcoming installment" reminders ahead of each due date, per the
 * configured cadence (config('installments.reminder_days_before')).
 * De-duplicated via reminder_sent_at.
 */
class SendInstallmentReminders extends Command
{
    protected $signature = 'installments:send-reminders';

    protected $description = 'Send flexible-payment reminder emails before due dates';

    public function handle(FlexiblePaymentMailer $mailer): int
    {
        $daysBefore = (array) config('installments.reminder_days_before', [7, 3, 1]);
        $today = Carbon::today();
        $sent = 0;

        // Candidate due dates: today + each offset.
        $targetDates = collect($daysBefore)
            ->map(fn ($d) => $today->copy()->addDays((int) $d)->toDateString())
            ->all();

        InstallmentPayment::query()
            ->whereIn('status', [
                InstallmentPayment::STATUS_SCHEDULED,
                InstallmentPayment::STATUS_DUE,
                InstallmentPayment::STATUS_RETRYING,
            ])
            ->where('sequence', '>', 0)
            ->whereHas('agreement', fn ($q) => $q->where('status', 'active'))
            ->with('agreement')
            ->orderBy('due_date')
            ->chunkById(500, function ($payments) use ($targetDates, $mailer, &$sent) {
                foreach ($payments as $payment) {
                    $dueDay = $payment->due_date->toDateString();

                    // Reminder before due date.
                    if (in_array($dueDay, $targetDates, true)) {
                        // Only one reminder per calendar day.
                        if ($payment->reminder_sent_at && $payment->reminder_sent_at->isToday()) {
                            continue;
                        }
                        $agreement = $payment->agreement;
                        if ($agreement) {
                            $mailer->send($agreement, 'installment_payment_upcoming', $mailer->paymentVariables($payment, $agreement->currency));
                            $payment->update(['reminder_sent_at' => now()]);
                            $sent++;
                        }
                    }
                }
            });

        $this->info("Sent {$sent} reminder(s).");
        return self::SUCCESS;
    }
}
