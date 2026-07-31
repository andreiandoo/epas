<?php

namespace App\Services\Marketplace;

use App\Filament\Marketplace\Pages\BillingBreakdown;
use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplacePayout;
use App\Models\Order;
use App\Models\ServiceOrder;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * All-time (lifetime) marketplace statistics, extracted from the dashboard so
 * they live on their own /marketplace/all-time-stats page. Also provides a
 * legacy-vs-Tixello breakdown: what was imported from the previous system
 * (source external_import / legacy_import) vs what was actually processed
 * through Tixello (everything else, test data excluded).
 */
class AllTimeStatsService
{
    /** Orders that were imported from a previous/external system, never processed by Tixello. */
    public const IMPORTED_SOURCES = ['external_import', 'legacy_import'];

    /** Test data — never counted on either side of the breakdown. */
    public const TEST_SOURCES = ['test_order', 'pos_test'];

    /**
     * All-time cumulative cards — moved verbatim from Dashboard::computeStats().
     * Lifetime totals DO count legacy_import (migrated orders) so figures reflect
     * the full history; only external_import + test data are excluded.
     */
    public function cards(int $marketplaceId, ?MarketplaceClient $marketplace = null): array
    {
        $today = Carbon::now()->startOfDay();

        // 1. Events
        $eventStats = Event::where('marketplace_client_id', $marketplaceId)
            ->whereNull('parent_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN NOT is_cancelled AND (
                (duration_mode = 'single_day' AND event_date >= ?) OR
                (duration_mode = 'range' AND range_end_date >= ?) OR
                (duration_mode NOT IN ('single_day','range') AND multi_slots IS NOT NULL)
            ) THEN 1 ELSE 0 END) as active", [$today, $today])
            ->first();

        // 2. Customers
        $customerStats = MarketplaceCustomer::where('marketplace_client_id', $marketplaceId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN password IS NULL THEN 1 ELSE 0 END) as guests')
            ->selectRaw('SUM(CASE WHEN password IS NOT NULL THEN 1 ELSE 0 END) as registered')
            ->first();

        // 3. Orders (money) — lifetime, legacy_import included, external+test excluded
        $orderStats = Order::where(fn ($q) => $this->scopeMarketplaceOrders($q, $marketplaceId))
            ->whereNotIn('source', ['test_order', 'external_import'])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN DATE(created_at) = ? THEN 1 ELSE 0 END) as today", [today()->toDateString()])
            ->selectRaw("SUM(CASE WHEN status IN ('paid','confirmed','completed') THEN 1 ELSE 0 END) as paid")
            ->selectRaw("SUM(CASE WHEN status IN ('paid','confirmed','completed') THEN \"total\" ELSE 0 END) as revenue")
            ->selectRaw("SUM(CASE WHEN status IN ('paid','confirmed','completed') THEN COALESCE(commission_amount, 0) ELSE 0 END) as commissions")
            ->first();

        $paidOrdersCount = (int) $orderStats->paid;
        $orderRevenue = (float) $orderStats->revenue;
        $commissions = (float) $orderStats->commissions;

        // 4. Service orders
        $serviceStats = ServiceOrder::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', ['active', 'completed'])
            ->where('payment_status', 'paid')
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->first();
        $serviceOrdersTotal = (float) $serviceStats->total;

        // 5. Tickets
        $ticketStats = Ticket::join('ticket_types', 'tickets.ticket_type_id', '=', 'ticket_types.id')
            ->join('events', 'ticket_types.event_id', '=', 'events.id')
            ->where('events.marketplace_client_id', $marketplaceId)
            ->selectRaw('COUNT(*) as total_db')
            ->selectRaw("SUM(CASE WHEN tickets.status IN ('valid', 'used') THEN 1 ELSE 0 END) as sold")
            ->selectRaw("SUM(CASE WHEN tickets.status IN ('valid', 'used') AND DATE(tickets.created_at) = ? THEN 1 ELSE 0 END) as sold_today", [today()->toDateString()])
            ->first();

        // Headline "Comenzi" / "Bilete" counts equal the list-page totals.
        $totalOrdersPage = Order::where('marketplace_client_id', $marketplaceId)->count();
        $totalTicketsPage = Ticket::where(function ($q) use ($marketplaceId) {
            $q->whereHas('order', fn ($q2) => $q2->where('marketplace_client_id', $marketplaceId))
                ->orWhereHas('ticketType.event', fn ($q2) => $q2->where('marketplace_client_id', $marketplaceId));
        })->count();

        // External import counts
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

        // 6. Organizers
        $orgStats = MarketplaceOrganizer::where('marketplace_client_id', $marketplaceId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active")
            ->first();

        // 7. Payouts
        $payoutStats = MarketplacePayout::where('marketplace_client_id', $marketplaceId)
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('pending','approved','processing') THEN amount ELSE 0 END), 0) as pending")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as completed")
            ->first();

        return [
            'total_events' => (int) $eventStats->total,
            'active_events' => (int) $eventStats->active,
            'total_customers' => (int) $customerStats->total,
            'guest_customers' => (int) $customerStats->guests,
            'registered_customers' => (int) $customerStats->registered,
            'total_orders' => $totalOrdersPage,
            'today_orders' => (int) $orderStats->today,
            'paid_orders' => $paidOrdersCount,
            'other_orders' => max(0, $totalOrdersPage - $paidOrdersCount),
            'total_incasari' => $orderRevenue + $serviceOrdersTotal,
            'order_revenue' => $orderRevenue,
            'service_revenue' => $serviceOrdersTotal,
            'commissions' => $commissions,
            'all_time_commissions' => Cache::remember("mp_alltime_comm_{$marketplaceId}", 3600, fn () =>
                BillingBreakdown::calculateMarketplaceCommission(
                    $marketplaceId, null, null, (float) ($marketplace->commission_rate ?? 5)
                )
            ),
            'service_orders_total' => $serviceOrdersTotal,
            'total_tickets' => (int) $ticketStats->sold,
            'today_tickets' => (int) $ticketStats->sold_today,
            'total_tickets_db' => $totalTicketsPage,
            'external_tickets' => $externalTickets,
            'external_orders' => $externalOrders,
            'external_customers' => $externalCustomers,
            'total_organizers' => (int) $orgStats->total,
            'active_organizers' => (int) $orgStats->active,
            'pending_payouts_value' => (float) $payoutStats->pending,
            'completed_payouts_value' => (float) $payoutStats->completed,
        ];
    }

    /**
     * Legacy-vs-Tixello breakdown that RECONCILES to the cards: for every
     * metric, imported + tixello == the card total. "imported" is the portion
     * coming from imported sources (external_import / legacy_import), computed
     * with the SAME scope/filters as the matching card; "tixello" is the rest
     * (card total − imported). Pass the already-computed cards() array.
     *
     * Returns ['imported'=>[...], 'tixello'=>[...], 'total'=>[...]] with keys
     * orders / tickets / customers / revenue.
     */
    public function breakdown(int $marketplaceId, array $cards): array
    {
        $imported = self::IMPORTED_SOURCES;
        $paidStatuses = ['paid', 'confirmed', 'completed'];

        // Orders — same scope as the total_orders card (marketplace_client_id,
        // all sources/statuses). Imported = orders from imported sources.
        $impOrders = Order::where('marketplace_client_id', $marketplaceId)
            ->whereIn('source', $imported)
            ->count();

        // Tickets — total_tickets_db counts every ticket; imported = tickets
        // whose order came from an imported source.
        $impTickets = Ticket::whereHas('order', fn ($q) => $q
                ->where('marketplace_client_id', $marketplaceId)
                ->whereIn('source', $imported))
            ->count();

        // Customers — imported-only: every order they have is from an imported
        // source (so they exist purely because of the migration).
        $impCustomers = DB::table('marketplace_customers as mc')
            ->where('mc.marketplace_client_id', $marketplaceId)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('orders')
                ->whereColumn('orders.marketplace_customer_id', 'mc.id')
                ->where('orders.marketplace_client_id', $marketplaceId)
                ->whereIn('orders.source', $imported))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('orders')
                ->whereColumn('orders.marketplace_customer_id', 'mc.id')
                ->where('orders.marketplace_client_id', $marketplaceId)
                ->whereNotIn('orders.source', $imported))
            ->count();

        // Revenue — the total_incasari card counts legacy_import but NOT
        // external_import, so the imported slice inside that total is legacy
        // only (external import revenue is deliberately not counted anywhere).
        $impRevenue = (float) Order::where(fn ($q) => $this->scopeMarketplaceOrders($q, $marketplaceId))
            ->where('source', 'legacy_import')
            ->whereIn('status', $paidStatuses)
            ->sum('total');

        $totalOrders = (int) ($cards['total_orders'] ?? 0);
        $totalTickets = (int) ($cards['total_tickets_db'] ?? 0);
        $totalCustomers = (int) ($cards['total_customers'] ?? 0);
        $totalRevenue = (float) ($cards['total_incasari'] ?? 0);

        return [
            'imported' => [
                'orders' => $impOrders,
                'tickets' => $impTickets,
                'customers' => $impCustomers,
                'revenue' => $impRevenue,
            ],
            'tixello' => [
                'orders' => max(0, $totalOrders - $impOrders),
                'tickets' => max(0, $totalTickets - $impTickets),
                'customers' => max(0, $totalCustomers - $impCustomers),
                'revenue' => $totalRevenue - $impRevenue,
            ],
            'total' => [
                'orders' => $totalOrders,
                'tickets' => $totalTickets,
                'customers' => $totalCustomers,
                'revenue' => $totalRevenue,
            ],
        ];
    }

    /**
     * Same order scope the dashboard uses: orders directly on the marketplace
     * client, or linked to one of its events by marketplace_event_id / event_id.
     */
    protected function scopeMarketplaceOrders($q, int $marketplaceId): void
    {
        $q->where('orders.marketplace_client_id', $marketplaceId)
            ->orWhereIn('orders.marketplace_event_id', fn ($sub) => $sub->select('id')->from('events')->where('marketplace_client_id', $marketplaceId))
            ->orWhereIn('orders.event_id', fn ($sub) => $sub->select('id')->from('events')->where('marketplace_client_id', $marketplaceId));
    }
}
