<?php

namespace App\Filament\Tenant\Widgets\Cashless;

use App\Services\Cashless\ReportService;
use Filament\Widgets\ChartWidget;

class TopVendorsChart extends ChartWidget
{
    protected ?string $heading = 'Top Vendors by Revenue';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '300px';

    public int $editionId = 0;

    protected function getData(): array
    {
        if (! $this->editionId) {
            return ['datasets' => [], 'labels' => []];
        }

        $vendors = app(ReportService::class)->salesPerVendor($this->editionId);
        $top10 = array_slice($vendors, 0, 10);

        return [
            'datasets' => [
                [
                    'label'           => 'Revenue (RON)',
                    'data'            => array_map(fn ($v) => round($v['revenue_cents'] / 100), $top10),
                    'backgroundColor' => '#f59e0b',
                ],
            ],
            'labels' => array_map(fn ($v) => $v['vendor_name'], $top10),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
