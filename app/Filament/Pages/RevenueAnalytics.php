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
    public array $saasMetrics = [];
    public array $unitEconomics = [];
    public array $financialHealth = [];

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

        // === ADVANCED SAAS METRICS ===
        $this->calculateSaasMetrics($mrr, $lastMonthMRR, $mrrGrowth);

        // === UNIT ECONOMICS ===
        $this->calculateUnitEconomics($mrr);

        // === FINANCIAL HEALTH ===
        $this->calculateFinancialHealth($mrr, $arr, $monthlyCosts, $netProfit);
    }

    protected function calculateMicroserviceBreakdown(): array
    {
        $recurring = 0;
        $fixed = 0;
        $this->microserviceBreakdown = [];

        $microservices = Microservice::where('is_active', true)->get();

        foreach ($microservices as $microservice) {
            $activeCount = $microservice->tenants()
                ->wherePivot('status', 'active')
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

    /**
     * Calculate SaaS health metrics: NRR, Churn, Retention, Growth
     */
    protected function calculateSaasMetrics(float $currentMRR, float $lastMonthMRR, float $mrrGrowth): void
    {
        // Get tenant churn data
        $currentMonthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonth()->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();

        // Count tenants at start of last month vs now
        $tenantsStartOfLastMonth = Tenant::where('created_at', '<', $lastMonthStart)
            ->where(function ($q) use ($lastMonthStart) {
                $q->where('status', 'active')
                    ->orWhere('updated_at', '>=', $lastMonthStart);
            })
            ->count();

        $tenantsStartOfThisMonth = Tenant::where('created_at', '<', $currentMonthStart)
            ->where('status', 'active')
            ->count();

        // Churned tenants (became inactive this month)
        $churnedTenants = Tenant::where('status', '!=', 'active')
            ->whereBetween('updated_at', [$currentMonthStart, now()])
            ->where('created_at', '<', $currentMonthStart)
            ->count();

        // New tenants acquired this month
        $newTenants = Tenant::where('status', 'active')
            ->whereBetween('created_at', [$currentMonthStart, now()])
            ->count();

        // Churn Rate: (Lost Tenants / Starting Tenants) × 100
        $churnRate = $tenantsStartOfThisMonth > 0
            ? ($churnedTenants / $tenantsStartOfThisMonth) * 100
            : 0;

        // Retention Rate: 100% - Churn Rate
        $retentionRate = 100 - $churnRate;

        // Growth Rate (MoM): Already calculated as mrrGrowth
        $growthRate = $mrrGrowth;

        // Net Revenue Retention (NRR): (MRR_end - churn + expansion) / MRR_start × 100
        // Simplified: current MRR from existing customers / starting MRR
        $expansionMRR = max(0, $currentMRR - $lastMonthMRR); // Revenue increase from existing
        $contractionMRR = max(0, $lastMonthMRR - $currentMRR); // Revenue decrease
        $churnMRR = $churnedTenants > 0 && $tenantsStartOfThisMonth > 0
            ? ($churnedTenants / $tenantsStartOfThisMonth) * $lastMonthMRR
            : 0;

        $nrr = $lastMonthMRR > 0
            ? (($lastMonthMRR + $expansionMRR - $contractionMRR - $churnMRR) / $lastMonthMRR) * 100
            : 100;

        // Stickiness: DAU / MAU × 100 (using order activity as proxy)
        $monthlyActiveTenantsWithOrders = Order::where('status', 'completed')
            ->whereBetween('created_at', [$currentMonthStart, now()])
            ->distinct('tenant_id')
            ->count('tenant_id');

        $dailyActiveTenantsWithOrders = Order::where('status', 'completed')
            ->whereDate('created_at', now())
            ->distinct('tenant_id')
            ->count('tenant_id');

        $stickiness = $monthlyActiveTenantsWithOrders > 0
            ? ($dailyActiveTenantsWithOrders / $monthlyActiveTenantsWithOrders) * 100
            : 0;

        $this->saasMetrics = [
            'nrr' => round($nrr, 1),
            'churn_rate' => round($churnRate, 2),
            'retention_rate' => round($retentionRate, 2),
            'growth_rate' => round($growthRate, 2),
            'stickiness' => round($stickiness, 1),
            'churned_tenants' => $churnedTenants,
            'new_tenants' => $newTenants,
            'tenants_start_of_month' => $tenantsStartOfThisMonth,
        ];
    }

    /**
     * Calculate unit economics: LTV, CAC, LTV:CAC ratio
     */
    protected function calculateUnitEconomics(float $mrr): void
    {
        $activeTenants = Tenant::where('status', 'active')->count();

        // Average Revenue Per User (ARPU)
        $arpu = $activeTenants > 0 ? $mrr / $activeTenants : 0;

        // Average Tenant Lifespan (months) - based on churn rate
        $churnRate = $this->saasMetrics['churn_rate'] ?? 5; // Default 5% if not calculated
        $avgLifespanMonths = $churnRate > 0 ? 100 / $churnRate : 24; // Cap at 24 months if no churn

        // Lifetime Value (LTV) = ARPU × Average Lifespan
        $ltv = $arpu * min($avgLifespanMonths, 60); // Cap at 60 months

        // Customer Acquisition Cost (CAC)
        // Get marketing costs from platform costs
        $marketingCosts = PlatformCost::active()
            ->where('category', 'marketing')
            ->get()
            ->sum(fn ($cost) => $cost->monthly_amount);

        // New tenants this month
        $newTenantsThisMonth = Tenant::where('status', 'active')
            ->whereBetween('created_at', [now()->startOfMonth(), now()])
            ->count();

        // CAC = Total Marketing Costs / New Customers Acquired
        $cac = $newTenantsThisMonth > 0 ? $marketingCosts / $newTenantsThisMonth : $marketingCosts;

        // LTV:CAC Ratio
        $ltvCacRatio = $cac > 0 ? $ltv / $cac : 0;

        $this->unitEconomics = [
            'ltv' => round($ltv, 2),
            'cac' => round($cac, 2),
            'ltv_cac_ratio' => round($ltvCacRatio, 2),
            'arpu' => round($arpu, 2),
            'avg_lifespan_months' => round(min($avgLifespanMonths, 60), 1),
            'marketing_costs' => round($marketingCosts, 2),
            'new_tenants_this_month' => $newTenantsThisMonth,
        ];
    }

    /**
     * Calculate financial health: EBITDA, Burn Multiple, Operating Cash Flow, Runway
     */
    protected function calculateFinancialHealth(float $mrr, float $arr, float $monthlyCosts, float $netProfit): void
    {
        // Get all costs by category
        $allCosts = PlatformCost::active()->get();
        $totalMonthlyCosts = $allCosts->sum(fn ($cost) => $cost->monthly_amount);

        // Operating costs (exclude one-time costs)
        $operatingCosts = PlatformCost::active()->recurring()->get()
            ->sum(fn ($cost) => $cost->monthly_amount);

        // EBITDA (Earnings Before Interest, Taxes, Depreciation, Amortization)
        // Simplified: Revenue - Operating Expenses
        $ebitda = $mrr - $operatingCosts;

        // Monthly burn rate (if losing money)
        $monthlyBurn = max(0, $totalMonthlyCosts - $mrr);

        // Net New ARR (growth in ARR from last month)
        $lastMonthMRR = $this->metrics['mrr'] ?? $mrr;
        $mrrGrowth = $this->metrics['mrr_growth'] ?? 0;
        $netNewARR = ($mrr * ($mrrGrowth / 100)) * 12;

        // Burn Multiple = Net Burn / Net New ARR
        // Lower is better (< 1 is excellent, < 2 is good)
        $burnMultiple = $netNewARR > 0 ? ($monthlyBurn * 12) / $netNewARR : 0;
        if ($monthlyBurn <= 0) {
            $burnMultiple = 0; // Profitable companies have 0 burn multiple
        }

        // Operating Cash Flow (simplified: EBITDA - working capital changes)
        // In a SaaS business, this is typically close to EBITDA
        $operatingCashFlow = $ebitda * 0.9; // Assume 10% tied in working capital

        // Cash balance (would need to be configured - using placeholder)
        // This should ideally come from settings or a dedicated field
        $cashBalance = $this->getCashBalance();

        // Runway = Cash Balance / Monthly Burn Rate
        $runway = $monthlyBurn > 0 ? $cashBalance / $monthlyBurn : 999; // 999 months if profitable

        // AI Search Visibility (placeholder - would need external API integration)
        $aiSearchVisibility = $this->getAISearchVisibility();

        $this->financialHealth = [
            'ebitda' => round($ebitda, 2),
            'ebitda_margin' => $mrr > 0 ? round(($ebitda / $mrr) * 100, 1) : 0,
            'burn_multiple' => round($burnMultiple, 2),
            'operating_cash_flow' => round($operatingCashFlow, 2),
            'monthly_burn' => round($monthlyBurn, 2),
            'runway_months' => min(round($runway, 0), 999),
            'cash_balance' => round($cashBalance, 2),
            'net_new_arr' => round($netNewARR, 2),
            'ai_search_visibility' => $aiSearchVisibility,
            'is_profitable' => $netProfit > 0,
        ];
    }

    /**
     * Get cash balance from settings or return default
     */
    protected function getCashBalance(): float
    {
        // This would ideally come from a settings table or manual input
        // For now, return a configurable default or check config
        return (float) config('analytics.cash_balance', 50000);
    }

    /**
     * Get AI search visibility score
     * This is a placeholder for future integration with AI search APIs
     */
    protected function getAISearchVisibility(): array
    {
        // This would integrate with external APIs to check brand visibility
        // in AI search results (ChatGPT, Claude, Perplexity, etc.)
        // For now, return placeholder data
        return [
            'score' => null, // null indicates not configured
            'status' => 'not_configured',
            'message' => 'Configure AI search monitoring to track visibility',
        ];
    }
}
