<?php

namespace App\Observers;

use App\Models\ActivityBooking;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sync ActivityBooking + their tickets to the parent Order's payment state.
 *
 * Activity bookings are inserted during checkout with status=pending_payment
 * and a 5-minute held_until window. Once the Order transitions to
 * paid/confirmed/completed (Netopia webhook → PaymentController, or
 * test/free auto-confirmation directly inside checkout), the bookings
 * linked to that order need to follow: status → paid, held_until → null
 * (capacity now permanent), and the pending tickets they emitted become
 * 'valid' so they show up in the customer's wallet and emails.
 *
 * Refunds / cancellations: when Order moves to cancelled or refunded, the
 * linked bookings go to cancelled (capacity freed) and tickets to
 * cancelled too.
 */
class ActivityBookingOrderObserver
{
    private const PAID_STATUSES      = ['paid', 'confirmed', 'completed'];
    private const CANCELLED_STATUSES = ['cancelled', 'refunded'];
    private const SKIP_SOURCES       = ['legacy_import', 'external_import'];

    public function created(Order $order): void
    {
        if (in_array($order->source ?? '', self::SKIP_SOURCES, true)) {
            return;
        }
        if (in_array($order->status, self::PAID_STATUSES, true)) {
            $this->syncToPaidAfterCommit($order);
        }
    }

    public function updated(Order $order): void
    {
        if (! $order->isDirty('status')) {
            return;
        }
        $newStatus = $order->status;
        $oldStatus = $order->getOriginal('status');

        if (in_array($newStatus, self::PAID_STATUSES, true) && ! in_array($oldStatus, self::PAID_STATUSES, true)) {
            $this->syncToPaidAfterCommit($order);
            return;
        }
        if (in_array($newStatus, self::CANCELLED_STATUSES, true) && ! in_array($oldStatus, self::CANCELLED_STATUSES, true)) {
            $this->syncToCancelledAfterCommit($order);
        }
    }

    protected function syncToPaidAfterCommit(Order $order): void
    {
        $orderId = $order->id;
        DB::afterCommit(function () use ($orderId) {
            try {
                $bookings = ActivityBooking::where('order_id', $orderId)->get();
                if ($bookings->isEmpty()) {
                    return;
                }
                foreach ($bookings as $booking) {
                    if (in_array($booking->status, [ActivityBooking::STATUS_PAID, ActivityBooking::STATUS_CONFIRMED, ActivityBooking::STATUS_CHECKED_IN], true)) {
                        continue;
                    }
                    $booking->update([
                        'status'      => ActivityBooking::STATUS_PAID,
                        'held_until'  => null,
                    ]);

                    Ticket::where('activity_booking_id', $booking->id)
                        ->where('status', 'pending')
                        ->update(['status' => 'valid']);
                }
            } catch (\Throwable $e) {
                Log::warning('[ActivityBookingOrderObserver] sync-to-paid failed', [
                    'order_id' => $orderId,
                    'error'    => $e->getMessage(),
                ]);
            }
        });
    }

    protected function syncToCancelledAfterCommit(Order $order): void
    {
        $orderId = $order->id;
        DB::afterCommit(function () use ($orderId) {
            try {
                $bookings = ActivityBooking::where('order_id', $orderId)->get();
                if ($bookings->isEmpty()) {
                    return;
                }
                foreach ($bookings as $booking) {
                    if ($booking->status === ActivityBooking::STATUS_CANCELLED) {
                        continue;
                    }
                    $booking->update([
                        'status'     => ActivityBooking::STATUS_CANCELLED,
                        'held_until' => null,
                    ]);

                    Ticket::where('activity_booking_id', $booking->id)
                        ->whereIn('status', ['pending', 'valid'])
                        ->update(['status' => 'cancelled']);
                }
            } catch (\Throwable $e) {
                Log::warning('[ActivityBookingOrderObserver] sync-to-cancelled failed', [
                    'order_id' => $orderId,
                    'error'    => $e->getMessage(),
                ]);
            }
        });
    }
}
