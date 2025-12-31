<?php

namespace App\Listeners;

use App\Events\OrderConfirmed;
use App\Models\Order;
use App\Services\Tracking\TxEventEmitter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class EmitTxOrderCompletedListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The queue this job should be dispatched to.
     */
    public string $queue = 'tracking';

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct(
        protected TxEventEmitter $emitter
    ) {}

    /**
     * Handle the event.
     */
    public function handle(OrderConfirmed $event): void
    {
        Log::info('EmitTxOrderCompletedListener: Processing order', [
            'tenant_id' => $event->tenantId,
            'order_ref' => $event->orderRef,
        ]);

        try {
            // Find the order
            $order = $this->findOrder($event);

            if (!$order) {
                Log::warning('EmitTxOrderCompletedListener: Order not found', [
                    'tenant_id' => $event->tenantId,
                    'order_ref' => $event->orderRef,
                ]);
                return;
            }

            // Emit the tx_event and perform identity stitching
            $txEvent = $this->emitter->emitOrderCompleted($order);

            if ($txEvent) {
                Log::info('EmitTxOrderCompletedListener: TX event emitted', [
                    'tx_event_id' => $txEvent->event_id,
                    'order_id' => $order->id,
                    'person_id' => $txEvent->person_id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('EmitTxOrderCompletedListener: Failed to process', [
                'tenant_id' => $event->tenantId,
                'order_ref' => $event->orderRef,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Find the order from the event data.
     */
    protected function findOrder(OrderConfirmed $event): ?Order
    {
        // Try to find by order_ref (could be ID or order_number)
        $order = Order::where('tenant_id', $event->tenantId)
            ->where(function ($query) use ($event) {
                $query->where('id', $event->orderRef)
                    ->orWhere('order_number', $event->orderRef);
            })
            ->with(['items', 'tickets', 'customer'])
            ->first();

        if ($order) {
            return $order;
        }

        // Try to find from orderData
        if (!empty($event->orderData['id'])) {
            return Order::with(['items', 'tickets', 'customer'])
                ->find($event->orderData['id']);
        }

        return null;
    }

    /**
     * Handle a job failure.
     */
    public function failed(OrderConfirmed $event, \Throwable $exception): void
    {
        Log::error('EmitTxOrderCompletedListener: Job failed permanently', [
            'tenant_id' => $event->tenantId,
            'order_ref' => $event->orderRef,
            'error' => $exception->getMessage(),
        ]);
    }
}
