<?php
/**
 * viaqui.com v2: labels for the activities module values (locations, products, lodging).
 * The keys are the ones the core accepts (OrganizerCatalog constants); the location page, the experience
 * page and the operator editors all read them from here.
 */

/**
 * What a location can offer. The keys are the ones the core accepts
 * (OrganizerCatalog::FACILITIES) and never change value. On top of them an
 * operator may add their own, stored as "custom:<label>" — am_facility_label()
 * reads both.
 */
const AM_FACILITIES = [
    // access and parking
    'parking' => 'Parking', 'free_parking' => 'Free parking', 'bus_parking' => 'Coach parking',
    'bike_parking' => 'Bicycle parking', 'ev_charging' => 'Electric car charging',
    'accessible' => 'Wheelchair access', 'stroller' => 'Pushchair access',
    // services
    'card' => 'Card payment', 'atm' => 'Cash machine', 'shop' => 'Shop', 'rentals' => 'Equipment hire',
    'guide' => 'Guide', 'audio_guide' => 'Audio guide', 'lockers' => 'Lockers',
    'luggage' => 'Luggage storage', 'wifi' => 'Wi-Fi', 'first_aid' => 'First aid',
    // food and rest
    'restaurant' => 'Restaurant', 'bar' => 'Bar / café', 'terrace' => 'Terrace', 'picnic' => 'Picnic area',
    'bbq' => 'Barbecues', 'gazebo' => 'Gazebos', 'drinking_water' => 'Drinking water',
    // for the visitors
    'toilets' => 'Toilets', 'changing_rooms' => 'Changing rooms', 'showers' => 'Showers',
    'baby_change' => 'Baby changing', 'playground' => 'Playground', 'smoking_area' => 'Smoking area',
    'pets' => 'Pets welcome',
    // water and nature
    'beach' => 'Beach', 'pool' => 'Swimming pool', 'sauna' => 'Sauna', 'boat_ramp' => 'Boat ramp',
    'fishing' => 'Fishing',
    // sleeping on site
    'lodging' => 'Accommodation', 'camping' => 'Camping',
];

/** The same keys, in the groups the operator's editor shows them in. */
const AM_FACILITY_GROUPS = [
    'Access and parking' => ['parking', 'free_parking', 'bus_parking', 'bike_parking', 'ev_charging', 'accessible', 'stroller'],
    'Services' => ['card', 'atm', 'shop', 'rentals', 'guide', 'audio_guide', 'lockers', 'luggage', 'wifi', 'first_aid'],
    'Food and rest' => ['restaurant', 'bar', 'terrace', 'picnic', 'bbq', 'gazebo', 'drinking_water'],
    'For visitors' => ['toilets', 'changing_rooms', 'showers', 'baby_change', 'playground', 'smoking_area', 'pets'],
    'Water and nature' => ['beach', 'pool', 'sauna', 'boat_ramp', 'fishing'],
    'Staying overnight' => ['lodging', 'camping'],
];

const AM_LODGING_FACILITIES = [
    'wifi' => 'Wi-Fi', 'parking' => 'Parking', 'breakfast' => 'Breakfast', 'restaurant' => 'Restaurant', 'kitchen' => 'Kitchen',
    'ac' => 'Air conditioning', 'heating' => 'Heating', 'private_bathroom' => 'Private bathroom', 'tv' => 'TV',
    'pets' => 'Pets welcome', 'pool' => 'Swimming pool', 'spa' => 'Spa', 'terrace' => 'Terrace', 'bbq' => 'Barbecue',
    'playground' => 'Playground', 'accessible' => 'Wheelchair access',
    'fridge' => 'Fridge', 'kettle' => 'Kettle', 'safe' => 'Safe', 'towels' => 'Towels',
    'washing_machine' => 'Washing machine', 'balcony' => 'Balcony', 'garden' => 'Garden', 'sauna' => 'Sauna',
    'fireplace' => 'Fireplace', 'crib' => 'Cot', 'ev_charging' => 'Electric car charging',
    'non_smoking' => 'Non-smoking',
];

/** A facility the operator wrote themselves is stored with this prefix. */
const AM_CUSTOM_FACILITY = 'custom:';

const AM_LODGING_TYPES = [
    'pensiune' => 'Guest house', 'hotel' => 'Hotel', 'cabana' => 'Mountain lodge', 'vila' => 'Villa', 'apartamente' => 'Apartments',
    'camping' => 'Camping', 'glamping' => 'Glamping', 'altele' => 'Accommodation',
];

const AM_LINK_PLATFORMS = [
    'booking' => 'Booking.com', 'airbnb' => 'Airbnb', 'travelminit' => 'Travelminit', 'website' => 'Official website', 'other' => 'Book your stay',
];

const AM_DAYS = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];

const AM_MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const AM_PRODUCT_TYPES = ['access' => 'Entry ticket', 'experience' => 'Experience', 'package' => 'Package'];
// A card without a photograph still needs a face: the product's icon, or its type's (product-icons.php).
require_once __DIR__ . '/product-icons.php';

// Attraction types with pages on the site (the homepage keeps the same list in V2_ATTRACTION_TYPES).
const AM_ATTRACTION_TYPES = [
    'castel-palat' => 'Castle & palace', 'muzeu' => 'Museum', 'monument' => 'Monument',
    'biserica-manastire' => 'Church & monastery', 'parc-gradina' => 'Park & garden',
    'piata-centru-vechi' => 'Square & old town', 'cladire-istorica' => 'Historic building',
    'punct-panoramic' => 'Viewpoint', 'lac-natura' => 'Lake & nature', 'teatru-opera' => 'Theatre & opera',
];

// The plural of each type, for headings like "Map of castles and palaces".
const AM_ATTRACTION_TYPES_GEN = [
    'castel-palat' => 'castles and palaces', 'muzeu' => 'museums', 'monument' => 'monuments',
    'biserica-manastire' => 'churches and monasteries', 'parc-gradina' => 'parks and gardens',
    'piata-centru-vechi' => 'squares and old towns', 'cladire-istorica' => 'historic buildings',
    'punct-panoramic' => 'viewpoints', 'lac-natura' => 'lakes and nature', 'teatru-opera' => 'theatres and operas',
];

/** City of a /{oras}/… hub from the rewrite (?city=): [slug, name], ['', ''] without one, null when unknown. */
function am_hub_city(): ?array
{
    $slug = $_GET['city'] ?? '';
    if ($slug === '' || $slug === null) {
        return ['', ''];
    }
    if (!is_string($slug) || !preg_match('/^[a-z][a-z0-9-]{1,50}$/', $slug)) {
        return null;
    }
    $city = navGetCityBySlug($slug);
    $name = is_array($city) ? navFlatName($city['name'] ?? '') : '';
    return $name !== '' ? [$slug, $name] : null;
}

/** ?pagina=N (1 when missing or invalid). */
function am_hub_page(): int
{
    return max(1, min(500, (int) ($_GET['pagina'] ?? 1)));
}

/** "04-01" → "1 April". */
function am_month_day(?string $md): string
{
    if (!is_string($md) || !preg_match('/^(\d{2})-(\d{2})$/', $md, $m)) {
        return '';
    }
    return (int) $m[2] . ' ' . (AM_MONTHS[(int) $m[1] - 1] ?? '');
}

/** "2026-12-25" → "25 December 2026". */
function am_date(?string $ymd): string
{
    if (!is_string($ymd) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
        return '';
    }
    return (int) $m[3] . ' ' . (AM_MONTHS[(int) $m[2] - 1] ?? '') . ' ' . $m[1];
}

/**
 * A week of {day: {open, close}} as grouped rows: [["Monday – Friday", "09:00–19:00"], ["Saturday", "closed"]].
 * Consecutive days with the same hours share a row; a week with one set of hours is "Every day".
 */
function am_week_rows($schedule): array
{
    $schedule = is_array($schedule) ? $schedule : [];
    $keys = array_keys(AM_DAYS);
    $hours = [];
    foreach ($keys as $d) {
        $h = $schedule[$d] ?? null;
        $hours[$d] = (is_array($h) && !empty($h['open']) && !empty($h['close']))
            ? substr($h['open'], 0, 5) . '–' . substr($h['close'], 0, 5)
            : 'closed';
    }
    if (count(array_unique($hours)) === 1) {
        return [['Every day', reset($hours)]];
    }
    $rows = [];
    $start = 0;
    for ($i = 1; $i <= 7; $i++) {
        if ($i === 7 || $hours[$keys[$i]] !== $hours[$keys[$start]]) {
            $label = AM_DAYS[$keys[$start]] . ($i - 1 > $start ? ' – ' . AM_DAYS[$keys[$i - 1]] : '');
            $rows[] = [$label, $hours[$keys[$start]]];
            $start = $i;
        }
    }
    return $rows;
}

/** Rich text from the core (already purified there): a small allow-list again, no handlers, no script URLs. */
function am_rich(?string $html): string
{
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }
    if (strip_tags($html) === $html) {
        return '<p>' . nl2br(htmlspecialchars($html, ENT_QUOTES, 'UTF-8')) . '</p>';
    }
    $html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1\s*>#is', '', $html);
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><h3><h4><a><blockquote>');
    $html = preg_replace('#\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
    $html = preg_replace('#\s(?:style|class|id)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
    $html = preg_replace('#href\s*=\s*(["\']?)\s*(?:javascript|data|vbscript):[^"\'>\s]*\1#i', 'href="#"', $html);
    return preg_replace('#<a\s#i', '<a rel="nofollow noopener" target="_blank" ', $html);
}

/**
 * A facility as the visitor reads it: "parking" → "Parking",
 * "custom:Boat ramp" → "Boat ramp", anything unknown → null.
 * $map is AM_FACILITIES for a location, AM_LODGING_FACILITIES for a lodging.
 */
function am_facility_label(?string $key, ?array $map = null): ?string
{
    $key = trim((string) $key);
    if ($key === '') {
        return null;
    }
    if (str_starts_with($key, AM_CUSTOM_FACILITY)) {
        $label = trim(substr($key, strlen(AM_CUSTOM_FACILITY)));
        return $label !== '' ? $label : null;
    }
    return ($map ?? AM_FACILITIES)[$key] ?? null;
}

/** The labels of a location's (or a lodging's) facilities, unknown ones dropped. */
function am_facility_labels($keys, ?array $map = null): array
{
    return array_values(array_filter(array_map(fn ($k) => am_facility_label(is_string($k) ? $k : null, $map), (array) $keys)));
}

/**
 * Money from cents, in a currency (the site's when none is given): 4100 → "€41", 4150 → "€41.50".
 * The name is from the site this one was copied from, where every price was in lei.
 */
function am_lei(?int $cents, ?string $currency = null): string
{
    $currency = strtoupper((string) $currency) ?: (defined('SITE_CURRENCY') ? SITE_CURRENCY : 'EUR');
    if (function_exists('v2_money_in')) {
        return v2_money_in(((int) $cents) / 100, $currency);
    }
    $v = ((int) $cents) / 100;
    return number_format($v, fmod($v, 1.0) ? 2 : 0, '.', ',') . ' ' . $currency;
}

/** The labels the operator screens need in JavaScript (#v2-data .am). */
function am_client_labels(): array
{
    return [
        'facilities' => AM_FACILITIES,
        'facility_groups' => AM_FACILITY_GROUPS,
        'custom_facility' => AM_CUSTOM_FACILITY,
        'lodging_facilities' => AM_LODGING_FACILITIES,
        'lodging_types' => AM_LODGING_TYPES,
        'link_platforms' => AM_LINK_PLATFORMS,
        'days' => AM_DAYS,
        'months' => AM_MONTHS,
        'product_types' => AM_PRODUCT_TYPES,
        'product_icons' => am_product_icon_list(),
        'product_type_icons' => AM_PRODUCT_TYPE_ICONS,
    ];
}
