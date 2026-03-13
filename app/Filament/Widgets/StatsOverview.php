<?php

namespace App\Filament\Widgets;

use App\Models\Artist;
use App\Models\Customer;
use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Ticket;
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
        $totalMarketplaces = MarketplaceClient::count();
        $activeMarketplaces = MarketplaceClient::where('status', 'active')->count();

        $tenantCustomers = Customer::count();
        $marketplaceCustomers = MarketplaceCustomer::count();

        $totalOrders = Order::whereIn('status', ['paid', 'confirmed', 'completed'])->count();

        // Try total (decimal) first, fallback to total_cents
        $totalRevenue = Order::whereIn('status', ['paid', 'confirmed', 'completed'])->sum('total');
        if ($totalRevenue == 0) {
            $totalRevenue = Order::whereIn('status', ['paid', 'confirmed', 'completed'])->sum('total_cents') / 100;
        }

        $totalTickets = Ticket::count();
        $ticketsValid = Ticket::where('status', 'valid')->count();

        $totalArtists = Artist::count();
        $totalVenues = Venue::count();

        return [
            Stat::make('Evenimente', number_format($totalEvents))
                ->description("{$activeEvents} active")
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary')
                ->url(route('filament.admin.resources.events.index')),

            Stat::make('Tenants / Marketplaces', "{$totalTenants} / {$totalMarketplaces}")
                ->description("{$activeTenants} tenants activi, {$activeMarketplaces} mp active")
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('success')
                ->url(route('filament.admin.resources.tenants.index')),

            Stat::make('Clienți', number_format($tenantCustomers + $marketplaceCustomers))
                ->description("{$tenantCustomers} tenant, {$marketplaceCustomers} marketplace")
                ->descriptionIcon('heroicon-m-user-group')
                ->color('warning'),

            Stat::make('Comenzi', number_format($totalOrders))
                ->description('Plătite / confirmate')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('gray')
                ->url(route('filament.admin.resources.orders.index')),

            Stat::make('Venituri', '€' . number_format($totalRevenue, 2))
                ->description('Total vânzări')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Bilete Vândute', number_format($ticketsValid))
                ->description("din " . number_format($totalTickets) . " total")
                ->descriptionIcon('heroicon-m-ticket')
                ->color('primary')
                ->url(route('filament.admin.resources.tickets.index')),

            Stat::make('Artiști', number_format($totalArtists))
                ->description("{$totalVenues} locații")
                ->descriptionIcon('heroicon-m-musical-note')
                ->color('info')
                ->url(route('filament.admin.resources.artists.index')),
        ];
    }
}
