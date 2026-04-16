<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Services\Marketplace\SalesAnalysisService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function exportCsv(): StreamedResponse
    {
        $service = $this->getService();
        if (!$service) {
            abort(404);
        }

        $tab = $this->activeTab;
        $filename = "sales-analysis-{$tab}-" . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($service, $tab) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Sales Analysis Export - ' . ucfirst($tab) . ' - ' . now()->format('d/m/Y H:i')]);
            fputcsv($handle, []);

            match ($tab) {
                'patterns' => $this->exportPatterns($handle, $service),
                'predictions' => $this->exportPredictions($handle, $service),
                'optimization' => $this->exportOptimization($handle, $service),
                'audience' => $this->exportAudience($handle, $service),
                'operational' => $this->exportOperational($handle, $service),
                default => null,
            };

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    protected function exportPatterns($handle, SalesAnalysisService $service): void
    {
        $dow = $service->getDayOfWeekRevenue();
        fputcsv($handle, ['Day of Week Revenue']);
        fputcsv($handle, ['Zi', 'Revenue', 'Comenzi']);
        foreach ($dow['labels'] as $i => $label) {
            fputcsv($handle, [$label, $dow['revenue'][$i], $dow['orders'][$i]]);
        }
        fputcsv($handle, []);

        $peak = $service->getPeakSalesWindows();
        fputcsv($handle, ['Peak Sales Windows']);
        fputcsv($handle, ['Zi', 'Interval', 'Revenue', 'Comenzi']);
        foreach ($peak as $w) {
            fputcsv($handle, [$w['day'], $w['hour'], $w['revenue'], $w['orders']]);
        }
    }

    protected function exportPredictions($handle, SalesAnalysisService $service): void
    {
        $season = $service->getSeasonalityIndex();
        fputcsv($handle, ['Seasonality Index']);
        fputcsv($handle, ['Luna', 'Index']);
        foreach ($season['labels'] as $i => $label) {
            fputcsv($handle, [$label, $season['index'][$i]]);
        }
        fputcsv($handle, []);

        $velocity = $service->getSalesVelocity();
        fputcsv($handle, ['Sales Velocity']);
        fputcsv($handle, ['Eveniment', 'Categorie', 'Bilete/zi', 'Sold %']);
        foreach ($velocity as $v) {
            fputcsv($handle, [$v['name'], $v['category'], $v['tickets_per_day'], $v['sell_through']]);
        }
    }

    protected function exportOptimization($handle, SalesAnalysisService $service): void
    {
        $golden = $service->getGoldenPriceZone();
        fputcsv($handle, ['Golden Price Zone']);
        fputcsv($handle, ['Categorie', 'Min Pret', 'Max Pret', 'Golden Min', 'Golden Max', 'Golden %', 'Total Vandut']);
        foreach ($golden as $g) {
            fputcsv($handle, [$g['category'], $g['min_price'], $g['max_price'], $g['golden_min'], $g['golden_max'], $g['golden_pct'], $g['total_sold']]);
        }
        fputcsv($handle, []);

        $pareto = $service->getRevenueConcentration();
        fputcsv($handle, ['Revenue Concentration']);
        fputcsv($handle, ['Eveniment', 'Revenue', '%', 'Cumulativ %']);
        foreach ($pareto as $p) {
            fputcsv($handle, [$p['name'], $p['revenue'], $p['pct'], $p['cumulative_pct']]);
        }
    }

    protected function exportAudience($handle, SalesAnalysisService $service): void
    {
        $geo = $service->getGeographicRevenue();
        fputcsv($handle, ['Geographic Revenue']);
        fputcsv($handle, ['Oras', 'Revenue', 'Comenzi']);
        foreach ($geo as $g) {
            fputcsv($handle, [$g['city'], $g['revenue'], $g['orders']]);
        }
        fputcsv($handle, []);

        $rfm = $service->getRfmSegmentation();
        fputcsv($handle, ['RFM Segmentation']);
        fputcsv($handle, ['Segment', 'Clienti']);
        foreach ($rfm['segments'] as $seg => $count) {
            fputcsv($handle, [$seg, $count]);
        }
    }

    protected function exportOperational($handle, SalesAnalysisService $service): void
    {
        $orgs = $service->getOrganizerLeaderboard();
        fputcsv($handle, ['Organizer Leaderboard']);
        fputcsv($handle, ['Organizator', 'Revenue', 'Bilete', 'Events']);
        foreach ($orgs as $o) {
            fputcsv($handle, [$o['name'], $o['revenue'], $o['tickets'], $o['events']]);
        }
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
                    'cohort' => $service->getCohortAnalysis(),
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
