<?php

namespace App\Services\Chat;

use App\Models\Chat\ChatConversation;
use App\Models\Event;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceOrganizer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Builds the "visitor history" panel for the chat console: for an authenticated
 * customer, their ticket/order history grouped by event (upcoming highlighted);
 * for an organizer, their events. Best-effort + marketplace-scoped; tolerant of
 * the well-known schema drift (order totals, event dates/titles, ticket→event
 * resolution, two event tables).
 */
class ChatVisitorContextService
{
    private const SUCCESS_STATUSES = ['paid', 'confirmed', 'completed', 'partially_refunded'];

    /**
     * @return array{type:?string, name:?string, stats:array, upcoming:array, past:array, searching:bool}
     */
    public function forConversation(ChatConversation $conversation, ?string $search = null): array
    {
        $empty = ['type' => null, 'name' => null, 'stats' => [], 'upcoming' => [], 'past' => [], 'searching' => false];

        try {
            $type = $conversation->opener_type; // 'customer' | 'organizer' | ...
            $openerId = (int) $conversation->opener_id;
            $clientId = (int) $conversation->marketplace_client_id;
            if (!$openerId || !$clientId) {
                return $empty;
            }

            $search = $search !== null ? trim($search) : null;
            if ($search === '') {
                $search = null;
            }

            if ($type === 'customer') {
                return $this->forCustomer($openerId, $clientId, $search) ?: $empty;
            }
            if ($type === 'organizer') {
                return $this->forOrganizer($openerId, $clientId) ?: $empty;
            }
            return $empty;
        } catch (\Throwable $e) {
            Log::warning('Chat visitor context failed', ['conversation_id' => $conversation->id, 'error' => $e->getMessage()]);
            return $empty;
        }
    }

    private function forCustomer(int $customerId, int $clientId, ?string $search = null): ?array
    {
        $customer = MarketplaceCustomer::query()
            ->where('id', $customerId)
            ->where('marketplace_client_id', $clientId)
            ->first();
        if (!$customer) {
            return null;
        }

        $pg = DB::getDriverName() === 'pgsql';
        $likeOp = $pg ? 'ilike' : 'like';
        $titleExpr = $pg
            ? "COALESCE(e.title->>'en', e.title->>'ro', '')"
            : "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(e.title, '$.en')), JSON_UNQUOTE(JSON_EXTRACT(e.title, '$.ro')), '')";

        // Tickets grouped by (order, resolved event). Resolve event via
        // ticket_type.event_id → ticket.event_id → ticket.marketplace_event_id.
        $eventExpr = 'COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)';
        $q = DB::table('tickets as t')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->leftJoin('events as e', 'e.id', '=', DB::raw($eventExpr))
            ->where('o.marketplace_customer_id', $customerId)
            ->where('o.marketplace_client_id', $clientId);

        if ($search) {
            $term = '%' . $search . '%';
            $q->where(function ($w) use ($term, $likeOp, $titleExpr) {
                $w->where('o.order_number', $likeOp, $term)
                    ->orWhere('t.code', $likeOp, $term)
                    ->orWhereRaw("$titleExpr $likeOp ?", [$term]);
            });
        }

        $groups = $q->selectRaw('t.order_id, ' . $eventExpr . ' as event_id, count(*) as cnt')
            ->groupBy('t.order_id')
            ->groupByRaw($eventExpr)
            ->limit(400)
            ->get();

        $orderIds = $groups->pluck('order_id')->filter()->unique()->all();
        $eventIds = $groups->pluck('event_id')->filter()->unique()->all();
        $orders = Order::query()->whereIn('id', $orderIds)->get()->keyBy('id');
        $events = Event::query()->whereIn('id', $eventIds)->get()->keyBy('id');

        // Group by event → { event, upcoming, total tickets, orders: [...] }.
        $byEvent = [];
        foreach ($groups as $g) {
            $order = $orders->get($g->order_id);
            if (!$order) {
                continue;
            }
            $event = $g->event_id ? $events->get($g->event_id) : null;
            $key = $g->event_id ? ('e' . $g->event_id) : 'none';
            if (!isset($byEvent[$key])) {
                $byEvent[$key] = [
                    'event_title' => $event ? $this->eventTitle($event) : 'Fără eveniment',
                    'event_date' => $event ? $this->eventDateLabel($event) : null,
                    'upcoming' => $event ? $this->isUpcoming($event) : false,
                    'total' => 0,
                    'orders' => [],
                ];
            }
            $byEvent[$key]['total'] += (int) $g->cnt;
            $byEvent[$key]['orders'][] = [
                'order_number' => $order->order_number ?? ('#' . $order->id),
                'order_date' => optional($order->created_at)->format('d.m.Y'),
                'payment' => $this->paymentLabel($order),
                'tickets' => (int) $g->cnt,
            ];
        }

        $upcoming = [];
        $past = [];
        foreach ($byEvent as $ev) {
            usort($ev['orders'], fn ($a, $b) => strcmp($b['order_date'], $a['order_date']));
            if ($ev['upcoming']) {
                $upcoming[] = $ev;
            } else {
                $past[] = $ev;
            }
        }

        $stats = [
            'orders' => count($orderIds),
            'tickets' => (int) $groups->sum('cnt'),
            'spent' => $this->totalSpent($customerId, $clientId),
        ];

        return [
            'type' => 'customer',
            'name' => trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: ($customer->email ?? 'Client'),
            'stats' => $stats,
            'upcoming' => array_slice($upcoming, 0, 40),
            'past' => array_slice($past, 0, 60),
            'searching' => (bool) $search,
        ];
    }

    private function forOrganizer(int $organizerId, int $clientId): ?array
    {
        $organizer = MarketplaceOrganizer::query()
            ->where('id', $organizerId)
            ->where('marketplace_client_id', $clientId)
            ->first();
        if (!$organizer) {
            return null;
        }

        // Live events belong to the `events` table (not the legacy marketplace_events).
        $events = Event::query()
            ->where('marketplace_organizer_id', $organizerId)
            ->where('marketplace_client_id', $clientId)
            ->limit(150)
            ->get();

        // Sold-ticket counts per event (success orders only).
        $counts = DB::table('tickets as t')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->leftJoin('ticket_types as tt', 'tt.id', '=', 't.ticket_type_id')
            ->where('o.marketplace_organizer_id', $organizerId)
            ->whereIn('o.status', self::SUCCESS_STATUSES)
            ->selectRaw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id) as event_id, count(*) as cnt')
            ->groupByRaw('COALESCE(tt.event_id, t.event_id, t.marketplace_event_id)')
            ->get()
            ->keyBy('event_id');

        $upcoming = [];
        $past = [];
        foreach ($events as $event) {
            $row = [
                'event_title' => $this->eventTitle($event),
                'event_date' => $this->eventDateLabel($event),
                'tickets_sold' => (int) ($counts->get($event->id)->cnt ?? 0),
                'status' => $event->status ?? null,
            ];
            if ($this->isUpcoming($event)) {
                $upcoming[] = $row;
            } else {
                $past[] = $row;
            }
        }

        return [
            'type' => 'organizer',
            'name' => $organizer->name ?? ($organizer->email ?? 'Organizator'),
            'stats' => [
                'events' => $events->count(),
                'tickets_sold' => (int) $counts->sum('cnt'),
            ],
            'upcoming' => array_slice($upcoming, 0, 30),
            'past' => array_slice($past, 0, 30),
            'searching' => false,
        ];
    }

    // -------- Helpers (drift-tolerant) --------

    private function eventTitle(Event $event): string
    {
        try {
            $t = method_exists($event, 'getTranslation') ? $event->getTranslation('title', app()->getLocale()) : $event->title;
            if (is_array($t)) {
                $t = $t['ro'] ?? $t['en'] ?? reset($t) ?: null;
            }
            return is_string($t) && $t !== '' ? $t : ('Eveniment #' . $event->id);
        } catch (\Throwable $e) {
            return 'Eveniment #' . $event->id;
        }
    }

    private function eventDateLabel(Event $event): ?string
    {
        try {
            if (method_exists($event, 'displayDateLabel')) {
                $l = $event->displayDateLabel();
                if (is_string($l) && $l !== '') {
                    return $l;
                }
            }
            $d = $event->start_date ?? $event->event_date ?? null;
            return $d ? \Illuminate\Support\Carbon::parse($d)->format('d.m.Y') : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function isUpcoming(Event $event): bool
    {
        try {
            if (method_exists($event, 'isUpcoming')) {
                return (bool) $event->isUpcoming();
            }
            $d = $event->start_date ?? $event->event_date ?? null;
            return $d ? \Illuminate\Support\Carbon::parse($d)->isFuture() : false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function totalSpent(int $customerId, int $clientId): ?string
    {
        try {
            $cents = (int) DB::table('orders')
                ->where('marketplace_customer_id', $customerId)
                ->where('marketplace_client_id', $clientId)
                ->whereIn('status', self::SUCCESS_STATUSES)
                ->selectRaw('COALESCE(SUM(COALESCE(NULLIF(total_cents,0), ROUND(total*100), 0)),0) as c')
                ->value('c');
            return $cents > 0 ? number_format($cents / 100, 2) . ' lei' : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function paymentLabel(Order $order): string
    {
        $processor = strtolower((string) ($order->payment_processor ?? ''));
        $meta = is_array($order->meta ?? null) ? $order->meta : [];

        $map = [
            'netopia' => 'Card (Netopia)', 'payment-netopia' => 'Card (Netopia)',
            'stripe' => 'Card (Stripe)', 'payment-stripe' => 'Card (Stripe)',
            'paypal' => 'PayPal',
            'cash' => 'Numerar', 'pos_cash' => 'Numerar',
            'bank_transfer' => 'Transfer bancar',
        ];
        if (isset($map[$processor])) {
            return $map[$processor];
        }
        $m = $meta['payment_method'] ?? $meta['method'] ?? null;
        if (is_string($m) && $m !== '') {
            return ucfirst($m);
        }
        return $processor !== '' ? ucfirst($processor) : '—';
    }
}
