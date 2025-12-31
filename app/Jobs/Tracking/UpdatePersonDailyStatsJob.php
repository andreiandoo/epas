<?php

namespace App\Jobs\Tracking;

use App\Models\FeatureStore\FsPersonDaily;
use App\Models\Tracking\TxEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Updates daily statistics per person.
 *
 * Aggregates:
 * - Views, carts, checkouts, purchases count
 * - Revenue (gross/net)
 * - Average order value
 * - Decision time (first view to purchase)
 * - Discount and affiliate usage rates
 */
class UpdatePersonDailyStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    protected ?int $tenantId;
    protected ?Carbon $date;
    protected int $lookbackDays;

    /**
     * Create a new job instance.
     *
     * @param int|null $tenantId Process only this tenant
     * @param Carbon|null $date Process only this date (null = yesterday)
     * @param int $lookbackDays For backfill: how many days to process
     */
    public function __construct(?int $tenantId = null, ?Carbon $date = null, int $lookbackDays = 1)
    {
        $this->tenantId = $tenantId;
        $this->date = $date;
        $this->lookbackDays = $lookbackDays;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('UpdatePersonDailyStats started', [
            'tenant_id' => $this->tenantId,
            'date' => $this->date?->toDateString(),
            'lookback_days' => $this->lookbackDays,
        ]);

        $startTime = microtime(true);
        $daysProcessed = 0;
        $personsProcessed = 0;

        try {
            // Determine dates to process
            $dates = $this->getDatesToProcess();

            foreach ($dates as $date) {
                $count = $this->processDate($date);
                $personsProcessed += $count;
                $daysProcessed++;
            }

            $duration = round(microtime(true) - $startTime, 2);

            Log::info('UpdatePersonDailyStats completed', [
                'days_processed' => $daysProcessed,
                'persons_processed' => $personsProcessed,
                'duration_seconds' => $duration,
            ]);

        } catch (\Exception $e) {
            Log::error('UpdatePersonDailyStats job failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /**
     * Get list of dates to process.
     */
    protected function getDatesToProcess(): array
    {
        if ($this->date) {
            return [$this->date->copy()->startOfDay()];
        }

        $dates = [];
        for ($i = 1; $i <= $this->lookbackDays; $i++) {
            $dates[] = now()->subDays($i)->startOfDay();
        }

        return $dates;
    }

    /**
     * Process all persons for a specific date.
     */
    protected function processDate(Carbon $date): int
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        // Get persons with activity on this date
        $query = TxEvent::query()
            ->whereNotNull('person_id')
            ->where('occurred_at', '>=', $startOfDay)
            ->where('occurred_at', '<=', $endOfDay)
            ->select('tenant_id', 'person_id')
            ->groupBy('tenant_id', 'person_id');

        if ($this->tenantId) {
            $query->where('tenant_id', $this->tenantId);
        }

        $persons = $query->get();
        $processed = 0;

        foreach ($persons as $person) {
            try {
                $this->updateStatsForPerson($person->tenant_id, $person->person_id, $date);
                $processed++;
            } catch (\Exception $e) {
                Log::error('Failed to update daily stats for person', [
                    'tenant_id' => $person->tenant_id,
                    'person_id' => $person->person_id,
                    'date' => $date->toDateString(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    /**
     * Update daily stats for a specific person.
     */
    protected function updateStatsForPerson(int $tenantId, int $personId, Carbon $date): void
    {
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        // Get all events for this person on this date
        $events = TxEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('person_id', $personId)
            ->where('occurred_at', '>=', $startOfDay)
            ->where('occurred_at', '<=', $endOfDay)
            ->select('event_name', 'payload', 'occurred_at', 'session_id', 'entities')
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        // Count by event type
        $viewsCount = 0;
        $cartsCount = 0;
        $checkoutsCount = 0;
        $purchasesCount = 0;
        $attendanceCount = 0;

        // Financial metrics
        $grossAmount = 0;
        $netAmount = 0;
        $orderValues = [];

        // Decision time tracking
        $sessionFirstView = [];
        $decisionTimes = [];

        // Discount and affiliate tracking
        $ordersWithDiscount = 0;
        $ordersWithAffiliate = 0;
        $totalOrders = 0;

        // Currency (take from first order)
        $currency = null;

        foreach ($events as $event) {
            switch ($event->event_name) {
                case 'event_view':
                case 'page_view':
                    $viewsCount++;
                    // Track first view per session for decision time
                    if ($event->session_id && !isset($sessionFirstView[$event->session_id])) {
                        $sessionFirstView[$event->session_id] = $event->occurred_at;
                    }
                    break;

                case 'add_to_cart':
                    $cartsCount++;
                    break;

                case 'checkout_started':
                    $checkoutsCount++;
                    break;

                case 'order_completed':
                    $purchasesCount++;
                    $totalOrders++;

                    $gross = (float) ($event->payload['gross_amount'] ?? 0);
                    $net = (float) ($event->payload['net_amount'] ?? $gross);

                    $grossAmount += $gross;
                    $netAmount += $net;
                    $orderValues[] = $gross;

                    // Track discounts
                    if (!empty($event->payload['discount_code']) || ($event->payload['discount_amount'] ?? 0) > 0) {
                        $ordersWithDiscount++;
                    }

                    // Track affiliates
                    if (!empty($event->payload['affiliate_id']) || !empty($event->payload['affiliate_code'])) {
                        $ordersWithAffiliate++;
                    }

                    // Currency
                    if (!$currency && !empty($event->payload['currency'])) {
                        $currency = $event->payload['currency'];
                    }

                    // Decision time: time from first view in session to order
                    if ($event->session_id && isset($sessionFirstView[$event->session_id])) {
                        $firstView = $sessionFirstView[$event->session_id];
                        $decisionMs = $event->occurred_at->diffInMilliseconds($firstView);
                        $decisionTimes[] = $decisionMs;
                    }
                    break;

                case 'entry_granted':
                    $attendanceCount++;
                    break;
            }
        }

        // Calculate averages
        $avgOrderValue = !empty($orderValues) ? array_sum($orderValues) / count($orderValues) : 0;
        $avgDecisionTimeMs = !empty($decisionTimes) ? (int) (array_sum($decisionTimes) / count($decisionTimes)) : null;
        $discountUsageRate = $totalOrders > 0 ? $ordersWithDiscount / $totalOrders : 0;
        $affiliateRate = $totalOrders > 0 ? $ordersWithAffiliate / $totalOrders : 0;

        // Upsert daily stats
        FsPersonDaily::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'person_id' => $personId,
                'date' => $date->toDateString(),
            ],
            [
                'views_count' => $viewsCount,
                'carts_count' => $cartsCount,
                'checkouts_count' => $checkoutsCount,
                'purchases_count' => $purchasesCount,
                'attendance_count' => $attendanceCount,
                'gross_amount' => round($grossAmount, 2),
                'net_amount' => round($netAmount, 2),
                'avg_order_value' => round($avgOrderValue, 2),
                'avg_decision_time_ms' => $avgDecisionTimeMs,
                'discount_usage_rate' => round($discountUsageRate, 4),
                'affiliate_rate' => round($affiliateRate, 4),
                'currency' => $currency ?? 'RON',
            ]
        );
    }

    /**
     * Dispatch job for yesterday's data (default daily run).
     */
    public static function dispatchDaily(?int $tenantId = null): void
    {
        static::dispatch($tenantId, now()->subDay(), 1);
    }

    /**
     * Dispatch job for a specific date.
     */
    public static function dispatchForDate(Carbon $date, ?int $tenantId = null): void
    {
        static::dispatch($tenantId, $date, 1);
    }

    /**
     * Dispatch job for historical backfill.
     */
    public static function dispatchBackfill(int $days = 90, ?int $tenantId = null): void
    {
        static::dispatch($tenantId, null, $days);
    }
}
