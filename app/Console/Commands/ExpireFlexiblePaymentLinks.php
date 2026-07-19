<?php

namespace App\Console\Commands;

use App\Models\PaymentLink;
use App\Services\Installments\DelegatedPaymentService;
use Illuminate\Console\Command;

/**
 * Expires stale payment links and releases delegated-payment holds whose
 * 24h window has lapsed.
 */
class ExpireFlexiblePaymentLinks extends Command
{
    protected $signature = 'installments:expire-links';

    protected $description = 'Expire stale payment links and release delegated-pay holds';

    public function handle(DelegatedPaymentService $delegated): int
    {
        $expired = 0;

        PaymentLink::where('status', PaymentLink::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(500, function ($links) use (&$expired, $delegated) {
                foreach ($links as $link) {
                    if ($link->purpose === PaymentLink::PURPOSE_DELEGATED) {
                        $delegated->onLinkExpired($link);
                    } else {
                        $link->markExpired();
                    }
                    $expired++;
                }
            });

        $this->info("Expired {$expired} payment link(s).");
        return self::SUCCESS;
    }
}
