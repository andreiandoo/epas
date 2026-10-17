<?php

namespace App\Services\Marketplace;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Raport vânzări pe zi" of the marketplace dashboard: per-event sales of one
 * day + each event's all-time totals. Shared by the dashboard page (today's
 * cards), the lazy report component and the `dashboard:warm` cron, so all of
 * them read the same cache slot.
 */
class DailySalesReportService
{
    /**
     * Per-event sales breakdown for a single day. Returns a list of events
     * that had at least one paid/confirmed/completed order on that day,
     * each row carrying same-day + all-time aggregates (orders, tickets,
     * revenue, commission).
     *
     * Caller is responsible for super-admin gating and date clamping
     * (the date arrives validated from the caller).
     */
    /**
     * Per-day report rows, cached under the SAME key used by the daily report
     * table so the month chart and the table share one computation and can
     * never disagree for a given day. Today keeps a short TTL (still moving);
     * past days are immutable → cache 24h.
     */
    public function rowsCached(int $marketplaceId, string $date): array
    {
        $today = Carbon::now('Europe/Bucharest')->format('Y-m-d');
        $ttl = ($date >= $today) ? 60 : 86400;

        return Cache::remember(
            "mp_dash_daily_evt_v9_{$marketplaceId}_{$date}",
            $ttl,
            fn () => $this->compute($marketplaceId, $date)
        );
    }

    /**
     * Distribute each paid order's total across the events it contains,
     * proportional to the FACE value (ticket.price) of that event's tickets in
     * the order. Single-event orders keep 100% of their total (fees/discounts
     * included); multi-event carts are split by real ticket value — never a
     * blind total ÷ count. Σ over events = Σ order.total = money collected.
     * Scoped by order created_at, valid/used tickets, paid non-excluded orders.
     *
     * @return array<int, array{revenue: float, tickets: int, orders: int}>
     */
    private function allocateDayRevenue(int $marketplaceId, Carbon $start, Carbon $end): array
    {
        $rows = DB::table('tickets as t')
            ->join('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->join('events as e', 'e.id', '=', 'tt.event_id')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('e.marketplace_client_id', $marketplaceId)
            ->whereIn('t.status', ['valid', 'used'])
            ->whereIn('o.status', ['paid', 'confirmed', 'completed'])
            ->whereNotIn('o.source', ['test_order', 'pos_test', 'external_import', 'legacy_import'])
            // Test POS nu intra in statistici
            ->whereRaw(\App\Support\TestPos::notTypeSql('tt'))
            ->whereBetween('o.created_at', [$start, $end])
            ->select('t.order_id', 't.price', 'e.id as eid', 'o.total as order_total')
            ->get();

        $out = [];
        foreach ($rows->groupBy('order_id') as $orderId => $tks) {
            $orderTotal = (float) $tks->first()->order_total;
            $sumFace = (float) $tks->sum('price');
            $count = $tks->count();
            foreach ($tks->groupBy('eid') as $eid => $group) {
                $eid = (int) $eid;
                // Split by ticket face value; fall back to ticket count only if
                // the whole order is zero-value (all comp/free tickets).
                $share = $sumFace > 0
                    ? ((float) $group->sum('price') / $sumFace)
                    : ($count > 0 ? $group->count() / $count : 0);
                if (!isset($out[$eid])) {
                    $out[$eid] = ['revenue' => 0.0, 'tickets' => 0, 'orders' => []];
                }
                $out[$eid]['revenue'] += $orderTotal * $share;
                $out[$eid]['tickets'] += $group->count();
                $out[$eid]['orders'][$orderId] = true;
            }
        }
        foreach ($out as &$d) {
            $d['revenue'] = round($d['revenue'], 2);
            $d['orders'] = count($d['orders']);
        }
        unset($d);

        return $out;
    }

    /**
     * All-time revenue + commission of one event (unbounded SalesBreakdownService
     * build — the heaviest call of the daily report). Cached per event, so it is
     * shared by every day the event sold on and by today's cards; the warm cron
     * refreshes it for today's events.
     *
     * @return array{revenue: float, commission: float}
     */
    private function eventAllTimeTotals(Event $event, bool $refresh = false): array
    {
        $key = "mp_evt_alltime_v1_{$event->id}";
        $compute = function () use ($event) {
            $bd = app(\App\Services\Marketplace\SalesBreakdownService::class)->build($event);
            $kept = (float) ($bd['total_commission_kept_from_refunds'] ?? 0);

            return [
                'revenue' => (float) $bd['total_revenue'] + $kept,
                'commission' => (float) $bd['total_commission'] + $kept,
            ];
        };

        if ($refresh) {
            $totals = $compute();
            Cache::put($key, $totals, 1200);

            return $totals;
        }

        return Cache::remember($key, 600, $compute);
    }

    public function compute(int $marketplaceId, string $date, bool $refreshAllTime = false): array
    {
        $tz = 'Europe/Bucharest';
        $dayStart = Carbon::createFromFormat('Y-m-d', $date, $tz)->startOfDay()->utc();
        $dayEnd = Carbon::createFromFormat('Y-m-d', $date, $tz)->endOfDay()->utc();
        $paidStatuses = ['paid', 'confirmed', 'completed'];

        // Step 1 — per-event day figures via per-ticket allocation: each order's
        // total is distributed across its events by the face value of that
        // event's tickets (see allocateDayRevenue). Discovers events through
        // their tickets, so multi-event carts (no single event_id on the order)
        // are attributed per event and Σ = money collected. No separate bucket.
        $eventSub = function ($sub) use ($marketplaceId) {
            $sub->select('id')->from('events')->where('marketplace_client_id', $marketplaceId);
        };
        $orderScope = function ($q) use ($marketplaceId, $eventSub) {
            $q->where('orders.marketplace_client_id', $marketplaceId)
                ->orWhereIn('orders.marketplace_event_id', $eventSub)
                ->orWhereIn('orders.event_id', $eventSub);
        };

        $dayAlloc = $this->allocateDayRevenue($marketplaceId, $dayStart, $dayEnd);
        if (empty($dayAlloc)) {
            return [];
        }
        $eventIds = array_keys($dayAlloc);

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
            // Test POS nu intra in statistici
            ->whereRaw(\App\Support\TestPos::notTypeSql('ticket_types'))
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
        foreach ($eventIds as $eid) {
            $eid = (int) $eid;
            $event = $events->get($eid);
            if (!$event) {
                continue; // Ticket pointing at a deleted event — skip.
            }
            $alloc = $dayAlloc[$eid];

            // sales_day = money collected, attributed to this event by ticket
            // face value (see allocateDayRevenue) — a package counts at the
            // price paid, and multi-event carts are split by real ticket value.
            // commission_day = AmBilet's actual cut via SalesBreakdownService
            // (floor/leisure/per-type aware — orders.commission_amount is 0 for
            // POS/leisure).
            $dayBd = $salesService->build($event, $dayStart, $dayEnd, exactBounds: true);
            $dayKept = (float) ($dayBd['total_commission_kept_from_refunds'] ?? 0);
            $dayRevenue = (float) $alloc['revenue'];
            $dayCommission = (float) $dayBd['total_commission'] + $dayKept;

            $total = $totalRows->get((string) $eid) ?? $totalRows->get($eid);
            $totalOrders = $total ? (int) $total->orders_count : 0;

            // All-time totals (no period bounds). Lifetime revenue uses the
            // service's per-event ticket valuation (an all-time order.total
            // allocation would have to scan the whole history).
            ['revenue' => $totalRevenue, 'commission' => $totalCommission] = $this->eventAllTimeTotals($event, $refreshAllTime);

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
                'orders_day' => (int) $alloc['orders'],
                'tickets_day' => (int) $alloc['tickets'],
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
