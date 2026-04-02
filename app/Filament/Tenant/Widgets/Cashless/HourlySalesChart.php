<?php

namespace App\Filament\Tenant\Widgets\Cashless;

use App\Services\Cashless\ReportService;
use Filament\Widgets\ChartWidget;

class HourlySalesChart extends ChartWidget
{
    protected static ?string $heading = 'Sales per Hour (Today)';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '250px';

    protected ?string $pollingInterval = '60s';

    public int $editionId = 0;

    protected function getData(): array
    {
        if (! $this->editionId) {
            return ['datasets' => [], 'labels' => []];
        }

        $hourly = app(ReportService::class)->hourlySales(
            $this->editionId,
            date: today()->toDateString()
        );

        // Fill all 24 hours
        $byHour = collect($hourly)->keyBy('hour');
        $labels = [];
        $revenue = [];
        $count = [];

        for ($h = 0; $h < 24; $h++) {
            $labels[] = sprintf('%02d:00', $h);
            $data = $byHour->get($h);
            $revenue[] = $data ? round($data['revenue_cents'] / 100) : 0;
            $count[] = $data ? $data['sales_count'] : 0;
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Revenue (RON)',
                    'data'            => $revenue,
                    'borderColor'     => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill'            => true,
                ],
                [
                    'label'           => 'Transactions',
                    'data'            => $count,
                    'borderColor'     => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill'            => true,
                    'yAxisID'         => 'y1',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
