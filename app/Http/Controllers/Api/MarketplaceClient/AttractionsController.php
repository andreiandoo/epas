<?php

namespace App\Http\Controllers\Api\MarketplaceClient;

use App\Models\Attraction;
use App\Models\AttractionType;
use App\Models\MarketplaceCity;
use App\Models\MarketplaceCounty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * F4 — Public marketplace API for Attractions (points of interest).
 *
 *   GET /attractions                — list (filter by city / type)
 *   GET /attractions/map            — every geo-located attraction as a packed pin
 *   GET /attractions/{slug}         — single attraction detail + activities
 *
 * Scoped by marketplace client (resolved from the API key by marketplace.auth).
 * Read access is open even when `discovery-module` is off (the table is simply
 * empty for marketplaces that never seeded attractions — same pattern as
 * activities/events).
 */
class AttractionsController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $locale = $request->query('locale', 'ro');

        $query = Attraction::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->with(['type:id,slug,name,icon_emoji', 'city:id,slug,name,is_visible'])
            ->withCount(['activities' => fn ($q) => $q->where('activities.is_published', true)]);

        if ($citySlug = $request->query('city')) {
            $query->whereHas('city', fn ($q) => $q->where('slug', $citySlug));
        }
        if ($typeSlug = $request->query('type')) {
            $query->whereHas('type', fn ($q) => $q->where('slug', $typeSlug));
        }
        if ($featured = $request->query('featured')) {
            if (in_array($featured, ['1', 'true', 'yes'], true)) {
                $query->where('is_featured', true);
            }
        }
        // Free text over the name, so the list page can offer a search box. Name is translatable JSONB and
        // ro is the primary locale on the marketplaces that have attractions.
        if (($search = trim((string) $request->query('search', ''))) !== '') {
            // Diacritics are optional when people type: "barca" has to find "Închiriere barcă cu vâsle".
            // Postgres folds them in the comparison; anything else (the SQLite used in tests) matches plainly.
            $needle = '%' . mb_strtolower($search) . '%';
            if (DB::connection()->getDriverName() === 'pgsql') {
                $folded = strtr($needle, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't']);
                $query->whereRaw("translate(lower(name->>'ro'), 'ăâîșşțţ', 'aaisstt') LIKE ?", [$folded]);
            } else {
                $query->whereRaw("LOWER(name->>'ro') LIKE ?", [$needle]);
            }
        }

        // 'default' keeps the curated order (featured, then sort_order). The others let the list page
        // offer a sort control; unknown values fall back to the curated order.
        match ((string) $request->query('sort', '')) {
            'name'     => $query->orderByRaw("LOWER(name->>'ro') ASC")->orderBy('id'),
            'activities' => $query->orderByDesc('activities_count')->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id'),
            default    => $query->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id'),
        };

        $perPage = max(1, min(50, (int) $request->query('per_page', 24)));
        $paginator = $query->paginate($perPage);

        return $this->success([
            'items' => $paginator->getCollection()->map(fn ($a) => $this->cardPayload($a, $locale))->values(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $client = $this->requireClient($request);
        $locale = $request->query('locale', 'ro');

        $attraction = Attraction::query()
            ->where('marketplace_client_id', $client->id)
            ->where('slug', $slug)
            ->where('is_visible', true)
            ->with([
                'type:id,slug,name,icon_emoji',
                'city:id,slug,name,is_visible',
                'county:id,name',
                'activities' => fn ($q) => $q->where('is_published', true)->orderBy('activity_attraction.sort_order'),
                'activities.city:id,slug,name',
                'activities.category:id,slug,name,parent_id',
            ])
            ->first();

        if (! $attraction) {
            return response()->json(['success' => false, 'message' => 'Attraction not found'], 404);
        }

        // Sibling attractions for the "explore more" rails — fetched here so the
        // single /attractions/{slug} call powers the whole page (no extra
        // round-trips from the frontend, which matters for page speed).
        $cityId = $attraction->marketplace_city_id;
        $countyId = $attraction->marketplace_county_id;

        $siblings = fn () => Attraction::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->where('id', '!=', $attraction->id)
            ->with(['type:id,slug,name,icon_emoji', 'city:id,slug,name,is_visible'])
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id');

        $cityAttractions = $cityId
            ? $siblings()->where('marketplace_city_id', $cityId)->limit(6)->get()
            : collect();

        $countyAttractions = $countyId
            ? $siblings()
                ->where('marketplace_county_id', $countyId)
                ->when($cityId, fn ($q) => $q->where('marketplace_city_id', '!=', $cityId))
                ->limit(6)->get()
            : collect();

        return $this->success([
            'attraction' => array_merge($this->cardPayload($attraction, $locale), [
                'county'      => $attraction->county ? $this->translate($attraction->county->name, $locale) : null,
                'city_attractions'   => $cityAttractions->map(fn ($a) => $this->cardPayload($a, $locale))->values()->all(),
                'county_attractions' => $countyAttractions->map(fn ($a) => $this->cardPayload($a, $locale))->values()->all(),
                'subtitle'    => $this->translate($attraction->subtitle, $locale),
                'description' => $this->translate($attraction->description, $locale),
                'gallery'     => collect((array) $attraction->gallery)->map(fn ($g) => $this->img($g))->filter()->values()->all(),
                'address'     => $attraction->address,
                'latitude'    => $attraction->latitude,
                'longitude'   => $attraction->longitude,
                'faqs'        => (array) ($attraction->faqs ?? []),
                'seo'         => [
                    'title'       => is_array($attraction->seo) ? ($attraction->seo['title_' . $locale] ?? null) : null,
                    'description' => is_array($attraction->seo) ? ($attraction->seo['description_' . $locale] ?? null) : null,
                ],
                'activities'  => $attraction->activities->map(fn ($act) => [
                    'slug'            => $act->slug,
                    'title'          => $this->translate($act->title, $locale),
                    'cover_image_url' => $this->img($act->cover_image_url),
                    'cheapest_price_cents' => $act->cheapest_price_cents,
                    'duration_minutes' => (int) $act->duration_minutes,
                    'city'           => $act->city ? ['slug' => $act->city->slug, 'name' => $this->translate($act->city->name, $locale)] : null,
                    'category'       => $act->category ? ['slug' => $act->category->slug, 'name' => $this->translate($act->category->name, $locale)] : null,
                ])->values()->all(),
            ]),
        ]);
    }

    /**
     * Every geo-located, visible attraction of the marketplace in one packed
     * response, for the interactive map. The paginated list above caps
     * per_page at 50, which would mean ~146 round-trips for a 7k-pin map.
     *
     * The payload is columnar on purpose (arrays, not objects, coordinates as
     * integers x1e5): it is a few hundred KB for 7k pins instead of a few MB
     * of GeoJSON. `fields` and `flags` document the row layout so the client
     * never hard-codes offsets.
     *
     * Cached per client+locale; the key carries a signature of the data
     * (row count + newest updated_at), so an import or an edit in the admin
     * invalidates it on the next request instead of waiting out the TTL.
     */
    public function map(Request $request): JsonResponse
    {
        $client = $this->requireClient($request);
        $locale = $request->query('locale', 'ro');

        $base = fn () => Attraction::query()
            ->where('marketplace_client_id', $client->id)
            ->where('is_visible', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        $signature = substr(sha1((string) $base()->count() . '|' . (string) $base()->max('updated_at')), 0, 12);

        $payload = Cache::remember(
            "mpc:{$client->id}:attractions-map:{$locale}:{$signature}",
            now()->addHours(24),
            fn () => $this->buildMapPayload($client->id, $locale, $signature)
        );

        return $this->success($payload);
    }

    private function buildMapPayload(int $clientId, string $locale, string $signature): array
    {
        // Type and city lookups first, so the pin rows can reference them by
        // index instead of repeating the names 7k times.
        $typeIdx = [];
        $types   = [];
        foreach (AttractionType::query()
            ->where('marketplace_client_id', $clientId)
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'slug', 'name', 'icon_emoji', 'color']) as $i => $t) {
            $typeIdx[$t->id] = $i;
            $types[] = [$t->slug, $this->translate($t->name, $locale), $t->icon_emoji, $t->color, 0];
        }

        $cityIds = Attraction::query()
            ->where('marketplace_client_id', $clientId)
            ->where('is_visible', true)
            ->whereNotNull('marketplace_city_id')
            ->distinct()
            ->pluck('marketplace_city_id');

        $cityIdx = [];
        $cities  = [];
        foreach (MarketplaceCity::query()
            ->whereIn('id', $cityIds)
            ->with(['county:id,name', 'region:id,name'])
            ->orderBy('id')
            ->get(['id', 'slug', 'name', 'county_id', 'region_id']) as $i => $c) {
            $cityIdx[$c->id] = $i;
            $cities[] = [
                $c->slug,
                $this->translate($c->name, $locale),
                $c->county ? $this->translate($c->county->name, $locale) : null,
                $c->region ? $this->translate($c->region->name, $locale) : null,
                0,
            ];
        }

        // Counties carry the region, and an attraction can have a county even
        // when it has no city, so zone (county+region) is its own lookup
        // instead of being read off the city. Region filters would otherwise
        // miss every attraction that is not tied to a city.
        $zoneIdx = [];
        $zones   = [];
        foreach (MarketplaceCounty::query()
            ->where('marketplace_client_id', $clientId)
            ->with('region:id,name')
            ->orderBy('id')
            ->get(['id', 'name', 'region_id']) as $i => $co) {
            $zoneIdx[$co->id] = $i;
            $zones[] = [
                $this->translate($co->name, $locale),
                $co->region ? $this->translate($co->region->name, $locale) : null,
                0,
            ];
        }

        // city id => county id, so an attraction with no county of its own
        // still lands in the right zone through its city.
        $cityCounty = MarketplaceCity::query()
            ->whereIn('id', $cityIds)
            ->whereNotNull('county_id')
            ->pluck('county_id', 'id')
            ->all();

        // Streamed in id order, lean columns only — 7k rows never sit in
        // memory as fully hydrated models with relations.
        $points = [];
        Attraction::query()
            ->where('marketplace_client_id', $clientId)
            ->where('is_visible', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            // select() before withCount(): the reverse order would reset the
            // column list and drop the count subquery.
            ->select(['id', 'slug', 'name', 'cover_image_url', 'latitude', 'longitude', 'is_featured', 'attraction_type_id', 'marketplace_city_id', 'marketplace_county_id'])
            ->withCount(['activities' => fn ($q) => $q->where('activities.is_published', true)])
            ->orderBy('id')
            ->lazy(1000)
            ->each(function (Attraction $a) use (&$points, &$types, &$cities, &$zones, $typeIdx, $cityIdx, $zoneIdx, $cityCounty, $locale) {
                $t = $typeIdx[$a->attraction_type_id] ?? -1;
                $c = $cityIdx[$a->marketplace_city_id] ?? -1;
                $countyId = $a->marketplace_county_id ?: ($cityCounty[$a->marketplace_city_id] ?? null);
                $z = $countyId !== null ? ($zoneIdx[$countyId] ?? -1) : -1;

                $flags = 0;
                if ($a->cover_image_url) {
                    $flags |= 1;
                }
                if ($a->is_featured) {
                    $flags |= 2;
                }
                if ((int) ($a->activities_count ?? 0) > 0) {
                    $flags |= 4;
                }

                $points[] = [
                    $this->translate($a->name, $locale),
                    $a->slug,
                    $t,
                    $c,
                    $z,
                    (int) round($a->latitude * 100000),
                    (int) round($a->longitude * 100000),
                    $flags,
                    // Full URL rather than a derived path: the covers happen
                    // to be <slug>.<ext> today, but nothing enforces that, and
                    // gzip collapses the shared prefix anyway.
                    $this->img($a->cover_image_url) ?? '',
                ];

                if ($t >= 0) {
                    $types[$t][4]++;
                }
                if ($c >= 0) {
                    $cities[$c][4]++;
                }
                if ($z >= 0) {
                    $zones[$z][2]++;
                }
            });

        return [
            'v'            => $signature,
            'generated_at' => now()->toIso8601String(),
            'fields'       => ['name', 'slug', 'type', 'city', 'zone', 'lat_e5', 'lng_e5', 'flags', 'img'],
            'flags'        => ['image' => 1, 'featured' => 2, 'activities' => 4],
            'type_fields'  => ['slug', 'name', 'emoji', 'color', 'count'],
            'city_fields'  => ['slug', 'name', 'county', 'region', 'count'],
            'zone_fields'  => ['county', 'region', 'count'],
            'types'        => $types,
            'cities'       => $cities,
            'zones'        => $zones,
            'points'       => $points,
            'total'        => count($points),
        ];
    }

    private function cardPayload(Attraction $a, string $locale): array
    {
        return [
            'id'              => $a->id,
            'slug'            => $a->slug,
            'name'            => $this->translate($a->name, $locale),
            'cover_image_url' => $this->img($a->cover_image_url),
            'latitude'        => $a->latitude,
            'longitude'       => $a->longitude,
            'is_featured'     => (bool) $a->is_featured,
            'activities_count' => $a->activities_count ?? null,
            'type' => $a->type ? [
                'slug' => $a->type->slug,
                'name' => $this->translate($a->type->name, $locale),
                'icon' => $a->type->icon_emoji,
            ] : null,
            // Who took the cover photo and under what licence, when it is not ours to give away.
            'cover_credit' => $a->cover_image_credit ?: null,
            'city' => $a->city ? [
                'slug' => $a->city->slug,
                'name' => $this->translate($a->city->name, $locale),
                // false for the localities the import created so that every attraction has a
                // place: they are real, but they have no city page to link to.
                'has_page' => (bool) $a->city->is_visible,
            ] : null,
        ];
    }

    private function translate($value, string $locale): ?string
    {
        if (is_array($value)) {
            return $value[$locale] ?? $value['ro'] ?? $value['en'] ?? (reset($value) ?: null);
        }

        return $value !== '' && $value !== null ? (string) $value : null;
    }

    private function img(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
