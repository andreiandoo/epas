<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class RevenueChart extends ChartWidget
{
    protected ?string $heading = 'Venituri (12 luni)';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 1;
    protected ?string $maxHeight = '200px';

    protected function getData(): array
    {
        $data = Cache::remember('widget.revenue_chart.' . now()->format('Y-m-d-H'), 300, function () {
            $paidStatuses = ['paid', 'confirmed', 'completed'];

            return collect(range(11, 0))->map(function ($monthsAgo) use ($paidStatuses) {
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
            })->toArray();
        });

        $data = collect($data);

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
