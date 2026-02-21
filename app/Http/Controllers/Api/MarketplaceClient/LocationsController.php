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

        // Count active cities (with events) - using Event model like getCityEventCounts
        $activeCities = Event::where('marketplace_client_id', $client->id)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->where('event_date', '>=', now()->toDateString())
            ->whereHas('venue', function ($q) {
                $q->whereNotNull('city');
            })
            ->with('venue:id,city')
            ->get()
            ->pluck('venue.city')
            ->filter()
            ->unique()
            ->count();

        // Total live events - using Event model
        $liveEvents = Event::where('marketplace_client_id', $client->id)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->where('event_date', '>=', now()->toDateString())
            ->count();

        // Count unique venues - using Event model
        $venues = Event::where('marketplace_client_id', $client->id)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->where('event_date', '>=', now()->toDateString())
            ->whereNotNull('venue_id')
            ->distinct('venue_id')
            ->count('venue_id');

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

        // Get featured cities first
        $cities = MarketplaceCity::where('marketplace_client_id', $client->id)
            ->where('is_featured', true)
            ->where('is_visible', true)
            ->with(['region:id,name', 'county:id,name,code'])
            ->orderBy('sort_order')
            ->get();

        // Get event counts with diacritics-aware matching
        $eventCounts = $this->getCityEventCounts($client->id, $cities);

        // Transform cities for response
        $result = $cities->map(function ($city) use ($eventCounts, $lang) {
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

        return $this->success(['cities' => $result]);
    }

    /**
     * Normalize string to ASCII (remove diacritics)
     */
    private function normalizeToAscii(string $str): string
    {
        // Romanian diacritics mapping
        $map = [
            'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ț' => 't',
            'Ă' => 'A', 'Â' => 'A', 'Î' => 'I', 'Ș' => 'S', 'Ț' => 'T',
            // Alternative characters sometimes used
            'ş' => 's', 'ţ' => 't', 'Ş' => 'S', 'Ţ' => 'T',
        ];
        $normalized = strtr($str, $map);
        return mb_strtolower(trim($normalized));
    }

    /**
     * Get all cities with pagination and alphabet filtering
     */
    public function cities(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $lang = $client->language ?? $client->locale ?? 'ro';

        $query = MarketplaceCity::where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->with(['region:id,name', 'county:id,name,code']);

        // Filter by country (via county relationship)
        if ($request->filled('country')) {
            $country = strtoupper(trim($request->country));
            $query->whereHas('county', fn ($q) => $q->where('country', $country));
        }

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

        // Build event counts with diacritics-aware matching
        $eventCounts = $this->getCityEventCounts($client->id, $cities->getCollection());

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
                'is_featured' => (bool) $city->is_featured,
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
     * Get event counts for cities with diacritics-aware matching
     */
    private function getCityEventCounts(int $clientId, $cities): array
    {
        // Build city name variants for matching (handles diacritics)
        $cityNameMap = [];
        $eventCounts = [];
        foreach ($cities as $city) {
            $eventCounts[$city->id] = 0;
            $names = is_array($city->name) ? $city->name : [$city->name];
            foreach ($names as $name) {
                if ($name) {
                    $normalized = mb_strtolower(trim($name));
                    $cityNameMap[$normalized] = $city->id;
                    $ascii = $this->normalizeToAscii($name);
                    if ($ascii !== $normalized) {
                        $cityNameMap[$ascii] = $city->id;
                    }
                }
            }
            $cityNameMap[$city->slug] = $city->id;
        }

        // Base query filters (consistent with EventsController listing)
        $baseFilters = function ($query) use ($clientId) {
            $query->where('marketplace_client_id', $clientId)
                ->where('status', 'published')
                ->where(function ($q) {
                    $q->where('is_public', true)->orWhereNull('is_public');
                })
                ->where(function ($q) {
                    $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                })
                ->where(function ($q) {
                    // Upcoming: event_date >= today OR starts_at >= now
                    $q->where(function ($inner) {
                        $inner->whereNotNull('event_date')->where('event_date', '>=', now()->toDateString());
                    })->orWhere(function ($inner) {
                        $inner->whereNull('event_date')->where('starts_at', '>=', now());
                    });
                });
        };

        // Count events by marketplace_city_id (direct link)
        $directQuery = Event::query();
        $baseFilters($directQuery);
        $directCounts = $directQuery
            ->whereNotNull('marketplace_city_id')
            ->whereIn('marketplace_city_id', $cities->pluck('id'))
            ->selectRaw('marketplace_city_id, COUNT(*) as event_count')
            ->groupBy('marketplace_city_id')
            ->pluck('event_count', 'marketplace_city_id');

        foreach ($directCounts as $cityId => $count) {
            $eventCounts[$cityId] = ($eventCounts[$cityId] ?? 0) + $count;
        }

        // Also count events by venue city name (for events without marketplace_city_id)
        $venueCityQuery = Event::query();
        $baseFilters($venueCityQuery);
        $eventsByVenueCity = $venueCityQuery
            ->whereNull('marketplace_city_id')
            ->whereHas('venue', function ($q) {
                $q->whereNotNull('city');
            })
            ->with('venue:id,city')
            ->get();

        foreach ($eventsByVenueCity as $event) {
            if ($event->venue && $event->venue->city) {
                $venueCityNormalized = mb_strtolower(trim($event->venue->city));
                $venueCityAscii = $this->normalizeToAscii($event->venue->city);

                $matchedCityId = $cityNameMap[$venueCityNormalized] ?? $cityNameMap[$venueCityAscii] ?? null;
                if ($matchedCityId) {
                    $eventCounts[$matchedCityId] = ($eventCounts[$matchedCityId] ?? 0) + 1;
                }
            }
        }

        return $eventCounts;
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

        // Get regions with cities
        $regionQuery = MarketplaceRegion::where('marketplace_client_id', $client->id)
            ->where('is_visible', true);

        // Filter by country
        if ($request->filled('country')) {
            $regionQuery->where('country', strtoupper(trim($request->country)));
        }

        $regions = $regionQuery->with(['cities' => function ($q) {
                $q->where('is_visible', true)->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        // Collect all cities from all regions
        $allCities = $regions->flatMap(fn($r) => $r->cities);

        // Get event counts with diacritics-aware matching
        $eventCounts = $this->getCityEventCounts($client->id, $allCities);

        $result = $regions->map(function ($region) use ($eventCounts, $lang) {
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

        return $this->success(['regions' => $result]);
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

        // Get event count for this city - count both by marketplace_city_id AND venue city name
        $eventCount = 0;

        // Count events with direct marketplace_city_id link
        $eventCount += Event::where('marketplace_client_id', $client->id)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->where('event_date', '>=', now()->toDateString())
            ->where('marketplace_city_id', $city->id)
            ->count();

        // Build city name variants for matching
        $cityNames = [];
        $names = is_array($city->name) ? $city->name : [$city->name];
        foreach ($names as $name) {
            if ($name) {
                $cityNames[] = mb_strtolower(trim($name));
                $cityNames[] = $this->normalizeToAscii($name);
            }
        }
        $cityNames[] = $city->slug;
        $cityNames = array_unique($cityNames);

        // Count events by venue city name (for events without marketplace_city_id)
        $venueEvents = Event::where('marketplace_client_id', $client->id)
            ->where(function ($q) {
                $q->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            })
            ->where('event_date', '>=', now()->toDateString())
            ->whereNull('marketplace_city_id')
            ->whereHas('venue', function ($q) {
                $q->whereNotNull('city');
            })
            ->with('venue:id,city')
            ->get();

        foreach ($venueEvents as $event) {
            if ($event->venue && $event->venue->city) {
                $venueCityNormalized = mb_strtolower(trim($event->venue->city));
                $venueCityAscii = $this->normalizeToAscii($event->venue->city);

                if (in_array($venueCityNormalized, $cityNames) || in_array($venueCityAscii, $cityNames)) {
                    $eventCount++;
                }
            }
        }

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

        // Get event counts with diacritics-aware matching
        $eventCounts = $this->getCityEventCounts($client->id, $region->cities);

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
