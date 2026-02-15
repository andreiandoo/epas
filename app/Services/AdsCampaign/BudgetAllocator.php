<?php

namespace App\Services\AdsCampaign;

use App\Models\AdsCampaign\AdsCampaign;

class BudgetAllocator
{
    /**
     * Default platform weights for initial budget allocation
     * Based on typical event marketing ROI per platform
     */
    protected array $defaultWeights = [
        'facebook' => 0.35,
        'instagram' => 0.30,
        'google' => 0.25,
        'tiktok' => 0.10,
    ];

    /**
     * Allocate campaign budget across platforms
     */
    public function allocate(AdsCampaign $campaign): array
    {
        $platforms = $campaign->target_platforms ?? [];
        $totalBudget = (float) $campaign->total_budget;

        if (empty($platforms)) return [];

        return match ($campaign->budget_allocation) {
            'equal' => $this->allocateEqual($platforms, $totalBudget),
            'performance' => $this->allocateByPerformance($campaign, $platforms, $totalBudget),
            'manual' => $this->allocateManual($campaign, $platforms, $totalBudget),
            default => $this->allocateByWeight($platforms, $totalBudget),
        };
    }

    /**
     * Equal split across all platforms
     */
    protected function allocateEqual(array $platforms, float $totalBudget): array
    {
        $perPlatform = $totalBudget / count($platforms);
        $allocations = [];

        foreach ($platforms as $platform) {
            $allocations[$platform] = round($perPlatform, 2);
        }

        return $allocations;
    }

    /**
     * Allocate by default weights
     */
    protected function allocateByWeight(array $platforms, float $totalBudget): array
    {
        $allocations = [];
        $totalWeight = 0;

        foreach ($platforms as $platform) {
            $totalWeight += $this->defaultWeights[$platform] ?? 0.25;
        }

        foreach ($platforms as $platform) {
            $weight = ($this->defaultWeights[$platform] ?? 0.25) / $totalWeight;
            $allocations[$platform] = round($totalBudget * $weight, 2);
        }

        return $allocations;
    }

    /**
     * Performance-based allocation using historical data
     */
    protected function allocateByPerformance(AdsCampaign $campaign, array $platforms, float $totalBudget): array
    {
        $platformCampaigns = $campaign->platformCampaigns;

        // If no historical data, fall back to weighted
        if ($platformCampaigns->isEmpty()) {
            return $this->allocateByWeight($platforms, $totalBudget);
        }

        // Calculate performance score per platform
        $scores = [];
        $totalScore = 0;

        foreach ($platformCampaigns as $pc) {
            $score = $this->calculatePerformanceScore($pc);
            $scores[$pc->platform] = $score;
            $totalScore += $score;
        }

        // If no meaningful scores, fall back to weighted
        if ($totalScore <= 0) {
            return $this->allocateByWeight($platforms, $totalBudget);
        }

        $allocations = [];
        foreach ($platforms as $platform) {
            $score = $scores[$platform] ?? 0;
            $weight = $totalScore > 0 ? $score / $totalScore : 1 / count($platforms);

            // Set floor: no platform gets less than 10% of budget
            $weight = max($weight, 0.10);
            $allocations[$platform] = round($totalBudget * $weight, 2);
        }

        // Normalize to ensure total matches budget
        $allocatedTotal = array_sum($allocations);
        if ($allocatedTotal > 0 && abs($allocatedTotal - $totalBudget) > 0.01) {
            $factor = $totalBudget / $allocatedTotal;
            foreach ($allocations as &$amount) {
                $amount = round($amount * $factor, 2);
            }
        }

        return $allocations;
    }

    /**
     * Manual allocation from campaign settings
     */
    protected function allocateManual(AdsCampaign $campaign, array $platforms, float $totalBudget): array
    {
        $manual = $campaign->optimization_rules['manual_allocation'] ?? [];
        $allocations = [];

        foreach ($platforms as $platform) {
            $allocations[$platform] = $manual[$platform] ?? ($totalBudget / count($platforms));
        }

        return $allocations;
    }

    /**
     * Calculate performance score for budget allocation
     * Higher score = more budget should be allocated
     */
    protected function calculatePerformanceScore($platformCampaign): float
    {
        $roas = (float) $platformCampaign->roas;
        $conversionRate = (float) $platformCampaign->conversion_rate;
        $ctr = (float) $platformCampaign->ctr;
        $spend = (float) $platformCampaign->spend;

        // No spend = no score (can't evaluate)
        if ($spend <= 0) return 1.0;

        // Weighted scoring: ROAS is most important, then conversion rate, then CTR
        $score = ($roas * 0.50) + ($conversionRate * 0.30) + ($ctr * 0.20);

        return max($score, 0.1);
    }

    /**
     * Calculate recommended daily budget per platform
     */
    public function getDailyBudgets(AdsCampaign $campaign): array
    {
        $allocations = $this->allocate($campaign);
        $daysRemaining = max(1, now()->diffInDays($campaign->end_date ?? now()->addDays(30)));

        $dailyBudgets = [];
        foreach ($allocations as $platform => $total) {
            $dailyBudgets[$platform] = round($total / $daysRemaining, 2);
        }

        return $dailyBudgets;
    }
}
