<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Services\Marketplace\SalesAnalysisService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;

class SalesAnalysis extends Page
{
    use HasMarketplaceContext;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Sales Analysis';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 5;
    protected string $view = 'filament.marketplace.pages.sales-analysis';

    #[Url]
    public string $dateRange = '90d';

    #[Url]
    public string $activeTab = 'patterns';

    #[Url]
    public string $categoryFilter = '';

    #[Url]
    public string $currencyFilter = '';

    #[Url]
    public bool $compareMode = false;

    public function getTitle(): string
    {
        return 'Sales Analysis';
    }

    public function getHeading(): ?string
    {
        return null;
    }

    protected function getService(): ?SalesAnalysisService
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) return null;

        return new SalesAnalysisService(
            $marketplace->id,
            $this->dateRange,
            $this->categoryFilter ? (int) $this->categoryFilter : null,
            $this->currencyFilter ?: null,
        );
    }

    public function updatedDateRange(): void
    {
        $this->dispatch('filters-updated');
    }

    public function updatedCategoryFilter(): void
    {
        $this->dispatch('filters-updated');
    }

    public function updatedCurrencyFilter(): void
    {
        $this->dispatch('filters-updated');
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function getCurrencySymbol(): string
    {
        $marketplace = static::getMarketplaceClient();
        $currency = $marketplace?->currency ?? 'EUR';
        return match (strtoupper($currency)) {
            'RON' => 'RON ',
            'USD' => '$',
            'GBP' => "\u{00A3}",
            'CHF' => 'CHF ',
            default => "\u{20AC}",
        };
    }

    public function getViewData(): array
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            return ['marketplace' => null];
        }

        $service = $this->getService();
        $cacheKey = "sa_{$marketplace->id}_{$this->dateRange}_{$this->categoryFilter}_{$this->currencyFilter}";

        // KPI stats (always loaded)
        $kpis = Cache::remember("{$cacheKey}_kpis", 900, fn() => $service->getKpiStats());

        // Tab-specific data
        $tabData = Cache::remember("{$cacheKey}_{$this->activeTab}", 900, function () use ($service) {
            return match ($this->activeTab) {
                'patterns' => [
                    'dowRevenue' => $service->getDayOfWeekRevenue(),
                    'dowTickets' => $service->getDayOfWeekTickets(),
                    'categoryDay' => $service->getCategoryDayHeatmap(),
                    'hourly' => $service->getHourlyHeatmap(),
                    'peakWindows' => $service->getPeakSalesWindows(),
                ],
                'predictions' => [
                    'monthlyForecast' => $service->getMonthlyForecast(),
                    'yearlyForecast' => $service->getYearlyForecast(),
                    'seasonality' => $service->getSeasonalityIndex(),
                    'salesVelocity' => $service->getSalesVelocity(),
                ],
                'optimization' => [
                    'goldenPrice' => $service->getGoldenPriceZone(),
                    'priceVolume' => $service->getPriceVolumeAnalysis(),
                    'pareto' => $service->getRevenueConcentration(),
                    'repeatCustomer' => $service->getRepeatCustomerAnalysis(),
                    'leadTime' => $service->getBookingLeadTime(),
                    'refundRate' => $service->getRefundRateByCategory(),
                ],
                'audience' => [
                    'rfm' => $service->getRfmSegmentation(),
                    'geographic' => $service->getGeographicRevenue(),
                    'affinity' => $service->getCrossCategoryAffinity(),
                ],
                'operational' => [
                    'organizers' => $service->getOrganizerLeaderboard(),
                    'capacity' => $service->getCapacityUtilization(),
                    'discount' => $service->getDiscountImpact(),
                    'payments' => $service->getPaymentMethodDistribution(),
                    'refundTimeline' => $service->getRefundTimeline(),
                ],
                default => [],
            };
        });

        // AI Insights for current tab
        $insights = Cache::remember("{$cacheKey}_{$this->activeTab}_insights", 900, fn() => $service->generateInsights($this->activeTab));

        // Category options for filter
        $categories = \App\Models\MarketplaceEventCategory::where('marketplace_client_id', $marketplace->id)
            ->where('is_visible', true)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn($cat) => [$cat->id => $cat->getLocalizedName('ro')]);

        return [
            'marketplace' => $marketplace,
            'kpis' => $kpis,
            'tabData' => $tabData,
            'insights' => $insights,
            'categories' => $categories,
            'currencySymbol' => $this->getCurrencySymbol(),
        ];
    }
}
