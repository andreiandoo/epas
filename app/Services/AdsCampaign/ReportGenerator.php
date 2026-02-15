<?php

namespace App\Services\AdsCampaign;

use App\Models\AdsCampaign\AdsCampaign;
use App\Models\AdsCampaign\AdsCampaignMetric;
use App\Models\AdsCampaign\AdsCampaignReport;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ReportGenerator
{
    public function __construct(
        protected MetricsAggregator $metricsAggregator,
    ) {}

    /**
     * Generate a campaign performance report
     */
    public function generate(
        AdsCampaign $campaign,
        string $type = 'weekly',
        ?string $periodStart = null,
        ?string $periodEnd = null,
        ?User $generatedBy = null
    ): AdsCampaignReport {
        $periodStart = $periodStart ?? $this->getDefaultPeriodStart($type, $campaign);
        $periodEnd = $periodEnd ?? now()->toDateString();

        // Gather metrics
        $summary = $this->buildSummary($campaign, $periodStart, $periodEnd);
        $platformBreakdown = $this->buildPlatformBreakdown($campaign, $periodStart, $periodEnd);
        $dailyData = $this->buildDailyData($campaign, $periodStart, $periodEnd);
        $creativePerformance = $this->buildCreativePerformance($campaign);
        $audienceInsights = $this->buildAudienceInsights($campaign, $periodStart, $periodEnd);
        $recommendations = $this->buildRecommendations($campaign, $summary);
        $abTestResults = $campaign->ab_testing_enabled ? $this->buildAbTestResults($campaign) : null;

        $report = AdsCampaignReport::create([
            'campaign_id' => $campaign->id,
            'report_type' => $type,
            'title' => $this->generateTitle($campaign, $type, $periodStart, $periodEnd),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'summary' => $summary,
            'platform_breakdown' => $platformBreakdown,
            'daily_data' => $dailyData,
            'creative_performance' => $creativePerformance,
            'audience_insights' => $audienceInsights,
            'recommendations' => $recommendations,
            'ab_test_results' => $abTestResults,
            'generated_by' => $generatedBy?->id,
        ]);

        Log::info("Campaign report generated", [
            'campaign_id' => $campaign->id,
            'report_id' => $report->id,
            'type' => $type,
        ]);

        return $report;
    }

    /**
     * Generate reports for all active campaigns (scheduled)
     */
    public function generateScheduledReports(): void
    {
        // Daily reports for active campaigns
        AdsCampaign::running()->each(function ($campaign) {
            try {
                $this->generate($campaign, 'daily');
            } catch (\Exception $e) {
                Log::error("Failed to generate daily report", [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        // Weekly reports on Mondays
        if (now()->isMonday()) {
            AdsCampaign::running()->each(function ($campaign) {
                try {
                    $this->generate($campaign, 'weekly');
                } catch (\Exception $e) {
                    Log::error("Failed to generate weekly report", [
                        'campaign_id' => $campaign->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        }
    }

    /**
     * Generate final report when campaign completes
     */
    public function generateFinalReport(AdsCampaign $campaign, ?User $generatedBy = null): AdsCampaignReport
    {
        return $this->generate(
            $campaign,
            'final',
            $campaign->start_date?->toDateString(),
            $campaign->end_date?->toDateString() ?? now()->toDateString(),
            $generatedBy
        );
    }

    // ==========================================
    // REPORT SECTIONS
    // ==========================================

    protected function buildSummary(AdsCampaign $campaign, string $start, string $end): array
    {
        $metrics = AdsCampaignMetric::where('campaign_id', $campaign->id)
            ->where('platform', 'aggregated')
            ->whereBetween('date', [$start, $end])
            ->selectRaw('
                SUM(impressions) as impressions,
                SUM(reach) as reach,
                SUM(clicks) as clicks,
                SUM(spend) as spend,
                SUM(conversions) as conversions,
                SUM(revenue) as revenue,
                SUM(tickets_sold) as tickets_sold,
                SUM(new_customers) as new_customers,
                SUM(video_views) as video_views
            ')
            ->first();

        if (!$metrics) {
            return array_fill_keys(['impressions', 'reach', 'clicks', 'spend', 'conversions', 'revenue', 'tickets_sold', 'ctr', 'cpc', 'cpm', 'roas', 'cac', 'roi'], 0);
        }

        $impressions = (int) $metrics->impressions;
        $clicks = (int) $metrics->clicks;
        $spend = (float) $metrics->spend;
        $conversions = (int) $metrics->conversions;
        $revenue = (float) $metrics->revenue;

        return [
            'impressions' => $impressions,
            'reach' => (int) $metrics->reach,
            'clicks' => $clicks,
            'spend' => $spend,
            'conversions' => $conversions,
            'revenue' => $revenue,
            'tickets_sold' => (int) $metrics->tickets_sold,
            'new_customers' => (int) $metrics->new_customers,
            'video_views' => (int) $metrics->video_views,
            'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
            'cpc' => $clicks > 0 ? round($spend / $clicks, 2) : 0,
            'cpm' => $impressions > 0 ? round(($spend / $impressions) * 1000, 2) : 0,
            'roas' => $spend > 0 ? round($revenue / $spend, 2) : 0,
            'cac' => $conversions > 0 ? round($spend / $conversions, 2) : 0,
            'roi' => $spend > 0 ? round((($revenue - $spend) / $spend) * 100, 1) : 0,
            'budget_total' => (float) $campaign->total_budget,
            'budget_spent' => (float) $campaign->spent_budget,
            'budget_remaining' => (float) $campaign->remaining_budget,
            'budget_utilization' => $campaign->budget_utilization,
        ];
    }

    protected function buildPlatformBreakdown(AdsCampaign $campaign, string $start, string $end): array
    {
        return AdsCampaignMetric::where('campaign_id', $campaign->id)
            ->where('platform', '!=', 'aggregated')
            ->whereBetween('date', [$start, $end])
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
            ->mapWithKeys(function ($row) {
                return [$row->platform => AdsCampaignMetric::calculateDerived($row->toArray())];
            })
            ->toArray();
    }

    protected function buildDailyData(AdsCampaign $campaign, string $start, string $end): array
    {
        return AdsCampaignMetric::where('campaign_id', $campaign->id)
            ->where('platform', 'aggregated')
            ->whereBetween('date', [$start, $end])
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

    protected function buildCreativePerformance(AdsCampaign $campaign): array
    {
        return $campaign->creatives()
            ->where('impressions', '>', 0)
            ->orderByDesc('ctr')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'headline' => $c->headline,
                'type' => $c->type,
                'variant' => $c->variant_label,
                'is_winner' => $c->is_winner,
                'impressions' => $c->impressions,
                'clicks' => $c->clicks,
                'ctr' => (float) $c->ctr,
                'spend' => (float) $c->spend,
                'conversions' => $c->conversions,
                'status' => $c->status,
            ])
            ->toArray();
    }

    protected function buildAudienceInsights(AdsCampaign $campaign, string $start, string $end): array
    {
        $targeting = $campaign->targeting()->first();
        if (!$targeting) return [];

        return [
            'targeting_summary' => $targeting->getAudienceDescription(),
            'age_range' => "{$targeting->age_min}-{$targeting->age_max}",
            'locations' => array_map(fn ($l) => $l['name'] ?? $l['id'], $targeting->locations ?? []),
            'interests' => array_map(fn ($i) => $i['name'], $targeting->interests ?? []),
            'retargeting_enabled' => $campaign->retargeting_enabled,
        ];
    }

    protected function buildRecommendations(AdsCampaign $campaign, array $summary): array
    {
        $recommendations = [];

        // ROI-based recommendations
        if ($summary['roas'] < 1) {
            $recommendations[] = [
                'type' => 'warning',
                'category' => 'roi',
                'message' => "ROAS is below 1.0 ({$summary['roas']}). The campaign is not yet profitable. Consider refining targeting or pausing underperforming platforms.",
                'impact' => 'high',
            ];
        } elseif ($summary['roas'] > 3) {
            $recommendations[] = [
                'type' => 'success',
                'category' => 'roi',
                'message' => "Excellent ROAS of {$summary['roas']}! Consider increasing budget to scale this performance.",
                'impact' => 'high',
            ];
        }

        // CTR recommendations
        if ($summary['ctr'] < 1.0 && $summary['impressions'] > 5000) {
            $recommendations[] = [
                'type' => 'warning',
                'category' => 'engagement',
                'message' => "Low CTR ({$summary['ctr']}%). Ad creatives may need refreshing. Consider new imagery or copy.",
                'impact' => 'medium',
            ];
        }

        // CPC recommendations
        if ($summary['cpc'] > 2.0) {
            $recommendations[] = [
                'type' => 'info',
                'category' => 'cost',
                'message' => "CPC is {$summary['cpc']} EUR. This is above average for event marketing. Review keyword/interest targeting.",
                'impact' => 'medium',
            ];
        }

        // Budget pacing
        if ($summary['budget_utilization'] > 80) {
            $recommendations[] = [
                'type' => 'info',
                'category' => 'budget',
                'message' => "Budget is {$summary['budget_utilization']}% spent. Campaign approaching budget limit.",
                'impact' => 'medium',
            ];
        } elseif ($summary['budget_utilization'] < 20 && $campaign->start_date && now()->diffInDays($campaign->start_date) > 7) {
            $recommendations[] = [
                'type' => 'warning',
                'category' => 'budget',
                'message' => "Only {$summary['budget_utilization']}% of budget spent after " . now()->diffInDays($campaign->start_date) . " days. Consider increasing daily budgets or broadening targeting.",
                'impact' => 'medium',
            ];
        }

        // Recent optimization suggestions from logs
        $recentSuggestions = $campaign->optimizationLogs()
            ->where('source', 'ai_suggested')
            ->where('created_at', '>=', now()->subDays(3))
            ->limit(3)
            ->get();

        foreach ($recentSuggestions as $suggestion) {
            $recommendations[] = [
                'type' => 'info',
                'category' => 'optimization',
                'message' => $suggestion->description,
                'impact' => 'medium',
            ];
        }

        return $recommendations;
    }

    protected function buildAbTestResults(AdsCampaign $campaign): array
    {
        return [
            'variant_a' => $campaign->getVariantAMetrics(),
            'variant_b' => $campaign->getVariantBMetrics(),
            'winner' => $campaign->ab_test_winner,
            'winner_date' => $campaign->ab_test_winner_date?->format('Y-m-d'),
            'metric_used' => $campaign->ab_test_metric,
            'confidence' => $campaign->ab_test_winner ? 'significant' : 'insufficient_data',
        ];
    }

    // ==========================================
    // HELPERS
    // ==========================================

    protected function getDefaultPeriodStart(string $type, AdsCampaign $campaign): string
    {
        return match ($type) {
            'daily' => now()->toDateString(),
            'weekly' => now()->subWeek()->toDateString(),
            'monthly' => now()->subMonth()->toDateString(),
            'final' => $campaign->start_date?->toDateString() ?? now()->subMonth()->toDateString(),
            default => now()->subWeek()->toDateString(),
        };
    }

    protected function generateTitle(AdsCampaign $campaign, string $type, string $start, string $end): string
    {
        return match ($type) {
            'daily' => "{$campaign->name} - Daily Report ({$end})",
            'weekly' => "{$campaign->name} - Weekly Report ({$start} to {$end})",
            'monthly' => "{$campaign->name} - Monthly Report ({$start} to {$end})",
            'final' => "{$campaign->name} - Final Campaign Report",
            'ab_test' => "{$campaign->name} - A/B Test Results",
            default => "{$campaign->name} - Report ({$start} to {$end})",
        };
    }
}
