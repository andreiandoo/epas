<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\PlatformCost;
use App\Models\Microservice;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class RevenueAnalytics extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static string $view = 'filament.pages.revenue-analytics';
    protected static \UnitEnum|string|null $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 10;
    protected static ?string $title = 'Revenue Analytics';

    public array $metrics = [];
    public array $projections = [];
    public array $monthlyData = [];
    public array $revenueBreakdown = [];
    public array $costBreakdown = [];

    public function mount(): void
    {
        $this->calculateMetrics();
    }

    protected function calculateMetrics(): void
    {
        // Current month dates
        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        // === REVENUE FROM ORDERS (Commission) ===
        $currentMonthOrderRevenue = Order::where('status', 'completed')
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->sum('total_cents') / 100;

        $lastMonthOrderRevenue = Order::where('status', 'completed')
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('total_cents') / 100;

        // Calculate commission from orders (average commission rate from tenants)
        $avgCommissionRate = Tenant::where('status', 'active')->avg('commission_rate') ?? 2;
        $currentMonthCommission = $currentMonthOrderRevenue * ($avgCommissionRate / 100);
        $lastMonthCommission = $lastMonthOrderRevenue * ($avgCommissionRate / 100);

        // === REVENUE FROM MICROSERVICES ===
        // Count active tenant-microservice subscriptions
        $microserviceRevenue = $this->calculateMicroserviceRevenue();

        // === MONTHLY RECURRING REVENUE (MRR) ===
        $mrr = $currentMonthCommission + $microserviceRevenue;

        // === COSTS ===
        $monthlyCosts = PlatformCost::active()->recurring()->get()
            ->sum(fn ($cost) => $cost->monthly_amount);

        // === NET PROFIT ===
        $netProfit = $mrr - $monthlyCosts;

        // === GROWTH ===
        $lastMonthMRR = $lastMonthCommission + $microserviceRevenue; // Simplified
        $mrrGrowth = $lastMonthMRR > 0 ? (($mrr - $lastMonthMRR) / $lastMonthMRR) * 100 : 0;

        // === ANNUAL RECURRING REVENUE (ARR) ===
        $arr = $mrr * 12;

        $this->metrics = [
            'mrr' => $mrr,
            'arr' => $arr,
            'mrr_growth' => $mrrGrowth,
            'gross_revenue' => $currentMonthOrderRevenue,
            'commission_revenue' => $currentMonthCommission,
            'microservice_revenue' => $microserviceRevenue,
            'monthly_costs' => $monthlyCosts,
            'net_profit' => $netProfit,
            'profit_margin' => $mrr > 0 ? ($netProfit / $mrr) * 100 : 0,
            'active_tenants' => Tenant::where('status', 'active')->count(),
            'avg_commission_rate' => $avgCommissionRate,
        ];

        // === PROJECTIONS ===
        $this->calculateProjections($mrr, $mrrGrowth, $monthlyCosts);

        // === MONTHLY HISTORICAL DATA ===
        $this->calculateMonthlyData();

        // === REVENUE BREAKDOWN ===
        $this->revenueBreakdown = [
            ['label' => 'Commission from Sales', 'value' => $currentMonthCommission],
            ['label' => 'Microservice Subscriptions', 'value' => $microserviceRevenue],
        ];

        // === COST BREAKDOWN ===
        $this->costBreakdown = PlatformCost::active()->recurring()
            ->selectRaw('category, SUM(CASE WHEN billing_cycle = "yearly" THEN amount/12 ELSE amount END) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn ($value, $key) => ['label' => ucfirst($key), 'value' => $value])
            ->values()
            ->toArray();
    }

    protected function calculateMicroserviceRevenue(): float
    {
        $revenue = 0;

        // Get all microservices with their pricing
        $microservices = Microservice::where('is_active', true)->get();

        foreach ($microservices as $microservice) {
            // Count active tenants using this microservice
            $activeCount = $microservice->tenants()
                ->wherePivot('status', 'active')
                ->count();

            // Calculate monthly revenue based on pricing model
            $monthlyPrice = match ($microservice->billing_cycle) {
                'yearly' => $microservice->price / 12,
                'one_time' => 0,
                default => $microservice->price,
            };

            $revenue += $monthlyPrice * $activeCount;
        }

        return $revenue;
    }

    protected function calculateProjections(float $mrr, float $growthRate, float $costs): void
    {
        // Use conservative growth rate (half of current if positive, 0 if negative)
        $projectedGrowthRate = max(0, $growthRate / 2) / 100;

        $this->projections = [];

        foreach ([3, 6, 9, 12] as $months) {
            // Compound growth
            $projectedMRR = $mrr * pow(1 + $projectedGrowthRate, $months);
            // Assume costs grow at 5% per year
            $projectedCosts = $costs * pow(1 + (0.05 / 12), $months);

            $this->projections[$months] = [
                'mrr' => $projectedMRR,
                'arr' => $projectedMRR * 12,
                'costs' => $projectedCosts,
                'net_profit' => $projectedMRR - $projectedCosts,
                'cumulative_revenue' => $mrr * $months * (1 + ($projectedGrowthRate * $months / 2)),
                'cumulative_profit' => ($mrr - $costs) * $months,
            ];
        }
    }

    protected function calculateMonthlyData(): void
    {
        $this->monthlyData = collect(range(11, 0))->map(function ($monthsAgo) {
            $date = now()->subMonths($monthsAgo);
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();

            $orderRevenue = Order::where('status', 'completed')
                ->whereBetween('created_at', [$start, $end])
                ->sum('total_cents') / 100;

            // Estimate commission (2% average)
            $commission = $orderRevenue * 0.02;

            return [
                'month' => $date->format('M Y'),
                'revenue' => $orderRevenue,
                'commission' => $commission,
            ];
        })->values()->toArray();
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }
}
