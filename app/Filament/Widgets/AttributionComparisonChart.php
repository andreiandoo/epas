<?php

namespace App\Filament\Widgets;

use App\Models\Platform\PlatformConversion;
use App\Services\Platform\AttributionModelService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class AttributionComparisonChart extends ChartWidget
{
    protected ?string $heading = 'Attribution Model Comparison (Last 30 Days)';

    protected static ?int $sort = 13;

    protected ?string $pollingInterval = '600s';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $data = Cache::remember('widget:attribution_comparison', 3600, function () {
            $attributionService = app(AttributionModelService::class);

            // Get recent conversions with touchpoints
            $conversions = PlatformConversion::query()
                ->where('status', 'confirmed')
                ->where('created_at', '>=', now()->subDays(30))
                ->with('customer.events')
                ->limit(100)
                ->get();

            if ($conversions->isEmpty()) {
                return null;
            }

            // Calculate attribution per model for each channel
            $models = ['first_touch', 'last_touch', 'linear', 'time_decay', 'position_based'];
            $channelsByModel = [];

            foreach ($conversions as $conversion) {
                if (!$conversion->customer) {
                    continue;
                }

                foreach ($models as $model) {
                    $attribution = $attributionService->calculateAttributionForConversion(
                        $conversion->customer,
                        $conversion->value ?? 0,
                        $model
                    );

                    if (!isset($channelsByModel[$model])) {
                        $channelsByModel[$model] = [];
                    }

                    foreach ($attribution['channels'] as $channel => $data) {
                        if (!isset($channelsByModel[$model][$channel])) {
                            $channelsByModel[$model][$channel] = 0;
                        }
                        $channelsByModel[$model][$channel] += $data['attributed_value'] ?? 0;
                    }
                }
            }

            // Get top 5 channels across all models
            $allChannels = [];
            foreach ($channelsByModel as $model => $channels) {
                foreach ($channels as $channel => $value) {
                    if (!isset($allChannels[$channel])) {
                        $allChannels[$channel] = 0;
                    }
                    $allChannels[$channel] += $value;
                }
            }
            arsort($allChannels);
            $topChannels = array_slice(array_keys($allChannels), 0, 5);

            return [
                'models' => $models,
                'channels' => $topChannels,
                'channelsByModel' => $channelsByModel,
            ];
        });

        if (!$data) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $colors = [
            'first_touch' => ['bg' => 'rgba(59, 130, 246, 0.7)', 'border' => 'rgb(59, 130, 246)'],
            'last_touch' => ['bg' => 'rgba(34, 197, 94, 0.7)', 'border' => 'rgb(34, 197, 94)'],
            'linear' => ['bg' => 'rgba(168, 85, 247, 0.7)', 'border' => 'rgb(168, 85, 247)'],
            'time_decay' => ['bg' => 'rgba(245, 158, 11, 0.7)', 'border' => 'rgb(245, 158, 11)'],
            'position_based' => ['bg' => 'rgba(236, 72, 153, 0.7)', 'border' => 'rgb(236, 72, 153)'],
        ];

        $modelLabels = [
            'first_touch' => 'First Touch',
            'last_touch' => 'Last Touch',
            'linear' => 'Linear',
            'time_decay' => 'Time Decay',
            'position_based' => 'Position Based',
        ];

        $datasets = [];
        foreach ($data['models'] as $model) {
            $values = [];
            foreach ($data['channels'] as $channel) {
                $values[] = round($data['channelsByModel'][$model][$channel] ?? 0, 2);
            }

            $datasets[] = [
                'label' => $modelLabels[$model],
                'data' => $values,
                'backgroundColor' => $colors[$model]['bg'],
                'borderColor' => $colors[$model]['border'],
                'borderWidth' => 1,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => array_map(fn ($c) => ucfirst($c ?: 'Direct'), $data['channels']),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(context) { return context.dataset.label + ': $' + context.raw.toLocaleString(); }",
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => "function(value) { return '$' + value.toLocaleString(); }",
                    ],
                ],
            ],
        ];
    }
}
