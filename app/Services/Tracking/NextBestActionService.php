<?php

namespace App\Services\Tracking;

use App\Models\CoreCustomer;
use App\Models\FeatureStore\FsPersonDaily;
use App\Models\FeatureStore\FsPersonEmailMetrics;
use App\Models\FeatureStore\FsPersonChannelAffinity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NextBestActionService
{
    protected int $tenantId;
    protected int $personId;
    protected ?CoreCustomer $person = null;

    // Action types with base priorities
    protected const ACTIONS = [
        'purchase_nudge' => ['priority' => 90, 'category' => 'conversion'],
        'cart_recovery' => ['priority' => 95, 'category' => 'conversion'],
        'event_reminder' => ['priority' => 85, 'category' => 'engagement'],
        'personalized_recs' => ['priority' => 70, 'category' => 'engagement'],
        'win_back' => ['priority' => 80, 'category' => 're-engagement'],
        'loyalty_reward' => ['priority' => 75, 'category' => 'retention'],
        'upsell_offer' => ['priority' => 65, 'category' => 'growth'],
        'feedback_request' => ['priority' => 50, 'category' => 'relationship'],
        'newsletter_signup' => ['priority' => 40, 'category' => 'acquisition'],
        'referral_ask' => ['priority' => 55, 'category' => 'growth'],
        'reactivation' => ['priority' => 85, 'category' => 're-engagement'],
        'vip_upgrade' => ['priority' => 60, 'category' => 'growth'],
    ];

    // Channel effectiveness weights
    protected const CHANNEL_WEIGHTS = [
        'email' => 1.0,
        'sms' => 0.9,
        'push' => 0.8,
        'in_app' => 0.7,
    ];

    public function __construct(int $tenantId, int $personId)
    {
        $this->tenantId = $tenantId;
        $this->personId = $personId;
    }

    public static function for(int $tenantId, int $personId): self
    {
        return new self($tenantId, $personId);
    }

    /**
     * Get the next best action for a person
     */
    public function getNextBestAction(): array
    {
        $cacheKey = "nba:{$this->tenantId}:{$this->personId}";

        return Cache::remember($cacheKey, 1800, function () {
            $person = $this->getPerson();
            $context = $this->buildContext($person);
            $actions = $this->evaluateActions($context);

            // Get best action
            $bestAction = $actions[0] ?? null;

            if (!$bestAction) {
                return [
                    'action' => null,
                    'message' => 'No action recommended at this time',
                ];
            }

            // Determine best channel
            $channel = $this->determineBestChannel($bestAction['action'], $context);

            // Get optimal timing
            $timing = $this->getOptimalTiming($context);

            return [
                'person_id' => $this->personId,
                'action' => $bestAction['action'],
                'category' => self::ACTIONS[$bestAction['action']]['category'],
                'score' => $bestAction['score'],
                'channel' => $channel,
                'timing' => $timing,
                'context' => $bestAction['context'],
                'alternative_actions' => array_slice($actions, 1, 3),
                'generated_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Get multiple action recommendations
     */
    public function getActionQueue(int $limit = 5): array
    {
        $person = $this->getPerson();
        $context = $this->buildContext($person);
        $actions = $this->evaluateActions($context);

        return [
            'person_id' => $this->personId,
            'actions' => array_slice($actions, 0, $limit),
            'context_summary' => [
                'lifecycle_stage' => $context['lifecycle_stage'],
                'engagement_level' => $context['engagement_level'],
                'churn_risk' => $context['churn_risk'],
                'ltv_tier' => $context['ltv_tier'],
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Build context for action evaluation
     */
    protected function buildContext(?CoreCustomer $person): array
    {
        if (!$person) {
            return $this->getDefaultContext();
        }

        // Get recent activity
        $recentActivity = $this->getRecentActivity();

        // Get email metrics
        $emailMetrics = FsPersonEmailMetrics::where('tenant_id', $this->tenantId)
            ->where('person_id', $this->personId)
            ->first();

        // Get channel preferences
        $channelAffinities = FsPersonChannelAffinity::where('tenant_id', $this->tenantId)
            ->where('person_id', $this->personId)
            ->orderByDesc('conversion_rate')
            ->get();

        // Calculate lifecycle stage
        $lifecycleStage = $this->determineLifecycleStage($person, $recentActivity);

        return [
            // Identity
            'person_id' => $this->personId,
            'has_email' => !empty($person->email_hash),
            'has_phone' => !empty($person->phone_hash),

            // Lifecycle
            'lifecycle_stage' => $lifecycleStage,
            'days_since_first_purchase' => $person->first_purchase_at
                ? now()->diffInDays($person->first_purchase_at)
                : null,
            'days_since_last_purchase' => $person->last_purchase_at
                ? now()->diffInDays($person->last_purchase_at)
                : null,
            'days_since_last_visit' => $person->last_seen_at
                ? now()->diffInDays($person->last_seen_at)
                : null,

            // Engagement
            'engagement_level' => $this->calculateEngagementLevel($person, $recentActivity),
            'engagement_score' => $person->engagement_score ?? 0,
            'total_visits' => $person->total_visits ?? 0,
            'total_pageviews' => $person->total_pageviews ?? 0,

            // Purchase behavior
            'total_purchases' => $person->total_purchases ?? 0,
            'total_spent' => $person->total_spent ?? 0,
            'avg_order_value' => $person->avg_order_value ?? 0,
            'ltv' => $person->ltv ?? 0,

            // Risk & Value
            'churn_risk' => $person->churn_risk ?? 'unknown',
            'ltv_tier' => $this->getLtvTier($person->predicted_ltv ?? 0),
            'purchase_likelihood' => $person->purchase_likelihood ?? 0,

            // RFM
            'rfm_segment' => $person->rfm_segment ?? 'unknown',
            'recency_score' => $person->recency_score ?? 0,
            'frequency_score' => $person->frequency_score ?? 0,
            'monetary_score' => $person->monetary_score ?? 0,

            // Email
            'email_open_rate' => $emailMetrics->open_rate_30d ?? 0,
            'email_click_rate' => $emailMetrics->click_rate_30d ?? 0,
            'email_fatigue' => $emailMetrics->fatigue_score ?? 0,
            'emails_sent_recently' => $emailMetrics->sent_last_7_days ?? 0,

            // Channel
            'preferred_channels' => $channelAffinities->take(3)->pluck('channel')->toArray(),
            'channel_affinities' => $channelAffinities->pluck('conversion_rate', 'channel')->toArray(),

            // Recent activity
            'has_active_cart' => $recentActivity['has_cart'],
            'cart_value' => $recentActivity['cart_value'],
            'cart_age_hours' => $recentActivity['cart_age_hours'],
            'recent_event_views' => $recentActivity['event_views'],
            'upcoming_events_purchased' => $recentActivity['upcoming_events'],

            // Consent
            'marketing_consent' => $person->marketing_consent ?? false,
            'has_unsubscribed' => $person->is_unsubscribed ?? false,
        ];
    }

    /**
     * Evaluate all possible actions
     */
    protected function evaluateActions(array $context): array
    {
        $scoredActions = [];

        foreach (self::ACTIONS as $action => $config) {
            $score = $this->scoreAction($action, $context);

            if ($score > 0) {
                $scoredActions[] = [
                    'action' => $action,
                    'score' => round($score, 3),
                    'category' => $config['category'],
                    'context' => $this->getActionContext($action, $context),
                ];
            }
        }

        // Sort by score descending
        usort($scoredActions, fn($a, $b) => $b['score'] <=> $a['score']);

        return $scoredActions;
    }

    /**
     * Score a specific action
     */
    protected function scoreAction(string $action, array $context): float
    {
        $baseScore = self::ACTIONS[$action]['priority'] / 100;

        // Apply context-based modifiers
        $score = match ($action) {
            'cart_recovery' => $this->scoreCartRecovery($context, $baseScore),
            'purchase_nudge' => $this->scorePurchaseNudge($context, $baseScore),
            'event_reminder' => $this->scoreEventReminder($context, $baseScore),
            'win_back' => $this->scoreWinBack($context, $baseScore),
            'reactivation' => $this->scoreReactivation($context, $baseScore),
            'loyalty_reward' => $this->scoreLoyaltyReward($context, $baseScore),
            'upsell_offer' => $this->scoreUpsell($context, $baseScore),
            'personalized_recs' => $this->scorePersonalizedRecs($context, $baseScore),
            'feedback_request' => $this->scoreFeedbackRequest($context, $baseScore),
            'referral_ask' => $this->scoreReferralAsk($context, $baseScore),
            'newsletter_signup' => $this->scoreNewsletterSignup($context, $baseScore),
            'vip_upgrade' => $this->scoreVipUpgrade($context, $baseScore),
            default => $baseScore * 0.5,
        };

        // Apply email fatigue penalty
        if ($context['email_fatigue'] > 70) {
            $score *= 0.5;
        } elseif ($context['email_fatigue'] > 50) {
            $score *= 0.75;
        }

        // Apply consent check
        if (!$context['marketing_consent'] || $context['has_unsubscribed']) {
            // Only allow in-app actions
            if (!in_array($action, ['personalized_recs', 'event_reminder'])) {
                $score = 0;
            }
        }

        return max(0, min(1, $score));
    }

    protected function scoreCartRecovery(array $context, float $baseScore): float
    {
        if (!$context['has_active_cart']) {
            return 0;
        }

        $score = $baseScore;

        // Boost for high cart value
        if ($context['cart_value'] > 100) {
            $score *= 1.3;
        }

        // Optimal timing: 1-24 hours after abandonment
        if ($context['cart_age_hours'] >= 1 && $context['cart_age_hours'] <= 24) {
            $score *= 1.2;
        } elseif ($context['cart_age_hours'] > 72) {
            $score *= 0.5; // Cart is stale
        }

        return $score;
    }

    protected function scorePurchaseNudge(array $context, float $baseScore): float
    {
        if ($context['has_active_cart']) {
            return 0; // Use cart recovery instead
        }

        if ($context['recent_event_views'] == 0) {
            return 0; // No interest shown
        }

        $score = $baseScore;

        // Boost for high purchase likelihood
        $score *= (1 + $context['purchase_likelihood']);

        // Boost for recent browsing
        if ($context['days_since_last_visit'] !== null && $context['days_since_last_visit'] <= 3) {
            $score *= 1.2;
        }

        return $score;
    }

    protected function scoreEventReminder(array $context, float $baseScore): float
    {
        if ($context['upcoming_events_purchased'] == 0) {
            return 0;
        }

        return $baseScore * 1.1;
    }

    protected function scoreWinBack(array $context, float $baseScore): float
    {
        if ($context['churn_risk'] === 'unknown' || $context['churn_risk'] === 'minimal') {
            return 0;
        }

        $score = $baseScore;

        // Higher score for higher churn risk
        $riskMultiplier = match ($context['churn_risk']) {
            'critical' => 1.5,
            'high' => 1.3,
            'medium' => 1.1,
            default => 0.8,
        };

        $score *= $riskMultiplier;

        // Boost for high-value customers
        if ($context['ltv_tier'] === 'platinum' || $context['ltv_tier'] === 'gold') {
            $score *= 1.3;
        }

        return $score;
    }

    protected function scoreReactivation(array $context, float $baseScore): float
    {
        $daysSinceVisit = $context['days_since_last_visit'];

        if ($daysSinceVisit === null || $daysSinceVisit < 30) {
            return 0;
        }

        $score = $baseScore;

        // Optimal window: 30-90 days inactive
        if ($daysSinceVisit >= 30 && $daysSinceVisit <= 90) {
            $score *= 1.2;
        } elseif ($daysSinceVisit > 180) {
            $score *= 0.5; // Too long gone
        }

        return $score;
    }

    protected function scoreLoyaltyReward(array $context, float $baseScore): float
    {
        if ($context['total_purchases'] < 2) {
            return 0;
        }

        $score = $baseScore;

        // Boost for loyal customers
        if (in_array($context['rfm_segment'], ['champions', 'loyal_customers'])) {
            $score *= 1.4;
        }

        return $score;
    }

    protected function scoreUpsell(array $context, float $baseScore): float
    {
        if ($context['total_purchases'] == 0) {
            return 0;
        }

        $score = $baseScore;

        // Boost for high engagement, high value customers
        if ($context['engagement_level'] === 'high' && $context['monetary_score'] >= 4) {
            $score *= 1.3;
        }

        return $score;
    }

    protected function scorePersonalizedRecs(array $context, float $baseScore): float
    {
        // Always relevant, but boost for engaged users
        $score = $baseScore;

        if ($context['engagement_level'] === 'high') {
            $score *= 1.2;
        }

        if ($context['days_since_last_visit'] !== null && $context['days_since_last_visit'] <= 7) {
            $score *= 1.1;
        }

        return $score;
    }

    protected function scoreFeedbackRequest(array $context, float $baseScore): float
    {
        // Only for recent purchasers
        $daysSincePurchase = $context['days_since_last_purchase'];

        if ($daysSincePurchase === null || $daysSincePurchase < 1 || $daysSincePurchase > 14) {
            return 0;
        }

        return $baseScore;
    }

    protected function scoreReferralAsk(array $context, float $baseScore): float
    {
        // Only for satisfied, engaged customers
        if ($context['engagement_level'] !== 'high' || $context['total_purchases'] < 2) {
            return 0;
        }

        $score = $baseScore;

        if (in_array($context['rfm_segment'], ['champions', 'loyal_customers'])) {
            $score *= 1.3;
        }

        return $score;
    }

    protected function scoreNewsletterSignup(array $context, float $baseScore): float
    {
        if ($context['has_email'] && $context['marketing_consent']) {
            return 0; // Already signed up
        }

        if ($context['total_visits'] < 2) {
            return 0; // Too early
        }

        return $baseScore;
    }

    protected function scoreVipUpgrade(array $context, float $baseScore): float
    {
        if ($context['total_purchases'] < 3 || $context['monetary_score'] < 3) {
            return 0;
        }

        $score = $baseScore;

        if ($context['ltv_tier'] === 'gold') {
            $score *= 1.3; // Good candidate for platinum
        }

        return $score;
    }

    /**
     * Determine best channel for action delivery
     */
    protected function determineBestChannel(string $action, array $context): array
    {
        $channels = [];

        // Check email viability
        if ($context['has_email'] && $context['marketing_consent'] && !$context['has_unsubscribed']) {
            $emailScore = self::CHANNEL_WEIGHTS['email'];

            // Adjust for fatigue
            if ($context['email_fatigue'] > 50) {
                $emailScore *= 0.7;
            }

            // Boost for high open rate
            if ($context['email_open_rate'] > 0.3) {
                $emailScore *= 1.2;
            }

            $channels['email'] = $emailScore;
        }

        // Check SMS viability
        if ($context['has_phone'] && $context['marketing_consent']) {
            $channels['sms'] = self::CHANNEL_WEIGHTS['sms'];
        }

        // In-app is always available
        $channels['in_app'] = self::CHANNEL_WEIGHTS['in_app'];

        // Push if enabled
        $channels['push'] = self::CHANNEL_WEIGHTS['push'] * 0.8;

        // Apply channel affinity data
        foreach ($context['channel_affinities'] as $channel => $rate) {
            if (isset($channels[$channel])) {
                $channels[$channel] *= (1 + $rate);
            }
        }

        arsort($channels);

        $primaryChannel = array_key_first($channels);

        return [
            'primary' => $primaryChannel,
            'score' => round($channels[$primaryChannel], 3),
            'alternatives' => array_slice(array_keys($channels), 1, 2),
        ];
    }

    /**
     * Get optimal timing for action
     */
    protected function getOptimalTiming(array $context): array
    {
        // Default timing
        $timing = [
            'send_immediately' => false,
            'optimal_hour' => 10,
            'optimal_day' => 'tuesday',
            'timezone' => 'UTC',
        ];

        // Urgent actions
        if ($context['has_active_cart'] && $context['cart_age_hours'] >= 1) {
            $timing['send_immediately'] = true;
            return $timing;
        }

        // Use activity patterns if available
        // This would integrate with FsPersonActivityPattern

        return $timing;
    }

    /**
     * Get action-specific context
     */
    protected function getActionContext(string $action, array $context): array
    {
        return match ($action) {
            'cart_recovery' => [
                'cart_value' => $context['cart_value'],
                'cart_age_hours' => $context['cart_age_hours'],
            ],
            'win_back' => [
                'churn_risk' => $context['churn_risk'],
                'days_inactive' => $context['days_since_last_visit'],
            ],
            'loyalty_reward' => [
                'total_purchases' => $context['total_purchases'],
                'rfm_segment' => $context['rfm_segment'],
            ],
            'upsell_offer' => [
                'ltv_tier' => $context['ltv_tier'],
                'avg_order_value' => $context['avg_order_value'],
            ],
            default => [],
        };
    }

    protected function getRecentActivity(): array
    {
        // Check for active cart
        $cart = DB::table('core_customer_events')
            ->where('tenant_id', $this->tenantId)
            ->where('person_id', $this->personId)
            ->where('event_type', 'add_to_cart')
            ->where('created_at', '>', now()->subDays(7))
            ->orderByDesc('created_at')
            ->first();

        $hasCart = false;
        $cartValue = 0;
        $cartAgeHours = 0;

        if ($cart) {
            // Check if converted
            $purchased = DB::table('core_customer_events')
                ->where('tenant_id', $this->tenantId)
                ->where('person_id', $this->personId)
                ->where('event_type', 'purchase')
                ->where('created_at', '>', $cart->created_at)
                ->exists();

            if (!$purchased) {
                $hasCart = true;
                $cartValue = $cart->value ?? 0;
                $cartAgeHours = now()->diffInHours($cart->created_at);
            }
        }

        // Recent event views
        $eventViews = DB::table('core_customer_events')
            ->where('tenant_id', $this->tenantId)
            ->where('person_id', $this->personId)
            ->where('event_type', 'view_item')
            ->where('created_at', '>', now()->subDays(7))
            ->count();

        // Upcoming events purchased
        $upcomingEvents = DB::table('core_customer_events as cce')
            ->join('events as e', 'cce.event_id', '=', 'e.id')
            ->where('cce.tenant_id', $this->tenantId)
            ->where('cce.person_id', $this->personId)
            ->where('cce.event_type', 'purchase')
            ->where('e.start_date', '>', now())
            ->count();

        return [
            'has_cart' => $hasCart,
            'cart_value' => $cartValue,
            'cart_age_hours' => $cartAgeHours,
            'event_views' => $eventViews,
            'upcoming_events' => $upcomingEvents,
        ];
    }

    protected function getPerson(): ?CoreCustomer
    {
        if (!$this->person) {
            $this->person = CoreCustomer::where('tenant_id', $this->tenantId)
                ->find($this->personId);
        }

        return $this->person;
    }

    protected function getDefaultContext(): array
    {
        return [
            'person_id' => $this->personId,
            'has_email' => false,
            'has_phone' => false,
            'lifecycle_stage' => 'unknown',
            'days_since_first_purchase' => null,
            'days_since_last_purchase' => null,
            'days_since_last_visit' => null,
            'engagement_level' => 'low',
            'engagement_score' => 0,
            'total_visits' => 0,
            'total_pageviews' => 0,
            'total_purchases' => 0,
            'total_spent' => 0,
            'avg_order_value' => 0,
            'ltv' => 0,
            'churn_risk' => 'unknown',
            'ltv_tier' => 'bronze',
            'purchase_likelihood' => 0,
            'rfm_segment' => 'unknown',
            'recency_score' => 0,
            'frequency_score' => 0,
            'monetary_score' => 0,
            'email_open_rate' => 0,
            'email_click_rate' => 0,
            'email_fatigue' => 0,
            'emails_sent_recently' => 0,
            'preferred_channels' => [],
            'channel_affinities' => [],
            'has_active_cart' => false,
            'cart_value' => 0,
            'cart_age_hours' => 0,
            'recent_event_views' => 0,
            'upcoming_events_purchased' => 0,
            'marketing_consent' => false,
            'has_unsubscribed' => false,
        ];
    }

    protected function determineLifecycleStage(?CoreCustomer $person, array $recentActivity): string
    {
        if (!$person) {
            return 'unknown';
        }

        $totalPurchases = $person->total_purchases ?? 0;
        $daysSinceFirstPurchase = $person->first_purchase_at
            ? now()->diffInDays($person->first_purchase_at)
            : null;
        $daysSinceLastPurchase = $person->last_purchase_at
            ? now()->diffInDays($person->last_purchase_at)
            : null;
        $daysSinceLastVisit = $person->last_seen_at
            ? now()->diffInDays($person->last_seen_at)
            : null;

        // New visitor (no purchases, recent activity)
        if ($totalPurchases == 0 && $daysSinceLastVisit !== null && $daysSinceLastVisit <= 30) {
            return 'prospect';
        }

        // First-time buyer
        if ($totalPurchases == 1 && $daysSinceFirstPurchase !== null && $daysSinceFirstPurchase <= 30) {
            return 'new_customer';
        }

        // Active customer
        if ($totalPurchases >= 2 && $daysSinceLastPurchase !== null && $daysSinceLastPurchase <= 90) {
            return 'active';
        }

        // Loyal customer
        if ($totalPurchases >= 5 && $daysSinceLastPurchase !== null && $daysSinceLastPurchase <= 180) {
            return 'loyal';
        }

        // At risk
        if ($totalPurchases >= 1 && $daysSinceLastPurchase !== null && $daysSinceLastPurchase > 90 && $daysSinceLastPurchase <= 180) {
            return 'at_risk';
        }

        // Lapsed
        if ($daysSinceLastPurchase !== null && $daysSinceLastPurchase > 180) {
            return 'lapsed';
        }

        // Dormant visitor
        if ($totalPurchases == 0 && ($daysSinceLastVisit === null || $daysSinceLastVisit > 30)) {
            return 'dormant';
        }

        return 'unknown';
    }

    protected function calculateEngagementLevel(?CoreCustomer $person, array $recentActivity): string
    {
        if (!$person) {
            return 'low';
        }

        $score = $person->engagement_score ?? 0;

        if ($score >= 70) {
            return 'high';
        } elseif ($score >= 40) {
            return 'medium';
        }

        return 'low';
    }

    protected function getLtvTier(float $predictedLtv): string
    {
        return match (true) {
            $predictedLtv >= 1000 => 'platinum',
            $predictedLtv >= 500 => 'gold',
            $predictedLtv >= 200 => 'silver',
            default => 'bronze',
        };
    }

    /**
     * Invalidate cache
     */
    public function invalidateCache(): void
    {
        Cache::forget("nba:{$this->tenantId}:{$this->personId}");
    }
}
