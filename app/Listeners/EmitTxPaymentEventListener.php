<?php

namespace App\Listeners;

use App\Events\PaymentCaptured;
use App\Models\Order;
use App\Services\Tracking\TxEventEmitter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class EmitTxPaymentEventListener implements ShouldQueue
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
    public function handle(PaymentCaptured $event): void
    {
        Log::info('EmitTxPaymentEventListener: Processing payment', [
            'tenant_id' => $event->tenantId,
            'order_ref' => $event->orderRef,
        ]);

        try {
            // Find the order
            $order = $this->findOrder($event);

            if (!$order) {
                Log::warning('EmitTxPaymentEventListener: Order not found', [
                    'tenant_id' => $event->tenantId,
                    'order_ref' => $event->orderRef,
                ]);
                return;
            }

            // Extract payment data
            $paymentData = $event->paymentData;
            $providerTxId = $paymentData['provider_tx_id']
                ?? $paymentData['payment_intent_id']
                ?? $paymentData['transaction_id']
                ?? $order->payment_reference
                ?? 'unknown';

            $amount = $paymentData['amount']
                ?? $paymentData['amount_captured']
                ?? (float) $order->total
                ?? ($order->total_cents / 100);

            $currency = $paymentData['currency']
                ?? $order->currency
                ?? 'RON';

            $latencyMs = $paymentData['latency_ms'] ?? null;

            // Emit the payment_succeeded event
            $txEvent = $this->emitter->emitPaymentSucceeded(
                $order,
                $providerTxId,
                $amount,
                strtoupper($currency),
                $latencyMs
            );

            if ($txEvent) {
                Log::info('EmitTxPaymentEventListener: TX event emitted', [
                    'tx_event_id' => $txEvent->event_id,
                    'order_id' => $order->id,
                    'provider_tx_id' => $providerTxId,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('EmitTxPaymentEventListener: Failed to process', [
                'tenant_id' => $event->tenantId,
                'order_ref' => $event->orderRef,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Find the order from the event data.
     */
    protected function findOrder(PaymentCaptured $event): ?Order
    {
        $order = Order::where('tenant_id', $event->tenantId)
            ->where(function ($query) use ($event) {
                $query->where('id', $event->orderRef)
                    ->orWhere('order_number', $event->orderRef);
            })
            ->first();

        if ($order) {
            return $order;
        }

        // Try payment reference from paymentData
        if (!empty($event->paymentData['payment_reference'])) {
            return Order::where('payment_reference', $event->paymentData['payment_reference'])
                ->first();
        }

        return null;
    }

    /**
     * Handle a job failure.
     */
    public function failed(PaymentCaptured $event, \Throwable $exception): void
    {
        Log::error('EmitTxPaymentEventListener: Job failed permanently', [
            'tenant_id' => $event->tenantId,
            'order_ref' => $event->orderRef,
            'error' => $exception->getMessage(),
        ]);
    }
}
