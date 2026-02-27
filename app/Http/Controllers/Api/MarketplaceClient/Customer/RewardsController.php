<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
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
use Illuminate\Support\Facades\Log;

class RewardsController extends BaseController
{
    /**
     * Get customer rewards summary
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        // Auto-sync points from orders if no points record exists
        $customerPoints = CustomerPoints::where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$customerPoints) {
            $customerPoints = $this->syncPointsFromOrders($customer, $client);
        }

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
        $pointsNameRaw = $config->points_name ?? 'Puncte';
        $pointsName = is_array($pointsNameRaw) ? ($pointsNameRaw['ro'] ?? 'Puncte') : $pointsNameRaw;
        $pointsPerCurrency = $config ? ($config->earn_percentage ?? 5) : 5;
        $pointsValue = $config ? $config->point_value : 0.01; // 1 point = 0.01 RON

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
     * Get customer's badges — evaluates conditions from marketplace order data
     */
    public function badges(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        // Build context from real marketplace data
        $context = $this->buildBadgeContext($customer, $client);

        // Get all active badges for this marketplace
        $allBadges = Badge::where('marketplace_client_id', $client->id)
            ->where('is_active', true)
            ->where('is_secret', false)
            ->orderBy('sort_order')
            ->get();

        // Get already-earned badge IDs
        $earnedBadgeIds = CustomerBadge::where('marketplace_customer_id', $customer->id)
            ->pluck('badge_id')
            ->toArray();

        // Evaluate and auto-award badges that aren't yet earned
        $newlyEarned = [];
        foreach ($allBadges as $badge) {
            if (in_array($badge->id, $earnedBadgeIds)) {
                continue; // Already earned
            }
            try {
                if ($badge->evaluateConditions($customer, $context)) {
                    // Award this badge
                    CustomerBadge::create([
                        'marketplace_client_id' => $client->id,
                        'marketplace_customer_id' => $customer->id,
                        'badge_id' => $badge->id,
                        'xp_awarded' => $badge->xp_reward ?? 0,
                        'points_awarded' => $badge->bonus_points ?? 0,
                        'earned_at' => now(),
                    ]);
                    $earnedBadgeIds[] = $badge->id;
                    $newlyEarned[] = $badge->id;

                    // Award bonus points if badge grants them
                    if (($badge->bonus_points ?? 0) > 0) {
                        $this->awardBadgeBonusPoints($customer, $client, $badge);
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Badge evaluation failed for badge {$badge->slug}: " . $e->getMessage());
            }
        }

        // Update total_badges_earned in context for collector-type badges
        if (!empty($newlyEarned)) {
            $context['total_badges_earned'] = count($earnedBadgeIds);
            // Re-check collector badges
            foreach ($allBadges as $badge) {
                if (in_array($badge->id, $earnedBadgeIds)) {
                    continue;
                }
                $conditions = $badge->conditions ?? [];
                if (($conditions['metric'] ?? '') === 'total_badges_earned') {
                    try {
                        if ($badge->evaluateConditions($customer, $context)) {
                            CustomerBadge::create([
                                'marketplace_client_id' => $client->id,
                                'marketplace_customer_id' => $customer->id,
                                'badge_id' => $badge->id,
                                'xp_awarded' => $badge->xp_reward ?? 0,
                                'points_awarded' => $badge->bonus_points ?? 0,
                                'earned_at' => now(),
                            ]);
                            $earnedBadgeIds[] = $badge->id;
                        }
                    } catch (\Exception $e) {
                        // Ignore
                    }
                }
            }
        }

        // Build response
        $earnedBadges = CustomerBadge::where('marketplace_customer_id', $customer->id)
            ->with('badge')
            ->orderByDesc('earned_at')
            ->get()
            ->filter(fn ($cb) => $cb->badge !== null)
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

        $availableBadges = $allBadges
            ->filter(fn ($b) => !in_array($b->id, $earnedBadgeIds))
            ->values()
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
     * Build badge evaluation context from marketplace order data
     */
    protected function buildBadgeContext(MarketplaceCustomer $customer, $client): array
    {
        // Get completed orders with event and ticket type data
        $orders = Order::where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->where('status', 'completed')
            ->with(['marketplaceEvent.eventCategory', 'items'])
            ->get();

        // Basic metrics
        $ordersCount = $orders->count();
        $totalSpent = (float) ($customer->total_spent ?? $orders->sum('total'));
        $ticketsPurchased = $orders->sum(fn ($o) => $o->items->sum('quantity'));
        $uniqueEvents = $orders->pluck('marketplace_event_id')->filter()->unique();

        // Account age
        $accountAgeDays = $customer->created_at ? now()->diffInDays($customer->created_at) : 0;

        // Early bird and VIP ticket detection (by ticket type name)
        $earlyBirdCount = 0;
        $vipCount = 0;
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $itemName = mb_strtolower($item->name ?? '');
                if (str_contains($itemName, 'early') || str_contains($itemName, 'bird')) {
                    $earlyBirdCount += $item->quantity;
                }
                if (str_contains($itemName, 'vip') || str_contains($itemName, 'premium')) {
                    $vipCount += $item->quantity;
                }
            }
        }

        // Category attendance (count orders per event category slug)
        $categoryAttendance = [];
        $uniqueCities = [];
        $lateNightEvents = 0;
        $seasonAttendance = ['summer' => 0, 'winter' => 0, 'spring' => 0, 'autumn' => 0];

        foreach ($uniqueEvents as $eventId) {
            $order = $orders->firstWhere('marketplace_event_id', $eventId);
            $event = $order?->marketplaceEvent;
            if (!$event) continue;

            // Category
            $category = $event->eventCategory;
            if ($category) {
                $slug = $category->slug ?? '';
                $categoryAttendance[$slug] = ($categoryAttendance[$slug] ?? 0) + 1;
                // Also match partial slugs (e.g., 'bilete-concerte' matches 'concerte')
                if (str_contains($slug, 'festival')) {
                    $categoryAttendance['festival'] = ($categoryAttendance['festival'] ?? 0) + 1;
                }
                if (str_contains($slug, 'comedy') || str_contains($slug, 'comedie')) {
                    $categoryAttendance['comedy'] = ($categoryAttendance['comedy'] ?? 0) + 1;
                }
            }

            // City
            $city = $event->venue_city ?? '';
            if ($city) {
                $uniqueCities[$city] = true;
            }

            // Late night (starts after 22:00)
            if ($event->starts_at) {
                $hour = (int) $event->starts_at->format('H');
                if ($hour >= 22 || $hour < 4) {
                    $lateNightEvents++;
                }

                // Season
                $month = (int) $event->starts_at->format('m');
                if ($month >= 6 && $month <= 8) $seasonAttendance['summer']++;
                elseif ($month >= 12 || $month <= 2) $seasonAttendance['winter']++;
                elseif ($month >= 3 && $month <= 5) $seasonAttendance['spring']++;
                else $seasonAttendance['autumn']++;
            }
        }

        // Referrals converted
        $referralsConverted = DB::table('marketplace_referrals')
            ->where('referrer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->where('status', 'converted')
            ->count();

        // Total badges earned (before this evaluation)
        $totalBadgesEarned = CustomerBadge::where('marketplace_customer_id', $customer->id)->count();

        return [
            'orders_count' => $ordersCount,
            'total_spent' => $totalSpent,
            'tickets_purchased' => $ticketsPurchased,
            'events_attended' => $uniqueEvents->count(),
            'first_purchase' => $ordersCount > 0,
            'account_age_days' => $accountAgeDays,
            'early_bird_purchases' => $earlyBirdCount,
            'vip_purchases' => $vipCount,
            'category_attendance' => $categoryAttendance,
            'unique_cities_visited' => count($uniqueCities),
            'late_night_events' => $lateNightEvents,
            'season_attendance' => $seasonAttendance,
            'referrals_converted' => $referralsConverted,
            'total_badges_earned' => $totalBadgesEarned,
            'unique_genres_attended' => 0, // Cannot determine genre IDs without mapping
            'reviews_submitted' => 0, // Not implemented for marketplace
        ];
    }

    /**
     * Award bonus points when a badge is earned
     */
    protected function awardBadgeBonusPoints(MarketplaceCustomer $customer, $client, Badge $badge): void
    {
        $points = $badge->bonus_points;

        $customerPoints = CustomerPoints::firstOrCreate(
            [
                'marketplace_customer_id' => $customer->id,
                'marketplace_client_id' => $client->id,
            ],
            [
                'current_balance' => 0,
                'total_earned' => 0,
                'total_spent' => 0,
                'total_expired' => 0,
                'pending_points' => 0,
            ]
        );

        $newBalance = $customerPoints->current_balance + $points;

        PointsTransaction::create([
            'marketplace_client_id' => $client->id,
            'marketplace_customer_id' => $customer->id,
            'type' => 'earned',
            'points' => $points,
            'balance_after' => $newBalance,
            'action_type' => 'badge_bonus',
            'description' => ['en' => "Badge earned: {$badge->getTranslation('name', 'en')}", 'ro' => "Badge obținut: {$badge->getTranslation('name', 'ro')}"],
            'reference_type' => 'badge',
            'reference_id' => $badge->id,
        ]);

        $customerPoints->update([
            'current_balance' => $newBalance,
            'total_earned' => $customerPoints->total_earned + $points,
            'last_earned_at' => now(),
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
     * Retroactively sync points from completed orders (one-time bootstrap)
     */
    protected function syncPointsFromOrders(MarketplaceCustomer $customer, $client): ?CustomerPoints
    {
        $orders = Order::where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->where('status', 'completed')
            ->whereNotExists(function ($q) use ($customer) {
                $q->select(DB::raw(1))
                    ->from('points_transactions')
                    ->where('marketplace_customer_id', $customer->id)
                    ->whereColumn('reference_id', 'orders.id')
                    ->where('reference_type', 'order');
            })
            ->get();

        if ($orders->isEmpty()) {
            return null;
        }

        $config = GamificationConfig::where('marketplace_client_id', $client->id)->first();
        $earnPercentage = $config ? (float) $config->earn_percentage : 5.0;

        $totalPointsEarned = 0;
        $balance = 0;

        // Get or create customer points record
        $customerPoints = CustomerPoints::firstOrCreate(
            [
                'marketplace_customer_id' => $customer->id,
                'marketplace_client_id' => $client->id,
            ],
            [
                'current_balance' => 0,
                'total_earned' => 0,
                'total_spent' => 0,
                'total_expired' => 0,
                'pending_points' => 0,
            ]
        );

        $balance = $customerPoints->current_balance;

        foreach ($orders as $order) {
            $orderTotal = (float) $order->total;
            $points = (int) floor($orderTotal * $earnPercentage / 100);

            if ($points <= 0) continue;

            $balance += $points;
            $totalPointsEarned += $points;

            PointsTransaction::create([
                'marketplace_client_id' => $client->id,
                'marketplace_customer_id' => $customer->id,
                'type' => 'earned',
                'points' => $points,
                'balance_after' => $balance,
                'action_type' => 'purchase',
                'description' => ['en' => "Points for order #{$order->order_number}", 'ro' => "Puncte pentru comanda #{$order->order_number}"],
                'reference_type' => 'order',
                'reference_id' => $order->id,
            ]);
        }

        if ($totalPointsEarned > 0) {
            $customerPoints->update([
                'current_balance' => $balance,
                'total_earned' => $customerPoints->total_earned + $totalPointsEarned,
                'last_earned_at' => now(),
            ]);

            Log::info("Retroactively awarded {$totalPointsEarned} points to marketplace customer {$customer->id} for {$orders->count()} orders");
        }

        return $customerPoints->fresh();
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
