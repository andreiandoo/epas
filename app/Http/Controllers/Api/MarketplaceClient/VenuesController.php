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
            ->with('venueCategories')
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
                $query->withCount(['marketplaceEvents' => function ($q) {
                    $q->where('status', 'published')
                      ->where('starts_at', '>=', now());
                }])->orderBy('marketplace_events_count', 'desc');
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
        $venueName = $venue->getTranslation('name', 'ro') ?: $venue->name;

        // Get upcoming marketplace events at this venue
        // Match by venue_id OR by venue_name/city for events without venue_id
        $upcomingEvents = \App\Models\MarketplaceEvent::query()
            ->where('marketplace_client_id', $venue->marketplace_client_id)
            ->where('status', 'published')
            ->where('starts_at', '>=', now())
            ->where(function ($query) use ($venue, $venueName) {
                $query->where('venue_id', $venue->id)
                    ->orWhere(function ($q) use ($venueName, $venue) {
                        $q->whereNull('venue_id')
                          ->whereRaw('LOWER(venue_name) = ?', [strtolower($venueName)])
                          ->whereRaw('LOWER(venue_city) = ?', [strtolower($venue->city)]);
                    });
            })
            ->with(['ticketTypes', 'marketplaceOrganizer:id,default_commission_mode,commission_rate', 'marketplaceEventCategory'])
            ->orderBy('starts_at')
            ->limit(10)
            ->get()
            ->map(function ($event) use ($language, $client) {
                // Calculate min price from active ticket types
                $minPrice = $event->ticketTypes
                    ->filter(fn ($tt) => $tt->status === 'active')
                    ->map(function ($tt) {
                        return ($tt->sale_price_cents ?? $tt->price_cents) / 100;
                    })->filter()->min();

                // Get image URL
                $imageUrl = $this->formatImageUrl($event->image);

                // Get commission settings (event > organizer > marketplace default)
                $organizer = $event->marketplaceOrganizer;
                $commissionMode = $event->commission_mode ?? $organizer?->default_commission_mode ?? $client?->commission_mode ?? 'included';
                $commissionRate = (float) ($event->commission_rate ?? $organizer?->commission_rate ?? $client?->commission_rate ?? 5.0);

                // Get category name
                $categoryName = $event->marketplaceEventCategory?->getTranslation('name', $language);

                return [
                    'id' => $event->id,
                    'name' => $event->name,
                    'slug' => $event->slug,
                    'starts_at' => $event->starts_at?->toIso8601String(),
                    'price_from' => $minPrice,
                    'currency' => $event->ticketTypes->first()?->currency ?? 'RON',
                    'image' => $imageUrl,
                    'is_sold_out' => $event->is_sold_out ?? false,
                    'category' => $categoryName ? ['name' => $categoryName] : null,
                    'commission_mode' => $commissionMode,
                    'commission_rate' => $commissionRate,
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
            'image' => $this->formatImageUrl($venue->image_url),
            'cover_image' => $this->formatImageUrl($venue->cover_image_url),
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
            'events_count' => $this->countVenueEvents($venue),
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
            'image' => $this->formatImageUrl($venue->image_url),
            'events_count' => $this->countVenueEvents($venue),
            'is_featured' => $venue->is_featured ?? false,
            'is_partner' => $venue->is_partner ?? false,
            'categories' => $venue->venueCategories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->getTranslation('name', $language),
                'slug' => $cat->slug,
            ]),
        ];
    }

    /**
     * Count upcoming published events for a venue
     * Counts events by venue_id OR by matching venue_name/city (for events without venue_id)
     */
    protected function countVenueEvents(Venue $venue): int
    {
        $venueName = $venue->getTranslation('name', 'ro') ?: $venue->name;

        return \App\Models\MarketplaceEvent::where('marketplace_client_id', $venue->marketplace_client_id)
            ->where('status', 'published')
            ->where('starts_at', '>=', now())
            ->where(function ($query) use ($venue, $venueName) {
                $query->where('venue_id', $venue->id)
                    ->orWhere(function ($q) use ($venueName, $venue) {
                        $q->whereNull('venue_id')
                          ->whereRaw('LOWER(venue_name) = ?', [strtolower($venueName)])
                          ->whereRaw('LOWER(venue_city) = ?', [strtolower($venue->city)]);
                    });
            })
            ->count();
    }

    /**
     * Format image URL with full domain
     */
    protected function formatImageUrl(?string $imagePath): ?string
    {
        if (!$imagePath) {
            return null;
        }

        if (str_starts_with($imagePath, 'http://') || str_starts_with($imagePath, 'https://')) {
            return $imagePath;
        }

        return rtrim(config('app.url'), '/') . '/storage/' . ltrim($imagePath, '/');
    }
}
