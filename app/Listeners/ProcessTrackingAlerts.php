<?php

namespace App\Listeners;

use App\Services\Tracking\AlertTriggerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessTrackingAlerts implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The queue this job should run on.
     */
    public string $queue = 'tracking-low';

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        // Extract event data - this listener handles multiple event types
        $eventData = $this->extractEventData($event);

        if (!$eventData) {
            return;
        }

        $tenantId = $eventData['tenant_id'] ?? null;
        if (!$tenantId) {
            return;
        }

        try {
            $alertService = AlertTriggerService::forTenant($tenantId);

            // Process the event through alert triggers
            $triggeredAlerts = $alertService->processEvent($eventData);

            if (!empty($triggeredAlerts)) {
                Log::info('Tracking alerts triggered', [
                    'tenant_id' => $tenantId,
                    'event_type' => $eventData['type'] ?? 'unknown',
                    'alerts_count' => count($triggeredAlerts),
                    'alert_types' => array_column($triggeredAlerts, 'type'),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to process tracking alerts', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);
        }
    }

    /**
     * Extract normalized event data from various event types.
     */
    protected function extractEventData(object $event): ?array
    {
        // If it's a TxEvent model event
        if (method_exists($event, 'getTxEvent')) {
            $txEvent = $event->getTxEvent();
            return [
                'tenant_id' => $txEvent->tenant_id,
                'person_id' => $txEvent->person_id,
                'type' => $this->mapEventName($txEvent->event_name),
                'data' => [
                    'event_id' => $txEvent->entities['event_entity_id'] ?? null,
                    'order_id' => $txEvent->entities['order_id'] ?? null,
                    'value' => $txEvent->payload['gross_amount'] ?? $txEvent->payload['value'] ?? 0,
                    'quantity' => $txEvent->payload['quantity'] ?? 1,
                ],
            ];
        }

        // If it's a generic tracking event
        if (isset($event->eventData)) {
            return [
                'tenant_id' => $event->eventData['tenant_id'] ?? null,
                'person_id' => $event->eventData['person_id'] ?? null,
                'type' => $this->mapEventName($event->eventData['event_name'] ?? ''),
                'data' => $event->eventData['payload'] ?? [],
            ];
        }

        // If it's an order event
        if (isset($event->order)) {
            return [
                'tenant_id' => $event->order->tenant_id,
                'person_id' => $event->order->customer_id,
                'type' => 'purchase',
                'data' => [
                    'order_id' => $event->order->id,
                    'value' => $event->order->total,
                    'quantity' => $event->order->quantity,
                    'event_id' => $event->order->event_id,
                ],
            ];
        }

        return null;
    }

    /**
     * Map event names to standard types.
     */
    protected function mapEventName(string $eventName): string
    {
        return match ($eventName) {
            'page_view', 'pageview' => 'page_view',
            'view_item', 'view_event' => 'view_item',
            'add_to_cart', 'cart_add' => 'add_to_cart',
            'remove_from_cart' => 'cart_abandon',
            'begin_checkout', 'checkout_started' => 'begin_checkout',
            'purchase', 'order_completed' => 'purchase',
            default => $eventName,
        };
    }
}
