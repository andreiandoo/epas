<?php

namespace App\Services\Activities;

use App\Models\Activity;
use App\Models\ActivityAddon;
use App\Models\ActivityLocation;
use App\Models\ActivityVariant;
use Illuminate\Support\Facades\Storage;

/**
 * Public shapes of locations and products for the marketplace site
 * (activities module). Only what a visitor may see: no POS prices, no
 * POS-only products or variants, no review data.
 */
class CatalogPresenter
{
    public function __construct(private string $locale = 'ro')
    {
    }

    public function locationCard(ActivityLocation $location): array
    {
        $products = $location->relationLoaded('products') ? $location->products->filter(fn ($p) => self::onSale($p)) : collect();
        $prices   = $products->flatMap(fn ($p) => $p->variants->filter(fn ($v) => $v->is_active && !$v->pos_only)->pluck('price_cents'));

        return [
            'id'                => $location->id,
            'slug'              => $location->slug,
            'name'              => $this->t($location->name),
            'subtitle'          => $this->t($location->subtitle),
            'short_description' => $this->t($location->short_description),
            'city'              => $location->city ? ['id' => $location->city->id, 'name' => $this->t($location->city->name), 'slug' => $location->city->slug] : null,
            'category'          => $location->category ? ['id' => $location->category->id, 'name' => $this->t($location->category->name), 'slug' => $location->category->slug] : null,
            'cover_image'       => self::url($location->cover_image_url),
            'latitude'          => $location->latitude,
            'longitude'         => $location->longitude,
            'counts'            => [
                'access'     => $products->where('product_type', Activity::TYPE_ACCESS)->count(),
                'experience' => $products->where('product_type', Activity::TYPE_EXPERIENCE)->count(),
                'package'    => $products->where('product_type', Activity::TYPE_PACKAGE)->count(),
            ],
            'min_price_cents'   => $prices->min(),
            'has_lodging'       => !empty($location->lodging['enabled']),
        ];
    }

    public function location(ActivityLocation $location): array
    {
        $products = $location->products->filter(fn ($p) => self::onSale($p))->sortBy('id')->values();

        return $this->locationCard($location) + [
            'description'        => $this->t($location->description),
            'rules'              => $this->t($location->rules),
            'address'            => $location->address,
            'google_maps_url'    => $location->google_maps_url,
            'contact'            => [
                'phone'   => $location->phone,
                'email'   => $location->email,
                'website' => $location->website_url,
            ],
            'gallery'            => array_values(array_filter(array_map(fn ($p) => self::url($p), (array) ($location->gallery ?? [])))),
            'facilities'         => array_values((array) ($location->facilities ?? [])),
            'seasons'            => array_map(fn ($s) => [
                'name'       => $s['name'] ?? null,
                'start'      => $s['start'] ?? null,
                'end'        => $s['end'] ?? null,
                'last_entry' => $s['last_entry'] ?? null,
                'schedule'   => $s['schedule'] ?? [],
            ], (array) ($location->seasons ?? [])),
            'closed_dates'       => array_values(array_filter((array) ($location->closed_dates ?? []), fn ($d) => $d >= now('Europe/Bucharest')->toDateString())),
            'max_advance_days'   => (int) $location->max_advance_days,
            'display_categories' => collect((array) ($location->display_categories ?? []))
                ->map(fn ($c) => ['id' => (string) ($c['id'] ?? ''), 'name' => $this->t($c['name'] ?? ''), 'sort_order' => (int) ($c['sort_order'] ?? 0)])
                ->filter(fn ($c) => $c['id'] !== '')
                ->sortBy('sort_order')->values()->all(),
            'lodging'            => $this->lodging($location->lodging),
            'faqs'               => array_values((array) ($location->faqs ?? [])),
            'seo'                => $location->seo,
            'organizer'          => $location->organizer ? ['id' => $location->organizer->id, 'name' => $location->organizer->name, 'slug' => $location->organizer->slug ?? null] : null,
            'products'           => $products->map(fn ($p) => $this->product($p))->all(),
        ];
    }

    /** A product as sold online (access ticket, experience or package). */
    public function product(Activity $product): array
    {
        return [
            'id'                    => $product->id,
            'slug'                  => $product->slug,
            'type'                  => $product->product_type,
            'access_kind'           => $product->access_kind,
            'service_type'          => $product->service_type,
            'title'                 => $this->t($product->title),
            'subtitle'              => $this->t($product->subtitle),
            'short_description'     => $this->t($product->short_description),
            'description'           => $this->t($product->description),
            'icon'                  => $product->icon,
            'image'                 => self::url($product->cover_image_url),
            'gallery'               => array_values(array_filter(array_map(fn ($p) => self::url($p), (array) ($product->gallery ?? [])))),
            'booking_mode'          => $product->booking_mode,
            'capacity_mode'         => $product->capacity_mode,
            'duration_minutes'      => (int) $product->duration_minutes,
            'unit_label'            => $this->t($product->unit_label),
            'usage_terms'           => $this->t($product->usage_terms),
            'display_category'      => $product->display_category,
            'access_requirement'    => $product->access_requirement ?: Activity::REQUIRES_NONE,
            'requires_vehicle_info' => (bool) $product->requires_vehicle_info,
            'included_items'        => array_values((array) ($product->included_items ?? [])),
            'not_included'          => array_values((array) ($product->not_included ?? [])),
            'requirements'          => array_values((array) ($product->requirements ?? [])),
            'meeting_point'         => $product->meeting_point,
            'languages'             => array_values((array) ($product->languages_offered ?? [])),
            'cancellation_policy'   => $product->cancellation_policy,
            'age_min'               => $product->age_min,
            'age_max'               => $product->age_max,
            'location_id'           => $product->location_id,
            // What the site shows as the service commission (added on top
            // of the prices, or already inside them).
            'commission'            => [
                'rate'  => $product->organizer ? (float) $product->organizer->getEffectiveCommissionRate() : 0.0,
                'mode'  => $product->organizer ? $product->organizer->getEffectiveCommissionMode() : 'included',
                // never less than this per unit sold, so the page shows the same total as the checkout charges
                'floor' => ActivityCommission::floor($product->organizer),
            ],
            'variants'              => $product->variants
                ->filter(fn ($v) => $v->is_active && !$v->pos_only)
                ->sortBy('sort_order')
                ->map(fn ($v) => $this->variant($v))
                ->values()->all(),
            'addons'                => $product->relationLoaded('addons')
                ? $product->addons->where('is_active', true)->map(fn ($a) => $this->addon($a))->values()->all()
                : [],
            'components'            => $product->isPackage() && $product->relationLoaded('packageItems')
                ? $product->packageItems->map(fn ($i) => [
                    'item_id'      => $i->id,
                    'product_id'   => $i->component_activity_id,
                    'title'        => $this->t($i->component?->title),
                    'type'         => $i->component?->product_type,
                    'booking_mode' => $i->component?->booking_mode,
                    'variant'      => $i->componentVariant ? $this->t($i->componentVariant->name) : null,
                    'is_child'     => (bool) $i->componentVariant?->is_child,
                    'quantity'     => (int) $i->quantity,
                    'unit_price_cents' => $i->componentVariant?->price_cents,
                ])->values()->all()
                : [],
        ];
    }

    public function variant(ActivityVariant $v): array
    {
        return [
            'id'               => $v->id,
            'name'             => $this->t($v->name),
            'description'      => $this->t($v->description),
            'price_cents'      => (int) $v->price_cents,
            'currency'         => $v->currency ?: 'RON',
            'price_type'       => $v->price_type ?: ActivityVariant::PRICE_PER_PERSON,
            'persons_min'      => $v->persons_min,
            'persons_max'      => $v->persons_max,
            'is_child'         => (bool) $v->is_child,
            'min_age'          => $v->min_age,
            'max_age'          => $v->max_age,
            'duration_minutes' => $v->duration_minutes,
            'validity_days'    => max(1, (int) ($v->validity_days ?: 1)),
            'min_per_order'    => max(1, (int) $v->min_per_order),
            'max_per_order'    => (int) ($v->max_per_order ?: 0) ?: null,
            'step_qty'         => $v->step_qty ?: null,
            'companion_label'  => $v->companion_label,
            'capacity_share'   => max(1, (int) ($v->capacity_share ?: 1)),
            'is_refundable'    => (bool) $v->is_refundable,
        ];
    }

    public function addon(ActivityAddon $a): array
    {
        return [
            'id'           => $a->id,
            'name'         => $this->t($a->name),
            'price_cents'  => (int) $a->price_cents,
            'included_qty' => (int) $a->included_qty,
            'max_per_unit' => (int) $a->max_per_unit,
        ];
    }

    /** Accommodation info (no inventory: the operator's own booking links). */
    public function lodging(?array $lodging): ?array
    {
        if (empty($lodging['enabled'])) {
            return null;
        }
        return [
            'type'           => $lodging['type'] ?? null,
            'classification' => $lodging['classification'] ?? null,
            'description'    => $this->t($lodging['description'] ?? null),
            'check_in'       => $lodging['check_in'] ?? null,
            'check_out'      => $lodging['check_out'] ?? null,
            'price_from'     => $lodging['price_from'] ?? null,
            'facilities'     => array_values((array) ($lodging['facilities'] ?? [])),
            'policies'       => $this->t($lodging['policies'] ?? null),
            'rooms'          => array_values(array_map(fn ($r) => [
                'name'        => $this->t($r['name'] ?? ''),
                'description' => $this->t($r['description'] ?? null),
                'capacity'    => $r['capacity'] ?? null,
                'beds'        => $r['beds'] ?? null,
                'count'       => $r['count'] ?? null,
                'price_from'  => $r['price_from'] ?? null,
                'facilities'  => array_values((array) ($r['facilities'] ?? [])),
                'images'      => array_values(array_filter(array_map(fn ($p) => self::url($p), (array) ($r['images'] ?? [])))),
            ], (array) ($lodging['rooms'] ?? []))),
            'gallery'        => array_values(array_filter(array_map(fn ($p) => self::url($p), (array) ($lodging['gallery'] ?? [])))),
            'links'          => array_values(array_filter(array_map(fn ($l) => !empty($l['url']) && preg_match('#^https?://#i', $l['url']) ? [
                'platform' => $l['platform'] ?? 'website',
                'label'    => $l['label'] ?? null,
                'url'      => $l['url'],
            ] : null, (array) ($lodging['links'] ?? [])))),
            'phone'          => $lodging['phone'] ?? null,
            'email'          => $lodging['email'] ?? null,
        ];
    }

    /** Sold online now: published, approved (or admin-made), not POS-only. */
    public static function onSale(Activity $product): bool
    {
        return $product->is_published
            && !$product->pos_only
            && ($product->review_status === null || $product->review_status === 'approved');
    }

    public function t($value): ?string
    {
        if (is_array($value)) {
            $value = $value[$this->locale] ?? $value['ro'] ?? $value['en'] ?? (array_values(array_filter($value))[0] ?? null);
        }
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    public static function url(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return Storage::disk('public')->url($path);
    }
}
