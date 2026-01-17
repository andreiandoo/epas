<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventAnalyticsHourly;
use App\Models\EventAnalyticsDaily;
use App\Models\EventAnalyticsWeekly;
use App\Models\EventAnalyticsMonthly;
use App\Models\Platform\CoreCustomerEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AggregateEventAnalyticsCommand extends Command
{
    protected $signature = 'analytics:aggregate
                            {--type=all : Type of aggregation (hourly, daily, weekly, monthly, all)}
                            {--event= : Specific event ID to process}
                            {--date= : Specific date to process (YYYY-MM-DD)}
                            {--force : Force re-aggregation even if data exists}';

    protected $description = 'Aggregate event analytics data from raw events into time-based buckets';

    public function handle(): int
    {
        $type = $this->option('type');
        $eventId = $this->option('event');
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : null;
        $force = $this->option('force');

        $this->info("Starting analytics aggregation (type: {$type})...");

        try {
            $events = $eventId
                ? Event::where('id', $eventId)->get()
                : Event::whereNotNull('id')->get(); // All events with analytics

            foreach ($events as $event) {
                $this->processEvent($event, $type, $date, $force);
            }

            $this->info('Analytics aggregation completed successfully.');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Aggregation failed: ' . $e->getMessage());
            Log::error('Analytics aggregation failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }

    protected function processEvent(Event $event, string $type, ?Carbon $date, bool $force): void
    {
        $this->line("Processing event #{$event->id}: {$event->name}");

        if (in_array($type, ['hourly', 'all'])) {
            $this->aggregateHourly($event, $date, $force);
        }

        if (in_array($type, ['daily', 'all'])) {
            $this->aggregateDaily($event, $date, $force);
        }

        if (in_array($type, ['weekly', 'all'])) {
            $this->aggregateWeekly($event, $date, $force);
        }

        if (in_array($type, ['monthly', 'all'])) {
            $this->aggregateMonthly($event, $date, $force);
        }
    }

    /**
     * Aggregate raw events into hourly buckets
     * This processes CoreCustomerEvent records into EventAnalyticsHourly
     */
    protected function aggregateHourly(Event $event, ?Carbon $date, bool $force): void
    {
        $targetDate = $date ?? now();
        $startOfDay = $targetDate->copy()->startOfDay();
        $endOfDay = $targetDate->copy()->endOfDay();

        $this->line("  - Aggregating hourly data for {$targetDate->toDateString()}");

        // Get raw events grouped by hour
        $hourlyData = CoreCustomerEvent::where('event_id', $event->id)
            ->whereBetween('created_at', [$startOfDay, $endOfDay])
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as total_events'),
                DB::raw("SUM(CASE WHEN event_type = 'page_view' THEN 1 ELSE 0 END) as page_views"),
                DB::raw("COUNT(DISTINCT visitor_id) as unique_visitors"),
                DB::raw("SUM(CASE WHEN event_type = 'view_item' THEN 1 ELSE 0 END) as ticket_views"),
                DB::raw("SUM(CASE WHEN event_type = 'add_to_cart' THEN 1 ELSE 0 END) as add_to_carts"),
                DB::raw("SUM(CASE WHEN event_type = 'begin_checkout' THEN 1 ELSE 0 END) as checkouts_started"),
                DB::raw("SUM(CASE WHEN event_type = 'purchase' THEN 1 ELSE 0 END) as purchases"),
                DB::raw("SUM(CASE WHEN event_type = 'purchase' THEN COALESCE(quantity, 1) ELSE 0 END) as tickets_sold"),
                DB::raw("SUM(CASE WHEN event_type = 'purchase' THEN COALESCE(event_value * 100, 0) ELSE 0 END) as revenue_cents"),
                DB::raw("SUM(CASE WHEN event_type = 'view_lineup' THEN 1 ELSE 0 END) as lineup_views"),
                DB::raw("SUM(CASE WHEN event_type = 'view_pricing' THEN 1 ELSE 0 END) as pricing_views"),
                DB::raw("SUM(CASE WHEN event_type = 'view_faq' THEN 1 ELSE 0 END) as faq_views"),
                DB::raw("SUM(CASE WHEN event_type = 'view_gallery' THEN 1 ELSE 0 END) as gallery_views"),
                DB::raw("SUM(CASE WHEN event_type = 'share' THEN 1 ELSE 0 END) as shares"),
                DB::raw("SUM(CASE WHEN event_type = 'event_interest' THEN 1 ELSE 0 END) as interests"),
                DB::raw("SUM(COALESCE(time_on_page_seconds, 0)) as total_time_on_page"),
            ])
            ->groupBy(DB::raw('DATE(created_at)'), DB::raw('HOUR(created_at)'))
            ->get();

        foreach ($hourlyData as $hourData) {
            // Get traffic sources breakdown for this hour
            $trafficSources = $this->getTrafficSourcesForHour($event->id, $hourData->date, $hourData->hour);
            $utmCampaigns = $this->getUtmCampaignsForHour($event->id, $hourData->date, $hourData->hour);
            $devices = $this->getDevicesForHour($event->id, $hourData->date, $hourData->hour);
            $locations = $this->getLocationsForHour($event->id, $hourData->date, $hourData->hour);

            EventAnalyticsHourly::updateOrCreate(
                [
                    'event_id' => $event->id,
                    'date' => $hourData->date,
                    'hour' => $hourData->hour,
                ],
                [
                    'page_views' => $hourData->page_views,
                    'unique_visitors' => $hourData->unique_visitors,
                    'ticket_views' => $hourData->ticket_views,
                    'add_to_carts' => $hourData->add_to_carts,
                    'checkouts_started' => $hourData->checkouts_started,
                    'purchases' => $hourData->purchases,
                    'tickets_sold' => $hourData->tickets_sold,
                    'revenue_cents' => $hourData->revenue_cents,
                    'lineup_views' => $hourData->lineup_views,
                    'pricing_views' => $hourData->pricing_views,
                    'faq_views' => $hourData->faq_views,
                    'gallery_views' => $hourData->gallery_views,
                    'shares' => $hourData->shares,
                    'interests' => $hourData->interests,
                    'total_time_on_page' => $hourData->total_time_on_page,
                    'traffic_sources' => $trafficSources,
                    'utm_campaigns' => $utmCampaigns,
                    'devices' => $devices,
                    'locations' => $locations,
                ]
            );
        }
    }

    /**
     * Aggregate hourly data into daily buckets
     */
    protected function aggregateDaily(Event $event, ?Carbon $date, bool $force): void
    {
        $targetDate = $date ?? now()->subDay(); // Default to yesterday

        $this->line("  - Aggregating daily data for {$targetDate->toDateString()}");

        // Sum up hourly data for the day
        $hourlyData = EventAnalyticsHourly::where('event_id', $event->id)
            ->where('date', $targetDate->toDateString())
            ->get();

        if ($hourlyData->isEmpty()) {
            $this->line("    No hourly data found for this date");
            return;
        }

        $uniqueVisitors = $hourlyData->sum('unique_visitors');
        $purchases = $hourlyData->sum('purchases');
        $revenueCents = $hourlyData->sum('revenue_cents');
        $addToCarts = $hourlyData->sum('add_to_carts');
        $checkouts = $hourlyData->sum('checkouts_started');
        $pageViews = $hourlyData->sum('page_views');

        // Calculate derived metrics
        $conversionRate = $uniqueVisitors > 0 ? ($purchases / $uniqueVisitors) * 100 : 0;
        $cartAbandonmentRate = $addToCarts > 0 ? (($addToCarts - $purchases) / $addToCarts) * 100 : 0;
        $avgOrderValue = $purchases > 0 ? $revenueCents / $purchases : 0;
        $bounceRate = $pageViews > 0 ? ($hourlyData->sum('bounces') / $pageViews) * 100 : 0;

        EventAnalyticsDaily::updateOrCreate(
            [
                'event_id' => $event->id,
                'date' => $targetDate->toDateString(),
            ],
            [
                'page_views' => $pageViews,
                'unique_visitors' => $uniqueVisitors,
                'ticket_views' => $hourlyData->sum('ticket_views'),
                'add_to_carts' => $addToCarts,
                'checkouts_started' => $checkouts,
                'purchases' => $purchases,
                'tickets_sold' => $hourlyData->sum('tickets_sold'),
                'revenue_cents' => $revenueCents,
                'lineup_views' => $hourlyData->sum('lineup_views'),
                'pricing_views' => $hourlyData->sum('pricing_views'),
                'faq_views' => $hourlyData->sum('faq_views'),
                'gallery_views' => $hourlyData->sum('gallery_views'),
                'shares' => $hourlyData->sum('shares'),
                'interests' => $hourlyData->sum('interests'),
                'conversion_rate' => round($conversionRate, 2),
                'cart_abandonment_rate' => round($cartAbandonmentRate, 2),
                'avg_order_value_cents' => round($avgOrderValue, 2),
                'bounce_rate' => round($bounceRate, 2),
                'traffic_sources' => $this->mergeJsonArrays($hourlyData->pluck('traffic_sources')->filter()->toArray()),
                'utm_campaigns' => $this->mergeJsonArrays($hourlyData->pluck('utm_campaigns')->filter()->toArray()),
                'devices' => $this->mergeJsonArrays($hourlyData->pluck('devices')->filter()->toArray()),
                'top_locations' => $this->mergeJsonArrays($hourlyData->pluck('locations')->filter()->toArray()),
            ]
        );
    }

    /**
     * Aggregate daily data into weekly buckets
     */
    protected function aggregateWeekly(Event $event, ?Carbon $date, bool $force): void
    {
        $targetDate = $date ?? now()->subWeek();
        $weekStart = $targetDate->copy()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $this->line("  - Aggregating weekly data for {$weekStart->toDateString()} to {$weekEnd->toDateString()}");

        // Sum up daily data for the week
        $dailyData = EventAnalyticsDaily::where('event_id', $event->id)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->get();

        if ($dailyData->isEmpty()) {
            $this->line("    No daily data found for this week");
            return;
        }

        $uniqueVisitors = $dailyData->sum('unique_visitors');
        $purchases = $dailyData->sum('purchases');
        $revenueCents = $dailyData->sum('revenue_cents');

        $conversionRate = $uniqueVisitors > 0 ? ($purchases / $uniqueVisitors) * 100 : 0;
        $avgOrderValue = $purchases > 0 ? $revenueCents / $purchases : 0;

        $weekly = EventAnalyticsWeekly::updateOrCreate(
            [
                'event_id' => $event->id,
                'week_start' => $weekStart->toDateString(),
            ],
            [
                'year' => $weekStart->year,
                'week_number' => $weekStart->weekOfYear,
                'page_views' => $dailyData->sum('page_views'),
                'unique_visitors' => $uniqueVisitors,
                'ticket_views' => $dailyData->sum('ticket_views'),
                'add_to_carts' => $dailyData->sum('add_to_carts'),
                'checkouts_started' => $dailyData->sum('checkouts_started'),
                'purchases' => $purchases,
                'tickets_sold' => $dailyData->sum('tickets_sold'),
                'revenue_cents' => $revenueCents,
                'lineup_views' => $dailyData->sum('lineup_views'),
                'pricing_views' => $dailyData->sum('pricing_views'),
                'faq_views' => $dailyData->sum('faq_views'),
                'gallery_views' => $dailyData->sum('gallery_views'),
                'shares' => $dailyData->sum('shares'),
                'interests' => $dailyData->sum('interests'),
                'conversion_rate' => round($conversionRate, 2),
                'cart_abandonment_rate' => round($dailyData->avg('cart_abandonment_rate'), 2),
                'avg_order_value_cents' => round($avgOrderValue, 2),
                'avg_time_on_page' => round($dailyData->avg('avg_time_on_page') ?? 0, 2),
                'bounce_rate' => round($dailyData->avg('bounce_rate') ?? 0, 2),
                'traffic_sources' => $this->mergeJsonArrays($dailyData->pluck('traffic_sources')->filter()->toArray()),
                'utm_campaigns' => $this->mergeJsonArrays($dailyData->pluck('utm_campaigns')->filter()->toArray()),
                'devices' => $this->mergeJsonArrays($dailyData->pluck('devices')->filter()->toArray()),
                'top_locations' => $this->mergeJsonArrays($dailyData->pluck('top_locations')->filter()->toArray()),
            ]
        );

        // Calculate week-over-week changes
        $weekly->calculateChanges();
        $weekly->save();
    }

    /**
     * Aggregate daily data into monthly buckets
     */
    protected function aggregateMonthly(Event $event, ?Carbon $date, bool $force): void
    {
        $targetDate = $date ?? now()->subMonth();
        $monthStart = $targetDate->copy()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $this->line("  - Aggregating monthly data for {$monthStart->format('F Y')}");

        // Sum up daily data for the month
        $dailyData = EventAnalyticsDaily::where('event_id', $event->id)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->get();

        if ($dailyData->isEmpty()) {
            $this->line("    No daily data found for this month");
            return;
        }

        $uniqueVisitors = $dailyData->sum('unique_visitors');
        $purchases = $dailyData->sum('purchases');
        $revenueCents = $dailyData->sum('revenue_cents');

        $conversionRate = $uniqueVisitors > 0 ? ($purchases / $uniqueVisitors) * 100 : 0;
        $avgOrderValue = $purchases > 0 ? $revenueCents / $purchases : 0;

        // Get milestone performance for the month
        $milestonePerformance = $this->getMilestonePerformance($event->id, $monthStart, $monthEnd);

        $monthly = EventAnalyticsMonthly::updateOrCreate(
            [
                'event_id' => $event->id,
                'year' => $monthStart->year,
                'month' => $monthStart->month,
            ],
            [
                'month_start' => $monthStart->toDateString(),
                'page_views' => $dailyData->sum('page_views'),
                'unique_visitors' => $uniqueVisitors,
                'ticket_views' => $dailyData->sum('ticket_views'),
                'add_to_carts' => $dailyData->sum('add_to_carts'),
                'checkouts_started' => $dailyData->sum('checkouts_started'),
                'purchases' => $purchases,
                'tickets_sold' => $dailyData->sum('tickets_sold'),
                'revenue_cents' => $revenueCents,
                'lineup_views' => $dailyData->sum('lineup_views'),
                'pricing_views' => $dailyData->sum('pricing_views'),
                'faq_views' => $dailyData->sum('faq_views'),
                'gallery_views' => $dailyData->sum('gallery_views'),
                'shares' => $dailyData->sum('shares'),
                'interests' => $dailyData->sum('interests'),
                'conversion_rate' => round($conversionRate, 2),
                'cart_abandonment_rate' => round($dailyData->avg('cart_abandonment_rate'), 2),
                'avg_order_value_cents' => round($avgOrderValue, 2),
                'avg_time_on_page' => round($dailyData->avg('avg_time_on_page') ?? 0, 2),
                'bounce_rate' => round($dailyData->avg('bounce_rate') ?? 0, 2),
                'traffic_sources' => $this->mergeJsonArrays($dailyData->pluck('traffic_sources')->filter()->toArray()),
                'utm_campaigns' => $this->mergeJsonArrays($dailyData->pluck('utm_campaigns')->filter()->toArray()),
                'devices' => $this->mergeJsonArrays($dailyData->pluck('devices')->filter()->toArray()),
                'top_locations' => $this->mergeJsonArrays($dailyData->pluck('top_locations')->filter()->toArray()),
                'milestone_performance' => $milestonePerformance,
            ]
        );

        // Calculate month-over-month changes
        $monthly->calculateChanges();
        $monthly->save();
    }

    /**
     * Get traffic sources breakdown for a specific hour
     */
    protected function getTrafficSourcesForHour(int $eventId, string $date, int $hour): array
    {
        return CoreCustomerEvent::where('event_id', $eventId)
            ->whereDate('created_at', $date)
            ->whereRaw('HOUR(created_at) = ?', [$hour])
            ->where('event_type', 'page_view')
            ->selectRaw("
                CASE
                    WHEN gclid IS NOT NULL THEN 'google_ads'
                    WHEN fbclid IS NOT NULL THEN 'facebook_ads'
                    WHEN ttclid IS NOT NULL THEN 'tiktok_ads'
                    WHEN utm_source IS NOT NULL THEN LOWER(utm_source)
                    WHEN referrer IS NOT NULL AND referrer != '' THEN 'referral'
                    ELSE 'direct'
                END as source,
                COUNT(*) as count
            ")
            ->groupBy('source')
            ->pluck('count', 'source')
            ->toArray();
    }

    /**
     * Get UTM campaigns breakdown for a specific hour
     */
    protected function getUtmCampaignsForHour(int $eventId, string $date, int $hour): array
    {
        return CoreCustomerEvent::where('event_id', $eventId)
            ->whereDate('created_at', $date)
            ->whereRaw('HOUR(created_at) = ?', [$hour])
            ->whereNotNull('utm_campaign')
            ->select('utm_campaign')
            ->selectRaw("COUNT(*) as views")
            ->selectRaw("SUM(CASE WHEN event_type = 'purchase' THEN 1 ELSE 0 END) as purchases")
            ->selectRaw("SUM(CASE WHEN event_type = 'purchase' THEN COALESCE(event_value, 0) ELSE 0 END) as revenue")
            ->groupBy('utm_campaign')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->utm_campaign => [
                    'views' => $row->views,
                    'purchases' => $row->purchases,
                    'revenue' => $row->revenue,
                ]
            ])
            ->toArray();
    }

    /**
     * Get devices breakdown for a specific hour
     */
    protected function getDevicesForHour(int $eventId, string $date, int $hour): array
    {
        return CoreCustomerEvent::where('event_id', $eventId)
            ->whereDate('created_at', $date)
            ->whereRaw('HOUR(created_at) = ?', [$hour])
            ->whereNotNull('device_type')
            ->selectRaw('LOWER(device_type) as device, COUNT(*) as count')
            ->groupBy('device_type')
            ->pluck('count', 'device')
            ->toArray();
    }

    /**
     * Get locations breakdown for a specific hour
     */
    protected function getLocationsForHour(int $eventId, string $date, int $hour): array
    {
        return CoreCustomerEvent::where('event_id', $eventId)
            ->whereDate('created_at', $date)
            ->whereRaw('HOUR(created_at) = ?', [$hour])
            ->whereNotNull('country_code')
            ->selectRaw('country_code, COUNT(*) as count')
            ->groupBy('country_code')
            ->orderByDesc('count')
            ->limit(20)
            ->pluck('count', 'country_code')
            ->toArray();
    }

    /**
     * Get milestone performance for a date range
     */
    protected function getMilestonePerformance(int $eventId, Carbon $start, Carbon $end): array
    {
        return \App\Models\EventMilestone::where('event_id', $eventId)
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                    ->orWhereBetween('end_date', [$start, $end])
                    ->orWhere(function ($inner) use ($start, $end) {
                        $inner->where('start_date', '<=', $start)
                            ->where('end_date', '>=', $end);
                    });
            })
            ->get()
            ->mapWithKeys(fn ($m) => [
                $m->id => [
                    'type' => $m->type,
                    'name' => $m->name,
                    'budget' => $m->budget,
                    'attributed_revenue' => $m->attributed_revenue,
                    'conversions' => $m->conversions,
                    'roi' => $m->roi,
                    'cac' => $m->cac,
                    'roas' => $m->roas,
                ]
            ])
            ->toArray();
    }

    /**
     * Merge multiple JSON arrays by summing values
     */
    protected function mergeJsonArrays(array $arrays): array
    {
        $result = [];
        foreach ($arrays as $array) {
            if (!is_array($array)) continue;
            foreach ($array as $key => $value) {
                if (is_numeric($value)) {
                    $result[$key] = ($result[$key] ?? 0) + $value;
                } elseif (is_array($value)) {
                    $result[$key] = $this->mergeJsonArrays([$result[$key] ?? [], $value]);
                }
            }
        }
        arsort($result);
        return $result;
    }
}
