<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ConversionFunnelChart;
use App\Filament\Widgets\EventsByMonthChart;
use App\Filament\Widgets\LiveStatsCards;
use App\Filament\Widgets\RecentEventsTable;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TicketSalesChart;
use App\Filament\Widgets\TopTenantsTable;
use Filament\Pages\Dashboard as BaseDashboard;

class CustomDashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            LiveStatsCards::class,
            EventsByMonthChart::class,
            RevenueChart::class,
            ConversionFunnelChart::class,
            TicketSalesChart::class,
            RecentEventsTable::class,
            TopTenantsTable::class,
        ];
    }
}
