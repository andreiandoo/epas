<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Tenant;
use App\Services\Gamification\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GamificationController extends Controller
{
    public function __construct(
        protected GamificationService $gamificationService
    ) {}

    private function resolveTenant(Request $request): ?Tenant
    {
        $hostname = $request->query('hostname');
        $tenantId = $request->query('tenant');

        if ($hostname) {
            $domain = Domain::where('domain', $hostname)
                ->where('is_active', true)
                ->first();
            return $domain?->tenant;
        }

        if ($tenantId) {
            return Tenant::find($tenantId);
        }

        return null;
    }

    private function hasGamificationMicroservice(Tenant $tenant): bool
    {
        return $tenant->microservices()
            ->where('slug', 'gamification')
            ->wherePivot('is_active', true)
            ->exists();
    }

    private function getCustomerId(Request $request): ?int
    {
        // Try to get from auth token
        $token = $request->bearerToken();
        if ($token) {
            $customer = \App\Models\Customer::where('api_token', $token)->first();
            if ($customer) {
                return $customer->id;
            }
        }

        // Try from request
        return $request->input('customer_id');
    }

    /**
     * Get gamification configuration for display
     */
    public function config(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasGamificationMicroservice($tenant)) {
            return response()->json(['success' => false, 'enabled' => false], 200);
        }

        $config = $this->gamificationService->getConfig($tenant->id);

        if (!$config || !$config->is_active) {
            return response()->json(['success' => true, 'enabled' => false], 200);
        }

        return response()->json([
            'success' => true,
            'enabled' => true,
            'data' => [
                'earn_percentage' => $config->earn_percentage,
                'point_value_cents' => $config->point_value_cents,
                'min_redeem_points' => $config->min_redeem_points,
                'max_redeem_percentage' => $config->max_redeem_percentage,
                'birthday_bonus_points' => $config->birthday_bonus_points,
                'signup_bonus_points' => $config->signup_bonus_points,
                'referral_bonus_points' => $config->referral_bonus_points,
                'referred_bonus_points' => $config->referred_bonus_points,
                'points_name' => $config->points_name,
                'points_name_singular' => $config->points_name_singular,
                'icon' => $config->icon,
                'currency' => $config->currency,
                'tiers' => $config->tiers,
            ],
        ]);
    }

    /**
     * Get customer points balance and summary
     */
    public function balance(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasGamificationMicroservice($tenant)) {
            return response()->json(['success' => false, 'enabled' => false], 200);
        }

        $customerId = $this->getCustomerId($request);

        if (!$customerId) {
            return response()->json(['success' => false, 'message' => 'Customer not authenticated'], 401);
        }

        $summary = $this->gamificationService->getCustomerSummary($tenant->id, $customerId);

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }

    /**
     * Get points transaction history
     */
    public function history(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasGamificationMicroservice($tenant)) {
            return response()->json(['success' => false, 'enabled' => false], 200);
        }

        $customerId = $this->getCustomerId($request);

        if (!$customerId) {
            return response()->json(['success' => false, 'message' => 'Customer not authenticated'], 401);
        }

        $limit = min($request->input('limit', 20), 100);
        $offset = $request->input('offset', 0);

        $history = $this->gamificationService->getPointsHistory($tenant->id, $customerId, $limit, $offset);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    /**
     * Check redemption eligibility for checkout
     */
    public function checkRedemption(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasGamificationMicroservice($tenant)) {
            return response()->json(['success' => false, 'enabled' => false], 200);
        }

        $customerId = $this->getCustomerId($request);

        if (!$customerId) {
            return response()->json(['success' => false, 'message' => 'Customer not authenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'order_total_cents' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->gamificationService->canRedeemPoints(
            $tenant->id,
            $customerId,
            $request->input('order_total_cents')
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Apply points at checkout
     */
    public function redeem(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasGamificationMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Gamification not enabled'], 403);
        }

        $customerId = $this->getCustomerId($request);

        if (!$customerId) {
            return response()->json(['success' => false, 'message' => 'Customer not authenticated'], 401);
        }

        $validator = Validator::make($request->all(), [
            'points' => 'required|integer|min:1',
            'order_total_cents' => 'required|integer|min:0',
            'reference_type' => 'required|string',
            'reference_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->gamificationService->redeemPoints(
            $tenant->id,
            $customerId,
            $request->input('points'),
            $request->input('order_total_cents'),
            $request->input('reference_type'),
            $request->input('reference_id')
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'data' => $result,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Get referral information
     */
    public function referral(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasGamificationMicroservice($tenant)) {
            return response()->json(['success' => false, 'enabled' => false], 200);
        }

        $customerId = $this->getCustomerId($request);

        if (!$customerId) {
            return response()->json(['success' => false, 'message' => 'Customer not authenticated'], 401);
        }

        $config = $this->gamificationService->getConfig($tenant->id);
        $customerPoints = $this->gamificationService->getCustomerPoints($tenant->id, $customerId);

        // Get referral stats
        $referrals = \App\Models\Gamification\Referral::where('tenant_id', $tenant->id)
            ->where('referrer_customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $customerPoints->referral_code,
                'referral_link' => $customerPoints->getReferralLink(),
                'total_referrals' => $customerPoints->referral_count,
                'points_earned' => $customerPoints->referral_points_earned,
                'referral_bonus' => $config?->referral_bonus_points ?? 0,
                'referred_bonus' => $config?->referred_bonus_points ?? 0,
                'recent_referrals' => $referrals->map(fn ($r) => [
                    'status' => $r->status,
                    'status_label' => $r->getStatusLabel(),
                    'points_awarded' => $r->referrer_points_awarded,
                    'created_at' => $r->created_at->toIso8601String(),
                    'converted_at' => $r->converted_at?->toIso8601String(),
                ]),
            ],
        ]);
    }

    /**
     * Track referral link click
     */
    public function trackReferral(Request $request, string $code): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasGamificationMicroservice($tenant)) {
            return response()->json(['success' => false, 'message' => 'Gamification not enabled'], 403);
        }

        $referral = $this->gamificationService->trackReferralClick($tenant->id, $code, [
            'source' => $request->input('source', 'direct'),
            'utm_source' => $request->input('utm_source'),
            'utm_medium' => $request->input('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if (!$referral) {
            return response()->json(['success' => false, 'message' => 'Invalid referral code'], 404);
        }

        $config = $this->gamificationService->getConfig($tenant->id);

        return response()->json([
            'success' => true,
            'data' => [
                'referral_id' => $referral->id,
                'bonus_points' => $config?->referred_bonus_points ?? 0,
                'message' => "Sign up to receive {$config?->referred_bonus_points} bonus points!",
            ],
        ]);
    }

    /**
     * Get how-to-earn points information
     */
    public function howToEarn(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        if (!$this->hasGamificationMicroservice($tenant)) {
            return response()->json(['success' => false, 'enabled' => false], 200);
        }

        $config = $this->gamificationService->getConfig($tenant->id);

        if (!$config) {
            return response()->json(['success' => false, 'enabled' => false], 200);
        }

        // Get active actions
        $actions = \App\Models\Gamification\GamificationAction::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->currentlyValid()
            ->orderBy('sort_order')
            ->get();

        $locale = $request->input('locale', app()->getLocale());

        $ways = $actions->map(function ($action) use ($locale, $config) {
            return [
                'action_type' => $action->action_type,
                'name' => $action->getTranslation('name', $locale),
                'description' => $action->getTranslation('description', $locale),
                'points_type' => $action->points_type,
                'points_amount' => $action->points_amount,
                'points_display' => match ($action->points_type) {
                    'fixed' => "{$action->points_amount} {$config->points_name}",
                    'percentage' => "{$action->points_amount}% of order value",
                    'multiplier' => "{$action->multiplier}x points",
                    default => "{$action->points_amount} {$config->points_name}",
                },
            ];
        });

        // Add defaults not in actions
        $defaults = [
            [
                'action_type' => 'order',
                'name' => $locale === 'ro' ? 'Comenzi' : 'Orders',
                'description' => $locale === 'ro' ? 'Castiga puncte la fiecare comanda' : 'Earn points on every order',
                'points_display' => "{$config->earn_percentage}% din valoarea comenzii",
            ],
            [
                'action_type' => 'birthday',
                'name' => $locale === 'ro' ? 'Zi de nastere' : 'Birthday',
                'description' => $locale === 'ro' ? 'Puncte bonus de ziua ta' : 'Birthday bonus points',
                'points_display' => "{$config->birthday_bonus_points} {$config->points_name}",
            ],
            [
                'action_type' => 'referral',
                'name' => $locale === 'ro' ? 'Referinte' : 'Referrals',
                'description' => $locale === 'ro' ? 'Invita prieteni si castiga puncte' : 'Invite friends and earn points',
                'points_display' => "{$config->referral_bonus_points} {$config->points_name}",
            ],
            [
                'action_type' => 'signup',
                'name' => $locale === 'ro' ? 'Inregistrare' : 'Sign Up',
                'description' => $locale === 'ro' ? 'Bonus de bun venit' : 'Welcome bonus',
                'points_display' => "{$config->signup_bonus_points} {$config->points_name}",
            ],
        ];

        // Merge defaults with actions, prioritizing actions
        $existingTypes = $ways->pluck('action_type')->toArray();
        foreach ($defaults as $default) {
            if (!in_array($default['action_type'], $existingTypes)) {
                $ways->push($default);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'ways_to_earn' => $ways->values(),
                'points_name' => $config->points_name,
                'point_value_cents' => $config->point_value_cents,
                'currency' => $config->currency,
            ],
        ]);
    }
}
