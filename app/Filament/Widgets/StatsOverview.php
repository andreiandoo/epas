<?php

namespace App\Filament\Widgets;

use App\Models\Artist;
use App\Models\Customer;
use App\Models\Event;
use App\Models\ExchangeRate;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Models\Seating\SeatingLayout;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\Venue;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        // Exchange rate
        $eurRon = ExchangeRate::getLatestRate('EUR', 'RON') ?: 1;
        $exchangeDate = ExchangeRate::where('base_currency', 'EUR')
            ->where('target_currency', 'RON')
            ->orderByDesc('date')
            ->first();

        $paidStatuses = ['paid', 'confirmed', 'completed'];

        // Events
        $totalEvents = Event::count();
        $activeEvents = Event::where('status', 'published')
            ->where(function ($q) {
                $q->where('event_date', '>=', now()->toDateString())
                    ->orWhere('range_end_date', '>=', now()->toDateString())
                    ->orWhere('range_start_date', '>=', now()->toDateString());
            })
            ->count();

        // Tenants & Marketplaces
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $totalMarketplaces = MarketplaceClient::count();
        $activeMarketplaces = MarketplaceClient::where('status', 'active')->count();

        // Customers
        $tenantCustomers = Customer::count();
        $marketplaceCustomers = MarketplaceCustomer::count();

        // Orders (paid/confirmed/completed)
        $paidOrders = Order::whereIn('status', $paidStatuses)->count();
        $todayPaidOrders = Order::whereIn('status', $paidStatuses)->whereDate('created_at', today())->count();

        // Revenue (paid/confirmed/completed) - split by currency, convert to EUR
        $revenueEur = $this->sumByCurrency($paidStatuses, 'EUR');
        $revenueRon = $this->sumByCurrency($paidStatuses, 'RON');
        $todayRevenueEur = $this->sumByCurrency($paidStatuses, 'EUR', true);
        $todayRevenueRon = $this->sumByCurrency($paidStatuses, 'RON', true);
        $totalRevenueEur = $revenueEur + ($eurRon > 0 ? $revenueRon / $eurRon : 0);
        $totalRevenueRon = $totalRevenueEur * $eurRon;
        $todayTotalEur = $todayRevenueEur + ($eurRon > 0 ? $todayRevenueRon / $eurRon : 0);

        // All orders value (any status)
        $allOrdersEur = $this->sumByCurrency(null, 'EUR');
        $allOrdersRon = $this->sumByCurrency(null, 'RON');
        $allOrdersTotalEur = $allOrdersEur + ($eurRon > 0 ? $allOrdersRon / $eurRon : 0);
        $allOrdersTotalRon = $allOrdersTotalEur * $eurRon;

        // Tickets (only Tixello-issued: valid tickets from paid orders)
        $ticketsIssued = Ticket::where('status', 'valid')
            ->whereHas('order', fn ($q) => $q->whereIn('status', $paidStatuses))
            ->count();
        $todayTicketsIssued = Ticket::where('status', 'valid')
            ->whereHas('order', fn ($q) => $q->whereIn('status', $paidStatuses))
            ->whereDate('created_at', today())
            ->count();
        $totalTickets = Ticket::count();

        // Commissions
        $totalCommissions = Order::whereIn('status', $paidStatuses)->sum('commission_amount');
        $todayCommissions = Order::whereIn('status', $paidStatuses)->whereDate('created_at', today())->sum('commission_amount');

        // Artists, Venues, Seating Maps
        $totalArtists = Artist::count();
        $totalVenues = Venue::count();
        $totalSeatingMaps = SeatingLayout::count();

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

            Stat::make('Comenzi', number_format($paidOrders))
                ->description("+{$todayPaidOrders} azi")
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('gray')
                ->url(route('filament.admin.resources.orders.index')),

            Stat::make('Venituri', '€' . number_format($totalRevenueEur, 2))
                ->description(number_format($totalRevenueRon, 2) . ' RON | +€' . number_format($todayTotalEur, 2) . ' azi')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Valoare Comenzi', '€' . number_format($allOrdersTotalEur, 2))
                ->description(number_format($allOrdersTotalRon, 2) . ' RON | toate statusurile')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('info'),

            Stat::make('Bilete Emise', number_format($ticketsIssued))
                ->description("+{$todayTicketsIssued} azi | " . number_format($totalTickets) . " total în DB")
                ->descriptionIcon('heroicon-m-ticket')
                ->color('primary')
                ->url(route('filament.admin.resources.tickets.index')),

            Stat::make('Comisioane Tixello', '€' . number_format($totalCommissions, 2))
                ->description('+€' . number_format($todayCommissions, 2) . ' azi')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('success'),

            Stat::make('Artiști', number_format($totalArtists))
                ->descriptionIcon('heroicon-m-musical-note')
                ->color('info')
                ->url(route('filament.admin.resources.artists.index')),

            Stat::make('Locații', number_format($totalVenues))
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('warning')
                ->url(route('filament.admin.resources.venues.index')),

            Stat::make('Hărți Locuri', number_format($totalSeatingMaps))
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color('gray'),

            Stat::make('Curs EUR/RON', $eurRon != 1 ? number_format($eurRon, 4) : 'N/A')
                ->description($exchangeDate ? $exchangeDate->date->format('d.m.Y') : 'Niciun curs')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('gray'),
        ];
    }

    private function sumByCurrency(?array $statuses, string $currency, bool $todayOnly = false): float
    {
        $query = Order::where('currency', $currency);

        if ($statuses) {
            $query->whereIn('status', $statuses);
        }

        if ($todayOnly) {
            $query->whereDate('created_at', today());
        }

        $total = $query->sum('total');

        if ($total == 0 && $currency === 'EUR') {
            $fallbackQuery = Order::where('currency', $currency);
            if ($statuses) {
                $fallbackQuery->whereIn('status', $statuses);
            }
            if ($todayOnly) {
                $fallbackQuery->whereDate('created_at', today());
            }
            $total = $fallbackQuery->sum('total_cents') / 100;
        }

        return (float) $total;
    }
}
