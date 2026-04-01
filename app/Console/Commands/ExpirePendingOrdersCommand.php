<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpirePendingOrdersCommand extends Command
{
    protected $signature = 'orders:expire-pending';
    protected $description = 'Expire pending orders that have passed their expires_at time';

    public function handle(): int
    {
        $expired = Order::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($expired as $order) {
            $order->update([
                'status' => 'expired',
                'payment_status' => $order->payment_status === 'pending' ? 'expired' : $order->payment_status,
            ]);

            // Cancel tickets
            $order->tickets()->where('status', 'pending')->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

            // Release stock
            $order->releaseSeatsAndRestoreStock();

            $count++;
        }

        if ($count > 0) {
            Log::channel('marketplace')->info("Expired {$count} pending orders");
            $this->info("Expired {$count} pending orders.");
        }

        return self::SUCCESS;
    }
}
