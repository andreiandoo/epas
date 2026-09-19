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

        return $this->success([
            'cities' => MarketplaceCity::where('marketplace_client_id', $clientId)->orderBy('sort_order')->get(['id', 'name', 'slug'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => $name($c->name), 'slug' => $c->slug])->values(),
            'categories' => MarketplaceCategory::where('marketplace_client_id', $clientId)->orderBy('sort_order')->get(['id', 'parent_id', 'name', 'slug'])
                ->map(fn ($c) => ['id' => $c->id, 'parent_id' => $c->parent_id, 'name' => $name($c->name), 'slug' => $c->slug])->values(),
            'facilities'          => OrganizerCatalog::FACILITIES,
            'lodging_facilities'  => OrganizerCatalog::LODGING_FACILITIES,
            'lodging_types'       => OrganizerCatalog::LODGING_TYPES,
            'link_platforms'      => OrganizerCatalog::LINK_PLATFORMS,
            'has_secondary_issuer' => (bool) $organizer->has_secondary_issuer,
        ]);
    }
}
