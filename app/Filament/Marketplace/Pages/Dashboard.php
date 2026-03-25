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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        $days = (int) $this->chartPeriod;

        // Cache stats for 2 minutes
        $stats = Cache::remember("mp_dash_stats_{$marketplaceId}", 120, function () use ($marketplaceId) {
            return $this->computeStats($marketplaceId);
        });

        // Cache chart data for 5 minutes (keyed by period)
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $chartData = Cache::remember("mp_dash_chart_{$marketplaceId}_{$days}", 300, function () use ($marketplaceId, $startDate, $endDate, $days) {
            return $this->getChartData($marketplaceId, $startDate, $endDate, $days);
        });

        $ticketChartData = Cache::remember("mp_dash_tchart_{$marketplaceId}_{$days}", 300, function () use ($marketplaceId, $startDate, $endDate, $days) {
            return $this->getTicketChartData($marketplaceId, $startDate, $endDate, $days);
        });

        // Pending review events (not cached — always fresh)
        $pendingReviewEvents = Event::where('marketplace_client_id', $marketplaceId)
            ->where('is_published', false)
            ->whereNotNull('submitted_at')
            ->where('is_cancelled', false)
            ->with(['marketplaceOrganizer', 'venue'])
            ->orderBy('submitted_at', 'desc')
            ->get();

        // Tixello billing for current month
        $billingData = Cache::remember("mp_dash_billing_{$marketplaceId}", 300, function () use ($marketplaceId) {
            return $this->computeMonthlyBilling($marketplaceId);
        });

        return [
            'marketplace' => $marketplace,
            'stats' => $stats['cards'],
            'chartData' => $chartData,
            'ticketChartData' => $ticketChartData,
            'chartPeriod' => $this->chartPeriod,
            'topOrganizers' => $stats['topOrganizers'],
            'topLiveEvents' => $stats['topLiveEvents'],
            'pendingReviewEvents' => $pendingReviewEvents,
            'billing' => $billingData,
        ];
    }

    private function computeStats(int $marketplaceId): array
    {
        $paidStatuses = ['paid', 'confirmed', 'completed'];
        $today = Carbon::now()->startOfDay();

        // 1. Events - single query with conditional counts
        $eventStats = Event::where('marketplace_client_id', $marketplaceId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN is_cancelled = 0 AND (
                (duration_mode = 'single_day' AND event_date >= ?) OR
                (duration_mode = 'range' AND range_end_date >= ?) OR
                (duration_mode NOT IN ('single_day','range') AND multi_slots IS NOT NULL)
            ) THEN 1 ELSE 0 END) as active", [$today, $today])
            ->first();

        // 2. Customers - single query with guest/registered split
        $customerStats = MarketplaceCustomer::where('marketplace_client_id', $marketplaceId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN password IS NULL THEN 1 ELSE 0 END) as guests')
            ->selectRaw('SUM(CASE WHEN password IS NOT NULL THEN 1 ELSE 0 END) as registered')
            ->first();

        // 3. Orders - single aggregation query
        $orderStats = Order::where('marketplace_client_id', $marketplaceId)
            ->where('source', '!=', 'test_order')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as today", [today()->toDateString()])
            ->selectRaw("SUM(CASE WHEN status IN ('paid','confirmed','completed') THEN 1 ELSE 0 END) as paid")
            ->selectRaw("SUM(CASE WHEN status IN ('paid','confirmed','completed') THEN `total` ELSE 0 END) as revenue")
            ->selectRaw("SUM(CASE WHEN status IN ('paid','confirmed','completed') THEN commission_amount ELSE 0 END) as commissions")
            ->first();

        $totalOrders = (int) $orderStats->total;
        $paidOrdersCount = (int) $orderStats->paid;
        $orderRevenue = (float) $orderStats->revenue;
        $commissions = (float) $orderStats->commissions;

        // 4. Service orders - single query
        $serviceStats = ServiceOrder::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', ['active', 'completed'])
            ->where('payment_status', 'paid')
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->first();
        $serviceOrdersTotal = (float) $serviceStats->total;

        // 5. Tickets - use direct marketplace_client_id (indexed, no joins)
        $ticketStats = Ticket::where('marketplace_client_id', $marketplaceId)
            ->selectRaw('COUNT(*) as total_db')
            ->selectRaw("SUM(CASE WHEN status = 'valid' THEN 1 ELSE 0 END) as sold")
            ->selectRaw("SUM(CASE WHEN status = 'valid' AND DATE(created_at) = ? THEN 1 ELSE 0 END) as sold_today", [today()->toDateString()])
            ->first();

        // 6. Organizers - single query
        $orgStats = MarketplaceOrganizer::where('marketplace_client_id', $marketplaceId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active")
            ->first();

        // 7. Payouts - single query
        $payoutStats = MarketplacePayout::where('marketplace_client_id', $marketplaceId)
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('pending','approved','processing') THEN amount ELSE 0 END), 0) as pending")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as completed")
            ->first();

        // Top Organizers - reads from denormalized columns (fast)
        $topOrganizers = MarketplaceOrganizer::where('marketplace_client_id', $marketplaceId)
            ->where('status', 'active')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        // Top Live Events
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
                $q->where('tickets.status', 'valid');
            }])
            ->get();

        if ($topLiveEvents->isNotEmpty()) {
            $liveEventIds = $topLiveEvents->pluck('id')->toArray();
            $revenueByEvent = Order::where('marketplace_client_id', $marketplaceId)
                ->whereIn('marketplace_event_id', $liveEventIds)
                ->whereIn('status', $paidStatuses)
                ->where('source', '!=', 'test_order')
                ->selectRaw('marketplace_event_id as eid, SUM(total) as rev')
                ->groupBy('eid')
                ->pluck('rev', 'eid');

            $topLiveEvents->each(function ($event) use ($revenueByEvent) {
                $event->event_revenue = (float) ($revenueByEvent[$event->id] ?? 0);
            });
        }

        $topLiveEvents = $topLiveEvents->sortByDesc('event_revenue')->take(5)->values();

        return [
            'cards' => [
                'total_events' => (int) $eventStats->total,
                'active_events' => (int) $eventStats->active,
                'total_customers' => (int) $customerStats->total,
                'guest_customers' => (int) $customerStats->guests,
                'registered_customers' => (int) $customerStats->registered,
                'total_orders' => $totalOrders,
                'today_orders' => (int) $orderStats->today,
                'paid_orders' => $paidOrdersCount,
                'other_orders' => $totalOrders - $paidOrdersCount,
                'total_incasari' => $orderRevenue + $serviceOrdersTotal,
                'order_revenue' => $orderRevenue,
                'service_revenue' => $serviceOrdersTotal,
                'commissions' => $commissions,
                'service_orders_total' => $serviceOrdersTotal,
                'total_tickets' => (int) $ticketStats->sold,
                'today_tickets' => (int) $ticketStats->sold_today,
                'total_tickets_db' => (int) $ticketStats->total_db,
                'total_organizers' => (int) $orgStats->total,
                'active_organizers' => (int) $orgStats->active,
                'pending_payouts_value' => (float) $payoutStats->pending,
                'completed_payouts_value' => (float) $payoutStats->completed,
            ],
            'topOrganizers' => $topOrganizers,
            'topLiveEvents' => $topLiveEvents,
        ];
    }

    private function getChartData(int $marketplaceId, Carbon $startDate, Carbon $endDate, int $days): array
    {
        $dailySales = Order::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('source', '!=', 'test_order')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, SUM(total) as total')
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $labels = [];
        $data = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $labels[] = $current->format($days <= 7 ? 'D' : 'M d');
            $data[] = (float) ($dailySales[$dateKey] ?? 0);
            $current->addDay();
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function getTicketChartData(int $marketplaceId, Carbon $startDate, Carbon $endDate, int $days): array
    {
        $dailyTickets = Ticket::where('marketplace_client_id', $marketplaceId)
            ->where('tickets.status', 'valid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $labels = [];
        $data = [];
        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dateKey = $current->format('Y-m-d');
            $labels[] = $current->format($days <= 7 ? 'D' : 'M d');
            $data[] = (int) ($dailyTickets[$dateKey] ?? 0);
            $current->addDay();
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function computeMonthlyBilling(int $marketplaceId): array
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $paidStatuses = ['paid', 'confirmed', 'completed'];

        // 1. Ticketing commissions for current month
        $ticketingCommission = (float) Order::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', $paidStatuses)
            ->where('source', '!=', 'test_order')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('commission_amount');

        // 2. Service orders for current month, grouped by service_type
        $serviceBreakdown = ServiceOrder::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', ['active', 'completed'])
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw('service_type, COALESCE(SUM(total), 0) as total')
            ->groupBy('service_type')
            ->pluck('total', 'service_type')
            ->toArray();

        $serviceLabels = [
            'featuring' => 'Promovare Eveniment',
            'email' => 'Email Marketing',
            'tracking' => 'Ad Tracking',
            'campaign' => 'Creare Campanie',
        ];

        // Always show all service types, even if 0
        $services = [];
        $servicesTotal = 0;
        foreach ($serviceLabels as $type => $label) {
            $amount = (float) ($serviceBreakdown[$type] ?? 0);
            $services[] = [
                'type' => $type,
                'label' => $label,
                'amount' => $amount,
            ];
            $servicesTotal += $amount;
        }

        return [
            'month_label' => Carbon::now()->translatedFormat('F Y'),
            'ticketing_commission' => $ticketingCommission,
            'services' => $services,
            'services_total' => $servicesTotal,
            'grand_total' => $ticketingCommission + $servicesTotal,
            'currency' => $this->marketplace->currency ?? 'RON',
        ];
    }
}
