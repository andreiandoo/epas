<?php

namespace App\Events;

use App\Models\WalletPass;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletPassGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public WalletPass $walletPass) {}
}
