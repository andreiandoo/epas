<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Reconcile orders that ended in a terminal state but still have tickets
 * marked `pending`/`valid` or seats held/sold against them. Fixes "orphaned"
 * state caused by partial observer failures.
 *
 * Usage:
 *   php artisan orders:reconcile-expired             # reconcile all affected orders
 *   php artisan orders:reconcile-expired --order=123 # reconcile a single order by id
 *   php artisan orders:reconcile-expired --dry-run   # report only, no writes
 */
class ReconcileExpiredOrdersCommand extends Command
{
    protected $signature = 'orders:reconcile-expired
        {--order= : Limit to a single order id}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Cancel tickets and release seats for orders stuck in a terminal status';

    protected array $terminalStatuses = ['expired', 'cancelled', 'refunded', 'failed'];
    protected array $activeTicketStatuses = ['pending', 'valid'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $orderId = $this->option('order');

        $query = Order::query()
            ->whereIn('status', $this->terminalStatuses)
            ->whereHas('tickets', fn ($q) => $q->whereIn('status', $this->activeTicketStatuses));

        if ($orderId) {
            $query->where('id', $orderId);
        }

        $orders = $query->get();

        if ($orders->isEmpty()) {
            $this->info('No orders need reconciliation.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Found {$orders->count()} order(s) to reconcile.");

        $fixed = 0;
        $failed = 0;

        foreach ($orders as $order) {
            $activeTickets = $order->tickets()->whereIn('status', $this->activeTicketStatuses)->count();

            if ($dryRun) {
                $this->line("  Order #{$order->id} ({$order->order_number}, status={$order->status}) — {$activeTickets} active ticket(s) to cancel");
                continue;
            }

            try {
                $this->reconcileOrder($order);
                $fixed++;
                $this->line("  Reconciled order #{$order->id} ({$activeTickets} ticket(s))");
            } catch (\Throwable $e) {
                $failed++;
                Log::channel('marketplace')->error('Reconcile: failed to fix order', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  Failed order #{$order->id}: {$e->getMessage()}");
            }
        }

        if (!$dryRun) {
            Log::channel('marketplace')->info("Reconciled {$fixed} orders" . ($failed > 0 ? " ({$failed} failed)" : ''));
            $this->info("Done: {$fixed} fixed" . ($failed > 0 ? ", {$failed} failed" : '') . '.');
        }

        return self::SUCCESS;
    }

    protected function reconcileOrder(Order $order): void
    {
        // Match the exact sequence used by ExpirePendingOrdersCommand so behavior
        // is identical between fresh expiries and backfill runs.
        $order->releaseSeatsAndRestoreStock();

        $order->tickets()
            ->whereIn('status', $this->activeTicketStatuses)
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);
    }
}
