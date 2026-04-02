<?php

namespace App\Jobs;

use App\Enums\SaleStatus;
use App\Models\Cashless\CashlessAccount;
use App\Models\Cashless\CashlessSale;
use App\Models\Cashless\CustomerProfile;
use App\Models\VendorSaleItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CalculateCustomerProfilesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $editionId,
    ) {}

    public function handle(): void
    {
        $accounts = CashlessAccount::where('festival_edition_id', $this->editionId)
            ->where('status', 'active')
            ->with('customer')
            ->cursor();

        // Gather all scores for percentile calculation
        $allSpending = CashlessAccount::where('festival_edition_id', $this->editionId)
            ->where('total_spent_cents', '>', 0)
            ->pluck('total_spent_cents')
            ->sort()
            ->values();

        foreach ($accounts as $account) {
            $this->calculateProfile($account, $allSpending);
        }
    }

    private function calculateProfile(CashlessAccount $account, $allSpending): void
    {
        $customer = $account->customer;
        if (! $customer) return;

        $sales = CashlessSale::where('cashless_account_id', $account->id)
            ->where('status', SaleStatus::Completed)
            ->get();

        $totalSpent = $sales->sum('total_cents');
        $totalTx = $sales->count();

        if ($totalTx === 0) {
            // Create minimal profile for zero-activity accounts
            CustomerProfile::updateOrCreate(
                ['cashless_account_id' => $account->id],
                [
                    'tenant_id'           => $account->tenant_id,
                    'festival_edition_id' => $this->editionId,
                    'customer_id'         => $account->customer_id,
                    'age'                 => $customer->date_of_birth?->age,
                    'age_group'           => $this->ageGroup($customer->date_of_birth?->age),
                    'gender'              => $customer->gender,
                    'is_minor'            => $customer->date_of_birth && $customer->date_of_birth->age < 18,
                    'segment'             => 'minimal',
                    'calculated_at'       => now(),
                ]
            );
            return;
        }

        // Spending stats
        $avgTx = (int) round($totalSpent / $totalTx);
        $maxTx = $sales->max('total_cents');
        $minTx = $sales->min('total_cents');

        // Top categories
        $categories = VendorSaleItem::whereIn('cashless_sale_id', $sales->pluck('id'))
            ->selectRaw('COALESCE(product_category_name, category_name, \'Other\') as cat, SUM(quantity) as cnt, SUM(total_cents) as total')
            ->groupBy(DB::raw('COALESCE(product_category_name, category_name, \'Other\')'))
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['category' => $r->cat, 'count' => (int) $r->cnt, 'total_cents' => (int) $r->total])
            ->toArray();

        // Top products
        $products = VendorSaleItem::whereIn('cashless_sale_id', $sales->pluck('id'))
            ->selectRaw('product_name, SUM(quantity) as cnt, SUM(total_cents) as total')
            ->groupBy('product_name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['product' => $r->product_name, 'count' => (int) $r->cnt, 'total_cents' => (int) $r->total])
            ->toArray();

        // Top vendors
        $vendors = $sales->groupBy('vendor_id')->map(function ($group) {
            return ['count' => $group->count(), 'total_cents' => $group->sum('total_cents')];
        })->sortByDesc('total_cents')->take(5)->toArray();

        // Temporal: peak hour
        $hourCounts = $sales->groupBy(fn ($s) => $s->sold_at->hour)->map->count();
        $peakHour = $hourCounts->isNotEmpty() ? $hourCounts->sortDesc()->keys()->first() : null;

        // Active hours (24-element array)
        $activeHours = array_fill(0, 24, 0);
        foreach ($sales as $sale) {
            $activeHours[$sale->sold_at->hour]++;
        }

        // Time between purchases
        $avgTimeBetween = null;
        if ($totalTx > 1) {
            $timestamps = $sales->sortBy('sold_at')->pluck('sold_at');
            $gaps = [];
            for ($i = 1; $i < $timestamps->count(); $i++) {
                $gaps[] = $timestamps[$i]->diffInMinutes($timestamps[$i - 1]);
            }
            $avgTimeBetween = count($gaps) > 0 ? (int) round(array_sum($gaps) / count($gaps)) : null;
        }

        // Scores (0-100)
        $spendingScore = $this->percentileRank($totalSpent, $allSpending);
        $frequencyScore = min(100, (int) round($totalTx / max(1, $this->editionDays()) * 100 / 5)); // 5 tx/day = 100
        $uniqueCategories = count($categories);
        $diversityScore = min(100, $uniqueCategories * 20);
        $overallScore = (int) round(($spendingScore * 0.4 + $frequencyScore * 0.35 + $diversityScore * 0.25));

        // Segment
        $segment = match (true) {
            $overallScore >= 90 => 'whale',
            $overallScore >= 50 => 'regular',
            $totalTx >= 2       => 'occasional',
            default             => 'minimal',
        };

        // Tags
        $tags = [];
        if ($spendingScore >= 80) $tags[] = 'big_spender';
        if ($diversityScore >= 80) $tags[] = 'variety_seeker';
        if ($peakHour !== null && $peakHour >= 22) $tags[] = 'night_owl';
        if ($peakHour !== null && $peakHour < 14) $tags[] = 'early_bird';
        if (count($vendors) === 1 && $totalTx >= 3) $tags[] = 'one_stop_shop';
        if ($uniqueCategories >= 5) $tags[] = 'food_explorer';

        $isMinor = $customer->date_of_birth && $customer->date_of_birth->age < 18;

        CustomerProfile::updateOrCreate(
            ['cashless_account_id' => $account->id],
            [
                'tenant_id'                  => $account->tenant_id,
                'festival_edition_id'        => $this->editionId,
                'customer_id'                => $account->customer_id,
                'age'                        => $customer->date_of_birth?->age,
                'age_group'                  => $this->ageGroup($customer->date_of_birth?->age),
                'gender'                     => $customer->gender,
                'total_spent_cents'          => $totalSpent,
                'total_transactions'         => $totalTx,
                'avg_transaction_cents'      => $avgTx,
                'max_transaction_cents'      => $maxTx,
                'min_transaction_cents'      => $minTx,
                'total_topped_up_cents'      => $account->total_topped_up_cents,
                'total_cashed_out_cents'     => $account->total_cashed_out_cents,
                'net_spend_cents'            => $account->total_topped_up_cents - $account->total_cashed_out_cents,
                'top_categories'             => $categories,
                'top_products'               => $products,
                'top_vendors'                => $vendors,
                'first_transaction_at'       => $sales->min('sold_at'),
                'last_transaction_at'        => $sales->max('sold_at'),
                'peak_hour'                  => $peakHour,
                'active_hours'               => $activeHours,
                'avg_time_between_purchases' => $avgTimeBetween,
                'spending_score'             => $spendingScore,
                'frequency_score'            => $frequencyScore,
                'diversity_score'            => $diversityScore,
                'overall_score'              => $overallScore,
                'segment'                    => $segment,
                'tags'                       => $tags,
                'is_minor'                   => $isMinor,
                'calculated_at'              => now(),
            ]
        );
    }

    private function percentileRank(int $value, $sortedValues): int
    {
        if ($sortedValues->isEmpty()) return 0;

        $below = $sortedValues->filter(fn ($v) => $v < $value)->count();

        return (int) round($below / $sortedValues->count() * 100);
    }

    private function ageGroup(?int $age): ?string
    {
        if ($age === null) return null;
        return match (true) {
            $age < 18 => 'minor',
            $age <= 24 => '18-24',
            $age <= 34 => '25-34',
            $age <= 44 => '35-44',
            $age <= 54 => '45-54',
            default    => '55+',
        };
    }

    private function editionDays(): int
    {
        $edition = \App\Models\FestivalEdition::find($this->editionId);
        if (! $edition || ! $edition->start_date || ! $edition->end_date) return 1;

        return max(1, $edition->start_date->diffInDays($edition->end_date) + 1);
    }
}
