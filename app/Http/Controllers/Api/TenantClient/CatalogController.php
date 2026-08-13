<?php

namespace App\Http\Controllers\Api\TenantClient;

use App\Http\Controllers\Controller;
use App\Models\Artist;
use App\Models\Event;
use App\Models\GeoLocality;
use App\Models\Venue;
use App\Support\PlainText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Fişele publice de eveniment, artist şi locaţie, pentru aplicaţia mobilă.
 *
 * DE CE UN CONTROLER NOU, când există deja `/tenant-client/events/{slug}`
 * Acela rezolvă întâi un TENANT (din `?tenant=` sau din domeniu) şi filtrează
 * după el. Aplicaţia Tixello nu aparţine niciunui tenant: feed-ul de shorts
 * trimite la evenimente, artişti şi locaţii din tot sistemul — ale tenanţilor
 * şi ale marketplace-urilor deopotrivă. Cerinţa e aceeaşi cu a feed-ului
 * (citire publică, fără cont), aşa că stă sub acelaşi prefix şi împrumută
 * aceleaşi CORS + throttle.
 *
 * Se caută după ID sau după slug: feed-ul are id-uri, linkurile au slug-uri.
 *
 * Se răspunde DOAR pentru conţinut deja public. Un eveniment nepublicat nu
 * există pentru aplicaţie, oricât de corect ar fi id-ul cerut.
 */
class CatalogController extends Controller
{
    /** Câte evenimente viitoare se listează pe fişa unui artist sau a unei locaţii. */
    private const RELATED_EVENTS = 12;

    public function event(Request $request, string $key): JsonResponse
    {
        $event = $this->findBy(Event::query()->with([
            'venue',
            'marketplaceOrganizer',
            'ticketTypes',
            'artists',
            'eventTypes',
        ]), $key);

        if (! $event || ! $this->eventIsVisible($event)) {
            return $this->missing('Evenimentul nu a fost găsit.');
        }

        return response()->json(['success' => true, 'data' => $this->eventPayload($event, full: true)]);
    }

    /**
     * Lista de evenimente PROPRII — ale tenantilor si ale marketplace-urilor
     * Tixello — pentru ecranele de descoperire din aplicatie.
     *
     * DE CE ARE PRIORITATE fata de Radar: Radarul TICS agrega preturi de pe alte
     * platforme si nu poate vinde nimic. Un eveniment de aici e vandut chiar de
     * noi, cu bilet in aplicatie. Ordinea o decide clientul, dar sursa asta
     * trebuie sa existe ca sa poata fi pusa prima.
     *
     * Se listeaza doar ce e publicat si inca nu a trecut. `scopeUpcoming` tine
     * cont de toate cele trei moduri de durata (o zi, interval, mai multe
     * intervale) — o simpla comparatie pe `event_date` ar fi ascuns
     * festivalurile aflate in desfasurare.
     */
    public function events(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 20), 50));
        $city = trim((string) $request->query('city', ''));
        $category = trim((string) $request->query('category', ''));

        $events = Event::query()
            ->where('is_published', true)
            ->upcoming()
            /* Categoria se filtreaza AICI, nu in aplicatie.
               Taxonomia scrie „Concert", „Concerte", „Concert live" — o
               comparatie de siruri egale in client rata aproape tot si ecranul
               de categorie ramanea gol. `LIKE` pe numele tipului de eveniment
               prinde variantele, iar `CAST` e obligatoriu: numele e traductibil
               si poate fi jsonb pe unele instalari. */
            ->when($category !== '', fn ($q) => $q->whereHas(
                'eventTypes',
                fn ($t) => $t->whereRaw('LOWER(CAST(name AS TEXT)) LIKE ?', ['%'.$this->categoryStem($category).'%']),
            ))
            ->with(['venue', 'ticketTypes', 'eventTypes'])
            /* Intai cele scoase in fata de organizator, apoi dupa cat de
               aproape sunt: un eveniment de saptamana viitoare e mai util
               decat unul de peste opt luni. */
            ->orderByDesc('is_homepage_featured')
            ->orderByRaw('COALESCE(event_date, range_start_date) ASC NULLS LAST')
            ->limit($city !== '' ? $limit * 4 : $limit)
            ->get()
            /* Orasul se filtreaza DUPA interogare, pe sala: evenimentele nu
               poarta un oras propriu, iar un join doar pentru filtrare ar fi
               complicat interogarea pentru un caz care oricum se rezolva pe
               cateva zeci de randuri. */
            ->when(
                $city !== '',
                fn ($rows) => $rows->filter(
                    fn (Event $e) => mb_strtolower((string) ($e->venue->city ?? '')) === mb_strtolower($city),
                ),
            )
            ->take($limit)
            ->map(fn (Event $e) => $this->eventPayload($e, full: false))
            ->values()
            ->all();

        return response()->json(['success' => true, 'data' => $events]);
    }

    /**
     * Evenimentele din jurul unui punct, ordonate dupa distanta.
     *
     * Filtrarea se face in DOUA trepte, si asta e intentionat:
     *
     * 1. In baza de date, pe un DREPTUNGHI (bounding box) in jurul punctului.
     *    E o comparatie pe doua coloane indexabile, deci taie catalogul la
     *    cateva zeci de randuri fara sa ceara functii trigonometrice in SQL —
     *    care oricum ar diferi intre Postgres si SQLite (dev ruleaza pe SQLite).
     * 2. In PHP, pe distanta REALA (haversine). Dreptunghiul e mai larg decat
     *    cercul in colturi, deci fara treapta a doua un eveniment la 138 km ar
     *    aparea pe o raza de 100 km.
     *
     * Salile FARA coordonate nu sunt excluse din interogare, ci trecute prin
     * `geo_localities` dupa numele orasului. Altfel „langa tine" ar fi aratat
     * doar salile carora cineva le-a completat manual lat/lng in admin —
     * adica o parte arbitrara din catalog, fara ca utilizatorul sa poata banui
     * ce lipseste.
     */
    public function nearby(Request $request): JsonResponse
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        /* Fara GPS, centrul e ORASUL ales in aplicatie. Nu e o consolare: pe o
           raza de 100 km, diferenta dintre pozitia exacta si centrul orasului
           schimba rareori lista — iar altfel functia ar fi existat doar pentru
           cine accepta permisiunea de locatie. */
        if ((! is_numeric($lat) || ! is_numeric($lng)) && trim((string) $request->query('city', '')) !== '') {
            $center = GeoLocality::query()
                ->where('name_ascii', $this->fold((string) $request->query('city')))
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('sort_order')
                ->first(['latitude', 'longitude']);

            if ($center) {
                $lat = (float) $center->latitude;
                $lng = (float) $center->longitude;
            }
        }

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return response()->json(['success' => false, 'message' => 'Coordonate lipsă.'], 422);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return response()->json(['success' => false, 'message' => 'Coordonate invalide.'], 422);
        }

        $radius = max(1, min((float) $request->query('radius', 100), 500));
        $limit = max(1, min((int) $request->query('limit', 20), 50));

        /* Un grad de latitudine ~111 km oriunde; unul de longitudine se
           ingusteaza spre poli, deci se imparte la cos(lat). Marja de 1.15
           acopera aproximarea, ca dreptunghiul sa nu taie din cerc. */
        $dLat = ($radius / 111.045) * 1.15;
        $cos = max(cos(deg2rad($lat)), 0.01);
        $dLng = ($radius / (111.045 * $cos)) * 1.15;

        $candidates = Event::query()
            ->where('is_published', true)
            ->upcoming()
            ->whereHas('venue', function ($v) use ($lat, $lng, $dLat, $dLng) {
                $v->where(function ($q) use ($lat, $lng, $dLat, $dLng) {
                    $q->whereBetween('lat', [$lat - $dLat, $lat + $dLat])
                        ->whereBetween('lng', [$lng - $dLng, $lng + $dLng]);
                })
                    ->orWhereNull('lat')
                    ->orWhereNull('lng');
            })
            ->with(['venue', 'ticketTypes', 'eventTypes'])
            /* 400, nu 20: dreptunghiul lasa sa treaca si salile fara
               coordonate (li se afla orasul abia in PHP), iar ordonarea e dupa
               DATA, nu dupa distanta. Cu o limita mica, un eveniment de maine
               de la 300 km ar fi ocupat locul unuia de peste o luna de la 5 km. */
            ->orderByRaw('COALESCE(event_date, range_start_date) ASC NULLS LAST')
            ->limit(400)
            ->get();

        $coords = $this->resolveVenueCoordinates($candidates);

        $near = $candidates
            ->map(function (Event $e) use ($coords, $lat, $lng) {
                $point = $coords[$e->venue?->id] ?? null;

                if (! $point) {
                    return null;
                }

                return [
                    'event' => $e,
                    'lat' => $point[0],
                    'lng' => $point[1],
                    'km' => $this->haversine($lat, $lng, $point[0], $point[1]),
                ];
            })
            ->filter()
            ->filter(fn (array $row) => $row['km'] <= $radius)
            ->sortBy('km')
            ->take($limit)
            ->map(fn (array $row) => $this->eventPayload($row['event'], full: false) + [
                'lat' => round($row['lat'], 5),
                'lng' => round($row['lng'], 5),
                'distance_km' => round($row['km'], 1),
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'center' => ['lat' => $lat, 'lng' => $lng],
                'radius_km' => $radius,
                'events' => $near,
            ],
        ]);
    }

    /**
     * Evenimentele dintr-un dreptunghi de harta — pentru harta care se
     * populeaza pe masura ce o plimbi.
     *
     * Separat de `nearby` fiindca intrebarea e alta: acolo „ce e aproape de
     * MINE", aici „ce e in ce vad ACUM". Nu exista centru, deci nu exista
     * distanta si nici ordonare dupa ea; ordinea e dupa data, iar limita e
     * mai mare — o harta cu 12 pini pe un judet intreg arata pustie.
     *
     * Dreptunghiul e plafonat: fara plafon, un utilizator care da zoom out
     * pana la continent ar cere toata baza de date la fiecare glisare.
     */
    public function inBounds(Request $request): JsonResponse
    {
        foreach (['north', 'south', 'east', 'west'] as $edge) {
            if (! is_numeric($request->query($edge))) {
                return response()->json(['success' => false, 'message' => 'Dreptunghi incomplet.'], 422);
            }
        }

        $north = (float) $request->query('north');
        $south = (float) $request->query('south');
        $east = (float) $request->query('east');
        $west = (float) $request->query('west');

        if ($north <= $south || $east <= $west) {
            return response()->json(['success' => false, 'message' => 'Dreptunghi invalid.'], 422);
        }

        /* Peste ~12 grade (≈1300 km) nu mai are sens sa desenam pini: la zoom-ul
           acela nu se disting oricum. Se raspunde cu lista goala si un semnal,
           ca aplicatia sa poata spune „mai apropie", nu sa para stricata. */
        if (($north - $south) > 12 || ($east - $west) > 12) {
            return response()->json([
                'success' => true,
                'data' => ['events' => [], 'too_wide' => true],
            ]);
        }

        $limit = max(1, min((int) $request->query('limit', 60), 120));

        $candidates = Event::query()
            ->where('is_published', true)
            ->upcoming()
            ->whereHas('venue', function ($v) use ($north, $south, $east, $west) {
                $v->where(function ($q) use ($north, $south, $east, $west) {
                    $q->whereBetween('lat', [$south, $north])
                        ->whereBetween('lng', [$west, $east]);
                })
                    ->orWhereNull('lat')
                    ->orWhereNull('lng');
            })
            ->with(['venue', 'ticketTypes', 'eventTypes'])
            ->orderByRaw('COALESCE(event_date, range_start_date) ASC NULLS LAST')
            ->limit(400)
            ->get();

        $coords = $this->resolveVenueCoordinates($candidates);

        $events = $candidates
            ->map(function (Event $e) use ($coords) {
                $point = $coords[$e->venue?->id] ?? null;

                return $point ? ['event' => $e, 'lat' => $point[0], 'lng' => $point[1]] : null;
            })
            ->filter()
            /* Al doilea filtru e obligatoriu: salile fara coordonate au trecut
               de interogare tocmai ca sa li se afle orasul, iar orasul aflat
               poate fi in afara dreptunghiului. */
            ->filter(fn (array $r) => $r['lat'] <= $north && $r['lat'] >= $south && $r['lng'] <= $east && $r['lng'] >= $west)
            ->take($limit)
            ->map(fn (array $r) => $this->eventPayload($r['event'], full: false) + [
                'lat' => round($r['lat'], 5),
                'lng' => round($r['lng'], 5),
            ])
            ->values()
            ->all();

        return response()->json(['success' => true, 'data' => ['events' => $events, 'too_wide' => false]]);
    }

    /**
     * Coordonata fiecarei sali din setul dat: a ei, sau a orasului ei.
     *
     * Localitatile se citesc INTR-O SINGURA interogare, pe numele pliat (fara
     * diacritice, litere mici) — coloana `name_ascii` exista exact pentru asta
     * si e indexata. O interogare per sala ar fi insemnat cateva zeci de
     * SELECT-uri pentru un ecran care se deschide la fiecare pornire.
     *
     * @param  \Illuminate\Support\Collection<int, Event>  $events
     * @return array<int, array{0: float, 1: float}>
     */
    private function resolveVenueCoordinates($events): array
    {
        $out = [];
        $needCity = [];

        foreach ($events as $event) {
            $venue = $event->venue;

            if (! $venue || isset($out[$venue->id])) {
                continue;
            }

            if ($venue->lat !== null && $venue->lng !== null) {
                $out[$venue->id] = [(float) $venue->lat, (float) $venue->lng];

                continue;
            }

            $city = $this->fold((string) ($venue->city ?? ''));

            if ($city !== '') {
                $needCity[$venue->id] = $city;
            }
        }

        if ($needCity === []) {
            return $out;
        }

        $localities = GeoLocality::query()
            ->whereIn('name_ascii', array_values(array_unique($needCity)))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            /* Municipiul inaintea satului omonim: „Alba Iulia" e mai probabil
               orasul decat o localitate componenta cu acelasi nume.
               `unique` INAINTE de `keyBy` nu e decorativ: `keyBy` pastreaza
               ULTIMA aparitie a unei chei, deci ar fi ales exact satul, adica
               fix pe dos fata de ordonare. */
            ->orderBy('sort_order')
            ->get(['name_ascii', 'latitude', 'longitude'])
            ->unique('name_ascii')
            ->keyBy('name_ascii');

        foreach ($needCity as $venueId => $city) {
            $hit = $localities->get($city);

            if ($hit) {
                $out[$venueId] = [(float) $hit->latitude, (float) $hit->longitude];
            }
        }

        return $out;
    }

    /** Distanta pe sfera, in kilometri. */
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Litere mici, fara diacritice — forma in care sunt indexate localitatile. */
    private function fold(string $value): string
    {
        $t = mb_strtolower(trim($value));

        return strtr($t, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't']);
    }

    /**
     * Cautare in catalogul propriu: evenimente, artisti, locatii.
     *
     * Se cauta pe TEXT SIMPLU, cu `LIKE`, si asta e o alegere, nu o lene:
     * `events.title` si `venues.name` sunt coloane traductibile (JSON pe unele
     * instalari, TEXT pe productie — vezi driftul cunoscut de la `venues.name`),
     * iar un index de cautare full-text peste ele ar trebui intretinut separat
     * pentru fiecare limba. La dimensiunea catalogului, `LIKE` pe cateva zeci de
     * mii de randuri e sub 50 ms si nu poate iesi din sincron.
     *
     * `CAST(... AS TEXT)` e obligatoriu: fara el, Postgres refuza `jsonb LIKE
     * text` si intreaga cautare crapa pe instalarile unde coloana chiar e JSON.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['success' => true, 'data' => ['events' => [], 'artists' => [], 'venues' => []]]);
        }

        $like = '%'.mb_strtolower($q).'%';
        $limit = max(1, min((int) $request->query('limit', 10), 25));

        $events = Event::query()
            ->where('is_published', true)
            ->upcoming()
            ->whereRaw('LOWER(CAST(title AS TEXT)) LIKE ?', [$like])
            ->with(['venue', 'ticketTypes', 'eventTypes'])
            ->orderByRaw('COALESCE(event_date, range_start_date) ASC NULLS LAST')
            ->limit($limit)
            ->get()
            ->map(fn (Event $e) => $this->eventPayload($e, full: false))
            ->values()
            ->all();

        $artists = Artist::query()
            ->whereRaw('LOWER(CAST(name AS TEXT)) LIKE ?', [$like])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Artist $a) => [
                'id' => $a->id,
                'slug' => $a->slug,
                'name' => PlainText::of($a->name),
                'role' => $a->role ?? null,
                'image' => $this->url($a->portrait_url) ?? $this->url($a->main_image_url),
            ])
            ->values()
            ->all();

        $venues = Venue::query()
            ->whereRaw('LOWER(CAST(name AS TEXT)) LIKE ?', [$like])
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Venue $v) => [
                'id' => $v->id,
                'slug' => $v->slug,
                'name' => PlainText::of($v->name),
                'city' => $this->text($v->city),
                'image' => $this->url($v->meta['portrait'] ?? null) ?? $this->url($v->image_url),
            ])
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => ['events' => $events, 'artists' => $artists, 'venues' => $venues],
        ]);
    }

    public function artist(Request $request, string $key): JsonResponse
    {
        $artist = $this->findBy(Artist::query(), $key);

        if (! $artist) {
            return $this->missing('Artistul nu a fost găsit.');
        }

        $events = $this->upcomingEvents($artist->events()->getQuery());

        return response()->json(['success' => true, 'data' => [
            'id' => $artist->id,
            'slug' => $artist->slug,
            'name' => PlainText::of($artist->name),
            'role' => $artist->role ?? null,
            'bio' => $this->plainHtml(PlainText::of($artist->bio_html)),
            'city' => $this->text($artist->city),
            'country' => $this->text($artist->country),
            'image' => $this->url($artist->portrait_url) ?? $this->url($artist->main_image_url),
            'cover' => $this->url($artist->main_image_url) ?? $this->url($artist->portrait_url),
            /* Doar reţelele care chiar au un link — un rând de pictograme din
               care jumătate nu duc nicăieri e mai rău decât trei care duc. */
            'links' => array_filter([
                'website' => $this->text($artist->website),
                'facebook' => $this->text($artist->facebook_url),
                'instagram' => $this->text($artist->instagram_url),
                'tiktok' => $this->text($artist->tiktok_url),
                'youtube' => $this->text($artist->youtube_url),
                'spotify' => $this->text($artist->spotify_url),
            ]),
            'followers' => array_filter([
                'facebook' => $this->count($artist->facebook_followers ?? $artist->followers_facebook),
                'instagram' => $this->count($artist->instagram_followers ?? $artist->followers_instagram),
                'tiktok' => $this->count($artist->tiktok_followers ?? $artist->followers_tiktok),
                'youtube' => $this->count($artist->youtube_followers ?? $artist->followers_youtube),
                'spotify' => $this->count($artist->spotify_followers),
            ], fn ($v) => $v !== null),
            'events' => $events,
        ]]);
    }

    public function venue(Request $request, string $key): JsonResponse
    {
        $venue = $this->findBy(Venue::query(), $key);

        if (! $venue) {
            return $this->missing('Locația nu a fost găsită.');
        }

        $reviews = $venue->google_reviews_payload;

        /* Coloanele se cheama `lat`/`lng`, nu `latitude`/`longitude`.
           Raspunsul citea numele lungi — care nu exista pe model — deci
           trimitea mereu null, iar aplicatia arata caseta decorativa in loc de
           harta pentru ORICE locatie, inclusiv pentru cele cu coordonate
           completate corect in admin.

           Cand sala chiar n-are coordonate, se cade pe centrul orasului si se
           SPUNE asta prin `location_approx`: un pin in centrul Clujului e util
           („e in Cluj"), un pin in centrul Clujului prezentat ca adresa salii
           e o minciuna mica dar suparatoare. */
        $lat = is_numeric($venue->lat) ? (float) $venue->lat : null;
        $lng = is_numeric($venue->lng) ? (float) $venue->lng : null;
        $approx = false;

        if ($lat === null || $lng === null) {
            $city = $this->fold((string) ($venue->city ?? ''));

            $hit = $city === '' ? null : GeoLocality::query()
                ->where('name_ascii', $city)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->orderBy('sort_order')
                ->first(['latitude', 'longitude']);

            if ($hit) {
                $lat = (float) $hit->latitude;
                $lng = (float) $hit->longitude;
                $approx = true;
            }
        }

        return response()->json(['success' => true, 'data' => [
            'id' => $venue->id,
            'slug' => $venue->slug,
            'name' => PlainText::of($venue->name),
            'city' => $this->text($venue->city),
            'address' => $this->text($venue->address),
            'country' => $this->text($venue->country),
            'capacity' => $this->count($venue->capacity ?? $venue->capacity_total),
            'description' => $this->plainHtml(PlainText::of($venue->description)),
            'image' => $this->url($venue->image_url),
            'portrait' => $this->url($venue->meta['portrait'] ?? null),
            'gallery' => $this->gallery($venue->gallery),
            'lat' => $lat,
            'lng' => $lng,
            'location_approx' => $approx,
            'rating' => isset($reviews['rating']) && is_numeric($reviews['rating']) ? (float) $reviews['rating'] : null,
            'review_count' => isset($reviews['review_count']) ? (int) $reviews['review_count'] : null,
            'reviews' => $reviews['reviews'] ?? [],
            'events' => $this->upcomingEvents($venue->events()->getQuery()),
        ]]);
    }

    /**
     * Cauta o locatie dupa NUME, pentru sursele externe.
     *
     * Radarul TICS agrega evenimente de pe alte platforme si tine sala doar ca
     * text — n-are id-ul nostru. Fara punctul asta, numele salii de pe un
     * eveniment din Radar nu putea duce nicaieri.
     *
     * Potrivirea e conservatoare: intai exact, apoi „incepe cu". NU se face
     * potrivire aproximativa — mai bine niciun rezultat decat sala gresita, care
     * ar trimite omul cu 300 de km in alta parte. Cand orasul e cunoscut, se
     * foloseste ca departajare intre sali cu acelasi nume.
     */
    public function venueLookup(Request $request): JsonResponse
    {
        $name = trim((string) $request->query('name', ''));
        $city = trim((string) $request->query('city', ''));

        if (mb_strlen($name) < 3) {
            return $this->missing('Nume prea scurt.');
        }

        $base = fn () => Venue::query()
            ->when($city !== '', fn ($q) => $q->where('city', $city));

        $venue = $base()->whereRaw('LOWER(CAST(name AS TEXT)) = ?', [mb_strtolower($name)])->first()
            ?? $base()->whereRaw('LOWER(CAST(name AS TEXT)) LIKE ?', [mb_strtolower($name).'%'])->first();

        /* Cu orasul dat si fara rezultat, mai incercam o data fara el: sursele
           externe scriu orasul altfel („Bucuresti" vs „București"). */
        if (! $venue && $city !== '') {
            $venue = Venue::query()
                ->whereRaw('LOWER(CAST(name AS TEXT)) = ?', [mb_strtolower($name)])
                ->first();
        }

        if (! $venue) {
            return $this->missing('Locația nu a fost găsită.');
        }

        return response()->json(['success' => true, 'data' => ['id' => $venue->id, 'slug' => $venue->slug]]);
    }

    /* ================= ajutoare ================= */

    /**
     * Un ID numeric sau un slug. Feed-ul trimite id-uri, linkurile trimit
     * slug-uri, iar ecranul e acelaşi — deci acceptăm ambele forme în loc să
     * cerem clientului să ştie care e care.
     */
    private function findBy($query, string $key)
    {
        return ctype_digit($key)
            ? $query->whereKey((int) $key)->first()
            : $query->where('slug', $key)->first();
    }

    private function missing(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 404);
    }

    /**
     * Un eveniment nepublicat nu există pentru aplicaţie.
     *
     * Coloana lipseşte pe unele instalări, aşa că absenţa ei se citeşte ca
     * „vizibil" — altfel un deployment fără ea ar întoarce 404 la tot.
     */
    private function eventIsVisible(Event $event): bool
    {
        return ! isset($event->is_published) || (bool) $event->is_published;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function upcomingEvents($query): array
    {
        try {
            return $query
                ->with(['venue', 'eventTypes'])
                ->when(
                    true,
                    fn ($q) => $q->whereDate('event_date', '>=', now()->toDateString()),
                )
                ->orderBy('event_date')
                ->limit(self::RELATED_EVENTS)
                ->get()
                ->filter(fn (Event $e) => $this->eventIsVisible($e))
                ->map(fn (Event $e) => $this->eventPayload($e, full: false))
                ->values()
                ->all();
        } catch (\Throwable) {
            /* Relaţia sau coloana de dată lipsesc pe acest deployment: fişa e
               tot utilă fără lista de evenimente. */
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(Event $event, bool $full): array
    {
        $date = $event->event_date;
        $venue = $event->relationLoaded('venue') ? $event->venue : null;

        $base = [
            'id' => $event->id,
            'slug' => $event->slug,
            'title' => PlainText::of($event->title),
            'subtitle' => PlainText::of($event->subtitle),
            'date' => $date?->toDateString(),
            'day' => $date?->format('d'),
            'month' => $date ? $this->monthShort((int) $date->format('n')) : null,
            'date_label' => $date ? $date->format('d').' '.$this->monthShort((int) $date->format('n')) : null,
            'time' => $event->start_time ? substr((string) $event->start_time, 0, 5) : null,
            'city' => $this->text($venue?->city) ?? $this->text($event->city ?? null),
            'venue' => $venue ? [
                'id' => $venue->id,
                'slug' => $venue->slug,
                'name' => PlainText::of($venue->name),
                'city' => $this->text($venue->city),
                'address' => $this->text($venue->address),
            ] : null,
            'poster' => $this->url($event->poster_url) ?? $this->url($event->hero_image_url),
            /* Si in forma scurta: ecranele de categorie si de descoperire
               filtreaza dupa ea, iar fara ea toate evenimentele noastre cadeau
               intr-o singura galeata numita „Eveniment". */
            'category' => $event->relationLoaded('eventTypes')
                ? PlainText::of($event->eventTypes->first()?->name)
                : null,
            'price_from' => $this->priceFrom($event),
            'is_cancelled' => (bool) ($event->cancelled_at ?? false),
            'is_postponed' => (bool) ($event->postponed_at ?? false),
        ];

        if (! $full) {
            return $base;
        }

        return $base + [
            'hero' => $this->url($event->hero_image_url) ?? $this->url($event->poster_url),
            'gallery' => $this->gallery($event->gallery ?? null),
            'organizer' => $event->relationLoaded('marketplaceOrganizer') && $event->marketplaceOrganizer
                ? PlainText::of($event->marketplaceOrganizer->name)
                : null,
            'short_description' => $this->plainHtml(PlainText::of($event->short_description)),
            'description' => $this->plainHtml(PlainText::of($event->description)),
            'terms' => $this->plainHtml(PlainText::of($event->ticket_terms)),
            'pricing' => $this->pricing($event),
            'ticket_types' => $this->ticketTypes($event),
            'artists' => $event->relationLoaded('artists')
                ? $event->artists->map(fn (Artist $a) => [
                    'id' => $a->id,
                    'slug' => $a->slug,
                    'name' => PlainText::of($a->name),
                    'role' => $a->role ?? null,
                    'image' => $this->url($a->portrait_url) ?? $this->url($a->main_image_url),
                ])->values()->all()
                : [],
        ];
    }

    /**
     * Categoriile de bilet, fără nimic despre stoc în cifre exacte.
     *
     * `available` e un DA/NU, nu un număr: stocul rămas e informaţie
     * comercială a organizatorului, iar un endpoint public care o publică o dă
     * şi concurenţei. Aplicaţia are nevoie doar să ştie ce poate cumpăra.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ticketTypes(Event $event): array
    {
        if (! $event->relationLoaded('ticketTypes')) {
            return [];
        }

        return $event->ticketTypes
            ->filter(fn ($t) => ($t->status ?? null) === 'active')
            /* Biletele marcate „doar POS" nu se vand online, deci n-au ce cauta
               intr-o lista din care utilizatorul poate alege: le-ar vedea, le-ar
               adauga in cos si ar afla abia la plata ca nu se poate. */
            ->reject(fn ($t) => (bool) ($t->meta['pos_only'] ?? false))
            ->map(function ($t) {
                /* `display_price`, NU `price`.
                   Pe TicketType, `price` e accesor pentru pretul de REDUCERE si
                   intoarce null cand nu exista una — pretul intreg sta in
                   `price_cents`. Cu `price` ajungeau in aplicatie doar valori
                   nule, adica toate biletele afisate cu 0 lei.
                   `display_price` alege singur: reducerea daca e in fereastra
                   ei, altfel pretul intreg. */
                $price = (float) $t->display_price;
                $full = (float) $t->price_max;

                return [
                    'id' => $t->id,
                    'name' => PlainText::of($t->name),
                    'description' => $this->plainHtml(PlainText::of($t->description ?? null)),
                    'price' => $price > 0 ? $price : null,
                    // pretul taiat, doar cand chiar exista o reducere activa
                    'full_price' => $full > $price && $price > 0 ? $full : null,
                    /* Ce include biletul. Repeaterul din admin e „simplu", deci
                       tine siruri; acceptam si forma cu obiecte, pentru randuri
                       scrise inainte. */
                    'perks' => $this->perks($t->perks ?? null),
                    'available' => ! isset($t->quota_total) || (int) $t->quota_total !== 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Cine ia comisionul si cum, pentru evenimentul asta.
     *
     * REGULA, si motivul ei:
     *
     *  - EVENIMENT DE MARKETPLACE (ambilet.ro, bilete.online...): se aplica
     *    EXCLUSIV comisionul marketplace-ului, in modul lui (inclus in pret sau
     *    adaugat peste). Tixello NU mai adauga nimic — isi incaseaza partea de
     *    la marketplace, iar un al doilea comision ar taxa cumparatorul de doua
     *    ori pentru acelasi bilet.
     *
     *  - EVENIMENT DE TENANT: comisionul Tixello, adaugat peste pret. Aici
     *    Tixello e platforma care vinde, deci comisionul lui e singurul.
     *
     * Cifrele vin din `getEffectiveCommission*()` — aceleasi pe care le
     * foloseste si decontarea, ca aplicatia sa nu ajunga sa afiseze un total
     * diferit de cel din contabilitate.
     *
     * @return array{source: string, mode: string, rate: float}
     */
    private function pricing(Event $event): array
    {
        $isMarketplace = (bool) $event->marketplace_organizer_id;

        $rate = (float) $event->getEffectiveCommissionRate();
        $mode = $event->getEffectiveCommissionMode();

        /* Pentru evenimentele de tenant, plafonul implicit al modelului e 5%
           — o valoare istorica, nepotrivita pentru vanzarea din aplicatie.
           Cand tenantul n-are nimic configurat, cade pe cota aplicatiei. */
        if (! $isMarketplace && $event->commission_rate === null && ($event->tenant->commission_rate ?? null) === null) {
            $rate = (float) config('tixello.app_commission_rate', 2.0);
        }

        return [
            'source' => $isMarketplace ? 'marketplace' : 'tenant',
            'mode' => $mode === 'added_on_top' ? 'added_on_top' : 'included',
            'rate' => round($rate, 2),
        ];
    }

    /**
     * Radacina dupa care se cauta categoria.
     *
     * Aplicatia trimite eticheta din interfata („Concerte", „Experiențe"), iar
     * taxonomia scrie altfel: „Concert", „Concert live", „Experiență". O
     * potrivire pe sirul intreg rateaza aproape tot — de aici cautarea pe
     * primele cinci litere, fara diacritice, care acopera si singularul, si
     * pluralul, si formele compuse. Cinci: destul cat sa nu confunde „teatru"
     * cu „tenis", scurt cat sa prinda „Concert" din „Concerte".
     */
    private function categoryStem(string $label): string
    {
        $t = mb_strtolower(trim($label));
        $t = strtr($t, ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't']);

        return mb_substr($t, 0, 5);
    }

    /** @return array<int, string> */
    private function perks(mixed $perks): array
    {
        if (is_string($perks)) {
            $perks = json_decode($perks, true);
        }

        if (! is_array($perks)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($p) {
            $text = is_string($p) ? $p : ($p['text'] ?? null);

            return is_string($text) && trim($text) !== '' ? trim($text) : null;
        }, $perks)));
    }

    private function priceFrom(Event $event): ?float
    {
        try {
            $types = $event->relationLoaded('ticketTypes')
                ? $event->ticketTypes
                : $event->ticketTypes()->where('status', 'active')->get();

            $prices = $types
                ->filter(fn ($t) => ($t->status ?? null) === 'active'
                    && ! ($t->meta['pos_only'] ?? false)
                    && (float) $t->display_price > 0)
                ->map(fn ($t) => (float) $t->display_price);

            return $prices->isEmpty() ? null : $prices->min();
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<int, string> */
    private function gallery(mixed $gallery): array
    {
        if (is_string($gallery)) {
            $gallery = json_decode($gallery, true);
        }

        if (! is_array($gallery)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($item) {
            $raw = is_string($item) ? $item : ($item['url'] ?? $item['path'] ?? $item['src'] ?? null);

            return is_string($raw) ? $this->url($raw) : null;
        }, $gallery)));
    }

    /** Cale de pe discul public sau URL absolut — clientul primeşte mereu URL. */
    private function url(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : Storage::disk('public')->url(ltrim($path, '/'));
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function count(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** Descrierile din catalog sunt HTML; ecranele afişează text. */
    private function plainHtml(?string $html): ?string
    {
        if (! is_string($html) || trim($html) === '') {
            return null;
        }

        $text = trim(preg_replace(
            '/\n{3,}/u',
            "\n\n",
            html_entity_decode(
                strip_tags(preg_replace('#<(br|/p|/div|/li)[^>]*>#i', "\n", $html)),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            ),
        ));

        return $text === '' ? null : $text;
    }

    private function monthShort(int $month): string
    {
        return ['ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sep', 'oct', 'noi', 'dec'][$month - 1] ?? '';
    }
}
