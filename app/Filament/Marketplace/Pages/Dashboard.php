<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Pages\BillingBreakdown;
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

        // Current month stats
        $monthStats = Cache::remember("mp_dash_month_{$marketplaceId}", 120, function () use ($marketplaceId) {
            return $this->computeCurrentMonthStats($marketplaceId);
        });

        return [
            'marketplace' => $marketplace,
            'isSuperAdmin' => Auth::guard('marketplace_admin')->user()?->isSuperAdmin() ?? false,
            'stats' => $stats['cards'],
            'monthStats' => $monthStats,
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
            ->selectRaw("SUM(CASE WHEN NOT is_cancelled AND (
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

        // 3. Orders - include orders linked to marketplace events (migrated may lack marketplace_client_id)
        $marketplaceEventIds = Event::where('marketplace_client_id', $marketplaceId)->pluck('id')->toArray();

        $orderStats = Order::where(function ($q) use ($marketplaceId, $marketplaceEventIds) {
                $q->where('orders.marketplace_client_id', $marketplaceId);
                if (!empty($marketplaceEventIds)) {
                    $q->orWhereIn('orders.marketplace_event_id', $marketplaceEventIds)
                      ->orWhereIn('orders.event_id', $marketplaceEventIds);
                }
            })
            ->where('source', '!=', 'test_order')->where('source', '!=', 'external_import')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as today", [today()->toDateString()])
            ->selectRaw("SUM(CASE WHEN status IN ('paid','confirmed','completed') THEN 1 ELSE 0 END) as paid")
            ->selectRaw("SUM(CASE WHEN status IN ('paid','confirmed','completed') THEN \"total\" ELSE 0 END) as revenue")
            ->selectRaw("SUM(CASE WHEN status IN ('paid','confirmed','completed') THEN COALESCE(commission_amount, 0) ELSE 0 END) as commissions")
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

        // 5. Tickets — use same logic as TicketResource: whereHas ticketType.event
        $ticketStats = Ticket::whereHas('ticketType.event', function ($q) use ($marketplaceId) {
                $q->where('marketplace_client_id', $marketplaceId);
            })
            ->selectRaw('COUNT(*) as total_db')
            ->selectRaw("SUM(CASE WHEN status IN ('valid', 'used') THEN 1 ELSE 0 END) as sold")
            ->selectRaw("SUM(CASE WHEN status IN ('valid', 'used') AND DATE(created_at) = ? THEN 1 ELSE 0 END) as sold_today", [today()->toDateString()])
            ->first();

        // External import counts (for "din care import" display)
        $externalTickets = Ticket::whereHas('ticketType.event', fn ($q) => $q->where('marketplace_client_id', $marketplaceId))
            ->whereHas('order', fn ($q) => $q->where('source', 'external_import'))
            ->whereIn('status', ['valid', 'used'])
            ->count();
        $externalOrders = Order::where('marketplace_client_id', $marketplaceId)
            ->where('source', 'external_import')
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->count();
        $externalCustomers = MarketplaceCustomer::where('marketplace_client_id', $marketplaceId)
            ->whereExists(function ($q) use ($marketplaceId) {
                $q->select(\DB::raw(1))->from('orders')
                    ->whereColumn('orders.marketplace_customer_id', 'marketplace_customers.id')
                    ->where('orders.source', 'external_import')
                    ->where('orders.marketplace_client_id', $marketplaceId);
            })
            ->whereNotExists(function ($q) use ($marketplaceId) {
                $q->select(\DB::raw(1))->from('orders')
                    ->whereColumn('orders.marketplace_customer_id', 'marketplace_customers.id')
                    ->where('orders.source', '!=', 'external_import')
                    ->where('orders.marketplace_client_id', $marketplaceId);
            })
            ->count();

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
                $q->whereIn('tickets.status', ['valid', 'used']);
            }])
            ->get();

        if ($topLiveEvents->isNotEmpty()) {
            $liveEventIds = $topLiveEvents->pluck('id')->toArray();
            // Include orders linked by marketplace_event_id OR event_id
            $revenueByEvent = Order::where(function ($q) use ($marketplaceId, $liveEventIds) {
                    $q->where('marketplace_client_id', $marketplaceId)
                      ->orWhereIn('marketplace_event_id', $liveEventIds)
                      ->orWhereIn('event_id', $liveEventIds);
                })
                ->where(function ($q) use ($liveEventIds) {
                    $q->whereIn('marketplace_event_id', $liveEventIds)
                      ->orWhereIn('event_id', $liveEventIds);
                })
                ->whereIn('status', $paidStatuses)
                ->where('source', '!=', 'test_order')->where('source', '!=', 'external_import')
                ->selectRaw('COALESCE(marketplace_event_id, event_id) as eid, SUM(total) as rev')
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
                // All-time marketplace commissions — single source of truth
                'all_time_commissions' => BillingBreakdown::calculateMarketplaceCommission(
                    $marketplaceId, null, null, (float) ($this->marketplace->commission_rate ?? 5)
                ),
                'service_orders_total' => $serviceOrdersTotal,
                'total_tickets' => (int) $ticketStats->sold,
                'today_tickets' => (int) $ticketStats->sold_today,
                'total_tickets_db' => (int) $ticketStats->total_db,
                'external_tickets' => $externalTickets,
                'external_orders' => $externalOrders,
                'external_customers' => $externalCustomers,
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
            ->where('source', '!=', 'test_order')->where('source', '!=', 'external_import')
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

    private function computeCurrentMonthStats(int $marketplaceId): array
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $now = Carbon::now();
        $validStatuses = ['paid', 'confirmed', 'completed'];

        // New organizers this month
        $newOrganizers = DB::table('marketplace_organizers')
            ->where('marketplace_client_id', $marketplaceId)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        // Live events (happening this month, published, not cancelled)
        $liveEvents = Event::where('marketplace_client_id', $marketplaceId)
            ->where('is_published', true)
            ->where('is_cancelled', false)
            ->where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('event_date', [$monthStart, $monthEnd])
                    ->orWhereBetween('range_start_date', [$monthStart, $monthEnd])
                    ->orWhere(function ($qq) use ($monthStart, $monthEnd) {
                        $qq->where('range_start_date', '<=', $monthEnd)
                            ->where('range_end_date', '>=', $monthStart);
                    });
            })
            ->where(function ($q) use ($now) {
                $q->where('event_date', '>=', $now->toDateString())
                    ->orWhere('range_end_date', '>=', $now->toDateString());
            })
            ->count();

        // Ended events this month
        $endedEvents = Event::where('marketplace_client_id', $marketplaceId)
            ->where('is_published', true)
            ->where(function ($q) use ($monthStart, $now) {
                $q->whereBetween('event_date', [$monthStart, $now])
                    ->orWhere(function ($qq) use ($monthStart, $now) {
                        $qq->where('range_end_date', '>=', $monthStart)
                            ->where('range_end_date', '<', $now);
                    });
            })
            ->count();

        // Sales this month — include orders for marketplace events (migrated may lack marketplace_client_id)
        $mpEventIds = Event::where('marketplace_client_id', $marketplaceId)->pluck('id')->toArray();
        $allStatuses = ['paid', 'confirmed', 'completed', 'refunded'];

        $orderScope = function ($q) use ($marketplaceId, $mpEventIds) {
            $q->where('orders.marketplace_client_id', $marketplaceId);
            if (!empty($mpEventIds)) {
                $q->orWhereIn('orders.marketplace_event_id', $mpEventIds)
                  ->orWhereIn('orders.event_id', $mpEventIds);
            }
        };

        // Revenue (excl refunded)
        $totalSales = (float) Order::where($orderScope)
            ->whereIn('orders.status', $allStatuses)
            ->where('orders.source', '!=', 'test_order')->where('orders.source', '!=', 'external_import')
            ->whereBetween('orders.created_at', [$monthStart, $monthEnd])
            ->selectRaw("SUM(CASE WHEN orders.status = 'refunded' THEN 0 ELSE orders.total END) as revenue")
            ->value('revenue') ?? 0;

        // Marketplace commission — reuse BillingBreakdown's per-event logic (single source of truth)
        $totalCommission = BillingBreakdown::calculateMarketplaceCommission(
            $marketplaceId, $monthStart, $monthEnd, (float) ($this->marketplace->commission_rate ?? 5)
        );

        // Tickets sold this month — exclude external imports
        $ticketsSold = Ticket::whereHas('ticketType.event', function ($q) use ($marketplaceId) {
                $q->where('marketplace_client_id', $marketplaceId);
            })
            ->whereHas('order', fn ($q) => $q->where('source', '!=', 'external_import'))
            ->whereIn('status', ['valid', 'used'])
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        // New customers this month — exclude those created only via external import
        $newCustomers = MarketplaceCustomer::where('marketplace_client_id', $marketplaceId)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->whereExists(function ($q) use ($marketplaceId) {
                $q->select(\DB::raw(1))->from('orders')
                    ->whereColumn('orders.marketplace_customer_id', 'marketplace_customers.id')
                    ->where('orders.source', '!=', 'external_import')
                    ->where('orders.marketplace_client_id', $marketplaceId);
            })
            ->count();

        // Orders this month (paid + confirmed + completed + refunded, excl cancelled)
        $monthOrders = Order::where($orderScope)
            ->whereIn('orders.status', ['paid', 'confirmed', 'completed', 'refunded'])
            ->where('orders.source', '!=', 'test_order')->where('orders.source', '!=', 'external_import')
            ->whereBetween('orders.created_at', [$monthStart, $monthEnd])
            ->count();

        // Payouts this month
        $monthPayouts = MarketplacePayout::where('marketplace_client_id', $marketplaceId)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('pending','approved','processing') THEN amount ELSE 0 END), 0) as pending")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as paid")
            ->first();

        return [
            'month_label' => Carbon::now()->translatedFormat('F Y'),
            'new_organizers' => $newOrganizers,
            'live_events' => $liveEvents,
            'ended_events' => $endedEvents,
            'total_sales' => $totalSales,
            'total_commission' => $totalCommission,
            'tickets_sold' => $ticketsSold,
            'new_customers' => $newCustomers,
            'payouts_pending' => (float) ($monthPayouts->pending ?? 0),
            'payouts_paid' => (float) ($monthPayouts->paid ?? 0),
            'month_orders' => $monthOrders,
            'currency' => $this->marketplace->currency ?? 'RON',
        ];
    }

    private function computeMonthlyBilling(int $marketplaceId): array
    {
        $tz = 'Europe/Bucharest';
        $monthStart = Carbon::now($tz)->startOfMonth()->utc();
        $monthEnd = Carbon::now($tz)->endOfMonth()->endOfDay()->utc();

        // If billing_starts_at is set and falls in the current month, use it as period start
        $billingStartsAt = $this->marketplace->billing_starts_at ?? null;
        if ($billingStartsAt) {
            $billingStart = Carbon::parse($billingStartsAt, $tz)->startOfDay()->utc();
            if ($billingStart->between($monthStart, $monthEnd)) {
                $monthStart = $billingStart;
            }
        }

        $validStatuses = ['paid', 'confirmed', 'completed', 'refunded'];

        // Commission rate from marketplace client settings (Tixello rate)
        $commissionRate = (float) ($this->marketplace->commission_rate ?? 0);

        // Include orders for marketplace events (migrated may lack marketplace_client_id)
        $mpEventIds = Event::where('marketplace_client_id', $marketplaceId)->pluck('id')->toArray();
        $billingScope = function ($q) use ($marketplaceId, $mpEventIds) {
            $q->where('orders.marketplace_client_id', $marketplaceId);
            if (!empty($mpEventIds)) {
                $q->orWhereIn('orders.marketplace_event_id', $mpEventIds)
                  ->orWhereIn('orders.event_id', $mpEventIds);
            }
        };

        // 1. Ticketing: per-event commission (matches BillingBreakdown logic exactly)
        $orderRevenue = (float) Order::where($billingScope)
            ->whereIn('status', $validStatuses)
            ->where('source', '!=', 'test_order')->where('source', '!=', 'external_import')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('total');

        // Use DB-level SUM with ROUND to match BillingBreakdown per-event rounding
        $ticketingCommission = (float) Order::where($billingScope)
            ->whereIn('status', $validStatuses)
            ->where('source', '!=', 'test_order')->where('source', '!=', 'external_import')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw("SUM(ROUND(total * {$commissionRate} / 100, 2)) as total_commission")
            ->value('total_commission') ?? 0;

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
            'commission_rate' => $commissionRate,
            'order_revenue' => $orderRevenue,
            'ticketing_commission' => $ticketingCommission,
            'services' => $services,
            'services_total' => $servicesTotal,
            'grand_total' => $ticketingCommission + $servicesTotal,
            'currency' => $this->marketplace->currency ?? 'RON',
        ];
    }
}
