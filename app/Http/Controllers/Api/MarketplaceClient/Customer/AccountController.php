<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AccountController extends BaseController
{
    /**
     * Get customer's orders
     */
    public function orders(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $query = Order::where('marketplace_customer_id', $customer->id)
            ->with(['marketplaceEvent:id,name,slug,starts_at,venue_name,venue_city,image']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('upcoming')) {
            $query->whereHas('marketplaceEvent', function ($q) {
                $q->where('starts_at', '>=', now());
            });
        }

        $query->orderByDesc('created_at');

        $perPage = min((int) $request->get('per_page', 20), 50);
        $orders = $query->paginate($perPage);

        return $this->paginated($orders, function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => (float) $order->total,
                'currency' => $order->currency,
                'tickets_count' => $order->tickets_count ?? $order->tickets()->count(),
                'event' => $order->marketplaceEvent ? [
                    'id' => $order->marketplaceEvent->id,
                    'name' => $order->marketplaceEvent->name,
                    'slug' => $order->marketplaceEvent->slug,
                    'date' => $order->marketplaceEvent->starts_at->toIso8601String(),
                    'venue' => $order->marketplaceEvent->venue_name,
                    'city' => $order->marketplaceEvent->venue_city,
                    'image' => $order->marketplaceEvent->image_url,
                    'is_upcoming' => $order->marketplaceEvent->starts_at >= now(),
                ] : null,
                'created_at' => $order->created_at->toIso8601String(),
            ];
        });
    }

    /**
     * Get single order details
     */
    public function orderDetail(Request $request, int $orderId): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $order = Order::where('id', $orderId)
            ->where('marketplace_customer_id', $customer->id)
            ->with([
                'marketplaceEvent',
                'tickets.marketplaceTicketType',
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
                'total' => (float) $order->total,
                'currency' => $order->currency,
                'event' => $order->marketplaceEvent ? [
                    'id' => $order->marketplaceEvent->id,
                    'name' => $order->marketplaceEvent->name,
                    'slug' => $order->marketplaceEvent->slug,
                    'description' => $order->marketplaceEvent->short_description,
                    'date' => $order->marketplaceEvent->starts_at->toIso8601String(),
                    'end_date' => $order->marketplaceEvent->ends_at?->toIso8601String(),
                    'doors_open' => $order->marketplaceEvent->doors_open_at?->toIso8601String(),
                    'venue' => $order->marketplaceEvent->venue_name,
                    'venue_address' => $order->marketplaceEvent->venue_address,
                    'city' => $order->marketplaceEvent->venue_city,
                    'image' => $order->marketplaceEvent->image_url,
                    'is_upcoming' => $order->marketplaceEvent->starts_at >= now(),
                ] : null,
                'tickets' => $order->tickets->map(function ($ticket) {
                    return [
                        'id' => $ticket->id,
                        'barcode' => $ticket->barcode,
                        'type' => $ticket->marketplaceTicketType?->name,
                        'price' => (float) ($ticket->marketplaceTicketType?->price ?? 0),
                        'status' => $ticket->status,
                        'attendee_name' => $ticket->attendee_name,
                        'checked_in' => $ticket->checked_in_at !== null,
                        'checked_in_at' => $ticket->checked_in_at?->toIso8601String(),
                    ];
                }),
                'can_download_tickets' => $order->status === 'completed' && $order->payment_status === 'paid',
                'created_at' => $order->created_at->toIso8601String(),
                'paid_at' => $order->paid_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get customer's upcoming tickets
     */
    public function upcomingTickets(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $orders = Order::where('marketplace_customer_id', $customer->id)
            ->where('status', 'completed')
            ->whereHas('marketplaceEvent', function ($q) {
                $q->where('starts_at', '>=', now());
            })
            ->with([
                'marketplaceEvent:id,name,slug,starts_at,venue_name,venue_city,image',
                'tickets.marketplaceTicketType:id,name',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $tickets = $orders->flatMap(function ($order) {
            return $order->tickets->map(function ($ticket) use ($order) {
                return [
                    'id' => $ticket->id,
                    'barcode' => $ticket->barcode,
                    'type' => $ticket->marketplaceTicketType?->name,
                    'status' => $ticket->status,
                    'order_number' => $order->order_number,
                    'event' => [
                        'id' => $order->marketplaceEvent->id,
                        'name' => $order->marketplaceEvent->name,
                        'slug' => $order->marketplaceEvent->slug,
                        'date' => $order->marketplaceEvent->starts_at->toIso8601String(),
                        'venue' => $order->marketplaceEvent->venue_name,
                        'city' => $order->marketplaceEvent->venue_city,
                        'image' => $order->marketplaceEvent->image_url,
                    ],
                ];
            });
        })->sortBy(function ($ticket) {
            return $ticket['event']['date'];
        })->values();

        return $this->success([
            'tickets' => $tickets,
        ]);
    }

    /**
     * Get past events
     */
    public function pastEvents(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $orders = Order::where('marketplace_customer_id', $customer->id)
            ->where('status', 'completed')
            ->whereHas('marketplaceEvent', function ($q) {
                $q->where('starts_at', '<', now());
            })
            ->with('marketplaceEvent:id,name,slug,starts_at,venue_name,venue_city,image')
            ->withCount('tickets')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $events = $orders->map(function ($order) {
            return [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'tickets_count' => $order->tickets_count,
                'event' => [
                    'id' => $order->marketplaceEvent->id,
                    'name' => $order->marketplaceEvent->name,
                    'slug' => $order->marketplaceEvent->slug,
                    'date' => $order->marketplaceEvent->starts_at->toIso8601String(),
                    'venue' => $order->marketplaceEvent->venue_name,
                    'city' => $order->marketplaceEvent->venue_city,
                    'image' => $order->marketplaceEvent->image_url,
                ],
            ];
        });

        return $this->success([
            'past_events' => $events,
        ]);
    }

    /**
     * Delete account
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $validated = $request->validate([
            'password' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        if (!$customer->password || !password_verify($validated['password'], $customer->password)) {
            return $this->error('Invalid password', 422);
        }

        // Check for upcoming events
        $hasUpcoming = Order::where('marketplace_customer_id', $customer->id)
            ->where('status', 'completed')
            ->whereHas('marketplaceEvent', function ($q) {
                $q->where('starts_at', '>=', now());
            })
            ->exists();

        if ($hasUpcoming) {
            return $this->error('Cannot delete account with upcoming events. Please contact support.', 400);
        }

        // Soft delete and anonymize
        $customer->update([
            'email' => 'deleted_' . $customer->id . '@deleted.local',
            'first_name' => 'Deleted',
            'last_name' => 'User',
            'phone' => null,
            'address' => null,
            'status' => 'deleted',
        ]);
        $customer->delete();

        return $this->success(null, 'Account deleted successfully');
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
