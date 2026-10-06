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
        $activityCounts = $this->getCityActivityCounts($client->id, $cities);

        // Transform cities for response
        $result = $cities->map(function ($city) use ($eventCounts, $activityCounts, $lang) {
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
                'activities_count' => $activityCounts[$city->id] ?? 0,
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

        // Filter by country using the city's own country column (faster, includes cities without county)
        if ($request->filled('country')) {
            $country = strtoupper(trim($request->country));
            $query->where('country', $country);
        }

        // Filter by region slug (the cities of one region of a country)
        if ($request->filled('region')) {
            $regionSlug = trim((string) $request->region);
            $query->whereHas('region', fn ($q) => $q->where('slug', $regionSlug));
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

        // Featured cities always appear first, then by sort_order, then slug.
        // sort=name lists them alphabetically, sort=population by size; neither is re-sorted by events below.
        $sortBy = $request->input('sort', 'events');
        if ($sortBy === 'name') {
            $query->orderBy('slug');
        } elseif ($sortBy === 'population') {
            $query->orderByRaw('population IS NULL')->orderByDesc('population')->orderBy('slug');
        } else {
            $query->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('slug');
        }

        // Pagination
        $perPage = min((int) $request->input('per_page', 8), 200);
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
                'country' => $city->country,
                'population' => $city->population,
                'is_capital' => (bool) $city->is_capital,
            ];
        });

        // Sort by events if requested — featured cities always come first
        if ($sortBy === 'events') {
            $transformedData = $transformedData->sort(function ($a, $b) {
                if ($a['is_featured'] !== $b['is_featured']) {
                    return $b['is_featured'] <=> $a['is_featured']; // featured first
                }
                return $b['events_count'] <=> $a['events_count'];   // then by event count desc
            })->values();
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
    /**
     * Count published activities per city in one grouped query.
     * Activities link to a city directly via marketplace_city_id, so this is an
     * exact count (no diacritics matching needed, unlike events).
     *
     * @return array<int,int> map of city id => activities count
     */
    private function getCityActivityCounts(int $clientId, $cities): array
    {
        $ids = collect($cities)->pluck('id')->all();
        if (empty($ids)) {
            return [];
        }

        return \App\Models\Activity::query()
            ->where('marketplace_client_id', $clientId)
            ->where('is_published', true)
            ->whereIn('marketplace_city_id', $ids)
            ->selectRaw('marketplace_city_id, COUNT(*) as c')
            ->groupBy('marketplace_city_id')
            ->pluck('c', 'marketplace_city_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

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
                ->where('is_published', true)
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
     * One country, by ISO code or slug: its name, the number of visible cities and its regions with their city
     * counts. The cities themselves come from the cities endpoint (country=, region=, sort=, page=).
     */
    public function country(Request $request, string $identifier): JsonResponse
    {
        $client = $this->requireClient($request);
        $lang = $client->language ?? $client->locale ?? 'ro';
        $identifier = strtolower(trim($identifier));

        $codes = MarketplaceCity::where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->whereNotNull('country')
            ->distinct()
            ->pluck('country');

        $match = null;
        foreach (DB::table('geo_countries')->whereIn('iso2', $codes)->get() as $row) {
            $name = $row->name_en ?: $row->name_native;
            if (strtolower($row->iso2) === $identifier || \Illuminate\Support\Str::slug($name) === $identifier) {
                $match = ['code' => $row->iso2, 'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name)];
                break;
            }
        }
        if (! $match) {
            return $this->error('Country not found', 404);
        }

        $perRegion = MarketplaceCity::where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->where('country', $match['code'])
            ->whereNotNull('region_id')
            ->selectRaw('region_id, COUNT(*) as cities_count')
            ->groupBy('region_id')
            ->pluck('cities_count', 'region_id');

        $regions = MarketplaceRegion::where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->whereIn('id', $perRegion->keys())
            ->get()
            ->map(fn ($region) => [
                'id' => $region->id,
                'name' => $region->name[$lang] ?? array_values((array) $region->name)[0] ?? $region->slug,
                'slug' => $region->slug,
                'cities_count' => (int) ($perRegion[$region->id] ?? 0),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $match['cities_count'] = (int) MarketplaceCity::where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->where('country', $match['code'])
            ->count();

        return $this->success(['country' => $match, 'regions' => $regions]);
    }

    /**
     * Countries that have visible cities, each with its largest cities.
     *
     * Made for marketplaces that span several countries: the menu needs "country -> top cities" without loading
     * every region and city (the regions endpoint does that, which is fine for one country and far too much for a
     * continent). Counts come from one grouped query; the top cities are the featured ones first, then by sort_order.
     *
     * Query: top = cities per country (default 8, max 30).
     */
    public function countries(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $lang = $client->language ?? $client->locale ?? 'ro';
        $top = max(1, min((int) $request->input('top', 8), 30));

        $counts = MarketplaceCity::where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->whereNotNull('country')
            ->selectRaw('country, COUNT(*) as cities_count')
            ->groupBy('country')
            ->pluck('cities_count', 'country');

        $geo = DB::table('geo_countries')->whereIn('iso2', $counts->keys())->get()->keyBy('iso2');

        $result = [];
        foreach ($counts as $code => $citiesCount) {
            $country = $geo[$code] ?? null;
            $name = $country->name_en ?? $country->name_native ?? $code;

            $topCities = MarketplaceCity::where('marketplace_client_id', $client->id)
                ->where('is_visible', true)
                ->where('country', $code)
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->limit($top)
                ->get()
                ->map(fn ($city) => [
                    'id' => $city->id,
                    'name' => $city->name[$lang] ?? array_values((array) $city->name)[0] ?? $city->slug,
                    'slug' => $city->slug,
                    'image' => $city->image_full_url,
                    'population' => $city->population,
                    'is_capital' => (bool) $city->is_capital,
                ]);

            $result[] = [
                'code' => $code,
                'name' => $name,
                'slug' => \Illuminate\Support\Str::slug($name),
                'cities_count' => (int) $citiesCount,
                'sort_order' => (int) ($country->sort_order ?? 9999),
                'top_cities' => $topCities,
            ];
        }
        usort($result, fn ($a, $b) => [$a['sort_order'], $a['name']] <=> [$b['sort_order'], $b['name']]);

        return $this->success(['countries' => $result]);
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
            // Match exact slug (e.g. 'ro-bucuresti') OR base slug without country prefix (e.g. 'bucuresti')
            $query->where(function ($q) use ($identifier) {
                $q->where('slug', $identifier)
                  ->orWhere('slug', 'like', '%-' . $identifier);
            });
        }

        // an exact slug wins over a slug that merely ends with the same word ("bath" vs "great-bath")
        if (! is_numeric($identifier)) {
            $query->orderByRaw('CASE WHEN slug = ? THEN 0 ELSE 1 END', [$identifier]);
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
                'seo_body_title' => isset($city->seo_body_title[$lang]) ? $city->seo_body_title[$lang] : ($city->seo_body_title['ro'] ?? null),
                'seo_body' => isset($city->seo_body[$lang]) ? $city->seo_body[$lang] : ($city->seo_body['ro'] ?? null),
                'faqs' => $city->faqs ?? [],
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
                'country' => $city->country,
                'population' => $city->population,
                'latitude' => $city->latitude,
                'longitude' => $city->longitude,
                'is_capital' => $city->is_capital,
                // Affiliate widget IDs surfaced to the public frontend so the
                // city template can render embedded blocks (e.g.
                // GetYourGuide) without needing a second API call.
                'getyourguide_city_id' => $city->getyourguide_city_id,
            ],
            // Per-marketplace affiliate configuration (partner ids etc.)
            // bundled with the city payload — keeps the frontend's network
            // budget at one call per city page render.
            'affiliates' => [
                'getyourguide_partner_id' => data_get($client->settings, 'affiliate.getyourguide_partner_id'),
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
