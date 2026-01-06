<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferralsController extends BaseController
{
    /**
     * Get customer's referral info and stats
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        // Get or create referral code
        $referralCode = DB::table('marketplace_referral_codes')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->first();

        if (!$referralCode) {
            // Create a new referral code
            $code = $this->generateUniqueCode($client->id);
            $referralCodeId = DB::table('marketplace_referral_codes')->insertGetId([
                'marketplace_client_id' => $client->id,
                'marketplace_customer_id' => $customer->id,
                'code' => $code,
                'is_active' => true,
                'clicks' => 0,
                'registrations' => 0,
                'conversions' => 0,
                'total_earnings' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $referralCode = DB::table('marketplace_referral_codes')->where('id', $referralCodeId)->first();
        }

        // Get referral settings for this marketplace
        $settings = $this->getReferralSettings($client->id);

        // Get referral link
        $referralLink = $this->buildReferralLink($client, $referralCode->code);

        // Get recent referrals
        $recentReferrals = DB::table('marketplace_referrals')
            ->where('referrer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($ref) {
                return [
                    'id' => $ref->id,
                    'status' => $ref->status,
                    'registered_at' => $ref->registered_at,
                    'converted_at' => $ref->converted_at,
                    'reward_amount' => $ref->reward_amount,
                    'reward_type' => $ref->reward_type,
                    'reward_claimed' => (bool) $ref->reward_claimed,
                ];
            });

        // Calculate pending rewards
        $pendingRewards = DB::table('marketplace_referrals')
            ->where('referrer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->where('status', 'converted')
            ->where('reward_claimed', false)
            ->sum('reward_amount');

        return $this->success([
            'code' => $referralCode->code,
            'link' => $referralLink,
            'stats' => [
                'clicks' => $referralCode->clicks,
                'registrations' => $referralCode->registrations,
                'conversions' => $referralCode->conversions,
                'conversion_rate' => $referralCode->registrations > 0
                    ? round(($referralCode->conversions / $referralCode->registrations) * 100, 1)
                    : 0,
                'total_earnings' => (float) $referralCode->total_earnings,
                'pending_rewards' => (float) $pendingRewards,
                'currency' => 'RON',
            ],
            'rewards' => [
                'referrer_reward' => $settings['referrer_reward'],
                'referred_reward' => $settings['referred_reward'],
                'reward_type' => $settings['reward_type'],
                'min_purchase' => $settings['min_purchase'],
            ],
            'recent_referrals' => $recentReferrals,
        ]);
    }

    /**
     * Generate a new referral code
     */
    public function regenerateCode(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        $newCode = $this->generateUniqueCode($client->id);

        DB::table('marketplace_referral_codes')
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->update([
                'code' => $newCode,
                'updated_at' => now(),
            ]);

        $referralLink = $this->buildReferralLink($client, $newCode);

        return $this->success([
            'code' => $newCode,
            'link' => $referralLink,
        ], 'Referral code regenerated');
    }

    /**
     * Track a referral click (public, no auth)
     */
    public function trackClick(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'code' => 'required|string|max:20',
        ]);

        $updated = DB::table('marketplace_referral_codes')
            ->where('code', $validated['code'])
            ->where('marketplace_client_id', $client->id)
            ->where('is_active', true)
            ->increment('clicks');

        if (!$updated) {
            return $this->error('Invalid referral code', 404);
        }

        // Store in session for later attribution
        session(['referral_code' => $validated['code']]);

        return $this->success(null, 'Click tracked');
    }

    /**
     * Get referral leaderboard
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $limit = min((int) $request->get('limit', 10), 50);

        $leaders = DB::table('marketplace_referral_codes')
            ->join('marketplace_customers', 'marketplace_referral_codes.marketplace_customer_id', '=', 'marketplace_customers.id')
            ->where('marketplace_referral_codes.marketplace_client_id', $client->id)
            ->where('marketplace_referral_codes.conversions', '>', 0)
            ->select([
                'marketplace_customers.first_name',
                'marketplace_customers.last_name',
                'marketplace_referral_codes.conversions',
                'marketplace_referral_codes.total_earnings',
            ])
            ->orderByDesc('marketplace_referral_codes.conversions')
            ->limit($limit)
            ->get()
            ->map(function ($item, $index) {
                $initials = substr($item->first_name ?? '', 0, 1) . substr($item->last_name ?? '', 0, 1);
                return [
                    'rank' => $index + 1,
                    'initials' => strtoupper($initials),
                    'name' => substr($item->first_name ?? '', 0, 1) . '***',
                    'conversions' => $item->conversions,
                    'earnings' => (float) $item->total_earnings,
                ];
            });

        return $this->success([
            'leaderboard' => $leaders,
        ]);
    }

    /**
     * Claim pending rewards
     */
    public function claimRewards(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        // Get unclaimed rewards
        $pendingReferrals = DB::table('marketplace_referrals')
            ->where('referrer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->where('status', 'converted')
            ->where('reward_claimed', false)
            ->get();

        if ($pendingReferrals->isEmpty()) {
            return $this->error('No pending rewards to claim', 400);
        }

        $totalAmount = $pendingReferrals->sum('reward_amount');
        $rewardType = $pendingReferrals->first()->reward_type;

        DB::beginTransaction();
        try {
            // Mark as claimed
            DB::table('marketplace_referrals')
                ->whereIn('id', $pendingReferrals->pluck('id'))
                ->update([
                    'reward_claimed' => true,
                    'reward_claimed_at' => now(),
                ]);

            // Add points or credit based on reward type
            if ($rewardType === 'points') {
                // Add points
                $customerPoints = DB::table('gamification_customer_points')
                    ->where('marketplace_customer_id', $customer->id)
                    ->first();

                if ($customerPoints) {
                    DB::table('gamification_customer_points')
                        ->where('id', $customerPoints->id)
                        ->update([
                            'balance' => $customerPoints->balance + $totalAmount,
                            'lifetime_earned' => $customerPoints->lifetime_earned + $totalAmount,
                            'updated_at' => now(),
                        ]);
                }

                // Create points transaction
                DB::table('gamification_points_transactions')->insert([
                    'marketplace_client_id' => $client->id,
                    'marketplace_customer_id' => $customer->id,
                    'type' => 'earned',
                    'points' => $totalAmount,
                    'balance_after' => ($customerPoints->balance ?? 0) + $totalAmount,
                    'action_type' => 'referral_reward',
                    'description' => 'Referral rewards claimed',
                    'created_at' => now(),
                ]);
            } else {
                // Add credit
                DB::table('marketplace_customers')
                    ->where('id', $customer->id)
                    ->increment('credit_balance', $totalAmount);
            }

            DB::commit();

            return $this->success([
                'claimed_amount' => (float) $totalAmount,
                'reward_type' => $rewardType,
                'referrals_count' => $pendingReferrals->count(),
            ], 'Rewards claimed successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to claim rewards', 500);
        }
    }

    /**
     * Generate unique referral code
     */
    protected function generateUniqueCode(int $clientId): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (
            DB::table('marketplace_referral_codes')
                ->where('marketplace_client_id', $clientId)
                ->where('code', $code)
                ->exists()
        );

        return $code;
    }

    /**
     * Build referral link
     */
    protected function buildReferralLink($client, string $code): string
    {
        $domain = $client->domain ?? 'bilete.online';
        return 'https://' . $domain . '/?ref=' . $code;
    }

    /**
     * Get referral settings for marketplace
     */
    protected function getReferralSettings(int $clientId): array
    {
        $settings = DB::table('marketplace_client_settings')
            ->where('marketplace_client_id', $clientId)
            ->where('key', 'referral_program')
            ->first();

        if ($settings && $settings->value) {
            return json_decode($settings->value, true);
        }

        // Default settings
        return [
            'referrer_reward' => 50, // 50 points or RON
            'referred_reward' => 25, // 25 points or RON for new user
            'reward_type' => 'points', // 'points' or 'credit'
            'min_purchase' => 0, // Minimum purchase for conversion
        ];
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
