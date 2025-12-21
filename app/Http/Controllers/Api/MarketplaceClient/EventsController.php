<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventsController extends Controller
{
    /**
     * List events for the marketplace.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $query = Event::where('tenant_id', $tenant->id)
            ->with(['venue', 'organizer', 'eventTypes', 'ticketTypes'])
            ->upcoming()
            ->where(function ($q) {
                $q->where('is_cancelled', false)
                    ->orWhereNull('is_cancelled');
            });

        // Filter by organizer
        if ($request->filled('organizer')) {
            $query->whereHas('organizer', function ($q) use ($request) {
                $q->where('slug', $request->organizer);
            });
        }

        // Filter by category/event type
        if ($request->filled('category')) {
            $query->whereHas('eventTypes', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Filter by date range
        if ($request->filled('from')) {
            $query->where(function ($q) use ($request) {
                $q->where('event_date', '>=', $request->from)
                    ->orWhere('range_start_date', '>=', $request->from);
            });
        }

        if ($request->filled('to')) {
            $query->where(function ($q) use ($request) {
                $q->where('event_date', '<=', $request->to)
                    ->orWhere('range_end_date', '<=', $request->to);
            });
        }

        // Search by name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereRaw("JSON_EXTRACT(title, '$.ro') LIKE ?", ["%{$search}%"])
                    ->orWhereRaw("JSON_EXTRACT(title, '$.en') LIKE ?", ["%{$search}%"]);
            });
        }

        // Sorting
        $sortBy = $request->get('sort', 'date');
        $query->when($sortBy === 'date', fn ($q) => $q->orderByRaw('COALESCE(event_date, range_start_date) ASC'));
        $query->when($sortBy === 'popular', fn ($q) => $q->withCount('tickets')->orderByDesc('tickets_count'));

        // Pagination
        $perPage = min($request->get('per_page', 12), 50);
        $events = $query->paginate($perPage);

        return response()->json([
            'events' => $events->map(fn ($event) => $this->formatEvent($event)),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * Get featured events for the marketplace homepage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function featured(Request $request): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $events = Event::where('tenant_id', $tenant->id)
            ->with(['venue', 'organizer'])
            ->upcoming()
            ->where('is_featured', true)
            ->orderByRaw('COALESCE(event_date, range_start_date) ASC')
            ->limit(6)
            ->get();

        return response()->json([
            'events' => $events->map(fn ($event) => $this->formatEvent($event)),
        ]);
    }

    /**
     * Get a single event by slug.
     *
     * @param Request $request
     * @param string $slug
     * @return JsonResponse
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $event = Event::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->with(['venue', 'organizer', 'eventTypes', 'eventGenres', 'artists', 'ticketTypes'])
            ->first();

        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        return response()->json([
            'event' => $this->formatEventDetailed($event),
        ]);
    }

    /**
     * Get available ticket types for an event.
     *
     * @param Request $request
     * @param string $slug
     * @return JsonResponse
     */
    public function ticketTypes(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->resolveMarketplace($request);

        if (!$tenant) {
            return response()->json(['error' => 'Marketplace not found'], 404);
        }

        $event = Event::where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->with(['ticketTypes' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('sort_order')
                    ->withCount('tickets');
            }])
            ->first();

        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        $ticketTypes = $event->ticketTypes->map(fn ($type) => [
            'id' => $type->id,
            'name' => $type->name,
            'description' => $type->description,
            'price' => $type->price_cents / 100,
            'price_formatted' => number_format($type->price_cents / 100, 2) . ' ' . ($tenant->currency ?? 'RON'),
            'quantity_available' => max(0, $type->quantity - $type->tickets_count),
            'is_sold_out' => $type->tickets_count >= $type->quantity,
            'sale_start' => $type->sale_start?->toISOString(),
            'sale_end' => $type->sale_end?->toISOString(),
            'is_on_sale' => $this->isOnSale($type),
            'min_per_order' => $type->min_per_order ?? 1,
            'max_per_order' => $type->max_per_order ?? 10,
        ]);

        return response()->json([
            'ticket_types' => $ticketTypes,
            'event' => [
                'id' => $event->id,
                'name' => $event->getTranslation('title', 'ro'),
                'is_sold_out' => $event->is_sold_out,
                'is_cancelled' => $event->is_cancelled,
            ],
        ]);
    }

    /**
     * Format event for list view.
     */
    protected function formatEvent(Event $event): array
    {
        return [
            'id' => $event->id,
            'slug' => $event->slug,
            'title' => $event->getTranslation('title', 'ro'),
            'title_en' => $event->getTranslation('title', 'en'),
            'short_description' => $event->getTranslation('short_description', 'ro'),
            'poster_url' => $event->poster_url,
            'hero_image_url' => $event->hero_image_url,
            'date' => $event->start_date?->format('Y-m-d'),
            'date_formatted' => $event->start_date?->format('M j, Y'),
            'time' => $event->start_time,
            'venue' => $event->venue ? [
                'name' => $event->venue->name,
                'city' => $event->venue->city,
            ] : null,
            'organizer' => $event->organizer ? [
                'name' => $event->organizer->name,
                'slug' => $event->organizer->slug,
                'logo' => $event->organizer->logo,
            ] : null,
            'is_sold_out' => $event->is_sold_out,
            'is_featured' => $event->is_featured,
            'starting_price' => $event->ticketTypes->min('price_cents') / 100,
        ];
    }

    /**
     * Format event for detail view.
     */
    protected function formatEventDetailed(Event $event): array
    {
        $basic = $this->formatEvent($event);

        return array_merge($basic, [
            'description' => $event->getTranslation('description', 'ro'),
            'description_en' => $event->getTranslation('description', 'en'),
            'ticket_terms' => $event->getTranslation('ticket_terms', 'ro'),
            'duration_mode' => $event->duration_mode,
            'end_date' => $event->end_date?->format('Y-m-d'),
            'door_time' => $event->door_time,
            'end_time' => $event->end_time,
            'address' => $event->address,
            'venue' => $event->venue ? [
                'id' => $event->venue->id,
                'name' => $event->venue->name,
                'address' => $event->venue->address,
                'city' => $event->venue->city,
                'country' => $event->venue->country,
                'latitude' => $event->venue->latitude,
                'longitude' => $event->venue->longitude,
            ] : null,
            'organizer' => $event->organizer ? [
                'id' => $event->organizer->id,
                'name' => $event->organizer->name,
                'slug' => $event->organizer->slug,
                'logo' => $event->organizer->logo,
                'description' => $event->organizer->description,
                'is_verified' => $event->organizer->is_verified,
            ] : null,
            'categories' => $event->eventTypes->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'slug' => $type->slug,
            ]),
            'genres' => $event->eventGenres->map(fn ($genre) => [
                'id' => $genre->id,
                'name' => $genre->name,
            ]),
            'artists' => $event->artists->map(fn ($artist) => [
                'id' => $artist->id,
                'name' => $artist->name,
                'image' => $artist->image_url,
            ]),
            'links' => [
                'website' => $event->event_website_url,
                'facebook' => $event->facebook_url,
            ],
            'is_cancelled' => $event->is_cancelled,
            'cancel_reason' => $event->cancel_reason,
            'is_postponed' => $event->is_postponed,
            'postponed_reason' => $event->postponed_reason,
        ]);
    }

    /**
     * Check if a ticket type is currently on sale.
     */
    protected function isOnSale($ticketType): bool
    {
        $now = now();

        if ($ticketType->sale_start && $ticketType->sale_start > $now) {
            return false;
        }

        if ($ticketType->sale_end && $ticketType->sale_end < $now) {
            return false;
        }

        return true;
    }

    /**
     * Resolve the marketplace tenant from the request.
     */
    protected function resolveMarketplace(Request $request): ?Tenant
    {
        $marketplaceId = $request->header('X-Marketplace-Id');
        if ($marketplaceId) {
            return Tenant::find($marketplaceId);
        }

        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];

        return Tenant::where('slug', $subdomain)
            ->orWhere('custom_domain', $host)
            ->first();
    }
}
