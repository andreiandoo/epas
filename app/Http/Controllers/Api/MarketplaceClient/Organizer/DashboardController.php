<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Event;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceOrganizer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class DashboardController extends BaseController
{
    /**
     * Get dashboard overview
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $fromDate = $request->input('from_date', now()->subDays(30)->toDateString());
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
            ->withCount('tickets as tickets_sold')
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'title' => $event->name,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'image' => $event->poster_url ?? $event->cover_image_url,
                    'start_date' => $event->event_date?->toDateString(),
                    'starts_at' => $event->event_date?->toIso8601String(),
                    'venue' => $event->venue_name,
                    'venue_city' => $event->venue_city,
                    'tickets_sold' => $event->tickets_sold ?? 0,
                    'tickets_total' => $event->ticketTypes()->sum('quantity') ?: 100,
                    'status' => $event->is_published ? 'published' : 'draft',
                ];
            });

        // Orders in period
        $orders = Order::where('marketplace_organizer_id', $organizer->id)
            ->whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59']);

        $completedOrders = (clone $orders)->where('status', 'completed');

        $commissionRate = $organizer->getEffectiveCommissionRate();
        $grossRevenue = (float) $completedOrders->sum('total');
        $commissionAmount = round($grossRevenue * $commissionRate / 100, 2);
        $netRevenue = $grossRevenue - $commissionAmount;

        $ticketsSold = $completedOrders->withCount('tickets')->get()->sum('tickets_count');

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
                'total_orders' => $orders->count(),
                'completed_orders' => $completedOrders->count(),
                'tickets_sold' => $ticketsSold,
                'gross_revenue' => $grossRevenue,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'net_revenue' => $netRevenue,
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
            'events_list' => $eventsList,
        ]);
    }

    /**
     * Get sales timeline
     */
    public function salesTimeline(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $fromDate = $request->input('from_date', now()->subDays(30)->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());
        $groupBy = $request->input('group_by', 'day');

        $dateFormat = match ($groupBy) {
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $sales = Order::where('marketplace_organizer_id', $organizer->id)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$fromDate, $toDate . ' 23:59:59'])
            ->selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as period")
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

        $limit = min((int) $request->get('limit', 10), 50);

        $orders = Order::where('marketplace_organizer_id', $organizer->id)
            ->with(['marketplaceEvent:id,name', 'marketplaceCustomer:id,first_name,last_name,email'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total' => (float) $order->total,
                    'event' => $order->marketplaceEvent?->name,
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
            ->with(['marketplaceEvent:id,name', 'marketplaceCustomer:id,first_name,last_name,email']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('event_id')) {
            $query->where('marketplace_event_id', $request->event_id);
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

        $query->orderByDesc('created_at');

        $perPage = min((int) $request->get('per_page', 20), 100);
        $orders = $query->paginate($perPage);

        return $this->paginated($orders, function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'total' => (float) $order->total,
                'event' => $order->marketplaceEvent?->name,
                'customer' => $order->marketplaceCustomer
                    ? $order->marketplaceCustomer->full_name
                    : $order->customer_name,
                'customer_email' => $order->marketplaceCustomer?->email ?? $order->customer_email,
                'tickets_count' => $order->tickets_count ?? $order->tickets()->count(),
                'created_at' => $order->created_at->toIso8601String(),
                'paid_at' => $order->paid_at?->toIso8601String(),
            ];
        });
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
            ->where('status', 'completed')
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
