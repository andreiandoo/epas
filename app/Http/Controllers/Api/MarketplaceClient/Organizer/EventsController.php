<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceEvent;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceTicketType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventsController extends BaseController
{
    /**
     * List organizer's events
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $query = MarketplaceEvent::where('marketplace_organizer_id', $organizer->id)
            ->with('ticketTypes');

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('upcoming')) {
            $query->where('starts_at', '>=', now());
        }

        if ($request->has('past')) {
            $query->where('starts_at', '<', now());
        }

        // Sorting
        $sortField = $request->get('sort', 'starts_at');
        $sortDir = $request->get('order', 'desc');
        $query->orderBy($sortField, $sortDir);

        $perPage = min((int) $request->get('per_page', 20), 100);
        $events = $query->paginate($perPage);

        return $this->paginated($events, function ($event) {
            return $this->formatEvent($event);
        });
    }

    /**
     * Get single event
     */
    public function show(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = MarketplaceEvent::with(['ticketTypes', 'venue'])
            ->where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        return $this->success([
            'event' => $this->formatEventDetailed($event),
        ]);
    }

    /**
     * Create a new event
     */
    public function store(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        if (!$organizer->isActive()) {
            return $this->error('Your account must be approved before creating events', 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'short_description' => 'nullable|string|max:500',
            'starts_at' => 'required|date|after:now',
            'ends_at' => 'nullable|date|after:starts_at',
            'doors_open_at' => 'nullable|date|before:starts_at',
            'venue_name' => 'required|string|max:255',
            'venue_address' => 'nullable|string|max:500',
            'venue_city' => 'required|string|max:100',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'capacity' => 'nullable|integer|min:1',
            'max_tickets_per_order' => 'nullable|integer|min:1|max:50',
            'sales_start_at' => 'nullable|date',
            'sales_end_at' => 'nullable|date|after:sales_start_at',
            'ticket_types' => 'required|array|min:1',
            'ticket_types.*.name' => 'required|string|max:255',
            'ticket_types.*.description' => 'nullable|string|max:500',
            'ticket_types.*.price' => 'required|numeric|min:0',
            'ticket_types.*.quantity' => 'nullable|integer|min:1',
            'ticket_types.*.min_per_order' => 'nullable|integer|min:1',
            'ticket_types.*.max_per_order' => 'nullable|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $event = MarketplaceEvent::create([
                'marketplace_client_id' => $organizer->marketplace_client_id,
                'marketplace_organizer_id' => $organizer->id,
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'description' => $validated['description'] ?? null,
                'short_description' => $validated['short_description'] ?? null,
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'] ?? null,
                'doors_open_at' => $validated['doors_open_at'] ?? null,
                'venue_name' => $validated['venue_name'],
                'venue_address' => $validated['venue_address'] ?? null,
                'venue_city' => $validated['venue_city'],
                'category' => $validated['category'] ?? null,
                'tags' => $validated['tags'] ?? null,
                'capacity' => $validated['capacity'] ?? null,
                'max_tickets_per_order' => $validated['max_tickets_per_order'] ?? 10,
                'sales_start_at' => $validated['sales_start_at'] ?? null,
                'sales_end_at' => $validated['sales_end_at'] ?? null,
                'status' => 'draft',
            ]);

            // Create ticket types
            foreach ($validated['ticket_types'] as $index => $ticketTypeData) {
                MarketplaceTicketType::create([
                    'marketplace_event_id' => $event->id,
                    'name' => $ticketTypeData['name'],
                    'description' => $ticketTypeData['description'] ?? null,
                    'price' => $ticketTypeData['price'],
                    'currency' => 'RON',
                    'quantity' => $ticketTypeData['quantity'] ?? null,
                    'min_per_order' => $ticketTypeData['min_per_order'] ?? 1,
                    'max_per_order' => $ticketTypeData['max_per_order'] ?? 10,
                    'status' => 'on_sale',
                    'is_visible' => true,
                    'sort_order' => $index,
                ]);
            }

            DB::commit();

            return $this->success([
                'event' => $this->formatEventDetailed($event->load('ticketTypes')),
            ], 'Event created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create event: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update an event
     */
    public function update(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = MarketplaceEvent::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // Can only edit draft or rejected events fully
        // Published events have limited editing
        $isPublished = $event->isPublished();

        $rules = [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:10000',
            'short_description' => 'nullable|string|max:500',
            'doors_open_at' => 'nullable|date',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
        ];

        if (!$isPublished) {
            $rules = array_merge($rules, [
                'starts_at' => 'sometimes|date|after:now',
                'ends_at' => 'nullable|date|after:starts_at',
                'venue_name' => 'sometimes|string|max:255',
                'venue_address' => 'nullable|string|max:500',
                'venue_city' => 'sometimes|string|max:100',
                'capacity' => 'nullable|integer|min:1',
                'max_tickets_per_order' => 'nullable|integer|min:1|max:50',
                'sales_start_at' => 'nullable|date',
                'sales_end_at' => 'nullable|date',
            ]);
        }

        $validated = $request->validate($rules);

        $event->update($validated);

        return $this->success([
            'event' => $this->formatEventDetailed($event->fresh()->load('ticketTypes')),
        ], 'Event updated');
    }

    /**
     * Submit event for review
     */
    public function submit(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = MarketplaceEvent::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        if (!$event->isDraft()) {
            return $this->error('Only draft events can be submitted for review', 400);
        }

        // Validate event has required data
        if (!$event->ticketTypes()->exists()) {
            return $this->error('Event must have at least one ticket type', 400);
        }

        $event->submitForReview();

        return $this->success([
            'event' => $this->formatEventDetailed($event->fresh()->load('ticketTypes')),
        ], 'Event submitted for review');
    }

    /**
     * Cancel an event
     */
    public function cancel(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = MarketplaceEvent::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        if ($event->isCancelled()) {
            return $this->error('Event is already cancelled', 400);
        }

        // Check if there are completed orders
        if ($event->orders()->where('status', 'completed')->exists()) {
            return $this->error('Cannot cancel event with completed orders. Contact support for assistance.', 400);
        }

        $event->cancel();

        return $this->success(null, 'Event cancelled');
    }

    /**
     * Get event statistics
     */
    public function statistics(Request $request, int $eventId): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $event = MarketplaceEvent::where('id', $eventId)
            ->where('marketplace_organizer_id', $organizer->id)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $completedOrders = $event->orders()->where('status', 'completed');

        $ticketStats = $event->ticketTypes()->get()->map(function ($tt) use ($event) {
            $sold = $event->orders()
                ->where('status', 'completed')
                ->whereHas('tickets', function ($q) use ($tt) {
                    $q->where('marketplace_ticket_type_id', $tt->id);
                })
                ->withCount(['tickets' => function ($q) use ($tt) {
                    $q->where('marketplace_ticket_type_id', $tt->id);
                }])
                ->get()
                ->sum('tickets_count');

            return [
                'id' => $tt->id,
                'name' => $tt->name,
                'price' => $tt->price,
                'quantity' => $tt->quantity,
                'sold' => $sold,
                'available' => $tt->available_quantity,
                'revenue' => $sold * $tt->price,
            ];
        });

        return $this->success([
            'event_id' => $event->id,
            'event_name' => $event->name,
            'summary' => [
                'total_orders' => $completedOrders->count(),
                'total_tickets_sold' => $event->tickets_sold,
                'gross_revenue' => (float) $event->revenue,
                'commission_rate' => $organizer->getEffectiveCommissionRate(),
                'commission_amount' => round($event->revenue * $organizer->getEffectiveCommissionRate() / 100, 2),
                'net_revenue' => round($event->revenue * (1 - $organizer->getEffectiveCommissionRate() / 100), 2),
                'views' => $event->views,
            ],
            'ticket_types' => $ticketStats,
            'sales_by_day' => $completedOrders
                ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue')
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
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

    /**
     * Format event for list
     */
    protected function formatEvent(MarketplaceEvent $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'starts_at' => $event->starts_at->toIso8601String(),
            'venue_name' => $event->venue_display_name,
            'venue_city' => $event->venue_display_city,
            'category' => $event->category,
            'status' => $event->status,
            'image' => $event->image_url,
            'tickets_sold' => $event->tickets_sold,
            'revenue' => (float) $event->revenue,
            'views' => $event->views,
            'price_range' => $event->price_range,
        ];
    }

    /**
     * Format event with details
     */
    protected function formatEventDetailed(MarketplaceEvent $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'description' => $event->description,
            'short_description' => $event->short_description,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'doors_open_at' => $event->doors_open_at?->toIso8601String(),
            'venue_name' => $event->venue_name,
            'venue_address' => $event->venue_address,
            'venue_city' => $event->venue_city,
            'category' => $event->category,
            'tags' => $event->tags,
            'image' => $event->image_url,
            'cover_image' => $event->cover_image_url,
            'gallery' => $event->gallery,
            'status' => $event->status,
            'is_public' => $event->is_public,
            'is_featured' => $event->is_featured,
            'capacity' => $event->capacity,
            'max_tickets_per_order' => $event->max_tickets_per_order,
            'sales_start_at' => $event->sales_start_at?->toIso8601String(),
            'sales_end_at' => $event->sales_end_at?->toIso8601String(),
            'rejection_reason' => $event->rejection_reason,
            'tickets_sold' => $event->tickets_sold,
            'revenue' => (float) $event->revenue,
            'views' => $event->views,
            'ticket_types' => $event->ticketTypes->map(function ($tt) {
                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'description' => $tt->description,
                    'price' => (float) $tt->price,
                    'currency' => $tt->currency,
                    'quantity' => $tt->quantity,
                    'quantity_sold' => $tt->quantity_sold,
                    'available' => $tt->available_quantity,
                    'min_per_order' => $tt->min_per_order,
                    'max_per_order' => $tt->max_per_order,
                    'status' => $tt->status,
                    'is_visible' => $tt->is_visible,
                ];
            }),
            'created_at' => $event->created_at->toIso8601String(),
        ];
    }
}
