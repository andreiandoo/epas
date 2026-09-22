<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Activities;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Activity;
use App\Services\Activities\CatalogPresenter;
use App\Services\Activities\ProductAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public product endpoints of the activities module: one access ticket,
 * experience or package, its calendar and its start times on a date.
 */
class ProductsController extends BaseController
{
    use LoadsCatalog;

    /** GET /activities-module/products/{slug} */
    public function show(Request $request, string $slug): JsonResponse
    {
        $client  = $this->requireClient($request);
        $product = $this->findProduct($client->id, $slug);
        if (!$product) {
            return $this->error('Product not found', 404);
        }

        // one more visitor on the product's page — the operator's panel counts these
        rescue(fn () => Activity::whereKey($product->id)->increment('views_count'), null, false);

        $presenter = new CatalogPresenter($this->locale($request));
        $data = $presenter->product($product);
        $data['location'] = $product->location ? $presenter->locationCard($product->location) : null;
        $data['max_advance_days'] = (new ProductAvailability())->maxAdvanceDays($product);

        // Other things sold at the same location.
        $data['at_location'] = $product->location
            ? $product->location->products()
                ->where('is_published', true)
                ->where('id', '<>', $product->id)
                ->with('variants')
                ->get()
                ->filter(fn ($p) => CatalogPresenter::onSale($p))
                ->map(fn ($p) => [
                    'id'               => $p->id,
                    'slug'             => $p->slug,
                    'type'             => $p->product_type,
                    'title'            => $presenter->t($p->title),
                    // the marketplace card has room for a line of its own; without it every card
                    // is just a title and a price, and long titles are all the page has to show
                    'subtitle'         => $presenter->t($p->subtitle),
                    'duration_minutes' => $p->duration_minutes,
                    'image'            => CatalogPresenter::url($p->cover_image_url),
                    'min_price_cents'  => $p->variants->filter(fn ($v) => $v->is_active && !$v->pos_only)->min('price_cents'),
                ])
                ->values()
            : [];

        return $this->success($data);
    }

    /** GET /activities-module/products/{slug}/calendar?from=&to= */
    public function calendar(Request $request, string $slug): JsonResponse
    {
        $client  = $this->requireClient($request);
        $product = $this->findProduct($client->id, $slug);
        if (!$product) {
            return $this->error('Product not found', 404);
        }
        [$from, $to] = $this->range($request);
        $availability = new ProductAvailability();

        if ($product->isPackage()) {
            // A package date is open when every component is.
            $days = [];
            for ($d = $from; $d->lte($to); $d = $d->addDay()) {
                $st = $this->productDay($availability, $product, $d);
                $days[] = [
                    'date'            => $d->toDateString(),
                    'status'          => $st['bookable'] ? 'available' : 'closed',
                    'min_price_cents' => $st['bookable'] ? $product->variants->where('is_active', true)->min('price_cents') : null,
                ];
            }
        } else {
            $days = $availability->calendar($product, $from, $to);
        }

        return $this->success(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => $days]);
    }

    /** GET /activities-module/products/{slug}/day?date=Y-m-d (start times, seats) */
    public function day(Request $request, string $slug): JsonResponse
    {
        $client  = $this->requireClient($request);
        $product = $this->findProduct($client->id, $slug);
        if (!$product) {
            return $this->error('Product not found', 404);
        }
        $date = $this->dateParam($request->query('date'));
        if (!$date) {
            return $this->error('date must be Y-m-d', 422);
        }

        return $this->success(['date' => $date->toDateString()] + $this->productDay(new ProductAvailability(), $product, $date));
    }
}
