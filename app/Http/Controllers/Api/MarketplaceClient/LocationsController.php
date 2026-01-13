<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Event;
use App\Models\MarketplaceCity;
use App\Models\MarketplaceCounty;
use App\Models\MarketplaceRegion;
use App\Models\MarketplaceEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Public API for marketplace locations (regions, counties, cities)
 */
class LocationsController extends BaseController
{
    /**
     * Get location statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        // Count active cities (with events)
        $activeCities = MarketplaceEvent::where('marketplace_client_id', $client->id)
            ->where('status', 'published')
            ->where('is_public', true)
            ->where('starts_at', '>=', now())
            ->whereNotNull('venue_city')
            ->distinct('venue_city')
            ->count('venue_city');

        // Total live events
        $liveEvents = MarketplaceEvent::where('marketplace_client_id', $client->id)
            ->where('status', 'published')
            ->where('is_public', true)
            ->where('starts_at', '>=', now())
            ->count();

        // Count unique venues
        $venues = MarketplaceEvent::where('marketplace_client_id', $client->id)
            ->where('status', 'published')
            ->where('is_public', true)
            ->whereNotNull('venue_name')
            ->distinct('venue_name')
            ->count('venue_name');

        // Total regions
        $regionsCount = MarketplaceRegion::where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->count();

        return $this->success([
            'active_cities' => $activeCities,
            'live_events' => $liveEvents,
            'venues' => $venues,
            'regions' => $regionsCount,
        ]);
    }

    /**
     * Get featured cities (is_featured = true)
     */
    public function featuredCities(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $lang = $client->language ?? $client->locale ?? 'ro';

        // Get event counts per city (by marketplace_city_id) using Event model
        $eventCounts = Event::where('marketplace_client_id', $client->id)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->where('event_date', '>=', now()->toDateString())
            ->whereNotNull('marketplace_city_id')
            ->selectRaw('marketplace_city_id, COUNT(*) as event_count')
            ->groupBy('marketplace_city_id')
            ->pluck('event_count', 'marketplace_city_id');

        // Get featured cities
        $cities = MarketplaceCity::where('marketplace_client_id', $client->id)
            ->where('is_featured', true)
            ->where('is_visible', true)
            ->with(['region:id,name', 'county:id,name,code'])
            ->orderBy('sort_order')
            ->get()
            ->map(function ($city) use ($eventCounts, $lang) {
                return [
                    'id' => $city->id,
                    'name' => $city->name[$lang] ?? $city->name['ro'] ?? array_values((array)$city->name)[0] ?? '',
                    'slug' => $city->slug,
                    'image' => $city->image_full_url,
                    'region' => $city->region ? ($city->region->name[$lang] ?? $city->region->name['ro'] ?? '') : null,
                    'county' => $city->county ? [
                        'name' => $city->county->name[$lang] ?? $city->county->name['ro'] ?? '',
                        'code' => $city->county->code,
                    ] : null,
                    'events_count' => $eventCounts[$city->id] ?? 0,
                    'is_capital' => $city->slug === 'bucuresti',
                ];
            });

        return $this->success(['cities' => $cities]);
    }

    /**
     * Get all cities with pagination and alphabet filtering
     */
    public function cities(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $lang = $client->language ?? $client->locale ?? 'ro';

        // Get event counts per city
        $eventCounts = MarketplaceEvent::where('marketplace_client_id', $client->id)
            ->where('status', 'published')
            ->where('is_public', true)
            ->where('starts_at', '>=', now())
            ->whereNotNull('marketplace_city_id')
            ->selectRaw('marketplace_city_id, COUNT(*) as event_count')
            ->groupBy('marketplace_city_id')
            ->pluck('event_count', 'marketplace_city_id');

        $query = MarketplaceCity::where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->with(['region:id,name', 'county:id,name,code']);

        // Filter by letter - use slug which is always ASCII
        if ($request->has('letter') && $request->letter) {
            $letter = strtoupper($request->letter);
            $query->whereRaw("UPPER(LEFT(slug, 1)) = ?", [$letter]);
        }

        // Search by slug (most reliable)
        if ($request->has('search') && $request->search) {
            $search = trim($request->search);
            $query->where('slug', 'like', "%{$search}%");
        }

        // Default sort by sort_order, then slug
        $query->orderBy('sort_order')->orderBy('slug');

        // Pagination
        $perPage = min((int) $request->get('per_page', 8), 50);
        $cities = $query->paginate($perPage);

        // Transform results
        $transformedData = $cities->getCollection()->map(function ($city) use ($eventCounts, $lang) {
            return [
                'id' => $city->id,
                'name' => $city->name[$lang] ?? $city->name['ro'] ?? array_values((array)$city->name)[0] ?? $city->slug,
                'slug' => $city->slug,
                'image' => $city->image_full_url,
                'region' => $city->region ? ($city->region->name[$lang] ?? $city->region->name['ro'] ?? '') : null,
                'county' => $city->county ? [
                    'name' => $city->county->name[$lang] ?? $city->county->name['ro'] ?? '',
                    'code' => $city->county->code ?? '',
                ] : null,
                'events_count' => $eventCounts[$city->id] ?? 0,
            ];
        });

        // Sort by events if requested (do it in PHP to avoid complex SQL)
        $sortBy = $request->get('sort', 'events');
        if ($sortBy === 'events') {
            $transformedData = $transformedData->sortByDesc('events_count')->values();
        }

        // Return in paginated format (data at root, meta separate)
        return response()->json([
            'success' => true,
            'data' => $transformedData->toArray(),
            'meta' => [
                'current_page' => $cities->currentPage(),
                'last_page' => $cities->lastPage(),
                'per_page' => $cities->perPage(),
                'total' => $cities->total(),
            ],
        ]);
    }

    /**
     * Get alphabet letters that have cities
     */
    public function alphabet(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);

        // Use slug for alphabet (always ASCII, more reliable)
        $letters = MarketplaceCity::where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->selectRaw("UPPER(LEFT(slug, 1)) as letter")
            ->distinct()
            ->pluck('letter')
            ->filter()
            ->sort()
            ->values();

        return $this->success(['letters' => $letters]);
    }

    /**
     * Get all regions with top cities
     */
    public function regions(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $lang = $client->language ?? $client->locale ?? 'ro';

        // Get event counts per city
        $eventCounts = MarketplaceEvent::where('marketplace_client_id', $client->id)
            ->where('status', 'published')
            ->where('is_public', true)
            ->where('starts_at', '>=', now())
            ->whereNotNull('marketplace_city_id')
            ->selectRaw('marketplace_city_id, COUNT(*) as event_count')
            ->groupBy('marketplace_city_id')
            ->pluck('event_count', 'marketplace_city_id');

        // Get regions with cities
        $regions = MarketplaceRegion::where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->with(['cities' => function ($q) {
                $q->where('is_visible', true)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get()
            ->map(function ($region) use ($eventCounts, $lang) {
                $regionCities = $region->cities;

                // Calculate total events in region
                $totalEvents = $regionCities->sum(function ($city) use ($eventCounts) {
                    return $eventCounts[$city->id] ?? 0;
                });

                // Sort cities by event count and take top 5
                $topCities = $regionCities
                    ->map(function ($city) use ($eventCounts, $lang) {
                        return [
                            'id' => $city->id,
                            'name' => $city->name[$lang] ?? $city->name['ro'] ?? array_values((array)$city->name)[0] ?? '',
                            'slug' => $city->slug,
                            'events_count' => $eventCounts[$city->id] ?? 0,
                        ];
                    })
                    ->sortByDesc('events_count')
                    ->take(5)
                    ->values();

                return [
                    'id' => $region->id,
                    'name' => $region->name[$lang] ?? $region->name['ro'] ?? array_values((array)$region->name)[0] ?? '',
                    'slug' => $region->slug,
                    'description' => isset($region->description[$lang]) ? $region->description[$lang] : ($region->description['ro'] ?? null),
                    'image' => $region->image_url,
                    'cities_count' => $regionCities->count(),
                    'events_count' => $totalEvents,
                    'top_cities' => $topCities,
                ];
            });

        return $this->success(['regions' => $regions]);
    }

    /**
     * Get single city by slug
     */
    public function city(Request $request, $identifier): JsonResponse
    {
        $client = $this->requireClient($request);
        $lang = $client->language ?? $client->locale ?? 'ro';

        $query = MarketplaceCity::where('marketplace_client_id', $client->id)
            ->where('is_visible', true);

        if (is_numeric($identifier)) {
            $query->where('id', $identifier);
        } else {
            $query->where('slug', $identifier);
        }

        $city = $query->with(['region:id,name,slug', 'county:id,name,code'])->first();

        if (!$city) {
            return $this->error('City not found', 404);
        }

        // Get event count for this city
        $eventCount = MarketplaceEvent::where('marketplace_client_id', $client->id)
            ->where('marketplace_city_id', $city->id)
            ->where('status', 'published')
            ->where('is_public', true)
            ->where('starts_at', '>=', now())
            ->count();

        return $this->success([
            'city' => [
                'id' => $city->id,
                'name' => $city->name[$lang] ?? $city->name['ro'] ?? array_values((array)$city->name)[0] ?? '',
                'slug' => $city->slug,
                'description' => isset($city->description[$lang]) ? $city->description[$lang] : ($city->description['ro'] ?? null),
                'image' => $city->image_full_url,
                'cover_image' => $city->cover_image_full_url,
                'region' => $city->region ? [
                    'name' => $city->region->name[$lang] ?? $city->region->name['ro'] ?? '',
                    'slug' => $city->region->slug,
                ] : null,
                'county' => $city->county ? [
                    'name' => $city->county->name[$lang] ?? $city->county->name['ro'] ?? '',
                    'code' => $city->county->code,
                ] : null,
                'events_count' => $eventCount,
                'population' => $city->population,
                'latitude' => $city->latitude,
                'longitude' => $city->longitude,
                'is_capital' => $city->is_capital,
            ],
        ]);
    }

    /**
     * Get single region with all cities
     */
    public function region(Request $request, $identifier): JsonResponse
    {
        $client = $this->requireClient($request);
        $lang = $client->language ?? $client->locale ?? 'ro';

        $query = MarketplaceRegion::where('marketplace_client_id', $client->id)
            ->where('is_visible', true);

        if (is_numeric($identifier)) {
            $query->where('id', $identifier);
        } else {
            $query->where('slug', $identifier);
        }

        $region = $query->with(['cities' => function ($q) {
            $q->where('is_visible', true)->orderBy('sort_order');
        }])->first();

        if (!$region) {
            return $this->error('Region not found', 404);
        }

        // Get event counts per city
        $eventCounts = MarketplaceEvent::where('marketplace_client_id', $client->id)
            ->where('status', 'published')
            ->where('is_public', true)
            ->where('starts_at', '>=', now())
            ->whereNotNull('marketplace_city_id')
            ->selectRaw('marketplace_city_id, COUNT(*) as event_count')
            ->groupBy('marketplace_city_id')
            ->pluck('event_count', 'marketplace_city_id');

        $cities = $region->cities->map(function ($city) use ($eventCounts, $lang) {
            return [
                'id' => $city->id,
                'name' => $city->name[$lang] ?? $city->name['ro'] ?? array_values((array)$city->name)[0] ?? '',
                'slug' => $city->slug,
                'image' => $city->image_full_url,
                'events_count' => $eventCounts[$city->id] ?? 0,
            ];
        })->sortByDesc('events_count')->values();

        return $this->success([
            'region' => [
                'id' => $region->id,
                'name' => $region->name[$lang] ?? $region->name['ro'] ?? '',
                'slug' => $region->slug,
                'description' => isset($region->description[$lang]) ? $region->description[$lang] : ($region->description['ro'] ?? null),
                'image' => $region->image_url,
            ],
            'cities' => $cities,
        ]);
    }
}
