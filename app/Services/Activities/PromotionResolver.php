<?php

namespace App\Services\Activities;

use App\Models\Activity;
use App\Models\ActivityLocation;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceCity;
use App\Models\ServiceOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Paid promotions of the activities module (bilete.online): which products and
 * locations are promoted right now, and where.
 *
 * Read from the service orders themselves instead of flags on the rows: an
 * order counts while it is active and today falls inside its paid period
 * (service_start_date..service_end_date, both inclusive, Europe/Bucharest), so
 * a promotion bought for next week starts next week and one that ran out stops
 * on its own, with nothing to keep in sync.
 *
 *   - "featuring" with config.activity_id promotes one product;
 *   - "location_featuring" with config.location_id promotes the location and,
 *     in the listings, every product sold there.
 *
 * Placements: home_hero, home_recommendations, category (the product's or the
 * location's category page), city (its city page). Each placement shows at most
 * CAPACITY entries at a time; ServiceOrderController refuses an order that would
 * go over it, so the cap only trims legacy or admin-made overlaps.
 *
 * Only used by activities-module code (routes/activities.php, and branches
 * gated on the microservice), so no other marketplace ever runs it.
 */
class PromotionResolver
{
    public const PLACEMENTS = ['home_hero', 'home_recommendations', 'category', 'city'];

    /** Simultaneous promotions per placement (category and city: per category / per city). */
    public const CAPACITY = [
        'home_hero'            => 3,
        'home_recommendations' => 8,
        'category'             => 4,
        'city'                 => 4,
    ];

    /** Orders that still hold a slot: paid and running, or paid and waiting for their start date. */
    private const HOLDING = [ServiceOrder::STATUS_PROCESSING, ServiceOrder::STATUS_ACTIVE];

    private const TTL = 300;

    public static function cacheKey(int $clientId): string
    {
        return 'am_promotions_' . $clientId;
    }

    public static function forget(?int $clientId): void
    {
        if ($clientId) {
            Cache::forget(self::cacheKey($clientId));
        }
    }

    /**
     * The promotions running today, oldest paid first:
     * [['kind' => 'product'|'location', 'id' => int, 'placements' => [..], 'city_id' => ?int, 'category_ids' => [..], 'location_id' => ?int], ...]
     */
    public function running(int $clientId): array
    {
        return Cache::remember(self::cacheKey($clientId), self::TTL, function () use ($clientId) {
            $today = CarbonImmutable::now('Europe/Bucharest')->toDateString();

            $orders = ServiceOrder::where('marketplace_client_id', $clientId)
                ->whereIn('service_type', [ServiceOrder::TYPE_FEATURING, ServiceOrder::TYPE_LOCATION_FEATURING])
                ->where('status', ServiceOrder::STATUS_ACTIVE)
                ->whereDate('service_start_date', '<=', $today)
                ->whereDate('service_end_date', '>=', $today)
                ->orderBy('paid_at')->orderBy('id')
                ->get(['id', 'service_type', 'config']);

            return $this->describe($clientId, $orders);
        });
    }

    /** Product ids promoted on the given placements (directly, or through their promoted location). */
    public function productIds(int $clientId, array $placements): array
    {
        $ids = [];
        foreach ($this->running($clientId) as $p) {
            if (!array_intersect($placements, $p['placements'])) {
                continue;
            }
            if ($p['kind'] === 'product') {
                $ids[] = $p['id'];
            } else {
                array_push($ids, ...$p['product_ids']);
            }
        }
        return array_values(array_unique($ids));
    }

    /** Location ids promoted on the given placements. */
    public function locationIds(int $clientId, array $placements): array
    {
        $ids = [];
        foreach ($this->running($clientId) as $p) {
            if ($p['kind'] === 'location' && array_intersect($placements, $p['placements'])) {
                $ids[] = $p['id'];
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Entries for one placement slot, capped, rotated every hour so every
     * promoted item gets the first position in turn. For category/city the
     * slug narrows to that page.
     */
    public function forPlacement(int $clientId, string $placement, ?string $categorySlug = null, ?string $citySlug = null): array
    {
        if (!in_array($placement, self::PLACEMENTS, true)) {
            return [];
        }

        $categoryIds = null;
        if ($placement === 'category') {
            $categoryIds = $this->categoryFamily($clientId, (string) $categorySlug);
            if (!$categoryIds) {
                return [];
            }
        }
        $cityId = null;
        if ($placement === 'city') {
            $cityId = $citySlug ? MarketplaceCity::where('marketplace_client_id', $clientId)->where('slug', $citySlug)->value('id') : null;
            if (!$cityId) {
                return [];
            }
        }

        $entries = array_values(array_filter($this->running($clientId), function ($p) use ($placement, $categoryIds, $cityId) {
            if (!in_array($placement, $p['placements'], true)) {
                return false;
            }
            if ($categoryIds !== null && !array_intersect($categoryIds, $p['category_ids'])) {
                return false;
            }
            if ($cityId !== null && (int) $p['city_id'] !== (int) $cityId) {
                return false;
            }
            return true;
        }));

        $entries = array_slice($entries, 0, self::CAPACITY[$placement]);
        $hour = CarbonImmutable::now('Europe/Bucharest')->format('YmdH');
        usort($entries, fn ($a, $b) => crc32($hour . $a['kind'] . $a['id']) <=> crc32($hour . $b['kind'] . $b['id']));

        return $entries;
    }

    /**
     * Placements that are already full for some day of [start, end] for this
     * subject (its category / city for those placements). Used before an
     * order is created. $ignoreOrderId skips the order being re-checked.
     */
    public function fullPlacements(int $clientId, array $placements, string $start, string $end, ?Activity $product, ?ActivityLocation $location): array
    {
        $subject = $product ? $this->subjectOfProduct($product) : ($location ? $this->subjectOfLocation($location) : null);
        if (!$subject) {
            return [];
        }

        $orders = ServiceOrder::where('marketplace_client_id', $clientId)
            ->whereIn('service_type', [ServiceOrder::TYPE_FEATURING, ServiceOrder::TYPE_LOCATION_FEATURING])
            ->whereIn('status', self::HOLDING)
            ->whereDate('service_start_date', '<=', $end)
            ->whereDate('service_end_date', '>=', $start)
            ->get(['id', 'service_type', 'config', 'service_start_date', 'service_end_date']);
        $described = collect($this->describe($clientId, $orders, true));

        $full = [];
        foreach (array_intersect($placements, self::PLACEMENTS) as $placement) {
            $rivals = $described->filter(function ($p) use ($placement, $subject) {
                if (!in_array($placement, $p['placements'], true)) {
                    return false;
                }
                if ($placement === 'category') {
                    return (bool) array_intersect($subject['category_ids'], $p['category_ids']);
                }
                if ($placement === 'city') {
                    return $subject['city_id'] && (int) $p['city_id'] === (int) $subject['city_id'];
                }
                return true;
            });
            // Busiest day of the requested period.
            $day = CarbonImmutable::parse($start);
            $last = CarbonImmutable::parse($end);
            while ($day <= $last) {
                $d = $day->toDateString();
                $count = $rivals->filter(fn ($p) => $p['start'] <= $d && $p['end'] >= $d)->count();
                if ($count >= self::CAPACITY[$placement]) {
                    $full[] = $placement;
                    break;
                }
                $day = $day->addDay();
            }
        }
        return $full;
    }

    // ------------------------------------------------------------------

    /** Turn orders into promotion entries, dropping those whose product or location is gone or off sale. */
    private function describe(int $clientId, Collection $orders, bool $withDates = false): array
    {
        $productIds = [];
        $locationIds = [];
        foreach ($orders as $o) {
            $cfg = (array) $o->config;
            if ($o->service_type === ServiceOrder::TYPE_FEATURING && !empty($cfg['activity_id'])) {
                $productIds[] = (int) $cfg['activity_id'];
            } elseif ($o->service_type === ServiceOrder::TYPE_LOCATION_FEATURING && !empty($cfg['location_id'])) {
                $locationIds[] = (int) $cfg['location_id'];
            }
        }

        $products = $productIds
            ? Activity::where('marketplace_client_id', $clientId)->whereIn('id', array_unique($productIds))
                ->with('location:id,marketplace_city_id,is_published,review_status')
                ->get(['id', 'location_id', 'marketplace_city_id', 'marketplace_category_id', 'marketplace_subcategory_id', 'is_published', 'pos_only', 'review_status'])
                ->keyBy('id')
            : collect();
        $locations = $locationIds
            ? ActivityLocation::visible()->where('marketplace_client_id', $clientId)->whereIn('id', array_unique($locationIds))
                ->with(['products' => fn ($q) => $q->where('is_published', true)->select(['id', 'location_id', 'is_published', 'pos_only', 'review_status'])])
                ->get(['id', 'marketplace_city_id', 'marketplace_category_id'])
                ->keyBy('id')
            : collect();

        $out = [];
        foreach ($orders as $o) {
            $cfg = (array) $o->config;
            $placements = array_values(array_intersect((array) ($cfg['locations'] ?? []), self::PLACEMENTS));
            if (!$placements) {
                continue;
            }
            $entry = null;
            if ($o->service_type === ServiceOrder::TYPE_FEATURING && !empty($cfg['activity_id'])) {
                $p = $products->get((int) $cfg['activity_id']);
                if ($p && CatalogPresenter::onSale($p) && (!$p->location || ($p->location->is_published && $p->location->review_status === ActivityLocation::REVIEW_APPROVED))) {
                    $entry = ['kind' => 'product', 'id' => $p->id, 'product_ids' => [$p->id]] + $this->subjectOfProduct($p);
                }
            } elseif ($o->service_type === ServiceOrder::TYPE_LOCATION_FEATURING && !empty($cfg['location_id'])) {
                $l = $locations->get((int) $cfg['location_id']);
                if ($l) {
                    $entry = ['kind' => 'location', 'id' => $l->id, 'product_ids' => $l->products->filter(fn ($p) => CatalogPresenter::onSale($p))->pluck('id')->all()] + $this->subjectOfLocation($l);
                }
            }
            if (!$entry) {
                continue;
            }
            $entry['placements'] = $placements;
            $entry['order_id'] = $o->id;
            if ($withDates) {
                $entry['start'] = $o->service_start_date?->toDateString() ?? '0000-00-00';
                $entry['end'] = $o->service_end_date?->toDateString() ?? '9999-12-31';
            }
            $out[] = $entry;
        }
        return $out;
    }

    private function subjectOfProduct(Activity $p): array
    {
        return [
            'city_id'      => $p->marketplace_city_id ?: $p->location?->marketplace_city_id,
            'category_ids' => array_values(array_filter([(int) $p->marketplace_category_id, (int) $p->marketplace_subcategory_id])),
        ];
    }

    private function subjectOfLocation(ActivityLocation $l): array
    {
        return [
            'city_id'      => $l->marketplace_city_id,
            'category_ids' => array_values(array_filter([(int) $l->marketplace_category_id])),
        ];
    }

    /** The category with this slug plus its direct subcategories (a parent page shows its children too). */
    private function categoryFamily(int $clientId, string $slug): array
    {
        if ($slug === '') {
            return [];
        }
        $id = MarketplaceCategory::where('marketplace_client_id', $clientId)->where('slug', $slug)->value('id');
        if (!$id) {
            return [];
        }
        $children = MarketplaceCategory::where('marketplace_client_id', $clientId)->where('parent_id', $id)->pluck('id')->all();
        return array_map('intval', array_merge([$id], $children));
    }
}
