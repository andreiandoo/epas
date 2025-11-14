<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomerStatsOverview extends StatsOverviewWidget
{
    /** IMPORTANT: pe paginile Resource\ViewRecord, Filament injectează $record automat dacă îl declari public */
    public ?Customer $record = null;

    protected function getStats(): array
    {
        $customerId = $this->record?->id;

        $ordersCount = Order::where('customer_id', $customerId)->count();
        $ordersValueCents = Order::where('customer_id', $customerId)->sum('total_cents');
        $tickets = Ticket::whereHas('order', fn ($q) => $q->where('customer_id', $customerId))->count();

        $events = DB::table('events as e')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.customer_id', $customerId)
            ->distinct('e.id')
            ->count('e.id');

        return [
            Stat::make('Orders', (string) $ordersCount),
            Stat::make('Orders value', number_format(($ordersValueCents ?? 0) / 100, 2) . ' RON'),
            Stat::make('Tickets', (string) $tickets),
            Stat::make('Events', (string) $events),
        ];
    }
}
