<?php

namespace App\Jobs;

use App\Enums\TopUpChannel;
use App\Models\Cashless\CashlessCreditAllocation;
use App\Services\Cashless\CashlessAccountService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AllocateDailyCreditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $editionId,
    ) {}

    public function handle(CashlessAccountService $accountService): void
    {
        $allocations = CashlessCreditAllocation::where('festival_edition_id', $this->editionId)
            ->where('is_active', true)
            ->where('allocation_type', 'daily')
            ->where(function ($q) {
                $q->whereNull('period_end')->orWhere('period_end', '>=', today());
            })
            ->where(function ($q) {
                $q->whereNull('period_start')->orWhere('period_start', '<=', today());
            })
            ->with('account')
            ->cursor();

        $credited = 0;
        $errors = 0;

        foreach ($allocations as $allocation) {
            try {
                $account = $allocation->account;
                if (! $account || ! $account->canTransact()) {
                    continue;
                }

                $accountService->topUp(
                    $account,
                    $allocation->amount_cents,
                    TopUpChannel::Physical,
                    operator: 'system:daily_credit',
                );

                $allocation->increment('total_allocated_cents', $allocation->amount_cents);
                $credited++;
            } catch (\Throwable $e) {
                $errors++;
                Log::error("Daily credit allocation failed", [
                    'allocation_id' => $allocation->id,
                    'account_id'    => $allocation->cashless_account_id,
                    'error'         => $e->getMessage(),
                ]);
            }
        }

        Log::info("Daily credit allocation completed", [
            'edition_id' => $this->editionId,
            'credited'   => $credited,
            'errors'     => $errors,
        ]);
    }
}
