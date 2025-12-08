<?php

namespace App\Filament\Widgets;

use App\Models\Platform\CoreCustomer;
use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use App\Models\Platform\PlatformAdAccount;
use App\Models\Platform\PlatformConversion;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformTrackingStats extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        // Active visitors (real-time)
        $activeVisitors = CoreSession::notBot()->active()->count();

        // Today's stats
        $todayConversions = CoreCustomerEvent::purchases()->today()->count();
        $todayRevenue = CoreCustomerEvent::purchases()->today()->sum('conversion_value');
        $todaySessions = CoreSession::notBot()->today()->count();

        // Yesterday comparison
        $yesterdayConversions = CoreCustomerEvent::purchases()
            ->whereDate('created_at', now()->subDay())
            ->count();
        $yesterdayRevenue = CoreCustomerEvent::purchases()
            ->whereDate('created_at', now()->subDay())
            ->sum('conversion_value');

        // Conversion trend
        $conversionTrend = $yesterdayConversions > 0
            ? round((($todayConversions - $yesterdayConversions) / $yesterdayConversions) * 100, 1)
            : ($todayConversions > 0 ? 100 : 0);
        $revenueTrend = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : ($todayRevenue > 0 ? 100 : 0);

        // Conversion rate
        $conversionRate = $todaySessions > 0
            ? round(($todayConversions / $todaySessions) * 100, 2)
            : 0;

        // Total platform customers
        $totalCustomers = CoreCustomer::count();
        $identifiedCustomers = CoreCustomer::whereNotNull('email_hash')->count();

        // Failed conversions needing attention
        $failedConversions = PlatformConversion::failed()
            ->where('retry_count', '<', 5)
            ->count();

        // Ad accounts with issues
        $accountsWithIssues = PlatformAdAccount::active()
            ->where(function ($q) {
                $q->whereNotNull('token_expires_at')
                  ->where('token_expires_at', '<=', now()->addDays(3));
            })
            ->count();

        return [
            Stat::make('Active Visitors', number_format($activeVisitors))
                ->description('Right now')
                ->descriptionIcon('heroicon-m-eye')
                ->color('success')
                ->chart($this->getVisitorTrend())
                ->url(route('filament.admin.pages.platform-analytics')),

            Stat::make('Today\'s Conversions', number_format($todayConversions))
                ->description($this->formatTrend($conversionTrend))
                ->descriptionIcon($conversionTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($conversionTrend >= 0 ? 'success' : 'danger')
                ->url(route('filament.admin.pages.platform-analytics')),

            Stat::make('Today\'s Revenue', '$' . number_format($todayRevenue, 2))
                ->description($this->formatTrend($revenueTrend))
                ->descriptionIcon($revenueTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueTrend >= 0 ? 'success' : 'danger')
                ->url(route('filament.admin.pages.platform-analytics')),

            Stat::make('Conversion Rate', $conversionRate . '%')
                ->description("{$todaySessions} sessions today")
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info')
                ->url(route('filament.admin.pages.attribution-report')),

            Stat::make('Platform Customers', number_format($totalCustomers))
                ->description("{$identifiedCustomers} identified")
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary')
                ->url(route('filament.admin.resources.core-customers.index')),

            Stat::make('Issues', $failedConversions + $accountsWithIssues)
                ->description($this->getIssueDescription($failedConversions, $accountsWithIssues))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($failedConversions + $accountsWithIssues > 0 ? 'danger' : 'success')
                ->url(route('filament.admin.resources.platform-ad-accounts.index')),
        ];
    }

    protected function formatTrend(float $trend): string
    {
        if ($trend === 0.0) {
            return 'No change vs yesterday';
        }
        $direction = $trend > 0 ? '+' : '';
        return "{$direction}{$trend}% vs yesterday";
    }

    protected function getIssueDescription(int $failed, int $accounts): string
    {
        $parts = [];
        if ($failed > 0) {
            $parts[] = "{$failed} failed conv.";
        }
        if ($accounts > 0) {
            $parts[] = "{$accounts} token issues";
        }
        return empty($parts) ? 'All systems healthy' : implode(', ', $parts);
    }

    protected function getVisitorTrend(): array
    {
        // Get hourly visitor counts for the last 6 hours
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $hour = now()->subHours($i);
            $count = CoreSession::notBot()
                ->whereBetween('started_at', [$hour->copy()->startOfHour(), $hour->copy()->endOfHour()])
                ->count();
            $trend[] = $count;
        }
        return $trend;
    }
}
