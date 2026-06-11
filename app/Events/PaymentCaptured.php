<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Payment Captured Event
 *
 * Fired when a payment is successfully captured from payment provider
 */
class PaymentCaptured
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $orderRef,
        public array $paymentData,
    ) {}
}
