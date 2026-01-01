<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use App\Services\Tracking\WinBackCampaignService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class IntelligenceOverview extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = '60s';
    protected static ?int $sort = -1;

    protected function getStats(): array
    {
        // Get aggregate stats across all tenants
        $totalAtRisk = 0;
        $totalLapsed = 0;
        $totalWonBack = 0;
        $revenueAtRisk = 0;

        $tenantIds = Tenant::pluck('id');

        foreach ($tenantIds as $tenantId) {
            try {
                $service = WinBackCampaignService::forTenant($tenantId);
                $stats = $service->getSummaryStats();

                $totalAtRisk += $stats['at_risk_customers'] ?? 0;
                $totalLapsed += $stats['lapsed_customers'] ?? 0;
                $totalWonBack += $stats['recently_won_back'] ?? 0;
                $revenueAtRisk += $stats['potential_revenue_at_risk'] ?? 0;
            } catch (\Exception $e) {
                continue;
            }
        }

        // Get pending alerts count
        $pendingAlerts = DB::table('tracking_alerts')
            ->where('status', 'pending')
            ->count();

        // Get high-risk events count
        $highRiskEvents = DB::table('demand_forecasts')
            ->whereIn('sellout_risk', ['very_high', 'high'])
            ->count();

        return [
            Stat::make('At-Risk Customers', number_format($totalAtRisk))
                ->description('Showing signs of churn')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->chart([7, 4, 6, 8, 5, 6, 3]),

            Stat::make('Lapsed Customers', number_format($totalLapsed))
                ->description('Inactive 90+ days')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('Won Back (30d)', number_format($totalWonBack))
                ->description('Re-engaged customers')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('success'),

            Stat::make('Pending Alerts', number_format($pendingAlerts))
                ->description('Awaiting action')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color($pendingAlerts > 10 ? 'danger' : 'info'),

            Stat::make('High-Risk Events', number_format($highRiskEvents))
                ->description('Likely to sellout')
                ->descriptionIcon('heroicon-m-fire')
                ->color('warning'),

            Stat::make('Revenue at Risk', number_format($revenueAtRisk, 0) . ' RON')
                ->description('Potential loss from churn')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('danger'),
        ];
    }

    public static function canView(): bool
    {
        return true; // Only visible in Core Admin
    }
}
