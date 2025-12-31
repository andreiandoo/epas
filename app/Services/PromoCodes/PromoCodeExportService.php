<?php

namespace App\Services\PromoCodes;

use Illuminate\Support\Facades\DB;

/**
 * Export promo codes to CSV/Excel formats
 */
class PromoCodeExportService
{
    /**
     * Export promo codes to CSV
     *
     * @param string $tenantId
     * @param array $filters
     * @return string CSV content
     */
    public function exportToCSV(string $tenantId, array $filters = []): string
    {
        $query = DB::table('promo_codes')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['type'])) {
            $query->where('status', $filters['type']);
        }

        $codes = $query->get();

        $csv = "Code,Name,Type,Value,Applies To,Status,Usage Count,Usage Limit,Starts At,Expires At,Created At\n";

        foreach ($codes as $code) {
            $csv .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $code->code,
                $code->name ?? '',
                $code->type,
                $code->value,
                $code->applies_to,
                $code->status,
                $code->usage_count,
                $code->usage_limit ?? '',
                $code->starts_at ?? '',
                $code->expires_at ?? '',
                $code->created_at
            );
        }

        return $csv;
    }

    /**
     * Export usage history to CSV
     *
     * @param string $promoCodeId
     * @return string CSV content
     */
    public function exportUsageToCSV(string $promoCodeId): string
    {
        $usageRecords = DB::table('promo_code_usage')
            ->where('promo_code_id', $promoCodeId)
            ->orderBy('used_at', 'desc')
            ->get();

        $csv = "Used At,Order ID,Customer ID,Customer Email,Original Amount,Discount Amount,Final Amount,IP Address\n";

        foreach ($usageRecords as $usage) {
            $csv .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $usage->used_at,
                $usage->order_id,
                $usage->customer_id ?? '',
                $usage->customer_email ?? '',
                $usage->original_amount,
                $usage->discount_amount,
                $usage->final_amount,
                $usage->ip_address ?? ''
            );
        }

        return $csv;
    }
}
