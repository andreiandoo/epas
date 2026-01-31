<?php

namespace App\Services\Platform;

use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class LtvPredictionService
{
    // LTV Tiers
    const TIER_PLATINUM = 'platinum';   // Top 5%
    const TIER_GOLD = 'gold';           // Top 20%
    const TIER_SILVER = 'silver';       // Top 50%
    const TIER_BRONZE = 'bronze';       // Bottom 50%

    // Prediction confidence levels
    const CONFIDENCE_HIGH = 'high';
    const CONFIDENCE_MEDIUM = 'medium';
    const CONFIDENCE_LOW = 'low';

    // Feature weights for prediction model
    protected array $featureWeights = [
        'first_purchase_value' => 0.20,
        'early_frequency' => 0.25,
        'engagement_score' => 0.15,
        'session_depth' => 0.10,
        'category_diversity' => 0.10,
        'channel_quality' => 0.10,
        'email_engagement' => 0.05,
        'referral_source' => 0.05,
    ];

    // Historical averages (would be calculated from data in production)
    protected array $benchmarks = [];

    protected int $predictionHorizonMonths = 12;

    public function setPredictionHorizon(int $months): self
    {
        $this->predictionHorizonMonths = $months;
        return $this;
    }

    /**
     * Predict LTV for a single customer
     */
    public function predictLtv(CoreCustomer $customer): array
    {
        $this->loadBenchmarks($customer);

        $features = $this->extractFeatures($customer);
        $prediction = $this->calculatePrediction($features, $customer);
        $tier = $this->assignTier($prediction['predicted_ltv']);
        $confidence = $this->calculateConfidence($features, $customer);

        return [
            'customer_id' => $customer->id,
            'customer_uuid' => $customer->uuid,
            'current_ltv' => round($customer->lifetime_value ?? 0, 2),
            'predicted_ltv' => round($prediction['predicted_ltv'], 2),
            'predicted_12m_ltv' => round($prediction['predicted_12m_ltv'], 2),
            'predicted_24m_ltv' => round($prediction['predicted_24m_ltv'], 2),
            'ltv_growth_potential' => round($prediction['growth_potential'], 2),
            'tier' => $tier,
            'tier_label' => $this->getTierLabel($tier),
            'confidence' => $confidence,
            'confidence_score' => $prediction['confidence_score'],
            'features' => $features,
            'factors' => $this->getContributingFactors($features),
            'recommendations' => $this->getGrowthRecommendations($features, $tier),
            'predicted_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Batch predict LTV for multiple customers
     */
    public function batchPredict(Collection $customers): Collection
    {
        return $customers->map(fn($customer) => $this->predictLtv($customer));
    }

    /**
     * Get high-potential customers (under-realized LTV)
     */
    public function getHighPotentialCustomers(
        int $limit = 50,
        ?int $tenantId = null
    ): Collection {
        $customers = CoreCustomer::query()
            ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
            ->notMerged()
            ->notAnonymized()
            ->where('total_orders', '>=', 1)
            ->where('total_orders', '<=', 3) // Early customers with growth potential
            ->orderByDesc('engagement_score')
            ->limit($limit * 2)
            ->get();

        return $customers
            ->map(fn($customer) => $this->predictLtv($customer))
            ->filter(fn($prediction) => $prediction['ltv_growth_potential'] > 50) // 50%+ growth potential
            ->sortByDesc('ltv_growth_potential')
            ->take($limit)
            ->values();
    }

    /**
     * Get LTV distribution by segment
     */
    public function getLtvBySegment(?int $tenantId = null): array
    {
        $segments = CoreCustomer::query()
            ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
            ->notMerged()
            ->notAnonymized()
            ->whereNotNull('customer_segment')
            ->groupBy('customer_segment')
            ->selectRaw('
                customer_segment,
                COUNT(*) as customer_count,
                AVG(lifetime_value) as avg_ltv,
                SUM(lifetime_value) as total_ltv,
                MIN(lifetime_value) as min_ltv,
                MAX(lifetime_value) as max_ltv,
                PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY lifetime_value) as median_ltv
            ')
            ->get();

        // Sample customers for predictions
        $segmentPredictions = [];
        foreach ($segments as $segment) {
            $sampleCustomers = CoreCustomer::query()
                ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
                ->where('customer_segment', $segment->customer_segment)
                ->notMerged()
                ->notAnonymized()
                ->inRandomOrder()
                ->limit(20)
                ->get();

            $predictions = $this->batchPredict($sampleCustomers);
            $avgPredictedLtv = $predictions->avg('predicted_ltv');

            $segmentPredictions[$segment->customer_segment] = [
                'segment' => $segment->customer_segment,
                'customer_count' => $segment->customer_count,
                'current_avg_ltv' => round($segment->avg_ltv, 2),
                'predicted_avg_ltv' => round($avgPredictedLtv, 2),
                'total_ltv' => round($segment->total_ltv, 2),
                'min_ltv' => round($segment->min_ltv, 2),
                'max_ltv' => round($segment->max_ltv, 2),
                'growth_potential' => $segment->avg_ltv > 0
                    ? round((($avgPredictedLtv - $segment->avg_ltv) / $segment->avg_ltv) * 100, 1)
                    : 0,
            ];
        }

        return $segmentPredictions;
    }

    /**
     * Get LTV cohort analysis
     */
    public function getLtvByCohort(
        string $cohortType = 'month',
        int $cohortsBack = 12,
        ?int $tenantId = null
    ): array {
        $cohortField = $cohortType === 'month' ? 'cohort_month' : 'cohort_week';

        $cohorts = CoreCustomer::query()
            ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
            ->notMerged()
            ->notAnonymized()
            ->whereNotNull($cohortField)
            ->groupBy($cohortField)
            ->selectRaw("
                {$cohortField} as cohort,
                COUNT(*) as customer_count,
                SUM(lifetime_value) as total_ltv,
                AVG(lifetime_value) as avg_ltv,
                AVG(total_orders) as avg_orders
            ")
            ->orderByDesc($cohortField)
            ->limit($cohortsBack)
            ->get();

        $analysis = [];
        foreach ($cohorts as $cohort) {
            $cohortAge = $this->getCohortAgeMonths($cohort->cohort, $cohortType);

            // Project expected LTV based on cohort age
            $projectedLtv = $this->projectLtvByAge($cohort->avg_ltv, $cohortAge);

            $analysis[] = [
                'cohort' => $cohort->cohort,
                'cohort_age_months' => $cohortAge,
                'customer_count' => $cohort->customer_count,
                'total_ltv' => round($cohort->total_ltv, 2),
                'avg_ltv' => round($cohort->avg_ltv, 2),
                'avg_orders' => round($cohort->avg_orders, 1),
                'projected_12m_ltv' => round($projectedLtv['12m'], 2),
                'projected_24m_ltv' => round($projectedLtv['24m'], 2),
                'ltv_per_month' => $cohortAge > 0 ? round($cohort->avg_ltv / $cohortAge, 2) : 0,
            ];
        }

        return $analysis;
    }

    /**
     * Get LTV tier distribution
     */
    public function getLtvTierDistribution(?int $tenantId = null): array
    {
        // Get LTV percentiles
        $percentiles = CoreCustomer::query()
            ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
            ->notMerged()
            ->notAnonymized()
            ->where('lifetime_value', '>', 0)
            ->selectRaw('
                PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY lifetime_value) as p95,
                PERCENTILE_CONT(0.80) WITHIN GROUP (ORDER BY lifetime_value) as p80,
                PERCENTILE_CONT(0.50) WITHIN GROUP (ORDER BY lifetime_value) as p50
            ')
            ->first();

        if (!$percentiles) {
            return [];
        }

        $tiers = [
            self::TIER_PLATINUM => [
                'min' => $percentiles->p95,
                'max' => null,
                'label' => 'Platinum (Top 5%)',
            ],
            self::TIER_GOLD => [
                'min' => $percentiles->p80,
                'max' => $percentiles->p95,
                'label' => 'Gold (Top 20%)',
            ],
            self::TIER_SILVER => [
                'min' => $percentiles->p50,
                'max' => $percentiles->p80,
                'label' => 'Silver (Top 50%)',
            ],
            self::TIER_BRONZE => [
                'min' => 0,
                'max' => $percentiles->p50,
                'label' => 'Bronze (Bottom 50%)',
            ],
        ];

        $distribution = [];
        foreach ($tiers as $tier => $bounds) {
            $query = CoreCustomer::query()
                ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
                ->notMerged()
                ->notAnonymized()
                ->where('lifetime_value', '>=', $bounds['min']);

            if ($bounds['max'] !== null) {
                $query->where('lifetime_value', '<', $bounds['max']);
            }

            $stats = $query->selectRaw('
                COUNT(*) as count,
                SUM(lifetime_value) as total_ltv,
                AVG(lifetime_value) as avg_ltv
            ')->first();

            $distribution[$tier] = [
                'tier' => $tier,
                'label' => $bounds['label'],
                'threshold' => round($bounds['min'], 2),
                'customer_count' => $stats->count ?? 0,
                'total_ltv' => round($stats->total_ltv ?? 0, 2),
                'avg_ltv' => round($stats->avg_ltv ?? 0, 2),
            ];
        }

        return $distribution;
    }

    /**
     * Get LTV prediction dashboard
     */
    public function getLtvDashboard(?int $tenantId = null): array
    {
        $cacheKey = "ltv:dashboard:" . ($tenantId ?? 'all');

        return Cache::remember($cacheKey, 3600, function () use ($tenantId) {
            $totalCustomers = CoreCustomer::query()
                ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
                ->notMerged()
                ->notAnonymized()
                ->count();

            $totalLtv = CoreCustomer::query()
                ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
                ->notMerged()
                ->notAnonymized()
                ->sum('lifetime_value');

            $avgLtv = $totalCustomers > 0 ? $totalLtv / $totalCustomers : 0;

            // Get high-potential customers
            $highPotential = $this->getHighPotentialCustomers(10, $tenantId);
            $totalPotentialValue = $highPotential->sum('ltv_growth_potential');

            return [
                'summary' => [
                    'total_customers' => $totalCustomers,
                    'total_ltv' => round($totalLtv, 2),
                    'average_ltv' => round($avgLtv, 2),
                    'high_potential_customers' => $highPotential->count(),
                    'potential_revenue_opportunity' => round($totalPotentialValue, 2),
                ],
                'tier_distribution' => $this->getLtvTierDistribution($tenantId),
                'segment_analysis' => $this->getLtvBySegment($tenantId),
                'high_potential_customers' => $highPotential->take(5)->toArray(),
            ];
        });
    }

    /**
     * Extract features for LTV prediction
     */
    protected function extractFeatures(CoreCustomer $customer): array
    {
        // First purchase value (normalized)
        $firstPurchaseValue = $this->getFirstPurchaseValue($customer);
        $firstPurchaseScore = $this->normalizeValue($firstPurchaseValue, $this->benchmarks['avg_first_purchase'] ?? 50);

        // Early purchase frequency (first 30 days)
        $earlyFrequency = $this->getEarlyPurchaseFrequency($customer);
        $frequencyScore = min(1, $earlyFrequency / 3); // Normalize to max 3 purchases

        // Engagement score (already 0-100)
        $engagementScore = ($customer->engagement_score ?? 50) / 100;

        // Session depth (avg pages per session)
        $sessionDepth = $this->getAverageSessionDepth($customer);
        $sessionScore = min(1, $sessionDepth / 10); // Normalize to 10 pages

        // Category diversity
        $categoryDiversity = $this->getCategoryDiversity($customer);
        $diversityScore = min(1, $categoryDiversity / 5); // Normalize to 5 categories

        // Channel quality (organic/direct > paid)
        $channelScore = $this->getChannelQualityScore($customer);

        // Email engagement
        $emailScore = $this->getEmailEngagementScore($customer);

        // Referral source bonus
        $referralScore = $customer->referred_by ? 1.0 : 0.0;

        return [
            'first_purchase_value' => round($firstPurchaseScore, 3),
            'first_purchase_amount' => round($firstPurchaseValue, 2),
            'early_frequency' => round($frequencyScore, 3),
            'early_orders' => $earlyFrequency,
            'engagement_score' => round($engagementScore, 3),
            'session_depth' => round($sessionScore, 3),
            'avg_pages_per_session' => round($sessionDepth, 1),
            'category_diversity' => round($diversityScore, 3),
            'categories_purchased' => $categoryDiversity,
            'channel_quality' => round($channelScore, 3),
            'email_engagement' => round($emailScore, 3),
            'referral_source' => $referralScore,
            'customer_age_days' => $customer->first_seen_at
                ? $customer->first_seen_at->diffInDays(now())
                : 0,
            'total_orders' => $customer->total_orders,
            'current_ltv' => $customer->lifetime_value ?? 0,
        ];
    }

    /**
     * Calculate LTV prediction
     */
    protected function calculatePrediction(array $features, CoreCustomer $customer): array
    {
        // Calculate weighted feature score
        $weightedScore = 0;
        foreach ($this->featureWeights as $feature => $weight) {
            $weightedScore += ($features[$feature] ?? 0) * $weight;
        }

        // Base prediction using regression-like approach
        $currentLtv = $customer->lifetime_value ?? 0;
        $customerAge = $features['customer_age_days'];

        // Monthly velocity (current LTV / months active)
        $monthsActive = max(1, $customerAge / 30);
        $monthlyVelocity = $currentLtv / $monthsActive;

        // Adjust velocity based on weighted score (0.5 to 1.5x multiplier)
        $velocityMultiplier = 0.5 + $weightedScore;
        $adjustedVelocity = $monthlyVelocity * $velocityMultiplier;

        // Project future LTV
        $predicted12m = $currentLtv + ($adjustedVelocity * 12);
        $predicted24m = $currentLtv + ($adjustedVelocity * 24);

        // For newer customers, use benchmark-based prediction
        if ($customerAge < 90) {
            $benchmarkMultiplier = $weightedScore * 2; // 0 to 2x
            $predicted12m = max($predicted12m, ($this->benchmarks['avg_12m_ltv'] ?? 200) * $benchmarkMultiplier);
            $predicted24m = max($predicted24m, ($this->benchmarks['avg_24m_ltv'] ?? 350) * $benchmarkMultiplier);
        }

        // Calculate growth potential
        $growthPotential = $currentLtv > 0
            ? (($predicted12m - $currentLtv) / $currentLtv) * 100
            : ($predicted12m > 0 ? 100 : 0);

        // Confidence score based on data quality
        $confidenceScore = $this->calculateDataQualityScore($features, $customer);

        return [
            'predicted_ltv' => $predicted12m,
            'predicted_12m_ltv' => $predicted12m,
            'predicted_24m_ltv' => $predicted24m,
            'growth_potential' => $growthPotential,
            'weighted_score' => $weightedScore,
            'monthly_velocity' => $adjustedVelocity,
            'confidence_score' => $confidenceScore,
        ];
    }

    /**
     * Assign tier based on predicted LTV
     */
    protected function assignTier(float $predictedLtv): string
    {
        $thresholds = $this->benchmarks['tier_thresholds'] ?? [
            self::TIER_PLATINUM => 1000,
            self::TIER_GOLD => 500,
            self::TIER_SILVER => 200,
            self::TIER_BRONZE => 0,
        ];

        return match (true) {
            $predictedLtv >= $thresholds[self::TIER_PLATINUM] => self::TIER_PLATINUM,
            $predictedLtv >= $thresholds[self::TIER_GOLD] => self::TIER_GOLD,
            $predictedLtv >= $thresholds[self::TIER_SILVER] => self::TIER_SILVER,
            default => self::TIER_BRONZE,
        };
    }

    /**
     * Calculate prediction confidence
     */
    protected function calculateConfidence(array $features, CoreCustomer $customer): string
    {
        $dataPoints = 0;

        if ($features['customer_age_days'] > 90) $dataPoints++;
        if ($features['total_orders'] >= 3) $dataPoints++;
        if ($features['engagement_score'] > 0.3) $dataPoints++;
        if ($features['session_depth'] > 0.3) $dataPoints++;
        if ($customer->email_hash) $dataPoints++;

        return match (true) {
            $dataPoints >= 4 => self::CONFIDENCE_HIGH,
            $dataPoints >= 2 => self::CONFIDENCE_MEDIUM,
            default => self::CONFIDENCE_LOW,
        };
    }

    /**
     * Calculate data quality score for confidence
     */
    protected function calculateDataQualityScore(array $features, CoreCustomer $customer): float
    {
        $score = 0;

        // More history = higher confidence
        $ageScore = min(1, $features['customer_age_days'] / 180);
        $score += $ageScore * 30;

        // More orders = higher confidence
        $orderScore = min(1, $features['total_orders'] / 5);
        $score += $orderScore * 30;

        // Engagement data available
        if ($features['engagement_score'] > 0) $score += 15;

        // Email engagement data available
        if ($customer->emails_sent > 0) $score += 10;

        // Has profile data
        if ($customer->first_name || $customer->email_hash) $score += 15;

        return min(100, $score);
    }

    /**
     * Get contributing factors
     */
    protected function getContributingFactors(array $features): array
    {
        $factors = [];

        if ($features['first_purchase_value'] > 0.7) {
            $factors[] = [
                'factor' => 'High Initial Purchase Value',
                'impact' => 'positive',
                'description' => "First purchase of \${$features['first_purchase_amount']} indicates strong buying intent",
            ];
        }

        if ($features['early_frequency'] > 0.5) {
            $factors[] = [
                'factor' => 'Strong Early Engagement',
                'impact' => 'positive',
                'description' => "{$features['early_orders']} orders in first 30 days shows repeat behavior",
            ];
        }

        if ($features['engagement_score'] > 0.6) {
            $factors[] = [
                'factor' => 'High Engagement',
                'impact' => 'positive',
                'description' => 'Active browsing and interaction patterns',
            ];
        }

        if ($features['category_diversity'] > 0.4) {
            $factors[] = [
                'factor' => 'Category Explorer',
                'impact' => 'positive',
                'description' => "Purchased from {$features['categories_purchased']} different categories",
            ];
        }

        if ($features['channel_quality'] > 0.7) {
            $factors[] = [
                'factor' => 'High-Quality Acquisition',
                'impact' => 'positive',
                'description' => 'Organic or direct traffic source',
            ];
        }

        if ($features['referral_source'] > 0) {
            $factors[] = [
                'factor' => 'Referred Customer',
                'impact' => 'positive',
                'description' => 'Referred customers typically have 25% higher LTV',
            ];
        }

        // Negative factors
        if ($features['early_frequency'] < 0.3 && $features['customer_age_days'] > 60) {
            $factors[] = [
                'factor' => 'Low Early Engagement',
                'impact' => 'negative',
                'description' => 'Few repeat purchases in first 60 days',
            ];
        }

        if ($features['email_engagement'] < 0.3) {
            $factors[] = [
                'factor' => 'Low Email Engagement',
                'impact' => 'negative',
                'description' => 'Not engaging with email communications',
            ];
        }

        return $factors;
    }

    /**
     * Get growth recommendations
     */
    protected function getGrowthRecommendations(array $features, string $tier): array
    {
        $recommendations = [];

        if ($features['email_engagement'] < 0.5) {
            $recommendations[] = [
                'action' => 'email_re_engagement',
                'priority' => 'high',
                'expected_impact' => '+15% LTV',
                'description' => 'Implement email re-engagement campaign',
            ];
        }

        if ($features['category_diversity'] < 0.3) {
            $recommendations[] = [
                'action' => 'cross_sell',
                'priority' => 'high',
                'expected_impact' => '+20% LTV',
                'description' => 'Recommend products from unexplored categories',
            ];
        }

        if ($tier === self::TIER_SILVER || $tier === self::TIER_GOLD) {
            $recommendations[] = [
                'action' => 'loyalty_program',
                'priority' => 'medium',
                'expected_impact' => '+10% LTV',
                'description' => 'Enroll in loyalty program for tier upgrade',
            ];
        }

        if ($features['early_frequency'] > 0.5 && $tier !== self::TIER_PLATINUM) {
            $recommendations[] = [
                'action' => 'subscription_offer',
                'priority' => 'medium',
                'expected_impact' => '+30% LTV',
                'description' => 'Offer subscription for frequently purchased items',
            ];
        }

        if ($features['session_depth'] > 0.5 && $features['total_orders'] < 3) {
            $recommendations[] = [
                'action' => 'conversion_optimization',
                'priority' => 'high',
                'expected_impact' => '+25% LTV',
                'description' => 'High browser, low converter - offer incentive',
            ];
        }

        return $recommendations;
    }

    /**
     * Load benchmarks from historical data
     */
    protected function loadBenchmarks(CoreCustomer $customer): void
    {
        $tenantIds = $customer->tenant_ids ?? [];
        $tenantId = $tenantIds[0] ?? null;

        $cacheKey = "ltv:benchmarks:" . ($tenantId ?? 'global');

        $this->benchmarks = Cache::remember($cacheKey, 3600, function () use ($tenantId) {
            $stats = CoreCustomer::query()
                ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
                ->notMerged()
                ->notAnonymized()
                ->where('total_orders', '>=', 1)
                ->selectRaw('
                    AVG(lifetime_value) as avg_ltv,
                    AVG(total_spent) as avg_spent,
                    AVG(average_order_value) as avg_order_value,
                    PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY lifetime_value) as p95_ltv,
                    PERCENTILE_CONT(0.80) WITHIN GROUP (ORDER BY lifetime_value) as p80_ltv,
                    PERCENTILE_CONT(0.50) WITHIN GROUP (ORDER BY lifetime_value) as p50_ltv
                ')
                ->first();

            return [
                'avg_ltv' => $stats->avg_ltv ?? 100,
                'avg_first_purchase' => $stats->avg_order_value ?? 50,
                'avg_12m_ltv' => ($stats->avg_ltv ?? 100) * 1.5,
                'avg_24m_ltv' => ($stats->avg_ltv ?? 100) * 2.5,
                'tier_thresholds' => [
                    self::TIER_PLATINUM => $stats->p95_ltv ?? 1000,
                    self::TIER_GOLD => $stats->p80_ltv ?? 500,
                    self::TIER_SILVER => $stats->p50_ltv ?? 200,
                    self::TIER_BRONZE => 0,
                ],
            ];
        });
    }

    // Helper methods

    protected function getFirstPurchaseValue(CoreCustomer $customer): float
    {
        $firstPurchase = CoreCustomerEvent::where('core_customer_id', $customer->id)
            ->where('event_type', 'purchase')
            ->orderBy('created_at')
            ->first();

        return $firstPurchase?->conversion_value ?? 0;
    }

    protected function getEarlyPurchaseFrequency(CoreCustomer $customer): int
    {
        if (!$customer->first_seen_at) {
            return 0;
        }

        return CoreCustomerEvent::where('core_customer_id', $customer->id)
            ->where('event_type', 'purchase')
            ->where('created_at', '<=', $customer->first_seen_at->addDays(30))
            ->count();
    }

    protected function getAverageSessionDepth(CoreCustomer $customer): float
    {
        $avgPageViews = CoreSession::where('core_customer_id', $customer->id)
            ->avg('page_views');

        return $avgPageViews ?? 0;
    }

    protected function getCategoryDiversity(CoreCustomer $customer): int
    {
        return CoreCustomerEvent::where('core_customer_id', $customer->id)
            ->where('event_type', 'purchase')
            ->whereNotNull('category')
            ->distinct('category')
            ->count('category');
    }

    protected function getChannelQualityScore(CoreCustomer $customer): float
    {
        $firstSession = CoreSession::where('core_customer_id', $customer->id)
            ->orderBy('started_at')
            ->first();

        if (!$firstSession) {
            return 0.5;
        }

        $source = strtolower($firstSession->utm_source ?? 'direct');

        return match (true) {
            in_array($source, ['direct', '']) => 1.0,
            in_array($source, ['google', 'organic']) => 0.9,
            in_array($source, ['email', 'newsletter']) => 0.8,
            str_contains($source, 'referral') => 0.85,
            in_array($source, ['facebook', 'instagram', 'tiktok']) => 0.6,
            str_contains($source, 'paid') => 0.5,
            default => 0.5,
        };
    }

    protected function getEmailEngagementScore(CoreCustomer $customer): float
    {
        if ($customer->email_unsubscribed_at) {
            return 0;
        }

        $openRate = $customer->email_open_rate ?? 0;
        $clickRate = $customer->email_click_rate ?? 0;

        return (($openRate * 0.6) + ($clickRate * 0.4)) / 100;
    }

    protected function normalizeValue(float $value, float $benchmark): float
    {
        if ($benchmark <= 0) {
            return 0.5;
        }
        return min(1, $value / ($benchmark * 2));
    }

    protected function getCohortAgeMonths(string $cohort, string $type): int
    {
        try {
            if ($type === 'month') {
                $cohortDate = Carbon::createFromFormat('Y-m', $cohort);
            } else {
                [$year, $week] = explode('-', str_replace('W', '', $cohort));
                $cohortDate = Carbon::now()->setISODate($year, $week);
            }
            return $cohortDate->diffInMonths(now());
        } catch (\Exception $e) {
            return 0;
        }
    }

    protected function projectLtvByAge(float $currentLtv, int $ageMonths): array
    {
        // Simple growth projection based on typical LTV curves
        $monthlyGrowth = $ageMonths > 0 ? $currentLtv / $ageMonths : $currentLtv;

        // Growth tends to slow over time (logarithmic decay)
        $decay12m = 0.8;
        $decay24m = 0.6;

        $monthsTo12 = max(0, 12 - $ageMonths);
        $monthsTo24 = max(0, 24 - $ageMonths);

        return [
            '12m' => $currentLtv + ($monthlyGrowth * $monthsTo12 * $decay12m),
            '24m' => $currentLtv + ($monthlyGrowth * $monthsTo24 * $decay24m),
        ];
    }

    protected function getTierLabel(string $tier): string
    {
        return match ($tier) {
            self::TIER_PLATINUM => 'Platinum - VIP Customer',
            self::TIER_GOLD => 'Gold - High Value',
            self::TIER_SILVER => 'Silver - Growing',
            self::TIER_BRONZE => 'Bronze - Developing',
            default => 'Unknown',
        };
    }
}
