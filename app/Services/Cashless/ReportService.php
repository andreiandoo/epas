<?php

namespace App\Services\Cashless;

use App\Enums\SaleStatus;
use App\Models\Cashless\CashlessAccount;
use App\Models\Cashless\CashlessSale;
use App\Models\Cashless\InventoryStock;
use App\Models\Cashless\VendorFinanceSummary;
use App\Models\VendorSaleItem;
use App\Models\WristbandTransaction;
use Illuminate\Support\Facades\DB;

class ReportService
{
    // ── S1: Total Sales Overview ──

    public function totalSales(int $editionId, ?string $date = null): array
    {
        $query = CashlessSale::where('festival_edition_id', $editionId)
            ->where('status', SaleStatus::Completed);

        if ($date) {
            $query->whereDate('sold_at', $date);
        }

        $totals = $query->selectRaw('
            COUNT(*) as sales_count,
            COALESCE(SUM(total_cents), 0) as revenue_cents,
            COALESCE(SUM(commission_cents), 0) as commission_cents,
            COALESCE(SUM(tip_cents), 0) as tips_cents,
            COALESCE(SUM(items_count), 0) as items_count
        ')->first();

        return [
            'sales_count'      => (int) $totals->sales_count,
            'revenue_cents'    => (int) $totals->revenue_cents,
            'commission_cents' => (int) $totals->commission_cents,
            'tips_cents'       => (int) $totals->tips_cents,
            'items_count'      => (int) $totals->items_count,
        ];
    }

    // ── S2: Sales per Vendor ──

    public function salesPerVendor(int $editionId, ?string $date = null): array
    {
        $query = CashlessSale::where('festival_edition_id', $editionId)
            ->where('status', SaleStatus::Completed)
            ->join('vendors', 'cashless_sales.vendor_id', '=', 'vendors.id');

        if ($date) {
            $query->whereDate('sold_at', $date);
        }

        return $query->selectRaw('
                vendors.id as vendor_id, vendors.name as vendor_name,
                COUNT(*) as sales_count, SUM(cashless_sales.total_cents) as revenue_cents,
                SUM(cashless_sales.commission_cents) as commission_cents,
                SUM(cashless_sales.tip_cents) as tips_cents
            ')
            ->groupBy('vendors.id', 'vendors.name')
            ->orderByDesc('revenue_cents')
            ->get()
            ->toArray();
    }

    // ── S3: Sales per Product ──

    public function salesPerProduct(int $editionId, ?int $vendorId = null, ?string $date = null, int $limit = 50): array
    {
        $query = VendorSaleItem::where('festival_edition_id', $editionId)
            ->whereHas('cashlessSale', fn ($q) => $q->where('status', SaleStatus::Completed));

        if ($vendorId) $query->where('vendor_id', $vendorId);
        if ($date) $query->whereDate('created_at', $date);

        return $query->selectRaw('
                product_name, COALESCE(product_category_name, category_name) as category,
                SUM(quantity) as total_qty, SUM(total_cents) as revenue_cents,
                AVG(unit_price_cents) as avg_price_cents
            ')
            ->groupBy('product_name', DB::raw('COALESCE(product_category_name, category_name)'))
            ->orderByDesc('revenue_cents')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    // ── S6: Hourly Sales Heatmap ──

    public function hourlySales(int $editionId, ?int $vendorId = null, ?string $date = null): array
    {
        $hourExpr = DB::getDriverName() === 'pgsql'
            ? 'EXTRACT(HOUR FROM sold_at)::int'
            : 'HOUR(sold_at)';
        $dateExpr = DB::getDriverName() === 'pgsql'
            ? 'sold_at::date'
            : 'DATE(sold_at)';

        $query = CashlessSale::where('festival_edition_id', $editionId)
            ->where('status', SaleStatus::Completed);

        if ($vendorId) $query->where('vendor_id', $vendorId);
        if ($date) $query->whereDate('sold_at', $date);

        return $query->selectRaw("
                {$dateExpr} as date, {$hourExpr} as hour,
                COUNT(*) as sales_count, SUM(total_cents) as revenue_cents
            ")
            ->groupBy(DB::raw($dateExpr), DB::raw($hourExpr))
            ->orderBy('date')
            ->orderBy('hour')
            ->get()
            ->toArray();
    }

    // ── F1: Festival Revenue Overview ──

    public function festivalRevenue(int $editionId): array
    {
        $topups = WristbandTransaction::where('festival_edition_id', $editionId)
            ->where('transaction_type', 'topup')
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as total')
            ->value('total');

        $cashouts = WristbandTransaction::where('festival_edition_id', $editionId)
            ->where('transaction_type', 'cashout')
            ->selectRaw('COALESCE(SUM(amount_cents), 0) as total')
            ->value('total');

        $sales = CashlessSale::where('festival_edition_id', $editionId)
            ->where('status', SaleStatus::Completed)
            ->selectRaw('COALESCE(SUM(total_cents), 0) as total, COALESCE(SUM(commission_cents), 0) as commission')
            ->first();

        $activeBalance = CashlessAccount::where('festival_edition_id', $editionId)
            ->selectRaw('COALESCE(SUM(balance_cents), 0) as total')
            ->value('total');

        return [
            'total_topped_up_cents'  => (int) $topups,
            'total_cashed_out_cents' => (int) $cashouts,
            'total_sales_cents'      => (int) $sales->total,
            'total_commission_cents'  => (int) $sales->commission,
            'active_balance_cents'   => (int) $activeBalance,
            'net_festival_revenue'   => (int) $sales->commission,
        ];
    }

    // ── F5: Vendor Balances (finance summaries) ──

    public function vendorBalances(int $editionId): array
    {
        return VendorFinanceSummary::where('festival_edition_id', $editionId)
            ->join('vendors', 'vendor_finance_summaries.vendor_id', '=', 'vendors.id')
            ->selectRaw('
                vendors.id as vendor_id, vendors.name as vendor_name,
                SUM(gross_sales_cents) as gross_sales_cents,
                SUM(net_sales_cents) as net_sales_cents,
                SUM(commission_cents) as commission_cents,
                SUM(fees_cents) as fees_cents,
                SUM(vendor_payout_cents) as vendor_payout_cents
            ')
            ->groupBy('vendors.id', 'vendors.name')
            ->orderByDesc('gross_sales_cents')
            ->get()
            ->toArray();
    }

    // ── T1: Top-ups per Channel ──

    public function topupsByChannel(int $editionId, ?string $date = null): array
    {
        $query = WristbandTransaction::where('festival_edition_id', $editionId)
            ->where('transaction_type', 'topup');

        if ($date) $query->whereDate('created_at', $date);

        return $query->selectRaw('
                COALESCE(channel, \'unknown\') as channel,
                COUNT(*) as count, SUM(amount_cents) as total_cents,
                AVG(amount_cents) as avg_cents
            ')
            ->groupBy('channel')
            ->get()
            ->toArray();
    }

    // ── T2: Top-ups per Method ──

    public function topupsByMethod(int $editionId): array
    {
        return WristbandTransaction::where('festival_edition_id', $editionId)
            ->where('transaction_type', 'topup')
            ->selectRaw('
                COALESCE(topup_method, payment_method, \'unknown\') as method,
                COUNT(*) as count, SUM(amount_cents) as total_cents
            ')
            ->groupBy(DB::raw('COALESCE(topup_method, payment_method, \'unknown\')'))
            ->get()
            ->toArray();
    }

    // ── C1: Active Customers ──

    public function activeCustomers(int $editionId, ?string $date = null): array
    {
        $totalAccounts = CashlessAccount::where('festival_edition_id', $editionId)->count();

        $query = CashlessSale::where('festival_edition_id', $editionId)
            ->where('status', SaleStatus::Completed);

        if ($date) $query->whereDate('sold_at', $date);

        $activeCustomers = $query->distinct('customer_id')->count('customer_id');

        return [
            'total_accounts'    => $totalAccounts,
            'active_customers'  => $activeCustomers,
            'activation_rate'   => $totalAccounts > 0
                ? round($activeCustomers / $totalAccounts * 100, 1)
                : 0,
        ];
    }

    // ── C2: Average Spending ──

    public function averageSpending(int $editionId): array
    {
        $stats = CashlessAccount::where('festival_edition_id', $editionId)
            ->where('total_spent_cents', '>', 0)
            ->selectRaw('
                COUNT(*) as customers_with_spending,
                AVG(total_spent_cents) as avg_spent_cents,
                MIN(total_spent_cents) as min_spent_cents,
                MAX(total_spent_cents) as max_spent_cents,
                PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY total_spent_cents) as median_spent_cents
            ')
            ->first();

        if (! $stats || ! $stats->customers_with_spending) {
            return [
                'customers_with_spending' => 0,
                'avg_spent_cents'         => 0,
                'min_spent_cents'         => 0,
                'max_spent_cents'         => 0,
                'median_spent_cents'      => 0,
            ];
        }

        // Fallback for SQLite which doesn't support PERCENTILE_CONT
        if ($stats->median_spent_cents === null) {
            $stats->median_spent_cents = $stats->avg_spent_cents;
        }

        return [
            'customers_with_spending' => (int) $stats->customers_with_spending,
            'avg_spent_cents'         => (int) ($stats->avg_spent_cents ?? 0),
            'min_spent_cents'         => (int) ($stats->min_spent_cents ?? 0),
            'max_spent_cents'         => (int) ($stats->max_spent_cents ?? 0),
            'median_spent_cents'      => (int) ($stats->median_spent_cents ?? 0),
        ];
    }

    // ── C4: Balance Distribution ──

    public function balanceDistribution(int $editionId): array
    {
        $ranges = [
            ['min' => 0, 'max' => 0, 'label' => '0 RON'],
            ['min' => 1, 'max' => 1000, 'label' => '0.01 - 10 RON'],
            ['min' => 1001, 'max' => 5000, 'label' => '10.01 - 50 RON'],
            ['min' => 5001, 'max' => 10000, 'label' => '50.01 - 100 RON'],
            ['min' => 10001, 'max' => 999999999, 'label' => '100+ RON'],
        ];

        $distribution = [];
        foreach ($ranges as $range) {
            $count = CashlessAccount::where('festival_edition_id', $editionId)
                ->whereBetween('balance_cents', [$range['min'], $range['max']])
                ->count();
            $distribution[] = [
                'label' => $range['label'],
                'count' => $count,
            ];
        }

        return $distribution;
    }

    // ── C6: Average Basket ──

    public function averageBasket(int $editionId, ?int $vendorId = null): array
    {
        $query = CashlessSale::where('festival_edition_id', $editionId)
            ->where('status', SaleStatus::Completed);

        if ($vendorId) $query->where('vendor_id', $vendorId);

        $stats = $query->selectRaw('
            AVG(total_cents) as avg_basket_cents,
            AVG(items_count) as avg_items,
            COUNT(*) as total_transactions
        ')->first();

        return [
            'avg_basket_cents'    => (int) ($stats->avg_basket_cents ?? 0),
            'avg_items_per_sale'  => round($stats->avg_items ?? 0, 1),
            'total_transactions'  => (int) $stats->total_transactions,
        ];
    }

    // ── I1: Stock Summary ──

    public function stockSummary(int $editionId, ?int $vendorId = null): array
    {
        $query = InventoryStock::where('festival_edition_id', $editionId)
            ->with('supplierProduct:id,name,sku,unit_measure');

        if ($vendorId !== null) {
            $query->where('vendor_id', $vendorId);
        } else {
            $query->whereNull('vendor_id'); // Festival level
        }

        return $query->get()->map(fn ($s) => [
            'product'            => $s->supplierProduct?->name,
            'sku'                => $s->supplierProduct?->sku,
            'quantity_total'     => (float) $s->quantity_total,
            'quantity_available' => $s->quantityAvailable,
            'quantity_sold'      => (float) $s->quantity_sold,
            'unit'               => $s->unit_measure,
            'is_low'             => $s->isLow(),
            'is_exhausted'       => $s->isExhausted(),
        ])->toArray();
    }

    // ── O1: Live Dashboard KPIs ──

    public function liveKpis(int $editionId): array
    {
        $today = today()->toDateString();

        return [
            'sales_today'       => $this->totalSales($editionId, $today),
            'revenue'           => $this->festivalRevenue($editionId),
            'customers'         => $this->activeCustomers($editionId, $today),
            'topups_today'      => $this->topupsByChannel($editionId, $today),
            'avg_basket'        => $this->averageBasket($editionId),
        ];
    }
}
