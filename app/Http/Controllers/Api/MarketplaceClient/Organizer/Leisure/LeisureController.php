<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer\Leisure;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\LeisureShift;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceOrganizerTeamMember;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
use App\Models\TicketType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
                    'price' => (float) ($tt->price_max ?? $tt->price ?? 0),
                    'price_max' => (float) ($tt->price_max ?? 0),
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

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/dashboard/live
     *
     * Snapshot real-time: vândut azi, scanat azi, ocupare curentă, venit azi,
     * activitate pe porți (ultima oră, grupată pe bucket-uri de 5 min)
     * și stream cu ultimele 20 activități (vânzări + scanări).
     */
    public function dashboardLive(Request $request, int $event): JsonResponse
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

        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();
        $hourAgo = Carbon::now()->subHour();

        // Stats azi
        $ordersToday = Order::query()
            ->where('event_id', $eventModel->id)
            ->whereIn('status', ['paid', 'completed'])
            ->whereBetween('paid_at', [$todayStart, $todayEnd])
            ->get(['id', 'total', 'currency', 'paid_at', 'customer_name']);

        $todayRevenue = round((float) $ordersToday->sum('total'), 2);
        $todayOrders = $ordersToday->count();

        $todaySoldTickets = (int) \App\Models\Ticket::query()
            ->whereHas('order', fn ($q) => $q
                ->where('event_id', $eventModel->id)
                ->whereIn('status', ['paid', 'completed'])
                ->whereBetween('paid_at', [$todayStart, $todayEnd]))
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->count();

        $todayCheckedIn = (int) \App\Models\Ticket::query()
            ->whereHas('order', fn ($q) => $q->where('event_id', $eventModel->id))
            ->whereBetween('checked_in_at', [$todayStart, $todayEnd])
            ->count();

        $occupancy = max(0, $todayCheckedIn);

        // Activitate ultima oră — check-ins grupate pe bucket 5 minute
        $recentScans = \App\Models\Ticket::query()
            ->whereHas('order', fn ($q) => $q->where('event_id', $eventModel->id))
            ->where('checked_in_at', '>=', $hourAgo)
            ->with(['ticketType:id,name,service_category'])
            ->orderByDesc('checked_in_at')
            ->limit(50)
            ->get(['id', 'order_id', 'ticket_type_id', 'code', 'checked_in_at']);

        $buckets = [];
        foreach ($recentScans as $s) {
            $ts = $s->checked_in_at;
            if (!$ts) continue;
            $bucketMinute = (int) floor($ts->minute / 5) * 5;
            $key = $ts->copy()->minute($bucketMinute)->second(0)->format('H:i');
            if (!isset($buckets[$key])) $buckets[$key] = ['time' => $key, 'count' => 0];
            $buckets[$key]['count']++;
        }
        ksort($buckets);

        // Stream — combinăm orders recente (vânzări) + check-ins recente
        $recentOrders = Order::query()
            ->where('event_id', $eventModel->id)
            ->whereIn('status', ['paid', 'completed'])
            ->where('paid_at', '>=', $hourAgo)
            ->orderByDesc('paid_at')
            ->limit(20)
            ->get(['id', 'customer_name', 'total', 'currency', 'paid_at']);

        $stream = [];
        foreach ($recentOrders as $o) {
            $stream[] = [
                'type' => 'sale',
                'at' => optional($o->paid_at)->toIso8601String(),
                'ts' => optional($o->paid_at)->timestamp ?? 0,
                'label' => 'Vânzare nouă',
                'detail' => ($o->customer_name ?? 'Client') . ' · ' . number_format((float) $o->total, 2) . ' RON',
                'order_id' => $o->id,
            ];
        }
        foreach ($recentScans as $s) {
            $stream[] = [
                'type' => 'scan',
                'at' => optional($s->checked_in_at)->toIso8601String(),
                'ts' => optional($s->checked_in_at)->timestamp ?? 0,
                'label' => 'Check-in',
                'detail' => ($s->ticketType->name ?? 'Bilet') . ' · cod ' . ($s->code ?: '—'),
                'ticket_id' => $s->id,
            ];
        }
        usort($stream, fn ($a, $b) => ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));
        $stream = array_slice($stream, 0, 20);

        return $this->success([
            'now' => Carbon::now()->toIso8601String(),
            'stats' => [
                'sold_today' => $todaySoldTickets,
                'scanned_today' => $todayCheckedIn,
                'occupancy' => $occupancy,
                'revenue_today' => $todayRevenue,
                'orders_today' => $todayOrders,
            ],
            'gates_activity' => array_values($buckets),
            'stream' => $stream,
        ]);
    }

    /**
     * POST /marketplace-client/organizer/events/{event}/leisure/pos-sale
     *
     * Body: {
     *   date: 'YYYY-MM-DD',
     *   items: [{ticket_type_id, qty}, ...],
     *   customer: { name?, email?, phone? },
     *   payment_method: 'cash'|'card'|'invoice'
     * }
     *
     * Creează Order + OrderItems + Tickets atomic (status='paid') pentru vânzare on-site.
     * Întoarce structura pentru chitanță print.
     */
    public function posSale(Request $request, int $event): JsonResponse
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
            'date' => 'nullable|date',
            'items' => 'required|array|min:1|max:50',
            'items.*.ticket_type_id' => 'required|integer|exists:ticket_types,id',
            'items.*.qty' => 'required|integer|min:1|max:100',
            'customer.name' => 'nullable|string|max:120',
            'customer.email' => 'nullable|email|max:120',
            'customer.phone' => 'nullable|string|max:30',
            'customer.vehicle_plate' => 'nullable|string|max:20',
            'payment_method' => 'required|in:cash,card,invoice',
        ]);

        $visitDate = isset($validated['date'])
            ? Carbon::parse($validated['date'])->toDateString()
            : Carbon::today()->toDateString();

        // Fetch ticket types valid pentru event
        $ttIds = collect($validated['items'])->pluck('ticket_type_id')->unique();
        $types = TicketType::query()
            ->whereIn('id', $ttIds)
            ->where('event_id', $eventModel->id)
            ->get()
            ->keyBy('id');

        if ($types->count() !== $ttIds->count()) {
            return $this->error('Unele tipuri de bilet nu aparțin acestui eveniment.', 422);
        }

        // Calculează total
        $subtotal = 0.0;
        $items = [];
        foreach ($validated['items'] as $row) {
            $tt = $types->get($row['ticket_type_id']);
            if (!$tt) continue;
            $unit = (float) ($tt->price_max ?? $tt->price ?? 0);
            $line = round($unit * (int) $row['qty'], 2);
            $subtotal += $line;
            $items[] = [
                'ticket_type' => $tt,
                'qty' => (int) $row['qty'],
                'unit_price' => $unit,
                'line_total' => $line,
            ];
        }
        $subtotal = round($subtotal, 2);
        $total = $subtotal;

        $paymentMethod = $validated['payment_method'];
        $now = Carbon::now();

        $order = null;
        $issued = [];

        try {
            DB::beginTransaction();

            $order = Order::create([
                'tenant_id' => $eventModel->tenant_id,
                'marketplace_client_id' => $marketplace->id,
                'marketplace_organizer_id' => $organizer->id,
                'event_id' => $eventModel->id,
                'order_number' => 'POS-' . strtoupper(Str::random(10)),
                'customer_name' => $validated['customer']['name'] ?? 'POS — vânzare on-site',
                'customer_email' => $validated['customer']['email'] ?? 'pos@ambilet.ro',
                'customer_phone' => $validated['customer']['phone'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'total' => $total,
                'currency' => 'RON',
                'status' => $paymentMethod === 'invoice' ? 'pending' : 'paid',
                'payment_status' => $paymentMethod === 'invoice' ? 'pending' : 'paid',
                'payment_processor' => 'pos',
                'payment_reference' => 'pos-' . $paymentMethod,
                'paid_at' => $paymentMethod === 'invoice' ? null : $now,
                'source' => 'pos',
                'meta' => [
                    'pos' => true,
                    'payment_method' => $paymentMethod,
                    'visit_date' => $visitDate,
                    'vehicle_plate' => $validated['customer']['vehicle_plate'] ?? null,
                    'cashier_organizer_id' => $organizer->id,
                ],
            ]);

            foreach ($items as $it) {
                $tt = $it['ticket_type'];
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'ticket_type_id' => $tt->id,
                    'name' => is_array($tt->name) ? ($tt->name['ro'] ?? reset($tt->name)) : $tt->name,
                    'quantity' => $it['qty'],
                    'unit_price' => $it['unit_price'],
                    'total' => $it['line_total'],
                    'meta' => [
                        'service_category' => $tt->service_category ?? 'access',
                        'issuing_company' => $tt->issuing_company ?? 'primary',
                        'visit_date' => $visitDate,
                    ],
                ]);

                for ($i = 0; $i < $it['qty']; $i++) {
                    $code = strtoupper(Str::random(10));
                    $ticket = Ticket::create([
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'ticket_type_id' => $tt->id,
                        'event_id' => $eventModel->id,
                        'tenant_id' => $eventModel->tenant_id,
                        'marketplace_client_id' => $marketplace->id,
                        'code' => $code,
                        'barcode' => $code,
                        'status' => $paymentMethod === 'invoice' ? 'pending' : 'valid',
                        'price' => $it['unit_price'],
                        'attendee_name' => $validated['customer']['name'] ?? null,
                        'attendee_email' => $validated['customer']['email'] ?? null,
                        'meta' => [
                            'pos' => true,
                            'visit_date' => $visitDate,
                            'service_category' => $tt->service_category ?? 'access',
                            'issuing_company' => $tt->issuing_company ?? 'primary',
                        ],
                    ]);
                    $issued[] = [
                        'id' => $ticket->id,
                        'code' => $ticket->code,
                        'ticket_type' => is_array($tt->name) ? ($tt->name['ro'] ?? reset($tt->name)) : $tt->name,
                        'service_category' => $tt->service_category ?? 'access',
                        'price' => $it['unit_price'],
                    ];
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Eroare la procesarea vânzării: ' . $e->getMessage(), 500);
        }

        // Datele pentru chitanță 80mm
        $issuer = $organizer->getIssuerData('primary');

        return $this->success([
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'paid_at' => optional($order->paid_at)->toIso8601String(),
                'total' => (float) $order->total,
                'subtotal' => (float) $order->subtotal,
                'currency' => $order->currency,
                'payment_method' => $paymentMethod,
                'status' => $order->status,
                'visit_date' => $visitDate,
            ],
            'customer' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
            ],
            'issuer' => $issuer,
            'items' => array_map(fn ($it) => [
                'name' => is_array($it['ticket_type']->name) ? ($it['ticket_type']->name['ro'] ?? reset($it['ticket_type']->name)) : $it['ticket_type']->name,
                'qty' => $it['qty'],
                'unit_price' => $it['unit_price'],
                'line_total' => $it['line_total'],
                'service_category' => $it['ticket_type']->service_category ?? 'access',
            ], $items),
            'tickets' => $issued,
        ]);
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/shifts?week=YYYY-MM-DD
     *
     * Listă turnete pentru săptămâna care conține `week` (default: săptămâna curentă).
     */
    public function shiftsIndex(Request $request, int $event): JsonResponse
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

        $weekParam = $request->query('week');
        $base = $weekParam ? Carbon::parse($weekParam) : Carbon::today();
        $weekStart = $base->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $shifts = LeisureShift::query()
            ->where('event_id', $eventModel->id)
            ->whereBetween('start_at', [$weekStart, $weekEnd])
            ->with(['teamMember:id,name,email,role'])
            ->orderBy('start_at')
            ->get();

        $members = MarketplaceOrganizerTeamMember::query()
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        return $this->success([
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'members' => $members,
            'shifts' => $shifts->map(fn ($s) => [
                'id' => $s->id,
                'team_member_id' => $s->team_member_id,
                'member_name' => $s->teamMember->name ?? null,
                'start_at' => optional($s->start_at)->toIso8601String(),
                'end_at' => optional($s->end_at)->toIso8601String(),
                'role' => $s->role,
                'gate' => $s->gate,
                'notes' => $s->notes,
            ])->values(),
        ]);
    }

    /**
     * POST /marketplace-client/organizer/events/{event}/leisure/shifts
     */
    public function shiftStore(Request $request, int $event): JsonResponse
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
            'team_member_id' => 'nullable|integer|exists:marketplace_organizer_team_members,id',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'role' => 'required|in:gate_scanner,sales_operator,shift_manager,accountant',
            'gate' => 'nullable|string|max:32',
            'notes' => 'nullable|string|max:500',
        ]);

        $shift = LeisureShift::create([
            'marketplace_organizer_id' => $organizer->id,
            'event_id' => $eventModel->id,
            'team_member_id' => $validated['team_member_id'] ?? null,
            'start_at' => Carbon::parse($validated['start_at']),
            'end_at' => Carbon::parse($validated['end_at']),
            'role' => $validated['role'],
            'gate' => $validated['gate'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $organizer->id,
        ]);

        return $this->success(['id' => $shift->id], 'Turnetă creată', 201);
    }

    /**
     * PUT /marketplace-client/organizer/events/{event}/leisure/shifts/{shift}
     */
    public function shiftUpdate(Request $request, int $event, int $shift): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $shiftModel = LeisureShift::query()
            ->where('id', $shift)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event)
            ->first();

        if (!$shiftModel) {
            return $this->error('Shift not found', 404);
        }

        $validated = $request->validate([
            'team_member_id' => 'nullable|integer|exists:marketplace_organizer_team_members,id',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after:start_at',
            'role' => 'nullable|in:gate_scanner,sales_operator,shift_manager,accountant',
            'gate' => 'nullable|string|max:32',
            'notes' => 'nullable|string|max:500',
        ]);

        $shiftModel->fill(array_filter($validated, fn ($v) => $v !== null && $v !== ''));
        $shiftModel->save();

        return $this->success(['id' => $shiftModel->id], 'Turnetă actualizată');
    }

    /**
     * DELETE /marketplace-client/organizer/events/{event}/leisure/shifts/{shift}
     */
    public function shiftDestroy(Request $request, int $event, int $shift): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $shiftModel = LeisureShift::query()
            ->where('id', $shift)
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event)
            ->first();

        if (!$shiftModel) {
            return $this->error('Shift not found', 404);
        }

        $shiftModel->delete();
        return $this->success(['id' => $shift], 'Turnetă ștearsă');
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
