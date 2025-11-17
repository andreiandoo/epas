<?php

namespace App\Services\PromoCodes;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Promo Code Service
 *
 * Manages promo/voucher codes for tenants including:
 * - Code generation and management
 * - Validation and application
 * - Usage tracking
 * - Reporting and analytics
 */
class PromoCodeService
{
    /**
     * Create a new promo code
     *
     * @param string $tenantId
     * @param array $data
     * @return array
     */
    public function create(string $tenantId, array $data): array
    {
        $promoCodeId = (string) Str::uuid();

        // Generate code if not provided
        $code = $data['code'] ?? $this->generateCode();

        // Ensure code is unique for this tenant
        $existing = DB::table('promo_codes')
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first();

        if ($existing) {
            throw new \Exception("Promo code '{$code}' already exists for this tenant");
        }

        DB::table('promo_codes')->insert([
            'id' => $promoCodeId,
            'tenant_id' => $tenantId,
            'code' => strtoupper($code),
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'type' => $data['type'], // 'fixed' or 'percentage'
            'value' => $data['value'],
            'applies_to' => $data['applies_to'] ?? 'cart',
            'event_id' => $data['event_id'] ?? null,
            'ticket_type_id' => $data['ticket_type_id'] ?? null,
            'min_purchase_amount' => $data['min_purchase_amount'] ?? null,
            'max_discount_amount' => $data['max_discount_amount'] ?? null,
            'min_tickets' => $data['min_tickets'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? null,
            'usage_count' => 0,
            'starts_at' => $data['starts_at'] ?? now(),
            'expires_at' => $data['expires_at'] ?? null,
            'status' => $data['status'] ?? 'active',
            'is_public' => $data['is_public'] ?? true,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_by' => $data['created_by'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->getById($promoCodeId);
    }

    /**
     * Update a promo code
     *
     * @param string $promoCodeId
     * @param array $data
     * @return array
     */
    public function update(string $promoCodeId, array $data): array
    {
        $updateData = array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'min_purchase_amount' => $data['min_purchase_amount'] ?? null,
            'max_discount_amount' => $data['max_discount_amount'] ?? null,
            'min_tickets' => $data['min_tickets'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'usage_limit_per_customer' => $data['usage_limit_per_customer'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'status' => $data['status'] ?? null,
            'is_public' => $data['is_public'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
        ], fn($value) => $value !== null);

        if (!empty($updateData)) {
            $updateData['updated_at'] = now();

            DB::table('promo_codes')
                ->where('id', $promoCodeId)
                ->update($updateData);
        }

        return $this->getById($promoCodeId);
    }

    /**
     * Deactivate a promo code
     *
     * @param string $promoCodeId
     * @return bool
     */
    public function deactivate(string $promoCodeId): bool
    {
        return DB::table('promo_codes')
            ->where('id', $promoCodeId)
            ->update([
                'status' => 'inactive',
                'updated_at' => now(),
            ]) > 0;
    }

    /**
     * Delete a promo code (soft delete)
     *
     * @param string $promoCodeId
     * @return bool
     */
    public function delete(string $promoCodeId): bool
    {
        return DB::table('promo_codes')
            ->where('id', $promoCodeId)
            ->update([
                'deleted_at' => now(),
            ]) > 0;
    }

    /**
     * Get promo code by ID
     *
     * @param string $promoCodeId
     * @return array
     */
    public function getById(string $promoCodeId): array
    {
        $promoCode = DB::table('promo_codes')
            ->where('id', $promoCodeId)
            ->whereNull('deleted_at')
            ->first();

        if (!$promoCode) {
            throw new \Exception('Promo code not found');
        }

        return $this->formatPromoCode($promoCode);
    }

    /**
     * Get promo code by code string
     *
     * @param string $tenantId
     * @param string $code
     * @return array|null
     */
    public function getByCode(string $tenantId, string $code): ?array
    {
        $promoCode = DB::table('promo_codes')
            ->where('tenant_id', $tenantId)
            ->where('code', strtoupper($code))
            ->whereNull('deleted_at')
            ->first();

        return $promoCode ? $this->formatPromoCode($promoCode) : null;
    }

    /**
     * List promo codes for a tenant
     *
     * @param string $tenantId
     * @param array $filters
     * @return array
     */
    public function list(string $tenantId, array $filters = []): array
    {
        $query = DB::table('promo_codes')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['applies_to'])) {
            $query->where('applies_to', $filters['applies_to']);
        }

        if (isset($filters['event_id'])) {
            $query->where('event_id', $filters['event_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                  ->orWhere('name', 'LIKE', "%{$search}%");
            });
        }

        // Ordering
        $orderBy = $filters['order_by'] ?? 'created_at';
        $orderDir = $filters['order_dir'] ?? 'desc';
        $query->orderBy($orderBy, $orderDir);

        // Pagination
        $limit = min($filters['limit'] ?? 50, 100);
        $offset = $filters['offset'] ?? 0;

        $promoCodes = $query->limit($limit)->offset($offset)->get();

        return array_map(fn($pc) => $this->formatPromoCode($pc), $promoCodes->toArray());
    }

    /**
     * Record promo code usage
     *
     * @param string $promoCodeId
     * @param string $orderId
     * @param array $usageData
     * @return string Usage record ID
     */
    public function recordUsage(string $promoCodeId, string $orderId, array $usageData): string
    {
        $usageId = (string) Str::uuid();

        $promoCode = $this->getById($promoCodeId);

        DB::table('promo_code_usage')->insert([
            'id' => $usageId,
            'promo_code_id' => $promoCodeId,
            'tenant_id' => $promoCode['tenant_id'],
            'order_id' => $orderId,
            'customer_id' => $usageData['customer_id'] ?? null,
            'customer_email' => $usageData['customer_email'] ?? null,
            'original_amount' => $usageData['original_amount'],
            'discount_amount' => $usageData['discount_amount'],
            'final_amount' => $usageData['final_amount'],
            'applied_to' => isset($usageData['applied_to']) ? json_encode($usageData['applied_to']) : null,
            'notes' => $usageData['notes'] ?? null,
            'ip_address' => $usageData['ip_address'] ?? null,
            'used_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Increment usage count
        DB::table('promo_codes')
            ->where('id', $promoCodeId)
            ->increment('usage_count');

        // Check if usage limit reached and update status
        $this->updateStatusIfNeeded($promoCodeId);

        return $usageId;
    }

    /**
     * Get usage statistics for a promo code
     *
     * @param string $promoCodeId
     * @return array
     */
    public function getUsageStats(string $promoCodeId): array
    {
        $promoCode = $this->getById($promoCodeId);

        $stats = [
            'usage_count' => $promoCode['usage_count'],
            'usage_limit' => $promoCode['usage_limit'],
            'total_discount_given' => 0,
            'total_revenue_affected' => 0,
            'unique_customers' => 0,
            'average_discount' => 0,
        ];

        $usage = DB::table('promo_code_usage')
            ->where('promo_code_id', $promoCodeId)
            ->selectRaw('
                SUM(discount_amount) as total_discount,
                SUM(original_amount) as total_original,
                COUNT(DISTINCT customer_id) as unique_customers,
                AVG(discount_amount) as avg_discount
            ')
            ->first();

        if ($usage) {
            $stats['total_discount_given'] = (float) $usage->total_discount;
            $stats['total_revenue_affected'] = (float) $usage->total_original;
            $stats['unique_customers'] = (int) $usage->unique_customers;
            $stats['average_discount'] = (float) $usage->avg_discount;
        }

        return $stats;
    }

    /**
     * Generate a random promo code
     *
     * @param int $length
     * @return string
     */
    protected function generateCode(int $length = 8): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $code;
    }

    /**
     * Update promo code status if needed (expired, depleted, etc.)
     *
     * @param string $promoCodeId
     * @return void
     */
    protected function updateStatusIfNeeded(string $promoCodeId): void
    {
        $promoCode = DB::table('promo_codes')->where('id', $promoCodeId)->first();

        if (!$promoCode || $promoCode->status !== 'active') {
            return;
        }

        $newStatus = null;

        // Check if usage limit reached
        if ($promoCode->usage_limit && $promoCode->usage_count >= $promoCode->usage_limit) {
            $newStatus = 'depleted';
        }

        // Check if expired
        if ($promoCode->expires_at && now()->isAfter($promoCode->expires_at)) {
            $newStatus = 'expired';
        }

        if ($newStatus) {
            DB::table('promo_codes')
                ->where('id', $promoCodeId)
                ->update([
                    'status' => $newStatus,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Format promo code object for API responses
     *
     * @param object $promoCode
     * @return array
     */
    protected function formatPromoCode(object $promoCode): array
    {
        return [
            'id' => $promoCode->id,
            'tenant_id' => $promoCode->tenant_id,
            'code' => $promoCode->code,
            'name' => $promoCode->name,
            'description' => $promoCode->description,
            'type' => $promoCode->type,
            'value' => (float) $promoCode->value,
            'applies_to' => $promoCode->applies_to,
            'event_id' => $promoCode->event_id,
            'ticket_type_id' => $promoCode->ticket_type_id,
            'min_purchase_amount' => $promoCode->min_purchase_amount ? (float) $promoCode->min_purchase_amount : null,
            'max_discount_amount' => $promoCode->max_discount_amount ? (float) $promoCode->max_discount_amount : null,
            'min_tickets' => $promoCode->min_tickets,
            'usage_limit' => $promoCode->usage_limit,
            'usage_limit_per_customer' => $promoCode->usage_limit_per_customer,
            'usage_count' => $promoCode->usage_count,
            'starts_at' => $promoCode->starts_at,
            'expires_at' => $promoCode->expires_at,
            'status' => $promoCode->status,
            'is_public' => (bool) $promoCode->is_public,
            'metadata' => $promoCode->metadata ? json_decode($promoCode->metadata, true) : null,
            'created_by' => $promoCode->created_by,
            'created_at' => $promoCode->created_at,
            'updated_at' => $promoCode->updated_at,
        ];
    }
}
