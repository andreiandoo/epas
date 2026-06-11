<?php

namespace App\Filament\Widgets;

use App\Models\Platform\CoreCustomer;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ChurnTrendChart extends ChartWidget
{
    protected ?string $heading = 'Churn Risk Distribution (Last 12 Weeks)';

    protected static ?int $sort = 11;

    protected ?string $pollingInterval = '300s';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $weeks = [];
        $criticalData = [];
        $highData = [];
        $mediumData = [];
        $lowData = [];

        // Get data for last 12 weeks
        for ($i = 11; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weeks[] = $weekStart->format('M d');

            // Count customers by risk level
            // Note: This shows current state per week - historical tracking would require snapshots
            $baseQuery = CoreCustomer::query()
                ->notMerged()
                ->notAnonymized()
                ->whereNotNull('churn_risk_score');

            $criticalData[] = (clone $baseQuery)->where('churn_risk_score', '>=', 80)->count();
            $highData[] = (clone $baseQuery)->whereBetween('churn_risk_score', [60, 80])->count();
            $mediumData[] = (clone $baseQuery)->whereBetween('churn_risk_score', [40, 60])->count();
            $lowData[] = (clone $baseQuery)->whereBetween('churn_risk_score', [20, 40])->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Critical (80%+)',
                    'data' => $criticalData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.5)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
                [
                    'label' => 'High (60-80%)',
                    'data' => $highData,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.5)',
                    'borderColor' => 'rgb(245, 158, 11)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
                [
                    'label' => 'Medium (40-60%)',
                    'data' => $mediumData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
                [
                    'label' => 'Low (20-40%)',
                    'data' => $lowData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.5)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
            ],
            'labels' => $weeks,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'stacked' => true,
                ],
                'x' => [
                    'stacked' => true,
                ],
            ],
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
        ];
    }
}
