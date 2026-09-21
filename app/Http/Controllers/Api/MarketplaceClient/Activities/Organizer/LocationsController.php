<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Activities\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\ActivityLocation;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceCity;
use App\Services\Activities\OrganizerCatalog;
use App\Services\Activities\OrganizerCatalogPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The operator's locations (activities module). A new location is a draft;
 * the first publication goes through the marketplace admin, later edits go
 * live directly.
 */
class LocationsController extends BaseController
{
    use ResolvesOrganizer;

    /** GET organizer/activities-module/locations */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->organizer($request);
        $presenter = new OrganizerCatalogPresenter();

        $locations = ActivityLocation::where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->withCount('products')
            ->orderBy('id')
            ->get();

        return $this->success(['locations' => $locations->map(fn ($l) => $presenter->location($l))->values()]);
    }

    /** GET organizer/activities-module/locations/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $catalog  = new OrganizerCatalog($this->organizer($request));
        $location = $catalog->ownLocation($id);
        if (!$location) {
            return $this->error('Locația nu există', 404);
        }
        return $this->success(['location' => (new OrganizerCatalogPresenter())->location($location)]);
    }

    /** POST organizer/activities-module/locations — saved as a draft */
    public function store(Request $request): JsonResponse
    {
        $catalog = new OrganizerCatalog($this->organizer($request));
        try {
            $data = $catalog->validateLocation($request->all());
        } catch (ValidationException $e) {
            return $this->invalid($e);
        }
        $location = new ActivityLocation();
        $catalog->fillLocation($location, $data);
        $location->save();

        return $this->success(['location' => (new OrganizerCatalogPresenter())->location($location->fresh())], 'Locația a fost salvată ca ciornă.', 201);
    }

    /** PUT organizer/activities-module/locations/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $catalog  = new OrganizerCatalog($this->organizer($request));
        $location = $catalog->ownLocation($id);
        if (!$location) {
            return $this->error('Locația nu există', 404);
        }
        try {
            $data = $catalog->validateLocation($request->all());
        } catch (ValidationException $e) {
            return $this->invalid($e);
        }
        $catalog->fillLocation($location, $data);
        $location->save();

        return $this->success(['location' => (new OrganizerCatalogPresenter())->location($location->fresh())], 'Modificările au fost salvate.');
    }

    /** POST organizer/activities-module/locations/{id}/submit — ask for approval */
    public function submit(Request $request, int $id): JsonResponse
    {
        $catalog  = new OrganizerCatalog($this->organizer($request));
        $location = $catalog->ownLocation($id);
        if (!$location) {
            return $this->error('Locația nu există', 404);
        }
        if (!in_array($location->review_status, [ActivityLocation::REVIEW_DRAFT, ActivityLocation::REVIEW_REJECTED], true)) {
            return $this->error($location->review_status === 'pending' ? 'Locația așteaptă deja aprobarea.' : 'Locația e deja aprobată.', 409);
        }
        $missing = array_keys(array_filter([
            'numele'           => empty($location->name['ro'] ?? null),
            'orașul'           => !$location->marketplace_city_id,
            'adresa sau harta' => !$location->address && ($location->latitude === null || $location->longitude === null),
            'poza principală'  => !$location->cover_image_url,
            'descrierea'       => empty($location->short_description['ro'] ?? null) && empty($location->description['ro'] ?? null),
        ]));
        if ($missing) {
            return $this->error('Mai completează: ' . implode(', ', $missing) . '.', 422, ['missing' => $missing]);
        }
        $location->update(['review_status' => ActivityLocation::REVIEW_PENDING, 'submitted_at' => now(), 'rejection_reason' => null]);

        return $this->success(['location' => (new OrganizerCatalogPresenter())->location($location->fresh())], 'Locația a fost trimisă spre aprobare.');
    }

    /** POST organizer/activities-module/locations/{id}/publish {published} — after approval */
    public function publish(Request $request, int $id): JsonResponse
    {
        $catalog  = new OrganizerCatalog($this->organizer($request));
        $location = $catalog->ownLocation($id);
        if (!$location) {
            return $this->error('Locația nu există', 404);
        }
        if ($location->review_status !== ActivityLocation::REVIEW_APPROVED) {
            return $this->error('Locația se poate publica după aprobare.', 409);
        }
        $location->update(['is_published' => $request->boolean('published')]);

        return $this->success(['location' => (new OrganizerCatalogPresenter())->location($location->fresh())],
            $location->is_published ? 'Locația e vizibilă pe site.' : 'Locația a fost ascunsă de pe site.');
    }

    /** DELETE organizer/activities-module/locations/{id} — only without products */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $catalog  = new OrganizerCatalog($this->organizer($request));
        $location = $catalog->ownLocation($id);
        if (!$location) {
            return $this->error('Locația nu există', 404);
        }
        if ($location->products()->exists()) {
            return $this->error('Locația are produse. Șterge-le sau mută-le întâi, ori ascunde locația.', 409);
        }
        $location->delete();

        return $this->success(null, 'Locația a fost ștearsă.');
    }

    /** GET organizer/activities-module/meta — options for the editors */
    public function meta(Request $request): JsonResponse
    {
        $organizer = $this->organizer($request);
        $clientId  = $organizer->marketplace_client_id;
        $name = fn ($n) => is_array($n) ? ($n['ro'] ?? $n['en'] ?? reset($n)) : $n;

        $cities = MarketplaceCity::where('marketplace_client_id', $clientId)
            ->when(Schema::hasTable('marketplace_counties'), fn ($q) => $q->with('county:id,name,code'))
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'county_id', 'country']);

        // The editor picks the county first and then only its cities, so every
        // city needs one. The marketplace's own counties answer first; for a
        // city without one the central geo dataset answers, but only when the
        // name points at a single county (no guessing between two "Sălciua").
        $byName = $this->geoCounties($cities->filter(fn ($c) => !$c->county)->map(fn ($c) => (string) $name($c->name))->all());

        return $this->success([
            'cities' => $cities->map(function ($c) use ($name, $byName) {
                $label = $c->county ? (string) $name($c->county->name) : ($byName[self::fold((string) $name($c->name))] ?? null);

                return [
                    'id'      => $c->id,
                    'name'    => $name($c->name),
                    'slug'    => $c->slug,
                    'county'  => $label !== '' ? $label : null,
                    'country' => strtoupper((string) ($c->country ?: 'RO')),
                ];
            })->values(),
            'countries' => $this->countries($cities->pluck('country')->all()),
            'categories' => MarketplaceCategory::where('marketplace_client_id', $clientId)->orderBy('sort_order')->get(['id', 'parent_id', 'name', 'slug'])
                ->map(fn ($c) => ['id' => $c->id, 'parent_id' => $c->parent_id, 'name' => $name($c->name), 'slug' => $c->slug])->values(),
            'facilities'          => OrganizerCatalog::FACILITIES,
            'lodging_facilities'  => OrganizerCatalog::LODGING_FACILITIES,
            'lodging_types'       => OrganizerCatalog::LODGING_TYPES,
            'link_platforms'      => OrganizerCatalog::LINK_PLATFORMS,
            'custom_facility_prefix' => OrganizerCatalog::CUSTOM_FACILITY,
            'has_secondary_issuer' => (bool) $organizer->has_secondary_issuer,
        ]);
    }

    /** "Băile Tușnad" → "baile tusnad": how geo_localities stores its match key. */
    private static function fold(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }

    /**
     * ["Băile Tușnad", …] → ["baile tusnad" => "Harghita", …] from the central
     * geo dataset. Only unambiguous names answer; missing tables answer [].
     */
    private function geoCounties(array $names): array
    {
        $names = array_values(array_filter(array_unique(array_map([self::class, 'fold'], $names))));
        if (!$names || !Schema::hasTable('geo_localities') || !Schema::hasTable('geo_counties')) {
            return [];
        }

        $rows = DB::table('geo_localities as l')
            ->join('geo_counties as c', 'c.id', '=', 'l.county_id')
            ->whereIn('l.name_ascii', $names)
            ->orderBy('l.id')
            ->get(['l.name_ascii as key', 'c.name_native as county']);

        $out = [];
        foreach ($rows as $row) {
            if (array_key_exists($row->key, $out) && $out[$row->key] !== $row->county) {
                $out[$row->key] = null; // the same name in two counties: we do not guess
                continue;
            }
            $out[$row->key] = $row->county;
        }

        return array_filter($out);
    }

    /** The countries the marketplace's cities are in, named in Romanian. */
    private function countries(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map(
            fn ($c) => strtoupper(trim((string) $c)) ?: null,
            $codes
        ))));
        if (!$codes) {
            $codes = ['RO'];
        }
        sort($codes);

        $names = ['RO' => 'România'];
        if (Schema::hasTable('geo_countries')) {
            foreach (DB::table('geo_countries')->whereIn('iso2', $codes)->get(['iso2', 'name_native']) as $row) {
                $names[$row->iso2] = $row->name_native;
            }
        }

        return array_map(fn ($code) => ['code' => $code, 'name' => $names[$code] ?? $code], $codes);
    }
}
