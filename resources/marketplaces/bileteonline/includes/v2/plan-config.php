<?php
/**
 * bilete.online v2: the numbers the trip planner runs on.
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
    'istorie'   => ['Istorie și cetăți', '🏰', ['castel-palat', 'monument', 'cladire-istorica']],
    'muzee'     => ['Muzee', '🏛️', ['muzeu']],
    'credinta'  => ['Biserici și mănăstiri', '⛪', ['biserica-manastire']],
    'natura'    => ['Natură și priveliști', '🏞️', ['lac-natura', 'punct-panoramic', 'parc-gradina']],
    'orase'     => ['Orașe și centre vechi', '🏙️', ['piata-centru-vechi', 'cladire-istorica']],
    'cultura'   => ['Teatru și spectacole', '🎭', ['teatru-opera']],
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
    'prieteni' => ['Cu prietenii', '🧑‍🤝‍🧑', 1.0, [
        'lac-natura' => 1.5, 'punct-panoramic' => 1.5, 'piata-centru-vechi' => 1.5,
        'teatru-opera' => 1.0, 'cladire-istorica' => 0.5, 'biserica-manastire' => -1.0,
    ]],
    'familie' => ['Cu familia', '👨‍👩‍👧', 0.9, [
        'parc-gradina' => 2.0, 'muzeu' => 1.5, 'castel-palat' => 1.5,
        'lac-natura' => 1.0, 'monument' => -0.5,
    ]],
    'copii' => ['Cu copii', '🧒', 0.75, [
        'parc-gradina' => 2.5, 'muzeu' => 1.5, 'lac-natura' => 1.5, 'castel-palat' => 1.0,
        'monument' => -1.5, 'biserica-manastire' => -2.0, 'cladire-istorica' => -1.0,
    ]],
    'cuplu' => ['Cu persoana iubită', '💞', 1.0, [
        'punct-panoramic' => 2.0, 'castel-palat' => 1.5, 'parc-gradina' => 1.5,
        'lac-natura' => 1.5, 'piata-centru-vechi' => 1.0, 'teatru-opera' => 1.0,
    ]],
];

/**
 * Stops the traveller adds themselves, offered as one tap: label, emoji, minutes.
 *
 * These are not places and the catalogue knows nothing about them — they are the meal, the coffee
 * and the breath between two visits, and they exist so that a day reads like a day. The name is
 * only a starting point: anything can be typed over it.
 */
const PLAN_STOP_PRESETS = [
    ['Masă', '🍽️', 60],
    ['Cafea', '☕', 30],
    ['Pauză', '😌', 20],
    ['Timp liber', '🚶', 60],
    ['Cumpărături', '🛍️', 45],
    ['Cazare', '🏨', 30],
];

/** The durations offered for any stop, yours or ours. */
const PLAN_STOP_MINUTES = [10, 15, 20, 30, 45, 60, 90, 120, 180, 240];

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
 * Same affiliate account as the rest of the platform. The embed is third-party, so /plan never
 * loads it before the traveller asks for it, and says out loud that a booking pays us a commission
 * without costing them more. `link` is the plain list, for when the iframe does not come up.
 */
/** What "at most per night" offers, in lei for the whole booking of that night. */
const PLAN_NIGHT_BUDGETS = [200, 300, 400, 500, 700, 1000, 1500];

const PLAN_STAY22 = [
    'embed'      => 'https://www.stay22.com/embed/gm',
    'link'       => 'https://www.stay22.com/allez/booking',
    'aid'        => '68f75671f26bfb6f2a73d0b9',
    'currency'   => 'RON',
    'maincolor'  => '1E5B48',
    'markertype' => 'circle',
    'zoom'       => 12,
    'note'       => 'Cazările vin de la Stay22, care compară Booking, Airbnb și altele. Dacă rezervi, primim un comision — prețul tău nu crește.',
];
