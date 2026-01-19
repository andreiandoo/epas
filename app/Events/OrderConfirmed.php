<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Order Confirmed Event
 *
 * Fired when an order is confirmed (payment captured successfully)
 */
class OrderConfirmed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $tenantId,
        public string $orderRef,
        public array $orderData,
    ) {}
}
