<?php

namespace App\Filament\Widgets;

use App\Models\ExchangeRate;
use App\Models\Order;
use App\Models\Ticket;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LiveStatsCards extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $paidStatuses = ['paid', 'confirmed', 'completed'];

        $totalOrders = Order::whereIn('status', $paidStatuses)->count();
        $todayOrders = Order::whereIn('status', $paidStatuses)->whereDate('created_at', today())->count();

        // Try total (decimal) first, fallback to total_cents
        $totalRevenue = Order::whereIn('status', $paidStatuses)->sum('total');
        $todayRevenue = Order::whereIn('status', $paidStatuses)->whereDate('created_at', today())->sum('total');
        if ($totalRevenue == 0) {
            $totalRevenue = Order::whereIn('status', $paidStatuses)->sum('total_cents') / 100;
            $todayRevenue = Order::whereIn('status', $paidStatuses)->whereDate('created_at', today())->sum('total_cents') / 100;
        }

        $totalTickets = Ticket::where('status', 'valid')->count();
        $todayTickets = Ticket::where('status', 'valid')->whereDate('created_at', today())->count();

        $totalCommissions = Order::whereIn('status', $paidStatuses)->sum('commission_amount');
        $todayCommissions = Order::whereIn('status', $paidStatuses)->whereDate('created_at', today())->sum('commission_amount');

        // Exchange rate EUR → RON
        $eurRon = ExchangeRate::getLatestRate('EUR', 'RON');
        $exchangeDate = ExchangeRate::where('base_currency', 'EUR')
            ->where('target_currency', 'RON')
            ->orderByDesc('date')
            ->first();

        return [
            Stat::make('Comenzi', number_format($totalOrders))
                ->description("+{$todayOrders} azi")
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('primary'),

            Stat::make('Valoare Comenzi', '€' . number_format($totalRevenue, 2))
                ->description('+€' . number_format($todayRevenue, 2) . ' azi')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Bilete Vândute', number_format($totalTickets))
                ->description("+{$todayTickets} azi")
                ->descriptionIcon('heroicon-m-ticket')
                ->color('warning'),

            Stat::make('Comisioane Tixello', '€' . number_format($totalCommissions, 2))
                ->description('+€' . number_format($todayCommissions, 2) . ' azi')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('success'),

            Stat::make('Curs EUR/RON', $eurRon ? number_format($eurRon, 4) : 'N/A')
                ->description($exchangeDate ? $exchangeDate->date->format('d.m.Y') : 'Niciun curs')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('gray'),
        ];
    }
}
