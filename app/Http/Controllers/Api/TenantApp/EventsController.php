<?php

namespace App\Http\Controllers\Api\TenantApp;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant-scoped events. Every query is filtered by the resolved tenant_id, so
 * a tenant only ever sees its own inventory (independent of the marketplace).
 */
class EventsController extends BaseController
{
    // NB: real paid orders carry status='paid' while payment_status stays
    // 'pending' — so revenue/"paid" is keyed on Order.status, NOT payment_status.
    private const PAID_ORDER_STATUSES = ['paid', 'completed', 'confirmed', 'free'];
    private const DEAD_TICKET_STATES = ['cancelled', 'refunded'];

    public function index(Request $request): JsonResponse
    {
        $tid = $this->tenantId($request);
        $perPage = min((int) $request->integer('per_page', 50) ?: 50, 100);

        $events = Event::where('tenant_id', $tid)
            ->withCount([
                'tickets as sold_count' => fn ($q) => $q->whereNotIn('status', self::DEAD_TICKET_STATES),
                'tickets as entered_count' => fn ($q) => $q->whereNotNull('checked_in_at'),
            ])
            ->orderByDesc('event_date')
            ->paginate($perPage);

        $ids = collect($events->items())->pluck('id');
        $revenue = Order::whereIn('event_id', $ids)
            ->where('tenant_id', $tid)
            ->whereIn('status', self::PAID_ORDER_STATUSES)
            ->groupBy('event_id')
            ->selectRaw('event_id, SUM(total) as rev')
            ->pluck('rev', 'event_id');

        return $this->paginated($events, fn (Event $e) => $this->summary($e, (float) ($revenue[$e->id] ?? 0)));
    }

    public function show(Request $request, int $eventId): JsonResponse
    {
        $tid = $this->tenantId($request);
        $event = Event::where('tenant_id', $tid)->with('venue')->find($eventId);
        if (! $event) {
            return $this->error('Eveniment inexistent.', 404);
        }

        $ticketTypes = $event->ticketTypes()
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'price' => (float) $t->price,
                'capacity' => $t->capacity,
                'quota_sold' => $t->quota_sold,
                'available' => $t->capacity !== null ? max(0, (int) $t->capacity - (int) $t->quota_sold) : null,
                'is_entry_ticket' => (bool) $t->is_entry_ticket,
                'is_active' => (bool) $t->is_active,
                'color' => $t->color ?? null,
            ]);

        return $this->success([
            'event' => array_merge($this->summary($event, $this->eventRevenue($tid, $event->id)), [
                'venue' => $event->venue?->name,
                'event_date' => $event->event_date,
                'start_time' => $event->start_time,
                'status' => $event->status,
                'is_published' => (bool) $event->is_published,
            ]),
            'ticket_types' => $ticketTypes,
        ]);
    }

    public function statistics(Request $request, int $eventId): JsonResponse
    {
        $tid = $this->tenantId($request);
        $event = Event::where('tenant_id', $tid)->find($eventId);
        if (! $event) {
            return $this->error('Eveniment inexistent.', 404);
        }

        $capacity = (int) $event->total_capacity;
        $sold = Ticket::where('event_id', $event->id)
            ->where('tenant_id', $tid)
            ->whereNotIn('status', self::DEAD_TICKET_STATES)
            ->count();
        $entered = Ticket::where('event_id', $event->id)
            ->where('tenant_id', $tid)
            ->whereNotNull('checked_in_at')
            ->count();
        $revenue = $this->eventRevenue($tid, $event->id);

        $doorCount = Order::where('event_id', $event->id)
            ->where('tenant_id', $tid)
            ->whereIn('status', self::PAID_ORDER_STATUSES)
            ->where('source', 'pos_app')
            ->count();

        return $this->success([
            'entered' => $entered,
            'sold' => $sold,
            'revenue' => $revenue,
            'available' => max(0, $capacity - $sold),
            'capacity' => $capacity,
            'check_in_rate' => $sold > 0 ? (int) round($entered / $sold * 100) : 0,
            'door_count' => $doorCount,
        ]);
    }

    private function eventRevenue(int $tenantId, int $eventId): float
    {
        return (float) Order::where('event_id', $eventId)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::PAID_ORDER_STATUSES)
            ->sum('total');
    }

    /** @return array<string,mixed> */
    private function summary(Event $event, float $revenue): array
    {
        $capacity = (int) $event->total_capacity;
        $sold = (int) ($event->sold_count ?? 0);

        return [
            'id' => $event->id,
            'name' => $event->name,
            'venue' => $event->venue?->name ?? null,
            'starts_at' => $event->event_date,
            'status' => $event->status,
            'tickets_sold' => $sold,
            'entered' => (int) ($event->entered_count ?? 0),
            'capacity' => $capacity,
            'revenue' => $revenue,
        ];
    }
}
