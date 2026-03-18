<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected ?string $heading = 'Venituri (12 luni)';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 1;
    protected ?string $maxHeight = '200px';

    protected function getData(): array
    {
        $paidStatuses = ['paid', 'confirmed', 'completed'];

        $data = collect(range(11, 0))->map(function ($monthsAgo) use ($paidStatuses) {
            $date = now()->subMonths($monthsAgo);

            $total = Order::whereIn('status', $paidStatuses)
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->sum('total');

            // Fallback to total_cents if total is 0
            if ($total == 0) {
                $total = Order::whereIn('status', $paidStatuses)
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->sum('total_cents') / 100;
            }

            return [
                'month' => $date->format('M Y'),
                'revenue' => (float) $total,
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
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
