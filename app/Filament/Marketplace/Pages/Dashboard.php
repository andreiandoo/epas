<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\MarketplaceClient;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Models\Ticket;
use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;

class Dashboard extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.marketplace.pages.dashboard';

    public ?MarketplaceClient $marketplace = null;

    #[Url]
    public string $chartPeriod = '30';

    public function mount(): void
    {
        $admin = Auth::guard('marketplace_admin')->user();
        $this->marketplace = $admin?->marketplaceClient;
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string|null
    {
        return null;
    }

    public function updatedChartPeriod(): void
    {
        $this->dispatch('charts-updated');
    }

    public function getViewData(): array
    {
        $marketplace = $this->marketplace;

        if (!$marketplace) {
            return [
                'marketplace' => null,
                'stats' => [],
                'chartData' => [],
            ];
        }

        $marketplaceId = $marketplace->id;

        // Calculate date range for chart
        $days = (int) $this->chartPeriod;
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Active events (upcoming or ongoing) - using Event model with correct fields
        $today = Carbon::now()->startOfDay();
        $activeEvents = Event::where('marketplace_client_id', $marketplaceId)
            ->where('is_cancelled', false)
            ->where(function ($query) use ($today) {
                // Single day events
                $query->where(function ($q) use ($today) {
                    $q->where('duration_mode', 'single_day')
                      ->where('event_date', '>=', $today);
                })
                // Range events
                ->orWhere(function ($q) use ($today) {
                    $q->where('duration_mode', 'range')
                      ->where('range_end_date', '>=', $today);
                })
                // Multi-day events
                ->orWhere(function ($q) use ($today) {
                    $q->whereNotIn('duration_mode', ['single_day', 'range'])
                      ->whereNotNull('multi_slots');
                });
            })
            ->count();

        // Total events
        $totalEvents = Event::where('marketplace_client_id', $marketplaceId)->count();

        // Total organizers
        $totalOrganizers = MarketplaceOrganizer::where('marketplace_client_id', $marketplaceId)->count();

        // Pending organizers
        $pendingOrganizers = MarketplaceOrganizer::where('marketplace_client_id', $marketplaceId)
            ->where('status', 'pending')
            ->count();

        // Total customers
        $totalCustomers = MarketplaceCustomer::where('marketplace_client_id', $marketplaceId)->count();

        // Total revenue (from orders linked to marketplace events)
        // Use marketplace_event_id for marketplace orders, or check via tickets for tenant events
        $eventIds = Event::where('marketplace_client_id', $marketplaceId)
            ->pluck('id')
            ->toArray();

        $totalRevenue = Order::where(function ($query) use ($eventIds, $marketplaceId) {
                // Direct marketplace orders
                $query->where('marketplace_client_id', $marketplaceId)
                    ->whereIn('status', ['paid', 'confirmed']);
            })
            ->orWhere(function ($query) use ($eventIds) {
                // Or orders with marketplace_event_id
                $query->whereIn('marketplace_event_id', $eventIds)
                    ->whereIn('status', ['paid', 'confirmed']);
            })
            ->sum('total_cents') / 100;

        // Total tickets sold
        $totalTickets = Ticket::whereHas('order', function ($query) use ($eventIds, $marketplaceId) {
            $query->where('marketplace_client_id', $marketplaceId)
                ->whereIn('status', ['paid', 'confirmed']);
        })->orWhereHas('order', function ($query) use ($eventIds) {
            $query->whereIn('marketplace_event_id', $eventIds)
                ->whereIn('status', ['paid', 'confirmed']);
        })->count();

        // Pending payouts
        $pendingPayouts = MarketplacePayout::where('marketplace_client_id', $marketplaceId)
            ->where('status', 'pending')
            ->count();

        // Pending payouts value
        $pendingPayoutsValue = MarketplacePayout::where('marketplace_client_id', $marketplaceId)
            ->where('status', 'pending')
            ->sum('amount');

        // Chart data - daily sales for the selected period
        $chartData = $this->getChartData($eventIds, $startDate, $endDate, $days);

        // Ticket chart data
        $ticketChartData = $this->getTicketChartData($eventIds, $startDate, $endDate, $days);

        // Top organizers
        $topOrganizers = MarketplaceOrganizer::where('marketplace_client_id', $marketplaceId)
            ->where('status', 'active')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        return [
            'marketplace' => $marketplace,
            'stats' => [
                'active_events' => $activeEvents,
                'total_events' => $totalEvents,
                'total_organizers' => $totalOrganizers,
                'pending_organizers' => $pendingOrganizers,
                'total_customers' => $totalCustomers,
                'total_revenue' => $totalRevenue,
                'total_tickets' => $totalTickets,
                'pending_payouts' => $pendingPayouts,
                'pending_payouts_value' => $pendingPayoutsValue,
            ],
            'chartData' => $chartData,
            'ticketChartData' => $ticketChartData,
            'chartPeriod' => $this->chartPeriod,
            'topOrganizers' => $topOrganizers,
        ];
    }

    private function getChartData(array $eventIds, Carbon $startDate, Carbon $endDate, int $days): array
    {
        $labels = [];
        $data = [];
        $marketplaceId = $this->marketplace?->id;

        if (empty($eventIds) && !$marketplaceId) {
            $current = $startDate->copy();
            while ($current <= $endDate) {
                $labels[] = $current->format($days <= 7 ? 'D' : ($days <= 30 ? 'M d' : 'M d'));
                $data[] = 0;
                $current->addDay();
            }
            return ['labels' => $labels, 'data' => $data];
        }

        // Get daily totals - use marketplace_client_id or marketplace_event_id
        $dailySales = Order::where(function ($query) use ($marketplaceId, $eventIds) {
                $query->where('marketplace_client_id', $marketplaceId)
                    ->orWhereIn('marketplace_event_id', $eventIds);
            })
            ->whereIn('status', ['paid', 'confirmed'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(total_cents) / 100 as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        // Fill in all days
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $labels[] = $current->format($days <= 7 ? 'D' : ($days <= 30 ? 'M d' : 'M d'));
            $data[] = (float) ($dailySales[$dateKey] ?? 0);
            $current->addDay();
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    private function getTicketChartData(array $eventIds, Carbon $startDate, Carbon $endDate, int $days): array
    {
        $labels = [];
        $data = [];
        $marketplaceId = $this->marketplace?->id;

        if (empty($eventIds) && !$marketplaceId) {
            $current = $startDate->copy();
            while ($current <= $endDate) {
                $labels[] = $current->format($days <= 7 ? 'D' : ($days <= 30 ? 'M d' : 'M d'));
                $data[] = 0;
                $current->addDay();
            }
            return ['labels' => $labels, 'data' => $data, 'tooltipData' => []];
        }

        // Get tickets grouped by date - use marketplace_client_id or marketplace_event_id
        $tickets = Ticket::with(['ticketType.event', 'order'])
            ->whereHas('order', function ($query) use ($eventIds, $marketplaceId, $startDate, $endDate) {
                $query->where(function ($q) use ($marketplaceId, $eventIds) {
                        $q->where('marketplace_client_id', $marketplaceId)
                            ->orWhereIn('marketplace_event_id', $eventIds);
                    })
                    ->whereIn('status', ['paid', 'confirmed'])
                    ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->get();

        // Group by date
        $dailyTickets = [];
        foreach ($tickets as $ticket) {
            $dateKey = $ticket->order->created_at->format('Y-m-d');
            if (!isset($dailyTickets[$dateKey])) {
                $dailyTickets[$dateKey] = 0;
            }
            $dailyTickets[$dateKey]++;
        }

        // Fill in all days
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $labels[] = $current->format($days <= 7 ? 'D' : ($days <= 30 ? 'M d' : 'M d'));
            $data[] = $dailyTickets[$dateKey] ?? 0;
            $current->addDay();
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'tooltipData' => [],
        ];
    }
}
