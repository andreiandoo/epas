<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer\Leisure;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\BoatRental;
use App\Models\Event;
use App\Models\LeisureBoat;
use App\Models\LeisureOrderDeletionLog;
use App\Models\LeisureResourceLock;
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
use Illuminate\Support\Facades\Cache;
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

        // C2: categorii custom pentru gruparea tipurilor de bilete (afișare pe
        // pagina publica + organizator panel). Stocate in venue_config.ticket_categories
        // ca [{id, name, sort_order}]. Sortate after sort_order asc, name asc.
        $venueConfig = is_array($eventModel->venue_config ?? null) ? $eventModel->venue_config : [];
        $ticketCategories = is_array($venueConfig['ticket_categories'] ?? null) ? $venueConfig['ticket_categories'] : [];
        usort($ticketCategories, function ($a, $b) {
            $sa = (int) ($a['sort_order'] ?? 0);
            $sb = (int) ($b['sort_order'] ?? 0);
            if ($sa !== $sb) return $sa <=> $sb;
            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

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
            'ticket_categories' => $ticketCategories,
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
                        // Variant label pentru afisare in POS breakdown
                        // ("Adult — 1h" in loc de doar "Adult").
                        $variantLabel = null;
                        if (!empty($row['variant_id']) && is_array($compTt->meta['variants'] ?? null)) {
                            foreach ($compTt->meta['variants'] as $cv) {
                                if (!is_array($cv) || empty($cv['label'])) continue;
                                $cvid = $cv['id'] ?? \Illuminate\Support\Str::slug($cv['label']);
                                if ($cvid === $row['variant_id']) {
                                    $variantLabel = $cv['label'];
                                    break;
                                }
                            }
                        }
                        $packageOutputs[] = [
                            'ticket_type_id' => (int) $row['ticket_type_id'],
                            'variant_id' => $row['variant_id'] ?? null,
                            'variant_label' => $variantLabel,
                            'qty' => $qty,
                            'component_name' => is_array($compTt->name) ? ($compTt->name['ro'] ?? reset($compTt->name)) : $compTt->name,
                            'component_unit_price' => $compPrice,
                            'service_category' => $compTt->effective_service_category ?? 'access',
                        ];
                    }
                }

                // Expun top-level field-urile pos_* + access_requirement + slots/inventory
                // ca POS frontend-ul (leisure-pos.php) si mobile app-ul sa filtreze
                // corect produsele si sa afiseze pretul POS. Frontend foloseste deja
                // t.pos_price direct (linia 130+ in leisure-pos.php) — fara expunere
                // explicita, valoarea era 'undefined' si toate produsele cu doar
                // pos_price setat erau ascunse din POS panel.
                $metaArr = is_array($tt->meta) ? $tt->meta : [];
                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'sku' => $tt->sku,
                    'price' => $price,
                    'price_max' => (float) ($tt->price_max ?? 0),
                    // Flag activ — folosit de POS ca sa filtreze produsele dezactivate din grid.
                    // Fara asta, produs cu is_active=false + pos_price setat apare in grid POS.
                    'is_active' => (bool) $tt->is_active,
                    'service_category' => $tt->effective_service_category,
                    'is_parking' => (bool) $tt->is_parking,
                    'requires_vehicle_info' => (bool) $tt->requires_vehicle_info,
                    'daily_capacity' => $tt->daily_capacity,
                    'ticket_group' => $tt->ticket_group,
                    // Cantitatea minima/maxima pe comanda — folosita de POS ca
                    // pe primul click sa punem in cos direct min_per_order
                    // (ex: bilet grup min=8 => click = 8 in cos), nu 1.
                    'min_per_order' => (int) ($tt->min_per_order ?? 1),
                    'max_per_order' => (int) ($tt->max_per_order ?? 0),
                    'issuing_company' => $tt->effective_issuing_company,
                    'issuing_explicit' => (bool) $tt->issuing_company,
                    'meta' => $metaArr ?: (object) [],
                    'variants' => $variants,
                    'addons' => $addons,
                    'package_outputs' => $packageOutputs,
                    'package_components_sum' => round($packageSum, 2),
                    'package_savings' => $packageOutputs ? round($packageSum - $price, 2) : 0,
                    // Top-level mirror pentru meta-uri folosite la POS gating + UI
                    'pos_price' => (isset($metaArr['pos_price']) && $metaArr['pos_price'] !== '' && $metaArr['pos_price'] !== null)
                        ? (float) $metaArr['pos_price'] : null,
                    'pos_only' => (bool) ($metaArr['pos_only'] ?? false),
                    'is_child_ticket' => (bool) ($metaArr['is_child_ticket'] ?? false),
                    'access_requirement' => in_array($metaArr['access_requirement'] ?? null, ['none','any','adult_only'], true)
                        ? $metaArr['access_requirement']
                        : 'none',
                    'slots_config' => is_array($metaArr['slots_config'] ?? null) ? $metaArr['slots_config'] : null,
                    'physical_inventory' => is_array($metaArr['physical_inventory'] ?? null) ? $metaArr['physical_inventory'] : null,
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
            // C2: categorii custom de afișare pentru tipurile de bilete (id, name, sort_order)
            'ticket_categories',
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
            'visit_from' => 'nullable|date',
            'visit_to' => 'nullable|date|after_or_equal:visit_from',
            'status' => 'nullable|in:valid,used,cancelled,refunded',
            'checkin' => 'nullable|in:checked_in,not_checked_in',
            'ticket_type_id' => 'nullable|string',
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
        $status = $validated['status'] ?? null;
        $checkinFilter = $validated['checkin'] ?? null;
        // ticket_type_id supports multiple ids as a comma-separated list (e.g. "5,7").
        $ticketTypeIds = [];
        if (!empty($validated['ticket_type_id'])) {
            $ticketTypeIds = array_values(array_filter(array_map('intval', explode(',', $validated['ticket_type_id']))));
        }
        $visitFrom = isset($validated['visit_from']) ? Carbon::parse($validated['visit_from'])->toDateString() : null;
        $visitTo = isset($validated['visit_to']) ? Carbon::parse($validated['visit_to'])->toDateString() : null;

        // Base scope: paid/completed tickets for this event within the PAYMENT window.
        // PARTICIPANTS = default "access" tickets (service_category NULL or 'access').
        // Cand user selecteaza EXPLICIT un tip din alta categorie (ex: parcare zona
        // noapte), scoatem restrictia access-only ca sa poata vedea si acele bilete.
        // Fara asta, dropdown-ul "Tip bilet" nu poate include parking/activity/rental.
        $accessOnlyBase = empty($ticketTypeIds);
        $base = \App\Models\Ticket::query()
            ->whereHas('order', fn ($q) => $q
                ->where('event_id', $eventModel->id)
                ->whereIn('status', ['completed', 'paid'])
                ->whereBetween('paid_at', [$from, $to]));
        if ($accessOnlyBase) {
            $base->whereHas('ticketType', fn ($q) => $q->byServiceCategory('access'));
        }

        // Header filters (status / ticket type / visit-date / search / checkin) —
        // applied to BOTH the paginated list and the stats query so the cards
        // match the view.
        $applyFilters = function ($q) use ($status, $checkinFilter, $ticketTypeIds, $visitFrom, $visitTo, $search) {
            if ($status) {
                $q->where('tickets.status', $status);
            } else {
                // Default: only active tickets count as participants (a cancelled
                // or refunded ticket is not a person on site). An explicit status
                // filter (e.g. 'cancelled') still overrides this.
                $q->whereIn('tickets.status', ['valid', 'used']);
            }
            if ($checkinFilter === 'checked_in') {
                $q->whereNotNull('tickets.checked_in_at');
            } elseif ($checkinFilter === 'not_checked_in') {
                $q->whereNull('tickets.checked_in_at');
            }
            if (!empty($ticketTypeIds)) {
                $q->whereIn('tickets.ticket_type_id', $ticketTypeIds);
            }
            // Visit day matches the SAME derivation shown in the table:
            // meta.visit_date first, then the ticket type's valid_date. LEFT(...,10)
            // + ::date keeps it robust against ISO strings carrying a time component
            // and avoids cast errors on empty values (NULLIF -> null -> fallback).
            if ($visitFrom || $visitTo) {
                $visitExpr = "COALESCE(NULLIF(LEFT(tickets.meta->>'visit_date', 10), '')::date, "
                    . "(select vt.valid_date::date from ticket_types vt where vt.id = tickets.ticket_type_id))";
                if ($visitFrom) {
                    $q->whereRaw("{$visitExpr} >= ?::date", [$visitFrom]);
                }
                if ($visitTo) {
                    $q->whereRaw("{$visitExpr} <= ?::date", [$visitTo]);
                }
            }
            if ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('code', 'ilike', "%{$search}%")
                      ->orWhere('barcode', 'ilike', "%{$search}%")
                      ->orWhereHas('order', fn ($oq) => $oq
                          ->where('customer_name', 'ilike', "%{$search}%")
                          ->orWhere('customer_email', 'ilike', "%{$search}%"));
                });
            }
        };

        $query = (clone $base)->with([
            'order:id,order_number,customer_name,customer_email,customer_phone,paid_at,status,total,meta',
            'ticketType:id,name,service_category,issuing_company,valid_date',
        ]);
        $applyFilters($query);
        $paginator = $query->orderByDesc('id')->paginate($perPage);

        // Aggregare stats (total, checked-in, no-show) pe ACELASI filter.
        $statsQuery = clone $base;
        $applyFilters($statsQuery);
        $totalTickets = (clone $statsQuery)->count();
        $checkedIn = (clone $statsQuery)->whereNotNull('checked_in_at')->count();
        $rate = $totalTickets > 0 ? round($checkedIn / $totalTickets * 100, 1) : 0;

        $rows = $paginator->getCollection()->map(function ($t) {
            $metaVisit = is_array($t->meta ?? null) ? ($t->meta['visit_date'] ?? null) : null;
            $visit = $metaVisit
                ?? optional($t->ticketType?->valid_date)->toDateString()
                ?? optional($t->order?->paid_at)->toDateString();
            $orderMeta = is_array($t->order?->meta ?? null) ? $t->order->meta : [];
            $vehiclePlate = $orderMeta['vehicle_plate'] ?? null;
            return [
                'id' => $t->id,
                'code' => $t->code,
                'barcode' => $t->barcode,
                'order_number' => $t->order->order_number ?? null,
                'order_paid_at' => optional($t->order?->paid_at)->toIso8601String(),
                'customer_name' => $t->order->customer_name ?? null,
                'customer_email' => $t->order->customer_email ?? null,
                'vehicle_plate' => $vehiclePlate,
                'ticket_type' => $t->ticketType->name ?? null,
                'ticket_type_id' => $t->ticket_type_id,
                'service_category' => $t->ticketType->service_category ?? 'access',
                'issuing_company' => $t->ticketType->issuing_company ?? 'primary',
                'visit_date' => $visit,
                'status' => $t->status,
                'checked_in_at' => optional($t->checked_in_at)->toIso8601String(),
            ];
        });

        // Ticket types of this event — populates the "Tip bilet" filter dropdown.
        // Includem TOATE tipurile (nu doar access), sa acopere si parking/activity/
        // rental. Base scope se comuta pe access-only doar cand niciun tip nu e
        // selectat; cand user selecteaza expres un tip parking, il gaseste.
        $ticketTypes = $eventModel->ticketTypes()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'service_category'])
            ->map(fn ($tt) => [
                'id' => $tt->id,
                'name' => is_array($tt->name)
                    ? ($tt->name['ro'] ?? $tt->name['en'] ?? (reset($tt->name) ?: ('#' . $tt->id)))
                    : ($tt->name ?? ('#' . $tt->id)),
                'service_category' => $tt->service_category ?? 'access',
            ])
            ->values();

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
            'ticket_types' => $ticketTypes,
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

        // Cache 5 min + limite ridicate. Pt Sf. Ana 90 zile (~50k tickets),
        // procesarea Eloquent in-memory depaseste Cloudflare 100s timeout.
        // Fix: 3 queries SQL agregate (buckets, by_payment_method, by_ticket_type)
        // fara load-uire in Eloquent, sub 5s pt orice range.
        $cacheKey = "leisure_sales_timeline_v2_{$eventModel->id}_{$from->format('Y-m-d')}_{$to->format('Y-m-d')}_{$groupBy}";
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached !== null) return $this->success($cached);
        @set_time_limit(120);
        @ignore_user_abort(true);

        // SQL expression pt bucket-key in functie de groupBy (Postgres)
        $bucketExpr = match ($groupBy) {
            'month' => "TO_CHAR(o.paid_at, 'YYYY-MM')",
            'week'  => "TO_CHAR(o.paid_at, 'IYYY-IW')",
            default => "TO_CHAR(o.paid_at, 'YYYY-MM-DD')",
        };
        // Effective price = price - discount_amount (from ticket meta). Fallback
        // proportional-per-order e ignorat aici (rare/legacy); acopera >99% cazuri.
        $effectivePriceExpr = "GREATEST(0, COALESCE(t.price, 0) - COALESCE(NULLIF(t.meta->>'discount_amount', '')::numeric, 0))";
        // Filtre pe bilete NErefundate + fara componente pachet + price>0 (mirror PHP)
        $isRevenueTicket = "t.status NOT IN ('cancelled','refunded')";
        $isTransactionTicket = "$isRevenueTicket AND (t.meta->>'from_package') IS DISTINCT FROM 'true' AND COALESCE(t.price, 0) > 0";
        $isVisitorTicket = "$isRevenueTicket AND (t.meta->>'is_package_umbrella') IS DISTINCT FROM 'true'";

        // Query 1: buckets — orders per bucket + revenue + tickets(tranzactii) + visitors
        $bucketsRaw = \DB::select(
            "SELECT
                $bucketExpr AS bucket,
                COUNT(DISTINCT o.id) AS orders_count,
                COALESCE(SUM(CASE WHEN $isRevenueTicket THEN $effectivePriceExpr ELSE 0 END), 0) AS revenue,
                COUNT(CASE WHEN $isVisitorTicket THEN t.id END) AS visitors,
                COUNT(CASE WHEN $isTransactionTicket THEN t.id END) AS tickets_count
             FROM orders o
             LEFT JOIN tickets t ON t.order_id = o.id
             WHERE o.event_id = ? AND o.status IN ('completed','paid') AND o.paid_at BETWEEN ? AND ?
             GROUP BY bucket
             ORDER BY bucket",
            [$eventModel->id, $from, $to]
        );

        $buckets = [];
        $totalRevenue = 0.0;
        $totalTickets = 0;
        $totalOrders = 0;
        foreach ($bucketsRaw as $row) {
            $rev = round((float) $row->revenue, 2);
            $buckets[] = [
                'date' => $row->bucket,
                'orders' => (int) $row->orders_count,
                'tickets' => (int) $row->tickets_count,
                'visitors' => (int) $row->visitors,
                'revenue' => $rev,
            ];
            $totalRevenue += $rev;
            $totalOrders += (int) $row->orders_count;
            $totalTickets += (int) $row->tickets_count;
        }

        // Query 2: by_payment_method (cash / card / online)
        $paymentExpr = "CASE
                WHEN o.source = 'pos' AND (o.meta->>'payment_method') = 'cash' THEN 'cash'
                WHEN o.source = 'pos' AND (o.meta->>'payment_method') = 'card' THEN 'card'
                ELSE 'online' END";
        $pmRaw = \DB::select(
            "SELECT
                $paymentExpr AS method,
                COUNT(DISTINCT o.id) AS orders_count,
                COUNT(CASE WHEN $isTransactionTicket THEN t.id END) AS tickets_count,
                COALESCE(SUM(CASE WHEN $isRevenueTicket THEN $effectivePriceExpr ELSE 0 END), 0) AS revenue
             FROM orders o
             LEFT JOIN tickets t ON t.order_id = o.id
             WHERE o.event_id = ? AND o.status IN ('completed','paid') AND o.paid_at BETWEEN ? AND ?
             GROUP BY method",
            [$eventModel->id, $from, $to]
        );
        $byPaymentMethod = array_map(fn ($r) => [
            'method' => $r->method,
            'orders' => (int) $r->orders_count,
            'tickets' => (int) $r->tickets_count,
            'revenue' => round((float) $r->revenue, 2),
        ], $pmRaw);

        // Query 3: by_ticket_type + by_category (join tickets + ticket_types)
        // Grupam pe (ticket_type_id, label_override) ca sa separam guide bonus.
        $ttRaw = \DB::select(
            "SELECT
                t.ticket_type_id,
                COALESCE(tt.service_category, 'access') AS service_category,
                tt.name AS tt_name,
                (t.meta->>'label_override') AS label_override,
                COUNT(t.id) AS tickets_count,
                COALESCE(SUM($effectivePriceExpr), 0) AS revenue
             FROM orders o
             INNER JOIN tickets t ON t.order_id = o.id
             LEFT JOIN ticket_types tt ON tt.id = t.ticket_type_id
             WHERE o.event_id = ? AND o.status IN ('completed','paid') AND o.paid_at BETWEEN ? AND ?
               AND $isTransactionTicket
             GROUP BY t.ticket_type_id, tt.service_category, tt.name, (t.meta->>'label_override')",
            [$eventModel->id, $from, $to]
        );

        $byCategory = [];
        $byTicketType = [];
        foreach ($ttRaw as $row) {
            $ttId = $row->ticket_type_id;
            $cat = $row->service_category ?? 'access';
            $labelOverride = $row->label_override ?? null;

            // Nume - JSON translatable (jsonb text): decode + fallback ro
            $rawName = $row->tt_name;
            if ($rawName && str_starts_with($rawName, '{')) {
                $decoded = json_decode($rawName, true);
                if (is_array($decoded)) $rawName = $decoded['ro'] ?? reset($decoded);
            }
            $rawName = $rawName ?: "Tip #{$ttId}";

            $ttKey = $labelOverride ? ('lbl_' . $labelOverride) : (
                $cat === 'package' ? ('pkg_' . $ttId) : ('tt_' . $ttId)
            );
            $name = $labelOverride ?: $rawName;

            if (!isset($byTicketType[$ttKey])) {
                $byTicketType[$ttKey] = [
                    'ticket_type_id' => $ttId,
                    'name' => $name,
                    'service_category' => $cat,
                    'tickets' => 0,
                    'revenue' => 0.0,
                ];
            }
            $byTicketType[$ttKey]['tickets'] += (int) $row->tickets_count;
            $byTicketType[$ttKey]['revenue'] += (float) $row->revenue;
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + (int) $row->tickets_count;
        }

        // Sortez desc dupa nr. bilete + rotunjire revenue
        usort($byTicketType, fn ($a, $b) => $b['tickets'] <=> $a['tickets']);
        foreach ($byTicketType as &$row) $row['revenue'] = round($row['revenue'], 2);
        unset($row);

        // Currency default (query rapida pt primul order in range)
        $currency = \DB::table('orders')
            ->where('event_id', $eventModel->id)
            ->whereIn('status', ['completed', 'paid'])
            ->whereBetween('paid_at', [$from, $to])
            ->value('currency') ?? 'RON';

        $payload = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'group_by' => $groupBy,
            'currency' => $currency,
            'rows' => $buckets,
            'totals' => [
                'orders' => $totalOrders,
                'tickets' => $totalTickets,
                'revenue' => round($totalRevenue, 2),
                'avg_order' => $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0,
            ],
            'by_category' => $byCategory,
            'by_ticket_type' => array_values($byTicketType),
            'by_payment_method' => $byPaymentMethod,
        ];
        \Illuminate\Support\Facades\Cache::put($cacheKey, $payload, 300);
        return $this->success($payload);
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

        // Bilete azi — 2 metrici distincte, consistente cu chart-ul din sales-timeline:
        //  - sold_today: TRANZACTII cu valoare (umbrella pachet + bilete individuale)
        //    EXCLUDE componentele pachet (from_package=true) + bilete pret=0 (bonus/free)
        //  - visitors_today: bilete FIZICE scanabile la poarta (componente + individuale + bonus)
        //    EXCLUDE umbrella pachet (nu se scaneaza, componentele lui da)
        // Fara distinctia asta cardul afisa 420 (toate biletele) iar chart-ul 312/394 → discordanta.
        $todayTicketsBase = \App\Models\Ticket::query()
            ->whereHas('order', fn ($q) => $q
                ->where('event_id', $eventModel->id)
                ->whereIn('status', ['paid', 'completed'])
                ->whereBetween('paid_at', [$todayStart, $todayEnd]))
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->get(['id', 'price', 'meta']);

        $todaySoldTickets = 0;
        $todayVisitors = 0;
        foreach ($todayTicketsBase as $t) {
            $meta = is_array($t->meta ?? null) ? $t->meta : [];
            $isFromPackage = !empty($meta['from_package']);
            $isUmbrella = !empty($meta['is_package_umbrella']);
            $price = (float) ($t->price ?? 0);
            // sold_today = tranzactii cu valoare (mirror filtru salesTimeline)
            if (!$isFromPackage && $price > 0) $todaySoldTickets++;
            // visitors_today = bilete scanabile fizic (exclus umbrella)
            if (!$isUmbrella) $todayVisitors++;
        }

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

        // Check-ins de STAFF (QR angajati permanent) in ultima ora, pentru
        // acelasi event. Alt tabel: leisure_staff_checkins.
        $recentStaffScans = \App\Models\LeisureStaffCheckin::query()
            ->where('event_id', $eventModel->id)
            ->where('checked_in_at', '>=', $hourAgo)
            ->with(['staffMember:id,first_name,last_name,position'])
            ->orderByDesc('checked_in_at')
            ->limit(50)
            ->get(['id', 'staff_member_id', 'event_id', 'location', 'checked_in_at']);

        $buckets = [];
        $bucketAdd = function ($ts) use (&$buckets) {
            if (!$ts) return;
            // Convertim la fus orar RO ca sa nu apara ora UTC pe grafic (timestamp
            // DB e UTC; server-ul poate rula in UTC — display trebuie RO).
            $tsRo = $ts->copy()->setTimezone('Europe/Bucharest');
            $bucketMinute = (int) floor($tsRo->minute / 5) * 5;
            $key = $tsRo->minute($bucketMinute)->second(0)->format('H:i');
            if (!isset($buckets[$key])) $buckets[$key] = ['time' => $key, 'count' => 0];
            $buckets[$key]['count']++;
        };
        foreach ($recentScans as $s) $bucketAdd($s->checked_in_at);
        foreach ($recentStaffScans as $s) $bucketAdd($s->checked_in_at);
        ksort($buckets);

        // Stream — combinăm orders recente (vânzări) + check-ins bilete + check-ins staff.
        // Sumarizam tipurile de bilete cumparate pe fiecare comanda ('2× Adult · 1× Elev').
        $recentOrders = Order::query()
            ->where('event_id', $eventModel->id)
            ->whereIn('status', ['paid', 'completed'])
            ->where('paid_at', '>=', $hourAgo)
            ->orderByDesc('paid_at')
            ->with(['tickets:id,order_id,ticket_type_id,status,meta', 'tickets.ticketType:id,name'])
            ->limit(20)
            ->get(['id', 'customer_name', 'total', 'currency', 'paid_at']);

        $stream = [];
        foreach ($recentOrders as $o) {
            // Agrega tipurile de bilete: 2× Adult · 1× Copil (ignora anulate + bonusuri fara pret,
            // dar pastreaza componentele pachetelor sub numele lor)
            $byType = [];
            foreach ($o->tickets as $t) {
                if (in_array($t->status, ['cancelled', 'refunded'], true)) continue;
                $rawName = null;
                if (is_array($t->meta ?? null) && !empty($t->meta['label_override'])) {
                    $rawName = $t->meta['label_override'];
                } else {
                    $rawName = $t->ticketType->name ?? 'Bilet';
                    if (is_array($rawName)) $rawName = $rawName['ro'] ?? reset($rawName);
                }
                if (!isset($byType[$rawName])) $byType[$rawName] = 0;
                $byType[$rawName]++;
            }
            arsort($byType);
            $ticketSummary = [];
            foreach (array_slice($byType, 0, 4, true) as $name => $qty) {
                $ticketSummary[] = $qty . '× ' . $name;
            }
            if (count($byType) > 4) $ticketSummary[] = '+' . (count($byType) - 4);
            $detail = ($ticketSummary ? implode(' · ', $ticketSummary) : 'Bilete') . ' · ' . number_format((float) $o->total, 2) . ' RON';

            $stream[] = [
                'type' => 'sale',
                'at' => optional($o->paid_at)->toIso8601String(),
                'ts' => optional($o->paid_at)->timestamp ?? 0,
                'label' => 'Vânzare nouă',
                'detail' => $detail,
                'order_id' => $o->id,
            ];
        }
        foreach ($recentScans as $s) {
            $stream[] = [
                'type' => 'scan',
                'at' => optional($s->checked_in_at)->toIso8601String(),
                'ts' => optional($s->checked_in_at)->timestamp ?? 0,
                'label' => 'Check-in bilet',
                'detail' => ($s->ticketType->name ?? 'Bilet') . ' · cod ' . ($s->code ?: '—'),
                'ticket_id' => $s->id,
            ];
        }
        foreach ($recentStaffScans as $s) {
            $sm = $s->staffMember;
            $staffName = $sm ? trim(($sm->first_name ?? '') . ' ' . ($sm->last_name ?? '')) : 'Angajat';
            if ($staffName === '') $staffName = 'Angajat';
            $position = $sm?->position;
            $stream[] = [
                'type' => 'staff_scan',
                'at' => optional($s->checked_in_at)->toIso8601String(),
                'ts' => optional($s->checked_in_at)->timestamp ?? 0,
                'label' => '👷 Pontaj angajat',
                'detail' => $staffName . ($position ? ' · ' . $position : '') . ($s->location ? ' · @ ' . $s->location : ''),
                'staff_id' => $s->staff_member_id,
            ];
        }
        usort($stream, fn ($a, $b) => ($b['ts'] ?? 0) <=> ($a['ts'] ?? 0));
        $stream = array_slice($stream, 0, 30);

        return $this->success([
            'now' => Carbon::now()->toIso8601String(),
            'stats' => [
                'sold_today' => $todaySoldTickets,
                'visitors_today' => $todayVisitors, // bilete fizice scanabile (mirror chart 'visitors')
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
            'items.*.slot_time' => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'items.*.start_time' => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'items.*.addons' => 'nullable|array|max:20',
            'items.*.addons.*.addon_id' => 'required_with:items.*.addons|string|max:64',
            'items.*.addons.*.qty' => 'required_with:items.*.addons|integer|min:0|max:500',
            'customer.name' => 'nullable|string|max:120',
            'customer.email' => 'nullable|email|max:120',
            'customer.phone' => 'nullable|string|max:30',
            'customer.vehicle_plate' => 'nullable|string|max:20',
            // Date firma (opțional) — pentru emitere factură pe persoană juridică
            'company.name' => 'nullable|string|max:200',
            'company.cui' => 'nullable|string|max:30',
            'company.reg_no' => 'nullable|string|max:60',
            'company.address' => 'nullable|string|max:255',
            'company.iban' => 'nullable|string|max:34',
            'company.contact_person' => 'nullable|string|max:120',
            'generate_invoice' => 'nullable|boolean',
            // Locale preferat al clientului (RO/EN/HU/...). Optional. Daca lipseste,
            // Order.locale ramane NULL si pipeline-ul foloseste 'ro' ca fallback.
            'locale' => 'nullable|string|max:8',
            'payment_method' => 'required|in:cash,card,invoice',
        ]);

        // Captura locale efectiv (whitelist din config) — zero risc de injectie.
        $availableLocales = config('locales.available', []);
        $posLocale = null;
        if (!empty($validated['locale']) && in_array($validated['locale'], $availableLocales, true)) {
            $posLocale = $validated['locale'];
        }

        // Guard: refuza vanzarea daca nu exista sesiune de casa deschisa.
        // Solicitat de operator: Sf. Ana vrea audit clar 'deschidere/inchidere
        // casa' + niciun POS sale in afara sesiunii deschise.
        $eventOrganizerIdForShift = $eventModel->marketplace_organizer_id ?? $organizer->id;
        $openSession = \App\Models\LeisureCashierSession::query()
            ->where('marketplace_organizer_id', $eventOrganizerIdForShift)
            ->where('event_id', $eventModel->id)
            ->whereNull('closed_at')
            ->orderByDesc('opened_at')
            ->first();
        if (!$openSession) {
            return $this->error('Casa este închisă. Deschide casa înainte de a face vânzări.', 403);
        }

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

        // F6 — validare asociere acces (1:1 între produs și bilet acces)
        // Calculează în cart:
        //   - count_access_any = total bilete acces (adult + copil)
        //   - count_access_adult = total bilete acces ADULT (is_child_ticket=false)
        //   - count_requires_any = total qty produse cu access_requirement='any'
        //   - count_requires_adult = total qty produse cu access_requirement='adult_only'
        $countAccessAny = 0;
        $countAccessAdult = 0;
        $countRequiresAny = 0;
        $countRequiresAdult = 0;
        foreach ($validated['items'] as $row) {
            $tt = $types->get($row['ticket_type_id']);
            if (!$tt) continue;
            $qty = (int) ($row['qty'] ?? 1);
            $cat = $tt->effective_service_category;
            $accessReq = $tt->meta['access_requirement'] ?? null;
            if (!in_array($accessReq, ['none', 'any', 'adult_only'], true)) {
                $accessReq = ($tt->requires_access_ticket ?? false) ? 'any' : 'none';
            }
            $isChild = (bool) ($tt->meta['is_child_ticket'] ?? false);
            if ($cat === 'access') {
                $countAccessAny += $qty;
                if (!$isChild) $countAccessAdult += $qty;
            }
            if ($accessReq === 'any') $countRequiresAny += $qty;
            if ($accessReq === 'adult_only') $countRequiresAdult += $qty;
        }
        if ($countRequiresAny > $countAccessAny) {
            return $this->error('Produse care necesită bilet acces (' . $countRequiresAny . ') dar în coș sunt doar ' . $countAccessAny . ' bilete acces.', 422);
        }
        if ($countRequiresAdult > $countAccessAdult) {
            return $this->error('Produse care necesită bilet acces ADULT (' . $countRequiresAdult . ') dar în coș sunt doar ' . $countAccessAdult . ' bilete acces adult.', 422);
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
            // Defensiv: blocheaza vanzarea de produse dezactivate (is_active=false),
            // chiar daca UI le-a lasat sa treaca (cache stale, request modificat).
            if ($tt->is_active === false) {
                $ttName = is_array($tt->name) ? ($tt->name['ro'] ?? reset($tt->name)) : $tt->name;
                return $this->error("Produsul „{$ttName}\" este dezactivat si nu poate fi vandut.", 422);
            }

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
            // F9: dacă produsul are pos_price configurat, folosim prețul POS în loc de cel online
            $posPrice = $tt->meta['pos_price'] ?? null;
            if ($variant === null && $posPrice !== null && $posPrice !== '') {
                $unit = (float) $posPrice;
            }

            $qty = (int) $row['qty'];

            // F3: slot booking (Vaporașe etc.)
            $slotsConfig = is_array($tt->meta['slots_config'] ?? null) ? $tt->meta['slots_config'] : null;
            $slotTime = null;
            $slotMeta = null;
            if ($slotsConfig && !empty($slotsConfig['enabled'])) {
                $slotTime = $row['slot_time'] ?? null;
                if (!$slotTime) {
                    return $this->error('Lipsește slot_time pentru produsul "' . (is_array($tt->name) ? reset($tt->name) : $tt->name) . '"', 422);
                }
                $capacity = max(1, (int) ($slotsConfig['capacity_per_slot'] ?? 1));
                $perSlot = ($slotsConfig['unit_pricing'] ?? 'per_person') === 'per_slot';
                $consume = $perSlot ? $capacity : $qty;
                // Sumă rezervări existente pe acel slot+dată
                $soldOnSlot = (int) OrderItem::query()
                    ->where('ticket_type_id', $tt->id)
                    ->whereHas('order', fn ($q) => $q->whereIn('status', ['paid', 'completed', 'pending']))
                    ->whereRaw("meta->>'visit_date' = ?", [$visitDate])
                    ->whereRaw("meta->>'slot_time' = ?", [$slotTime])
                    ->sum('quantity');
                if ($soldOnSlot + $consume > $capacity) {
                    return $this->error('Cursa de la ' . $slotTime . ' este aproape plină (rămân ' . max(0, $capacity - $soldOnSlot) . ' locuri).', 422);
                }
                $slotMeta = [
                    'slot_time' => $slotTime,
                    'duration_minutes' => (int) ($slotsConfig['duration_minutes'] ?? 30),
                    'unit_pricing' => $slotsConfig['unit_pricing'] ?? 'per_person',
                ];
            }

            // F5: physical inventory lock (Bărci etc.)
            $physical = is_array($tt->meta['physical_inventory'] ?? null) ? $tt->meta['physical_inventory'] : null;
            $physicalMeta = null;
            if ($physical && !empty($physical['enabled'])) {
                $startTime = $row['start_time'] ?? $row['slot_time'] ?? null;
                $duration = (int) ($variant['duration_minutes'] ?? $tt->service_duration_minutes ?? 60);
                if (!$startTime) {
                    return $this->error('Lipsește start_time / slot_time pentru rezervare interval pe "' . (is_array($tt->name) ? reset($tt->name) : $tt->name) . '"', 422);
                }
                $start = Carbon::parse($visitDate . ' ' . $startTime);
                $end = $start->copy()->addMinutes($duration);
                $count = max(1, (int) ($physical['count'] ?? 1));
                $availableUnits = LeisureResourceLock::availableForInterval($tt->id, $count, $start, $end);
                if ($availableUnits < $qty) {
                    return $this->error('Doar ' . $availableUnits . ' unități fizice libere în intervalul ' . $start->format('H:i') . '-' . $end->format('H:i') . '.', 422);
                }
                $physicalMeta = [
                    'start_at' => $start->toIso8601String(),
                    'end_at' => $end->toIso8601String(),
                    'start_time' => $startTime,
                    'duration_minutes' => $duration,
                    'physical_inventory' => true,
                ];
            }

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
                'slot' => $slotMeta,
                'physical' => $physicalMeta,
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
                'locale' => $posLocale,
                'status' => $paymentMethod === 'invoice' ? 'pending' : 'paid',
                'payment_status' => $paymentMethod === 'invoice' ? 'pending' : 'paid',
                'payment_processor' => 'pos',
                'payment_reference' => 'pos-' . $paymentMethod,
                'paid_at' => $paymentMethod === 'invoice' ? null : $now,
                'source' => 'pos',
                'meta' => array_merge([
                    'pos' => true,
                    'payment_method' => $paymentMethod,
                    'visit_date' => $visitDate,
                    'vehicle_plate' => $validated['customer']['vehicle_plate'] ?? null,
                    'cashier_organizer_id' => $organizer->id,
                    // Sesiunea de casa activa in momentul vanzarii — pentru audit
                    // 'ce s-a vandut in sesiunea X'. Guard-ul de mai sus a asigurat
                    // ca $openSession exista.
                    'cashier_session_id' => $openSession->id,
                    // Team member care a operat POS-ul (pentru raport per operator).
                    // Detectat din numele token-ului sanctum: 'team-member-{id}'.
                    // NULL cand loginul e ownership direct pe organizer.
                    'cashier_team_member_id' => (function () use ($request) {
                        $tokenName = $request->user()?->currentAccessToken()?->name ?? '';
                        if (str_starts_with($tokenName, 'team-member-')) {
                            return (int) str_replace('team-member-', '', $tokenName);
                        }
                        return null;
                    })(),
                    'commission_total' => $commissionTotal,
                    'commission_rate' => $commissionRate,
                    'commission_fixed' => $commissionFixed,
                    'commission_mode' => $commissionMode,
                ], (!empty($validated['company']['cui']) || !empty($validated['company']['name'])) ? [
                    // Date firma client (pentru factura B2B). Doar daca operatorul le-a introdus.
                    'company_billing' => array_filter([
                        'name' => $validated['company']['name'] ?? null,
                        'cui' => $validated['company']['cui'] ?? null,
                        'reg_no' => $validated['company']['reg_no'] ?? null,
                        'address' => $validated['company']['address'] ?? null,
                        'iban' => $validated['company']['iban'] ?? null,
                        'contact_person' => $validated['company']['contact_person'] ?? null,
                    ], fn ($v) => $v !== null && $v !== ''),
                    'invoice_requested' => !empty($validated['generate_invoice']),
                ] : []),
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
                        'slot_time' => $it['slot'] ? ($it['slot']['slot_time'] ?? null) : null,
                        'slot_duration' => $it['slot'] ? ($it['slot']['duration_minutes'] ?? null) : null,
                        'physical' => $it['physical'] ?? null,
                    ]),
                ]);

                // F5: creează lock-ul fizic dacă produsul are physical_inventory
                if (!empty($it['physical']) && is_array($it['physical'])) {
                    LeisureResourceLock::create([
                        'event_id' => $eventModel->id,
                        'ticket_type_id' => $tt->id,
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'start_at' => $it['physical']['start_at'],
                        'end_at' => $it['physical']['end_at'],
                        'qty' => $it['qty'],
                        'status' => 'active',
                    ]);
                }

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
                            'locale' => $posLocale,
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
                                    'locale' => $posLocale,
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
                            'locale' => $posLocale,
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

                    // Guide bonus: pentru bilete de grup cu group_includes_guide, emitem 1 SINGUR
                    // bilet gratuit (ghid) pe order daca cantitatea >= minim, indiferent de multipli.
                    // Vechea logica intdiv(qty/min) genera N ghizi (3 la qty=24 min=8), dar 1 ghid
                    // coordoneaza tot grupul indiferent de marime. Mirror comportament
                    // CheckoutController pentru consistenta customer + POS.
                    if (
                        !empty($tt->meta['is_group_ticket'])
                        && !empty($tt->meta['group_includes_guide'])
                    ) {
                        $minPerGroup = max(1, (int) ($tt->min_per_order ?? 1));
                        $bonusCount = ((int) $it['qty']) >= $minPerGroup ? 1 : 0;
                        $guideLabel = trim((string) ($tt->meta['group_guide_label'] ?? '')) ?: 'Ghid grup';
                        for ($g = 0; $g < $bonusCount; $g++) {
                            $codeBonus = strtoupper(Str::random(10));
                            $bonusTicket = Ticket::create([
                                'order_id' => $order->id,
                                'order_item_id' => $orderItem->id,
                                'ticket_type_id' => $tt->id,
                                'event_id' => $eventModel->id,
                                'tenant_id' => $eventModel->tenant_id,
                                'marketplace_client_id' => $marketplace->id,
                                'code' => $codeBonus,
                                'barcode' => $codeBonus,
                                'locale' => $posLocale,
                                'status' => $paymentMethod === 'invoice' ? 'pending' : 'valid',
                                'price' => 0,
                                'attendee_name' => $validated['customer']['name'] ?? null,
                                'attendee_email' => $validated['customer']['email'] ?? null,
                                'meta' => array_filter([
                                    'pos' => true,
                                    'visit_date' => $visitDate,
                                    'service_category' => $tt->service_category ?? 'access',
                                    'issuing_company' => $tt->issuing_company ?? 'primary',
                                    'guide_bonus' => true,
                                    'label_override' => $guideLabel,
                                    'parent_ticket_type_id' => $tt->id,
                                ]),
                            ]);
                            $issued[] = [
                                'id' => $bonusTicket->id,
                                'code' => $bonusTicket->code,
                                'ticket_type' => $guideLabel,
                                'service_category' => $tt->service_category ?? 'access',
                                'price' => 0,
                                'variant' => null,
                                'guide_bonus' => true,
                            ];
                        }
                    }
                }
            }

            // Rezervare numar factura secvential — DOAR daca operatorul a bifat
            // "Genereaza factura fiscala" + a introdus date firma. Determinam
            // AUTOMAT societatea emitenta din items: cand TOATE items sunt pe SC2,
            // rezervam pe secondary; altfel (mix sau SC1 only) → primary. Consistent
            // cu logica din pos-printer.js si generateInvoice() endpoint.
            // Fara asta, factura tiparita afisa un numar random din order.order_number.
            // Se salveaza in order.meta.invoice_number + invoice_company pentru
            // idempotenta si tras in raspunsul JSON pentru pos-printer.
            if (
                !empty($validated['generate_invoice'])
                && (!empty($validated['company']['cui']) || !empty($validated['company']['name']))
            ) {
                try {
                    // Detecteaza societatea emitenta din items
                    $hasAnyPrimary = false;
                    $hasAnySecondary = false;
                    foreach ($items as $it) {
                        $c = $it['ticket_type']->effective_issuing_company ?? 'primary';
                        if ($c === 'secondary') $hasAnySecondary = true;
                        else $hasAnyPrimary = true;
                    }
                    $invoiceCompany = ($hasAnySecondary && !$hasAnyPrimary && $organizer->has_secondary_issuer)
                        ? 'secondary'
                        : 'primary';

                    $invoiceNumber = $organizer->reserveNextInvoiceNumber($invoiceCompany);
                    $updatedMeta = is_array($order->meta) ? $order->meta : [];
                    $updatedMeta['invoice_number'] = $invoiceNumber;
                    $updatedMeta['invoice_company'] = $invoiceCompany;
                    $updatedMeta['invoice_issued_at'] = Carbon::now()->toIso8601String();
                    $order->meta = $updatedMeta;
                    $order->save();
                } catch (\Throwable $e) {
                    // Nu blocam vanzarea daca reservation fails — factura poate fi
                    // emisa manual prin generateInvoice endpoint. Log doar.
                    \Log::warning('[posSale] invoice reserve failed', [
                        'order_id' => $order->id, 'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Eroare la procesarea vânzării: ' . $e->getMessage(), 500);
        }

        // Datele pentru chitanță 80mm
        $issuer = $organizer->getIssuerData('primary');
        $issuerSecondary = $organizer->has_secondary_issuer ? $organizer->getIssuerData('secondary') : null;

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
            'company_billing' => is_array($order->meta['company_billing'] ?? null) ? $order->meta['company_billing'] : null,
            'invoice_requested' => (bool) ($order->meta['invoice_requested'] ?? false),
            // invoice_number: pre-populat cand operatorul a bifat 'Genereaza factura fiscala'
            // + a introdus date firma. Reservat atomic din organizer.primary_last_invoice_number
            // (crescut cu 1 in tranzactie). Fara asta, numarul afisat pe factura era random
            // (order.order_number cu random 10 char). Serie PASTREAZA CAPS din DB.
            'invoice_number' => is_array($order->meta) ? ($order->meta['invoice_number'] ?? null) : null,
            'issuer' => $issuer,
            'issuer_secondary' => $issuerSecondary ?? null,
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
                    'issuing_company' => $it['ticket_type']->effective_issuing_company ?? 'primary',
                    'variant' => $it['variant'],
                    'addons' => $it['addons'] ?? [],
                    'addons_total' => $it['addons_total'] ?? 0,
                ];
            }, $items),
            'tickets' => $issued,
        ]);
    }

    /**
     * POST /marketplace-client/organizer/orders/{order}/generate-invoice
     *
     * Genereaza factura B2B pentru o comanda POS care a fost emisa cu date firma.
     * Foloseste order.meta.company_billing (CUI, denumire, adresa) ca destinatar
     * si datele organizatorului (primary issuer) ca emitent.
     *
     * Faza 1: marcam invoice_status='requested' + invoice_number generat secvential.
     * Faza 2 (TODO): genereaza PDF + integrare e-Factura ANAF prin EFacturaService.
     */
    /**
     * POST /marketplace-client/organizer/events/{event}/leisure/upload-image
     *
     * Upload imagine card pentru un produs leisure. Acceptat multipart cu campul
     * `image` (jpeg/png/webp, max 10MB). Returnam URL public pentru a fi salvat in
     * meta.image al TicketType-ului prin endpoint-ul existent /products/{id}.
     */
    public function uploadProductImage(Request $request, int $event): JsonResponse
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

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,webp|max:10240',
        ]);

        $path = $request->file('image')->store('events/' . $eventModel->id . '/products', 'public');
        $url = \Illuminate\Support\Facades\Storage::disk('public')->url($path);

        return $this->success([
            'path' => $path,
            'url' => $url,
        ], 'Imagine încărcată');
    }

    public function generateInvoice(Request $request, int $order): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $orderModel = Order::query()
            ->where('id', $order)
            ->where('marketplace_client_id', $marketplace->id)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$orderModel) {
            return $this->error('Order not found', 404);
        }

        $meta = is_array($orderModel->meta) ? $orderModel->meta : [];
        $company = $meta['company_billing'] ?? null;

        if (!is_array($company) || (empty($company['cui']) && empty($company['name']))) {
            return $this->error('Comanda nu are date firmă pentru emiterea unei facturi.', 422);
        }

        // Daca s-a generat deja o factura, returnam datele existente.
        if (!empty($meta['invoice_number'])) {
            return $this->success([
                'order_id' => $orderModel->id,
                'invoice_number' => $meta['invoice_number'],
                'invoice_status' => $meta['invoice_status'] ?? 'requested',
                'invoice_url' => $meta['invoice_url'] ?? null,
                'already_generated' => true,
            ]);
        }

        // Determinam societatea emitenta pe baza produselor din comanda.
        // Cand TOATE biletele au issuing_company='secondary' -> emitem pe SC2.
        // Altfel (mix sau doar primary) -> SC1. Consistent cu logica din
        // pos-printer.js care filtreaza items primary pentru factura fiscala.
        $orderModel->loadMissing('tickets.ticketType');
        $issuerCompany = 'primary';
        $tickets = $orderModel->tickets ?? collect();
        if ($tickets->isNotEmpty()) {
            $allSecondary = $tickets->every(function ($t) {
                $tt = $t->ticketType;
                if (!$tt) return false;
                return ($tt->issuing_company ?? 'primary') === 'secondary';
            });
            if ($allSecondary && $organizer->has_secondary_issuer) {
                $issuerCompany = 'secondary';
            }
        }

        // Rezervare atomica a numarului facturii pe societatea corecta.
        // Format: "SZAMEC-000002" (serie + numar padded). Foloseste
        // reserveNextInvoiceNumber ca sa evite race conditions si duplicate.
        $invoiceNumber = $organizer->reserveNextInvoiceNumber($issuerCompany);

        $issuer = $organizer->getIssuerData($issuerCompany) ?? [];

        $meta['invoice_number'] = $invoiceNumber;
        $meta['invoice_status'] = 'requested';
        $meta['invoice_issued_at'] = Carbon::now()->toIso8601String();
        $meta['invoice_issuer'] = $issuer;
        // invoice_url va fi populat de un job care genereaza PDF + trimite la e-Factura.
        // Pentru moment, link catre detaliul comenzii in panou admin.
        $meta['invoice_url'] = null;

        $orderModel->meta = $meta;
        $orderModel->save();

        return $this->success([
            'order_id' => $orderModel->id,
            'invoice_number' => $invoiceNumber,
            'invoice_status' => 'requested',
            'invoice_url' => null,
            'company_billing' => $company,
            'issuer' => $issuer,
            'message' => 'Factura ' . $invoiceNumber . ' a fost înregistrată. Generarea PDF + transmitere la ANAF e-Factura se procesează automat.',
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
            'role' => 'required|in:gate_scanner,sales_operator,shift_manager,accountant,operator_boats,operator_pontoon,operator_pontoon_rental,operator_sled,operator_tow_validation,admin_mobile,field_seller',
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
            'role' => 'nullable|in:gate_scanner,sales_operator,shift_manager,accountant,operator_boats,operator_pontoon,operator_pontoon_rental,operator_sled,operator_tow_validation,admin_mobile,field_seller',
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

        // C2: permite resetarea explicită a ticket_group la NULL (atunci când
        // utilizatorul alege "— Alte produse —" în modal). validateProduct
        // filtrează NULL prin array_filter → re-aplicam manual câmpurile
        // nullable care vin cu null explicit din payload.
        if ($request->exists('ticket_group')) {
            $data['ticket_group'] = $request->input('ticket_group') ?: null;
        }

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
            'issuing_company' => 'nullable|in:primary,secondary,mix',
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
            'slots_config', 'physical_inventory',
            'pos_price', 'is_child_ticket', 'access_requirement', 'blocked_time_ranges',
            'pos_only',
            // Bilet de grup (Sf. Ana): meta pentru grupare + emitere bonus ghid.
            //  - is_group_ticket: bifa 'Bilet de grup'
            //  - group_includes_guide: emite +1 bilet gratuit ghid la cantitate minima
            //  - group_guide_label: nume bilet ghid (afisat pe cos + printat)
            //  - step_qty: incrementul cosului (+/-) pentru bilete de grup
            'is_group_ticket', 'group_includes_guide', 'group_guide_label', 'step_qty',
            // Multi-locale (B2): traduceri opt-in pe name/description/etc. + pe variants/addons.
            'translations',
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

        // Normalize pos_price + is_child_ticket + access_requirement
        if (isset($filtered['pos_price'])) {
            $filtered['pos_price'] = $filtered['pos_price'] !== null && $filtered['pos_price'] !== '' ? (float) $filtered['pos_price'] : null;
        }
        if (isset($filtered['is_child_ticket'])) {
            $filtered['is_child_ticket'] = (bool) $filtered['is_child_ticket'];
        }
        if (isset($filtered['pos_only'])) {
            $filtered['pos_only'] = (bool) $filtered['pos_only'];
        }
        if (isset($filtered['access_requirement'])) {
            $val = $filtered['access_requirement'];
            $filtered['access_requirement'] = in_array($val, ['none', 'any', 'adult_only'], true) ? $val : 'none';
        }

        // Normalize meta bilet de grup
        if (isset($filtered['is_group_ticket'])) $filtered['is_group_ticket'] = (bool) $filtered['is_group_ticket'];
        if (isset($filtered['group_includes_guide'])) $filtered['group_includes_guide'] = (bool) $filtered['group_includes_guide'];
        if (isset($filtered['group_guide_label'])) $filtered['group_guide_label'] = trim((string) $filtered['group_guide_label']);
        if (isset($filtered['step_qty'])) $filtered['step_qty'] = max(1, (int) $filtered['step_qty']);

        // Normalize blocked_time_ranges (F10 — informativ)
        if (isset($filtered['blocked_time_ranges']) && is_array($filtered['blocked_time_ranges'])) {
            $normalizedRanges = [];
            foreach ($filtered['blocked_time_ranges'] as $r) {
                if (!is_array($r) || empty($r['date']) || empty($r['start_time']) || empty($r['end_time'])) continue;
                $normalizedRanges[] = [
                    'date' => substr((string) $r['date'], 0, 10),
                    'start_time' => substr((string) $r['start_time'], 0, 5),
                    'end_time' => substr((string) $r['end_time'], 0, 5),
                    'reason' => isset($r['reason']) ? substr((string) $r['reason'], 0, 200) : null,
                ];
            }
            $filtered['blocked_time_ranges'] = $normalizedRanges;
        }

        // Normalize package_outputs (F4).
        // 'price' e alocarea per componenta pentru raportare pe societate emitenta
        // (folosit cand pachetul e Mix = ambele societati). Opțional; salvam doar
        // valori >= 0. Suma alocarilor NU e validata aici — validare vizuala pe UI
        // + backend nu blocam salvarea (organizatorul poate salva partial).
        if (isset($filtered['package_outputs']) && is_array($filtered['package_outputs'])) {
            $normalizedOutputs = [];
            foreach ($filtered['package_outputs'] as $row) {
                if (!is_array($row) || empty($row['ticket_type_id'])) continue;
                $entry = [
                    'ticket_type_id' => (int) $row['ticket_type_id'],
                    'variant_id' => !empty($row['variant_id']) ? (string) $row['variant_id'] : null,
                    'qty' => max(1, (int) ($row['qty'] ?? 1)),
                ];
                if (isset($row['price']) && is_numeric($row['price']) && (float) $row['price'] >= 0) {
                    $entry['price'] = round((float) $row['price'], 2);
                }
                $normalizedOutputs[] = $entry;
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
            'slots_config' => is_array($t->meta['slots_config'] ?? null) ? $t->meta['slots_config'] : null,
            'physical_inventory' => is_array($t->meta['physical_inventory'] ?? null) ? $t->meta['physical_inventory'] : null,
            'pos_price' => isset($t->meta['pos_price']) && $t->meta['pos_price'] !== '' ? (float) $t->meta['pos_price'] : null,
            'pos_only' => (bool) ($t->meta['pos_only'] ?? false),
            'is_child_ticket' => (bool) ($t->meta['is_child_ticket'] ?? false),
            'access_requirement' => in_array($t->meta['access_requirement'] ?? null, ['none', 'any', 'adult_only'], true)
                ? $t->meta['access_requirement']
                : ((bool) $t->requires_access_ticket ? 'any' : 'none'),
            'blocked_time_ranges' => is_array($t->meta['blocked_time_ranges'] ?? null) ? $t->meta['blocked_time_ranges'] : [],
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

        // Cache: pentru volume mari (Sf. Ana ~5000+ orders/luna) endpoint-ul poate
        // depasi timeout Cloudflare (100s). Cache 30 min pt request-uri repetate +
        // memory/time limit local. Cache key = event+range; invalidat automat cand
        // se schimba data.
        $cacheKey = "leisure_raport_v2_{$eventModel->id}_{$from->format('Y-m-d')}_{$to->format('Y-m-d')}";
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached !== null) {
            return $this->success($cached);
        }
        // Serve-stale: dacă avem cache expirat (versiune veche) il servim + trigger
        // recompute in background. Pentru moment, ridic limite agresiv la cold cache.
        @ini_set('memory_limit', '2048M');
        @set_time_limit(300);
        @ignore_user_abort(true);
        $timeStart = microtime(true);
        \Illuminate\Support\Facades\Log::info('[LeisureRaport] cold cache start', [
            'event' => $eventModel->id, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'),
        ]);

        // OPTIMIZARE MAJORA: preload TOATE ticket_types ale evenimentului ODATA in
        // memory (~50 records pt Sf Ana), apoi NU eager load ticketType pe fiecare
        // ticket (evita duplicare meta package_outputs pt 30k+ tickets).
        // Reduce memory de la ~5GB la ~200MB + iterare 10x mai rapida.
        $ttMap = TicketType::query()
            ->where('event_id', $eventModel->id)
            ->get(['id', 'name', 'service_category', 'issuing_company', 'meta'])
            ->keyBy('id');

        // Preload componentIssuerMap direct din ttMap (Mix packages)
        $componentIssuerMap = [];
        foreach ($ttMap as $tt) {
            if ($tt->service_category !== 'package' || ($tt->issuing_company ?? 'primary') !== 'mix') continue;
            $outputs = is_array($tt->meta ?? null) ? ($tt->meta['package_outputs'] ?? []) : [];
            foreach ($outputs as $out) {
                $cid = (int) ($out['ticket_type_id'] ?? 0);
                if (!$cid) continue;
                $ct = $ttMap->get($cid);
                if ($ct) $componentIssuerMap[$cid] = ($ct->issuing_company === 'secondary') ? 'secondary' : 'primary';
            }
        }

        $orders = Order::query()
            ->where('event_id', $eventModel->id)
            ->whereIn('status', ['completed', 'paid'])
            ->whereBetween('paid_at', [$from, $to])
            ->with(['tickets:id,order_id,ticket_type_id,price,status,meta'])
            ->get(['id', 'event_id', 'paid_at', 'status', 'total', 'currency', 'source', 'meta']);

        $byTicketType = [];   // Pachetul apare ca 1 tranzactie cu valoarea lui; componentele NU aici
        $byComponentType = []; // Bilete FIZICE emise: componente pachet + bilete individuale (fara parintele pachet)
        $bySource = [];
        $byCashier = [];
        $byPaymentMethod = []; // cash, card, online (Cash/Card = POS; Online = customer online)
        $byIssuer = [
            'primary' => ['issuer' => 'primary', 'tickets' => 0, 'revenue' => 0.0, 'commission' => 0.0,
                'by_payment' => ['cash' => 0.0, 'card' => 0.0, 'online' => 0.0]],
            'secondary' => ['issuer' => 'secondary', 'tickets' => 0, 'revenue' => 0.0, 'commission' => 0.0,
                'by_payment' => ['cash' => 0.0, 'card' => 0.0, 'online' => 0.0]],
        ];
        $totalRevenue = 0.0;
        $totalTickets = 0;
        $totalPhysicalTickets = 0; // real emise (fara parintele pachet)
        $totalOrders = $orders->count();
        $totalCommission = 0.0; // Comisioane cumulate (POS + customer)

        // Commission rate + floor pentru organizatorul evenimentului. Folosite pt
        // fallback cand order.meta.commission_total nu exista (comenzi vechi
        // sau ordonate de customer flow inainte de fix-ul din 2026-07).
        // FLOOR se aplica DOAR daca organizator.commission_use_floor === true
        // (opt-in explicit, ca sa nu impactam org-urile care au
        // fixed_commission_default setat cu alta intentie).
        $eventOrganizer = $eventModel->marketplace_organizer_id
            ? MarketplaceOrganizer::find($eventModel->marketplace_organizer_id)
            : $organizer;
        $orgRate = (float) ($eventOrganizer?->getEffectiveCommissionRate() ?? 5);
        $orgFloor = ($eventOrganizer?->commission_use_floor)
            ? (float) ($eventOrganizer?->fixed_commission_default ?? 0)
            : 0.0;

        // Pre-load numele team-member-ilor care au operat POS-ul (unici, un singur
        // query). Owner login (fara team member) apare ca 'InfoPoint' — statia
        // fizica de vanzare on-site.
        $tmIds = [];
        foreach ($orders as $o) {
            $tmId = $o->meta['cashier_team_member_id'] ?? null;
            if ($tmId) $tmIds[(int) $tmId] = true;
        }
        $tmNames = [];
        if (!empty($tmIds)) {
            \App\Models\MarketplaceOrganizerTeamMember::query()
                ->whereIn('id', array_keys($tmIds))
                ->get(['id', 'name'])
                ->each(function ($tm) use (&$tmNames) { $tmNames[$tm->id] = $tm->name; });
        }

        foreach ($orders as $o) {
            // Revenue = suma effective_price a biletelor NErefundate = ce a incasat efectiv
            // organizatorul (post-discount, exclusiv processing_fee + insurance + on-top commission).
            // NU folosim $o->total pentru ca include fee/insurance/on-top ce nu conteaza ca
            // "vanzare organizator" si NU reflecta discount-uri per-ticket (ex. promo code 100%
            // pe biletele elligible din cart mixt).
            $rev = 0.0;
            foreach ($o->tickets as $t) {
                if (in_array($t->status, ['cancelled', 'refunded'], true)) continue;
                $rev += method_exists($t, 'getEffectivePrice')
                    ? (float) $t->getEffectivePrice()
                    : (float) ($t->price ?? 0);
            }
            $rev = round($rev, 2);
            $totalRevenue += $rev;

            // Comisionul pe comanda:
            //  - meta.commission_total > 0 => e comision on-top scris de POS
            //    (prefereram valoarea snapshot ca sa nu re-calculam variabila)
            //  - meta.commission_total = 0 sau lipsa => e mod 'included' sau
            //    comanda customer online => recalculam per-bilet din pretul
            //    biletului aplicand floor-ul:  max(price * rate/100, floor)
            //    Asta face BACKTRACE automat: comenzi vechi (chiar fara meta)
            //    apar cu comision corect in raport, folosind config-ul curent
            //    al organizatorului.
            $orderCommission = 0.0;
            $posSnapshot = isset($o->meta['commission_total']) && is_numeric($o->meta['commission_total'])
                ? (float) $o->meta['commission_total']
                : null;
            if ($posSnapshot !== null && $posSnapshot > 0) {
                $orderCommission = $posSnapshot;
            } else {
                foreach ($o->tickets as $t) {
                    if (in_array($t->status, ['cancelled', 'refunded'], true)) continue;
                    // Comisionul se calculeaza pe SUMA REALA INCASATA (post-discount).
                    // Bilete cu effective_price=0 (bonus, componente pachet, sau full-discount) → fara comision.
                    $tp = method_exists($t, 'getEffectivePrice')
                        ? (float) $t->getEffectivePrice()
                        : (float) ($t->price ?? 0);
                    if ($tp <= 0) continue;
                    $pct = round($tp * $orgRate / 100, 2);
                    $orderCommission += $orgFloor > 0 ? max($pct, $orgFloor) : $pct;
                }
                $orderCommission = round($orderCommission, 2);
            }
            $totalCommission += $orderCommission;

            // Source: pos vs online
            $source = $o->source === 'pos' ? 'pos' : ($o->source ?: 'online');
            if ($source !== 'pos') $source = 'online';
            if (!isset($bySource[$source])) $bySource[$source] = ['source' => $source, 'orders' => 0, 'tickets' => 0, 'revenue' => 0.0, 'commission' => 0.0];
            $bySource[$source]['orders']++;
            $bySource[$source]['revenue'] += $rev;
            $bySource[$source]['commission'] += $orderCommission;

            // Metoda plata:
            //  - POS cash / card citite din meta.payment_method (posSale scrie)
            //  - POS 'invoice' (link email) -> tratam ca 'online' (banii vin online)
            //  - non-POS -> 'online'
            $posMethod = $o->meta['payment_method'] ?? null;
            if ($source === 'pos' && $posMethod === 'cash') $pmKey = 'cash';
            elseif ($source === 'pos' && $posMethod === 'card') $pmKey = 'card';
            else $pmKey = 'online';
            if (!isset($byPaymentMethod[$pmKey])) $byPaymentMethod[$pmKey] = ['method' => $pmKey, 'orders' => 0, 'tickets' => 0, 'revenue' => 0.0, 'commission' => 0.0];
            $byPaymentMethod[$pmKey]['orders']++;
            $byPaymentMethod[$pmKey]['revenue'] += $rev;
            $byPaymentMethod[$pmKey]['commission'] += $orderCommission;

            // Cashier (operator POS) — team_member_id (prioritate) sau organizer_id
            // (owner cand nu e team member). Cheia de grupare distinge intre ele
            // ca sa nu se combine team member 5 cu organizer 5.
            $cashierTmId = $o->meta['cashier_team_member_id'] ?? null;
            $cashierId = $o->meta['cashier_organizer_id'] ?? null;
            if ($cashierTmId) {
                $cashierKey = 'tm_' . $cashierTmId;
                $cashierLabel = $tmNames[$cashierTmId] ?? ('Angajat #' . $cashierTmId);
            } elseif ($cashierId) {
                // Vanzari facute direct de owner (login organizator, fara team
                // member) apar ca 'InfoPoint' — statia fizica de vanzare on-site.
                // Numele organizatorului nu are sens ca 'cine a vandut' aici.
                $cashierKey = 'infopoint_' . $cashierId;
                $cashierLabel = 'InfoPoint';
            } else {
                $cashierKey = 'online';
                $cashierLabel = 'Online (auto)';
            }
            if (!isset($byCashier[$cashierKey])) $byCashier[$cashierKey] = ['cashier_id' => $cashierTmId ?: $cashierId, 'cashier_label' => $cashierLabel, 'orders' => 0, 'tickets' => 0, 'revenue' => 0.0, 'commission' => 0.0];
            $byCashier[$cashierKey]['orders']++;
            $byCashier[$cashierKey]['revenue'] += $rev;
            $byCashier[$cashierKey]['commission'] += $orderCommission;

            foreach ($o->tickets as $t) {
                if (in_array($t->status, ['cancelled', 'refunded'], true)) continue;

                // Resolve ticket type din ttMap in loc de $t->ticketType (evita
                // hidratare Eloquent per-ticket + duplicare meta).
                $ttId = $t->ticket_type_id;
                $ttModel = $ttMap->get($ttId);

                // Clasificare bilet:
                //  - isFromPackage: componenta emisa din pachet (meta.from_package=true, price=0)
                //  - isPackageParent: parintele pachet (service_category='package', poarta pretul intreg)
                //  - Bilet individual normal (nici una, nici alta)
                $isFromPackage = is_array($t->meta ?? null) && !empty($t->meta['from_package']);
                $svcCat = $ttModel?->service_category ?? 'access';
                $isPackageParent = ($svcCat === 'package');

                $bySource[$source]['tickets']++;
                $byCashier[$cashierKey]['tickets']++;
                $byPaymentMethod[$pmKey]['tickets']++;

                $ttName = $ttModel?->name ?? "Tip #{$ttId}";
                if (is_array($ttName)) $ttName = $ttName['ro'] ?? reset($ttName);

                // Cazul components (guide bonus, package output): label_override
                if (is_array($t->meta ?? null) && !empty($t->meta['label_override'])) {
                    $ttName = $t->meta['label_override'];
                }

                // ---- Sectiunea "Per tip bilet" (contorizare TRANZACTII, nu bilete fizice)
                // Componentele pachetului nu apar aici (nu au valoare proprie).
                //
                // IMPORTANT: folosim getEffectivePrice() (nu ticket.price) ca sa reflectam
                // discount-ul din promo code aplicat pe order. Fara asta, o comanda cu
                // reducere 100% aparea in raport cu suma pre-discount (bilete de 101 lei
                // in loc de 0). Discount-ul e stocat in ticket.meta.discount_amount
                // (per-ticket, scris la checkout) sau ca fallback proportional din
                // order.subtotal/discount_amount pe biletele vechi.
                //
                // Filtru dublu:
                //  (a) skip biletele marcate cu meta.from_package=true (metoda POS noua)
                //  (b) defensiv: skip biletele cu effective_price=0 (legacy sau full-discount)
                //      pentru sectiunea de tranzactii — apar totusi la "Pe tip bilet emis"
                //      pentru a nu pierde inventarul emis.
                //  (c) Un bilet FULL-DISCOUNT (effective_price=0) reprezinta o tranzactie
                //      cu discount 100% — TREBUIE contorizat ca tranzactie (in tickets++)
                //      dar cu revenue=0. Verificam pe ticket.price sa fim consecvenți.
                $tp = (float) ($t->price ?? 0);
                $effectivePrice = method_exists($t, 'getEffectivePrice') ? $t->getEffectivePrice() : $tp;
                if (!$isFromPackage && $tp > 0) {
                    $totalTickets++;
                    $issuer = $ttModel?->issuing_company ?? 'primary';
                    $key = $isPackageParent ? "pkg_{$ttId}" : "tt_{$ttId}";
                    if (!isset($byTicketType[$key])) {
                        $byTicketType[$key] = [
                            'ticket_type_id' => $ttId,
                            'name' => $ttName,
                            'service_category' => $svcCat,
                            'issuing_company' => $issuer,
                            'tickets' => 0,
                            'revenue' => 0.0,
                            'commission' => 0.0,
                        ];
                    }
                    $byTicketType[$key]['tickets']++;
                    // Revenue post-discount (effective) pentru cifra reala incasata.
                    $byTicketType[$key]['revenue'] += $effectivePrice;
                    // Comision per bilet pe SUMA REALA INCASATA (nu pe pretul list).
                    // Cand biletul e full-discount (effective_price=0), comisionul = 0.
                    // Floor-ul se aplica DOAR daca biletul a fost incasat (effectivePrice > 0)
                    // — pe biletele complet reduse nu percepem floor (nu ai ce colecta din 0).
                    $ticketCommission = round($effectivePrice * $orgRate / 100, 2);
                    if ($orgFloor > 0 && $ticketCommission < $orgFloor && $effectivePrice > 0) {
                        $ticketCommission = $orgFloor;
                    }
                    $byTicketType[$key]['commission'] += $ticketCommission;

                    // Aggregare pe societate emitenta (SC1 primary / SC2 secondary).
                    // Pentru pachete cu issuer='mix': impartim venitul + comisionul intre
                    // societatile componente conform alocarilor din meta.package_outputs.
                    // Componentele fara alocare (price) sunt tratate proportional cu pretul
                    // lor de referinta * qty; daca nici asa nu se poate, cadem inapoi pe
                    // 'primary' pentru siguranta contabila.
                    $splits = [];
                    if ($isPackageParent && $issuer === 'mix') {
                        $outputs = is_array($ttModel?->meta ?? null) ? ($ttModel->meta['package_outputs'] ?? []) : [];
                        $allocSum = 0.0;
                        foreach ($outputs as $out) {
                            if (isset($out['price']) && is_numeric($out['price']) && (float) $out['price'] >= 0) {
                                $allocSum += (float) $out['price'];
                            }
                        }
                        if ($allocSum > 0) {
                            foreach ($outputs as $out) {
                                $cid = (int) ($out['ticket_type_id'] ?? 0);
                                $cIssuer = $componentIssuerMap[$cid] ?? 'primary';
                                $portion = isset($out['price']) ? (float) $out['price'] : 0.0;
                                if ($portion <= 0) continue;
                                // Alocarea din meta e in RATIO — o pastram pentru distribuire,
                                // dar suma efectiva o luam din effective_price mai jos.
                                $splits[] = ['issuer' => $cIssuer, 'amount' => $portion];
                            }
                        }
                    }
                    if (empty($splits)) {
                        // Fallback: single-issuer (comportament vechi)
                        $issuerKey = $issuer === 'secondary' ? 'secondary' : 'primary';
                        $splits[] = ['issuer' => $issuerKey, 'amount' => $tp > 0 ? $tp : 1];
                    }

                    // Distribute revenue + commission POST-DISCOUNT proportional pe alocarile
                    // originale (Mix pachete) sau integral pe single-issuer. Ratio-ul e din
                    // meta.package_outputs (proportion din pretul catalog), suma REALA vine
                    // din effectivePrice.
                    $splitTotal = array_sum(array_column($splits, 'amount'));
                    $biggest = null; $biggestAmt = -1;
                    foreach ($splits as $s) {
                        if ($s['amount'] > $biggestAmt) { $biggestAmt = $s['amount']; $biggest = $s['issuer']; }
                        $ratio = $splitTotal > 0 ? $s['amount'] / $splitTotal : 0;
                        $rev = $effectivePrice * $ratio;
                        $com = $ticketCommission * $ratio;
                        $byIssuer[$s['issuer']]['revenue'] += $rev;
                        $byIssuer[$s['issuer']]['commission'] += $com;
                        $byIssuer[$s['issuer']]['by_payment'][$pmKey] = ($byIssuer[$s['issuer']]['by_payment'][$pmKey] ?? 0) + $rev;
                    }
                    if ($biggest) $byIssuer[$biggest]['tickets']++;
                }

                // ---- Sectiunea "Pe tip bilet emis" (bilete FIZICE, componente + individuale)
                // Parintele pachet nu apare aici (nu e un bilet fizic real emis).
                if (!$isPackageParent) {
                    $totalPhysicalTickets++;
                    $compKey = is_array($t->meta ?? null) && !empty($t->meta['label_override'])
                        ? 'lbl_' . $ttName
                        : 'tt_' . $ttId;
                    if (!isset($byComponentType[$compKey])) {
                        $byComponentType[$compKey] = [
                            'ticket_type_id' => $ttId,
                            'name' => $ttName,
                            'service_category' => $svcCat,
                            'tickets' => 0,
                        ];
                    }
                    $byComponentType[$compKey]['tickets']++;
                }
            }
        }

        // Round totals
        $totalRevenue = round($totalRevenue, 2);
        $totalCommission = round($totalCommission, 2);
        foreach ($bySource as &$row) { $row['revenue'] = round($row['revenue'], 2); $row['commission'] = round($row['commission'] ?? 0, 2); }
        foreach ($byCashier as &$row) { $row['revenue'] = round($row['revenue'], 2); $row['commission'] = round($row['commission'] ?? 0, 2); }
        foreach ($byTicketType as &$row) { $row['revenue'] = round($row['revenue'], 2); $row['commission'] = round($row['commission'] ?? 0, 2); }
        foreach ($byPaymentMethod as &$row) { $row['revenue'] = round($row['revenue'], 2); $row['commission'] = round($row['commission'] ?? 0, 2); }
        foreach ($byIssuer as &$row) {
            $row['revenue'] = round($row['revenue'], 2);
            $row['commission'] = round($row['commission'], 2);
            foreach ($row['by_payment'] as $k => $v) {
                $row['by_payment'][$k] = round($v, 2);
            }
        }
        unset($row);
        // by_component_type sortat descrescator dupa nr. bilete
        usort($byComponentType, fn ($a, $b) => $b['tickets'] <=> $a['tickets']);

        // Numele reale societatilor + status TVA per societate. Folosite pe cardurile
        // din leisure-raport ca sa afiseze net + suma TVA cand societatea e pltitoare
        // de TVA. vat_rate default 19% cand pltitor dar rate necunoscut.
        $issuerNames = [
            'primary' => $eventOrganizer?->company_name ?: 'Societatea principală',
            'secondary' => $eventOrganizer?->has_secondary_issuer ? ($eventOrganizer?->secondary_company_name ?: 'Societatea secundară') : null,
        ];

        $primaryVatPayer = $eventOrganizer?->primary_vat_payer !== null
            ? (bool) $eventOrganizer->primary_vat_payer
            : (bool) ($eventOrganizer?->vat_payer ?? false);
        $primaryVatRate = $eventOrganizer?->primary_vat_rate !== null
            ? (float) $eventOrganizer->primary_vat_rate
            : ((float) ($eventOrganizer?->tax_settings['vat_rate'] ?? 0) ?: 19.0);
        $secondaryVatPayer = (bool) ($eventOrganizer?->secondary_vat_payer ?? false);
        $secondaryVatRate = $eventOrganizer?->secondary_vat_rate !== null
            ? (float) $eventOrganizer->secondary_vat_rate
            : 19.0;

        $issuerVat = [
            'primary' => ['vat_payer' => $primaryVatPayer, 'vat_rate' => $primaryVatRate],
            'secondary' => ['vat_payer' => $secondaryVatPayer, 'vat_rate' => $secondaryVatRate],
        ];

        // Calcul net + suma TVA per societate. Cand vat_payer=true: pretul include TVA,
        // net = revenue / (1 + rate/100), vat_amount = revenue - net.
        foreach (['primary', 'secondary'] as $k) {
            $rev = $byIssuer[$k]['revenue'] ?? 0;
            if ($issuerVat[$k]['vat_payer'] && $issuerVat[$k]['vat_rate'] > 0 && $rev > 0) {
                $net = round($rev / (1 + $issuerVat[$k]['vat_rate'] / 100), 2);
                $vatAmt = round($rev - $net, 2);
            } else {
                $net = round($rev, 2);
                $vatAmt = 0.0;
            }
            $byIssuer[$k]['net_revenue'] = $net;
            $byIssuer[$k]['vat_amount'] = $vatAmt;
            $byIssuer[$k]['vat_payer'] = $issuerVat[$k]['vat_payer'];
            $byIssuer[$k]['vat_rate'] = $issuerVat[$k]['vat_rate'];
        }

        $responsePayload = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'currency' => $orders->first()?->currency ?? 'RON',
            'totals' => [
                'orders' => $totalOrders,
                'tickets' => $totalTickets,
                'revenue' => $totalRevenue,
                'commission' => $totalCommission,
                'net_revenue' => round($totalRevenue - $totalCommission, 2),
                'avg_order' => $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0,
                'avg_commission_per_order' => $totalOrders > 0 ? round($totalCommission / $totalOrders, 2) : 0,
                'commission_pct_effective' => $totalRevenue > 0 ? round(($totalCommission / $totalRevenue) * 100, 2) : 0,
            ],
            'commission_config' => [
                'rate' => $orgRate,
                'floor' => $orgFloor,
                'mode' => $eventOrganizer?->getEffectiveCommissionMode() ?? 'included',
                'formula' => $orgFloor > 0 ? "max({$orgRate}% × preț, {$orgFloor} lei)" : "{$orgRate}% × preț",
            ],
            'by_source' => array_values($bySource),
            'by_ticket_type' => array_values($byTicketType),
            'by_component_type' => array_values($byComponentType),
            'total_physical_tickets' => $totalPhysicalTickets,
            'by_cashier' => array_values($byCashier),
            'by_payment_method' => array_values($byPaymentMethod),
            'by_issuer' => [
                'primary' => array_merge($byIssuer['primary'], ['name' => $issuerNames['primary']]),
                'secondary' => $issuerNames['secondary'] ? array_merge($byIssuer['secondary'], ['name' => $issuerNames['secondary']]) : null,
            ],
            'cached_at' => now()->toIso8601String(),
        ];
        // Cache 5 min: reduce load pt utilizatori care re-deschide pagina + evita
        // multi-request-uri simultane sa piardă timp pe agregare identică.
        \Illuminate\Support\Facades\Cache::put($cacheKey, $responsePayload, 300);
        return $this->success($responsePayload);
    }

    /**
     * POST /marketplace-client/organizer/events/{event}/leisure/tickets/{ticketId}/manual-checkin
     *
     * Marcheaza manual un bilet ca "checked in" din pagina Participanti.
     * Idempotent: daca deja e check-in, nu-l suprascrie (return existing timestamp).
     */
    public function manualCheckin(Request $request, int $event, int $ticketId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        // Load ticket + verifica ca apartine acestui event (via order.event_id sau
        // ticket_types.event_id — cover ambele patterns istorice).
        $ticket = Ticket::query()
            ->where('id', $ticketId)
            ->where(function ($q) use ($eventModel) {
                $q->whereHas('order', fn ($oq) => $oq->where('event_id', $eventModel->id))
                    ->orWhereHas('ticketType', fn ($tq) => $tq->where('event_id', $eventModel->id));
            })
            ->first();
        if (!$ticket) return $this->error('Ticket not found', 404);
        if (in_array($ticket->status, ['cancelled', 'refunded'], true)) {
            return $this->error('Bilet ' . $ticket->status . ' — nu se poate face check-in.', 422);
        }

        // Idempotent: daca e deja checked in, return existing
        if ($ticket->checked_in_at !== null) {
            return $this->success([
                'ticket_id' => $ticket->id,
                'code' => $ticket->code,
                'checked_in_at' => $ticket->checked_in_at->toIso8601String(),
                'was_already_checked_in' => true,
            ], 'Bilet deja validat anterior.');
        }

        // Detecteaza actor pentru audit meta
        $actorType = 'organizer';
        $actorName = $organizer->name ?? $organizer->company_name ?? 'Organizator';
        $tokenName = $request->user()?->currentAccessToken()?->name ?? '';
        if (str_starts_with($tokenName, 'team-member-')) {
            $tmId = (int) str_replace('team-member-', '', $tokenName);
            $tm = \App\Models\MarketplaceOrganizerTeamMember::find($tmId);
            if ($tm) { $actorType = 'team_member'; $actorName = $tm->name; }
        }

        $now = Carbon::now();
        $ticket->checked_in_at = $now;
        if ($ticket->status === 'valid') $ticket->status = 'used';
        $existingMeta = is_array($ticket->meta ?? null) ? $ticket->meta : [];
        $ticket->meta = array_merge($existingMeta, [
            'manual_checkin' => [
                'by_type' => $actorType,
                'by_name' => $actorName,
                'at' => $now->toIso8601String(),
                'via' => 'leisure_participants_page',
            ],
        ]);
        $ticket->save();

        return $this->success([
            'ticket_id' => $ticket->id,
            'code' => $ticket->code,
            'checked_in_at' => $now->toIso8601String(),
            'was_already_checked_in' => false,
            'by' => $actorName,
        ], 'Check-in manual efectuat.');
    }

    // ========================================================================
    // ORDERS — pagina noua /organizator/leisure-orders (list + acordeon + delete)
    // ========================================================================

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/orders
     *   ?from=&to=&search=&source=pos|online&status=&page=&per_page=
     *
     * Lista paginata de comenzi cu meta esentiala pentru pagina Comenzi.
     * Biletele NU sunt incluse (lazy load prin orderShow cand user expandeaza row).
     */
    public function ordersIndex(Request $request, int $event): JsonResponse
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
            'search' => 'nullable|string|max:100',
            'source' => 'nullable|in:pos,online',
            'status' => 'nullable|string|max:32',
            'per_page' => 'nullable|integer|min:1|max:200',
            'page' => 'nullable|integer|min:1',
        ]);

        $tz = 'Europe/Bucharest';
        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'], $tz)->startOfDay()
            : Carbon::now($tz)->subDays(30)->startOfDay();
        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'], $tz)->endOfDay()
            : Carbon::now($tz)->endOfDay();
        $perPage = (int) ($validated['per_page'] ?? 30);

        $q = Order::query()
            ->where('event_id', $eventModel->id)
            ->whereBetween('paid_at', [$from, $to]);

        if (!empty($validated['status'])) {
            $q->where('status', $validated['status']);
        }
        if (!empty($validated['source'])) {
            if ($validated['source'] === 'pos') $q->where('source', 'pos');
            else $q->where('source', '!=', 'pos');
        }
        if (!empty($validated['search'])) {
            $s = $validated['search'];
            $q->where(function ($sq) use ($s) {
                $sq->where('order_number', 'ilike', "%{$s}%")
                    ->orWhere('customer_name', 'ilike', "%{$s}%")
                    ->orWhere('customer_email', 'ilike', "%{$s}%");
            });
        }

        $paginator = $q->orderByDesc('paid_at')
            ->withCount(['tickets as tickets_count' => function ($tq) {
                $tq->whereNotIn('status', ['cancelled', 'refunded']);
            }])
            ->paginate($perPage, ['id', 'order_number', 'source', 'status', 'total', 'currency', 'paid_at', 'created_at', 'customer_name', 'customer_email', 'customer_phone', 'meta']);

        // Preload team members pt operator_name
        $tmIds = [];
        foreach ($paginator->items() as $o) {
            $tmId = $o->meta['cashier_team_member_id'] ?? null;
            if ($tmId) $tmIds[(int) $tmId] = true;
        }
        $tmNames = [];
        if (!empty($tmIds)) {
            \App\Models\MarketplaceOrganizerTeamMember::query()
                ->whereIn('id', array_keys($tmIds))
                ->get(['id', 'name'])
                ->each(function ($tm) use (&$tmNames) { $tmNames[$tm->id] = $tm->name; });
        }

        $rows = collect($paginator->items())->map(function ($o) use ($tmNames) {
            $meta = is_array($o->meta ?? null) ? $o->meta : [];
            $pm = $meta['payment_method'] ?? null;
            $tmId = $meta['cashier_team_member_id'] ?? null;
            $operator = $tmId ? ($tmNames[(int) $tmId] ?? ('Angajat #' . $tmId)) : ($o->source === 'pos' ? 'InfoPoint' : null);
            $sessionId = $meta['cashier_session_id'] ?? null;
            return [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'source' => $o->source,
                'status' => $o->status,
                'total' => (float) $o->total,
                'currency' => $o->currency,
                'paid_at' => optional($o->paid_at)->toIso8601String(),
                'created_at' => optional($o->created_at)->toIso8601String(),
                'customer_name' => $o->customer_name,
                'customer_email' => $o->customer_email,
                'customer_phone' => $o->customer_phone,
                'payment_method' => $pm,
                'cashier_session_id' => $sessionId,
                'operator_name' => $operator,
                'tickets_count' => (int) ($o->tickets_count ?? 0),
            ];
        });

        return $this->success([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'orders' => $rows->values(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/orders/{orderId}
     * Returneaza detalii + bilete pentru acordeon expand.
     */
    public function orderShow(Request $request, int $event, int $orderId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $order = Order::query()
            ->where('id', $orderId)
            ->where('event_id', $eventModel->id)
            ->with(['tickets:id,order_id,ticket_type_id,code,barcode,price,status,checked_in_at,meta', 'tickets.ticketType:id,name,service_category'])
            ->first();
        if (!$order) return $this->error('Order not found', 404);

        $tickets = $order->tickets->map(function ($t) {
            $tt = $t->ticketType;
            $ttName = $tt->name ?? '—';
            if (is_array($ttName)) $ttName = $ttName['ro'] ?? reset($ttName);
            $meta = is_array($t->meta ?? null) ? $t->meta : [];
            if (!empty($meta['label_override'])) $ttName = $meta['label_override'];
            return [
                'id' => $t->id,
                'code' => $t->code ?: $t->barcode,
                'ticket_type' => $ttName,
                'service_category' => $tt->service_category ?? 'access',
                'price' => (float) ($t->price ?? 0),
                'status' => $t->status,
                'checked_in_at' => optional($t->checked_in_at)->toIso8601String(),
                'from_package' => !empty($meta['from_package']),
                'is_umbrella' => !empty($meta['is_package_umbrella']),
                'visit_date' => $meta['visit_date'] ?? null,
            ];
        });

        return $this->success([
            'id' => $order->id,
            'order_number' => $order->order_number,
            'source' => $order->source,
            'status' => $order->status,
            'total' => (float) $order->total,
            'currency' => $order->currency,
            'paid_at' => optional($order->paid_at)->toIso8601String(),
            'created_at' => optional($order->created_at)->toIso8601String(),
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'meta' => $order->meta,
            'tickets' => $tickets,
        ]);
    }

    /**
     * DELETE /marketplace-client/organizer/events/{event}/leisure/orders/{orderId}
     * Body: { note: "motiv obligatoriu" }
     *
     * Sterge orderul + biletele + order_items dintr-o tranzactie, cu:
     *  1. Snapshot audit persistat in leisure_order_deletion_logs (JSONB blob)
     *  2. Regenerare closing_snapshot pt sesiunea casa (daca era in sesiune inchisa)
     *  3. Nota interna OBLIGATORIE
     */
    public function orderDestroy(Request $request, int $event, int $orderId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $validated = $request->validate([
            'note' => 'required|string|min:3|max:1000',
        ]);

        $order = Order::query()
            ->where('id', $orderId)
            ->where('event_id', $eventModel->id)
            ->with(['tickets:id,order_id,ticket_type_id,code,barcode,price,status,checked_in_at,meta', 'tickets.ticketType:id,name,service_category'])
            ->first();
        if (!$order) return $this->error('Order not found', 404);

        // Detect actor (team_member vs organizer)
        $actorType = 'organizer';
        $actorId = $organizer->id;
        $actorName = $organizer->name ?? $organizer->company_name;
        $actorEmail = $organizer->email ?? null;
        $tokenName = $request->user()?->currentAccessToken()?->name ?? '';
        if (str_starts_with($tokenName, 'team-member-')) {
            $tmId = (int) str_replace('team-member-', '', $tokenName);
            $tm = \App\Models\MarketplaceOrganizerTeamMember::find($tmId);
            if ($tm) {
                $actorType = 'team_member';
                $actorId = $tm->id;
                $actorName = $tm->name;
                $actorEmail = $tm->email ?? null;
            }
        }

        // Operator name pt log (poate fi diferit de actor - operatorul de la POS)
        $meta = is_array($order->meta ?? null) ? $order->meta : [];
        $orderTmId = $meta['cashier_team_member_id'] ?? null;
        $operatorName = null;
        if ($orderTmId) {
            $operatorTm = \App\Models\MarketplaceOrganizerTeamMember::find((int) $orderTmId);
            $operatorName = $operatorTm?->name ?? ('Angajat #' . $orderTmId);
        } elseif ($order->source === 'pos') {
            $operatorName = 'InfoPoint';
        }

        // Build full snapshot pentru audit
        $ticketsSnapshot = $order->tickets->map(function ($t) {
            return [
                'id' => $t->id,
                'ticket_type_id' => $t->ticket_type_id,
                'ticket_type_name' => $t->ticketType?->name,
                'service_category' => $t->ticketType?->service_category,
                'code' => $t->code,
                'barcode' => $t->barcode,
                'price' => (float) ($t->price ?? 0),
                'status' => $t->status,
                'checked_in_at' => optional($t->checked_in_at)->toIso8601String(),
                'meta' => $t->meta,
            ];
        })->toArray();

        $fullSnapshot = [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'source' => $order->source,
                'status' => $order->status,
                'total' => (float) $order->total,
                'currency' => $order->currency,
                'paid_at' => optional($order->paid_at)->toIso8601String(),
                'created_at' => optional($order->created_at)->toIso8601String(),
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'customer_phone' => $order->customer_phone,
                'meta' => $order->meta,
            ],
            'tickets' => $ticketsSnapshot,
        ];

        $cashierSessionId = $meta['cashier_session_id'] ?? null;
        $snapshotRegenerated = false;

        \DB::transaction(function () use ($order, $eventModel, $marketplace, $organizer, $actorType, $actorId, $actorName, $actorEmail, $operatorName, $meta, $cashierSessionId, $fullSnapshot, $validated, $ticketsSnapshot, &$snapshotRegenerated) {
            // 1. Log audit inainte de delete
            LeisureOrderDeletionLog::create([
                'marketplace_client_id' => $marketplace->id,
                'marketplace_organizer_id' => $eventModel->marketplace_organizer_id ?? $organizer->id,
                'event_id' => $eventModel->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_source' => $order->source,
                'order_status' => $order->status,
                'order_total' => $order->total,
                'order_currency' => $order->currency,
                'order_paid_at' => $order->paid_at,
                'order_created_at' => $order->created_at,
                'customer_name' => $order->customer_name,
                'customer_email' => $order->customer_email,
                'customer_phone' => $order->customer_phone,
                'payment_method' => $meta['payment_method'] ?? null,
                'cashier_session_id' => $cashierSessionId,
                'cashier_operator_name' => $operatorName,
                'tickets_count' => count($ticketsSnapshot),
                'snapshot' => $fullSnapshot,
                'note' => $validated['note'],
                'deleted_by_type' => $actorType,
                'deleted_by_id' => $actorId,
                'deleted_by_name' => $actorName,
                'deleted_by_email' => $actorEmail,
                'deleted_at' => now(),
            ]);

            // 2. Delete cascade: order_items -> tickets -> order
            if (\Schema::hasTable('order_items')) {
                \DB::table('order_items')->where('order_id', $order->id)->delete();
            }
            Ticket::where('order_id', $order->id)->delete();
            Order::where('id', $order->id)->delete();

            // 3. Regenerare snapshot pt sesiunea inchisa
            if ($cashierSessionId) {
                $session = \App\Models\LeisureCashierSession::find($cashierSessionId);
                if ($session && $session->closed_at !== null) {
                    $newSnapshot = $this->buildCashierSnapshotForSession($session, $eventModel, $organizer);
                    $newSnapshot['regenerated_at'] = now()->toIso8601String();
                    $newSnapshot['regenerated_reason'] = 'order_deleted:' . $order->order_number;
                    $session->update(['closing_snapshot' => $newSnapshot]);
                    $snapshotRegenerated = true;
                }
            }
        });

        // Update log cu flag regeneration (dupa commit)
        if ($snapshotRegenerated) {
            LeisureOrderDeletionLog::where('order_id', $order->id)
                ->orderByDesc('id')->limit(1)
                ->update(['cashier_snapshot_regenerated' => true]);
        }

        return $this->success([
            'deleted' => true,
            'order_number' => $order->order_number,
            'cashier_snapshot_regenerated' => $snapshotRegenerated,
        ], 'Comanda a fost stearsa.');
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/orders/deletion-history
     *   ?from=&to=&search=&page=&per_page=
     *
     * Lista ștergerilor - toate detaliile pastrate.
     */
    public function orderDeletionHistory(Request $request, int $event): JsonResponse
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
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:200',
            'page' => 'nullable|integer|min:1',
        ]);

        $tz = 'Europe/Bucharest';
        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'], $tz)->startOfDay()
            : Carbon::now($tz)->subDays(60)->startOfDay();
        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'], $tz)->endOfDay()
            : Carbon::now($tz)->endOfDay();
        $perPage = (int) ($validated['per_page'] ?? 30);

        $q = LeisureOrderDeletionLog::query()
            ->where('event_id', $eventModel->id)
            ->whereBetween('deleted_at', [$from, $to])
            ->orderByDesc('deleted_at');

        if (!empty($validated['search'])) {
            $s = $validated['search'];
            $q->where(function ($sq) use ($s) {
                $sq->where('order_number', 'ilike', "%{$s}%")
                    ->orWhere('customer_name', 'ilike', "%{$s}%")
                    ->orWhere('customer_email', 'ilike', "%{$s}%")
                    ->orWhere('note', 'ilike', "%{$s}%");
            });
        }

        $paginator = $q->paginate($perPage);

        $rows = collect($paginator->items())->map(function ($l) {
            return [
                'id' => $l->id,
                'order_number' => $l->order_number,
                'order_source' => $l->order_source,
                'order_status' => $l->order_status,
                'order_total' => (float) $l->order_total,
                'order_currency' => $l->order_currency,
                'order_paid_at' => optional($l->order_paid_at)->toIso8601String(),
                'customer_name' => $l->customer_name,
                'customer_email' => $l->customer_email,
                'customer_phone' => $l->customer_phone,
                'payment_method' => $l->payment_method,
                'cashier_operator_name' => $l->cashier_operator_name,
                'cashier_session_id' => $l->cashier_session_id,
                'tickets_count' => $l->tickets_count,
                'note' => $l->note,
                'deleted_by_type' => $l->deleted_by_type,
                'deleted_by_name' => $l->deleted_by_name,
                'deleted_by_email' => $l->deleted_by_email,
                'deleted_at' => optional($l->deleted_at)->toIso8601String(),
                'cashier_snapshot_regenerated' => (bool) $l->cashier_snapshot_regenerated,
            ];
        });

        return $this->success([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'items' => $rows->values(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Helper: reconstruieste closing_snapshot pentru o sesiune de casa dupa
     * modificari (delete order). Mirror 1:1 al logicii din cashierClose().
     */
    private function buildCashierSnapshotForSession(\App\Models\LeisureCashierSession $session, Event $eventModel, MarketplaceOrganizer $organizer): array
    {
        $eventId = $session->event_id;
        $openedAt = $session->opened_at;
        $closedAt = $session->closed_at;
        $issuerNames = [
            'primary' => $organizer->company_name ?: 'SC1',
            'secondary' => $organizer->secondary_company_name ?: null,
            'mix' => 'Mix',
        ];
        // Component issuer map pt Mix packages
        $componentIssuerMap = [];
        $ttPkg = TicketType::where('event_id', $eventId)->where('service_category', 'package')->get();
        foreach ($ttPkg as $tt) {
            $outs = is_array($tt->meta ?? null) ? ($tt->meta['package_outputs'] ?? []) : [];
            foreach ($outs as $o) {
                $cid = (int) ($o['ticket_type_id'] ?? 0);
                if (!$cid) continue;
                if (!isset($componentIssuerMap[$cid])) {
                    $ct = TicketType::find($cid);
                    if ($ct) $componentIssuerMap[$cid] = $ct->issuing_company ?? 'primary';
                }
            }
        }
        $orders = Order::where('event_id', $eventId)
            ->whereIn('status', ['paid', 'completed'])
            ->whereBetween('paid_at', [$openedAt, $closedAt])
            ->with(['tickets:id,order_id,ticket_type_id,price,status,meta', 'tickets.ticketType:id,name,service_category,issuing_company'])
            ->get(['id', 'source', 'meta', 'total']);

        $byPayment = [];
        $byTicketType = [];
        $byIssuer = [
            'primary' => ['issuer' => 'primary', 'name' => $issuerNames['primary'], 'orders' => 0, 'tickets' => 0, 'revenue' => 0.0, 'by_payment' => ['cash' => 0.0, 'card' => 0.0, 'online' => 0.0]],
            'secondary' => ['issuer' => 'secondary', 'name' => $issuerNames['secondary'], 'orders' => 0, 'tickets' => 0, 'revenue' => 0.0, 'by_payment' => ['cash' => 0.0, 'card' => 0.0, 'online' => 0.0]],
        ];
        $totalOrders = $orders->count();
        $totalTickets = 0; $totalTicketsSold = 0; $totalTicketsVisitors = 0; $totalRevenue = 0.0;
        $posOnlyOrders = 0; $posOnlyTickets = 0; $posOnlyTicketsSold = 0; $posOnlyTicketsVisitors = 0; $posOnlyRevenue = 0.0;
        $posOnlyByCategory = [];

        foreach ($orders as $o) {
            $rev = (float) ($o->total ?? 0);
            $totalRevenue += $rev;
            $isPos = $o->source === 'pos';
            $pmRaw = $o->meta['payment_method'] ?? null;
            if ($isPos && $pmRaw === 'cash') $pm = 'cash';
            elseif ($isPos && $pmRaw === 'card') $pm = 'card';
            else $pm = 'online';
            if (!isset($byPayment[$pm])) $byPayment[$pm] = ['method' => $pm, 'orders' => 0, 'tickets' => 0, 'revenue' => 0.0];
            $byPayment[$pm]['orders']++;
            $byPayment[$pm]['revenue'] += $rev;
            if ($isPos) { $posOnlyOrders++; $posOnlyRevenue += $rev; }
            $orderTouchedIssuers = ['primary' => false, 'secondary' => false];

            foreach ($o->tickets as $t) {
                if (in_array($t->status, ['cancelled', 'refunded'], true)) continue;
                $metaArr = is_array($t->meta ?? null) ? $t->meta : [];
                $isFromPackage = !empty($metaArr['from_package']);
                $isUmbrella = !empty($metaArr['is_package_umbrella']);
                $tp = (float) ($t->price ?? 0);
                $tt = $t->ticketType;
                $svcCat = $tt->service_category ?? 'access';
                $isPackageParent = ($svcCat === 'package');
                $totalTickets++;
                if (!$isFromPackage && $tp > 0) $totalTicketsSold++;
                if (!$isUmbrella) $totalTicketsVisitors++;
                $byPayment[$pm]['tickets']++;

                if ($isPos) {
                    $posOnlyTickets++;
                    if (!$isFromPackage && $tp > 0) $posOnlyTicketsSold++;
                    if (!$isUmbrella) $posOnlyTicketsVisitors++;
                    if (!$isUmbrella) {
                        $catKey = $svcCat ?: 'access';
                        if (!isset($posOnlyByCategory[$catKey])) $posOnlyByCategory[$catKey] = ['category' => $catKey, 'tickets' => 0, 'revenue' => 0.0];
                        $posOnlyByCategory[$catKey]['tickets']++;
                        $posOnlyByCategory[$catKey]['revenue'] += $tp;
                    }
                }

                $ttId = $t->ticket_type_id;
                $ttName = $tt->name ?? "Tip #{$ttId}";
                if (is_array($ttName)) $ttName = $ttName['ro'] ?? reset($ttName);
                if (!empty($metaArr['label_override'])) {
                    $ttName = $metaArr['label_override'];
                    $key = 'lbl_' . $ttName;
                } else {
                    $key = ($isPackageParent ? 'pkg_' : 'tt_') . $ttId;
                }
                if (!isset($byTicketType[$key])) $byTicketType[$key] = ['ticket_type_id' => $ttId, 'name' => $ttName, 'tickets' => 0, 'revenue' => 0.0];
                $byTicketType[$key]['tickets']++;
                $byTicketType[$key]['revenue'] += $tp;

                if ($isFromPackage) continue;
                if ($tp <= 0) continue;

                $issuer = $tt->issuing_company ?? 'primary';
                $splits = [];
                if ($isPackageParent && $issuer === 'mix') {
                    $outs = is_array($tt->meta ?? null) ? ($tt->meta['package_outputs'] ?? []) : [];
                    $allocSum = 0.0;
                    foreach ($outs as $out) if (isset($out['price']) && is_numeric($out['price'])) $allocSum += (float) $out['price'];
                    if ($allocSum > 0) foreach ($outs as $out) {
                        $cid = (int) ($out['ticket_type_id'] ?? 0);
                        $cIssuer = $componentIssuerMap[$cid] ?? 'primary';
                        $portion = isset($out['price']) ? (float) $out['price'] : 0.0;
                        if ($portion <= 0) continue;
                        $splits[] = ['issuer' => $cIssuer, 'amount' => $portion];
                    }
                }
                if (empty($splits)) $splits[] = ['issuer' => $issuer === 'secondary' ? 'secondary' : 'primary', 'amount' => $tp > 0 ? $tp : 1];
                $splitTotal = array_sum(array_column($splits, 'amount'));
                $biggest = null; $biggestAmt = -1;
                foreach ($splits as $s) {
                    if ($s['amount'] > $biggestAmt) { $biggestAmt = $s['amount']; $biggest = $s['issuer']; }
                    $ratio = $splitTotal > 0 ? $s['amount'] / $splitTotal : 0;
                    $portionRev = $tp * $ratio;
                    $byIssuer[$s['issuer']]['revenue'] += $portionRev;
                    $byIssuer[$s['issuer']]['by_payment'][$pm] = ($byIssuer[$s['issuer']]['by_payment'][$pm] ?? 0) + $portionRev;
                    if (!$orderTouchedIssuers[$s['issuer']]) {
                        $byIssuer[$s['issuer']]['orders']++;
                        $orderTouchedIssuers[$s['issuer']] = true;
                    }
                }
                if ($biggest) $byIssuer[$biggest]['tickets']++;
            }
        }
        foreach ($byPayment as &$row) $row['revenue'] = round($row['revenue'], 2);
        foreach ($byTicketType as &$row) $row['revenue'] = round($row['revenue'], 2);
        foreach ($byIssuer as &$row) {
            $row['revenue'] = round($row['revenue'], 2);
            foreach ($row['by_payment'] as $k => $v) $row['by_payment'][$k] = round($v, 2);
        }
        unset($row);
        usort($byTicketType, fn ($a, $b) => $b['tickets'] <=> $a['tickets']);
        foreach ($posOnlyByCategory as &$row) $row['revenue'] = round($row['revenue'], 2);
        unset($row);
        $posOnlyByCategoryList = array_values($posOnlyByCategory);
        $catOrder = ['access' => 1, 'parking' => 2, 'activity' => 3, 'rental' => 4];
        usort($posOnlyByCategoryList, fn ($a, $b) => ($catOrder[$a['category']] ?? 99) <=> ($catOrder[$b['category']] ?? 99));

        return [
            'totals' => [
                'orders' => $totalOrders,
                'tickets' => $totalTickets,
                'tickets_sold' => $totalTicketsSold,
                'tickets_visitors' => $totalTicketsVisitors,
                'revenue' => round($totalRevenue, 2),
                'avg_order' => $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0,
            ],
            'pos_only_totals' => [
                'orders' => $posOnlyOrders,
                'tickets' => $posOnlyTickets,
                'tickets_sold' => $posOnlyTicketsSold,
                'tickets_visitors' => $posOnlyTicketsVisitors,
                'revenue' => round($posOnlyRevenue, 2),
                'avg_order' => $posOnlyOrders > 0 ? round($posOnlyRevenue / $posOnlyOrders, 2) : 0,
            ],
            'by_payment' => array_values($byPayment),
            'by_ticket_type' => array_values($byTicketType),
            'by_category_pos' => $posOnlyByCategoryList,
            'by_issuer' => [
                'primary' => $byIssuer['primary'],
                'secondary' => $issuerNames['secondary'] ? $byIssuer['secondary'] : null,
            ],
        ];
    }

    // ========================================================================
    // F7 — Bărci: tabel boats + rentals (timer + calup recalculare)
    // ========================================================================

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/boats?ticket_type_id=X
     * Listă bărci pentru un produs (rental). Sincronizează automat dacă lipsesc.
     */
    public function boatsIndex(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;
        $eventModel = Event::where('id', $event)->where('marketplace_client_id', $marketplace->id)->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $validated = $request->validate(['ticket_type_id' => 'required|integer']);
        $tt = TicketType::where('id', $validated['ticket_type_id'])->where('event_id', $eventModel->id)->first();
        if (!$tt) return $this->error('Ticket type not found', 404);

        $boats = LeisureBoat::query()
            ->where('ticket_type_id', $tt->id)
            ->orderBy('number')
            ->get();

        // Atașează rental activ dacă există
        $activeRentals = BoatRental::query()
            ->whereIn('boat_id', $boats->pluck('id'))
            ->where('status', 'active')
            ->get()
            ->keyBy('boat_id');

        return $this->success([
            'boats' => $boats->map(function ($b) use ($activeRentals) {
                $active = $activeRentals->get($b->id);
                return [
                    'id' => $b->id,
                    'number' => $b->number,
                    'label' => $b->label ?: ('Barca #' . $b->number),
                    'qr_code' => $b->qr_code,
                    'status' => $b->status,
                    'active_rental' => $active ? [
                        'id' => $active->id,
                        'started_at' => $active->started_at->toIso8601String(),
                        'planned_end_at' => $active->planned_end_at->toIso8601String(),
                    ] : null,
                ];
            })->values(),
        ]);
    }

    /**
     * POST /marketplace-client/organizer/events/{event}/leisure/boats/sync
     * Sincronizează numărul de bărci pentru un produs cu meta.physical_inventory.count.
     */
    public function boatsSync(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;
        $eventModel = Event::where('id', $event)->where('marketplace_client_id', $marketplace->id)->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $validated = $request->validate(['ticket_type_id' => 'required|integer']);
        $tt = TicketType::where('id', $validated['ticket_type_id'])->where('event_id', $eventModel->id)->first();
        if (!$tt) return $this->error('Ticket type not found', 404);

        $physical = is_array($tt->meta['physical_inventory'] ?? null) ? $tt->meta['physical_inventory'] : null;
        if (!$physical || empty($physical['enabled'])) {
            return $this->error('Produsul nu are inventar fizic activat (meta.physical_inventory.enabled).', 422);
        }
        $targetCount = max(1, (int) ($physical['count'] ?? 1));
        $current = LeisureBoat::where('ticket_type_id', $tt->id)->count();

        $created = 0;
        $deactivated = 0;
        if ($current < $targetCount) {
            // Adaug bărci noi (numere consecutive)
            $maxNumber = (int) LeisureBoat::where('ticket_type_id', $tt->id)->max('number');
            for ($i = $maxNumber + 1; $i <= $maxNumber + ($targetCount - $current); $i++) {
                LeisureBoat::create([
                    'event_id' => $eventModel->id,
                    'ticket_type_id' => $tt->id,
                    'number' => $i,
                    'status' => 'available',
                ]);
                $created++;
            }
        } elseif ($current > $targetCount) {
            // Marchez ca retired pe cele suplimentare (NU le șterg dacă au rentals istorice)
            $extraIds = LeisureBoat::where('ticket_type_id', $tt->id)
                ->orderByDesc('number')
                ->take($current - $targetCount)
                ->pluck('id');
            LeisureBoat::whereIn('id', $extraIds)->update(['status' => 'retired']);
            $deactivated = $extraIds->count();
        }

        return $this->success([
            'target' => $targetCount,
            'before' => $current,
            'created' => $created,
            'deactivated' => $deactivated,
            'total_now' => LeisureBoat::where('ticket_type_id', $tt->id)->where('status', '!=', 'retired')->count(),
        ], 'Sincronizare completă');
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/active-rentals?ticket_type_id=X
     */
    public function activeRentals(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;
        $eventModel = Event::where('id', $event)->where('marketplace_client_id', $marketplace->id)->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $query = BoatRental::query()
            ->where('event_id', $eventModel->id)
            ->where('status', 'active')
            ->with(['boat:id,number,label', 'ticketType:id,name'])
            ->orderBy('started_at');

        if ($request->filled('ticket_type_id')) {
            $query->where('ticket_type_id', (int) $request->input('ticket_type_id'));
        }

        return $this->success([
            'rentals' => $query->get()->map(function ($r) {
                return [
                    'id' => $r->id,
                    'boat_id' => $r->boat_id,
                    'boat_number' => $r->boat?->number,
                    'boat_label' => $r->boat?->label ?: ('Barca #' . $r->boat?->number),
                    'started_at' => $r->started_at->toIso8601String(),
                    'planned_end_at' => $r->planned_end_at->toIso8601String(),
                    'calup_duration_minutes' => $r->calup_duration_minutes,
                    'calupuri_planned' => $r->calupuri_planned,
                    'calup_unit_price' => (float) $r->calup_unit_price,
                    'ticket_type' => is_array($r->ticketType?->name) ? ($r->ticketType->name['ro'] ?? reset($r->ticketType->name)) : $r->ticketType?->name,
                ];
            })->values(),
        ]);
    }

    /**
     * POST /marketplace-client/organizer/events/{event}/leisure/boat-rentals/start
     * Body: { ticket_type_id, boat_id, rental_ticket_id? sau access_ticket_id?, calupuri?: 1, variant_id? }
     *
     * Pornește cursa pe o barcă. Dacă rental_ticket_id e dat, atașează-l;
     * altfel emite un Ticket nou (flow on-site: cumpărare imediată).
     */
    public function rentalStart(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;
        $eventModel = Event::where('id', $event)->where('marketplace_client_id', $marketplace->id)->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $validated = $request->validate([
            'ticket_type_id' => 'required|integer',
            'boat_id' => 'required|integer',
            'rental_ticket_id' => 'nullable|integer',
            'access_ticket_id' => 'nullable|integer',
            'calupuri' => 'nullable|integer|min:1|max:48',
            'variant_id' => 'nullable|string|max:64',
            'started_by_member_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:500',
        ]);

        $tt = TicketType::where('id', $validated['ticket_type_id'])->where('event_id', $eventModel->id)->first();
        if (!$tt) return $this->error('Ticket type not found', 404);

        $boat = LeisureBoat::where('id', $validated['boat_id'])->where('ticket_type_id', $tt->id)->first();
        if (!$boat) return $this->error('Barca nu există pentru acest produs.', 404);
        if ($boat->status !== 'available') return $this->error('Barca nu e disponibilă (status: ' . $boat->status . ')', 422);

        // Verifică dacă barca are deja un rental activ
        $existingActive = BoatRental::where('boat_id', $boat->id)->where('status', 'active')->exists();
        if ($existingActive) return $this->error('Barca are deja o cursă activă. Închide-o prima dată.', 422);

        // Rezolvă varianta pentru a calcula calup_duration_minutes + unit_price
        $variant = null;
        if (!empty($validated['variant_id']) && is_array($tt->meta['variants'] ?? null)) {
            foreach ($tt->meta['variants'] as $v) {
                if (!is_array($v) || empty($v['label'])) continue;
                $vid = $v['id'] ?? Str::slug($v['label']);
                if ($vid === $validated['variant_id']) { $variant = $v; break; }
            }
        }

        $calupDurationMinutes = (int) ($variant['duration_minutes'] ?? 30);
        // Dacă durata e 60 (1h), calupul e tot 30min, deci calupuri_planned = 2
        $calupBase = 30;
        $calupuriPlanned = max(1, (int) ($validated['calupuri'] ?? ceil($calupDurationMinutes / $calupBase)));
        $calupUnitPrice = (float) ($variant['price'] ?? $tt->price_max ?? $tt->price ?? 0) / max(1, $calupuriPlanned);

        $now = Carbon::now();
        $plannedEnd = $now->copy()->addMinutes($calupBase * $calupuriPlanned);

        $rental = BoatRental::create([
            'event_id' => $eventModel->id,
            'ticket_type_id' => $tt->id,
            'boat_id' => $boat->id,
            'rental_ticket_id' => $validated['rental_ticket_id'] ?? null,
            'access_ticket_id' => $validated['access_ticket_id'] ?? null,
            'started_by_member_id' => $validated['started_by_member_id'] ?? null,
            'started_at' => $now,
            'planned_end_at' => $plannedEnd,
            'calup_duration_minutes' => $calupBase,
            'calup_unit_price' => round($calupUnitPrice, 2),
            'calupuri_planned' => $calupuriPlanned,
            'status' => 'active',
            'notes' => $validated['notes'] ?? null,
        ]);

        // Marchează barca în uz
        $boat->update(['status' => 'in_use']);

        // Marchează biletul rental ca "used" dacă există
        if (!empty($validated['rental_ticket_id'])) {
            Ticket::where('id', $validated['rental_ticket_id'])->update(['status' => 'used', 'checked_in_at' => $now]);
        }
        if (!empty($validated['access_ticket_id'])) {
            $accessTicket = Ticket::find($validated['access_ticket_id']);
            if ($accessTicket && empty($accessTicket->checked_in_at)) {
                $accessTicket->update(['checked_in_at' => $now]);
            }
        }

        return $this->success([
            'rental' => [
                'id' => $rental->id,
                'boat_number' => $boat->number,
                'started_at' => $rental->started_at->toIso8601String(),
                'planned_end_at' => $rental->planned_end_at->toIso8601String(),
                'calupuri_planned' => $rental->calupuri_planned,
                'calup_unit_price' => (float) $rental->calup_unit_price,
            ],
        ], 'Cursă pornită');
    }

    /**
     * POST /marketplace-client/organizer/events/{event}/leisure/boat-rentals/{rental}/end
     * Închide timer-ul (operator). Calculează calupuri_actual + extra_charge dar nu emite ticket încă.
     */
    public function rentalEnd(Request $request, int $event, int $rental): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;
        $eventModel = Event::where('id', $event)->where('marketplace_client_id', $marketplace->id)->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $r = BoatRental::where('id', $rental)->where('event_id', $eventModel->id)->first();
        if (!$r) return $this->error('Cursa nu există.', 404);
        if ($r->status !== 'active') return $this->error('Cursa nu e activă (status: ' . $r->status . ')', 422);

        $now = Carbon::now();
        $r->ended_at = $now;
        $r->calupuri_actual = $r->calculateActualCalupuri();
        $extraCalupuri = max(0, $r->calupuri_actual - $r->calupuri_planned);
        $r->extra_charge_total = round($extraCalupuri * (float) $r->calup_unit_price, 2);
        $r->status = 'ended';
        $r->save();

        return $this->success([
            'rental_id' => $r->id,
            'started_at' => $r->started_at->toIso8601String(),
            'ended_at' => $r->ended_at->toIso8601String(),
            'duration_minutes' => $r->started_at->diffInMinutes($r->ended_at),
            'calupuri_planned' => $r->calupuri_planned,
            'calupuri_actual' => $r->calupuri_actual,
            'extra_calupuri' => $extraCalupuri,
            'calup_unit_price' => (float) $r->calup_unit_price,
            'extra_charge_total' => (float) $r->extra_charge_total,
        ], $extraCalupuri > 0
            ? "Cursă încheiată. Clientul a depășit cu {$extraCalupuri} calup(uri) — încasează {$r->extra_charge_total} RON."
            : 'Cursă încheiată în limita planificată.');
    }

    /**
     * POST /marketplace-client/organizer/events/{event}/leisure/boat-rentals/{rental}/finalize
     * Finalizează complet: emite ticket extra pentru calupurile suplimentare (dacă există),
     * eliberează barca.
     */
    public function rentalFinalize(Request $request, int $event, int $rental): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;
        $eventModel = Event::where('id', $event)->where('marketplace_client_id', $marketplace->id)->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $r = BoatRental::where('id', $rental)->where('event_id', $eventModel->id)->first();
        if (!$r) return $this->error('Cursa nu există.', 404);
        if ($r->status !== 'ended') return $this->error('Cursa trebuie închisă (end) înainte de finalizare.', 422);

        $now = Carbon::now();
        $extraTicketId = null;

        try {
            DB::beginTransaction();

            if ($r->extra_charge_total > 0 && $r->order_id) {
                // Adăugare OrderItem suplimentar + Ticket extra pentru calupurile depășite
                $tt = TicketType::find($r->ticket_type_id);
                $extraCalupuri = max(0, $r->calupuri_actual - $r->calupuri_planned);
                $orderItem = OrderItem::create([
                    'order_id' => $r->order_id,
                    'ticket_type_id' => $tt->id,
                    'name' => 'Calup suplimentar 30min × ' . $extraCalupuri,
                    'quantity' => $extraCalupuri,
                    'unit_price' => $r->calup_unit_price,
                    'total' => $r->extra_charge_total,
                    'meta' => [
                        'service_category' => $tt->service_category ?? 'rental',
                        'visit_date' => Carbon::today()->toDateString(),
                        'rental_extra_calup' => true,
                        'rental_id' => $r->id,
                    ],
                ]);
                $code = strtoupper(Str::random(10));
                $extraTicket = Ticket::create([
                    'order_id' => $r->order_id,
                    'order_item_id' => $orderItem->id,
                    'ticket_type_id' => $tt->id,
                    'event_id' => $eventModel->id,
                    'tenant_id' => $eventModel->tenant_id,
                    'marketplace_client_id' => $marketplace->id,
                    'code' => $code,
                    'barcode' => $code,
                    // Bilet extra calup — mosteneste locale-ul din order-ul original
                    // ca sa fie tradus corect daca clientul a comandat in HU/EN.
                    'locale' => Order::find($r->order_id)?->locale,
                    'status' => 'used',
                    'price' => $r->extra_charge_total,
                    'checked_in_at' => $now,
                    'meta' => [
                        'pos' => true,
                        'rental_extra_calup' => true,
                        'rental_id' => $r->id,
                        'extra_calupuri' => $extraCalupuri,
                    ],
                ]);
                $extraTicketId = $extraTicket->id;

                // Update order total
                if ($r->order) {
                    $r->order->update([
                        'subtotal' => round((float) $r->order->subtotal + $r->extra_charge_total, 2),
                        'total' => round((float) $r->order->total + $r->extra_charge_total, 2),
                    ]);
                }
            }

            $r->update([
                'finalized_at' => $now,
                'extra_ticket_id' => $extraTicketId,
                'status' => 'finalized',
            ]);

            // Eliberează barca
            $r->boat?->update(['status' => 'available']);

            // Eliberează lock-ul pe resource (dacă există pe order_item)
            if ($r->order_item_id) {
                LeisureResourceLock::where('order_item_id', $r->order_item_id)->update(['status' => 'released']);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->error('Eroare la finalizare: ' . $e->getMessage(), 500);
        }

        return $this->success([
            'rental_id' => $r->id,
            'extra_ticket_id' => $extraTicketId,
            'extra_charge_total' => (float) $r->extra_charge_total,
            'boat_status' => 'available',
        ], 'Cursă finalizată');
    }

    // ========================================================================
    // F11 — Shift activ pentru rolul curent al operatorului
    // ========================================================================

    /**
     * GET /marketplace-client/organizer/me/active-shift
     * Returnează shift-ul activ acum pentru organizer-ul autentificat (sau pentru un team_member specific).
     * Folosit de mobile app pentru a determina rolul curent.
     */
    public function myActiveShift(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $now = Carbon::now();
        $teamMemberId = $request->query('team_member_id');

        // Determina team_member curent (din token name = "team-member-{id}").
        $currentTeamMember = null;
        if (!$teamMemberId) {
            $tokenName = $request->user()->currentAccessToken()->name ?? '';
            if (str_starts_with($tokenName, 'team-member-')) {
                $teamMemberId = (int) str_replace('team-member-', '', $tokenName);
            }
        }
        if ($teamMemberId) {
            $currentTeamMember = MarketplaceOrganizerTeamMember::find($teamMemberId);
            if ($currentTeamMember && $currentTeamMember->marketplace_organizer_id !== $organizer->id) {
                $currentTeamMember = null;
            }
        }

        $query = LeisureShift::query()
            ->where('marketplace_organizer_id', $organizer->id)
            ->where('start_at', '<=', $now)
            ->where('end_at', '>=', $now);

        if ($teamMemberId) {
            $query->where('team_member_id', (int) $teamMemberId);
        }

        $shifts = $query->orderByDesc('start_at')->get();

        // Fallback: daca nu exista shift activ, foloseste leisure_role static al team_member-ului.
        $fallbackRole = null;
        if ($shifts->isEmpty() && $currentTeamMember && $currentTeamMember->leisure_role) {
            $fallbackRole = $this->leisureRoleToShiftRole($currentTeamMember->leisure_role);
        }

        return $this->success([
            'now' => $now->toIso8601String(),
            'shifts' => $shifts->map(fn ($s) => [
                'id' => $s->id,
                'team_member_id' => $s->team_member_id,
                'role' => $s->role,
                'gate' => $s->gate,
                'start_at' => $s->start_at->toIso8601String(),
                'end_at' => $s->end_at->toIso8601String(),
                'allowed_features' => $this->featuresForRole($s->role),
            ])->values(),
            'fallback_role' => $fallbackRole,
            'fallback_features' => $fallbackRole ? $this->featuresForRole($fallbackRole) : [],
            'team_member' => $currentTeamMember ? [
                'id' => (string) $currentTeamMember->id,
                'name' => $currentTeamMember->name,
                'role' => $currentTeamMember->role,
                'leisure_role' => $currentTeamMember->leisure_role,
            ] : null,
        ]);
    }

    /**
     * Mapeaza leisure_role (din team_members) la role (din leisure_shifts) astfel
     * incat mobile app foloseste acelasi switch.
     */
    protected function leisureRoleToShiftRole(?string $leisureRole): ?string
    {
        return match ($leisureRole) {
            'check_in'           => 'gate_scanner',
            'rental_boats'       => 'operator_boats',
            'rental_pontoon'     => 'operator_pontoon_rental',
            'validation_pontoon' => 'operator_pontoon',
            'rental_sled'        => 'operator_sled',
            'validation_tow'     => 'operator_tow_validation',
            'pos_cashier'        => 'sales_operator',
            'admin_mobile'       => 'admin_mobile',
            default              => null,
        };
    }

    /**
     * Lista feature-uri permise per rol (folosit la UI gating).
     */
    protected function featuresForRole(?string $role): array
    {
        return match ($role) {
            'gate_scanner' => ['checkin'],
            'sales_operator' => ['pos', 'checkin'],
            'shift_manager' => ['pos', 'checkin', 'reports', 'team'],
            'accountant' => ['reports', 'finance'],
            'operator_boats' => ['boat_rentals', 'checkin', 'pos'],
            'operator_pontoon' => ['pontoon_validation', 'checkin'],
            'operator_pontoon_rental' => ['pontoon_rental', 'pos'],
            'operator_sled' => ['sled_rentals', 'pos'],
            'operator_tow_validation' => ['tow_validation', 'checkin'],
            'admin_mobile' => ['pos', 'checkin', 'reports', 'boat_rentals', 'pontoon_validation', 'pontoon_rental', 'sled_rentals', 'tow_validation'],
            'field_seller' => ['pos_mobile', 'checkin'],
            default => [],
        };
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

    // ========================================================================
    // CASA POS — sesiuni deschidere/inchidere pentru infopoint on-site
    // ========================================================================

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/cashier/current
     * Returneaza sesiunea de casa deschisa curent (daca exista) pentru event.
     */
    public function cashierCurrent(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $eventOrganizerId = $eventModel->marketplace_organizer_id ?? $organizer->id;

        $session = \App\Models\LeisureCashierSession::query()
            ->where('marketplace_organizer_id', $eventOrganizerId)
            ->where('event_id', $eventModel->id)
            ->whereNull('closed_at')
            ->orderByDesc('opened_at')
            ->first();

        // Live drawer totals for the OPEN session (POS orders of this session):
        // cash = physical money to hand over, card = collected electronically.
        // Deliberately POS-only — online sales never touch the physical drawer.
        $live = null;
        if ($session) {
            $sessOrders = Order::where('event_id', $eventModel->id)
                ->whereIn('status', ['paid', 'completed'])
                ->where('source', 'pos')
                ->whereRaw("meta->>'cashier_session_id' = ?", [(string) $session->id]);
            $cash = round((float) (clone $sessOrders)->whereRaw("meta->>'payment_method' = 'cash'")->sum('total'), 2);
            $card = round((float) (clone $sessOrders)->whereRaw("meta->>'payment_method' = 'card'")->sum('total'), 2);
            $live = [
                'cash'   => $cash,
                'card'   => $card,
                'total'  => round($cash + $card, 2),
                'orders' => (clone $sessOrders)->count(),
            ];
        }

        return $this->success([
            'session' => $session ? [
                'id' => $session->id,
                'opened_at' => $session->opened_at?->toIso8601String(),
                'opened_label' => $session->opened_label,
                'live' => $live,
            ] : null,
        ]);
    }

    /**
     * POST /marketplace-client/organizer/events/{event}/leisure/cashier/open
     * Deschide o sesiune noua. Refuza daca deja exista una deschisa.
     */
    public function cashierOpen(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $eventOrganizerId = $eventModel->marketplace_organizer_id ?? $organizer->id;

        // Refuza dublu-deschidere
        $existing = \App\Models\LeisureCashierSession::query()
            ->where('marketplace_organizer_id', $eventOrganizerId)
            ->where('event_id', $eventModel->id)
            ->whereNull('closed_at')
            ->first();
        if ($existing) {
            return $this->error('Există deja o sesiune de casă deschisă (ID ' . $existing->id . ').', 409);
        }

        // Detect team_member din token
        $tmId = null;
        $tmName = null;
        $tokenName = $request->user()?->currentAccessToken()?->name ?? '';
        if (str_starts_with($tokenName, 'team-member-')) {
            $tmId = (int) str_replace('team-member-', '', $tokenName);
            $tmName = \App\Models\MarketplaceOrganizerTeamMember::find($tmId)?->name;
        }

        $session = \App\Models\LeisureCashierSession::create([
            'marketplace_client_id' => $marketplace->id,
            'marketplace_organizer_id' => $eventOrganizerId,
            'event_id' => $eventModel->id,
            'team_member_id' => $tmId,
            'opened_at' => Carbon::now(),
            'opened_label' => $tmName ?: 'InfoPoint',
            'opening_notes' => (string) ($request->input('notes') ?? '') ?: null,
        ]);

        return $this->success([
            'session' => [
                'id' => $session->id,
                'opened_at' => $session->opened_at->toIso8601String(),
                'opened_label' => $session->opened_label,
            ],
        ], 'Casa a fost deschisă.');
    }

    /**
     * POST /marketplace-client/organizer/events/{event}/leisure/cashier/close
     * Inchide sesiunea curenta deschisa + snapshot incasari pe interval.
     */
    public function cashierClose(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $eventOrganizerId = $eventModel->marketplace_organizer_id ?? $organizer->id;

        $session = \App\Models\LeisureCashierSession::query()
            ->where('marketplace_organizer_id', $eventOrganizerId)
            ->where('event_id', $eventModel->id)
            ->whereNull('closed_at')
            ->orderByDesc('opened_at')
            ->first();
        if (!$session) return $this->error('Nu există nicio sesiune de casă deschisă.', 404);

        $closedAt = Carbon::now();
        $openedAt = $session->opened_at ?? Carbon::now()->subHours(12);

        // Incasari pe interval — comenzi platite intre opened_at si closed_at.
        // Include si issuing_company + meta pentru breakdown per societate (SC1/SC2).
        $orders = Order::query()
            ->where('event_id', $eventModel->id)
            ->whereIn('status', ['paid', 'completed'])
            ->whereBetween('paid_at', [$openedAt, $closedAt])
            ->with(['tickets:id,order_id,ticket_type_id,price,status,meta', 'tickets.ticketType:id,name,service_category,issuing_company,meta'])
            ->get(['id', 'total', 'currency', 'source', 'meta']);

        // Preload component issuer map pentru Mix packages — la fel ca in raport()
        $componentIssuerMap = [];
        $mixComponentIds = [];
        foreach ($orders as $o) {
            foreach ($o->tickets as $t) {
                $tt = $t->ticketType ?? null;
                if (!$tt || $tt->service_category !== 'package') continue;
                if (($tt->issuing_company ?? 'primary') !== 'mix') continue;
                $outs = is_array($tt->meta ?? null) ? ($tt->meta['package_outputs'] ?? []) : [];
                foreach ($outs as $out) {
                    if (!empty($out['ticket_type_id'])) $mixComponentIds[(int) $out['ticket_type_id']] = true;
                }
            }
        }
        if (!empty($mixComponentIds)) {
            \App\Models\TicketType::query()
                ->whereIn('id', array_keys($mixComponentIds))
                ->get(['id', 'issuing_company'])
                ->each(function ($tt) use (&$componentIssuerMap) {
                    $componentIssuerMap[$tt->id] = ($tt->issuing_company === 'secondary') ? 'secondary' : 'primary';
                });
        }

        // Nume societati emitente pentru afisare pe carduri
        $issuerNames = [
            'primary' => $organizer->company_name ?: 'Societatea principală',
            'secondary' => $organizer->has_secondary_issuer
                ? ($organizer->secondary_company_name ?: 'Societatea secundară')
                : null,
        ];

        $byPayment = [];
        $byTicketType = [];
        $byIssuer = [
            'primary' => [
                'issuer' => 'primary',
                'name' => $issuerNames['primary'],
                'orders' => 0,
                'tickets' => 0,
                'revenue' => 0.0,
                'by_payment' => ['cash' => 0.0, 'card' => 0.0, 'online' => 0.0],
            ],
            'secondary' => [
                'issuer' => 'secondary',
                'name' => $issuerNames['secondary'],
                'orders' => 0,
                'tickets' => 0,
                'revenue' => 0.0,
                'by_payment' => ['cash' => 0.0, 'card' => 0.0, 'online' => 0.0],
            ],
        ];
        $totalOrders = $orders->count();
        $totalTickets = 0;         // raw: toate ticketele non-cancelled (backwards compat + label "Tickets fizice")
        $totalTicketsSold = 0;     // tranzactii cu valoare (mirror dashboard "Bilete vandute"): exclude from_package + price<=0
        $totalTicketsVisitors = 0; // bilete scanabile (mirror dashboard "Vizitatori"): exclude umbrella parent pachet
        $totalRevenue = 0.0;
        // POS-only aggregates: la inchiderea de casa operatorului ii pasa DOAR de POS
        // (banii fizici din sertar + cardurile trecute). Online-ul nu intra in casa.
        $posOnlyOrders = 0;
        $posOnlyTickets = 0;
        $posOnlyTicketsSold = 0;
        $posOnlyTicketsVisitors = 0;
        $posOnlyRevenue = 0.0;
        // Breakdown pe categorie serviciu (access/parking/activity/rental/extra) - POS only,
        // exclude componentele pachet (from_package) ca sa evitam dublul-numarat.
        $posOnlyByCategory = [];

        foreach ($orders as $o) {
            $rev = (float) ($o->total ?? 0);
            $totalRevenue += $rev;

            $isPos = $o->source === 'pos';
            $pmRaw = $o->meta['payment_method'] ?? null;
            if ($isPos && $pmRaw === 'cash') $pm = 'cash';
            elseif ($isPos && $pmRaw === 'card') $pm = 'card';
            else $pm = 'online';
            if (!isset($byPayment[$pm])) $byPayment[$pm] = ['method' => $pm, 'orders' => 0, 'tickets' => 0, 'revenue' => 0.0];
            $byPayment[$pm]['orders']++;
            $byPayment[$pm]['revenue'] += $rev;

            // Track pos-only totals
            if ($isPos) {
                $posOnlyOrders++;
                $posOnlyRevenue += $rev;
            }

            // Track daca comanda a inclus vreun bilet pe SC1/SC2 (pentru orders count per societate)
            $orderTouchedIssuers = ['primary' => false, 'secondary' => false];

            foreach ($o->tickets as $t) {
                if (in_array($t->status, ['cancelled', 'refunded'], true)) continue;

                $metaArr = is_array($t->meta ?? null) ? $t->meta : [];
                $isFromPackage = !empty($metaArr['from_package']);
                $isUmbrella = !empty($metaArr['is_package_umbrella']);
                $tp = (float) ($t->price ?? 0);
                $tt = $t->ticketType;
                $svcCat = $tt->service_category ?? 'access';
                $isPackageParent = ($svcCat === 'package');

                $totalTickets++;
                // Metrici paralele (aliniere EXACTA cu dashboardLive):
                if (!$isFromPackage && $tp > 0) $totalTicketsSold++;   // "Bilete vandute" (tranzactii cu valoare)
                if (!$isUmbrella) $totalTicketsVisitors++;              // "Vizitatori" (bilete scanabile fizic)
                $byPayment[$pm]['tickets']++;

                // POS-only counters + breakdown pe categorie
                if ($isPos) {
                    $posOnlyTickets++;
                    if (!$isFromPackage && $tp > 0) $posOnlyTicketsSold++;
                    if (!$isUmbrella) $posOnlyTicketsVisitors++;
                    // Category breakdown: exclude umbrella (dubla numaratoare - umbrella e categorie 'package',
                    // dar componentele au categoriile lor reale). Contorizam BILETELE FIZICE (fizic scanabile
                    // la poarta) grupate pe service_category.
                    if (!$isUmbrella) {
                        $catKey = $svcCat ?: 'access';
                        if (!isset($posOnlyByCategory[$catKey])) {
                            $posOnlyByCategory[$catKey] = ['category' => $catKey, 'tickets' => 0, 'revenue' => 0.0];
                        }
                        $posOnlyByCategory[$catKey]['tickets']++;
                        $posOnlyByCategory[$catKey]['revenue'] += $tp;
                    }
                }

                $ttId = $t->ticket_type_id;
                $ttName = $tt->name ?? "Tip #{$ttId}";
                if (is_array($ttName)) $ttName = $ttName['ro'] ?? reset($ttName);
                // Guide bonus -> label_override
                if (is_array($t->meta ?? null) && !empty($t->meta['label_override'])) {
                    $ttName = $t->meta['label_override'];
                    $key = 'lbl_' . $ttName;
                } else {
                    $key = ($isPackageParent ? 'pkg_' : 'tt_') . $ttId;
                }
                if (!isset($byTicketType[$key])) {
                    $byTicketType[$key] = ['ticket_type_id' => $ttId, 'name' => $ttName, 'tickets' => 0, 'revenue' => 0.0];
                }
                $byTicketType[$key]['tickets']++;
                $byTicketType[$key]['revenue'] += $tp;

                // by_issuer: componentele pachet (from_package) NU se contorizeaza aici —
                // pachetul parent poarta pretul si issuer-ul. Skip pentru a evita double-count.
                if ($isFromPackage) continue;
                if ($tp <= 0) continue;

                $issuer = $tt->issuing_company ?? 'primary';

                // Pentru Mix packages: distribuim ratio-ul intre societatile componentelor
                $splits = [];
                if ($isPackageParent && $issuer === 'mix') {
                    $outs = is_array($tt->meta ?? null) ? ($tt->meta['package_outputs'] ?? []) : [];
                    $allocSum = 0.0;
                    foreach ($outs as $out) {
                        if (isset($out['price']) && is_numeric($out['price']) && (float) $out['price'] >= 0) {
                            $allocSum += (float) $out['price'];
                        }
                    }
                    if ($allocSum > 0) {
                        foreach ($outs as $out) {
                            $cid = (int) ($out['ticket_type_id'] ?? 0);
                            $cIssuer = $componentIssuerMap[$cid] ?? 'primary';
                            $portion = isset($out['price']) ? (float) $out['price'] : 0.0;
                            if ($portion <= 0) continue;
                            $splits[] = ['issuer' => $cIssuer, 'amount' => $portion];
                        }
                    }
                }
                if (empty($splits)) {
                    $issuerKey = $issuer === 'secondary' ? 'secondary' : 'primary';
                    $splits[] = ['issuer' => $issuerKey, 'amount' => $tp > 0 ? $tp : 1];
                }

                $splitTotal = array_sum(array_column($splits, 'amount'));
                $biggest = null; $biggestAmt = -1;
                foreach ($splits as $s) {
                    if ($s['amount'] > $biggestAmt) { $biggestAmt = $s['amount']; $biggest = $s['issuer']; }
                    $ratio = $splitTotal > 0 ? $s['amount'] / $splitTotal : 0;
                    $portionRev = $tp * $ratio;
                    $byIssuer[$s['issuer']]['revenue'] += $portionRev;
                    $byIssuer[$s['issuer']]['by_payment'][$pm] = ($byIssuer[$s['issuer']]['by_payment'][$pm] ?? 0) + $portionRev;
                    if (!$orderTouchedIssuers[$s['issuer']]) {
                        $byIssuer[$s['issuer']]['orders']++;
                        $orderTouchedIssuers[$s['issuer']] = true;
                    }
                }
                if ($biggest) $byIssuer[$biggest]['tickets']++;
            }
        }

        foreach ($byPayment as &$row) $row['revenue'] = round($row['revenue'], 2);
        foreach ($byTicketType as &$row) $row['revenue'] = round($row['revenue'], 2);
        foreach ($byIssuer as &$row) {
            $row['revenue'] = round($row['revenue'], 2);
            foreach ($row['by_payment'] as $k => $v) $row['by_payment'][$k] = round($v, 2);
        }
        unset($row);
        usort($byTicketType, fn ($a, $b) => $b['tickets'] <=> $a['tickets']);
        foreach ($posOnlyByCategory as &$row) $row['revenue'] = round($row['revenue'], 2);
        unset($row);
        $posOnlyByCategoryList = array_values($posOnlyByCategory);
        // Ordonare fixa pt UI: acces, parcare, activitate, inchiriere, altele
        $catOrder = ['access' => 1, 'parking' => 2, 'activity' => 3, 'rental' => 4];
        usort($posOnlyByCategoryList, function ($a, $b) use ($catOrder) {
            $oa = $catOrder[$a['category']] ?? 99;
            $ob = $catOrder[$b['category']] ?? 99;
            return $oa <=> $ob;
        });

        $snapshot = [
            'totals' => [
                'orders' => $totalOrders,
                'tickets' => $totalTickets,               // raw (backwards compat)
                'tickets_sold' => $totalTicketsSold,       // "Bilete vandute" - mirror dashboard
                'tickets_visitors' => $totalTicketsVisitors, // "Vizitatori" - mirror dashboard
                'revenue' => round($totalRevenue, 2),
                'avg_order' => $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0,
            ],
            // POS-only totals — cifrele care conteaza pentru operator la inchidere casa
            // (cardul + cash-ul din sertar). Online-ul e IGNORAT complet aici.
            'pos_only_totals' => [
                'orders' => $posOnlyOrders,
                'tickets' => $posOnlyTickets,
                'tickets_sold' => $posOnlyTicketsSold,
                'tickets_visitors' => $posOnlyTicketsVisitors,
                'revenue' => round($posOnlyRevenue, 2),
                'avg_order' => $posOnlyOrders > 0 ? round($posOnlyRevenue / $posOnlyOrders, 2) : 0,
            ],
            'by_payment' => array_values($byPayment),
            'by_ticket_type' => array_values($byTicketType),
            // Breakdown pe categorie serviciu (access/parking/activity/rental/etc) - POS ONLY.
            // Contorizeaza bilete fizice scanabile (exclude umbrella pachet).
            'by_category_pos' => $posOnlyByCategoryList,
            // Breakdown per societate emitenta (SC1/SC2), fiecare cu by_payment nested.
            // Secondary null cand organizatorul nu are has_secondary_issuer setat.
            'by_issuer' => [
                'primary' => $byIssuer['primary'],
                'secondary' => $issuerNames['secondary'] ? $byIssuer['secondary'] : null,
            ],
        ];

        $session->update([
            'closed_at' => $closedAt,
            'closing_snapshot' => $snapshot,
            'closing_notes' => (string) ($request->input('notes') ?? '') ?: null,
        ]);

        return $this->success([
            'session' => [
                'id' => $session->id,
                'opened_at' => $session->opened_at->toIso8601String(),
                'closed_at' => $closedAt->toIso8601String(),
                'opened_label' => $session->opened_label,
                'duration_minutes' => (int) $openedAt->diffInMinutes($closedAt),
            ],
            'snapshot' => $snapshot,
        ], 'Casa a fost închisă.');
    }

    // ========================================================================
    // SCANARI — raport per zi (expected/valid/staff/invalid) + detaliu per zi
    // ========================================================================

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/scans?from=&to=
     *
     * Chart per zi cu:
     *   - expected: bilete acces cu visit_date=zi + status IN (valid/used) — cate scanari de intrare ar trebui sa avem
     *   - valid: bilete efectiv scanate (checked_in_at cade in ziua respectiva)
     *   - staff: staff check-ins (leisure_staff_checkins) pe zi
     *   - invalid: leisure_scan_attempts pe zi (result IN invalid/duplicate)
     */
    public function scansOverview(Request $request, int $event): JsonResponse
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
        // Interpretam intervalul ca zile RO. Query-urile DB folosesc bounds UTC
        // (coloanele stocheaza UTC), iar iterarea zilelor + bucket-urile
        // folosesc datele RO — asa evitam mismatch-uri tz + label-uri corecte.
        $fromRo = isset($validated['from'])
            ? Carbon::parse($validated['from'], 'Europe/Bucharest')->startOfDay()
            : Carbon::today('Europe/Bucharest')->subDays(29)->startOfDay();
        $toRo = isset($validated['to'])
            ? Carbon::parse($validated['to'], 'Europe/Bucharest')->endOfDay()
            : Carbon::today('Europe/Bucharest')->endOfDay();
        // Bounds efective pentru whereBetween (UTC)
        $from = $fromRo->copy()->setTimezone('UTC');
        $to = $toRo->copy()->setTimezone('UTC');

        // Expected: bilete acces (service_category='access') cu visit_date in interval,
        // status IN (valid/used), pe comenzi platite. Grupam pe visit_date din meta.
        $expected = \App\Models\Ticket::query()
            ->whereHas('ticketType', fn ($q) => $q->where('event_id', $eventModel->id)->where('service_category', 'access'))
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['paid', 'confirmed', 'completed']))
            ->whereIn('status', ['valid', 'used'])
            ->get(['id', 'meta', 'checked_in_at']);
        $expectedByDay = [];
        $roFromStr = $fromRo->toDateString();
        $roToStr = $toRo->toDateString();
        foreach ($expected as $t) {
            $vd = is_array($t->meta ?? null) ? ($t->meta['visit_date'] ?? null) : null;
            if (!$vd) continue;
            if ($vd < $roFromStr || $vd > $roToStr) continue;
            $expectedByDay[$vd] = ($expectedByDay[$vd] ?? 0) + 1;
        }

        // Valid: bilete cu checked_in_at pe ziua respectiva (in fus RO)
        $validScans = \App\Models\Ticket::query()
            ->whereHas('ticketType', fn ($q) => $q->where('event_id', $eventModel->id))
            ->whereBetween('checked_in_at', [$from, $to])
            ->get(['id', 'checked_in_at']);
        $validByDay = [];
        foreach ($validScans as $t) {
            $day = $t->checked_in_at?->copy()->setTimezone('Europe/Bucharest')->toDateString();
            if (!$day) continue;
            $validByDay[$day] = ($validByDay[$day] ?? 0) + 1;
        }

        // Staff: leisure_staff_checkins. Include si scan-uri unde event_id e NULL
        // (kiosk / mobile care nu trimit event_id) — le filtram prin staffMember
        // sa apartina organizer-ului curent. NU putem sa cerem event_id strict
        // pentru ca /organizator/staff-raport valideaza corect prin staff, iar
        // acest modal ar fi de altfel gol.
        $staffScans = \App\Models\LeisureStaffCheckin::query()
            ->where(function ($q) use ($eventModel, $organizer) {
                $q->where('event_id', $eventModel->id)
                  ->orWhere(function ($qq) use ($organizer) {
                      $qq->whereNull('event_id')
                         ->whereHas('staffMember', fn ($s) => $s->where('marketplace_organizer_id', $organizer->id));
                  });
            })
            ->whereBetween('checked_in_at', [$from, $to])
            ->get(['id', 'checked_in_at']);
        $staffByDay = [];
        foreach ($staffScans as $c) {
            $day = $c->checked_in_at?->copy()->setTimezone('Europe/Bucharest')->toDateString();
            if (!$day) continue;
            $staffByDay[$day] = ($staffByDay[$day] ?? 0) + 1;
        }

        // Invalid: leisure_scan_attempts (defensive: tabela poate lipsi pe environments
        // unde migratia noua nu a rulat inca — tratam ca 0 in loc de 500).
        // Include si scan attempts unde event_id e NULL (edge case) daca vin de
        // la acest organizer.
        $invalidByDay = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('leisure_scan_attempts')) {
            $invalidScans = \App\Models\LeisureScanAttempt::query()
                ->where(function ($q) use ($eventModel, $organizer) {
                    $q->where('event_id', $eventModel->id)
                      ->orWhere(function ($qq) use ($organizer) {
                          $qq->whereNull('event_id')
                             ->where('marketplace_organizer_id', $organizer->id);
                      });
                })
                ->whereBetween('occurred_at', [$from, $to])
                ->get(['id', 'occurred_at']);
            foreach ($invalidScans as $s) {
                $day = $s->occurred_at?->copy()->setTimezone('Europe/Bucharest')->toDateString();
                if (!$day) continue;
                $invalidByDay[$day] = ($invalidByDay[$day] ?? 0) + 1;
            }
        }

        // Compune array de zile (fara gap-uri), iterare pe RO
        $rows = [];
        $cursor = $fromRo->copy();
        while ($cursor->lte($toRo)) {
            $day = $cursor->toDateString();
            $rows[] = [
                'date' => $day,
                'expected' => (int) ($expectedByDay[$day] ?? 0),
                'valid' => (int) ($validByDay[$day] ?? 0),
                'staff' => (int) ($staffByDay[$day] ?? 0),
                'invalid' => (int) ($invalidByDay[$day] ?? 0),
            ];
            $cursor->addDay();
        }

        return $this->success([
            'from' => $fromRo->toDateString(),
            'to' => $toRo->toDateString(),
            'rows' => $rows,
            'totals' => [
                'expected' => array_sum(array_column($rows, 'expected')),
                'valid' => array_sum(array_column($rows, 'valid')),
                'staff' => array_sum(array_column($rows, 'staff')),
                'invalid' => array_sum(array_column($rows, 'invalid')),
            ],
        ]);
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/scans-detail?date=YYYY-MM-DD
     * Lista TOATE scanarile inregistrate intr-o zi anume.
     */
    public function scansDetail(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $validated = $request->validate(['date' => 'required|date']);
        // Interpretam data ca ora locala RO, apoi convertim la UTC pentru query
        // (coloanele DB stocheaza UTC). Fara conversie, laravel format() strip-uie
        // tz-ul si compara ora locala string direct cu ora UTC din DB -> mismatch.
        $day = Carbon::parse($validated['date'], 'Europe/Bucharest');
        $dayStart = $day->copy()->startOfDay()->setTimezone('UTC');
        $dayEnd = $day->copy()->endOfDay()->setTimezone('UTC');

        $items = [];

        // 1) Bilete acces (valid checked in)
        $validScans = \App\Models\Ticket::query()
            ->with(['ticketType:id,name', 'order.marketplaceCustomer:id,first_name,last_name,email', 'order:id,customer_name,customer_email'])
            ->whereHas('ticketType', fn ($q) => $q->where('event_id', $eventModel->id))
            ->whereBetween('checked_in_at', [$dayStart, $dayEnd])
            ->orderBy('checked_in_at')
            ->get(['id', 'order_id', 'ticket_type_id', 'code', 'checked_in_at', 'attendee_name', 'attendee_email', 'meta']);
        foreach ($validScans as $t) {
            $cust = $t->order?->marketplaceCustomer;
            $name = $cust ? trim(($cust->first_name ?? '') . ' ' . ($cust->last_name ?? '')) : ($t->attendee_name ?? $t->order?->customer_name ?? 'Client');
            $email = $cust?->email ?? $t->attendee_email ?? $t->order?->customer_email;
            $items[] = [
                'type' => 'ticket_valid',
                'time' => $t->checked_in_at?->copy()->setTimezone('Europe/Bucharest')->format('H:i:s'),
                'timestamp' => $t->checked_in_at?->toIso8601String(),
                'name' => $name ?: '—',
                'status' => 'valid',
                'code' => $t->code,
                'email' => $email,
                'ticket_type' => is_array($t->ticketType?->name) ? ($t->ticketType->name['ro'] ?? '') : $t->ticketType?->name,
            ];
        }

        // 2) Staff check-ins. Include si event_id=NULL cand staff-ul apartine
        // acestui organizer — vezi staff-raport care nu filtreaza dupa event_id
        // (scanner-ul Kiosk nu trimite event_id, /organizator/staff-raport
        // valideaza prin staff, deci trebuie sa fie consistent aici).
        $staffScans = \App\Models\LeisureStaffCheckin::query()
            ->with(['staffMember:id,first_name,last_name,position'])
            ->where(function ($q) use ($eventModel, $organizer) {
                $q->where('event_id', $eventModel->id)
                  ->orWhere(function ($qq) use ($organizer) {
                      $qq->whereNull('event_id')
                         ->whereHas('staffMember', fn ($s) => $s->where('marketplace_organizer_id', $organizer->id));
                  });
            })
            ->whereBetween('checked_in_at', [$dayStart, $dayEnd])
            ->orderBy('checked_in_at')
            ->get(['id', 'staff_member_id', 'checked_in_at', 'location']);
        foreach ($staffScans as $c) {
            $sm = $c->staffMember;
            $name = $sm ? trim(($sm->first_name ?? '') . ' ' . ($sm->last_name ?? '')) : 'Angajat';
            $items[] = [
                'type' => 'staff',
                'time' => $c->checked_in_at?->copy()->setTimezone('Europe/Bucharest')->format('H:i:s'),
                'timestamp' => $c->checked_in_at?->toIso8601String(),
                'name' => $name . ($sm?->position ? ' · ' . $sm->position : ''),
                'status' => 'staff',
                'code' => null,
                'email' => null,
                'ticket_type' => 'Angajat',
            ];
        }

        // 3) Invalid attempts (defensive: table poate lipsi). Include si event_id=NULL
        // pentru acest organizer, ca si la staff.
        $invalidScans = \Illuminate\Support\Facades\Schema::hasTable('leisure_scan_attempts')
            ? \App\Models\LeisureScanAttempt::query()
                ->where(function ($q) use ($eventModel, $organizer) {
                    $q->where('event_id', $eventModel->id)
                      ->orWhere(function ($qq) use ($organizer) {
                          $qq->whereNull('event_id')
                             ->where('marketplace_organizer_id', $organizer->id);
                      });
                })
                ->whereBetween('occurred_at', [$dayStart, $dayEnd])
                ->orderBy('occurred_at')
                ->get(['id', 'attempted_code', 'result', 'reason', 'occurred_at'])
            : collect();
        foreach ($invalidScans as $s) {
            $items[] = [
                'type' => 'invalid',
                'time' => $s->occurred_at?->copy()->setTimezone('Europe/Bucharest')->format('H:i:s'),
                'timestamp' => $s->occurred_at?->toIso8601String(),
                'name' => '—',
                'status' => $s->result, // 'invalid' | 'duplicate'
                'code' => $s->attempted_code,
                'email' => null,
                'ticket_type' => $s->reason ?: '—',
            ];
        }

        // Sortez cronologic
        usort($items, fn ($a, $b) => strcmp((string) $a['timestamp'], (string) $b['timestamp']));

        return $this->success([
            'date' => $day->toDateString(),
            'items' => $items,
            'totals' => [
                'valid' => count(array_filter($items, fn ($i) => $i['type'] === 'ticket_valid')),
                'staff' => count(array_filter($items, fn ($i) => $i['type'] === 'staff')),
                'invalid' => count(array_filter($items, fn ($i) => $i['type'] === 'invalid')),
            ],
        ]);
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/cashier/sessions?date=YYYY-MM-DD
     * Sesiuni de casa pentru o zi (default azi). Folosit pe leisure-pos ca
     * 'desfasurator' cand casa e inchisa.
     */
    public function cashierSessions(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);
        $eventOrganizerId = $eventModel->marketplace_organizer_id ?? $organizer->id;

        $dateInput = $request->query('date');
        $dayRo = $dateInput ? Carbon::parse($dateInput, 'Europe/Bucharest') : Carbon::now('Europe/Bucharest');
        // Coloanele opened_at/closed_at sunt stocate in UTC de PostgreSQL. Trebuie
        // sa convertim bounds la UTC pentru whereBetween, altfel sesiunea deschisa
        // pe 03.07 21:02 RO si inchisa 04.07 02:29 RO (= 03.07 23:29 UTC) nu apare
        // la 04.07 pentru ca 23:29 UTC nu e intre 04.07 00:00 si 04.07 23:59
        // interpretate ca string-uri fara timezone.
        $dayStart = $dayRo->copy()->startOfDay()->setTimezone('UTC');
        $dayEnd = $dayRo->copy()->endOfDay()->setTimezone('UTC');

        // Sesiuni deschise SAU inchise in aceasta zi (RO)
        $sessions = \App\Models\LeisureCashierSession::query()
            ->where('marketplace_organizer_id', $eventOrganizerId)
            ->where('event_id', $eventModel->id)
            ->where(function ($q) use ($dayStart, $dayEnd) {
                $q->whereBetween('opened_at', [$dayStart, $dayEnd])
                  ->orWhereBetween('closed_at', [$dayStart, $dayEnd]);
            })
            ->orderByDesc('opened_at')
            ->get();

        // Fallback: cand nu sunt sesiuni astazi (sau doar deschise fara snapshot),
        // includem ULTIMA sesiune inchisa cu snapshot ca referinta. Marcat cu
        // 'is_last_closed_reference'=true ca frontend-ul sa-l afiseze sub o alta
        // sectiune ("Ultima inchidere de casa").
        $hasClosedToday = $sessions->contains(fn ($s) => $s->closed_at !== null);
        $lastClosedRef = null;
        if (!$hasClosedToday) {
            $last = \App\Models\LeisureCashierSession::query()
                ->where('marketplace_organizer_id', $eventOrganizerId)
                ->where('event_id', $eventModel->id)
                ->whereNotNull('closed_at')
                ->orderByDesc('closed_at')
                ->first();
            if ($last && !$sessions->contains(fn ($s) => $s->id === $last->id)) {
                $lastClosedRef = $last;
            }
        }

        $mapSession = function ($s, bool $isReference = false) {
            return [
                'id' => $s->id,
                'opened_at' => $s->opened_at?->toIso8601String(),
                'closed_at' => $s->closed_at?->toIso8601String(),
                'opened_label' => $s->opened_label,
                'duration_minutes' => $s->closed_at ? (int) $s->opened_at->diffInMinutes($s->closed_at) : null,
                'snapshot' => $s->closing_snapshot,
                'is_open' => $s->closed_at === null,
                'is_last_closed_reference' => $isReference,
            ];
        };

        return $this->success([
            'date' => $dayRo->toDateString(),
            'sessions' => $sessions->map(fn ($s) => $mapSession($s, false))->values(),
            'last_closed_reference' => $lastClosedRef ? $mapSession($lastClosedRef, true) : null,
        ]);
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/cashier/sales-csv?date=YYYY-MM-DD
     *
     * Streamuieste CSV cu toate vanzarile din ziua ceruta (RO tz). O linie per
     * BILET emis, cu toate detaliile: comanda, timp platire, sesiune casa, metoda,
     * societate, tip bilet, pret, casier, plus firma cumparator cand e cazul.
     *
     * Folosit din butonul Export CSV in Desfasurator Casa (leisure-pos.php).
     */
    public function cashierDaySalesCsv(Request $request, int $event): mixed
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $dateInput = $request->query('date');
        $dayRo = $dateInput ? Carbon::parse($dateInput, 'Europe/Bucharest') : Carbon::now('Europe/Bucharest');
        // Bounds UTC pentru whereBetween (aceeasi logica ca la scans/cashierSessions).
        $dayStart = $dayRo->copy()->startOfDay()->setTimezone('UTC');
        $dayEnd = $dayRo->copy()->endOfDay()->setTimezone('UTC');

        $eventOrganizerId = $eventModel->marketplace_organizer_id ?? $organizer->id;

        // Preload team members pentru nume operatori POS.
        $orders = Order::query()
            ->where('event_id', $eventModel->id)
            ->whereIn('status', ['paid', 'completed'])
            ->whereBetween('paid_at', [$dayStart, $dayEnd])
            ->with(['tickets:id,order_id,ticket_type_id,price,status,meta,code,barcode,attendee_name,attendee_email', 'tickets.ticketType:id,name,service_category,issuing_company'])
            ->orderBy('paid_at')
            ->get(['id', 'order_number', 'customer_name', 'customer_email', 'customer_phone', 'total', 'currency', 'source', 'paid_at', 'meta']);

        $tmIds = [];
        foreach ($orders as $o) {
            $tmId = $o->meta['cashier_team_member_id'] ?? null;
            if ($tmId) $tmIds[(int) $tmId] = true;
        }
        $tmNames = [];
        if (!empty($tmIds)) {
            \App\Models\MarketplaceOrganizerTeamMember::query()
                ->whereIn('id', array_keys($tmIds))
                ->get(['id', 'name'])
                ->each(function ($tm) use (&$tmNames) { $tmNames[$tm->id] = $tm->name; });
        }

        // Preload cashier sessions din zi pentru mapare order → sesiune
        $sessions = \App\Models\LeisureCashierSession::query()
            ->where('marketplace_organizer_id', $eventOrganizerId)
            ->where('event_id', $eventModel->id)
            ->where(function ($q) use ($dayStart, $dayEnd) {
                $q->whereBetween('opened_at', [$dayStart, $dayEnd])
                  ->orWhereBetween('closed_at', [$dayStart, $dayEnd]);
            })
            ->get(['id', 'opened_at', 'closed_at', 'opened_label']);
        $sessionLabelById = [];
        foreach ($sessions as $s) {
            $sessionLabelById[$s->id] = ($s->opened_label ?: 'Sesiune #' . $s->id)
                . ' (' . $s->opened_at?->copy()->setTimezone('Europe/Bucharest')->format('H:i')
                . ($s->closed_at ? '–' . $s->closed_at->copy()->setTimezone('Europe/Bucharest')->format('H:i') : '–deschis')
                . ')';
        }

        $issuerNames = [
            'primary' => $organizer->company_name ?: 'SC1',
            'secondary' => $organizer->secondary_company_name ?: 'SC2',
            'mix' => 'Mix',
        ];

        $filename = 'vanzari-' . $dayRo->format('Y-m-d') . '-event-' . $eventModel->id . '.csv';
        $columns = [
            'Data', 'Ora', 'Nr. comanda', 'Sursa', 'Metoda plata',
            'Sesiune casa', 'Casier',
            'Tip bilet', 'Societate emitenta', 'Cod bilet', 'Pret bilet (RON)',
            'Categorie serviciu', 'Componenta pachet (from_package)', 'Bonus ghid',
            'Nume client', 'Email client', 'Telefon client',
            'Firma nume', 'Firma CUI', 'Firma Reg.Com', 'Firma adresa',
            'Total comanda (RON)', 'Comanda cu factura', 'Numar factura',
        ];

        return response()->streamDownload(function () use ($orders, $sessionLabelById, $tmNames, $issuerNames, $columns) {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM pentru Excel (afiseaza diacritice corect)
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns, ';', escape: '\\');

            foreach ($orders as $o) {
                $paidAtRo = $o->paid_at?->copy()->setTimezone('Europe/Bucharest');
                $dataStr = $paidAtRo ? $paidAtRo->format('d.m.Y') : '';
                $oraStr = $paidAtRo ? $paidAtRo->format('H:i:s') : '';
                $isPos = $o->source === 'pos';
                $pmRaw = $o->meta['payment_method'] ?? null;
                if ($isPos && $pmRaw === 'cash') $pmLabel = 'Cash (POS)';
                elseif ($isPos && $pmRaw === 'card') $pmLabel = 'Card (POS)';
                elseif ($isPos && $pmRaw === 'invoice') $pmLabel = 'Link email (POS)';
                else $pmLabel = 'Online';
                $sourceLabel = $isPos ? 'POS' : 'Online';
                $sessionId = $o->meta['cashier_session_id'] ?? null;
                $sessionLabel = $sessionId ? ($sessionLabelById[(int) $sessionId] ?? ('Sesiune #' . $sessionId)) : '';
                $tmId = $o->meta['cashier_team_member_id'] ?? null;
                $casierLabel = $tmId ? ($tmNames[(int) $tmId] ?? ('Angajat #' . $tmId)) : ($isPos ? 'InfoPoint' : '—');

                $company = is_array($o->meta['company_billing'] ?? null) ? $o->meta['company_billing'] : [];
                $companyName = $company['name'] ?? '';
                $companyCui = $company['cui'] ?? '';
                $companyReg = $company['reg_no'] ?? '';
                $companyAddr = $company['address'] ?? '';
                $invoiceNumber = is_array($o->meta) ? ($o->meta['invoice_number'] ?? '') : '';
                $withInvoice = !empty($invoiceNumber) ? 'DA' : (!empty($o->meta['invoice_requested']) ? 'CERUT' : '');
                $total = (float) ($o->total ?? 0);

                if ($o->tickets->isEmpty()) {
                    // Comanda fara bilete — o linie summar, pentru completitudine
                    fputcsv($out, [
                        $dataStr, $oraStr, $o->order_number, $sourceLabel, $pmLabel,
                        $sessionLabel, $casierLabel,
                        '(fara bilete)', '', '', '',
                        '', '', '',
                        $o->customer_name, $o->customer_email, $o->customer_phone,
                        $companyName, $companyCui, $companyReg, $companyAddr,
                        number_format($total, 2, '.', ''), $withInvoice, $invoiceNumber,
                    ], ';', escape: '\\');
                    continue;
                }

                foreach ($o->tickets as $t) {
                    if (in_array($t->status, ['cancelled', 'refunded'], true)) continue;
                    $tt = $t->ticketType;
                    $ttName = $tt->name ?? 'Bilet';
                    if (is_array($ttName)) $ttName = $ttName['ro'] ?? reset($ttName);
                    if (is_array($t->meta ?? null) && !empty($t->meta['label_override'])) {
                        $ttName = $t->meta['label_override'];
                    }
                    $issuer = $tt->issuing_company ?? 'primary';
                    $issuerName = $issuerNames[$issuer] ?? $issuer;
                    $svcCat = $tt->service_category ?? 'access';
                    $isFromPackage = is_array($t->meta ?? null) && !empty($t->meta['from_package']);
                    $isGuideBonus = is_array($t->meta ?? null) && !empty($t->meta['guide_bonus']);

                    fputcsv($out, [
                        $dataStr, $oraStr, $o->order_number, $sourceLabel, $pmLabel,
                        $sessionLabel, $casierLabel,
                        $ttName, $issuerName, $t->code ?: $t->barcode, number_format((float) ($t->price ?? 0), 2, '.', ''),
                        $svcCat, $isFromPackage ? 'DA' : 'NU', $isGuideBonus ? 'DA' : 'NU',
                        $t->attendee_name ?: $o->customer_name, $t->attendee_email ?: $o->customer_email, $o->customer_phone,
                        $companyName, $companyCui, $companyReg, $companyAddr,
                        number_format($total, 2, '.', ''), $withInvoice, $invoiceNumber,
                    ], ';', escape: '\\');
                }
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/weather
     *
     * Prognoza meteo 7 zile pentru locatia evenimentului leisure.
     * Coordonatele lat/lng vin din Venue-ul evenimentului. Cache 1h (in service).
     *
     * Return: { location: {lat,lng}, timezone, days: [...] } sau null daca:
     *  - Event fara venue asociat
     *  - Venue fara lat/lng (null in DB)
     *  - Open-Meteo indisponibil (fail-safe → widget UI se ascunde)
     */
    public function weather(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->with('venue:id,name,city,lat,lng')
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $venue = $eventModel->venue;
        if (!$venue || $venue->lat === null || $venue->lng === null) {
            // Nu returnam eroare — front-end-ul ascunde widget-ul cand data e null.
            return $this->success(['forecast' => null, 'reason' => 'no_coordinates']);
        }

        $svc = app(\App\Services\Leisure\WeatherService::class);
        $forecast = $svc->getForecast((float) $venue->lat, (float) $venue->lng, 7);

        // Venue.name este JSON translatable via Translatable trait -> extract RO
        // (fallback EN, apoi prima cheie). Fara asta UI-ul afiseaza "[object Object]".
        $venueName = $venue->getTranslation('name', 'ro')
            ?: $venue->getTranslation('name', 'en')
            ?: (is_array($venue->name) ? (reset($venue->name) ?: '') : (string) $venue->name);

        return $this->success([
            'forecast' => $forecast,
            'venue' => ['id' => $venue->id, 'name' => $venueName, 'city' => $venue->city],
        ]);
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/dashboard/compare
     *
     * Comparativ cifre-cheie pentru dashboard: azi vs ieri vs saptamana trecuta
     * vs luna trecuta vs anul trecut. Fiecare snapshot = ZIUA INTREAGA (00:00–23:59
     * in RO tz) a datei respective — nu ora curenta.
     *
     * Metrici comparate:
     *  - revenue (order.total, paid/completed)
     *  - orders (count)
     *  - tickets_sold (tranzactii cu valoare: exclude from_package + price=0)
     *  - checkins (tickets.checked_in_at in intervalul zilei)
     *
     * Cache: azi 60s (schimba live); istorice 24h (imutabile).
     */
    public function dashboardCompare(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $tz = 'Europe/Bucharest';
        $now = Carbon::now($tz);
        $today = $now->copy()->startOfDay();

        // Perioadele de comparat: fiecare = ziua intreaga pt data respectiva
        $periods = [
            'today'      => $today->copy(),
            'yesterday'  => $today->copy()->subDay(),
            'last_week'  => $today->copy()->subWeek(),   // aceeasi zi a saptamanii, cu 7 zile in urma
            'last_month' => $today->copy()->subMonth(),  // aceeasi data luna trecuta
            'last_year'  => $today->copy()->subYear(),   // aceeasi data anul trecut
        ];

        // FAIR TIMING: la ora curenta X, "azi" acopera doar 00:00-now (partial).
        // Compararea cu istorice pana la 23:59 (zi intreaga) e nefair - istoricele
        // par mult mai mari. Aplicam cutoff = ora curenta pe TOATE perioadele
        // ("pana la ora X din fiecare zi"). Sfarsit-de-zi ramane afisat doar
        // pentru istorice sub "full_day" opt (nu implementat aici).
        $cutoffHms = $now->format('H:i:s');

        $result = [];
        foreach ($periods as $key => $day) {
            // Azi cache scurt (60s), istorice mai lung (600s) pt ca ora cutoff
            // se schimba minut-de-minut. Anterior aveam 24h dar acum snapshot-ul
            // depinde de ora curenta -> nu mai e imutabil.
            $ttl = $key === 'today' ? 60 : 600;
            $cacheKey = "leisure_compare_v3_{$eventModel->id}_{$key}_" . $day->format('Y-m-d') . '_' . $now->format('H');
            try {
                $result[$key] = Cache::remember($cacheKey, $ttl, function () use ($eventModel, $day, $tz, $cutoffHms) {
                    return $this->buildDaySnapshot($eventModel->id, $day, $tz, $cutoffHms);
                });
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[LeisureCompare] snapshot failed', [
                    'key' => $key, 'date' => $day->format('Y-m-d'), 'error' => $e->getMessage(),
                    'file' => $e->getFile(), 'line' => $e->getLine(),
                ]);
                // Fallback safe: zero-uri, ca sa nu picam tot endpointul dintr-o zi problematica
                $result[$key] = [
                    'date' => $day->format('Y-m-d'),
                    'revenue' => 0.0, 'orders' => 0, 'tickets_sold' => 0, 'checkins' => 0,
                    'error' => true,
                ];
            }
        }

        // Delta computed vs today pentru fiecare cheie (pentru UI convenienta)
        $t = $result['today'];
        foreach (['yesterday', 'last_week', 'last_month', 'last_year'] as $k) {
            $result[$k]['delta_vs_today'] = [
                'revenue' => $this->pctDelta((float) $t['revenue'], (float) $result[$k]['revenue']),
                'orders' => $this->pctDelta((int) $t['orders'], (int) $result[$k]['orders']),
                'tickets_sold' => $this->pctDelta((int) $t['tickets_sold'], (int) $result[$k]['tickets_sold']),
                'checkins' => $this->pctDelta((int) $t['checkins'], (int) $result[$k]['checkins']),
            ];
        }

        return $this->success([
            'timezone' => $tz,
            'cutoff_time' => $now->format('H:i'),
            'note' => 'Comparare fair: cifrele pentru fiecare zi sunt pana la ora ' . $now->format('H:i') . ' (aceeasi ora ca azi).',
            'snapshots' => $result,
        ]);
    }

    /**
     * Delta procentual: today vs old. Return null cand old = 0 (nu putem calcula %).
     */
    private function pctDelta(float|int $today, float|int $old): ?float
    {
        if ($old == 0) return null; // divide by zero
        return round((($today - $old) / $old) * 100, 1);
    }

    /**
     * Snapshot pt o zi RO tz. Cand primeste $cutoffHms (ex: "08:30:00"), fereastra
     * este 00:00 → cutoffHms; altfel = zi calendaristica intreaga.
     * Cutoff-ul permite compararea fair intre azi partial si istorice pana la
     * aceeasi ora ("azi 8:00 vs ieri 8:00" in loc de "azi 8:00 vs ieri 23:59").
     */
    private function buildDaySnapshot(int $eventId, Carbon $dayRo, string $tz, ?string $cutoffHms = null): array
    {
        $start = $dayRo->copy()->startOfDay()->setTimezone('UTC');
        if ($cutoffHms) {
            [$h, $m, $s] = array_pad(array_map('intval', explode(':', $cutoffHms)), 3, 0);
            $end = $dayRo->copy()->setTime($h, $m, $s)->setTimezone('UTC');
        } else {
            $end = $dayRo->copy()->endOfDay()->setTimezone('UTC');
        }

        $orders = Order::query()
            ->where('event_id', $eventId)
            ->whereIn('status', ['paid', 'completed'])
            ->whereBetween('paid_at', [$start, $end])
            ->get(['id', 'total']);
        $revenue = round((float) $orders->sum('total'), 2);
        $ordersCount = $orders->count();

        // Bilete vandute (tranzactii cu valoare) — aliniat cu dashboardLive:
        // exclude from_package + price<=0.
        $ticketsBase = \App\Models\Ticket::query()
            ->whereHas('order', fn ($q) => $q
                ->where('event_id', $eventId)
                ->whereIn('status', ['paid', 'completed'])
                ->whereBetween('paid_at', [$start, $end]))
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->get(['id', 'price', 'meta']);
        $ticketsSold = 0;
        foreach ($ticketsBase as $t) {
            $meta = is_array($t->meta ?? null) ? $t->meta : [];
            $isFromPackage = !empty($meta['from_package']);
            if (!$isFromPackage && (float) ($t->price ?? 0) > 0) {
                $ticketsSold++;
            }
        }

        // Check-ins in ziua respectiva (tickets.checked_in_at cade in interval)
        $checkins = \App\Models\Ticket::query()
            ->whereHas('order', fn ($q) => $q->where('event_id', $eventId))
            ->whereNotNull('checked_in_at')
            ->whereBetween('checked_in_at', [$start, $end])
            ->count();

        return [
            'date' => $dayRo->format('Y-m-d'),
            'revenue' => $revenue,
            'orders' => $ordersCount,
            'tickets_sold' => $ticketsSold,
            'checkins' => $checkins,
        ];
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/sales/summary?from=&to=
     *
     * Snapshot bogat pentru cardurile din /organizator/leisure-sales:
     *   - revenue / comision / net cu split online-vs-POS (via SalesBreakdownService)
     *   - orders, tickets_transactions (386-style), tickets_physical (570-style)
     *   - tickets_by_category (breakdown service_category peste biletele fizice)
     *   - cash_gross / card_gross pe POS (brut, nu net)
     *   - sesiuni casa POS din interval cu operator + open/close + cash/card
     */
    public function salesSummary(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $tz = 'Europe/Bucharest';
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'), $tz)->startOfDay()
            : Carbon::now($tz)->subDays(7)->startOfDay();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'), $tz)->endOfDay()
            : Carbon::now($tz)->endOfDay();
        if ($to->lt($from)) { [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()]; }
        $fromUtc = $from->copy()->utc();
        $toUtc = $to->copy()->utc();

        $eventOrganizerId = $eventModel->marketplace_organizer_id ?? $organizer->id;

        // === Revenue + commission (autoritative, via SalesBreakdownService) ===
        /** @var \App\Services\Marketplace\SalesBreakdownService $svc */
        $svc = app(\App\Services\Marketplace\SalesBreakdownService::class);
        $onlineBd = $svc->build($eventModel, $from, $to, excludePos: true, dateColumn: 'paid_at');
        $posBd    = $svc->build($eventModel, $from, $to, onlyPos: true,   dateColumn: 'paid_at');
        $revOnline  = round((float) ($onlineBd['total_revenue'] ?? 0), 2);
        $revPos     = round((float) ($posBd['total_revenue'] ?? 0), 2);
        $commOnline = round((float) ($onlineBd['total_commission'] ?? 0), 2);
        $commPos    = round((float) ($posBd['total_commission'] ?? 0), 2);
        $revTotal   = round($revOnline + $revPos, 2);
        $commTotal  = round($commOnline + $commPos, 2);

        // === Orders + tickets count + by_category (per-order iterate) ===
        $orders = Order::query()
            ->where('event_id', $eventModel->id)
            ->whereIn('status', ['paid', 'completed'])
            ->whereBetween('paid_at', [$fromUtc, $toUtc])
            ->with(['tickets:id,order_id,ticket_type_id,price,status,meta', 'tickets.ticketType:id,service_category'])
            ->get(['id', 'source', 'meta', 'total', 'paid_at']);

        $ordersTotal = $orders->count();
        $ticketsTransactions = 0;   // 386-style: umbrella pachet + bilete individuale (cu pret)
        $ticketsPhysical = 0;        // 570-style: bilete fizice scanabile (componente + individuale + bonus, EXCL umbrella)
        $ticketsByCategory = [];     // service_category => count (peste fizice)
        $cashGross = 0.0;
        $cardGross = 0.0;
        foreach ($orders as $o) {
            $isPos = $o->source === 'pos';
            $pm = $o->meta['payment_method'] ?? null;
            if ($isPos && $pm === 'cash') $cashGross += (float) ($o->total ?? 0);
            elseif ($isPos && $pm === 'card') $cardGross += (float) ($o->total ?? 0);

            foreach ($o->tickets as $t) {
                if (in_array($t->status, ['cancelled', 'refunded'], true)) continue;
                $meta = is_array($t->meta ?? null) ? $t->meta : [];
                $isFromPackage = !empty($meta['from_package']);
                $isUmbrella = !empty($meta['is_package_umbrella']);
                $tp = (float) ($t->price ?? 0);
                // Fizic scanabil = tot ce NU e umbrella parent
                if (!$isUmbrella) {
                    $ticketsPhysical++;
                    $cat = $t->ticketType->service_category ?? 'access';
                    $ticketsByCategory[$cat] = ($ticketsByCategory[$cat] ?? 0) + 1;
                }
                // Tranzactie cu valoare = umbrella + individuale cu pret > 0 (EXCL componente / bonus / invitatii)
                if (!$isFromPackage && $tp > 0) {
                    $ticketsTransactions++;
                }
            }
        }
        $cashGross = round($cashGross, 2);
        $cardGross = round($cardGross, 2);

        // === Sesiuni casa POS in interval (opened_at SAU closed_at cad in range) ===
        $sessions = \App\Models\LeisureCashierSession::query()
            ->where('marketplace_organizer_id', $eventOrganizerId)
            ->where('event_id', $eventModel->id)
            ->where(function ($q) use ($fromUtc, $toUtc) {
                $q->whereBetween('opened_at', [$fromUtc, $toUtc])
                  ->orWhereBetween('closed_at', [$fromUtc, $toUtc]);
            })
            ->orderBy('opened_at')
            ->get(['id', 'opened_at', 'closed_at', 'opened_label', 'closing_snapshot']);
        $sessionsOut = $sessions->map(function ($s) {
            $t = is_array($s->closing_snapshot ?? null) ? ($s->closing_snapshot['totals'] ?? []) : [];
            $bp = is_array($s->closing_snapshot ?? null) ? ($s->closing_snapshot['by_payment'] ?? []) : [];
            $cash = 0.0; $card = 0.0;
            foreach ($bp as $row) {
                if (($row['method'] ?? '') === 'cash') $cash += (float) ($row['revenue'] ?? 0);
                if (($row['method'] ?? '') === 'card') $card += (float) ($row['revenue'] ?? 0);
            }
            return [
                'id' => $s->id,
                'operator' => $s->opened_label ?: 'InfoPoint',
                'opened_at' => $s->opened_at?->toIso8601String(),
                'closed_at' => $s->closed_at?->toIso8601String(),
                'is_open' => $s->closed_at === null,
                'cash' => round($cash, 2),
                'card' => round($card, 2),
                'orders' => (int) ($t['orders'] ?? 0),
                'tickets_sold' => (int) ($t['tickets_sold'] ?? $t['tickets'] ?? 0),
                'tickets_visitors' => (int) ($t['tickets_visitors'] ?? 0),
                'revenue' => round((float) ($t['revenue'] ?? 0), 2),
            ];
        })->values();

        return $this->success([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'currency' => $marketplace->currency ?? 'RON',
            'totals' => [
                'revenue_total'  => $revTotal,
                'revenue_online' => $revOnline,
                'revenue_pos'    => $revPos,
                'commission_total'  => $commTotal,
                'commission_online' => $commOnline,
                'commission_pos'    => $commPos,
                'net_total'  => round($revTotal - $commTotal, 2),
                'net_online' => round($revOnline - $commOnline, 2),
                'net_pos'    => round($revPos - $commPos, 2),
                'orders' => $ordersTotal,
                'avg_order' => $ordersTotal > 0 ? round($revTotal / $ordersTotal, 2) : 0.0,
                'tickets_transactions' => $ticketsTransactions,
                'tickets_physical' => $ticketsPhysical,
                'tickets_by_category' => $ticketsByCategory,
            ],
            'pos' => [
                'cash_gross' => $cashGross,
                'card_gross' => $cardGross,
            ],
            'sessions' => $sessionsOut,
        ]);
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/sales/range-csv?from=YYYY-MM-DD&to=YYYY-MM-DD
     *
     * Streamuieste CSV cu vanzarile pe un interval (from-to). Cate o LINIE per
     * bilet cu absolut toate campurile cerute: canal, metoda plata, tip, cod,
     * pret, comision, net, id comanda, email, status comanda, status bilet, data
     * achizitie, data validare, operator POS.
     *
     * Folosit din butonul "Export CSV" din leisure-sales.php (per range custom).
     *
     * Commission per ticket: mediu per (ticket_type, canal). Provine din
     * SalesBreakdownService care aplica logica de leisure (issuing_company
     * SC1/SC2) + commission_mode per tip. Ruleaza 2 build-uri (online, POS)
     * pentru ca aceleasi tip poate avea rate diferite in cele 2 canale.
     */
    public function salesRangeCsv(Request $request, int $event): mixed
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $tz = 'Europe/Bucharest';
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'), $tz)->endOfDay()
            : Carbon::now($tz)->endOfDay();
        if ($to->lt($from)) { [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()]; }
        $fromUtc = $from->copy()->utc();
        $toUtc = $to->copy()->utc();

        $eventOrganizerId = $eventModel->marketplace_organizer_id ?? $organizer->id;

        /** @var \App\Services\Marketplace\SalesBreakdownService $svc */
        $svc = app(\App\Services\Marketplace\SalesBreakdownService::class);
        // 2 build-uri separate: rate/mode pot diferi intre online si POS.
        $onlineBd = $svc->build($eventModel, $from, $to, excludePos: true, dateColumn: 'paid_at');
        $posBd    = $svc->build($eventModel, $from, $to, onlyPos: true,   dateColumn: 'paid_at');

        // Salvez per_type ca [gross, commission] pt fiecare canal. Atribuirea per
        // bilet se face PROPORTIONAL cu pretul (nu media commission_per_ticket din
        // service — aceea distribuie porTia si peste componentele pachet / bonusuri
        // ghid / invitatii cu pret 0, gresit).
        $commOnlineByType = [];
        foreach ($onlineBd['per_type'] ?? [] as $row) {
            $commOnlineByType[(int) $row['ticket_type_id']] = [
                'gross' => (float) ($row['gross'] ?? 0),
                'commission' => (float) ($row['commission_amount'] ?? 0),
            ];
        }
        $commPosByType = [];
        foreach ($posBd['per_type'] ?? [] as $row) {
            $commPosByType[(int) $row['ticket_type_id']] = [
                'gross' => (float) ($row['gross'] ?? 0),
                'commission' => (float) ($row['commission_amount'] ?? 0),
            ];
        }

        // Orders in interval (toate sursele + toate statusurile relevante ca CSV
        // sa reflecte si comenzi anulate/refundate). Pentru raportare filtram
        // biletele cancelled/refunded din output? User cere "TOATE datele" - deci
        // lasam si biletele anulate cu status marcat clar.
        $orders = Order::query()
            ->where('event_id', $eventModel->id)
            ->whereIn('status', ['paid', 'completed', 'refunded', 'cancelled', 'pending'])
            ->whereBetween('paid_at', [$fromUtc, $toUtc])
            ->with(['tickets:id,order_id,ticket_type_id,price,status,meta,code,barcode,attendee_name,attendee_email,checked_in_at,created_at', 'tickets.ticketType:id,name,service_category,issuing_company'])
            ->orderBy('paid_at')
            ->get(['id', 'order_number', 'customer_name', 'customer_email', 'status', 'total', 'source', 'paid_at', 'meta']);

        // Preload team members (operatori POS)
        $tmIds = [];
        foreach ($orders as $o) {
            $tmId = $o->meta['cashier_team_member_id'] ?? null;
            if ($tmId) $tmIds[(int) $tmId] = true;
        }
        $tmNames = [];
        if (!empty($tmIds)) {
            \App\Models\MarketplaceOrganizerTeamMember::query()
                ->whereIn('id', array_keys($tmIds))
                ->get(['id', 'name'])
                ->each(function ($tm) use (&$tmNames) { $tmNames[$tm->id] = $tm->name; });
        }

        $filename = 'vanzari-' . $from->format('Y-m-d') . '_' . $to->format('Y-m-d') . '-event-' . $eventModel->id . '.csv';
        $columns = [
            'Canal', 'Metoda plata', 'Tip bilet', 'Cod bilet',
            'Valoare bilet (RON)', 'Comision AmBilet (RON)', 'Valoare neta (RON)',
            'ID comanda', 'Nr. comanda', 'Email comanda', 'Status comanda', 'Status bilet',
            'Data si ora achizitie', 'Data si ora validare', 'Operator POS',
        ];

        return response()->streamDownload(function () use ($orders, $tmNames, $columns, $commOnlineByType, $commPosByType) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM pentru Excel
            fputcsv($out, $columns, ';', escape: '\\');

            foreach ($orders as $o) {
                $paidAtRo = $o->paid_at?->copy()->setTimezone('Europe/Bucharest');
                $dtBuy = $paidAtRo ? $paidAtRo->format('d.m.Y H:i:s') : '';
                $isPos = $o->source === 'pos';
                $pmRaw = $o->meta['payment_method'] ?? null;
                if ($isPos && $pmRaw === 'cash') $pmLabel = 'Cash';
                elseif ($isPos && $pmRaw === 'card') $pmLabel = 'Card';
                elseif ($isPos && $pmRaw === 'invoice') $pmLabel = 'Link email (POS)';
                else $pmLabel = 'Online';
                $canal = $isPos ? 'POS' : 'Online';
                $tmId = $o->meta['cashier_team_member_id'] ?? null;
                $operatorPos = $isPos
                    ? ($tmId ? ($tmNames[(int) $tmId] ?? ('Angajat #' . $tmId)) : 'InfoPoint')
                    : '';

                foreach ($o->tickets as $t) {
                    $tt = $t->ticketType;
                    $ttName = $tt->name ?? 'Bilet';
                    if (is_array($ttName)) $ttName = $ttName['ro'] ?? reset($ttName);
                    if (is_array($t->meta ?? null) && !empty($t->meta['label_override'])) {
                        $ttName = $t->meta['label_override'];
                    }
                    $ttId = (int) ($t->ticket_type_id ?? 0);
                    $price = (float) ($t->price ?? 0);
                    // Comision per bilet: PROPORTIONAL cu pretul in cadrul tipului.
                    // Bilete cu pret 0 (componenta pachet from_package, bonus ghid,
                    // invitatii) primesc 0 - umbrella pachet plateste comisionul intreg.
                    // Bilete cancelled/refunded sunt EXCLUSE din SalesBreakdownService,
                    // deci ele nu vor gasi tipul in per_type -> primesc 0 automat.
                    $tStatusExcluded = in_array($t->status, ['cancelled', 'refunded'], true);
                    if ($price <= 0 || $tStatusExcluded) {
                        $comm = 0.0;
                    } else {
                        $typeStats = $isPos ? ($commPosByType[$ttId] ?? null) : ($commOnlineByType[$ttId] ?? null);
                        $typeGross = (float) ($typeStats['gross'] ?? 0);
                        $typeComm  = (float) ($typeStats['commission'] ?? 0);
                        // Ratio comision/gross al tipului × pretul biletului. Suma pe
                        // toate biletele cu pret > 0 dintr-un tip = commission_amount
                        // (pt ca gross include exact aceleasi bilete).
                        $comm = $typeGross > 0 ? round($price * $typeComm / $typeGross, 2) : 0.0;
                    }
                    $net = round($price - $comm, 2);
                    $checkedRo = $t->checked_in_at?->copy()->setTimezone('Europe/Bucharest');
                    $dtValid = $checkedRo ? $checkedRo->format('d.m.Y H:i:s') : '';

                    fputcsv($out, [
                        $canal, $pmLabel, $ttName, $t->code ?: $t->barcode,
                        number_format($price, 2, '.', ''),
                        number_format($comm, 2, '.', ''),
                        number_format($net, 2, '.', ''),
                        $o->id, $o->order_number, $t->attendee_email ?: $o->customer_email,
                        $o->status, $t->status,
                        $dtBuy, $dtValid, $operatorPos,
                    ], ';', escape: '\\');
                }
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/payouts
     *
     * Lista deconturi (MarketplacePayout) pentru event-ul cerut + document PDF
     * decont daca a fost generat + breakdown detaliat (ticket_breakdown JSON).
     */
    public function payouts(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        // Scoping: organizatorul evenimentului (nu utilizatorul autentificat —
        // pentru cazul in care e un team member logat)
        $eventOrganizerId = $eventModel->marketplace_organizer_id ?? $organizer->id;

        // Resolve issuing-company names once per event so the API can send
        // display-ready names ("ASOCIATIA PRO SZENT ANNA" / "Csomadcom SRL")
        // instead of raw 'primary' / 'secondary' slugs. Fetch fresh — the
        // parent MarketplaceOrganizer isn't loaded on the payout query below.
        $eventOrg = \App\Models\MarketplaceOrganizer::find($eventOrganizerId);
        $societyNames = [
            'primary' => (string) ($eventOrg?->company_name ?? ''),
            'secondary' => (string) ($eventOrg?->secondary_company_name ?? ''),
        ];

        $payouts = \App\Models\MarketplacePayout::query()
            ->where('marketplace_organizer_id', $eventOrganizerId)
            ->where('event_id', $eventModel->id)
            ->with([
                'decontDocument:id,marketplace_payout_id,file_path,file_name',
                // Load ALL invoices tied to this payout (organizer + POS +
                // general_client). The HasOne relations on the model each
                // filter by meta->is_pos_commission — we want the unfiltered
                // set so any Oblio-sent invoice surfaces on the accordion.
                'invoicesForPayout',
            ])
            ->orderByDesc('created_at')
            ->get([
                'id',
                'reference',
                'decont_series',
                'amount',
                'currency',
                'period_start',
                'period_end',
                'gross_amount',
                'commission_amount',
                'discount_amount',
                'refund_amount',
                'fees_amount',
                'status',
                'source',
                'issuing_company',
                'ticket_breakdown',
                'created_at',
                'completed_at',
            ]);

        return $this->success([
            'event' => [
                'id' => $eventModel->id,
                'title' => $this->localizedTitle($eventModel),
            ],
            'currency' => $payouts->first()?->currency ?? 'RON',
            'payouts' => $payouts->map(function ($p) use ($societyNames) {
                $doc = $p->decontDocument;
                $docUrl = null;
                if ($doc && $doc->file_path) {
                    $docUrl = url('storage/' . $doc->file_path);
                }
                // Breakdown per tip bilet — reduce ticket_breakdown JSON la doar
                // campurile de care are nevoie UI-ul (nume, qty, unit_price,
                // gross, comision, discount, net).
                $breakdownRows = [];
                foreach ((array) $p->ticket_breakdown as $row) {
                    if (!is_array($row)) continue;
                    $qty = (int) ($row['quantity'] ?? $row['qty'] ?? 0);
                    if ($qty <= 0) continue;
                    $unit = (float) ($row['unit_price'] ?? $row['price'] ?? 0);
                    $gross = (float) ($row['gross'] ?? ($qty * $unit));
                    $comm = (float) ($row['commission_amount'] ?? ($qty * (float) ($row['commission_per_ticket'] ?? 0)));
                    $disc = (float) ($row['discount'] ?? 0);
                    $net = (float) ($row['net'] ?? ($gross - $comm - $disc));
                    $breakdownRows[] = [
                        'ticket_type_id' => (int) ($row['ticket_type_id'] ?? 0),
                        'name' => $row['ticket_type_name'] ?? ('Tip #' . ($row['ticket_type_id'] ?? '?')),
                        'qty' => $qty,
                        'unit_price' => round($unit, 2),
                        'gross' => round($gross, 2),
                        'commission' => round($comm, 2),
                        'discount' => round($disc, 2),
                        'net' => round($net, 2),
                    ];
                }

                // Society resolution: primary/secondary → display name from
                // organizer, "-" for legacy payouts without issuing_company.
                $issuer = $p->issuing_company;
                $societyName = '-';
                $societyKey = null;
                if ($issuer === 'primary' || $issuer === 'secondary') {
                    $societyKey = $issuer;
                    $resolved = $societyNames[$issuer] ?? '';
                    $societyName = $resolved !== '' ? $resolved : ucfirst($issuer);
                }

                // Invoices linked to this payout, filtered to only those that
                // were actually sent to Oblio (either as proformă or fiscală).
                // Local drafts without an accounting external_ref are hidden —
                // the organizer only sees invoices that count as issued.
                $invoicesData = [];
                foreach (($p->invoicesForPayout ?? collect()) as $inv) {
                    $meta = is_array($inv->meta) ? $inv->meta : [];
                    $acc = $meta['accounting'] ?? [];
                    $proforma = $meta['accounting_proforma'] ?? [];
                    $accExtRef = $acc['external_ref'] ?? null;
                    $proExtRef = $proforma['external_ref'] ?? null;
                    if (!$accExtRef && !$proExtRef) {
                        continue; // Not sent to Oblio yet — hide from organizer view.
                    }

                    // Type label — organizer/general_client comes from
                    // meta.recipient_type; POS commission has meta.is_pos_commission=true.
                    $isPos = (bool) ($meta['is_pos_commission'] ?? false);
                    $recipientType = $meta['recipient_type'] ?? 'organizer';
                    if ($isPos) {
                        $typeKey = 'pos_commission';
                        $typeLabel = 'POS';
                    } elseif ($recipientType === 'general_client') {
                        $typeKey = 'general_client';
                        $typeLabel = 'Client general';
                    } else {
                        $typeKey = 'organizer';
                        $typeLabel = 'Organizator';
                    }

                    // Prefer fiscal PDF; fall back to proforma when only that was sent.
                    $accountingPdfUrl = $acc['pdf_url'] ?? $proforma['pdf_url'] ?? null;
                    $accountingStage = $accExtRef ? 'fiscala' : 'proforma';
                    // Oblio-issued number (e.g. AMBFF2342) — shown as the
                    // primary identifier on the accordion; the internal
                    // Tixello series (F-9862066) is shown as a small
                    // secondary label underneath.
                    $accountingNumber = $acc['invoice_number'] ?? $proforma['invoice_number'] ?? null;

                    // Article items straight from meta.items (both `name` and
                    // legacy `description`-only rows accepted).
                    $items = [];
                    foreach (($meta['items'] ?? []) as $it) {
                        if (!is_array($it)) continue;
                        $items[] = [
                            'name' => (string) ($it['name'] ?? $it['description'] ?? ''),
                            'description' => (string) ($it['description'] ?? ''),
                            'quantity' => (float) ($it['quantity'] ?? 1),
                            'unit_price' => (float) ($it['unit_price'] ?? $it['price'] ?? 0),
                            'amount' => (float) ($it['amount'] ?? 0),
                        ];
                    }

                    $invoicesData[] = [
                        'id' => $inv->id,
                        'number' => $inv->number, // Tixello internal (e.g. F-9862066)
                        'accounting_number' => $accountingNumber, // Oblio-issued (e.g. AMBFF2342)
                        'type' => $typeKey,
                        'type_label' => $typeLabel,
                        'amount' => (float) $inv->amount,
                        'subtotal' => (float) $inv->subtotal,
                        'vat_amount' => (float) $inv->vat_amount,
                        'currency' => $inv->currency ?? 'RON',
                        'status' => $inv->status,
                        'issue_date' => optional($inv->issue_date)->toDateString(),
                        'accounting_stage' => $accountingStage, // 'fiscala' | 'proforma'
                        'accounting_pdf_url' => $accountingPdfUrl,
                        'items' => $items,
                    ];
                }

                return [
                    'id' => $p->id,
                    'reference' => $p->reference,
                    'decont_series' => $p->decont_series,
                    'amount' => (float) $p->amount,
                    'gross_amount' => (float) $p->gross_amount,
                    'commission_amount' => (float) $p->commission_amount,
                    'discount_amount' => (float) $p->discount_amount,
                    'refund_amount' => (float) $p->refund_amount,
                    'fees_amount' => (float) $p->fees_amount,
                    'currency' => $p->currency ?? 'RON',
                    'status' => $p->status,
                    'source' => $p->source,
                    'issuing_company' => $societyKey,
                    'society_name' => $societyName,
                    'period_start' => optional($p->period_start)->toDateString(),
                    'period_end' => optional($p->period_end)->toDateString(),
                    'created_at' => optional($p->created_at)->toIso8601String(),
                    'completed_at' => optional($p->completed_at)->toIso8601String(),
                    'pdf_url' => $docUrl,
                    'breakdown' => $breakdownRows,
                    'invoices' => $invoicesData,
                ];
            })->values(),
        ]);
    }

    protected function localizedTitle(Event $event): string
    {
        $title = $event->title;
        if (is_array($title)) {
            return $title['ro'] ?? $title['en'] ?? (reset($title) ?: '');
        }
        return (string) ($title ?? '');
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/settlement?from=&to=
     *
     * Settlement (compensare) between AmBilet and the leisure venue for a period:
     *  - Online sales are collected by AmBilet → AmBilet owes the venue the online
     *    NET (gross_online - commission_online).
     *  - POS sales (cash/card) are collected physically at the venue → the venue
     *    owes AmBilet the POS commission.
     *  - The balance nets the two off; the result says who pays whom and how much.
     * Online and POS are kept strictly separate (online never mixes with the
     * physical cash drawer). Figures use SalesBreakdownService (authoritative
     * commission), anchored on paid_at.
     */
    public function settlement(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $tz = 'Europe/Bucharest';
        // Project start = 2026-07-15; default window = the first 2-week period.
        $projectStart = Carbon::parse('2026-07-15', $tz)->startOfDay();
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'), $tz)->startOfDay()
            : $projectStart->copy();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'), $tz)->endOfDay()
            : $from->copy()->addDays(13)->endOfDay();

        $fromUtc = $from->copy()->utc();
        $toUtc = $to->copy()->utc();

        /** @var \App\Services\Marketplace\SalesBreakdownService $svc */
        $svc = app(\App\Services\Marketplace\SalesBreakdownService::class);
        $online = $svc->build($eventModel, $from, $to, excludePos: true, dateColumn: 'paid_at');
        $pos    = $svc->build($eventModel, $from, $to, onlyPos: true,   dateColumn: 'paid_at');

        $grossOnline = round((float) $online['total_revenue'], 2);
        $commOnline  = round((float) $online['total_commission'], 2);
        $netOnline   = round($grossOnline - $commOnline, 2);
        $grossPos    = round((float) $pos['total_revenue'], 2);
        $commPos     = round((float) $pos['total_commission'], 2);

        // Physical money in the POS drawer, split by method (what the customer
        // actually paid — order.total). Cash = to hand over; card = electronic.
        $posOrders = Order::where('event_id', $eventModel->id)
            ->whereIn('status', ['paid', 'completed'])
            ->where('source', 'pos')
            ->whereBetween('paid_at', [$fromUtc, $toUtc]);
        $cashTotal = round((float) (clone $posOrders)->whereRaw("meta->>'payment_method' = 'cash'")->sum('total'), 2);
        $cardTotal = round((float) (clone $posOrders)->whereRaw("meta->>'payment_method' = 'card'")->sum('total'), 2);

        // Settlement balance (compensare):
        //  AmBilet owes venue    = online net (collected online, minus its cut)
        //  Venue owes AmBilet    = POS commission (venue kept the cash/card)
        $ambiletOwes = $netOnline;
        $venueOwes   = $commPos;
        $net = round($ambiletOwes - $venueOwes, 2);

        // Breakdown per societate emitenta (SC1 primary / SC2 secondary).
        // Pentru fiecare societate: vanzari online + comisioane online, vanzari POS +
        // comisioane POS, si valoarea de compensare (vanzari_online - comisioane totale).
        $eventOrganizer = $eventModel->marketplace_organizer_id
            ? MarketplaceOrganizer::find($eventModel->marketplace_organizer_id)
            : $organizer;
        $issuerBreakdown = $this->buildSettlementIssuerBreakdown($online, $pos, $eventOrganizer);

        return $this->success([
            'period'    => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'currency'  => $marketplace->currency ?? 'RON',
            'online'    => [
                'gross'      => $grossOnline,
                'commission' => $commOnline,
                'net'        => $netOnline,
            ],
            'pos'       => [
                'gross'      => $grossPos,
                'commission' => $commPos,
                'cash'       => $cashTotal,
                'card'       => $cardTotal,
            ],
            'balance'   => [
                'ambilet_owes_venue' => $ambiletOwes,
                'venue_owes_ambilet' => $venueOwes,
                'net'                => $net,
                'direction'          => $net > 0 ? 'ambilet_to_venue' : ($net < 0 ? 'venue_to_ambilet' : 'settled'),
                'amount'             => abs($net),
            ],
            'by_issuer' => $issuerBreakdown,
        ]);
    }

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/settlement/cumulative
     *
     * Cifre cumulate de la 2026-07-16 (start Sf Ana) pana azi:
     *   - total zile
     *   - vanzari online + POS
     *   - comisioane online + POS
     *   - breakdown pe societate cu compensarea per societate
     * Folosit in cardurile de sus din /organizator/deconturi.
     */
    public function settlementCumulative(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $tz = 'Europe/Bucharest';
        $projectStart = Carbon::parse('2026-07-16', $tz)->startOfDay();
        $now = Carbon::now($tz);
        $daysCount = max(1, (int) $projectStart->diffInDays($now->copy()->startOfDay()) + 1);

        // Cache 10 min (recalculare completa e scumpa - agregare pe 30+ zile)
        $cacheKey = "leisure_settlement_cumulative_v1_{$eventModel->id}_" . $now->format('Y-m-d-H');
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached !== null) return $this->success($cached);

        @ini_set('memory_limit', '1024M');
        @set_time_limit(120);

        /** @var \App\Services\Marketplace\SalesBreakdownService $svc */
        $svc = app(\App\Services\Marketplace\SalesBreakdownService::class);
        $online = $svc->build($eventModel, $projectStart, $now, excludePos: true, dateColumn: 'paid_at');
        $pos    = $svc->build($eventModel, $projectStart, $now, onlyPos: true,   dateColumn: 'paid_at');

        $eventOrganizer = $eventModel->marketplace_organizer_id
            ? MarketplaceOrganizer::find($eventModel->marketplace_organizer_id)
            : $organizer;
        $issuerBreakdown = $this->buildSettlementIssuerBreakdown($online, $pos, $eventOrganizer);

        $payload = [
            'from' => $projectStart->toDateString(),
            'to' => $now->toDateString(),
            'days_count' => $daysCount,
            'currency' => $marketplace->currency ?? 'RON',
            'online' => [
                'gross' => round((float) $online['total_revenue'], 2),
                'commission' => round((float) $online['total_commission'], 2),
            ],
            'pos' => [
                'gross' => round((float) $pos['total_revenue'], 2),
                'commission' => round((float) $pos['total_commission'], 2),
            ],
            'by_issuer' => $issuerBreakdown,
        ];
        \Illuminate\Support\Facades\Cache::put($cacheKey, $payload, 600);
        return $this->success($payload);
    }

    /**
     * Helper: breakdown per societate emitenta din 2 breakdown-uri (online + POS)
     * ale SalesBreakdownService. Pentru fiecare societate calculeaza vanzari online +
     * comisioane online + vanzari POS + comisioane POS + valoare compensare.
     *
     * Compensare = vanzari_online - (comisioane_online + comisioane_pos)
     *   → Locatia primeste net-ul online din care se scad TOATE comisioanele.
     *
     * Pentru pachete Mix (issuing_company='mix'), distribuim revenue + commission
     * proportional pe issuers componenti conform meta.package_outputs. Consistent
     * cu logica din raport().
     */
    private function buildSettlementIssuerBreakdown(array $onlineBd, array $posBd, ?MarketplaceOrganizer $eventOrganizer): array
    {
        $primaryName = $eventOrganizer?->company_name ?: 'SC1';
        $secondaryName = $eventOrganizer?->has_secondary_issuer ? ($eventOrganizer?->secondary_company_name ?: 'SC2') : null;

        // Preload componentIssuerMap pentru Mix packages
        $componentIssuerMap = [];
        $mixTypes = TicketType::query()
            ->where('event_id', $eventOrganizer?->id ? null : null) // will re-scope below
            ->where('service_category', 'package')
            ->where('issuing_company', 'mix')
            ->get(['id', 'meta']);
        // Correct scope: doar tipurile evenimentului. Reload via SalesBreakdownService per_type ids.
        $allTypeIds = collect(($onlineBd['per_type'] ?? []))->pluck('ticket_type_id')
            ->merge(collect(($posBd['per_type'] ?? []))->pluck('ticket_type_id'))
            ->unique()->values();
        if ($allTypeIds->isNotEmpty()) {
            $mixTypes = TicketType::query()
                ->whereIn('id', $allTypeIds)
                ->where('service_category', 'package')
                ->where('issuing_company', 'mix')
                ->get(['id', 'meta']);
            foreach ($mixTypes as $tt) {
                $outputs = is_array($tt->meta ?? null) ? ($tt->meta['package_outputs'] ?? []) : [];
                foreach ($outputs as $out) {
                    $cid = (int) ($out['ticket_type_id'] ?? 0);
                    if (!$cid) continue;
                    if (!isset($componentIssuerMap[$cid])) {
                        $ct = TicketType::find($cid);
                        if ($ct) $componentIssuerMap[$cid] = ($ct->issuing_company === 'secondary') ? 'secondary' : 'primary';
                    }
                }
            }
        }

        // Distribute per_type by issuer for both online + POS
        $out = [
            'primary' => [
                'issuer' => 'primary', 'name' => $primaryName,
                'online_gross' => 0.0, 'online_commission' => 0.0,
                'pos_gross' => 0.0, 'pos_commission' => 0.0,
            ],
            'secondary' => [
                'issuer' => 'secondary', 'name' => $secondaryName,
                'online_gross' => 0.0, 'online_commission' => 0.0,
                'pos_gross' => 0.0, 'pos_commission' => 0.0,
            ],
        ];

        $accumulate = function (array $perType, string $channel) use (&$out, $componentIssuerMap): void {
            foreach ($perType as $row) {
                $gross = (float) ($row['gross'] ?? 0);
                $comm = (float) ($row['commission_amount'] ?? 0);
                $tt = $row['tt'] ?? null; // model
                $issuer = ($tt?->issuing_company) ?: 'primary';
                $splits = [];
                if ($issuer === 'mix' && $tt && ($tt->service_category ?? '') === 'package') {
                    $outputs = is_array($tt->meta ?? null) ? ($tt->meta['package_outputs'] ?? []) : [];
                    $allocSum = 0.0;
                    foreach ($outputs as $o) if (isset($o['price']) && is_numeric($o['price'])) $allocSum += (float) $o['price'];
                    if ($allocSum > 0) foreach ($outputs as $o) {
                        $cid = (int) ($o['ticket_type_id'] ?? 0);
                        $cIssuer = $componentIssuerMap[$cid] ?? 'primary';
                        $portion = isset($o['price']) ? (float) $o['price'] : 0.0;
                        if ($portion <= 0) continue;
                        $splits[] = ['issuer' => $cIssuer, 'amount' => $portion];
                    }
                }
                if (empty($splits)) $splits[] = ['issuer' => $issuer === 'secondary' ? 'secondary' : 'primary', 'amount' => 1];
                $splitTotal = array_sum(array_column($splits, 'amount'));
                foreach ($splits as $s) {
                    $ratio = $splitTotal > 0 ? $s['amount'] / $splitTotal : 0;
                    $portionRev = $gross * $ratio;
                    $portionCom = $comm * $ratio;
                    $out[$s['issuer']][$channel . '_gross'] += $portionRev;
                    $out[$s['issuer']][$channel . '_commission'] += $portionCom;
                }
            }
        };
        $accumulate($onlineBd['per_type'] ?? [], 'online');
        $accumulate($posBd['per_type'] ?? [], 'pos');

        // Compute compensation per issuer + round
        foreach (['primary', 'secondary'] as $k) {
            $out[$k]['online_gross'] = round($out[$k]['online_gross'], 2);
            $out[$k]['online_commission'] = round($out[$k]['online_commission'], 2);
            $out[$k]['pos_gross'] = round($out[$k]['pos_gross'], 2);
            $out[$k]['pos_commission'] = round($out[$k]['pos_commission'], 2);
            // Compensare = vanzari_online - (comisioane_online + comisioane_pos)
            $comp = $out[$k]['online_gross'] - $out[$k]['online_commission'] - $out[$k]['pos_commission'];
            $out[$k]['compensation'] = round($comp, 2);
        }

        return [
            'primary' => $out['primary'],
            'secondary' => $secondaryName ? $out['secondary'] : null,
        ];
    }

    // ========================================================================
    // SOCIETATI — GET/PUT pentru editarea celor 2 societati emitente
    // ========================================================================

    /**
     * GET /marketplace-client/organizer/events/{event}/leisure/issuers
     * Returneaza datele complete editabile ale celor 2 societati (primary +
     * secondary, daca activata) ale organizatorului evenimentului.
     */
    public function issuersShow(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $eventOrganizer = $eventModel->marketplace_organizer_id
            ? MarketplaceOrganizer::find($eventModel->marketplace_organizer_id)
            : $organizer;
        if (!$eventOrganizer) return $this->error('Organizer not found', 404);

        return $this->success([
            'organizer_id' => $eventOrganizer->id,
            'has_secondary_issuer' => (bool) $eventOrganizer->has_secondary_issuer,
            'primary' => [
                'name' => $eventOrganizer->company_name,
                'tax_id' => $eventOrganizer->company_tax_id,
                'registration' => $eventOrganizer->company_registration,
                'address' => $eventOrganizer->company_address,
                'city' => $eventOrganizer->company_city,
                'county' => $eventOrganizer->company_county,
                'zip' => $eventOrganizer->company_zip,
                'bank_name' => $eventOrganizer->bank_name,
                'iban' => $eventOrganizer->iban,
                'invoice_series' => $eventOrganizer->primary_invoice_series,
                'last_invoice_number' => (int) ($eventOrganizer->primary_last_invoice_number ?? 0),
                'next_invoice_number' => (int) ($eventOrganizer->primary_last_invoice_number ?? 0) + 1,
                'vat_payer' => $eventOrganizer->primary_vat_payer !== null
                    ? (bool) $eventOrganizer->primary_vat_payer
                    : (bool) ($eventOrganizer->vat_payer ?? false),
                'vat_rate' => $eventOrganizer->primary_vat_rate !== null
                    ? (float) $eventOrganizer->primary_vat_rate
                    : (isset($eventOrganizer->tax_settings['vat_rate']) ? (float) $eventOrganizer->tax_settings['vat_rate'] : null),
            ],
            'secondary' => [
                'name' => $eventOrganizer->secondary_company_name,
                'tax_id' => $eventOrganizer->secondary_company_tax_id,
                'registration' => $eventOrganizer->secondary_company_registration,
                'address' => $eventOrganizer->secondary_company_address,
                'city' => $eventOrganizer->secondary_company_city,
                'county' => $eventOrganizer->secondary_company_county,
                'zip' => $eventOrganizer->secondary_company_zip,
                'bank_name' => $eventOrganizer->secondary_bank_name,
                'iban' => $eventOrganizer->secondary_iban,
                'invoice_series' => $eventOrganizer->secondary_invoice_series,
                'last_invoice_number' => (int) ($eventOrganizer->secondary_last_invoice_number ?? 0),
                'next_invoice_number' => (int) ($eventOrganizer->secondary_last_invoice_number ?? 0) + 1,
                'vat_payer' => (bool) ($eventOrganizer->secondary_vat_payer ?? false),
                'vat_rate' => $eventOrganizer->secondary_vat_rate !== null ? (float) $eventOrganizer->secondary_vat_rate : null,
            ],
        ]);
    }

    /**
     * PUT /marketplace-client/organizer/events/{event}/leisure/issuers
     *
     * Update partial pentru societatea ceruta. Body: { company: 'primary'|'secondary', fields: {...} }
     * Fields acceptate (toate optionale, doar cele trimise se actualizeaza):
     *  name, tax_id, registration, address, city, county, zip, bank_name, iban,
     *  invoice_series, next_invoice_number, vat_payer, vat_rate,
     *  has_secondary_issuer (DOAR cand company=secondary)
     *
     * next_invoice_number: cand e setat, scrie last_invoice_number = next - 1 ca sa pornim
     * urmatoarea factura emisa de la `next` (atomic reservation in reserveNextInvoiceNumber).
     */
    public function issuersUpdate(Request $request, int $event): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $marketplace = $organizer->marketplaceClient;

        $eventModel = Event::query()
            ->where('id', $event)
            ->where('marketplace_client_id', $marketplace->id)
            ->first();
        if (!$eventModel) return $this->error('Event not found', 404);

        $eventOrganizer = $eventModel->marketplace_organizer_id
            ? MarketplaceOrganizer::find($eventModel->marketplace_organizer_id)
            : $organizer;
        if (!$eventOrganizer) return $this->error('Organizer not found', 404);

        $validated = $request->validate([
            'company' => 'required|in:primary,secondary',
            'fields' => 'required|array',
            'fields.name' => 'nullable|string|max:255',
            'fields.tax_id' => 'nullable|string|max:32',
            'fields.registration' => 'nullable|string|max:32',
            'fields.address' => 'nullable|string|max:1024',
            'fields.city' => 'nullable|string|max:100',
            'fields.county' => 'nullable|string|max:100',
            'fields.zip' => 'nullable|string|max:20',
            'fields.bank_name' => 'nullable|string|max:255',
            'fields.iban' => 'nullable|string|max:34',
            'fields.invoice_series' => 'nullable|string|max:16',
            'fields.next_invoice_number' => 'nullable|integer|min:1|max:9999999',
            'fields.vat_payer' => 'nullable|boolean',
            'fields.vat_rate' => 'nullable|numeric|min:0|max:100',
            'fields.has_secondary_issuer' => 'nullable|boolean',
        ]);

        $company = $validated['company'];
        $fields = $validated['fields'] ?? [];
        $prefix = $company === 'secondary' ? 'secondary_' : '';

        // Mapare camp UI -> coloana DB (per societate)
        $colMap = $company === 'secondary' ? [
            'name' => 'secondary_company_name',
            'tax_id' => 'secondary_company_tax_id',
            'registration' => 'secondary_company_registration',
            'address' => 'secondary_company_address',
            'city' => 'secondary_company_city',
            'county' => 'secondary_company_county',
            'zip' => 'secondary_company_zip',
            'bank_name' => 'secondary_bank_name',
            'iban' => 'secondary_iban',
            'invoice_series' => 'secondary_invoice_series',
            'vat_payer' => 'secondary_vat_payer',
            'vat_rate' => 'secondary_vat_rate',
        ] : [
            'name' => 'company_name',
            'tax_id' => 'company_tax_id',
            'registration' => 'company_registration',
            'address' => 'company_address',
            'city' => 'company_city',
            'county' => 'company_county',
            'zip' => 'company_zip',
            'bank_name' => 'bank_name',
            'iban' => 'iban',
            'invoice_series' => 'primary_invoice_series',
            'vat_payer' => 'primary_vat_payer',
            'vat_rate' => 'primary_vat_rate',
        ];

        foreach ($colMap as $uiField => $dbCol) {
            if (array_key_exists($uiField, $fields)) {
                $value = $fields[$uiField];
                // Seria facturii se scrie MEREU cu MAJUSCULE (input-ul form-ului are
                // class="uppercase" doar pentru DISPLAY; valoarea actuala poate fi
                // lowercase). Fara asta, "SZAMEC" tastat de operator ajungea "szamec"
                // pe factura tiparita.
                if ($uiField === 'invoice_series' && is_string($value) && $value !== '') {
                    $value = mb_strtoupper($value);
                }
                $eventOrganizer->{$dbCol} = $value;
            }
        }

        // next_invoice_number -> last_invoice_number = next - 1 (atomic transaction)
        if (array_key_exists('next_invoice_number', $fields) && $fields['next_invoice_number'] !== null) {
            $next = (int) $fields['next_invoice_number'];
            $lastCol = $company === 'secondary' ? 'secondary_last_invoice_number' : 'primary_last_invoice_number';
            $eventOrganizer->{$lastCol} = max(0, $next - 1);
        }

        // Mirror primary_vat_payer -> legacy vat_payer ca sa nu rupem TaxReportController / BillingController
        if ($company === 'primary' && array_key_exists('vat_payer', $fields)) {
            $eventOrganizer->vat_payer = (bool) $fields['vat_payer'];
        }

        // Toggle has_secondary_issuer DOAR cand vine din societatea secundara
        if ($company === 'secondary' && array_key_exists('has_secondary_issuer', $fields)) {
            $eventOrganizer->has_secondary_issuer = (bool) $fields['has_secondary_issuer'];
        }

        $eventOrganizer->save();

        return $this->success([
            'saved' => true,
            'company' => $company,
        ]);
    }
}
