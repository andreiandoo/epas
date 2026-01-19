<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Admin;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceTransaction;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseController
{
    /**
     * Get dashboard overview stats
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);
        $clientId = $admin->marketplace_client_id;

        // Date range
        $period = $request->get('period', '30'); // days
        $startDate = now()->subDays((int) $period)->startOfDay();
        $endDate = now()->endOfDay();

        // Core stats
        $stats = [
            'organizers' => [
                'total' => MarketplaceOrganizer::where('marketplace_client_id', $clientId)->count(),
                'active' => MarketplaceOrganizer::where('marketplace_client_id', $clientId)
                    ->where('status', 'active')->count(),
                'pending' => MarketplaceOrganizer::where('marketplace_client_id', $clientId)
                    ->where('status', 'pending')->count(),
                'new_this_period' => MarketplaceOrganizer::where('marketplace_client_id', $clientId)
                    ->whereBetween('created_at', [$startDate, $endDate])->count(),
            ],
            'customers' => [
                'total' => MarketplaceCustomer::where('marketplace_client_id', $clientId)->count(),
                'new_this_period' => MarketplaceCustomer::where('marketplace_client_id', $clientId)
                    ->whereBetween('created_at', [$startDate, $endDate])->count(),
            ],
            'events' => [
                'total' => MarketplaceEvent::where('marketplace_client_id', $clientId)->count(),
                'published' => MarketplaceEvent::where('marketplace_client_id', $clientId)
                    ->where('status', 'published')->count(),
                'pending_review' => MarketplaceEvent::where('marketplace_client_id', $clientId)
                    ->where('status', 'pending_review')->count(),
                'draft' => MarketplaceEvent::where('marketplace_client_id', $clientId)
                    ->where('status', 'draft')->count(),
                'upcoming' => MarketplaceEvent::where('marketplace_client_id', $clientId)
                    ->where('status', 'published')
                    ->where('starts_at', '>=', now())->count(),
            ],
            'orders' => [
                'total' => Order::where('marketplace_client_id', $clientId)->count(),
                'completed' => Order::where('marketplace_client_id', $clientId)
                    ->where('status', 'completed')->count(),
                'this_period' => Order::where('marketplace_client_id', $clientId)
                    ->whereBetween('created_at', [$startDate, $endDate])->count(),
            ],
            'revenue' => [
                'total' => (float) Order::where('marketplace_client_id', $clientId)
                    ->where('status', 'completed')
                    ->sum('total'),
                'this_period' => (float) Order::where('marketplace_client_id', $clientId)
                    ->where('status', 'completed')
                    ->whereBetween('paid_at', [$startDate, $endDate])
                    ->sum('total'),
                'commission_earned' => (float) Order::where('marketplace_client_id', $clientId)
                    ->where('status', 'completed')
                    ->sum('commission_amount'),
                'commission_this_period' => (float) Order::where('marketplace_client_id', $clientId)
                    ->where('status', 'completed')
                    ->whereBetween('paid_at', [$startDate, $endDate])
                    ->sum('commission_amount'),
            ],
            'payouts' => [
                'pending_amount' => (float) MarketplacePayout::where('marketplace_client_id', $clientId)
                    ->where('status', 'pending')
                    ->sum('amount'),
                'pending_count' => MarketplacePayout::where('marketplace_client_id', $clientId)
                    ->where('status', 'pending')->count(),
                'processed_this_period' => (float) MarketplacePayout::where('marketplace_client_id', $clientId)
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [$startDate, $endDate])
                    ->sum('amount'),
            ],
        ];

        return $this->success([
            'stats' => $stats,
            'period' => [
                'days' => $period,
                'start' => $startDate->toIso8601String(),
                'end' => $endDate->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get sales timeline chart data
     */
    public function salesTimeline(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);
        $clientId = $admin->marketplace_client_id;

        $days = min((int) $request->get('days', 30), 90);
        $startDate = now()->subDays($days)->startOfDay();

        $sales = Order::where('marketplace_client_id', $clientId)
            ->where('status', 'completed')
            ->where('paid_at', '>=', $startDate)
            ->selectRaw('DATE(paid_at) as date, COUNT(*) as orders, SUM(total) as revenue, SUM(commission_amount) as commission')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $timeline = [];
        for ($i = $days; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $timeline[] = [
                'date' => $date,
                'orders' => (int) ($sales[$date]->orders ?? 0),
                'revenue' => (float) ($sales[$date]->revenue ?? 0),
                'commission' => (float) ($sales[$date]->commission ?? 0),
            ];
        }

        return $this->success(['timeline' => $timeline]);
    }

    /**
     * Get recent activity feed
     */
    public function recentActivity(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);
        $clientId = $admin->marketplace_client_id;
        $limit = min((int) $request->get('limit', 20), 50);

        $activities = collect();

        // Recent orders
        $recentOrders = Order::where('marketplace_client_id', $clientId)
            ->with('marketplaceOrganizer:id,name')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($o) => [
                'type' => 'order',
                'id' => $o->id,
                'title' => "New order #{$o->order_number}",
                'subtitle' => $o->marketplaceOrganizer?->name,
                'amount' => (float) $o->total,
                'status' => $o->status,
                'created_at' => $o->created_at,
            ]);

        // Recent organizer registrations
        $recentOrganizers = MarketplaceOrganizer::where('marketplace_client_id', $clientId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($o) => [
                'type' => 'organizer_registered',
                'id' => $o->id,
                'title' => "New organizer: {$o->name}",
                'subtitle' => $o->email,
                'status' => $o->status,
                'created_at' => $o->created_at,
            ]);

        // Events submitted for review
        $recentEvents = MarketplaceEvent::where('marketplace_client_id', $clientId)
            ->whereNotNull('submitted_at')
            ->with('organizer:id,name')
            ->orderByDesc('submitted_at')
            ->limit($limit)
            ->get()
            ->map(fn($e) => [
                'type' => 'event_submitted',
                'id' => $e->id,
                'title' => "Event submitted: {$e->name}",
                'subtitle' => $e->organizer?->name,
                'status' => $e->status,
                'created_at' => $e->submitted_at,
            ]);

        // Payout requests
        $recentPayouts = MarketplacePayout::where('marketplace_client_id', $clientId)
            ->with('organizer:id,name')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn($p) => [
                'type' => 'payout_requested',
                'id' => $p->id,
                'title' => "Payout requested: {$p->reference}",
                'subtitle' => $p->organizer?->name,
                'amount' => (float) $p->amount,
                'status' => $p->status,
                'created_at' => $p->created_at,
            ]);

        // Merge and sort by date
        $activities = $recentOrders
            ->merge($recentOrganizers)
            ->merge($recentEvents)
            ->merge($recentPayouts)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values()
            ->map(function ($item) {
                $item['created_at'] = $item['created_at']->toIso8601String();
                return $item;
            });

        return $this->success(['activities' => $activities]);
    }

    /**
     * Get top organizers by revenue
     */
    public function topOrganizers(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);
        $clientId = $admin->marketplace_client_id;
        $limit = min((int) $request->get('limit', 10), 50);

        $organizers = MarketplaceOrganizer::where('marketplace_client_id', $clientId)
            ->where('status', 'active')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get()
            ->map(fn($o) => [
                'id' => $o->id,
                'name' => $o->name,
                'email' => $o->email,
                'total_events' => $o->total_events,
                'total_tickets_sold' => $o->total_tickets_sold,
                'total_revenue' => (float) $o->total_revenue,
                'available_balance' => (float) $o->available_balance,
            ]);

        return $this->success(['organizers' => $organizers]);
    }

    /**
     * Get top events by sales
     */
    public function topEvents(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);
        $clientId = $admin->marketplace_client_id;
        $limit = min((int) $request->get('limit', 10), 50);

        $events = MarketplaceEvent::where('marketplace_client_id', $clientId)
            ->where('status', 'published')
            ->with('organizer:id,name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'organizer' => $e->organizer?->name,
                'starts_at' => $e->starts_at->toIso8601String(),
                'tickets_sold' => $e->tickets_sold,
                'revenue' => (float) $e->revenue,
                'is_upcoming' => $e->starts_at >= now(),
            ]);

        return $this->success(['events' => $events]);
    }

    /**
     * Require authenticated admin
     */
    protected function requireAdmin(Request $request): MarketplaceAdmin
    {
        $admin = $request->user();

        if (!$admin instanceof MarketplaceAdmin) {
            abort(401, 'Unauthorized');
        }

        return $admin;
    }
}
