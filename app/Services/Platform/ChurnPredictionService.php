<?php

namespace App\Services\Platform;

use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ChurnPredictionService
{
    // Churn risk levels
    const RISK_CRITICAL = 'critical';   // 80-100% likely to churn
    const RISK_HIGH = 'high';           // 60-80% likely to churn
    const RISK_MEDIUM = 'medium';       // 40-60% likely to churn
    const RISK_LOW = 'low';             // 20-40% likely to churn
    const RISK_MINIMAL = 'minimal';     // 0-20% likely to churn

    // Feature weights for the prediction model
    protected array $featureWeights = [
        'recency_score' => 0.25,        // How recently they engaged
        'frequency_decay' => 0.20,       // Declining purchase frequency
        'engagement_decline' => 0.15,    // Declining engagement
        'support_issues' => 0.10,        // Support ticket correlation
        'email_disengagement' => 0.10,   // Email unsubscribes/no opens
        'session_decline' => 0.10,       // Declining session activity
        'value_decline' => 0.10,         // Declining order values
    ];

    // Configurable thresholds
    protected int $churned_days_threshold = 90;
    protected int $analysis_window_days = 90;

    public function setChurnedDaysThreshold(int $days): self
    {
        $this->churned_days_threshold = $days;
        return $this;
    }

    /**
     * Calculate churn probability for a single customer
     */
    public function predictChurn(CoreCustomer $customer): array
    {
        $features = $this->extractFeatures($customer);
        $probability = $this->calculateChurnProbability($features);
        $riskLevel = $this->getRiskLevel($probability);

        return [
            'customer_id' => $customer->id,
            'customer_uuid' => $customer->uuid,
            'churn_probability' => round($probability * 100, 1),
            'risk_level' => $riskLevel,
            'risk_label' => $this->getRiskLabel($riskLevel),
            'features' => $features,
            'contributing_factors' => $this->getContributingFactors($features),
            'recommendations' => $this->getRetentionRecommendations($features, $riskLevel),
            'predicted_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Batch predict churn for multiple customers
     */
    public function batchPredict(Collection $customers): Collection
    {
        return $customers->map(fn($customer) => $this->predictChurn($customer));
    }

    /**
     * Get at-risk customers
     */
    public function getAtRiskCustomers(
        string $minRiskLevel = self::RISK_MEDIUM,
        int $limit = 100,
        ?int $tenantId = null
    ): Collection {
        $customers = CoreCustomer::query()
            ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
            ->notMerged()
            ->notAnonymized()
            ->purchasers()
            ->orderByDesc('total_spent')
            ->limit($limit * 3) // Get more to filter
            ->get();

        $riskLevels = [
            self::RISK_CRITICAL => 5,
            self::RISK_HIGH => 4,
            self::RISK_MEDIUM => 3,
            self::RISK_LOW => 2,
            self::RISK_MINIMAL => 1,
        ];

        $minLevel = $riskLevels[$minRiskLevel] ?? 3;

        return $customers
            ->map(fn($customer) => $this->predictChurn($customer))
            ->filter(fn($prediction) => ($riskLevels[$prediction['risk_level']] ?? 0) >= $minLevel)
            ->sortByDesc('churn_probability')
            ->take($limit)
            ->values();
    }

    /**
     * Calculate churn statistics for segments
     */
    public function getChurnStatsBySegment(?int $tenantId = null): array
    {
        $segments = CoreCustomer::query()
            ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
            ->notMerged()
            ->notAnonymized()
            ->purchasers()
            ->whereNotNull('customer_segment')
            ->select('customer_segment')
            ->distinct()
            ->pluck('customer_segment');

        $stats = [];

        foreach ($segments as $segment) {
            $customers = CoreCustomer::query()
                ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
                ->where('customer_segment', $segment)
                ->notMerged()
                ->notAnonymized()
                ->limit(100)
                ->get();

            $predictions = $this->batchPredict($customers);

            $avgProbability = $predictions->avg('churn_probability');
            $riskDistribution = $predictions->groupBy('risk_level')->map->count();

            $stats[$segment] = [
                'segment' => $segment,
                'customer_count' => $customers->count(),
                'avg_churn_probability' => round($avgProbability, 1),
                'risk_distribution' => [
                    'critical' => $riskDistribution->get(self::RISK_CRITICAL, 0),
                    'high' => $riskDistribution->get(self::RISK_HIGH, 0),
                    'medium' => $riskDistribution->get(self::RISK_MEDIUM, 0),
                    'low' => $riskDistribution->get(self::RISK_LOW, 0),
                    'minimal' => $riskDistribution->get(self::RISK_MINIMAL, 0),
                ],
                'total_value_at_risk' => $customers
                    ->filter(fn($c) => $this->predictChurn($c)['risk_level'] !== self::RISK_MINIMAL)
                    ->sum('lifetime_value'),
            ];
        }

        return $stats;
    }

    /**
     * Get cohort churn analysis
     */
    public function getCohortChurnAnalysis(
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
            ->select($cohortField)
            ->distinct()
            ->orderByDesc($cohortField)
            ->limit($cohortsBack)
            ->pluck($cohortField);

        $analysis = [];

        foreach ($cohorts as $cohort) {
            $customers = CoreCustomer::query()
                ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
                ->where($cohortField, $cohort)
                ->notMerged()
                ->notAnonymized()
                ->get();

            $totalCustomers = $customers->count();
            $activeCustomers = $customers->filter(fn($c) =>
                $c->last_seen_at && $c->last_seen_at->diffInDays(now()) < $this->churned_days_threshold
            )->count();

            $churnedCustomers = $totalCustomers - $activeCustomers;
            $churnRate = $totalCustomers > 0 ? ($churnedCustomers / $totalCustomers) * 100 : 0;

            $predictions = $this->batchPredict($customers->take(50));
            $avgChurnProbability = $predictions->avg('churn_probability');

            $analysis[] = [
                'cohort' => $cohort,
                'total_customers' => $totalCustomers,
                'active_customers' => $activeCustomers,
                'churned_customers' => $churnedCustomers,
                'actual_churn_rate' => round($churnRate, 1),
                'predicted_churn_probability' => round($avgChurnProbability, 1),
                'revenue_at_risk' => $customers
                    ->filter(fn($c) => $c->last_seen_at && $c->last_seen_at->diffInDays(now()) > 30)
                    ->sum('lifetime_value'),
            ];
        }

        return $analysis;
    }

    /**
     * Get churn prevention dashboard data
     */
    public function getChurnDashboard(?int $tenantId = null): array
    {
        $totalCustomers = CoreCustomer::query()
            ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
            ->notMerged()
            ->notAnonymized()
            ->purchasers()
            ->count();

        $atRiskCustomers = $this->getAtRiskCustomers(self::RISK_HIGH, 100, $tenantId);

        $totalValueAtRisk = $atRiskCustomers->sum(function ($prediction) use ($tenantId) {
            $customer = CoreCustomer::find($prediction['customer_id']);
            return $customer ? $customer->lifetime_value : 0;
        });

        $riskDistribution = $atRiskCustomers->groupBy('risk_level')->map->count();

        // Calculate 30-day trend
        $thirtyDaysAgo = now()->subDays(30);
        $recentlyChurned = CoreCustomer::query()
            ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
            ->notMerged()
            ->notAnonymized()
            ->purchasers()
            ->where('last_seen_at', '<', $thirtyDaysAgo)
            ->where('last_seen_at', '>=', now()->subDays(60))
            ->count();

        return [
            'summary' => [
                'total_customers' => $totalCustomers,
                'at_risk_customers' => $atRiskCustomers->count(),
                'total_value_at_risk' => round($totalValueAtRisk, 2),
                'recently_churned' => $recentlyChurned,
                'churn_rate_30d' => $totalCustomers > 0
                    ? round(($recentlyChurned / $totalCustomers) * 100, 1)
                    : 0,
            ],
            'risk_distribution' => [
                'critical' => $riskDistribution->get(self::RISK_CRITICAL, 0),
                'high' => $riskDistribution->get(self::RISK_HIGH, 0),
                'medium' => $riskDistribution->get(self::RISK_MEDIUM, 0),
            ],
            'top_at_risk' => $atRiskCustomers->take(10)->toArray(),
            'segment_stats' => $this->getChurnStatsBySegment($tenantId),
        ];
    }

    /**
     * Extract features for churn prediction
     */
    protected function extractFeatures(CoreCustomer $customer): array
    {
        $now = now();

        // Recency score (days since last activity, normalized)
        $daysSinceLastSeen = $customer->last_seen_at
            ? $customer->last_seen_at->diffInDays($now)
            : 365;
        $recencyScore = min(1, $daysSinceLastSeen / $this->churned_days_threshold);

        // Frequency decay (comparing recent vs historical frequency)
        $frequencyDecay = $this->calculateFrequencyDecay($customer);

        // Engagement decline
        $engagementDecline = $this->calculateEngagementDecline($customer);

        // Session decline
        $sessionDecline = $this->calculateSessionDecline($customer);

        // Value decline
        $valueDecline = $this->calculateValueDecline($customer);

        // Email disengagement
        $emailDisengagement = $this->calculateEmailDisengagement($customer);

        // Support issues (placeholder - would integrate with support system)
        $supportIssues = 0;

        return [
            'recency_score' => round($recencyScore, 3),
            'frequency_decay' => round($frequencyDecay, 3),
            'engagement_decline' => round($engagementDecline, 3),
            'session_decline' => round($sessionDecline, 3),
            'value_decline' => round($valueDecline, 3),
            'email_disengagement' => round($emailDisengagement, 3),
            'support_issues' => $supportIssues,
            'days_since_last_seen' => $daysSinceLastSeen,
            'days_since_last_purchase' => $customer->last_purchase_at
                ? $customer->last_purchase_at->diffInDays($now)
                : 365,
            'total_orders' => $customer->total_orders,
            'lifetime_value' => $customer->lifetime_value ?? 0,
            'rfm_score' => $customer->rfm_score ?? 0,
            'engagement_score' => $customer->engagement_score ?? 0,
        ];
    }

    /**
     * Calculate frequency decay
     */
    protected function calculateFrequencyDecay(CoreCustomer $customer): float
    {
        if ($customer->total_orders < 2) {
            return 0.5; // Neutral for new customers
        }

        // Compare purchase intervals
        $recentPurchases = CoreCustomerEvent::where('core_customer_id', $customer->id)
            ->where('event_type', 'purchase')
            ->where('created_at', '>=', now()->subDays(180))
            ->orderByDesc('created_at')
            ->limit(5)
            ->pluck('created_at');

        if ($recentPurchases->count() < 2) {
            return 0.7; // Higher risk if few recent purchases
        }

        // Calculate average interval between recent purchases
        $intervals = [];
        for ($i = 0; $i < $recentPurchases->count() - 1; $i++) {
            $intervals[] = $recentPurchases[$i]->diffInDays($recentPurchases[$i + 1]);
        }

        $avgRecentInterval = count($intervals) > 0 ? array_sum($intervals) / count($intervals) : 90;

        // Compare to expected frequency
        $expectedInterval = $customer->purchase_frequency_days ?? 90;

        if ($expectedInterval <= 0) {
            return 0.5;
        }

        $intervalIncrease = ($avgRecentInterval - $expectedInterval) / $expectedInterval;
        return min(1, max(0, $intervalIncrease));
    }

    /**
     * Calculate engagement decline
     */
    protected function calculateEngagementDecline(CoreCustomer $customer): float
    {
        $currentEngagement = $customer->engagement_score ?? 50;

        // Get historical engagement (last 90 days of sessions)
        $recentSessions = CoreSession::where('core_customer_id', $customer->id)
            ->where('started_at', '>=', now()->subDays(90))
            ->count();

        $olderSessions = CoreSession::where('core_customer_id', $customer->id)
            ->where('started_at', '>=', now()->subDays(180))
            ->where('started_at', '<', now()->subDays(90))
            ->count();

        if ($olderSessions === 0) {
            return $recentSessions === 0 ? 0.5 : 0;
        }

        $sessionDeclineRatio = 1 - ($recentSessions / max(1, $olderSessions));
        return min(1, max(0, $sessionDeclineRatio));
    }

    /**
     * Calculate session decline
     */
    protected function calculateSessionDecline(CoreCustomer $customer): float
    {
        $last30Days = CoreSession::where('core_customer_id', $customer->id)
            ->where('started_at', '>=', now()->subDays(30))
            ->count();

        $previous30Days = CoreSession::where('core_customer_id', $customer->id)
            ->where('started_at', '>=', now()->subDays(60))
            ->where('started_at', '<', now()->subDays(30))
            ->count();

        if ($previous30Days === 0) {
            return $last30Days === 0 ? 0.7 : 0;
        }

        $decline = 1 - ($last30Days / $previous30Days);
        return min(1, max(0, $decline));
    }

    /**
     * Calculate value decline
     */
    protected function calculateValueDecline(CoreCustomer $customer): float
    {
        $recentOrders = CoreCustomerEvent::where('core_customer_id', $customer->id)
            ->where('event_type', 'purchase')
            ->where('created_at', '>=', now()->subDays(180))
            ->orderByDesc('created_at')
            ->limit(3)
            ->avg('conversion_value');

        $avgOrderValue = $customer->average_order_value ?? 0;

        if ($avgOrderValue <= 0) {
            return 0.5;
        }

        $valueChange = ($avgOrderValue - ($recentOrders ?? 0)) / $avgOrderValue;
        return min(1, max(0, $valueChange));
    }

    /**
     * Calculate email disengagement
     */
    protected function calculateEmailDisengagement(CoreCustomer $customer): float
    {
        // Check for unsubscribe
        if ($customer->email_unsubscribed_at) {
            return 1.0;
        }

        // Check email engagement rate
        $emailOpenRate = $customer->email_open_rate ?? 0;
        $emailClickRate = $customer->email_click_rate ?? 0;

        // Low engagement indicates potential churn
        if ($customer->emails_sent > 10) {
            $engagementScore = ($emailOpenRate * 0.6) + ($emailClickRate * 0.4);
            return min(1, max(0, 1 - ($engagementScore / 100)));
        }

        return 0.3; // Neutral for new subscribers
    }

    /**
     * Calculate churn probability from features
     */
    protected function calculateChurnProbability(array $features): float
    {
        $weightedSum = 0;

        $weightedSum += $features['recency_score'] * $this->featureWeights['recency_score'];
        $weightedSum += $features['frequency_decay'] * $this->featureWeights['frequency_decay'];
        $weightedSum += $features['engagement_decline'] * $this->featureWeights['engagement_decline'];
        $weightedSum += $features['session_decline'] * $this->featureWeights['session_decline'];
        $weightedSum += $features['value_decline'] * $this->featureWeights['value_decline'];
        $weightedSum += $features['email_disengagement'] * $this->featureWeights['email_disengagement'];
        $weightedSum += $features['support_issues'] * $this->featureWeights['support_issues'];

        // Apply sigmoid for probability
        $probability = 1 / (1 + exp(-5 * ($weightedSum - 0.5)));

        // Adjust for customer lifetime value (more valuable = lower churn threshold matters more)
        $ltvFactor = min(1, ($features['lifetime_value'] ?? 0) / 1000);
        $probability = $probability * (0.8 + (0.2 * $ltvFactor));

        return min(1, max(0, $probability));
    }

    /**
     * Get risk level from probability
     */
    protected function getRiskLevel(float $probability): string
    {
        return match (true) {
            $probability >= 0.8 => self::RISK_CRITICAL,
            $probability >= 0.6 => self::RISK_HIGH,
            $probability >= 0.4 => self::RISK_MEDIUM,
            $probability >= 0.2 => self::RISK_LOW,
            default => self::RISK_MINIMAL,
        };
    }

    /**
     * Get human-readable risk label
     */
    protected function getRiskLabel(string $riskLevel): string
    {
        return match ($riskLevel) {
            self::RISK_CRITICAL => 'Critical - Immediate Action Required',
            self::RISK_HIGH => 'High Risk - Needs Attention',
            self::RISK_MEDIUM => 'Medium Risk - Monitor',
            self::RISK_LOW => 'Low Risk',
            self::RISK_MINIMAL => 'Minimal Risk',
            default => 'Unknown',
        };
    }

    /**
     * Get contributing factors to churn risk
     */
    protected function getContributingFactors(array $features): array
    {
        $factors = [];

        if ($features['recency_score'] > 0.6) {
            $factors[] = [
                'factor' => 'Inactivity',
                'severity' => $features['recency_score'] > 0.8 ? 'high' : 'medium',
                'description' => "No activity for {$features['days_since_last_seen']} days",
            ];
        }

        if ($features['frequency_decay'] > 0.5) {
            $factors[] = [
                'factor' => 'Declining Purchase Frequency',
                'severity' => $features['frequency_decay'] > 0.7 ? 'high' : 'medium',
                'description' => 'Time between purchases is increasing',
            ];
        }

        if ($features['engagement_decline'] > 0.5) {
            $factors[] = [
                'factor' => 'Declining Engagement',
                'severity' => $features['engagement_decline'] > 0.7 ? 'high' : 'medium',
                'description' => 'Fewer sessions and interactions',
            ];
        }

        if ($features['email_disengagement'] > 0.6) {
            $factors[] = [
                'factor' => 'Email Disengagement',
                'severity' => $features['email_disengagement'] > 0.8 ? 'high' : 'medium',
                'description' => 'Not engaging with email communications',
            ];
        }

        if ($features['value_decline'] > 0.4) {
            $factors[] = [
                'factor' => 'Declining Order Value',
                'severity' => 'medium',
                'description' => 'Recent orders are smaller than average',
            ];
        }

        return $factors;
    }

    /**
     * Get retention recommendations based on risk factors
     */
    protected function getRetentionRecommendations(array $features, string $riskLevel): array
    {
        $recommendations = [];

        if ($riskLevel === self::RISK_CRITICAL || $riskLevel === self::RISK_HIGH) {
            $recommendations[] = [
                'action' => 'personal_outreach',
                'priority' => 'high',
                'description' => 'Reach out with a personal message or call',
            ];
        }

        if ($features['recency_score'] > 0.5) {
            $recommendations[] = [
                'action' => 'win_back_campaign',
                'priority' => 'high',
                'description' => 'Send win-back email with special offer',
            ];
        }

        if ($features['value_decline'] > 0.3) {
            $recommendations[] = [
                'action' => 'loyalty_reward',
                'priority' => 'medium',
                'description' => 'Offer loyalty points or exclusive discount',
            ];
        }

        if ($features['email_disengagement'] > 0.5) {
            $recommendations[] = [
                'action' => 'preference_update',
                'priority' => 'medium',
                'description' => 'Request email preference update',
            ];
            $recommendations[] = [
                'action' => 'sms_outreach',
                'priority' => 'medium',
                'description' => 'Try alternative channel (SMS)',
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'action' => 'maintain_engagement',
                'priority' => 'low',
                'description' => 'Continue regular engagement',
            ];
        }

        return $recommendations;
    }

    /**
     * Update customer churn risk scores
     */
    public function updateCustomerChurnScores(int $batchSize = 500, ?int $tenantId = null): array
    {
        $updated = 0;
        $errors = 0;

        CoreCustomer::query()
            ->when($tenantId, fn($q) => $q->fromTenant($tenantId))
            ->notMerged()
            ->notAnonymized()
            ->purchasers()
            ->chunk($batchSize, function ($customers) use (&$updated, &$errors) {
                foreach ($customers as $customer) {
                    try {
                        $prediction = $this->predictChurn($customer);
                        $customer->update([
                            'churn_risk_score' => $prediction['churn_probability'],
                        ]);
                        $updated++;
                    } catch (\Exception $e) {
                        $errors++;
                    }
                }
            });

        return [
            'updated' => $updated,
            'errors' => $errors,
        ];
    }
}
