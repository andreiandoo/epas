<?php

namespace App\Services\Gamification;

use App\Models\Customer;
use App\Models\Gamification\Badge;
use App\Models\Gamification\CustomerBadge;
use App\Models\Gamification\CustomerExperience;
use App\Models\Gamification\CustomerPoints;
use App\Models\Gamification\ExperienceAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BadgeService
{
    protected ExperienceService $experienceService;

    public function __construct(ExperienceService $experienceService)
    {
        $this->experienceService = $experienceService;
    }

    // ==========================================
    // BADGE LISTING
    // ==========================================

    /**
     * Get all badges for tenant
     */
    public function getBadgesForTenant(int $tenantId, ?int $customerId = null): Collection
    {
        $query = Badge::forTenant($tenantId)
            ->active()
            ->orderBy('is_featured', 'desc')
            ->orderBy('rarity_level', 'desc')
            ->orderBy('sort_order');

        // For non-logged in users, only show non-secret badges
        if (!$customerId) {
            $query->visible();
        }

        $badges = $query->get();

        // Mark earned badges if customer is specified
        if ($customerId) {
            $earnedBadgeIds = CustomerBadge::where('tenant_id', $tenantId)
                ->where('customer_id', $customerId)
                ->pluck('badge_id')
                ->toArray();

            $badges = $badges->map(function ($badge) use ($earnedBadgeIds) {
                $badge->is_earned = in_array($badge->id, $earnedBadgeIds);
                // Hide secret badges if not earned
                if ($badge->is_secret && !$badge->is_earned) {
                    $badge->name = ['en' => '???', 'ro' => '???'];
                    $badge->description = ['en' => 'Secret badge', 'ro' => 'Badge secret'];
                }
                return $badge;
            });
        }

        return $badges;
    }

    /**
     * Get all badges for marketplace
     */
    public function getBadgesForMarketplace(int $marketplaceClientId, ?int $customerId = null): Collection
    {
        $query = Badge::forMarketplace($marketplaceClientId)
            ->active()
            ->orderBy('is_featured', 'desc')
            ->orderBy('rarity_level', 'desc')
            ->orderBy('sort_order');

        // For non-logged in users, only show non-secret badges
        if (!$customerId) {
            $query->visible();
        }

        $badges = $query->get();

        // Mark earned badges if customer is specified
        if ($customerId) {
            $earnedBadgeIds = CustomerBadge::where('marketplace_client_id', $marketplaceClientId)
                ->where('customer_id', $customerId)
                ->pluck('badge_id')
                ->toArray();

            $badges = $badges->map(function ($badge) use ($earnedBadgeIds) {
                $badge->is_earned = in_array($badge->id, $earnedBadgeIds);
                // Hide secret badges if not earned
                if ($badge->is_secret && !$badge->is_earned) {
                    $badge->name = ['en' => '???', 'ro' => '???'];
                    $badge->description = ['en' => 'Secret badge', 'ro' => 'Badge secret'];
                }
                return $badge;
            });
        }

        return $badges;
    }

    /**
     * Get customer's earned badges
     */
    public function getCustomerBadges(int $customerId, ?int $tenantId = null, ?int $marketplaceClientId = null): Collection
    {
        $query = CustomerBadge::where('customer_id', $customerId)
            ->with('badge')
            ->orderBy('earned_at', 'desc');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        if ($marketplaceClientId) {
            $query->where('marketplace_client_id', $marketplaceClientId);
        }

        return $query->get();
    }

    // ==========================================
    // BADGE AWARDING
    // ==========================================

    /**
     * Award a badge to a customer (tenant context)
     */
    public function awardBadgeForTenant(
        int $tenantId,
        int $customerId,
        int $badgeId,
        array $options = []
    ): array {
        return DB::transaction(function () use ($tenantId, $customerId, $badgeId, $options) {
            $badge = Badge::forTenant($tenantId)->find($badgeId);
            if (!$badge) {
                return ['success' => false, 'error' => 'Badge not found'];
            }

            $customer = Customer::find($customerId);
            if (!$customer) {
                return ['success' => false, 'error' => 'Customer not found'];
            }

            // Check if already earned
            if ($badge->isEarnedBy($customer)) {
                return ['success' => false, 'error' => 'Badge already earned'];
            }

            // Award XP if badge has XP reward
            $experienceTransaction = null;
            if ($badge->xp_reward > 0) {
                $experienceTransaction = $this->experienceService->awardXpForTenant(
                    $tenantId,
                    $customerId,
                    $badge->xp_reward,
                    ExperienceAction::ACTION_BADGE_EARNED,
                    [
                        'reference_type' => Badge::class,
                        'reference_id' => $badge->id,
                        'description' => [
                            'en' => "Earned badge: {$badge->getTranslation('name', 'en')}",
                            'ro' => "Badge câștigat: {$badge->getTranslation('name', 'ro')}",
                        ],
                    ]
                );
            }

            // Award bonus points if badge has them
            $pointsTransaction = null;
            if ($badge->bonus_points > 0) {
                $customerPoints = CustomerPoints::getOrCreate($tenantId, $customerId);
                $pointsTransaction = $customerPoints->addPoints($badge->bonus_points, 'badge_bonus', [
                    'reference_type' => Badge::class,
                    'reference_id' => $badge->id,
                    'description' => [
                        'en' => "Bonus points for badge: {$badge->getTranslation('name', 'en')}",
                        'ro' => "Puncte bonus pentru badge: {$badge->getTranslation('name', 'ro')}",
                    ],
                ]);
            }

            // Create customer badge record
            $customerBadge = CustomerBadge::awardBadge($badge, $customer, [
                'xp_awarded' => $badge->xp_reward,
                'experience_transaction_id' => $experienceTransaction?->id,
                'points_awarded' => $badge->bonus_points,
                'points_transaction_id' => $pointsTransaction?->id,
                'context' => $options['context'] ?? null,
                'reference_type' => $options['reference_type'] ?? null,
                'reference_id' => $options['reference_id'] ?? null,
            ]);

            // Update customer experience badge count
            $customerExperience = CustomerExperience::where('tenant_id', $tenantId)
                ->where('customer_id', $customerId)
                ->first();
            $customerExperience?->incrementBadgeCount();

            Log::info("Badge awarded", [
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'badge_id' => $badgeId,
                'xp_awarded' => $badge->xp_reward,
                'points_awarded' => $badge->bonus_points,
            ]);

            return [
                'success' => true,
                'customer_badge' => $customerBadge,
                'xp_awarded' => $badge->xp_reward,
                'points_awarded' => $badge->bonus_points,
            ];
        });
    }

    /**
     * Award a badge to a customer (marketplace context)
     */
    public function awardBadgeForMarketplace(
        int $marketplaceClientId,
        int $customerId,
        int $badgeId,
        array $options = []
    ): array {
        return DB::transaction(function () use ($marketplaceClientId, $customerId, $badgeId, $options) {
            $badge = Badge::forMarketplace($marketplaceClientId)->find($badgeId);
            if (!$badge) {
                return ['success' => false, 'error' => 'Badge not found'];
            }

            $customer = Customer::find($customerId);
            if (!$customer) {
                return ['success' => false, 'error' => 'Customer not found'];
            }

            // Check if already earned
            if ($badge->isEarnedBy($customer)) {
                return ['success' => false, 'error' => 'Badge already earned'];
            }

            // Award XP if badge has XP reward
            $experienceTransaction = null;
            if ($badge->xp_reward > 0) {
                $experienceTransaction = $this->experienceService->awardXpForMarketplace(
                    $marketplaceClientId,
                    $customerId,
                    $badge->xp_reward,
                    ExperienceAction::ACTION_BADGE_EARNED,
                    [
                        'reference_type' => Badge::class,
                        'reference_id' => $badge->id,
                        'description' => [
                            'en' => "Earned badge: {$badge->getTranslation('name', 'en')}",
                            'ro' => "Badge câștigat: {$badge->getTranslation('name', 'ro')}",
                        ],
                    ]
                );
            }

            // Award bonus points if badge has them
            $pointsTransaction = null;
            if ($badge->bonus_points > 0) {
                $customerPoints = CustomerPoints::where('marketplace_client_id', $marketplaceClientId)
                    ->where('customer_id', $customerId)
                    ->first();

                if ($customerPoints) {
                    $pointsTransaction = $customerPoints->addPoints($badge->bonus_points, 'badge_bonus', [
                        'reference_type' => Badge::class,
                        'reference_id' => $badge->id,
                        'description' => [
                            'en' => "Bonus points for badge: {$badge->getTranslation('name', 'en')}",
                            'ro' => "Puncte bonus pentru badge: {$badge->getTranslation('name', 'ro')}",
                        ],
                    ]);
                }
            }

            // Create customer badge record
            $customerBadge = CustomerBadge::awardBadge($badge, $customer, [
                'xp_awarded' => $badge->xp_reward,
                'experience_transaction_id' => $experienceTransaction?->id,
                'points_awarded' => $badge->bonus_points,
                'points_transaction_id' => $pointsTransaction?->id,
                'context' => $options['context'] ?? null,
                'reference_type' => $options['reference_type'] ?? null,
                'reference_id' => $options['reference_id'] ?? null,
            ]);

            // Update customer experience badge count
            $customerExperience = CustomerExperience::where('marketplace_client_id', $marketplaceClientId)
                ->where('customer_id', $customerId)
                ->first();
            $customerExperience?->incrementBadgeCount();

            Log::info("Badge awarded", [
                'marketplace_client_id' => $marketplaceClientId,
                'customer_id' => $customerId,
                'badge_id' => $badgeId,
                'xp_awarded' => $badge->xp_reward,
                'points_awarded' => $badge->bonus_points,
            ]);

            return [
                'success' => true,
                'customer_badge' => $customerBadge,
                'xp_awarded' => $badge->xp_reward,
                'points_awarded' => $badge->bonus_points,
            ];
        });
    }

    // ==========================================
    // BADGE EVALUATION
    // ==========================================

    /**
     * Check and award eligible badges for customer (tenant context)
     */
    public function evaluateBadgesForTenant(int $tenantId, int $customerId, array $context = []): array
    {
        $customer = Customer::find($customerId);
        if (!$customer) {
            return ['awarded' => []];
        }

        $earnedBadgeIds = CustomerBadge::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->pluck('badge_id')
            ->toArray();

        $badges = Badge::forTenant($tenantId)
            ->active()
            ->whereNotIn('id', $earnedBadgeIds)
            ->get();

        $awarded = [];

        foreach ($badges as $badge) {
            if ($badge->evaluateConditions($customer, $context)) {
                $result = $this->awardBadgeForTenant($tenantId, $customerId, $badge->id, [
                    'context' => $context,
                ]);

                if ($result['success']) {
                    $awarded[] = $badge;
                }
            }
        }

        return ['awarded' => $awarded];
    }

    /**
     * Check and award eligible badges for customer (marketplace context)
     */
    public function evaluateBadgesForMarketplace(int $marketplaceClientId, int $customerId, array $context = []): array
    {
        $customer = Customer::find($customerId);
        if (!$customer) {
            return ['awarded' => []];
        }

        $earnedBadgeIds = CustomerBadge::where('marketplace_client_id', $marketplaceClientId)
            ->where('customer_id', $customerId)
            ->pluck('badge_id')
            ->toArray();

        $badges = Badge::forMarketplace($marketplaceClientId)
            ->active()
            ->whereNotIn('id', $earnedBadgeIds)
            ->get();

        $awarded = [];

        foreach ($badges as $badge) {
            if ($badge->evaluateConditions($customer, $context)) {
                $result = $this->awardBadgeForMarketplace($marketplaceClientId, $customerId, $badge->id, [
                    'context' => $context,
                ]);

                if ($result['success']) {
                    $awarded[] = $badge;
                }
            }
        }

        return ['awarded' => $awarded];
    }

    // ==========================================
    // BADGE ANALYTICS
    // ==========================================

    /**
     * Get badge statistics
     */
    public function getBadgeStats(int $badgeId): array
    {
        $badge = Badge::find($badgeId);
        if (!$badge) {
            return [];
        }

        $totalEarned = CustomerBadge::where('badge_id', $badgeId)->count();
        $recentlyEarned = CustomerBadge::where('badge_id', $badgeId)
            ->recent(7)
            ->count();

        return [
            'badge' => $badge,
            'total_earned' => $totalEarned,
            'recently_earned' => $recentlyEarned,
        ];
    }

    /**
     * Get most popular badges
     */
    public function getMostPopularBadges(?int $tenantId = null, ?int $marketplaceClientId = null, int $limit = 10): Collection
    {
        $query = CustomerBadge::selectRaw('badge_id, COUNT(*) as earned_count')
            ->groupBy('badge_id')
            ->orderByDesc('earned_count')
            ->limit($limit);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        if ($marketplaceClientId) {
            $query->where('marketplace_client_id', $marketplaceClientId);
        }

        return $query->with('badge')->get();
    }
}
