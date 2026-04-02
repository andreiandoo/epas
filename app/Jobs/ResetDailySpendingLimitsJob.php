<?php

namespace App\Jobs;

use App\Models\Cashless\CashlessSpendingLimit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResetDailySpendingLimitsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        CashlessSpendingLimit::where('is_active', true)
            ->where('daily_spent_cents', '>', 0)
            ->update(['daily_spent_cents' => 0]);
    }
}
