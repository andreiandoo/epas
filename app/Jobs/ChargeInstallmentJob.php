<?php

namespace App\Jobs;

use App\Models\InstallmentPayment;
use App\Services\Installments\InstallmentChargeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Charges a single due installment off-session. Idempotent: the charge
 * service locks the row and no-ops if already settled, so re-dispatch is safe.
 */
class ChargeInstallmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // retries are managed by the charge service (backoff schedule)

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
