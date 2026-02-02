<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Admin;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceAdmin;
use App\Models\MarketplaceEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EventsController extends BaseController
{
    /**
     * List all events with filters
     */
    public function index(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('events.view')) {
            return $this->error('Unauthorized', 403);
        }

        $clientId = $admin->marketplace_client_id;

        $query = MarketplaceEvent::where('marketplace_client_id', $clientId)
            ->with(['organizer:id,name,email']);

        // Filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('organizer_id')) {
            $query->where('marketplace_organizer_id', $request->organizer_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('venue_name', 'like', "%{$search}%")
                    ->orWhere('venue_city', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('upcoming_only')) {
            $query->where('starts_at', '>=', now());
        }

        if ($request->boolean('pending_only')) {
            $query->where('status', 'pending_review');
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('order', 'desc');
        $query->orderBy($sortField, $sortDir);

        $perPage = min((int) $request->get('per_page', 20), 100);
        $events = $query->paginate($perPage);

        return $this->paginated($events, function ($event) {
            return [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'status' => $event->status,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'venue_name' => $event->venue_name,
                'venue_city' => $event->venue_city,
                'category' => $event->category,
                'is_featured' => $event->is_featured,
                'tickets_sold' => $event->tickets_sold,
                'revenue' => (float) $event->revenue,
                'organizer' => $event->organizer ? [
                    'id' => $event->organizer->id,
                    'name' => $event->organizer->name,
                    'email' => $event->organizer->email,
                ] : null,
                'submitted_at' => $event->submitted_at?->toIso8601String(),
                'approved_at' => $event->approved_at?->toIso8601String(),
                'created_at' => $event->created_at->toIso8601String(),
            ];
        });
    }

    /**
     * Get events pending review
     */
    public function pendingReview(Request $request): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('events.view')) {
            return $this->error('Unauthorized', 403);
        }

        $clientId = $admin->marketplace_client_id;

        $events = MarketplaceEvent::where('marketplace_client_id', $clientId)
            ->where('status', 'pending_review')
            ->with(['organizer:id,name,email,status'])
            ->orderBy('submitted_at')
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'description' => $event->short_description,
                    'starts_at' => $event->starts_at->toIso8601String(),
                    'venue_name' => $event->venue_name,
                    'venue_city' => $event->venue_city,
                    'category' => $event->category,
                    'image' => $event->image_url,
                    'organizer' => $event->organizer ? [
                        'id' => $event->organizer->id,
                        'name' => $event->organizer->name,
                        'email' => $event->organizer->email,
                        'status' => $event->organizer->status,
                    ] : null,
                    'submitted_at' => $event->submitted_at?->toIso8601String(),
                ];
            });

        return $this->success([
            'events' => $events,
            'count' => $events->count(),
        ]);
    }

    /**
     * Get single event details
     */
    public function show(Request $request, int $eventId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('events.view')) {
            return $this->error('Unauthorized', 403);
        }

        $event = MarketplaceEvent::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $eventId)
            ->with(['organizer', 'ticketTypes'])
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        return $this->success([
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'description' => $event->description,
                'short_description' => $event->short_description,
                'status' => $event->status,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'doors_open_at' => $event->doors_open_at?->toIso8601String(),
                'venue_name' => $event->venue_name,
                'venue_address' => $event->venue_address,
                'venue_city' => $event->venue_city,
                'category' => $event->category,
                'tags' => $event->tags,
                'image' => $event->image,
                'cover_image' => $event->cover_image,
                'gallery' => $event->gallery,
                'is_public' => $event->is_public,
                'is_featured' => $event->is_featured,
                'capacity' => $event->capacity,
                'max_tickets_per_order' => $event->max_tickets_per_order,
                'sales_start_at' => $event->sales_start_at?->toIso8601String(),
                'sales_end_at' => $event->sales_end_at?->toIso8601String(),
                'tickets_sold' => $event->tickets_sold,
                'revenue' => (float) $event->revenue,
                'views' => $event->views,
                'rejection_reason' => $event->rejection_reason,
                'submitted_at' => $event->submitted_at?->toIso8601String(),
                'approved_at' => $event->approved_at?->toIso8601String(),
                'created_at' => $event->created_at->toIso8601String(),
            ],
            'organizer' => $event->organizer ? [
                'id' => $event->organizer->id,
                'name' => $event->organizer->name,
                'email' => $event->organizer->email,
                'phone' => $event->organizer->phone,
                'status' => $event->organizer->status,
                'verified' => $event->organizer->verified_at !== null,
            ] : null,
            'ticket_types' => $event->ticketTypes->map(fn($tt) => [
                'id' => $tt->id,
                'name' => $tt->name,
                'description' => $tt->description,
                'price' => (float) $tt->price,
                'quantity' => $tt->quantity,
                'quantity_sold' => $tt->quantity_sold,
                'status' => $tt->status,
            ]),
        ]);
    }

    /**
     * Approve an event
     */
    public function approve(Request $request, int $eventId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('events.approve')) {
            return $this->error('Unauthorized', 403);
        }

        $event = MarketplaceEvent::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $eventId)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        if ($event->status !== 'pending_review') {
            return $this->error('Event is not pending review', 400);
        }

        $event->update([
            'status' => 'published',
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'rejection_reason' => null,
        ]);

        Log::channel('marketplace')->info('Event approved', [
            'event_id' => $event->id,
            'admin_id' => $admin->id,
            'client_id' => $admin->marketplace_client_id,
        ]);

        // TODO: Send notification to organizer

        return $this->success([
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'status' => $event->status,
                'approved_at' => $event->approved_at->toIso8601String(),
            ],
        ], 'Event approved successfully');
    }

    /**
     * Reject an event
     */
    public function reject(Request $request, int $eventId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('events.approve')) {
            return $this->error('Unauthorized', 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $event = MarketplaceEvent::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $eventId)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        if ($event->status !== 'pending_review') {
            return $this->error('Event is not pending review', 400);
        }

        $event->update([
            'status' => 'draft',
            'rejection_reason' => $validated['reason'],
            'approved_at' => null,
            'approved_by' => null,
        ]);

        Log::channel('marketplace')->info('Event rejected', [
            'event_id' => $event->id,
            'admin_id' => $admin->id,
            'reason' => $validated['reason'],
        ]);

        // TODO: Send notification to organizer

        return $this->success([
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'status' => $event->status,
            ],
        ], 'Event rejected');
    }

    /**
     * Toggle featured status
     */
    public function toggleFeatured(Request $request, int $eventId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('events.approve')) {
            return $this->error('Unauthorized', 403);
        }

        $event = MarketplaceEvent::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $eventId)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $event->update(['is_featured' => !$event->is_featured]);

        return $this->success([
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'is_featured' => $event->is_featured,
            ],
        ], $event->is_featured ? 'Event marked as featured' : 'Event removed from featured');
    }

    /**
     * Unpublish/suspend an event
     */
    public function suspend(Request $request, int $eventId): JsonResponse
    {
        $admin = $this->requireAdmin($request);

        if (!$admin->hasPermission('events.approve')) {
            return $this->error('Unauthorized', 403);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $event = MarketplaceEvent::where('marketplace_client_id', $admin->marketplace_client_id)
            ->where('id', $eventId)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        if ($event->status !== 'published') {
            return $this->error('Event is not published', 400);
        }

        $event->update([
            'status' => 'suspended',
            'rejection_reason' => $validated['reason'],
        ]);

        Log::channel('marketplace')->warning('Event suspended', [
            'event_id' => $event->id,
            'admin_id' => $admin->id,
            'reason' => $validated['reason'],
        ]);

        return $this->success(null, 'Event suspended');
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
