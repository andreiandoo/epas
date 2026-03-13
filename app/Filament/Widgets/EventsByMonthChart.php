<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class EventsByMonthChart extends ChartWidget
{
    protected ?string $heading = 'Evenimente (12 luni)';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 1;
    protected static ?string $maxHeight = '200px';

    protected function getData(): array
    {
        $data = collect(range(11, 0))->map(function ($monthsAgo) {
            $date = now()->subMonths($monthsAgo);

            return [
                'month' => $date->format('M Y'),
                'count' => Event::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)
                    ->count(),
            ];
        });

        return [
            'datasets' => [
                [
                    'label' => 'Events Created',
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6',
                ],
            ],
            'labels' => $data->pluck('month')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
