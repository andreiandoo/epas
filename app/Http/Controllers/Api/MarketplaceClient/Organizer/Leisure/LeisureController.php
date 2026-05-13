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

        // Comision la nivel de organizator (folosit pentru calcul live in POS)
        $commission = ['rate' => 0.0, 'fixed' => 0.0, 'mode' => 'included'];
        if ($eventOrganizer) {
            $commission = [
                'rate' => (float) $eventOrganizer->getEffectiveCommissionRate(),
                'fixed' => (float) ($eventOrganizer->fixed_commission_default ?? 0),
                'mode' => $eventOrganizer->getEffectiveCommissionMode(),
            ];
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
            'commission' => $commission,
            'issuers' => $issuers,
            'ticket_types' => $ticketTypes->map(function (TicketType $tt) use ($ticketTypes) {
                $variants = [];
                $rawVariants = is_array($tt->meta) ? ($tt->meta['variants'] ?? null) : null;
                if (is_array($rawVariants)) {
                    foreach ($rawVariants as $v) {
                        if (!is_array($v) || empty($v['label'])) continue;
                        $variants[] = [
                            'id' => $v['id'] ?? \Illuminate\Support\Str::slug($v['label']),
                            'label' => $v['label'],
                            'duration_minutes' => isset($v['duration_minutes']) ? (int) $v['duration_minutes'] : null,
                            'price' => isset($v['price']) ? (float) $v['price'] : (float) ($tt->price_max ?? $tt->price ?? 0),
                        ];
                    }
                }

                // Add-ons (F2: ex Sanii tractare extra)
                $addons = [];
                $rawAddons = is_array($tt->meta) ? ($tt->meta['addons'] ?? null) : null;
                if (is_array($rawAddons)) {
                    foreach ($rawAddons as $a) {
                        if (!is_array($a) || empty($a['label'])) continue;
                        $addons[] = [
                            'id' => $a['id'] ?? \Illuminate\Support\Str::slug($a['label']),
                            'label' => $a['label'],
                            'price' => (float) ($a['price'] ?? 0),
                            'included_qty' => max(0, (int) ($a['included_qty'] ?? 0)),
                            'max_per_unit' => max(0, (int) ($a['max_per_unit'] ?? 5)),
                        ];
                    }
                }

                // Pachete: enrich outputs cu nume + preț component
                $packageOutputs = [];
                $packageSum = 0.0;
                $rawOutputs = is_array($tt->meta) ? ($tt->meta['package_outputs'] ?? null) : null;
                $price = (float) ($tt->price_max ?? $tt->price ?? 0);
                if (is_array($rawOutputs) && ($tt->effective_service_category === 'package')) {
                    foreach ($rawOutputs as $row) {
                        if (!is_array($row) || empty($row['ticket_type_id'])) continue;
                        $compTt = $ticketTypes->firstWhere('id', $row['ticket_type_id']);
                        if (!$compTt) continue;
                        $compPrice = (float) ($compTt->price_max ?? $compTt->price ?? 0);
                        if (!empty($row['variant_id']) && is_array($compTt->meta['variants'] ?? null)) {
                            foreach ($compTt->meta['variants'] as $cv) {
                                if (!is_array($cv) || empty($cv['label'])) continue;
                                $cvid = $cv['id'] ?? \Illuminate\Support\Str::slug($cv['label']);
                                if ($cvid === $row['variant_id']) {
                                    $compPrice = (float) ($cv['price'] ?? $compPrice);
                                    break;
                                }
                            }
                        }
                        $qty = (int) ($row['qty'] ?? 1);
                        $packageSum += $compPrice * $qty;
                        $packageOutputs[] = [
                            'ticket_type_id' => (int) $row['ticket_type_id'],
                            'variant_id' => $row['variant_id'] ?? null,
                            'qty' => $qty,
                            'component_name' => is_array($compTt->name) ? ($compTt->name['ro'] ?? reset($compTt->name)) : $compTt->name,
                            'component_unit_price' => $compPrice,
                        ];
                    }
                }

                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'sku' => $tt->sku,
                    'price' => $price,
                    'price_max' => (float) ($tt->price_max ?? 0),
                    'service_category' => $tt->effective_service_category,
                    'is_parking' => (bool) $tt->is_parking,
                    'requires_vehicle_info' => (bool) $tt->requires_vehicle_info,
                    'daily_capacity' => $tt->daily_capacity,
                    'ticket_group' => $tt->ticket_group,
                    'issuing_company' => $tt->effective_issuing_company,
                    'issuing_explicit' => (bool) $tt->issuing_company,
                    'meta' => is_array($tt->meta) ? $tt->meta : (object) [],
                    'variants' => $variants,
                    'addons' => $addons,
                    'package_outputs' => $packageOutputs,
                    'package_components_sum' => round($packageSum, 2),
                    'package_savings' => $packageOutputs ? round($packageSum - $price, 2) : 0,
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
            'items.*.variant_id' => 'nullable|string|max:64',
            'items.*.addons' => 'nullable|array|max:20',
            'items.*.addons.*.addon_id' => 'required_with:items.*.addons|string|max:64',
            'items.*.addons.*.qty' => 'required_with:items.*.addons|integer|min:0|max:500',
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

        // Comision la nivel de organizator (max procentual sau fix per bilet)
        $commissionRate = (float) $organizer->getEffectiveCommissionRate();
        $commissionFixed = (float) ($organizer->fixed_commission_default ?? 0);
        $commissionMode = $organizer->getEffectiveCommissionMode();
        $commissionOnTop = ($commissionMode === 'added_on_top');

        // Calculează subtotal + comision per linie
        $subtotal = 0.0;
        $commissionTotal = 0.0;
        $items = [];
        foreach ($validated['items'] as $row) {
            $tt = $types->get($row['ticket_type_id']);
            if (!$tt) continue;

            // Rezolvă varianta selectată (dacă e cazul)
            $variant = null;
            $rawVariants = is_array($tt->meta) ? ($tt->meta['variants'] ?? null) : null;
            if (!empty($row['variant_id']) && is_array($rawVariants)) {
                foreach ($rawVariants as $v) {
                    if (!is_array($v) || empty($v['label'])) continue;
                    $vid = $v['id'] ?? \Illuminate\Support\Str::slug($v['label']);
                    if ($vid === $row['variant_id']) { $variant = $v; break; }
                }
            }
            $unit = $variant !== null && isset($variant['price'])
                ? (float) $variant['price']
                : (float) ($tt->price_max ?? $tt->price ?? 0);

            $qty = (int) $row['qty'];
            $line = round($unit * $qty, 2);

            // Add-ons (F2): calcul paid_qty = total_qty - parent_qty * included_qty
            $addonsResolved = [];
            $addonsTotal = 0.0;
            if (!empty($row['addons']) && is_array($row['addons']) && !empty($tt->meta['addons']) && is_array($tt->meta['addons'])) {
                $addonsByid = [];
                foreach ($tt->meta['addons'] as $a) {
                    if (!is_array($a) || empty($a['label'])) continue;
                    $aid = $a['id'] ?? \Illuminate\Support\Str::slug($a['label']);
                    $addonsByid[$aid] = $a;
                }
                foreach ($row['addons'] as $reqAddon) {
                    if (empty($reqAddon['addon_id'])) continue;
                    $aid = $reqAddon['addon_id'];
                    $aDef = $addonsByid[$aid] ?? null;
                    if (!$aDef) continue;
                    $totalAddonQty = max(0, (int) ($reqAddon['qty'] ?? 0));
                    if ($totalAddonQty === 0) continue;

                    $includedPerTicket = max(0, (int) ($aDef['included_qty'] ?? 0));
                    $maxPaidPerTicket = max(0, (int) ($aDef['max_per_unit'] ?? 5));
                    $freePool = $includedPerTicket * $qty;
                    $maxTotal = ($includedPerTicket + $maxPaidPerTicket) * $qty;
                    if ($totalAddonQty > $maxTotal) {
                        return $this->error('Add-on "' . $aDef['label'] . '" depășește maximul (' . $maxTotal . ' pentru ' . $qty . ' bilete).', 422);
                    }
                    $paidQty = max(0, $totalAddonQty - $freePool);
                    $unitPriceAddon = (float) ($aDef['price'] ?? 0);
                    $addonLine = round($unitPriceAddon * $paidQty, 2);
                    $addonsTotal += $addonLine;
                    $addonsResolved[] = [
                        'addon_id' => $aid,
                        'label' => $aDef['label'],
                        'total_qty' => $totalAddonQty,
                        'free_qty' => min($freePool, $totalAddonQty),
                        'paid_qty' => $paidQty,
                        'unit_price' => $unitPriceAddon,
                        'line_total' => $addonLine,
                    ];
                }
            }

            $line += round($addonsTotal, 2);
            $subtotal += $line;

            // Comision per bilet emis: max(unit * rate%, fixed) doar dacă 'added_on_top'
            $commissionPerTicket = 0.0;
            if ($commissionOnTop) {
                $commissionPerTicket = max($unit * $commissionRate / 100, $commissionFixed);
                $commissionTotal += round($commissionPerTicket * $qty, 2);
            }

            $items[] = [
                'ticket_type' => $tt,
                'qty' => $qty,
                'unit_price' => $unit,
                'line_total' => $line,
                'commission_per_ticket' => round($commissionPerTicket, 2),
                'variant' => $variant ? [
                    'id' => $variant['id'] ?? \Illuminate\Support\Str::slug($variant['label']),
                    'label' => $variant['label'],
                    'duration_minutes' => $variant['duration_minutes'] ?? null,
                ] : null,
                'addons' => $addonsResolved,
                'addons_total' => round($addonsTotal, 2),
            ];
        }
        $subtotal = round($subtotal, 2);
        $commissionTotal = round($commissionTotal, 2);
        $total = round($subtotal + $commissionTotal, 2);

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
                    'commission_total' => $commissionTotal,
                    'commission_rate' => $commissionRate,
                    'commission_fixed' => $commissionFixed,
                    'commission_mode' => $commissionMode,
                ],
            ]);

            foreach ($items as $it) {
                $tt = $it['ticket_type'];
                $variantMeta = $it['variant'] ?? null;
                $displayName = is_array($tt->name) ? ($tt->name['ro'] ?? reset($tt->name)) : $tt->name;
                if ($variantMeta) {
                    $displayName .= ' — ' . $variantMeta['label'];
                }

                $isPackage = (($tt->service_category ?? null) === 'package');
                $packageOutputs = is_array($tt->meta['package_outputs'] ?? null)
                    ? $tt->meta['package_outputs']
                    : [];

                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'ticket_type_id' => $tt->id,
                    'name' => $displayName,
                    'quantity' => $it['qty'],
                    'unit_price' => $it['unit_price'],
                    'total' => $it['line_total'],
                    'meta' => array_filter([
                        'service_category' => $tt->service_category ?? 'access',
                        'issuing_company' => $tt->issuing_company ?? 'primary',
                        'visit_date' => $visitDate,
                        'variant' => $variantMeta,
                        'package' => $isPackage,
                        'package_outputs' => $isPackage ? $packageOutputs : null,
                        'addons' => !empty($it['addons']) ? $it['addons'] : null,
                        'addons_total' => $it['addons_total'] ?? 0,
                    ]),
                ]);

                if ($isPackage && !empty($packageOutputs)) {
                    // Fan-out: pentru fiecare bucată de pachet cumpărată, emite N tickets per component
                    $componentIds = collect($packageOutputs)->pluck('ticket_type_id')->filter()->unique();
                    $componentTypes = TicketType::query()
                        ->whereIn('id', $componentIds)
                        ->where('event_id', $eventModel->id)
                        ->get()
                        ->keyBy('id');

                    $packagesQty = (int) $it['qty'];
                    for ($p = 0; $p < $packagesQty; $p++) {
                        $packageCode = strtoupper(Str::random(10));
                        // Bilet "umbrella" pentru pachet (status valid dar fără scanare directă)
                        $pkgTicket = Ticket::create([
                            'order_id' => $order->id,
                            'order_item_id' => $orderItem->id,
                            'ticket_type_id' => $tt->id,
                            'event_id' => $eventModel->id,
                            'tenant_id' => $eventModel->tenant_id,
                            'marketplace_client_id' => $marketplace->id,
                            'code' => $packageCode,
                            'barcode' => $packageCode,
                            'status' => $paymentMethod === 'invoice' ? 'pending' : 'valid',
                            'price' => $it['unit_price'],
                            'attendee_name' => $validated['customer']['name'] ?? null,
                            'attendee_email' => $validated['customer']['email'] ?? null,
                            'meta' => array_filter([
                                'pos' => true,
                                'visit_date' => $visitDate,
                                'service_category' => 'package',
                                'issuing_company' => $tt->issuing_company ?? 'primary',
                                'is_package_umbrella' => true,
                                'package_outputs' => $packageOutputs,
                            ]),
                        ]);
                        $issued[] = [
                            'id' => $pkgTicket->id,
                            'code' => $pkgTicket->code,
                            'ticket_type' => $displayName . ' (pachet)',
                            'service_category' => 'package',
                            'price' => $it['unit_price'],
                            'variant' => null,
                        ];

                        // Componentele reale (scanabile, fără preț propriu — prețul = 0)
                        foreach ($packageOutputs as $row) {
                            if (empty($row['ticket_type_id'])) continue;
                            $compTt = $componentTypes->get($row['ticket_type_id']);
                            if (!$compTt) continue;
                            $compQty = (int) ($row['qty'] ?? 1);
                            $compVariant = null;
                            if (!empty($row['variant_id']) && is_array($compTt->meta['variants'] ?? null)) {
                                foreach ($compTt->meta['variants'] as $cv) {
                                    if (!is_array($cv) || empty($cv['label'])) continue;
                                    $cvid = $cv['id'] ?? Str::slug($cv['label']);
                                    if ($cvid === $row['variant_id']) {
                                        $compVariant = [
                                            'id' => $cvid,
                                            'label' => $cv['label'],
                                            'duration_minutes' => $cv['duration_minutes'] ?? null,
                                        ];
                                        break;
                                    }
                                }
                            }
                            $compDisplay = is_array($compTt->name) ? ($compTt->name['ro'] ?? reset($compTt->name)) : $compTt->name;
                            if ($compVariant) $compDisplay .= ' — ' . $compVariant['label'];

                            for ($c = 0; $c < $compQty; $c++) {
                                $code = strtoupper(Str::random(10));
                                $compTicket = Ticket::create([
                                    'order_id' => $order->id,
                                    'order_item_id' => $orderItem->id,
                                    'ticket_type_id' => $compTt->id,
                                    'event_id' => $eventModel->id,
                                    'tenant_id' => $eventModel->tenant_id,
                                    'marketplace_client_id' => $marketplace->id,
                                    'code' => $code,
                                    'barcode' => $code,
                                    'status' => $paymentMethod === 'invoice' ? 'pending' : 'valid',
                                    'price' => 0, // componenta = $0, prețul e în pachet
                                    'attendee_name' => $validated['customer']['name'] ?? null,
                                    'attendee_email' => $validated['customer']['email'] ?? null,
                                    'meta' => array_filter([
                                        'pos' => true,
                                        'visit_date' => $visitDate,
                                        'service_category' => $compTt->service_category ?? 'access',
                                        'issuing_company' => $compTt->issuing_company ?? 'primary',
                                        'variant' => $compVariant,
                                        'from_package' => true,
                                        'parent_package_ticket_id' => $pkgTicket->id,
                                    ]),
                                ]);
                                $issued[] = [
                                    'id' => $compTicket->id,
                                    'code' => $compTicket->code,
                                    'ticket_type' => $compDisplay,
                                    'service_category' => $compTt->service_category ?? 'access',
                                    'price' => 0,
                                    'variant' => $compVariant,
                                    'from_package' => true,
                                ];
                            }
                        }
                    }
                } else {
                    // Flow standard (non-pachet)
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
                            'meta' => array_filter([
                                'pos' => true,
                                'visit_date' => $visitDate,
                                'service_category' => $tt->service_category ?? 'access',
                                'issuing_company' => $tt->issuing_company ?? 'primary',
                                'variant' => $variantMeta,
                            ]),
                        ]);
                        $issued[] = [
                            'id' => $ticket->id,
                            'code' => $ticket->code,
                            'ticket_type' => $displayName,
                            'service_category' => $tt->service_category ?? 'access',
                            'price' => $it['unit_price'],
                            'variant' => $variantMeta,
                        ];
                    }
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
                'commission_total' => $commissionTotal,
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
            'items' => array_map(function ($it) {
                $baseName = is_array($it['ticket_type']->name) ? ($it['ticket_type']->name['ro'] ?? reset($it['ticket_type']->name)) : $it['ticket_type']->name;
                $name = $it['variant'] ? ($baseName . ' — ' . $it['variant']['label']) : $baseName;
                return [
                    'name' => $name,
                    'qty' => $it['qty'],
                    'unit_price' => $it['unit_price'],
                    'line_total' => $it['line_total'],
                    'commission_per_ticket' => $it['commission_per_ticket'] ?? 0,
                    'service_category' => $it['ticket_type']->service_category ?? 'access',
                    'variant' => $it['variant'],
                    'addons' => $it['addons'] ?? [],
                    'addons_total' => $it['addons_total'] ?? 0,
                ];
            }, $items),
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

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/products
     *
     * Listă completă produse (TicketType) cu toate câmpurile editabile prin panou.
     */
    public function productsIndex(Request $request, int $event): JsonResponse
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

        $types = TicketType::query()
            ->where('event_id', $eventModel->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->success([
            'products' => $types->map(fn (TicketType $t) => $this->presentProduct($t))->all(),
        ]);
    }

    /**
     * POST /marketplace-client/organizer/events/{event}/leisure/products
     */
    public function productStore(Request $request, int $event): JsonResponse
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

        $data = $this->validateProduct($request);
        $data['event_id'] = $eventModel->id;
        $data['sort_order'] = (int) (TicketType::where('event_id', $eventModel->id)->max('sort_order') ?? 0) + 10;
        $data['is_active'] = $data['is_active'] ?? true;

        $meta = $this->extractProductMeta($request);
        if ($meta !== null) $data['meta'] = $meta;

        $tt = TicketType::create($data);

        return $this->success(['product' => $this->presentProduct($tt)], 'Produs creat', 201);
    }

    /**
     * PUT /marketplace-client/organizer/events/{event}/leisure/products/{product}
     */
    public function productUpdate(Request $request, int $event, int $product): JsonResponse
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

        $tt = TicketType::query()
            ->where('id', $product)
            ->where('event_id', $eventModel->id)
            ->first();
        if (!$tt) return $this->error('Product not found', 404);

        $data = $this->validateProduct($request, $tt->id);

        $meta = $this->extractProductMeta($request);
        if ($meta !== null) {
            $existing = is_array($tt->meta) ? $tt->meta : [];
            $data['meta'] = array_merge($existing, $meta);
        }

        $tt->fill($data);
        $tt->save();

        return $this->success(['product' => $this->presentProduct($tt->fresh())], 'Produs actualizat');
    }

    /**
     * DELETE /marketplace-client/organizer/events/{event}/leisure/products/{product}
     */
    public function productDestroy(Request $request, int $event, int $product): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $tt = TicketType::query()
            ->where('id', $product)
            ->where('event_id', $eventModel->id)
            ->first();
        if (!$tt) return $this->error('Product not found', 404);

        // Refuz ștergerea dacă există bilete emise (siguranță)
        $issued = Ticket::where('ticket_type_id', $tt->id)->limit(1)->exists();
        if ($issued) {
            return $this->error('Există bilete emise pentru acest produs. Dezactivează în loc să ștergi.', 422);
        }

        $tt->delete();
        return $this->success(['id' => $product], 'Produs șters');
    }

    /**
     * POST /marketplace-client/organizer/events/{event}/leisure/products/reorder
     * Body: { ids: [12, 5, 18, ...] }
     */
    public function productsReorder(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $validated = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $order = 10;
        foreach ($validated['ids'] as $id) {
            TicketType::where('id', $id)->where('event_id', $eventModel->id)->update(['sort_order' => $order]);
            $order += 10;
        }
        return $this->success(['count' => count($validated['ids'])], 'Ordine actualizată');
    }

    protected function validateProduct(Request $request, ?int $skipId = null): array
    {
        $rules = [
            'name' => 'required|string|max:160',
            'sku' => 'nullable|string|max:64',
            'description' => 'nullable|string|max:2000',
            'service_category' => 'nullable|in:access,parking,rental,activity,extra,package',
            'issuing_company' => 'nullable|in:primary,secondary',
            'price' => 'nullable|numeric|min:0|max:999999',
            'price_max' => 'nullable|numeric|min:0|max:999999',
            'currency' => 'nullable|string|size:3',
            'capacity' => 'nullable|integer|min:0',
            'daily_capacity' => 'nullable|integer|min:0',
            'service_duration_minutes' => 'nullable|integer|min:0|max:1440',
            'product_description' => 'nullable|string|max:10000',
            'usage_terms' => 'nullable|string|max:10000',
            'is_parking' => 'nullable|boolean',
            'requires_vehicle_info' => 'nullable|boolean',
            'requires_access_ticket' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'ticket_group' => 'nullable|string|max:64',
            'min_per_order' => 'nullable|integer|min:0',
            'max_per_order' => 'nullable|integer|min:0',
            'sales_start_at' => 'nullable|date',
            'sales_end_at' => 'nullable|date',
            'valid_date' => 'nullable|date',
        ];

        $validated = $request->validate($rules);

        // Conversie tipuri pentru tabel
        $out = array_filter($validated, fn ($v) => $v !== null);

        // price -> price_max (compat schema)
        if (isset($out['price']) && !isset($out['price_max'])) {
            $out['price_max'] = $out['price'];
        }
        if (!isset($out['currency'])) {
            $out['currency'] = 'RON';
        }

        return $out;
    }

    protected function extractProductMeta(Request $request): ?array
    {
        $meta = $request->input('meta');
        if (!is_array($meta)) return null;

        // whitelist meta keys safe pentru organizer
        $allowed = [
            'icon', 'image', 'image_url', 'unit_label', 'includes',
            'package_outputs', 'badge', 'color', 'variants', 'addons',
        ];
        $filtered = array_intersect_key($meta, array_flip($allowed));

        // Normalize variants: ensure id slug, prețul ca float, durata ca int
        if (isset($filtered['variants']) && is_array($filtered['variants'])) {
            $normalized = [];
            foreach ($filtered['variants'] as $v) {
                if (!is_array($v) || empty($v['label'])) continue;
                $id = isset($v['id']) && $v['id'] !== ''
                    ? preg_replace('/[^a-z0-9-]+/i', '-', strtolower($v['id']))
                    : \Illuminate\Support\Str::slug($v['label']);
                $normalized[] = [
                    'id' => substr($id, 0, 32) ?: 'v',
                    'label' => (string) $v['label'],
                    'duration_minutes' => isset($v['duration_minutes']) ? (int) $v['duration_minutes'] : null,
                    'price' => isset($v['price']) ? (float) $v['price'] : 0.0,
                ];
            }
            $filtered['variants'] = $normalized;
        }

        // Normalize addons (F2)
        if (isset($filtered['addons']) && is_array($filtered['addons'])) {
            $normalizedAddons = [];
            foreach ($filtered['addons'] as $a) {
                if (!is_array($a) || empty($a['label'])) continue;
                $id = isset($a['id']) && $a['id'] !== ''
                    ? preg_replace('/[^a-z0-9-]+/i', '-', strtolower($a['id']))
                    : \Illuminate\Support\Str::slug($a['label']);
                $normalizedAddons[] = [
                    'id' => substr($id, 0, 32) ?: 'a',
                    'label' => (string) $a['label'],
                    'price' => isset($a['price']) ? (float) $a['price'] : 0.0,
                    'included_qty' => max(0, (int) ($a['included_qty'] ?? 0)),
                    'max_per_unit' => max(0, (int) ($a['max_per_unit'] ?? 5)),
                ];
            }
            $filtered['addons'] = $normalizedAddons;
        }

        // Normalize package_outputs (F4)
        if (isset($filtered['package_outputs']) && is_array($filtered['package_outputs'])) {
            $normalizedOutputs = [];
            foreach ($filtered['package_outputs'] as $row) {
                if (!is_array($row) || empty($row['ticket_type_id'])) continue;
                $normalizedOutputs[] = [
                    'ticket_type_id' => (int) $row['ticket_type_id'],
                    'variant_id' => !empty($row['variant_id']) ? (string) $row['variant_id'] : null,
                    'qty' => max(1, (int) ($row['qty'] ?? 1)),
                ];
            }
            $filtered['package_outputs'] = $normalizedOutputs;
        }

        return $filtered;
    }

    protected function presentProduct(TicketType $t): array
    {
        $variants = [];
        $rawVariants = is_array($t->meta) ? ($t->meta['variants'] ?? null) : null;
        if (is_array($rawVariants)) {
            foreach ($rawVariants as $v) {
                if (!is_array($v) || empty($v['label'])) continue;
                $variants[] = [
                    'id' => $v['id'] ?? \Illuminate\Support\Str::slug($v['label']),
                    'label' => $v['label'],
                    'duration_minutes' => isset($v['duration_minutes']) ? (int) $v['duration_minutes'] : null,
                    'price' => isset($v['price']) ? (float) $v['price'] : (float) ($t->price_max ?? $t->price ?? 0),
                ];
            }
        }

        $packageOutputs = is_array($t->meta) ? ($t->meta['package_outputs'] ?? null) : null;
        $packageOutputs = is_array($packageOutputs) ? $packageOutputs : [];

        $addons = [];
        $rawAddons = is_array($t->meta) ? ($t->meta['addons'] ?? null) : null;
        if (is_array($rawAddons)) {
            foreach ($rawAddons as $a) {
                if (!is_array($a) || empty($a['label'])) continue;
                $addons[] = [
                    'id' => $a['id'] ?? \Illuminate\Support\Str::slug($a['label']),
                    'label' => $a['label'],
                    'price' => (float) ($a['price'] ?? 0),
                    'included_qty' => max(0, (int) ($a['included_qty'] ?? 0)),
                    'max_per_unit' => max(0, (int) ($a['max_per_unit'] ?? 5)),
                ];
            }
        }

        return [
            'id' => $t->id,
            'name' => $t->name,
            'sku' => $t->sku,
            'description' => $t->description,
            'service_category' => $t->effective_service_category,
            'issuing_company' => $t->effective_issuing_company,
            'price' => (float) ($t->price_max ?? $t->price ?? 0),
            'price_max' => (float) ($t->price_max ?? 0),
            'currency' => $t->currency,
            'capacity' => $t->capacity,
            'daily_capacity' => $t->daily_capacity,
            'is_active' => (bool) $t->is_active,
            'is_parking' => (bool) $t->is_parking,
            'requires_vehicle_info' => (bool) $t->requires_vehicle_info,
            'requires_access_ticket' => (bool) $t->requires_access_ticket,
            'service_duration_minutes' => $t->service_duration_minutes,
            'product_description' => $t->product_description,
            'usage_terms' => $t->usage_terms,
            'ticket_group' => $t->ticket_group,
            'min_per_order' => $t->min_per_order,
            'max_per_order' => $t->max_per_order,
            'sales_start_at' => optional($t->sales_start_at)->toIso8601String(),
            'sales_end_at' => optional($t->sales_end_at)->toIso8601String(),
            'valid_date' => optional($t->valid_date)->toDateString(),
            'sort_order' => $t->sort_order,
            'meta' => is_array($t->meta) ? $t->meta : (object) [],
            'variants' => $variants,
            'addons' => $addons,
            'package_outputs' => $packageOutputs,
        ];
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/raport?from=&to=
     *
     * Raport agregat: by_ticket_type, by_source (online vs POS),
     * by_cashier (operator POS) cu totaluri venit + bilete + comenzi.
     */
    public function raport(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

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

        $orders = Order::query()
            ->where('event_id', $eventModel->id)
            ->whereIn('status', ['completed', 'paid'])
            ->whereBetween('paid_at', [$from, $to])
            ->with(['tickets:id,order_id,ticket_type_id,price,status', 'tickets.ticketType:id,name,service_category,issuing_company'])
            ->get(['id', 'event_id', 'paid_at', 'status', 'total', 'currency', 'source', 'meta']);

        $byTicketType = [];
        $bySource = [];
        $byCashier = [];
        $totalRevenue = 0.0;
        $totalTickets = 0;
        $totalOrders = $orders->count();

        foreach ($orders as $o) {
            $rev = (float) ($o->total ?? 0);
            $totalRevenue += $rev;

            // Source: pos vs online
            $source = $o->source === 'pos' ? 'pos' : ($o->source ?: 'online');
            if ($source !== 'pos') $source = 'online';
            if (!isset($bySource[$source])) $bySource[$source] = ['source' => $source, 'orders' => 0, 'tickets' => 0, 'revenue' => 0.0];
            $bySource[$source]['orders']++;
            $bySource[$source]['revenue'] += $rev;

            // Cashier (operator POS) — din meta.cashier_organizer_id
            $cashierId = $o->meta['cashier_organizer_id'] ?? null;
            $cashierKey = $cashierId ? "org_{$cashierId}" : 'online';
            if (!isset($byCashier[$cashierKey])) $byCashier[$cashierKey] = ['cashier_id' => $cashierId, 'cashier_label' => $cashierId ? "Operator #{$cashierId}" : 'Online (auto)', 'orders' => 0, 'tickets' => 0, 'revenue' => 0.0];
            $byCashier[$cashierKey]['orders']++;
            $byCashier[$cashierKey]['revenue'] += $rev;

            foreach ($o->tickets as $t) {
                if (in_array($t->status, ['cancelled', 'refunded'], true)) continue;
                $totalTickets++;
                $bySource[$source]['tickets']++;
                $byCashier[$cashierKey]['tickets']++;

                $ttId = $t->ticket_type_id;
                $ttName = $t->ticketType->name ?? "Tip #{$ttId}";
                if (is_array($ttName)) $ttName = $ttName['ro'] ?? reset($ttName);
                $key = "tt_{$ttId}";
                if (!isset($byTicketType[$key])) {
                    $byTicketType[$key] = [
                        'ticket_type_id' => $ttId,
                        'name' => $ttName,
                        'service_category' => $t->ticketType->service_category ?? 'access',
                        'issuing_company' => $t->ticketType->issuing_company ?? 'primary',
                        'tickets' => 0,
                        'revenue' => 0.0,
                    ];
                }
                $byTicketType[$key]['tickets']++;
                $byTicketType[$key]['revenue'] += (float) ($t->price ?? 0);
            }
        }

        // Round totals
        $totalRevenue = round($totalRevenue, 2);
        foreach ($bySource as &$row) $row['revenue'] = round($row['revenue'], 2);
        foreach ($byCashier as &$row) $row['revenue'] = round($row['revenue'], 2);
        foreach ($byTicketType as &$row) $row['revenue'] = round($row['revenue'], 2);
        unset($row);

        return $this->success([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'currency' => $orders->first()?->currency ?? 'RON',
            'totals' => [
                'orders' => $totalOrders,
                'tickets' => $totalTickets,
                'revenue' => $totalRevenue,
                'avg_order' => $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0,
            ],
            'by_source' => array_values($bySource),
            'by_ticket_type' => array_values($byTicketType),
            'by_cashier' => array_values($byCashier),
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
