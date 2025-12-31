<?php

namespace App\Jobs\Tracking;

use App\Models\FeatureStore\FsPersonActivityPattern;
use App\Models\FeatureStore\FsPersonPurchaseWindow;
use App\Models\Platform\CoreCustomer;
use App\Models\Tracking\TxEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculateTemporalPatternsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 1800;

    public function __construct(
        protected ?int $tenantId = null,
        protected ?int $personId = null,
        protected int $lookbackDays = 365
    ) {
        $this->onQueue('tracking-low');
    }

    public function handle(): void
    {
        Log::info('CalculateTemporalPatternsJob: Starting', [
            'tenant_id' => $this->tenantId ?? 'all',
            'person_id' => $this->personId ?? 'all',
            'lookback_days' => $this->lookbackDays,
        ]);

        $startDate = now()->subDays($this->lookbackDays);

        if ($this->personId) {
            $this->processPersonTemporalPatterns($this->tenantId, $this->personId, $startDate);
        } else {
            $this->processAllPersons($startDate);
        }

        Log::info('CalculateTemporalPatternsJob: Completed');
    }

    protected function processAllPersons($startDate): void
    {
        // Get all persons with activity
        $query = TxEvent::select('tenant_id', 'person_id')
            ->whereNotNull('person_id')
            ->where('occurred_at', '>=', $startDate);

        if ($this->tenantId) {
            $query->where('tenant_id', $this->tenantId);
        }

        $persons = $query->distinct()
            ->cursor();

        $count = 0;
        foreach ($persons as $person) {
            $this->processPersonTemporalPatterns($person->tenant_id, $person->person_id, $startDate);
            $count++;

            if ($count % 100 === 0) {
                Log::info("CalculateTemporalPatternsJob: Processed {$count} persons");
            }
        }

        Log::info("CalculateTemporalPatternsJob: Total {$count} persons processed");
    }

    protected function processPersonTemporalPatterns(int $tenantId, int $personId, $startDate): void
    {
        // Calculate activity patterns (hour/day preferences)
        $this->calculateActivityPattern($tenantId, $personId, $startDate);

        // Calculate purchase windows
        $this->calculatePurchaseWindows($tenantId, $personId, $startDate);
    }

    protected function calculateActivityPattern(int $tenantId, int $personId, $startDate): void
    {
        // Get all events for this person
        $events = TxEvent::where('tenant_id', $tenantId)
            ->where('person_id', $personId)
            ->where('occurred_at', '>=', $startDate)
            ->select('event_name', 'occurred_at')
            ->get();

        if ($events->isEmpty()) {
            return;
        }

        $hourlyViews = array_fill(0, 24, 0);
        $hourlyPurchases = array_fill(0, 24, 0);
        $dailyViews = array_fill(0, 7, 0);
        $dailyPurchases = array_fill(0, 7, 0);

        $viewEvents = ['page_view', 'event_view', 'artist_view'];
        $purchaseEvents = ['order_completed'];

        foreach ($events as $event) {
            $hour = (int) $event->occurred_at->format('G'); // 0-23
            $day = (int) $event->occurred_at->format('w');  // 0-6 (Sunday = 0)

            if (in_array($event->event_name, $viewEvents)) {
                $hourlyViews[$hour]++;
                $dailyViews[$day]++;
            } elseif (in_array($event->event_name, $purchaseEvents)) {
                $hourlyPurchases[$hour]++;
                $dailyPurchases[$day]++;
            }
        }

        // Find peak hours (top 3)
        arsort($hourlyViews);
        $peakHours = array_slice(array_keys($hourlyViews), 0, 3);

        // Find peak days
        arsort($dailyViews);
        $peakDays = array_slice(array_keys($dailyViews), 0, 3);

        // Find preferred hour/day (by purchase if available, else views)
        $preferredHour = array_sum($hourlyPurchases) > 0
            ? array_search(max($hourlyPurchases), $hourlyPurchases)
            : array_search(max($hourlyViews), $hourlyViews);

        $preferredDay = array_sum($dailyPurchases) > 0
            ? array_search(max($dailyPurchases), $dailyPurchases)
            : array_search(max($dailyViews), $dailyViews);

        // Calculate weekend ratio
        $weekendActivity = ($dailyViews[0] ?? 0) + ($dailyViews[6] ?? 0);
        $totalActivity = array_sum($dailyViews);
        $weekendRatio = $totalActivity > 0 ? $weekendActivity / $totalActivity : 0;

        // Weekend buyer if more than 40% of purchases are on weekends
        $weekendPurchases = ($dailyPurchases[0] ?? 0) + ($dailyPurchases[6] ?? 0);
        $totalPurchases = array_sum($dailyPurchases);
        $isWeekendBuyer = $totalPurchases > 0 && ($weekendPurchases / $totalPurchases) > 0.4;

        // Reset arrays to proper format (re-sort by key)
        ksort($hourlyViews);
        ksort($hourlyPurchases);
        ksort($dailyViews);
        ksort($dailyPurchases);
        sort($peakHours);
        sort($peakDays);

        FsPersonActivityPattern::updateOrCreate(
            ['tenant_id' => $tenantId, 'person_id' => $personId],
            [
                'hourly_views' => $hourlyViews,
                'hourly_purchases' => $hourlyPurchases,
                'preferred_hour' => $preferredHour,
                'daily_views' => $dailyViews,
                'daily_purchases' => $dailyPurchases,
                'preferred_day' => $preferredDay,
                'peak_hours' => $peakHours,
                'peak_days' => $peakDays,
                'weekend_ratio' => $weekendRatio,
                'is_weekend_buyer' => $isWeekendBuyer,
            ]
        );
    }

    protected function calculatePurchaseWindows(int $tenantId, int $personId, $startDate): void
    {
        // Get order_completed events with event_entity_id
        $purchases = TxEvent::where('tenant_id', $tenantId)
            ->where('person_id', $personId)
            ->where('event_name', 'order_completed')
            ->where('occurred_at', '>=', $startDate)
            ->whereRaw("entities->>'event_entity_id' IS NOT NULL")
            ->get();

        if ($purchases->isEmpty()) {
            return;
        }

        // We need to calculate days before event for each purchase
        // This requires knowing the event date from the event entity
        $windowCounts = [];
        $windowDays = [];

        foreach (FsPersonPurchaseWindow::WINDOW_TYPES as $type => $range) {
            $windowCounts[$type] = 0;
            $windowDays[$type] = [];
        }

        foreach ($purchases as $purchase) {
            $eventEntityId = $purchase->entities['event_entity_id'] ?? null;
            if (!$eventEntityId) {
                continue;
            }

            // Get event date
            $eventDate = DB::table('events')
                ->where('id', $eventEntityId)
                ->value('start_at');

            if (!$eventDate) {
                continue;
            }

            $eventDate = \Carbon\Carbon::parse($eventDate);
            $purchaseDate = $purchase->occurred_at;

            $daysBefore = $purchaseDate->diffInDays($eventDate, false);

            // Only count if purchase was before event
            if ($daysBefore >= 0) {
                $windowType = FsPersonPurchaseWindow::determineWindowType($daysBefore);
                $windowCounts[$windowType]++;
                $windowDays[$windowType][] = $daysBefore;
            }
        }

        $totalPurchases = array_sum($windowCounts);

        if ($totalPurchases === 0) {
            return;
        }

        // Save window data
        foreach ($windowCounts as $type => $count) {
            if ($count > 0) {
                $avgDays = count($windowDays[$type]) > 0
                    ? array_sum($windowDays[$type]) / count($windowDays[$type])
                    : null;

                FsPersonPurchaseWindow::updateOrCreate(
                    ['tenant_id' => $tenantId, 'person_id' => $personId, 'window_type' => $type],
                    [
                        'purchases_count' => $count,
                        'avg_days_before_event' => $avgDays,
                        'preference_score' => $count / $totalPurchases,
                    ]
                );
            }
        }
    }
}
