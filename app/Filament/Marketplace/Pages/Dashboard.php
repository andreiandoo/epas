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
    public string $chartMonth = '';

    #[Url]
    public string $selectedMonth = '';

    #[Url]
    public string $selectedDailyReportDate = '';

    public function mount(): void
    {
        $admin = Auth::guard('marketplace_admin')->user();
        $this->marketplace = $admin?->marketplaceClient;
        $nowRo = Carbon::now('Europe/Bucharest');
        if (!$this->selectedMonth) {
            $this->selectedMonth = $nowRo->format('Y-m');
        }
        if (!$this->selectedDailyReportDate) {
            $this->selectedDailyReportDate = $nowRo->format('Y-m-d');
        }
        if (!$this->chartMonth) {
            $this->chartMonth = $nowRo->format('Y-m');
        }
    }

    public function updatedChartMonth(): void
    {
        $this->dispatch('charts-updated');
    }

    public function shiftChartMonth(int $delta): void
    {
        $tz = 'Europe/Bucharest';
        try {
            $current = Carbon::createFromFormat('Y-m', $this->chartMonth, $tz)->startOfMonth();
        } catch (\Throwable $e) {
            $current = Carbon::now($tz)->startOfMonth();
        }
        $current->addMonths($delta);
        // Clamp: don't go before 2024-01 (data didn't exist) or beyond +12 months
        // ahead of now (predictions get too speculative further out).
        $minMonth = Carbon::create(2024, 1, 1, 0, 0, 0, $tz);
        $maxMonth = Carbon::now($tz)->addMonths(12)->startOfMonth();
        if ($current->lt($minMonth)) $current = $minMonth;
        if ($current->gt($maxMonth)) $current = $maxMonth;
        $this->chartMonth = $current->format('Y-m');
        $this->dispatch('charts-updated');
    }

    public function updatedSelectedMonth(): void
    {
        // Clear month-related caches when month changes
        if ($this->marketplace) {
            $id = $this->marketplace->id;
            Cache::forget("mp_dash_month_{$id}_{$this->selectedMonth}");
            Cache::forget("mp_dash_billing_{$id}_{$this->selectedMonth}");
            // Month sales/commission now come from the daily-report cache; the
            // report-based chart series cache is keyed by month too.
            Cache::forget("mp_dash_chart_rep_v1_{$id}_month_{$this->selectedMonth}");
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

        // Resolved early: the sales/tickets month chart is super-admin-only and,
        // when shown, is driven by the SAME per-day figures as the daily report
        // table (so the chart bar for a day equals that day's report total).
        $marketplaceAdminUser = Auth::guard('marketplace_admin')->user();
        $isSuperAdmin = $marketplaceAdminUser?->isSuperAdmin() ?? false;
        // "Marketplace admins" = Administrator + Super Administrator roles
        // (excludes Moderator). Gates the invitation-abuse financial report.
        $isMarketplaceAdmin = in_array($marketplaceAdminUser?->role, ['super_admin', 'admin'], true);

        // Cache stats for 30 minutes (heavy queries on large tables)
        $stats = Cache::remember("mp_dash_stats_{$marketplaceId}", 1800, function () use ($marketplaceId) {
            return $this->computeStats($marketplaceId);
        });

        // Cache chart data for 10 minutes (keyed by period) — use Romania timezone
        $tz = 'Europe/Bucharest';
        $isMonthView = $this->chartPeriod === 'month';
        $prevYearChartData = null;
        $prevYearTicketChartData = null;
        $chartMonthMeta = null;

        if ($isMonthView) {
            $nowRo = Carbon::now($tz);
            $monthRef = $this->chartMonth ?: $nowRo->format('Y-m');
            try {
                $chartMonthDate = Carbon::createFromFormat('Y-m', $monthRef, $tz)->startOfMonth();
            } catch (\Throwable $e) {
                $chartMonthDate = $nowRo->copy()->startOfMonth();
                $monthRef = $chartMonthDate->format('Y-m');
            }

            $nowMonthStart = $nowRo->copy()->startOfMonth();
            if ($chartMonthDate->lt($nowMonthStart)) {
                $chartMonthMode = 'past';
                $currentDayInView = $chartMonthDate->daysInMonth + 1; // entire month completed
            } elseif ($chartMonthDate->gt($nowMonthStart)) {
                $chartMonthMode = 'future';
                $currentDayInView = 0;
            } else {
                $chartMonthMode = 'current';
                $currentDayInView = (int) $nowRo->day;
            }

            $startDate = $chartMonthDate->copy()->utc();
            $endDate = $chartMonthDate->copy()->endOfMonth()->endOfDay()->utc();
            $chartDays = $chartMonthDate->daysInMonth;

            // Past months are immutable → cache 24h. Current/future change as
            // sales come in → keep the existing 10-min TTL.
            $chartCacheTtl = $chartMonthMode === 'past' ? 86400 : 600;

            // "Vânzări" bars = money collected per day (SUM order.total), the
            // same basis as the "Total vânzări" card and billing-breakdown.
            $chartData = Cache::remember("mp_dash_chart_{$marketplaceId}_month_{$monthRef}", $chartCacheTtl, function () use ($marketplaceId, $startDate, $endDate, $chartDays) {
                return $this->getChartData($marketplaceId, $startDate, $endDate, $chartDays, true);
            });
            $ticketChartData = Cache::remember("mp_dash_tchart_{$marketplaceId}_month_{$monthRef}", $chartCacheTtl, function () use ($marketplaceId, $startDate, $endDate, $chartDays) {
                return $this->getTicketChartData($marketplaceId, $startDate, $endDate, $chartDays, true);
            });

            // Previous year same month — always immutable, cache 24h.
            $prevYearMonthDate = $chartMonthDate->copy()->subYear();
            $prevStart = $prevYearMonthDate->copy()->utc();
            $prevEnd = $prevYearMonthDate->copy()->endOfMonth()->endOfDay()->utc();
            $prevDays = $prevYearMonthDate->daysInMonth;
            $prevYearChartData = Cache::remember("mp_dash_chart_{$marketplaceId}_prevyear_{$prevYearMonthDate->format('Y-m')}", 86400, function () use ($marketplaceId, $prevStart, $prevEnd, $prevDays) {
                // Include legacy_import for the comparison line — last year's
                // sales are mostly migrated orders; excluding them would leave
                // the prev-year series empty and nothing to compare against.
                return $this->getChartData($marketplaceId, $prevStart, $prevEnd, $prevDays, true, excludeLegacy: false);
            });
            $prevYearTicketChartData = Cache::remember("mp_dash_tchart_{$marketplaceId}_prevyear_{$prevYearMonthDate->format('Y-m')}", 86400, function () use ($marketplaceId, $prevStart, $prevEnd, $prevDays) {
                return $this->getTicketChartData($marketplaceId, $prevStart, $prevEnd, $prevDays, true);
            });

            // Future-month prediction needs a YoY growth basis. We don't have
            // any same-month observations yet, so derive it from the current
            // month MTD vs same DOW-aligned days last year (overall ratio,
            // capped). Used by both sales and tickets card.
            $futureGrowthRatio = null;
            if ($chartMonthMode === 'future') {
                $futureGrowthRatio = $this->deriveCurrentMonthGrowthRatio($marketplaceId, $nowRo);
            }

            // Cards under chart — uses cache window that varies per mode.
            $summaryKey = "mp_dash_chart_summary_{$marketplaceId}_{$monthRef}_" . ($chartMonthMode === 'current' ? $nowRo->format('Y-m-d-H') : 'static');
            $summaryTtl = $chartMonthMode === 'current' ? 300 : 86400;
            $chartSummary = Cache::remember(
                $summaryKey,
                $summaryTtl,
                fn () => $this->computeMonthChartSummary(
                    $chartData,
                    $ticketChartData,
                    $prevYearChartData,
                    $prevYearTicketChartData,
                    $chartMonthMode,
                    $currentDayInView,
                    $chartDays,
                    $chartMonthDate,
                    $futureGrowthRatio,
                )
            );

            // Navigation bounds — keep clamp consistent with shiftChartMonth().
            $minMonth = Carbon::create(2024, 1, 1, 0, 0, 0, $tz);
            $maxMonth = $nowRo->copy()->addMonths(12)->startOfMonth();

            $chartMonthMeta = [
                'mode' => $chartMonthMode,
                'monthRef' => $monthRef,
                'monthStart' => $chartMonthDate->format('Y-m-d'),
                'monthLabel' => $chartMonthDate->locale('ro')->translatedFormat('F Y'),
                'prevMonthLabel' => $prevYearMonthDate->locale('ro')->translatedFormat('F Y'),
                'daysInMonth' => $chartDays,
                'currentDay' => $currentDayInView,
                'futureGrowth' => $futureGrowthRatio,
                'canGoPrev' => $chartMonthDate->copy()->subMonth()->gte($minMonth),
                'canGoNext' => $chartMonthDate->copy()->addMonth()->lte($maxMonth),
            ];
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
        $todayStats = Cache::remember("mp_dash_today_v2_{$marketplaceId}", 30, function () use ($marketplaceId) {
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

        // Invitation abuse detection — events where projected commission
        // on free tickets exceeds commission earned from paid sales. Cached
        // 30 min inside the service. Manual bust via ?refresh_invite_abuse=1.
        $invitationAbuse = app(\App\Services\Analytics\InvitationAbuseAnalyzer::class)
            ->analyze($marketplaceId, request()->has('refresh_invite_abuse'));

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
            // Shared per-day cache — same key the month chart reads, so the
            // chart bar and this table never diverge for the same day.
            $dailyEventReport = $this->dailyReportRowsCached($marketplaceId, $reqDate);
        }

        return [
            'marketplace' => $marketplace,
            'isSuperAdmin' => $isSuperAdmin,
            'isMarketplaceAdmin' => $isMarketplaceAdmin,
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
            'invitationAbuse' => $invitationAbuse,
            'billing' => $billingData,
            'todayStats' => $todayStats,
            'prevYearChartData' => $prevYearChartData,
            'prevYearTicketChartData' => $prevYearTicketChartData,
            'chartSummary' => $chartSummary,
            'chartMonthMeta' => $chartMonthMeta,
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
            ? ['test_order', 'pos_test', 'external_import', 'legacy_import']
            : ['test_order', 'pos_test', 'external_import'];
        // Event-linked scope (same as the daily report table): only orders tied
        // to a marketplace event count, so the chart's daily "Vânzări" bar
        // equals that day's report total. Orders with no event link can't be
        // attributed to a report row, so they're excluded here too.
        $eventSub = function ($sub) use ($marketplaceId) {
            $sub->select('id')->from('events')->where('marketplace_client_id', $marketplaceId);
        };
        $dailySales = Order::where(function ($q) use ($eventSub) {
                $q->whereIn('marketplace_event_id', $eventSub)->orWhereIn('event_id', $eventSub);
            })
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
    private function computeMonthChartSummary(
        array $chartData,
        array $ticketChartData,
        ?array $prevSales,
        ?array $prevTickets,
        string $mode = 'current',
        ?int $currentDay = null,
        ?int $daysInMonth = null,
        ?Carbon $monthDate = null,
        ?float $futureGrowth = null,
    ): array {
        $tz = 'Europe/Bucharest';
        $monthDate = $monthDate ?? Carbon::now($tz)->startOfMonth();
        $daysInMonth = $daysInMonth ?? (int) $monthDate->daysInMonth;
        $currentDay = $currentDay ?? (int) Carbon::now($tz)->day;

        $salesArr = array_map('floatval', $chartData['data'] ?? []);
        $ticketsArr = array_map('intval', $ticketChartData['data'] ?? []);
        $prevSalesArr = array_map('floatval', $prevSales['data'] ?? []);
        $prevTicketsArr = array_map('intval', $prevTickets['data'] ?? []);

        $salesSoFar = array_sum($salesArr);
        $ticketsSoFar = array_sum($ticketsArr);

        $predicted = $this->predictMonthlySeries($salesArr, $prevSalesArr, $currentDay, $daysInMonth, $tz, $monthDate, $mode, $futureGrowth);
        $predictedTickets = $this->predictMonthlySeries($ticketsArr, $prevTicketsArr, $currentDay, $daysInMonth, $tz, $monthDate, $mode, $futureGrowth);

        return [
            'mode' => $mode,
            'sales_so_far' => $salesSoFar,
            'tickets_so_far' => $ticketsSoFar,
            'prev_year_sales' => array_sum($prevSalesArr),
            'prev_year_tickets' => array_sum($prevTicketsArr),
            'predicted_sales' => (float) array_sum($predicted),
            'predicted_tickets' => (int) round(array_sum($predictedTickets)),
        ];
    }

    /**
     * Overall YoY growth ratio for the CURRENT month so far, used as the
     * growth basis when the chart is showing a FUTURE month (we have no
     * observations there yet, so we borrow the current month's pace).
     * Returns null if either side has no data — caller falls back to 1.0.
     */
    private function deriveCurrentMonthGrowthRatio(int $marketplaceId, Carbon $now): ?float
    {
        $tz = 'Europe/Bucharest';
        $currMonth = $now->format('Y-m');
        $prevYearMonth = $now->copy()->subYear()->format('Y-m');

        // Reuse the same cache keys populated by the main month flow when
        // viewing the current month — saves a query in the common case.
        $currKey = "mp_dash_chart_{$marketplaceId}_month_{$currMonth}";
        $prevKey = "mp_dash_chart_{$marketplaceId}_prevyear_{$prevYearMonth}";

        $currData = Cache::get($currKey);
        $prevData = Cache::get($prevKey);

        if (!$currData) {
            $currStart = $now->copy()->startOfMonth()->utc();
            $currEnd = $now->copy()->endOfDay()->utc();
            $currDays = $now->daysInMonth;
            $currData = Cache::remember($currKey, 600, fn () => $this->getChartData($marketplaceId, $currStart, $currEnd, $currDays, true));
        }
        if (!$prevData) {
            $prevStart = $now->copy()->subYear()->startOfMonth()->utc();
            $prevEnd = $now->copy()->subYear()->endOfMonth()->endOfDay()->utc();
            $prevDays = $now->copy()->subYear()->daysInMonth;
            $prevData = Cache::remember($prevKey, 86400, fn () => $this->getChartData($marketplaceId, $prevStart, $prevEnd, $prevDays, true, excludeLegacy: false));
        }

        $currDay = (int) $now->day;
        $currSum = 0.0;
        for ($i = 0; $i < $currDay; $i++) {
            $currSum += (float) ($currData['data'][$i] ?? 0);
        }

        // DOW-align prev year (same shift logic as predictMonthlySeries).
        $currMonthStart = $now->copy()->startOfMonth();
        $prevMonthStart = $currMonthStart->copy()->subYear();
        $dowShift = (((int) $currMonthStart->dayOfWeek - (int) $prevMonthStart->dayOfWeek) + 7) % 7;
        $prevSum = 0.0;
        $prevDataArr = $prevData['data'] ?? [];
        for ($i = 0; $i < $currDay; $i++) {
            $idx = $i + $dowShift;
            if ($idx >= 0 && $idx < count($prevDataArr)) {
                $prevSum += (float) ($prevDataArr[$idx] ?? 0);
            }
        }

        if ($currSum <= 0 || $prevSum <= 0) {
            return null;
        }
        return $currSum / $prevSum;
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
    private function predictMonthlySeries(
        array $current,
        array $prev,
        int $currentDay,
        int $daysInMonth,
        string $tz,
        ?Carbon $monthDate = null,
        string $mode = 'current',
        ?float $futureGrowth = null,
    ): array {
        $now = Carbon::now($tz);
        $monthStart = ($monthDate ?? $now)->copy()->startOfMonth();
        $prevMonthStart = $monthStart->copy()->subYear();
        $prevDaysInMonth = $prevMonthStart->daysInMonth;

        // DOW shift between adjacent years — see backtest analysis on
        // payout dashboards. Without this, "Wednesday ratio" at index i
        // actually compared current Wednesday vs prev Tuesday.
        $currDowAtFirst = (int) $monthStart->dayOfWeek;
        $prevDowAtFirst = (int) $prevMonthStart->dayOfWeek;
        $dowShift = (($currDowAtFirst - $prevDowAtFirst) + 7) % 7;

        $prevAt = function (int $i) use ($prev, $dowShift, $prevDaysInMonth): float {
            $prevIdx = $i + $dowShift;
            if ($prevIdx < 0 || $prevIdx >= count($prev) || $prevIdx >= $prevDaysInMonth) {
                return 0.0;
            }
            return (float) ($prev[$prevIdx] ?? 0);
        };

        $clampGlobal = fn (float $x): float => max(0.3, min(3.5, $x));

        // PAST month: month is done, no prediction — return actuals.
        if ($mode === 'past') {
            $out = [];
            for ($i = 0; $i < $daysInMonth; $i++) {
                $out[] = (float) ($current[$i] ?? 0);
            }
            return $out;
        }

        // FUTURE month: no current observations exist. Predict every day
        // as prev[i+shift] × (growth from current month MTD vs prev year
        // MTD). If no growth basis available, assume parity.
        if ($mode === 'future') {
            $growth = $futureGrowth !== null ? $clampGlobal($futureGrowth) : 1.0;
            $out = [];
            for ($i = 0; $i < $daysInMonth; $i++) {
                $out[] = round($prevAt($i) * $growth);
            }
            return $out;
        }

        // Smoothing helpers — backtest on May+April 2025 showed that
        // arithmetic mean + uncapped growth ratios let one big early-
        // month day (event launch, promo spike) poison the whole month's
        // prediction. April day-5 cutoff went from +70% off to +51% off
        // just from adding these two, and to +51% with MTD pacing on top.
        //
        // Caps reflect realistic year-over-year growth for marketplace
        // ticketing: nothing should grow 4× or shrink to 30% on a single
        // DOW. Anything outside that band is signal-too-weak; fall back
        // to overall growth (also capped).
        $clamp = fn (float $x): float => max(0.3, min(3.5, $x));

        // Geometric mean dampens single-day outliers far better than
        // arithmetic. sqrt(0.5 × 5) = 1.58 vs arithmetic (0.5 + 5) / 2 = 2.75.
        $geomean = function (array $vals) {
            $vals = array_filter($vals, fn ($v) => $v > 0);
            if (empty($vals)) return null;
            if (count($vals) === 1) return reset($vals);
            $sumLog = 0.0;
            foreach ($vals as $v) $sumLog += log($v);
            return exp($sumLog / count($vals));
        };

        // Collect ratios from completed days (excludes today which is partial).
        $dowRatios = [];
        $allRatios = [];
        $completedTotal = 0.0;
        $completedDays = 0;
        for ($i = 0; $i < $currentDay - 1 && $i < count($current); $i++) {
            $dow = (int) $monthStart->copy()->addDays($i)->dayOfWeek;
            $curr = (float) ($current[$i] ?? 0);
            $prv = $prevAt($i);
            if ($prv > 0 && $curr > 0) {
                $dowRatios[$dow][] = $curr / $prv;
                $allRatios[] = $curr / $prv;
            }
            $completedTotal += $curr;
            if ($curr > 0) $completedDays++;
        }
        $dowGrowth = [];
        for ($dow = 0; $dow < 7; $dow++) {
            $g = $geomean($dowRatios[$dow] ?? []);
            $dowGrowth[$dow] = $g !== null ? $clamp($g) : null;
        }
        $overallGeoGrowth = $geomean($allRatios);
        $overallGrowth = $overallGeoGrowth !== null ? $clamp($overallGeoGrowth) : null;
        $overallAvg = $completedDays > 0 ? $completedTotal / $completedDays : 0.0;

        // Today: extrapolate by hours-elapsed.
        $hoursElapsed = $now->hour + $now->minute / 60;
        $todayActual = (float) ($current[$currentDay - 1] ?? 0);
        $todayEstimated = $hoursElapsed > 1 ? round($todayActual * (24 / $hoursElapsed)) : $todayActual;

        // MTD pacing fallback for cutoff < 7 — with so few completed
        // days, per-DOW ratios are 1-sample noise. Project total month
        // from current month-to-date vs same-pace point in prev year,
        // then distribute the remaining proportionally to prev days
        // (preserves DOW shape without the per-day amplification).
        $useMtdPacing = ($currentDay - 1) < 7;
        $mtdRemaining = null;
        if ($useMtdPacing) {
            $currMtd = $completedTotal + (float) $todayEstimated;
            $prevMtdAligned = 0.0;
            for ($j = 0; $j < $currentDay; $j++) $prevMtdAligned += $prevAt($j);
            $prevTotalAligned = 0.0;
            for ($j = 0; $j < $daysInMonth; $j++) $prevTotalAligned += $prevAt($j);
            if ($prevMtdAligned > 0 && $prevTotalAligned > 0) {
                $pacePct = $prevMtdAligned / $prevTotalAligned;
                $projectedTotal = $currMtd / $pacePct;
                $mtdRemaining = max(0.0, $projectedTotal - $currMtd);
            }
        }

        // Pre-compute prev remaining sum for proportional distribution.
        $prevRemainingSum = 0.0;
        for ($j = $currentDay; $j < $daysInMonth; $j++) $prevRemainingSum += $prevAt($j);

        $out = [];
        for ($i = 0; $i < $daysInMonth; $i++) {
            if ($i < $currentDay - 1) {
                $out[] = (float) ($current[$i] ?? 0);
            } elseif ($i === $currentDay - 1) {
                $out[] = (float) $todayEstimated;
            } else {
                if ($useMtdPacing && $mtdRemaining !== null && $prevRemainingSum > 0) {
                    $weight = $prevAt($i) / $prevRemainingSum;
                    $out[] = round($mtdRemaining * $weight);
                    continue;
                }
                $dow = (int) $monthStart->copy()->addDays($i)->dayOfWeek;
                $growth = $dowGrowth[$dow] ?? $overallGrowth ?? 1.0;
                $prv = $prevAt($i);
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
        // Count via ticket_types→events→orders (same as the daily report), not
        // the unreliable tickets.marketplace_client_id column, so the chart's
        // ticket bars match the report's tickets_day.
        $dailyTickets = DB::table('tickets as t')
            ->join('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->join('events as e', 'e.id', '=', 'tt.event_id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('e.marketplace_client_id', $marketplaceId)
            ->whereIn('t.status', ['valid', 'used'])
            ->whereIn('o.status', ['paid', 'confirmed', 'completed'])
            ->whereNotIn('o.source', ['test_order', 'pos_test', 'external_import', 'legacy_import'])
            ->whereBetween('t.created_at', [$startDate, $endDate])
            ->selectRaw("DATE(t.created_at AT TIME ZONE 'UTC' AT TIME ZONE '{$tz}') as date, COUNT(*) as count")
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

        // Order scope for the month counts below (subquery avoids loading IDs).
        $eventSubquery = function ($sub) use ($marketplaceId) {
            $sub->select('id')->from('events')->where('marketplace_client_id', $marketplaceId);
        };

        $orderScope = function ($q) use ($marketplaceId, $eventSubquery) {
            $q->where('orders.marketplace_client_id', $marketplaceId)
                ->orWhereIn('orders.marketplace_event_id', $eventSubquery)
                ->orWhereIn('orders.event_id', $eventSubquery);
        };

        // "Total vânzări" = money actually collected from sales this month =
        // SUM(order.total). A package counts at the price paid for it (not the
        // sum of its component products), which is exactly what order.total is.
        // Matches billing-breakdown's "Încasări totale".
        $totalSales = (float) Order::where($orderScope)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereNotIn('source', ['test_order', 'pos_test', 'external_import', 'legacy_import'])
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('total');

        // "Venituri brute" = commission AmBilet actually earned this month, via
        // the authoritative SalesBreakdownService (floor/leisure/per-type aware
        // — the raw commission_amount is 0 for POS/leisure). One month-scoped
        // build per event (not per event × per day), cached on its own key so
        // the expensive part isn't recomputed on every dashboard load.
        $isPastMonth = $monthDate->copy()->endOfMonth()->lt(Carbon::now($tz)->startOfMonth());
        $totalCommission = Cache::remember(
            "mp_month_comm_svc_{$marketplaceId}_{$monthDate->format('Y-m')}",
            $isPastMonth ? 86400 : 900,
            fn () => $this->monthCommissionViaService($marketplaceId, $monthStart, $monthEnd)
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
            ->whereNotIn('orders.source', ['test_order', 'pos_test', 'external_import', 'legacy_import'])
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

        // 1. Ticketing: commission base = sum of tickets.price for sold and
        //    refunded tickets in the period. Mirrors BillingBreakdown's
        //    ticketBaseTotal exactly so the two views agree to the RON.
        //
        //    Historical bug: the old query multiplied orders.total × rate.
        //    For added_on_top events, orders.total INCLUDES the commission
        //    the customer paid on top of face value, so `rate × orders.total`
        //    double-counted the commission (a 100 lei ticket sold as 105
        //    would produce 5.25 lei instead of 5 lei). Aligning on tickets
        //    .price (face value) removes the loop-back.

        // orders.total still surfaces as "Comenzi" in the stats card
        // above the billing card; it's not the commission base anymore.
        $orderRevenue = (float) Order::where($billingScope)
            ->whereIn('status', $validStatuses)
            ->whereNotIn('source', ['test_order', 'pos_test', 'external_import', 'legacy_import'])
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->sum('total');

        // Sum of tickets.price over the marketplace-scoped orders in the
        // month, counting tickets that are either currently sold
        // (status IN valid/used and NOT refund_status=refunded) or
        // refunded outright. Matches BillingBreakdown's ticket loop.
        $ticketCommissionBase = (float) \DB::table('tickets as t')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where(function ($q) use ($marketplaceId, $eventSub) {
                $q->where('o.marketplace_client_id', $marketplaceId)
                    ->orWhereIn('o.marketplace_event_id', $eventSub)
                    ->orWhereIn('o.event_id', $eventSub);
            })
            ->whereNotIn('o.source', ['test_order', 'pos_test', 'external_import', 'legacy_import'])
            ->whereBetween('t.created_at', [$monthStart, $monthEnd])
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereIn('t.status', ['valid', 'used'])
                        ->where(function ($q3) {
                            $q3->whereNull('t.refund_status')
                                ->orWhere('t.refund_status', '<>', 'refunded');
                        });
                })->orWhere('t.refund_status', 'refunded');
            })
            ->sum(\DB::raw('COALESCE(t.price, 0)'));

        $ticketingCommission = round($ticketCommissionBase * ($commissionRate / 100), 2);

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
            // Actual base the commission is computed on — sum of
            // tickets.price (face value). Surfaced separately from
            // order_revenue because the two diverge on added_on_top
            // events and the "Comision X% din Y" label needs the base
            // that yields the shown total.
            'ticket_commission_base' => round($ticketCommissionBase, 2),
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
            ->whereNotIn('orders.source', ['test_order', 'pos_test', 'external_import', 'legacy_import'])
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
            // Exclude Test POS smoke-test tickets so the "today" card matches
            // the daily report (which excludes them via SalesBreakdownService).
            ->whereRaw("COALESCE((ticket_types.meta->>'is_test')::boolean, false) = false")
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

        // Money/ticket cards must MATCH the "Raport vânzări pe zi" totals — both
        // reflect the authoritative SalesBreakdownService (floor-aware, leisure-
        // aware) rather than SUM(orders.total) + a flat rate. Reuse the (cached)
        // per-event daily report for today and sum its "Azi" columns so the two
        // panels can never disagree. The status breakdown (pending/failed/...)
        // below stays from the raw query — it's informational and not part of
        // the paid-sales report.
        $todayDate = Carbon::now($tz)->toDateString();
        $dailyRows = $this->computeDailyEventReport($marketplaceId, $todayDate);
        $reportOrders = (int) array_sum(array_column($dailyRows, 'orders_day'));
        $reportTickets = (int) array_sum(array_column($dailyRows, 'tickets_day'));
        $reportRevenue = (float) array_sum(array_column($dailyRows, 'sales_day'));
        $reportCommission = (float) array_sum(array_column($dailyRows, 'commission_day'));

        return [
            'total_orders' => $reportOrders,
            'paid_orders' => $reportOrders,
            'pending_orders' => (int) ($orderStats->pending_orders ?? 0),
            'failed_orders' => (int) ($orderStats->failed_orders ?? 0),
            'cancelled_orders' => (int) ($orderStats->cancelled_orders ?? 0),
            'expired_orders' => (int) ($orderStats->expired_orders ?? 0),
            'refunded_orders' => (int) ($orderStats->refunded_orders ?? 0),
            'revenue' => $reportRevenue,
            'commission' => $reportCommission,
            'tickets_sold' => $reportTickets,
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
    /**
     * Per-day report rows, cached under the SAME key used by the daily report
     * table so the month chart and the table share one computation and can
     * never disagree for a given day. Today keeps a short TTL (still moving);
     * past days are immutable → cache 24h.
     */
    private function dailyReportRowsCached(int $marketplaceId, string $date): array
    {
        $today = Carbon::now('Europe/Bucharest')->format('Y-m-d');
        $ttl = ($date >= $today) ? 60 : 86400;

        return Cache::remember(
            "mp_dash_daily_evt_v8_{$marketplaceId}_{$date}",
            $ttl,
            fn () => $this->computeDailyEventReport($marketplaceId, $date)
        );
    }

    /**
     * Authoritative marketplace commission for a month — one month-scoped
     * SalesBreakdownService build per event that had a paid sale in the window
     * (floor/leisure/per-type aware). Cached by the caller. Much cheaper than
     * summing per-day reports (one build per event, not per event × per day).
     */
    private function monthCommissionViaService(int $marketplaceId, Carbon $monthStart, Carbon $monthEnd): float
    {
        $eventSub = function ($sub) use ($marketplaceId) {
            $sub->select('id')->from('events')->where('marketplace_client_id', $marketplaceId);
        };
        $eventIds = Order::where(function ($q) use ($eventSub) {
                $q->whereIn('marketplace_event_id', $eventSub)->orWhereIn('event_id', $eventSub);
            })
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->whereNotIn('source', ['test_order', 'pos_test', 'external_import', 'legacy_import'])
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->where(function ($q) {
                $q->whereNotNull('marketplace_event_id')->orWhereNotNull('event_id');
            })
            ->selectRaw('COALESCE(marketplace_event_id, event_id) as eid')
            ->groupBy('eid')
            ->pluck('eid');

        $svc = app(\App\Services\Marketplace\SalesBreakdownService::class);
        $sum = 0.0;
        foreach ($eventIds as $eid) {
            $event = Event::find((int) $eid);
            if (!$event) {
                continue;
            }
            $bd = $svc->build($event, $monthStart, $monthEnd, exactBounds: true);
            $sum += (float) $bd['total_commission'] + (float) ($bd['total_commission_kept_from_refunds'] ?? 0);
        }

        return round($sum, 2);
    }

    private function computeDailyEventReport(int $marketplaceId, string $date): array
    {
        $tz = 'Europe/Bucharest';
        $dayStart = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay()->utc();
        $dayEnd = Carbon::createFromFormat('Y-m-d', $date, $tz)->endOfDay()->utc();
        $paidStatuses = ['paid', 'confirmed', 'completed'];

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
            ->whereNotIn('orders.source', ['test_order', 'pos_test', 'external_import', 'legacy_import'])
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
            ->whereNotIn('orders.source', ['test_order', 'pos_test', 'external_import', 'legacy_import'])
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
            ->whereNotIn('orders.source', ['test_order', 'pos_test', 'external_import', 'legacy_import'])
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
            ->whereNotIn('orders.source', ['test_order', 'pos_test', 'external_import', 'legacy_import'])
            ->selectRaw('events.id as eid, COUNT(*) as cnt')
            ->groupBy('events.id')
            ->pluck('cnt', 'eid');

        // Step 4 — pull the event records (with venue) so we can render
        // name/date/venue in the UI. Done in one query and indexed by id.
        $events = Event::with(['venue:id,name,city', 'marketplaceOrganizer'])
            ->whereIn('id', $eventIds)
            ->get([
                'id', 'title', 'event_date', 'range_start_date', 'duration_mode', 'multi_slots', 'venue_id',
                // Needed by SalesBreakdownService for leisure valuation + commission
                // (rate/floor/mode). Without these it re-fetches per event or falls
                // back to the wrong rate.
                'display_template', 'marketplace_organizer_id', 'marketplace_client_id', 'tenant_id', 'commission_rate', 'commission_mode',
            ])
            ->keyBy('id');

        // Revenue + commission must match each event's own Vânzări tab (and
        // payout math), so derive them from the authoritative
        // SalesBreakdownService rather than SUM(orders.total) / a flat
        // marketplace commission rate. The old approach (a) undercounted
        // revenue for orders linked to the event only through their tickets
        // (order.event_id / marketplace_event_id NULL — typical for POS /
        // leisure sales) while still counting those tickets, and (b) fell back
        // to a flat marketplace rate when orders.commission_amount was 0. That
        // produced e.g. 863,00 / 8,63 on the dashboard vs 1.303,00 / 71,58 on
        // the event page for the same 25 tickets.
        $salesService = app(\App\Services\Marketplace\SalesBreakdownService::class);

        $rows = [];
        foreach ($dayRows as $eid => $day) {
            $eid = (int) $eid;
            $event = $events->get($eid);
            if (!$event) {
                continue; // Orphan order pointing at a deleted event — skip.
            }

            // Revenue = money collected (SUM order.total), so the report agrees
            // with the "Total vânzări" card, the chart and billing's "Încasări
            // totale". A package counts at the price paid, not the sum of its
            // components. Commission = AmBilet's actual cut via
            // SalesBreakdownService (floor/leisure/per-type aware — the raw
            // orders.commission_amount is 0 for POS/leisure).
            $dayBd = $salesService->build($event, $dayStart, $dayEnd, exactBounds: true);
            $dayKept = (float) ($dayBd['total_commission_kept_from_refunds'] ?? 0);
            $dayRevenue = (float) $day->revenue;
            $dayCommission = (float) $dayBd['total_commission'] + $dayKept;

            $total = $totalRows->get((string) $eid) ?? $totalRows->get($eid);
            $totalOrders = $total ? (int) $total->orders_count : 0;

            // All-time totals for the same event (no period bounds).
            $totalBd = $salesService->build($event);
            $totalKept = (float) ($totalBd['total_commission_kept_from_refunds'] ?? 0);
            $totalRevenue = $total ? (float) $total->revenue : 0.0;
            $totalCommission = (float) $totalBd['total_commission'] + $totalKept;

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
