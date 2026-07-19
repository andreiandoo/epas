<?php

namespace App\Console\Commands;

use App\Jobs\ChargeInstallmentJob;
use App\Models\InstallmentAgreement;
use App\Models\InstallmentPayment;
use App\Services\Installments\InstallmentAgreementService;
use App\Services\Installments\InstallmentDunningService;
use Illuminate\Console\Command;

/**
 * Finds installments that are due and dispatches an off-session charge for
 * each, then sweeps for agreements that should be defaulted.
 */
class ProcessDueInstallments extends Command
{
    protected $signature = 'installments:process-due {--sync : Charge inline instead of queueing}';

    protected $description = 'Charge due flexible-payment installments and sweep defaults';

    public function handle(InstallmentDunningService $dunning): int
    {
        // Recover installments stuck in 'processing' (worker crashed mid-charge)
        // so they get retried instead of hanging forever.
        $recovered = InstallmentPayment::query()
            ->where('status', InstallmentPayment::STATUS_PROCESSING)
            ->where('last_attempt_at', '<', now()->subMinutes(30))
            ->update(['status' => InstallmentPayment::STATUS_RETRYING]);
        if ($recovered > 0) {
            $this->warn("Recovered {$recovered} stuck 'processing' installment(s).");
        }

        // Reconcile active agreements against their live event date (§16.3): if
        // an organizer moved the event, re-fit the remaining schedule BEFORE we
        // charge, so we never debit a customer past a moved-up deadline.
        $agreements = app(InstallmentAgreementService::class);
        $reconciled = 0;
        InstallmentAgreement::query()
            ->where('status', InstallmentAgreement::STATUS_ACTIVE)
            ->whereNotNull('event_id')
            ->chunkById(200, function ($chunk) use ($agreements, &$reconciled) {
                foreach ($chunk as $agreement) {
                    try {
                        if ($agreements->reconcileEventStartDate($agreement)) {
                            $reconciled++;
                        }
                    } catch (\Throwable $e) {
                        $this->warn("Reconcile failed for agreement {$agreement->id}: {$e->getMessage()}");
                    }
                }
            });
        if ($reconciled > 0) {
            $this->warn("Reconciled {$reconciled} agreement(s) to a changed event date.");
        }

        $due = InstallmentPayment::query()
            ->whereIn('status', [
                InstallmentPayment::STATUS_SCHEDULED,
                InstallmentPayment::STATUS_DUE,
                InstallmentPayment::STATUS_RETRYING,
            ])
            ->where('sequence', '>', 0)
            ->where('due_date', '<=', now())
            ->whereHas('agreement', fn ($q) => $q->where('status', 'active'))
            ->orderBy('due_date')
            ->limit(2000)
            ->get();

        $this->info("Found {$due->count()} due installment(s).");

        foreach ($due as $payment) {
            if ($this->option('sync')) {
                app(\App\Services\Installments\InstallmentChargeService::class)->charge($payment);
            } else {
                ChargeInstallmentJob::dispatch($payment->id);
            }
        }

        $defaulted = $dunning->sweepDefaults();
        if ($defaulted > 0) {
            $this->warn("Defaulted {$defaulted} agreement(s).");
        }

        return self::SUCCESS;
    }
}
