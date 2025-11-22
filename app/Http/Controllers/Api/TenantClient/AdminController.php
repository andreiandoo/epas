<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * Get admin dashboard stats
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $totalEvents = Event::where('tenant_id', $tenant->id)->count();
        $activeEvents = Event::where('tenant_id', $tenant->id)
            ->where('status', 'published')
            ->where('event_date', '>=', now())
            ->count();
        $totalOrders = Order::where('tenant_id', $tenant->id)->count();
        $totalRevenue = Order::where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->sum('total_cents') / 100;
        $ticketsSold = Order::where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->withCount('tickets')
            ->get()
            ->sum('tickets_count');
        $customers = Customer::where('tenant_id', $tenant->id)->count();

        $recentOrders = Order::where('tenant_id', $tenant->id)
            ->with(['customer', 'event'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'customer_name' => $order->customer?->name ?? $order->customer_email,
                'event_title' => $order->event?->title,
                'total' => $order->total_cents / 100,
                'status' => $order->status,
                'created_at' => $order->created_at->toISOString(),
            ]);

        $upcomingEvents = Event::where('tenant_id', $tenant->id)
            ->where('status', 'published')
            ->where('event_date', '>=', now())
            ->orderBy('event_date')
            ->limit(5)
            ->get()
            ->map(fn ($event) => [
                'id' => $event->id,
                'title' => $event->title,
                'slug' => $event->slug,
                'event_date' => $event->event_date->toISOString(),
                'venue' => $event->venue,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_events' => $totalEvents,
                    'active_events' => $activeEvents,
                    'total_orders' => $totalOrders,
                    'total_revenue' => $totalRevenue,
                    'tickets_sold' => $ticketsSold,
                    'customers' => $customers,
                ],
                'recent_orders' => $recentOrders,
                'upcoming_events' => $upcomingEvents,
            ],
        ]);
    }

    /**
     * List events (admin view)
     */
    public function events(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $perPage = $request->input('per_page', 20);

        $events = Event::where('tenant_id', $tenant->id)
            ->with('venue')
            ->orderBy('event_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'events' => $events->map(fn ($event) => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'start_date' => $event->event_date?->toISOString(),
                    'venue' => $event->venue,
                    'status' => $event->status,
                    'tickets_sold' => $event->tickets()->count(),
                ]),
                'meta' => [
                    'total' => $events->total(),
                    'page' => $events->currentPage(),
                    'per_page' => $events->perPage(),
                    'last_page' => $events->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Create event
     */
    public function createEvent(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'venue' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'required|in:draft,published,cancelled',
        ]);

        $event = Event::create([
            'tenant_id' => $tenant->id,
            'title' => $validated['title'],
            'slug' => \Str::slug($validated['title']),
            'description' => $validated['description'] ?? '',
            'venue' => $validated['venue'],
            'event_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Event created',
            'data' => [
                'id' => $event->id,
                'slug' => $event->slug,
            ],
        ]);
    }

    /**
     * Update event
     */
    public function updateEvent(Request $request, int $eventId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $event = Event::where('tenant_id', $tenant->id)
            ->findOrFail($eventId);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'venue' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'nullable|date|after:start_date',
            'status' => 'sometimes|in:draft,published,cancelled',
        ]);

        $event->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Event updated',
        ]);
    }

    /**
     * List orders
     */
    public function orders(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $perPage = $request->input('per_page', 20);

        $orders = Order::where('tenant_id', $tenant->id)
            ->with(['customer', 'event'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $orders->map(fn ($order) => [
                    'id' => $order->id,
                    'customer_name' => $order->customer?->name,
                    'customer_email' => $order->customer_email,
                    'event_title' => $order->event?->title,
                    'total_cents' => $order->total_cents,
                    'status' => $order->status,
                    'created_at' => $order->created_at->toISOString(),
                ]),
                'meta' => [
                    'total' => $orders->total(),
                    'page' => $orders->currentPage(),
                    'per_page' => $orders->perPage(),
                ],
            ],
        ]);
    }

    /**
     * Get order details
     */
    public function orderDetail(Request $request, int $orderId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $order = Order::where('tenant_id', $tenant->id)
            ->with(['customer', 'event', 'tickets'])
            ->findOrFail($orderId);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->id,
                'customer' => [
                    'name' => $order->customer?->name,
                    'email' => $order->customer_email,
                    'phone' => $order->customer?->phone,
                ],
                'event' => $order->event ? [
                    'id' => $order->event->id,
                    'title' => $order->event->title,
                    'date' => $order->event->event_date?->toISOString(),
                ] : null,
                'tickets' => $order->tickets->map(fn ($ticket) => [
                    'id' => $ticket->id,
                    'code' => $ticket->code,
                    'type' => $ticket->ticket_type,
                    'status' => $ticket->status,
                ]),
                'total_cents' => $order->total_cents,
                'status' => $order->status,
                'payment_method' => $order->payment_method,
                'created_at' => $order->created_at->toISOString(),
            ],
        ]);
    }

    /**
     * List customers
     */
    public function customers(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $perPage = $request->input('per_page', 20);

        $customers = Customer::where('tenant_id', $tenant->id)
            ->withCount('orders')
            ->withSum('orders', 'total_cents')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'customers' => $customers->map(fn ($customer) => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'orders_count' => $customer->orders_count,
                    'total_spent' => ($customer->orders_sum_total_cents ?? 0) / 100,
                    'created_at' => $customer->created_at->toISOString(),
                ]),
                'meta' => [
                    'total' => $customers->total(),
                    'page' => $customers->currentPage(),
                    'per_page' => $customers->perPage(),
                ],
            ],
        ]);
    }

    /**
     * List users
     */
    public function users(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Get users associated with this tenant
        $users = User::where('tenant_id', $tenant->id)
            ->orWhere('id', $tenant->owner_id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'created_at' => $user->created_at->toISOString(),
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $users,
            ],
        ]);
    }

    /**
     * Create user
     */
    public function createUser(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:editor,manager',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'tenant_id' => $tenant->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created',
            'data' => [
                'id' => $user->id,
            ],
        ]);
    }

    /**
     * Get settings
     */
    public function settings(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        return response()->json([
            'success' => true,
            'data' => [
                'general' => [
                    'name' => $tenant->name,
                    'email' => $tenant->contact_email,
                    'phone' => $tenant->contact_phone,
                ],
                'branding' => $tenant->settings['branding'] ?? [],
                'theme' => $tenant->settings['theme'] ?? [],
                'payments' => $this->getPaymentSettings($tenant),
                'notifications' => $tenant->settings['notifications'] ?? [],
            ],
        ]);
    }

    /**
     * Update settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'general' => 'sometimes|array',
            'branding' => 'sometimes|array',
            'theme' => 'sometimes|array',
            'notifications' => 'sometimes|array',
        ]);

        $settings = $tenant->settings ?? [];

        if (isset($validated['branding'])) {
            $settings['branding'] = array_merge($settings['branding'] ?? [], $validated['branding']);
        }
        if (isset($validated['theme'])) {
            $settings['theme'] = array_merge($settings['theme'] ?? [], $validated['theme']);
        }
        if (isset($validated['notifications'])) {
            $settings['notifications'] = array_merge($settings['notifications'] ?? [], $validated['notifications']);
        }

        $tenant->settings = $settings;

        if (isset($validated['general'])) {
            if (isset($validated['general']['name'])) {
                $tenant->name = $validated['general']['name'];
            }
            if (isset($validated['general']['email'])) {
                $tenant->contact_email = $validated['general']['email'];
            }
            if (isset($validated['general']['phone'])) {
                $tenant->contact_phone = $validated['general']['phone'];
            }
        }

        $tenant->save();

        return response()->json([
            'success' => true,
            'message' => 'Settings updated',
        ]);
    }

    protected function getPaymentSettings($tenant): array
    {
        $settings = $tenant->settings['payments'] ?? [];

        return [
            'stripe' => [
                'enabled' => !empty($settings['stripe']['enabled']),
            ],
            'netopia' => [
                'enabled' => !empty($settings['netopia']['enabled']),
            ],
            'euplatesc' => [
                'enabled' => !empty($settings['euplatesc']['enabled']),
            ],
            'payu' => [
                'enabled' => !empty($settings['payu']['enabled']),
            ],
        ];
    }
}
