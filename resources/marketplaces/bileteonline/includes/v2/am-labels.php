<?php
/**
 * bilete.online v2: Romanian labels for the activities module values (locations, products, lodging).
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
    'parking' => 'Parcare', 'free_parking' => 'Parcare gratuită', 'bus_parking' => 'Parcare autocare',
    'bike_parking' => 'Parcare biciclete', 'ev_charging' => 'Încărcare mașini electrice',
    'accessible' => 'Acces persoane cu dizabilități', 'stroller' => 'Acces cărucior de copil',
    // services
    'card' => 'Plată cu cardul', 'atm' => 'Bancomat', 'shop' => 'Magazin', 'rentals' => 'Închirieri',
    'guide' => 'Ghid', 'audio_guide' => 'Audioghid', 'lockers' => 'Seif / dulapuri',
    'luggage' => 'Depozit bagaje', 'wifi' => 'Wi-Fi', 'first_aid' => 'Prim ajutor',
    // food and rest
    'restaurant' => 'Restaurant', 'bar' => 'Bar / cafenea', 'terrace' => 'Terasă', 'picnic' => 'Zonă de picnic',
    'bbq' => 'Grătare', 'gazebo' => 'Foișoare', 'drinking_water' => 'Apă potabilă',
    // for the visitors
    'toilets' => 'Toalete', 'changing_rooms' => 'Vestiare', 'showers' => 'Dușuri',
    'baby_change' => 'Masă de înfășat', 'playground' => 'Loc de joacă', 'smoking_area' => 'Zonă de fumat',
    'pets' => 'Animale acceptate',
    // water and nature
    'beach' => 'Plajă', 'pool' => 'Piscină', 'sauna' => 'Saună', 'boat_ramp' => 'Rampă de barcă',
    'fishing' => 'Pescuit',
    // sleeping on site
    'lodging' => 'Cazare', 'camping' => 'Camping',
];

/** The same keys, in the groups the operator's editor shows them in. */
const AM_FACILITY_GROUPS = [
    'Acces și parcare' => ['parking', 'free_parking', 'bus_parking', 'bike_parking', 'ev_charging', 'accessible', 'stroller'],
    'Servicii' => ['card', 'atm', 'shop', 'rentals', 'guide', 'audio_guide', 'lockers', 'luggage', 'wifi', 'first_aid'],
    'Mâncare și odihnă' => ['restaurant', 'bar', 'terrace', 'picnic', 'bbq', 'gazebo', 'drinking_water'],
    'Pentru vizitatori' => ['toilets', 'changing_rooms', 'showers', 'baby_change', 'playground', 'smoking_area', 'pets'],
    'Apă și natură' => ['beach', 'pool', 'sauna', 'boat_ramp', 'fishing'],
    'Dormit pe loc' => ['lodging', 'camping'],
];

const AM_LODGING_FACILITIES = [
    'wifi' => 'Wi-Fi', 'parking' => 'Parcare', 'breakfast' => 'Mic dejun', 'restaurant' => 'Restaurant', 'kitchen' => 'Bucătărie',
    'ac' => 'Aer condiționat', 'heating' => 'Încălzire', 'private_bathroom' => 'Baie proprie', 'tv' => 'TV',
    'pets' => 'Animale acceptate', 'pool' => 'Piscină', 'spa' => 'Spa', 'terrace' => 'Terasă', 'bbq' => 'Grătar',
    'playground' => 'Loc de joacă', 'accessible' => 'Acces persoane cu dizabilități',
    'fridge' => 'Frigider', 'kettle' => 'Fierbător', 'safe' => 'Seif', 'towels' => 'Prosoape',
    'washing_machine' => 'Mașină de spălat', 'balcony' => 'Balcon', 'garden' => 'Grădină', 'sauna' => 'Saună',
    'fireplace' => 'Șemineu', 'crib' => 'Pătuț pentru bebeluși', 'ev_charging' => 'Încărcare mașini electrice',
    'non_smoking' => 'Nefumători',
];

/** A facility the operator wrote themselves is stored with this prefix. */
const AM_CUSTOM_FACILITY = 'custom:';

const AM_LODGING_TYPES = [
    'pensiune' => 'Pensiune', 'hotel' => 'Hotel', 'cabana' => 'Cabană', 'vila' => 'Vilă', 'apartamente' => 'Apartamente',
    'camping' => 'Camping', 'glamping' => 'Glamping', 'altele' => 'Cazare',
];

const AM_LINK_PLATFORMS = [
    'booking' => 'Booking.com', 'airbnb' => 'Airbnb', 'travelminit' => 'Travelminit', 'website' => 'Site-ul oficial', 'other' => 'Rezervă cazarea',
];

const AM_DAYS = ['mon' => 'luni', 'tue' => 'marți', 'wed' => 'miercuri', 'thu' => 'joi', 'fri' => 'vineri', 'sat' => 'sâmbătă', 'sun' => 'duminică'];

const AM_MONTHS = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];

const AM_PRODUCT_TYPES = ['access' => 'Bilet de acces', 'experience' => 'Experiență', 'package' => 'Pachet'];

// Attraction types with pages on the site (the homepage keeps the same list in V2_ATTRACTION_TYPES).
const AM_ATTRACTION_TYPES = [
    'castel-palat' => 'Castel & palat', 'muzeu' => 'Muzeu', 'monument' => 'Monument',
    'biserica-manastire' => 'Biserică & mănăstire', 'parc-gradina' => 'Parc & grădină',
    'piata-centru-vechi' => 'Piață & centru vechi', 'cladire-istorica' => 'Clădire istorică',
    'punct-panoramic' => 'Punct panoramic', 'lac-natura' => 'Lac & natură', 'teatru-opera' => 'Teatru & operă',
];

// Genitive plural of each type, for headings like "Harta castelelor din România". Romanian does not
// derive this from the singular label, so it is spelled out.
const AM_ATTRACTION_TYPES_GEN = [
    'castel-palat' => 'castelelor și palatelor', 'muzeu' => 'muzeelor', 'monument' => 'monumentelor',
    'biserica-manastire' => 'bisericilor și mănăstirilor', 'parc-gradina' => 'parcurilor și grădinilor',
    'piata-centru-vechi' => 'piețelor și centrelor vechi', 'cladire-istorica' => 'clădirilor istorice',
    'punct-panoramic' => 'punctelor panoramice', 'lac-natura' => 'lacurilor și locurilor din natură',
    'teatru-opera' => 'teatrelor și operelor',
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

/** "04-01" → "1 aprilie". */
function am_month_day(?string $md): string
{
    if (!is_string($md) || !preg_match('/^(\d{2})-(\d{2})$/', $md, $m)) {
        return '';
    }
    return (int) $m[2] . ' ' . (AM_MONTHS[(int) $m[1] - 1] ?? '');
}

/** "2026-12-25" → "25 decembrie 2026". */
function am_date(?string $ymd): string
{
    if (!is_string($ymd) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
        return '';
    }
    return (int) $m[3] . ' ' . (AM_MONTHS[(int) $m[2] - 1] ?? '') . ' ' . $m[1];
}

/**
 * A week of {day: {open, close}} as grouped rows: [["luni – vineri", "09:00–19:00"], ["sâmbătă", "închis"]].
 * Consecutive days with the same hours share a row; a week with one set of hours is "zilnic".
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
            : 'închis';
    }
    if (count(array_unique($hours)) === 1) {
        return [['zilnic', reset($hours)]];
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
 * A facility as the visitor reads it: "parking" → "Parcare",
 * "custom:Rampă de barcă" → "Rampă de barcă", anything unknown → null.
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

/** Money from cents: 4100 → "41 lei", 4150 → "41,50 lei". */
function am_lei(?int $cents): string
{
    $v = ((int) $cents) / 100;
    return number_format($v, fmod($v, 1.0) ? 2 : 0, ',', '.') . ' lei';
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
    ];
}
