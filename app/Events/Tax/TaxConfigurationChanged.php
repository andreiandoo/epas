<?php

namespace App\Events\Tax;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Tax Configuration Changed Event
 *
 * Fired when a tax configuration is created, updated, or deleted
 */
class TaxConfigurationChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $tenantId,
        public string $taxType, // 'general', 'local', 'exemption'
        public int $taxId,
        public string $action, // 'created', 'updated', 'deleted', 'restored'
        public array $taxData,
        public ?array $previousData = null,
        public ?int $userId = null,
        public ?string $userName = null,
    ) {}

    public function toWebhookPayload(): array
    {
        return [
            'event' => 'tax.configuration.' . $this->action,
            'tenant_id' => $this->tenantId,
            'tax_type' => $this->taxType,
            'tax_id' => $this->taxId,
            'action' => $this->action,
            'data' => $this->taxData,
            'previous_data' => $this->previousData,
            'changed_by' => [
                'user_id' => $this->userId,
                'user_name' => $this->userName,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }
}
