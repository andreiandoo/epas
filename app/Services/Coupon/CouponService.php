<?php

namespace App\Services\Coupon;

use App\Models\Coupon\CouponCampaign;
use App\Models\Coupon\CouponCode;
use App\Models\Coupon\CouponGenerationJob;
use App\Models\Coupon\CouponRedemption;
use App\Models\Coupon\CouponValidationAttempt;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CouponService
{
    // ==========================================
    // CAMPAIGN MANAGEMENT
    // ==========================================

    public function createCampaign(int $tenantId, array $data): CouponCampaign
    {
        return CouponCampaign::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'minimum_purchase' => $data['minimum_purchase'] ?? null,
            'maximum_discount' => $data['maximum_discount'] ?? null,
            'applies_to' => $data['applies_to'] ?? 'all',
            'applicable_items' => $data['applicable_items'] ?? null,
            'excluded_items' => $data['excluded_items'] ?? null,
            'code_format' => $data['code_format'] ?? 'alphanumeric',
            'code_prefix' => $data['code_prefix'] ?? null,
            'code_suffix' => $data['code_suffix'] ?? null,
            'code_length' => $data['code_length'] ?? 8,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'max_uses_total' => $data['max_uses_total'] ?? null,
            'max_uses_per_user' => $data['max_uses_per_user'] ?? 1,
            'is_combinable' => $data['is_combinable'] ?? false,
            'is_first_purchase_only' => $data['is_first_purchase_only'] ?? false,
            'user_segments' => $data['user_segments'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    public function updateCampaign(CouponCampaign $campaign, array $data): CouponCampaign
    {
        $campaign->update(array_filter($data, fn($value) => $value !== null));
        return $campaign->fresh();
    }

    public function activateCampaign(CouponCampaign $campaign): CouponCampaign
    {
        $campaign->update(['status' => 'active']);
        return $campaign;
    }

    public function pauseCampaign(CouponCampaign $campaign): CouponCampaign
    {
        $campaign->update(['status' => 'paused']);
        return $campaign;
    }

    public function expireCampaign(CouponCampaign $campaign): CouponCampaign
    {
        $campaign->update(['status' => 'expired']);
        return $campaign;
    }

    public function deleteCampaign(CouponCampaign $campaign): bool
    {
        return $campaign->delete();
    }

    public function getCampaign(int $tenantId, int $campaignId): ?CouponCampaign
    {
        return CouponCampaign::where('tenant_id', $tenantId)
            ->withCount(['codes', 'redemptions'])
            ->find($campaignId);
    }

    public function listCampaigns(
        int $tenantId,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = CouponCampaign::where('tenant_id', $tenantId)
            ->withCount(['codes', 'redemptions']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['discount_type'])) {
            $query->where('discount_type', $filters['discount_type']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    public function getActiveCampaigns(int $tenantId): Collection
    {
        return CouponCampaign::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->get();
    }

    // ==========================================
    // CODE GENERATION
    // ==========================================

    public function generateCodes(
        CouponCampaign $campaign,
        int $quantity,
        ?int $userId = null
    ): CouponGenerationJob {
        $job = CouponGenerationJob::create([
            'campaign_id' => $campaign->id,
            'quantity_requested' => $quantity,
            'quantity_generated' => 0,
            'status' => 'pending',
            'started_at' => now(),
            'created_by' => $userId,
        ]);

        // Generate codes in batches
        $this->processCodeGeneration($job, $campaign);

        return $job->fresh();
    }

    protected function processCodeGeneration(
        CouponGenerationJob $job,
        CouponCampaign $campaign
    ): void {
        $job->update(['status' => 'processing']);

        $batchSize = 100;
        $generated = 0;
        $errors = [];
        $attempts = 0;
        $maxAttempts = $job->quantity_requested * 3; // Allow 3x attempts for collisions

        DB::beginTransaction();

        try {
            while ($generated < $job->quantity_requested && $attempts < $maxAttempts) {
                $code = $this->generateUniqueCode($campaign);
                $attempts++;

                // Check if code already exists
                $exists = CouponCode::where('campaign_id', $campaign->id)
                    ->where('code', $code)
                    ->exists();

                if (!$exists) {
                    CouponCode::create([
                        'campaign_id' => $campaign->id,
                        'code' => $code,
                        'status' => 'active',
                        'uses_remaining' => $campaign->max_uses_total,
                    ]);

                    $generated++;

                    // Update job progress periodically
                    if ($generated % $batchSize === 0) {
                        $job->update(['quantity_generated' => $generated]);
                    }
                }
            }

            $job->update([
                'quantity_generated' => $generated,
                'status' => 'completed',
                'completed_at' => now(),
                'error_log' => !empty($errors) ? $errors : null,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            $job->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_log' => ['error' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    protected function generateUniqueCode(CouponCampaign $campaign): string
    {
        $length = $campaign->code_length;
        $format = $campaign->code_format;

        $code = match ($format) {
            'numeric' => $this->generateNumericCode($length),
            'alphabetic' => $this->generateAlphabeticCode($length),
            'alphanumeric' => $this->generateAlphanumericCode($length),
            'custom' => $this->generateCustomCode($length, $campaign->metadata['custom_chars'] ?? null),
            default => $this->generateAlphanumericCode($length),
        };

        // Add prefix and suffix
        if ($campaign->code_prefix) {
            $code = $campaign->code_prefix . $code;
        }

        if ($campaign->code_suffix) {
            $code = $code . $campaign->code_suffix;
        }

        return strtoupper($code);
    }

    protected function generateNumericCode(int $length): string
    {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= random_int(0, 9);
        }
        return $code;
    }

    protected function generateAlphabeticCode(int $length): string
    {
        // Exclude confusing characters: I, L, O
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        return $this->generateFromChars($chars, $length);
    }

    protected function generateAlphanumericCode(int $length): string
    {
        // Exclude confusing characters: 0, 1, I, L, O
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        return $this->generateFromChars($chars, $length);
    }

    protected function generateCustomCode(int $length, ?string $customChars): string
    {
        $chars = $customChars ?? 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        return $this->generateFromChars($chars, $length);
    }

    protected function generateFromChars(string $chars, int $length): string
    {
        $code = '';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, $max)];
        }

        return $code;
    }

    public function createSingleCode(CouponCampaign $campaign, ?string $customCode = null): CouponCode
    {
        $code = $customCode ?? $this->generateUniqueCode($campaign);

        // Validate custom code doesn't exist
        if ($customCode) {
            $exists = CouponCode::where('campaign_id', $campaign->id)
                ->where('code', strtoupper($customCode))
                ->exists();

            if ($exists) {
                throw new \InvalidArgumentException("Code '{$customCode}' already exists in this campaign.");
            }
        }

        return CouponCode::create([
            'campaign_id' => $campaign->id,
            'code' => strtoupper($code),
            'status' => 'active',
            'uses_remaining' => $campaign->max_uses_total,
        ]);
    }

    public function deactivateCode(CouponCode $code): CouponCode
    {
        $code->update(['status' => 'inactive']);
        return $code;
    }

    public function activateCode(CouponCode $code): CouponCode
    {
        $code->update(['status' => 'active']);
        return $code;
    }

    public function listCodes(
        CouponCampaign $campaign,
        array $filters = [],
        int $perPage = 50
    ): LengthAwarePaginator {
        $query = CouponCode::where('campaign_id', $campaign->id)
            ->withCount('redemptions');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $query->where('code', 'ilike', "%{$filters['search']}%");
        }

        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function assignCodeToUser(CouponCode $code, int $userId): CouponCode
    {
        $code->update([
            'assigned_to' => $userId,
            'assigned_at' => now(),
        ]);

        return $code;
    }

    // ==========================================
    // CODE VALIDATION & REDEMPTION
    // ==========================================

    public function validateCode(
        int $tenantId,
        string $code,
        array $context = []
    ): array {
        $couponCode = CouponCode::whereHas('campaign', fn($q) => $q->where('tenant_id', $tenantId))
            ->where('code', strtoupper($code))
            ->with('campaign')
            ->first();

        // Log validation attempt
        $attempt = CouponValidationAttempt::create([
            'code_id' => $couponCode?->id,
            'code_entered' => strtoupper($code),
            'user_id' => $context['user_id'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'cart_total' => $context['cart_total'] ?? null,
            'cart_items' => $context['cart_items'] ?? null,
        ]);

        // Validation checks
        $result = $this->performValidation($couponCode, $context);

        // Update attempt with result
        $attempt->update([
            'is_valid' => $result['valid'],
            'validation_result' => $result,
        ]);

        return $result;
    }

    protected function performValidation(?CouponCode $couponCode, array $context): array
    {
        // Check if code exists
        if (!$couponCode) {
            return [
                'valid' => false,
                'error' => 'invalid_code',
                'message' => 'The coupon code is invalid.',
            ];
        }

        $campaign = $couponCode->campaign;

        // Check code status
        if ($couponCode->status !== 'active') {
            return [
                'valid' => false,
                'error' => 'code_inactive',
                'message' => 'This coupon code is no longer active.',
            ];
        }

        // Check campaign status
        if ($campaign->status !== 'active') {
            return [
                'valid' => false,
                'error' => 'campaign_inactive',
                'message' => 'This promotion is no longer available.',
            ];
        }

        // Check campaign dates
        if ($campaign->starts_at && $campaign->starts_at > now()) {
            return [
                'valid' => false,
                'error' => 'not_started',
                'message' => 'This promotion has not started yet.',
            ];
        }

        if ($campaign->expires_at && $campaign->expires_at <= now()) {
            return [
                'valid' => false,
                'error' => 'expired',
                'message' => 'This coupon code has expired.',
            ];
        }

        // Check uses remaining on code
        if ($couponCode->uses_remaining !== null && $couponCode->uses_remaining <= 0) {
            return [
                'valid' => false,
                'error' => 'max_uses_reached',
                'message' => 'This coupon code has reached its usage limit.',
            ];
        }

        // Check user-specific usage
        if (!empty($context['user_id']) && $campaign->max_uses_per_user) {
            $userRedemptions = CouponRedemption::where('code_id', $couponCode->id)
                ->where('user_id', $context['user_id'])
                ->count();

            if ($userRedemptions >= $campaign->max_uses_per_user) {
                return [
                    'valid' => false,
                    'error' => 'user_limit_reached',
                    'message' => 'You have already used this coupon code the maximum number of times.',
                ];
            }
        }

        // Check minimum purchase
        if ($campaign->minimum_purchase && isset($context['cart_total'])) {
            if ($context['cart_total'] < $campaign->minimum_purchase) {
                return [
                    'valid' => false,
                    'error' => 'minimum_not_met',
                    'message' => "Minimum purchase of {$campaign->minimum_purchase} required.",
                    'minimum_purchase' => $campaign->minimum_purchase,
                ];
            }
        }

        // Check first purchase only
        if ($campaign->is_first_purchase_only && !empty($context['user_id'])) {
            $hasPreviousPurchases = $context['has_previous_purchases'] ?? false;
            if ($hasPreviousPurchases) {
                return [
                    'valid' => false,
                    'error' => 'first_purchase_only',
                    'message' => 'This coupon is only valid for first-time purchases.',
                ];
            }
        }

        // Check assigned user
        if ($couponCode->assigned_to && $couponCode->assigned_to !== ($context['user_id'] ?? null)) {
            return [
                'valid' => false,
                'error' => 'not_assigned',
                'message' => 'This coupon code is assigned to another user.',
            ];
        }

        // Check applicable items
        if ($campaign->applies_to !== 'all' && !empty($context['cart_items'])) {
            $applicableItems = $this->getApplicableItems($campaign, $context['cart_items']);
            if (empty($applicableItems)) {
                return [
                    'valid' => false,
                    'error' => 'no_applicable_items',
                    'message' => 'This coupon does not apply to any items in your cart.',
                ];
            }
        }

        // Calculate discount
        $discount = $this->calculateDiscount($campaign, $context);

        return [
            'valid' => true,
            'code' => $couponCode->code,
            'campaign_name' => $campaign->name,
            'discount_type' => $campaign->discount_type,
            'discount_value' => $campaign->discount_value,
            'discount_amount' => $discount,
            'maximum_discount' => $campaign->maximum_discount,
            'minimum_purchase' => $campaign->minimum_purchase,
            'is_combinable' => $campaign->is_combinable,
        ];
    }

    protected function calculateDiscount(CouponCampaign $campaign, array $context): float
    {
        $cartTotal = $context['cart_total'] ?? 0;

        $discount = match ($campaign->discount_type) {
            'percentage' => $cartTotal * ($campaign->discount_value / 100),
            'fixed' => $campaign->discount_value,
            'free_shipping' => $context['shipping_cost'] ?? 0,
            'buy_x_get_y' => $this->calculateBuyXGetYDiscount($campaign, $context),
            default => 0,
        };

        // Apply maximum discount cap
        if ($campaign->maximum_discount && $discount > $campaign->maximum_discount) {
            $discount = $campaign->maximum_discount;
        }

        // Discount cannot exceed cart total
        if ($discount > $cartTotal) {
            $discount = $cartTotal;
        }

        return round($discount, 2);
    }

    protected function calculateBuyXGetYDiscount(CouponCampaign $campaign, array $context): float
    {
        // Implementation depends on campaign metadata configuration
        $config = $campaign->metadata ?? [];
        $buyQuantity = $config['buy_quantity'] ?? 1;
        $getQuantity = $config['get_quantity'] ?? 1;
        $discountPercent = $config['discount_percent'] ?? 100;

        // Calculate based on cart items
        // This is simplified - real implementation would check specific items
        return 0;
    }

    protected function getApplicableItems(CouponCampaign $campaign, array $cartItems): array
    {
        $applicableItems = $campaign->applicable_items ?? [];
        $excludedItems = $campaign->excluded_items ?? [];

        return array_filter($cartItems, function ($item) use ($campaign, $applicableItems, $excludedItems) {
            $itemId = $item['id'] ?? $item['product_id'] ?? null;
            $categoryId = $item['category_id'] ?? null;

            // Check exclusions first
            if (in_array($itemId, $excludedItems['products'] ?? [])) {
                return false;
            }
            if (in_array($categoryId, $excludedItems['categories'] ?? [])) {
                return false;
            }

            // Check inclusions
            if ($campaign->applies_to === 'specific_products') {
                return in_array($itemId, $applicableItems['products'] ?? []);
            }

            if ($campaign->applies_to === 'categories') {
                return in_array($categoryId, $applicableItems['categories'] ?? []);
            }

            return true;
        });
    }

    public function redeemCode(
        int $tenantId,
        string $code,
        array $context
    ): CouponRedemption {
        // Validate first
        $validation = $this->validateCode($tenantId, $code, $context);

        if (!$validation['valid']) {
            throw new \InvalidArgumentException($validation['message']);
        }

        $couponCode = CouponCode::whereHas('campaign', fn($q) => $q->where('tenant_id', $tenantId))
            ->where('code', strtoupper($code))
            ->firstOrFail();

        return DB::transaction(function () use ($couponCode, $context, $validation) {
            // Create redemption record
            $redemption = CouponRedemption::create([
                'code_id' => $couponCode->id,
                'user_id' => $context['user_id'] ?? null,
                'order_id' => $context['order_id'] ?? null,
                'order_total' => $context['cart_total'] ?? null,
                'discount_amount' => $validation['discount_amount'],
                'ip_address' => $context['ip_address'] ?? null,
                'redeemed_at' => now(),
            ]);

            // Decrement uses remaining if applicable
            if ($couponCode->uses_remaining !== null) {
                $couponCode->decrement('uses_remaining');

                // Deactivate if no uses remaining
                if ($couponCode->uses_remaining <= 0) {
                    $couponCode->update(['status' => 'used']);
                }
            }

            // Update first used timestamp
            if (!$couponCode->first_used_at) {
                $couponCode->update(['first_used_at' => now()]);
            }
            $couponCode->update(['last_used_at' => now()]);

            return $redemption;
        });
    }

    public function reverseRedemption(CouponRedemption $redemption): bool
    {
        return DB::transaction(function () use ($redemption) {
            $code = $redemption->code;

            // Restore uses remaining
            if ($code->uses_remaining !== null) {
                $code->increment('uses_remaining');

                // Reactivate if was used up
                if ($code->status === 'used') {
                    $code->update(['status' => 'active']);
                }
            }

            // Mark redemption as reversed
            $redemption->update(['reversed_at' => now()]);

            return true;
        });
    }

    // ==========================================
    // REPORTING & STATISTICS
    // ==========================================

    public function getCampaignStats(CouponCampaign $campaign): array
    {
        $redemptions = CouponRedemption::whereHas('code', fn($q) => $q->where('campaign_id', $campaign->id))
            ->whereNull('reversed_at');

        return [
            'total_codes' => $campaign->codes()->count(),
            'active_codes' => $campaign->codes()->where('status', 'active')->count(),
            'used_codes' => $campaign->codes()->where('status', 'used')->count(),
            'total_redemptions' => $redemptions->count(),
            'total_discount_given' => $redemptions->sum('discount_amount'),
            'total_order_value' => $redemptions->sum('order_total'),
            'average_order_value' => $redemptions->avg('order_total'),
            'unique_users' => $redemptions->distinct('user_id')->count('user_id'),
            'redemption_rate' => $campaign->codes()->count() > 0
                ? round(($campaign->codes()->whereHas('redemptions')->count() / $campaign->codes()->count()) * 100, 2)
                : 0,
        ];
    }

    public function getTenantStats(int $tenantId): array
    {
        $campaigns = CouponCampaign::where('tenant_id', $tenantId);
        $redemptions = CouponRedemption::whereHas('code.campaign', fn($q) => $q->where('tenant_id', $tenantId))
            ->whereNull('reversed_at');

        return [
            'total_campaigns' => $campaigns->count(),
            'active_campaigns' => $campaigns->clone()->where('status', 'active')->count(),
            'total_codes_generated' => CouponCode::whereHas('campaign', fn($q) => $q->where('tenant_id', $tenantId))->count(),
            'total_redemptions' => $redemptions->count(),
            'total_discount_given' => $redemptions->sum('discount_amount'),
            'total_revenue_from_coupons' => $redemptions->sum('order_total'),
        ];
    }

    public function getRedemptionHistory(
        int $tenantId,
        array $filters = [],
        int $perPage = 50
    ): LengthAwarePaginator {
        $query = CouponRedemption::whereHas('code.campaign', fn($q) => $q->where('tenant_id', $tenantId))
            ->with(['code.campaign', 'user']);

        if (!empty($filters['campaign_id'])) {
            $query->whereHas('code', fn($q) => $q->where('campaign_id', $filters['campaign_id']));
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('redeemed_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('redeemed_at', '<=', $filters['date_to']);
        }

        if (isset($filters['include_reversed']) && !$filters['include_reversed']) {
            $query->whereNull('reversed_at');
        }

        return $query->orderBy('redeemed_at', 'desc')->paginate($perPage);
    }

    public function exportCodes(CouponCampaign $campaign, string $format = 'csv'): string
    {
        $codes = $campaign->codes()->with('redemptions')->get();

        if ($format === 'csv') {
            $csv = "Code,Status,Uses Remaining,Assigned To,First Used,Last Used,Total Redemptions\n";

            foreach ($codes as $code) {
                $csv .= implode(',', [
                    $code->code,
                    $code->status,
                    $code->uses_remaining ?? 'unlimited',
                    $code->assigned_to ?? '',
                    $code->first_used_at?->toDateTimeString() ?? '',
                    $code->last_used_at?->toDateTimeString() ?? '',
                    $code->redemptions->count(),
                ]) . "\n";
            }

            return $csv;
        }

        return json_encode($codes->toArray(), JSON_PRETTY_PRINT);
    }
}
