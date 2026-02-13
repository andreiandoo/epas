<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Seating\EventSeat;
use App\Models\Seating\SeatHold;
use App\Models\TicketType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupExpiredOrders extends Command
{
    protected $signature = 'marketplace:cleanup-expired-orders
                            {--dry-run : Preview what would be cleaned up without making changes}';

    protected $description = 'Clean up expired pending marketplace orders: release held seats, cancel tickets, restore quota';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Find expired pending orders (status=pending, expires_at has passed)
        $expiredOrders = Order::where('status', 'pending')
            ->where('payment_status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        if ($expiredOrders->isEmpty()) {
            $this->comment('No expired pending orders found.');
            return 0;
        }

        $this->info("Found {$expiredOrders->count()} expired pending order(s).");

        $cleaned = 0;

        foreach ($expiredOrders as $order) {
            $this->line("  Processing order #{$order->order_number} (ID: {$order->id}, expired: {$order->expires_at})");

            if ($dryRun) {
                $seatedItems = $order->meta['seated_items'] ?? [];
                $ticketCount = $order->tickets()->where('status', 'pending')->count();
                $this->line("    - Would release seats from " . count($seatedItems) . " seated item(s)");
                $this->line("    - Would cancel {$ticketCount} pending ticket(s)");
                $this->line("    - Would restore quota for " . $order->items()->count() . " order item(s)");
                $cleaned++;
                continue;
            }

            try {
                DB::transaction(function () use ($order) {
                    // 1. Release held seats back to available
                    $seatedItems = $order->meta['seated_items'] ?? [];
                    foreach ($seatedItems as $seatedItem) {
                        $eventSeatingId = $seatedItem['event_seating_id'] ?? null;
                        $seatUids = $seatedItem['seat_uids'] ?? [];

                        if ($eventSeatingId && !empty($seatUids)) {
                            // Release seats that are still 'held' (not yet sold by another process)
                            $released = EventSeat::where('event_seating_id', $eventSeatingId)
                                ->whereIn('seat_uid', $seatUids)
                                ->where('status', 'held')
                                ->update([
                                    'status' => 'available',
                                    'version' => DB::raw('version + 1'),
                                    'last_change_at' => now(),
                                ]);

                            // Clean up any remaining hold records
                            SeatHold::where('event_seating_id', $eventSeatingId)
                                ->whereIn('seat_uid', $seatUids)
                                ->delete();

                            if ($released > 0) {
                                Log::channel('marketplace')->info('CleanupExpiredOrders: Released seats', [
                                    'order_id' => $order->id,
                                    'event_seating_id' => $eventSeatingId,
                                    'released_count' => $released,
                                ]);
                            }
                        }
                    }

                    // 2. Cancel pending tickets
                    $cancelledTickets = $order->tickets()
                        ->where('status', 'pending')
                        ->update(['status' => 'cancelled']);

                    // 3. Restore quota_sold for ticket types
                    foreach ($order->items as $orderItem) {
                        if ($orderItem->ticket_type_id && $orderItem->quantity > 0) {
                            TicketType::where('id', $orderItem->ticket_type_id)
                                ->where('quota_sold', '>=', $orderItem->quantity)
                                ->decrement('quota_sold', $orderItem->quantity);
                        }
                    }

                    // 4. Mark order as expired
                    $order->update([
                        'status' => 'expired',
                        'payment_status' => 'expired',
                    ]);

                    Log::channel('marketplace')->info('CleanupExpiredOrders: Order expired', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'cancelled_tickets' => $cancelledTickets,
                    ]);
                });

                $this->info("    ✓ Cleaned up successfully");
                $cleaned++;
            } catch (\Exception $e) {
                $this->error("    ✗ Error: {$e->getMessage()}");
                Log::channel('marketplace')->error('CleanupExpiredOrders: Failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Done. Cleaned up {$cleaned}/{$expiredOrders->count()} expired orders.");

        return 0;
    }
}
