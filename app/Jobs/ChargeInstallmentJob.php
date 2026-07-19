<?php

namespace App\Jobs;

use App\Models\InstallmentPayment;
use App\Services\Installments\InstallmentChargeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Charges a single due installment off-session. Unique per installment so two
 * overlapping process-due runs can't enqueue the same charge twice; the charge
 * service also atomically claims the row, so re-dispatch is doubly safe.
 */
class ChargeInstallmentJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // retries are managed by the charge service (backoff schedule)

    // The unique lock is held until the job finishes (defensive upper bound).
    public int $uniqueFor = 900;

    public function __construct(public int $installmentPaymentId)
    {
    }

    /**
     * Prevent two workers charging the same installment concurrently.
     */
    public function uniqueId(): string
    {
        return 'charge-installment-' . $this->installmentPaymentId;
    }

    public function handle(InstallmentChargeService $service): void
    {
        $payment = InstallmentPayment::find($this->installmentPaymentId);
        if (! $payment) {
            return;
        }
        $service->charge($payment);
    }
}
