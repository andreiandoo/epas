<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Models\Customer;
use App\Models\Order;
use Filament\Widgets\ChartWidget;

class CustomerOrdersByMonthChart extends ChartWidget
{
    protected ?string $heading = 'Orders by Month'; // NON-STATIC
    public ?Customer $record = null;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        if (! $this->record) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Construim serii lunare (YYYY-MM)
        $rows = Order::query()
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, COUNT(*) as cnt, SUM(total_cents) as total")
            ->where('customer_id', $this->record->id)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $labels = $rows->pluck('month')->all();
        $counts = $rows->pluck('cnt')->all();
        $totals = $rows->pluck('total')->map(fn ($c) => (int) $c / 100)->all();

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data'  => $counts,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Revenue (RON)',
                    'data'  => $totals,
                    'tension' => 0.3,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
            'options' => [
                'responsive' => true,
                'interaction' => ['mode' => 'index', 'intersect' => false],
                'scales' => [
                    'y'  => ['beginAtZero' => true],
                    'y1' => ['beginAtZero' => true, 'position' => 'right'],
                ],
            ],
        ];
    }
}
