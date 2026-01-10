<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\Gamification\CustomerPoints;
use App\Models\Gamification\PointsTransaction;
use App\Models\Gamification\CustomerExperience;
use App\Models\Gamification\ExperienceTransaction;
use App\Models\Gamification\CustomerBadge;
use App\Models\Gamification\Badge;
use App\Models\Gamification\Reward;
use App\Models\Gamification\RewardRedemption;
use App\Models\Gamification\GamificationConfig;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RewardsController extends BaseController
{
    /**
     * Get customer rewards summary
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        // Get points balance
        $customerPoints = CustomerPoints::where('marketplace_customer_id', $customer->id)->first();
        $pointsBalance = $customerPoints ? $customerPoints->current_balance : 0;
        $lifetimePoints = $customerPoints ? $customerPoints->total_earned : 0;
        $lifetimeSpent = $customerPoints ? $customerPoints->total_spent : 0;

        // Get experience/level info
        $customerExperience = CustomerExperience::where('marketplace_customer_id', $customer->id)->first();
        $level = $customerExperience ? $customerExperience->current_level : 1;
        $levelGroup = $customerExperience ? $customerExperience->current_level_group : 'Bronze';
        $totalXp = $customerExperience ? $customerExperience->total_xp : 0;
        $xpToNextLevel = $customerExperience ? $customerExperience->xp_to_next_level : 100;
        $xpInCurrentLevel = $customerExperience ? $customerExperience->xp_in_current_level : 0;

        // Get gamification config for this marketplace
        $config = GamificationConfig::where('marketplace_client_id', $client->id)->first();
        $pointsName = $config ? ($config->points_name['ro'] ?? 'Puncte') : 'Puncte';
        $pointsPerCurrency = $config ? $config->points_per_currency : 2;
        $pointsValue = $config ? $config->points_value : 0.01; // 1 point = 0.01 RON

        // Get badges count
        $badgesEarned = CustomerBadge::where('marketplace_customer_id', $customer->id)->count();
        $totalBadges = Badge::where('marketplace_client_id', $client->id)
            ->where('is_active', true)
            ->where('is_secret', false)
            ->count();

        // Get tier info based on level
        $tiers = [
            ['name' => 'Bronze', 'min_level' => 1, 'max_level' => 5, 'color' => '#CD7F32', 'benefits' => ['Acces la oferte exclusive']],
            ['name' => 'Silver', 'min_level' => 6, 'max_level' => 10, 'color' => '#C0C0C0', 'benefits' => ['10% bonus puncte', 'Acces prioritar la bilete']],
            ['name' => 'Gold', 'min_level' => 11, 'max_level' => 15, 'color' => '#FFD700', 'benefits' => ['25% bonus puncte', 'Acces VIP la evenimente', 'Suport prioritar']],
            ['name' => 'Platinum', 'min_level' => 16, 'max_level' => 99, 'color' => '#E5E4E2', 'benefits' => ['50% bonus puncte', 'Meet & Greet exclusiv', 'Upgrades gratuite']],
        ];

        $currentTier = $tiers[0];
        $nextTier = $tiers[1] ?? null;
        foreach ($tiers as $index => $tier) {
            if ($level >= $tier['min_level'] && $level <= $tier['max_level']) {
                $currentTier = $tier;
                $nextTier = $tiers[$index + 1] ?? null;
                break;
            }
        }

        return $this->success([
            'points' => [
                'balance' => $pointsBalance,
                'lifetime_earned' => $lifetimePoints,
                'lifetime_spent' => $lifetimeSpent,
                'value_in_currency' => round($pointsBalance * $pointsValue, 2),
                'currency' => 'RON',
                'name' => $pointsName,
                'earn_rate' => $pointsPerCurrency . ' puncte / RON',
                'redeem_rate' => '100 puncte = ' . ($pointsValue * 100) . ' RON',
            ],
            'level' => [
                'current' => $level,
                'name' => $levelGroup,
                'total_xp' => $totalXp,
                'xp_in_level' => $xpInCurrentLevel,
                'xp_to_next' => $xpToNextLevel,
                'progress_percent' => $xpToNextLevel > 0 ? round(($xpInCurrentLevel / $xpToNextLevel) * 100) : 100,
            ],
            'tier' => [
                'current' => $currentTier,
                'next' => $nextTier,
                'levels_to_next' => $nextTier ? max(0, $nextTier['min_level'] - $level) : 0,
            ],
            'badges' => [
                'earned' => $badgesEarned,
                'total' => $totalBadges,
            ],
        ]);
    }

    /**
     * Get points transaction history
     */
    public function history(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $query = PointsTransaction::where('marketplace_customer_id', $customer->id)
            ->orderByDesc('created_at');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type); // earned, spent, expired, adjusted
        }

        $perPage = min((int) $request->get('per_page', 20), 50);
        $transactions = $query->paginate($perPage);

        $formatted = collect($transactions->items())->map(function ($tx) {
            // Get translated description
            $description = $tx->description;
            if (is_array($description)) {
                $locale = app()->getLocale();
                $description = $description[$locale] ?? $description['ro'] ?? $description['en'] ?? '';
            }

            return [
                'id' => $tx->id,
                'type' => $tx->type,
                'points' => $tx->points,
                'balance_after' => $tx->balance_after,
                'description' => $description,
                'action_type' => $tx->action_type,
                'reference_type' => $tx->reference_type,
                'reference_id' => $tx->reference_id,
                'created_at' => $tx->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formatted,
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Get customer's badges
     */
    public function badges(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        // Get earned badges
        $earnedBadges = CustomerBadge::where('marketplace_customer_id', $customer->id)
            ->with('badge')
            ->orderByDesc('earned_at')
            ->get()
            ->map(function ($cb) {
                return [
                    'id' => $cb->badge->id,
                    'name' => $cb->badge->getTranslation('name', 'ro'),
                    'description' => $cb->badge->getTranslation('description', 'ro'),
                    'icon_url' => $cb->badge->icon_url,
                    'color' => $cb->badge->color,
                    'category' => $cb->badge->category,
                    'rarity_level' => $cb->badge->rarity_level,
                    'xp_reward' => $cb->badge->xp_reward,
                    'earned' => true,
                    'earned_at' => $cb->earned_at->toIso8601String(),
                ];
            });

        // Get available badges (not yet earned, not secret)
        $earnedIds = $earnedBadges->pluck('id')->toArray();
        $availableBadges = Badge::where('marketplace_client_id', $client->id)
            ->where('is_active', true)
            ->where('is_secret', false)
            ->whereNotIn('id', $earnedIds)
            ->orderBy('rarity_level')
            ->get()
            ->map(function ($badge) {
                return [
                    'id' => $badge->id,
                    'name' => $badge->getTranslation('name', 'ro'),
                    'description' => $badge->getTranslation('description', 'ro'),
                    'icon_url' => $badge->icon_url,
                    'color' => $badge->color,
                    'category' => $badge->category,
                    'rarity_level' => $badge->rarity_level,
                    'xp_reward' => $badge->xp_reward,
                    'earned' => false,
                    'earned_at' => null,
                ];
            });

        return $this->success([
            'earned' => $earnedBadges,
            'available' => $availableBadges,
            'stats' => [
                'earned_count' => $earnedBadges->count(),
                'available_count' => $availableBadges->count(),
                'total_xp_from_badges' => $earnedBadges->sum('xp_reward'),
            ],
        ]);
    }

    /**
     * Get available rewards to redeem
     */
    public function availableRewards(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $customerPoints = CustomerPoints::where('marketplace_customer_id', $customer->id)->first();
        $pointsBalance = $customerPoints ? $customerPoints->balance : 0;

        $rewards = Reward::where('marketplace_client_id', $client->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            })
            ->orderBy('points_cost')
            ->get()
            ->map(function ($reward) use ($pointsBalance) {
                return [
                    'id' => $reward->id,
                    'name' => $reward->getTranslation('name', 'ro'),
                    'description' => $reward->getTranslation('description', 'ro'),
                    'image_url' => $reward->image_url,
                    'type' => $reward->type,
                    'points_cost' => $reward->points_cost,
                    'value' => $reward->value,
                    'currency' => $reward->currency ?? 'RON',
                    'min_order_value' => $reward->min_order_value,
                    'can_redeem' => $pointsBalance >= $reward->points_cost,
                    'points_needed' => max(0, $reward->points_cost - $pointsBalance),
                    'valid_until' => $reward->valid_until?->toIso8601String(),
                ];
            });

        return $this->success([
            'rewards' => $rewards,
            'points_balance' => $pointsBalance,
        ]);
    }

    /**
     * Redeem points for a reward
     */
    public function redeem(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'reward_id' => 'required|integer',
        ]);

        $reward = Reward::where('id', $validated['reward_id'])
            ->where('marketplace_client_id', $client->id)
            ->where('is_active', true)
            ->first();

        if (!$reward) {
            return $this->error('Reward not found', 404);
        }

        // Check validity
        if ($reward->valid_from && $reward->valid_from > now()) {
            return $this->error('This reward is not yet available', 400);
        }
        if ($reward->valid_until && $reward->valid_until < now()) {
            return $this->error('This reward has expired', 400);
        }

        // Check points balance
        $customerPoints = CustomerPoints::where('marketplace_customer_id', $customer->id)->first();
        $pointsBalance = $customerPoints ? $customerPoints->balance : 0;

        if ($pointsBalance < $reward->points_cost) {
            return $this->error('Insufficient points', 400);
        }

        // Check redemption limits
        if ($reward->max_redemptions_per_customer) {
            $customerRedemptions = RewardRedemption::where('marketplace_customer_id', $customer->id)
                ->where('reward_id', $reward->id)
                ->count();
            if ($customerRedemptions >= $reward->max_redemptions_per_customer) {
                return $this->error('You have reached the maximum redemptions for this reward', 400);
            }
        }

        if ($reward->max_redemptions_total) {
            $totalRedemptions = RewardRedemption::where('reward_id', $reward->id)->count();
            if ($totalRedemptions >= $reward->max_redemptions_total) {
                return $this->error('This reward is no longer available', 400);
            }
        }

        DB::beginTransaction();
        try {
            // Deduct points
            $customerPoints->balance -= $reward->points_cost;
            $customerPoints->lifetime_spent += $reward->points_cost;
            $customerPoints->save();

            // Create points transaction
            $pointsTx = PointsTransaction::create([
                'marketplace_client_id' => $client->id,
                'marketplace_customer_id' => $customer->id,
                'type' => 'spent',
                'points' => -$reward->points_cost,
                'balance_after' => $customerPoints->balance,
                'action_type' => 'reward_redemption',
                'description' => 'Redeemed: ' . $reward->getTranslation('name', 'ro'),
                'reference_type' => 'reward',
                'reference_id' => $reward->id,
            ]);

            // Generate voucher code if applicable
            $voucherCode = null;
            if (in_array($reward->type, ['voucher_code', 'fixed_discount', 'percentage_discount'])) {
                $voucherCode = strtoupper(($reward->voucher_prefix ?? 'RWD') . '-' . substr(md5(uniqid()), 0, 8));
            }

            // Create redemption record
            $redemption = RewardRedemption::create([
                'marketplace_client_id' => $client->id,
                'marketplace_customer_id' => $customer->id,
                'reward_id' => $reward->id,
                'points_spent' => $reward->points_cost,
                'points_transaction_id' => $pointsTx->id,
                'reward_snapshot' => $reward->toArray(),
                'voucher_code' => $voucherCode,
                'voucher_expires_at' => $reward->valid_until ?? now()->addDays(30),
                'status' => 'active',
            ]);

            DB::commit();

            return $this->success([
                'redemption_id' => $redemption->id,
                'voucher_code' => $voucherCode,
                'voucher_expires_at' => $redemption->voucher_expires_at->toIso8601String(),
                'points_spent' => $reward->points_cost,
                'points_balance' => $customerPoints->balance,
            ], 'Reward redeemed successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to redeem reward', 500);
        }
    }

    /**
     * Get customer's redemption history
     */
    public function redemptions(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $query = RewardRedemption::where('marketplace_customer_id', $customer->id)
            ->with('reward')
            ->orderByDesc('created_at');

        $perPage = min((int) $request->get('per_page', 20), 50);
        $redemptions = $query->paginate($perPage);

        $formatted = collect($redemptions->items())->map(function ($r) {
            return [
                'id' => $r->id,
                'reward' => [
                    'id' => $r->reward->id,
                    'name' => $r->reward->getTranslation('name', 'ro'),
                    'type' => $r->reward->type,
                ],
                'points_spent' => $r->points_spent,
                'voucher_code' => $r->voucher_code,
                'voucher_expires_at' => $r->voucher_expires_at?->toIso8601String(),
                'voucher_used' => $r->voucher_used,
                'voucher_used_at' => $r->voucher_used_at?->toIso8601String(),
                'status' => $r->status,
                'created_at' => $r->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formatted,
            'meta' => [
                'current_page' => $redemptions->currentPage(),
                'last_page' => $redemptions->lastPage(),
                'per_page' => $redemptions->perPage(),
                'total' => $redemptions->total(),
            ],
        ]);
    }

    /**
     * Require authenticated customer
     */
    protected function requireCustomer(Request $request): MarketplaceCustomer
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            abort(401, 'Unauthorized');
        }

        return $customer;
    }
}
