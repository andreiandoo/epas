<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Activities\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Activity;
use App\Services\Activities\OrganizerCatalog;
use App\Services\Activities\OrganizerCatalogPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The operator's access tickets, experiences and packages. A new product is
 * a draft; the first publication goes through the marketplace admin, later
 * edits go live directly.
 */
class ProductsController extends BaseController
{
    use ResolvesOrganizer;

    private const RELATIONS = ['variants', 'schedules', 'scheduleExceptions', 'addons', 'packageItems.component', 'packageItems.componentVariant', 'location'];

    /** GET organizer/activities-module/products?location_id=&type= */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->organizer($request);
        $presenter = new OrganizerCatalogPresenter();

        $products = Activity::where('marketplace_organizer_id', $organizer->id)
            ->where('marketplace_client_id', $organizer->marketplace_client_id)
            ->when($request->query('location_id'), fn ($q, $id) => $q->where('location_id', (int) $id))
            ->when($request->query('type'), fn ($q, $t) => $q->where('product_type', $t))
            ->with(['variants', 'location'])
            ->orderBy('location_id')->orderBy('id')
            ->get();

        return $this->success(['products' => $products->map(fn ($p) => $presenter->productCard($p))->values()]);
    }

    /** GET organizer/activities-module/products/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $catalog = new OrganizerCatalog($this->organizer($request));
        $product = $catalog->ownProduct($id)?->load(self::RELATIONS);
        if (!$product) {
            return $this->error('Produsul nu există', 404);
        }
        return $this->success(['product' => (new OrganizerCatalogPresenter())->product($product)]);
    }

    /** POST organizer/activities-module/products — saved as a draft */
    public function store(Request $request): JsonResponse
    {
        $catalog = new OrganizerCatalog($this->organizer($request));
        try {
            $data = $catalog->validateProduct($request->all());
        } catch (ValidationException $e) {
            return $this->invalid($e);
        }
        $product = $catalog->saveProduct(new Activity(), $data);

        return $this->success(['product' => (new OrganizerCatalogPresenter())->product($product)], 'Produsul a fost salvat ca ciornă.', 201);
    }

    /** PUT organizer/activities-module/products/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $catalog = new OrganizerCatalog($this->organizer($request));
        $product = $catalog->ownProduct($id)?->load('variants');
        if (!$product) {
            return $this->error('Produsul nu există', 404);
        }
        try {
            $data = $catalog->validateProduct($request->all(), $product);
        } catch (ValidationException $e) {
            return $this->invalid($e);
        }
        $product = $catalog->saveProduct($product, $data);

        return $this->success(['product' => (new OrganizerCatalogPresenter())->product($product)], 'Modificările au fost salvate.');
    }

    /** POST organizer/activities-module/products/{id}/submit — ask for approval */
    public function submit(Request $request, int $id): JsonResponse
    {
        $catalog = new OrganizerCatalog($this->organizer($request));
        $product = $catalog->ownProduct($id)?->load(self::RELATIONS);
        if (!$product) {
            return $this->error('Produsul nu există', 404);
        }
        if (!in_array($product->review_status, ['draft', 'rejected'], true)) {
            return $this->error($product->review_status === 'pending' ? 'Produsul așteaptă deja aprobarea.' : 'Produsul e deja aprobat.', 409);
        }
        $missing = array_keys(array_filter([
            'titlul'              => empty($product->title['ro'] ?? null),
            'un bilet activ'      => $product->variants->where('is_active', true)->isEmpty(),
            'o poză'              => !$product->cover_image_url && !$product->location?->cover_image_url,
            'conținutul pachetului' => $product->isPackage() && $product->packageItems->isEmpty(),
            'programul'           => !$product->isPackage() && !$product->isDayMode() && !$product->use_location_schedule && $product->schedules->isEmpty(),
        ]));
        if ($missing) {
            return $this->error('Mai completează: ' . implode(', ', $missing) . '.', 422, ['missing' => $missing]);
        }
        $product->update(['review_status' => 'pending', 'submitted_at' => now(), 'rejection_reason' => null]);

        return $this->success(['product' => (new OrganizerCatalogPresenter())->product($product->fresh(self::RELATIONS))], 'Produsul a fost trimis spre aprobare.');
    }

    /** POST organizer/activities-module/products/{id}/publish {published} — after approval */
    public function publish(Request $request, int $id): JsonResponse
    {
        $catalog = new OrganizerCatalog($this->organizer($request));
        $product = $catalog->ownProduct($id);
        if (!$product) {
            return $this->error('Produsul nu există', 404);
        }
        if ($product->review_status !== null && $product->review_status !== 'approved') {
            return $this->error('Produsul se poate publica după aprobare.', 409);
        }
        $product->update(['is_published' => $request->boolean('published')]);

        return $this->success(['product' => (new OrganizerCatalogPresenter())->product($product->fresh(self::RELATIONS))],
            $product->is_published ? 'Produsul e în vânzare.' : 'Produsul a fost scos din vânzare.');
    }

    /** POST organizer/activities-module/products/{id}/duplicate — a draft copy */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $organizer = $this->organizer($request);
        $catalog   = new OrganizerCatalog($organizer);
        $source    = $catalog->ownProduct($id)?->load(self::RELATIONS);
        if (!$source) {
            return $this->error('Produsul nu există', 404);
        }
        $data = (new OrganizerCatalogPresenter())->product($source);
        $data['title'] = mb_substr(($data['title'] ?? 'Produs') . ' (copie)', 0, 190);
        $data['product_type'] = $source->product_type;
        $data['location_id'] = $source->location_id;
        $data['cover_image'] = $data['cover_image']['path'] ?? null;
        $data['gallery'] = array_column($data['gallery'], 'path');
        foreach ($data['variants'] as &$v) {
            unset($v['id']);
        }
        foreach ($data['addons'] as &$a) {
            unset($a['id']);
        }
        unset($v, $a);

        $copy = DB::transaction(function () use ($catalog, $data, $source) {
            $copy = $catalog->saveProduct(new Activity(), $data);
            // The copy may reuse the original's images.
            $copy->update(['cover_image_url' => $source->cover_image_url, 'gallery' => $source->gallery]);
            return $copy->fresh(self::RELATIONS);
        });

        return $this->success(['product' => (new OrganizerCatalogPresenter())->product($copy)], 'Copia a fost creată ca ciornă.', 201);
    }

    /** DELETE organizer/activities-module/products/{id} — only if never sold */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $catalog = new OrganizerCatalog($this->organizer($request));
        $product = $catalog->ownProduct($id);
        if (!$product) {
            return $this->error('Produsul nu există', 404);
        }
        if ($product->bookings()->exists()) {
            return $this->error('Produsul are vânzări și nu se poate șterge. Scoate-l din vânzare.', 409);
        }
        if (\App\Models\ActivityPackageItem::where('component_activity_id', $product->id)->exists()) {
            return $this->error('Produsul face parte dintr-un pachet. Scoate-l întâi din pachet.', 409);
        }
        $product->delete();

        return $this->success(null, 'Produsul a fost șters.');
    }
}
