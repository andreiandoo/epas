<?php

namespace App\Console\Commands;

use App\Models\ServiceOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeactivateExpiredFeaturing extends Command
{
    protected $signature = 'marketplace:deactivate-expired-featuring';

    protected $description = 'Deactivate expired event featuring service orders and remove featured flags from events';

    public function handle(): int
    {
        $expired = ServiceOrder::where('service_type', ServiceOrder::TYPE_FEATURING)
            ->where('status', ServiceOrder::STATUS_ACTIVE)
            ->where('service_end_date', '<', now()->startOfDay())
            ->get();

        if ($expired->isEmpty()) {
            return self::SUCCESS;
        }

        $deactivated = 0;
        $errors = 0;

        foreach ($expired as $order) {
            try {
                $order->complete();
                $deactivated++;
                Log::channel('marketplace')->info('Expired featuring order deactivated', [
                    'order_number'     => $order->order_number,
                    'event_id'         => $order->marketplace_event_id,
                    'service_end_date' => $order->service_end_date?->toDateString(),
                ]);
            } catch (\Throwable $e) {
                $errors++;
                Log::channel('marketplace')->error('Failed to deactivate expired featuring order', [
                    'order_number' => $order->order_number,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        $this->info("Deactivated {$deactivated} expired featuring orders. Errors: {$errors}.");
        Log::info("DeactivateExpiredFeaturing: deactivated={$deactivated}, errors={$errors}");

        return self::SUCCESS;
    }
}
