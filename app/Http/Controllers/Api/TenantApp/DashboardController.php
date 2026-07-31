<?php

namespace App\Http\Controllers\Api\TenantApp;

use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant home KPIs. Aggregates the tenant's own events/orders/tickets.
 * Figures are intentionally simple (paid orders + non-dead tickets); tune the
 * status buckets against real data once a staging environment is available.
 */
class DashboardController extends BaseController
{
    // Revenue keyed on Order.status (paid orders keep payment_status='pending').
    private const PAID_ORDER_STATUSES = ['paid', 'completed', 'confirmed', 'free'];
    private const DEAD_TICKET_STATES = ['cancelled', 'refunded'];

    public function index(Request $request): JsonResponse
    {
        // Home KPIs are visible to any authenticated tenant staff (a gate
        // scanner still sees the basic numbers); detailed reports/exports are
        // gated separately by 'reports.view'.
        $tid = $this->tenantId($request);

        $totalEvents = Event::where('tenant_id', $tid)->count();
        $upcoming = Event::where('tenant_id', $tid)
            ->whereDate('event_date', '>=', now()->toDateString())
            ->count();

        $totalSold = Ticket::where('tenant_id', $tid)
            ->whereNotIn('status', self::DEAD_TICKET_STATES)
            ->count();
        $entered = Ticket::where('tenant_id', $tid)
            ->whereNotNull('checked_in_at')
            ->count();
        $totalRevenue = (float) Order::where('tenant_id', $tid)
            ->whereIn('status', self::PAID_ORDER_STATUSES)
            ->sum('total');

        $recent = Order::where('tenant_id', $tid)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'total' => (float) $o->total,
                'status' => $o->status,
                'source' => $o->source,
                'created_at' => optional($o->created_at)->toIso8601String(),
            ]);

        return $this->success([
            'kpis' => [
                'total_events' => $totalEvents,
                'upcoming_events' => $upcoming,
                'tickets_sold' => $totalSold,
                'entered' => $entered,
                'revenue' => $totalRevenue,
            ],
            'recent_orders' => $recent,
        ]);
    }
}
