<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrdersByStatusChart extends ChartWidget
{
    protected ?string $heading = 'Orders by Status';
    protected static ?int $sort = 99;
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return false;
    }

    protected function getData(): array
    {
        $statuses = Order::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => array_values($statuses),
                    'backgroundColor' => [
                        '#22c55e', // completed - green
                        '#f59e0b', // pending - amber
                        '#ef4444', // cancelled - red
                        '#6b7280', // refunded - gray
                    ],
                ],
            ],
            'labels' => array_map('ucfirst', array_keys($statuses)),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
