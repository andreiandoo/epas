<?php

namespace App\Filament\Tenant\Widgets\Cashless;

use App\Services\Cashless\ReportService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CashlessKpiCards extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    public int $editionId = 0;

    protected function getStats(): array
    {
        if (! $this->editionId) {
            return [];
        }

        $report = app(ReportService::class);
        $todaySales = $report->totalSales($this->editionId, today()->toDateString());
        $revenue = $report->festivalRevenue($this->editionId);
        $customers = $report->activeCustomers($this->editionId, today()->toDateString());

        return [
            Stat::make('Revenue Today', number_format($todaySales['revenue_cents'] / 100, 0) . ' RON')
                ->description($todaySales['sales_count'] . ' sales')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Total Revenue (Edition)', number_format($revenue['total_sales_cents'] / 100, 0) . ' RON')
                ->description('Commission: ' . number_format($revenue['total_commission_cents'] / 100, 0) . ' RON')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('primary'),

            Stat::make('Active Customers Today', number_format($customers['active_customers']))
                ->description($customers['activation_rate'] . '% activation rate')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),

            Stat::make('Balance in Accounts', number_format($revenue['active_balance_cents'] / 100, 0) . ' RON')
                ->description('Top-ups: ' . number_format($revenue['total_topped_up_cents'] / 100, 0) . ' RON')
                ->descriptionIcon('heroicon-m-wallet')
                ->color('info'),
        ];
    }
}
