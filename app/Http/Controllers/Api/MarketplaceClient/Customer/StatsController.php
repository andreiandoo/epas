<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use App\Models\Gamification\CustomerPoints;
use App\Models\Gamification\CustomerExperience;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StatsController extends BaseController
{
    /**
     * Get customer dashboard stats
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        // Orders stats
        $totalOrders = Order::where('marketplace_customer_id', $customer->id)->count();
        $completedOrders = Order::where('marketplace_customer_id', $customer->id)
            ->where('status', 'completed')
            ->count();

        // Tickets stats
        $totalTickets = Order::where('marketplace_customer_id', $customer->id)
            ->where('status', 'completed')
            ->withCount('tickets')
            ->get()
            ->sum('tickets_count');

        // Upcoming events
        $upcomingEvents = Order::where('marketplace_customer_id', $customer->id)
            ->where('status', 'completed')
            ->whereHas('marketplaceEvent', function ($q) {
                $q->where('starts_at', '>=', now());
            })
            ->count();

        // Past events
        $pastEvents = Order::where('marketplace_customer_id', $customer->id)
            ->where('status', 'completed')
            ->whereHas('marketplaceEvent', function ($q) {
                $q->where('starts_at', '<', now());
            })
            ->count();

        // Active tickets (for upcoming events)
        $activeTickets = Order::where('marketplace_customer_id', $customer->id)
            ->where('status', 'completed')
            ->whereHas('marketplaceEvent', function ($q) {
                $q->where('starts_at', '>=', now());
            })
            ->withCount('tickets')
            ->get()
            ->sum('tickets_count');

        // Points/Rewards (if gamification is enabled)
        $points = 0;
        $level = 1;
        $levelName = 'Bronze';
        $xp = 0;
        $xpToNextLevel = 100;

        try {
            $customerPoints = CustomerPoints::where('marketplace_customer_id', $customer->id)->first();
            if ($customerPoints) {
                $points = $customerPoints->balance ?? 0;
            }

            $customerExperience = CustomerExperience::where('marketplace_customer_id', $customer->id)->first();
            if ($customerExperience) {
                $level = $customerExperience->current_level ?? 1;
                $levelName = $customerExperience->current_level_group ?? 'Bronze';
                $xp = $customerExperience->total_xp ?? 0;
                $xpToNextLevel = $customerExperience->xp_to_next_level ?? 100;
            }
        } catch (\Exception $e) {
            // Gamification tables might not exist yet
        }

        // Reviews count
        $reviewsCount = 0;
        try {
            $reviewsCount = \DB::table('marketplace_customer_reviews')
                ->where('marketplace_customer_id', $customer->id)
                ->count();
        } catch (\Exception $e) {
            // Table might not exist yet
        }

        // Watchlist count
        $watchlistCount = 0;
        try {
            $watchlistCount = \DB::table('marketplace_customer_watchlist')
                ->where('marketplace_customer_id', $customer->id)
                ->count();
        } catch (\Exception $e) {
            // Table might not exist yet
        }

        // Total spent
        $totalSpent = Order::where('marketplace_customer_id', $customer->id)
            ->where('status', 'completed')
            ->sum('total');

        // Credit balance (if applicable)
        $creditBalance = $customer->credit_balance ?? 0;

        return $this->success([
            'orders' => [
                'total' => $totalOrders,
                'completed' => $completedOrders,
            ],
            'tickets' => [
                'total' => $totalTickets,
                'active' => $activeTickets,
            ],
            'events' => [
                'upcoming' => $upcomingEvents,
                'past' => $pastEvents,
            ],
            'rewards' => [
                'points' => $points,
                'level' => $level,
                'level_name' => $levelName,
                'xp' => $xp,
                'xp_to_next_level' => $xpToNextLevel,
            ],
            'reviews_count' => $reviewsCount,
            'watchlist_count' => $watchlistCount,
            'total_spent' => (float) $totalSpent,
            'credit_balance' => (float) $creditBalance,
            'currency' => 'RON',
        ]);
    }

    /**
     * Get upcoming events for dashboard
     */
    public function upcomingEvents(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $limit = min((int) $request->get('limit', 5), 20);

        $orders = Order::where('marketplace_customer_id', $customer->id)
            ->where('status', 'completed')
            ->whereHas('marketplaceEvent', function ($q) {
                $q->where('starts_at', '>=', now());
            })
            ->with(['marketplaceEvent:id,name,slug,starts_at,ends_at,venue_name,venue_city,image'])
            ->withCount('tickets')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        $events = $orders->map(function ($order) {
            $event = $order->marketplaceEvent;
            $daysUntil = now()->diffInDays($event->starts_at, false);

            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'tickets_count' => $order->tickets_count,
                'event' => [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'date' => $event->starts_at->toIso8601String(),
                    'date_formatted' => $event->starts_at->format('d M Y'),
                    'time' => $event->starts_at->format('H:i'),
                    'venue' => $event->venue_name,
                    'city' => $event->venue_city,
                    'image' => $event->image_url,
                    'days_until' => max(0, $daysUntil),
                ],
            ];
        })->sortBy(function ($item) {
            return $item['event']['date'];
        })->values();

        return $this->success([
            'upcoming_events' => $events,
        ]);
    }

    /**
     * Require authenticated customer
     */
    protected function requireCustomer(Request $request): MarketplaceCustomer
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            abort(401, 'Unauthorized');
        }

        return $customer;
    }
}
