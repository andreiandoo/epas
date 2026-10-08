<?php
/**
 * viaqui.com v2: the numbers the trip planner runs on.
 *
 * The catalogue has no opening hours and no visit durations, so the planner works with per-type
 * estimates. They are declared here, in one place, and the page labels them as estimates wherever
 * they show up — see /plan. The day budgets are what a person can realistically walk through,
 * travel included, not a theoretical maximum.
 *
 * Type slugs are the ones of V2_ATTRACTION_TYPES (includes/v2/places.php).
 */

/** Minutes a visit of each attraction type usually takes. */
const PLAN_DURATIONS = [
    'castles'              => 90,
    'palaces'              => 90,
    'fortresses'           => 60,
    'museums'              => 75,
    'cathedrals'           => 40,
    'churches'             => 25,
    'monasteries'          => 45,
    'archaeological-sites' => 75,
    'unesco-sites'         => 90,
    'landmarks'            => 30,
    'old-towns-squares'    => 60,
    'viewpoints'           => 30,
    'theatres-operas'      => 30,
    'national-parks'       => 180,
    'caves'                => 75,
    'waterfalls'           => 45,
    'lakes'                => 60,
    'beaches'              => 120,
    'zoos'                 => 150,
    'aquariums'            => 90,
    'botanical-gardens'    => 60,
    'theme-parks'          => 240,
    'thermal-baths'        => 150,
    'cable-cars'           => 60,
    'salt-mines'           => 120,
    'wineries'             => 75,
    'bridges'              => 15,
    'lighthouses'          => 20,
    'default'              => 45,
];

/** How full a day gets: label, minutes of visiting + travel, and how much is left deliberately free. */
const PLAN_PACES = [
    'relaxed' => ['Relaxed', 300, 'Three or four places a day, with time between them.'],
    'normal'  => ['Normal', 420, 'A full day, with no rushing.'],
    'intense' => ['Intense', 540, 'As much as fits in a long day.'],
];

/** Interest presets: a label, an icon and the attraction types behind it. */
const PLAN_INTERESTS = [
    'history' => ['Castles and history', 'castle', ['castles', 'palaces', 'fortresses', 'archaeological-sites', 'unesco-sites', 'landmarks']],
    'museums' => ['Museums', 'museum', ['museums', 'aquariums']],
    'faith'   => ['Churches and monasteries', 'church', ['cathedrals', 'churches', 'monasteries']],
    'nature'  => ['Nature and views', 'mountains', ['national-parks', 'caves', 'waterfalls', 'lakes', 'beaches', 'viewpoints', 'botanical-gardens', 'cable-cars']],
    'towns'   => ['Old towns and squares', 'city', ['old-towns-squares', 'landmarks', 'bridges', 'unesco-sites']],
    'fun'     => ['Family fun', 'sparkle', ['zoos', 'aquariums', 'theme-parks', 'thermal-baths', 'salt-mines', 'cable-cars']],
    'culture' => ['Theatre and wine', 'theatre', ['theatres-operas', 'wineries']],
];

/**
 * Who you are travelling with, and how that tilts the recommendations.
 *
 * This is a weighting of attraction types, not knowledge about each place: the catalogue does not
 * say whether a particular museum suits a six-year-old. A positive number pushes a type up the
 * list, a negative one pushes it down; nothing is ever excluded outright. `budget` scales the
 * day, because a day with small children is shorter than a day without them.
 */
const PLAN_COMPANY = [
    'friends' => ['With friends', 'group', 1.0, [
        'viewpoints' => 1.5, 'old-towns-squares' => 1.5, 'beaches' => 1.5, 'lakes' => 1.0, 'wineries' => 1.5,
        'thermal-baths' => 1.0, 'theme-parks' => 1.0, 'churches' => -1.0, 'monasteries' => -0.5,
    ]],
    'family' => ['With family', 'heart', 0.9, [
        'castles' => 1.5, 'museums' => 1.0, 'zoos' => 2.0, 'botanical-gardens' => 1.5, 'national-parks' => 1.0,
        'caves' => 1.0, 'lakes' => 1.0, 'landmarks' => -0.5,
    ]],
    'children' => ['With children', 'baby', 0.75, [
        'zoos' => 3.0, 'aquariums' => 3.0, 'theme-parks' => 3.0, 'caves' => 1.5, 'castles' => 1.5, 'beaches' => 1.5,
        'salt-mines' => 1.5, 'cable-cars' => 1.5, 'botanical-gardens' => 1.0,
        'churches' => -2.0, 'monasteries' => -1.5, 'cathedrals' => -1.0, 'wineries' => -3.0, 'theatres-operas' => -1.0,
    ]],
    'couple' => ['As a couple', 'heart', 1.0, [
        'viewpoints' => 2.0, 'castles' => 1.5, 'palaces' => 1.5, 'old-towns-squares' => 1.5, 'wineries' => 1.5,
        'thermal-baths' => 1.5, 'lakes' => 1.0, 'botanical-gardens' => 1.0, 'theatres-operas' => 1.0,
    ]],
];

/**
 * Stops the traveller adds themselves, offered as one tap: label, icon key (product-icons.php), minutes.
 *
 * These are not places and the catalogue knows nothing about them — they are the meal, the coffee
 * and the breath between two visits, and they exist so that a day reads like a day. The name is
 * only a starting point: anything can be typed over it.
 */
const PLAN_STOP_PRESETS = [
    ['Meal', 'fork', 60],
    ['Coffee', 'coffee', 30],
    ['Break', 'armchair', 20],
    ['Free time', 'walk', 60],
    ['Shopping', 'shopping', 45],
    ['Check-in', 'bed', 30],
];

/** The durations offered for any stop, yours or ours. */
const PLAN_STOP_MINUTES = [10, 15, 20, 30, 45, 60, 90, 120, 180, 240];

/**
 * "Replace": how the ready-made list of stand-ins for a stop is built.
 *
 * `count` is how many places are offered at once, `near_km` how far around the stop that is going we
 * look for them, and `far_km` how far we are willing to widen the circle when the first one comes back
 * empty. `km_per_point` is the exchange rate between distance and fit: every this many kilometres cost
 * an alternative one point of score. The order on screen is by distance; the score only decides who
 * makes the list.
 */
const PLAN_SWAP = [
    'count'        => 8,
    'near_km'      => 60,
    'far_km'       => 120,
    'km_per_point' => 7,
];

/**
 * How you travel: label, icon (v2 sprite id), and what it changes in the plan.
 *
 *   kmh, detour   the straight-line estimate, used until the road answer arrives or if it never does
 *   profile       which network /api/route.php routes on: a bicycle has its own
 *   gmaps         the travel mode handed to Google Maps
 *   leg_max       the longest hop, in minutes, the generator will put between two stops
 *   radius        how far around a city it looks, against the car's reach
 *   weights       how the mode tilts the attraction types, on top of who you travel with
 */
const PLAN_MODES = [
    'car' => ['Car', 'pi-car', [
        'kmh' => 60, 'detour' => 1.35, 'profile' => 'car', 'gmaps' => 'driving', 'leg_max' => 75, 'radius' => 1.0, 'weights' => [],
    ]],
    'moto' => ['Motorcycle', 'pl-moto', [
        'kmh' => 55, 'detour' => 1.4, 'profile' => 'car', 'gmaps' => 'driving', 'leg_max' => 110, 'radius' => 1.6,
        'weights' => [
            'viewpoints' => 3.0, 'lakes' => 2.0, 'national-parks' => 2.0, 'waterfalls' => 1.5, 'castles' => 1.0, 'bridges' => 1.0,
            'museums' => -0.5, 'landmarks' => -1.0, 'churches' => -1.0,
        ],
    ]],
    'bike' => ['Bicycle', 'pi-bicycle', [
        'kmh' => 17, 'detour' => 1.3, 'profile' => 'bike', 'gmaps' => 'bicycling', 'leg_max' => 120, 'radius' => 0.45,
        'weights' => ['lakes' => 2.0, 'viewpoints' => 2.0, 'botanical-gardens' => 1.5, 'national-parks' => 1.5, 'beaches' => 1.0],
    ]],
];

/** Average road speed used to turn a straight-line hop into a travel estimate, and the detour factor. */
const PLAN_TRAVEL = ['kmh' => 60, 'detour' => 1.35, 'min_leg' => 5];

/**
 * Who travels, as the start form asks it and as the accommodation search is told.
 *
 * Two adults is the honest default for a trip built on a map; children are counted separately
 * because that is what the booking engines ask for. `per_room` is only how many rooms we propose
 * for a party of that size — the traveller changes it on the night itself.
 */
const PLAN_PARTY = [
    'adults'       => 2,
    'children'     => 0,
    'adults_max'   => 12,
    'children_max' => 10,
    'rooms_max'    => 8,
    'per_room'     => 2,
];

/** What "at most per night" offers, in euro for the whole booking of that night. */
const PLAN_NIGHT_BUDGETS = [50, 75, 100, 150, 200, 300, 500];

/**
 * Stay22: everything about the accommodation embed that is not a date, a place or a party.
 *
 * The partner id is the account's own handle in the Stay22 Hub; a link made with an id Stay22 does
 * not know still works, but is credited to nobody. The embed is third-party, so /plan never loads it
 * before the traveller asks for it, and says out loud that a booking pays us a commission without
 * costing them more. `link` is the plain list, for when the iframe does not come up.
 */
const PLAN_STAY22 = [
    'embed'      => 'https://www.stay22.com/embed/gm',
    'link'       => 'https://www.stay22.com/allez/booking',
    'aid'        => 'goodtechinc',
    'currency'   => 'EUR',
    'maincolor'  => '1E5B48',
    'markertype' => 'circle',
    'zoom'       => 12,
    // What the list can be asked to show. The embed draws one source at a time, so the traveller
    // switches between them: label, the embed's own parameters for it, and its plain-list address.
    'providers'  => [
        'booking' => ['Booking', 'hotelsapi=booking', 'https://www.stay22.com/allez/booking'],
        'expedia' => ['Expedia', 'hotelsapi=expedia', 'https://www.stay22.com/allez/expedia'],
        'vrbo'    => ['Holiday homes', 'showhotels=false&rentalsapi=vrbo', 'https://www.stay22.com/allez/vrbo'],
    ],
    'note'       => 'Places to stay come from Booking, Expedia, Vrbo and others. If you book one of them, the website earns a commission.',
];

/**
 * The labels of the lists above, in the visitor's language.
 *
 * A constant cannot call a function, so the lists stay in English and every label is written here once more, as a
 * literal, where the catalogue collector (plans/viaqui-data/extract_strings.py) can find it. A label added to a list
 * above and not named here is shown in English.
 */
function plan_label(string $text): string
{
    static $map = null;
    if ($map === null) {
        $map = [
            // PLAN_PACES
            'Relaxed' => v2_t('Relaxed'),
            'Normal' => v2_t('Normal'),
            'Intense' => v2_t('Intense'),
            'Three or four places a day, with time between them.' => v2_t('Three or four places a day, with time between them.'),
            'A full day, with no rushing.' => v2_t('A full day, with no rushing.'),
            'As much as fits in a long day.' => v2_t('As much as fits in a long day.'),
            // PLAN_INTERESTS
            'Castles and history' => v2_t('Castles and history'),
            'Museums' => v2_t('Museums'),
            'Churches and monasteries' => v2_t('Churches and monasteries'),
            'Nature and views' => v2_t('Nature and views'),
            'Old towns and squares' => v2_t('Old towns and squares'),
            'Family fun' => v2_t('Family fun'),
            'Theatre and wine' => v2_t('Theatre and wine'),
            // PLAN_COMPANY
            'With friends' => v2_t('With friends'),
            'With family' => v2_t('With family'),
            'With children' => v2_t('With children'),
            'As a couple' => v2_t('As a couple'),
            // PLAN_STOP_PRESETS
            'Meal' => v2_t('Meal'),
            'Coffee' => v2_t('Coffee'),
            'Break' => v2_t('Break'),
            'Free time' => v2_t('Free time'),
            'Shopping' => v2_t('Shopping'),
            'Check-in' => v2_t('Check-in'),
            // PLAN_MODES
            'Car' => v2_t('Car'),
            'Motorcycle' => v2_t('Motorcycle'),
            'Bicycle' => v2_t('Bicycle'),
            // PLAN_STAY22 (Booking and Expedia are names and stay as they are)
            'Holiday homes' => v2_t('Holiday homes'),
            'Places to stay come from Booking, Expedia, Vrbo and others. If you book one of them, the website earns a commission.'
                => v2_t('Places to stay come from Booking, Expedia, Vrbo and others. If you book one of them, the website earns a commission.'),
        ];
    }
    return $map[$text] ?? $text;
}

/** One of the lists above with its labels translated: $at says which positions of each row hold a label. */
function plan_labels(array $rows, array $at = [0]): array
{
    foreach ($rows as $key => $row) {
        foreach ($at as $i) {
            if (isset($row[$i]) && is_string($row[$i])) {
                $rows[$key][$i] = plan_label($row[$i]);
            }
        }
    }
    return $rows;
}
