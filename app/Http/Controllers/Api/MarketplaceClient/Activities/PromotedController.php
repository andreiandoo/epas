<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Activities;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\Activity;
use App\Models\ActivityLocation;
use App\Services\Activities\CatalogPresenter;
use App\Services\Activities\PromotionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paid placements of the activities module (bilete.online): the products and
 * locations promoted right now on one placement, ready to draw as cards.
 *
 * GET /activities-module/promoted?placement=home_hero|home_recommendations|category|city&category=&city=
 *
 * Behind `marketplace.microservice:activities-module` like the rest of
 * routes/activities.php. An empty list means "nothing promoted here": the site
 * hides the block.
 */
class PromotedController extends BaseController
{
    public function index(Request $request, PromotionResolver $resolver): JsonResponse
    {
        $client = $this->requireClient($request);
        $presenter = new CatalogPresenter((string) $request->query('locale', 'ro'));
        $placement = (string) $request->query('placement', '');

        if (!in_array($placement, PromotionResolver::PLACEMENTS, true)) {
            return $this->error('placement must be one of: ' . implode(', ', PromotionResolver::PLACEMENTS), 422);
        }

        $entries = $resolver->forPlacement(
            $client->id,
            $placement,
            $request->query('category') ? (string) $request->query('category') : null,
            $request->query('city') ? (string) $request->query('city') : null,
        );

        $productIds = array_column(array_filter($entries, fn ($e) => $e['kind'] === 'product'), 'id');
        $locationIds = array_column(array_filter($entries, fn ($e) => $e['kind'] === 'location'), 'id');

        $products = $productIds
            ? Activity::whereIn('id', $productIds)->where('marketplace_client_id', $client->id)
                ->with(['variants', 'city', 'category', 'location.city'])->get()->keyBy('id')
            : collect();
        $locations = $locationIds
            ? ActivityLocation::visible()->whereIn('id', $locationIds)->where('marketplace_client_id', $client->id)
                ->with(['city', 'category', 'products' => fn ($q) => $q->where('is_published', true)->with('variants')])->get()->keyBy('id')
            : collect();

        $items = [];
        foreach ($entries as $e) {
            if ($e['kind'] === 'product' && ($p = $products->get($e['id']))) {
                $items[] = $this->productCard($presenter, $p);
            } elseif ($e['kind'] === 'location' && ($l = $locations->get($e['id']))) {
                $card = $presenter->locationCard($l);
                $items[] = [
                    'kind'            => 'location',
                    'id'              => $card['id'],
                    'slug'            => $card['slug'],
                    'title'           => $card['name'],
                    'subtitle'        => $card['subtitle'] ?: $card['short_description'],
                    'image'           => $card['cover_image'],
                    'href'            => '/locatie/' . $card['slug'],
                    'type'            => null,
                    'city'            => $card['city'],
                    'category'        => $card['category'],
                    'location'        => null,
                    'price_from_cents' => $card['min_price_cents'],
                    'currency'        => $card['currency'],
                    'duration_minutes' => null,
                ];
            }
        }

        return $this->success(['placement' => $placement, 'items' => $items]);
    }

    private function productCard(CatalogPresenter $presenter, Activity $p): array
    {
        $prices = $p->variants->filter(fn ($v) => $v->is_active && !$v->pos_only)->pluck('price_cents');
        $location = $p->location;
        $city = $p->city ?: $location?->city;

        return [
            'kind'     => 'product',
            'id'       => $p->id,
            'slug'     => $p->slug,
            'title'    => $presenter->t($p->title),
            'subtitle' => $presenter->t($p->subtitle) ?: $presenter->t($p->short_description),
            'image'    => CatalogPresenter::url($p->cover_image_url) ?: ($location ? CatalogPresenter::url($location->cover_image_url) : null),
            // Experiences have their own page; access tickets and packages are bought on their location's page.
            'href'     => $p->product_type === Activity::TYPE_EXPERIENCE || !$location
                ? '/experienta/' . $p->slug
                : '/locatie/' . $location->slug,
            'type'     => $p->product_type,
            'city'     => $city ? ['id' => $city->id, 'name' => $presenter->t($city->name), 'slug' => $city->slug] : null,
            'category' => $p->category ? ['id' => $p->category->id, 'name' => $presenter->t($p->category->name), 'slug' => $p->category->slug] : null,
            'location' => $location ? ['id' => $location->id, 'name' => $presenter->t($location->name), 'slug' => $location->slug] : null,
            'price_from_cents' => $prices->isNotEmpty() ? (int) $prices->min() : ($p->cheapest_price_cents ?: null),
            'currency' => $p->currency ?: ($p->variants->first()?->currency ?: null),
            'duration_minutes' => (int) $p->duration_minutes ?: null,
        ];
    }
}
