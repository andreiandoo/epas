<?php
/**
 * Leisure Venue page — atmospheric immersive template.
 * Included from event.php when display_template === 'leisure_venue'.
 *
 * Source design: epas/resources/marketplaces/ambilet/designs/sf-ana.html
 * Stack: Alpine.js (CDN) + Tailwind (inline config) + Leaflet (CDN) +
 *        Plus Jakarta Sans + Fraunces (Google Fonts).
 *
 * Data sources:
 *  - $ev (event preload)
 *  - $ev.venue_config — seasons, schedule, pricing_rules, closed_dates,
 *    amenities, gallery, hero_images, contact_phone, directions_url,
 *    + NEW (vezi LIST_BACKEND_FIELDS.md): trails, faqs, flora, attractions,
 *    stats_highlights, services_overview, getting_there, location_map,
 *    safety_warning, etc.
 *  - $issuers din $eventPreload['data']['organizer'] — 2 societati emitente
 *  - API live: GET /marketplace-events/{slug}/date-availability?month|date
 */

// ── Multi-locale (B4): detectare strict-scoped pentru leisure_venue ──
// Sursa adevarului, in ordine: (1) ?lang= din URL, (2) cookie scoped, (3) RO.
// Cookie-ul `ambilet_locale_leisure` e citit DOAR aici → restul site-ului
// ambilet.ro nu e afectat de selectia clientului pe leisure.
$availableLocalesLv = ['ro', 'en', 'hu'];
$publicLocale = 'ro';
if (!empty($_GET['lang']) && in_array($_GET['lang'], $availableLocalesLv, true)) {
    $publicLocale = $_GET['lang'];
    // Persistent pentru navigarile urmatoare in tab-ul curent (24h)
    @setcookie('ambilet_locale_leisure', $publicLocale, [
        'expires' => time() + 86400,
        'path' => '/',
        'samesite' => 'Lax',
    ]);
} elseif (!empty($_COOKIE['ambilet_locale_leisure']) && in_array($_COOKIE['ambilet_locale_leisure'], $availableLocalesLv, true)) {
    $publicLocale = $_COOKIE['ambilet_locale_leisure'];
}

/**
 * Helper local: aplica traduceri opt-in per-item pe array-urile accordeoanelor
 * (attractions, trails, faqs, flora, getting_there). Schema asteptata in venue_config:
 *
 *   "attractions": [{
 *       "title": "Lacul Sf. Ana",
 *       "description": "Singurul lac de crater din Romania...",
 *       "translations": {
 *           "hu": {"title": "Szent Anna-tó", "description": "..."},
 *           "en": {"title": "Saint Ann Lake",  "description": "..."}
 *       }
 *   }, ...]
 *
 * Pentru locale='ro' sau item fara `translations` → intoarce item-ul neatins
 * (zero regresie pe organizatorii fara traduceri).
 */
if (!function_exists('lv_localize_accordion_items')) {
    function lv_localize_accordion_items(array $items, string $locale, array $fields = ['title', 'subtitle', 'name', 'description', 'body', 'answer', 'question', 'short', 'long', 'q', 'a', 'note', 'latin']): array
    {
        if ($locale === 'ro' || empty($items)) return $items;
        return array_map(function ($item) use ($locale, $fields) {
            if (!is_array($item)) return $item;
            $tr = $item['translations'][$locale] ?? null;
            if (!is_array($tr)) return $item;
            foreach ($fields as $field) {
                if (isset($tr[$field]) && $tr[$field] !== '') {
                    $item[$field] = $tr[$field];
                }
            }
            return $item;
        }, $items);
    }
}

$venueConfig    = $ev['venue_config'] ?? [];
$amenities      = $venueConfig['amenities'] ?? [];
$heroImages     = $venueConfig['hero_images'] ?? [];
$heroImage      = !empty($heroImages) ? $heroImages[0] : ($ev['cover_image_url'] ?? $ev['hero_image_url'] ?? '');
if ($heroImage && !str_starts_with($heroImage, 'http')) {
    $heroImage = STORAGE_URL . '/' . $heroImage;
}
$posterImage    = $ev['poster_url'] ?? $ev['image_url'] ?? $ev['image'] ?? '';
if ($posterImage && !str_starts_with($posterImage, 'http')) {
    $posterImage = STORAGE_URL . '/' . $posterImage;
}
$contactPhone   = $venueConfig['contact_phone'] ?? '';
$directionsUrl  = $venueConfig['directions_url'] ?? '';
$rulesHtml      = $venueConfig['rules_html'] ?? '';
$seasons        = $venueConfig['seasons'] ?? [];
$maxAdvanceDays = $venueConfig['max_advance_days'] ?? 90;
$gallery        = $ev['gallery'] ?? [];
$venueName      = $ev['venue_name'] ?? '';
$venueCity      = $ev['venue_city'] ?? '';
$venueAddress   = $ev['venue_address'] ?? '';
$description    = $ev['description'] ?? '';
$shortDescription = $ev['short_description'] ?? '';
$eventTitle     = $ev['name'] ?? $ev['title'] ?? 'Rezervație';

// Title parts (hero — title + italic subtitle)
$titlePrimary   = $venueConfig['title_primary']   ?? $eventTitle;
$titleSecondary = $venueConfig['title_secondary'] ?? '';

// Hero badges (Sit Natura 2000, Altitudine, Județ)
$heroBadges = $venueConfig['hero_badges'] ?? [];

// Quick stats bar
$quickStats = $venueConfig['quick_stats'] ?? [];

// Tagline / kicker peste titlu
$heroKicker = $venueConfig['hero_kicker'] ?? '';

// Big stats highlights (30k, 950m, etc.)
$statsHighlights = $venueConfig['stats_highlights'] ?? [];

// Trasee turistice
$trails = $venueConfig['trails'] ?? [];

// Galerie & video
$galleryImages = $venueConfig['gallery'] ?? ($ev['gallery'] ?? []);
$videos = $venueConfig['videos'] ?? [];

// FAQs
$faqs = $venueConfig['faqs'] ?? [];

// Hoteluri din apropiere (markeri pe harta de trasee)
$nearbyHotels = $venueConfig['nearby_hotels'] ?? [];

// Floră & faună
$flora = $venueConfig['flora'] ?? [];

// Atracții — cele 2 cratere / locuri principale
$attractions = $venueConfig['attractions'] ?? [];

// Cum ajungi — 3 carduri (mașină / pe jos / cazare)
$gettingThere = $venueConfig['getting_there'] ?? [];

// Aplica traduceri pe accordeoane (Fix 4+5). Item-urile fara `translations` raman
// neatinse → zero regresie pentru organizatorii fara traduceri completate.
$trails       = lv_localize_accordion_items($trails,       $publicLocale);
$faqs         = lv_localize_accordion_items($faqs,         $publicLocale);
$flora        = lv_localize_accordion_items($flora,        $publicLocale);
$attractions  = lv_localize_accordion_items($attractions,  $publicLocale, ['title', 'name', 'description', 'bullets']);
$gettingThere = lv_localize_accordion_items($gettingThere, $publicLocale, ['title', 'name', 'description', 'note']);
$quickStats   = lv_localize_accordion_items($quickStats,   $publicLocale, ['label', 'value']);
$statsHighlights = lv_localize_accordion_items($statsHighlights, $publicLocale, ['label']);

// Hero traduceri (campuri simple pe venue_config.translations)
$heroTranslations = is_array($venueConfig['translations'] ?? null) ? $venueConfig['translations'] : [];
$pickTr = function (string $field) use ($heroTranslations, $publicLocale) {
    if ($publicLocale === 'ro') return null;
    $value = $heroTranslations[$field][$publicLocale] ?? null;
    return ($value !== null && $value !== '') ? $value : null;
};
if ($publicLocale !== 'ro') {
    if ($v = $pickTr('title_primary'))   $titlePrimary = $v;
    if ($v = $pickTr('title_secondary')) $titleSecondary = $v;
    if ($v = $pickTr('hero_kicker'))     $heroKicker = $v;
    // hero_badges: array de string-uri, varianta locale daca exista
    if (isset($heroTranslations['hero_badges'][$publicLocale]) && is_array($heroTranslations['hero_badges'][$publicLocale]) && !empty($heroTranslations['hero_badges'][$publicLocale])) {
        $heroBadges = $heroTranslations['hero_badges'][$publicLocale];
    }
}

// Map config
$mapConfig = $venueConfig['map_config'] ?? [];
$mapCenter = $mapConfig['center'] ?? null; // [lat, lng]
$mapZoom = $mapConfig['zoom'] ?? 12;
$mapPois = $mapConfig['pois'] ?? [];

// Safety warning — declarat înainte de traducere (bug fix: înainte era folosit ca null)
$safetyWarning = $venueConfig['safety_warning'] ?? null;

// Aplica traduceri safety_warning (titlu + body) din venue_config.safety_warning.translations[locale]
if ($safetyWarning && is_array($safetyWarning) && $publicLocale !== 'ro') {
    $swTr = $safetyWarning['translations'][$publicLocale] ?? null;
    if (is_array($swTr)) {
        if (!empty($swTr['title'])) $safetyWarning['title'] = $swTr['title'];
        if (!empty($swTr['body']))  $safetyWarning['body']  = $swTr['body'];
    }
}

// Build issuers map (organizer e SIBLING al cheii event in raspuns)
$organizer = $eventPreload['data']['organizer']
    ?? $ev['organizer']
    ?? $ev['marketplace_organizer']
    ?? [];
$issuers = [
    'primary' => [
        'name' => $organizer['company_name'] ?? $organizer['name'] ?? ($ev['venue_name'] ?? ''),
        'tax_id' => $organizer['company_tax_id'] ?? null,
    ],
];
if (!empty($organizer['has_secondary_issuer'])) {
    $issuers['secondary'] = [
        'name' => $organizer['secondary_company_name'] ?? '',
        'tax_id' => $organizer['secondary_company_tax_id'] ?? null,
    ];
}

$bodyClass = 'bg-fog text-ink font-sans antialiased';
$cssBundle = 'single'; // fallback — folosim tailwind inline + custom CSS

require_once __DIR__ . '/includes/head.php';

// Marker that head.php already emitted </head><body>
?>

<!-- Tailwind CDN cu config inline (pentru paleta forest/lake/sand) -->
<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    'sans': ['Plus Jakarta Sans', 'sans-serif'],
                    'display': ['Fraunces', 'serif'],
                },
                colors: {
                    'forest': { 50:'#F0FAF4',100:'#DCF2E3',200:'#BBE5C9',300:'#8DCFA5',400:'#5BB17F',500:'#3D9663',600:'#2D7A4F',700:'#256142',800:'#1F4E37',900:'#0F2C20' },
                    'lake':   { 50:'#ECFEFF',100:'#CFFAFE',200:'#A5F3FC',300:'#67E8F9',400:'#22D3EE',500:'#06B6D4',600:'#0891B2',700:'#0E7490',800:'#155E75',900:'#164E63' },
                    'sand':   { 50:'#FAF7F2',100:'#F1EBE0',200:'#E5D8C0',300:'#D3BD93',400:'#B89968',500:'#9A7B4D' },
                    'ink': '#0F1E1A',
                    'fog': '#F5F7F4',
                }
            }
        }
    }
</script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600;9..144,700;9..144,800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<style>
    body { -webkit-font-smoothing: antialiased; }
    .lv-btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 9999px; font-weight: 600; font-size: 0.9375rem; transition: all 0.2s; cursor: pointer; border: none; }
    .lv-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .lv-btn-primary { background: #1F4E37; color: white; }
    .lv-btn-primary:hover:not(:disabled) { background: #0F2C20; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(31, 78, 55, 0.25); }
    .lv-btn-secondary { background: white; color: #1F4E37; border: 1.5px solid #BBE5C9; }
    .lv-btn-secondary:hover:not(:disabled) { background: #F0FAF4; border-color: #5BB17F; }
    .lv-qty-btn { width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: white; border: 1.5px solid #DCF2E3; color: #1F4E37; font-weight: 700; font-size: 1.125rem; cursor: pointer; transition: all 0.15s; }
    .lv-qty-btn:hover:not(:disabled) { background: #F0FAF4; border-color: #5BB17F; }
    .lv-qty-btn:disabled { opacity: 0.3; cursor: not-allowed; }
    .lv-hero-bg {
        background:
            radial-gradient(ellipse at 50% 100%, rgba(11, 130, 87, 0.3) 0%, transparent 60%),
            radial-gradient(ellipse at 80% 20%, rgba(34, 211, 238, 0.15) 0%, transparent 50%),
            linear-gradient(180deg, #0F2C20 0%, #1F4E37 40%, #256142 70%, #2D7A4F 100%);
    }
    .lv-topo-pattern {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200' viewBox='0 0 200 200'%3E%3Cg fill='none' stroke='%23BBE5C9' stroke-width='0.5' opacity='0.4'%3E%3Cpath d='M0 100 Q 50 80, 100 100 T 200 100'/%3E%3Cpath d='M0 120 Q 50 100, 100 120 T 200 120'/%3E%3Cpath d='M0 140 Q 50 120, 100 140 T 200 140'/%3E%3Cpath d='M0 80 Q 50 60, 100 80 T 200 80'/%3E%3Cpath d='M0 60 Q 50 40, 100 60 T 200 60'/%3E%3C/g%3E%3C/svg%3E");
    }
    #trailMap, #locationMap { z-index: 1; }
    .lv-scroll-mt { scroll-margin-top: 80px; }
    .lv-badge { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.3rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
</style>

<!-- Inject server-side data ca să fie disponibilă în Alpine fără API roundtrip -->
<script>
    window.__LEISURE_VENUE__ = <?= json_encode([
        'slug' => $eventSlug,
        'event' => [
            'id' => $ev['id'] ?? null,
            'name' => $ev['name'] ?? '',
            'slug' => $eventSlug,
            'image' => $posterImage,
            'hero_image' => $heroImage,
            'starts_at' => $ev['starts_at'] ?? null,
            'ends_at' => $ev['ends_at'] ?? null,
            'range_start_date' => $ev['range_start_date'] ?? null,
            'range_end_date' => $ev['range_end_date'] ?? null,
        ],
        'venue_config' => $venueConfig,
        'max_advance_days' => $maxAdvanceDays,
        'issuers' => $issuers,
        'trails' => $trails,
        'faqs' => $faqs,
        'flora' => $flora,
        'attractions' => $attractions,
        'getting_there' => $gettingThere,
        'map_config' => $mapConfig,
        'stats_highlights' => $statsHighlights,
        'hero_badges' => $heroBadges,
        'quick_stats' => $quickStats,
        'safety_warning' => $safetyWarning,
        'gallery' => array_map(function ($img) {
            if (str_starts_with($img, 'http')) return $img;
            return STORAGE_URL . '/' . ltrim($img, '/');
        }, $galleryImages),
        'videos' => $videos,
        'nearby_hotels' => $nearbyHotels,
        'is_preview' => !empty($_GET['preview']),
        // B4 — locale efectiv pentru aceasta pagina (RO/HU/EN)
        'locale' => $publicLocale,
        // B5 — dicționar texte statice. RO = textele actuale 1:1 (verificate la
        // randul cu HTML-ul); HU/EN traduse de mine pe baza textelor existente.
        'i18n' => [
            'ro' => [
                'step1' => 'Pasul 1 din 2',
                'step1_title' => 'Alege data și biletele',
                'step1_subtitle' => 'Selectează ziua vizitei, apoi tipul de acces.',
                'step2' => 'Pasul 2 din 2 · Opțional',
                'step2_subtitle' => 'Adaugă plimbare cu barca, ghid privat sau alte experiențe.',
                'legend_available' => 'Disponibil',
                'legend_almost_full' => 'Aproape full',
                'legend_sold_out' => 'Sold out',
                'legend_closed' => 'Închis',
                'pick_a_date' => 'Selectează o dată din calendar',
                'choose_option' => 'Alege opțiunea',
                'choose_slot' => 'Alege ora cursei',
                'choose_date' => 'Alege data',
                'requires_access' => 'Adaugă mai întâi un bilet de acces',
                'closed' => 'Închis',
                'cart_total' => 'Total',
                'cart_for' => 'Pentru',
                'cart_remove_title' => 'Elimină din coș',
                'savings' => 'Economisești',
                'individual_value' => 'Valoare individuală',
                'continue' => 'Continuă spre coș',
                'timer_message' => 'Biletele sunt rezervate pentru tine încă',
                'timer_minutes' => 'minute',
                'timer_expired' => 'Timpul a expirat. Coșul a fost golit.',
                'days_short' => ['Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sâm', 'Dum'],
                'months' => ['Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'],
                'lang_label' => 'Limbă',
                // Nav
                'nav_tickets' => 'Bilete', 'nav_trails' => 'Trasee', 'nav_about' => 'Despre', 'nav_directions' => 'Cum ajungi',
                // Hero / labels
                'see_trails' => 'Vezi traseele turistice',
                'label_program' => 'Program', 'label_contact' => 'Contact', 'label_location' => 'Locație',
                'selected_date' => 'Data selectată',
                // Booking section
                'package_includes' => 'Include în pachet',
                'rental_start_time' => 'Ora de start a închirierii',
                // Upsell
                'upsell_title' => 'Fă vizita o experiență completă',
                'upsell_subtitle' => 'Alege ce te tentează și economisește timp pe loc.',
                // Step 2 services
                'extra_services' => 'Servicii suplimentare',
                'packages_promo_title' => 'Pachete promoționale',
                'back_to_categories' => '← Înapoi la categorii',
                'other_categories' => 'Alte categorii de bilete',
                'ticket_singular' => 'opțiune',
                'ticket_plural' => 'opțiuni',
                'from_price' => 'de la ',
                'price_breakdown' => 'Detalii preț',
                'price_ticket' => 'Preț bilet',
                'ticketing_fee' => 'Taxă ticketing',
                'requires_access_short' => 'Necesită bilet acces',
                'includes_label' => 'Include',
                'rental_hour' => 'Ora închiriere',
                // Sections
                'marked_trails' => 'Drumeții marcate',
                'tourist_trails' => 'Trasee turistice',
                'know_the_place' => 'Cunoaște locul',
                'program_h2' => 'Program',
                'when_we_open' => 'Când suntem deschiși',
                'location_h2' => 'Locație',
                'how_to_reach' => 'Cum ajungi la noi',
                'gallery' => 'Galerie',
                'video' => 'Video',
                'place_in_motion' => 'Locul în mișcare',
                'good_to_know' => 'Bine de știut',
                // Cart panel
                'order_summary' => 'Sumar comandă',
                'subtotal_tickets' => 'Subtotal bilete',
                'total_commission' => 'Total comision',
                'commission_plus' => '+ Comision ticketing',
                'total' => 'Total',
                'choose_date_first' => 'Alege mai întâi data',
                'missing_access' => 'Lipsesc bilete acces',
                'continue_to_payment' => 'Continuă spre plată →',
                // Booking section labels
                'date_label' => '📅 Data vizitei',
                'access_types_label' => '🎫 Tipuri de acces',
                'tickets_selected_one' => 'bilet selectat',
                'tickets_selected_many' => 'bilete selectate',
                'savings_short' => 'Economisești',
                'requires_access_some' => 'Unele necesită bilet de acces',
                // Upsell + recommended
                'recommended_for_you' => '✨ Recomandate pentru tine',
                'see_all' => 'Vezi toate',
                // FAQ section
                'faqs_title' => 'Întrebări frecvente',
                // Trail details
                'trail_length' => 'Lungime',
                'trail_duration' => 'Durată',
                'trail_elevation' => 'Diferență',
                'trail_starts_from' => 'Pornește din:',
                'closed_today' => 'Închis astăzi',
                'check_schedule' => 'Verificați programul',
                'book_access' => 'Cumpără bilete →',
                'book_access_short' => 'Cumpără bilete →',
                'open_in_maps' => 'Deschide în Google Maps',
                'send_whatsapp' => 'Trimite pe WhatsApp',
            ],
            'hu' => [
                'step1' => '1./2. lépés',
                'step1_title' => 'Válassz dátumot és jegyet',
                'step1_subtitle' => 'Válaszd ki a látogatás napját, majd a belépő típusát.',
                'step2' => '2./2. lépés · Opcionális',
                'step2_subtitle' => 'Adj hozzá csónakázást, magánvezetést vagy más élményt.',
                'legend_available' => 'Elérhető',
                'legend_almost_full' => 'Majdnem tele',
                'legend_sold_out' => 'Elfogyott',
                'legend_closed' => 'Zárva',
                'pick_a_date' => 'Válassz egy dátumot a naptárból',
                'choose_option' => 'Válassz opciót',
                'choose_slot' => 'Válassz indulási időpontot',
                'choose_date' => 'Válassz dátumot',
                'requires_access' => 'Először adj hozzá egy belépőjegyet',
                'closed' => 'Zárva',
                'cart_total' => 'Összesen',
                'cart_for' => 'Erre a napra',
                'cart_remove_title' => 'Eltávolítás a kosárból',
                'savings' => 'Megtakarítás',
                'individual_value' => 'Egyedi ár',
                'continue' => 'Tovább a kosárhoz',
                'timer_message' => 'A jegyek le vannak foglalva számodra még',
                'timer_minutes' => 'percig',
                'timer_expired' => 'Az idő lejárt. A kosár kiürült.',
                'days_short' => ['Hét', 'Ked', 'Sze', 'Csü', 'Pén', 'Szo', 'Vas'],
                'months' => ['Január', 'Február', 'Március', 'Április', 'Május', 'Június', 'Július', 'Augusztus', 'Szeptember', 'Október', 'November', 'December'],
                'lang_label' => 'Nyelv',
                'nav_tickets' => 'Jegyek', 'nav_trails' => 'Túraútvonalak', 'nav_about' => 'Rólunk', 'nav_directions' => 'Megközelítés',
                'see_trails' => 'Túraútvonalak megtekintése',
                'label_program' => 'Nyitvatartás', 'label_contact' => 'Kapcsolat', 'label_location' => 'Helyszín',
                'selected_date' => 'Kiválasztott dátum',
                'package_includes' => 'A csomag tartalmazza',
                'rental_start_time' => 'Bérlés kezdő időpontja',
                'upsell_title' => 'Tedd látogatásod teljes élménnyé',
                'upsell_subtitle' => 'Válaszd ki, mi tetszik, és spórolj időt a helyszínen.',
                'extra_services' => 'Kiegészítő szolgáltatások',
                'packages_promo_title' => 'Promóciós csomagok',
                'back_to_categories' => '← Vissza a kategóriákhoz',
                'other_categories' => 'Más jegykategóriák',
                'ticket_singular' => 'opció',
                'ticket_plural' => 'opció',
                'from_price' => '-tól ',
                'price_breakdown' => 'Ár részletei',
                'price_ticket' => 'Jegy ára',
                'ticketing_fee' => 'Jegykezelési díj',
                'requires_access_short' => 'Belépőjegy szükséges',
                'includes_label' => 'Tartalmaz',
                'rental_hour' => 'Bérlés időpontja',
                'marked_trails' => 'Kijelölt túraútvonalak',
                'tourist_trails' => 'Túraútvonalak',
                'know_the_place' => 'Ismerd meg a helyet',
                'program_h2' => 'Nyitvatartás',
                'when_we_open' => 'Mikor vagyunk nyitva',
                'location_h2' => 'Helyszín',
                'how_to_reach' => 'Hogyan érhetsz el',
                'gallery' => 'Galéria',
                'video' => 'Videó',
                'place_in_motion' => 'A hely mozgásban',
                'good_to_know' => 'Jó tudni',
                'order_summary' => 'Rendelés összesítő',
                'subtotal_tickets' => 'Jegyek részösszege',
                'total_commission' => 'Összes jutalék',
                'commission_plus' => '+ Jegyértékesítési jutalék',
                'total' => 'Összesen',
                'choose_date_first' => 'Előbb válassz dátumot',
                'missing_access' => 'Hiányzó belépőjegyek',
                'continue_to_payment' => 'Tovább a fizetéshez →',
                'date_label' => '📅 Látogatás dátuma',
                'access_types_label' => '🎫 Belépőtípusok',
                'tickets_selected_one' => 'jegy kiválasztva',
                'tickets_selected_many' => 'jegy kiválasztva',
                'savings_short' => 'Megtakarítás',
                'requires_access_some' => 'Néhányhoz belépőjegy szükséges',
                'recommended_for_you' => '✨ Ajánlott neked',
                'see_all' => 'Mind megtekintése',
                'faqs_title' => 'Gyakori kérdések',
                'trail_length' => 'Hossz',
                'trail_duration' => 'Időtartam',
                'trail_elevation' => 'Szintkülönbség',
                'trail_starts_from' => 'Indul innen:',
                'closed_today' => 'Ma zárva',
                'check_schedule' => 'Ellenőrizze a nyitvatartást',
                'book_access' => 'Jegyvásárlás →',
                'book_access_short' => 'Jegyvásárlás →',
                'open_in_maps' => 'Megnyitás Google Térképen',
                'send_whatsapp' => 'Üzenet WhatsAppon',
            ],
            'en' => [
                'step1' => 'Step 1 of 2',
                'step1_title' => 'Choose date and tickets',
                'step1_subtitle' => 'Select your visit date, then the access type.',
                'step2' => 'Step 2 of 2 · Optional',
                'step2_subtitle' => 'Add a boat ride, private guide or other experiences.',
                'legend_available' => 'Available',
                'legend_almost_full' => 'Almost full',
                'legend_sold_out' => 'Sold out',
                'legend_closed' => 'Closed',
                'pick_a_date' => 'Pick a date from the calendar',
                'choose_option' => 'Choose option',
                'choose_slot' => 'Choose departure time',
                'choose_date' => 'Pick a date',
                'requires_access' => 'Add an access ticket first',
                'closed' => 'Closed',
                'cart_total' => 'Total',
                'cart_for' => 'For',
                'cart_remove_title' => 'Remove from cart',
                'savings' => 'You save',
                'individual_value' => 'Individual value',
                'continue' => 'Continue to cart',
                'timer_message' => 'Your tickets are reserved for another',
                'timer_minutes' => 'minutes',
                'timer_expired' => 'Time expired. Cart has been emptied.',
                'days_short' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'months' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                'lang_label' => 'Language',
                'nav_tickets' => 'Tickets', 'nav_trails' => 'Trails', 'nav_about' => 'About', 'nav_directions' => 'Directions',
                'see_trails' => 'See the hiking trails',
                'label_program' => 'Schedule', 'label_contact' => 'Contact', 'label_location' => 'Location',
                'selected_date' => 'Selected date',
                'package_includes' => 'Package includes',
                'rental_start_time' => 'Rental start time',
                'upsell_title' => 'Make your visit a complete experience',
                'upsell_subtitle' => 'Pick what tempts you and save time on-site.',
                'extra_services' => 'Extra services',
                'packages_promo_title' => 'Promo packages',
                'back_to_categories' => '← Back to categories',
                'other_categories' => 'Other ticket categories',
                'ticket_singular' => 'option',
                'ticket_plural' => 'options',
                'from_price' => 'from ',
                'price_breakdown' => 'Price breakdown',
                'price_ticket' => 'Ticket price',
                'ticketing_fee' => 'Ticketing fee',
                'requires_access_short' => 'Access ticket required',
                'includes_label' => 'Includes',
                'rental_hour' => 'Rental time',
                'marked_trails' => 'Marked hikes',
                'tourist_trails' => 'Hiking trails',
                'know_the_place' => 'Know the place',
                'program_h2' => 'Schedule',
                'when_we_open' => 'When we are open',
                'location_h2' => 'Location',
                'how_to_reach' => 'How to reach us',
                'gallery' => 'Gallery',
                'video' => 'Video',
                'place_in_motion' => 'The place in motion',
                'good_to_know' => 'Good to know',
                'order_summary' => 'Order summary',
                'subtotal_tickets' => 'Tickets subtotal',
                'total_commission' => 'Total commission',
                'commission_plus' => '+ Ticketing commission',
                'total' => 'Total',
                'choose_date_first' => 'Pick a date first',
                'missing_access' => 'Missing access tickets',
                'continue_to_payment' => 'Continue to payment →',
                'date_label' => '📅 Visit date',
                'access_types_label' => '🎫 Access types',
                'tickets_selected_one' => 'ticket selected',
                'tickets_selected_many' => 'tickets selected',
                'savings_short' => 'You save',
                'requires_access_some' => 'Some require an access ticket',
                'recommended_for_you' => '✨ Recommended for you',
                'see_all' => 'See all',
                'faqs_title' => 'Frequently asked questions',
                'trail_length' => 'Length',
                'trail_duration' => 'Duration',
                'trail_elevation' => 'Elevation',
                'trail_starts_from' => 'Starts from:',
                'closed_today' => 'Closed today',
                'check_schedule' => 'Check schedule',
                'book_access' => 'Buy tickets →',
                'book_access_short' => 'Buy tickets →',
                'open_in_maps' => 'Open in Google Maps',
                'send_whatsapp' => 'Send via WhatsApp',
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<div x-data="reservationPage()" x-cloak>

<!-- ============ TOP NAV (overlay over hero) ============ -->
<nav class="absolute top-0 left-0 right-0 z-30 px-6 lg:px-12 py-5">
    <div class="max-w-7xl mx-auto flex items-center justify-between">
        <a href="/" class="flex items-center gap-2 text-white">
            <div class="w-9 h-9 bg-white/15 backdrop-blur rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
            </div>
            <span class="font-extrabold text-lg"><?= SITE_NAME ?></span>
        </a>
        <div class="hidden lg:flex items-center gap-1 bg-white/10 backdrop-blur rounded-full px-2 py-1.5">
            <a href="#bilete" class="px-4 py-1.5 text-white/80 hover:text-white text-sm font-medium transition-colors rounded-full hover:bg-white/10" data-i18n="nav_tickets">Bilete</a>
            <a href="#trasee" class="px-4 py-1.5 text-white/80 hover:text-white text-sm font-medium transition-colors rounded-full hover:bg-white/10" data-i18n="nav_trails">Trasee</a>
            <a href="#despre" class="px-4 py-1.5 text-white/80 hover:text-white text-sm font-medium transition-colors rounded-full hover:bg-white/10" data-i18n="nav_about">Despre</a>
            <a href="#cum-ajungi" class="px-4 py-1.5 text-white/80 hover:text-white text-sm font-medium transition-colors rounded-full hover:bg-white/10" data-i18n="nav_directions">Cum ajungi</a>
        </div>
        <div class="flex items-center gap-2">
            <!-- Selector limbă MOBILE (dropdown) — lângă butonul "Cumpără bilete". -->
            <?php
            $_lvSelectorQuery = $_GET;
            foreach (['slug', '_route', '_path'] as $_drop) { unset($_lvSelectorQuery[$_drop]); }
            $_langLabels = ['ro' => 'RO', 'hu' => 'HU', 'en' => 'EN'];
            $_currentLabel = $_langLabels[$publicLocale] ?? 'RO';
            ?>
            <div class="lg:hidden relative" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1 bg-white/15 backdrop-blur text-white px-3 py-2 rounded-full text-xs font-bold hover:bg-white/25 transition-colors">
                    🌐 <span><?= $_currentLabel ?></span>
                    <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-transition class="absolute top-full right-0 mt-1 bg-white rounded-lg shadow-xl border border-forest-100 py-1 min-w-[80px] z-50">
                    <?php foreach ($_langLabels as $code => $label): ?>
                        <a href="?<?= http_build_query(array_merge($_lvSelectorQuery, ['lang' => $code])) ?>"
                           class="block px-3 py-1.5 text-xs font-bold text-forest-900 hover:bg-forest-50 <?= $publicLocale === $code ? 'bg-forest-700 text-white hover:bg-forest-700' : '' ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <a href="#bilete" class="bg-white/15 backdrop-blur text-white px-4 py-2 rounded-full text-sm font-semibold hover:bg-white/25 transition-colors" data-i18n="book_access_short">Cumpără bilete →</a>
        </div>
    </div>
</nav>

<!-- ============ B4: SELECTOR LIMBĂ DESKTOP (fixed top-right, hidden mobile) ============ -->
<div class="hidden lg:flex fixed top-4 right-4 z-50 items-center gap-1 bg-white/95 backdrop-blur rounded-full px-2 py-1 shadow-lg border border-forest-200" style="font-size:11px;">
    <span class="text-forest-600 text-[10px] uppercase tracking-wider font-bold px-1.5" data-i18n="lang_label">Limbă</span>
    <?php foreach ($_langLabels as $code => $label): ?>
        <a href="?<?= http_build_query(array_merge($_lvSelectorQuery, ['lang' => $code])) ?>"
           class="px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wider transition-colors <?= $publicLocale === $code ? 'bg-forest-700 text-white' : 'text-forest-700 hover:bg-forest-100' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<!-- ============ HERO ============ -->
<section class="lv-hero-bg relative min-h-[100vh] flex flex-col justify-end overflow-hidden pb-24 lg:pb-32">
    <?php if ($heroImage): ?>
    <img src="<?= htmlspecialchars($heroImage) ?>" alt="<?= htmlspecialchars($eventTitle) ?>"
         class="absolute inset-0 w-full h-full object-cover opacity-30" loading="eager">
    <?php endif; ?>
    <svg class="absolute bottom-0 left-0 right-0 w-full h-64 lg:h-96 text-forest-900/60" viewBox="0 0 1440 320" preserveAspectRatio="none"><path fill="currentColor" d="M0,224L48,213.3C96,203,192,181,288,165.3C384,149,480,139,576,154.7C672,171,768,213,864,213.3C960,213,1056,171,1152,165.3C1248,160,1344,192,1392,208L1440,224L1440,320L0,320Z"/></svg>
    <svg class="absolute bottom-0 left-0 right-0 w-full h-48 lg:h-64 text-forest-900/80" viewBox="0 0 1440 320" preserveAspectRatio="none"><path fill="currentColor" d="M0,288L60,272C120,256,240,224,360,224C480,224,600,256,720,261.3C840,267,960,245,1080,234.7C1200,224,1320,224,1380,224L1440,224L1440,320L0,320Z"/></svg>

    <div class="relative z-10 px-6 lg:px-12 max-w-7xl mx-auto w-full">
        <div class="max-w-3xl">
            <?php if (!empty($heroBadges)): ?>
            <div class="flex flex-wrap items-center gap-2 mb-6">
                <?php foreach ($heroBadges as $b): ?>
                <span class="lv-badge bg-white/10 backdrop-blur text-white border border-white/15">
                    <?= htmlspecialchars($b) ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($heroKicker): ?>
            <p class="text-lake-200 text-xs uppercase tracking-[0.3em] font-bold mb-4"><?= htmlspecialchars($heroKicker) ?></p>
            <?php endif; ?>

            <h1 class="font-display text-5xl sm:text-6xl lg:text-8xl font-black text-white leading-[0.95] mb-6">
                <?= htmlspecialchars($titlePrimary) ?>
                <?php if ($titleSecondary): ?>
                <br><span class="text-lake-300 italic font-medium"><?= htmlspecialchars($titleSecondary) ?></span>
                <?php endif; ?>
            </h1>

            <?php if ($shortDescription): ?>
            <p class="text-lg lg:text-xl text-white/80 max-w-2xl font-light leading-relaxed"><?= htmlspecialchars($shortDescription) ?></p>
            <?php endif; ?>

            <div class="flex flex-wrap items-center gap-4 mt-8">
                <a href="#bilete" class="lv-btn bg-white text-forest-800 hover:bg-lake-50" data-i18n="book_access">Cumpără bilete →</a>
                <?php if (!empty($trails)): ?>
                <a href="#trasee" class="lv-btn bg-transparent text-white border border-white/30 hover:bg-white/10" data-i18n="see_trails">Vezi traseele turistice</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============ RESERVATION TIMER BAR ============
     Sticky top, vizibil DOAR cand cart-ul are item-uri + cart_end_time e setat
     in localStorage de AmbiletCart.startReservationTimer(). Re-foloseste id=countdown
     ca scripts-ul global de timer (din cart.js / cart-page.js) sa sincronizeze
     daca user-ul are deja tab cu /cos deschis. -->
<!-- ============ QUICK STATS BAR ============
     Sticky doar cand timer-bar NU e activ (are clasa lg:sticky by default,
     scoasa de JS in setupCartTimer cand timer-ul apare — ca sa nu ai 2
     bare sticky suprapuse). -->
<section id="quick-stats-bar" class="bg-white border-y border-forest-100 lg:sticky lg:top-0 lg:z-20 backdrop-blur">
    <div class="max-w-7xl mx-auto px-6 lg:px-12">
        <div class="grid grid-cols-2 lg:grid-cols-4 divide-x divide-forest-100">
            <div class="py-4 px-4 flex items-center gap-3">
                <div class="w-9 h-9 bg-forest-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-forest-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-forest-700/60 font-medium" data-i18n="label_program">Program</p>
                    <p class="text-sm font-bold text-forest-900 truncate" x-text="currentScheduleLabel"></p>
                </div>
            </div>
            <?php if ($contactPhone): ?>
            <a href="tel:<?= htmlspecialchars($contactPhone) ?>" class="py-4 px-4 flex items-center gap-3 hover:bg-forest-50 transition-colors">
                <div class="w-9 h-9 bg-lake-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-lake-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-forest-700/60 font-medium" data-i18n="label_contact">Contact</p>
                    <p class="text-sm font-bold text-forest-900 truncate"><?= htmlspecialchars($contactPhone) ?></p>
                </div>
            </a>
            <?php endif; ?>
            <?php
            // Locație afișată: prefera venue_config.location_label (custom suprascriere)
            // → venue.city → venue.address. Permite organizatorului să afișeze
            // "Lăzărești" în loc de "Bixad" (cazul Sf. Ana).
            // Suport traducere prin venue_config.translations.location_label[locale].
            $locationLabel = $venueConfig['location_label'] ?? '';
            if (!empty($heroTranslations['location_label'][$publicLocale])) {
                $locationLabel = $heroTranslations['location_label'][$publicLocale];
            }
            $locationDisplay = $locationLabel ?: ($venueCity ?: $venueAddress);
            ?>
            <?php if ($locationDisplay): ?>
            <div class="py-4 px-4 flex items-center gap-3">
                <div class="w-9 h-9 bg-sand-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-sand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-forest-700/60 font-medium" data-i18n="label_location">Locație</p>
                    <p class="text-sm font-bold text-forest-900 truncate"><?= htmlspecialchars($locationDisplay) ?></p>
                </div>
            </div>
            <?php endif; ?>
            <?php foreach (array_slice($quickStats, 0, 1) as $qs): ?>
            <div class="py-4 px-4 flex items-center gap-3">
                <div class="w-9 h-9 bg-amber-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <span class="text-lg"><?= htmlspecialchars($qs['icon'] ?? '⚠️') ?></span>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-forest-700/60 font-medium"><?= htmlspecialchars($qs['label'] ?? 'Atenție') ?></p>
                    <p class="text-sm font-bold text-forest-900 truncate"><?= htmlspecialchars($qs['value'] ?? '') ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============ RESERVATION TIMER BAR ============
     Plasat SUB quick-stats. Cand e vizibil, JS scoate 'lg:sticky' de pe
     quick-stats, iar timer-bar devine sticky-top. Fundal solid (#f8f1e3)
     ca sa nu se vada continut prin el. -->
<div id="timer-bar" class="hidden sticky top-0 z-30 border-b border-warning/20" style="display:none; background:#f8f1e3;">
    <div class="px-4 py-2.5 mx-auto max-w-7xl">
        <div class="flex items-center justify-center gap-2 text-sm">
            <svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-forest-900" x-text="t('timer_message') || 'Biletele sunt rezervate pentru tine încă'"></span>
            <span id="countdown" class="font-bold text-warning tabular-nums">15:00</span>
            <span class="text-forest-900" x-text="t('timer_minutes') || 'minute'"></span>
        </div>
    </div>
</div>

<!-- ============ CALENDAR + BILETE ============ -->
<section id="bilete" class="lv-scroll-mt py-16 lg:py-24 px-6 lg:px-12 bg-fog">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-12">
            <p class="text-forest-600 text-xs uppercase tracking-[0.3em] font-bold mb-3" data-i18n="step1">Pasul 1 din 2</p>
            <h2 class="font-display text-4xl lg:text-5xl font-bold text-ink mb-3" data-i18n="step1_title">Alege data și biletele</h2>
            <p class="text-forest-700/70 max-w-xl mx-auto" data-i18n="step1_subtitle">Selectează ziua vizitei, apoi tipul de acces.</p>
        </div>

        <div class="grid lg:grid-cols-12 gap-6">
            <!-- LEFT: Calendar -->
            <div class="lg:col-span-5">
                <div class="bg-white rounded-3xl border border-forest-100 shadow-sm p-6 lg:p-7 lg:sticky lg:top-24">
                    <p class="text-xs uppercase tracking-wider text-forest-700/60 font-bold mb-3" data-i18n="date_label">📅 Data vizitei</p>
                    <div class="flex items-center justify-between mb-5">
                        <button @click="prevMonth()" class="lv-qty-btn" aria-label="Luna anterioară">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <h3 class="font-display text-xl font-bold text-ink" x-text="monthLabel"></h3>
                        <button @click="nextMonth()" class="lv-qty-btn" aria-label="Luna următoare">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 mb-2">
                        <template x-for="d in ['Lu','Ma','Mi','Jo','Vi','Sâ','Du']">
                            <div class="text-center text-xs font-bold text-forest-700/50 py-2" x-text="d"></div>
                        </template>
                    </div>
                    <div class="grid grid-cols-7 gap-1" x-show="!loadingMonth">
                        <template x-for="day in calendarDays" :key="day.key">
                            <button :disabled="day.empty || day.disabled"
                                    @click="!day.disabled && selectDate(day.key)"
                                    :class="[
                                        day.empty ? 'invisible' : '',
                                        day.disabled ? 'text-forest-700/20 cursor-not-allowed' : '',
                                        !day.disabled && selectedDate === day.key ? 'bg-forest-700 text-white shadow-md' : '',
                                        !day.disabled && selectedDate !== day.key ? 'hover:bg-forest-50 text-ink' : '',
                                    ]"
                                    class="aspect-square rounded-xl text-sm font-semibold relative transition-all">
                                <span x-text="day.day"></span>
                                <span x-show="!day.disabled && !day.empty"
                                      class="absolute bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full"
                                      :class="{
                                          'bg-forest-400': day.status === 'available' && selectedDate !== day.key,
                                          'bg-amber-400': day.status === 'limited' && selectedDate !== day.key,
                                          'bg-red-400': day.status === 'sold_out' && selectedDate !== day.key,
                                          'bg-white': selectedDate === day.key,
                                      }"></span>
                            </button>
                        </template>
                    </div>
                    <div x-show="loadingMonth" class="grid grid-cols-7 gap-1">
                        <template x-for="i in 35">
                            <div class="aspect-square rounded-xl bg-forest-50 animate-pulse"></div>
                        </template>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 mt-5 pt-5 border-t border-forest-100 text-xs text-forest-700/70">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-forest-400"></span><span data-i18n="legend_available">Disponibil</span></span>
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-400"></span><span data-i18n="legend_almost_full">Aproape full</span></span>
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-red-400"></span><span data-i18n="legend_sold_out">Sold out</span></span>
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-forest-700/20"></span><span data-i18n="legend_closed">Închis</span></span>
                    </div>

                    <div x-show="selectedDate" class="mt-5 p-3 bg-forest-50 rounded-xl flex items-center gap-3">
                        <svg class="w-5 h-5 text-forest-700 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <div class="text-sm min-w-0">
                            <p class="text-xs text-forest-700/60 font-medium" data-i18n="selected_date">Data selectată</p>
                            <p class="font-bold text-forest-900 truncate" x-text="formatDate(selectedDate)"></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Tickets -->
            <div class="lg:col-span-7">
                <p class="text-xs uppercase tracking-wider text-forest-700/60 font-bold mb-3" data-i18n="access_types_label">🎫 Tipuri de acces</p>

                <div x-show="!selectedDate" class="bg-white rounded-2xl p-8 text-center border-2 border-dashed border-forest-200">
                    <svg class="w-12 h-12 mx-auto text-forest-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="text-forest-700/70 font-medium" data-i18n="pick_a_date">Selectează o dată din calendar</p>
                </div>

                <div x-show="selectedDate && loadingTickets" class="space-y-3">
                    <template x-for="i in 3">
                        <div class="bg-white rounded-2xl p-5 h-32 animate-pulse"></div>
                    </template>
                </div>

                <div x-show="selectedDate && !loadingTickets && !hasDisplayableTickets" class="bg-white rounded-2xl p-8 text-center">
                    <p class="text-forest-700/70">Nu sunt bilete disponibile pentru această dată.</p>
                </div>

                <!-- ===== MODE A: Picker categorii (carduri mari cu imagine) ===== -->
                <!-- Se afiseaza cand:
                     - exista categorii configurate de organizator (>=2 sau pachete + categorii)
                     - utilizatorul nu a ales inca o categorie -->
                <div x-show="selectedDate && !loadingTickets && hasDisplayableTickets && showCategoryPicker"
                     class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <template x-for="cat in publicCategoryGroups" :key="cat.id">
                        <button type="button" @click="selectedCategoryId = cat.id; $nextTick(() => { document.getElementById('bilete')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); })"
                                class="group relative bg-white rounded-2xl overflow-hidden border-2 border-transparent hover:border-forest-500 hover:shadow-lg transition-all text-left">
                            <div class="aspect-[4/3] relative overflow-hidden flex items-center justify-center bg-fog"
                                 :style="!cat.image_url ? `background: linear-gradient(135deg, #DCF2E3 0%, #BFE5CD 100%)` : ''">
                                <img x-show="cat.image_url" :src="cat.image_url" :alt="cat.name" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                <span x-show="!cat.image_url" class="text-6xl opacity-70">🎟️</span>
                                <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent p-4">
                                    <p class="text-white text-xs font-medium opacity-90" x-text="cat.items.length + ' ' + (cat.items.length === 1 ? (t('ticket_singular') || 'opțiune') : (t('ticket_plural') || 'opțiuni'))"></p>
                                </div>
                            </div>
                            <div class="p-4 flex items-center justify-between">
                                <h3 class="font-display text-lg font-bold text-ink leading-tight" x-text="cat.name"></h3>
                                <svg class="w-5 h-5 text-forest-500 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </div>
                        </button>
                    </template>
                </div>

                <!-- ===== MODE B: Bilete din categoria selectata ===== -->
                <!-- Se afiseaza in 2 cazuri:
                     - utilizatorul a selectat o categorie (selectedCategoryId)
                     - nu sunt categorii definite → fallback la lista flat (showCategoryPicker = false) -->
                <div x-show="selectedDate && !loadingTickets && hasDisplayableTickets && !showCategoryPicker" class="space-y-4">
                    <!-- Breadcrumb back (vizibil doar cand exista picker, adica avem mai multe categorii) -->
                    <div x-show="hasMultipleCategories" class="flex items-center justify-between gap-3 mb-2">
                        <button type="button" @click="selectedCategoryId = null"
                                class="inline-flex items-center gap-2 text-sm font-semibold text-forest-700 hover:text-forest-900 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            <span data-i18n="back_to_categories" x-text="t('back_to_categories') || '← Înapoi la categorii'"></span>
                        </button>
                        <span x-show="selectedCategoryName" class="text-xs text-forest-700/60" x-text="selectedCategoryName"></span>
                    </div>

                    <template x-for="(grp, gi) in displayedTicketGroups" :key="grp.id">
                        <div class="space-y-2">
                            <!-- Header categorie (ascuns daca name e gol → backward compat fara categorii) -->
                            <h3 x-show="grp.name" class="font-display text-lg lg:text-xl font-bold text-ink mt-2 flex items-center gap-2">
                                <span x-text="grp.name"></span>
                                <span class="text-xs font-normal text-forest-700/50" x-text="'(' + grp.items.length + ')'"></span>
                            </h3>
                            <template x-for="ticket in grp.items" :key="ticket.id">
                        <div class="bg-white rounded-xl p-3 lg:p-4 border-2 transition-all"
                             :class="ticket.qty > 0 ? 'border-forest-500 shadow-md' : 'border-transparent hover:border-forest-200'">
                            <div class="flex items-start gap-3">
                                <!-- Iconita tip bilet ascunsa pe mobile (eliberare spatiu) -->
                                <div class="hidden lg:flex w-10 h-10 rounded-xl items-center justify-center flex-shrink-0 transition-colors"
                                     :class="ticket.qty > 0 ? 'bg-forest-600 text-white' : 'bg-forest-50 text-forest-700'">
                                    <span class="text-xl" x-text="ticket.icon || '🎟️'"></span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <h3 class="font-display text-base lg:text-lg font-bold text-ink leading-snug" x-text="ticket.name"></h3>
                                        <span x-show="ticket.is_parking" class="lv-badge bg-lake-100 text-lake-800 text-[10px] !py-0.5">🅿️</span>
                                        <span x-show="ticket.service_category === 'package'" class="lv-badge bg-rose-100 text-rose-800 text-[10px] font-bold !py-0.5">🎁</span>
                                        <span x-show="ticket.package_savings > 0" class="lv-badge bg-emerald-100 text-emerald-800 text-[10px] font-bold !py-0.5">
                                            -<span x-text="parseFloat(ticket.package_savings).toFixed(0)"></span> RON
                                        </span>
                                    </div>
                                    <p x-show="ticket.description" class="text-xs text-forest-700/70 mt-0.5 line-clamp-2" x-text="ticket.description"></p>
                                    <!-- Lista componentelor (pentru pachet) — compact -->
                                    <div x-show="ticket.service_category === 'package' && ticket.package_outputs && ticket.package_outputs.length > 0" class="mt-1.5 p-2 bg-rose-50 rounded-lg text-xs">
                                        <p class="text-[10px] uppercase tracking-wider text-rose-700 font-bold mb-1" data-i18n="package_includes">Include în pachet</p>
                                        <ul class="space-y-0.5">
                                            <template x-for="comp in (ticket.package_outputs || [])" :key="comp.ticket_type_id + (comp.variant_id || '')">
                                                <li class="text-rose-900 flex items-start gap-1">
                                                    <span class="text-rose-600">•</span>
                                                    <span><strong x-text="comp.qty + '×'"></strong> <span x-text="comp.component_name"></span></span>
                                                </li>
                                            </template>
                                        </ul>
                                    </div>
                                    <!-- Includes — pillule beneficii, mai compact -->
                                    <div x-show="ticket.includes && ticket.includes.length > 0" class="flex flex-wrap gap-1 mt-1.5">
                                        <template x-for="inc in ticket.includes" :key="inc">
                                            <span class="inline-flex items-center gap-0.5 text-[11px] px-1.5 py-0.5 bg-forest-50 text-forest-800 rounded">
                                                <svg class="w-2.5 h-2.5 text-forest-500" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                <span x-text="inc"></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <!-- Variante (Bărci 30m/1h etc.) — se afișează când există -->
                            <div x-show="ticket.variants && ticket.variants.length > 0" class="mt-4 pt-4 border-t border-forest-100">
                                <p class="text-xs uppercase tracking-wider text-forest-700/60 font-bold mb-2" data-i18n="choose_option">Alege opțiunea</p>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="v in (ticket.variants || [])" :key="v.id">
                                        <button @click="variantSelectedByTicket[ticket.id] = v.id; if (ticket.physical_inventory && ticket.physical_inventory.enabled) refreshPhysicalAvailability(ticket)"
                                                :class="(variantSelectedByTicket[ticket.id] || ticket.variants[0].id) === v.id
                                                    ? 'border-forest-600 bg-forest-600 text-white'
                                                    : 'border-forest-200 bg-white text-ink hover:border-forest-400'"
                                                class="px-3 py-2 border-2 rounded-xl text-sm font-semibold transition-colors text-left">
                                            <div x-text="v.label"></div>
                                            <div class="text-xs font-medium opacity-75"><span x-text="parseFloat(v.price).toFixed(2)"></span> RON</div>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <!-- F3: Slot-uri pe oră (Vaporașe etc.) -->
                            <div x-show="ticket.slots_config && ticket.slots_config.enabled" class="mt-4 pt-4 border-t border-forest-100">
                                <p class="text-xs uppercase tracking-wider text-forest-700/60 font-bold mb-2" data-i18n="choose_slot">Alege ora cursei</p>
                                <div class="flex flex-wrap gap-1.5 max-h-44 overflow-y-auto pr-1">
                                    <template x-for="slot in (ticketSlots[ticket.id] || [])" :key="slot.time">
                                        <button @click="slotSelectedByTicket[ticket.id] = slot.time"
                                                :disabled="slot.sold_out"
                                                :class="slot.sold_out ? 'bg-rose-100 text-rose-600 border-rose-200 cursor-not-allowed' : (slotSelectedByTicket[ticket.id] === slot.time ? 'bg-forest-600 text-white border-forest-600' : 'bg-white text-ink border-forest-200 hover:border-forest-500')"
                                                class="px-2.5 py-1.5 border-2 rounded-lg text-xs font-semibold transition-colors">
                                            <div x-text="slot.time"></div>
                                            <div class="text-[10px] opacity-70" x-text="slot.sold_out ? 'plin' : (slot.remaining + ' libere')"></div>
                                        </button>
                                    </template>
                                </div>
                                <p x-show="!(ticketSlots[ticket.id] && ticketSlots[ticket.id].length)" class="text-xs text-forest-700/60">Se încarcă orele disponibile...</p>
                            </div>
                            <!-- F5: Inventar fizic — selector ora start -->
                            <div x-show="ticket.physical_inventory && ticket.physical_inventory.enabled && !(ticket.slots_config && ticket.slots_config.enabled)" class="mt-4 pt-4 border-t border-forest-100">
                                <p class="text-xs uppercase tracking-wider text-forest-700/60 font-bold mb-2" data-i18n="rental_start_time">Ora de start a închirierii</p>
                                <div class="flex items-center gap-3">
                                    <input type="time" x-model="slotSelectedByTicket[ticket.id]" @change="refreshPhysicalAvailability(ticket)" class="px-3 py-2 border-2 border-forest-200 rounded-lg text-sm">
                                    <span class="text-xs text-forest-700/70" x-text="physicalAvailableLabel(ticket)"></span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between pt-2.5 mt-2.5 border-t border-forest-100">
                                <div>
                                    <p class="font-display text-xl lg:text-2xl font-bold text-ink leading-none">
                                        <span x-show="isGroupTicket(ticket)" class="text-xs font-medium text-forest-700/60" data-i18n="from_price">de la </span>
                                        <span x-text="groupDisplayPrice(ticket).toFixed(2)"></span>
                                        <span class="text-xs font-medium text-forest-700/60" x-text="ticket.currency || 'RON'"></span>
                                    </p>
                                    <p x-show="isGroupTicket(ticket)" class="text-[10px] text-forest-700/60 mt-0.5">
                                        <span x-text="ticketMin(ticket)"></span> × <span x-text="displayPriceFor(ticket).toFixed(2)"></span> <span x-text="ticket.currency || 'RON'"></span>
                                        <span x-show="(ticket.meta && ticket.meta.group_includes_guide)" class="ml-1 text-emerald-700 font-semibold">+ 1 ghid GRATUIT</span>
                                    </p>
                                    <p x-show="!isGroupTicket(ticket) && ticket.unit_label" class="text-[10px] text-forest-700/60 mt-0.5" x-text="ticket.unit_label"></p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button @click="decrementTicket(ticket)" :disabled="qtyForTicket(ticket) === 0" class="lv-qty-btn !w-8 !h-8 !text-base">−</button>
                                    <span class="w-6 text-center font-bold text-base text-ink" x-text="qtyForTicket(ticket)"></span>
                                    <button @click="incrementTicket(ticket)" :disabled="ticket.available !== null && qtyForTicket(ticket) >= ticket.available" class="lv-qty-btn !w-8 !h-8 !text-base bg-forest-700 text-white border-forest-700 hover:bg-forest-800 hover:border-forest-800">+</button>
                                </div>
                            </div>
                            <!-- Add-ons (apar doar dacă ticket are cantitate > 0) -->
                            <div x-show="ticket.addons && ticket.addons.length > 0 && qtyForTicket(ticket) > 0" class="mt-4 pt-4 border-t border-forest-100">
                                <p class="text-xs uppercase tracking-wider text-forest-700/60 font-bold mb-2">+ Extras opționale</p>
                                <div class="space-y-2">
                                    <template x-for="addon in (ticket.addons || [])" :key="addon.id">
                                        <div class="flex items-center justify-between gap-3 p-2.5 bg-amber-50 rounded-lg">
                                            <div class="flex-1 min-w-0">
                                                <div class="text-sm font-semibold text-ink" x-text="addon.label"></div>
                                                <div class="text-[11px] text-forest-700/70" x-text="addonHint(ticket, addon)"></div>
                                            </div>
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <button @click="addonDec(ticket, addon)" :disabled="(addonQty(ticket, addon) || 0) === 0" class="lv-qty-btn !w-7 !h-7 !text-sm">−</button>
                                                <span class="w-5 text-center font-bold text-sm" x-text="addonQty(ticket, addon)"></span>
                                                <button @click="addonInc(ticket, addon)" :disabled="(addonQty(ticket, addon) || 0) >= addonMaxTotal(ticket, addon)" class="lv-qty-btn !w-7 !h-7 !text-sm bg-forest-700 text-white border-forest-700 hover:bg-forest-800 hover:border-forest-800">+</button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                            </template>
                        </div>
                    </template>

                    <!-- Chip-uri mici cu celelalte categorii — afisate sub lista
                         de bilete cand utilizatorul e intr-o categorie selectata.
                         Permit navigarea rapida la alta categorie fara back-button. -->
                    <div x-show="hasMultipleCategories && otherCategoryChips.length > 0" class="pt-6 mt-6 border-t border-forest-100">
                        <p class="text-xs uppercase tracking-wider text-forest-700/60 font-bold mb-3" data-i18n="other_categories" x-text="t('other_categories') || 'Alte categorii de bilete'"></p>
                        <!-- Pe mobile: 1 chip per rand (texte lungi de categorie nu se mai taie). -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                            <template x-for="cat in otherCategoryChips" :key="cat.id">
                                <button type="button" @click="selectedCategoryId = cat.id; $nextTick(() => { document.getElementById('bilete')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); })"
                                        class="group bg-white border-2 border-forest-100 hover:border-forest-500 rounded-xl p-2 flex items-center gap-2.5 transition-all text-left">
                                    <div class="w-10 h-10 rounded-lg overflow-hidden flex-shrink-0 bg-fog flex items-center justify-center">
                                        <img x-show="cat.image_url" :src="cat.image_url" :alt="cat.name" class="w-full h-full object-cover">
                                        <span x-show="!cat.image_url" class="text-xl">🎟️</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-ink truncate" x-text="cat.name"></p>
                                        <p class="text-[10px] text-forest-700/60" x-text="cat.items.length + ' ' + (cat.items.length === 1 ? (t('ticket_singular') || 'opțiune') : (t('ticket_plural') || 'opțiuni'))"></p>
                                    </div>
                                    <svg class="w-3.5 h-3.5 text-forest-500 group-hover:translate-x-0.5 transition-transform flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Upsell CTA (apare după ce e cel puțin un bilet în coș + există servicii disponibile) -->
                <div x-show="cartCount > 0 && !upsellDismissed && services.length > 0" x-transition class="mt-6">
                    <div class="bg-gradient-to-br from-lake-700 via-forest-700 to-forest-800 rounded-3xl p-6 lg:p-8 text-white relative overflow-hidden">
                        <div class="absolute -top-12 -right-12 w-48 h-48 rounded-full bg-lake-400/20 blur-3xl"></div>
                        <div class="absolute -bottom-12 -left-12 w-48 h-48 rounded-full bg-forest-400/20 blur-3xl"></div>
                        <div class="relative flex flex-col lg:flex-row lg:items-center gap-5">
                            <div class="flex-1">
                                <p class="text-lake-200 text-xs uppercase tracking-[0.2em] font-bold mb-2" data-i18n="recommended_for_you">✨ Recomandate pentru tine</p>
                                <h3 class="font-display text-xl lg:text-2xl font-bold mb-2" data-i18n="upsell_title">Fă vizita o experiență completă</h3>
                                <p class="text-white/80 text-sm">Alege ce te tentează și economisește timp pe loc.</p>
                            </div>
                            <div class="flex gap-2 flex-shrink-0">
                                <button @click="scrollToServices()" class="lv-btn bg-white text-forest-800 hover:bg-lake-50" data-i18n="see_all">Vezi toate →</button>
                                <button @click="upsellDismissed = true" class="p-2 text-white/60 hover:text-white" aria-label="Închide">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                        <!-- Quick-add chips pentru top 3 servicii -->
                        <div x-show="topUpsellServices.length > 0" class="mt-5 pt-5 border-t border-white/15 relative">
                            <div class="flex flex-wrap gap-2">
                                <template x-for="service in topUpsellServices" :key="service.id">
                                    <button @click="incrementService(service)"
                                            :disabled="service.requires_access_ticket && !hasAccessInCart"
                                            class="inline-flex items-center gap-2 px-3 py-2 bg-white/10 backdrop-blur hover:bg-white/20 disabled:opacity-40 rounded-xl text-sm font-medium transition-colors group">
                                        <span class="text-lg" x-text="serviceEmoji(service.service_category)"></span>
                                        <span x-text="service.name"></span>
                                        <span class="text-lake-200 font-bold">+<span x-text="service.effective_price"></span> <span class="text-xs" x-text="service.currency || 'RON'"></span></span>
                                        <svg class="w-4 h-4 text-white/60 group-hover:text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============ SERVICII ============ -->
<section id="servicii" x-show="services.length > 0" class="lv-scroll-mt py-16 lg:py-24 px-6 lg:px-12 bg-white">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 mb-12">
            <div>
                <p class="text-forest-600 text-xs uppercase tracking-[0.3em] font-bold mb-3" data-i18n="step2">Pasul 2 din 2 · Opțional</p>
                <h2 class="font-display text-4xl lg:text-5xl font-bold text-ink mb-3" data-i18n="extra_services">Servicii suplimentare</h2>
                <p class="text-forest-700/70 max-w-xl" data-i18n="step2_subtitle">Adaugă plimbare cu barca, ghid privat sau alte experiențe.</p>
            </div>
            <span class="lv-badge bg-fog text-forest-700 border border-forest-100" x-show="anyRequiresAccess">
                ⚠️ <span data-i18n="requires_access_some">Unele necesită bilet de acces</span>
            </span>
        </div>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
            <template x-for="service in services" :key="service.id">
                <div class="group bg-fog rounded-2xl overflow-hidden border-2 transition-all"
                     :class="service.qty > 0 ? 'border-forest-500 shadow-md' : 'border-transparent hover:border-forest-200'">
                    <div class="aspect-[4/3] relative overflow-hidden flex items-center justify-center"
                         :style="service.image_url ? '' : `background: ${categoryGradient(service.service_category)}`">
                        <img x-show="service.image_url" :src="service.image_url" :alt="service.name" class="absolute inset-0 w-full h-full object-cover">
                        <div x-show="service.image_url" class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent"></div>
                        <span class="absolute top-3 left-3 lv-badge bg-white/90 backdrop-blur text-forest-800 text-xs z-10"
                              x-text="categoryLabel(service.service_category)"></span>
                        <span x-show="!service.image_url" class="text-6xl opacity-80" x-text="serviceEmoji(service.service_category)"></span>
                    </div>
                    <div class="p-5 bg-white">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <h3 class="font-display text-lg font-bold text-ink leading-tight" x-text="service.name"></h3>
                            <span x-show="service.service_category === 'package'" class="lv-badge bg-rose-100 text-rose-800 text-[10px] font-bold flex-shrink-0">🎁 PACHET</span>
                        </div>
                        <p class="text-sm text-forest-700/70 mb-2" x-text="service.description || ''"></p>
                        <div class="flex flex-wrap gap-1.5 mb-3" x-show="service.service_duration_minutes || (service.access_requirement && service.access_requirement !== 'none') || service.package_savings > 0">
                            <span x-show="service.service_duration_minutes" class="lv-badge bg-blue-50 text-blue-700 text-[10px]" x-text="formatDuration(service.service_duration_minutes)"></span>
                            <span x-show="service.access_requirement === 'adult_only'" class="lv-badge bg-amber-50 text-amber-700 text-[10px]">Necesită bilet acces ADULT</span>
                            <span x-show="service.access_requirement === 'any'" class="lv-badge bg-amber-50 text-amber-700 text-[10px]" data-i18n="requires_access_short">Necesită bilet acces</span>
                            <span x-show="service.package_savings > 0" class="lv-badge bg-emerald-100 text-emerald-800 text-[10px] font-bold">
                                <span data-i18n="savings_short">Economisești</span> <span x-text="parseFloat(service.package_savings).toFixed(2)"></span> RON
                            </span>
                        </div>
                        <!-- F10: Banner blocked time ranges pentru ziua selectată -->
                        <div x-show="Array.isArray(service.blocked_time_ranges_today) && service.blocked_time_ranges_today.length > 0" class="mb-3 p-2 bg-amber-50 border border-amber-200 rounded text-[11px] text-amber-900">
                            <p class="font-bold mb-0.5">⚠️ Atenție — intervale rezervate astăzi:</p>
                            <ul class="space-y-0.5">
                                <template x-for="(b, idx) in (service.blocked_time_ranges_today || [])" :key="idx">
                                    <li><strong x-text="b.start_time + '–' + b.end_time"></strong><span x-show="b.reason"> · <span x-text="b.reason"></span></span></li>
                                </template>
                            </ul>
                        </div>
                        <!-- Componente pachet -->
                        <div x-show="service.service_category === 'package' && service.package_outputs && service.package_outputs.length > 0" class="mb-3 p-2.5 bg-rose-50 rounded-lg">
                            <p class="text-[10px] uppercase tracking-wider text-rose-700 font-bold mb-1" data-i18n="includes_label">Include</p>
                            <ul class="space-y-0.5">
                                <template x-for="comp in (service.package_outputs || [])" :key="comp.ticket_type_id + (comp.variant_id || '')">
                                    <li class="text-xs text-rose-900"><strong x-text="comp.qty + '×'"></strong> <span x-text="comp.component_name"></span></li>
                                </template>
                            </ul>
                        </div>
                        <!-- Includes pe servicii -->
                        <div x-show="service.includes && service.includes.length > 0" class="flex flex-wrap gap-1.5 mb-3">
                            <template x-for="inc in service.includes" :key="inc">
                                <span class="inline-flex items-center gap-1 text-xs px-2 py-1 bg-forest-50 text-forest-800 rounded-md">
                                    <svg class="w-3 h-3 text-forest-500" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <span x-text="inc"></span>
                                </span>
                            </template>
                        </div>
                        <!-- Variante (Bărci 30m/1h etc.) — se afișează când există -->
                        <div x-show="service.variants && service.variants.length > 0" class="mt-3 mb-3">
                            <div class="flex flex-wrap gap-2">
                                <template x-for="v in (service.variants || [])" :key="v.id">
                                    <button @click="variantSelectedByTicket[service.id] = v.id; if (service.physical_inventory && service.physical_inventory.enabled) refreshPhysicalAvailability(service)"
                                            :class="(variantSelectedByTicket[service.id] || service.variants[0].id) === v.id
                                                ? 'border-forest-600 bg-forest-600 text-white'
                                                : 'border-forest-200 bg-white text-ink hover:border-forest-400'"
                                            class="px-2.5 py-1.5 border-2 rounded-lg text-xs font-semibold transition-colors text-left">
                                        <div x-text="v.label"></div>
                                        <div class="text-[10px] font-medium opacity-75"><span x-text="parseFloat(v.price).toFixed(2)"></span> RON</div>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <!-- F3: Slot-uri pe oră (Vaporașe) -->
                        <div x-show="service.slots_config && service.slots_config.enabled" class="mb-3">
                            <p class="text-[10px] uppercase tracking-wider text-forest-700/60 font-bold mb-1.5" data-i18n="choose_slot">Alege ora cursei</p>
                            <div class="flex flex-wrap gap-1 max-h-36 overflow-y-auto pr-1">
                                <template x-for="slot in (ticketSlots[service.id] || [])" :key="slot.time">
                                    <button @click="slotSelectedByTicket[service.id] = slot.time"
                                            :disabled="slot.sold_out"
                                            :class="slot.sold_out ? 'bg-rose-100 text-rose-600 border-rose-200 cursor-not-allowed' : (slotSelectedByTicket[service.id] === slot.time ? 'bg-forest-600 text-white border-forest-600' : 'bg-white text-ink border-forest-200')"
                                            class="px-2 py-1 border-2 rounded text-[11px] font-semibold">
                                        <span x-text="slot.time"></span><span x-show="!slot.sold_out" class="opacity-60"> · <span x-text="slot.remaining"></span></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <!-- F5: Physical inventory time picker -->
                        <div x-show="service.physical_inventory && service.physical_inventory.enabled && !(service.slots_config && service.slots_config.enabled)" class="mb-3">
                            <p class="text-[10px] uppercase tracking-wider text-forest-700/60 font-bold mb-1.5" data-i18n="rental_hour">Ora închiriere</p>
                            <div class="flex items-center gap-2">
                                <input type="time" x-model="slotSelectedByTicket[service.id]" @change="refreshPhysicalAvailability(service)" class="px-2 py-1.5 border-2 border-forest-200 rounded text-sm">
                                <span class="text-[11px] text-forest-700/70" x-text="physicalAvailableLabel(service)"></span>
                            </div>
                        </div>
                        <div class="flex items-end justify-between pt-3 border-t border-forest-100">
                            <div>
                                <p class="font-display text-2xl font-bold text-ink">
                                    <span x-text="displayPriceFor(service).toFixed(2)"></span>
                                    <span class="text-sm font-medium text-forest-700/60" x-text="service.currency || 'RON'"></span>
                                </p>
                                <p x-show="service.unit_label" class="text-xs text-forest-700/60" x-text="service.unit_label"></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button @click="decrementTicket(service)" :disabled="qtyForTicket(service) === 0" class="lv-qty-btn">−</button>
                                <span class="w-5 text-center font-bold text-ink" x-text="qtyForTicket(service)"></span>
                                <button @click="incrementService(service)" :disabled="(service.requires_access_ticket && !hasAccessInCart) || (service.available !== null && qtyForTicket(service) >= service.available)" class="lv-qty-btn bg-forest-700 text-white border-forest-700 hover:bg-forest-800 hover:border-forest-800">+</button>
                            </div>
                        </div>
                        <p x-show="service.requires_access_ticket && !hasAccessInCart && service.qty === 0" class="text-[11px] text-red-600 mt-2 font-medium" data-i18n="requires_access">Adaugă mai întâi un bilet de acces</p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</section>

<!-- ============ TRASEE TURISTICE ============ -->
<?php if (!empty($trails) || !empty($mapPois)): ?>
<section id="trasee" class="lv-scroll-mt py-16 lg:py-24 px-6 lg:px-12 bg-forest-900 text-white relative overflow-hidden">
    <div class="absolute inset-0 lv-topo-pattern opacity-10"></div>
    <div class="max-w-7xl mx-auto relative">
        <div class="text-center mb-12">
            <p class="text-lake-300 text-xs uppercase tracking-[0.3em] font-bold mb-3" data-i18n="marked_trails">Drumeții marcate</p>
            <h2 class="font-display text-4xl lg:text-5xl font-bold mb-3" data-i18n="tourist_trails">Trasee turistice</h2>
        </div>
        <?php if (!empty($trails)): ?>
        <div class="grid lg:grid-cols-2 gap-6 mb-10">
            <?php foreach ($trails as $trail): ?>
            <div class="bg-white/5 backdrop-blur rounded-2xl border border-white/10 p-6 hover:bg-white/[0.08] transition-all">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <div class="flex-1 min-w-0">
                        <?php if (!empty($trail['marker'])): ?>
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-white/10"><span class="font-bold text-sm"><?= htmlspecialchars($trail['marker_symbol'] ?? '✚') ?></span></span>
                            <p class="text-xs uppercase tracking-wider text-white/60 font-bold"><?= htmlspecialchars($trail['marker']) ?></p>
                        </div>
                        <?php endif; ?>
                        <h3 class="font-display text-2xl font-bold mb-2 leading-tight"><?= htmlspecialchars($trail['name'] ?? '') ?></h3>
                        <p class="text-sm text-white/70 leading-relaxed"><?= htmlspecialchars($trail['description'] ?? '') ?></p>
                    </div>
                    <?php if (!empty($trail['difficulty'])): ?>
                    <span class="lv-badge text-xs flex-shrink-0 bg-amber-500/20 text-amber-300"><?= htmlspecialchars($trail['difficulty']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-3 gap-3 pt-4 border-t border-white/10">
                    <?php foreach (['length' => ['Lungime', 'trail_length'], 'duration' => ['Durată', 'trail_duration'], 'elevation' => ['Diferență', 'trail_elevation']] as $k => $meta): ?>
                    <?php if (!empty($trail[$k])): ?>
                    <div>
                        <p class="text-xs text-white/50 uppercase tracking-wider font-medium" data-i18n="<?= $meta[1] ?>"><?= $meta[0] ?></p>
                        <p class="font-display text-lg font-bold mt-0.5"><?= htmlspecialchars($trail[$k]) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($trail['start_point'])): ?>
                <div class="mt-4 flex items-center gap-2 text-sm">
                    <svg class="w-4 h-4 text-lake-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    <span class="text-white/70"><span data-i18n="trail_starts_from">Pornește din:</span> <span class="font-semibold text-white"><?= htmlspecialchars($trail['start_point']) ?></span></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php /* #trailMap eliminat la cerere — afișa overlap cu locationMap si
                 nu aducea valoare suplimentară (POI-urile sunt deja in #locationMap). */ ?>
        <?php if ($safetyWarning): ?>
        <div class="mt-8 bg-amber-500/10 border border-amber-500/30 rounded-2xl p-5 flex items-start gap-3 max-w-3xl mx-auto">
            <span class="text-2xl flex-shrink-0"><?= htmlspecialchars($safetyWarning['icon'] ?? '⚠️') ?></span>
            <div class="text-sm">
                <p class="font-bold text-amber-200 mb-1"><?= htmlspecialchars($safetyWarning['title'] ?? 'Atenție') ?></p>
                <p class="text-amber-100/80"><?= htmlspecialchars($safetyWarning['body'] ?? '') ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============ DESPRE LOCAȚIE ============ -->
<?php if (!empty($attractions) || $description): ?>
<section id="despre" class="lv-scroll-mt py-16 lg:py-24 px-6 lg:px-12 bg-white">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-12">
            <p class="text-forest-600 text-xs uppercase tracking-[0.3em] font-bold mb-3" data-i18n="know_the_place">Cunoaște locul</p>
            <h2 class="font-display text-4xl lg:text-5xl font-bold text-ink mb-3"><?= htmlspecialchars($venueConfig['about_title'] ?? 'Despre locație') ?></h2>
        </div>
        <?php if (!empty($attractions)): ?>
        <div class="grid lg:grid-cols-<?= min(count($attractions), 2) ?> gap-8 mb-16">
            <?php foreach ($attractions as $a): ?>
            <article class="bg-fog rounded-3xl p-8 lg:p-10 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-40 h-40 rounded-full opacity-20 blur-3xl" style="background: <?= htmlspecialchars($a['blob_gradient'] ?? 'linear-gradient(135deg, #A5F3FC, #22D3EE)') ?>"></div>
                <div class="relative">
                    <?php if (!empty($a['badge'])): ?>
                    <span class="lv-badge mb-4" style="background: <?= htmlspecialchars($a['badge_bg'] ?? '#CFFAFE') ?>; color: <?= htmlspecialchars($a['badge_color'] ?? '#155E75') ?>;">
                        <?= htmlspecialchars($a['badge']) ?>
                    </span>
                    <?php endif; ?>
                    <h3 class="font-display text-3xl font-bold text-ink mb-4"><?= htmlspecialchars($a['name'] ?? $a['title'] ?? '') ?></h3>
                    <p class="text-forest-800 leading-relaxed mb-4"><?= htmlspecialchars($a['description'] ?? '') ?></p>
                    <?php if (!empty($a['bullets'])): ?>
                    <ul class="space-y-2 text-sm text-forest-800">
                        <?php foreach ($a['bullets'] as $bullet): ?>
                        <li class="flex items-start gap-2"><span class="text-forest-600 font-bold">•</span><span><?= htmlspecialchars($bullet) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($statsHighlights)): ?>
        <div class="grid grid-cols-2 lg:grid-cols-<?= min(count($statsHighlights), 4) ?> gap-6 mb-16">
            <?php foreach ($statsHighlights as $i => $s): ?>
            <div class="text-center">
                <p class="font-display text-5xl lg:text-6xl font-black <?= $i % 2 ? 'text-lake-700' : 'text-forest-700' ?>"><?= htmlspecialchars($s['value'] ?? '') ?></p>
                <p class="text-xs uppercase tracking-wider text-forest-700/60 font-bold mt-2"><?= htmlspecialchars($s['label'] ?? '') ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($flora)): ?>
        <div class="bg-forest-50 rounded-3xl p-8 lg:p-12">
            <h3 class="font-display text-2xl lg:text-3xl font-bold text-ink mb-6 text-center">Floră &amp; faună</h3>
            <div class="grid sm:grid-cols-2 lg:grid-cols-<?= min(count($flora), 4) ?> gap-6">
                <?php foreach ($flora as $sp): ?>
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto bg-white rounded-2xl flex items-center justify-center mb-3 text-3xl shadow-sm"><?= htmlspecialchars($sp['emoji'] ?? '🌿') ?></div>
                    <p class="font-bold text-ink text-sm"><?= htmlspecialchars($sp['name'] ?? '') ?></p>
                    <p class="text-xs text-forest-700/60 italic"><?= htmlspecialchars($sp['latin'] ?? '') ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($description) && empty($attractions)): ?>
        <div class="max-w-3xl mx-auto prose prose-lg text-forest-800">
            <?= $description ?>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============ PROGRAM SEZONIER ============ -->
<?php if (!empty($seasons)): ?>
<section class="py-16 lg:py-24 px-6 lg:px-12 bg-fog">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-12">
            <p class="text-forest-600 text-xs uppercase tracking-[0.3em] font-bold mb-3" data-i18n="program_h2">Program</p>
            <h2 class="font-display text-4xl lg:text-5xl font-bold text-ink mb-3" data-i18n="when_we_open">Când suntem deschiși</h2>
        </div>
        <div class="grid md:grid-cols-<?= min(count($seasons), 2) ?> gap-6">
            <?php
            // Etichete zile per locale (RO/HU/EN). Pentru alte locale → fallback RO.
            $dayLabelsByLocale = [
                'ro' => ['mon'=>'Luni','tue'=>'Marți','wed'=>'Miercuri','thu'=>'Joi','fri'=>'Vineri','sat'=>'Sâmbătă','sun'=>'Duminică'],
                'hu' => ['mon'=>'Hétfő','tue'=>'Kedd','wed'=>'Szerda','thu'=>'Csütörtök','fri'=>'Péntek','sat'=>'Szombat','sun'=>'Vasárnap'],
                'en' => ['mon'=>'Monday','tue'=>'Tuesday','wed'=>'Wednesday','thu'=>'Thursday','fri'=>'Friday','sat'=>'Saturday','sun'=>'Sunday'],
            ];
            $closedLabelByLocale = ['ro' => 'Închis', 'hu' => 'Zárva', 'en' => 'Closed'];
            $dayLabels   = $dayLabelsByLocale[$publicLocale] ?? $dayLabelsByLocale['ro'];
            $closedLabel = $closedLabelByLocale[$publicLocale] ?? $closedLabelByLocale['ro'];
            foreach ($seasons as $idx => $season):
                // Schema actuală: schedule_list (array de {day, open, close})
                // Schema legacy: schedule (object cu chei zile)
                $schedule = [];
                if (is_array($season['schedule_list'] ?? null)) {
                    foreach ($season['schedule_list'] as $entry) {
                        if (is_array($entry) && !empty($entry['day'])) {
                            $schedule[$entry['day']] = ['open' => $entry['open'] ?? null, 'close' => $entry['close'] ?? null];
                        }
                    }
                } elseif (is_array($season['schedule'] ?? null)) {
                    $schedule = $season['schedule'];
                }
            ?>
            <div class="bg-white rounded-3xl p-8 <?= $idx === 0 ? 'border-2 border-forest-500 relative' : 'border-2 border-forest-100' ?>">
                <?php if ($idx === 0): ?>
                <span class="absolute -top-3 left-8 lv-badge bg-forest-700 text-white">☀️ Activ</span>
                <?php endif; ?>
                <?php
                // Traduceri nume sezon: prefera translations[locale].name → name[locale] → name (string)
                $sName = '';
                if (!empty($season['translations'][$publicLocale]['name'])) {
                    $sName = $season['translations'][$publicLocale]['name'];
                } elseif (is_array($season['name'] ?? null)) {
                    $sName = $season['name'][$publicLocale] ?? $season['name']['ro'] ?? '';
                } else {
                    $sName = $season['name'] ?? 'Sezon';
                }
                ?>
                <h3 class="font-display text-2xl font-bold text-ink mb-1"><?= htmlspecialchars($sName ?: 'Sezon') ?></h3>
                <?php if (!empty($season['start']) && !empty($season['end'])): ?>
                <p class="text-sm text-forest-700/60 mb-6"><?= htmlspecialchars($season['start']) ?> – <?= htmlspecialchars($season['end']) ?></p>
                <?php endif; ?>
                <div class="space-y-2">
                    <?php foreach ($dayLabels as $key => $lbl): $hrs = $schedule[$key] ?? null; ?>
                    <div class="flex items-center justify-between py-2 border-b border-forest-100 last:border-0">
                        <span class="text-forest-800 font-medium"><?= $lbl ?></span>
                        <?php if ($hrs && !empty($hrs['open'])): ?>
                        <span class="text-ink font-semibold"><?= htmlspecialchars($hrs['open']) ?> – <?= htmlspecialchars($hrs['close'] ?? '') ?></span>
                        <?php else: ?>
                        <span class="text-red-600 font-semibold"><?= htmlspecialchars($closedLabel) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($season['note'])): ?>
                <div class="mt-6 pt-6 border-t border-forest-100 text-sm text-forest-800"><?= htmlspecialchars($season['note']) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ CUM AJUNGI ============ -->
<?php if (!empty($gettingThere) || $mapCenter): ?>
<section id="cum-ajungi" class="lv-scroll-mt py-16 lg:py-24 px-6 lg:px-12 bg-white">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-12">
            <p class="text-forest-600 text-xs uppercase tracking-[0.3em] font-bold mb-3" data-i18n="location_h2">Locație</p>
            <h2 class="font-display text-4xl lg:text-5xl font-bold text-ink mb-3" data-i18n="how_to_reach">Cum ajungi la noi</h2>
        </div>
        <?php if (!empty($gettingThere)): ?>
        <div class="grid lg:grid-cols-<?= min(count($gettingThere), 3) ?> gap-6 mb-8">
            <?php foreach ($gettingThere as $gt): ?>
            <div class="bg-fog rounded-2xl p-6">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4 text-white" style="background: <?= htmlspecialchars($gt['icon_bg'] ?? '#1F4E37') ?>">
                    <span class="text-2xl"><?= htmlspecialchars($gt['icon'] ?? '📍') ?></span>
                </div>
                <h3 class="font-display text-lg font-bold text-ink mb-2"><?= htmlspecialchars($gt['title'] ?? '') ?></h3>
                <p class="text-sm text-forest-800 mb-3"><?= htmlspecialchars($gt['description'] ?? '') ?></p>
                <?php if (!empty($gt['note'])): ?>
                <p class="text-xs text-forest-700/60"><?= htmlspecialchars($gt['note']) ?></p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($mapCenter): ?>
        <div class="bg-fog rounded-3xl p-2 mb-6">
            <div id="locationMap" class="rounded-2xl overflow-hidden" style="height: 480px;"></div>
        </div>
        <?php endif; ?>
        <div class="flex flex-wrap items-center justify-center gap-3">
            <?php if ($directionsUrl): ?>
            <a href="<?= htmlspecialchars($directionsUrl) ?>" target="_blank" rel="noopener" class="lv-btn lv-btn-primary">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                <span data-i18n="open_in_maps">Deschide în Google Maps</span>
            </a>
            <?php endif; ?>
            <?php if ($contactPhone):
                // WhatsApp click-to-chat — număr fără spații / + / paranteze
                $waNumber = preg_replace('/[^0-9]/', '', $contactPhone);
                $waMessage = rawurlencode('Salut! Sunt interesat de o vizită la ' . $eventTitle . '.');
            ?>
            <a href="https://wa.me/<?= $waNumber ?>?text=<?= $waMessage ?>" target="_blank" rel="noopener" class="lv-btn lv-btn-secondary">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                <span data-i18n="send_whatsapp">Trimite pe WhatsApp</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ GALERIE FOTO ============ -->
<?php if (!empty($galleryImages)): ?>
<section id="galerie" class="lv-scroll-mt py-16 lg:py-24 px-6 lg:px-12 bg-fog">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-12">
            <p class="text-forest-600 text-xs uppercase tracking-[0.3em] font-bold mb-3" data-i18n="gallery">Galerie</p>
            <h2 class="font-display text-4xl lg:text-5xl font-bold text-ink">În imagini</h2>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            <template x-for="(img, i) in gallery" :key="i">
                <a :href="img" target="_blank" rel="noopener"
                   class="block rounded-xl overflow-hidden aspect-square group hover:opacity-90 transition"
                   :class="i === 0 ? 'md:col-span-2 md:row-span-2 aspect-video md:aspect-square' : ''">
                    <img :src="img" alt="" class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-500" loading="lazy">
                </a>
            </template>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ VIDEO ============ -->
<?php if (!empty($videos)): ?>
<section id="video" class="lv-scroll-mt py-16 lg:py-24 px-6 lg:px-12 bg-white">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-12">
            <p class="text-forest-600 text-xs uppercase tracking-[0.3em] font-bold mb-3" data-i18n="video">Video</p>
            <h2 class="font-display text-4xl lg:text-5xl font-bold text-ink" data-i18n="place_in_motion">Locul în mișcare</h2>
        </div>
        <div class="grid md:grid-cols-2 gap-6">
            <template x-for="(v, i) in videos" :key="i">
                <div class="bg-fog rounded-2xl overflow-hidden">
                    <div class="aspect-video relative bg-forest-900">
                        <!-- YouTube embed -->
                        <template x-if="v.type === 'youtube'">
                            <iframe :src="`https://www.youtube.com/embed/${extractYoutubeId(v.url)}`" class="absolute inset-0 w-full h-full" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                        </template>
                        <template x-if="v.type === 'vimeo'">
                            <iframe :src="`https://player.vimeo.com/video/${extractVimeoId(v.url)}`" class="absolute inset-0 w-full h-full" allow="autoplay; fullscreen" allowfullscreen></iframe>
                        </template>
                        <template x-if="v.type === 'mp4' && v.file">
                            <video :poster="v.poster ? storageUrl(v.poster) : ''" controls class="absolute inset-0 w-full h-full object-cover">
                                <source :src="storageUrl(v.file)" type="video/mp4">
                            </video>
                        </template>
                    </div>
                    <div class="p-4" x-show="v.title">
                        <p class="font-display text-lg font-bold text-ink" x-text="v.title"></p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ FAQ ============ -->
<?php if (!empty($faqs)): ?>
<section class="py-16 lg:py-24 px-6 lg:px-12 bg-fog">
    <div class="max-w-3xl mx-auto">
        <div class="text-center mb-10">
            <p class="text-forest-600 text-xs uppercase tracking-[0.3em] font-bold mb-3" data-i18n="good_to_know">Bine de știut</p>
            <h2 class="font-display text-4xl lg:text-5xl font-bold text-ink" data-i18n="faqs_title">Întrebări frecvente</h2>
        </div>
        <div class="space-y-3">
            <template x-for="(faq, idx) in faqs" :key="idx">
                <div class="bg-white rounded-2xl border border-forest-100 overflow-hidden">
                    <button @click="openFaq = openFaq === idx ? null : idx" class="w-full text-left p-5 flex items-center justify-between gap-4 hover:bg-forest-50/50 transition-colors">
                        <span class="font-semibold text-ink" x-text="faq.q"></span>
                        <svg class="w-5 h-5 text-forest-700 flex-shrink-0 transition-transform" :class="openFaq === idx ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="openFaq === idx" x-collapse class="px-5 pb-5 text-forest-800 text-sm leading-relaxed border-t border-forest-100 pt-4" x-html="faq.a"></div>
                </div>
            </template>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============ STICKY CART ============ -->
<div x-show="cartCount > 0" x-transition class="fixed bottom-0 left-0 right-0 z-[110] lg:bottom-24 lg:right-6 lg:left-auto lg:max-w-sm">
    <div class="bg-forest-900 text-white shadow-2xl lg:rounded-2xl overflow-hidden">
        <button @click="hasStorageOnlyItems ? openStorageCart() : (cartOpen = !cartOpen)" class="w-full p-4 flex items-center justify-between gap-4 hover:bg-forest-800 transition-colors">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 bg-lake-400 rounded-xl flex items-center justify-center flex-shrink-0 font-bold text-forest-900" x-text="cartCount"></div>
                <div class="text-left min-w-0">
                    <p class="font-bold text-sm" x-text="cartCount + ' ' + (cartCount === 1 ? t('tickets_selected_one') : t('tickets_selected_many'))"></p>
                    <p class="text-xs text-white/60 truncate" x-text="selectedDate ? (t('cart_for') + ' ' + formatDate(selectedDate)) : t('choose_date')"></p>
                </div>
            </div>
            <div class="text-right flex-shrink-0">
                <p class="font-display text-xl font-bold"><span x-text="cartTotal"></span> RON</p>
                <svg class="w-4 h-4 ml-auto text-white/60 transition-transform" :class="cartOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
            </div>
        </button>
        <div x-show="cartOpen" x-collapse class="px-4 pb-4 border-t border-white/10 max-h-[60vh] overflow-y-auto">
            <p class="text-xs uppercase tracking-wider text-white/40 font-bold mt-4 mb-2" data-i18n="order_summary">Sumar comandă</p>
            <div class="space-y-2 mb-4">
                <template x-for="item in cartItems" :key="item._cartKey">
                    <div class="py-1.5 group">
                        <div class="flex items-center justify-between text-sm gap-2">
                            <span class="text-white/80 flex-1 min-w-0"><span x-text="item.qty"></span>× <span x-text="item.name"></span></span>
                            <div class="flex items-center gap-1 flex-shrink-0">
                                <!-- Info tooltip: split bilet + taxa ticketing (vizibil doar daca exista comision) -->
                                <span x-show="hasCommission" class="relative inline-flex items-center" @mouseenter="hoverTooltipKey = item._cartKey" @mouseleave="hoverTooltipKey = null" @click.stop="hoverTooltipKey = (hoverTooltipKey === item._cartKey ? null : item._cartKey)">
                                    <button type="button" class="text-white/50 hover:text-white p-0.5" :aria-label="t('price_breakdown') || 'Detalii preț'">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </button>
                                    <div x-show="hoverTooltipKey === item._cartKey" x-transition class="absolute right-full mr-2 top-1/2 -translate-y-1/2 bg-white text-forest-900 text-[11px] rounded-lg shadow-xl border border-forest-100 px-3 py-2 w-44 z-50">
                                        <div class="flex items-center justify-between gap-3 mb-1">
                                            <span class="text-forest-700/70" data-i18n="price_ticket">Preț bilet</span>
                                            <span class="font-bold"><span x-text="(item.qty * (item.effective_price - commissionPerTicket(item.effective_price))).toFixed(2)"></span> RON</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3 pb-1 border-b border-forest-100">
                                            <span class="text-forest-700/70" data-i18n="ticketing_fee">Taxă ticketing</span>
                                            <span class="font-bold"><span x-text="(item.qty * commissionPerTicket(item.effective_price)).toFixed(2)"></span> RON</span>
                                        </div>
                                        <div class="flex items-center justify-between gap-3 pt-1">
                                            <span class="font-semibold text-forest-900" data-i18n="total">Total</span>
                                            <span class="font-bold"><span x-text="(item.qty * item.effective_price).toFixed(2)"></span> RON</span>
                                        </div>
                                    </div>
                                </span>
                                <button @click="removeLineFromCart(item)" type="button" class="text-white/40 hover:text-rose-300 transition-colors p-0.5" title="Elimină din coș">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22"/></svg>
                                </button>
                                <span class="font-semibold whitespace-nowrap"><span x-text="(item.qty * item.effective_price).toFixed(2)"></span> RON</span>
                            </div>
                        </div>
                        <!-- Add-on lines -->
                        <template x-for="addon in (item._addons || [])" :key="addon.addon_id">
                            <div class="flex items-center justify-between text-xs text-white/55 mt-0.5 pl-3">
                                <span>+ <span x-text="addon.total_qty"></span>× <span x-text="addon.label"></span><span x-show="addon.free_qty > 0" class="text-emerald-300"> · <span x-text="addon.free_qty"></span> gratis</span></span>
                                <span x-show="addon.line_total > 0">+<span x-text="addon.line_total.toFixed(2)"></span> RON</span>
                                <span x-show="addon.line_total === 0" class="text-emerald-300">gratis</span>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
            <div x-show="hasCommission" class="flex items-center justify-between text-sm text-white/70 py-2 border-t border-white/10">
                <span data-i18n="subtotal_tickets">Subtotal bilete</span>
                <span><span x-text="cartSubtotalBase.toFixed(2)"></span> RON</span>
            </div>
            <div x-show="hasCommission" class="flex items-center justify-between text-sm text-white/70 py-1">
                <span data-i18n="total_commission">Total comision</span>
                <span>+<span x-text="cartCommissionTotal.toFixed(2)"></span> RON</span>
            </div>
            <div class="flex items-center justify-between py-3 border-t border-white/10">
                <span class="font-bold" data-i18n="total">Total</span>
                <span class="font-display text-2xl font-bold"><span x-text="cartTotal"></span> RON</span>
            </div>
            <button @click="checkout()" :disabled="!selectedDate || cartCount === 0 || !canCheckout" class="lv-btn w-full mt-2"
                    :class="!selectedDate || cartCount === 0 || !canCheckout ? 'bg-white/10 text-white/40' : 'bg-lake-400 text-forest-900 hover:bg-lake-300'">
                <span x-show="!selectedDate" data-i18n="choose_date_first">Alege mai întâi data</span>
                <span x-show="selectedDate && !canCheckout && cartCount > 0" data-i18n="missing_access">Lipsesc bilete acces</span>
                <span x-show="selectedDate && canCheckout && cartCount > 0" data-i18n="continue_to_payment">Continuă spre plată →</span>
            </button>
            <p x-show="!canCheckout && cartCount > 0" class="mt-2 text-xs text-amber-200">
                <template x-if="requiresAccessAdult > accessAdultInCart">
                    <span>⚠️ Adaugă <strong x-text="requiresAccessAdult - accessAdultInCart"></strong> bilet(e) acces ADULT (necesare pentru bărci).</span>
                </template>
                <template x-if="requiresAccessAdult <= accessAdultInCart && requiresAccessAny > accessAnyInCart">
                    <span>⚠️ Adaugă <strong x-text="requiresAccessAny - accessAnyInCart"></strong> bilet(e) acces (necesare pentru vaporașe).</span>
                </template>
            </p>
        </div>
    </div>
</div>

<div class="h-24 lg:h-0"></div>

</div><!-- /x-data reservationPage -->

<script>
function reservationPage() {
    const DATA = window.__LEISURE_VENUE__ || {};
    const SLUG = DATA.slug;
    const EVENT = DATA.event || {};
    const MAX_ADVANCE = DATA.max_advance_days || 90;
    const IS_PREVIEW = !!DATA.is_preview;

    // B4/B5 — locale + dicționar i18n
    const PUBLIC_LOCALE = DATA.locale || 'ro';
    const I18N_DICT = (DATA.i18n && DATA.i18n[PUBLIC_LOCALE]) ? DATA.i18n[PUBLIC_LOCALE] : (DATA.i18n?.ro || {});
    // Helper global pentru template-uri Alpine: t('key') sau t('days_short')
    function t(key, fallback) {
        const v = I18N_DICT[key];
        if (v === undefined || v === null) return fallback ?? '';
        return v;
    }
    // Aplica traduceri pe elementele cu data-i18n. Ruleaza dupa Alpine init (DOMContentLoaded);
    // daca un element nu are cheia in dictionar, lasa textul HTML original neatins (RO).
    function applyI18N() {
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.dataset.i18n;
            if (!key) return;
            const txt = I18N_DICT[key];
            if (typeof txt === 'string' && txt !== '') {
                el.textContent = txt;
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyI18N);
    } else {
        applyI18N();
    }

    // Numele lunilor — citite din dicționar daca exista, altfel RO ca default
    const MONTHS_RO = Array.isArray(I18N_DICT.months) && I18N_DICT.months.length === 12
        ? I18N_DICT.months
        : ['Ianuarie','Februarie','Martie','Aprilie','Mai','Iunie','Iulie','August','Septembrie','Octombrie','Noiembrie','Decembrie'];

    return {
        // i18n helper expus pe Alpine scope (utilizat in template prin t('key'))
        t: t,
        cartOpen: false,
        hoverTooltipKey: null,
        // Count total items in localStorage cart for THIS event (indiferent de visit_date).
        // Sursa de adevar pentru vizibilitatea floating cart + a numarului afisat.
        // Actualizat la init + pe eveniment 'ambilet:cart:update'.
        _storageCartCount: 0,
        // Categorie selectata in flow-ul nou (null = arata picker categorii)
        selectedCategoryId: null,
        openFaq: 0,
        upsellDismissed: false,
        selectedDate: null,
        currentMonth: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
        loadingMonth: true,
        loadingTickets: false,
        monthCache: {},        // 'YYYY-MM' -> { dates: { 'YYYY-MM-DD': { status } } }
        ticketsRaw: [],        // toate ticket types pentru data selectata (din API)
        ticketCategoriesRaw: [], // C2: categorii custom pentru grupare bilete
        qtyById: {},           // { ticketTypeId: qty } sau { 'ticketTypeId|variantId': qty }
        variantSelectedByTicket: {}, // { ticketId: variantId } — varianta activă pe card
        addonQtyByKey: {},     // { 'ticketCartKey::addonId': qty } — add-ons selectate per linie de cart
        slotSelectedByTicket: {}, // { ticketId: 'HH:MM' } — slot/ora aleasă (F3 / F5)
        ticketSlots: {},          // { ticketId: [{time, remaining, sold_out}] } — F3 cache disponibilitate
        physicalAvailableByTicket: {}, // { ticketId: number } — F5 cache rămas la interval
        issuers: DATA.issuers || {},
        faqs: DATA.faqs || [],
        gallery: DATA.gallery || [],
        videos: DATA.videos || [],
        nearbyHotels: DATA.nearby_hotels || [],
        commission: { rate: 0, fixed: 0, mode: 'included' },

        init() {
            this.loadMonth(this.monthKey(this.currentMonth));
            this.$nextTick(() => {
                this.renderTrailMap();
                this.renderLocationMap();
            });
            // Restaurare cos: daca clientul a navigat la /cos si a venit inapoi,
            // AmbiletCart are itemi pentru EVENT.id — re-selectam data lor +
            // hidratam qtyById dupa ce ticketsRaw e incarcat. Astfel cosul
            // float ramane plin, nu se reseteaza la 0.
            // Poll pentru AmbiletCart — cart.js este defer + declara AmbiletCart
            // la parse-time, dar in unele cazuri (cache, order defer) Alpine
            // init ruleaza inainte. Retry-uim pana e disponibil, apoi apelam
            // restoreCartState + refresh + timer.
            const waitForCart = (tries = 30) => {
                if (typeof window.AmbiletCart !== 'undefined') {
                    this.restoreCartState();
                    this.setupCartTimer();
                    this.refreshStorageCartCount();
                    // Re-evalueaza timer + contor cand AmbiletCart se schimba
                    window.addEventListener('ambilet:cart:update', () => { this.setupCartTimer(); this.refreshStorageCartCount(); });
                    window.addEventListener('ambilet:cart:clear', () => { this.hideCartTimer(); this.refreshStorageCartCount(); });
                    return;
                }
                if (tries <= 0) { console.warn('[leisure] AmbiletCart never became available'); return; }
                setTimeout(() => waitForCart(tries - 1), 100);
            };
            waitForCart();
            // BFCACHE fix: cand utilizatorul face BACK din /cos, pagina poate fi
            // servita din back-forward cache si init nu ruleaza. pageshow cu
            // persisted=true acopera acest caz — recitim cart-ul + repornim
            // timer-ul din localStorage pentru a nu ramane in stare stale.
            window.addEventListener('pageshow', (ev) => {
                if (ev.persisted) {
                    this.refreshStorageCartCount();
                    this.setupCartTimer();
                    // Daca ticketsRaw e populat + qtyById gol, re-hidram din cart
                    // (fara sa reincarcam data — items sunt deja acolo)
                    try {
                        const items = (AmbiletCart.getItems() || []).filter(i => Number(i.eventId) === Number(EVENT.id));
                        items.forEach(it => {
                            const key = this.cartKey(Number(it.ticketTypeId), it.meta?.variant_id || null);
                            if (this.qtyById[key] === 0 || this.qtyById[key] === undefined) {
                                this.qtyById[key] = Number(it.quantity) || 0;
                            }
                        });
                    } catch (e) { /* best-effort */ }
                }
            });
        },

        // Numara items din localStorage AmbiletCart pentru EVENT.id (indiferent
        // de visit_date). Sursa de adevar pentru vizibilitatea floating cart.
        refreshStorageCartCount() {
            try {
                if (typeof AmbiletCart === 'undefined' || !EVENT.id) { this._storageCartCount = 0; return; }
                const items = (AmbiletCart.getItems() || []).filter(i => Number(i?.eventId) === Number(EVENT.id));
                this._storageCartCount = items.reduce((s, i) => s + (Number(i.quantity) || 0), 0);
            } catch (e) { this._storageCartCount = 0; }
        },
        // Called cand user click pe cos flotant si acesta e populat DOAR din
        // storage (nu se vede in UI, ex: data selectata != visit_date items).
        // In loc sa arate panou gol, redirectam direct la /cos.
        openStorageCart() {
            const langParam = PUBLIC_LOCALE && PUBLIC_LOCALE !== 'ro' ? ('?lang=' + encodeURIComponent(PUBLIC_LOCALE)) : '';
            window.location.href = '/cos' + langParam;
        },

        // ========== Reservation timer bar ==========
        _cartTimerInterval: null,
        setupCartTimer() {
            try {
                const bar = document.getElementById('timer-bar');
                if (!bar) return;
                const cart = (typeof AmbiletCart !== 'undefined') ? AmbiletCart.getCart() : { items: [] };
                if (!cart.items || cart.items.length === 0) { this.hideCartTimer(); return; }
                let endTime = parseInt(localStorage.getItem('cart_end_time') || '0', 10);
                if (!endTime || endTime <= Date.now()) {
                    // Pornim un timer nou de 15 minute daca avem cart valid dar fara end_time
                    // (ex: cart restaurat dintr-o sesiune veche fara timer setat).
                    endTime = Date.now() + 15 * 60 * 1000;
                    localStorage.setItem('cart_end_time', String(endTime));
                }
                bar.style.display = '';
                bar.classList.remove('hidden');
                // Cand timer-ul e activ, quick-stats nu mai e sticky — timer-bar
                // ramane singurul sticky top. Asa nu ai 2 bare care se acopera.
                const stats = document.getElementById('quick-stats-bar');
                if (stats) { stats.classList.remove('lg:sticky'); }
                const tick = () => {
                    const remaining = Math.max(0, endTime - Date.now());
                    const mm = Math.floor(remaining / 60000);
                    const ss = Math.floor((remaining % 60000) / 1000);
                    const el = document.getElementById('countdown');
                    if (el) el.textContent = String(mm).padStart(2, '0') + ':' + String(ss).padStart(2, '0');
                    if (remaining <= 0) {
                        clearInterval(this._cartTimerInterval);
                        this._cartTimerInterval = null;
                        // Goleste cosul (in-tab) + ascunde bara
                        try { if (typeof AmbiletCart !== 'undefined' && AmbiletCart.clear) AmbiletCart.clear(); } catch (e) {}
                        Object.keys(this.qtyById).forEach(k => this.qtyById[k] = 0);
                        this.hideCartTimer();
                        alert(t('timer_expired', 'Timpul a expirat. Coșul a fost golit.'));
                        return;
                    }
                    // Sub 1 minut: rosu urgent
                    if (remaining < 60000) {
                        bar.classList.remove('bg-warning/10', 'border-warning/20');
                        bar.classList.add('bg-red-50', 'border-red-200');
                        if (el) { el.classList.remove('text-warning'); el.classList.add('text-red-600'); }
                    }
                };
                tick();
                if (this._cartTimerInterval) clearInterval(this._cartTimerInterval);
                this._cartTimerInterval = setInterval(tick, 1000);
            } catch (e) { console.warn('[timer-bar]', e); }
        },
        hideCartTimer() {
            const bar = document.getElementById('timer-bar');
            if (bar) { bar.style.display = 'none'; bar.classList.add('hidden'); }
            // Re-activeaza sticky pe quick-stats cand timer-ul dispare
            const stats = document.getElementById('quick-stats-bar');
            if (stats) { stats.classList.add('lg:sticky'); }
            if (this._cartTimerInterval) { clearInterval(this._cartTimerInterval); this._cartTimerInterval = null; }
        },

        // Re-hidrare a starii coşului din AmbiletCart (localStorage) după ce
        // utilizatorul navighează înapoi din /cos. Apelată o singură dată la init.
        async restoreCartState() {
            if (typeof AmbiletCart === 'undefined' || !EVENT.id) return;
            const items = (AmbiletCart.getItems() || []).filter(i => i && Number(i.eventId) === Number(EVENT.id));
            if (!items.length) return;

            // Toate item-urile aceluiași eveniment au aceeasi visit_date in mod
            // normal. Folosim primul ca sursa pentru selectedDate; daca lipseste,
            // skip restore (clientul va alege data manual).
            const restoreDate = items[0]?.meta?.visit_date || '';
            if (!restoreDate) return;

            await this.selectDate(restoreDate);

            // selectDate a populat ticketsRaw + a setat qtyById la 0 pentru
            // fiecare ticket type. Acum suprascriem cu cantitatile reale din cart.
            items.forEach(it => {
                const ttId = Number(it.ticketTypeId);
                if (!ttId) return;
                const vid = it.meta?.variant_id || null;
                const slot = it.meta?.slot_time || it.meta?.start_time || null;
                const key = this.cartKey(ttId, vid);
                this.qtyById[key] = Number(it.quantity) || 0;
                if (vid) this.variantSelectedByTicket[ttId] = vid;
                if (slot) this.slotSelectedByTicket[ttId] = slot;
            });

            // Daca toate biletele restaurate sunt dintr-o singura categorie, auto-selectam acea
            // categorie ca utilizatorul sa o vada deschisa imediat. Daca sunt mixte, lasam
            // picker-ul activ.
            const groupIds = new Set(items.map(it => {
                const tt = this.ticketsRaw.find(t => Number(t.id) === Number(it.ticketTypeId));
                return tt?.ticket_group || null;
            }).filter(Boolean));
            if (groupIds.size === 1) {
                this.selectedCategoryId = [...groupIds][0];
            }

            // Deschide cart-ul float automat ca utilizatorul sa vada produsele restaurate
            this.cartOpen = true;
        },

        // ========== Calendar ==========
        monthKey(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0'); },

        get monthLabel() {
            return MONTHS_RO[this.currentMonth.getMonth()] + ' ' + this.currentMonth.getFullYear();
        },

        get calendarDays() {
            const days = [];
            const y = this.currentMonth.getFullYear();
            const m = this.currentMonth.getMonth();
            const first = new Date(y, m, 1);
            let startDow = first.getDay();
            startDow = startDow === 0 ? 6 : startDow - 1;
            const daysInMonth = new Date(y, m + 1, 0).getDate();
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const maxDate = new Date(); maxDate.setDate(maxDate.getDate() + MAX_ADVANCE);
            const monthData = (this.monthCache[this.monthKey(this.currentMonth)] || {}).dates || {};

            for (let i = 0; i < startDow; i++) days.push({ key: 'e' + i, empty: true });

            for (let d = 1; d <= daysInMonth; d++) {
                const key = y + '-' + String(m + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
                const dObj = new Date(y, m, d);
                const data = monthData[key] || {};
                let status = data.status || 'available';
                let disabled = false;
                if (dObj < today || dObj > maxDate) { disabled = true; status = 'past'; }
                if (status === 'closed' || status === 'sold_out' || status === 'past') disabled = true;
                days.push({ key, day: d, empty: false, disabled, status });
            }
            return days;
        },

        prevMonth() {
            this.currentMonth = new Date(this.currentMonth.getFullYear(), this.currentMonth.getMonth() - 1, 1);
            this.loadMonth(this.monthKey(this.currentMonth));
        },
        nextMonth() {
            this.currentMonth = new Date(this.currentMonth.getFullYear(), this.currentMonth.getMonth() + 1, 1);
            this.loadMonth(this.monthKey(this.currentMonth));
        },

        async loadMonth(monthStr) {
            if (this.monthCache[monthStr]) { this.loadingMonth = false; return; }
            this.loadingMonth = true;
            try {
                const url = `/marketplace-events/${SLUG}/date-availability` + (IS_PREVIEW ? `?month=${monthStr}&preview=1` : `?month=${monthStr}`);
                const baseParams = Object.fromEntries(new URLSearchParams(url.split('?')[1] || ''));
                // B4: propaga locale-ul la API (date-availability filtreaza ticket types
                // si returnaza string-urile traduse din meta.translations).
                baseParams.lang = PUBLIC_LOCALE;
                const resp = await AmbiletAPI.get(url.split('?')[0], baseParams);
                if (resp && resp.dates) this.monthCache[monthStr] = resp;
            } catch (e) {
                console.warn('loadMonth failed', e);
            }
            this.loadingMonth = false;
        },

        // ========== Date selection ==========
        async selectDate(key) {
            this.selectedDate = key;
            this.loadingTickets = true;
            this.ticketsRaw = [];
            this.qtyById = {};
            // Reset categoria selectata daca schimbam data — categoriile pot diferi
            // intre date (ex: anumite tipuri de bilete nu sunt disponibile in
            // sezonul ales). Picker-ul se reafiseaza, utilizatorul alege din nou.
            this.selectedCategoryId = null;
            try {
                const params = { date: key, lang: PUBLIC_LOCALE };
                if (IS_PREVIEW) params.preview = 1;
                const resp = await AmbiletAPI.get(`/marketplace-events/${SLUG}/date-availability`, params);
                if (resp && resp.is_open) {
                    this.ticketsRaw = resp.ticket_types || [];
                    // C2 — categorii custom pentru gruparea biletelor (din venue_config.ticket_categories)
                    this.ticketCategoriesRaw = Array.isArray(resp.ticket_categories) ? resp.ticket_categories : [];
                    if (resp.commission) this.commission = resp.commission;
                    // Init qty map pentru ambele scenarii (cu/fara variants)
                    this.ticketsRaw.forEach(t => {
                        if (Array.isArray(t.variants) && t.variants.length > 0) {
                            t.variants.forEach(v => { this.qtyById[`${t.id}|${v.id}`] = 0; });
                            // pre-selecteaza prima varianta
                            this.variantSelectedByTicket[t.id] = t.variants[0].id;
                        } else {
                            this.qtyById[t.id] = 0;
                        }
                        // F3: preload slot availability dacă produsul are slots configurate
                        if (t.slots_config && t.slots_config.enabled) {
                            this.loadSlotsFor(t);
                        }
                    });
                } else {
                    this.ticketsRaw = [];
                }
            } catch (e) {
                console.error('selectDate failed', e);
                this.ticketsRaw = [];
            }
            this.loadingTickets = false;
        },

        // ========== Tickets/Services derived ==========
        // Helper: qty pe (ticket, variantă) — sau direct pe ticket dacă fără variants
        cartKey(ticketId, variantId) {
            return variantId ? `${ticketId}|${variantId}` : String(ticketId);
        },
        activeVariantId(t) {
            if (!Array.isArray(t.variants) || t.variants.length === 0) return null;
            return this.variantSelectedByTicket[t.id] || t.variants[0].id;
        },
        activeVariant(t) {
            const vid = this.activeVariantId(t);
            if (!vid) return null;
            return (t.variants || []).find(v => v.id === vid) || null;
        },
        displayPriceFor(t) {
            const v = this.activeVariant(t);
            return v ? parseFloat(v.price || 0) : parseFloat(t.effective_price || 0);
        },
        qtyForTicket(t) {
            // Pe card, qty = qty pentru varianta selectată curent (sau pentru tichet dacă fără variants)
            const vid = this.activeVariantId(t);
            return this.qtyById[this.cartKey(t.id, vid)] || 0;
        },
        // Sumă totală qty pe acest ticket (toate variantele)
        totalQtyForTicket(t) {
            if (!Array.isArray(t.variants) || t.variants.length === 0) {
                return this.qtyById[t.id] || 0;
            }
            return t.variants.reduce((s, v) => s + (this.qtyById[this.cartKey(t.id, v.id)] || 0), 0);
        },
        // Pune qty + reactivitate pe ticketsRaw items
        get _ticketsWithQty() {
            return this.ticketsRaw.map(t => ({ ...t, qty: this.totalQtyForTicket(t) }));
        },
        // accessTickets = bilete de acces PLUS pachete promotionale (pachetele
        // sunt afișate în secțiunea principală de bilete, NU în Servicii suplimentare).
        get accessTickets() {
            return this._ticketsWithQty.filter(t => {
                const cat = t.service_category || 'access';
                return cat === 'access' || cat === 'package';
            });
        },
        // services = TOATE produsele care nu sunt bilete acces și nu sunt pachete
        // ȘI care NU au fost asignate manual de organizator unei categorii
        // (cele cu ticket_group valid apar in accessTicketsGrouped).
        get services() {
            const cats = Array.isArray(this.ticketCategoriesRaw) ? this.ticketCategoriesRaw : [];
            const catIds = new Set(cats.map(c => c.id));
            return this._ticketsWithQty.filter(t => {
                const cat = t.service_category || 'access';
                if (cat === 'access' || cat === 'package') return false;
                // Daca user-ul a clasat manual o activitate intr-o categorie, NU mai apare in Servicii
                if (t.ticket_group && catIds.has(t.ticket_group)) return false;
                return true;
            });
        },
        // Pachetele promoționale ca grup virtual de afișare (apar mereu la finalul
        // listei de bilete, sub un header dedicat "Pachete promoționale").
        get packageTickets() {
            return this._ticketsWithQty.filter(t => (t.service_category || '') === 'package');
        },
        // Eticheta grupului de pachete in functie de locale curent.
        get packageGroupLabel() {
            return t('packages_promo_title') || 'Pachete promoționale';
        },
        // C2 — Grupare bilete pe ticket_categories (definite de organizator in
        // /organizator/leisure tab Produse → Categorii afișare).
        //
        // Regula UNICA: alocarea MANUALA (t.ticket_group) decide totul. Daca un
        // ticket are ticket_group setat catre o categorie existenta, intra in
        // acea categorie INDIFERENT de service_category (access/activity/package).
        //
        // Fallback pentru tickets FARA ticket_group: tip 'access' (default) →
        // grup ungrouped (header gol) DOAR daca nu exista categorii custom; cand
        // exista categorii, tickets-urile fara categorie ASIGNATA nu apar
        // (organizatorul decide ce e public). Pachetele NU mai au auto-grup
        // dedicat — daca nu e clasat manual, nu apare pe pagina publica.
        get accessTicketsGrouped() {
            const cats = Array.isArray(this.ticketCategoriesRaw) ? this.ticketCategoriesRaw : [];
            const catIds = new Set(cats.map(c => c.id));

            // Manual: orice ticket cu ticket_group valid intra in acea categorie.
            const manuallyGrouped = this._ticketsWithQty.filter(t =>
                t.ticket_group && catIds.has(t.ticket_group)
            );
            const manualIds = new Set(manuallyGrouped.map(t => t.id));

            // Backward compat: cand NU exista categorii custom, listam toate
            // biletele de acces intr-un grup flat (fara header). Asa pastram
            // pagina functionala pe organizatorii care nu folosesc categorii.
            if (!cats.length) {
                const autoAccess = this._ticketsWithQty.filter(t =>
                    (t.service_category || 'access') === 'access'
                );
                return autoAccess.length ? [{ id: '__all__', name: '', items: autoAccess }] : [];
            }

            // Cu categorii custom: doar ce a clasat organizatorul manual.
            const byId = {};
            for (const t of manuallyGrouped) {
                (byId[t.ticket_group] ||= []).push(t);
            }
            const groups = [];
            for (const c of cats) {
                const items = byId[c.id] || [];
                if (items.length) groups.push({ id: c.id, name: c.name, items });
            }
            return groups;
        },
        // ===== FLOW CATEGORII v2: picker + selected category navigation =====
        // Toate grupurile populate (folosit ca lista pentru picker + navigare).
        // Reuses accessTicketsGrouped + injecteaza image_url din ticketCategoriesRaw.
        get publicCategoryGroups() {
            const cats = Array.isArray(this.ticketCategoriesRaw) ? this.ticketCategoriesRaw : [];
            return this.accessTicketsGrouped.map(g => {
                const cat = cats.find(c => c.id === g.id);
                return {
                    ...g,
                    image_url: cat?.image_url || null,
                    // Pachetele primesc un placeholder vizual (nu au image_url propriu)
                    is_packages: g.id === '__packages__',
                };
            }).filter(g => g.items && g.items.length > 0);
        },
        // True daca avem >=2 grupuri afisabile → flow picker activ.
        get hasMultipleCategories() {
            return this.publicCategoryGroups.length >= 2;
        },
        // True cand avem CEL PUTIN 1 produs de aratat in sectiunea bilete (acces sau
        // manual-categorizat). Foloseste pentru gate-ul x-show al sectiunilor de
        // picker / mode-B, ca sa nu fie ascunse cand categoria selectata are doar
        // activitati/pachete (accessTickets.length nu numara activitatile).
        get hasDisplayableTickets() {
            return this.publicCategoryGroups.length > 0;
        },
        // True cand picker-ul de categorii e activ (utilizatorul nu a ales nimic INCA).
        get showCategoryPicker() {
            if (!this.hasMultipleCategories) return false;     // 0 sau 1 grup → fallback flat
            if (!this.selectedCategoryId) return true;          // niciun grup ales → picker
            // Verifica grupul ales sa existe (caz: se schimba data si grupul vechi nu mai are bilete)
            return !this.publicCategoryGroups.find(g => g.id === this.selectedCategoryId);
        },
        // Grupurile de afisat in lista de bilete: doar cel ales daca avem picker; toate altfel.
        get displayedTicketGroups() {
            if (!this.hasMultipleCategories) {
                // Fallback flat — afisam toate grupurile (ca inainte)
                return this.accessTicketsGrouped;
            }
            const sel = this.publicCategoryGroups.find(g => g.id === this.selectedCategoryId);
            return sel ? [sel] : [];
        },
        // Numele categoriei selectate (pentru breadcrumb back).
        get selectedCategoryName() {
            const sel = this.publicCategoryGroups.find(g => g.id === this.selectedCategoryId);
            return sel?.name || '';
        },
        // Chip-uri navigare jos: toate celelalte categorii (nu cea curenta).
        get otherCategoryChips() {
            if (!this.selectedCategoryId) return [];
            return this.publicCategoryGroups.filter(g => g.id !== this.selectedCategoryId);
        },
        get anyRequiresAccess() {
            return this.services.some(s => !!s.requires_access_ticket);
        },
        get hasAccessInCart() {
            return this.accessTickets.some(t => this.totalQtyForTicket(t) > 0);
        },
        // F6 — Total bilete acces în coș (any vs adult)
        get accessAnyInCart() {
            let n = 0;
            for (const t of this.accessTickets) n += this.totalQtyForTicket(t);
            return n;
        },
        get accessAdultInCart() {
            let n = 0;
            for (const t of this.accessTickets) {
                if (t.is_child_ticket) continue;
                n += this.totalQtyForTicket(t);
            }
            return n;
        },
        // F6 — Necesar pe baza produselor cu access_requirement
        get requiresAccessAny() {
            let n = 0;
            for (const t of this.ticketsRaw) {
                const req = t.access_requirement || (t.requires_access_ticket ? 'any' : 'none');
                if (req === 'any') n += this.totalQtyForTicket(t);
            }
            return n;
        },
        get requiresAccessAdult() {
            let n = 0;
            for (const t of this.ticketsRaw) {
                if ((t.access_requirement || 'none') === 'adult_only') n += this.totalQtyForTicket(t);
            }
            return n;
        },
        get accessGatingOk() {
            return this.requiresAccessAny <= this.accessAnyInCart
                && this.requiresAccessAdult <= this.accessAdultInCart;
        },
        get cartItems() {
            // Splite pe (ticket, variant) — fiecare combinaţie cu qty>0 = un line item în coş
            const out = [];
            for (const t of this.ticketsRaw) {
                if (Array.isArray(t.variants) && t.variants.length > 0) {
                    for (const v of t.variants) {
                        const cartK = this.cartKey(t.id, v.id);
                        const qty = this.qtyById[cartK] || 0;
                        if (qty > 0) {
                            out.push({
                                ...t,
                                qty,
                                effective_price: parseFloat(v.price || 0),
                                variant: v,
                                name: t.name + ' — ' + v.label,
                                _cartKey: cartK,
                                _addons: this._lineAddonsFor(t, cartK),
                            });
                        }
                    }
                } else {
                    const qty = this.qtyById[t.id] || 0;
                    if (qty > 0) {
                        out.push({
                            ...t,
                            qty,
                            variant: null,
                            _cartKey: String(t.id),
                            _addons: this._lineAddonsFor(t, String(t.id)),
                        });
                    }
                }
            }
            return out;
        },
        // Construiește lista add-ons pentru o linie de cart (cu qty + paid_qty + total)
        _lineAddonsFor(ticket, cartK) {
            const out = [];
            if (!Array.isArray(ticket.addons)) return out;
            const parentQty = this.qtyById[cartK] || 0;
            if (parentQty === 0) return out;
            for (const addon of ticket.addons) {
                const total = this.addonQtyByKey[cartK + '::' + addon.id] || 0;
                if (total <= 0) continue;
                const inc = parseInt(addon.included_qty || 0, 10);
                const free = Math.min(parentQty * inc, total);
                const paid = Math.max(0, total - free);
                const lineTotal = paid * parseFloat(addon.price || 0);
                out.push({
                    addon_id: addon.id,
                    label: addon.label,
                    total_qty: total,
                    free_qty: free,
                    paid_qty: paid,
                    unit_price: parseFloat(addon.price || 0),
                    line_total: lineTotal,
                });
            }
            return out;
        },
        get cartCount() {
            // Prioritizam localul (ce vede pe ecran); daca e 0 dar avem items in
            // localStorage (ex: back din /cos sau utilizatorul a schimbat data),
            // aratam contorul din storage ca sa nu para gol coșul.
            const local = this.cartItems.reduce((s, t) => s + t.qty, 0);
            return local > 0 ? local : this._storageCartCount;
        },
        // True cand avem items in localStorage pt EVENT.id dar nu apar in UI
        // (data selectata != visit_date-ul lor sau nu am selectat inca o data).
        get hasStorageOnlyItems() {
            const local = this.cartItems.reduce((s, t) => s + t.qty, 0);
            return local === 0 && this._storageCartCount > 0;
        },
        // Comision per UN bilet din acel tip: max(price * rate%, fixed) — numai daca mode='added_on_top'
        commissionPerTicket(price) {
            if ((this.commission.mode || 'included') !== 'added_on_top') return 0;
            const rate = parseFloat(this.commission.rate || 0);
            const fixed = parseFloat(this.commission.fixed || 0);
            const pct = parseFloat(price || 0) * rate / 100;
            return Math.max(pct, fixed);
        },
        cartItemSubtotal(item) {
            const unit = parseFloat(item.effective_price || 0);
            const com = this.commissionPerTicket(unit);
            return item.qty * (unit + com);
        },
        get cartSubtotalBase() {
            return this.cartItems.reduce((s, t) => s + t.qty * parseFloat(t.effective_price || 0), 0);
        },
        get cartAddonsTotal() {
            return this.cartItems.reduce((s, t) => s + (Array.isArray(t._addons) ? t._addons.reduce((a, ad) => a + ad.line_total, 0) : 0), 0);
        },
        get cartCommissionTotal() {
            return this.cartItems.reduce((s, t) => s + t.qty * this.commissionPerTicket(parseFloat(t.effective_price || 0)), 0);
        },
        get cartTotal() {
            return (this.cartSubtotalBase + this.cartAddonsTotal + this.cartCommissionTotal).toFixed(2);
        },
        get hasCommission() {
            return (this.commission.mode === 'added_on_top') && (this.commission.rate > 0 || this.commission.fixed > 0);
        },
        get canCheckout() {
            // F6: gating cumulativ — toate produsele care cer acces (any sau adult_only)
            // trebuie să aibă suficiente bilete acces în coș
            return this.accessGatingOk;
        },
        get topUpsellServices() {
            // Primele 3 servicii cu qty=0 (utilizatorul nu le-a adaugat inca)
            return this.services.filter(s => s.qty === 0).slice(0, 3);
        },

        // Helper: pas + minim pentru un ticket (default 1 / 1).
        // Pentru "bilet de grup" + pas absent → forțam pasul = min_per_order
        // (ex: grup de 10 → +10 / -10) ca sa nu poată ajunge la cantitati
        // invalide între pași.
        ticketStep(ticket) {
            const meta = ticket.meta || {};
            const min = Math.max(1, Number(ticket.min_per_order) || 1);
            const isGroup = !!meta.is_group_ticket;
            const step = Number(meta.step_qty);
            if (step && step > 0) return Math.max(1, step);
            return isGroup ? min : 1;
        },
        ticketMin(ticket) {
            return Math.max(1, Number(ticket.min_per_order) || 1);
        },
        isGroupTicket(ticket) {
            return !!(ticket && ticket.meta && ticket.meta.is_group_ticket);
        },
        // Pretul afișat pentru un bilet "de grup" este min_per_order × unit.
        // Pentru bilete normale e prețul unitar (același comportament ca înainte).
        groupDisplayPrice(ticket) {
            const unit = this.displayPriceFor(ticket);
            if (this.isGroupTicket(ticket)) {
                return unit * this.ticketMin(ticket);
            }
            return unit;
        },

        incrementTicket(ticket) {
            // F3/F5: blochează adăugare dacă produsul cere slot/oră
            const needsSlot = (ticket.slots_config && ticket.slots_config.enabled) || (ticket.physical_inventory && ticket.physical_inventory.enabled);
            if (needsSlot && !this.slotSelectedByTicket[ticket.id]) {
                alert('Selectează mai întâi ora pentru ' + ticket.name);
                return;
            }
            // F5: verifică disponibilitate fizică
            if (ticket.physical_inventory && ticket.physical_inventory.enabled) {
                const av = this.physicalAvailableByTicket[ticket.id];
                const curQty = this.qtyForTicket(ticket);
                if (av !== undefined && av !== null && curQty >= av) {
                    alert('Doar ' + av + ' unități libere la acea oră.');
                    return;
                }
            }
            const vid = this.activeVariantId(ticket);
            const key = this.cartKey(ticket.id, vid);
            const current = this.qtyById[key] || 0;
            const min = this.ticketMin(ticket);
            const step = this.ticketStep(ticket);
            // Prima adăugare → setam direct la minim (nu la 0 + step, pentru ca
            // operatorii pot avea min > step si vrem sa respectam minim setat).
            this.qtyById[key] = current === 0 ? Math.max(min, step) : current + step;
        },
        decrementTicket(ticket) {
            const vid = this.activeVariantId(ticket);
            const key = this.cartKey(ticket.id, vid);
            const current = this.qtyById[key] || 0;
            const min = this.ticketMin(ticket);
            const step = this.ticketStep(ticket);
            const next = current - step;
            // Sub minim → 0 (resetare completa)
            this.qtyById[key] = (next < min) ? 0 : next;
        },
        // Elimină complet o linie din coș (pe baza cheii compuse din _cartKey)
        removeLineFromCart(item) {
            if (!item || !item._cartKey) return;
            this.qtyById[item._cartKey] = 0;
            // Curăță și add-on-urile legate de acea linie
            Object.keys(this.addonQtyByKey).forEach(k => {
                if (k.startsWith(item._cartKey + '::')) this.addonQtyByKey[k] = 0;
            });
        },

        // ========== F3: Slot availability ==========
        async loadSlotsFor(ticket) {
            if (!ticket || !ticket.slots_config || !ticket.slots_config.enabled || !this.selectedDate) return;
            try {
                const resp = await AmbiletAPI.get(`/marketplace-events/${SLUG}/slot-availability`, {
                    ticket_type_id: ticket.id,
                    date: this.selectedDate,
                });
                this.ticketSlots[ticket.id] = resp.slots || [];
            } catch (e) {
                console.error('[loadSlotsFor] failed', e);
                this.ticketSlots[ticket.id] = [];
            }
        },

        // ========== F5: Physical inventory availability ==========
        async refreshPhysicalAvailability(ticket) {
            if (!ticket || !ticket.physical_inventory || !ticket.physical_inventory.enabled) return;
            if (!this.selectedDate) return;
            const startTime = this.slotSelectedByTicket[ticket.id];
            if (!startTime || !/^\d{2}:\d{2}$/.test(startTime)) return;
            const variant = this.activeVariant(ticket);
            const duration = (variant && variant.duration_minutes) ? variant.duration_minutes : (ticket.service_duration_minutes || 60);
            try {
                const resp = await AmbiletAPI.get(`/marketplace-events/${SLUG}/resource-availability`, {
                    ticket_type_id: ticket.id,
                    date: this.selectedDate,
                    start_time: startTime,
                    duration_minutes: duration,
                });
                this.physicalAvailableByTicket[ticket.id] = resp.available;
            } catch (e) {
                console.error('[refreshPhysicalAvailability] failed', e);
                this.physicalAvailableByTicket[ticket.id] = null;
            }
        },
        physicalAvailableLabel(ticket) {
            const av = this.physicalAvailableByTicket[ticket.id];
            const total = ticket.physical_inventory?.count || 0;
            if (av === undefined || av === null) return total > 0 ? `${total} total · alege ora pentru disponibilitate` : '';
            return av > 0 ? `${av}/${total} libere la acea oră` : `Niciuna liberă la acea oră`;
        },

        // ========== Add-ons helpers ==========
        addonCartKey(ticket, addon) {
            const vid = this.activeVariantId(ticket);
            return this.cartKey(ticket.id, vid) + '::' + addon.id;
        },
        addonQty(ticket, addon) {
            return this.addonQtyByKey[this.addonCartKey(ticket, addon)] || 0;
        },
        addonMaxTotal(ticket, addon) {
            const parentQty = this.qtyForTicket(ticket);
            const inc = parseInt(addon.included_qty || 0, 10);
            const max = parseInt(addon.max_per_unit || 5, 10);
            return parentQty * (inc + max);
        },
        addonFreePool(ticket, addon) {
            return this.qtyForTicket(ticket) * parseInt(addon.included_qty || 0, 10);
        },
        addonPaidQty(ticket, addon) {
            const total = this.addonQty(ticket, addon);
            const free = this.addonFreePool(ticket, addon);
            return Math.max(0, total - free);
        },
        addonHint(ticket, addon) {
            const free = this.addonFreePool(ticket, addon);
            const max = this.addonMaxTotal(ticket, addon);
            const price = parseFloat(addon.price || 0).toFixed(2);
            if (free > 0) {
                return `${free} incluse · ${price} RON/buc după · max ${max}`;
            }
            return `${price} RON/buc · max ${max}`;
        },
        addonInc(ticket, addon) {
            const key = this.addonCartKey(ticket, addon);
            const max = this.addonMaxTotal(ticket, addon);
            const cur = this.addonQty(ticket, addon);
            if (cur >= max) return;
            this.addonQtyByKey[key] = cur + 1;
        },
        addonDec(ticket, addon) {
            const key = this.addonCartKey(ticket, addon);
            this.addonQtyByKey[key] = Math.max(0, (this.addonQtyByKey[key] || 0) - 1);
        },
        incrementService(service) {
            if (service.requires_access_ticket && !this.hasAccessInCart) return;
            const vid = this.activeVariantId(service);
            const key = this.cartKey(service.id, vid);
            this.qtyById[key] = (this.qtyById[key] || 0) + 1;
            this.cartOpen = true;
        },

        // ========== Helpers ==========
        formatDate(d) {
            if (!d) return '';
            const date = new Date(d + 'T00:00:00');
            // Mapare locale → cod browser pentru toLocaleDateString
            const localeMap = { ro: 'ro-RO', hu: 'hu-HU', en: 'en-GB' };
            const loc = localeMap[PUBLIC_LOCALE] || 'ro-RO';
            return date.toLocaleDateString(loc, { weekday: 'long', day: 'numeric', month: 'long' });
        },
        formatDuration(min) {
            if (!min) return '';
            if (min < 60) return min + ' min';
            if (min % 1440 === 0) return (min / 1440) + (min === 1440 ? ' zi' : ' zile');
            if (min % 60 === 0) return (min / 60) + (min === 60 ? ' oră' : ' ore');
            return min + ' min';
        },
        categoryLabel(cat) {
            const map = { access:'Acces', parking:'Parcare', rental:'Închiriere', activity:'Activitate', extra:'Extra' };
            return map[cat] || 'Serviciu';
        },
        categoryGradient(cat) {
            const map = {
                access:   'linear-gradient(135deg, #1F4E37, #0F2C20)',
                parking:  'linear-gradient(135deg, #5BB17F, #2D7A4F)',
                rental:   'linear-gradient(135deg, #0891B2, #155E75)',
                activity: 'linear-gradient(135deg, #2D7A4F, #0F2C20)',
                extra:    'linear-gradient(135deg, #B89968, #92400E)',
            };
            return map[cat] || 'linear-gradient(135deg, #5BB17F, #155E75)';
        },
        serviceEmoji(cat) {
            const map = { access:'🎟️', parking:'🅿️', rental:'🎿', activity:'🚣', extra:'✨' };
            return map[cat] || '✨';
        },
        get currentScheduleLabel() {
            // Returneaza programul pe ziua curenta (sau "Verificați programul" fallback).
            // Suporta atat noua structura `schedule_list` (array de {day,open,close})
            // cat si vechea `schedule` (object cu chei zile) ca fallback.
            const seasons = (DATA.venue_config || {}).seasons || [];
            if (seasons.length === 0) return t('check_schedule') || 'Verificați programul';
            const today = new Date().toISOString().slice(5, 10); // MM-DD
            const dayKey = ['sun','mon','tue','wed','thu','fri','sat'][new Date().getDay()];
            // Suport 2 stocări: season.translations.hu/en.name (Filament) SAU
            // season.name = {ro,hu,en} (legacy) SAU string simplu RO.
            const seasonName = (s) => {
                if (s?.translations && s.translations[PUBLIC_LOCALE]?.name) {
                    return s.translations[PUBLIC_LOCALE].name;
                }
                if (typeof s.name === 'object' && s.name) {
                    return s.name[PUBLIC_LOCALE] || s.name.ro || s.name.en || '';
                }
                return s?.name || '';
            };
            for (const s of seasons) {
                const start = s.start || '01-01', end = s.end || '12-31';
                const inSeason = start <= end ? (today >= start && today <= end) : (today >= start || today <= end);
                if (!inSeason) continue;
                // Schema noua: schedule_list (array de {day, open, close})
                if (Array.isArray(s.schedule_list)) {
                    for (const entry of s.schedule_list) {
                        if (entry && entry.day === dayKey && entry.open) {
                            const name = seasonName(s) || (t('label_program') || 'Program');
                            return name + ': ' + entry.open + ' – ' + (entry.close || '');
                        }
                    }
                }
                // Schema veche: schedule object cu chei zile
                if (s.schedule && s.schedule[dayKey] && s.schedule[dayKey].open) {
                    const name = seasonName(s) || (t('label_program') || 'Program');
                    return name + ': ' + s.schedule[dayKey].open + ' – ' + s.schedule[dayKey].close;
                }
            }
            return t('closed_today') || 'Închis astăzi';
        },

        scrollToServices() {
            const el = document.getElementById('servicii');
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        },

        // Video helpers
        extractYoutubeId(url) {
            if (!url) return '';
            const m = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/);
            return m ? m[1] : url.trim();
        },
        extractVimeoId(url) {
            if (!url) return '';
            const m = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
            return m ? m[1] : url.trim();
        },
        storageUrl(path) {
            if (!path) return '';
            if (path.startsWith('http')) return path;
            return '<?= STORAGE_URL ?>/' + path.replace(/^\//, '');
        },

        // ========== Cart submit ==========
        checkout() {
            if (!this.selectedDate || this.cartCount === 0 || !this.canCheckout) return;
            if (typeof AmbiletCart === 'undefined' || !AmbiletCart.addItem) {
                console.error('AmbiletCart not available');
                return;
            }

            // Sync cart cu starea coşului din UI: cosul de pe ecran reflecta
            // qty-ul final pentru ACEST eveniment, indiferent ce era inainte
            // in cart (restoreCartState a hidratat qtyById din cart la load).
            // Sterge intai toate item-urile de pe acest event + visit_date din
            // cart, apoi adauga din nou cu cantitatile actuale.
            // Asa evitam dublarea cantitatilor la re-checkout (Back -> +qty -> Submit).
            try {
                const existing = (AmbiletCart.getItems() || []).filter(i =>
                    Number(i?.eventId) === Number(EVENT.id) &&
                    String(i?.meta?.visit_date || '') === String(this.selectedDate)
                );
                existing.forEach(i => {
                    if (i && i.key && AmbiletCart.removeItem) AmbiletCart.removeItem(i.key);
                });
            } catch (e) { /* never block checkout on cleanup */ }

            // Build plain primitives — Alpine reactive proxies se serializeaza
            // ca [object Object] si toate operatiile aritmetice dau NaN.
            // Aici fortam Number()/String() pentru fiecare camp.
            const eventPayload = {
                id: Number(EVENT.id) || 0,
                title: String(EVENT.name || ''),
                slug: String(EVENT.slug || ''),
                image: String(EVENT.image || ''),
                visit_date: String(this.selectedDate),
            };

            this.cartItems.forEach(t => {
                const unit = parseFloat(t.effective_price) || 0;
                const commission = this.commissionPerTicket(unit);
                const finalUnit = unit + commission;
                const variantInfo = t.variant ? {
                    id: String(t.variant.id),
                    label: String(t.variant.label),
                    duration_minutes: t.variant.duration_minutes ? Number(t.variant.duration_minutes) : null,
                    price: Number(parseFloat(t.variant.price)) || 0,
                } : null;
                const ticketPayload = {
                    id: Number(t.id) || 0,
                    name: String(t.name || ''),
                    price: Number(finalUnit) || 0,
                    originalPrice: Number(parseFloat(t.base_price)) || null,
                    commission_per_ticket: Number(commission) || 0,
                    min_per_order: Number(t.min_per_order) || 1,
                    max_per_order: Number(t.max_per_order) || 10,
                    is_parking: Boolean(t.is_parking),
                    requires_vehicle_info: Boolean(t.requires_vehicle_info),
                    service_category: String(t.service_category || 'access'),
                    issuing_company: String(t.issuing_company || 'primary'),
                    image_url: t.image_url ? String(t.image_url) : null,
                    variant: variantInfo,
                };
                const qty = parseInt(t.qty, 10) || 0;
                if (qty <= 0) return;

                // Semnatura veche (6 args) — alternative new (4 args) are
                // mapping bug in cart.js: muta meta -> quantity rezultand
                // [object Object] / NaN in /cos. Apelam direct positional.
                // Add-ons din linia curentă (Sanii cu tractare extra etc.)
                const addonsForCart = Array.isArray(t._addons) && t._addons.length > 0
                    ? t._addons.map(a => ({
                        addon_id: String(a.addon_id),
                        label: String(a.label),
                        total_qty: Number(a.total_qty) || 0,
                        free_qty: Number(a.free_qty) || 0,
                        paid_qty: Number(a.paid_qty) || 0,
                        unit_price: Number(a.unit_price) || 0,
                        line_total: Number(a.line_total) || 0,
                    }))
                    : null;

                const slotTime = this.slotSelectedByTicket[t.id] || null;

                AmbiletCart.addItem(
                    Number(EVENT.id) || 0,
                    eventPayload,
                    Number(t.id) || 0,
                    ticketPayload,
                    qty,
                    {
                        visit_date: String(this.selectedDate),
                        variant_id: variantInfo ? variantInfo.id : null,
                        variant_label: variantInfo ? variantInfo.label : null,
                        addons: addonsForCart,
                        slot_time: slotTime,
                        start_time: slotTime, // pentru physical_inventory
                    }
                );
            });

            // B5: păstram limba selectata pe pagina publica si in cos/checkout
            const langParam = PUBLIC_LOCALE && PUBLIC_LOCALE !== 'ro' ? ('?lang=' + encodeURIComponent(PUBLIC_LOCALE)) : '';
            window.location.href = '/cos' + langParam;
        },

        // ========== Maps (Leaflet) ==========
        renderTrailMap() {
            // #trailMap a fost eliminat din HTML — early return; functia ramane
            // ca stub pentru backward compat (init() o apeleaza).
            return;
            if (typeof L === 'undefined') return;
            const cfg = DATA.map_config || {};
            const center = cfg.center;
            const pois = cfg.pois || [];
            const trails = DATA.trails || [];
            const hotels = DATA.nearby_hotels || [];
            if (!center || !document.getElementById('trailMap')) return;
            const lat = center.lat || center[0];
            const lng = center.lng || center[1];
            const map = L.map('trailMap').setView([lat, lng], cfg.zoom || 13);
            L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap, &copy; OpenTopoMap',
                maxZoom: 17,
            }).addTo(map);

            // POI markers — puncte principale (lac, tinov, etc.) + puncte de intrare trasee
            pois.forEach(p => {
                const icon = L.divIcon({
                    html: `<div style="background:${p.color || '#1F4E37'};color:white;border-radius:50%;padding:6px 10px;font-weight:bold;font-size:11px;white-space:nowrap;border:2px solid white;box-shadow:0 4px 12px rgba(0,0,0,0.3)">${(p.label || '').replace(/[<>&]/g, '')}</div>`,
                    className: '', iconSize: null,
                });
                L.marker([p.lat, p.lng], { icon }).addTo(map);
            });

            // Hotel markers — mai mici, distincte
            hotels.forEach(h => {
                if (!h.lat || !h.lng) return;
                const icon = L.divIcon({
                    html: `<div style="background:#B89968;color:white;border-radius:8px;padding:4px 8px;font-weight:600;font-size:10px;white-space:nowrap;border:2px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.2);display:flex;align-items:center;gap:4px"><span>🏨</span><span>${(h.name || '').replace(/[<>&]/g, '')}</span></div>`,
                    className: '', iconSize: null,
                });
                const marker = L.marker([h.lat, h.lng], { icon }).addTo(map);
                if (h.subtitle || h.url) {
                    const popup = `<strong>${(h.name || '').replace(/[<>&]/g, '')}</strong>` +
                        (h.subtitle ? `<br><small>${(h.subtitle || '').replace(/[<>&]/g, '')}</small>` : '') +
                        (h.url ? `<br><a href="${h.url}" target="_blank" rel="noopener">Vezi detalii →</a>` : '');
                    marker.bindPopup(popup);
                }
            });

            // Trail polylines (dacă au coords)
            trails.forEach(t => {
                if (!t.polyline || !Array.isArray(t.polyline) || t.polyline.length < 2) return;
                const color = (t.marker && t.marker.toLowerCase().includes('albast')) ? '#3B82F6' : '#EF4444';
                L.polyline(t.polyline, { color, weight: 3, dashArray: '6, 6' })
                    .addTo(map)
                    .bindPopup(`<strong>${(t.name || '').replace(/[<>&]/g, '')}</strong>`);
            });
        },

        renderLocationMap() {
            if (typeof L === 'undefined') return;
            const cfg = DATA.map_config || {};
            const center = cfg.center;
            if (!center || !document.getElementById('locationMap')) return;
            const lat = center.lat || center[0];
            const lng = center.lng || center[1];
            const map = L.map('locationMap').setView([lat, lng], (cfg.zoom || 13) - 1);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                attribution: '&copy; OpenStreetMap, &copy; CARTO', maxZoom: 19,
            }).addTo(map);
            const icon = L.divIcon({
                html: `<div style="background:#1F4E37;color:white;border-radius:50%;width:48px;height:48px;display:flex;align-items:center;justify-content:center;font-size:20px;border:4px solid white;box-shadow:0 6px 16px rgba(0,0,0,0.3)">📍</div>`,
                className: '', iconSize: [48, 48],
            });
            L.marker([lat, lng], { icon }).addTo(map).bindPopup(EVENT.name || 'Locație');
        },
    };
}
</script>

<?php
// Inject leisure-venue.js (păstrat pentru retrocompatibilitate cu vechi DOM, dar
// designul nou foloseste Alpine inline; JS-ul vechi nu mai gaseste elementele
// vechi #cal-days etc., deci nu strica nimic).
$leisureJsPath = __DIR__ . '/assets/js/pages/leisure-venue.js';
$leisureAssets = defined('ASSETS_URL') ? ASSETS_URL : '/assets';
require_once __DIR__ . '/includes/footer.php';
require_once __DIR__ . '/includes/scripts.php';
?>
