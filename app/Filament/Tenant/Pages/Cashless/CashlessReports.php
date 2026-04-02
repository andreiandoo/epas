<?php

namespace App\Filament\Tenant\Pages\Cashless;

use App\Services\Cashless\ReportService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use App\Models\FestivalEdition;

class CashlessReports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Cashless Reports';

    protected static ?string $navigationGroup = 'Cashless';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.tenant.pages.cashless.reports';

    public ?int $editionId = null;

    public string $reportType = 'sales_overview';

    public ?string $date = null;

    public ?int $vendorId = null;

    public array $reportData = [];

    public function mount(): void
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id ?? null;

        $edition = FestivalEdition::when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'active')
            ->latest('start_date')
            ->first();

        $this->editionId = $edition?->id;

        if ($this->editionId) {
            $this->loadReport();
        }
    }

    public function loadReport(): void
    {
        if (! $this->editionId) {
            $this->reportData = [];
            return;
        }

        $service = app(ReportService::class);

        $this->reportData = match ($this->reportType) {
            'sales_overview'    => $service->totalSales($this->editionId, $this->date),
            'sales_per_vendor'  => $service->salesPerVendor($this->editionId, $this->date),
            'sales_per_product' => $service->salesPerProduct($this->editionId, $this->vendorId, $this->date),
            'hourly_sales'      => $service->hourlySales($this->editionId, $this->vendorId, $this->date),
            'festival_revenue'  => $service->festivalRevenue($this->editionId),
            'vendor_balances'   => $service->vendorBalances($this->editionId),
            'topups_channel'    => $service->topupsByChannel($this->editionId, $this->date),
            'topups_method'     => $service->topupsByMethod($this->editionId),
            'active_customers'  => $service->activeCustomers($this->editionId, $this->date),
            'avg_spending'      => $service->averageSpending($this->editionId),
            'balance_dist'      => $service->balanceDistribution($this->editionId),
            'avg_basket'        => $service->averageBasket($this->editionId, $this->vendorId),
            'stock_summary'     => $service->stockSummary($this->editionId, $this->vendorId),
            'live_kpis'         => $service->liveKpis($this->editionId),
            default             => [],
        };
    }

    public function updatedReportType(): void
    {
        $this->loadReport();
    }

    public function updatedDate(): void
    {
        $this->loadReport();
    }

    public function updatedVendorId(): void
    {
        $this->loadReport();
    }

    public function getReportTypes(): array
    {
        return [
            'Sales' => [
                'sales_overview'    => 'Sales Overview',
                'sales_per_vendor'  => 'Sales per Vendor',
                'sales_per_product' => 'Sales per Product',
                'hourly_sales'      => 'Hourly Sales',
            ],
            'Finance' => [
                'festival_revenue'  => 'Festival Revenue',
                'vendor_balances'   => 'Vendor Balances',
            ],
            'Top-ups' => [
                'topups_channel' => 'Top-ups by Channel',
                'topups_method'  => 'Top-ups by Method',
            ],
            'Customers' => [
                'active_customers' => 'Active Customers',
                'avg_spending'     => 'Average Spending',
                'balance_dist'     => 'Balance Distribution',
                'avg_basket'       => 'Average Basket',
            ],
            'Inventory' => [
                'stock_summary' => 'Stock Summary',
            ],
            'Live' => [
                'live_kpis' => 'Live KPIs',
            ],
        ];
    }
}
