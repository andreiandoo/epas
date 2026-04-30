<?php

namespace App\Observers;

use App\Jobs\SendFacebookCapiPurchaseJob;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacebookCapiOrderObserver
{
    private const PAID_STATUSES = ['paid', 'confirmed', 'completed'];
    private const SKIP_SOURCES = ['legacy_import', 'external_import'];

    public function created(Order $order): void
    {
        if (in_array($order->source ?? '', self::SKIP_SOURCES, true)) {
            return;
        }
        if (!in_array($order->status, self::PAID_STATUSES, true)) {
            return;
        }
        $this->dispatchAfterCommit($order);
    }

    public function updated(Order $order): void
    {
        if (!$order->isDirty('status')) {
            return;
        }
        $newStatus = $order->status;
        $oldStatus = $order->getOriginal('status');

        if (!in_array($newStatus, self::PAID_STATUSES, true)) {
            return;
        }
        if (in_array($oldStatus, self::PAID_STATUSES, true)) {
            return;
        }
        $this->dispatchAfterCommit($order);
    }

    protected function dispatchAfterCommit(Order $order): void
    {
        $orderId = $order->id;
        DB::afterCommit(function () use ($orderId) {
            try {
                SendFacebookCapiPurchaseJob::dispatch($orderId);
            } catch (\Throwable $e) {
                Log::warning('FB CAPI: failed to dispatch Purchase job', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
