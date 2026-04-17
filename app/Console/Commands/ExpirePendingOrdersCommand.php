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

        $success = 0;
        $failed = 0;

        foreach ($expired as $order) {
            try {
                $this->expireOrder($order);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                Log::channel('marketplace')->error('Failed to expire order', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($success > 0 || $failed > 0) {
            $msg = "Expired {$success} pending orders" . ($failed > 0 ? " ({$failed} failed)" : '');
            Log::channel('marketplace')->info($msg);
            $this->info($msg . '.');
        }

        return self::SUCCESS;
    }

    /**
     * Expire a single order explicitly. We drive every step from the command
     * instead of relying on the `saved` observer so partial failures can't
     * leave the order in a half-reconciled state.
     */
    protected function expireOrder(Order $order): void
    {
        // 1. Release seats first — if this throws, the order keeps status=pending
        //    and the next cron run retries instead of getting "stuck" expired.
        $order->releaseSeatsAndRestoreStock();

        // 2. Cancel all non-cancelled tickets for this order.
        $order->tickets()
            ->whereNotIn('status', ['cancelled'])
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

        // 3. Finally flip the order. skipTicketSync avoids the observer
        //    duplicating work we already did explicitly.
        $order->skipTicketSync = true;
        $order->update([
            'status' => 'expired',
            'payment_status' => $order->payment_status === 'pending' ? 'expired' : $order->payment_status,
        ]);
    }
}
