<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Backfill: paid orders whose tickets stayed in 'pending' because a
 * silent observer error broke the payment-callback's ticket-activation
 * step. Marks tickets as 'valid' and confirms held seats for seated
 * orders. Idempotent — safe to re-run.
 *
 * Symptom that triggered this: PaymentController updated orders to
 * status=completed/payment_status=paid; the OrderObserver then threw
 * \Error("sum() on null") because OrganizerNotificationService::notifySale
 * accessed Order->orderItems (a non-existent relation) instead of
 * Order->items. The catch (\Exception) in the observer didn't catch
 * \Error, the exception bubbled past the tickets()->update() line, and
 * subsequent webhook retries hit the idempotency guard.
 */
class FixPendingTicketsOnPaidOrders extends Command
{
    protected $signature = 'fix:pending-tickets-on-paid-orders
        {--dry-run : Print what would change without writing to DB}
        {--id= : Limit to a single order id (for spot-checks)}';

    protected $description = 'Activate tickets for paid orders that were left in pending due to the notifySale observer bug';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $onlyId = $this->option('id');

        $query = Order::query()
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('payment_status', 'paid')
            ->whereHas('tickets', fn ($q) => $q->where('status', 'pending'));

        if ($onlyId) {
            $query->where('id', (int) $onlyId);
        }

        $orders = $query->with('tickets')->get();

        if ($orders->isEmpty()) {
            $this->info('Nothing to fix.');
            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Found {$orders->count()} order(s) with pending tickets.");

        $totalTickets = 0;
        $seatConfirmFailures = 0;

        foreach ($orders as $order) {
            $pending = $order->tickets->where('status', 'pending');
            $count = $pending->count();
            $totalTickets += $count;

            if ($dryRun) {
                $this->line("  - Order #{$order->order_number} (id={$order->id}): {$count} pending ticket(s)");
                continue;
            }

            DB::transaction(function () use ($order, $pending, &$seatConfirmFailures) {
                Ticket::whereIn('id', $pending->pluck('id'))->update([
                    'status' => 'valid',
                    'updated_at' => now(),
                ]);

                // For seated orders, confirm any seats still held in the seat
                // service. Idempotent — confirmPurchase is a no-op for already
                // sold seats. Wrap in try/catch so a partial seat-state
                // mismatch doesn't roll back the ticket activation.
                $seatedItems = $order->meta['seated_items'] ?? [];
                if (!empty($seatedItems)) {
                    try {
                        $seatHoldService = app(\App\Services\Seating\SeatHoldService::class);
                        foreach ($seatedItems as $seatedItem) {
                            $seatHoldService->confirmPurchase(
                                $seatedItem['event_seating_id'],
                                $seatedItem['seat_uids'],
                                'backfill-pending-tickets',
                                (int) ($order->total * 100)
                            );
                        }
                    } catch (\Throwable $e) {
                        $seatConfirmFailures++;
                        Log::warning('Backfill: seat confirm skipped', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

            $this->info("  - Order #{$order->order_number} (id={$order->id}): {$count} ticket(s) activated");
        }

        $verb = $dryRun ? 'Would activate' : 'Activated';
        $this->info("{$verb} {$totalTickets} ticket(s) across {$orders->count()} order(s).");
        if ($seatConfirmFailures > 0) {
            $this->warn("Seat confirm skipped on {$seatConfirmFailures} order(s) — see laravel.log for details.");
        }

        return self::SUCCESS;
    }
}
