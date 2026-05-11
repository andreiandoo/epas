<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer\Leisure;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\Order;
use App\Models\TicketType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Leisure venue endpoints (F1 — minim viabil).
 *
 * Activate doar pentru evenimente cu display_template === 'leisure_venue'.
 * Filtreaza pe marketplace_client_id al organizatorului autenticat.
 *
 * Modelul fiscal: organizatorul poate avea 2 societati emitente
 * (primary = company_*, secondary = secondary_company_*). Fiecare TicketType
 * are issuing_company ('primary'|'secondary'|NULL=primary).
 */
class LeisureController extends BaseController
{
    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/config
     *
     * Returneaza configurarea leisure: ticket types cu societatea emitenta efectiva
     * + datele celor 2 societati ale organizatorului (primary intotdeauna,
     * secondary doar daca has_secondary_issuer=true).
     */
    public function config(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();

        if (!$eventModel) {
            return $this->error('Event not found', 404);
        }

        if (($eventModel->display_template ?? 'standard') !== 'leisure_venue') {
            return $this->error('Event is not a leisure venue', 422);
        }

        // Organizatorul evenimentului (in cazul multi-organizer marketplace,
        // poate diferi de organizer-ul autentificat — folosim cel al evenimentului
        // pentru datele juridice).
        $eventOrganizer = $eventModel->marketplace_organizer_id
            ? MarketplaceOrganizer::find($eventModel->marketplace_organizer_id)
            : $organizer;

        $ticketTypes = TicketType::query()
            ->where('event_id', $eventModel->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $issuers = [
            'primary' => $eventOrganizer?->getIssuerData('primary') ?? [],
        ];
        if ($eventOrganizer?->has_secondary_issuer) {
            $issuers['secondary'] = $eventOrganizer->getIssuerData('secondary');
        }

        return $this->success([
            'event' => [
                'id' => $eventModel->id,
                'title' => $this->localizedTitle($eventModel),
                'display_template' => $eventModel->display_template,
            ],
            'organizer' => $eventOrganizer ? [
                'id' => $eventOrganizer->id,
                'name' => $eventOrganizer->name,
                'has_secondary_issuer' => (bool) $eventOrganizer->has_secondary_issuer,
            ] : null,
            'issuers' => $issuers,
            'ticket_types' => $ticketTypes->map(function (TicketType $tt) {
                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'sku' => $tt->sku,
                    'service_category' => $tt->effective_service_category,
                    'is_parking' => (bool) $tt->is_parking,
                    'requires_vehicle_info' => (bool) $tt->requires_vehicle_info,
                    'daily_capacity' => $tt->daily_capacity,
                    'ticket_group' => $tt->ticket_group,
                    'issuing_company' => $tt->effective_issuing_company,
                    'issuing_explicit' => (bool) $tt->issuing_company,
                ];
            })->all(),
        ]);
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/reports/by-issuer?from=&to=
     *
     * Break-down vanzari pe societate emitenta a organizatorului (primary | secondary)
     * pentru perioada specificata.
     */
    public function reportsByIssuer(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();

        if (!$eventModel) {
            return $this->error('Event not found', 404);
        }

        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : Carbon::today()->subDays(30)->startOfDay();
        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : Carbon::today()->endOfDay();

        $eventOrganizer = $eventModel->marketplace_organizer_id
            ? MarketplaceOrganizer::find($eventModel->marketplace_organizer_id)
            : $organizer;

        $orders = Order::query()
            ->where('event_id', $eventModel->id)
            ->whereIn('status', ['completed', 'paid'])
            ->whereBetween('paid_at', [$from, $to])
            ->with([
                'tickets:id,order_id,ticket_type_id,price,status',
                'tickets.ticketType:id,name,issuing_company,service_category',
            ])
            ->get(['id', 'event_id', 'paid_at', 'status', 'currency']);

        $buckets = [
            'primary' => $this->emptyBucket(),
            'secondary' => $this->emptyBucket(),
        ];

        foreach ($orders as $order) {
            foreach ($order->tickets as $ticket) {
                if (in_array($ticket->status, ['cancelled', 'refunded'], true)) {
                    continue;
                }

                $tt = $ticket->ticketType;
                $company = $tt?->effective_issuing_company ?: 'primary';
                if (!isset($buckets[$company])) {
                    $buckets[$company] = $this->emptyBucket();
                }

                $bucket = &$buckets[$company];
                $bucket['orders'][$order->id] = true;
                $bucket['tickets_count']++;
                $bucket['subtotal'] += (float) ($ticket->price ?? 0);

                $cat = $tt?->effective_service_category ?? 'access';
                if (!isset($bucket['by_category'][$cat])) {
                    $bucket['by_category'][$cat] = ['count' => 0, 'subtotal' => 0.0];
                }
                $bucket['by_category'][$cat]['count']++;
                $bucket['by_category'][$cat]['subtotal'] += (float) ($ticket->price ?? 0);
                unset($bucket);
            }
        }

        $rows = [];
        foreach ($buckets as $company => $b) {
            // Skip secondary daca nu are date si nu e activat pe organizer
            if ($company === 'secondary'
                && $b['tickets_count'] === 0
                && !$eventOrganizer?->has_secondary_issuer) {
                continue;
            }

            $rows[] = [
                'company' => $company,
                'issuer' => $eventOrganizer?->getIssuerData($company),
                'orders_count' => count($b['orders']),
                'tickets_count' => $b['tickets_count'],
                'subtotal' => round($b['subtotal'], 2),
                'by_category' => $b['by_category'],
            ];
        }

        return $this->success([
            'event_id' => $eventModel->id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'currency' => $orders->first()?->currency ?? 'RON',
            'rows' => $rows,
        ]);
    }

    /**
     * PUT /marketplace-client/organizer/events/{event}/leisure/venue-config
     *
     * Self-service editor pentru continutul venue_config (hero, FAQ, attractions,
     * trails, etc.). Organizatorul rezervatiei poate modifica fara sa intre in
     * Filament admin Tixello.
     *
     * Cheile array (faqs, trails, etc.) sunt INLOCUITE complet ca sa permita
     * stergerea/reordonarea elementelor.
     */
    public function updateVenueConfig(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$eventModel) {
            return $this->error('Event not found or access denied', 404);
        }

        if (($eventModel->display_template ?? 'standard') !== 'leisure_venue') {
            return $this->error('Event is not a leisure venue', 422);
        }

        $validated = $request->validate([
            'venue_config' => 'required|array',
        ]);

        $current = $eventModel->venue_config ?? [];
        $incoming = $validated['venue_config'];

        $listKeys = [
            'hero_badges', 'amenities', 'closed_dates', 'pricing_rules', 'seasons',
            'attractions', 'stats_highlights', 'flora', 'trails', 'getting_there',
            'quick_stats', 'gallery', 'videos', 'nearby_hotels',
            'faqs', 'bundle_discounts',
            'sections', 'section_order',
        ];

        $merged = $current;
        foreach ($incoming as $key => $value) {
            if (in_array($key, $listKeys, true)) {
                $merged[$key] = $value;
            } elseif (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = array_replace_recursive($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        $eventModel->venue_config = $merged;
        $eventModel->save();

        return $this->success([
            'venue_config' => $merged,
            'updated_keys' => array_keys($incoming),
        ], 'Conținut actualizat cu succes');
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/participants
     *     ?from=&to=&search=&page=&per_page=
     *
     * Listă tickete vândute (= participanți) cu filtre dată + search + paginare.
     */
    public function participants(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();

        if (!$eventModel) {
            return $this->error('Event not found', 404);
        }

        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:200',
            'page' => 'nullable|integer|min:1',
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : Carbon::today()->subDays(30)->startOfDay();
        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : Carbon::today()->endOfDay();
        $perPage = (int) ($validated['per_page'] ?? 50);
        $search = $validated['search'] ?? null;

        $query = \App\Models\Ticket::query()
            ->whereHas('order', fn ($q) => $q
                ->where('event_id', $eventModel->id)
                ->whereIn('status', ['completed', 'paid'])
                ->whereBetween('paid_at', [$from, $to]))
            ->with([
                'order:id,customer_name,customer_email,customer_phone,paid_at,status,total',
                'ticketType:id,name,service_category,issuing_company,valid_date',
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                  ->orWhere('barcode', 'ilike', "%{$search}%")
                  ->orWhereHas('order', fn ($oq) => $oq
                      ->where('customer_name', 'ilike', "%{$search}%")
                      ->orWhere('customer_email', 'ilike', "%{$search}%"));
            });
        }

        $paginator = $query->orderByDesc('id')->paginate($perPage);

        // Aggregare stats (total, checked-in, no-show) — query separat pe acelasi filter
        $statsQuery = \App\Models\Ticket::query()
            ->whereHas('order', fn ($q) => $q
                ->where('event_id', $eventModel->id)
                ->whereIn('status', ['completed', 'paid'])
                ->whereBetween('paid_at', [$from, $to]));
        $totalTickets = (clone $statsQuery)->count();
        $checkedIn = (clone $statsQuery)->whereNotNull('checked_in_at')->count();
        $rate = $totalTickets > 0 ? round($checkedIn / $totalTickets * 100, 1) : 0;

        $rows = $paginator->getCollection()->map(function ($t) {
            $metaVisit = is_array($t->meta ?? null) ? ($t->meta['visit_date'] ?? null) : null;
            $visit = $metaVisit
                ?? optional($t->ticketType?->valid_date)->toDateString()
                ?? optional($t->order?->paid_at)->toDateString();
            return [
                'id' => $t->id,
                'code' => $t->code,
                'barcode' => $t->barcode,
                'customer_name' => $t->order->customer_name ?? null,
                'customer_email' => $t->order->customer_email ?? null,
                'ticket_type' => $t->ticketType->name ?? null,
                'service_category' => $t->ticketType->service_category ?? 'access',
                'issuing_company' => $t->ticketType->issuing_company ?? 'primary',
                'visit_date' => $visit,
                'status' => $t->status,
                'checked_in_at' => optional($t->checked_in_at)->toIso8601String(),
            ];
        });

        return $this->success([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'stats' => [
                'total' => $totalTickets,
                'checked_in' => $checkedIn,
                'no_show' => max(0, $totalTickets - $checkedIn),
                'rate' => $rate,
            ],
            'rows' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/sales-timeline
     *     ?from=&to=&group_by=day|week|month
     *
     * Time series vânzări — pentru chart-uri pe pagina Sales.
     */
    public function salesTimeline(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();

        if (!$eventModel) {
            return $this->error('Event not found', 404);
        }

        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'group_by' => 'nullable|in:day,week,month',
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : Carbon::today()->subDays(7)->startOfDay();
        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : Carbon::today()->endOfDay();
        $groupBy = $validated['group_by'] ?? 'day';

        $orders = Order::query()
            ->where('event_id', $eventModel->id)
            ->whereIn('status', ['completed', 'paid'])
            ->whereBetween('paid_at', [$from, $to])
            ->with(['tickets:id,order_id,ticket_type_id,price,status', 'tickets.ticketType:id,service_category'])
            ->get(['id', 'event_id', 'paid_at', 'status', 'total', 'currency']);

        // Format key per groupBy
        $fmtKey = function ($carbon) use ($groupBy) {
            if ($groupBy === 'month') return $carbon->format('Y-m');
            if ($groupBy === 'week')  return $carbon->format('o-W'); // ISO week
            return $carbon->toDateString();
        };

        $buckets = [];
        $totalRevenue = 0.0;
        $totalTickets = 0;
        $totalOrders = $orders->count();
        $byCategory = [];

        foreach ($orders as $order) {
            $key = $fmtKey($order->paid_at);
            if (!isset($buckets[$key])) {
                $buckets[$key] = ['date' => $key, 'orders' => 0, 'tickets' => 0, 'revenue' => 0.0];
            }
            $buckets[$key]['orders']++;
            $rev = (float) ($order->total ?? 0);
            $buckets[$key]['revenue'] += $rev;
            $totalRevenue += $rev;

            foreach ($order->tickets as $ticket) {
                if (in_array($ticket->status, ['cancelled', 'refunded'], true)) continue;
                $buckets[$key]['tickets']++;
                $totalTickets++;
                $cat = $ticket->ticketType->service_category ?? 'access';
                $byCategory[$cat] = ($byCategory[$cat] ?? 0) + 1;
            }
        }

        ksort($buckets);

        return $this->success([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'group_by' => $groupBy,
            'currency' => $orders->first()?->currency ?? 'RON',
            'rows' => array_values($buckets),
            'totals' => [
                'orders' => $totalOrders,
                'tickets' => $totalTickets,
                'revenue' => round($totalRevenue, 2),
                'avg_order' => $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0,
            ],
            'by_category' => $byCategory,
        ]);
    }

    protected function emptyBucket(): array
    {
        return [
            'orders' => [],
            'tickets_count' => 0,
            'subtotal' => 0.0,
            'by_category' => [],
        ];
    }

    protected function requireOrganizer(Request $request): MarketplaceOrganizer
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }

        return $organizer;
    }

    protected function localizedTitle(Event $event): string
    {
        $title = $event->title;
        if (is_array($title)) {
            return $title['ro'] ?? $title['en'] ?? (reset($title) ?: '');
        }
        return (string) ($title ?? '');
    }
}
