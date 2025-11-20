<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use Filament\Widgets\ChartWidget;

class TicketSalesChart extends ChartWidget
{
    protected ?string $heading = 'Tickets Sold (Last 30 Days)';
    protected static ?int $sort = 7;
    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $data = collect(range(29, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo);

            return [
                'day' => $date->format('d M'),
                'count' => Ticket::where('status', 'sold')
                    ->whereDate('created_at', $date->toDateString())
                    ->count(),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Tickets Sold',
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => 'rgba(139, 92, 246, 0.2)',
                    'borderColor' => '#8b5cf6',
                    'fill' => true,
                ],
            ],
            'labels' => $data->pluck('day')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
