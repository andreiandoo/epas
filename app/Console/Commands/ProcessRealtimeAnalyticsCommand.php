<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventAnalyticsHourly;
use App\Models\Platform\CoreCustomerEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessRealtimeAnalyticsCommand extends Command
{
    protected $signature = 'analytics:process-realtime
                            {--minutes=5 : Process events from last N minutes}';

    protected $description = 'Process recent tracking events into real-time analytics (runs every minute)';

    /**
     * Batch size for processing events
     */
    protected const BATCH_SIZE = 1000;

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $cutoff = now()->subMinutes($minutes);

        $this->info("Processing events from last {$minutes} minutes...");

        try {
            // Get all unique event IDs that have recent activity
            $eventIds = CoreCustomerEvent::where('created_at', '>=', $cutoff)
                ->whereNotNull('event_id')
                ->distinct()
                ->pluck('event_id');

            $this->info("Found " . $eventIds->count() . " events with recent activity");

            foreach ($eventIds as $eventId) {
                $this->processEventRealtimeData($eventId, $cutoff);
            }

            $this->info('Real-time analytics processing completed.');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Processing failed: ' . $e->getMessage());
            Log::error('Real-time analytics processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }

    /**
     * Process real-time data for a specific event
     */
    protected function processEventRealtimeData(int $eventId, Carbon $cutoff): void
    {
        $now = now();

        // Group events by hour for the recent period
        $hourlyStats = CoreCustomerEvent::where('event_id', $eventId)
            ->where('created_at', '>=', $cutoff)
            ->select([
                DB::raw('DATE(created_at) as date'),
                DB::raw('HOUR(created_at) as hour'),
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
            ])
            ->groupBy(DB::raw('DATE(created_at)'), DB::raw('HOUR(created_at)'))
            ->get();

        foreach ($hourlyStats as $stats) {
            // Get additional breakdown data
            $trafficSources = $this->getTrafficSources($eventId, $stats->date, $stats->hour, $cutoff);
            $utmCampaigns = $this->getUtmCampaigns($eventId, $stats->date, $stats->hour, $cutoff);
            $devices = $this->getDevices($eventId, $stats->date, $stats->hour, $cutoff);
            $locations = $this->getLocations($eventId, $stats->date, $stats->hour, $cutoff);

            // Upsert into hourly analytics table
            // Use updateOrCreate with increment logic for real-time updates
            $existing = EventAnalyticsHourly::where([
                'event_id' => $eventId,
                'date' => $stats->date,
                'hour' => $stats->hour,
            ])->first();

            if ($existing) {
                // Merge with existing data (for the recent window)
                $existing->update([
                    'page_views' => max($existing->page_views, $stats->page_views),
                    'unique_visitors' => max($existing->unique_visitors, $stats->unique_visitors),
                    'ticket_views' => max($existing->ticket_views, $stats->ticket_views),
                    'add_to_carts' => max($existing->add_to_carts, $stats->add_to_carts),
                    'checkouts_started' => max($existing->checkouts_started, $stats->checkouts_started),
                    'purchases' => max($existing->purchases, $stats->purchases),
                    'tickets_sold' => max($existing->tickets_sold, $stats->tickets_sold),
                    'revenue_cents' => max($existing->revenue_cents, $stats->revenue_cents),
                    'lineup_views' => max($existing->lineup_views, $stats->lineup_views),
                    'pricing_views' => max($existing->pricing_views, $stats->pricing_views),
                    'faq_views' => max($existing->faq_views, $stats->faq_views),
                    'gallery_views' => max($existing->gallery_views, $stats->gallery_views),
                    'shares' => max($existing->shares, $stats->shares),
                    'interests' => max($existing->interests, $stats->interests),
                    'traffic_sources' => $this->mergeJsonData($existing->traffic_sources ?? [], $trafficSources),
                    'utm_campaigns' => $this->mergeJsonData($existing->utm_campaigns ?? [], $utmCampaigns),
                    'devices' => $this->mergeJsonData($existing->devices ?? [], $devices),
                    'locations' => $this->mergeJsonData($existing->locations ?? [], $locations),
                ]);
            } else {
                EventAnalyticsHourly::create([
                    'event_id' => $eventId,
                    'date' => $stats->date,
                    'hour' => $stats->hour,
                    'page_views' => $stats->page_views,
                    'unique_visitors' => $stats->unique_visitors,
                    'ticket_views' => $stats->ticket_views,
                    'add_to_carts' => $stats->add_to_carts,
                    'checkouts_started' => $stats->checkouts_started,
                    'purchases' => $stats->purchases,
                    'tickets_sold' => $stats->tickets_sold,
                    'revenue_cents' => $stats->revenue_cents,
                    'lineup_views' => $stats->lineup_views,
                    'pricing_views' => $stats->pricing_views,
                    'faq_views' => $stats->faq_views,
                    'gallery_views' => $stats->gallery_views,
                    'shares' => $stats->shares,
                    'interests' => $stats->interests,
                    'traffic_sources' => $trafficSources,
                    'utm_campaigns' => $utmCampaigns,
                    'devices' => $devices,
                    'locations' => $locations,
                ]);
            }
        }

        // Invalidate cache for this event
        Cache::forget("event_analytics_realtime_{$eventId}");
        Cache::forget("event_analytics_overview_{$eventId}");
    }

    /**
     * Get traffic sources for a specific hour
     */
    protected function getTrafficSources(int $eventId, string $date, int $hour, Carbon $cutoff): array
    {
        return CoreCustomerEvent::where('event_id', $eventId)
            ->whereDate('created_at', $date)
            ->whereRaw('HOUR(created_at) = ?', [$hour])
            ->where('created_at', '>=', $cutoff)
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
     * Get UTM campaigns for a specific hour
     */
    protected function getUtmCampaigns(int $eventId, string $date, int $hour, Carbon $cutoff): array
    {
        return CoreCustomerEvent::where('event_id', $eventId)
            ->whereDate('created_at', $date)
            ->whereRaw('HOUR(created_at) = ?', [$hour])
            ->where('created_at', '>=', $cutoff)
            ->whereNotNull('utm_campaign')
            ->select('utm_campaign')
            ->selectRaw('COUNT(*) as views')
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
     * Get devices for a specific hour
     */
    protected function getDevices(int $eventId, string $date, int $hour, Carbon $cutoff): array
    {
        return CoreCustomerEvent::where('event_id', $eventId)
            ->whereDate('created_at', $date)
            ->whereRaw('HOUR(created_at) = ?', [$hour])
            ->where('created_at', '>=', $cutoff)
            ->whereNotNull('device_type')
            ->selectRaw('LOWER(device_type) as device, COUNT(*) as count')
            ->groupBy('device_type')
            ->pluck('count', 'device')
            ->toArray();
    }

    /**
     * Get locations for a specific hour
     */
    protected function getLocations(int $eventId, string $date, int $hour, Carbon $cutoff): array
    {
        return CoreCustomerEvent::where('event_id', $eventId)
            ->whereDate('created_at', $date)
            ->whereRaw('HOUR(created_at) = ?', [$hour])
            ->where('created_at', '>=', $cutoff)
            ->whereNotNull('country_code')
            ->selectRaw('country_code, COUNT(*) as count')
            ->groupBy('country_code')
            ->orderByDesc('count')
            ->limit(20)
            ->pluck('count', 'country_code')
            ->toArray();
    }

    /**
     * Merge JSON data arrays (take max values)
     */
    protected function mergeJsonData(array $existing, array $new): array
    {
        foreach ($new as $key => $value) {
            if (is_numeric($value)) {
                $existing[$key] = max($existing[$key] ?? 0, $value);
            } elseif (is_array($value)) {
                $existing[$key] = $this->mergeJsonData($existing[$key] ?? [], $value);
            }
        }
        return $existing;
    }
}
