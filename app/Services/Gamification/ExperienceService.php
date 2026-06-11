<?php

namespace App\Services\Gamification;

use App\Models\Customer;
use App\Models\Gamification\CustomerExperience;
use App\Models\Gamification\ExperienceAction;
use App\Models\Gamification\ExperienceConfig;
use App\Models\Gamification\ExperienceTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExperienceService
{
    // ==========================================
    // CONFIGURATION
    // ==========================================

    /**
     * Get or create experience config for tenant
     */
    public function getConfigForTenant(int $tenantId): ExperienceConfig
    {
        return ExperienceConfig::getOrCreateForTenant($tenantId);
    }

    /**
     * Get or create experience config for marketplace
     */
    public function getConfigForMarketplace(int $marketplaceClientId): ExperienceConfig
    {
        return ExperienceConfig::getOrCreateForMarketplace($marketplaceClientId);
    }

    // ==========================================
    // XP AWARDING
    // ==========================================

    /**
     * Award XP for a specific action (tenant context)
     */
    public function awardActionXpForTenant(
        int $tenantId,
        int $customerId,
        string $actionType,
        float $currencyAmount = 0,
        array $options = []
    ): ?ExperienceTransaction {
        // Get action configuration
        $action = ExperienceAction::forTenant($tenantId)
            ->forAction($actionType)
            ->active()
            ->first();

        if (!$action) {
            return null;
        }

        // Check rate limits
        $canEarn = $action->canCustomerEarn($customerId);
        if (!$canEarn['can_earn']) {
            Log::debug("Customer cannot earn XP", [
                'customer_id' => $customerId,
                'action_type' => $actionType,
                'reason' => $canEarn['reason'],
            ]);
            return null;
        }

        // Calculate XP
        $xp = $action->calculateXp($currencyAmount);
        if ($xp <= 0) {
            return null;
        }

        return $this->awardXpForTenant($tenantId, $customerId, $xp, $actionType, $options);
    }

    /**
     * Award XP for a specific action (marketplace context)
     */
    public function awardActionXpForMarketplace(
        int $marketplaceClientId,
        int $customerId,
        string $actionType,
        float $currencyAmount = 0,
        array $options = []
    ): ?ExperienceTransaction {
        // Get action configuration
        $action = ExperienceAction::forMarketplace($marketplaceClientId)
            ->forAction($actionType)
            ->active()
            ->first();

        if (!$action) {
            return null;
        }

        // Check rate limits
        $canEarn = $action->canCustomerEarn($customerId);
        if (!$canEarn['can_earn']) {
            Log::debug("Customer cannot earn XP", [
                'customer_id' => $customerId,
                'action_type' => $actionType,
                'reason' => $canEarn['reason'],
            ]);
            return null;
        }

        // Calculate XP
        $xp = $action->calculateXp($currencyAmount);
        if ($xp <= 0) {
            return null;
        }

        return $this->awardXpForMarketplace($marketplaceClientId, $customerId, $xp, $actionType, $options);
    }

    /**
     * Award specific XP amount (tenant context)
     */
    public function awardXpForTenant(
        int $tenantId,
        int $customerId,
        int $xp,
        string $actionType,
        array $options = []
    ): ExperienceTransaction {
        $customerExperience = CustomerExperience::getOrCreateForTenant($tenantId, $customerId);

        $transaction = $customerExperience->addXp($xp, $actionType, $options);

        Log::info("XP awarded", [
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'xp' => $xp,
            'action_type' => $actionType,
            'new_level' => $customerExperience->current_level,
            'triggered_level_up' => $transaction->triggered_level_up,
        ]);

        // Process level up rewards if applicable
        if ($transaction->triggered_level_up) {
            $this->processLevelUpRewardsForTenant($tenantId, $customerId, $transaction);
        }

        return $transaction;
    }

    /**
     * Award specific XP amount (marketplace context)
     */
    public function awardXpForMarketplace(
        int $marketplaceClientId,
        int $customerId,
        int $xp,
        string $actionType,
        array $options = []
    ): ExperienceTransaction {
        $customerExperience = CustomerExperience::getOrCreateForMarketplace($marketplaceClientId, $customerId);

        $transaction = $customerExperience->addXp($xp, $actionType, $options);

        Log::info("XP awarded", [
            'marketplace_client_id' => $marketplaceClientId,
            'customer_id' => $customerId,
            'xp' => $xp,
            'action_type' => $actionType,
            'new_level' => $customerExperience->current_level,
            'triggered_level_up' => $transaction->triggered_level_up,
        ]);

        // Process level up rewards if applicable
        if ($transaction->triggered_level_up) {
            $this->processLevelUpRewardsForMarketplace($marketplaceClientId, $customerId, $transaction);
        }

        return $transaction;
    }

    // ==========================================
    // LEVEL UP REWARDS
    // ==========================================

    /**
     * Process level up rewards (tenant context)
     */
    protected function processLevelUpRewardsForTenant(int $tenantId, int $customerId, ExperienceTransaction $transaction): void
    {
        $config = $this->getConfigForTenant($tenantId);

        for ($level = $transaction->old_level + 1; $level <= $transaction->new_level; $level++) {
            $rewards = $config->getLevelRewards($level);
            if (!$rewards) {
                continue;
            }

            // Award bonus points
            if (isset($rewards['bonus_points']) && $rewards['bonus_points'] > 0) {
                $customerPoints = \App\Models\Gamification\CustomerPoints::getOrCreate($tenantId, $customerId);
                $customerPoints->addPoints($rewards['bonus_points'], 'level_up_bonus', [
                    'description' => [
                        'en' => "Bonus points for reaching level {$level}",
                        'ro' => "Puncte bonus pentru atingerea nivelului {$level}",
                    ],
                ]);
            }

            // Award badge if specified
            if (isset($rewards['badge_id'])) {
                app(BadgeService::class)->awardBadgeForTenant($tenantId, $customerId, $rewards['badge_id'], [
                    'context' => ['level_up' => $level],
                ]);
            }

            Log::info("Level up rewards processed", [
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'level' => $level,
                'rewards' => $rewards,
            ]);
        }
    }

    /**
     * Process level up rewards (marketplace context)
     */
    protected function processLevelUpRewardsForMarketplace(int $marketplaceClientId, int $customerId, ExperienceTransaction $transaction): void
    {
        $config = $this->getConfigForMarketplace($marketplaceClientId);

        for ($level = $transaction->old_level + 1; $level <= $transaction->new_level; $level++) {
            $rewards = $config->getLevelRewards($level);
            if (!$rewards) {
                continue;
            }

            // Award bonus points
            if (isset($rewards['bonus_points']) && $rewards['bonus_points'] > 0) {
                $customerPoints = \App\Models\Gamification\CustomerPoints::where('marketplace_client_id', $marketplaceClientId)
                    ->where('customer_id', $customerId)
                    ->first();

                if ($customerPoints) {
                    $customerPoints->addPoints($rewards['bonus_points'], 'level_up_bonus', [
                        'description' => [
                            'en' => "Bonus points for reaching level {$level}",
                            'ro' => "Puncte bonus pentru atingerea nivelului {$level}",
                        ],
                    ]);
                }
            }

            // Award badge if specified
            if (isset($rewards['badge_id'])) {
                app(BadgeService::class)->awardBadgeForMarketplace($marketplaceClientId, $customerId, $rewards['badge_id'], [
                    'context' => ['level_up' => $level],
                ]);
            }

            Log::info("Level up rewards processed", [
                'marketplace_client_id' => $marketplaceClientId,
                'customer_id' => $customerId,
                'level' => $level,
                'rewards' => $rewards,
            ]);
        }
    }

    // ==========================================
    // CUSTOMER EXPERIENCE
    // ==========================================

    /**
     * Get customer experience summary (tenant context)
     */
    public function getCustomerSummaryForTenant(int $tenantId, int $customerId): array
    {
        $config = $this->getConfigForTenant($tenantId);
        $customerExperience = CustomerExperience::getOrCreateForTenant($tenantId, $customerId);

        return $this->buildSummary($config, $customerExperience);
    }

    /**
     * Get customer experience summary (marketplace context)
     */
    public function getCustomerSummaryForMarketplace(int $marketplaceClientId, int $customerId): array
    {
        $config = $this->getConfigForMarketplace($marketplaceClientId);
        $customerExperience = CustomerExperience::getOrCreateForMarketplace($marketplaceClientId, $customerId);

        return $this->buildSummary($config, $customerExperience);
    }

    /**
     * Build customer summary array
     */
    protected function buildSummary(ExperienceConfig $config, CustomerExperience $customerExperience): array
    {
        $levelGroup = $config->getLevelGroup($customerExperience->current_level);

        return [
            'total_xp' => $customerExperience->total_xp,
            'current_level' => $customerExperience->current_level,
            'level_name' => $config->formatLevelName($customerExperience->current_level),
            'xp_in_current_level' => $customerExperience->xp_in_current_level,
            'xp_to_next_level' => $customerExperience->xp_to_next_level,
            'level_progress' => $customerExperience->level_progress,
            'level_group' => $levelGroup ? [
                'name' => $levelGroup['name'],
                'color' => $levelGroup['color'] ?? '#6366F1',
                'icon' => $levelGroup['icon'] ?? null,
            ] : null,
            'total_badges_earned' => $customerExperience->total_badges_earned,
            'events_attended' => $customerExperience->events_attended,
            'reviews_submitted' => $customerExperience->reviews_submitted,
            'referrals_converted' => $customerExperience->referrals_converted,
            'last_xp_earned_at' => $customerExperience->last_xp_earned_at?->toIso8601String(),
            'last_level_up_at' => $customerExperience->last_level_up_at?->toIso8601String(),
            'xp_name' => $config->getTranslation('xp_name', app()->getLocale()) ?? 'XP',
        ];
    }

    // ==========================================
    // XP HISTORY
    // ==========================================

    /**
     * Get customer XP history
     */
    public function getXpHistory(
        int $customerId,
        ?int $tenantId = null,
        ?int $marketplaceClientId = null,
        int $limit = 20,
        int $offset = 0
    ): array {
        $query = ExperienceTransaction::where('customer_id', $customerId)
            ->orderBy('created_at', 'desc');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        if ($marketplaceClientId) {
            $query->where('marketplace_client_id', $marketplaceClientId);
        }

        $total = $query->count();
        $transactions = $query->skip($offset)->take($limit)->get();

        return [
            'transactions' => $transactions->map(function ($t) {
                return [
                    'id' => $t->id,
                    'xp' => $t->xp,
                    'formatted_xp' => $t->formatted_xp,
                    'xp_balance_after' => $t->xp_balance_after,
                    'level_after' => $t->level_after,
                    'triggered_level_up' => $t->triggered_level_up,
                    'level_change' => $t->level_change_summary,
                    'action_type' => $t->action_type,
                    'action_label' => $t->action_type_label,
                    'description' => $t->getTranslation('description', app()->getLocale()),
                    'created_at' => $t->created_at->toIso8601String(),
                ];
            }),
            'pagination' => [
                'total' => $total,
                'has_more' => ($offset + $limit) < $total,
                'offset' => $offset,
                'limit' => $limit,
            ],
        ];
    }

    // ==========================================
    // LEADERBOARD
    // ==========================================

    /**
     * Get XP leaderboard (tenant context)
     */
    public function getLeaderboardForTenant(int $tenantId, int $limit = 10): Collection
    {
        return CustomerExperience::forTenant($tenantId)
            ->with('customer')
            ->orderByDesc('total_xp')
            ->limit($limit)
            ->get()
            ->map(function ($ce, $index) {
                return [
                    'rank' => $index + 1,
                    'customer_id' => $ce->customer_id,
                    'customer_name' => $ce->customer?->full_name ?? $ce->customer?->email ?? 'Unknown',
                    'total_xp' => $ce->total_xp,
                    'current_level' => $ce->current_level,
                    'level_group' => $ce->current_level_group,
                ];
            });
    }

    /**
     * Get XP leaderboard (marketplace context)
     */
    public function getLeaderboardForMarketplace(int $marketplaceClientId, int $limit = 10): Collection
    {
        return CustomerExperience::forMarketplace($marketplaceClientId)
            ->with('customer')
            ->orderByDesc('total_xp')
            ->limit($limit)
            ->get()
            ->map(function ($ce, $index) {
                return [
                    'rank' => $index + 1,
                    'customer_id' => $ce->customer_id,
                    'customer_name' => $ce->customer?->full_name ?? $ce->customer?->email ?? 'Unknown',
                    'total_xp' => $ce->total_xp,
                    'current_level' => $ce->current_level,
                    'level_group' => $ce->current_level_group,
                ];
            });
    }

    /**
     * Get customer's leaderboard position
     */
    public function getCustomerRank(
        int $customerId,
        ?int $tenantId = null,
        ?int $marketplaceClientId = null
    ): ?int {
        $query = CustomerExperience::query();

        if ($tenantId) {
            $query->forTenant($tenantId);
        }
        if ($marketplaceClientId) {
            $query->forMarketplace($marketplaceClientId);
        }

        $customerExperience = $query->where('customer_id', $customerId)->first();
        if (!$customerExperience) {
            return null;
        }

        // Count how many customers have more XP
        $higherRanked = (clone $query)
            ->where('total_xp', '>', $customerExperience->total_xp)
            ->count();

        return $higherRanked + 1;
    }

    // ==========================================
    // ACTIONS MANAGEMENT
    // ==========================================

    /**
     * Ensure default actions exist for tenant
     */
    public function ensureDefaultActionsForTenant(int $tenantId): void
    {
        ExperienceAction::createDefaultsForTenant($tenantId);
    }

    /**
     * Ensure default actions exist for marketplace
     */
    public function ensureDefaultActionsForMarketplace(int $marketplaceClientId): void
    {
        ExperienceAction::createDefaultsForMarketplace($marketplaceClientId);
    }
}
