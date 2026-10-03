<?php
/**
 * bilete.online v2: the roads people ride for the road itself — the second half of /trasee.
 *
 * An editorial route (map-routes.php) is a list of places; a road is a line. It is declared here as
 * a handful of points the line must pass through, and bin/build-roads.php turns that into what the
 * pages print: the routed geometry and its length, the elevation profile, and the catalogue's own
 * attractions that happen to stand beside it. All of it lands in assets/v2/data/drumuri.json.
 *
 * The geometry is OpenStreetMap's, routed between the points below — nothing is copied from a
 * route-sharing site. Heights and climbs are measured on the profile, never typed in by hand.
 *
 *   title    the road's name, as riders say it
 *   ref      the road number on the signs; empty where there is none (a cycle path)
 *   modes    who it is for: 'moto', 'bike', or both; the first one is the page's default
 *   from/to  the two ends, as they appear in the heading
 *   lead     one sentence, for the card and under the heading
 *   intro    the opening paragraph of the page
 *   season   only where the closure is officially announced every year; otherwise left out
 *   cap      a ceiling for the profile in metres, where the road runs through a tunnel under a ridge
 *   points   [lat, lng] the line is routed through, in order — the first and last are the ends
 */

const MAP_ROADS = [
    'transfagarasan' => [
        'title'  => 'Transfăgărășan',
        'ref'    => 'DN7C',
        'modes'  => ['moto', 'bike'],
        'from'   => 'Cârțișoara',
        'to'     => 'Curtea de Argeș',
        'lead'   => 'Serpentinele de la Bâlea, tunelul de sub creastă și coborârea pe lângă lacul Vidraru.',
        'intro'  => 'Drumul traversează Munții Făgăraș de la nord la sud. Dinspre Cârțișoara urcă în serpentine pe lângă cascada Bâlea până la lacul glaciar, trece creasta printr-un tunel și coboară pe valea Argeșului, pe lângă barajul Vidraru și cetatea Poenari.',
        'season' => 'Sectorul dintre Piscu Negru și Bâlea Cascadă se închide iarna, de regulă din noiembrie până la sfârșitul lui iunie. Datele exacte le anunță CNAIR în fiecare an.',
        'cap'    => 2042,
        'points' => [[45.722, 24.578], [45.6044, 24.6168], [45.139, 24.675]],
    ],
    'transalpina' => [
        'title'  => 'Transalpina',
        'ref'    => 'DN67C',
        'modes'  => ['moto', 'bike'],
        'from'   => 'Novaci',
        'to'     => 'Sebeș',
        'lead'   => 'Cel mai înalt drum din România, peste Parâng, prin Rânca și pasul Urdele.',
        'intro'  => 'Transalpina leagă Oltenia de Transilvania peste Munții Parâng. Din Novaci urcă prin Rânca până în pasul Urdele, coboară la Obârșia Lotrului și urmează apoi valea Sebeșului, pe lângă lacul Oașa, până la Sebeș.',
        'season' => 'Sectorul dintre Rânca și Curpăt se închide iarna, în aceeași perioadă cu Transfăgărășanul. Datele exacte le anunță CNAIR în fiecare an.',
        'points' => [[45.180, 23.672], [45.295, 23.686], [45.443, 23.634], [45.956, 23.571]],
    ],
    'transbucegi' => [
        'title'  => 'Transbucegi',
        'ref'    => 'DJ713',
        'modes'  => ['moto', 'bike'],
        'from'   => 'Sinaia',
        'to'     => 'Piatra Arsă',
        'lead'   => 'Urcarea scurtă și abruptă din valea Prahovei până pe platoul Bucegilor.',
        'intro'  => 'Un drum scurt, cu multă diferență de nivel: pleacă de lângă Sinaia și urcă până pe platoul Bucegilor, la Piatra Arsă. De sus se merge pe jos spre Babele și Sfinx.',
        'points' => [[45.317, 25.508], [45.3845, 25.4725]],
    ],
    'transrarau' => [
        'title'  => 'Transrarău',
        'ref'    => 'DJ175B',
        'modes'  => ['moto', 'bike'],
        'from'   => 'Chiril',
        'to'     => 'Pojorâta',
        'lead'   => 'Peste masivul Rarău, pe sub Pietrele Doamnei, între valea Bistriței și Câmpulung Moldovenesc.',
        'intro'  => 'Drumul urcă din valea Bistriței, de la Chiril, până sub Pietrele Doamnei, și coboară spre Pojorâta, lângă Câmpulung Moldovenesc. E îngust, cu serpentine strânse.',
        'points' => [[47.397, 25.573], [47.451, 25.566], [47.516, 25.449]],
    ],
    'cheile-bicazului' => [
        'title'  => 'Cheile Bicazului',
        'ref'    => 'DN12C',
        'modes'  => ['moto'],
        'from'   => 'Gheorgheni',
        'to'     => 'Bicaz',
        'lead'   => 'Din depresiunea Giurgeului, pe lângă Lacul Roșu, printre pereții cheilor.',
        'intro'  => 'Drumul leagă Transilvania de Moldova peste Hășmaș. Din Gheorgheni urcă în pasul Pângărați, coboară la Lacul Roșu și intră apoi în chei, unde șoseaua se strecoară printre pereți de calcar.',
        'points' => [[46.722, 25.600], [46.790, 25.795], [46.912, 26.091]],
    ],
    'rucar-bran' => [
        'title'  => 'Culoarul Rucăr–Bran',
        'ref'    => 'DN73',
        'modes'  => ['moto'],
        'from'   => 'Câmpulung',
        'to'     => 'Râșnov',
        'lead'   => 'Vechiul drum dintre Țara Românească și Transilvania, între Piatra Craiului și Bucegi.',
        'intro'  => 'Din Câmpulung, drumul urcă prin Rucăr și Dâmbovicioara până în pasul Giuvala, apoi coboară prin satele risipite pe dealuri din jurul Branului, cu Piatra Craiului într-o parte și Bucegii în cealaltă.',
        'points' => [[45.268, 25.046], [45.515, 25.367], [45.590, 25.460]],
    ],
    'pasul-prislop' => [
        'title'  => 'Pasul Prislop',
        'ref'    => 'DN18',
        'modes'  => ['moto'],
        'from'   => 'Borșa',
        'to'     => 'Iacobeni',
        'lead'   => 'Din Maramureș în Bucovina, peste pasul dintre Munții Rodnei și Munții Maramureșului.',
        'intro'  => 'Drumul pleacă din Borșa, urcă în pasul Prislop și coboară pe valea Bistriței Aurii, prin Cârlibaba, până la Iacobeni.',
        'points' => [[47.655, 24.663], [47.577, 25.132], [47.433, 25.317]],
    ],
    'pasul-tihuta' => [
        'title'  => 'Pasul Tihuța',
        'ref'    => 'DN17',
        'modes'  => ['moto'],
        'from'   => 'Bistrița',
        'to'     => 'Vatra Dornei',
        'lead'   => 'Trecătoarea Bârgăului, cu viraje largi și priveliști deschise spre Călimani.',
        'intro'  => 'Din Bistrița, drumul urcă pe valea Bârgăului până în pasul Tihuța și coboară în Țara Dornelor. E un drum național larg, cu asfalt bun și viraje lungi.',
        'points' => [[47.133, 24.500], [47.346, 25.357]],
    ],
    'pasul-gutai' => [
        'title'  => 'Pasul Gutâi',
        'ref'    => 'DN18',
        'modes'  => ['moto'],
        'from'   => 'Baia Mare',
        'to'     => 'Sighetu Marmației',
        'lead'   => 'Serpentinele dintre Baia Mare și Maramureșul istoric.',
        'intro'  => 'Drumul urcă din Baia Mare în serpentine până în pasul Gutâi și coboară pe valea Marei, printre sate cu biserici și porți de lemn, până la Sighetu Marmației.',
        'points' => [[47.659, 23.568], [47.930, 23.890]],
    ],
    'valea-lotrului' => [
        'title'  => 'Valea Lotrului',
        'ref'    => 'DN7A',
        'modes'  => ['moto'],
        'from'   => 'Brezoi',
        'to'     => 'Petroșani',
        'lead'   => 'De la Olt până în Valea Jiului, pe lângă lacul Vidra și prin Obârșia Lotrului.',
        'intro'  => 'Drumul urmează Lotrul în amonte din Brezoi, prin Voineasa și pe lângă lacul Vidra, se întâlnește cu Transalpina la Obârșia Lotrului și coboară apoi spre Petroșani.',
        'points' => [[45.343, 24.247], [45.417, 23.960], [45.443, 23.634], [45.417, 23.370]],
    ],
    'defileul-jiului' => [
        'title'  => 'Defileul Jiului',
        'ref'    => 'DN66',
        'modes'  => ['moto'],
        'from'   => 'Bumbești-Jiu',
        'to'     => 'Petroșani',
        'lead'   => 'Șoseaua care ține malul Jiului printre Parâng și Vâlcan.',
        'intro'  => 'Între Bumbești-Jiu și Petroșani, drumul merge pe firul Jiului printr-un defileu îngust, cu viaducte și tuneluri de cale ferată deasupra apei.',
        'points' => [[45.178, 23.386], [45.417, 23.370]],
    ],
    'clisura-dunarii' => [
        'title'  => 'Clisura Dunării',
        'ref'    => 'DN57',
        'modes'  => ['bike', 'moto'],
        'from'   => 'Moldova Nouă',
        'to'     => 'Orșova',
        'lead'   => 'Pe malul Dunării, prin Cazane, pe traseul EuroVelo 6.',
        'intro'  => 'Drumul ține malul românesc al Dunării de la Moldova Nouă până la Orșova. E aproape plat, cu apa mereu în dreapta, și trece prin Cazanele Dunării, pe lângă chipul lui Decebal sculptat în stâncă. Pe aici trece și EuroVelo 6, traseul cicloturistic dintre Atlantic și Marea Neagră.',
        'points' => [[44.737, 21.666], [44.647, 21.955], [44.499, 22.106], [44.725, 22.396]],
    ],
    'canalul-bega' => [
        'title'  => 'Pista de pe canalul Bega',
        'ref'    => '',
        'modes'  => ['bike'],
        'from'   => 'Timișoara',
        'to'     => 'Otelec',
        'lead'   => 'Pistă asfaltată pe digul canalului, din Timișoara până aproape de granița cu Serbia.',
        'intro'  => 'Pista pornește din Timișoara și urmează digul canalului Bega spre vest, prin Sânmihaiu Român și Uivar. E plată și separată de trafic, potrivită și pentru o ieșire cu copiii.',
        'points' => [[45.7475, 21.2080], [45.7080, 21.0900], [45.6600, 20.9050], [45.6230, 20.8450]],
    ],
];

/** What each mode is called, and the icon it wears (v2 sprite ids). */
const MAP_ROAD_MODES = [
    'moto' => ['Motocicletă', 'pl-moto'],
    'bike' => ['Bicicletă', 'pi-bicycle'],
];
