<?php

namespace App\Filament\Widgets;

use App\Models\SystemError;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 24h trend line, bucket = 1h, three series stacked: critical, error, warning.
 * Polling cadence is configurable; defaults to 30s for a sensible balance
 * between freshness and DB load.
 */
class SystemErrorTrendsChartWidget extends ChartWidget
{
    protected ?string $heading = 'Errors per hour (last 24h)';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected ?string $pollingInterval = null;

    public function __construct()
    {
        $this->pollingInterval = config('system_errors.polling.chart', 30) . 's';
    }

    public static function canView(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $end = Carbon::now()->startOfHour()->addHour();
        $start = $end->copy()->subHours(24);

        // Build 24 hourly buckets keyed by Y-m-d H:00
        $buckets = [];
        for ($i = 0; $i < 24; $i++) {
            $ts = $start->copy()->addHours($i);
            $buckets[$ts->format('Y-m-d H:00')] = [
                'critical' => 0,
                'error' => 0,
                'warning' => 0,
            ];
        }

        // Single grouped query for the whole window.
        $rows = SystemError::query()
            ->selectRaw("DATE_TRUNC('hour', created_at) AS bucket, level, COUNT(*) AS c")
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->groupByRaw("DATE_TRUNC('hour', created_at), level")
            ->get();

        foreach ($rows as $row) {
            $key = Carbon::parse($row->bucket)->format('Y-m-d H:00');
            if (!isset($buckets[$key])) {
                continue;
            }
            $level = (int) $row->level;
            $count = (int) $row->c;
            if ($level >= 500) {
                $buckets[$key]['critical'] += $count;
            } elseif ($level >= 400) {
                $buckets[$key]['error'] += $count;
            } elseif ($level >= 300) {
                $buckets[$key]['warning'] += $count;
            }
        }

        $labels = array_map(
            fn (string $key) => Carbon::parse($key)->format('H:00'),
            array_keys($buckets)
        );
        $critical = array_column($buckets, 'critical');
        $error = array_column($buckets, 'error');
        $warning = array_column($buckets, 'warning');

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Critical+',
                    'data' => array_values($critical),
                    'borderColor' => '#dc2626',
                    'backgroundColor' => 'rgba(220, 38, 38, 0.15)',
                    'tension' => 0.3,
                    'fill' => false,
                ],
                [
                    'label' => 'Error',
                    'data' => array_values($error),
                    'borderColor' => '#f97316',
                    'backgroundColor' => 'rgba(249, 115, 22, 0.15)',
                    'tension' => 0.3,
                    'fill' => false,
                ],
                [
                    'label' => 'Warning',
                    'data' => array_values($warning),
                    'borderColor' => '#eab308',
                    'backgroundColor' => 'rgba(234, 179, 8, 0.15)',
                    'tension' => 0.3,
                    'fill' => false,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'top'],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
            'interaction' => ['mode' => 'index', 'intersect' => false],
        ];
    }
}
