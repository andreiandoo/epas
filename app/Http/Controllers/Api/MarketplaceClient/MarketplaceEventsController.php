<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\MarketplaceEvent;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceTicketType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public API for browsing marketplace events (organizer-created events)
 */
class MarketplaceEventsController extends BaseController
{
    /**
     * List all published marketplace events
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = MarketplaceEvent::where('marketplace_client_id', $client->id)
            ->where('status', 'published')
            ->where('is_public', true)
            ->with([
                'organizer:id,name,slug,logo,verified_at',
                'ticketTypes' => function ($q) {
                    $q->where('is_visible', true)
                        ->where('status', 'on_sale')
                        ->select(['id', 'marketplace_event_id', 'name', 'price', 'quantity', 'quantity_sold', 'quantity_reserved']);
                },
            ]);

        // Filter upcoming only by default
        if (!$request->has('include_past')) {
            $query->where('starts_at', '>=', now());
        }

        // Filters
        if ($request->has('organizer_id')) {
            $query->where('marketplace_organizer_id', $request->organizer_id);
        }

        if ($request->has('organizer_slug')) {
            $organizer = MarketplaceOrganizer::where('marketplace_client_id', $client->id)
                ->where('slug', $request->organizer_slug)
                ->first();
            if ($organizer) {
                $query->where('marketplace_organizer_id', $organizer->id);
            }
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('city')) {
            $query->where('venue_city', 'like', '%' . $request->city . '%');
        }

        if ($request->has('from_date')) {
            $query->where('starts_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('starts_at', '<=', $request->to_date);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('venue_name', 'like', "%{$search}%")
                    ->orWhere('venue_city', 'like', "%{$search}%")
                    ->orWhereHas('organizer', function ($oq) use ($search) {
                        $oq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Price range
        if ($request->has('min_price') || $request->has('max_price')) {
            $query->whereHas('ticketTypes', function ($q) use ($request) {
                $q->where('is_visible', true)->where('status', 'on_sale');
                if ($request->has('min_price')) {
                    $q->where('price', '>=', $request->min_price);
                }
                if ($request->has('max_price')) {
                    $q->where('price', '<=', $request->max_price);
                }
            });
        }

        // Featured only
        if ($request->boolean('featured_only')) {
            $query->where('is_featured', true);
        }

        // Has available tickets
        if ($request->boolean('available_only')) {
            $query->whereHas('ticketTypes', function ($q) {
                $q->where('is_visible', true)
                    ->where('status', 'on_sale')
                    ->whereRaw('(quantity IS NULL OR quantity - quantity_sold - quantity_reserved > 0)');
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'starts_at');
        $allowedSorts = ['starts_at', 'name', 'created_at', 'tickets_sold', 'views'];
        if (!in_array($sortField, $allowedSorts)) {
            $sortField = 'starts_at';
        }
        $sortDir = $request->get('order', 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortField, $sortDir);

        // Pagination
        $perPage = min((int) $request->get('per_page', 20), 100);
        $events = $query->paginate($perPage);

        return $this->paginated($events, function ($event) {
            $minPrice = $event->ticketTypes->min('price');
            $maxPrice = $event->ticketTypes->max('price');
            $totalAvailable = $event->ticketTypes->sum(function ($tt) {
                if ($tt->quantity === null) {
                    return 999;
                }
                return max(0, $tt->quantity - $tt->quantity_sold - $tt->quantity_reserved);
            });

            return [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'short_description' => $event->short_description,
                'image' => $event->image,
                'category' => $event->category,
                'tags' => $event->tags,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'venue_name' => $event->venue_name,
                'venue_city' => $event->venue_city,
                'is_featured' => $event->is_featured,
                'organizer' => $event->organizer ? [
                    'id' => $event->organizer->id,
                    'name' => $event->organizer->name,
                    'slug' => $event->organizer->slug,
                    'logo' => $event->organizer->logo,
                    'verified' => $event->organizer->verified_at !== null,
                ] : null,
                'price_from' => $minPrice,
                'price_to' => $maxPrice !== $minPrice ? $maxPrice : null,
                'has_availability' => $totalAvailable > 0,
            ];
        });
    }

    /**
     * Get featured events
     */
    public function featured(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $events = MarketplaceEvent::where('marketplace_client_id', $client->id)
            ->where('status', 'published')
            ->where('is_public', true)
            ->where('is_featured', true)
            ->where('starts_at', '>=', now())
            ->with('organizer:id,name,slug,logo')
            ->orderBy('starts_at')
            ->limit(min((int) $request->get('limit', 10), 50))
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'image' => $event->image,
                    'starts_at' => $event->starts_at->toIso8601String(),
                    'venue_name' => $event->venue_name,
                    'venue_city' => $event->venue_city,
                    'organizer' => $event->organizer?->name,
                ];
            });

        return $this->success(['events' => $events]);
    }

    /**
     * Get single event details
     */
    public function show(Request $request, $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        // Find by ID or slug
        $query = MarketplaceEvent::where('marketplace_client_id', $client->id)
            ->where('status', 'published')
            ->where('is_public', true);

        if (is_numeric($identifier)) {
            $query->where('id', $identifier);
        } else {
            $query->where('slug', $identifier);
        }

        $event = $query->with([
            'organizer:id,name,slug,logo,description,website,social_links,verified_at',
            'ticketTypes' => function ($q) {
                $q->where('is_visible', true)
                    ->orderBy('sort_order')
                    ->orderBy('price');
            },
        ])->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        // Increment views
        $event->increment('views');

        return $this->success([
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'description' => $event->description,
                'short_description' => $event->short_description,
                'image' => $event->image,
                'cover_image' => $event->cover_image,
                'gallery' => $event->gallery,
                'category' => $event->category,
                'tags' => $event->tags,
                'starts_at' => $event->starts_at->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'doors_open_at' => $event->doors_open_at?->toIso8601String(),
                'venue_name' => $event->venue_name,
                'venue_address' => $event->venue_address,
                'venue_city' => $event->venue_city,
                'capacity' => $event->capacity,
                'max_tickets_per_order' => $event->max_tickets_per_order,
                'sales_start_at' => $event->sales_start_at?->toIso8601String(),
                'sales_end_at' => $event->sales_end_at?->toIso8601String(),
                'is_featured' => $event->is_featured,
            ],
            'organizer' => $event->organizer ? [
                'id' => $event->organizer->id,
                'name' => $event->organizer->name,
                'slug' => $event->organizer->slug,
                'logo' => $event->organizer->logo,
                'description' => $event->organizer->description,
                'website' => $event->organizer->website,
                'social_links' => $event->organizer->social_links,
                'verified' => $event->organizer->verified_at !== null,
            ] : null,
            'ticket_types' => $event->ticketTypes->map(function ($tt) {
                $available = $tt->quantity === null
                    ? null
                    : max(0, $tt->quantity - $tt->quantity_sold - $tt->quantity_reserved);

                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'description' => $tt->description,
                    'price' => (float) $tt->price,
                    'currency' => $tt->currency,
                    'available' => $available,
                    'min_per_order' => $tt->min_per_order,
                    'max_per_order' => $tt->max_per_order,
                    'status' => $tt->status,
                    'sale_starts_at' => $tt->sale_starts_at?->toIso8601String(),
                    'sale_ends_at' => $tt->sale_ends_at?->toIso8601String(),
                    'is_sold_out' => $available !== null && $available <= 0,
                ];
            }),
        ]);
    }

    /**
     * Get ticket availability for an event
     */
    public function availability(Request $request, int $eventId): JsonResponse
    {
        $client = $this->requireClient($request);

        $event = MarketplaceEvent::where('marketplace_client_id', $client->id)
            ->where('id', $eventId)
            ->where('status', 'published')
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        $ticketTypes = MarketplaceTicketType::where('marketplace_event_id', $eventId)
            ->where('is_visible', true)
            ->where('status', 'on_sale')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($tt) {
                $available = $tt->quantity === null
                    ? null
                    : max(0, $tt->quantity - $tt->quantity_sold - $tt->quantity_reserved);

                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'price' => (float) $tt->price,
                    'available' => $available,
                    'status' => $available === 0 ? 'sold_out' : 'available',
                ];
            });

        return $this->success([
            'event_id' => $eventId,
            'ticket_types' => $ticketTypes,
            'last_updated' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get available categories
     */
    public function categories(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $categories = MarketplaceEvent::where('marketplace_client_id', $client->id)
            ->where('status', 'published')
            ->where('is_public', true)
            ->where('starts_at', '>=', now())
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as event_count')
            ->groupBy('category')
            ->orderByDesc('event_count')
            ->get()
            ->map(fn($item) => [
                'name' => $item->category,
                'slug' => \Illuminate\Support\Str::slug($item->category),
                'event_count' => $item->event_count,
            ]);

        return $this->success(['categories' => $categories]);
    }

    /**
     * Get available cities
     */
    public function cities(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $cities = MarketplaceEvent::where('marketplace_client_id', $client->id)
            ->where('status', 'published')
            ->where('is_public', true)
            ->where('starts_at', '>=', now())
            ->whereNotNull('venue_city')
            ->selectRaw('venue_city, COUNT(*) as event_count')
            ->groupBy('venue_city')
            ->orderByDesc('event_count')
            ->get()
            ->map(fn($item) => [
                'name' => $item->venue_city,
                'event_count' => $item->event_count,
            ]);

        return $this->success(['cities' => $cities]);
    }

    /**
     * Get organizer profile with their events
     */
    public function organizer(Request $request, $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = MarketplaceOrganizer::where('marketplace_client_id', $client->id)
            ->where('status', 'active');

        if (is_numeric($identifier)) {
            $query->where('id', $identifier);
        } else {
            $query->where('slug', $identifier);
        }

        $organizer = $query->first();

        if (!$organizer) {
            return $this->error('Organizer not found', 404);
        }

        // Get upcoming events
        $upcomingEvents = $organizer->events()
            ->where('status', 'published')
            ->where('is_public', true)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(20)
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'name' => $e->name,
                'slug' => $e->slug,
                'image' => $e->image,
                'starts_at' => $e->starts_at->toIso8601String(),
                'venue_name' => $e->venue_name,
                'venue_city' => $e->venue_city,
            ]);

        // Get past events count
        $pastEventsCount = $organizer->events()
            ->where('status', 'published')
            ->where('starts_at', '<', now())
            ->count();

        return $this->success([
            'organizer' => [
                'id' => $organizer->id,
                'name' => $organizer->name,
                'slug' => $organizer->slug,
                'logo' => $organizer->logo,
                'description' => $organizer->description,
                'website' => $organizer->website,
                'social_links' => $organizer->social_links,
                'verified' => $organizer->verified_at !== null,
                'total_events' => $organizer->total_events,
            ],
            'upcoming_events' => $upcomingEvents,
            'past_events_count' => $pastEventsCount,
        ]);
    }

    /**
     * List all organizers
     */
    public function organizers(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = MarketplaceOrganizer::where('marketplace_client_id', $client->id)
            ->where('status', 'active')
            ->withCount(['events' => function ($q) {
                $q->where('status', 'published')
                    ->where('is_public', true)
                    ->where('starts_at', '>=', now());
            }]);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('verified_only')) {
            $query->whereNotNull('verified_at');
        }

        if ($request->boolean('has_events')) {
            $query->having('events_count', '>', 0);
        }

        $sortField = $request->get('sort', 'name');
        $query->orderBy($sortField === 'events' ? 'total_events' : $sortField);

        $perPage = min((int) $request->get('per_page', 20), 100);
        $organizers = $query->paginate($perPage);

        return $this->paginated($organizers, fn($org) => [
            'id' => $org->id,
            'name' => $org->name,
            'slug' => $org->slug,
            'logo' => $org->logo,
            'description' => $org->description ? \Illuminate\Support\Str::limit($org->description, 200) : null,
            'verified' => $org->verified_at !== null,
            'upcoming_events_count' => $org->events_count,
            'total_events' => $org->total_events,
        ]);
    }
}
