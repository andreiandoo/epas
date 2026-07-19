<?php

namespace App\Services\Installments;

use App\Models\InstallmentAgreement;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * Manages ticket validity for flexible-payment orders.
 *
 * Decision: tickets are issued immediately but INVALID (`pending_installments`)
 * and only become `valid` when the agreement completes. On default they are
 * invalidated and the inventory is released.
 */
class TicketStateService
{
    public const STATUS_PENDING = 'pending_installments';
    public const STATUS_VALID = 'valid';
    public const STATUS_INVALIDATED = 'cancelled';

    /** Mark an order's tickets reserved-but-invalid (at checkout). */
    public function markPendingForAgreement(InstallmentAgreement $agreement): void
    {
        $this->transition($agreement, self::STATUS_PENDING, ['valid', 'active', 'pending']);
    }

    /** Flip tickets to valid once fully paid. */
    public function markValidForAgreement(InstallmentAgreement $agreement): void
    {
        $this->transition($agreement, self::STATUS_VALID, [self::STATUS_PENDING]);
        Log::info("Installments: tickets validated for agreement {$agreement->id}");
    }

    /** Invalidate tickets on default/cancellation AND release seats + quota. */
    public function invalidateForAgreement(InstallmentAgreement $agreement): void
    {
        $this->transition($agreement, self::STATUS_INVALIDATED, [self::STATUS_PENDING]);
        if ($agreement->order_id && ($order = Order::find($agreement->order_id))) {
            $this->releaseInventoryForOrder($order);
        }
        Log::info("Installments: tickets invalidated for agreement {$agreement->id}");
    }

    /**
     * Confirm the order's held seats (held → sold) when the down payment clears —
     * the customer owns the seat now; the ticket is just invalid until fully paid.
     * Without this the seat stays 'held' and gets auto-released → oversell.
     */
    public function confirmSeatsForOrder(Order $order): void
    {
        $seatedItems = $order->meta['seated_items'] ?? [];
        if (empty($seatedItems)) {
            return;
        }
        $svc = app(\App\Services\Seating\SeatHoldService::class);
        foreach ($seatedItems as $item) {
            try {
                $svc->confirmPurchase(
                    $item['event_seating_id'],
                    $item['seat_uids'],
                    'installment-downpayment',
                    (int) round(((float) ($order->total ?? 0)) * 100)
                );
            } catch (\Throwable $e) {
                Log::error("Installments: seat confirm failed for order {$order->id}: {$e->getMessage()}");
            }
        }
    }

    /** Release seats + restore quota on default/cancel (no oversell / phantom stock). */
    public function releaseInventoryForOrder(Order $order): void
    {
        try {
            $order->releaseSeatsAndRestoreStock();
        } catch (\Throwable $e) {
            Log::error("Installments: inventory release failed for order {$order->id}: {$e->getMessage()}");
        }
    }

    protected function transition(InstallmentAgreement $agreement, string $to, array $fromStatuses): void
    {
        if (! $agreement->order_id) {
            return;
        }
        $order = Order::with('tickets')->find($agreement->order_id);
        if (! $order) {
            return;
        }

        foreach ($order->tickets as $ticket) {
            // Never touch already-used tickets.
            if ($ticket->status === 'used') {
                continue;
            }
            if (in_array($ticket->status, $fromStatuses, true) || $to === self::STATUS_INVALIDATED) {
                $ticket->update(['status' => $to]);
            }
        }
    }
}
