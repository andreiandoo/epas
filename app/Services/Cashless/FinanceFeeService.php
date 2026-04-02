<?php

namespace App\Services\Cashless;

use App\Enums\FeeType;
use App\Models\Cashless\CashlessSale;
use App\Models\Cashless\FinanceFeeRule;
use App\Models\VendorSaleItem;

class FinanceFeeService
{
    /**
     * Calculate total fees for a sale based on FinanceFeeRules.
     *
     * @return array{total_fees_cents: int, breakdown: array}
     */
    public function calculateFeesForSale(CashlessSale $sale): array
    {
        $rules = FinanceFeeRule::where('festival_edition_id', $sale->festival_edition_id)
            ->where('is_active', true)
            ->where(function ($q) use ($sale) {
                $q->whereNull('vendor_id')
                    ->orWhere('vendor_id', $sale->vendor_id);
            })
            ->get();

        $totalFees = 0;
        $breakdown = [];

        foreach ($rules as $rule) {
            $fee = $this->calculateRuleFeeForSale($rule, $sale);
            if ($fee > 0) {
                $totalFees += $fee;
                $breakdown[] = [
                    'rule_id'      => $rule->id,
                    'rule_name'    => $rule->name,
                    'fee_type'     => $rule->fee_type->value,
                    'fee_cents'    => $fee,
                ];
            }
        }

        return [
            'total_fees_cents' => $totalFees,
            'breakdown'        => $breakdown,
        ];
    }

    private function calculateRuleFeeForSale(FinanceFeeRule $rule, CashlessSale $sale): int
    {
        return match ($rule->fee_type) {
            FeeType::PercentageSales => (int) round($sale->total_cents * $rule->percentage / 100),

            FeeType::FixedPerTransaction => $rule->amount_cents ?? 0,

            FeeType::PercentagePerCategory => $this->calculateCategoryFee($rule, $sale),

            // Daily/period fees are not per-sale; they're calculated by the summary job
            default => 0,
        };
    }

    private function calculateCategoryFee(FinanceFeeRule $rule, CashlessSale $sale): int
    {
        if (empty($rule->category_filter)) {
            return 0;
        }

        $matchingItems = VendorSaleItem::where('cashless_sale_id', $sale->id)
            ->where(function ($q) use ($rule) {
                $q->whereIn('product_category_name', $rule->category_filter)
                    ->orWhereIn('category_name', $rule->category_filter);
            })
            ->sum('total_cents');

        return (int) round($matchingItems * $rule->percentage / 100);
    }

    /**
     * Calculate daily fixed fees for a vendor on a given date.
     */
    public function calculateDailyFees(int $editionId, int $vendorId, string $date): int
    {
        $rules = FinanceFeeRule::where('festival_edition_id', $editionId)
            ->where('is_active', true)
            ->where('fee_type', FeeType::FixedDaily)
            ->where(function ($q) use ($vendorId) {
                $q->whereNull('vendor_id')->orWhere('vendor_id', $vendorId);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('period_start')->orWhere('period_start', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('period_end')->orWhere('period_end', '>=', $date);
            })
            ->get();

        return $rules->sum('amount_cents');
    }

    /**
     * Calculate period fixed fees for a vendor.
     */
    public function calculatePeriodFees(int $editionId, int $vendorId): int
    {
        return FinanceFeeRule::where('festival_edition_id', $editionId)
            ->where('is_active', true)
            ->where('fee_type', FeeType::FixedPeriod)
            ->where(function ($q) use ($vendorId) {
                $q->whereNull('vendor_id')->orWhere('vendor_id', $vendorId);
            })
            ->sum('amount_cents');
    }
}
