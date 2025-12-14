<?php

namespace App\Services\Gamification;

use App\Models\Customer;
use App\Models\Gamification\CustomerPoints;
use App\Models\Gamification\GamificationAction;
use App\Models\Gamification\GamificationConfig;
use App\Models\Gamification\PointsTransaction;
use App\Models\Gamification\Referral;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GamificationService
{
    // ==========================================
    // CONFIGURATION
    // ==========================================

    /**
     * Check if gamification is enabled for a tenant
     */
    public function isEnabled(int $tenantId): bool
    {
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            return false;
        }

        return $tenant->microservices()
            ->where('slug', 'gamification')
            ->wherePivot('is_active', true)
            ->exists();
    }

    /**
     * Get gamification config for a tenant
     */
    public function getConfig(int $tenantId): ?GamificationConfig
    {
        return GamificationConfig::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get or create customer points record
     */
    public function getCustomerPoints(int $tenantId, int $customerId): CustomerPoints
    {
        return CustomerPoints::getOrCreate($tenantId, $customerId);
    }

    // ==========================================
    // POINTS EARNING
    // ==========================================

    /**
     * Award points for an order
     */
    public function awardOrderPoints(
        int $tenantId,
        int $customerId,
        int $orderAmountCents,
        string $referenceType,
        int $referenceId,
        array $metadata = []
    ): ?PointsTransaction {
        if (!$this->isEnabled($tenantId)) {
            return null;
        }

        $config = $this->getConfig($tenantId);
        if (!$config) {
            return null;
        }

        // Get order action
        $action = GamificationAction::where('tenant_id', $tenantId)
            ->where('action_type', GamificationAction::ACTION_ORDER)
            ->active()
            ->first();

        if (!$action) {
            // Fall back to config percentage
            $points = $config->calculateEarnedPoints($orderAmountCents);
        } else {
            $points = $action->calculatePoints($orderAmountCents);

            // Check if action is valid and rate limits
            $customerPoints = $this->getCustomerPoints($tenantId, $customerId);
            if (!$action->isEligibleForCustomer($customerPoints) || !$action->checkRateLimits($customerId)) {
                return null;
            }
        }

        if ($points <= 0) {
            return null;
        }

        $customerPoints = $this->getCustomerPoints($tenantId, $customerId);

        return $customerPoints->addPoints($points, GamificationAction::ACTION_ORDER, [
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => [
                'en' => "Earned {$points} points from order",
                'ro' => "Ai castigat {$points} puncte din comanda",
            ],
            'metadata' => array_merge($metadata, [
                'order_amount_cents' => $orderAmountCents,
            ]),
        ]);
    }

    /**
     * Award points for a specific action
     */
    public function awardActionPoints(
        int $tenantId,
        int $customerId,
        string $actionType,
        array $options = []
    ): ?PointsTransaction {
        if (!$this->isEnabled($tenantId)) {
            return null;
        }

        $config = $this->getConfig($tenantId);
        if (!$config) {
            return null;
        }

        // Get action definition
        $action = GamificationAction::where('tenant_id', $tenantId)
            ->where('action_type', $actionType)
            ->active()
            ->currentlyValid()
            ->first();

        if (!$action) {
            // Use config defaults for known actions
            $points = match ($actionType) {
                GamificationAction::ACTION_BIRTHDAY => $config->birthday_bonus_points,
                GamificationAction::ACTION_SIGNUP => $config->signup_bonus_points,
                GamificationAction::ACTION_REFERRAL => $config->referral_bonus_points,
                default => 0,
            };

            if ($points <= 0) {
                return null;
            }
        } else {
            $customerPoints = $this->getCustomerPoints($tenantId, $customerId);

            // Check eligibility and rate limits
            if (!$action->isEligibleForCustomer($customerPoints) || !$action->checkRateLimits($customerId)) {
                return null;
            }

            $orderAmount = $options['order_amount_cents'] ?? 0;
            $points = $action->calculatePoints($orderAmount);
        }

        if ($points <= 0) {
            return null;
        }

        $customerPoints = $this->getCustomerPoints($tenantId, $customerId);

        return $customerPoints->addPoints($points, $actionType, [
            'reference_type' => $options['reference_type'] ?? null,
            'reference_id' => $options['reference_id'] ?? null,
            'description' => $options['description'] ?? [
                'en' => "Earned {$points} points",
                'ro' => "Ai castigat {$points} puncte",
            ],
            'metadata' => $options['metadata'] ?? null,
        ]);
    }

    /**
     * Award signup bonus
     */
    public function awardSignupBonus(int $tenantId, int $customerId): ?PointsTransaction
    {
        return $this->awardActionPoints($tenantId, $customerId, GamificationAction::ACTION_SIGNUP, [
            'description' => [
                'en' => 'Welcome bonus for signing up',
                'ro' => 'Bonus de bun venit pentru inregistrare',
            ],
        ]);
    }

    /**
     * Award birthday bonus
     */
    public function awardBirthdayBonus(int $tenantId, int $customerId): ?PointsTransaction
    {
        // Check if already awarded this year
        $existingThisYear = PointsTransaction::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('action_type', GamificationAction::ACTION_BIRTHDAY)
            ->whereYear('created_at', now()->year)
            ->exists();

        if ($existingThisYear) {
            return null;
        }

        return $this->awardActionPoints($tenantId, $customerId, GamificationAction::ACTION_BIRTHDAY, [
            'description' => [
                'en' => 'Happy Birthday! Here are your bonus points',
                'ro' => 'La multi ani! Iata punctele tale bonus',
            ],
        ]);
    }

    // ==========================================
    // POINTS REDEMPTION
    // ==========================================

    /**
     * Check if customer can redeem points
     */
    public function canRedeemPoints(int $tenantId, int $customerId, int $orderTotalCents): array
    {
        if (!$this->isEnabled($tenantId)) {
            return [
                'can_redeem' => false,
                'reason' => 'Gamification not enabled',
            ];
        }

        $config = $this->getConfig($tenantId);
        if (!$config) {
            return [
                'can_redeem' => false,
                'reason' => 'Gamification not configured',
            ];
        }

        $customerPoints = CustomerPoints::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->first();

        if (!$customerPoints || $customerPoints->current_balance < $config->min_redeem_points) {
            return [
                'can_redeem' => false,
                'reason' => 'Insufficient points',
                'min_required' => $config->min_redeem_points,
                'current_balance' => $customerPoints?->current_balance ?? 0,
            ];
        }

        $maxRedeemable = $config->getMaxRedeemablePoints($orderTotalCents, $customerPoints->current_balance);

        if ($maxRedeemable <= 0) {
            return [
                'can_redeem' => false,
                'reason' => 'Cannot redeem points for this order',
            ];
        }

        return [
            'can_redeem' => true,
            'current_balance' => $customerPoints->current_balance,
            'max_redeemable' => $maxRedeemable,
            'max_discount_cents' => $config->getPointsValueCents($maxRedeemable),
            'point_value_cents' => $config->point_value_cents,
            'points_name' => $config->points_name,
        ];
    }

    /**
     * Redeem points at checkout
     */
    public function redeemPoints(
        int $tenantId,
        int $customerId,
        int $points,
        int $orderTotalCents,
        string $referenceType,
        int $referenceId
    ): array {
        if (!$this->isEnabled($tenantId)) {
            return [
                'success' => false,
                'error' => 'Gamification not enabled',
            ];
        }

        $config = $this->getConfig($tenantId);
        if (!$config) {
            return [
                'success' => false,
                'error' => 'Gamification not configured',
            ];
        }

        $customerPoints = CustomerPoints::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->first();

        if (!$customerPoints) {
            return [
                'success' => false,
                'error' => 'Customer points not found',
            ];
        }

        // Validate redemption
        $maxRedeemable = $config->getMaxRedeemablePoints($orderTotalCents, $customerPoints->current_balance);

        if ($points > $maxRedeemable) {
            return [
                'success' => false,
                'error' => 'Exceeds maximum redeemable points',
                'max_redeemable' => $maxRedeemable,
            ];
        }

        if ($points > $customerPoints->current_balance) {
            return [
                'success' => false,
                'error' => 'Insufficient balance',
                'balance' => $customerPoints->current_balance,
            ];
        }

        if ($points < $config->min_redeem_points) {
            return [
                'success' => false,
                'error' => 'Below minimum redemption threshold',
                'min_required' => $config->min_redeem_points,
            ];
        }

        // Process redemption
        $discountCents = $config->getPointsValueCents($points);

        $transaction = $customerPoints->spendPoints($points, [
            'action_type' => 'redemption',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => [
                'en' => "Redeemed {$points} points for " . number_format($discountCents / 100, 2) . " {$config->currency} discount",
                'ro' => "Ai folosit {$points} puncte pentru o reducere de " . number_format($discountCents / 100, 2) . " {$config->currency}",
            ],
            'metadata' => [
                'discount_cents' => $discountCents,
                'order_total_cents' => $orderTotalCents,
            ],
        ]);

        if (!$transaction) {
            return [
                'success' => false,
                'error' => 'Failed to process redemption',
            ];
        }

        return [
            'success' => true,
            'transaction_id' => $transaction->id,
            'points_redeemed' => $points,
            'discount_cents' => $discountCents,
            'new_balance' => $customerPoints->fresh()->current_balance,
        ];
    }

    /**
     * Refund redeemed points (e.g., order cancelled)
     */
    public function refundRedeemedPoints(int $transactionId): array
    {
        $transaction = PointsTransaction::find($transactionId);

        if (!$transaction || $transaction->type !== 'spent') {
            return [
                'success' => false,
                'error' => 'Invalid transaction for refund',
            ];
        }

        $customerPoints = CustomerPoints::where('tenant_id', $transaction->tenant_id)
            ->where('customer_id', $transaction->customer_id)
            ->first();

        if (!$customerPoints) {
            return [
                'success' => false,
                'error' => 'Customer points not found',
            ];
        }

        $refundTransaction = $customerPoints->refundPoints($transaction, [
            'description' => [
                'en' => 'Points refunded due to order cancellation',
                'ro' => 'Puncte returnate datorita anularii comenzii',
            ],
        ]);

        if (!$refundTransaction) {
            return [
                'success' => false,
                'error' => 'Failed to process refund',
            ];
        }

        return [
            'success' => true,
            'transaction_id' => $refundTransaction->id,
            'points_refunded' => abs($transaction->points),
            'new_balance' => $customerPoints->fresh()->current_balance,
        ];
    }

    // ==========================================
    // REFERRALS
    // ==========================================

    /**
     * Track referral link click
     */
    public function trackReferralClick(int $tenantId, string $referralCode, array $metadata = []): ?Referral
    {
        if (!$this->isEnabled($tenantId)) {
            return null;
        }

        // Find customer with this referral code
        $customerPoints = CustomerPoints::where('tenant_id', $tenantId)
            ->where('referral_code', $referralCode)
            ->first();

        if (!$customerPoints) {
            return null;
        }

        return Referral::createPending($tenantId, $customerPoints->customer_id, $referralCode, $metadata);
    }

    /**
     * Process referral on signup
     */
    public function processReferralSignup(int $tenantId, Customer $newCustomer, ?string $referralCode = null): ?Referral
    {
        if (!$this->isEnabled($tenantId) || !$referralCode) {
            return null;
        }

        // Find pending referral
        $referral = Referral::where('tenant_id', $tenantId)
            ->where('referral_code', $referralCode)
            ->where('status', Referral::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$referral) {
            // Try to create from referral code
            $referral = Referral::findByCode($tenantId, $referralCode);
            if (!$referral) {
                return null;
            }
        }

        // Don't allow self-referrals
        if ($referral->referrer_customer_id === $newCustomer->id) {
            return null;
        }

        $referral->markSignedUp($newCustomer);

        return $referral;
    }

    /**
     * Process referral conversion on first qualifying order
     */
    public function processReferralConversion(
        int $tenantId,
        int $customerId,
        string $referenceType,
        int $referenceId
    ): ?Referral {
        if (!$this->isEnabled($tenantId)) {
            return null;
        }

        // Find signed-up referral for this customer
        $referral = Referral::where('tenant_id', $tenantId)
            ->where('referred_customer_id', $customerId)
            ->where('status', Referral::STATUS_SIGNED_UP)
            ->first();

        if (!$referral) {
            return null;
        }

        $referral->markConverted($referenceType, $referenceId);
        $referral->processPoints();

        return $referral;
    }

    // ==========================================
    // EXPIRATION
    // ==========================================

    /**
     * Process expired points for a tenant
     */
    public function processExpiredPoints(int $tenantId): int
    {
        $config = $this->getConfig($tenantId);
        if (!$config || !$config->points_expire_days) {
            return 0;
        }

        // Find earned transactions that have expired
        $expiredTransactions = PointsTransaction::where('tenant_id', $tenantId)
            ->where('type', 'earned')
            ->where('is_expired', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $totalExpired = 0;

        foreach ($expiredTransactions as $transaction) {
            // Mark as expired
            $transaction->update(['is_expired' => true]);

            // We need to track remaining points from this transaction
            // For simplicity, we'll expire proportionally from customer balance
            $customerPoints = CustomerPoints::where('tenant_id', $tenantId)
                ->where('customer_id', $transaction->customer_id)
                ->first();

            if ($customerPoints && $customerPoints->current_balance > 0) {
                // Calculate how many points to expire
                $pointsToExpire = min($transaction->points, $customerPoints->current_balance);

                if ($pointsToExpire > 0) {
                    $customerPoints->expirePoints($pointsToExpire);
                    $totalExpired += $pointsToExpire;
                }
            }
        }

        return $totalExpired;
    }

    // ==========================================
    // ANALYTICS
    // ==========================================

    /**
     * Get customer points summary for display
     */
    public function getCustomerSummary(int $tenantId, int $customerId): array
    {
        if (!$this->isEnabled($tenantId)) {
            return ['enabled' => false];
        }

        $config = $this->getConfig($tenantId);
        if (!$config) {
            return ['enabled' => false];
        }

        $customerPoints = CustomerPoints::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->first();

        if (!$customerPoints) {
            $customerPoints = CustomerPoints::getOrCreate($tenantId, $customerId);
        }

        $nextTier = $config->getNextTier($customerPoints->tier_points);

        return [
            'enabled' => true,
            'current_balance' => $customerPoints->current_balance,
            'total_earned' => $customerPoints->total_earned,
            'total_spent' => $customerPoints->total_spent,
            'current_tier' => $customerPoints->current_tier,
            'tier_points' => $customerPoints->tier_points,
            'next_tier' => $nextTier,
            'points_to_next_tier' => $nextTier ? $nextTier['min_points'] - $customerPoints->tier_points : null,
            'referral_code' => $customerPoints->referral_code,
            'referral_link' => $customerPoints->getReferralLink(),
            'referral_count' => $customerPoints->referral_count,
            'points_name' => $config->points_name,
            'points_name_singular' => $config->points_name_singular,
            'point_value_cents' => $config->point_value_cents,
            'currency' => $config->currency,
            'expires_at' => $customerPoints->points_expire_at,
        ];
    }

    /**
     * Get points history for customer
     */
    public function getPointsHistory(int $tenantId, int $customerId, int $limit = 20, int $offset = 0): array
    {
        $transactions = PointsTransaction::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();

        $total = PointsTransaction::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->count();

        return [
            'items' => $transactions->map(function ($t) {
                return [
                    'id' => $t->id,
                    'type' => $t->type,
                    'type_label' => $t->getDisplayType(),
                    'points' => $t->points,
                    'formatted_points' => $t->getFormattedPoints(),
                    'balance_after' => $t->balance_after,
                    'action_type' => $t->action_type,
                    'action_label' => $t->getActionTypeLabel(),
                    'description' => $t->getTranslation('description', app()->getLocale()),
                    'created_at' => $t->created_at->toIso8601String(),
                    'expires_at' => $t->expires_at?->toIso8601String(),
                ];
            }),
            'total' => $total,
            'has_more' => ($offset + $limit) < $total,
        ];
    }
}
