<?php
/**
 * viaqui.com v2: the numbers the trip planner runs on.
 *
 * The catalogue has no opening hours and no visit durations, so the planner works with per-type
 * estimates. They are declared here, in one place, and the page labels them as estimates wherever
 * they show up — see /plan. The day budgets are what a person can realistically walk through,
 * travel included, not a theoretical maximum.
 */

/** Minutes a visit of each attraction type usually takes. */
const PLAN_DURATIONS = [
    'castel-palat'       => 90,
    'muzeu'              => 75,
    'biserica-manastire' => 30,
    'monument'           => 20,
    'cladire-istorica'   => 20,
    'parc-gradina'       => 60,
    'piata-centru-vechi' => 45,
    'punct-panoramic'    => 30,
    'lac-natura'         => 90,
    'teatru-opera'       => 45,
    'default'            => 45,
];

/** How full a day gets: label, minutes of visiting + travel, and how much is left deliberately free. */
const PLAN_PACES = [
    'relaxat' => ['Relaxat', 300, 'Trei-patru locuri pe zi, cu timp între ele.'],
    'normal'  => ['Normal', 420, 'O zi plină, dar fără alergat.'],
    'intens'  => ['Intens', 540, 'Cât încape într-o zi lungă.'],
];

/** Interest presets: a label and the attraction types behind it. */
const PLAN_INTERESTS = [
    'istorie'   => ['Istorie și cetăți', 'castle', ['castel-palat', 'monument', 'cladire-istorica']],
    'muzee'     => ['Muzee', 'museum', ['muzeu']],
    'credinta'  => ['Biserici și mănăstiri', 'church', ['biserica-manastire']],
    'natura'    => ['Natură și priveliști', 'mountains', ['lac-natura', 'punct-panoramic', 'parc-gradina']],
    'orase'     => ['Orașe și centre vechi', 'city', ['piata-centru-vechi', 'cladire-istorica']],
    'cultura'   => ['Teatru și spectacole', 'theatre', ['teatru-opera']],
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
    'prieteni' => ['Cu prietenii', 'group', 1.0, [
        'lac-natura' => 1.5, 'punct-panoramic' => 1.5, 'piata-centru-vechi' => 1.5,
        'teatru-opera' => 1.0, 'cladire-istorica' => 0.5, 'biserica-manastire' => -1.0,
    ]],
    'familie' => ['Cu familia', 'heart', 0.9, [
        'parc-gradina' => 2.0, 'muzeu' => 1.5, 'castel-palat' => 1.5,
        'lac-natura' => 1.0, 'monument' => -0.5,
    ]],
    'copii' => ['Cu copii', 'baby', 0.75, [
        'parc-gradina' => 2.5, 'muzeu' => 1.5, 'lac-natura' => 1.5, 'castel-palat' => 1.0,
        'monument' => -1.5, 'biserica-manastire' => -2.0, 'cladire-istorica' => -1.0,
    ]],
    'cuplu' => ['Cu persoana iubită', 'heart', 1.0, [
        'punct-panoramic' => 2.0, 'castel-palat' => 1.5, 'parc-gradina' => 1.5,
        'lac-natura' => 1.5, 'piata-centru-vechi' => 1.0, 'teatru-opera' => 1.0,
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
    ['Masă', 'fork', 60],
    ['Cafea', 'coffee', 30],
    ['Pauză', 'armchair', 20],
    ['Timp liber', 'walk', 60],
    ['Cumpărături', 'shopping', 45],
    ['Cazare', 'bed', 30],
];

/** The durations offered for any stop, yours or ours. */
const PLAN_STOP_MINUTES = [10, 15, 20, 30, 45, 60, 90, 120, 180, 240];

/**
 * «Înlocuiește»: how the ready-made list of stand-ins for a stop is built.
 *
 * `count` is how many places are offered at once, `near_km` how far around the stop that is going we
 * look for them, and `far_km` how far we are willing to widen the circle when the first one comes back
 * empty. Inside that circle the declared interests filter exactly as they do when the whole plan is
 * generated, and the company weights above tilt what is left; only if nothing of the right kind is
 * anywhere near does the panel let go of the filter, and it says so when it does. `km_per_point` is
 * the exchange rate between distance and fit: every this many kilometres cost an alternative one point
 * of score, so a place the company leans towards can beat a closer one it does not — but never by
 * much, because a swap that moves the day across the county is not a swap. The order on screen is by
 * distance; the score only decides who makes the list.
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
 *   radius        how far around a city it looks, against the car's reach: a rider goes further for
 *                 a good road, a cyclist does not
 *   weights       how the mode tilts the attraction types, on top of who you travel with. As with
 *                 the company weights, nothing is ever excluded — it only moves up or down the list.
 *
 * Car is the plan as it always was: every number below is the old behaviour.
 */
const PLAN_MODES = [
    'car' => ['Mașină', 'pi-car', [
        'kmh' => 55, 'detour' => 1.35, 'profile' => 'car', 'gmaps' => 'driving', 'leg_max' => 75, 'radius' => 1.0, 'weights' => [],
    ]],
    'moto' => ['Motocicletă', 'pl-moto', [
        'kmh' => 50, 'detour' => 1.4, 'profile' => 'car', 'gmaps' => 'driving', 'leg_max' => 110, 'radius' => 1.6,
        'weights' => [
            'punct-panoramic' => 3.0, 'lac-natura' => 2.5, 'castel-palat' => 1.0,
            'muzeu' => -0.5, 'monument' => -1.0, 'cladire-istorica' => -1.0,
        ],
    ]],
    'bike' => ['Bicicletă', 'pi-bicycle', [
        'kmh' => 17, 'detour' => 1.3, 'profile' => 'bike', 'gmaps' => 'bicycling', 'leg_max' => 120, 'radius' => 0.45,
        'weights' => ['lac-natura' => 2.0, 'punct-panoramic' => 2.0, 'parc-gradina' => 1.5],
    ]],
];

/** Average road speed used to turn a straight-line hop into a travel estimate, and the detour factor. */
const PLAN_TRAVEL = ['kmh' => 55, 'detour' => 1.35, 'min_leg' => 5];

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

/**
 * Stay22: everything about the accommodation embed that is not a date, a place or a party.
 *
 * The partner id is the account's own handle in the Stay22 Hub; a link made with an id Stay22 does
 * not know still works, but is credited to nobody (its Booking label reads "stay22"). The embed is third-party, so /plan never
 * loads it before the traveller asks for it, and says out loud that a booking pays us a commission
 * without costing them more. `link` is the plain list, for when the iframe does not come up.
 */
/** What "at most per night" offers, in lei for the whole booking of that night. */
const PLAN_NIGHT_BUDGETS = [200, 300, 400, 500, 700, 1000, 1500];

const PLAN_STAY22 = [
    'embed'      => 'https://www.stay22.com/embed/gm',
    'link'       => 'https://www.stay22.com/allez/booking',
    'aid'        => 'goodtechinc',
    'currency'   => 'RON',
    'maincolor'  => '1E5B48',
    'markertype' => 'circle',
    'zoom'       => 12,
    // What the list can be asked to show. The embed draws one source at a time, so the traveller
    // switches between them: label, the embed's own parameters for it, and its plain-list address.
    'providers'  => [
        'booking' => ['Booking', 'hotelsapi=booking', 'https://www.stay22.com/allez/booking'],
        'expedia' => ['Expedia', 'hotelsapi=expedia', 'https://www.stay22.com/allez/expedia'],
        'vrbo'    => ['Case de vacanță', 'showhotels=false&rentalsapi=vrbo', 'https://www.stay22.com/allez/vrbo'],
    ],
    'note'       => 'Opțiunile de cazare vin de la Booking, Expedia, Vrbo ș.a. Dacă alegi o cazare din cele propuse, website-ul va înregistra un comision.',
];
