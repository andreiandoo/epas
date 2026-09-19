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
 * and held_until = the order's expires_at. Once the Order transitions to
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
                    if (in_array($booking->status, [ActivityBooking::STATUS_PAID, ActivityBooking::STATUS_CONFIRMED, ActivityBooking::STATUS_CHECKED_IN, ActivityBooking::STATUS_NO_SHOW], true)) {
                        continue;
                    }

                    $updates = [
                        'status'      => ActivityBooking::STATUS_PAID,
                        'held_until'  => null,
                    ];

                    if ($booking->status === ActivityBooking::STATUS_CANCELLED) {
                        // Only a booking the expired-hold sweep released comes
                        // back (the payment landed after the hold ran out).
                        // A booking cancelled by staff stays cancelled.
                        if (! str_contains((string) $booking->notes, ActivityBooking::HOLD_EXPIRED_NOTE)) {
                            Log::warning('[ActivityBookingOrderObserver] paid order has a booking cancelled by staff; left cancelled', [
                                'order_id'   => $orderId,
                                'booking_id' => $booking->id,
                            ]);
                            continue;
                        }

                        // The seats were free for others meanwhile: re-check.
                        // The customer paid, so the booking is kept either
                        // way; an overbooked one is flagged for the operator.
                        $overbooked = $this->slotWouldOverflow($booking);
                        $updates['notes'] = trim(($booking->notes ?? '') . "\n" . ($overbooked
                            ? 'Overbooked: paid after the hold expired, the slot was already full.'
                            : 'Restored: paid after the hold expired.'));
                        if ($overbooked) {
                            Log::error('[ActivityBookingOrderObserver] late payment overbooked an activity slot', [
                                'order_id'    => $orderId,
                                'booking_id'  => $booking->id,
                                'activity_id' => $booking->activity_id,
                            ]);
                        }
                    }

                    $booking->update($updates);

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

            $this->notifyOperators($orderId);
        });
    }

    /**
     * One notification per operator in the order (a cart can hold several
     * operators' products): what was booked, for when, and its value.
     */
    protected function notifyOperators(int $orderId): void
    {
        try {
            $bookings = ActivityBooking::with(['activity.organizer', 'activity.location', 'variant'])
                ->where('order_id', $orderId)
                ->whereNull('package_booking_id')
                ->whereIn('status', [ActivityBooking::STATUS_PAID, ActivityBooking::STATUS_CONFIRMED])
                ->get();

            foreach ($bookings->groupBy(fn ($b) => $b->activity?->marketplace_organizer_id) as $organizerId => $rows) {
                $organizer = $rows->first()->activity?->organizer;
                if (!$organizerId || !$organizer) {
                    continue;
                }
                $first  = \App\Services\Activities\BookingDescriber::booking($rows->first());
                $qty    = (int) $rows->sum('quantity');
                $value  = number_format($rows->sum('total_cents') / 100, 2, ',', '.') . ' ' . ($rows->first()->currency ?: 'RON');
                $title  = $rows->count() > 1
                    ? "Rezervare nouă: {$rows->count()} produse"
                    : "Rezervare nouă: {$qty} × {$first['title']}";

                \App\Services\OrganizerNotificationService::notify(
                    $organizer,
                    \App\Models\MarketplaceNotification::TYPE_TICKET_SALE,
                    $title,
                    $value . ' · ' . $first['date_label'] . ($first['start_time'] ? ', ' . $first['start_time'] : ''),
                    '/organizator/rezervari',
                    $rows->first()->order,
                    [
                        'order_id'    => $orderId,
                        'booking_ids' => $rows->pluck('id')->all(),
                        'persons'     => $qty,
                        'kind'        => 'activity',
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('[ActivityBookingOrderObserver] operator notification failed', [
                'order_id' => $orderId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Would restoring this (released) booking push its slot / day over
     * capacity? Same counting as checkout (day tickets, per-slot seats,
     * units in use at the same time).
     */
    protected function slotWouldOverflow(ActivityBooking $booking): bool
    {
        return app(\App\Services\Activities\ProductAvailability::class)->bookingOverflows($booking);
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
