<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EventsController extends BaseController
{
    /**
     * Get all available events from allowed tenants
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Event::query()
            ->with(['venue:id,name,city,state,country', 'ticketTypes' => function ($q) {
                $q->where('is_visible', true)
                    ->where('status', 'on_sale')
                    ->select(['id', 'event_id', 'name', 'price', 'available_quantity', 'max_per_order']);
            }])
            ->where('status', 'published')
            ->where('is_public', true)
            ->where('starts_at', '>=', now());

        // Filter by allowed tenants
        $allowedTenants = $client->allowed_tenants;
        if (!is_null($allowedTenants)) {
            $query->whereIn('tenant_id', $allowedTenants);
        }

        // Additional filters
        if ($request->has('tenant_id')) {
            $tenantId = (int) $request->tenant_id;
            if (!$client->canSellForTenant($tenantId)) {
                return $this->error('Not authorized to sell tickets for this tenant', 403);
            }
            $query->where('tenant_id', $tenantId);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('city')) {
            $query->whereHas('venue', function ($q) use ($request) {
                $q->where('city', 'like', '%' . $request->city . '%');
            });
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
                    ->orWhereHas('venue', function ($vq) use ($search) {
                        $vq->where('name', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%");
                    })
                    ->orWhereHas('artists', function ($aq) use ($search) {
                        $aq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Price range filter
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

        // Has available tickets
        if ($request->boolean('available_only', false)) {
            $query->whereHas('ticketTypes', function ($q) {
                $q->where('is_visible', true)
                    ->where('status', 'on_sale')
                    ->where('available_quantity', '>', 0);
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'starts_at');
        $sortDir = $request->get('order', 'asc');
        $query->orderBy($sortField, $sortDir);

        // Pagination
        $perPage = min((int) $request->get('per_page', 20), 100);
        $events = $query->paginate($perPage);

        return $this->paginated($events);
    }

    /**
     * Get single event details (supports both ID and slug)
     */
    public function show(Request $request, string|int $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Event::with([
            'venue',
            'ticketTypes' => function ($q) {
                $q->where('is_visible', true)
                    ->where('status', 'on_sale')
                    ->orderBy('sort_order');
            },
            'artists',
            'images',
        ]);

        // Support both ID and slug lookup
        if (is_numeric($identifier)) {
            $query->where('id', (int) $identifier);
        } else {
            $query->where('slug', $identifier);
        }

        $event = $query->where('status', 'published')
            ->where('is_public', true)
            ->first();

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        if (!$client->canSellForTenant($event->tenant_id)) {
            return $this->error('Not authorized to sell tickets for this event', 403);
        }

        $commission = $client->getCommissionForTenant($event->tenant_id);

        return $this->success([
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'slug' => $event->slug,
                'description' => $event->description,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'doors_open_at' => $event->doors_open_at,
                'category' => $event->category,
                'image_url' => $event->image_url,
                'cover_image_url' => $event->cover_image_url,
                'tenant_id' => $event->tenant_id,
            ],
            'venue' => $event->venue ? [
                'id' => $event->venue->id,
                'name' => $event->venue->name,
                'address' => $event->venue->address,
                'city' => $event->venue->city,
                'state' => $event->venue->state,
                'country' => $event->venue->country,
                'latitude' => $event->venue->latitude,
                'longitude' => $event->venue->longitude,
            ] : null,
            'ticket_types' => $event->ticketTypes->map(function ($tt) {
                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'description' => $tt->description,
                    'price' => $tt->price,
                    'price_formatted' => number_format($tt->price, 2) . ' RON',
                    'available_quantity' => $tt->available_quantity,
                    'max_per_order' => $tt->max_per_order,
                    'min_per_order' => $tt->min_per_order ?? 1,
                    'sale_starts_at' => $tt->sale_starts_at,
                    'sale_ends_at' => $tt->sale_ends_at,
                ];
            }),
            'artists' => $event->artists->map(function ($artist) {
                return [
                    'id' => $artist->id,
                    'name' => $artist->name,
                    'image_url' => $artist->image_url,
                ];
            }),
            'images' => $event->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'url' => $image->url,
                    'type' => $image->type,
                ];
            }),
            'commission_rate' => $commission,
        ]);
    }

    /**
     * Get featured events
     */
    public function featured(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Event::query()
            ->with(['venue:id,name,city', 'ticketTypes' => function ($q) {
                $q->where('is_visible', true)
                    ->where('status', 'on_sale')
                    ->select(['id', 'event_id', 'name', 'price', 'available_quantity']);
            }])
            ->where('status', 'published')
            ->where('is_public', true)
            ->where('is_featured', true)
            ->where('starts_at', '>=', now());

        // Filter by allowed tenants
        $allowedTenants = $client->allowed_tenants;
        if (!is_null($allowedTenants)) {
            $query->whereIn('tenant_id', $allowedTenants);
        }

        $limit = min((int) $request->get('limit', 10), 50);
        $events = $query->orderBy('starts_at')->limit($limit)->get();

        return $this->success([
            'events' => $events->map(function ($event) {
                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'image_url' => $event->image_url,
                    'starts_at' => $event->starts_at,
                    'venue' => $event->venue?->name,
                    'city' => $event->venue?->city,
                    'price_from' => $event->ticketTypes->min('price'),
                    'has_availability' => $event->ticketTypes->sum('available_quantity') > 0,
                ];
            }),
        ]);
    }

    /**
     * Get available categories
     */
    public function categories(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Event::query()
            ->where('status', 'published')
            ->where('is_public', true)
            ->where('starts_at', '>=', now())
            ->whereNotNull('category');

        // Filter by allowed tenants
        $allowedTenants = $client->allowed_tenants;
        if (!is_null($allowedTenants)) {
            $query->whereIn('tenant_id', $allowedTenants);
        }

        $categories = $query->selectRaw('category, COUNT(*) as event_count')
            ->groupBy('category')
            ->orderByDesc('event_count')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->category,
                    'slug' => \Illuminate\Support\Str::slug($item->category),
                    'event_count' => $item->event_count,
                ];
            });

        return $this->success([
            'categories' => $categories,
        ]);
    }

    /**
     * Get available cities
     */
    public function cities(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Event::query()
            ->join('venues', 'events.venue_id', '=', 'venues.id')
            ->where('events.status', 'published')
            ->where('events.is_public', true)
            ->where('events.starts_at', '>=', now())
            ->whereNotNull('venues.city');

        // Filter by allowed tenants
        $allowedTenants = $client->allowed_tenants;
        if (!is_null($allowedTenants)) {
            $query->whereIn('events.tenant_id', $allowedTenants);
        }

        $cities = $query->selectRaw('venues.city, venues.country, COUNT(*) as event_count')
            ->groupBy('venues.city', 'venues.country')
            ->orderByDesc('event_count')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->city,
                    'country' => $item->country,
                    'event_count' => $item->event_count,
                ];
            });

        return $this->success([
            'cities' => $cities,
        ]);
    }

    /**
     * Get ticket availability for an event (supports both ID and slug)
     */
    public function availability(Request $request, string|int $identifier): JsonResponse
    {
        $client = $this->requireClient($request);

        // Support both ID and slug lookup
        if (is_numeric($identifier)) {
            $event = Event::find((int) $identifier);
        } else {
            $event = Event::where('slug', $identifier)->first();
        }

        if (!$event) {
            return $this->error('Event not found', 404);
        }

        if (!$client->canSellForTenant($event->tenant_id)) {
            return $this->error('Not authorized', 403);
        }

        $ticketTypes = TicketType::where('event_id', $event->id)
            ->where('is_visible', true)
            ->where('status', 'on_sale')
            ->get()
            ->map(function ($tt) {
                return [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'price' => $tt->price,
                    'available' => $tt->available_quantity,
                    'status' => $tt->available_quantity > 0 ? 'available' : 'sold_out',
                ];
            });

        return $this->success([
            'event_id' => $event->id,
            'ticket_types' => $ticketTypes,
            'last_updated' => now()->toIso8601String(),
        ]);
    }
}
