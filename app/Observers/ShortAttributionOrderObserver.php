<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Shorts\ShortAttributionService;
use Illuminate\Support\Facades\Log;

/**
 * Credits (and un-credits) the short an order came from (B1).
 *
 * Hooked on the status transition rather than an event, for the same reason the
 * ticket sync lives there: orders reach "paid" through several paths (Stripe
 * webhook, free-order shortcut, admin action) and only the model sees them all.
 *
 * Never throws: an attribution failure must not roll back a paid order.
 */
class ShortAttributionOrderObserver
{
    public function __construct(private readonly ShortAttributionService $attribution) {}

    public function created(Order $order): void
    {
        if (! $order->source_short_id) {
            return;
        }

        if (in_array($order->status, ShortAttributionService::PAID_STATUSES, true)) {
            $this->safely(fn () => $this->attribution->credit($order));
        }
    }

    public function updated(Order $order): void
    {
        if (! $order->source_short_id || ! $order->wasChanged('status')) {
            return;
        }

        $new = $order->status;
        $old = $order->getOriginal('status');

        $becamePaid = in_array($new, ShortAttributionService::PAID_STATUSES, true)
            && ! in_array($old, ShortAttributionService::PAID_STATUSES, true);

        if ($becamePaid) {
            $this->safely(fn () => $this->attribution->credit($order));

            return;
        }

        $wasUndone = in_array($new, ShortAttributionService::REFUNDED_STATUSES, true)
            && in_array($old, ShortAttributionService::PAID_STATUSES, true);

        if ($wasUndone) {
            $this->safely(fn () => $this->attribution->reverse($order));
        }
    }

    protected function safely(callable $action): void
    {
        try {
            $action();
        } catch (\Throwable $e) {
            Log::error('Shorts: attribution failed', ['error' => $e->getMessage()]);
        }
    }
}
