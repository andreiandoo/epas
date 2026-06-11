<?php

namespace App\Listeners;

use App\Events\WalletPassGenerated;
use App\Notifications\WalletPassReadyNotification;

class SendWalletPassNotification
{
    public function handle(WalletPassGenerated $event): void
    {
        if (!config('wallet.notifications.send_on_generation')) {
            return;
        }

        $pass = $event->walletPass;
        $customer = $pass->ticket->customer ?? null;

        if ($customer) {
            $customer->notify(new WalletPassReadyNotification($pass));
        }
    }
}
