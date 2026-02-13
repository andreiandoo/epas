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
                            {--dry-run : Preview what would be cleaned up without making changes}
                            {--fix-seats : Also release stuck seats from already-expired orders}';

    protected $description = 'Clean up expired pending marketplace orders: release held/sold seats, cancel tickets, restore quota';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $fixSeats = $this->option('fix-seats');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Find expired pending orders
        $expiredOrders = Order::where('status', 'pending')
            ->where('payment_status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        if ($expiredOrders->isEmpty() && !$fixSeats) {
            $this->comment('No expired pending orders found.');
            return 0;
        }

        if ($expiredOrders->isNotEmpty()) {
            $this->info("Found {$expiredOrders->count()} expired pending order(s).");
            $this->processOrders($expiredOrders, $dryRun);
        }

        // Optionally fix seats from already-expired orders that still have stuck seats
        if ($fixSeats) {
            $this->info('');
            $this->info('Checking already-expired orders for stuck seats...');
            $expiredWithSeats = Order::where('status', 'expired')
                ->get();

            $fixed = 0;
            foreach ($expiredWithSeats as $order) {
                $seatInfo = $this->extractSeatInfo($order);
                if (empty($seatInfo)) {
                    continue;
                }

                // Check if any of these seats are still held or sold
                $stuckCount = 0;
                foreach ($seatInfo as $item) {
                    $stuckCount += EventSeat::where('event_seating_id', $item['event_seating_id'])
                        ->whereIn('seat_uid', $item['seat_uids'])
                        ->whereIn('status', ['held', 'sold'])
                        ->count();
                }

                if ($stuckCount === 0) {
                    continue;
                }

                $this->line("  Order #{$order->order_number} (ID: {$order->id}) has {$stuckCount} stuck seat(s)");

                if ($dryRun) {
                    $this->line("    - Would release {$stuckCount} stuck seat(s)");
                    $fixed++;
                    continue;
                }

                foreach ($seatInfo as $item) {
                    $released = EventSeat::where('event_seating_id', $item['event_seating_id'])
                        ->whereIn('seat_uid', $item['seat_uids'])
                        ->whereIn('status', ['held', 'sold'])
                        ->update([
                            'status' => 'available',
                            'version' => DB::raw('version + 1'),
                            'last_change_at' => now(),
                        ]);

                    SeatHold::where('event_seating_id', $item['event_seating_id'])
                        ->whereIn('seat_uid', $item['seat_uids'])
                        ->delete();

                    if ($released > 0) {
                        $this->info("    ✓ Released {$released} seat(s) for event_seating_id={$item['event_seating_id']}");
                    }
                }
                $fixed++;
            }

            if ($fixed > 0) {
                $this->info("Fixed stuck seats for {$fixed} expired order(s).");
            } else {
                $this->comment('No stuck seats found in expired orders.');
            }
        }

        return 0;
    }

    protected function processOrders($orders, bool $dryRun): void
    {
        $cleaned = 0;

        foreach ($orders as $order) {
            $this->line("  Processing order #{$order->order_number} (ID: {$order->id}, expired: {$order->expires_at})");

            if ($dryRun) {
                $seatInfo = $this->extractSeatInfo($order);
                $ticketCount = $order->tickets()->where('status', 'pending')->count();
                $seatCount = collect($seatInfo)->sum(fn($i) => count($i['seat_uids']));
                $this->line("    - Would release {$seatCount} seat(s) from " . count($seatInfo) . " seated item(s)");
                $this->line("    - Would cancel {$ticketCount} pending ticket(s)");
                $this->line("    - Would restore quota for " . $order->items()->count() . " order item(s)");
                $cleaned++;
                continue;
            }

            try {
                DB::transaction(function () use ($order) {
                    // 1. Release seats back to available
                    $seatInfo = $this->extractSeatInfo($order);
                    foreach ($seatInfo as $item) {
                        $eventSeatingId = $item['event_seating_id'];
                        $seatUids = $item['seat_uids'];

                        // Release seats that are held OR sold (old orders used confirmPurchase in checkout)
                        $released = EventSeat::where('event_seating_id', $eventSeatingId)
                            ->whereIn('seat_uid', $seatUids)
                            ->whereIn('status', ['held', 'sold'])
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

        $this->info("Cleaned up {$cleaned}/{$orders->count()} expired orders.");
    }

    /**
     * Extract seat info from order - tries meta first, falls back to ticket meta
     */
    protected function extractSeatInfo(Order $order): array
    {
        // Try order meta first (new orders store seated_items here)
        $seatedItems = $order->meta['seated_items'] ?? [];
        if (!empty($seatedItems)) {
            return $seatedItems;
        }

        // Fallback: extract seat info from individual tickets
        $tickets = $order->tickets()
            ->whereNotNull('meta')
            ->get();

        $seatsByLayout = [];
        foreach ($tickets as $ticket) {
            $meta = $ticket->meta;
            $seatUid = $meta['seat_uid'] ?? null;
            $eventSeatingId = $meta['event_seating_id'] ?? null;

            if ($seatUid && $eventSeatingId) {
                $key = (string) $eventSeatingId;
                if (!isset($seatsByLayout[$key])) {
                    $seatsByLayout[$key] = [
                        'event_seating_id' => (int) $eventSeatingId,
                        'seat_uids' => [],
                    ];
                }
                $seatsByLayout[$key]['seat_uids'][] = $seatUid;
            }
        }

        return array_values($seatsByLayout);
    }
}
