<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Activities;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Activity;
use App\Models\ActivityLocation;
use App\Models\Attraction;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceCity;
use App\Services\Activities\CatalogPresenter;
use App\Services\Activities\ProductAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public locations of the activities module (/locatii on bilete.online):
 * list, detail with everything sold there, one day's availability and the
 * calendar. Only published, approved locations.
 */
class LocationsController extends BaseController
{
    use LoadsCatalog;

    /** GET /activities-module/locations?city=&category=&q=&page=&per_page= */
    public function index(Request $request): JsonResponse
    {
        $client    = $this->requireClient($request);
        $presenter = new CatalogPresenter($this->locale($request));
        $perPage   = min(50, max(1, (int) $request->query('per_page', 24)));

        $query = ActivityLocation::visible()
            ->where('marketplace_client_id', $client->id)
            ->with(['city', 'category', 'products' => fn ($q) => $q->where('is_published', true)->with('variants')]);

        if ($city = $request->query('city')) {
            $cityId = MarketplaceCity::where('marketplace_client_id', $client->id)->where('slug', $city)->value('id');
            $query->where('marketplace_city_id', $cityId ?: 0);
        }
        if ($category = $request->query('category')) {
            $categoryId = MarketplaceCategory::where('marketplace_client_id', $client->id)->where('slug', $category)->value('id');
            $query->where('marketplace_category_id', $categoryId ?: 0);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            // Match names without case or diacritics ("sfanta" finds "Sfânta"),
            // the same on every database; a marketplace has few locations.
            $needle = self::fold($q);
            $ids = ActivityLocation::visible()
                ->where('marketplace_client_id', $client->id)
                ->get(['id', 'name'])
                ->filter(fn ($l) => str_contains(self::fold(implode(' ', array_filter((array) $l->name, 'is_string'))), $needle))
                ->pluck('id');
            $query->whereIn('id', $ids->all() ?: [0]);
        }

        $page = $query->orderBy('id')->paginate($perPage);

        return $this->success([
            'items'      => collect($page->items())->map(fn ($l) => $presenter->locationCard($l))->values(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page'    => $page->lastPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
            ],
        ]);
    }

    /** GET /activities-module/locations/{slug} */
    public function show(Request $request, string $slug): JsonResponse
    {
        $client   = $this->requireClient($request);
        $location = $this->findLocation($client->id, $slug);
        if (!$location) {
            return $this->error('Location not found', 404);
        }

        $data = (new CatalogPresenter($this->locale($request)))->location($location);
        $data['nearby_attractions'] = $client->hasMicroservice('discovery-module')
            ? $this->nearbyAttractions($client->id, $location)
            : [];

        return $this->success($data);
    }

    /**
     * GET /activities-module/locations/{slug}/day?date=Y-m-d
     *
     * Everything sellable that day: day tickets with seats left, time slots
     * (per variant when durations differ), packages with their components.
     */
    public function day(Request $request, string $slug): JsonResponse
    {
        $client   = $this->requireClient($request);
        $location = $this->findLocation($client->id, $slug);
        if (!$location) {
            return $this->error('Location not found', 404);
        }
        $date = $this->dateParam($request->query('date'));
        if (!$date) {
            return $this->error('date must be Y-m-d', 422);
        }

        $availability = new ProductAvailability();
        $products = $location->products->filter(fn ($p) => CatalogPresenter::onSale($p));

        return $this->success([
            'date'     => $date->toDateString(),
            'hours'    => $location->hoursOn($date),
            'products' => $products->map(fn (Activity $p) => ['id' => $p->id] + $this->productDay($availability, $p, $date))->values(),
        ]);
    }

    /** GET /activities-module/locations/{slug}/calendar?from=&to= (at most 62 days) */
    public function calendar(Request $request, string $slug): JsonResponse
    {
        $client   = $this->requireClient($request);
        $location = $this->findLocation($client->id, $slug);
        if (!$location) {
            return $this->error('Location not found', 404);
        }
        [$from, $to] = $this->range($request);

        $availability = new ProductAvailability();
        $products = $location->products->filter(fn ($p) => CatalogPresenter::onSale($p) && !$p->isPackage());
        // The day is "open" when an access ticket can be bought (or any product
        // when the location sells no access tickets).
        $lead = $products->where('product_type', Activity::TYPE_ACCESS);
        if ($lead->isEmpty()) {
            $lead = $products;
        }

        $days = [];
        foreach ($lead as $product) {
            foreach ($availability->calendar($product, $from, $to) as $row) {
                $d = &$days[$row['date']];
                $d ??= ['date' => $row['date'], 'status' => 'closed', 'min_price_cents' => null];
                $rank = ['closed' => 0, 'full' => 1, 'limited' => 2, 'available' => 3];
                if ($rank[$row['status']] > $rank[$d['status']]) {
                    $d['status'] = $row['status'];
                }
                if ($row['min_price_cents'] !== null) {
                    $d['min_price_cents'] = $d['min_price_cents'] === null ? $row['min_price_cents'] : min($d['min_price_cents'], $row['min_price_cents']);
                }
                unset($d);
            }
        }
        ksort($days);

        return $this->success([
            'from' => $from->toDateString(),
            'to'   => $to->toDateString(),
            'days' => array_values($days),
        ]);
    }

    /** Points of interest within 25 km, closest first (discovery module). */
    private function nearbyAttractions(int $clientId, ActivityLocation $location): array
    {
        if ($location->latitude === null || $location->longitude === null) {
            return [];
        }
        $lat = (float) $location->latitude;
        $lng = (float) $location->longitude;
        $presenter = new CatalogPresenter();

        return Attraction::visible()
            ->where('marketplace_client_id', $clientId)
            ->whereBetween('latitude', [$lat - 0.25, $lat + 0.25])
            ->whereBetween('longitude', [$lng - 0.35, $lng + 0.35])
            ->limit(200)
            ->get(['id', 'slug', 'name', 'subtitle', 'cover_image_url', 'latitude', 'longitude'])
            ->map(function ($a) use ($lat, $lng, $presenter) {
                $km = self::distanceKm($lat, $lng, (float) $a->latitude, (float) $a->longitude);
                return [
                    'slug'        => $a->slug,
                    'name'        => $presenter->t($a->name),
                    'subtitle'    => $presenter->t($a->subtitle),
                    'image'       => CatalogPresenter::url($a->cover_image_url),
                    'distance_km' => round($km, 1),
                ];
            })
            ->filter(fn ($a) => $a['distance_km'] <= 25)
            ->sortBy('distance_km')
            ->take(8)
            ->values()
            ->all();
    }

    /** Lower case without Romanian, Hungarian or German diacritics. */
    private static function fold(string $text): string
    {
        return strtr(mb_strtolower($text), [
            'ă' => 'a', 'â' => 'a', 'á' => 'a', 'ä' => 'a', 'î' => 'i', 'í' => 'i',
            'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't', 'é' => 'e', 'ó' => 'o',
            'ö' => 'o', 'ő' => 'o', 'ú' => 'u', 'ü' => 'u', 'ű' => 'u', 'ß' => 'ss',
        ]);
    }

    private static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
