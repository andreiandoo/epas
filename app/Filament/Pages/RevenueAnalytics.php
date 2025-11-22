<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\Tenant;
use App\Models\PlatformCost;
use App\Models\Microservice;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

class RevenueAnalytics extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';
    protected string $view = 'filament.pages.revenue-analytics';
    protected static \UnitEnum|string|null $navigationGroup = 'Finance';
    protected static ?int $navigationSort = 10;
    protected static ?string $title = 'Revenue Analytics';

    public array $metrics = [];
    public array $projections = [];
    public array $monthlyData = [];
    public array $revenueBreakdown = [];
    public array $costBreakdown = [];
    public array $microserviceBreakdown = [];
    public array $chartData = [];
    public array $filteredRevenue = [];

    #[Url]
    public ?string $startDate = null;

    #[Url]
    public ?string $endDate = null;

    public function mount(): void
    {
        $this->startDate = $this->startDate ?? now()->startOfMonth()->format('Y-m-d');
        $this->endDate = $this->endDate ?? now()->endOfMonth()->format('Y-m-d');
        $this->calculateMetrics();
    }

    public function updatedStartDate(): void
    {
        $this->calculateMetrics();
    }

    public function updatedEndDate(): void
    {
        $this->calculateMetrics();
    }

    protected function calculateMetrics(): void
    {
        // Parse filter dates
        $filterStart = Carbon::parse($this->startDate)->startOfDay();
        $filterEnd = Carbon::parse($this->endDate)->endOfDay();

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

        // === MICROSERVICE REVENUE BREAKDOWN ===
        $microserviceData = $this->calculateMicroserviceBreakdown();
        $recurringMicroserviceRevenue = $microserviceData['recurring'];
        $fixedMicroserviceRevenue = $microserviceData['fixed'];
        $totalMicroserviceRevenue = $recurringMicroserviceRevenue + $fixedMicroserviceRevenue;

        // === MONTHLY RECURRING REVENUE (MRR) ===
        $mrr = $currentMonthCommission + $recurringMicroserviceRevenue;

        // === COSTS ===
        $monthlyCosts = PlatformCost::active()->recurring()->get()
            ->sum(fn ($cost) => $cost->monthly_amount);

        // === NET PROFIT ===
        $netProfit = $mrr - $monthlyCosts;

        // === GROWTH ===
        $lastMonthMRR = $lastMonthCommission + $recurringMicroserviceRevenue;
        $mrrGrowth = $lastMonthMRR > 0 ? (($mrr - $lastMonthMRR) / $lastMonthMRR) * 100 : 0;

        // === ANNUAL RECURRING REVENUE (ARR) ===
        $arr = $mrr * 12;

        $this->metrics = [
            'mrr' => $mrr,
            'arr' => $arr,
            'mrr_growth' => $mrrGrowth,
            'gross_revenue' => $currentMonthOrderRevenue,
            'commission_revenue' => $currentMonthCommission,
            'recurring_microservice_revenue' => $recurringMicroserviceRevenue,
            'fixed_microservice_revenue' => $fixedMicroserviceRevenue,
            'total_microservice_revenue' => $totalMicroserviceRevenue,
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

        // === FILTERED PERIOD REVENUE ===
        $this->calculateFilteredRevenue($filterStart, $filterEnd, $avgCommissionRate);

        // === REVENUE BREAKDOWN (3 parts) ===
        $this->revenueBreakdown = [
            ['label' => 'Commission from Sales', 'value' => $currentMonthCommission, 'color' => '#3b82f6'],
            ['label' => 'Recurring Microservices', 'value' => $recurringMicroserviceRevenue, 'color' => '#22c55e'],
            ['label' => 'One-time Microservices', 'value' => $fixedMicroserviceRevenue, 'color' => '#f59e0b'],
        ];

        // === COST BREAKDOWN ===
        $this->costBreakdown = PlatformCost::active()->recurring()
            ->selectRaw('category, SUM(CASE WHEN billing_cycle = "yearly" THEN amount/12 ELSE amount END) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn ($value, $key) => ['label' => ucfirst($key), 'value' => $value])
            ->values()
            ->toArray();

        // === CHART DATA ===
        $this->prepareChartData();
    }

    protected function calculateMicroserviceBreakdown(): array
    {
        $recurring = 0;
        $fixed = 0;
        $this->microserviceBreakdown = [];

        $microservices = Microservice::where('is_active', true)->get();

        foreach ($microservices as $microservice) {
            $activeCount = $microservice->tenants()
                ->wherePivot('is_active', true)
                ->count();

            $isRecurring = in_array($microservice->billing_cycle, ['monthly', 'yearly']);

            $monthlyPrice = match ($microservice->billing_cycle) {
                'yearly' => $microservice->price / 12,
                'one_time' => $microservice->price,
                default => $microservice->price,
            };

            $monthlyRevenue = $monthlyPrice * $activeCount;

            if ($isRecurring) {
                $recurring += $monthlyRevenue;
            } else {
                $fixed += $monthlyRevenue;
            }

            $this->microserviceBreakdown[] = [
                'id' => $microservice->id,
                'name' => $microservice->name,
                'price' => $microservice->price,
                'billing_cycle' => $microservice->billing_cycle,
                'active_tenants' => $activeCount,
                'monthly_revenue' => $monthlyRevenue,
                'is_recurring' => $isRecurring,
                'projections' => [
                    3 => $monthlyRevenue * 3,
                    6 => $monthlyRevenue * 6,
                    9 => $monthlyRevenue * 9,
                    12 => $monthlyRevenue * 12,
                ],
            ];
        }

        return ['recurring' => $recurring, 'fixed' => $fixed];
    }

    protected function calculateProjections(float $mrr, float $growthRate, float $costs): void
    {
        $projectedGrowthRate = max(0, $growthRate / 2) / 100;

        $this->projections = [];

        foreach ([0, 3, 6, 9, 12] as $months) {
            $projectedMRR = $months === 0 ? $mrr : $mrr * pow(1 + $projectedGrowthRate, $months);
            $projectedCosts = $months === 0 ? $costs : $costs * pow(1 + (0.05 / 12), $months);

            $this->projections[$months] = [
                'label' => $months === 0 ? 'Current' : "{$months}mo",
                'mrr' => $projectedMRR,
                'arr' => $projectedMRR * 12,
                'costs' => $projectedCosts,
                'net_profit' => $projectedMRR - $projectedCosts,
                'cumulative_revenue' => $months === 0 ? $mrr : $mrr * $months * (1 + ($projectedGrowthRate * $months / 2)),
                'cumulative_profit' => $months === 0 ? ($mrr - $costs) : ($mrr - $costs) * $months,
            ];
        }
    }

    protected function calculateFilteredRevenue(Carbon $start, Carbon $end, float $avgCommissionRate): void
    {
        $orderRevenue = Order::where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->sum('total_cents') / 100;

        $commission = $orderRevenue * ($avgCommissionRate / 100);

        // Calculate months in period for recurring revenue
        $monthsInPeriod = $start->diffInMonths($end) + 1;
        $recurringRevenue = $this->metrics['recurring_microservice_revenue'] * $monthsInPeriod;

        $this->filteredRevenue = [
            'start' => $start->format('M d, Y'),
            'end' => $end->format('M d, Y'),
            'gross_sales' => $orderRevenue,
            'commission' => $commission,
            'recurring_microservices' => $recurringRevenue,
            'total' => $commission + $recurringRevenue,
        ];
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

            $commission = $orderRevenue * 0.02;

            return [
                'month' => $date->format('M Y'),
                'revenue' => $orderRevenue,
                'commission' => $commission,
            ];
        })->values()->toArray();
    }

    protected function prepareChartData(): void
    {
        $this->chartData = [
            'projections' => [
                'labels' => array_column($this->projections, 'label'),
                'mrr' => array_column($this->projections, 'mrr'),
                'costs' => array_column($this->projections, 'costs'),
                'profit' => array_column($this->projections, 'net_profit'),
            ],
            'revenue_breakdown' => [
                'labels' => array_column($this->revenueBreakdown, 'label'),
                'values' => array_column($this->revenueBreakdown, 'value'),
                'colors' => array_column($this->revenueBreakdown, 'color'),
            ],
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }
}
