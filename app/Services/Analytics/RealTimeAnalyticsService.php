<?php

namespace App\Services\Analytics;

use App\Models\Order;
use App\Models\EventMilestone;
use App\Models\EventAnalyticsHourly;
use App\Models\Platform\CoreCustomerEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RealTimeAnalyticsService
{
    /**
     * Cache TTL for real-time data (in seconds)
     */
    protected const REALTIME_CACHE_TTL = 60; // 1 minute

    /**
     * Track when a new order is created (checkout started)
     */
    public function trackOrderCreated(Order $order): void
    {
        if (!$order->marketplace_event_id) {
            return;
        }

        $this->incrementHourlyMetric($order->marketplace_event_id, 'checkouts_started');
        $this->invalidateRealtimeCache($order->marketplace_event_id);
    }

    /**
     * Track when a purchase is completed
     */
    public function trackPurchaseCompleted(Order $order, ?EventMilestone $attributedMilestone = null): void
    {
        if (!$order->marketplace_event_id) {
            return;
        }

        $eventId = $order->marketplace_event_id;
        $ticketCount = $order->items()->sum('quantity') ?: $order->tickets()->count();
        $revenueCents = $order->total_cents ?? ($order->total * 100);

        // Update hourly metrics
        $this->incrementHourlyMetric($eventId, 'purchases');
        $this->incrementHourlyMetric($eventId, 'tickets_sold', $ticketCount);
        $this->incrementHourlyMetric($eventId, 'revenue_cents', $revenueCents);

        // Track UTM campaign performance
        $this->trackUtmCampaignPurchase($eventId, $order);

        // Track traffic source
        $this->trackTrafficSourcePurchase($eventId, $order);

        // Track location
        $this->trackLocationPurchase($eventId, $order);

        // Invalidate cache
        $this->invalidateRealtimeCache($eventId);
    }

    /**
     * Track when an order is cancelled/refunded
     */
    public function trackOrderCancelled(Order $order): void
    {
        if (!$order->marketplace_event_id) {
            return;
        }

        // We could decrement metrics or track separately
        // For now, just invalidate cache so dashboard shows fresh data
        $this->invalidateRealtimeCache($order->marketplace_event_id);
    }

    /**
     * Track page view event
     */
    public function trackPageView(int $eventId, array $data = []): void
    {
        $this->incrementHourlyMetric($eventId, 'page_views');

        if (!empty($data['is_unique'])) {
            $this->incrementHourlyMetric($eventId, 'unique_visitors');
        }

        if (!empty($data['is_bounce'])) {
            $this->incrementHourlyMetric($eventId, 'bounces');
        }

        // Track traffic source
        if (!empty($data['source'])) {
            $this->incrementJsonMetric($eventId, 'traffic_sources', $data['source']);
        }

        // Track device
        if (!empty($data['device_type'])) {
            $this->incrementJsonMetric($eventId, 'devices', $data['device_type']);
        }

        // Track location
        if (!empty($data['country_code'])) {
            $this->incrementJsonMetric($eventId, 'locations', $data['country_code']);
        }

        $this->invalidateRealtimeCache($eventId);
    }

    /**
     * Track ticket view
     */
    public function trackTicketView(int $eventId): void
    {
        $this->incrementHourlyMetric($eventId, 'ticket_views');
        $this->invalidateRealtimeCache($eventId);
    }

    /**
     * Track add to cart
     */
    public function trackAddToCart(int $eventId): void
    {
        $this->incrementHourlyMetric($eventId, 'add_to_carts');
        $this->invalidateRealtimeCache($eventId);
    }

    /**
     * Track engagement events (lineup, pricing, FAQ, gallery views)
     */
    public function trackEngagement(int $eventId, string $type): void
    {
        $metricMap = [
            'view_lineup' => 'lineup_views',
            'view_pricing' => 'pricing_views',
            'view_faq' => 'faq_views',
            'view_gallery' => 'gallery_views',
            'share' => 'shares',
            'event_interest' => 'interests',
        ];

        if (isset($metricMap[$type])) {
            $this->incrementHourlyMetric($eventId, $metricMap[$type]);
            $this->invalidateRealtimeCache($eventId);
        }
    }

    /**
     * Increment a metric in the hourly analytics table
     */
    protected function incrementHourlyMetric(int $eventId, string $metric, int $amount = 1): void
    {
        $now = now();
        $date = $now->toDateString();
        $hour = $now->hour;

        try {
            EventAnalyticsHourly::upsert(
                [
                    [
                        'event_id' => $eventId,
                        'date' => $date,
                        'hour' => $hour,
                        $metric => $amount,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                ],
                ['event_id', 'date', 'hour'],
                [
                    $metric => DB::raw("$metric + $amount"),
                    'updated_at' => $now,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to increment hourly metric', [
                'event_id' => $eventId,
                'metric' => $metric,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Increment a value in a JSON metric column
     */
    protected function incrementJsonMetric(int $eventId, string $column, string $key, int $amount = 1): void
    {
        $now = now();
        $date = $now->toDateString();
        $hour = $now->hour;

        try {
            $record = EventAnalyticsHourly::firstOrCreate(
                ['event_id' => $eventId, 'date' => $date, 'hour' => $hour],
                ['created_at' => $now, 'updated_at' => $now]
            );

            $data = $record->$column ?? [];
            $data[$key] = ($data[$key] ?? 0) + $amount;
            $record->$column = $data;
            $record->save();
        } catch (\Exception $e) {
            Log::error('Failed to increment JSON metric', [
                'event_id' => $eventId,
                'column' => $column,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Track UTM campaign purchase attribution
     */
    protected function trackUtmCampaignPurchase(int $eventId, Order $order): void
    {
        $campaign = $order->metadata['utm_campaign'] ?? $order->meta['utm_campaign'] ?? null;

        if (!$campaign) {
            // Try to get from associated tracking events
            $trackingEvent = CoreCustomerEvent::where('order_id', $order->id)
                ->whereNotNull('utm_campaign')
                ->first();

            $campaign = $trackingEvent?->utm_campaign;
        }

        if ($campaign) {
            $now = now();
            $record = EventAnalyticsHourly::firstOrCreate(
                ['event_id' => $eventId, 'date' => $now->toDateString(), 'hour' => $now->hour],
                ['created_at' => $now, 'updated_at' => $now]
            );

            $campaigns = $record->utm_campaigns ?? [];
            if (!isset($campaigns[$campaign])) {
                $campaigns[$campaign] = ['views' => 0, 'purchases' => 0, 'revenue' => 0];
            }
            $campaigns[$campaign]['purchases']++;
            $campaigns[$campaign]['revenue'] += ($order->total_cents ?? $order->total * 100) / 100;
            $record->utm_campaigns = $campaigns;
            $record->save();
        }
    }

    /**
     * Track traffic source for purchase
     */
    protected function trackTrafficSourcePurchase(int $eventId, Order $order): void
    {
        $source = $this->determineTrafficSource($order);
        if ($source) {
            $this->incrementJsonMetric($eventId, 'traffic_sources', $source . '_purchases');
        }
    }

    /**
     * Track location for purchase
     */
    protected function trackLocationPurchase(int $eventId, Order $order): void
    {
        $country = $order->metadata['country_code'] ?? $order->meta['country_code'] ?? null;

        if (!$country) {
            // Try to get from tracking events
            $trackingEvent = CoreCustomerEvent::where('order_id', $order->id)
                ->whereNotNull('country_code')
                ->first();
            $country = $trackingEvent?->country_code;
        }

        if ($country) {
            $this->incrementJsonMetric($eventId, 'locations', $country . '_purchases');
        }
    }

    /**
     * Determine traffic source from order
     */
    protected function determineTrafficSource(Order $order): ?string
    {
        $meta = array_merge($order->meta ?? [], $order->metadata ?? []);

        // Check click IDs first
        if (!empty($meta['gclid'])) return 'google_ads';
        if (!empty($meta['fbclid'])) return 'facebook_ads';
        if (!empty($meta['ttclid'])) return 'tiktok_ads';

        // Check UTM source
        if (!empty($meta['utm_source'])) {
            return strtolower($meta['utm_source']);
        }

        // Try to get from tracking events
        $trackingEvent = CoreCustomerEvent::where('order_id', $order->id)->first();
        if ($trackingEvent) {
            if ($trackingEvent->gclid) return 'google_ads';
            if ($trackingEvent->fbclid) return 'facebook_ads';
            if ($trackingEvent->ttclid) return 'tiktok_ads';
            if ($trackingEvent->utm_source) return strtolower($trackingEvent->utm_source);
        }

        return 'direct';
    }

    /**
     * Invalidate real-time cache for an event
     */
    protected function invalidateRealtimeCache(int $eventId): void
    {
        Cache::forget("event_analytics_realtime_{$eventId}");
        Cache::forget("event_analytics_overview_{$eventId}");
    }

    /**
     * Get real-time stats for an event (cached for performance)
     */
    public function getRealtimeStats(int $eventId, bool $isMarketplace = false): array
    {
        $prefix = $isMarketplace ? 'mp_' : '';
        return Cache::remember(
            "{$prefix}event_analytics_realtime_{$eventId}",
            self::REALTIME_CACHE_TTL,
            function () use ($eventId, $isMarketplace) {
                return $this->calculateRealtimeStats($eventId, $isMarketplace);
            }
        );
    }

    /**
     * Calculate real-time stats from hourly data
     */
    protected function calculateRealtimeStats(int $eventId, bool $isMarketplace = false): array
    {
        // For marketplace events, compute from raw tracking data
        if ($isMarketplace) {
            return $this->calculateRealtimeStatsFromRaw($eventId);
        }

        $now = now();

        // Last 24 hours data
        $last24h = EventAnalyticsHourly::where('event_id', $eventId)
            ->where(function ($q) use ($now) {
                $q->where('date', $now->toDateString())
                    ->orWhere(function ($inner) use ($now) {
                        $inner->where('date', $now->copy()->subDay()->toDateString())
                            ->where('hour', '>=', $now->hour);
                    });
            })
            ->get();

        // Today's data
        $today = $last24h->where('date', $now->toDateString());

        // Current hour
        $currentHour = $today->where('hour', $now->hour)->first();

        return [
            'current_hour' => [
                'page_views' => $currentHour?->page_views ?? 0,
                'unique_visitors' => $currentHour?->unique_visitors ?? 0,
                'purchases' => $currentHour?->purchases ?? 0,
                'revenue' => ($currentHour?->revenue_cents ?? 0) / 100,
            ],
            'today' => [
                'page_views' => $today->sum('page_views'),
                'unique_visitors' => $today->sum('unique_visitors'),
                'purchases' => $today->sum('purchases'),
                'tickets_sold' => $today->sum('tickets_sold'),
                'revenue' => $today->sum('revenue_cents') / 100,
            ],
            'last_24h' => [
                'page_views' => $last24h->sum('page_views'),
                'unique_visitors' => $last24h->sum('unique_visitors'),
                'purchases' => $last24h->sum('purchases'),
                'tickets_sold' => $last24h->sum('tickets_sold'),
                'revenue' => $last24h->sum('revenue_cents') / 100,
            ],
            'hourly_chart' => $this->buildHourlyChart($last24h, $now),
        ];
    }

    /**
     * Calculate real-time stats from raw tracking data (for marketplace events)
     */
    protected function calculateRealtimeStatsFromRaw(int $eventId): array
    {
        $now = now();
        $startOfDay = $now->copy()->startOfDay();
        $startOfHour = $now->copy()->startOfHour();
        $last24h = $now->copy()->subHours(24);

        // Current hour stats
        $currentHourPageViews = CoreCustomerEvent::where('marketplace_event_id', $eventId)
            ->where('event_type', 'page_view')
            ->where('created_at', '>=', $startOfHour)
            ->count();

        $currentHourUniqueVisitors = CoreCustomerEvent::where('marketplace_event_id', $eventId)
            ->where('event_type', 'page_view')
            ->where('created_at', '>=', $startOfHour)
            ->distinct('visitor_id')
            ->count('visitor_id');

        $currentHourPurchases = CoreCustomerEvent::where('marketplace_event_id', $eventId)
            ->where('event_type', 'purchase')
            ->where('created_at', '>=', $startOfHour)
            ->count();

        $currentHourRevenue = Order::where('marketplace_event_id', $eventId)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('paid_at', '>=', $startOfHour)
            ->sum('total');

        // Today stats
        $todayPageViews = CoreCustomerEvent::where('marketplace_event_id', $eventId)
            ->where('event_type', 'page_view')
            ->where('created_at', '>=', $startOfDay)
            ->count();

        $todayUniqueVisitors = CoreCustomerEvent::where('marketplace_event_id', $eventId)
            ->where('event_type', 'page_view')
            ->where('created_at', '>=', $startOfDay)
            ->distinct('visitor_id')
            ->count('visitor_id');

        $todayPurchases = CoreCustomerEvent::where('marketplace_event_id', $eventId)
            ->where('event_type', 'purchase')
            ->where('created_at', '>=', $startOfDay)
            ->count();

        $todayRevenue = Order::where('marketplace_event_id', $eventId)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('paid_at', '>=', $startOfDay)
            ->sum('total');

        $todayTicketsSold = \App\Models\Ticket::where('marketplace_event_id', $eventId)
            ->whereIn('status', ['valid', 'checked_in'])
            ->where('created_at', '>=', $startOfDay)
            ->count();

        // Last 24h stats
        $last24hPageViews = CoreCustomerEvent::where('marketplace_event_id', $eventId)
            ->where('event_type', 'page_view')
            ->where('created_at', '>=', $last24h)
            ->count();

        $last24hUniqueVisitors = CoreCustomerEvent::where('marketplace_event_id', $eventId)
            ->where('event_type', 'page_view')
            ->where('created_at', '>=', $last24h)
            ->distinct('visitor_id')
            ->count('visitor_id');

        $last24hPurchases = CoreCustomerEvent::where('marketplace_event_id', $eventId)
            ->where('event_type', 'purchase')
            ->where('created_at', '>=', $last24h)
            ->count();

        $last24hRevenue = Order::where('marketplace_event_id', $eventId)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('paid_at', '>=', $last24h)
            ->sum('total');

        $last24hTicketsSold = \App\Models\Ticket::where('marketplace_event_id', $eventId)
            ->whereIn('status', ['valid', 'checked_in'])
            ->where('created_at', '>=', $last24h)
            ->count();

        return [
            'current_hour' => [
                'page_views' => $currentHourPageViews,
                'unique_visitors' => $currentHourUniqueVisitors,
                'purchases' => $currentHourPurchases,
                'revenue' => $currentHourRevenue,
            ],
            'today' => [
                'page_views' => $todayPageViews,
                'unique_visitors' => $todayUniqueVisitors,
                'purchases' => $todayPurchases,
                'tickets_sold' => $todayTicketsSold,
                'revenue' => $todayRevenue,
            ],
            'last_24h' => [
                'page_views' => $last24hPageViews,
                'unique_visitors' => $last24hUniqueVisitors,
                'purchases' => $last24hPurchases,
                'tickets_sold' => $last24hTicketsSold,
                'revenue' => $last24hRevenue,
            ],
            'hourly_chart' => $this->buildHourlyChartFromRaw($eventId, $now),
        ];
    }

    /**
     * Build hourly chart from raw tracking data (for marketplace events)
     */
    protected function buildHourlyChartFromRaw(int $eventId, \Carbon\Carbon $now): array
    {
        $chart = [];

        for ($i = 23; $i >= 0; $i--) {
            $time = $now->copy()->subHours($i);
            $hourStart = $time->copy()->startOfHour();
            $hourEnd = $time->copy()->endOfHour();

            $pageViews = CoreCustomerEvent::where('marketplace_event_id', $eventId)
                ->where('event_type', 'page_view')
                ->whereBetween('created_at', [$hourStart, $hourEnd])
                ->count();

            $purchases = CoreCustomerEvent::where('marketplace_event_id', $eventId)
                ->where('event_type', 'purchase')
                ->whereBetween('created_at', [$hourStart, $hourEnd])
                ->count();

            $revenue = Order::where('marketplace_event_id', $eventId)
                ->whereIn('status', ['paid', 'confirmed', 'completed'])
                ->whereBetween('paid_at', [$hourStart, $hourEnd])
                ->sum('total');

            $chart[] = [
                'time' => $time->format('H:00'),
                'page_views' => $pageViews,
                'purchases' => $purchases,
                'revenue' => $revenue,
            ];
        }

        return $chart;
    }

    /**
     * Build hourly chart data for last 24 hours
     */
    protected function buildHourlyChart($data, Carbon $now): array
    {
        $chart = [];
        $keyedData = $data->keyBy(fn ($item) => $item->date . '_' . $item->hour);

        for ($i = 23; $i >= 0; $i--) {
            $time = $now->copy()->subHours($i);
            $key = $time->toDateString() . '_' . $time->hour;
            $hourData = $keyedData->get($key);

            $chart[] = [
                'time' => $time->format('H:00'),
                'page_views' => $hourData?->page_views ?? 0,
                'purchases' => $hourData?->purchases ?? 0,
                'revenue' => ($hourData?->revenue_cents ?? 0) / 100,
            ];
        }

        return $chart;
    }

    /**
     * Get live visitors count (from recent tracking events)
     */
    public function getLiveVisitorsCount(int $eventId, bool $isMarketplace = false): int
    {
        $trackingColumn = $isMarketplace ? 'marketplace_event_id' : 'event_id';

        return CoreCustomerEvent::where($trackingColumn, $eventId)
            ->where('event_type', 'page_view')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->distinct('visitor_id')
            ->count('visitor_id');
    }

    /**
     * Get live visitors with location for globe display
     */
    public function getLiveVisitorsForGlobe(int $eventId, bool $isMarketplace = false): array
    {
        $trackingColumn = $isMarketplace ? 'marketplace_event_id' : 'event_id';

        return CoreCustomerEvent::where($trackingColumn, $eventId)
            ->where('event_type', 'page_view')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select(['visitor_id', 'latitude', 'longitude', 'city', 'country_code', 'created_at'])
            ->get()
            ->unique('visitor_id')
            ->map(fn ($event) => [
                'lat' => (float) $event->latitude,
                'lng' => (float) $event->longitude,
                'city' => $event->city,
                'country' => $event->country_code,
                'time' => $event->created_at->diffForHumans(),
            ])
            ->values()
            ->toArray();
    }
}
