<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
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
        $totalCustomers = Customer::count();
        $todayCustomers = Customer::whereDate('created_at', today())->count();

        $totalOrders = Order::where('status', 'completed')->count();
        $todayOrders = Order::where('status', 'completed')->whereDate('created_at', today())->count();

        $totalRevenue = Order::where('status', 'completed')->sum('total_cents') / 100;
        $todayRevenue = Order::where('status', 'completed')->whereDate('created_at', today())->sum('total_cents') / 100;

        $totalTickets = Ticket::where('status', 'sold')->count();
        $todayTickets = Ticket::where('status', 'sold')->whereDate('created_at', today())->count();

        $totalCommissions = Order::where('status', 'completed')->sum('commission_amount');
        $todayCommissions = Order::where('status', 'completed')->whereDate('created_at', today())->sum('commission_amount');

        // Exchange rate EUR → RON
        $eurRon = ExchangeRate::getLatestRate('EUR', 'RON');
        $exchangeDate = ExchangeRate::where('base_currency', 'EUR')
            ->where('target_currency', 'RON')
            ->orderByDesc('date')
            ->first();

        return [
            Stat::make('Clienți', number_format($totalCustomers))
                ->description("+{$todayCustomers} azi")
                ->descriptionIcon('heroicon-m-user-group')
                ->color('info'),

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
