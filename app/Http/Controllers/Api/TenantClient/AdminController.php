<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Models\Venue;
use App\Models\Seating\SeatingLayout;
use App\Models\Seating\PriceTier;
use App\Models\Seating\DynamicPricingRule;
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

    // ==================== VENUES ====================

    /**
     * List venues
     */
    public function venues(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $venues = Venue::where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($venue) => [
                'id' => $venue->id,
                'name' => $venue->name,
                'address' => $venue->address,
                'city' => $venue->city,
                'capacity' => $venue->capacity,
                'has_seating' => $venue->seatingLayouts()->exists(),
            ]);

        return response()->json([
            'success' => true,
            'data' => ['venues' => $venues],
        ]);
    }

    /**
     * Create venue
     */
    public function createVenue(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
        ]);

        $venue = Venue::create([
            'tenant_id' => $tenant->id,
            ...$validated,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Venue created',
            'data' => ['id' => $venue->id],
        ]);
    }

    /**
     * Update venue
     */
    public function updateVenue(Request $request, int $venueId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $venue = Venue::where('tenant_id', $tenant->id)->findOrFail($venueId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
        ]);

        $venue->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Venue updated',
        ]);
    }

    // ==================== ARTISTS ====================

    /**
     * List artists
     */
    public function artists(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $artists = Artist::where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($artist) => [
                'id' => $artist->id,
                'name' => $artist->name,
                'slug' => $artist->slug,
                'genre' => $artist->genre,
                'image_url' => $artist->image_url,
                'events_count' => $artist->events()->count(),
            ]);

        return response()->json([
            'success' => true,
            'data' => ['artists' => $artists],
        ]);
    }

    /**
     * Create artist
     */
    public function createArtist(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bio' => 'nullable|string',
            'genre' => 'nullable|string|max:255',
            'website' => 'nullable|url',
            'social_links' => 'nullable|array',
        ]);

        $artist = Artist::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'slug' => \Str::slug($validated['name']),
            'bio' => $validated['bio'] ?? null,
            'genre' => $validated['genre'] ?? null,
            'website' => $validated['website'] ?? null,
            'social_links' => $validated['social_links'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Artist created',
            'data' => ['id' => $artist->id],
        ]);
    }

    /**
     * Update artist
     */
    public function updateArtist(Request $request, int $artistId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $artist = Artist::where('tenant_id', $tenant->id)->findOrFail($artistId);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'bio' => 'nullable|string',
            'genre' => 'nullable|string|max:255',
        ]);

        $artist->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Artist updated',
        ]);
    }

    // ==================== SEATING LAYOUTS ====================

    /**
     * List seating layouts
     */
    public function seatingLayouts(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $layouts = SeatingLayout::where('tenant_id', $tenant->id)
            ->with('venue')
            ->orderBy('name')
            ->get()
            ->map(fn ($layout) => [
                'id' => $layout->id,
                'name' => $layout->name,
                'venue_id' => $layout->venue_id,
                'venue_name' => $layout->venue?->name,
                'total_seats' => $layout->total_seats,
                'sections_count' => $layout->sections()->count(),
                'is_active' => $layout->is_active,
            ]);

        return response()->json([
            'success' => true,
            'data' => ['layouts' => $layouts],
        ]);
    }

    /**
     * Get seating layout detail
     */
    public function seatingLayoutDetail(Request $request, int $layoutId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $layout = SeatingLayout::where('tenant_id', $tenant->id)
            ->with(['sections.rows.seats', 'venue'])
            ->findOrFail($layoutId);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $layout->id,
                'name' => $layout->name,
                'venue' => $layout->venue ? [
                    'id' => $layout->venue->id,
                    'name' => $layout->venue->name,
                ] : null,
                'sections' => $layout->sections->map(fn ($section) => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'color' => $section->color,
                    'rows' => $section->rows->map(fn ($row) => [
                        'id' => $row->id,
                        'name' => $row->name,
                        'seats' => $row->seats->map(fn ($seat) => [
                            'id' => $seat->id,
                            'number' => $seat->seat_number,
                            'status' => $seat->status,
                        ]),
                    ]),
                ]),
                'geometry' => $layout->geometry,
            ],
        ]);
    }

    // ==================== PRICE TIERS ====================

    /**
     * List price tiers
     */
    public function priceTiers(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $tiers = PriceTier::where('tenant_id', $tenant->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($tier) => [
                'id' => $tier->id,
                'name' => $tier->name,
                'color' => $tier->color,
                'base_price' => $tier->base_price,
                'currency' => $tier->currency ?? 'RON',
                'is_active' => $tier->is_active,
            ]);

        return response()->json([
            'success' => true,
            'data' => ['tiers' => $tiers],
        ]);
    }

    /**
     * Create price tier
     */
    public function createPriceTier(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:7',
            'base_price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $tier = PriceTier::create([
            'tenant_id' => $tenant->id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#3B82F6',
            'base_price' => $validated['base_price'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Price tier created',
            'data' => ['id' => $tier->id],
        ]);
    }

    // ==================== DYNAMIC PRICING ====================

    /**
     * List dynamic pricing rules
     */
    public function dynamicPricingRules(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $rules = DynamicPricingRule::where('tenant_id', $tenant->id)
            ->orderBy('priority')
            ->get()
            ->map(fn ($rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'type' => $rule->type,
                'adjustment_type' => $rule->adjustment_type,
                'adjustment_value' => $rule->adjustment_value,
                'conditions' => $rule->conditions,
                'is_active' => $rule->is_active,
                'priority' => $rule->priority,
            ]);

        return response()->json([
            'success' => true,
            'data' => ['rules' => $rules],
        ]);
    }

    /**
     * Create dynamic pricing rule
     */
    public function createDynamicPricingRule(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:time_based,demand_based,inventory_based',
            'adjustment_type' => 'required|in:percentage,fixed',
            'adjustment_value' => 'required|numeric',
            'conditions' => 'required|array',
            'priority' => 'nullable|integer',
        ]);

        $rule = DynamicPricingRule::create([
            'tenant_id' => $tenant->id,
            ...$validated,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dynamic pricing rule created',
            'data' => ['id' => $rule->id],
        ]);
    }

    // ==================== SITE TEMPLATES ====================

    /**
     * List available site templates
     */
    public function siteTemplates(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Get available templates from config or database
        $templates = config('tenant-package.templates', [
            [
                'id' => 'modern',
                'name' => 'Modern',
                'description' => 'Clean, modern design with bold typography',
                'preview_url' => '/templates/modern-preview.png',
                'colors' => ['primary' => '#3B82F6', 'secondary' => '#1E40AF'],
            ],
            [
                'id' => 'classic',
                'name' => 'Classic',
                'description' => 'Traditional, elegant layout',
                'preview_url' => '/templates/classic-preview.png',
                'colors' => ['primary' => '#1F2937', 'secondary' => '#374151'],
            ],
            [
                'id' => 'vibrant',
                'name' => 'Vibrant',
                'description' => 'Colorful and energetic design for entertainment',
                'preview_url' => '/templates/vibrant-preview.png',
                'colors' => ['primary' => '#7C3AED', 'secondary' => '#EC4899'],
            ],
            [
                'id' => 'minimal',
                'name' => 'Minimal',
                'description' => 'Simple and focused on content',
                'preview_url' => '/templates/minimal-preview.png',
                'colors' => ['primary' => '#111827', 'secondary' => '#6B7280'],
            ],
            [
                'id' => 'dark',
                'name' => 'Dark Mode',
                'description' => 'Dark theme for nightlife and concerts',
                'preview_url' => '/templates/dark-preview.png',
                'colors' => ['primary' => '#F59E0B', 'secondary' => '#D97706'],
            ],
        ]);

        // Get current template
        $currentTemplate = $tenant->settings['site_template'] ?? 'modern';

        return response()->json([
            'success' => true,
            'data' => [
                'templates' => $templates,
                'current_template' => $currentTemplate,
            ],
        ]);
    }

    /**
     * Select site template
     */
    public function selectSiteTemplate(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'template_id' => 'required|string|in:modern,classic,vibrant,minimal,dark',
        ]);

        $settings = $tenant->settings ?? [];
        $settings['site_template'] = $validated['template_id'];
        $tenant->settings = $settings;
        $tenant->save();

        // Invalidate existing packages so they regenerate with new template
        $tenant->packages()
            ->where('status', 'ready')
            ->update(['status' => 'invalidated']);

        return response()->json([
            'success' => true,
            'message' => 'Site template updated. Packages will be regenerated.',
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

    /**
     * Get payment processor settings
     */
    public function paymentSettings(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $settings = $tenant->settings['payments'] ?? [];

        $processors = [
            [
                'id' => 'stripe',
                'name' => 'Stripe',
                'description' => 'Accept credit cards globally',
                'logo' => '/images/processors/stripe.svg',
                'fields' => [
                    ['key' => 'public_key', 'label' => 'Publishable Key', 'type' => 'text', 'required' => true],
                    ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password', 'required' => true],
                    ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password', 'required' => false],
                ],
                'enabled' => !empty($settings['stripe']['enabled']),
                'configured' => !empty($settings['stripe']['public_key']),
                'values' => [
                    'public_key' => $settings['stripe']['public_key'] ?? '',
                    'secret_key' => !empty($settings['stripe']['secret_key']) ? '••••••••' : '',
                    'webhook_secret' => !empty($settings['stripe']['webhook_secret']) ? '••••••••' : '',
                ],
            ],
            [
                'id' => 'netopia',
                'name' => 'Netopia',
                'description' => 'Popular Romanian payment processor',
                'logo' => '/images/processors/netopia.svg',
                'fields' => [
                    ['key' => 'merchant_id', 'label' => 'Merchant ID', 'type' => 'text', 'required' => true],
                    ['key' => 'public_key', 'label' => 'Public Key (PEM)', 'type' => 'textarea', 'required' => true],
                    ['key' => 'private_key', 'label' => 'Private Key (PEM)', 'type' => 'textarea', 'required' => true],
                ],
                'enabled' => !empty($settings['netopia']['enabled']),
                'configured' => !empty($settings['netopia']['merchant_id']),
                'values' => [
                    'merchant_id' => $settings['netopia']['merchant_id'] ?? '',
                    'public_key' => !empty($settings['netopia']['public_key']) ? '[CONFIGURED]' : '',
                    'private_key' => !empty($settings['netopia']['private_key']) ? '[CONFIGURED]' : '',
                ],
            ],
            [
                'id' => 'euplatesc',
                'name' => 'EuPlatesc',
                'description' => 'Romanian card payments',
                'logo' => '/images/processors/euplatesc.svg',
                'fields' => [
                    ['key' => 'merchant_id', 'label' => 'Merchant ID', 'type' => 'text', 'required' => true],
                    ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password', 'required' => true],
                ],
                'enabled' => !empty($settings['euplatesc']['enabled']),
                'configured' => !empty($settings['euplatesc']['merchant_id']),
                'values' => [
                    'merchant_id' => $settings['euplatesc']['merchant_id'] ?? '',
                    'secret_key' => !empty($settings['euplatesc']['secret_key']) ? '••••••••' : '',
                ],
            ],
            [
                'id' => 'payu',
                'name' => 'PayU',
                'description' => 'Multi-currency payment gateway',
                'logo' => '/images/processors/payu.svg',
                'fields' => [
                    ['key' => 'merchant_id', 'label' => 'Merchant ID', 'type' => 'text', 'required' => true],
                    ['key' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password', 'required' => true],
                ],
                'enabled' => !empty($settings['payu']['enabled']),
                'configured' => !empty($settings['payu']['merchant_id']),
                'values' => [
                    'merchant_id' => $settings['payu']['merchant_id'] ?? '',
                    'secret_key' => !empty($settings['payu']['secret_key']) ? '••••••••' : '',
                ],
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => ['processors' => $processors],
        ]);
    }

    /**
     * Update payment processor settings
     */
    public function updatePaymentSettings(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'processor' => 'required|string|in:stripe,netopia,euplatesc,payu',
            'enabled' => 'required|boolean',
            'settings' => 'required|array',
        ]);

        $settings = $tenant->settings ?? [];
        $payments = $settings['payments'] ?? [];

        $processorSettings = $validated['settings'];
        $processorSettings['enabled'] = $validated['enabled'];

        // Don't overwrite existing secrets with masked values
        if (isset($payments[$validated['processor']])) {
            foreach ($processorSettings as $key => $value) {
                if ($value === '••••••••' || $value === '[CONFIGURED]') {
                    $processorSettings[$key] = $payments[$validated['processor']][$key] ?? '';
                }
            }
        }

        $payments[$validated['processor']] = $processorSettings;
        $settings['payments'] = $payments;
        $tenant->settings = $settings;
        $tenant->save();

        return response()->json([
            'success' => true,
            'message' => 'Payment processor settings updated.',
        ]);
    }

    /**
     * Get site settings (name, tagline, etc.)
     */
    public function siteSettings(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $settings = $tenant->settings ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'site_name' => $settings['site_name'] ?? $tenant->name,
                'tagline' => $settings['tagline'] ?? '',
                'logo_url' => $settings['logo_url'] ?? '',
                'favicon_url' => $settings['favicon_url'] ?? '',
                'contact_email' => $settings['contact_email'] ?? $tenant->email,
                'contact_phone' => $settings['contact_phone'] ?? '',
                'address' => $settings['address'] ?? '',
                'social_links' => $settings['social_links'] ?? [
                    'facebook' => '',
                    'instagram' => '',
                    'twitter' => '',
                ],
                'footer_text' => $settings['footer_text'] ?? '',
            ],
        ]);
    }

    /**
     * Update site settings
     */
    public function updateSiteSettings(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
            'tagline' => 'nullable|string|max:500',
            'logo_url' => 'nullable|url|max:500',
            'favicon_url' => 'nullable|url|max:500',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'social_links' => 'nullable|array',
            'social_links.facebook' => 'nullable|url',
            'social_links.instagram' => 'nullable|url',
            'social_links.twitter' => 'nullable|url',
            'footer_text' => 'nullable|string|max:1000',
        ]);

        $settings = $tenant->settings ?? [];
        foreach ($validated as $key => $value) {
            $settings[$key] = $value;
        }
        $tenant->settings = $settings;
        $tenant->save();

        return response()->json([
            'success' => true,
            'message' => 'Site settings updated.',
        ]);
    }

    /**
     * Get tenant's subscribed microservices
     */
    public function microservices(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Get tenant's subscribed microservices
        $subscriptions = $tenant->subscriptions()
            ->with('microservice')
            ->where('status', 'active')
            ->get();

        $microservices = $subscriptions->map(function ($sub) use ($tenant) {
            $ms = $sub->microservice;
            $config = $tenant->settings['microservices'][$ms->id] ?? [];

            return [
                'id' => $ms->id,
                'name' => $ms->name,
                'slug' => $ms->slug,
                'description' => $ms->description,
                'icon' => $ms->icon ?? 'puzzle',
                'configured' => !empty($config),
                'config_fields' => $this->getMicroserviceConfigFields($ms->slug),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => ['microservices' => $microservices],
        ]);
    }

    /**
     * Get microservice configuration
     */
    public function microserviceConfig(Request $request, int $microserviceId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Verify tenant has this microservice
        $subscription = $tenant->subscriptions()
            ->with('microservice')
            ->where('microservice_id', $microserviceId)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Microservice not found or not subscribed.',
            ], 404);
        }

        $ms = $subscription->microservice;
        $config = $tenant->settings['microservices'][$ms->id] ?? [];
        $fields = $this->getMicroserviceConfigFields($ms->slug);

        // Populate field values
        foreach ($fields as &$field) {
            $value = $config[$field['key']] ?? '';
            // Mask sensitive fields
            if ($field['type'] === 'password' && !empty($value)) {
                $value = '••••••••';
            }
            $field['value'] = $value;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'microservice' => [
                    'id' => $ms->id,
                    'name' => $ms->name,
                    'slug' => $ms->slug,
                    'description' => $ms->description,
                ],
                'fields' => $fields,
                'config' => $config,
            ],
        ]);
    }

    /**
     * Update microservice configuration
     */
    public function updateMicroserviceConfig(Request $request, int $microserviceId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        // Verify tenant has this microservice
        $subscription = $tenant->subscriptions()
            ->where('microservice_id', $microserviceId)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Microservice not found or not subscribed.',
            ], 404);
        }

        $validated = $request->validate([
            'config' => 'required|array',
        ]);

        $settings = $tenant->settings ?? [];
        $microservices = $settings['microservices'] ?? [];

        // Don't overwrite masked values
        $existingConfig = $microservices[$microserviceId] ?? [];
        $newConfig = $validated['config'];

        foreach ($newConfig as $key => $value) {
            if ($value === '••••••••' && isset($existingConfig[$key])) {
                $newConfig[$key] = $existingConfig[$key];
            }
        }

        $microservices[$microserviceId] = $newConfig;
        $settings['microservices'] = $microservices;
        $tenant->settings = $settings;
        $tenant->save();

        return response()->json([
            'success' => true,
            'message' => 'Microservice configuration updated.',
        ]);
    }

    /**
     * Get configuration fields for a microservice
     */
    protected function getMicroserviceConfigFields(string $slug): array
    {
        $fieldDefinitions = [
            'tracking-pixels' => [
                ['key' => 'facebook_pixel_id', 'label' => 'Facebook Pixel ID', 'type' => 'text', 'required' => false, 'description' => 'Your Facebook Pixel ID for conversion tracking'],
                ['key' => 'google_analytics_id', 'label' => 'Google Analytics ID', 'type' => 'text', 'required' => false, 'description' => 'GA4 Measurement ID (G-XXXXXXXXXX)'],
                ['key' => 'google_tag_manager_id', 'label' => 'Google Tag Manager ID', 'type' => 'text', 'required' => false, 'description' => 'GTM Container ID (GTM-XXXXXXX)'],
                ['key' => 'tiktok_pixel_id', 'label' => 'TikTok Pixel ID', 'type' => 'text', 'required' => false],
                ['key' => 'custom_head_scripts', 'label' => 'Custom Head Scripts', 'type' => 'textarea', 'required' => false, 'description' => 'Additional scripts to add to <head>'],
            ],
            'analytics' => [
                ['key' => 'mixpanel_token', 'label' => 'Mixpanel Token', 'type' => 'text', 'required' => false],
                ['key' => 'amplitude_api_key', 'label' => 'Amplitude API Key', 'type' => 'password', 'required' => false],
                ['key' => 'hotjar_site_id', 'label' => 'Hotjar Site ID', 'type' => 'text', 'required' => false],
            ],
            'whatsapp' => [
                ['key' => 'phone_number', 'label' => 'WhatsApp Business Number', 'type' => 'text', 'required' => true, 'description' => 'Include country code (e.g., +40712345678)'],
                ['key' => 'default_message', 'label' => 'Default Message', 'type' => 'textarea', 'required' => false, 'description' => 'Pre-filled message when customer clicks WhatsApp button'],
                ['key' => 'business_account_id', 'label' => 'Business Account ID', 'type' => 'text', 'required' => false],
                ['key' => 'api_token', 'label' => 'API Token', 'type' => 'password', 'required' => false, 'description' => 'For automated messages'],
            ],
            'affiliates' => [
                ['key' => 'commission_rate', 'label' => 'Default Commission Rate (%)', 'type' => 'number', 'required' => true],
                ['key' => 'cookie_duration', 'label' => 'Cookie Duration (days)', 'type' => 'number', 'required' => true],
                ['key' => 'min_payout', 'label' => 'Minimum Payout Amount', 'type' => 'number', 'required' => true],
                ['key' => 'payout_method', 'label' => 'Payout Method', 'type' => 'select', 'options' => ['bank_transfer', 'paypal'], 'required' => true],
            ],
            'crm' => [
                ['key' => 'hubspot_api_key', 'label' => 'HubSpot API Key', 'type' => 'password', 'required' => false],
                ['key' => 'salesforce_client_id', 'label' => 'Salesforce Client ID', 'type' => 'text', 'required' => false],
                ['key' => 'salesforce_client_secret', 'label' => 'Salesforce Client Secret', 'type' => 'password', 'required' => false],
                ['key' => 'mailchimp_api_key', 'label' => 'Mailchimp API Key', 'type' => 'password', 'required' => false],
                ['key' => 'mailchimp_list_id', 'label' => 'Mailchimp List ID', 'type' => 'text', 'required' => false],
            ],
            'mobile-wallet' => [
                ['key' => 'apple_pass_team_id', 'label' => 'Apple Team ID', 'type' => 'text', 'required' => false],
                ['key' => 'apple_pass_certificate', 'label' => 'Apple Pass Certificate', 'type' => 'textarea', 'required' => false],
                ['key' => 'google_pay_issuer_id', 'label' => 'Google Pay Issuer ID', 'type' => 'text', 'required' => false],
                ['key' => 'google_pay_service_account', 'label' => 'Google Service Account JSON', 'type' => 'textarea', 'required' => false],
            ],
            'ticket-insurance' => [
                ['key' => 'provider', 'label' => 'Insurance Provider', 'type' => 'select', 'options' => ['refundable', 'allianz', 'custom'], 'required' => true],
                ['key' => 'api_key', 'label' => 'Provider API Key', 'type' => 'password', 'required' => false],
                ['key' => 'default_rate', 'label' => 'Default Rate (%)', 'type' => 'number', 'required' => true],
            ],
            'invitations' => [
                ['key' => 'smtp_host', 'label' => 'SMTP Host', 'type' => 'text', 'required' => false],
                ['key' => 'smtp_port', 'label' => 'SMTP Port', 'type' => 'number', 'required' => false],
                ['key' => 'smtp_username', 'label' => 'SMTP Username', 'type' => 'text', 'required' => false],
                ['key' => 'smtp_password', 'label' => 'SMTP Password', 'type' => 'password', 'required' => false],
                ['key' => 'from_email', 'label' => 'From Email', 'type' => 'email', 'required' => false],
                ['key' => 'from_name', 'label' => 'From Name', 'type' => 'text', 'required' => false],
            ],
            'seating' => [
                ['key' => 'auto_release_minutes', 'label' => 'Auto-release Reserved Seats (minutes)', 'type' => 'number', 'required' => false],
                ['key' => 'allow_seat_selection', 'label' => 'Allow Seat Selection', 'type' => 'checkbox', 'required' => false],
            ],
            'door-sales' => [
                ['key' => 'enable_cash', 'label' => 'Enable Cash Payments', 'type' => 'checkbox', 'required' => false],
                ['key' => 'enable_card', 'label' => 'Enable Card Payments', 'type' => 'checkbox', 'required' => false],
                ['key' => 'receipt_printer', 'label' => 'Receipt Printer Type', 'type' => 'select', 'options' => ['thermal', 'standard', 'none'], 'required' => false],
            ],
            'group-booking' => [
                ['key' => 'min_group_size', 'label' => 'Minimum Group Size', 'type' => 'number', 'required' => true],
                ['key' => 'max_group_size', 'label' => 'Maximum Group Size', 'type' => 'number', 'required' => true],
                ['key' => 'discount_rate', 'label' => 'Group Discount (%)', 'type' => 'number', 'required' => false],
            ],
            'ticket-customizer' => [
                ['key' => 'allow_custom_text', 'label' => 'Allow Custom Text', 'type' => 'checkbox', 'required' => false],
                ['key' => 'allow_custom_image', 'label' => 'Allow Custom Image', 'type' => 'checkbox', 'required' => false],
                ['key' => 'max_text_length', 'label' => 'Max Text Length', 'type' => 'number', 'required' => false],
            ],
            'promo-codes' => [
                ['key' => 'allow_stacking', 'label' => 'Allow Code Stacking', 'type' => 'checkbox', 'required' => false],
                ['key' => 'max_uses_per_customer', 'label' => 'Max Uses Per Customer', 'type' => 'number', 'required' => false],
            ],
        ];

        return $fieldDefinitions[$slug] ?? [];
    }
}
