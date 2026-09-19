<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Activities;

use App\Models\Activity;
use App\Models\ActivityLocation;
use App\Services\Activities\CatalogPresenter;
use App\Services\Activities\ProductAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Loading and availability helpers shared by the public location and
 * product endpoints of the activities module.
 */
trait LoadsCatalog
{
    private function productRelations(): array
    {
        return [
            'variants', 'schedules', 'scheduleExceptions', 'location', 'addons', 'organizer.marketplaceClient',
            'packageItems.component.variants', 'packageItems.component.schedules',
            'packageItems.component.scheduleExceptions', 'packageItems.component.location',
            'packageItems.componentVariant',
        ];
    }

    private function findLocation(int $clientId, string $slug): ?ActivityLocation
    {
        return ActivityLocation::visible()
            ->where('marketplace_client_id', $clientId)
            ->where('slug', $slug)
            ->with([
                'city', 'category', 'organizer',
                'products' => fn ($q) => $q->where('is_published', true)->with($this->productRelations()),
            ])
            ->first();
    }

    private function findProduct(int $clientId, string $slug): ?Activity
    {
        $product = Activity::where('marketplace_client_id', $clientId)
            ->where('slug', $slug)
            ->with(array_merge($this->productRelations(), ['location.city', 'location.category']))
            ->first();

        if (!$product || !CatalogPresenter::onSale($product)) {
            return null;
        }
        $location = $product->location;
        if ($location && (!$location->is_published || $location->review_status !== ActivityLocation::REVIEW_APPROVED)) {
            return null;
        }
        return $product;
    }

    /**
     * A product's availability on one date: day tickets → seats left; timed
     * products → start times (per variant when durations differ); packages →
     * each component, and whether the package can be bought at all.
     */
    private function productDay(ProductAvailability $availability, Activity $product, CarbonImmutable $date): array
    {
        if ($product->isPackage()) {
            $components = [];
            $bookable = $product->packageItems->isNotEmpty();
            foreach ($product->packageItems as $item) {
                $component = $item->component;
                if (!$component) {
                    $bookable = false;
                    continue;
                }
                $variant = $item->componentVariant;
                if ($component->isDayMode()) {
                    $st = $availability->day($component, $date);
                    $need = max(1, (int) $item->quantity) * max(1, (int) ($variant?->capacity_share ?: 1));
                    $ok = $st['bookable'] && ($st['remaining'] === null || $st['remaining'] >= $need);
                    $components[] = ['item_id' => $item->id, 'mode' => 'day', 'bookable' => $ok, 'reason' => $st['reason'], 'remaining' => $st['remaining']];
                } else {
                    $slots = $availability->slots($component, $date, $variant);
                    $ok = collect($slots)->where('is_bookable', true)->isNotEmpty();
                    $components[] = ['item_id' => $item->id, 'mode' => 'slot', 'bookable' => $ok, 'slots' => $slots];
                }
                $bookable = $bookable && $ok;
            }
            return ['mode' => 'package', 'bookable' => $bookable, 'components' => $components];
        }

        if ($product->isDayMode()) {
            $st = $availability->day($product, $date);
            return ['mode' => 'day', 'bookable' => $st['bookable'], 'reason' => $st['reason'], 'remaining' => $st['remaining']];
        }

        $default = $availability->slots($product, $date);
        $byVariant = [];
        foreach ($product->variants as $variant) {
            if ($variant->is_active && !$variant->pos_only && $variant->duration_minutes && $variant->duration_minutes !== (int) $product->duration_minutes) {
                $byVariant[$variant->id] = $availability->slots($product, $date, $variant);
            }
        }
        return [
            'mode'              => 'slot',
            'bookable'          => collect($default)->where('is_bookable', true)->isNotEmpty()
                || collect($byVariant)->flatten(1)->where('is_bookable', true)->isNotEmpty(),
            'slots'             => $default,
            'slots_by_variant'  => (object) $byVariant,
        ];
    }

    private function dateParam($value): ?CarbonImmutable
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value, ProductAvailability::TIMEZONE)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** ?from=&to= → [from, to], default today + 34 days, at most 62 days. */
    private function range(Request $request): array
    {
        $today = CarbonImmutable::now(ProductAvailability::TIMEZONE)->startOfDay();
        $from  = $this->dateParam($request->query('from')) ?? $today;
        if ($from->lt($today)) {
            $from = $today;
        }
        $to = $this->dateParam($request->query('to')) ?? $from->addDays(34);
        if ($to->lt($from)) {
            $to = $from;
        }
        if ($from->diffInDays($to) > 62) {
            $to = $from->addDays(62);
        }
        return [$from, $to];
    }

    private function locale(Request $request): string
    {
        $locale = (string) $request->query('locale', 'ro');
        return in_array($locale, ['ro', 'en', 'hu', 'de', 'fr', 'es'], true) ? $locale : 'ro';
    }
}
