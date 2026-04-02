<?php

namespace App\Filament\Tenant\Widgets\Cashless;

use App\Enums\SaleStatus;
use App\Models\VendorSaleItem;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SalesByCategoryChart extends ChartWidget
{
    protected static ?string $heading = 'Sales by Category';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected static ?string $maxHeight = '300px';

    public int $editionId = 0;

    protected function getData(): array
    {
        if (! $this->editionId) {
            return ['datasets' => [], 'labels' => []];
        }

        $categories = VendorSaleItem::where('festival_edition_id', $this->editionId)
            ->whereHas('cashlessSale', fn ($q) => $q->where('status', SaleStatus::Completed))
            ->selectRaw('COALESCE(product_category_name, category_name, \'Other\') as category, SUM(total_cents) as total')
            ->groupBy(DB::raw('COALESCE(product_category_name, category_name, \'Other\')'))
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'datasets' => [
                [
                    'data'            => $categories->pluck('total')->map(fn ($v) => round($v / 100))->toArray(),
                    'backgroundColor' => ['#f59e0b', '#3b82f6', '#10b981', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'],
                ],
            ],
            'labels' => $categories->pluck('category')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
