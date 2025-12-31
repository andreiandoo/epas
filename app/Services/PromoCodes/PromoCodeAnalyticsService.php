<?php

namespace App\Services\PromoCodes;

use Illuminate\Support\Facades\DB;

/**
 * Advanced analytics for promo codes including A/B testing
 */
class PromoCodeAnalyticsService
{
    /**
     * Get comprehensive analytics dashboard data
     *
     * @param string $tenantId
     * @param array $filters
     * @return array
     */
    public function getDashboard(string $tenantId, array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? now()->subMonth();
        $dateTo = $filters['date_to'] ?? now();

        // Overall stats
        $overall = DB::table('promo_codes')
            ->where('tenant_id', $tenantId)
            ->selectRaw('
                COUNT(*) as total_codes,
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_codes,
                SUM(usage_count) as total_uses
            ')
            ->first();

        // Revenue impact
        $revenue = DB::table('promo_code_usage')
            ->join('promo_codes', 'promo_code_usage.promo_code_id', '=', 'promo_codes.id')
            ->where('promo_codes.tenant_id', $tenantId)
            ->whereBetween('promo_code_usage.used_at', [$dateFrom, $dateTo])
            ->selectRaw('
                SUM(discount_amount) as total_discount,
                SUM(original_amount) as total_revenue,
                COUNT(DISTINCT customer_id) as unique_customers,
                AVG(discount_amount) as avg_discount
            ')
            ->first();

        // Top performing codes
        $topCodes = DB::table('promo_codes')
            ->leftJoin('promo_code_usage', 'promo_codes.id', '=', 'promo_code_usage.promo_code_id')
            ->where('promo_codes.tenant_id', $tenantId)
            ->whereBetween('promo_code_usage.used_at', [$dateFrom, $dateTo])
            ->select('promo_codes.code', 'promo_codes.type', 'promo_codes.value')
            ->selectRaw('
                COUNT(promo_code_usage.id) as uses,
                SUM(promo_code_usage.discount_amount) as total_discount
            ')
            ->groupBy('promo_codes.id', 'promo_codes.code', 'promo_codes.type', 'promo_codes.value')
            ->orderByDesc('uses')
            ->limit(10)
            ->get();

        // Category breakdown
        $categories = DB::table('promo_codes')
            ->leftJoin('promo_code_usage', 'promo_codes.id', '=', 'promo_code_usage.promo_code_id')
            ->where('promo_codes.tenant_id', $tenantId)
            ->whereNotNull('promo_codes.category')
            ->select('promo_codes.category')
            ->selectRaw('
                COUNT(DISTINCT promo_codes.id) as codes_count,
                COUNT(promo_code_usage.id) as uses,
                SUM(promo_code_usage.discount_amount) as total_discount
            ')
            ->groupBy('promo_codes.category')
            ->get();

        return [
            'overall' => [
                'total_codes' => $overall->total_codes,
                'active_codes' => $overall->active_codes,
                'total_uses' => $overall->total_uses,
            ],
            'revenue_impact' => [
                'total_discount' => (float) ($revenue->total_discount ?? 0),
                'total_revenue' => (float) ($revenue->total_revenue ?? 0),
                'unique_customers' => (int) ($revenue->unique_customers ?? 0),
                'avg_discount' => (float) ($revenue->avg_discount ?? 0),
            ],
            'top_codes' => array_map(fn($c) => (array) $c, $topCodes->toArray()),
            'categories' => array_map(fn($c) => (array) $c, $categories->toArray()),
        ];
    }

    /**
     * Run A/B test analysis for variant codes
     *
     * @param string $campaignId
     * @return array
     */
    public function abTestAnalysis(string $campaignId): array
    {
        $variants = DB::table('promo_codes')
            ->leftJoin('promo_code_usage', 'promo_codes.id', '=', 'promo_code_usage.promo_code_id')
            ->where('promo_codes.campaign_id', $campaignId)
            ->whereNotNull('promo_codes.variant')
            ->select('promo_codes.variant', 'promo_codes.code', 'promo_codes.type', 'promo_codes.value')
            ->selectRaw('
                COUNT(DISTINCT promo_codes.id) as code_count,
                COUNT(promo_code_usage.id) as uses,
                COUNT(DISTINCT promo_code_usage.customer_id) as unique_users,
                SUM(promo_code_usage.discount_amount) as total_discount,
                SUM(promo_code_usage.original_amount) as total_revenue,
                AVG(promo_codes.conversion_rate) as avg_conversion_rate
            ')
            ->groupBy('promo_codes.variant', 'promo_codes.code', 'promo_codes.type', 'promo_codes.value')
            ->get();

        $variantData = array_map(function($v) {
            $conversion = ($v->avg_conversion_rate ?? 0);
            $revenuePerUser = $v->unique_users > 0 ? $v->total_revenue / $v->unique_users : 0;

            return [
                'variant' => $v->variant,
                'code' => $v->code,
                'type' => $v->type,
                'value' => $v->value,
                'uses' => $v->uses,
                'unique_users' => $v->unique_users,
                'total_discount' => (float) $v->total_discount,
                'total_revenue' => (float) $v->total_revenue,
                'conversion_rate' => (float) $conversion,
                'revenue_per_user' => (float) $revenuePerUser,
            ];
        }, $variants->toArray());

        // Determine winner based on revenue per user
        $winner = collect($variantData)->sortByDesc('revenue_per_user')->first();

        return [
            'campaign_id' => $campaignId,
            'variants' => $variantData,
            'winner' => $winner,
            'sample_size' => array_sum(array_column($variantData, 'uses')),
        ];
    }

    /**
     * Get referral source analytics
     *
     * @param string $tenantId
     * @return array
     */
    public function referralAnalytics(string $tenantId): array
    {
        $referrals = DB::table('promo_codes')
            ->leftJoin('promo_code_usage', 'promo_codes.id', '=', 'promo_code_usage.promo_code_id')
            ->where('promo_codes.tenant_id', $tenantId)
            ->whereNotNull('promo_codes.referral_source')
            ->select('promo_codes.referral_source')
            ->selectRaw('
                COUNT(DISTINCT promo_codes.id) as codes_count,
                COUNT(promo_code_usage.id) as uses,
                COUNT(DISTINCT promo_code_usage.customer_id) as unique_customers,
                SUM(promo_code_usage.discount_amount) as total_discount,
                SUM(promo_code_usage.original_amount) as total_revenue
            ')
            ->groupBy('promo_codes.referral_source')
            ->orderByDesc('total_revenue')
            ->get();

        return array_map(fn($r) => (array) $r, $referrals->toArray());
    }
}
