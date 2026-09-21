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

/** Average road speed used to turn a straight-line hop into a travel estimate, and the detour factor. */
const PLAN_TRAVEL = ['kmh' => 55, 'detour' => 1.35, 'min_leg' => 5];
