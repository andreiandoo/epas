<?php

namespace App\Console\Commands;

use App\Jobs\ChargeInstallmentJob;
use App\Models\InstallmentPayment;
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
