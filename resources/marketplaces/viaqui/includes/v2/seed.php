<?php
/**
 * Viaqui v2: starter catalogue for the shared shell and the homepage.
 *
 * Used ONLY while marketplace 4 has no categories or cities in Tixello core (v2/nav.php checks). It gives the menu,
 * the footer and the homepage something true to show before the database is filled: the twelve main categories
 * the platform will carry and the first countries and cities on the roadmap. It holds no prices, counts or ratings.
 * As soon as the API returns categories, none of this is read.
 *
 * Returns the same keys v2/nav.php builds from the API.
 */

// slug => [name, image key under assets/v2/img/cat-{key}.webp, one line, subcategories]
const V2_SEED_CATEGORIES = [
    'museums-exhibitions' => ['Museums & Exhibitions', 'muzee-expozitii', 'Collections worth an unhurried afternoon.', ['History museums', 'Art galleries', 'Science centres', 'Open-air museums', 'Temporary exhibitions']],
    'culture-art' => ['Culture & Art', 'cultura-arta', 'Palaces, concert halls and living traditions.', ['Castles & palaces', 'Citadels', 'Monasteries', 'Concert halls', 'Old towns']],
    'tours-sightseeing' => ['Tours & Sightseeing', 'tururi-experiente-turistice', 'Walks, rides and stories with a local guide.', ['Walking tours', 'Day trips', 'Boat trips', 'Food tours', 'Bike tours']],
    'nature-outdoors' => ['Nature & Outdoors', 'natura-outdoor', 'Gorges, caves, lakes and trails with a ticket gate.', ['Caves', 'Salt mines', 'National parks', 'Gardens', 'Viewpoints']],
    'adventure-parks' => ['Adventure Parks', 'parcuri-de-aventura', 'Zip lines, rope courses and a healthy dose of nerve.', ['Rope courses', 'Zip lines', 'Via ferrata', 'Climbing', 'Rafting']],
    'theme-parks' => ['Theme Parks', 'parcuri-de-distractii', 'Rides, slides and a full day of noise.', ['Amusement parks', 'Water parks', 'Dino parks', 'Trampoline parks']],
    'zoos-aquariums' => ['Zoos & Aquariums', 'acvarii-zoo-animale', 'Animals up close, from sanctuaries to sea life.', ['Zoos', 'Aquariums', 'Sanctuaries', 'Farms', 'Butterfly houses']],
    'family-kids' => ['Family & Kids', 'familie-copii', 'Days out that children ask to repeat.', ['Play centres', 'Kids museums', 'Puppet theatres', 'Family shows']],
    'escape-rooms' => ['Escape Rooms', 'escape-rooms', 'Sixty minutes, one locked door, your best friends.', ['Mystery rooms', 'Horror rooms', 'Outdoor quests', 'VR rooms']],
    'workshops-creative' => ['Workshops & Creative', 'ateliere-experiente-creative', 'Make something with your hands and take it home.', ['Pottery', 'Cooking classes', 'Painting', 'Crafts', 'Tastings']],
    'learning-experiences' => ['Learning Experiences', 'educatie-invatare-experientiala', 'Planetariums, labs and lessons you can touch.', ['Planetariums', 'Science shows', 'Farm schools', 'History re-enactments']],
    'groups-corporate' => ['Groups & Corporate', 'corporate-grupuri', 'One booking for the whole team, class or party.', ['Team building', 'School trips', 'Private tours', 'Celebrations']],
];

// country => [slug, [city slug => [name, capital?]], the first four become picture tiles]
const V2_SEED_COUNTRIES = [
    'Romania' => ['romania', ['bucharest' => ['Bucharest', true], 'brasov' => ['Brașov', false], 'sibiu' => ['Sibiu', false], 'sighisoara' => ['Sighișoara', false], 'constanta' => ['Constanța', false], 'cluj-napoca' => ['Cluj-Napoca', false], 'sinaia' => ['Sinaia', false], 'timisoara' => ['Timișoara', false], 'iasi' => ['Iași', false], 'oradea' => ['Oradea', false]]],
    'Italy' => ['italy', ['rome' => ['Rome', true], 'florence' => ['Florence', false], 'venice' => ['Venice', false], 'milan' => ['Milan', false], 'naples' => ['Naples', false], 'turin' => ['Turin', false], 'bologna' => ['Bologna', false], 'verona' => ['Verona', false]]],
    'Spain' => ['spain', ['madrid' => ['Madrid', true], 'barcelona' => ['Barcelona', false], 'seville' => ['Seville', false], 'valencia' => ['Valencia', false], 'granada' => ['Granada', false], 'bilbao' => ['Bilbao', false], 'malaga' => ['Málaga', false]]],
    'France' => ['france', ['paris' => ['Paris', true], 'lyon' => ['Lyon', false], 'nice' => ['Nice', false], 'bordeaux' => ['Bordeaux', false], 'marseille' => ['Marseille', false], 'strasbourg' => ['Strasbourg', false], 'toulouse' => ['Toulouse', false]]],
    'Germany' => ['germany', ['berlin' => ['Berlin', true], 'munich' => ['Munich', false], 'hamburg' => ['Hamburg', false], 'cologne' => ['Cologne', false], 'dresden' => ['Dresden', false], 'frankfurt' => ['Frankfurt', false], 'nuremberg' => ['Nuremberg', false]]],
    'Austria' => ['austria', ['vienna' => ['Vienna', true], 'salzburg' => ['Salzburg', false], 'innsbruck' => ['Innsbruck', false], 'graz' => ['Graz', false], 'linz' => ['Linz', false], 'hallstatt' => ['Hallstatt', false]]],
    'Greece' => ['greece', ['athens' => ['Athens', true], 'thessaloniki' => ['Thessaloniki', false], 'heraklion' => ['Heraklion', false], 'rhodes' => ['Rhodes', false], 'corfu' => ['Corfu', false], 'santorini' => ['Santorini', false]]],
    'Portugal' => ['portugal', ['lisbon' => ['Lisbon', true], 'porto' => ['Porto', false], 'sintra' => ['Sintra', false], 'faro' => ['Faro', false], 'coimbra' => ['Coimbra', false], 'evora' => ['Évora', false]]],
];

// Photos we already hold (Wikimedia Commons, credited in the footer): seed slug => [file, width, height, alt]
const V2_SEED_CITY_PHOTOS = [
    'brasov' => ['img/dest-brasov.webp', 960, 800, 'Council Square in Brașov seen from above'],
    'sibiu' => ['img/dest-sibiu.webp', 640, 540, 'The Large Square in Sibiu with the Council Tower'],
    'bucharest' => ['img/dest-bucuresti.webp', 640, 540, 'The Romanian Athenaeum in Bucharest'],
    'constanta' => ['img/dest-constanta.webp', 640, 540, 'The Casino in Constanța on the seafront'],
    'sighisoara' => ['img/dest-sighisoara.webp', 640, 540, 'A street of painted houses in the citadel of Sighișoara'],
];

// slug => [title, category, minutes, thumb, cover (or null), one line]
const V2_SEED_GUIDES = [
    'brasov-in-two-days' => ['Brașov in two days: the old town, Tâmpa and a castle before lunch', 'City guides', 7, 'img/g-brasov.webp', ['img/insp-brasov.webp', 640, 480, 'Council Square in Brașov with Tâmpa behind it'], 'What to book ahead, what to walk into, and where the queues are shortest.'],
    'sinaia-and-the-bucegi' => ['Sinaia and the Bucegi mountains: a royal summer residence in the pines', 'Culture & history', 6, 'img/g-sinaia.webp', ['img/hero-900.webp', 900, 643, 'Peleș Castle in Sinaia'], 'Peleș at opening time, then the cable car while the light is good.'],
    'a-weekend-in-maramures' => ['A weekend in Maramureș: wooden churches and a steam train', 'City guides', 8, 'img/g-maramures.webp', null, 'A slow itinerary through villages that still build in wood.'],
    'bucharest-beyond-the-obvious' => ['Bucharest beyond the obvious: seven places locals take their guests', 'City guides', 6, 'img/g-bucuresti.webp', null, 'Grouped by neighbourhood, so you walk less and see more.'],
    'cluj-with-children' => ['Cluj-Napoca with children: parks, science and somewhere to run', 'Family & kids', 5, 'img/g-cluj.webp', null, 'Short visits that fit between naps and meals.'],
    'the-black-sea-out-of-season' => ['The Black Sea coast out of season', 'Adventure & adrenaline', 6, 'img/g-litoral.webp', null, 'Empty beaches, an Art Nouveau casino and two thousand years of harbour life.'],
];

function v2_seed_nav(): array
{
    $out = ['seed' => true];

    $out['categories'] = [];
    foreach (V2_SEED_CATEGORIES as $slug => [$name, $img, $desc, $subs]) {
        $out['categories'][] = [
            'slug' => $slug,
            'name' => $name,
            'desc' => $desc,
            'image' => v2_asset('img/cat-' . $img . '.webp'),
            'thumb' => v2_asset('img/cat-' . $img . '-320.webp'),
            'srcset' => v2_asset('img/cat-' . $img . '-320.webp') . ' 320w, ' . v2_asset('img/cat-' . $img . '-360.webp') . ' 360w, ' . v2_asset('img/cat-' . $img . '.webp') . ' 640w',
            'count' => 0,
            'href' => '/' . $slug,
            'subs' => array_map(function ($s) use ($slug) {
                return ['name' => $s, 'href' => '/' . $slug . '/' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-')];
            }, $subs),
        ];
    }
    $out['categoryBySlug'] = array_column($out['categories'], null, 'slug');

    $out['cities'] = [];
    $out['regions'] = [];
    foreach (V2_SEED_COUNTRIES as $country => [$countrySlug, $cities]) {
        $featured = [];
        foreach ($cities as $slug => [$name, $capital]) {
            $photo = V2_SEED_CITY_PHOTOS[$slug] ?? null;
            $city = [
                'slug' => $slug, 'name' => $name, 'region' => $country, 'count' => 0, 'capital' => $capital, 'href' => '/' . $slug,
                'photo' => $photo ? [v2_asset($photo[0]), $photo[1], $photo[2], $photo[3]] : null,
            ];
            $out['cities'][$slug] = $city;
            $featured[] = $city;
        }
        $out['regions'][] = ['name' => $country, 'slug' => $countrySlug, 'citiesCount' => count($featured), 'featured' => $featured, 'more' => []];
    }
    $out['citiesList'] = array_values($out['cities']);
    $out['allCities'] = array_map(function ($c) {
        return ['slug' => $c['slug'], 'name' => $c['name'], 'region' => $c['region'], 'county' => '', 'count' => 0, 'image' => null];
    }, $out['cities']);
    $out['intentCities'] = ['bucharest', 'brasov', 'sibiu', 'rome', 'vienna', 'lisbon'];

    $out['guides'] = [];
    foreach (V2_SEED_GUIDES as $slug => [$title, $category, $minutes, $thumb, $cover, $excerpt]) {
        $out['guides'][] = [
            'slug' => $slug, 'title' => $title, 'excerpt' => $excerpt, 'category' => $category, 'readTime' => $minutes,
            'href' => '/guides/' . $slug, 'thumb' => v2_asset($thumb),
            'cover' => $cover ? [v2_asset($cover[0]), $cover[1], $cover[2], $cover[3]] : null,
            'hasOwnImage' => (bool) $cover,
        ];
    }

    return $out;
}
