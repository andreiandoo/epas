<?php

namespace App\Filament\Widgets;

use App\Models\Platform\CoreCustomerEvent;
use App\Models\Platform\CoreSession;
use Filament\Widgets\ChartWidget;

class ConversionFunnelChart extends ChartWidget
{
    protected ?string $heading = 'Conversion Funnel (Today)';

    protected static ?int $sort = 3;

    protected static ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        // Get today's funnel data
        $sessions = CoreSession::notBot()->today()->count();
        $pageViews = CoreCustomerEvent::pageViews()->today()->distinct('session_id')->count('session_id');
        $addToCarts = CoreCustomerEvent::where('event_type', 'add_to_cart')->today()->distinct('session_id')->count('session_id');
        $checkouts = CoreCustomerEvent::where('event_type', 'begin_checkout')->today()->distinct('session_id')->count('session_id');
        $purchases = CoreCustomerEvent::purchases()->today()->distinct('session_id')->count('session_id');

        return [
            'datasets' => [
                [
                    'label' => 'Sessions',
                    'data' => [$sessions, $pageViews, $addToCarts, $checkouts, $purchases],
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',  // blue
                        'rgba(99, 102, 241, 0.8)',  // indigo
                        'rgba(168, 85, 247, 0.8)', // purple
                        'rgba(236, 72, 153, 0.8)', // pink
                        'rgba(34, 197, 94, 0.8)',  // green
                    ],
                    'borderColor' => [
                        'rgb(59, 130, 246)',
                        'rgb(99, 102, 241)',
                        'rgb(168, 85, 247)',
                        'rgb(236, 72, 153)',
                        'rgb(34, 197, 94)',
                    ],
                    'borderWidth' => 1,
                ],
            ],
            'labels' => [
                "Sessions ({$sessions})",
                "Page Views ({$pageViews})",
                "Add to Cart ({$addToCarts})",
                "Checkout ({$checkouts})",
                "Purchase ({$purchases})",
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
