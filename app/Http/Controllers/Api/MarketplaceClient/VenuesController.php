<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VenuesController extends BaseController
{
    /**
     * Get venues listing with filters
     */
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $query = Venue::query()
            ->where('marketplace_client_id', $client->id);

        // Filter by city
        if ($request->filled('city')) {
            $query->where('city', $request->input('city'));
        }

        // Filter by category
        if ($request->filled('category')) {
            $categorySlug = $request->input('category');
            $query->whereHas('venueCategories', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('city', 'LIKE', "%{$search}%")
                  ->orWhere('address', 'LIKE', "%{$search}%");
            });
        }

        // Featured only
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        // Partner only
        if ($request->boolean('partner')) {
            $query->where('is_partner', true);
        }

        // Sorting
        $sort = $request->input('sort', 'name');
        $order = $request->input('order', 'asc');

        switch ($sort) {
            case 'events':
                $query->withCount(['events' => function ($q) {
                    $q->where('event_date', '>=', now()->toDateString());
                }])->orderBy('events_count', 'desc');
                break;
            case 'capacity':
                $query->orderBy('capacity', $order);
                break;
            default:
                $query->orderBy('name', $order);
        }

        $perPage = min($request->input('per_page', 12), 50);
        $paginator = $query->paginate($perPage);

        $language = $client->language ?? 'ro';

        $venues = collect($paginator->items())->map(function ($venue) use ($language) {
            return $this->formatVenue($venue, $language);
        });

        return response()->json([
            'success' => true,
            'data' => $venues,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Get featured venues
     */
    public function featured(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        $venues = Venue::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_featured', true)
            ->limit($request->input('limit', 6))
            ->get();

        $language = $client->language ?? 'ro';

        return $this->success([
            'venues' => $venues->map(fn ($v) => $this->formatVenue($v, $language)),
        ]);
    }

    /**
     * Get single venue by slug
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);

        $venue = Venue::query()
            ->where('marketplace_client_id', $client->id)
            ->where('slug', $slug)
            ->first();

        if (!$venue) {
            return $this->error('Venue not found', 404);
        }

        $language = $client->language ?? 'ro';

        // Get upcoming events at this venue
        $upcomingEvents = $venue->events()
            ->where('event_date', '>=', now()->toDateString())
            ->where('is_cancelled', false)
            ->orderBy('event_date')
            ->limit(10)
            ->get()
            ->map(function ($event) use ($language) {
                return [
                    'id' => $event->id,
                    'title' => $event->getTranslation('title', $language),
                    'slug' => $event->slug,
                    'event_date' => $event->event_date,
                    'start_time' => $event->start_time,
                    'min_price' => $event->min_price_minor ? ($event->min_price_minor / 100) : null,
                    'currency' => $event->currency ?? 'RON',
                    'image' => $event->main_image_url,
                    'is_sold_out' => $event->is_sold_out ?? false,
                ];
            });

        // Build response
        $data = [
            'id' => $venue->id,
            'name' => $venue->getTranslation('name', $language),
            'slug' => $venue->slug,
            'description' => $venue->getTranslation('description', $language),
            'city' => $venue->city,
            'address' => $venue->address,
            'postal_code' => $venue->postal_code,
            'country' => $venue->country,
            'latitude' => $venue->latitude,
            'longitude' => $venue->longitude,
            'capacity' => $venue->capacity,
            'image' => $venue->image_url,
            'cover_image' => $venue->cover_image_url,
            'gallery' => $venue->gallery ?? [],
            'schedule' => $venue->schedule,
            'amenities' => $venue->amenities ?? [],
            'contact' => [
                'phone' => $venue->phone,
                'email' => $venue->email,
                'website' => $venue->website,
            ],
            'categories' => $venue->venueCategories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->getTranslation('name', $language),
                'slug' => $cat->slug,
            ]),
            'upcoming_events' => $upcomingEvents,
            'events_count' => $venue->events()->where('event_date', '>=', now()->toDateString())->count(),
        ];

        return $this->success($data);
    }

    /**
     * Format venue for list display
     */
    protected function formatVenue(Venue $venue, string $language): array
    {
        return [
            'id' => $venue->id,
            'name' => $venue->getTranslation('name', $language),
            'slug' => $venue->slug,
            'city' => $venue->city,
            'address' => $venue->address,
            'capacity' => $venue->capacity,
            'image' => $venue->image_url,
            'events_count' => $venue->events()->where('event_date', '>=', now()->toDateString())->count(),
            'is_featured' => $venue->is_featured ?? false,
            'is_partner' => $venue->is_partner ?? false,
            'categories' => $venue->venueCategories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->getTranslation('name', $language),
                'slug' => $cat->slug,
            ]),
        ];
    }
}
