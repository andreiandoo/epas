<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\PaymentRefundService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The payment processor told us money went back to the customer. If the
 * refund didn't start in the platform (e.g. made in the Netopia panel), it is
 * recorded here, or flagged for the operator when the amount is partial.
 */
class RecordProcessorCreditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 120;

    public function __construct(
        public int $orderId,
        public ?float $amount,
        public ?string $reference,
        public string $processor
    ) {}

    public function handle(PaymentRefundService $refunds): void
    {
        $order = Order::find($this->orderId);
        if (!$order) {
            return;
        }

        $refunds->handleProcessorCredit($order, $this->amount, $this->reference, $this->processor);
    }
}
