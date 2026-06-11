<?php

namespace Database\Seeders\Demo;

use App\Models\Cashless\CashlessAccount;
use App\Models\Cashless\CashlessSale;
use App\Models\Cashless\CustomerProfile;
use App\Models\VendorSaleItem;
use Carbon\Carbon;

class DemoCustomerProfileSeeder
{
    public function __construct(protected FestivalDemoSeeder $parent) {}

    public function run(): void
    {
        $tenantId = $this->parent->tenantId;
        $edition = $this->parent->refs['edition'];
        $accounts = $this->parent->refs['accounts'] ?? [];
        $customers = $this->parent->refs['customers'] ?? [];

        foreach ($accounts as $idx => $account) {
            $customer = $customers[$idx] ?? null;
            if (!$customer) continue;

            // Get real sales for this customer
            $sales = CashlessSale::where('festival_edition_id', $edition->id)
                ->where('customer_id', $customer->id)->get();

            $totalSpent = $account->total_spent_cents;
            $totalTxn = $sales->count();
            $avgTxn = $totalTxn > 0 ? (int) round($totalSpent / $totalTxn) : 0;
            $maxTxn = $sales->max('total_cents') ?? 0;
            $minTxn = $sales->min('total_cents') ?? 0;

            // Determine categories from sale items
            $saleIds = $sales->pluck('id');
            $items = VendorSaleItem::whereIn('cashless_sale_id', $saleIds)->get();

            $catTotals = $items->groupBy('product_type')->map(fn ($g) => $g->sum('total_cents'))->toArray();
            $vendorTotals = $items->groupBy('vendor_id')->map(fn ($g) => $g->sum('total_cents'))
                ->sortDesc()->take(3)->keys()->toArray();

            // Scoring
            $spendingScore = min(100, (int) round($totalSpent / 500)); // 1 point per 5 RON
            $frequencyScore = min(100, $totalTxn * 15);
            $diversityScore = min(100, count($catTotals) * 25);
            $overallScore = (int) round(($spendingScore + $frequencyScore + $diversityScore) / 3);

            $segment = match (true) {
                $overallScore >= 70 => 'whale',
                $overallScore >= 40 => 'regular',
                $overallScore >= 15 => 'occasional',
                default => 'minimal',
            };

            CustomerProfile::firstOrCreate(
                ['festival_edition_id' => $edition->id, 'customer_id' => $customer->id],
                [
                    'tenant_id' => $tenantId,
                    'cashless_account_id' => $account->id,
                    'city' => $customer->city,
                    'country' => $customer->country ?? 'RO',
                    'total_spent_cents' => $totalSpent,
                    'total_transactions' => $totalTxn,
                    'avg_transaction_cents' => $avgTxn,
                    'max_transaction_cents' => $maxTxn,
                    'min_transaction_cents' => $minTxn,
                    'total_topped_up_cents' => $account->total_topped_up_cents,
                    'total_cashed_out_cents' => $account->total_cashed_out_cents,
                    'net_spend_cents' => $totalSpent,
                    'top_categories' => array_slice(array_keys($catTotals), 0, 3),
                    'top_products' => $items->groupBy('product_name')->map(fn ($g) => $g->sum('quantity'))->sortDesc()->take(3)->keys()->toArray(),
                    'top_vendors' => $vendorTotals,
                    'product_type_distribution' => $catTotals,
                    'first_transaction_at' => $sales->min('sold_at'),
                    'last_transaction_at' => $sales->max('sold_at'),
                    'peak_hour' => 20,
                    'active_hours' => [14, 15, 16, 17, 18, 19, 20, 21, 22, 23],
                    'active_days' => [3, 4, 5, 6], // Wed-Sat
                    'spending_score' => $spendingScore,
                    'frequency_score' => $frequencyScore,
                    'diversity_score' => $diversityScore,
                    'overall_score' => $overallScore,
                    'segment' => $segment,
                    'tags' => $overallScore >= 70 ? ['vip_candidate'] : [],
                    'is_minor' => false,
                    'has_age_restricted_attempts' => false,
                    'flagged_for_review' => false,
                    'calculated_at' => now(),
                ]
            );
        }
    }

    public function cleanup(): void
    {
        $tenantId = $this->parent->tenantId;
        $edition = \App\Models\FestivalEdition::where('tenant_id', $tenantId)->where('slug', 'demo-alpha-fest-2026')->first();
        if ($edition) {
            CustomerProfile::where('festival_edition_id', $edition->id)->delete();
        }
    }
}
