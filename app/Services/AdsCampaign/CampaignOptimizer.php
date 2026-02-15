<?php

namespace App\Services\AdsCampaign;

use App\Models\AdsCampaign\AdsCampaign;
use App\Models\AdsCampaign\AdsCampaignMetric;
use App\Models\AdsCampaign\AdsOptimizationLog;
use App\Models\AdsCampaign\AdsPlatformCampaign;
use Illuminate\Support\Facades\Log;

class CampaignOptimizer
{
    public function __construct(
        protected FacebookMarketingService $facebookService,
        protected GoogleAdsCampaignService $googleService,
        protected BudgetAllocator $budgetAllocator,
    ) {}

    /**
     * Run full optimization cycle for a campaign
     */
    public function optimize(AdsCampaign $campaign): void
    {
        if (!$campaign->auto_optimize) return;

        // Need at least 2 days of data to optimize
        $dataPoints = $campaign->metrics()->aggregated()->count();
        if ($dataPoints < 2) return;

        $this->checkPerformanceThresholds($campaign);
        $this->optimizeBudgetAllocation($campaign);
        $this->checkCreativePerformance($campaign);
        $this->checkAudienceSaturation($campaign);
    }

    /**
     * Check if campaign meets performance thresholds, pause underperforming segments
     */
    protected function checkPerformanceThresholds(AdsCampaign $campaign): void
    {
        $rules = $campaign->optimization_rules ?? $this->getDefaultRules();
        $recentMetrics = $this->getRecentMetrics($campaign, 3);

        if (!$recentMetrics) return;

        // Check CPC threshold
        if (isset($rules['max_cpc']) && $recentMetrics['cpc'] > $rules['max_cpc']) {
            $this->handleHighCpc($campaign, $recentMetrics, $rules['max_cpc']);
        }

        // Check CTR floor
        if (isset($rules['min_ctr']) && $recentMetrics['ctr'] < $rules['min_ctr'] && $recentMetrics['impressions'] > 5000) {
            $this->handleLowCtr($campaign, $recentMetrics, $rules['min_ctr']);
        }

        // Check ROAS threshold
        if (isset($rules['min_roas']) && $recentMetrics['roas'] < $rules['min_roas'] && $recentMetrics['spend'] > 50) {
            $this->handleLowRoas($campaign, $recentMetrics, $rules['min_roas']);
        }

        // Budget pacing check
        $this->checkBudgetPacing($campaign);
    }

    /**
     * Optimize budget allocation across platforms based on performance
     */
    protected function optimizeBudgetAllocation(AdsCampaign $campaign): void
    {
        if ($campaign->budget_allocation !== 'performance') return;

        $platformMetrics = [];
        foreach ($campaign->platformCampaigns()->active()->get() as $pc) {
            $metrics = $this->getRecentPlatformMetrics($pc, 7);
            if ($metrics && $metrics['spend'] > 0) {
                $platformMetrics[$pc->id] = [
                    'platform_campaign' => $pc,
                    'roas' => $metrics['roas'],
                    'cpc' => $metrics['cpc'],
                    'conversions' => $metrics['conversions'],
                    'spend' => $metrics['spend'],
                ];
            }
        }

        if (count($platformMetrics) < 2) return;

        // Score platforms: higher ROAS and more conversions = higher score
        $totalScore = 0;
        foreach ($platformMetrics as &$pm) {
            $pm['score'] = ($pm['roas'] * 0.6) + (($pm['conversions'] / max($pm['spend'], 1)) * 0.4);
            $totalScore += $pm['score'];
        }

        if ($totalScore <= 0) return;

        // Reallocate remaining budget proportionally to scores
        $remainingBudget = (float) $campaign->remaining_budget;
        $budgetChanged = false;

        foreach ($platformMetrics as $pm) {
            $pc = $pm['platform_campaign'];
            $newAllocation = ($pm['score'] / $totalScore) * $remainingBudget;
            $oldAllocation = (float) $pc->budget_allocated;

            // Only adjust if change is significant (>10%)
            if (abs($newAllocation - $oldAllocation) / max($oldAllocation, 1) > 0.10) {
                $newDailyBudget = $newAllocation / max(1, now()->diffInDays($campaign->end_date ?? now()->addDays(30)));

                try {
                    match ($pc->platform) {
                        'facebook', 'instagram' => $this->facebookService->updateBudget($pc, $newDailyBudget),
                        'google' => $this->googleService->updateBudget($pc, $newDailyBudget),
                    };

                    AdsOptimizationLog::create([
                        'campaign_id' => $campaign->id,
                        'platform_campaign_id' => $pc->id,
                        'action_type' => 'budget_reallocation',
                        'description' => "Budget reallocated for {$pc->platform}: {$oldAllocation} → {$newAllocation} (score: " . round($pm['score'], 2) . ")",
                        'before_state' => ['budget' => $oldAllocation, 'roas' => $pm['roas']],
                        'after_state' => ['budget' => $newAllocation, 'daily_budget' => $newDailyBudget],
                        'trigger_metrics' => $pm,
                        'source' => 'auto',
                    ]);

                    $budgetChanged = true;
                } catch (\Exception $e) {
                    Log::warning("Budget reallocation failed", ['error' => $e->getMessage()]);
                }
            }
        }

        if ($budgetChanged) {
            $campaign->update(['status' => AdsCampaign::STATUS_OPTIMIZING]);
        }
    }

    /**
     * Check creative performance and pause underperformers
     */
    protected function checkCreativePerformance(AdsCampaign $campaign): void
    {
        $creatives = $campaign->creatives()->active()->get();
        if ($creatives->count() < 2) return;

        // Get performance per creative (via variant labels)
        $bestCtr = 0;
        $bestCreative = null;

        foreach ($creatives as $creative) {
            if ($creative->impressions > 1000 && $creative->ctr > $bestCtr) {
                $bestCtr = $creative->ctr;
                $bestCreative = $creative;
            }
        }

        if (!$bestCreative) return;

        // Pause creatives performing significantly worse (< 50% of best CTR)
        foreach ($creatives as $creative) {
            if ($creative->id === $bestCreative->id) continue;
            if ($creative->impressions < 1000) continue;

            if ($creative->ctr < $bestCtr * 0.5) {
                AdsOptimizationLog::create([
                    'campaign_id' => $campaign->id,
                    'action_type' => 'creative_pause',
                    'description' => "Paused underperforming creative '{$creative->headline}' (CTR: {$creative->ctr}% vs best: {$bestCtr}%)",
                    'before_state' => ['creative_id' => $creative->id, 'ctr' => $creative->ctr],
                    'after_state' => ['status' => 'paused'],
                    'source' => 'auto',
                ]);

                $creative->update(['status' => 'paused']);
            }
        }
    }

    /**
     * Check for audience saturation (high frequency, declining CTR)
     */
    protected function checkAudienceSaturation(AdsCampaign $campaign): void
    {
        foreach ($campaign->platformCampaigns()->active()->get() as $pc) {
            // High frequency indicates audience saturation
            if ($pc->frequency > 5) {
                $trendMetrics = AdsCampaignMetric::where('platform_campaign_id', $pc->id)
                    ->orderBy('date', 'desc')
                    ->limit(7)
                    ->get();

                if ($trendMetrics->count() < 3) continue;

                $recentCtr = $trendMetrics->take(3)->avg('ctr');
                $olderCtr = $trendMetrics->skip(3)->avg('ctr');

                // CTR declining by > 20% indicates ad fatigue
                if ($olderCtr > 0 && (($olderCtr - $recentCtr) / $olderCtr) > 0.20) {
                    AdsOptimizationLog::create([
                        'campaign_id' => $campaign->id,
                        'platform_campaign_id' => $pc->id,
                        'action_type' => 'audience_expansion',
                        'description' => "Audience saturation detected on {$pc->platform}. Frequency: {$pc->frequency}, CTR declining from " . round($olderCtr, 2) . "% to " . round($recentCtr, 2) . "%. Consider expanding audience or refreshing creatives.",
                        'trigger_metrics' => [
                            'frequency' => $pc->frequency,
                            'recent_ctr' => $recentCtr,
                            'older_ctr' => $olderCtr,
                            'decline_pct' => round((($olderCtr - $recentCtr) / $olderCtr) * 100, 1),
                        ],
                        'source' => 'ai_suggested',
                    ]);
                }
            }
        }
    }

    /**
     * Check budget pacing - are we spending too fast or too slow?
     */
    protected function checkBudgetPacing(AdsCampaign $campaign): void
    {
        if (!$campaign->end_date || !$campaign->start_date) return;

        $totalDays = $campaign->start_date->diffInDays($campaign->end_date);
        $elapsedDays = $campaign->start_date->diffInDays(now());

        if ($totalDays <= 0 || $elapsedDays <= 0) return;

        $expectedSpendPct = ($elapsedDays / $totalDays) * 100;
        $actualSpendPct = $campaign->budget_utilization;

        // Overpacing: spending > 120% of expected
        if ($actualSpendPct > $expectedSpendPct * 1.2) {
            AdsOptimizationLog::create([
                'campaign_id' => $campaign->id,
                'action_type' => 'budget_decrease',
                'description' => "Budget overpacing: {$actualSpendPct}% spent vs {$expectedSpendPct}% expected at this point. Daily budgets should be reduced.",
                'trigger_metrics' => [
                    'expected_spend_pct' => round($expectedSpendPct, 1),
                    'actual_spend_pct' => round($actualSpendPct, 1),
                    'days_elapsed' => $elapsedDays,
                    'days_remaining' => $totalDays - $elapsedDays,
                ],
                'source' => 'ai_suggested',
            ]);
        }

        // Underpacing: spending < 60% of expected
        if ($actualSpendPct < $expectedSpendPct * 0.6 && $elapsedDays > 3) {
            AdsOptimizationLog::create([
                'campaign_id' => $campaign->id,
                'action_type' => 'budget_increase',
                'description' => "Budget underpacing: {$actualSpendPct}% spent vs {$expectedSpendPct}% expected. Consider increasing bids or expanding targeting.",
                'trigger_metrics' => [
                    'expected_spend_pct' => round($expectedSpendPct, 1),
                    'actual_spend_pct' => round($actualSpendPct, 1),
                ],
                'source' => 'ai_suggested',
            ]);
        }
    }

    // ==========================================
    // HELPERS
    // ==========================================

    protected function getRecentMetrics(AdsCampaign $campaign, int $days): ?array
    {
        $metrics = AdsCampaignMetric::where('campaign_id', $campaign->id)
            ->where('platform', 'aggregated')
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                SUM(spend) as spend,
                SUM(conversions) as conversions,
                SUM(revenue) as revenue
            ')
            ->first();

        if (!$metrics || $metrics->impressions == 0) return null;

        return AdsCampaignMetric::calculateDerived($metrics->toArray());
    }

    protected function getRecentPlatformMetrics(AdsPlatformCampaign $pc, int $days): ?array
    {
        $metrics = AdsCampaignMetric::where('platform_campaign_id', $pc->id)
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('
                SUM(impressions) as impressions,
                SUM(clicks) as clicks,
                SUM(spend) as spend,
                SUM(conversions) as conversions,
                SUM(revenue) as revenue
            ')
            ->first();

        if (!$metrics || $metrics->impressions == 0) return null;

        return AdsCampaignMetric::calculateDerived($metrics->toArray());
    }

    protected function getDefaultRules(): array
    {
        return [
            'max_cpc' => 3.00,
            'min_ctr' => 0.5,
            'min_roas' => 1.5,
            'max_frequency' => 5,
        ];
    }

    protected function handleHighCpc(AdsCampaign $campaign, array $metrics, float $threshold): void
    {
        AdsOptimizationLog::create([
            'campaign_id' => $campaign->id,
            'action_type' => 'bid_adjustment',
            'description' => "CPC ({$metrics['cpc']}) exceeds threshold ({$threshold}). Review targeting or creative quality.",
            'trigger_metrics' => $metrics,
            'source' => 'ai_suggested',
        ]);
    }

    protected function handleLowCtr(AdsCampaign $campaign, array $metrics, float $threshold): void
    {
        AdsOptimizationLog::create([
            'campaign_id' => $campaign->id,
            'action_type' => 'creative_pause',
            'description' => "CTR ({$metrics['ctr']}%) below threshold ({$threshold}%). Consider refreshing ad creatives.",
            'trigger_metrics' => $metrics,
            'source' => 'ai_suggested',
        ]);
    }

    protected function handleLowRoas(AdsCampaign $campaign, array $metrics, float $threshold): void
    {
        AdsOptimizationLog::create([
            'campaign_id' => $campaign->id,
            'action_type' => 'budget_decrease',
            'description' => "ROAS ({$metrics['roas']}) below threshold ({$threshold}). Consider reducing budget or refining audience.",
            'trigger_metrics' => $metrics,
            'source' => 'ai_suggested',
        ]);
    }
}
