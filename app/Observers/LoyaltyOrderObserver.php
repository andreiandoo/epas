<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Gamification\MarketplaceLoyaltyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Marketplace loyalty points follow the order: paid → the points wait for the activity; never paid (expired, failed,
 * cancelled) → the points it reserved at checkout go back; refunded or cancelled after payment → what it earned is
 * cancelled or taken back and the points it used go back. The work runs after the transaction commits and never
 * throws: the payment callback updates the order and must not be stopped by the points.
 */
class LoyaltyOrderObserver
{
    private const PAID = ['paid', 'confirmed', 'completed'];
    private const CLOSED_UNPAID = ['expired', 'failed', 'cancelled', 'abandoned'];

    public function created(Order $order): void
    {
        if (!$order->marketplace_client_id || !in_array($order->status, self::PAID, true)) {
            return;
        }
        $this->later($order, 'onOrderPaid');
    }

    public function updated(Order $order): void
    {
        if (!$order->marketplace_client_id || !$order->isDirty('status')) {
            return;
        }
        $new = $order->status;
        $old = $order->getOriginal('status');
        $wasPaid = in_array($old, self::PAID, true) || $old === 'partially_refunded';

        if (in_array($new, self::PAID, true) && !$wasPaid) {
            $this->later($order, 'onOrderPaid');
        } elseif ($new === 'refunded' || ($new === 'cancelled' && $wasPaid)) {
            $this->later($order, 'onOrderReversed');
        } elseif (in_array($new, self::CLOSED_UNPAID, true) && !$wasPaid) {
            $this->later($order, 'onOrderClosedUnpaid');
        }
    }

    protected function later(Order $order, string $method): void
    {
        $orderId = $order->id;
        DB::afterCommit(function () use ($orderId, $method) {
            try {
                $fresh = Order::find($orderId);
                if ($fresh) {
                    app(MarketplaceLoyaltyService::class)->{$method}($fresh);
                }
            } catch (\Throwable $e) {
                Log::error('Loyalty: order hook failed', ['order_id' => $orderId, 'hook' => $method, 'error' => $e->getMessage()]);
            }
        });
    }
}
