<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\Artist;
use App\Models\Venue;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalEvents = Event::count();
        $activeEvents = Event::where('status', 'published')
            ->where(function ($q) {
                $q->where('event_date', '>=', now()->toDateString())
                    ->orWhere('range_end_date', '>=', now()->toDateString())
                    ->orWhere('range_start_date', '>=', now()->toDateString());
            })
            ->count();

        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();

        $totalUsers = User::count();
        $totalCustomers = Customer::count();

        $totalOrders = Order::count();
        $totalRevenue = Order::where('status', 'completed')->sum('total_cents') / 100;

        $totalTickets = Ticket::count();
        $ticketsSold = Ticket::where('status', 'sold')->count();

        $totalArtists = Artist::count();
        $totalVenues = Venue::count();

        return [
            Stat::make('Total Events', number_format($totalEvents))
                ->description("{$activeEvents} active")
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),

            Stat::make('Tenants', number_format($totalTenants))
                ->description("{$activeTenants} active")
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('success'),

            Stat::make('Users', number_format($totalUsers))
                ->description('Registered accounts')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('Customers', number_format($totalCustomers))
                ->description('Ticket buyers')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('warning'),

            Stat::make('Orders', number_format($totalOrders))
                ->description('Total orders')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('gray'),

            Stat::make('Revenue', '€' . number_format($totalRevenue, 2))
                ->description('Total sales')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Tickets Sold', number_format($ticketsSold))
                ->description("of {$totalTickets} total")
                ->descriptionIcon('heroicon-m-ticket')
                ->color('primary'),

            Stat::make('Artists', number_format($totalArtists))
                ->description("{$totalVenues} venues")
                ->descriptionIcon('heroicon-m-musical-note')
                ->color('info'),
        ];
    }
}
