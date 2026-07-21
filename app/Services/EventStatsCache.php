<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for per-event aggregate statistics used by the
 * organizer scan-app dashboard and the mobile app.
 *
 * Before this service, every render of /panou or /vanzare for a single
 * event ran:
 *   - 1 COUNT for total_tickets_sold
 *   - 1 GROUP BY COUNT + 1 SUM(discount_amount) for total_revenue
 *   - N COUNTs for per-ticket-type sold (one per active type)
 *   - N COUNTs for per-ticket-type checked_in
 * For a 5-type event that's ~12 queries per render. Eight operators on the
 * same event hitting the dashboard during a check-in shift = ~100 queries/min
 * for data that's identical between requests until a check-in happens.
 *
 * Strategy:
 *  - Compute all aggregates in ONE service call and cache them in Redis
 *    under a single key 'event_stats:v1:{event_id}' with TTL 60s.
 *  - Invalidate proactively from:
 *      - OrderObserver (status -> paid|confirmed|completed)
 *      - TicketObserver (status changes, checked_in_at changes, deletions)
 *    so callers see fresh numbers within the same request after a
 *    check-in or sale.
 *  - Cache failures (Redis down, key collision, exception in compute) are
 *    swallowed and the live computation is returned, so this layer can
 *    NEVER take down the dashboard. Live site safety first.
 *
 * Cache shape:
 *   [
 *     'total_tickets_sold' => 218,
 *     'total_revenue'      => 12502.0,
 *     'per_ticket_type'    => [
 *         {tt_id} => ['sold' => 42, 'checked_in' => 15],
 *         ...
 *     ],
 *     'computed_at'        => unix timestamp
 *   ]
 */
class EventStatsCache
{
    public const VERSION = 'v1';
    public const TTL = 60;

    public static function key(int $eventId): string
    {
        return 'event_stats:' . self::VERSION . ':' . $eventId;
    }

    /**
     * Get the full stats hash for an event. Reads from cache; on miss
     * computes + writes. On ANY exception falls back to live computation
     * so the call site never breaks.
     */
    public static function get(int $eventId): array
    {
        try {
            return Cache::remember(self::key($eventId), self::TTL, function () use ($eventId) {
                return self::compute($eventId);
            });
        } catch (\Throwable $e) {
            Log::warning('EventStatsCache::get failed; computing live', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            try {
                return self::compute($eventId);
            } catch (\Throwable $e2) {
                Log::error('EventStatsCache::compute also failed', [
                    'event_id' => $eventId,
                    'error' => $e2->getMessage(),
                ]);
                return self::emptyShape();
            }
        }
    }

    /**
     * Invalidate stats for an event. Called from observers on every state
     * change that could affect the numbers (Order paid, Ticket status
     * changed, Ticket checked in, etc.).
     *
     * Idempotent and swallows errors — invalidation must NEVER throw out
     * of the calling observer or it could break order processing.
     */
    public static function forget(?int $eventId): void
    {
        if (!$eventId) return;
        try {
            Cache::forget(self::key($eventId));
        } catch (\Throwable $e) {
            // Silent — worst case we serve stale stats for up to 60s.
        }
    }

    /**
     * Live computation (the OLD code path). This is what would have run
     * unconditionally before the cache layer was introduced.
     *
     * The query patterns are identical to:
     *   - Event::getTotalTicketsSoldAttribute
     *   - Event::getTotalRevenueAttribute
     *   - The per-ticket-type loop in
     *     EventsController::show()
     * so we don't change any business logic — only where the result is
     * stored.
     */
    public static function compute(int $eventId): array
    {
        $event = Event::with('ticketTypes')->find($eventId);
        if (!$event) return self::emptyShape();

        $totalSold = (int) Ticket::where('event_id', $eventId)
            ->whereIn('status', ['valid', 'used'])
            ->count();

        // Invitations have no order_id (issued directly, free) — same
        // convention as SalesBreakdownService. Everything else that's
        // valid/used was bought through an order.
        $invitations = (int) Ticket::where('event_id', $eventId)
            ->whereIn('status', ['valid', 'used'])
            ->whereNull('order_id')
            ->count();
        $ticketsPaid = max(0, $totalSold - $invitations);

        $ticketTypes = $event->ticketTypes;
        $ttIds = $ticketTypes->pluck('id')->toArray();

        $perType = [];
        if (!empty($ttIds)) {
            $soldByType = Ticket::whereIn('ticket_type_id', $ttIds)
                ->where('event_id', $eventId)
                ->whereIn('status', ['valid', 'used'])
                ->groupBy('ticket_type_id')
                ->selectRaw('ticket_type_id, COUNT(*) as cnt')
                ->pluck('cnt', 'ticket_type_id');

            $checkedByType = Ticket::whereIn('ticket_type_id', $ttIds)
                ->where('event_id', $eventId)
                ->whereIn('status', ['valid', 'used'])
                ->whereNotNull('checked_in_at')
                ->groupBy('ticket_type_id')
                ->selectRaw('ticket_type_id, COUNT(*) as cnt')
                ->pluck('cnt', 'ticket_type_id');

            foreach ($ttIds as $id) {
                $perType[$id] = [
                    'sold' => (int) ($soldByType[$id] ?? 0),
                    'checked_in' => (int) ($checkedByType[$id] ?? 0),
                ];
            }
        }

        $gross = 0.0;
        foreach ($ticketTypes as $tt) {
            $count = $perType[$tt->id]['sold'] ?? 0;
            if ($count === 0) continue;
            $priceCents = ((int) ($tt->sale_price_cents ?? 0)) > 0
                ? (int) $tt->sale_price_cents
                : (int) ($tt->price_cents ?? 0);
            $gross += $count * ($priceCents / 100);
        }

        $discount = (float) Order::where('event_id', $eventId)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->sum('discount_amount');

        $revenue = round(max(0.0, $gross - $discount), 2);

        return [
            'total_tickets_sold' => $totalSold,
            'total_tickets_paid' => $ticketsPaid,
            'total_invitations'  => $invitations,
            'total_revenue'      => $revenue,
            'per_ticket_type'    => $perType,
            'computed_at'        => time(),
        ];
    }

    protected static function emptyShape(): array
    {
        return [
            'total_tickets_sold' => 0,
            'total_tickets_paid' => 0,
            'total_invitations'  => 0,
            'total_revenue'      => 0.0,
            'per_ticket_type'    => [],
            'computed_at'        => time(),
        ];
    }
}
