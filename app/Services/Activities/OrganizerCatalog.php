<?php

namespace App\Services\Activities;

use App\Models\Activity;
use App\Models\ActivityAddon;
use App\Models\ActivityLocation;
use App\Models\ActivityPackageItem;
use App\Models\ActivitySchedule;
use App\Models\ActivityScheduleException;
use App\Models\ActivityVariant;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceCity;
use App\Models\MarketplaceOrganizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Mews\Purifier\Facades\Purifier;

/**
 * What an operator may write about their locations and products (activities
 * module), how it is validated and how it is stored. Commission, SEO slugs and
 * review decisions stay with the marketplace admin.
 */
class OrganizerCatalog
{
    /**
     * What a location can offer. The first thirteen are the original list and
     * never change value; the rest were added for the leisure venues (2026-09).
     * On top of these an operator may add their own, stored as
     * "custom:<label>" — see self::CUSTOM_FACILITY.
     */
    public const FACILITIES = [
        'parking', 'toilets', 'restaurant', 'accessible', 'playground', 'wifi', 'pets',
        'lodging', 'camping', 'rentals', 'guide', 'shop', 'card',
        'free_parking', 'bus_parking', 'bike_parking', 'ev_charging', 'atm', 'lockers',
        'changing_rooms', 'showers', 'terrace', 'bar', 'picnic', 'bbq', 'gazebo',
        'drinking_water', 'first_aid', 'boat_ramp', 'beach', 'pool', 'sauna',
        'audio_guide', 'stroller', 'baby_change', 'smoking_area', 'luggage', 'fishing',
    ];

    public const LODGING_FACILITIES = [
        'wifi', 'parking', 'breakfast', 'restaurant', 'kitchen', 'ac', 'heating', 'private_bathroom',
        'tv', 'pets', 'pool', 'spa', 'terrace', 'bbq', 'playground', 'accessible',
        'fridge', 'kettle', 'safe', 'towels', 'washing_machine', 'balcony', 'garden',
        'sauna', 'fireplace', 'crib', 'ev_charging', 'non_smoking',
    ];

    /** Prefix of a facility the operator wrote themselves: "custom:Rampă de barcă". */
    public const CUSTOM_FACILITY = 'custom:';

    /** At most this many facilities on a location (or on its lodging). */
    private const FACILITY_MAX = 40;

    public const LODGING_TYPES = ['pensiune', 'hotel', 'cabana', 'vila', 'apartamente', 'camping', 'glamping', 'altele'];

    public const LINK_PLATFORMS = ['booking', 'airbnb', 'travelminit', 'website', 'other'];

    private const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    private const HM = 'regex:/^([01]\d|2[0-3]):[0-5]\d$/';

    private const MD = 'regex:/^(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/';

    public function __construct(private MarketplaceOrganizer $organizer)
    {
    }

    /** One of the known keys, or the operator's own "custom:<label>". */
    private static function facilityRule(array $known): string
    {
        return 'regex:/^(?:' . implode('|', $known) . '|' . preg_quote(self::CUSTOM_FACILITY, '/') . '[^\x00-\x1F]{1,40})$/u';
    }

    /**
     * Known keys kept as they are, the operator's own ones trimmed to
     * "custom:<label>"; anything else dropped. Order is the one that came in,
     * duplicates removed.
     */
    private function facilities($in, array $known): array
    {
        $out = [];
        foreach ((array) $in as $value) {
            $value = is_string($value) ? trim($value) : '';
            if ($value === '') {
                continue;
            }
            if (str_starts_with($value, self::CUSTOM_FACILITY)) {
                $label = trim(preg_replace('/\s+/u', ' ', substr($value, strlen(self::CUSTOM_FACILITY))));
                if ($label === '') {
                    continue;
                }
                $value = self::CUSTOM_FACILITY . mb_substr($label, 0, 40);
            } elseif (!in_array($value, $known, true)) {
                continue;
            }
            $out[$value] = true;
        }

        return array_slice(array_keys($out), 0, self::FACILITY_MAX);
    }

    // ================================================================
    // Locations
    // ================================================================

    public function validateLocation(array $input): array
    {
        $v = Validator::make($input, [
            'name'               => 'required|string|max:190',
            'subtitle'           => 'nullable|string|max:190',
            'short_description'  => 'nullable|string|max:280',
            'description'        => 'nullable|string|max:20000',
            'rules'              => 'nullable|string|max:6000',
            'city_id'            => 'nullable|integer',
            'category_id'        => 'nullable|integer',
            'address'            => 'nullable|string|max:255',
            'latitude'           => 'nullable|numeric|between:-90,90',
            'longitude'          => 'nullable|numeric|between:-180,180',
            'google_maps_url'    => 'nullable|url|max:500',
            'phone'              => 'nullable|string|max:40',
            'email'              => 'nullable|email|max:255',
            'website_url'        => 'nullable|url|max:500',
            'cover_image'        => 'nullable|string|max:255',
            'gallery'            => 'nullable|array|max:20',
            'gallery.*'          => 'string|max:255',
            'facilities'         => 'nullable|array|max:' . self::FACILITY_MAX,
            'facilities.*'       => ['string', self::facilityRule(self::FACILITIES)],
            'seasons'            => 'nullable|array|max:12',
            'seasons.*.name'     => 'required|string|max:60',
            'seasons.*.start'    => ['required', self::MD],
            'seasons.*.end'      => ['required', self::MD],
            'seasons.*.last_entry' => ['nullable', self::HM],
            'seasons.*.schedule' => 'nullable|array',
            'closed_dates'       => 'nullable|array|max:366',
            'closed_dates.*'     => 'date_format:Y-m-d',
            'max_advance_days'   => 'nullable|integer|min:1|max:365',
            'display_categories' => 'nullable|array|max:20',
            'display_categories.*.id'   => 'required|string|max:64|regex:/^[a-z0-9-]+$/',
            'display_categories.*.name' => 'required|string|max:60',
            'display_categories.*.sort_order' => 'nullable|integer|min:0|max:999',
            'faqs'               => 'nullable|array|max:30',
            'faqs.*.q'           => 'required|string|max:200',
            'faqs.*.a'           => 'required|string|max:2000',
            'lodging'            => 'nullable|array',
            'lodging.enabled'    => 'nullable|boolean',
            'lodging.type'       => 'nullable|in:' . implode(',', self::LODGING_TYPES),
            'lodging.classification' => 'nullable|string|max:40',
            'lodging.check_in'   => ['nullable', self::HM],
            'lodging.check_out'  => ['nullable', self::HM],
            'lodging.price_from' => 'nullable|numeric|min:0|max:100000',
            'lodging.description' => 'nullable|string|max:6000',
            'lodging.policies'   => 'nullable|string|max:4000',
            'lodging.phone'      => 'nullable|string|max:40',
            'lodging.email'      => 'nullable|email|max:255',
            'lodging.facilities' => 'nullable|array|max:' . self::FACILITY_MAX,
            'lodging.facilities.*' => ['string', self::facilityRule(self::LODGING_FACILITIES)],
            'lodging.gallery'    => 'nullable|array|max:20',
            'lodging.gallery.*'  => 'string|max:255',
            'lodging.rooms'      => 'nullable|array|max:30',
            'lodging.rooms.*.name' => 'required|string|max:80',
            'lodging.rooms.*.capacity' => 'nullable|integer|min:1|max:50',
            'lodging.rooms.*.beds' => 'nullable|string|max:80',
            'lodging.rooms.*.count' => 'nullable|integer|min:1|max:500',
            'lodging.rooms.*.price_from' => 'nullable|numeric|min:0|max:100000',
            'lodging.rooms.*.description' => 'nullable|string|max:1000',
            'lodging.rooms.*.facilities' => 'nullable|array',
            'lodging.rooms.*.facilities.*' => 'in:' . implode(',', self::LODGING_FACILITIES),
            'lodging.rooms.*.images' => 'nullable|array|max:8',
            'lodging.rooms.*.images.*' => 'string|max:255',
            'lodging.links'      => 'nullable|array|max:6',
            'lodging.links.*.platform' => 'required|in:' . implode(',', self::LINK_PLATFORMS),
            'lodging.links.*.url' => ['required', 'url', 'max:1000', 'regex:#^https?://#i'],
            'lodging.links.*.label' => 'nullable|string|max:60',
        ], [], ['name' => 'numele']);

        $v->after(function ($v) use ($input) {
            foreach ((array) ($input['seasons'] ?? []) as $i => $season) {
                foreach ((array) ($season['schedule'] ?? []) as $day => $hours) {
                    if (!in_array($day, self::DAYS, true)) {
                        $v->errors()->add("seasons.$i.schedule", 'Zi necunoscută în program.');
                        continue;
                    }
                    if ($hours === null || (empty($hours['open']) && empty($hours['close']))) {
                        continue;
                    }
                    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) ($hours['open'] ?? '')) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) ($hours['close'] ?? ''))
                        || $hours['open'] >= $hours['close']) {
                        $v->errors()->add("seasons.$i.schedule.$day", 'Ora de deschidere trebuie să fie înaintea celei de închidere.');
                    }
                }
            }
            $ids = array_column((array) ($input['display_categories'] ?? []), 'id');
            if (count($ids) !== count(array_unique($ids))) {
                $v->errors()->add('display_categories', 'Două categorii au același cod.');
            }
        });

        return $v->validate();
    }

    public function fillLocation(ActivityLocation $location, array $data): void
    {
        $clientId = $this->organizer->marketplace_client_id;

        $location->fill([
            'name'              => $this->tr($location->name, $data['name']),
            'subtitle'          => $this->tr($location->subtitle, $data['subtitle'] ?? null),
            'short_description' => $this->tr($location->short_description, $data['short_description'] ?? null),
            'description'       => $this->tr($location->description, $this->html($data['description'] ?? null)),
            'rules'             => $this->tr($location->rules, $this->html($data['rules'] ?? null)),
            'marketplace_city_id'     => $this->cityId($data['city_id'] ?? null),
            'marketplace_category_id' => $this->categoryId($data['category_id'] ?? null),
            'address'           => $data['address'] ?? null,
            'latitude'          => $data['latitude'] ?? null,
            'longitude'         => $data['longitude'] ?? null,
            'google_maps_url'   => $data['google_maps_url'] ?? null,
            'phone'             => $data['phone'] ?? null,
            'email'             => $data['email'] ?? null,
            'website_url'       => $data['website_url'] ?? null,
            'cover_image_url'   => $this->path($data['cover_image'] ?? null, [$location->cover_image_url]),
            'gallery'           => $this->paths($data['gallery'] ?? [], (array) ($location->gallery ?? [])),
            'facilities'        => $this->facilities($data['facilities'] ?? [], self::FACILITIES),
            'seasons'           => array_values(array_map(fn ($s) => [
                'name'       => $s['name'],
                'start'      => $s['start'],
                'end'        => $s['end'],
                'last_entry' => $s['last_entry'] ?? null,
                'schedule'   => collect(self::DAYS)->mapWithKeys(fn ($d) => [$d => !empty($s['schedule'][$d]['open']) && !empty($s['schedule'][$d]['close'])
                    ? ['open' => $s['schedule'][$d]['open'], 'close' => $s['schedule'][$d]['close']]
                    : null])->all(),
            ], (array) ($data['seasons'] ?? []))),
            'closed_dates'      => array_values(array_unique((array) ($data['closed_dates'] ?? []))),
            'max_advance_days'  => (int) ($data['max_advance_days'] ?? $location->max_advance_days ?? 90),
            'display_categories' => array_values(array_map(fn ($c) => [
                'id' => $c['id'], 'name' => $c['name'], 'sort_order' => (int) ($c['sort_order'] ?? 0),
            ], (array) ($data['display_categories'] ?? []))),
            'faqs'              => array_values(array_map(fn ($f) => ['q' => $f['q'], 'a' => $f['a']], (array) ($data['faqs'] ?? []))),
            'lodging'           => $this->lodging($data['lodging'] ?? null, (array) ($location->lodging ?? [])),
        ]);

        if (!$location->exists) {
            $location->marketplace_client_id    = $clientId;
            $location->marketplace_organizer_id = $this->organizer->id;
            $location->slug          = ActivityLocation::uniqueSlug($data['name'], $clientId);
            $location->review_status = ActivityLocation::REVIEW_DRAFT;
            $location->is_published  = false;
        }
    }

    private function lodging(?array $in, array $current): ?array
    {
        if ($in === null) {
            return $current ?: null;
        }
        if (empty($in['enabled'])) {
            return ['enabled' => false] + array_diff_key($current, ['enabled' => 1]);
        }
        $oldRoomImages = collect((array) ($current['rooms'] ?? []))->flatMap(fn ($r) => (array) ($r['images'] ?? []))->all();

        return [
            'enabled'        => true,
            'type'           => $in['type'] ?? null,
            'classification' => $in['classification'] ?? null,
            'check_in'       => $in['check_in'] ?? null,
            'check_out'      => $in['check_out'] ?? null,
            'price_from'     => isset($in['price_from']) ? (float) $in['price_from'] : null,
            'description'    => isset($in['description']) ? ['ro' => $this->html($in['description'])] : null,
            'policies'       => isset($in['policies']) ? ['ro' => $this->html($in['policies'])] : null,
            'phone'          => $in['phone'] ?? null,
            'email'          => $in['email'] ?? null,
            'facilities'     => $this->facilities($in['facilities'] ?? [], self::LODGING_FACILITIES),
            'gallery'        => $this->paths($in['gallery'] ?? [], (array) ($current['gallery'] ?? [])),
            'rooms'          => array_values(array_map(fn ($r) => [
                'name'        => ['ro' => $r['name']],
                'capacity'    => isset($r['capacity']) ? (int) $r['capacity'] : null,
                'beds'        => $r['beds'] ?? null,
                'count'       => isset($r['count']) ? (int) $r['count'] : null,
                'price_from'  => isset($r['price_from']) ? (float) $r['price_from'] : null,
                'description' => isset($r['description']) ? ['ro' => $r['description']] : null,
                'facilities'  => array_values(array_unique((array) ($r['facilities'] ?? []))),
                'images'      => $this->paths($r['images'] ?? [], $oldRoomImages),
            ], (array) ($in['rooms'] ?? []))),
            'links'          => array_values(array_map(fn ($l) => [
                'platform' => $l['platform'],
                'url'      => $l['url'],
                'label'    => $l['label'] ?? null,
            ], (array) ($in['links'] ?? []))),
        ];
    }

    // ================================================================
    // Products
    // ================================================================

    public function validateProduct(array $input, ?Activity $product = null): array
    {
        $type = $input['product_type'] ?? $product?->product_type;
        $v = Validator::make($input, [
            'product_type'          => 'required|in:access,experience,package',
            'location_id'           => [$type === 'experience' ? 'nullable' : 'required', 'integer'],
            'access_kind'           => 'nullable|in:person,vehicle,camping,other',
            'service_type'          => 'nullable|in:rental,guided,workshop,other',
            'title'                 => 'required|string|max:190',
            'subtitle'              => 'nullable|string|max:190',
            'short_description'     => 'nullable|string|max:280',
            'description'           => 'nullable|string|max:20000',
            'category_id'           => 'nullable|integer',
            'subcategory_id'        => 'nullable|integer',
            'city_id'               => 'nullable|integer',
            'booking_mode'          => 'required|in:day,slot',
            'capacity_mode'         => 'nullable|in:per_slot,concurrent',
            'capacity_per_slot'     => 'nullable|integer|min:1|max:10000',
            'daily_capacity'        => 'nullable|integer|min:1|max:1000000',
            'duration_minutes'      => 'nullable|integer|min:5|max:1440',
            'slot_interval_minutes' => 'nullable|integer|min:5|max:1440',
            'booking_lead_time_hours'  => 'nullable|integer|min:0|max:720',
            'booking_max_advance_days' => 'nullable|integer|min:1|max:365',
            'use_location_schedule' => 'nullable|boolean',
            'access_requirement'    => 'nullable|in:none,any,adult',
            'requires_vehicle_info' => 'nullable|boolean',
            'pos_only'              => 'nullable|boolean',
            'issuing_company'       => 'nullable|in:primary,secondary',
            'display_category'      => 'nullable|string|max:64',
            'unit_label'            => 'nullable|string|max:60',
            'usage_terms'           => 'nullable|string|max:2000',
            'icon'                  => 'nullable|string|max:16',
            'meeting_point'         => 'nullable|string|max:500',
            'languages'             => 'nullable|array|max:10',
            'languages.*'           => 'string|size:2',
            'included_items'        => 'nullable|array|max:20',
            'included_items.*'      => 'string|max:200',
            'not_included'          => 'nullable|array|max:20',
            'not_included.*'        => 'string|max:200',
            'requirements'          => 'nullable|array|max:20',
            'requirements.*'        => 'string|max:200',
            'cancellation_policy'   => 'nullable|string|max:2000',
            'age_min'               => 'nullable|integer|min:0|max:99',
            'age_max'               => 'nullable|integer|min:0|max:99',
            'difficulty_level'      => 'nullable|in:easy,medium,hard,expert',
            'is_indoor'             => 'nullable|boolean',
            'is_outdoor'            => 'nullable|boolean',
            'is_kid_friendly'       => 'nullable|boolean',
            'is_accessible'         => 'nullable|boolean',
            'is_weather_sensitive'  => 'nullable|boolean',
            'cover_image'           => 'nullable|string|max:255',
            'gallery'               => 'nullable|array|max:20',
            'gallery.*'             => 'string|max:255',

            'variants'                    => 'required|array|min:1|max:30',
            'variants.*.id'               => 'nullable|integer',
            'variants.*.name'             => 'required|string|max:120',
            'variants.*.description'      => 'nullable|string|max:280',
            'variants.*.price'            => 'required|numeric|min:0|max:100000',
            'variants.*.price_type'       => 'nullable|in:per_person,per_unit',
            'variants.*.persons_min'      => 'nullable|integer|min:1|max:500',
            'variants.*.persons_max'      => 'nullable|integer|min:1|max:500',
            'variants.*.is_child'         => 'nullable|boolean',
            'variants.*.min_age'          => 'nullable|integer|min:0|max:99',
            'variants.*.max_age'          => 'nullable|integer|min:0|max:99',
            'variants.*.duration_minutes' => 'nullable|integer|min:5|max:1440',
            'variants.*.validity_days'    => 'nullable|integer|min:1|max:60',
            'variants.*.min_per_order'    => 'nullable|integer|min:0|max:500',
            'variants.*.max_per_order'    => 'nullable|integer|min:1|max:500',
            'variants.*.step_qty'         => 'nullable|integer|min:1|max:100',
            'variants.*.companion_label'  => 'nullable|string|max:80',
            'variants.*.pos_price'        => 'nullable|numeric|min:0|max:100000',
            'variants.*.pos_only'         => 'nullable|boolean',
            'variants.*.capacity_share'   => 'nullable|integer|min:1|max:100',
            'variants.*.is_active'        => 'nullable|boolean',
            'variants.*.is_refundable'    => 'nullable|boolean',

            'schedules'                 => 'nullable|array|max:60',
            'schedules.*.day_of_week'   => 'required|integer|min:1|max:7',
            'schedules.*.open'          => ['required', self::HM],
            'schedules.*.close'         => ['required', self::HM],
            'schedules.*.season_start'  => ['nullable', self::MD],
            'schedules.*.season_end'    => ['nullable', self::MD],
            'schedules.*.is_active'     => 'nullable|boolean',

            'exceptions'              => 'nullable|array|max:200',
            'exceptions.*.date'       => 'required|date_format:Y-m-d',
            'exceptions.*.is_closed'  => 'nullable|boolean',
            'exceptions.*.open'       => ['nullable', self::HM],
            'exceptions.*.close'      => ['nullable', self::HM],
            'exceptions.*.reason'     => 'nullable|string|max:190',

            'addons'                => 'nullable|array|max:20',
            'addons.*.id'           => 'nullable|integer',
            'addons.*.name'         => 'required|string|max:120',
            'addons.*.price'        => 'required|numeric|min:0|max:100000',
            'addons.*.included_qty' => 'nullable|integer|min:0|max:50',
            'addons.*.max_per_unit' => 'nullable|integer|min:0|max:50',
            'addons.*.is_active'    => 'nullable|boolean',

            'package_items'                   => [$type === 'package' ? 'required' : 'nullable', 'array', 'max:20'],
            'package_items.*.product_id'      => 'required|integer',
            'package_items.*.variant_id'      => 'nullable|integer',
            'package_items.*.quantity'        => 'required|integer|min:1|max:50',
            'package_items.*.allocated_price' => 'nullable|numeric|min:0|max:100000',
        ], [], ['title' => 'titlul', 'variants' => 'biletele']);

        $v->after(function ($v) use ($input, $type, $product) {
            $location = !empty($input['location_id']) ? $this->ownLocation((int) $input['location_id']) : null;
            if (!empty($input['location_id']) && !$location) {
                $v->errors()->add('location_id', 'Locația nu există în contul tău.');
            }
            if (!empty($input['display_category']) && $location
                && !in_array($input['display_category'], array_column((array) ($location->display_categories ?? []), 'id'), true)) {
                $v->errors()->add('display_category', 'Categoria nu există la această locație.');
            }
            if (!empty($input['use_location_schedule']) && (!$location || empty($location->seasons))) {
                $v->errors()->add('use_location_schedule', 'Locația nu are sezoane cu program.');
            }
            if (($input['booking_mode'] ?? null) === 'slot' && $type !== 'package'
                && empty($input['use_location_schedule']) && empty($input['schedules'])) {
                $v->errors()->add('schedules', 'Adaugă programul în care se pot rezerva orele.');
            }
            if (($input['issuing_company'] ?? 'primary') === 'secondary' && !$this->organizer->has_secondary_issuer) {
                $v->errors()->add('issuing_company', 'Contul nu are o a doua firmă emitentă.');
            }
            foreach ((array) ($input['schedules'] ?? []) as $i => $s) {
                if (($s['open'] ?? '') >= ($s['close'] ?? '')) {
                    $v->errors()->add("schedules.$i.close", 'Ora de închidere trebuie să fie după cea de deschidere.');
                }
                if (empty($s['season_start']) !== empty($s['season_end'])) {
                    $v->errors()->add("schedules.$i.season_end", 'Sezonul are nevoie de ambele capete.');
                }
            }
            foreach ((array) ($input['variants'] ?? []) as $i => $var) {
                if (!empty($var['min_per_order']) && !empty($var['max_per_order']) && $var['min_per_order'] > $var['max_per_order']) {
                    $v->errors()->add("variants.$i.max_per_order", 'Maximul trebuie să fie cel puțin cât minimul.');
                }
                if (!empty($var['persons_min']) && !empty($var['persons_max']) && $var['persons_min'] > $var['persons_max']) {
                    $v->errors()->add("variants.$i.persons_max", 'Numărul maxim de persoane e sub minim.');
                }
                if (!empty($var['id']) && $product && !$product->variants()->withTrashed()->whereKey((int) $var['id'])->exists()) {
                    $v->errors()->add("variants.$i.id", 'Biletul nu aparține acestui produs.');
                }
            }
            foreach ((array) ($input['package_items'] ?? []) as $i => $item) {
                $component = Activity::where('marketplace_organizer_id', $this->organizer->id)->whereKey((int) $item['product_id'])->first();
                if (!$component || $component->product_type === 'package' || ($product && $component->id === $product->id)) {
                    $v->errors()->add("package_items.$i.product_id", 'Pachetul poate conține doar bilete de acces și servicii din contul tău.');
                    continue;
                }
                if (!empty($item['variant_id']) && !$component->variants()->whereKey((int) $item['variant_id'])->exists()) {
                    $v->errors()->add("package_items.$i.variant_id", 'Varianta nu aparține produsului ales.');
                }
            }
            if ($product && $type !== $product->product_type && $product->bookings()->exists()) {
                $v->errors()->add('product_type', 'Tipul nu se mai poate schimba după primele vânzări.');
            }
        });

        return $v->validate();
    }

    public function fillProduct(Activity $product, array $data): void
    {
        $clientId = $this->organizer->marketplace_client_id;
        $location = !empty($data['location_id']) ? $this->ownLocation((int) $data['location_id']) : null;
        $category = $this->categoryId($data['category_id'] ?? null);
        $subcat   = $category ? $this->categoryId($data['subcategory_id'] ?? null, $category) : null;

        $product->fill([
            'product_type'          => $data['product_type'],
            'location_id'           => $location?->id,
            'access_kind'           => $data['product_type'] === 'access' ? ($data['access_kind'] ?? 'person') : null,
            'service_type'          => $data['product_type'] === 'experience' ? ($data['service_type'] ?? null) : null,
            'title'                 => $this->tr($product->title, $data['title']),
            'subtitle'              => $this->tr($product->subtitle, $data['subtitle'] ?? null),
            'short_description'     => $this->tr($product->short_description, $data['short_description'] ?? null),
            'description'           => $this->tr($product->description, $this->html($data['description'] ?? null)),
            'marketplace_category_id'    => $category,
            'marketplace_subcategory_id' => $subcat,
            'marketplace_city_id'   => $this->cityId($data['city_id'] ?? null) ?? $location?->marketplace_city_id,
            'latitude'              => $product->latitude ?? $location?->latitude,
            'longitude'             => $product->longitude ?? $location?->longitude,
            'booking_mode'          => $data['product_type'] === 'package' ? 'day' : $data['booking_mode'],
            'capacity_mode'         => $data['capacity_mode'] ?? 'per_slot',
            'capacity_per_slot'     => (int) ($data['capacity_per_slot'] ?? $product->capacity_per_slot ?? 1),
            'daily_capacity'        => $data['daily_capacity'] ?? null,
            'duration_minutes'      => (int) ($data['duration_minutes'] ?? $product->duration_minutes ?? 60),
            'slot_interval_minutes' => (int) ($data['slot_interval_minutes'] ?? $product->slot_interval_minutes ?? 60),
            'booking_lead_time_hours'  => (int) ($data['booking_lead_time_hours'] ?? $product->booking_lead_time_hours ?? 0),
            'booking_max_advance_days' => (int) ($data['booking_max_advance_days'] ?? $product->booking_max_advance_days ?? 90),
            'use_location_schedule' => (bool) ($data['use_location_schedule'] ?? false),
            'access_requirement'    => $data['product_type'] === 'access' ? 'none' : ($data['access_requirement'] ?? 'none'),
            'requires_vehicle_info' => (bool) ($data['requires_vehicle_info'] ?? false),
            'pos_only'              => (bool) ($data['pos_only'] ?? false),
            'issuing_company'       => $data['issuing_company'] ?? 'primary',
            'display_category'      => $data['display_category'] ?? null,
            'unit_label'            => $this->tr($product->unit_label, $data['unit_label'] ?? null),
            'usage_terms'           => $this->tr($product->usage_terms, $data['usage_terms'] ?? null),
            'icon'                  => $data['icon'] ?? null,
            'meeting_point'         => $data['meeting_point'] ?? null,
            'languages_offered'     => array_values((array) ($data['languages'] ?? [])),
            'included_items'        => array_values((array) ($data['included_items'] ?? [])),
            'not_included'          => array_values((array) ($data['not_included'] ?? [])),
            'requirements'          => array_values((array) ($data['requirements'] ?? [])),
            'cancellation_policy'   => $data['cancellation_policy'] ?? null,
            'age_min'               => $data['age_min'] ?? null,
            'age_max'               => $data['age_max'] ?? null,
            'difficulty_level'      => $data['difficulty_level'] ?? null,
            'is_indoor'             => (bool) ($data['is_indoor'] ?? false),
            'is_outdoor'            => (bool) ($data['is_outdoor'] ?? false),
            'is_kid_friendly'       => (bool) ($data['is_kid_friendly'] ?? false),
            'is_accessible'         => (bool) ($data['is_accessible'] ?? false),
            'is_weather_sensitive'  => (bool) ($data['is_weather_sensitive'] ?? false),
            'cover_image_url'       => $this->path($data['cover_image'] ?? null, [$product->cover_image_url]),
            'gallery'               => $this->paths($data['gallery'] ?? [], (array) ($product->gallery ?? [])),
        ]);

        if (!$product->exists) {
            $product->marketplace_client_id    = $clientId;
            $product->marketplace_organizer_id = $this->organizer->id;
            $product->review_status = 'draft';
            $product->is_published  = false;
            // Unique among deleted rows too: the (client, slug) index counts them.
            $base = \Illuminate\Support\Str::slug($data['title']) ?: 'produs';
            $slug = $base;
            for ($n = 2; Activity::withTrashed()->where('marketplace_client_id', $clientId)->where('slug', $slug)->exists(); $n++) {
                $slug = $base . '-' . $n;
            }
            $product->slug = $slug;
        }
    }

    /** Variants, schedule, exceptions, addons and package contents, as sent. */
    public function syncChildren(Activity $product, array $data): void
    {
        // Variants: update by id, create new ones, retire the rest (soft delete
        // keeps sold tickets pointing at them).
        $keep = [];
        foreach (array_values($data['variants']) as $i => $v) {
            $variant = !empty($v['id'])
                ? $product->variants()->withTrashed()->whereKey((int) $v['id'])->first()
                : new ActivityVariant(['activity_id' => $product->id]);
            if ($variant->trashed()) {
                $variant->restore();
            }
            $variant->fill([
                'name'             => $this->tr($variant->name, $v['name']),
                'description'      => $this->tr($variant->description, $v['description'] ?? null),
                'price_cents'      => (int) round(((float) $v['price']) * 100),
                'currency'         => $variant->currency ?: 'RON',
                'price_type'       => $v['price_type'] ?? 'per_person',
                'persons_min'      => $v['persons_min'] ?? null,
                'persons_max'      => $v['persons_max'] ?? null,
                'is_child'         => (bool) ($v['is_child'] ?? false),
                'min_age'          => $v['min_age'] ?? null,
                'max_age'          => $v['max_age'] ?? null,
                'duration_minutes' => $v['duration_minutes'] ?? null,
                'validity_days'    => (int) ($v['validity_days'] ?? 1),
                'min_per_order'    => (int) ($v['min_per_order'] ?? 0),
                'max_per_order'    => (int) ($v['max_per_order'] ?? 10),
                'step_qty'         => $v['step_qty'] ?? null,
                'companion_label'  => $v['companion_label'] ?? null,
                'pos_price_cents'  => isset($v['pos_price']) && $v['pos_price'] !== '' ? (int) round(((float) $v['pos_price']) * 100) : null,
                'pos_only'         => (bool) ($v['pos_only'] ?? false),
                'capacity_share'   => (int) ($v['capacity_share'] ?? 1),
                'is_active'        => (bool) ($v['is_active'] ?? true),
                'is_refundable'    => (bool) ($v['is_refundable'] ?? false),
                'sort_order'       => $i,
            ]);
            $variant->activity_id = $product->id;
            $variant->save();
            $keep[] = $variant->id;
        }
        $product->variants()->whereNotIn('id', $keep)->get()->each->delete();

        if (array_key_exists('schedules', $data)) {
            $product->schedules()->delete();
            foreach (array_values((array) $data['schedules']) as $i => $s) {
                ActivitySchedule::create([
                    'activity_id'  => $product->id,
                    'day_of_week'  => (int) $s['day_of_week'],
                    'open_time'    => $s['open'] . ':00',
                    'close_time'   => $s['close'] . ':00',
                    'season_start' => $s['season_start'] ?? null,
                    'season_end'   => $s['season_end'] ?? null,
                    'is_active'    => (bool) ($s['is_active'] ?? true),
                    'sort_order'   => $i,
                ]);
            }
        }

        if (array_key_exists('exceptions', $data)) {
            $product->scheduleExceptions()->delete();
            foreach (collect((array) $data['exceptions'])->unique('date') as $e) {
                $closed = (bool) ($e['is_closed'] ?? true);
                ActivityScheduleException::create([
                    'activity_id'    => $product->id,
                    'exception_date' => $e['date'],
                    'is_closed'      => $closed || empty($e['open']) || empty($e['close']),
                    'open_time'      => !$closed && !empty($e['open']) ? $e['open'] . ':00' : null,
                    'close_time'     => !$closed && !empty($e['close']) ? $e['close'] . ':00' : null,
                    'reason'         => $e['reason'] ?? null,
                ]);
            }
        }

        if (array_key_exists('addons', $data)) {
            $keep = [];
            foreach (array_values((array) $data['addons']) as $i => $a) {
                $addon = !empty($a['id']) ? $product->addons()->whereKey((int) $a['id'])->first() : null;
                $addon ??= new ActivityAddon(['activity_id' => $product->id]);
                $addon->fill([
                    'name'         => $this->tr($addon->name, $a['name']),
                    'price_cents'  => (int) round(((float) $a['price']) * 100),
                    'included_qty' => (int) ($a['included_qty'] ?? 0),
                    'max_per_unit' => (int) ($a['max_per_unit'] ?? 5),
                    'is_active'    => (bool) ($a['is_active'] ?? true),
                    'sort_order'   => $i,
                ]);
                $addon->activity_id = $product->id;
                $addon->save();
                $keep[] = $addon->id;
            }
            $product->addons()->whereNotIn('id', $keep)->delete();
        }

        if ($product->isPackage() && array_key_exists('package_items', $data)) {
            $product->packageItems()->delete();
            foreach (array_values((array) $data['package_items']) as $i => $item) {
                ActivityPackageItem::create([
                    'package_activity_id'   => $product->id,
                    'component_activity_id' => (int) $item['product_id'],
                    'component_variant_id'  => !empty($item['variant_id']) ? (int) $item['variant_id'] : null,
                    'quantity'              => (int) $item['quantity'],
                    'allocated_price_cents' => isset($item['allocated_price']) && $item['allocated_price'] !== '' ? (int) round(((float) $item['allocated_price']) * 100) : null,
                    'sort_order'            => $i,
                ]);
            }
        }
    }

    /** Save a product with its children in one transaction. */
    public function saveProduct(Activity $product, array $data): Activity
    {
        return DB::transaction(function () use ($product, $data) {
            $this->fillProduct($product, $data);
            $product->save();
            $this->syncChildren($product, $data);
            return $product->fresh(['variants', 'schedules', 'scheduleExceptions', 'addons', 'packageItems.component', 'packageItems.componentVariant', 'location']);
        });
    }

    // ================================================================
    // Helpers
    // ================================================================

    public function ownLocation(int $id): ?ActivityLocation
    {
        return ActivityLocation::where('marketplace_organizer_id', $this->organizer->id)
            ->where('marketplace_client_id', $this->organizer->marketplace_client_id)
            ->whereKey($id)
            ->first();
    }

    public function ownProduct(int $id): ?Activity
    {
        return Activity::where('marketplace_organizer_id', $this->organizer->id)
            ->where('marketplace_client_id', $this->organizer->marketplace_client_id)
            ->whereKey($id)
            ->first();
    }

    /** Store the Romanian text, keep the other languages the admin set. */
    private function tr($current, ?string $ro): ?array
    {
        $values = is_array($current) ? $current : [];
        $ro = $ro !== null ? trim($ro) : null;
        if ($ro === null || $ro === '') {
            unset($values['ro']);
        } else {
            $values['ro'] = $ro;
        }
        return $values ?: null;
    }

    /** Operator HTML: text formatting and links only. */
    private function html(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        return Purifier::clean($html, [
            'HTML.Allowed'             => 'p,br,b,strong,i,em,u,ul,ol,li,h3,h4,a[href|title]',
            'URI.AllowedSchemes'       => ['http' => true, 'https' => true, 'mailto' => true],
            'HTML.TargetBlank'         => true,
            'AutoFormat.RemoveEmpty'   => true,
        ]);
    }

    private function cityId($id): ?int
    {
        if (!$id) {
            return null;
        }
        return MarketplaceCity::where('marketplace_client_id', $this->organizer->marketplace_client_id)->whereKey((int) $id)->value('id');
    }

    private function categoryId($id, ?int $parentId = null): ?int
    {
        if (!$id) {
            return null;
        }
        return MarketplaceCategory::where('marketplace_client_id', $this->organizer->marketplace_client_id)
            ->when($parentId, fn ($q) => $q->where('parent_id', $parentId))
            ->whereKey((int) $id)
            ->value('id');
    }

    /** Folder where this operator's uploads go (public disk). */
    public function uploadFolder(string $kind): string
    {
        return 'activities/organizers/' . $this->organizer->id . '/' . $kind;
    }

    /**
     * An image path the operator may reference: one they uploaded, or one the
     * record already had (set by the admin). Anything else is dropped.
     */
    private function path(?string $path, array $current = []): ?string
    {
        if (!$path) {
            return null;
        }
        if (str_starts_with($path, 'activities/organizers/' . $this->organizer->id . '/') && !str_contains($path, '..')) {
            return $path;
        }
        return in_array($path, array_filter($current), true) ? $path : null;
    }

    private function paths(array $paths, array $current = []): array
    {
        return array_values(array_filter(array_map(fn ($p) => is_string($p) ? $this->path($p, $current) : null, $paths)));
    }

    /** First validation message, for a simple 422. */
    public static function firstError(ValidationException $e): string
    {
        return collect($e->errors())->flatten()->first() ?? 'Date invalide.';
    }
}
