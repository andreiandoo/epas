<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use Filament\Widgets\ChartWidget;

class TicketSalesChart extends ChartWidget
{
    protected ?string $heading = 'Bilete vândute (30 zile)';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 1;
    protected ?string $maxHeight = '200px';

    protected function getData(): array
    {
        $paidStatuses = ['paid', 'confirmed', 'completed'];

        $data = collect(range(29, 0))->map(function ($daysAgo) use ($paidStatuses) {
            $date = now()->subDays($daysAgo);

            return [
                'day' => $date->format('d M'),
                'count' => Ticket::where('status', 'valid')
                    ->whereHas('order', fn ($q) => $q->whereIn('status', $paidStatuses))
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
