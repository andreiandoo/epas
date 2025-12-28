<?php

namespace App\Jobs;

use App\Models\WalletPass;
use App\Services\Wallet\WalletService;
use App\Events\WalletPassGenerated;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateWalletPassJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $tenantId,
        public int $ticketId,
        public string $platform
    ) {}

    public function handle(WalletService $service): void
    {
        $result = $service->generatePass([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $this->ticketId,
            'platform' => $this->platform,
        ]);

        if ($result['success']) {
            event(new WalletPassGenerated($result['pass']));
        }
    }
}
