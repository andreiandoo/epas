<?php

namespace App\Observers;

use App\Models\FestivalEdition;
use App\Services\Cashless\CashlessBillingService;

class FestivalEditionObserver
{
    /**
     * When a festival edition is marked as completed,
     * auto-generate the cashless commission invoice.
     */
    public function updated(FestivalEdition $edition): void
    {
        if ($edition->isDirty('status') && $edition->status === 'completed') {
            if ($edition->cashless_mode && $edition->hasCashlessMicroservice()) {
                app(CashlessBillingService::class)->generateCompletionInvoice($edition);
            }
        }
    }
}
