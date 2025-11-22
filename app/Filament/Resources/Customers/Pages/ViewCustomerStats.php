<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Widgets\CustomerStatsOverview;
use App\Filament\Resources\Customers\Widgets\CustomerOrdersByMonthChart;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Ticket;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewCustomerStats extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    /** Date expuse blade-ului pentru liste/tabele simple */
    public array $totals = [];
    public array $monthlyOrders = [];
    public array $preferredGenres = [];
    public array $eventsList = [];
    public array $artistsList = [];
    public array $tenantsList = [];

    public function mount($record): void
    {
        parent::mount($record);

        /** @var Customer $customer */
        $customer = $this->record;

        // Totals
        $ordersBase = Order::query()->where('customer_id', $customer->id);

        $this->totals = [
            'orders_count' => (clone $ordersBase)->count(),
            'orders_value' => (clone $ordersBase)->sum('total_cents'),
            'tickets'      => Ticket::query()
                ->whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
                ->count(),
            'events'       => DB::table('events as e')
                ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
                ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
                ->join('orders as o', 'o.id', '=', 't.order_id')
                ->where('o.customer_id', $customer->id)
                ->distinct('e.id')
                ->count('e.id'),
        ];

        // Monthly orders (YYYY-MM)
        $this->monthlyOrders = Order::query()
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, COUNT(*) as cnt")
            ->where('customer_id', $customer->id)
            ->groupBy('month')
            ->orderBy('month')
            ->pluck('cnt', 'month')
            ->toArray();

        // Top event genres
        $this->preferredGenres = $this->topEventGenres();

        // Events list (last 20 distinct)
        $this->eventsList = DB::table('events as e')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.customer_id', $customer->id)
            ->select('e.id', 'e.title')
            ->distinct()
            ->orderByDesc('e.id')
            ->limit(20)
            ->get()
            ->toArray();

        // Top artists (by count)
        $this->artistsList = DB::table('artists as a')
            ->join('event_artist as ea', 'ea.artist_id', '=', 'a.id')
            ->join('events as e', 'e.id', '=', 'ea.event_id')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.customer_id', $customer->id)
            ->select('a.id', 'a.name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('a.id', 'a.name')
            ->orderByDesc('cnt')
            ->limit(20)
            ->get()
            ->toArray();

        // Tenants list with totals
        $this->tenantsList = DB::table('tenants as tn')
            ->join('orders as o', 'o.tenant_id', '=', 'tn.id')
            ->where('o.customer_id', $customer->id)
            ->select('tn.id', 'tn.name', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(o.total_cents) as total'))
            ->groupBy('tn.id', 'tn.name')
            ->orderByDesc('total')
            ->get()
            ->toArray();
    }

    /** Titlul paginii (public în Filament v4) */
    public function getTitle(): string
    {
        $name = $this->record->full_name
            ?? trim(($this->record->first_name ?? '') . ' ' . ($this->record->last_name ?? ''));
        $label = $name !== '' ? $name : ($this->record->email ?? 'Customer');

        return "Customer Statistics: {$label}";
    }

    /** HEAD widgets (stat cards + chart) — Filament v4 */
    protected function getHeaderWidgets(): array
    {
        return [
            CustomerStatsOverview::class,
            CustomerOrdersByMonthChart::class, // presupunând că deja îl ai; are public ?Customer $record
        ];
    }

    /** Blade-ul custom al paginii */
    public function getView(): string
    {
        return 'filament.customers.pages.customer-stats';
    }

    // ---------- Helpers ----------

    private function topEventGenres(): array
    {
        $customerId = $this->record->id;

        return DB::table('event_event_genre as eeg')
            ->join('event_genres as eg', 'eg.id', '=', 'eeg.event_genre_id')
            ->join('events as e', 'e.id', '=', 'eeg.event_id')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.customer_id', $customerId)
            ->groupBy('eg.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(10)
            ->pluck(DB::raw('COUNT(*) as cnt'), 'eg.name')
            ->toArray();
    }

    private function topEventCategories(): array
    {
        $customerId = $this->record->id;

        return DB::table('event_event_category as eec')
            ->join('event_categories as ec', 'ec.id', '=', 'eec.event_category_id')
            ->join('events as e', 'e.id', '=', 'eec.event_id')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.customer_id', $customerId)
            ->groupBy('ec.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(10)
            ->pluck(DB::raw('COUNT(*) as cnt'), 'ec.name')
            ->toArray();
    }

    private function topMusicGenres(): array
    {
        $customerId = $this->record->id;

        return DB::table('event_music_genre as emg')
            ->join('music_genres as mg', 'mg.id', '=', 'emg.music_genre_id')
            ->join('events as e', 'e.id', '=', 'emg.event_id')
            ->join('ticket_types as tt', 'tt.event_id', '=', 'e.id')
            ->join('tickets as t', 't.ticket_type_id', '=', 'tt.id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('o.customer_id', $customerId)
            ->groupBy('mg.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(10)
            ->pluck(DB::raw('COUNT(*) as cnt'), 'mg.name')
            ->toArray();
    }
}
