<?php

namespace App\Filament\Organizer\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class RevenueChart extends ChartWidget
{
    protected static ?string $heading = 'Revenue Overview';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $organizer = auth('organizer')->user()?->organizer;

        if (!$organizer) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        // Get last 6 months of revenue data
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(Carbon::now()->subMonths($i));
        }

        $revenueData = $months->map(function ($month) use ($organizer) {
            return Order::where('organizer_id', $organizer->id)
                ->whereIn('status', ['paid', 'completed'])
                ->whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->sum('organizer_revenue');
        })->toArray();

        $ordersData = $months->map(function ($month) use ($organizer) {
            return Order::where('organizer_id', $organizer->id)
                ->whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->count();
        })->toArray();

        $labels = $months->map(fn ($month) => $month->format('M Y'))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (RON)',
                    'data' => $revenueData,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Orders',
                    'data' => $ordersData,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'fill' => true,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
        ];
    }
}
