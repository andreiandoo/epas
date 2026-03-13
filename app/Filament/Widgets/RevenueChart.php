<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected ?string $heading = 'Venituri (12 luni)';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 1;
    protected static ?string $maxHeight = '200px';

    protected function getData(): array
    {
        $data = collect(range(11, 0))->map(function ($monthsAgo) {
            $date = now()->subMonths($monthsAgo);

            return [
                'month' => $date->format('M Y'),
                'revenue' => Order::where('status', 'completed')
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->sum('total_cents') / 100,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (€)',
                    'data' => $data->pluck('revenue')->toArray(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.2)',
                    'borderColor' => '#22c55e',
                    'fill' => true,
                ],
            ],
            'labels' => $data->pluck('month')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
