<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Models\Customer;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class CustomerTopGenresChart extends ChartWidget
{
    protected ?string $heading = 'Top Genres'; // NON-STATIC
    public ?Customer $record = null;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        if (! $this->record) {
            return ['datasets' => [], 'labels' => []];
        }

        // Adaptează numele tabelelor pivot/genre conform bazei tale
        $rows = DB::table('event_genre')
            ->join('events', 'events.id', '=', 'event_genre.event_id')
            ->join('ticket_types', 'ticket_types.event_id', '=', 'events.id')
            ->join('tickets', 'tickets.ticket_type_id', '=', 'ticket_types.id')
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->join('genres', 'genres.id', '=', 'event_genre.genre_id')
            ->where('orders.customer_id', $this->record->id)
            ->select('genres.name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('genres.name')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        $labels = $rows->pluck('name')->all();
        $data   = $rows->pluck('cnt')->all();

        return [
            'datasets' => [
                [
                    'label' => 'Tickets by Genre',
                    'data'  => $data,
                ],
            ],
            'labels' => $labels,
            'options' => [
                'indexAxis' => 'y',
                'scales' => [
                    'x' => ['beginAtZero' => true],
                ],
            ],
        ];
    }
}
