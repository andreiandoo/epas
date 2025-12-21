<?php

namespace App\Filament\Organizer\Widgets;

use App\Models\Event;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrganizerStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $organizer = auth('organizer')->user()?->organizer;

        if (!$organizer) {
            return [];
        }

        $totalEvents = $organizer->events()->count();
        $activeEvents = $organizer->events()->where('status', 'published')->count();
        $totalOrders = Order::where('organizer_id', $organizer->id)->count();
        $totalRevenue = Order::where('organizer_id', $organizer->id)
            ->whereIn('status', ['paid', 'completed'])
            ->sum('organizer_revenue');

        $pendingPayout = $organizer->pending_payout ?? 0;

        // Calculate this month's stats
        $thisMonthOrders = Order::where('organizer_id', $organizer->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $thisMonthRevenue = Order::where('organizer_id', $organizer->id)
            ->whereIn('status', ['paid', 'completed'])
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('organizer_revenue');

        return [
            Stat::make('Total Events', $totalEvents)
                ->description($activeEvents . ' active')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),

            Stat::make('Total Orders', $totalOrders)
                ->description($thisMonthOrders . ' this month')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('success'),

            Stat::make('Total Revenue', number_format($totalRevenue, 2) . ' RON')
                ->description(number_format($thisMonthRevenue, 2) . ' this month')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('success'),

            Stat::make('Pending Payout', number_format($pendingPayout, 2) . ' RON')
                ->description('Available for withdrawal')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
        ];
    }
}
