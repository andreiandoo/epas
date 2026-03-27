<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Ticket;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class ConversionFunnelChart extends ChartWidget
{
    protected ?string $heading = 'Conversii (azi)';

    protected static ?int $sort = 4;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '200px';

    protected function getData(): array
    {
        $cached = Cache::remember('widget.conversion_funnel.' . now()->format('Y-m-d-H-i'), 120, function () {
            $paidStatuses = ['paid', 'confirmed', 'completed'];

            $todayOrders = Order::whereDate('created_at', today())->count();
            $todayPaid = Order::whereIn('status', $paidStatuses)->whereDate('created_at', today())->count();
            $todayCancelled = Order::where('status', 'cancelled')->whereDate('created_at', today())->count();
            $todayPending = Order::where('status', 'pending')->whereDate('created_at', today())->count();
            $todayTickets = Ticket::where('status', 'valid')
                ->whereHas('order', fn ($q) => $q->whereIn('status', $paidStatuses))
                ->whereDate('created_at', today())
                ->count();

            return compact('todayOrders', 'todayPaid', 'todayCancelled', 'todayPending', 'todayTickets');
        });

        extract($cached);

        return [
            'datasets' => [
                [
                    'label' => 'Count',
                    'data' => [$todayOrders, $todayPaid, $todayTickets, $todayPending, $todayCancelled],
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.8)',  // blue
                        'rgba(34, 197, 94, 0.8)',   // green
                        'rgba(168, 85, 247, 0.8)',  // purple
                        'rgba(245, 158, 11, 0.8)',  // amber
                        'rgba(239, 68, 68, 0.8)',   // red
                    ],
                    'borderColor' => [
                        'rgb(59, 130, 246)',
                        'rgb(34, 197, 94)',
                        'rgb(168, 85, 247)',
                        'rgb(245, 158, 11)',
                        'rgb(239, 68, 68)',
                    ],
                    'borderWidth' => 1,
                ],
            ],
            'labels' => [
                "Comenzi ({$todayOrders})",
                "Plătite ({$todayPaid})",
                "Bilete ({$todayTickets})",
                "Pending ({$todayPending})",
                "Anulate ({$todayCancelled})",
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
