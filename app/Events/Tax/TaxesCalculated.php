<?php

namespace App\Events\Tax;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Taxes Calculated Event
 *
 * Fired when taxes are calculated for an order or transaction
 */
class TaxesCalculated implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $tenantId,
        public string $orderType, // 'order', 'shop_order', etc.
        public int|string $orderId,
        public float $subtotal,
        public float $totalTax,
        public float $total,
        public array $breakdown,
        public ?string $country = null,
        public ?string $currency = null,
    ) {}

    public function toWebhookPayload(): array
    {
        return [
            'event' => 'taxes.calculated',
            'tenant_id' => $this->tenantId,
            'order_type' => $this->orderType,
            'order_id' => $this->orderId,
            'subtotal' => $this->subtotal,
            'total_tax' => $this->totalTax,
            'total' => $this->total,
            'country' => $this->country,
            'currency' => $this->currency,
            'breakdown' => $this->breakdown,
            'timestamp' => now()->toISOString(),
        ];
    }
}
