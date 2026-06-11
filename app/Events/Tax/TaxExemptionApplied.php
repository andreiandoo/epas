<?php

namespace App\Events\Tax;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Tax Exemption Applied Event
 *
 * Fired when a tax exemption is applied to an order
 */
class TaxExemptionApplied implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $tenantId,
        public string $orderType,
        public int|string $orderId,
        public int $exemptionId,
        public string $exemptionName,
        public float $exemptionPercent,
        public float $originalTaxAmount,
        public float $reducedTaxAmount,
        public float $savings,
        public ?string $exemptableType = null,
        public ?int $exemptableId = null,
    ) {}

    public function toWebhookPayload(): array
    {
        return [
            'event' => 'tax.exemption.applied',
            'tenant_id' => $this->tenantId,
            'order_type' => $this->orderType,
            'order_id' => $this->orderId,
            'exemption' => [
                'id' => $this->exemptionId,
                'name' => $this->exemptionName,
                'percent' => $this->exemptionPercent,
                'exemptable_type' => $this->exemptableType,
                'exemptable_id' => $this->exemptableId,
            ],
            'amounts' => [
                'original_tax' => $this->originalTaxAmount,
                'reduced_tax' => $this->reducedTaxAmount,
                'savings' => $this->savings,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }
}
