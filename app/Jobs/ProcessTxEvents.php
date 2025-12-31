<?php

namespace App\Jobs;

use App\Models\Tracking\TxEvent;
use App\Models\Tracking\TxIdentityLink;
use App\Models\FeatureStore\FsEventFunnelHourly;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProcessTxEvents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * Maximum events to process per batch.
     */
    protected int $batchSize = 100;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Processing tx_events from queue');

        $processed = 0;
        $errors = 0;
        $stitched = 0;
        $funnelUpdates = [];

        try {
            // Pull events from Redis queue
            $events = $this->pullEventsFromQueue();

            if (empty($events)) {
                Log::info('No events to process');
                return;
            }

            foreach ($events as $eventData) {
                try {
                    // Create the event record
                    $event = TxEvent::createFromEnvelope($eventData);

                    // Handle identity stitching for order_completed events
                    if ($event->event_name === 'order_completed') {
                        $stitchResult = $this->handleIdentityStitching($event);
                        if ($stitchResult) {
                            $stitched++;
                        }
                    }

                    // Collect funnel updates
                    if ($this->isFunnelEvent($event->event_name)) {
                        $this->collectFunnelUpdate($funnelUpdates, $event);
                    }

                    $processed++;

                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Failed to process tx_event', [
                        'event_id' => $eventData['event_id'] ?? null,
                        'event_name' => $eventData['event_name'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Batch update funnel metrics
            if (!empty($funnelUpdates)) {
                $this->updateFunnelMetrics($funnelUpdates);
            }

            Log::info('Tx events processing completed', [
                'processed' => $processed,
                'errors' => $errors,
                'stitched' => $stitched,
                'funnel_updates' => count($funnelUpdates),
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessTxEvents job failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Pull events from Redis queue.
     */
    protected function pullEventsFromQueue(): array
    {
        $events = [];

        try {
            for ($i = 0; $i < $this->batchSize; $i++) {
                $data = Redis::lpop('tx_events_queue');

                if (!$data) {
                    break;
                }

                $decoded = json_decode($data, true);
                if ($decoded) {
                    $events[] = $decoded;
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to pull from Redis queue', ['error' => $e->getMessage()]);
        }

        return $events;
    }

    /**
     * Handle identity stitching for order_completed events.
     */
    protected function handleIdentityStitching(TxEvent $event): bool
    {
        // Check if we have the required data for stitching
        if (empty($event->visitor_id)) {
            return false;
        }

        // Check consent for data processing
        if (!$event->hasDataProcessingConsent()) {
            Log::debug('Skipping identity stitch - no data_processing consent', [
                'event_id' => $event->event_id,
            ]);
            return false;
        }

        // Try to find person_id from order
        $orderId = $event->entities['order_id'] ?? null;
        if (!$orderId) {
            return false;
        }

        // Get customer's core_customer_id from order
        $personId = DB::table('orders')
            ->join('customers', 'orders.customer_id', '=', 'customers.id')
            ->join('core_customers', function ($join) {
                $join->on('customers.email', '=', 'core_customers.email')
                    ->orOn('customers.phone', '=', 'core_customers.phone');
            })
            ->where('orders.id', $orderId)
            ->value('core_customers.id');

        if (!$personId) {
            return false;
        }

        // Create identity link
        $link = TxIdentityLink::linkIdentity(
            $event->tenant_id,
            $event->visitor_id,
            $personId,
            'order_completed',
            $orderId,
            1.0,
            [
                'event_id' => $event->event_id,
                'consent' => $event->consent_snapshot,
            ]
        );

        // Backfill person_id on historical events
        $backfilled = $link->performStitching();

        // Also update the current event
        $event->update(['person_id' => $personId]);

        Log::info('Identity stitched', [
            'tenant_id' => $event->tenant_id,
            'visitor_id' => $event->visitor_id,
            'person_id' => $personId,
            'order_id' => $orderId,
            'backfilled_events' => $backfilled,
        ]);

        return true;
    }

    /**
     * Check if event is part of the conversion funnel.
     */
    protected function isFunnelEvent(string $eventName): bool
    {
        return in_array($eventName, [
            'event_view',
            'ticket_type_selected',
            'add_to_cart',
            'checkout_started',
            'payment_attempted',
            'order_completed',
        ]);
    }

    /**
     * Collect funnel update for batch processing.
     */
    protected function collectFunnelUpdate(array &$updates, TxEvent $event): void
    {
        $eventEntityId = $event->entities['event_entity_id'] ?? null;
        if (!$eventEntityId) {
            return;
        }

        $hour = $event->occurred_at->startOfHour()->toIso8601String();
        $key = "{$event->tenant_id}:{$eventEntityId}:{$hour}";

        if (!isset($updates[$key])) {
            $updates[$key] = [
                'tenant_id' => $event->tenant_id,
                'event_entity_id' => $eventEntityId,
                'hour' => $hour,
                'page_views' => 0,
                'ticket_selections' => 0,
                'add_to_carts' => 0,
                'checkout_starts' => 0,
                'payment_attempts' => 0,
                'orders_completed' => 0,
                'revenue_gross' => 0,
            ];
        }

        switch ($event->event_name) {
            case 'event_view':
                $updates[$key]['page_views']++;
                break;
            case 'ticket_type_selected':
                $updates[$key]['ticket_selections']++;
                break;
            case 'add_to_cart':
                $updates[$key]['add_to_carts']++;
                break;
            case 'checkout_started':
                $updates[$key]['checkout_starts']++;
                break;
            case 'payment_attempted':
                $updates[$key]['payment_attempts']++;
                break;
            case 'order_completed':
                $updates[$key]['orders_completed']++;
                $updates[$key]['revenue_gross'] += $event->payload['gross_amount'] ?? 0;
                break;
        }
    }

    /**
     * Batch update funnel metrics.
     */
    protected function updateFunnelMetrics(array $updates): void
    {
        foreach ($updates as $data) {
            try {
                FsEventFunnelHourly::updateOrCreate(
                    [
                        'tenant_id' => $data['tenant_id'],
                        'event_entity_id' => $data['event_entity_id'],
                        'hour' => $data['hour'],
                    ],
                    [
                        'page_views' => DB::raw('COALESCE(page_views, 0) + ' . $data['page_views']),
                        'ticket_selections' => DB::raw('COALESCE(ticket_selections, 0) + ' . $data['ticket_selections']),
                        'add_to_carts' => DB::raw('COALESCE(add_to_carts, 0) + ' . $data['add_to_carts']),
                        'checkout_starts' => DB::raw('COALESCE(checkout_starts, 0) + ' . $data['checkout_starts']),
                        'payment_attempts' => DB::raw('COALESCE(payment_attempts, 0) + ' . $data['payment_attempts']),
                        'orders_completed' => DB::raw('COALESCE(orders_completed, 0) + ' . $data['orders_completed']),
                        'revenue_gross' => DB::raw('COALESCE(revenue_gross, 0) + ' . $data['revenue_gross']),
                    ]
                );
            } catch (\Exception $e) {
                Log::error('Failed to update funnel metrics', [
                    'key' => "{$data['tenant_id']}:{$data['event_entity_id']}:{$data['hour']}",
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
