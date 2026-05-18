<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceOrganizer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends BaseController
{
    /**
     * Get dashboard overview
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $fromDate = $request->input('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());

        // Events stats - use Event model directly
        $eventsBaseQuery = Event::where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id);

        $totalEvents = (clone $eventsBaseQuery)->count();
        $publishedEvents = (clone $eventsBaseQuery)->where('is_published', true)->count();
        $upcomingEventsQuery = (clone $eventsBaseQuery)
            ->where('is_published', true)
            ->where('event_date', '>=', now());
        $upcomingEvents = (clone $upcomingEventsQuery)->count();

        // Get list of upcoming events with details
        $eventsList = (clone $upcomingEventsQuery)
            ->orderBy('event_date')
            ->limit(10)
            ->withCount(['tickets as tickets_sold' => function ($q) use ($organizer) {
                $q->whereHas('order', fn ($oq) => $oq->whereIn('status', ['paid', 'completed'])
                    ->where('marketplace_organizer_id', $organizer->id));
            }])
            ->with(['marketplaceCity', 'venue'])
            ->get()
            ->map(function ($event) {
                // Get city from marketplaceCity (translatable) or venue
                $venueCity = $event->marketplaceCity?->getLocalizedName('ro') ?? $event->venue?->city ?? null;

                return [
                    'id' => $event->id,
                    'title' => $event->name,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'image' => $event->poster_url ?? $event->cover_image_url,
                    'start_date' => $event->event_date?->toDateString(),
                    'starts_at' => $event->event_date?->toIso8601String(),
                    'venue' => $event->venue_name,
                    'venue_city' => $venueCity,
                    'tickets_sold' => $event->tickets_sold ?? 0,
                    'tickets_total' => $event->ticketTypes()->where('quota_total', '<', 0)->exists() ? -1 : ($event->ticketTypes()->sum('quota_total') ?: 100),
                    'status' => $event->is_published ? 'published' : 'draft',
                ];
            });

        // Orders in period
        $orders = Order::where('marketplace_organizer_id', $organizer->id)
            ->whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59']);

        $completedOrders = (clone $orders)->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('source', '!=', 'test_order');

        $commissionRate = $organizer->getEffectiveCommissionRate();
        $grossRevenue = (float) (clone $completedOrders)->sum('total');
        $commissionAmount = round($grossRevenue * $commissionRate / 100, 2);
        $netRevenue = $grossRevenue - $commissionAmount;

        // Count only valid/used tickets (not cancelled tickets on valid orders)
        $ticketsSold = \App\Models\Ticket::whereHas('order', function ($q) use ($organizer, $fromDate, $toDate) {
            $q->where('marketplace_organizer_id', $organizer->id)
              ->whereIn('status', ['paid', 'confirmed', 'completed'])
              ->where('source', '!=', 'test_order')
              ->whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59']);
        })->whereNotIn('status', ['cancelled', 'refunded', 'void'])->count();

        // Order status breakdown
        $allOrdersInPeriod = (clone $orders)->where('source', '!=', 'test_order');
        $orderBreakdown = [
            'paid' => (clone $allOrdersInPeriod)->whereIn('status', ['paid', 'confirmed', 'completed'])->count(),
            'pending' => (clone $allOrdersInPeriod)->where('status', 'pending')->count(),
            'failed' => (clone $allOrdersInPeriod)->where('status', 'failed')->count(),
            'cancelled' => (clone $allOrdersInPeriod)->where('status', 'cancelled')->count(),
            'expired' => (clone $allOrdersInPeriod)->where('status', 'expired')->count(),
            'refunded' => (clone $allOrdersInPeriod)->whereIn('status', ['refunded', 'partially_refunded'])->count(),
        ];

        // Weekly sales (last 7 days)
        $weeklySales = Order::where('marketplace_organizer_id', $organizer->id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('source', '!=', 'test_order')
            ->where('created_at', '>=', now()->subDays(7))
            ->withCount('tickets')
            ->get()
            ->sum('tickets_count');

        return $this->success([
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
            // Structured data
            'events' => [
                'total' => $totalEvents,
                'published' => $publishedEvents,
                'upcoming' => $upcomingEvents,
                'pending_review' => (clone $eventsBaseQuery)->where('is_published', false)->where('is_cancelled', false)->count(),
            ],
            'sales' => [
                'total_orders' => (clone $orders)->count(),
                'completed_orders' => (clone $completedOrders)->count(),
                'tickets_sold' => $ticketsSold,
                'gross_revenue' => $grossRevenue,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'net_revenue' => $netRevenue,
                'order_breakdown' => $orderBreakdown,
            ],
            'account' => [
                'status' => $organizer->status,
                'is_verified' => $organizer->isVerified(),
                'has_payout_details' => !empty($organizer->payout_details),
            ],
            'balance' => [
                'available' => (float) $organizer->available_balance,
                'pending' => (float) $organizer->pending_balance,
                'total_paid_out' => (float) $organizer->total_paid_out,
                'can_request_payout' => $organizer->hasMinimumPayoutBalance()
                    && !$organizer->hasPendingPayout()
                    && !empty($organizer->payout_details),
            ],
            // Flattened fields for frontend compatibility
            'active_events' => $upcomingEvents,
            'tickets_sold' => $ticketsSold,
            'revenue_month' => $grossRevenue,
            'weekly_sales' => $weeklySales,
            'events_list' => $eventsList,
        ]);
    }

    /**
     * Get sales timeline
     */
    public function salesTimeline(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $fromDate = $request->input('from_date', now()->startOfMonth()->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());
        $groupBy = $request->input('group_by', 'day');

        $isPgsql = DB::getDriverName() === 'pgsql';
        $dateFormat = match ($groupBy) {
            'week' => $isPgsql ? 'IYYY-IW' : '%Y-%u',
            'month' => $isPgsql ? 'YYYY-MM' : '%Y-%m',
            default => $isPgsql ? 'YYYY-MM-DD' : '%Y-%m-%d',
        };
        $periodExpr = $isPgsql ? "TO_CHAR(created_at, '{$dateFormat}')" : "DATE_FORMAT(created_at, '{$dateFormat}')";

        $sales = Order::where('marketplace_organizer_id', $organizer->id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('source', '!=', 'test_order')
            ->whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59'])
            ->selectRaw("{$periodExpr} as period")
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('SUM(total) as revenue')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $this->success([
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
                'group_by' => $groupBy,
            ],
            'timeline' => $sales,
        ]);
    }

    /**
     * Get recent orders
     */
    public function recentOrders(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $limit = min((int) $request->input('limit', 10), 50);

        $orders = Order::where('marketplace_organizer_id', $organizer->id)
            ->with([
                'event:id,title',
                'marketplaceEvent:id,name',
                'marketplaceCustomer:id,first_name,last_name,email',
            ])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total' => (float) $order->total,
                    'event' => $order->event?->name ?? $order->marketplaceEvent?->name,
                    'customer' => $order->marketplaceCustomer
                        ? $order->marketplaceCustomer->full_name
                        : $order->customer_name,
                    'customer_email' => $order->marketplaceCustomer?->email ?? $order->customer_email,
                    'created_at' => $order->created_at->toIso8601String(),
                ];
            });

        return $this->success([
            'orders' => $orders,
        ]);
    }

    /**
     * Get orders list with filtering
     */
    public function orders(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $query = Order::where('marketplace_organizer_id', $organizer->id)
            ->with([
                'event:id,title',
                'marketplaceEvent:id,name',
                'marketplaceCustomer:id,first_name,last_name,email',
                'tickets.marketplaceTicketType:id,name',
                'tickets.ticketType:id,name',
            ]);

        // Filters — "completed" is a user-facing bucket covering paid/confirmed/
        // completed so app-channel orders (status="confirmed") also show up
        // under Finalizate. Other statuses use exact match.
        if ($request->has('status')) {
            $statusFilter = $request->status;
            if ($statusFilter === 'completed') {
                $query->whereIn('status', ['paid', 'confirmed', 'completed']);
            } else {
                $query->where('status', $statusFilter);
            }
        }

        if ($request->has('event_id')) {
            // Filter by either event_id or marketplace_event_id
            $eventId = $request->event_id;
            $query->where(function ($q) use ($eventId) {
                $q->where('event_id', $eventId)
                    ->orWhere('marketplace_event_id', $eventId);
            });
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        // Sorting — explicit allowlist; anything else falls back to
        // chronological desc. tickets_count isn't an orders column so it
        // can't be sorted server-side via a plain orderBy; left out of
        // the allowed set rather than adding a join the rest of the
        // listing doesn't need.
        $allowedSorts = ['order_number', 'customer_name', 'total', 'status', 'source', 'created_at'];
        $sortBy = $request->input('sort_by');
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderByDesc('created_at');
        }

        // Compute aggregate stats — only paid/confirmed/completed orders
        $statsQuery = (clone $query)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('source', '!=', 'test_order');

        // Count only valid tickets (not cancelled/refunded on valid orders)
        $statsOrderIds = (clone $statsQuery)->pluck('id');
        $validTickets = \App\Models\Ticket::whereIn('order_id', $statsOrderIds)
            ->whereNotIn('status', ['cancelled', 'refunded', 'void'])
            ->count();

        // Net revenue = sum of ticket base prices (without commission), with
        // per-ticket promo discount allocations subtracted via getEffectivePrice().
        // We iterate in PHP rather than SUM in SQL because Ticket::getEffectivePrice()
        // also handles the legacy proportional fallback (orders without per-ticket
        // meta.discount_amount).
        $netRevenue = (float) \App\Models\Ticket::whereIn('order_id', $statsOrderIds)
            ->whereIn('status', ['valid', 'used'])
            ->with(['order:id,discount_amount,subtotal'])
            ->get(['id', 'order_id', 'price', 'meta'])
            ->sum(fn ($t) => $t->getEffectivePrice());

        $stats = [
            'total_revenue' => $netRevenue,
            'gross_revenue' => (float) (clone $statsQuery)->sum('total'),
            'total_tickets' => $validTickets,
            'completed_orders' => (int) (clone $statsQuery)->count(),
        ];

        // Order breakdown for display
        $allOrdersQuery = (clone $query)->where('source', '!=', 'test_order');
        $stats['order_breakdown'] = [
            'paid' => (int) (clone $allOrdersQuery)->whereIn('status', ['paid', 'confirmed', 'completed'])->count(),
            'pending' => (int) (clone $allOrdersQuery)->where('status', 'pending')->count(),
            'failed' => (int) (clone $allOrdersQuery)->where('status', 'failed')->count(),
            'cancelled' => (int) (clone $allOrdersQuery)->where('status', 'cancelled')->count(),
            'expired' => (int) (clone $allOrdersQuery)->where('status', 'expired')->count(),
            'refunded' => (int) (clone $allOrdersQuery)->whereIn('status', ['refunded', 'partially_refunded'])->count(),
        ];

        // Hide expired/cancelled orders from the listing — they're noise
        // for the organizer (abandoned checkouts that never produced
        // tickets). Stats above still see them via the
        // order_breakdown.cancelled/expired counts so the operator can
        // tell at a glance how many were dropped this period.
        $query->whereNotIn('status', ['cancelled', 'expired']);

        $perPage = min((int) $request->input('per_page', 20), 100);
        $orders = $query->paginate($perPage);

        return $this->paginated($orders, function ($order) {
            // Use event name from either relationship (event or marketplaceEvent)
            $eventName = $order->event?->name ?? $order->marketplaceEvent?->name;
            $eventId = $order->event_id ?? $order->marketplace_event_id;

            // Pre-set the order relation on each ticket so getEffectivePrice()
            // can read order.discount_amount / subtotal for legacy fallback
            // without triggering N+1.
            $order->tickets->each(fn ($t) => $t->setRelation('order', $order));

            // Net total = sum of ticket base prices for this order, with
            // per-ticket promo discount applied (sale_price already baked in).
            $validTickets = $order->tickets->whereIn('status', ['valid', 'used']);
            $ticketPriceSum = $validTickets->sum(fn ($t) => $t->getEffectivePrice());
            $ticketOriginalSum = (float) $validTickets->sum('price');
            $ticketDiscountSum = round($ticketOriginalSum - $ticketPriceSum, 2);

            // Per-ticket seat detail for seated events. Non-seated tickets
            // get null entries which the UI filters out so general-admission
            // orders don't render an empty Loc column.
            $seats = $order->tickets
                ->whereIn('status', ['valid', 'used'])
                ->map(function ($t) {
                    $details = $t->getSeatDetails();
                    if (!$details) return null;
                    $type = $t->marketplaceTicketType?->name ?? $t->ticketType?->name;
                    return [
                        'section' => $details['section_name'],
                        'row' => $details['row_label'],
                        'seat' => $details['seat_number'],
                        'ticket_type' => $type,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            // Promo / discount fields surfaced on the Vanzari row's
            // Valoare column. Marketplace checkout writes the code to
            // order.meta.promo_code.code; orders.promo_code (column) is
            // only set by the tenant-side flow. Pull from either source.
            $orderMeta = is_array($order->meta) ? $order->meta : [];
            $promoCode = trim((string) (
                $orderMeta['promo_code']['code']
                ?? $orderMeta['coupon_code']
                ?? $order->promo_code
                ?? ''
            )) ?: null;
            $promoDiscount = (float) ($order->promo_discount ?? 0);

            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => (float) $order->total,
                'net_total' => (float) $ticketPriceSum,
                'event' => $eventName,
                'event_id' => $eventId,
                'customer' => $order->marketplaceCustomer
                    ? $order->marketplaceCustomer->full_name
                    : $order->customer_name,
                'customer_email' => $order->marketplaceCustomer?->email ?? $order->customer_email,
                'customer_phone' => $order->marketplaceCustomer?->phone ?? $order->customer_phone ?? '',
                'customer_city' => $order->marketplaceCustomer?->city ?? '',
                'source' => $order->source ?? 'marketplace',
                'tickets_count' => $order->tickets_count ?? $order->tickets()->count(),
                'ticket_types' => $order->tickets
                    ->map(fn ($t) => $t->marketplaceTicketType?->name ?? $t->ticketType?->name ?? null)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'seats' => $seats,
                'promo_code' => $promoCode,
                'promo_discount' => $promoDiscount,
                // Discount details surfaced under the Valoare column when a
                // reduction was applied (either via promo code or per-ticket
                // intrinsic sale_price). original_value is the pre-discount sum
                // of ticket prices; discount_amount is what was knocked off
                // across all valid tickets of the order.
                'discount_info' => $ticketDiscountSum > 0.01 ? [
                    'code' => $promoCode,
                    'discount_amount' => $ticketDiscountSum,
                    'original_value' => round($ticketOriginalSum, 2),
                ] : null,
                'currency' => $order->currency ?? 'RON',
                'created_at' => $order->created_at->toIso8601String(),
                'paid_at' => $order->paid_at?->toIso8601String(),
            ];
        }, $stats);
    }

    /**
     * Export orders as CSV
     */
    public function exportOrders(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $organizer = $this->requireOrganizer($request);

        // ticketType MUST include commission_* columns — Ticket::getNetPrice()
        // resolves the mode from there. Without them the per-type override
        // (added_on_top / included) silently falls through to organizer
        // default, producing wrong Net bilet values on the CSV.
        $query = Order::where('marketplace_organizer_id', $organizer->id)
            ->with([
                'event:id,title,marketplace_organizer_id,commission_rate,commission_mode',
                'marketplaceEvent:id,name',
                'marketplaceCustomer:id,first_name,last_name,phone',
                'tickets.marketplaceTicketType:id,name',
                'tickets.ticketType:id,name,event_id,commission_type,commission_rate,commission_fixed,commission_mode',
            ]);

        if ($request->has('event_id')) {
            $eventId = $request->event_id;
            $query->where(function ($q) use ($eventId) {
                $q->where('event_id', $eventId)
                    ->orWhere('marketplace_event_id', $eventId);
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $orders = $query->orderByDesc('created_at')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="export.csv"',
        ];

        return response()->stream(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            // BOM for Excel UTF-8 compatibility
            fwrite($handle, "\xEF\xBB\xBF");
            // One row per ticket so seat info (section/row/seat) gets a
            // dedicated cell per ticket. The "Comanda" column repeats across
            // tickets of the same order; spreadsheet users can group/sort by
            // it. Order-level fields (Status/Valoare/Sursa/Data) stay
            // identical for every ticket in the same order.
            fputcsv($handle, [
                'Data', 'Comanda', 'Status', 'Client', 'Telefon',
                'Tip bilet', 'Cod bilet', 'Sectiune', 'Rand', 'Loc',
                'Pret bilet', 'Dupa reducere', 'Reducere aplicata', 'Cod reducere',
            ], escape: '\\');

            foreach ($orders as $order) {
                $customer = $order->marketplaceCustomer?->full_name ?? $order->customer_name ?? '-';
                $phone = $order->marketplaceCustomer?->phone ?? $order->customer_phone ?? '-';
                $createdAt = $order->created_at->format('Y-m-d H:i');
                // Marketplace checkout stores the applied promo as
                // order.meta.promo_code.code; the orders.promo_code column
                // is only written by the tenant-side flow. Pick first that
                // has a value so the column reliably shows the code.
                $orderMeta = is_array($order->meta) ? $order->meta : [];
                $promoCode = trim((string) (
                    $orderMeta['promo_code']['code']
                    ?? $orderMeta['coupon_code']
                    ?? $order->promo_code
                    ?? ''
                ));

                if ($order->tickets->isEmpty()) {
                    // Order without tickets — emit one row so the order is
                    // still represented in the export instead of vanishing.
                    fputcsv($handle, [
                        $createdAt,
                        $order->order_number,
                        $order->status,
                        $customer,
                        $phone,
                        '-', '', '', '', '',
                        '0.00', '0.00', '0.00',
                        $promoCode,
                    ], escape: '\\');
                    continue;
                }

                // Pre-attach order on tickets so getEffectivePrice's fallback
                // (proportional via order.discount_amount/subtotal) avoids N+1.
                $order->tickets->each(fn ($t) => $t->setRelation('order', $order));

                foreach ($order->tickets as $ticket) {
                    $type = $ticket->marketplaceTicketType?->name ?? $ticket->ticketType?->name ?? '-';
                    $details = $ticket->getSeatDetails();
                    // Net = price minus baked-in commission for included
                    // / POS orders; equals price for on_top orders.
                    // See Ticket::getNetPrice() for the resolution table.
                    // Valoare finala bilet = ce a platit clientul efectiv pe
                    // acest bilet, dupa orice reducere (cod promo sau sale_price).
                    $finalValue = $ticket->getEffectivePrice();
                    $discountApplied = $ticket->getDiscountAmount();
                    fputcsv($handle, [
                        $createdAt,
                        $order->order_number,
                        $order->status,
                        $customer,
                        $phone,
                        $type,
                        $ticket->code ?? '',
                        $details['section_name'] ?? '',
                        $details['row_label'] ?? '',
                        $details['seat_number'] ?? '',
                        number_format($ticket->getNetPrice(), 2, '.', ''),
                        number_format($finalValue, 2, '.', ''),
                        number_format($discountApplied, 2, '.', ''),
                        $promoCode,
                    ], escape: '\\');
                }
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Get single order details
     */
    public function orderDetail(Request $request, int $orderId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $order = Order::where('id', $orderId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->with([
                'marketplaceEvent:id,name,starts_at,venue_name,venue_city',
                'marketplaceCustomer',
                'tickets.marketplaceTicketType:id,name,price',
            ])
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        return $this->success([
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'subtotal' => (float) $order->subtotal,
                'commission_amount' => (float) $order->commission_amount,
                'total' => (float) $order->total,
                'currency' => $order->currency,
                'event' => $order->marketplaceEvent ? [
                    'id' => $order->marketplaceEvent->id,
                    'name' => $order->marketplaceEvent->name,
                    'date' => $order->marketplaceEvent->starts_at->toIso8601String(),
                    'venue' => $order->marketplaceEvent->venue_name,
                    'city' => $order->marketplaceEvent->venue_city,
                ] : null,
                'customer' => [
                    'name' => $order->marketplaceCustomer?->full_name ?? $order->customer_name,
                    'email' => $order->marketplaceCustomer?->email ?? $order->customer_email,
                    'phone' => $order->marketplaceCustomer?->phone ?? $order->customer_phone,
                ],
                'tickets' => $order->tickets->map(function ($ticket) {
                    return [
                        'id' => $ticket->id,
                        'barcode' => $ticket->barcode,
                        'type' => $ticket->marketplaceTicketType?->name,
                        'price' => (float) $ticket->marketplaceTicketType?->price,
                        'status' => $ticket->status,
                        'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
                    ];
                }),
                'created_at' => $order->created_at->toIso8601String(),
                'paid_at' => $order->paid_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get payout summary
     */
    public function payoutSummary(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $month = $request->input('month', now()->format('Y-m'));

        $startDate = Carbon::parse($month)->startOfMonth();
        $endDate = Carbon::parse($month)->endOfMonth();

        $orders = Order::where('marketplace_organizer_id', $organizer->id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('source', '!=', 'test_order')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->get();

        $commissionRate = $organizer->getEffectiveCommissionRate();
        $grossRevenue = (float) $orders->sum('total');
        $commissionAmount = round($grossRevenue * $commissionRate / 100, 2);
        $netRevenue = $grossRevenue - $commissionAmount;

        // Get recent payouts
        $recentPayouts = $organizer->payouts()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function ($payout) {
                return [
                    'id' => $payout->id,
                    'reference' => $payout->reference,
                    'amount' => (float) $payout->amount,
                    'status' => $payout->status,
                    'status_label' => $payout->status_label,
                    'created_at' => $payout->created_at->toIso8601String(),
                    'completed_at' => $payout->completed_at?->toIso8601String(),
                ];
            });

        // Get pending payout if exists
        $pendingPayout = $organizer->getPendingPayout();

        return $this->success([
            'month' => $month,
            'summary' => [
                'total_orders' => $orders->count(),
                'gross_revenue' => $grossRevenue,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'net_payout' => $netRevenue,
            ],
            'balance' => [
                'available' => (float) $organizer->available_balance,
                'pending' => (float) $organizer->pending_balance,
                'total_paid_out' => (float) $organizer->total_paid_out,
                'minimum_payout' => $organizer->getMinimumPayoutAmount(),
            ],
            'has_payout_details' => !empty($organizer->payout_details),
            'can_request_payout' => $organizer->hasMinimumPayoutBalance()
                && !$organizer->hasPendingPayout()
                && !empty($organizer->payout_details),
            'pending_payout' => $pendingPayout ? [
                'id' => $pendingPayout->id,
                'reference' => $pendingPayout->reference,
                'amount' => (float) $pendingPayout->amount,
                'status' => $pendingPayout->status,
                'status_label' => $pendingPayout->status_label,
                'created_at' => $pendingPayout->created_at->toIso8601String(),
            ] : null,
            'recent_payouts' => $recentPayouts,
        ]);
    }

    /**
     * Require authenticated organizer
     */
    protected function requireOrganizer(Request $request): MarketplaceOrganizer
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }

        return $organizer;
    }
}
