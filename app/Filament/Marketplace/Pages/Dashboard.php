<?php

namespace App\Filament\Marketplace\Pages;

use App\Filament\Marketplace\Pages\BillingBreakdown;
use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceTodo;
use App\Models\Order;
use App\Models\ServiceOrder;
use App\Models\SupportTicket;
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

    #[Url]
    public string $selectedMonth = '';

    #[Url]
    public string $selectedDailyReportDate = '';

    public function mount(): void
    {
        $admin = Auth::guard('marketplace_admin')->user();
        $this->marketplace = $admin?->marketplaceClient;
        if (!$this->selectedMonth) {
            $this->selectedMonth = Carbon::now('Europe/Bucharest')->format('Y-m');
        }
        if (!$this->selectedDailyReportDate) {
            $this->selectedDailyReportDate = Carbon::now('Europe/Bucharest')->format('Y-m-d');
        }
    }

    public function updatedSelectedMonth(): void
    {
        // Clear month-related caches when month changes
        if ($this->marketplace) {
            $id = $this->marketplace->id;
            Cache::forget("mp_dash_month_{$id}_{$this->selectedMonth}");
            Cache::forget("mp_dash_billing_{$id}_{$this->selectedMonth}");
            Cache::forget("mp_month_comm_{$id}_{$this->selectedMonth}");
        }
    }

    public function updatedSelectedDailyReportDate(): void
    {
        // No cache invalidation needed — the daily report cache key already
        // includes the date, so changing the date just hits a different
        // (possibly empty) cache slot. Method exists so Livewire registers
        // the property as live-bindable.
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
        $days = $this->chartPeriod === 'month' ? 30 : (int) $this->chartPeriod;

        // Cache stats for 30 minutes (heavy queries on large tables)
        $stats = Cache::remember("mp_dash_stats_{$marketplaceId}", 1800, function () use ($marketplaceId) {
            return $this->computeStats($marketplaceId);
        });

        // Cache chart data for 10 minutes (keyed by period) — use Romania timezone
        $tz = 'Europe/Bucharest';
        $isMonthView = $this->chartPeriod === 'month';
        $prevYearChartData = null;
        $prevYearTicketChartData = null;

        if ($isMonthView) {
            $nowRo = Carbon::now($tz);
            $startDate = $nowRo->copy()->startOfMonth()->utc();
            $endDate = $nowRo->copy()->endOfMonth()->endOfDay()->utc();
            $chartDays = $nowRo->daysInMonth;

            $chartData = Cache::remember("mp_dash_chart_{$marketplaceId}_month_{$nowRo->format('Y-m')}", 600, function () use ($marketplaceId, $startDate, $endDate, $chartDays) {
                return $this->getChartData($marketplaceId, $startDate, $endDate, $chartDays, true);
            });
            $ticketChartData = Cache::remember("mp_dash_tchart_{$marketplaceId}_month_{$nowRo->format('Y-m')}", 600, function () use ($marketplaceId, $startDate, $endDate, $chartDays) {
                return $this->getTicketChartData($marketplaceId, $startDate, $endDate, $chartDays, true);
            });

            // Previous year same month
            $prevStart = $nowRo->copy()->subYear()->startOfMonth()->utc();
            $prevEnd = $nowRo->copy()->subYear()->endOfMonth()->endOfDay()->utc();
            $prevDays = $nowRo->copy()->subYear()->daysInMonth;
            $prevYearChartData = Cache::remember("mp_dash_chart_{$marketplaceId}_prevyear_{$nowRo->copy()->subYear()->format('Y-m')}", 3600, function () use ($marketplaceId, $prevStart, $prevEnd, $prevDays) {
                // Include legacy_import for the comparison line — last year's
                // sales are mostly migrated orders; excluding them would leave
                // the prev-year series empty and nothing to compare against.
                return $this->getChartData($marketplaceId, $prevStart, $prevEnd, $prevDays, true, excludeLegacy: false);
            });
            $prevYearTicketChartData = Cache::remember("mp_dash_tchart_{$marketplaceId}_prevyear_{$nowRo->copy()->subYear()->format('Y-m')}", 3600, function () use ($marketplaceId, $prevStart, $prevEnd, $prevDays) {
                return $this->getTicketChartData($marketplaceId, $prevStart, $prevEnd, $prevDays, true);
            });

            // Cards under chart — sales/tickets so far + last year + prediction.
            // 5-min cache: today's value drifts as orders come in, but the
            // expensive parts (last year aggregates) are stable.
            $chartSummary = Cache::remember(
                "mp_dash_chart_summary_{$marketplaceId}_{$nowRo->format('Y-m-d-H')}",
                300,
                fn () => $this->computeMonthChartSummary(
                    $chartData,
                    $ticketChartData,
                    $prevYearChartData,
                    $prevYearTicketChartData,
                )
            );
        } else {
            $startDate = Carbon::now($tz)->subDays($days)->startOfDay()->utc();
            $endDate = Carbon::now($tz)->endOfDay()->utc();

            $chartData = Cache::remember("mp_dash_chart_{$marketplaceId}_{$days}", 600, function () use ($marketplaceId, $startDate, $endDate, $days) {
                return $this->getChartData($marketplaceId, $startDate, $endDate, $days);
            });
            $ticketChartData = Cache::remember("mp_dash_tchart_{$marketplaceId}_{$days}", 600, function () use ($marketplaceId, $startDate, $endDate, $days) {
                return $this->getTicketChartData($marketplaceId, $startDate, $endDate, $days);
            });
            $chartSummary = null;
        }

        // Pending review events (cached 2 min — lightweight query)
        $pendingReviewEvents = Cache::remember("mp_dash_pending_{$marketplaceId}", 120, function () use ($marketplaceId) {
            return Event::where('marketplace_client_id', $marketplaceId)
                ->where('is_published', false)
                ->whereNotNull('submitted_at')
                ->where('is_cancelled', false)
                ->with(['marketplaceOrganizer:id,name', 'venue:id,name,city'])
                ->orderBy('submitted_at', 'desc')
                ->get();
        });

        // Selected month stats
        $month = $this->selectedMonth ?: Carbon::now('Europe/Bucharest')->format('Y-m');
        $billingData = Cache::remember("mp_dash_billing_{$marketplaceId}_{$month}", 600, function () use ($marketplaceId, $month) {
            return $this->computeMonthlyBilling($marketplaceId, $month);
        });

        $monthStats = Cache::remember("mp_dash_month_{$marketplaceId}_{$month}", 300, function () use ($marketplaceId, $month) {
            return $this->computeCurrentMonthStats($marketplaceId, $month);
        });

        // Today stats (Romania timezone)
        $todayStats = Cache::remember("mp_dash_today_{$marketplaceId}", 30, function () use ($marketplaceId) {
            return $this->computeTodayStats($marketplaceId);
        });

        // Pending support tickets — visible to ALL marketplace admins, not
        // just super-admins. Cache for 60s to keep the dashboard snappy.
        $pendingSupportTickets = Cache::remember(
            "mp_dash_support_pending_{$marketplaceId}",
            60,
            fn () => SupportTicket::query()
                ->where('marketplace_client_id', $marketplaceId)
                ->whereNotIn('status', [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED])
                ->with(['department', 'opener', 'assignee'])
                ->orderByDesc('last_activity_at')
                ->limit(10)
                ->get()
        );
        $pendingSupportTicketsCount = Cache::remember(
            "mp_dash_support_pending_count_{$marketplaceId}",
            60,
            fn () => SupportTicket::query()
                ->where('marketplace_client_id', $marketplaceId)
                ->whereNotIn('status', [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED])
                ->count()
        );

        // Pending artist accounts — status='pending' shows as "În review"
        // in the ArtistAccountResource UI. Same visibility rules / cache
        // window as support tickets above.
        $pendingArtistAccounts = Cache::remember(
            "mp_dash_artists_pending_{$marketplaceId}",
            60,
            fn () => \App\Models\MarketplaceArtistAccount::query()
                ->where('marketplace_client_id', $marketplaceId)
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
        );
        $pendingArtistAccountsCount = Cache::remember(
            "mp_dash_artists_pending_count_{$marketplaceId}",
            60,
            fn () => \App\Models\MarketplaceArtistAccount::query()
                ->where('marketplace_client_id', $marketplaceId)
                ->where('status', 'pending')
                ->count()
        );

        // Active TODOs — visible to ALL marketplace admins. Cache for 60s
        // so opening the dashboard stays snappy. Mirrors the support
        // tickets section's behaviour, scoped to this marketplace.
        $pendingTodos = Cache::remember(
            "mp_dash_todos_pending_{$marketplaceId}",
            60,
            fn () => MarketplaceTodo::query()
                ->where('marketplace_client_id', $marketplaceId)
                ->whereNotIn('status', [MarketplaceTodo::STATUS_RESOLVED, MarketplaceTodo::STATUS_CLOSED])
                ->with(['creator', 'assignee', 'category'])
                ->orderByDesc('last_activity_at')
                ->limit(10)
                ->get()
        );
        $pendingTodosCount = Cache::remember(
            "mp_dash_todos_pending_count_{$marketplaceId}",
            60,
            fn () => MarketplaceTodo::query()
                ->where('marketplace_client_id', $marketplaceId)
                ->whereNotIn('status', [MarketplaceTodo::STATUS_RESOLVED, MarketplaceTodo::STATUS_CLOSED])
                ->count()
        );

        $isSuperAdmin = Auth::guard('marketplace_admin')->user()?->isSuperAdmin() ?? false;

        // Daily event sales report (super-admin only) — events that had
        // sales on the selected day, with same-day + all-time aggregates.
        // Date is clamped to [today-30d, today] in Romania time. Today
        // gets a short TTL (data is still moving); past days cache longer.
        $dailyEventReport = null;
        $dailyReportDate = null;
        $dailyReportMinDate = null;
        $dailyReportMaxDate = null;
        if ($isSuperAdmin) {
            $maxDate = Carbon::now($tz)->format('Y-m-d');
            $minDate = Carbon::now($tz)->subDays(30)->format('Y-m-d');
            $reqDate = $this->selectedDailyReportDate ?: $maxDate;
            if ($reqDate < $minDate) { $reqDate = $minDate; }
            if ($reqDate > $maxDate) { $reqDate = $maxDate; }
            $dailyReportDate = $reqDate;
            $dailyReportMinDate = $minDate;
            $dailyReportMaxDate = $maxDate;
            $isToday = $reqDate === $maxDate;
            $ttl = $isToday ? 60 : 900;
            $dailyEventReport = Cache::remember(
                "mp_dash_daily_evt_v3_{$marketplaceId}_{$reqDate}",
                $ttl,
                fn () => $this->computeDailyEventReport($marketplaceId, $reqDate)
            );
        }

        return [
            'marketplace' => $marketplace,
            'isSuperAdmin' => $isSuperAdmin,
            'stats' => $stats['cards'],
            'monthStats' => $monthStats,
            'chartData' => $chartData,
            'ticketChartData' => $ticketChartData,
            'chartPeriod' => $this->chartPeriod,
            'topOrganizers' => $stats['topOrganizers'],
            'topLiveEvents' => $stats['topLiveEvents'],
            'pendingReviewEvents' => $pendingReviewEvents,
            'pendingSupportTickets' => $pendingSupportTickets,
            'pendingSupportTicketsCount' => $pendingSupportTicketsCount,
            'pendingArtistAccounts' => $pendingArtistAccounts,
            'pendingArtistAccountsCount' => $pendingArtistAccountsCount,
            'pendingTodos' => $pendingTodos,
            'pendingTodosCount' => $pendingTodosCount,
            'billing' => $billingData,
            'todayStats' => $todayStats,
            'prevYearChartData' => $prevYearChartData,
            'prevYearTicketChartData' => $prevYearTicketChartData,
            'chartSummary' => $chartSummary,
            'selectedMonth' => $month,
            'dailyEventReport' => $dailyEventReport,
            'dailyReportDate' => $dailyReportDate,
            'dailyReportMinDate' => $dailyReportMinDate,
            'dailyReportMaxDate' => $dailyReportMaxDate,
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

        // 3. Orders - use subquery instead of loading all event IDs into PHP array
        $orderStats = Order::where(function ($q) use ($marketplaceId) {
                $q->where('orders.marketplace_client_id', $marketplaceId)
                    ->orWhereIn('orders.marketplace_event_id', function ($sub) use ($marketplaceId) {
                        $sub->select('id')->from('events')->where('marketplace_client_id', $marketplaceId);
                    })
                    ->orWhereIn('orders.event_id', function ($sub) use ($marketplaceId) {
                        $sub->select('id')->from('events')->where('marketplace_client_id', $marketplaceId);
                    });
            })
            // All Time cards are lifetime cumulative totals — they DO count
            // legacy_import (orders migrated from the previous system) so the
            // figures reflect the full history. Only the period/sales views
            // (daily report + Vânzări chart) exclude legacy_import.
            ->whereNotIn('source', ['test_order', 'external_import'])
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

        // 5. Tickets — use efficient join instead of nested whereHas
        $ticketStats = Ticket::join('ticket_types', 'tickets.ticket_type_id', '=', 'ticket_types.id')
            ->join('events', 'ticket_types.event_id', '=', 'events.id')
            ->where('events.marketplace_client_id', $marketplaceId)
            ->selectRaw('COUNT(*) as total_db')
            ->selectRaw("SUM(CASE WHEN tickets.status IN ('valid', 'used') THEN 1 ELSE 0 END) as sold")
            ->selectRaw("SUM(CASE WHEN tickets.status IN ('valid', 'used') AND DATE(tickets.created_at) = ? THEN 1 ELSE 0 END) as sold_today", [today()->toDateString()])
            ->first();

        // External import counts — single combined query
        $externalOrders = Order::where('marketplace_client_id', $marketplaceId)
            ->where('source', 'external_import')
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->count();
        $externalTickets = Ticket::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', ['valid', 'used'])
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('orders')
                    ->whereColumn('orders.id', 'tickets.order_id')
                    ->where('orders.source', 'external_import');
            })
            ->count();
        $externalCustomers = DB::table('marketplace_customers as mc')
            ->where('mc.marketplace_client_id', $marketplaceId)
            ->whereExists(function ($q) use ($marketplaceId) {
                $q->select(DB::raw(1))->from('orders')
                    ->whereColumn('orders.marketplace_customer_id', 'mc.id')
                    ->where('orders.source', 'external_import')
                    ->where('orders.marketplace_client_id', $marketplaceId);
            })
            ->whereNotExists(function ($q) use ($marketplaceId) {
                $q->select(DB::raw(1))->from('orders')
                    ->whereColumn('orders.marketplace_customer_id', 'mc.id')
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
                // Top-live-events revenue: live (current/upcoming) events have
                // no legacy_import orders attached, so this matches the All
                // Time treatment — leave legacy in for consistency.
                ->whereNotIn('source', ['test_order', 'external_import'])
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
                // All-time marketplace commissions — cached separately (very expensive query)
                'all_time_commissions' => Cache::remember("mp_alltime_comm_{$marketplaceId}", 3600, fn () =>
                    BillingBreakdown::calculateMarketplaceCommission(
                        $marketplaceId, null, null, (float) ($this->marketplace->commission_rate ?? 5)
                    )
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

    private function getChartData(int $marketplaceId, Carbon $startDate, Carbon $endDate, int $days, bool $fullMonth = false, bool $excludeLegacy = true): array
    {
        $tz = 'Europe/Bucharest';
        // Current-period bars present real marketplace sales → exclude
        // legacy_import. The prev-year comparison passes excludeLegacy=false
        // because last year's data is mostly migrated (legacy_import); drop
        // it and there's nothing left to compare against.
        $excludedSources = $excludeLegacy
            ? ['test_order', 'external_import', 'legacy_import']
            : ['test_order', 'external_import'];
        $dailySales = Order::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereNotIn('source', $excludedSources)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("DATE(created_at AT TIME ZONE 'UTC' AT TIME ZONE '{$tz}') as date, SUM(total) as total")
            ->groupBy('date')
            ->pluck('total', 'date')
            ->toArray();

        $labels = [];
        $data = [];
        if ($fullMonth) {
            $monthStart = Carbon::parse($startDate)->timezone($tz)->startOfMonth();
            $monthEnd = Carbon::parse($endDate)->timezone($tz)->endOfMonth();
            $current = $monthStart->copy();
            while ($current <= $monthEnd) {
                $dateKey = $current->format('Y-m-d');
                $labels[] = $current->format('d');
                $data[] = (float) ($dailySales[$dateKey] ?? 0);
                $current->addDay();
            }
        } else {
            $current = Carbon::now($tz)->subDays($days)->startOfDay();
            $end = Carbon::now($tz)->endOfDay();
            while ($current <= $end) {
                $dateKey = $current->format('Y-m-d');
                $labels[] = $current->format($days <= 7 ? 'D' : 'M d');
                $data[] = (float) ($dailySales[$dateKey] ?? 0);
                $current->addDay();
            }
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Aggregate "current month chart" series + last year + prediction into a
     * single summary used by the stats grid below the chart. Prediction logic
     * mirrors the JS in dashboard.blade.php so the rendered total matches the
     * dashed prediction line: past days use actuals, today is extrapolated by
     * hours-elapsed, future days use a day-of-week growth ratio against last
     * year (falling back to overall ratio, then to overall daily average).
     *
     * @return array{
     *   sales_so_far: float,
     *   tickets_so_far: int,
     *   prev_year_sales: float,
     *   prev_year_tickets: int,
     *   predicted_sales: float,
     *   predicted_tickets: int,
     * }
     */
    private function computeMonthChartSummary(array $chartData, array $ticketChartData, ?array $prevSales, ?array $prevTickets): array
    {
        $tz = 'Europe/Bucharest';
        $now = Carbon::now($tz);
        $daysInMonth = (int) $now->daysInMonth;
        $currentDay = (int) $now->day; // 1-based

        $salesArr = array_map('floatval', $chartData['data'] ?? []);
        $ticketsArr = array_map('intval', $ticketChartData['data'] ?? []);
        $prevSalesArr = array_map('floatval', $prevSales['data'] ?? []);
        $prevTicketsArr = array_map('intval', $prevTickets['data'] ?? []);

        $salesSoFar = array_sum($salesArr);
        $ticketsSoFar = array_sum($ticketsArr);

        // Predict daily series using the same logic the chart JS uses.
        $predicted = $this->predictMonthlySeries($salesArr, $prevSalesArr, $currentDay, $daysInMonth, $tz);
        $predictedTickets = $this->predictMonthlySeries($ticketsArr, $prevTicketsArr, $currentDay, $daysInMonth, $tz);

        return [
            'sales_so_far' => $salesSoFar,
            'tickets_so_far' => $ticketsSoFar,
            'prev_year_sales' => array_sum($prevSalesArr),
            'prev_year_tickets' => array_sum($prevTicketsArr),
            'predicted_sales' => (float) array_sum($predicted),
            'predicted_tickets' => (int) round(array_sum($predictedTickets)),
        ];
    }

    /**
     * Day-by-day prediction matching the chart JS in dashboard.blade.php.
     * Returns the full-month series (past actuals + today extrapolation +
     * future predicted). Sum it for the predicted monthly total.
     *
     * @param array<int, float|int> $current Daily actuals for the month so far
     *                                       (length = days in current month)
     * @param array<int, float|int> $prev    Daily actuals for the same month
     *                                       last year (length = days in that month)
     */
    private function predictMonthlySeries(array $current, array $prev, int $currentDay, int $daysInMonth, string $tz): array
    {
        $now = Carbon::now($tz);
        $monthStart = $now->copy()->startOfMonth();

        // Day-of-week growth ratios from completed days (i < currentDay - 1)
        $dowRatios = []; // 0..6 → list of curr/prev ratios
        $allRatios = [];
        $completedTotal = 0.0;
        $completedDays = 0;
        for ($i = 0; $i < $currentDay - 1 && $i < count($current); $i++) {
            $dow = $monthStart->copy()->addDays($i)->dayOfWeek;
            $curr = (float) ($current[$i] ?? 0);
            $prv = (float) ($prev[$i] ?? 0);
            if ($prv > 0 && $curr > 0) {
                $dowRatios[$dow][] = $curr / $prv;
                $allRatios[] = $curr / $prv;
            }
            $completedTotal += $curr;
            if ($curr > 0) $completedDays++;
        }
        $dowGrowth = [];
        for ($dow = 0; $dow < 7; $dow++) {
            $r = $dowRatios[$dow] ?? [];
            $dowGrowth[$dow] = !empty($r) ? array_sum($r) / count($r) : null;
        }
        $overallGrowth = !empty($allRatios) ? array_sum($allRatios) / count($allRatios) : null;
        $overallAvg = $completedDays > 0 ? $completedTotal / $completedDays : 0.0;

        // Today: extrapolate by hours-elapsed (matches JS hoursElapsed > 1 path)
        $hoursElapsed = $now->hour + $now->minute / 60;
        $todayActual = (float) ($current[$currentDay - 1] ?? 0);
        $todayEstimated = $hoursElapsed > 1 ? round($todayActual * (24 / $hoursElapsed)) : $todayActual;

        // Build prediction for all days
        $out = [];
        for ($i = 0; $i < $daysInMonth; $i++) {
            if ($i < $currentDay - 1) {
                $out[] = (float) ($current[$i] ?? 0);
            } elseif ($i === $currentDay - 1) {
                $out[] = (float) $todayEstimated;
            } else {
                $dow = $monthStart->copy()->addDays($i)->dayOfWeek;
                $growth = $dowGrowth[$dow] ?? $overallGrowth ?? 1.0;
                $prv = (float) ($prev[$i] ?? 0);
                if ($prv > 0 && ($dowGrowth[$dow] !== null || $overallGrowth !== null)) {
                    $out[] = round($prv * $growth);
                } else {
                    $out[] = round($overallAvg);
                }
            }
        }
        return $out;
    }

    private function getTicketChartData(int $marketplaceId, Carbon $startDate, Carbon $endDate, int $days, bool $fullMonth = false): array
    {
        $tz = 'Europe/Bucharest';
        $dailyTickets = Ticket::where('marketplace_client_id', $marketplaceId)
            ->whereIn('tickets.status', ['valid', 'used'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("DATE(created_at AT TIME ZONE 'UTC' AT TIME ZONE '{$tz}') as date, COUNT(*) as count")
            ->groupBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $labels = [];
        $data = [];
        if ($fullMonth) {
            $monthStart = Carbon::parse($startDate)->timezone($tz)->startOfMonth();
            $monthEnd = Carbon::parse($endDate)->timezone($tz)->endOfMonth();
            $current = $monthStart->copy();
            while ($current <= $monthEnd) {
                $dateKey = $current->format('Y-m-d');
                $labels[] = $current->format('d');
                $data[] = (int) ($dailyTickets[$dateKey] ?? 0);
                $current->addDay();
            }
        } else {
            $current = Carbon::now($tz)->subDays($days)->startOfDay();
            $end = Carbon::now($tz)->endOfDay();
            while ($current <= $end) {
                $dateKey = $current->format('Y-m-d');
                $labels[] = $current->format($days <= 7 ? 'D' : 'M d');
                $data[] = (int) ($dailyTickets[$dateKey] ?? 0);
                $current->addDay();
            }
        }

        return ['labels' => $labels, 'data' => $data];
    }

    private function computeCurrentMonthStats(int $marketplaceId, string $month = ''): array
    {
        $tz = 'Europe/Bucharest';
        $monthDate = $month ? Carbon::createFromFormat('Y-m', $month, $tz) : Carbon::now($tz);
        $monthStart = $monthDate->copy()->startOfMonth()->utc();
        $monthEnd = $monthDate->copy()->endOfMonth()->endOfDay()->utc();
        $now = Carbon::now($tz);
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

        // Sales this month — use subquery instead of loading IDs into memory
        $allStatuses = ['paid', 'confirmed', 'completed', 'refunded'];
        $eventSubquery = function ($sub) use ($marketplaceId) {
            $sub->select('id')->from('events')->where('marketplace_client_id', $marketplaceId);
        };

        $orderScope = function ($q) use ($marketplaceId, $eventSubquery) {
            $q->where('orders.marketplace_client_id', $marketplaceId)
                ->orWhereIn('orders.marketplace_event_id', $eventSubquery)
                ->orWhereIn('orders.event_id', $eventSubquery);
        };

        // Revenue (excl refunded)
        $totalSales = (float) Order::where($orderScope)
            ->whereIn('orders.status', $allStatuses)
            ->whereNotIn('orders.source', ['test_order', 'external_import', 'legacy_import'])
            ->whereBetween('orders.created_at', [$monthStart, $monthEnd])
            ->selectRaw("SUM(CASE WHEN orders.status = 'refunded' THEN 0 ELSE orders.total END) as revenue")
            ->value('revenue') ?? 0;

        // Marketplace commission — cached per month (expensive per-event calculation)
        $monthKey = $monthDate->format('Y-m');
        $totalCommission = Cache::remember("mp_month_comm_{$marketplaceId}_{$monthKey}", 600, fn () =>
            BillingBreakdown::calculateMarketplaceCommission(
                $marketplaceId, $monthStart, $monthEnd, (float) ($this->marketplace->commission_rate ?? 5)
            )
        );

        // Tickets sold this month — efficient join instead of nested whereHas
        $ticketsSold = Ticket::join('ticket_types', 'tickets.ticket_type_id', '=', 'ticket_types.id')
            ->join('events', 'ticket_types.event_id', '=', 'events.id')
            ->where('events.marketplace_client_id', $marketplaceId)
            ->whereIn('tickets.status', ['valid', 'used'])
            ->whereBetween('tickets.created_at', [$monthStart, $monthEnd])
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('orders')
                    ->whereColumn('orders.id', 'tickets.order_id')
                    ->where('orders.source', '!=', 'external_import');
            })
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
            ->whereNotIn('orders.source', ['test_order', 'external_import', 'legacy_import'])
            ->whereBetween('orders.created_at', [$monthStart, $monthEnd])
            ->count();

        // Payouts this month
        $monthPayouts = MarketplacePayout::where('marketplace_client_id', $marketplaceId)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('pending','approved','processing') THEN amount ELSE 0 END), 0) as pending")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as paid")
            ->first();

        // Average daily sales. For the in-progress month, divide by the days
        // elapsed so far (so a mid-month value isn't deflated by future empty
        // days); for a past month, divide by its full day count.
        $isCurrentMonth = $monthDate->isSameMonth($now) && $monthDate->isSameYear($now);
        $daysForAvg = $isCurrentMonth ? max(1, $now->day) : $monthDate->daysInMonth;
        $avgDailySales = $daysForAvg > 0 ? $totalSales / $daysForAvg : 0;

        return [
            'month_label' => $monthDate->translatedFormat('F Y'),
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
            'avg_daily_sales' => $avgDailySales,
            'avg_daily_sales_days' => $daysForAvg,
            'currency' => $this->marketplace->currency ?? 'RON',
        ];
    }

    private function computeMonthlyBilling(int $marketplaceId, string $month = ''): array
    {
        $tz = 'Europe/Bucharest';
        $monthDate = $month ? Carbon::createFromFormat('Y-m', $month, $tz) : Carbon::now($tz);
        $monthStart = $monthDate->copy()->startOfMonth()->utc();
        $monthEnd = $monthDate->copy()->endOfMonth()->endOfDay()->utc();

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

        // Include orders for marketplace events — subquery instead of loading IDs
        $eventSub = function ($sub) use ($marketplaceId) {
            $sub->select('id')->from('events')->where('marketplace_client_id', $marketplaceId);
        };
        $billingScope = function ($q) use ($marketplaceId, $eventSub) {
            $q->where('orders.marketplace_client_id', $marketplaceId)
                ->orWhereIn('orders.marketplace_event_id', $eventSub)
                ->orWhereIn('orders.event_id', $eventSub);
        };

        // 1. Ticketing: per-event commission (matches BillingBreakdown logic exactly)
        $orderRevenue = (float) Order::where($billingScope)
            ->whereIn('status', $validStatuses)
            ->whereNotIn('source', ['test_order', 'external_import', 'legacy_import'])
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('total');

        // Use DB-level SUM with ROUND to match BillingBreakdown per-event rounding
        $ticketingCommission = (float) Order::where($billingScope)
            ->whereIn('status', $validStatuses)
            ->whereNotIn('source', ['test_order', 'external_import', 'legacy_import'])
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
        // Tixello collects only TIXELLO_SHARE of each service order; the rest stays with the marketplace operator.
        $services = [];
        $servicesTotal = 0;
        foreach ($serviceLabels as $type => $label) {
            $gross = (float) ($serviceBreakdown[$type] ?? 0);
            $amount = round($gross * ServiceOrder::TIXELLO_SHARE, 2);
            $services[] = [
                'type' => $type,
                'label' => $label,
                'amount' => $amount,
            ];
            $servicesTotal += $amount;
        }

        return [
            'month_label' => $monthDate->translatedFormat('F Y'),
            'commission_rate' => $commissionRate,
            'order_revenue' => $orderRevenue,
            'ticketing_commission' => $ticketingCommission,
            'services' => $services,
            'services_total' => $servicesTotal,
            'grand_total' => $ticketingCommission + $servicesTotal,
            'currency' => $this->marketplace->currency ?? 'RON',
        ];
    }

    private function computeTodayStats(int $marketplaceId): array
    {
        $tz = 'Europe/Bucharest';
        $todayStart = Carbon::now($tz)->startOfDay()->utc();
        $todayEnd = Carbon::now($tz)->endOfDay()->utc();
        $validStatuses = ['paid', 'confirmed', 'completed'];

        $eventSubquery = function ($sub) use ($marketplaceId) {
            $sub->select('id')->from('events')->where('marketplace_client_id', $marketplaceId);
        };

        $orderScope = function ($q) use ($marketplaceId, $eventSubquery) {
            $q->where('orders.marketplace_client_id', $marketplaceId)
                ->orWhereIn('orders.marketplace_event_id', $eventSubquery)
                ->orWhereIn('orders.event_id', $eventSubquery);
        };

        $orderStats = Order::where($orderScope)
            ->whereNotIn('orders.source', ['test_order', 'external_import', 'legacy_import'])
            ->whereBetween('orders.created_at', [$todayStart, $todayEnd])
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("SUM(CASE WHEN status IN ('paid','confirmed','completed') THEN 1 ELSE 0 END) as paid_orders")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_orders")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders")
            ->selectRaw("SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_orders")
            ->selectRaw("SUM(CASE WHEN status IN ('refunded','partially_refunded') THEN 1 ELSE 0 END) as refunded_orders")
            ->selectRaw("SUM(CASE WHEN status IN ('paid','confirmed','completed') THEN \"total\" ELSE 0 END) as revenue")
            ->selectRaw("SUM(CASE WHEN status IN ('paid','confirmed','completed') THEN COALESCE(commission_amount, 0) ELSE 0 END) as commission_from_db")
            ->first();

        $ticketsSold = (int) Ticket::join('ticket_types', 'tickets.ticket_type_id', '=', 'ticket_types.id')
            ->join('events', 'ticket_types.event_id', '=', 'events.id')
            ->where('events.marketplace_client_id', $marketplaceId)
            ->whereIn('tickets.status', ['valid', 'used'])
            ->whereBetween('tickets.created_at', [$todayStart, $todayEnd])
            ->count();

        $customerStats = MarketplaceCustomer::where('marketplace_client_id', $marketplaceId)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN password IS NOT NULL THEN 1 ELSE 0 END) as registered')
            ->selectRaw('SUM(CASE WHEN password IS NULL THEN 1 ELSE 0 END) as guests')
            ->first();

        $eventsPublished = (int) Event::where('marketplace_client_id', $marketplaceId)
            ->where('is_published', true)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        // Commission: use DB commission_amount if available, otherwise calculate from rate
        $revenue = (float) ($orderStats->revenue ?? 0);
        $commissionFromDb = (float) ($orderStats->commission_from_db ?? 0);
        $commissionRate = (float) ($this->marketplace->commission_rate ?? 5);
        $commission = $commissionFromDb > 0 ? $commissionFromDb : round($revenue * $commissionRate / 100, 2);

        return [
            'total_orders' => (int) ($orderStats->total_orders ?? 0),
            'paid_orders' => (int) ($orderStats->paid_orders ?? 0),
            'pending_orders' => (int) ($orderStats->pending_orders ?? 0),
            'failed_orders' => (int) ($orderStats->failed_orders ?? 0),
            'cancelled_orders' => (int) ($orderStats->cancelled_orders ?? 0),
            'expired_orders' => (int) ($orderStats->expired_orders ?? 0),
            'refunded_orders' => (int) ($orderStats->refunded_orders ?? 0),
            'revenue' => $revenue,
            'commission' => $commission,
            'tickets_sold' => $ticketsSold,
            'new_customers' => (int) ($customerStats->total ?? 0),
            'registered_customers' => (int) ($customerStats->registered ?? 0),
            'guest_customers' => (int) ($customerStats->guests ?? 0),
            'events_published' => $eventsPublished,
            'date_label' => Carbon::now($tz)->translatedFormat('d F Y'),
        ];
    }

    /**
     * Per-event sales breakdown for a single day. Returns a list of events
     * that had at least one paid/confirmed/completed order on that day,
     * each row carrying same-day + all-time aggregates (orders, tickets,
     * revenue, commission).
     *
     * Caller is responsible for super-admin gating and date clamping
     * (the date arrives validated from getViewData()).
     */
    private function computeDailyEventReport(int $marketplaceId, string $date): array
    {
        $tz = 'Europe/Bucharest';
        $dayStart = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay()->utc();
        $dayEnd = Carbon::createFromFormat('Y-m-d', $date, $tz)->endOfDay()->utc();
        $paidStatuses = ['paid', 'confirmed', 'completed'];
        $commissionRate = (float) ($this->marketplace->commission_rate ?? 5);

        // Step 1 — find every event with at least one paid order on the
        // selected day. Group by effective event id (marketplace_event_id
        // falls back to event_id for legacy single-tenant orders), so
        // both linkage paths fold into one row.
        $eventSub = function ($sub) use ($marketplaceId) {
            $sub->select('id')->from('events')->where('marketplace_client_id', $marketplaceId);
        };
        $orderScope = function ($q) use ($marketplaceId, $eventSub) {
            $q->where('orders.marketplace_client_id', $marketplaceId)
                ->orWhereIn('orders.marketplace_event_id', $eventSub)
                ->orWhereIn('orders.event_id', $eventSub);
        };

        $dayRows = Order::where($orderScope)
            ->whereIn('orders.status', $paidStatuses)
            ->whereNotIn('orders.source', ['test_order', 'external_import', 'legacy_import'])
            ->whereBetween('orders.created_at', [$dayStart, $dayEnd])
            ->where(function ($q) {
                // Skip orders that aren't linked to any event — they
                // can't appear as a row in this per-event report.
                $q->whereNotNull('marketplace_event_id')
                  ->orWhereNotNull('event_id');
            })
            ->selectRaw('COALESCE(marketplace_event_id, event_id) as eid')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total) as revenue')
            ->selectRaw('SUM(COALESCE(commission_amount, 0)) as commission_db')
            ->groupBy('eid')
            ->get()
            ->keyBy('eid');

        if ($dayRows->isEmpty()) {
            return [];
        }

        $eventIds = $dayRows->keys()->map(fn ($v) => (int) $v)->all();

        // Step 2 — same-day ticket counts per event. Join through
        // ticket_types to land on the event id, and join through orders
        // so the parent order's status / source filters apply too. This
        // protects against tickets that are still marked 'valid' on the
        // ticket row but whose order was cancelled/refunded/imported.
        $dayTicketCounts = Ticket::join('ticket_types', 'tickets.ticket_type_id', '=', 'ticket_types.id')
            ->join('events', 'ticket_types.event_id', '=', 'events.id')
            ->join('orders', 'tickets.order_id', '=', 'orders.id')
            ->where('events.marketplace_client_id', $marketplaceId)
            ->whereIn('events.id', $eventIds)
            ->whereIn('tickets.status', ['valid', 'used'])
            ->whereIn('orders.status', $paidStatuses)
            ->whereNotIn('orders.source', ['test_order', 'external_import', 'legacy_import'])
            ->whereBetween('tickets.created_at', [$dayStart, $dayEnd])
            ->selectRaw('events.id as eid, COUNT(*) as cnt')
            ->groupBy('events.id')
            ->pluck('cnt', 'eid');

        // Step 3 — all-time per-event aggregates for the same scope, so
        // both the day cell and the running total live on the same row.
        // Filter by the same effective event id list so we don't pull
        // every marketplace order into memory.
        $eventIdsInt = array_map('intval', $eventIds);
        $eventIdsList = implode(',', $eventIdsInt);
        $totalRows = Order::where($orderScope)
            ->whereIn('orders.status', $paidStatuses)
            ->whereNotIn('orders.source', ['test_order', 'external_import', 'legacy_import'])
            ->whereRaw("COALESCE(marketplace_event_id, event_id) IN ({$eventIdsList})")
            ->selectRaw('COALESCE(marketplace_event_id, event_id) as eid')
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('SUM(total) as revenue')
            ->selectRaw('SUM(COALESCE(commission_amount, 0)) as commission_db')
            ->groupBy('eid')
            ->get()
            ->keyBy('eid');

        $totalTicketCounts = Ticket::join('ticket_types', 'tickets.ticket_type_id', '=', 'ticket_types.id')
            ->join('events', 'ticket_types.event_id', '=', 'events.id')
            ->join('orders', 'tickets.order_id', '=', 'orders.id')
            ->where('events.marketplace_client_id', $marketplaceId)
            ->whereIn('events.id', $eventIds)
            ->whereIn('tickets.status', ['valid', 'used'])
            ->whereIn('orders.status', $paidStatuses)
            ->whereNotIn('orders.source', ['test_order', 'external_import', 'legacy_import'])
            ->selectRaw('events.id as eid, COUNT(*) as cnt')
            ->groupBy('events.id')
            ->pluck('cnt', 'eid');

        // Step 4 — pull the event records (with venue) so we can render
        // name/date/venue in the UI. Done in one query and indexed by id.
        $events = Event::with(['venue:id,name,city'])
            ->whereIn('id', $eventIds)
            ->get(['id', 'title', 'event_date', 'range_start_date', 'duration_mode', 'multi_slots', 'venue_id'])
            ->keyBy('id');

        $rows = [];
        foreach ($dayRows as $eid => $day) {
            $eid = (int) $eid;
            $event = $events->get($eid);
            if (!$event) {
                continue; // Orphan order pointing at a deleted event — skip.
            }

            $dayRevenue = (float) $day->revenue;
            $dayCommissionDb = (float) $day->commission_db;
            $dayCommission = $dayCommissionDb > 0
                ? $dayCommissionDb
                : round($dayRevenue * $commissionRate / 100, 2);

            $total = $totalRows->get((string) $eid) ?? $totalRows->get($eid);
            $totalOrders = $total ? (int) $total->orders_count : 0;
            $totalRevenue = $total ? (float) $total->revenue : 0.0;
            $totalCommissionDb = $total ? (float) $total->commission_db : 0.0;
            $totalCommission = $totalCommissionDb > 0
                ? $totalCommissionDb
                : round($totalRevenue * $commissionRate / 100, 2);

            // Display date — single_day uses event_date, range starts at
            // range_start_date, multi-day slot lists fall back to the
            // earliest entry in multi_slots.
            $displayDate = null;
            if ($event->duration_mode === 'range' && $event->range_start_date) {
                $displayDate = Carbon::parse($event->range_start_date);
            } elseif ($event->duration_mode === 'single_day' && $event->event_date) {
                $displayDate = Carbon::parse($event->event_date);
            } elseif (is_array($event->multi_slots) && !empty($event->multi_slots)) {
                $firstSlot = collect($event->multi_slots)->pluck('date')->filter()->sort()->first();
                if ($firstSlot) {
                    $displayDate = Carbon::parse($firstSlot);
                }
            } elseif ($event->event_date) {
                $displayDate = Carbon::parse($event->event_date);
            }

            $venueName = '-';
            $venueCity = '';
            if ($event->venue) {
                $rawName = $event->venue->name;
                if (is_array($rawName)) {
                    $venueName = $rawName['ro'] ?? $rawName['en'] ?? reset($rawName) ?: '-';
                } else {
                    $venueName = $rawName ?: '-';
                }
                $venueCity = $event->venue->city ?: '';
            }

            $rows[] = [
                'event_id' => $eid,
                'event_name' => $event->getTranslation('title', 'ro') ?: $event->getTranslation('title', 'en') ?: '-',
                'event_edit_url' => \App\Filament\Marketplace\Resources\EventResource::getUrl('edit', ['record' => $eid]),
                'event_date' => $displayDate?->format('Y-m-d'),
                'event_date_label' => $displayDate?->translatedFormat('d M Y') ?? '-',
                'venue_name' => $venueName,
                'venue_city' => $venueCity,
                'orders_day' => (int) $day->orders_count,
                'tickets_day' => (int) ($dayTicketCounts[$eid] ?? 0),
                'sales_day' => $dayRevenue,
                'commission_day' => $dayCommission,
                'orders_total' => $totalOrders,
                'tickets_total' => (int) ($totalTicketCounts[$eid] ?? 0),
                'sales_total' => $totalRevenue,
                'commission_total' => $totalCommission,
            ];
        }

        // Highest same-day revenue first — operators usually scan from
        // top for "what moved today".
        usort($rows, fn ($a, $b) => $b['sales_day'] <=> $a['sales_day']);

        return $rows;
    }
}
