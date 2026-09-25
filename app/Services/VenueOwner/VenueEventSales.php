<?php

namespace App\Services\VenueOwner;

use App\Models\Ticket;
use App\Services\Marketplace\SalesBreakdownService;
use Illuminate\Support\Collection;

/**
 * Tickets sold, check-ins and revenue per event for the venue-owner pages
 * (web shell /venue/* and the mobile app), counted from the tickets actually
 * sold — valid / used tickets on a paid order — like the organizer reports.
 *
 * Replaces the old `quota_sold × price_cents` estimate: quota_sold is a stock
 * counter that marketplace checkout only moves for capped ticket types, so
 * events sold online by another organizer showed 0 revenue.
 */
class VenueEventSales
{
    /** Smoke-test sales never count. */
    public const TEST_SOURCES = ['pos_test', 'test_order'];

    /** Sold elsewhere and imported: counted as tickets, not as revenue here. */
    public const NO_REVENUE_SOURCES = ['external_import'];

    /**
     * @param  iterable<int>  $eventIds
     * @return Collection<int, object{tickets_sold:int, checked_in_count:int, revenue:float}>
     */
    public static function forEvents(iterable $eventIds): Collection
    {
        $ids = collect($eventIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $noRevenue = "'" . implode("','", self::NO_REVENUE_SOURCES) . "'";

        $rows = self::baseQuery()
            ->whereIn('tickets.event_id', $ids)
            ->selectRaw("tickets.event_id,
                count(*) as tickets_sold,
                count(tickets.checked_in_at) as checked_in_count,
                SUM(CASE WHEN orders.source IN ($noRevenue) THEN 0
                    ELSE COALESCE(tickets.price, ticket_types.price_cents / 100.0, 0) END) as revenue")
            ->groupBy('tickets.event_id')
            ->get()
            ->keyBy('event_id');

        return $ids->mapWithKeys(function ($id) use ($rows) {
            $r = $rows->get($id);
            return [$id => (object) [
                'tickets_sold' => $r ? (int) $r->tickets_sold : 0,
                'checked_in_count' => $r ? (int) $r->checked_in_count : 0,
                'revenue' => $r ? round((float) $r->revenue, 2) : 0.0,
            ]];
        });
    }

    /**
     * Per ticket type for one event, keyed by ticket_type_id.
     *
     * @return Collection<int, object{sold:int, checked_in:int}>
     */
    public static function perTicketType(int $eventId): Collection
    {
        return self::baseQuery()
            ->where('tickets.event_id', $eventId)
            ->selectRaw('tickets.ticket_type_id, count(*) as sold, count(tickets.checked_in_at) as checked_in')
            ->groupBy('tickets.ticket_type_id')
            ->get()
            ->keyBy('ticket_type_id');
    }

    protected static function baseQuery()
    {
        return Ticket::query()
            ->join('orders', 'orders.id', '=', 'tickets.order_id')
            ->leftJoin('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
            ->whereIn('tickets.status', ['valid', 'used'])
            ->whereIn('orders.status', SalesBreakdownService::PAID_ORDER_STATUSES)
            ->where(fn ($q) => $q->whereNull('orders.source')->orWhereNotIn('orders.source', self::TEST_SOURCES));
    }
}
