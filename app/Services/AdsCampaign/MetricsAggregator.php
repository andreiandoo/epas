<?php

namespace App\Services\AdsCampaign;

use App\Models\AdsCampaign\AdsCampaign;
use App\Models\AdsCampaign\AdsCampaignMetric;
use App\Models\AdsCampaign\AdsPlatformCampaign;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class MetricsAggregator
{
    public function __construct(
        protected FacebookMarketingService $facebookService,
        protected GoogleAdsCampaignService $googleService,
    ) {}

    /**
     * Sync metrics for a single campaign from all platforms
     */
    public function syncCampaignMetrics(AdsCampaign $campaign): void
    {
        $platformCampaigns = $campaign->platformCampaigns()->where('status', 'active')->get();

        foreach ($platformCampaigns as $pc) {
            try {
                $this->syncPlatformMetrics($campaign, $pc);
            } catch (\Exception $e) {
                Log::warning("Metrics sync failed for platform campaign {$pc->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Calculate aggregated daily metrics
        $this->calculateAggregatedMetrics($campaign);

        // Enrich with Tixello revenue data
        $this->enrichWithRevenueData($campaign);

        // Update campaign aggregate totals
        $campaign->recalculateAggregates();
    }

    /**
     * Sync metrics for all active campaigns (scheduled job)
     */
    public function syncAllActiveCampaigns(): void
    {
        $campaigns = AdsCampaign::running()->with('platformCampaigns')->get();

        foreach ($campaigns as $campaign) {
            try {
                $this->syncCampaignMetrics($campaign);
            } catch (\Exception $e) {
                Log::error("Failed to sync metrics for campaign {$campaign->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Fetch and store metrics from a specific platform
     */
    protected function syncPlatformMetrics(AdsCampaign $campaign, AdsPlatformCampaign $pc): void
    {
        $dateFrom = $pc->last_synced_at
            ? $pc->last_synced_at->subDay()->toDateString()
            : ($campaign->start_date ? $campaign->start_date->toDateString() : now()->subDays(7)->toDateString());
        $dateTo = now()->toDateString();

        $insights = match ($pc->platform) {
            'facebook', 'instagram' => $this->facebookService->fetchInsights($pc, $dateFrom, $dateTo),
            'google' => $this->googleService->fetchInsights($pc, $dateFrom, $dateTo),
            default => [],
        };

        foreach ($insights as $dayData) {
            $calculated = AdsCampaignMetric::calculateDerived($dayData);

            AdsCampaignMetric::updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'platform_campaign_id' => $pc->id,
                    'date' => $dayData['date'],
                    'platform' => $pc->platform,
                    'variant_label' => $pc->variant_label,
                ],
                $calculated
            );
        }

        // Update platform campaign totals
        $totals = AdsCampaignMetric::where('platform_campaign_id', $pc->id)
            ->selectRaw('
                SUM(impressions) as impressions,
                SUM(reach) as reach,
                SUM(clicks) as clicks,
                SUM(spend) as spend,
                SUM(conversions) as conversions,
                SUM(revenue) as revenue,
                SUM(video_views) as video_views
            ')
            ->first();

        if ($totals) {
            $pc->syncMetrics([
                'impressions' => (int) $totals->impressions,
                'reach' => (int) $totals->reach,
                'clicks' => (int) $totals->clicks,
                'spend' => (float) $totals->spend,
                'conversions' => (int) $totals->conversions,
                'revenue' => (float) $totals->revenue,
                'video_views' => (int) $totals->video_views,
            ]);
        }
    }

    /**
     * Calculate aggregated metrics across all platforms per day
     */
    protected function calculateAggregatedMetrics(AdsCampaign $campaign): void
    {
        $dailyAggregates = AdsCampaignMetric::where('campaign_id', $campaign->id)
            ->where('platform', '!=', 'aggregated')
            ->groupBy('date')
            ->selectRaw('
                date,
                SUM(impressions) as impressions,
                SUM(reach) as reach,
                SUM(clicks) as clicks,
                SUM(spend) as spend,
                SUM(conversions) as conversions,
                SUM(revenue) as revenue,
                SUM(tickets_sold) as tickets_sold,
                SUM(new_customers) as new_customers,
                SUM(likes) as likes,
                SUM(shares) as shares,
                SUM(comments) as comments,
                SUM(video_views) as video_views
            ')
            ->get();

        foreach ($dailyAggregates as $day) {
            $calculated = AdsCampaignMetric::calculateDerived($day->toArray());

            AdsCampaignMetric::updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'platform_campaign_id' => null,
                    'date' => $day->date,
                    'platform' => 'aggregated',
                    'variant_label' => null,
                ],
                $calculated
            );
        }
    }

    /**
     * Enrich metrics with actual Tixello revenue data
     * Matches UTM parameters from orders to campaign
     */
    protected function enrichWithRevenueData(AdsCampaign $campaign): void
    {
        if (!$campaign->event_id) return;

        $utmCampaign = $campaign->utm_campaign;
        if (!$utmCampaign) return;

        // Find orders attributable to this campaign via UTM tracking
        $orders = Order::where('event_id', $campaign->event_id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where(function ($q) use ($utmCampaign, $campaign) {
                $q->where('utm_campaign', $utmCampaign)
                  ->orWhere('utm_source', $campaign->utm_source);
            })
            ->selectRaw('DATE(created_at) as order_date, SUM(total) as revenue, COUNT(*) as orders, COUNT(DISTINCT customer_id) as customers')
            ->groupBy('order_date')
            ->get();

        foreach ($orders as $order) {
            $metric = AdsCampaignMetric::where('campaign_id', $campaign->id)
                ->where('date', $order->order_date)
                ->where('platform', 'aggregated')
                ->first();

            if ($metric) {
                $metric->update([
                    'revenue' => $order->revenue,
                    'tickets_sold' => $order->orders,
                    'new_customers' => $order->customers,
                    'roas' => $metric->spend > 0 ? $order->revenue / $metric->spend : 0,
                    'cac' => $order->customers > 0 ? $metric->spend / $order->customers : 0,
                ]);
            }
        }
    }

    /**
     * Get performance trend for a campaign (last N days)
     */
    public function getPerformanceTrend(AdsCampaign $campaign, int $days = 14): array
    {
        return AdsCampaignMetric::where('campaign_id', $campaign->id)
            ->where('platform', 'aggregated')
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('date')
            ->get()
            ->map(fn ($m) => [
                'date' => $m->date->format('Y-m-d'),
                'impressions' => $m->impressions,
                'clicks' => $m->clicks,
                'spend' => (float) $m->spend,
                'conversions' => $m->conversions,
                'revenue' => (float) $m->revenue,
                'ctr' => (float) $m->ctr,
                'roas' => (float) $m->roas,
            ])
            ->toArray();
    }

    /**
     * Get platform comparison data
     */
    public function getPlatformComparison(AdsCampaign $campaign): array
    {
        return AdsCampaignMetric::where('campaign_id', $campaign->id)
            ->where('platform', '!=', 'aggregated')
            ->groupBy('platform')
            ->selectRaw('
                platform,
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                SUM(spend) as spend,
                SUM(conversions) as conversions,
                SUM(revenue) as revenue
            ')
            ->get()
            ->map(function ($row) {
                $calculated = AdsCampaignMetric::calculateDerived($row->toArray());
                $calculated['platform_label'] = match ($row->platform) {
                    'facebook' => 'Facebook',
                    'instagram' => 'Instagram',
                    'google' => 'Google Ads',
                    'tiktok' => 'TikTok',
                    default => ucfirst($row->platform),
                };
                return $calculated;
            })
            ->toArray();
    }
}
