<?php

namespace App\Filament\Marketplace\Pages;

use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplacePayout;
use App\Models\Order;
use App\Models\ServiceOrder;
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
        $paidStatuses = ['paid', 'confirmed', 'completed'];

        // Date range for chart
        $days = (int) $this->chartPeriod;
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        // Event IDs for this marketplace
        $eventIds = Event::where('marketplace_client_id', $marketplaceId)->pluck('id')->toArray();

        // 1. Evenimente
        $today = Carbon::now()->startOfDay();
        $totalEvents = Event::where('marketplace_client_id', $marketplaceId)->count();
        $activeEvents = Event::where('marketplace_client_id', $marketplaceId)
            ->where('is_cancelled', false)
            ->where(function ($query) use ($today) {
                $query->where(function ($q) use ($today) {
                    $q->where('duration_mode', 'single_day')->where('event_date', '>=', $today);
                })->orWhere(function ($q) use ($today) {
                    $q->where('duration_mode', 'range')->where('range_end_date', '>=', $today);
                })->orWhere(function ($q) use ($today) {
                    $q->whereNotIn('duration_mode', ['single_day', 'range'])->whereNotNull('multi_slots');
                });
            })
            ->count();

        // 2. Clienți
        $totalCustomers = MarketplaceCustomer::where('marketplace_client_id', $marketplaceId)->count();

        // 3. Comenzi
        $ordersQuery = fn ($statuses = null) => Order::where(function ($q) use ($marketplaceId, $eventIds) {
            $q->where('marketplace_client_id', $marketplaceId)
                ->orWhereIn('marketplace_event_id', $eventIds);
        })->where('source', '!=', 'test_order')
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses));

        $totalOrders = $ordersQuery()->count();
        $todayOrders = $ordersQuery()->whereDate('created_at', today())->count();
        $paidOrdersCount = $ordersQuery($paidStatuses)->count();
        $otherOrdersCount = $totalOrders - $paidOrdersCount;

        // 4. Încasări (orders paid + service orders paid)
        $orderRevenue = $ordersQuery($paidStatuses)->sum('total');
        if ($orderRevenue == 0) {
            $orderRevenue = $ordersQuery($paidStatuses)->sum('total_cents') / 100;
        }

        $serviceOrderRevenue = ServiceOrder::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', ['active', 'completed'])
            ->where('payment_status', 'paid')
            ->sum('total');

        $totalIncasari = $orderRevenue + $serviceOrderRevenue;

        // 5. Venituri (commissions from orders + service order values)
        $commissions = $ordersQuery($paidStatuses)->sum('commission_amount');

        $serviceOrdersTotal = ServiceOrder::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', ['active', 'completed'])
            ->where('payment_status', 'paid')
            ->sum('total');

        // 6. Bilete vândute
        $totalTickets = Ticket::whereHas('order', function ($query) use ($eventIds, $marketplaceId) {
            $query->where(function ($q) use ($marketplaceId, $eventIds) {
                $q->where('marketplace_client_id', $marketplaceId)
                    ->orWhereIn('marketplace_event_id', $eventIds);
            })->whereIn('status', ['paid', 'confirmed', 'completed'])
                ->where('source', '!=', 'test_order');
        })->where('status', 'valid')->count();

        // 7. Organizatori
        $totalOrganizers = MarketplaceOrganizer::where('marketplace_client_id', $marketplaceId)->count();
        $activeOrganizers = MarketplaceOrganizer::where('marketplace_client_id', $marketplaceId)
            ->where('status', 'active')->count();

        // 8. Payouts
        $pendingPayoutsValue = MarketplacePayout::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->sum('amount');
        $completedPayoutsValue = MarketplacePayout::where('marketplace_client_id', $marketplaceId)
            ->where('status', 'completed')
            ->sum('amount');

        // Top Organizers (by revenue + tickets)
        $topOrganizers = MarketplaceOrganizer::where('marketplace_client_id', $marketplaceId)
            ->where('status', 'active')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        // Top Live Events (active/upcoming, with revenue and ticket counts)
        $topLiveEvents = Event::where('marketplace_client_id', $marketplaceId)
            ->where('is_cancelled', false)
            ->where(function ($query) use ($today) {
                $query->where(function ($q) use ($today) {
                    $q->where('duration_mode', 'single_day')->where('event_date', '>=', $today);
                })->orWhere(function ($q) use ($today) {
                    $q->where('duration_mode', 'range')->where('range_end_date', '>=', $today);
                })->orWhere(function ($q) use ($today) {
                    $q->whereNotIn('duration_mode', ['single_day', 'range'])->whereNotNull('multi_slots');
                });
            })
            ->withCount(['tickets as sold_tickets_count' => function ($q) {
                $q->where('status', 'valid');
            }])
            ->get()
            ->map(function ($event) use ($marketplaceId) {
                $event->event_revenue = Order::where(function ($q) use ($event, $marketplaceId) {
                    $q->where('marketplace_event_id', $event->id)
                        ->orWhere(function ($q2) use ($event, $marketplaceId) {
                            $q2->where('marketplace_client_id', $marketplaceId)
                                ->where('event_id', $event->id);
                        });
                })->whereIn('status', ['paid', 'confirmed', 'completed'])
                    ->where('source', '!=', 'test_order')
                    ->sum('total');
                return $event;
            })
            ->sortByDesc('event_revenue')
            ->take(5);

        // Chart data
        $chartData = $this->getChartData($eventIds, $startDate, $endDate, $days);
        $ticketChartData = $this->getTicketChartData($eventIds, $startDate, $endDate, $days);

        return [
            'marketplace' => $marketplace,
            'stats' => [
                'total_events' => $totalEvents,
                'active_events' => $activeEvents,
                'total_customers' => $totalCustomers,
                'total_orders' => $totalOrders,
                'today_orders' => $todayOrders,
                'paid_orders' => $paidOrdersCount,
                'other_orders' => $otherOrdersCount,
                'total_incasari' => $totalIncasari,
                'order_revenue' => $orderRevenue,
                'service_revenue' => $serviceOrderRevenue,
                'commissions' => $commissions,
                'service_orders_total' => $serviceOrdersTotal,
                'total_tickets' => $totalTickets,
                'total_organizers' => $totalOrganizers,
                'active_organizers' => $activeOrganizers,
                'pending_payouts_value' => $pendingPayoutsValue,
                'completed_payouts_value' => $completedPayoutsValue,
            ],
            'chartData' => $chartData,
            'ticketChartData' => $ticketChartData,
            'chartPeriod' => $this->chartPeriod,
            'topOrganizers' => $topOrganizers,
            'topLiveEvents' => $topLiveEvents,
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
                $labels[] = $current->format($days <= 7 ? 'D' : 'M d');
                $data[] = 0;
                $current->addDay();
            }
            return ['labels' => $labels, 'data' => $data];
        }

        $dailySales = Order::where(function ($query) use ($marketplaceId, $eventIds) {
                $query->where('marketplace_client_id', $marketplaceId)
                    ->orWhereIn('marketplace_event_id', $eventIds);
            })
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('source', '!=', 'test_order')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(total) as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $labels[] = $current->format($days <= 7 ? 'D' : 'M d');
            $data[] = (float) ($dailySales[$dateKey] ?? 0);
            $current->addDay();
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function getTicketChartData(array $eventIds, Carbon $startDate, Carbon $endDate, int $days): array
    {
        $labels = [];
        $data = [];
        $marketplaceId = $this->marketplace?->id;

        if (empty($eventIds) && !$marketplaceId) {
            $current = $startDate->copy();
            while ($current <= $endDate) {
                $labels[] = $current->format($days <= 7 ? 'D' : 'M d');
                $data[] = 0;
                $current->addDay();
            }
            return ['labels' => $labels, 'data' => $data];
        }

        $dailyTickets = Ticket::whereHas('order', function ($query) use ($eventIds, $marketplaceId, $startDate, $endDate) {
                $query->where(function ($q) use ($marketplaceId, $eventIds) {
                    $q->where('marketplace_client_id', $marketplaceId)
                        ->orWhereIn('marketplace_event_id', $eventIds);
                })
                ->whereIn('status', ['paid', 'confirmed', 'completed'])
                ->where('source', '!=', 'test_order')
                ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->where('status', 'valid')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $labels[] = $current->format($days <= 7 ? 'D' : 'M d');
            $data[] = (int) ($dailyTickets[$dateKey] ?? 0);
            $current->addDay();
        }

        return ['labels' => $labels, 'data' => $data];
    }
}
