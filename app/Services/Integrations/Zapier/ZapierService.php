<?php

namespace App\Services\Integrations\Zapier;

use App\Models\Integrations\Zapier\ZapierConnection;
use App\Models\Integrations\Zapier\ZapierTrigger;
use App\Models\Integrations\Zapier\ZapierTriggerLog;
use App\Models\Integrations\Zapier\ZapierAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ZapierService
{
    // Supported trigger types
    public const TRIGGER_ORDER_CREATED = 'order_created';
    public const TRIGGER_TICKET_SOLD = 'ticket_sold';
    public const TRIGGER_CUSTOMER_CREATED = 'customer_created';
    public const TRIGGER_EVENT_PUBLISHED = 'event_published';
    public const TRIGGER_REGISTRATION_COMPLETED = 'registration_completed';
    public const TRIGGER_REFUND_ISSUED = 'refund_issued';

    public function connect(int $tenantId): ZapierConnection
    {
        return ZapierConnection::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'status' => 'active',
                'connected_at' => now(),
            ]
        );
    }

    public function disconnect(ZapierConnection $connection): bool
    {
        // Deactivate all triggers
        $connection->triggers()->update(['is_active' => false]);
        $connection->update(['status' => 'disconnected']);

        return true;
    }

    public function regenerateApiKey(ZapierConnection $connection): string
    {
        $newKey = bin2hex(random_bytes(32));
        $connection->update(['api_key' => $newKey]);

        return $newKey;
    }

    // REST Hook subscription (Zapier's preferred method)
    public function subscribeTrigger(ZapierConnection $connection, string $triggerType, string $webhookUrl, ?string $zapId = null): ZapierTrigger
    {
        return ZapierTrigger::updateOrCreate(
            [
                'connection_id' => $connection->id,
                'trigger_type' => $triggerType,
                'webhook_url' => $webhookUrl,
            ],
            [
                'zap_id' => $zapId,
                'is_active' => true,
            ]
        );
    }

    public function unsubscribeTrigger(ZapierConnection $connection, string $triggerType, string $webhookUrl): bool
    {
        return ZapierTrigger::where('connection_id', $connection->id)
            ->where('trigger_type', $triggerType)
            ->where('webhook_url', $webhookUrl)
            ->delete() > 0;
    }

    public function getActiveTriggers(ZapierConnection $connection): Collection
    {
        return $connection->triggers()->where('is_active', true)->get();
    }

    // Fire triggers
    public function fireTrigger(int $tenantId, string $triggerType, array $payload, ?string $correlationRef = null): array
    {
        $connection = $this->getConnection($tenantId);
        if (!$connection) {
            return ['success' => false, 'error' => 'No active Zapier connection'];
        }

        $triggers = $connection->triggers()
            ->where('trigger_type', $triggerType)
            ->where('is_active', true)
            ->get();

        $results = [];

        foreach ($triggers as $trigger) {
            $result = $this->sendTriggerWebhook($trigger, $payload, $correlationRef);
            $results[] = $result;
        }

        $connection->update(['last_used_at' => now()]);

        return [
            'success' => true,
            'triggers_fired' => count($triggers),
            'results' => $results,
        ];
    }

    protected function sendTriggerWebhook(ZapierTrigger $trigger, array $payload, ?string $correlationRef): array
    {
        $log = ZapierTriggerLog::create([
            'trigger_id' => $trigger->id,
            'trigger_type' => $trigger->trigger_type,
            'payload' => $payload,
            'status' => 'pending',
            'correlation_ref' => $correlationRef,
            'triggered_at' => now(),
        ]);

        try {
            $response = Http::timeout(30)
                ->post($trigger->webhook_url, $payload);

            $log->update([
                'status' => $response->successful() ? 'sent' : 'failed',
                'http_status' => $response->status(),
                'response' => $response->body(),
            ]);

            if ($response->successful()) {
                $trigger->increment('trigger_count');
                $trigger->update(['last_triggered_at' => now()]);
            }

            return [
                'trigger_id' => $trigger->id,
                'success' => $response->successful(),
                'http_status' => $response->status(),
            ];
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'response' => $e->getMessage(),
            ]);

            return [
                'trigger_id' => $trigger->id,
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    // Convenience methods for common triggers
    public function fireOrderCreated(int $tenantId, array $orderData): array
    {
        return $this->fireTrigger($tenantId, self::TRIGGER_ORDER_CREATED, [
            'event' => 'order_created',
            'timestamp' => now()->toIso8601String(),
            'data' => $orderData,
        ], $orderData['order_id'] ?? null);
    }

    public function fireTicketSold(int $tenantId, array $ticketData): array
    {
        return $this->fireTrigger($tenantId, self::TRIGGER_TICKET_SOLD, [
            'event' => 'ticket_sold',
            'timestamp' => now()->toIso8601String(),
            'data' => $ticketData,
        ], $ticketData['ticket_id'] ?? null);
    }

    public function fireCustomerCreated(int $tenantId, array $customerData): array
    {
        return $this->fireTrigger($tenantId, self::TRIGGER_CUSTOMER_CREATED, [
            'event' => 'customer_created',
            'timestamp' => now()->toIso8601String(),
            'data' => $customerData,
        ], $customerData['customer_id'] ?? null);
    }

    public function fireEventPublished(int $tenantId, array $eventData): array
    {
        return $this->fireTrigger($tenantId, self::TRIGGER_EVENT_PUBLISHED, [
            'event' => 'event_published',
            'timestamp' => now()->toIso8601String(),
            'data' => $eventData,
        ], $eventData['event_id'] ?? null);
    }

    // Actions (incoming from Zapier)
    public function executeAction(ZapierConnection $connection, string $actionType, array $payload): ZapierAction
    {
        $action = ZapierAction::create([
            'connection_id' => $connection->id,
            'action_type' => $actionType,
            'payload' => $payload,
            'status' => 'pending',
            'correlation_ref' => $payload['correlation_ref'] ?? null,
        ]);

        try {
            $result = $this->processAction($connection, $actionType, $payload);

            $action->update([
                'status' => 'completed',
                'result' => $result,
                'executed_at' => now(),
            ]);
        } catch (\Exception $e) {
            $action->update([
                'status' => 'failed',
                'result' => ['error' => $e->getMessage()],
                'executed_at' => now(),
            ]);
        }

        return $action->fresh();
    }

    protected function processAction(ZapierConnection $connection, string $actionType, array $payload): array
    {
        // This would dispatch to appropriate handlers based on action type
        // For now, just return the payload as acknowledgment
        return [
            'action_type' => $actionType,
            'processed' => true,
            'payload_received' => $payload,
        ];
    }

    // API Key validation (for Zapier authentication)
    public function validateApiKey(string $apiKey): ?ZapierConnection
    {
        return ZapierConnection::where('api_key', $apiKey)
            ->where('status', 'active')
            ->first();
    }

    // Polling endpoint for triggers (fallback method)
    public function pollTrigger(ZapierConnection $connection, string $triggerType, ?\DateTime $since = null): array
    {
        // This would return recent data for the trigger type
        // Implementation depends on what data needs to be polled
        return [];
    }

    public function getSupportedTriggers(): array
    {
        return [
            self::TRIGGER_ORDER_CREATED => 'New Order Created',
            self::TRIGGER_TICKET_SOLD => 'Ticket Sold',
            self::TRIGGER_CUSTOMER_CREATED => 'New Customer',
            self::TRIGGER_EVENT_PUBLISHED => 'Event Published',
            self::TRIGGER_REGISTRATION_COMPLETED => 'Registration Completed',
            self::TRIGGER_REFUND_ISSUED => 'Refund Issued',
        ];
    }

    public function getConnection(int $tenantId): ?ZapierConnection
    {
        return ZapierConnection::where('tenant_id', $tenantId)->where('status', 'active')->first();
    }
}
