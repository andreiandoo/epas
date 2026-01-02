<?php

namespace App\Services\Gamification;

use App\Models\Customer;
use App\Models\Gamification\CustomerPoints;
use App\Models\Gamification\CustomerExperience;
use App\Models\Gamification\Reward;
use App\Models\Gamification\RewardRedemption;
use App\Models\MarketplaceClient;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RewardsService
{
    // ==========================================
    // REWARDS LISTING
    // ==========================================

    /**
     * Get available rewards for tenant
     */
    public function getAvailableRewardsForTenant(int $tenantId, ?int $customerId = null): Collection
    {
        $query = Reward::forTenant($tenantId)
            ->available()
            ->orderBy('is_featured', 'desc')
            ->orderBy('sort_order')
            ->orderBy('points_cost');

        $rewards = $query->get();

        // If customer specified, add redemption eligibility
        if ($customerId) {
            $customerPoints = CustomerPoints::where('tenant_id', $tenantId)
                ->where('customer_id', $customerId)
                ->first();

            $customerExperience = CustomerExperience::where('tenant_id', $tenantId)
                ->where('customer_id', $customerId)
                ->first();

            $customer = Customer::find($customerId);

            $rewards = $rewards->map(function ($reward) use ($customer, $customerPoints, $customerExperience) {
                $eligibility = $customer
                    ? $reward->canBeRedeemedBy($customer, $customerPoints, $customerExperience)
                    : ['can_redeem' => false, 'errors' => ['Not logged in']];

                $reward->can_redeem = $eligibility['can_redeem'];
                $reward->eligibility_errors = $eligibility['errors'] ?? [];

                return $reward;
            });
        }

        return $rewards;
    }

    /**
     * Get available rewards for marketplace
     */
    public function getAvailableRewardsForMarketplace(int $marketplaceClientId, ?int $customerId = null): Collection
    {
        $query = Reward::forMarketplace($marketplaceClientId)
            ->available()
            ->orderBy('is_featured', 'desc')
            ->orderBy('sort_order')
            ->orderBy('points_cost');

        $rewards = $query->get();

        // If customer specified, add redemption eligibility
        if ($customerId) {
            $customerPoints = CustomerPoints::where('marketplace_client_id', $marketplaceClientId)
                ->where('customer_id', $customerId)
                ->first();

            $customerExperience = CustomerExperience::where('marketplace_client_id', $marketplaceClientId)
                ->where('customer_id', $customerId)
                ->first();

            $customer = Customer::find($customerId);

            $rewards = $rewards->map(function ($reward) use ($customer, $customerPoints, $customerExperience) {
                $eligibility = $customer
                    ? $reward->canBeRedeemedBy($customer, $customerPoints, $customerExperience)
                    : ['can_redeem' => false, 'errors' => ['Not logged in']];

                $reward->can_redeem = $eligibility['can_redeem'];
                $reward->eligibility_errors = $eligibility['errors'] ?? [];

                return $reward;
            });
        }

        return $rewards;
    }

    // ==========================================
    // REWARD REDEMPTION
    // ==========================================

    /**
     * Redeem a reward for a customer (tenant context)
     */
    public function redeemForTenant(
        int $tenantId,
        int $customerId,
        int $rewardId,
        ?int $createdBy = null
    ): array {
        return DB::transaction(function () use ($tenantId, $customerId, $rewardId, $createdBy) {
            $reward = Reward::forTenant($tenantId)->find($rewardId);
            if (!$reward) {
                return ['success' => false, 'error' => 'Reward not found'];
            }

            $customer = Customer::find($customerId);
            if (!$customer) {
                return ['success' => false, 'error' => 'Customer not found'];
            }

            $customerPoints = CustomerPoints::where('tenant_id', $tenantId)
                ->where('customer_id', $customerId)
                ->first();

            $customerExperience = CustomerExperience::where('tenant_id', $tenantId)
                ->where('customer_id', $customerId)
                ->first();

            // Check eligibility
            $eligibility = $reward->canBeRedeemedBy($customer, $customerPoints, $customerExperience);
            if (!$eligibility['can_redeem']) {
                return ['success' => false, 'error' => implode(', ', $eligibility['errors'])];
            }

            // Deduct points
            $transaction = $customerPoints->spendPoints($reward->points_cost, [
                'action_type' => 'reward_redemption',
                'reference_type' => Reward::class,
                'reference_id' => $reward->id,
                'description' => [
                    'en' => "Redeemed reward: {$reward->getTranslation('name', 'en')}",
                    'ro' => "Recompensă revendicată: {$reward->getTranslation('name', 'ro')}",
                ],
                'created_by' => $createdBy,
            ]);

            if (!$transaction) {
                return ['success' => false, 'error' => 'Failed to deduct points'];
            }

            // Create redemption record
            $redemption = RewardRedemption::create([
                'tenant_id' => $tenantId,
                'reward_id' => $reward->id,
                'customer_id' => $customerId,
                'points_spent' => $reward->points_cost,
                'points_transaction_id' => $transaction->id,
                'reward_snapshot' => [
                    'name' => $reward->name,
                    'type' => $reward->type,
                    'value' => $reward->value,
                    'currency' => $reward->currency,
                    'min_order_value' => $reward->min_order_value,
                ],
                'voucher_code' => $reward->type === 'voucher_code' ? $reward->generateVoucherCode() : null,
                'voucher_expires_at' => $reward->valid_until,
                'status' => 'active',
            ]);

            Log::info("Reward redeemed", [
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'reward_id' => $rewardId,
                'redemption_id' => $redemption->id,
            ]);

            return [
                'success' => true,
                'redemption' => $redemption,
                'voucher_code' => $redemption->voucher_code,
                'new_balance' => $customerPoints->fresh()->current_balance,
            ];
        });
    }

    /**
     * Redeem a reward for a customer (marketplace context)
     */
    public function redeemForMarketplace(
        int $marketplaceClientId,
        int $customerId,
        int $rewardId,
        ?int $createdBy = null
    ): array {
        return DB::transaction(function () use ($marketplaceClientId, $customerId, $rewardId, $createdBy) {
            $reward = Reward::forMarketplace($marketplaceClientId)->find($rewardId);
            if (!$reward) {
                return ['success' => false, 'error' => 'Reward not found'];
            }

            $customer = Customer::find($customerId);
            if (!$customer) {
                return ['success' => false, 'error' => 'Customer not found'];
            }

            $customerPoints = CustomerPoints::where('marketplace_client_id', $marketplaceClientId)
                ->where('customer_id', $customerId)
                ->first();

            $customerExperience = CustomerExperience::where('marketplace_client_id', $marketplaceClientId)
                ->where('customer_id', $customerId)
                ->first();

            // Check eligibility
            $eligibility = $reward->canBeRedeemedBy($customer, $customerPoints, $customerExperience);
            if (!$eligibility['can_redeem']) {
                return ['success' => false, 'error' => implode(', ', $eligibility['errors'])];
            }

            // Deduct points
            $transaction = $customerPoints->spendPoints($reward->points_cost, [
                'action_type' => 'reward_redemption',
                'reference_type' => Reward::class,
                'reference_id' => $reward->id,
                'description' => [
                    'en' => "Redeemed reward: {$reward->getTranslation('name', 'en')}",
                    'ro' => "Recompensă revendicată: {$reward->getTranslation('name', 'ro')}",
                ],
                'created_by' => $createdBy,
            ]);

            if (!$transaction) {
                return ['success' => false, 'error' => 'Failed to deduct points'];
            }

            // Create redemption record
            $redemption = RewardRedemption::create([
                'marketplace_client_id' => $marketplaceClientId,
                'reward_id' => $reward->id,
                'customer_id' => $customerId,
                'points_spent' => $reward->points_cost,
                'points_transaction_id' => $transaction->id,
                'reward_snapshot' => [
                    'name' => $reward->name,
                    'type' => $reward->type,
                    'value' => $reward->value,
                    'currency' => $reward->currency,
                    'min_order_value' => $reward->min_order_value,
                ],
                'voucher_code' => $reward->type === 'voucher_code' ? $reward->generateVoucherCode() : null,
                'voucher_expires_at' => $reward->valid_until,
                'status' => 'active',
            ]);

            Log::info("Reward redeemed", [
                'marketplace_client_id' => $marketplaceClientId,
                'customer_id' => $customerId,
                'reward_id' => $rewardId,
                'redemption_id' => $redemption->id,
            ]);

            return [
                'success' => true,
                'redemption' => $redemption,
                'voucher_code' => $redemption->voucher_code,
                'new_balance' => $customerPoints->fresh()->current_balance,
            ];
        });
    }

    // ==========================================
    // VOUCHER VALIDATION
    // ==========================================

    /**
     * Validate voucher code
     */
    public function validateVoucher(string $voucherCode, ?int $tenantId = null, ?int $marketplaceClientId = null): array
    {
        $query = RewardRedemption::where('voucher_code', $voucherCode);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        if ($marketplaceClientId) {
            $query->where('marketplace_client_id', $marketplaceClientId);
        }

        $redemption = $query->first();

        if (!$redemption) {
            return ['valid' => false, 'error' => 'Invalid voucher code'];
        }

        if (!$redemption->isVoucherValid()) {
            return [
                'valid' => false,
                'error' => match ($redemption->status) {
                    'used' => 'Voucher has already been used',
                    'expired' => 'Voucher has expired',
                    'cancelled' => 'Voucher has been cancelled',
                    default => 'Voucher is not valid',
                },
            ];
        }

        return [
            'valid' => true,
            'redemption' => $redemption,
            'reward_type' => $redemption->reward_type,
            'value' => $redemption->reward_snapshot['value'] ?? 0,
            'currency' => $redemption->reward_snapshot['currency'] ?? 'RON',
            'min_order_value' => $redemption->reward_snapshot['min_order_value'] ?? null,
        ];
    }

    /**
     * Apply voucher to order
     */
    public function applyVoucher(
        string $voucherCode,
        float $orderTotal,
        string $referenceType,
        int $referenceId,
        ?int $tenantId = null,
        ?int $marketplaceClientId = null
    ): array {
        $validation = $this->validateVoucher($voucherCode, $tenantId, $marketplaceClientId);

        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        $redemption = $validation['redemption'];

        // Check minimum order value
        if ($validation['min_order_value'] && $orderTotal < $validation['min_order_value']) {
            return [
                'success' => false,
                'error' => "Minimum order value of {$validation['min_order_value']} {$validation['currency']} required",
            ];
        }

        // Calculate discount
        $rewardType = $validation['reward_type'];
        $value = $validation['value'];

        $discount = match ($rewardType) {
            'fixed_discount' => min($value, $orderTotal),
            'percentage_discount' => $orderTotal * ($value / 100),
            'voucher_code' => min($value, $orderTotal),
            default => 0,
        };

        // Mark voucher as used
        $redemption->markAsUsed($referenceType, $referenceId, $discount);

        return [
            'success' => true,
            'discount' => $discount,
            'new_total' => $orderTotal - $discount,
        ];
    }

    // ==========================================
    // CUSTOMER REDEMPTIONS
    // ==========================================

    /**
     * Get customer's redemptions history
     */
    public function getCustomerRedemptions(
        int $customerId,
        ?int $tenantId = null,
        ?int $marketplaceClientId = null,
        int $limit = 20
    ): Collection {
        $query = RewardRedemption::where('customer_id', $customerId)
            ->with('reward')
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        if ($marketplaceClientId) {
            $query->where('marketplace_client_id', $marketplaceClientId);
        }

        return $query->get();
    }

    /**
     * Get customer's active (unused) vouchers
     */
    public function getActiveVouchers(
        int $customerId,
        ?int $tenantId = null,
        ?int $marketplaceClientId = null
    ): Collection {
        $query = RewardRedemption::where('customer_id', $customerId)
            ->where('status', 'active')
            ->whereNotNull('voucher_code')
            ->where('voucher_used', false)
            ->where(function ($q) {
                $q->whereNull('voucher_expires_at')
                    ->orWhere('voucher_expires_at', '>', now());
            })
            ->with('reward')
            ->orderBy('voucher_expires_at');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        if ($marketplaceClientId) {
            $query->where('marketplace_client_id', $marketplaceClientId);
        }

        return $query->get();
    }

    // ==========================================
    // EXPIRATION
    // ==========================================

    /**
     * Expire old unused vouchers
     */
    public function expireVouchers(): int
    {
        $expired = RewardRedemption::where('status', 'active')
            ->whereNotNull('voucher_expires_at')
            ->where('voucher_expires_at', '<', now())
            ->where('voucher_used', false)
            ->update(['status' => 'expired']);

        if ($expired > 0) {
            Log::info("Expired {$expired} vouchers");
        }

        return $expired;
    }
}
