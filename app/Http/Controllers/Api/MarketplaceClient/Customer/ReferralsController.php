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
                'signups' => 0,
                'conversions' => 0,
                'total_value' => 0,
                'points_earned' => 0,
                'pending_points' => 0,
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
                    'points_awarded' => $ref->points_awarded ?? 0,
                    'order_value' => $ref->order_value,
                ];
            });

        // Calculate pending rewards (referrals that registered but haven't converted yet)
        $pendingCount = DB::table('marketplace_referrals')
            ->where('referrer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->where('status', 'registered')
            ->count();

        return $this->success([
            'code' => $referralCode->code,
            'link' => $referralLink,
            'stats' => [
                'clicks' => $referralCode->clicks ?? 0,
                'registrations' => $referralCode->signups ?? 0,
                'conversions' => $referralCode->conversions ?? 0,
                'conversion_rate' => ($referralCode->signups ?? 0) > 0
                    ? round(($referralCode->conversions / $referralCode->signups) * 100, 1)
                    : 0,
                'total_earnings' => (float) ($referralCode->points_earned ?? 0),
                'pending_rewards' => (float) ($referralCode->pending_points ?? 0),
                'pending_count' => $pendingCount,
                'currency' => 'puncte',
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
     * Validate a referral code and get info for notification (public, no auth)
     */
    public function validateCode(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $validated = $request->validate([
            'code' => 'required|string|max:20',
        ]);

        $code = $validated['code'];

        // First check marketplace_referral_codes table
        $referralCode = DB::table('marketplace_referral_codes')
            ->where('code', $code)
            ->where('marketplace_client_id', $client->id)
            ->where('is_active', true)
            ->first();

        $referrer = null;
        $customerId = null;

        if ($referralCode) {
            $customerId = $referralCode->marketplace_customer_id;
            // Increment clicks
            DB::table('marketplace_referral_codes')
                ->where('id', $referralCode->id)
                ->increment('clicks');
        } else {
            // Fallback: check gamification_customer_points.referral_code
            try {
                $gamificationPoints = DB::table('gamification_customer_points')
                    ->where('referral_code', $code)
                    ->where('marketplace_client_id', $client->id)
                    ->first();

                if (!$gamificationPoints) {
                    return $this->error('Cod de referral invalid', 404);
                }

                $customerId = $gamificationPoints->marketplace_customer_id;

                // Create a record in marketplace_referral_codes for future tracking
                $referralCodeId = DB::table('marketplace_referral_codes')->insertGetId([
                    'marketplace_client_id' => $client->id,
                    'marketplace_customer_id' => $customerId,
                    'code' => $code,
                    'is_active' => true,
                    'clicks' => 1,
                    'signups' => 0,
                    'conversions' => 0,
                    'total_value' => 0,
                    'points_earned' => 0,
                    'pending_points' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                // Table may not exist, return invalid code
                return $this->error('Cod de referral invalid', 404);
            }
        }

        // Get referrer info
        $referrer = DB::table('marketplace_customers')
            ->where('id', $customerId)
            ->first();

        // Get referral settings
        $settings = $this->getReferralSettings($client->id);

        return $this->success([
            'valid' => true,
            'code' => $code,
            'referrer_name' => $referrer ? ($referrer->first_name ?? 'Un prieten') : 'Un prieten',
            'referred_reward' => $settings['referred_reward'],
            'reward_type' => $settings['reward_type'],
            'message' => 'Ai fost invitat de ' . ($referrer ? $referrer->first_name : 'un prieten') . '! Inregistreaza-te si primesti ' . $settings['referred_reward'] . ' puncte bonus.',
        ]);
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
                'marketplace_referral_codes.points_earned',
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
                    'earnings' => (float) ($item->points_earned ?? 0),
                ];
            });

        return $this->success([
            'leaderboard' => $leaders,
        ]);
    }

    /**
     * Claim pending rewards (converted referrals that haven't been paid out yet)
     */
    public function claimRewards(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $client = $this->requireClient($request);

        // Get converted referrals that haven't had points awarded yet
        $pendingReferrals = DB::table('marketplace_referrals')
            ->where('referrer_id', $customer->id)
            ->where('marketplace_client_id', $client->id)
            ->where('status', 'converted')
            ->whereNull('points_transaction_id')
            ->get();

        if ($pendingReferrals->isEmpty()) {
            return $this->error('Nu ai recompense de revendicat', 400);
        }

        $settings = $this->getReferralSettings($client->id);
        $pointsPerReferral = $settings['referrer_reward'] ?? 100;
        $totalPoints = $pendingReferrals->count() * $pointsPerReferral;

        DB::beginTransaction();
        try {
            // Get or create customer points
            $customerPoints = DB::table('gamification_customer_points')
                ->where('marketplace_customer_id', $customer->id)
                ->where('marketplace_client_id', $client->id)
                ->first();

            $newBalance = ($customerPoints->current_balance ?? 0) + $totalPoints;

            // Create points transaction
            $transactionId = DB::table('gamification_points_transactions')->insertGetId([
                'marketplace_client_id' => $client->id,
                'marketplace_customer_id' => $customer->id,
                'type' => 'earned',
                'points' => $totalPoints,
                'balance_after' => $newBalance,
                'action_type' => 'referral_reward',
                'description' => json_encode(['en' => 'Referral rewards claimed', 'ro' => 'Recompense referral revendicate']),
                'created_at' => now(),
            ]);

            // Update customer points
            if ($customerPoints) {
                DB::table('gamification_customer_points')
                    ->where('id', $customerPoints->id)
                    ->update([
                        'current_balance' => $newBalance,
                        'total_earned' => ($customerPoints->total_earned ?? 0) + $totalPoints,
                        'referral_points_earned' => ($customerPoints->referral_points_earned ?? 0) + $totalPoints,
                        'updated_at' => now(),
                    ]);
            }

            // Mark referrals as paid
            DB::table('marketplace_referrals')
                ->whereIn('id', $pendingReferrals->pluck('id'))
                ->update([
                    'points_awarded' => $pointsPerReferral,
                    'points_transaction_id' => $transactionId,
                ]);

            // Update referral code stats
            DB::table('marketplace_referral_codes')
                ->where('marketplace_customer_id', $customer->id)
                ->where('marketplace_client_id', $client->id)
                ->update([
                    'points_earned' => DB::raw('points_earned + ' . $totalPoints),
                    'pending_points' => DB::raw('GREATEST(0, pending_points - ' . $totalPoints . ')'),
                ]);

            DB::commit();

            return $this->success([
                'claimed_amount' => $totalPoints,
                'reward_type' => 'points',
                'referrals_count' => $pendingReferrals->count(),
            ], 'Recompensele au fost revendicate cu succes!');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Eroare la revendicarea recompenselor: ' . $e->getMessage(), 500);
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
        // Default settings
        $defaults = [
            'referrer_reward' => 100, // 100 points for inviter
            'referred_reward' => 50, // 50 points for new user (usable on first purchase)
            'reward_type' => 'points', // 'points' or 'credit'
            'min_purchase' => 0, // Minimum purchase for conversion
        ];

        try {
            $settings = DB::table('marketplace_client_settings')
                ->where('marketplace_client_id', $clientId)
                ->where('key', 'referral_program')
                ->first();

            if ($settings && $settings->value) {
                return json_decode($settings->value, true);
            }
        } catch (\Exception $e) {
            // Table may not exist, return defaults
        }

        return $defaults;
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
