<?php

namespace App\Jobs\Tracking;

use App\Models\FeatureStore\FsEventFunnelHourly;
use App\Models\Tracking\TxEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Aggregates event funnel metrics by hour for each event entity.
 *
 * This job recalculates funnel metrics from raw tx_events data,
 * useful for:
 * - Backfilling historical data
 * - Correcting any inconsistencies
 * - Initial population after migration
 */
class AggregateEventFunnelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 900;

    protected ?int $tenantId;
    protected ?int $eventEntityId;
    protected int $lookbackHours;

    /**
     * Funnel event types and their corresponding columns.
     */
    protected const FUNNEL_EVENTS = [
        'event_view' => 'page_views',
        'ticket_type_selected' => 'ticket_selections',
        'add_to_cart' => 'add_to_carts',
        'checkout_started' => 'checkout_starts',
        'payment_attempted' => 'payment_attempts',
        'order_completed' => 'orders_completed',
    ];

    /**
     * Create a new job instance.
     *
     * @param int|null $tenantId Process only this tenant
     * @param int|null $eventEntityId Process only this event
     * @param int $lookbackHours How far back to aggregate (default 24)
     */
    public function __construct(?int $tenantId = null, ?int $eventEntityId = null, int $lookbackHours = 24)
    {
        $this->tenantId = $tenantId;
        $this->eventEntityId = $eventEntityId;
        $this->lookbackHours = $lookbackHours;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('AggregateEventFunnels started', [
            'tenant_id' => $this->tenantId,
            'event_entity_id' => $this->eventEntityId,
            'lookback_hours' => $this->lookbackHours,
        ]);

        $startTime = microtime(true);
        $hoursProcessed = 0;

        try {
            $startHour = now()->subHours($this->lookbackHours)->startOfHour();
            $endHour = now()->startOfHour();

            // Get distinct tenant/event combinations to process
            $combinations = $this->getCombinationsToProcess($startHour, $endHour);

            foreach ($combinations as $combo) {
                $this->aggregateForEventEntity(
                    $combo->tenant_id,
                    $combo->event_entity_id,
                    $startHour,
                    $endHour
                );
                $hoursProcessed++;
            }

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('AggregateEventFunnels completed', [
                'combinations_processed' => $hoursProcessed,
                'duration_seconds' => $duration,
            ]);

        } catch (\Exception $e) {
            Log::error('AggregateEventFunnels job failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /**
     * Get tenant/event combinations that have data in the time range.
     */
    protected function getCombinationsToProcess($startHour, $endHour)
    {
        $query = DB::table('tx_events')
            ->whereNotNull(DB::raw("entities->>'event_entity_id'"))
            ->where('occurred_at', '>=', $startHour)
            ->where('occurred_at', '<', $endHour)
            ->whereIn('event_name', array_keys(self::FUNNEL_EVENTS))
            ->selectRaw("tenant_id, (entities->>'event_entity_id')::int as event_entity_id")
            ->groupBy('tenant_id', DB::raw("entities->>'event_entity_id'"));

        if ($this->tenantId) {
            $query->where('tenant_id', $this->tenantId);
        }

        if ($this->eventEntityId) {
            $query->whereRaw("entities->>'event_entity_id' = ?", [(string) $this->eventEntityId]);
        }

        return $query->get();
    }

    /**
     * Aggregate funnel metrics for a specific event entity.
     */
    protected function aggregateForEventEntity(int $tenantId, int $eventEntityId, $startHour, $endHour): void
    {
        // Get hourly aggregates using PostgreSQL date_trunc
        $hourlyData = DB::table('tx_events')
            ->where('tenant_id', $tenantId)
            ->whereRaw("entities->>'event_entity_id' = ?", [(string) $eventEntityId])
            ->where('occurred_at', '>=', $startHour)
            ->where('occurred_at', '<', $endHour)
            ->whereIn('event_name', array_keys(self::FUNNEL_EVENTS))
            ->selectRaw("
                date_trunc('hour', occurred_at) as hour,
                event_name,
                COUNT(*) as count,
                SUM(COALESCE((payload->>'gross_amount')::numeric, 0)) as revenue
            ")
            ->groupBy(DB::raw("date_trunc('hour', occurred_at)"), 'event_name')
            ->get();

        // Organize by hour
        $byHour = [];
        foreach ($hourlyData as $row) {
            $hour = $row->hour;
            if (!isset($byHour[$hour])) {
                $byHour[$hour] = [
                    'page_views' => 0,
                    'ticket_selections' => 0,
                    'add_to_carts' => 0,
                    'checkout_starts' => 0,
                    'payment_attempts' => 0,
                    'orders_completed' => 0,
                    'revenue_gross' => 0,
                ];
            }

            $column = self::FUNNEL_EVENTS[$row->event_name] ?? null;
            if ($column) {
                $byHour[$hour][$column] = (int) $row->count;
            }

            if ($row->event_name === 'order_completed') {
                $byHour[$hour]['revenue_gross'] = (float) $row->revenue;
            }
        }

        // Calculate timing metrics (average time between funnel stages)
        // This requires session-level analysis
        $timingMetrics = $this->calculateTimingMetrics($tenantId, $eventEntityId, $startHour, $endHour);

        // Upsert hourly records
        foreach ($byHour as $hour => $metrics) {
            $timing = $timingMetrics[$hour] ?? [];

            FsEventFunnelHourly::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'event_entity_id' => $eventEntityId,
                    'hour' => $hour,
                ],
                [
                    'page_views' => $metrics['page_views'],
                    'ticket_selections' => $metrics['ticket_selections'],
                    'add_to_carts' => $metrics['add_to_carts'],
                    'checkout_starts' => $metrics['checkout_starts'],
                    'payment_attempts' => $metrics['payment_attempts'],
                    'orders_completed' => $metrics['orders_completed'],
                    'revenue_gross' => $metrics['revenue_gross'],
                    'avg_time_to_cart_ms' => $timing['avg_time_to_cart_ms'] ?? null,
                    'avg_time_to_checkout_ms' => $timing['avg_time_to_checkout_ms'] ?? null,
                    'avg_checkout_duration_ms' => $timing['avg_checkout_duration_ms'] ?? null,
                ]
            );
        }
    }

    /**
     * Calculate average timing between funnel stages.
     */
    protected function calculateTimingMetrics(int $tenantId, int $eventEntityId, $startHour, $endHour): array
    {
        // Get session-level funnel events ordered by time
        $sessionEvents = DB::table('tx_events')
            ->where('tenant_id', $tenantId)
            ->whereRaw("entities->>'event_entity_id' = ?", [(string) $eventEntityId])
            ->where('occurred_at', '>=', $startHour)
            ->where('occurred_at', '<', $endHour)
            ->whereIn('event_name', ['event_view', 'add_to_cart', 'checkout_started', 'order_completed'])
            ->whereNotNull('session_id')
            ->select('session_id', 'event_name', 'occurred_at')
            ->orderBy('session_id')
            ->orderBy('occurred_at')
            ->get()
            ->groupBy('session_id');

        $hourlyTimings = [];

        foreach ($sessionEvents as $sessionId => $events) {
            $eventsByType = [];
            foreach ($events as $event) {
                $eventsByType[$event->event_name] = $event->occurred_at;
            }

            // Calculate time deltas
            $hour = null;

            if (isset($eventsByType['event_view'], $eventsByType['add_to_cart'])) {
                $viewTime = strtotime($eventsByType['event_view']);
                $cartTime = strtotime($eventsByType['add_to_cart']);
                $hour = date('Y-m-d H:00:00', $viewTime);

                if (!isset($hourlyTimings[$hour])) {
                    $hourlyTimings[$hour] = [
                        'time_to_cart' => [],
                        'time_to_checkout' => [],
                        'checkout_duration' => [],
                    ];
                }

                $hourlyTimings[$hour]['time_to_cart'][] = ($cartTime - $viewTime) * 1000;
            }

            if (isset($eventsByType['event_view'], $eventsByType['checkout_started'])) {
                $viewTime = strtotime($eventsByType['event_view']);
                $checkoutTime = strtotime($eventsByType['checkout_started']);
                $hour = $hour ?? date('Y-m-d H:00:00', $viewTime);

                $hourlyTimings[$hour]['time_to_checkout'][] = ($checkoutTime - $viewTime) * 1000;
            }

            if (isset($eventsByType['checkout_started'], $eventsByType['order_completed'])) {
                $checkoutTime = strtotime($eventsByType['checkout_started']);
                $orderTime = strtotime($eventsByType['order_completed']);
                $hour = $hour ?? date('Y-m-d H:00:00', $checkoutTime);

                $hourlyTimings[$hour]['checkout_duration'][] = ($orderTime - $checkoutTime) * 1000;
            }
        }

        // Calculate averages
        $result = [];
        foreach ($hourlyTimings as $hour => $timings) {
            $result[$hour] = [
                'avg_time_to_cart_ms' => !empty($timings['time_to_cart'])
                    ? (int) (array_sum($timings['time_to_cart']) / count($timings['time_to_cart']))
                    : null,
                'avg_time_to_checkout_ms' => !empty($timings['time_to_checkout'])
                    ? (int) (array_sum($timings['time_to_checkout']) / count($timings['time_to_checkout']))
                    : null,
                'avg_checkout_duration_ms' => !empty($timings['checkout_duration'])
                    ? (int) (array_sum($timings['checkout_duration']) / count($timings['checkout_duration']))
                    : null,
            ];
        }

        return $result;
    }

    /**
     * Dispatch job for a specific event entity.
     */
    public static function dispatchForEvent(int $tenantId, int $eventEntityId, int $hours = 24): void
    {
        static::dispatch($tenantId, $eventEntityId, $hours);
    }

    /**
     * Dispatch job for a specific tenant.
     */
    public static function dispatchForTenant(int $tenantId, int $hours = 24): void
    {
        static::dispatch($tenantId, null, $hours);
    }

    /**
     * Dispatch full historical backfill.
     */
    public static function dispatchFullBackfill(?int $tenantId = null): void
    {
        // 90 days = 2160 hours
        static::dispatch($tenantId, null, 2160);
    }
}
