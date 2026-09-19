<?php

namespace App\Services\Activities;

use App\Models\Activity;
use App\Models\ActivityLocation;

/**
 * Locations and products as the operator edits them: every field, raw
 * values (Romanian text, prices in lei, image paths with their URLs),
 * inactive and POS-only variants included, plus the review state.
 */
class OrganizerCatalogPresenter
{
    public function location(ActivityLocation $l): array
    {
        $lodging = (array) ($l->lodging ?? []);

        return [
            'id'                 => $l->id,
            'slug'               => $l->slug,
            'public_path'        => '/locatie/' . $l->slug,
            'name'               => self::ro($l->name),
            'subtitle'           => self::ro($l->subtitle),
            'short_description'  => self::ro($l->short_description),
            'description'        => self::ro($l->description),
            'rules'              => self::ro($l->rules),
            'city_id'            => $l->marketplace_city_id,
            'category_id'        => $l->marketplace_category_id,
            'address'            => $l->address,
            'latitude'           => $l->latitude,
            'longitude'          => $l->longitude,
            'google_maps_url'    => $l->google_maps_url,
            'phone'              => $l->phone,
            'email'              => $l->email,
            'website_url'        => $l->website_url,
            'cover_image'        => self::image($l->cover_image_url),
            'gallery'            => array_map(fn ($p) => self::image($p), (array) ($l->gallery ?? [])),
            'facilities'         => array_values((array) ($l->facilities ?? [])),
            'seasons'            => array_values((array) ($l->seasons ?? [])),
            'closed_dates'       => array_values((array) ($l->closed_dates ?? [])),
            'max_advance_days'   => (int) $l->max_advance_days,
            'display_categories' => array_values((array) ($l->display_categories ?? [])),
            'faqs'               => array_values((array) ($l->faqs ?? [])),
            'lodging'            => [
                'enabled'        => (bool) ($lodging['enabled'] ?? false),
                'type'           => $lodging['type'] ?? null,
                'classification' => $lodging['classification'] ?? null,
                'check_in'       => $lodging['check_in'] ?? null,
                'check_out'      => $lodging['check_out'] ?? null,
                'price_from'     => $lodging['price_from'] ?? null,
                'description'    => self::ro($lodging['description'] ?? null),
                'policies'       => self::ro($lodging['policies'] ?? null),
                'phone'          => $lodging['phone'] ?? null,
                'email'          => $lodging['email'] ?? null,
                'facilities'     => array_values((array) ($lodging['facilities'] ?? [])),
                'gallery'        => array_map(fn ($p) => self::image($p), (array) ($lodging['gallery'] ?? [])),
                'rooms'          => array_map(fn ($r) => [
                    'name'        => self::ro($r['name'] ?? null),
                    'capacity'    => $r['capacity'] ?? null,
                    'beds'        => $r['beds'] ?? null,
                    'count'       => $r['count'] ?? null,
                    'price_from'  => $r['price_from'] ?? null,
                    'description' => self::ro($r['description'] ?? null),
                    'facilities'  => array_values((array) ($r['facilities'] ?? [])),
                    'images'      => array_map(fn ($p) => self::image($p), (array) ($r['images'] ?? [])),
                ], (array) ($lodging['rooms'] ?? [])),
                'links'          => array_values((array) ($lodging['links'] ?? [])),
            ],
            'review_status'      => $l->review_status,
            'rejection_reason'   => $l->review_status === 'rejected' ? $l->rejection_reason : null,
            'is_published'       => (bool) $l->is_published,
            'submitted_at'       => $l->submitted_at?->toIso8601String(),
            'reviewed_at'        => $l->reviewed_at?->toIso8601String(),
            'products_count'     => $l->products_count ?? $l->products()->count(),
            'updated_at'         => $l->updated_at?->toIso8601String(),
        ];
    }

    public function productCard(Activity $p): array
    {
        $prices = $p->variants->where('is_active', true)->pluck('price_cents');

        return [
            'id'              => $p->id,
            'slug'            => $p->slug,
            'type'            => $p->product_type,
            'title'           => self::ro($p->title),
            'location_id'     => $p->location_id,
            'booking_mode'    => $p->booking_mode,
            'image'           => self::image($p->cover_image_url),
            'min_price'       => $prices->isNotEmpty() ? round($prices->min() / 100, 2) : null,
            'variants_count'  => $p->variants->count(),
            'review_status'   => $p->review_status,
            'rejection_reason'=> $p->review_status === 'rejected' ? $p->rejection_reason : null,
            'is_published'    => (bool) $p->is_published,
            'pos_only'        => (bool) $p->pos_only,
            'public_path'     => self::publicPath($p),
            'updated_at'      => $p->updated_at?->toIso8601String(),
        ];
    }

    public function product(Activity $p): array
    {
        return $this->productCard($p) + [
            'access_kind'           => $p->access_kind,
            'service_type'          => $p->service_type,
            'subtitle'              => self::ro($p->subtitle),
            'short_description'     => self::ro($p->short_description),
            'description'           => self::ro($p->description),
            'category_id'           => $p->marketplace_category_id,
            'subcategory_id'        => $p->marketplace_subcategory_id,
            'city_id'               => $p->marketplace_city_id,
            'capacity_mode'         => $p->capacity_mode,
            'capacity_per_slot'     => (int) $p->capacity_per_slot,
            'daily_capacity'        => $p->daily_capacity,
            'duration_minutes'      => (int) $p->duration_minutes,
            'slot_interval_minutes' => (int) $p->slot_interval_minutes,
            'booking_lead_time_hours'  => (int) $p->booking_lead_time_hours,
            'booking_max_advance_days' => (int) $p->booking_max_advance_days,
            'use_location_schedule' => (bool) $p->use_location_schedule,
            'access_requirement'    => $p->access_requirement ?: 'none',
            'requires_vehicle_info' => (bool) $p->requires_vehicle_info,
            'issuing_company'       => $p->issuing_company ?: 'primary',
            'display_category'      => $p->display_category,
            'unit_label'            => self::ro($p->unit_label),
            'usage_terms'           => self::ro($p->usage_terms),
            'icon'                  => $p->icon,
            'meeting_point'         => $p->meeting_point,
            'languages'             => array_values((array) ($p->languages_offered ?? [])),
            'included_items'        => array_values((array) ($p->included_items ?? [])),
            'not_included'          => array_values((array) ($p->not_included ?? [])),
            'requirements'          => array_values((array) ($p->requirements ?? [])),
            'cancellation_policy'   => $p->cancellation_policy,
            'age_min'               => $p->age_min,
            'age_max'               => $p->age_max,
            'difficulty_level'      => $p->difficulty_level,
            'is_indoor'             => (bool) $p->is_indoor,
            'is_outdoor'            => (bool) $p->is_outdoor,
            'is_kid_friendly'       => (bool) $p->is_kid_friendly,
            'is_accessible'         => (bool) $p->is_accessible,
            'is_weather_sensitive'  => (bool) $p->is_weather_sensitive,
            'cover_image'           => self::image($p->cover_image_url),
            'gallery'               => array_map(fn ($x) => self::image($x), (array) ($p->gallery ?? [])),
            'variants'              => $p->variants->sortBy('sort_order')->map(fn ($v) => [
                'id'               => $v->id,
                'name'             => self::ro($v->name),
                'description'      => self::ro($v->description),
                'price'            => round($v->price_cents / 100, 2),
                'price_type'       => $v->price_type ?: 'per_person',
                'persons_min'      => $v->persons_min,
                'persons_max'      => $v->persons_max,
                'is_child'         => (bool) $v->is_child,
                'min_age'          => $v->min_age,
                'max_age'          => $v->max_age,
                'duration_minutes' => $v->duration_minutes,
                'validity_days'    => (int) ($v->validity_days ?: 1),
                'min_per_order'    => (int) $v->min_per_order,
                'max_per_order'    => (int) $v->max_per_order,
                'step_qty'         => $v->step_qty,
                'companion_label'  => $v->companion_label,
                'pos_price'        => $v->pos_price_cents !== null ? round($v->pos_price_cents / 100, 2) : null,
                'pos_only'         => (bool) $v->pos_only,
                'capacity_share'   => (int) ($v->capacity_share ?: 1),
                'is_active'        => (bool) $v->is_active,
                'is_refundable'    => (bool) $v->is_refundable,
            ])->values()->all(),
            'schedules'             => $p->schedules->map(fn ($s) => [
                'day_of_week'  => (int) $s->day_of_week,
                'open'         => substr(ProductAvailability::time($s->open_time), 0, 5),
                'close'        => substr(ProductAvailability::time($s->close_time), 0, 5),
                'season_start' => $s->season_start,
                'season_end'   => $s->season_end,
                'is_active'    => (bool) $s->is_active,
            ])->values()->all(),
            'exceptions'            => $p->scheduleExceptions->sortBy('exception_date')->map(fn ($e) => [
                'date'      => \Carbon\Carbon::parse($e->exception_date)->toDateString(),
                'is_closed' => (bool) $e->is_closed,
                'open'      => $e->open_time ? substr(ProductAvailability::time($e->open_time), 0, 5) : null,
                'close'     => $e->close_time ? substr(ProductAvailability::time($e->close_time), 0, 5) : null,
                'reason'    => $e->reason,
            ])->values()->all(),
            'addons'                => $p->addons->map(fn ($a) => [
                'id'           => $a->id,
                'name'         => self::ro($a->name),
                'price'        => round($a->price_cents / 100, 2),
                'included_qty' => (int) $a->included_qty,
                'max_per_unit' => (int) $a->max_per_unit,
                'is_active'    => (bool) $a->is_active,
            ])->values()->all(),
            'package_items'         => $p->isPackage() ? $p->packageItems->map(fn ($i) => [
                'product_id'      => $i->component_activity_id,
                'product_title'   => self::ro($i->component?->title),
                'variant_id'      => $i->component_variant_id,
                'variant_name'    => self::ro($i->componentVariant?->name),
                'quantity'        => (int) $i->quantity,
                'allocated_price' => $i->allocated_price_cents !== null ? round($i->allocated_price_cents / 100, 2) : null,
            ])->values()->all() : [],
        ];
    }

    /** Where a product shows on the site. */
    public static function publicPath(Activity $p): ?string
    {
        if ($p->product_type === Activity::TYPE_EXPERIENCE) {
            return '/experienta/' . $p->slug;
        }
        return $p->location ? '/locatie/' . $p->location->slug : null;
    }

    public static function image(?string $path): ?array
    {
        return $path ? ['path' => $path, 'url' => CatalogPresenter::url($path)] : null;
    }

    public static function ro($value): ?string
    {
        if (is_array($value)) {
            $value = $value['ro'] ?? null;
        }
        return is_string($value) && $value !== '' ? $value : null;
    }
}
