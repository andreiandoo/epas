<?php

namespace App\Services\PromoCodes;

use Illuminate\Support\Facades\DB;

/**
 * Analyze promo code usage patterns and detect fraud
 */
class PromoCodeUsageAnalyzer
{
    /**
     * Get detailed usage history with filters
     *
     * @param string $promoCodeId
     * @param array $filters
     * @return array
     */
    public function getUsageHistory(string $promoCodeId, array $filters = []): array
    {
        $query = DB::table('promo_code_usage')
            ->where('promo_code_id', $promoCodeId);

        // Apply filters
        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (isset($filters['date_from'])) {
            $query->where('used_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('used_at', '<=', $filters['date_to']);
        }

        if (isset($filters['min_discount'])) {
            $query->where('discount_amount', '>=', $filters['min_discount']);
        }

        // Ordering and pagination
        $orderBy = $filters['order_by'] ?? 'used_at';
        $orderDir = $filters['order_dir'] ?? 'desc';
        $query->orderBy($orderBy, $orderDir);

        $limit = min($filters['limit'] ?? 100, 500);
        $offset = $filters['offset'] ?? 0;

        $usage = $query->limit($limit)->offset($offset)->get();

        return array_map(fn($u) => (array) $u, $usage->toArray());
    }

    /**
     * Detect potential fraud patterns
     *
     * @param string $promoCodeId
     * @return array Fraud indicators
     */
    public function detectFraud(string $promoCodeId): array
    {
        $indicators = [];

        // Check for same IP address excessive usage
        $ipUsage = DB::table('promo_code_usage')
            ->where('promo_code_id', $promoCodeId)
            ->select('ip_address', DB::raw('COUNT(*) as count'))
            ->groupBy('ip_address')
            ->having('count', '>', 10)
            ->get();

        if ($ipUsage->count() > 0) {
            $indicators[] = [
                'type' => 'excessive_ip_usage',
                'severity' => 'high',
                'description' => 'Multiple uses from same IP address',
                'data' => $ipUsage->toArray(),
            ];
        }

        // Check for rapid succession usage (within 1 minute)
        $rapidUsage = DB::select("
            SELECT
                customer_id,
                COUNT(*) as uses,
                MIN(used_at) as first_use,
                MAX(used_at) as last_use
            FROM promo_code_usage
            WHERE promo_code_id = ?
            GROUP BY customer_id, TO_CHAR(used_at, 'YYYY-MM-DD HH24:MI')
            HAVING COUNT(*) > 3
        ", [$promoCodeId]);

        if (count($rapidUsage) > 0) {
            $indicators[] = [
                'type' => 'rapid_usage',
                'severity' => 'medium',
                'description' => 'Multiple uses in rapid succession',
                'data' => $rapidUsage,
            ];
        }

        // Check for unusually high discount amounts
        $avgDiscount = DB::table('promo_code_usage')
            ->where('promo_code_id', $promoCodeId)
            ->avg('discount_amount');

        $highDiscounts = DB::table('promo_code_usage')
            ->where('promo_code_id', $promoCodeId)
            ->where('discount_amount', '>', $avgDiscount * 3)
            ->get();

        if ($highDiscounts->count() > 0) {
            $indicators[] = [
                'type' => 'unusual_discount_amounts',
                'severity' => 'low',
                'description' => 'Discount amounts significantly higher than average',
                'data' => [
                    'average' => $avgDiscount,
                    'flagged_count' => $highDiscounts->count(),
                ],
            ];
        }

        // Check for same customer multiple uses (if per-customer limit exists)
        $customerOveruse = DB::table('promo_code_usage as pcu')
            ->join('promo_codes as pc', 'pcu.promo_code_id', '=', 'pc.id')
            ->where('pcu.promo_code_id', $promoCodeId)
            ->whereNotNull('pc.usage_limit_per_customer')
            ->select('pcu.customer_id', DB::raw('COUNT(*) as uses'), 'pc.usage_limit_per_customer')
            ->groupBy('pcu.customer_id', 'pc.usage_limit_per_customer')
            ->havingRaw('COUNT(*) > pc.usage_limit_per_customer')
            ->get();

        if ($customerOveruse->count() > 0) {
            $indicators[] = [
                'type' => 'customer_limit_exceeded',
                'severity' => 'high',
                'description' => 'Customers exceeding per-customer usage limits',
                'data' => $customerOveruse->toArray(),
            ];
        }

        return [
            'has_fraud_indicators' => count($indicators) > 0,
            'indicator_count' => count($indicators),
            'indicators' => $indicators,
        ];
    }

    /**
     * Get usage analytics over time
     *
     * @param string $promoCodeId
     * @param string $groupBy day|week|month
     * @return array
     */
    public function getUsageTimeline(string $promoCodeId, string $groupBy = 'day'): array
    {
        $dateFormat = match($groupBy) {
            'week' => 'IYYY-IW',
            'month' => 'YYYY-MM',
            default => 'YYYY-MM-DD',
        };

        $timeline = DB::select("
            SELECT
                TO_CHAR(used_at, ?) as period,
                COUNT(*) as uses,
                SUM(discount_amount) as total_discount,
                SUM(original_amount) as total_revenue,
                COUNT(DISTINCT customer_id) as unique_customers
            FROM promo_code_usage
            WHERE promo_code_id = ?
            GROUP BY period
            ORDER BY period ASC
        ", [$dateFormat, $promoCodeId]);

        return array_map(fn($t) => (array) $t, $timeline);
    }
}
