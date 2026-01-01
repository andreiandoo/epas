<?php

namespace App\Services\Tracking;

use App\Models\CoreCustomer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CustomerJourneyService
{
    protected int $tenantId;

    // Journey stages with progression criteria
    protected const STAGES = [
        'anonymous' => [
            'order' => 0,
            'description' => 'Unknown visitor',
            'criteria' => [],
        ],
        'aware' => [
            'order' => 1,
            'description' => 'Has visited the site',
            'criteria' => ['min_visits' => 1],
        ],
        'interested' => [
            'order' => 2,
            'description' => 'Has viewed events',
            'criteria' => ['min_event_views' => 1],
        ],
        'considering' => [
            'order' => 3,
            'description' => 'Has added to cart or started checkout',
            'criteria' => ['has_cart_activity' => true],
        ],
        'converted' => [
            'order' => 4,
            'description' => 'Has made a purchase',
            'criteria' => ['min_purchases' => 1],
        ],
        'retained' => [
            'order' => 5,
            'description' => 'Repeat purchaser',
            'criteria' => ['min_purchases' => 2],
        ],
        'loyal' => [
            'order' => 6,
            'description' => 'Frequent purchaser',
            'criteria' => ['min_purchases' => 5, 'min_ltv' => 200],
        ],
        'advocate' => [
            'order' => 7,
            'description' => 'High-value promoter',
            'criteria' => ['min_purchases' => 5, 'min_ltv' => 500, 'has_referrals' => true],
        ],
        'at_risk' => [
            'order' => -1,
            'description' => 'Showing signs of churn',
            'criteria' => ['churn_risk' => ['high', 'critical']],
        ],
        'lapsed' => [
            'order' => -2,
            'description' => 'Inactive for extended period',
            'criteria' => ['days_inactive' => 180],
        ],
    ];

    // Stage transitions and their triggers
    protected const TRANSITIONS = [
        'anonymous_to_aware' => ['trigger' => 'first_visit', 'celebration' => false],
        'aware_to_interested' => ['trigger' => 'first_event_view', 'celebration' => false],
        'interested_to_considering' => ['trigger' => 'first_cart', 'celebration' => false],
        'considering_to_converted' => ['trigger' => 'first_purchase', 'celebration' => true],
        'converted_to_retained' => ['trigger' => 'second_purchase', 'celebration' => true],
        'retained_to_loyal' => ['trigger' => 'fifth_purchase', 'celebration' => true],
        'loyal_to_advocate' => ['trigger' => 'first_referral', 'celebration' => true],
    ];

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    public static function forTenant(int $tenantId): self
    {
        return new self($tenantId);
    }

    /**
     * Get current journey stage for a person
     */
    public function getCurrentStage(int $personId): array
    {
        $cacheKey = "journey:stage:{$this->tenantId}:{$personId}";

        return Cache::remember($cacheKey, 1800, function () use ($personId) {
            $person = CoreCustomer::where('tenant_id', $this->tenantId)
                ->find($personId);

            if (!$person) {
                return [
                    'stage' => 'anonymous',
                    'stage_info' => self::STAGES['anonymous'],
                ];
            }

            $metrics = $this->getPersonMetrics($person);
            $stage = $this->determineStage($metrics);

            return [
                'person_id' => $personId,
                'stage' => $stage,
                'stage_info' => self::STAGES[$stage],
                'metrics' => $metrics,
                'next_stage' => $this->getNextStage($stage),
                'progress_to_next' => $this->calculateProgressToNext($stage, $metrics),
                'journey_history' => $this->getJourneyHistory($personId),
                'time_in_stage' => $this->getTimeInStage($personId, $stage),
            ];
        });
    }

    /**
     * Record a journey transition
     */
    public function recordTransition(int $personId, string $fromStage, string $toStage, ?string $trigger = null): void
    {
        DB::table('customer_journey_transitions')->insert([
            'tenant_id' => $this->tenantId,
            'person_id' => $personId,
            'from_stage' => $fromStage,
            'to_stage' => $toStage,
            'trigger' => $trigger,
            'created_at' => now(),
        ]);

        // Update person's current stage
        CoreCustomer::where('tenant_id', $this->tenantId)
            ->where('id', $personId)
            ->update([
                'journey_stage' => $toStage,
                'journey_stage_updated_at' => now(),
            ]);

        // Invalidate cache
        Cache::forget("journey:stage:{$this->tenantId}:{$personId}");

        // Fire webhook for celebrations
        $transitionKey = "{$fromStage}_to_{$toStage}";
        if (isset(self::TRANSITIONS[$transitionKey]) && self::TRANSITIONS[$transitionKey]['celebration']) {
            $this->fireCelebrationWebhook($personId, $toStage);
        }
    }

    /**
     * Get complete journey for a person
     */
    public function getFullJourney(int $personId): array
    {
        $person = CoreCustomer::where('tenant_id', $this->tenantId)
            ->find($personId);

        if (!$person) {
            return ['error' => 'Person not found'];
        }

        // Get all touchpoints
        $touchpoints = $this->getTouchpoints($personId);

        // Get stage transitions
        $transitions = $this->getJourneyHistory($personId);

        // Get key milestones
        $milestones = $this->getMilestones($personId, $person);

        // Get current stage
        $currentStage = $this->getCurrentStage($personId);

        return [
            'person_id' => $personId,
            'current_stage' => $currentStage,
            'journey_start' => $person->created_at,
            'journey_duration_days' => now()->diffInDays($person->created_at),
            'touchpoints' => $touchpoints,
            'stage_transitions' => $transitions,
            'milestones' => $milestones,
            'summary' => [
                'total_touchpoints' => count($touchpoints),
                'total_transitions' => count($transitions),
                'conversion_time_days' => $this->getConversionTime($person),
                'is_progressing' => $currentStage['stage'] !== 'at_risk' && $currentStage['stage'] !== 'lapsed',
            ],
        ];
    }

    /**
     * Get journey analytics for all customers
     */
    public function getJourneyAnalytics(): array
    {
        // Stage distribution
        $stageDistribution = CoreCustomer::where('tenant_id', $this->tenantId)
            ->whereNotNull('journey_stage')
            ->groupBy('journey_stage')
            ->selectRaw('journey_stage, COUNT(*) as count')
            ->pluck('count', 'journey_stage')
            ->toArray();

        // Fill in missing stages
        foreach (array_keys(self::STAGES) as $stage) {
            if (!isset($stageDistribution[$stage])) {
                $stageDistribution[$stage] = 0;
            }
        }

        // Stage conversion rates
        $conversionRates = $this->calculateStageConversionRates();

        // Average time in each stage
        $avgTimeInStage = $this->calculateAverageTimeInStage();

        // Transition funnel
        $transitionFunnel = $this->getTransitionFunnel();

        // Recent transitions
        $recentTransitions = DB::table('customer_journey_transitions')
            ->where('tenant_id', $this->tenantId)
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('from_stage, to_stage, COUNT(*) as count')
            ->groupBy('from_stage', 'to_stage')
            ->get();

        return [
            'stage_distribution' => $stageDistribution,
            'total_customers' => array_sum($stageDistribution),
            'conversion_rates' => $conversionRates,
            'avg_time_in_stage_days' => $avgTimeInStage,
            'transition_funnel' => $transitionFunnel,
            'recent_transitions' => $recentTransitions,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get customers stuck at a stage
     */
    public function getStuckCustomers(string $stage, int $minDays = 30, int $limit = 100): Collection
    {
        return CoreCustomer::where('tenant_id', $this->tenantId)
            ->where('journey_stage', $stage)
            ->whereNotNull('journey_stage_updated_at')
            ->whereRaw('journey_stage_updated_at < NOW() - INTERVAL ? DAY', [$minDays])
            ->where('churn_risk', '!=', 'critical')
            ->orderBy('ltv', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($c) => [
                'person_id' => $c->id,
                'stage' => $stage,
                'days_in_stage' => now()->diffInDays($c->journey_stage_updated_at),
                'ltv' => $c->ltv,
                'churn_risk' => $c->churn_risk,
                'next_best_action' => $this->getNextBestActionForStage($stage),
            ]);
    }

    /**
     * Determine current stage based on metrics
     */
    protected function determineStage(array $metrics): string
    {
        // Check for negative states first
        if ($metrics['churn_risk'] === 'critical' || $metrics['churn_risk'] === 'high') {
            return 'at_risk';
        }

        if ($metrics['days_inactive'] >= 180) {
            return 'lapsed';
        }

        // Check positive progression (highest to lowest)
        if ($metrics['purchases'] >= 5 && $metrics['ltv'] >= 500 && $metrics['has_referrals']) {
            return 'advocate';
        }

        if ($metrics['purchases'] >= 5 && $metrics['ltv'] >= 200) {
            return 'loyal';
        }

        if ($metrics['purchases'] >= 2) {
            return 'retained';
        }

        if ($metrics['purchases'] >= 1) {
            return 'converted';
        }

        if ($metrics['cart_additions'] > 0 || $metrics['checkouts'] > 0) {
            return 'considering';
        }

        if ($metrics['event_views'] > 0) {
            return 'interested';
        }

        if ($metrics['visits'] > 0) {
            return 'aware';
        }

        return 'anonymous';
    }

    /**
     * Get person metrics for stage determination
     */
    protected function getPersonMetrics(CoreCustomer $person): array
    {
        $eventCounts = DB::table('core_customer_events')
            ->where('tenant_id', $this->tenantId)
            ->where('person_id', $person->id)
            ->selectRaw("
                SUM(CASE WHEN event_type = 'view_item' THEN 1 ELSE 0 END) as event_views,
                SUM(CASE WHEN event_type = 'add_to_cart' THEN 1 ELSE 0 END) as cart_additions,
                SUM(CASE WHEN event_type = 'begin_checkout' THEN 1 ELSE 0 END) as checkouts
            ")
            ->first();

        $referrals = DB::table('referrals')
            ->where('tenant_id', $this->tenantId)
            ->where('referrer_id', $person->id)
            ->where('status', 'completed')
            ->count();

        return [
            'visits' => $person->total_visits ?? 0,
            'event_views' => $eventCounts->event_views ?? 0,
            'cart_additions' => $eventCounts->cart_additions ?? 0,
            'checkouts' => $eventCounts->checkouts ?? 0,
            'purchases' => $person->total_purchases ?? 0,
            'ltv' => $person->ltv ?? 0,
            'churn_risk' => $person->churn_risk ?? 'unknown',
            'days_inactive' => $person->last_seen_at
                ? now()->diffInDays($person->last_seen_at)
                : 999,
            'has_referrals' => $referrals > 0,
            'referral_count' => $referrals,
        ];
    }

    /**
     * Get next stage in progression
     */
    protected function getNextStage(string $currentStage): ?array
    {
        $currentOrder = self::STAGES[$currentStage]['order'] ?? -1;

        if ($currentOrder < 0) {
            // At risk or lapsed - next stage is their last positive stage
            return [
                'stage' => 'recovery',
                'description' => 'Re-engage and return to active state',
            ];
        }

        $nextOrder = $currentOrder + 1;
        foreach (self::STAGES as $stage => $info) {
            if ($info['order'] === $nextOrder) {
                return [
                    'stage' => $stage,
                    'description' => $info['description'],
                    'criteria' => $info['criteria'],
                ];
            }
        }

        return null; // Already at highest stage
    }

    /**
     * Calculate progress to next stage (0-100%)
     */
    protected function calculateProgressToNext(string $currentStage, array $metrics): ?array
    {
        $nextStage = $this->getNextStage($currentStage);
        if (!$nextStage || !isset($nextStage['criteria'])) {
            return null;
        }

        $criteria = $nextStage['criteria'];
        $progress = [];

        if (isset($criteria['min_purchases'])) {
            $progress['purchases'] = [
                'current' => $metrics['purchases'],
                'required' => $criteria['min_purchases'],
                'percent' => min(100, ($metrics['purchases'] / $criteria['min_purchases']) * 100),
            ];
        }

        if (isset($criteria['min_ltv'])) {
            $progress['ltv'] = [
                'current' => $metrics['ltv'],
                'required' => $criteria['min_ltv'],
                'percent' => min(100, ($metrics['ltv'] / $criteria['min_ltv']) * 100),
            ];
        }

        if (isset($criteria['has_referrals'])) {
            $progress['referrals'] = [
                'current' => $metrics['has_referrals'] ? 1 : 0,
                'required' => 1,
                'percent' => $metrics['has_referrals'] ? 100 : 0,
            ];
        }

        // Overall progress
        $overallProgress = empty($progress) ? 0 : array_sum(array_column($progress, 'percent')) / count($progress);

        return [
            'next_stage' => $nextStage['stage'],
            'criteria_progress' => $progress,
            'overall_percent' => round($overallProgress, 1),
        ];
    }

    /**
     * Get journey history (stage transitions)
     */
    protected function getJourneyHistory(int $personId): Collection
    {
        return DB::table('customer_journey_transitions')
            ->where('tenant_id', $this->tenantId)
            ->where('person_id', $personId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Get time in current stage
     */
    protected function getTimeInStage(int $personId, string $stage): ?int
    {
        $lastTransition = DB::table('customer_journey_transitions')
            ->where('tenant_id', $this->tenantId)
            ->where('person_id', $personId)
            ->where('to_stage', $stage)
            ->orderByDesc('created_at')
            ->first();

        if ($lastTransition) {
            return now()->diffInDays($lastTransition->created_at);
        }

        // Check if this is their first stage
        $person = CoreCustomer::where('tenant_id', $this->tenantId)
            ->find($personId);

        if ($person) {
            return now()->diffInDays($person->created_at);
        }

        return null;
    }

    /**
     * Get touchpoints for journey visualization
     */
    protected function getTouchpoints(int $personId): Collection
    {
        return DB::table('core_customer_events')
            ->where('tenant_id', $this->tenantId)
            ->where('person_id', $personId)
            ->orderBy('created_at')
            ->select([
                'event_type',
                'event_category',
                'page_path',
                'event_id',
                'value',
                'utm_source',
                'utm_medium',
                'created_at',
            ])
            ->limit(500)
            ->get()
            ->map(fn($t) => [
                'type' => $t->event_type,
                'category' => $t->event_category,
                'page' => $t->page_path,
                'event_id' => $t->event_id,
                'value' => $t->value,
                'source' => $t->utm_source,
                'medium' => $t->utm_medium,
                'timestamp' => $t->created_at,
            ]);
    }

    /**
     * Get key milestones
     */
    protected function getMilestones(int $personId, CoreCustomer $person): array
    {
        $milestones = [];

        $milestones[] = [
            'type' => 'first_visit',
            'date' => $person->created_at,
            'label' => 'First visit',
        ];

        if ($person->first_purchase_at) {
            $milestones[] = [
                'type' => 'first_purchase',
                'date' => $person->first_purchase_at,
                'label' => 'First purchase',
            ];
        }

        // Get purchase milestones
        $purchaseMilestones = [2 => 'Second purchase', 5 => 'Fifth purchase', 10 => 'Tenth purchase'];
        foreach ($purchaseMilestones as $count => $label) {
            if (($person->total_purchases ?? 0) >= $count) {
                $purchase = DB::table('orders')
                    ->where('tenant_id', $this->tenantId)
                    ->where('customer_id', $personId)
                    ->where('status', 'completed')
                    ->orderBy('created_at')
                    ->skip($count - 1)
                    ->first();

                if ($purchase) {
                    $milestones[] = [
                        'type' => "purchase_{$count}",
                        'date' => $purchase->created_at,
                        'label' => $label,
                    ];
                }
            }
        }

        // Sort by date
        usort($milestones, fn($a, $b) => $a['date'] <=> $b['date']);

        return $milestones;
    }

    /**
     * Get conversion time (first visit to first purchase)
     */
    protected function getConversionTime(CoreCustomer $person): ?int
    {
        if (!$person->first_purchase_at) {
            return null;
        }

        return $person->created_at->diffInDays($person->first_purchase_at);
    }

    /**
     * Calculate stage conversion rates
     */
    protected function calculateStageConversionRates(): array
    {
        $rates = [];
        $orderedStages = ['aware', 'interested', 'considering', 'converted', 'retained', 'loyal', 'advocate'];

        for ($i = 0; $i < count($orderedStages) - 1; $i++) {
            $fromStage = $orderedStages[$i];
            $toStage = $orderedStages[$i + 1];

            $reached = DB::table('customer_journey_transitions')
                ->where('tenant_id', $this->tenantId)
                ->where('from_stage', $fromStage)
                ->count();

            $progressed = DB::table('customer_journey_transitions')
                ->where('tenant_id', $this->tenantId)
                ->where('from_stage', $fromStage)
                ->where('to_stage', $toStage)
                ->count();

            $rates["{$fromStage}_to_{$toStage}"] = $reached > 0
                ? round($progressed / $reached * 100, 1)
                : 0;
        }

        return $rates;
    }

    /**
     * Calculate average time in each stage
     */
    protected function calculateAverageTimeInStage(): array
    {
        $avgTimes = [];

        foreach (array_keys(self::STAGES) as $stage) {
            if (in_array($stage, ['anonymous', 'at_risk', 'lapsed'])) {
                continue;
            }

            $avg = DB::table('customer_journey_transitions as t1')
                ->join('customer_journey_transitions as t2', function ($join) {
                    $join->on('t1.person_id', '=', 't2.person_id')
                        ->on('t1.tenant_id', '=', 't2.tenant_id')
                        ->whereRaw('t2.from_stage = t1.to_stage')
                        ->whereRaw('t2.created_at > t1.created_at');
                })
                ->where('t1.tenant_id', $this->tenantId)
                ->where('t1.to_stage', $stage)
                ->selectRaw('AVG(TIMESTAMPDIFF(DAY, t1.created_at, t2.created_at)) as avg_days')
                ->first();

            $avgTimes[$stage] = round($avg->avg_days ?? 0, 1);
        }

        return $avgTimes;
    }

    /**
     * Get transition funnel
     */
    protected function getTransitionFunnel(): array
    {
        $funnel = [];
        $stages = ['aware', 'interested', 'considering', 'converted', 'retained', 'loyal'];

        foreach ($stages as $stage) {
            $count = CoreCustomer::where('tenant_id', $this->tenantId)
                ->whereIn('journey_stage', array_slice($stages, array_search($stage, $stages)))
                ->count();

            $funnel[$stage] = $count;
        }

        return $funnel;
    }

    /**
     * Get next best action for stage
     */
    protected function getNextBestActionForStage(string $stage): string
    {
        return match ($stage) {
            'aware' => 'Send personalized event recommendations',
            'interested' => 'Offer first-purchase discount',
            'considering' => 'Send cart reminder or limited-time offer',
            'converted' => 'Send thank you and related events',
            'retained' => 'Offer loyalty rewards',
            'loyal' => 'Invite to VIP program',
            'at_risk' => 'Send win-back campaign',
            'lapsed' => 'Aggressive reactivation offer',
            default => 'Monitor and engage',
        };
    }

    /**
     * Fire celebration webhook
     */
    protected function fireCelebrationWebhook(int $personId, string $stage): void
    {
        // This would integrate with the webhook system
        dispatch(function () use ($personId, $stage) {
            // Fire celebration event for email/notification
        })->onQueue('notifications');
    }

    /**
     * Invalidate cache
     */
    public function invalidateCache(int $personId): void
    {
        Cache::forget("journey:stage:{$this->tenantId}:{$personId}");
    }
}
