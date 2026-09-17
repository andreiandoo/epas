<?php
/**
 * Partners: /parteneri (v2, direction "product theatre"). The single sales page for operators: everything
 * /devino-partener, /pentru-locatii and /vinde-bilete say, in one place (those pages stay online). The vague
 * Start / Growth / Pro tiers of /pentru-locatii are left out on purpose.
 *
 * Top to bottom: hero with the stage (personalised by ?tip=<type>&loc=<name>, copy shared with /devino-partener in
 * includes/v2/partner-profiles.php), ecosystem numbers, chapter nav, who it's for (+ live categories), the problem and
 * its fix, booking on slots (#booking), a day at the venue (#o-zi), the operator panel (#panou), the ticket office
 * (#ghiseu), the scanning app (#scanare), hardware, partner stories (#povesti: a film and a wall of quotes), everything
 * you get (#ce-primesti) and the modules, visibility / SEO
 * (#vizibilitate), analytics and tracking (#analytics), payments (#plati), the money (#bani, calculator), fiscal / ANAF
 * (#fiscal), how to start (#cum), the Tixello engine (#tehnologie), FAQ (#intrebari), one large partner quote, and the
 * finale: self-service signup or a demo request (#demo, same lead pipeline as /pentru-locatii through for-venues.js).
 *
 * The stories come from includes/v2/partner-testimonials.php. Stand-ins marked 'demo' show only in preview (?preview=1,
 * which also skips the page cache) with a "Demo" tag, never to visitors; with nothing real to show, the stories
 * sections and their chapter link are left out.
 *
 * The screens are HTML mock-ups with sample figures, marked as such; the catalogue counts come from the API.
 * partners.js runs the stage, the demos, the funnel pings (leads.track, like /devino-partener) and, on large screens
 * with motion allowed, the scroll scenes (GSAP + ScrollTrigger + Lenis, loaded only there). Everything reads without
 * JavaScript and without motion.
 */

$pageCacheTTL = 900;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/partner-profiles.php';
require_once __DIR__ . '/includes/v2/partner-testimonials.php';

$ptAccent = '<strong class="pt-accent">doar 2%*</strong>';
$ptProfiles = v2_partner_profiles($ptAccent);

$ptR = api_cached_many([
    'attractions' => ['key' => 'v2_attractions_total', 'endpoint' => '/attractions', 'params' => ['per_page' => 1], 'ttl' => 21600],
]);
$ptAttractions = (int) ($ptR['attractions']['data']['pagination']['total'] ?? 0);
$ptCities = count($V2NAV['allCities'] ?? []);
$ptCategories = array_values(array_filter($V2NAV['categories'] ?? [], fn ($c) => !empty($c['name'])));

// Real results of the Tixello ecosystem bilete.online runs on (confirmed by the owner).
$ptStats = [[4294, 'Evenimente & activități', ''], [96341, 'Clienți în bază', ''], [301310, 'Bilete vândute', ''], [4409557, 'Vânzări generate', ' €']];

$ptWho = [
    ['lock-simple', 'Escape rooms', 'Sloturi orare, capacitate per cameră, beneficiari diferiți, bilete de grup și check-in rapid.'],
    ['buildings', 'Muzee & expoziții', 'Bilete de acces, expoziții temporare, tururi ghidate, copii/adulți, gratuități și program.'],
    ['castle-turret', 'Parcuri & agrement', 'Pachete, categorii de vârstă, acces pe zi, extra-opțiuni și capacitate.'],
    ['map-pin', 'Peșteri & rezervații', 'Tururi, reguli de acces, nivel de dificultate, echipament, ghid și sezonalitate.'],
    ['star', 'Ateliere & educație', 'Locuri limitate, vârste recomandate, materiale incluse, grupuri școlare.'],
    ['globe-simple', 'Tururi & experiențe', 'City walks, tururi gastronomice, tururi istorice, experiențe turistice și private.'],
];
// the pain, then what the platform does about it (the fix repeats claims made further down the page)
$ptProblems = [
    ['coins', 'Comisioane din marja ta', 'Plătești tu, la fiecare bilet. La volum, e o gaură reală în buget.', '2%* plătit de cumpărător', 'Îți stabilești prețul și îl primești integral la decont.'],
    ['calendar-blank', 'Booking inflexibil', 'Activitățile au sloturi, zile, capacități. Majoritatea platformelor nu le suportă.', 'Sloturi, zile și capacitate', 'Clientul alege ziua, ora, participanții și opțiunile.'],
    ['x', 'Tracking pierdut', 'Ad blockerele și iOS blochează datele de conversie — plătești mai mult pe reclame.', '100% evenimente urmărite', 'Conversii trimise server-side: reclame până la 60% mai ieftine.'],
];
$ptBookingList = ['Selecție de zile disponibile pe calendar', 'Sloturi orare cu capacitate configurabilă', 'Booking detaliat: participanți, opțiuni, add-on-uri', 'Pachete de grup și prețuri pe categorie de vârstă'];
$ptBookingSteps = ['Ora', 'Bilet', 'Extra', 'Nume', 'Plată', 'Gata'];
$ptDay = [
    ['clock', '08:40', 'Deschizi ziua', 'Vezi câte bilete sunt vândute pentru azi și câți oameni sunt așteptați pe fiecare interval.'],
    ['shopping-cart-simple', '09:15', 'Vânzări online', 'Oamenii cumpără de pe pagina activității tale sau din widgetul de pe site-ul propriu. Biletul pleacă pe email, cu cod QR.'],
    ['printer', '10:30', 'Vânzare la ghișeu', 'La intrare, POS-ul emite biletul pe loc: coș, cash sau card, bon tipărit pe imprimanta termică.'],
    ['scan', '11:00', 'Scanare la intrare', 'Cu telefonul sau tableta. Biletul e valid, a mai fost scanat sau nu e recunoscut: vezi pe loc, cu sunet și vibrație.'],
    ['door-open', '18:00', 'Închizi casa', 'Desfășurătorul casei arată cât cash predai și cât s-a încasat pe card, pe tura fiecărui operator.'],
    ['chart-line-up', '18:20', 'Vezi rezultatul', 'Raportul zilei adună online și ghișeu, pe activitate, pe interval și pe tip de bilet.'],
];
$ptPosChecks = [
    'Coș cu tipurile tale de bilete, pachete și extra-opțiuni',
    'Încasare cash sau card, pe tura fiecărui operator',
    'Bon tipărit automat după fiecare comandă, dacă vrei',
    'Desfășurător de casă: cât cash predai, cât s-a încasat pe card',
    'Închidere de casă la final de tură, cu totalul comenzilor',
    'Factură pentru firme, direct din comandă',
    'Servicii suplimentare și închirieri (rentals), pe lângă biletele de acces',
    'Online + local, în același sistem',
];
$ptPosItems = [['Intrare adult', 45], ['Intrare copil', 25], ['Tur ghidat', 60], ['Familie (2+2)', 120], ['Audioghid', 15], ['Atelier', 80]];
$ptScanChecks = [
    'Trei răspunsuri clare: acces aprobat, deja scanat, bilet invalid',
    'Scanare QR cu validare anti-fraudă și prevenirea dublei intrări',
    'Funcționează fără internet stabil: scanezi offline, se sincronizează ulterior',
    'Cod tastat manual, când biletul e șifonat sau ecranul e crăpat',
    'Porți de acces și oameni alocați pe fiecare poartă',
    'Listă de invitați și check-in fără bilet tipărit',
    'Ecranul nu se stinge cât scanezi, iar aplicația merge și pe tabletă',
    'Rapoarte pe tură: câți au intrat, când, pe ce poartă',
    'Vânzări și trafic în timp real, oriunde te-ai afla',
];
$ptHardware = [
    ['scan', 'Un telefon sau o tabletă', 'Android sau iPhone, pentru scanare la intrare și pentru vânzare pe loc.'],
    ['printer', 'O imprimantă termică', 'De 58 sau 80 mm, pentru bon. Se tipărește din browser, fără drivere speciale.'],
    ['squares-four', 'Un calculator pentru ghișeu', 'Orice laptop sau desktop cu un browser modern. POS-ul rulează în browser.'],
    ['code', 'Site-ul tău, dacă ai unul', 'Pui widgetul de vânzare pe pagina ta și vinzi direct de acolo, cu aceleași bilete.'],
    ['file-text', 'Documente și facturi', 'Facturi pentru firme, documentele contului și rapoartele rămân în panou.'],
    ['headset', 'Oameni care răspund', 'Suport pe email și telefon, luni - vineri, 09:00 - 18:00, plus tichete din panou.'],
];
$ptStack = [
    ['01', 'SEO', 'Pagini care pot atrage trafic organic.', 'Pagină de locație, pagini pentru activități, categorii, orașe și intenții precum „activități copii”, „weekend”, „indoor”, „sub 50 lei”.', 'is-seo'],
    ['02', 'Checkout', 'Cumpărare rapidă, clară, modernă.', 'Card (inclusiv Apple Pay și Google Pay), Card Cultural acolo unde este acceptat, beneficiari diferiți, cont automat, taxe afișate separat și opțiuni comerciale.', 'is-checkout'],
    ['03', 'QR', 'Bilete digitale și check-in rapid.', 'Fiecare bilet are cod unic, status, beneficiar și poate fi scanat la intrare pentru control clar al accesului.', 'is-qr'],
    ['04', 'Dashboard', 'Comenzi, clienți, scanări și rapoarte.', 'Vezi vânzările, biletele emise, participanții, disponibilitatea, statusurile și performanța activităților.', 'is-dash'],
    ['05', 'Growth', 'Promoții, vouchere, carduri cadou.', 'Poți rula coduri promo, campanii sezoniere, carduri cadou, puncte bonus și oferte pentru audiențe specifice.', 'is-growth'],
    ['06', 'Trust', 'O experiență mai bună pentru clienți.', 'Clientul vede clar ce cumpără, unde merge, cum intră, ce include biletul și ce se întâmplă după plată.', 'is-trust'],
];
$ptBenefits = [
    ['plus', 'Activități nelimitate', 'Adaugi oricâte activități, de orice tip și în orice formă. Fără limită, fără costuri de pornire.'],
    ['list', 'Gestiune avansată', 'Capacități, sloturi, variante de preț, disponibilitate, add-on-uri — controlezi fiecare detaliu al fiecărei activități.'],
    ['tag', 'Coduri de reducere', 'Creezi coduri promoționale și campanii de discount, cu reguli proprii, ca să-ți crești vânzările când vrei.'],
    ['users-three', 'Pachete de grup', 'Vinzi pachete pentru grupuri, familii, clase sau echipe corporate, cu prețuri și capacități dedicate.'],
    ['target', 'Sistem de recomandare', 'Motorul propriu expune activitățile tale celor mai potriviți cumpărători din baza de peste 96.000 de clienți.'],
    ['lock-simple', 'Bilete sigure', 'Validare QR, verificare și protecție anti-fraudă — tehnologie testată în producție pe Tixello.'],
];
$ptSmall = [
    ['ticket', 'Pagină dedicată per activitate', 'Pagină SEO-friendly, link de partajat și schema markup pentru Google.'],
    ['clock', 'Liste de așteptare', 'Slot plin? Clientul se înscrie pe waitlist și e anunțat dacă se eliberează locuri.'],
    ['qr-code', 'Check-in & control acces', 'Validare QR cu prevenirea dublei intrări, ideal la sloturi cu capacitate fixă.'],
    ['map-pin', 'Multi-locație', 'Gestionezi mai multe locații sau puncte de lucru dintr-un singur cont.'],
    ['user-circle', 'Roluri & echipă', 'Adaugi colegi cu permisiuni (casier, scanare, manager), fără acces total.'],
    ['heart', 'Branding propriu', 'Logo, culori și aspect pe paginile tale, ca să arate ca brandul tău.'],
    ['star', 'Recenzii & rating', 'Clienții lasă recenzii care cresc conversia pentru următorii cumpărători.'],
    ['file-text', 'Export & rapoarte', 'Exporți comenzi, participanți și încasări pentru raportare și contabilitate.'],
];
$ptSeoUrls = [
    ['/brasov/activitati-copii', 'oraș + intenție'],
    ['/escape-rooms', 'categorie'],
    ['/locatie/mystery-rooms', 'pagină locație'],
    ['/activitate/camera-13', 'pagină activitate'],
];
$ptAnatomy = [
    ['Titlu + descriere clare', 'Ce este, unde este, pentru cine este.'],
    ['Date structurate', 'Breadcrumbs, FAQ, local entity, activitate.'],
    ['Întrebări practice', 'Program, acces, vârstă, durată, reguli, parcare.'],
    ['Internal linking', 'Orașe, categorii, activități similare, ghiduri.'],
];
$ptReach = [
    ['magnifying-glass', 'Pagina ta e făcută să fie găsită', 'Fiecare activitate și fiecare locație are pagina ei, optimizată pentru căutări, cu program, prețuri și disponibilitate.'],
    ['map-pin', 'Apari în oraș și în categorie', 'Ești în paginile de oraș, în categorii și în ghidurile editoriale, lângă activități căutate de aceiași oameni.'],
    ['gift', 'Carduri cadou și puncte', 'Cardurile cadou și punctele bonus aduc oameni înapoi, fără să construiești tu programul de fidelizare.'],
    ['star', 'Recenzii și recomandări', 'Recenziile clienților și recomandările automate îți trimit oameni noi către activitățile potrivite.'],
];
$ptAnalytics = [
    'Vezi exact ce activitate, slot și zi se vând cel mai bine',
    'Înțelegi de unde vin cumpărătorii și ce canal aduce profit',
    'Optimizezi prețurile și capacitatea pe baza cererii reale',
    'Urmărești conversia din vizită în vânzare, în timp real',
    'Identifici sloturile goale și le umpli cu promoții țintite',
    'Iei decizii pe date, nu pe presupuneri',
];
$ptPayments = [['credit-card', 'Card bancar'], ['phone', 'Apple Pay'], ['phone', 'Google Pay'], ['ticket', 'Carduri culturale'], ['lock-simple', 'Stripe']];
$ptFiscal = [
    ['file-text', 'Documente ANAF', 'Generare automată a documentelor necesare pentru ANAF.'],
    ['receipt', 'Facturi fiscale', 'Emiți facturi fiscale către clienți direct din platformă.'],
    ['check-circle', 'Contabilitate RO', 'Integrare cu sisteme de contabilitate din România.'],
    ['coins', 'Deconturi clare', 'Deconturi periodice sau la cerere, cu evidență transparentă.'],
];
$ptDocs = [['receipt', 'Factură fiscală', 'serie BO · client'], ['file-text', 'Document ANAF', 'raportare automată'], ['check-circle', 'Înregistrare contabilă', 'sync contabilitate RO']];
$ptSteps = [
    ['user-circle', 'Îți faci contul', 'Te înregistrezi în câteva minute. Fără taxe de pornire, fără abonament, fără card la înscriere.', '≈ 5 minute'],
    ['plus', 'Adaugi activitățile', 'Oricâte, de orice tip. Setezi sloturi orare, zile, capacități, variante de preț și pachete de grup.', 'activități nelimitate'],
    ['arrow-right', 'Mergi live', 'Publici și ești în piață, cu pagini gata de partajat și tracking conectat. Intri direct în baza de 96.000+ clienți.', 'go-live în max 1 zi'],
    ['coins', 'Vinzi & încasezi', 'Online și local, în același sistem. bilete.online încasează de la client și îți face deconturi periodice sau la cerere.', 'prețul tău, întreg'],
];
$ptOps = [
    ['Onboarding', 'Date locație, activități, bilete, politici.'],
    ['Publicare', 'Pagini SEO și activități disponibile online.'],
    ['Vânzare', 'Checkout, plăți, comisioane, bilete QR.'],
    ['Scanare', 'Validare rapidă la intrare, statusuri clare.'],
    ['Creștere', 'Rapoarte, recenzii, promoții, campanii.'],
];
$ptTixello = [['Evenimente & activități', '4.294'], ['Clienți în bază', '96.341'], ['Bilete vândute', '301.310'], ['Vânzări generate', '4.409.557 €'], ['Scanare offline', 'Da, cu sync']];
$ptFaqGroups = [
    ['Bani & plăți', [
        ['Cât e comisionul și cine îl plătește?', 'Comisionul este de 2%* și este adăugat în prețul final, plătit de cumpărător. Tu îți stabilești prețul și îl primești integral la decont. *Cei 2% se aplică pentru vânzarea exclusivă prin bilete.online. Dacă vinzi biletele și în alte părți, comisionul este de 4%: 2% incluse în preț și 2% adăugate. Nu ai abonament lunar și nu plătești instalare.'],
        ['Cum și când primesc banii?', 'bilete.online încasează plata de la client și îți face deconturi periodice — sau la cerere, ori de câte ori vrei să-ți fie decontați banii. În panou vezi soldul disponibil, ce e în procesare și cât ai încasat până acum, pe fiecare activitate, iar documentele și facturile rămân în cont.'],
        ['Ce metode de plată sunt acceptate?', 'Card bancar (Visa, Mastercard, Maestro), Apple Pay și Google Pay, procesate securizat prin Stripe, plus Card Cultural (Edenred, Sodexo, Up România) acolo unde este acceptat.'],
        ['Cum îmi reduce costul reclamelor?', 'Platforma se integrează cu toți pixelii de tracking și cu Facebook CAPI, trimițând 100% din evenimentele de conversie fără să fie blocate de ad blockere sau de iOS. Rezultatul: costul reclamelor pe Facebook, Instagram, TikTok și Google scade cu până la 60%.'],
        ['Mă ajută cu partea fiscală?', 'Da. Generare automată de documente ANAF, emitere facturi fiscale către clienți și integrare cu sisteme de contabilitate din România.'],
        ['Pot crea promoții sau coduri de reducere?', 'Da, platforma poate include coduri promoționale, campanii sezoniere, vouchere, puncte bonus și carduri cadou, în funcție de configurare.'],
    ]],
    ['Produs & operațiuni', [
        ['Ce tipuri de locații pot folosi platforma?', 'Platforma este potrivită pentru escape rooms, muzee, expoziții, parcuri de distracții, parcuri de aventură, peșteri, rezervații naturale, ateliere, tururi ghidate, ferme educative și alte experiențe care vând bilete sau rezervări.'],
        ['Pot vinde activități cu sloturi și pe zile?', 'Da. Clientul alege ziua din calendar, slotul orar, numărul de participanți și opțiunile. Tu controlezi capacitatea fiecărui slot și faci booking în detaliu.'],
        ['Pot avea mai multe activități în aceeași locație?', 'Da. O locație poate avea o pagină principală și mai multe pagini pentru activități, camere, tururi, pachete sau tipuri de acces.'],
        ['Pot vinde și la fața locului, nu doar online?', 'Da. Pe lângă dashboard-ul online, ai un panou de vânzări locale: POS-ul emite bilete pe loc (acces, servicii suplimentare și închirieri), ține coșul, încasează cash sau card, tipărește bonul și îți dă desfășurătorul casei și închiderea de casă la final de tură. Vânzările online și cele de la ghișeu ajung în același raport.'],
        ['Cum se validează biletele la intrare?', 'Fiecare bilet este emis cu un cod QR unic. Personalul îl scanează cu aplicația de scanare, pe telefon sau tabletă; dacă un cod nu se citește, îl poate tasta. Aplicația arată pe loc dacă biletul e valid, dacă a mai fost scanat sau dacă nu e recunoscut, cu vibrație și sunet, și funcționează și offline, cu sincronizare ulterioară.'],
        ['Ce se întâmplă după ce primesc o comandă?', 'Comanda apare în dashboard, biletele sunt emise automat, clientul primește confirmarea, iar tu poți vedea participanții și valida biletele la intrare.'],
        ['Mă ajută cu SEO?', 'Da. Platforma este gândită pentru pagini indexabile: locație, activități, orașe, categorii și pagini de intenție precum activități pentru copii, weekend, indoor sau outdoor.'],
    ]],
    ['Pornire & suport', [
        ['Cât durează să încep?', 'Onboarding în aproximativ 5 minute, fără costuri de pornire. Mergi live în maximum o zi, în funcție de câte activități adaugi. Dacă ne trimiți datele locației ca să-ți pregătim noi contul, activitățile și tipurile de bilete, ritmul depinde de cât de repede primim programul, prețurile și pozele.'],
        ['Ce hardware îmi trebuie?', 'Un telefon sau o tabletă pentru scanare și, dacă vrei bon tipărit, o imprimantă termică de 58 sau 80 mm. Bonul se tipărește direct din browser, fără drivere speciale. POS-ul ține loc de casă în aplicație.'],
        ['Cine îmi răspunde dacă apare o problemă?', 'Ai suport pe email și telefon, de luni până vineri, între 09:00 și 18:00, plus tichete de suport direct din panou. Pentru ziua unui eveniment mare, stabilim din timp cine e disponibil.'],
    ]],
];
$ptFaqs = array_merge(...array_map(fn ($g) => $g[1], $ptFaqGroups));
$ptRoles = ['Proprietar', 'Manager locație', 'Marketing', 'Operațiuni / ghișeu', 'Altul'];
$ptCounts = ['1 activitate', '2-5 activități', '6-15 activități', '15+ activități'];
// partner stories: real ones for everyone, stand-ins ('demo') only in preview
$ptPreview = !empty($_GET['preview']);
$ptStories = v2_partner_testimonials();
$ptShown = static fn (array $item): bool => empty($item['demo']) || $ptPreview;
$ptVideo = $ptShown($ptStories['video'] ?? ['demo' => true]) && preg_match('/^[A-Za-z0-9_-]{11}$/', (string) ($ptStories['video']['youtube'] ?? '')) ? $ptStories['video'] : null;
$ptQuotes = array_values(array_filter($ptStories['quotes'] ?? [], $ptShown));
$ptWall = array_values(array_filter($ptQuotes, fn ($q) => empty($q['pull'])));
$ptPull = array_values(array_filter($ptQuotes, fn ($q) => !empty($q['pull'])))[0] ?? null;
$ptWallMoves = count($ptWall) >= 5; // fewer quotes sit still in a grid
$ptInitials = static function (string $name): string {
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    return mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($parts, 0, 2))));
};
$ptDemoTag = static fn (array $item): string => !empty($item['demo']) ? '<span class="pt-demo-tag">Demo</span>' : '';

$ptChapters = [['pentru-cine', 'Pentru cine'], ['booking', 'Booking'], ['o-zi', 'Operațiuni'], ['ce-primesti', 'Ce primești'], ['vizibilitate', 'Vizibilitate'], ['bani', 'Bani'], ['cum', 'Cum începi'], ['intrebari', 'Întrebări']];
if ($ptVideo || $ptWall) {
    array_splice($ptChapters, 3, 0, [['povesti', 'Povești']]);
}

// A small QR-like pattern for the mock-ups: finder squares plus a fixed pseudo-random fill (not a real code), drawn as
// one path of horizontal runs so five of them stay light.
$ptQr = static function (string $seed, int $n = 21): string {
    $d = '';
    $finder = static fn (int $x, int $y): bool => ($x < 7 && $y < 7) || ($x >= $n - 7 && $y < 7) || ($x < 7 && $y >= $n - 7);
    for ($y = 0; $y < $n; $y++) {
        $run = 0;
        for ($x = 0; $x <= $n; $x++) {
            $on = false;
            if ($x < $n && $finder($x, $y)) {
                $fx = $x >= $n - 7 ? $x - ($n - 7) : $x;
                $fy = $y >= $n - 7 ? $y - ($n - 7) : $y;
                $on = ($fx === 0 || $fx === 6 || $fy === 0 || $fy === 6) || ($fx >= 2 && $fx <= 4 && $fy >= 2 && $fy <= 4);
            } elseif ($x < $n) {
                $on = (crc32($seed . ':' . $x . ':' . $y) & 3) === 0;
            }
            if ($on) {
                $run++;
            } elseif ($run) {
                $d .= 'M' . ($x - $run) . ' ' . $y . 'h' . $run . 'v1h-' . $run . 'z';
                $run = 0;
            }
        }
    }
    return '<svg viewBox="0 0 ' . $n . ' ' . $n . '" shape-rendering="crispEdges" aria-hidden="true" focusable="false"><path d="' . $d . '"/></svg>';
};
/** A row in the mock operator sidebar. */
$ptNav = static function (string $icon, string $label, bool $on = false): string {
    return '<span class="pt-ui-nav' . ($on ? ' is-on' : '') . '">' . v2_ic($icon) . '<b>' . v2_e($label) . '</b></span>';
};
/** A figure in the mock dashboard. */
$ptKpi = static function (string $label, string $value, string $delta = ''): string {
    return '<div class="pt-ui-kpi"><p>' . v2_e($label) . '</p><b>' . v2_e($value) . '</b>'
        . ($delta ? '<small>' . v2_ic('trend-up') . v2_e($delta) . '</small>' : '') . '</div>';
};
/** A section heading: kicker, title, optional lead. */
$ptHead = static function (string $id, string $kicker, string $title, string $lead = '', string $cls = ''): string {
    return '<div class="pt-head ' . $cls . '"><p class="pt-k">' . v2_e($kicker) . '</p><h2 id="' . $id . '">' . $title . '</h2>'
        . ($lead !== '' ? '<p class="pt-sub">' . $lead . '</p>' : '') . '</div>';
};

$pageTitleRaw = 'Parteneri ' . SITE_NAME . ' — vinde bilete la activitățile tale, comision 2%* plătit de client';
$pageDescription = 'Tot ce primește o locație pe bilete.online: booking pe sloturi, panou de operator, ghișeu cu bon, aplicație de scanare offline, SEO, analytics și tracking, deconturi și documente fiscale. Comision 2%* plătit de cumpărător, fără abonament.';
$canonicalUrl = SITE_URL . '/parteneri';
$ogImage = SITE_URL . '/assets/v2/img/hero-1440.webp';
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => 'bilete.online pentru parteneri — ticketing & booking pentru activități',
        'serviceType' => 'Platformă de vânzare bilete online și la fața locului pentru activități și locații',
        'description' => 'Booking pe sloturi orare, panou de operator, POS pentru ghișeu, aplicație de scanare offline, pagini SEO, analytics și tracking server-side, deconturi periodice și documente fiscale. Comision 2% plătit de cumpărător.',
        'provider' => ['@type' => 'Organization', 'name' => SITE_NAME, 'url' => SITE_URL . '/'],
        'areaServed' => ['@type' => 'Country', 'name' => 'România'],
        'audience' => ['@type' => 'BusinessAudience', 'audienceType' => 'Locații de agrement, muzee, escape rooms, parcuri, ateliere, operatori de tururi și experiențe'],
        'offers' => ['@type' => 'Offer', 'priceCurrency' => 'RON', 'price' => '0', 'description' => '0 lei cost de pornire. Comision 2%* plătit de cumpărător la fiecare bilet vândut.'],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $ptFaqs),
    ],
];

$v2Styles = ['partners.css'];
$v2Scripts = ['partners.js', 'for-venues.js'];
$v2HeaderOverlay = true;
$v2ClientData = [
    'profiles' => $ptProfiles,
    'aliases' => V2_PARTNER_ALIASES,
    'supportEmail' => SUPPORT_EMAIL,
    'demoName' => 'Andrei Popescu',
    'demoGift' => 'La mulți ani! Distracție plăcută!',
    'libs' => [v2_asset('vendor/gsap-3.15.0.min.js'), v2_asset('vendor/ScrollTrigger-3.15.0.min.js'), v2_asset('vendor/lenis-1.3.26.min.js')],
];
// head.php strips utm_* from the address bar after load; the funnel pings and the demo lead read them from here
$v2HeadExtra = '<script>(function(){try{var q=new URLSearchParams(location.search),u={};["utm_source","utm_medium","utm_campaign","utm_content","utm_term"].forEach(function(k){if(q.get(k))u[k]=q.get(k).slice(0,150)});window.BO_UTM=u;}catch(e){}})();</script>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ===================== HERO: the stage ===================== -->
  <section class="pt-hero" id="pt-hero" aria-labelledby="pt-h">
    <div class="pt-bg" aria-hidden="true">
      <svg class="deco-arches" viewBox="0 0 400 400" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    </div>
    <div class="pt-in">
      <div class="pt-copy" id="pt-copy">
        <p class="pt-hello pt-rv" id="pt-hello" style="--d:0ms" hidden></p>
        <p class="pt-chip pt-rv" style="--d:40ms"><span class="pt-live" aria-hidden="true"></span><span id="pt-chip-t">Ticketing &amp; booking pentru activități</span></p>
        <h1 class="pt-h" id="pt-h">
          <span class="pt-line" style="--d:120ms"><span class="pt-line-in" id="pt-h1a">Vinzi bilete</span></span>
          <span class="pt-line is-soft" style="--d:220ms"><span class="pt-line-in" id="pt-h1b">la activitățile tale.</span></span>
          <span class="pt-line is-mark" style="--d:320ms"><span class="pt-line-in"><span id="pt-h1c">Prețul tău rămâne al tău.</span></span></span>
        </h1>
        <p class="pt-lead pt-rv" id="pt-sub" style="--d:460ms">Booking pe sloturi orare și calendar, panou de operator, ghișeu cu bon, aplicație de scanare offline, analytics și tracking care îți reduce costul reclamelor. Comisionul de <?= $ptAccent ?> e plătit de cumpărător — tu îți păstrezi prețul stabilit.</p>
        <div class="pt-cta pt-rv" style="--d:560ms">
          <a class="btn btn-light pt-go" href="/inregistrare-locatie" data-signup data-track-cta="parteneri_hero_signup"><span id="pt-cta-t">Începe gratuit</span><?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="#demo" data-track-cta="parteneri_hero_demo"><?= v2_ic('calendar-blank') ?>Cere un demo</a>
        </div>
        <ul class="pt-ticks pt-rv" style="--d:660ms">
          <li><?= v2_ic('check') ?>0 lei cost de pornire</li>
          <li><?= v2_ic('check') ?>Fără abonament</li>
          <li><?= v2_ic('check') ?>Onboarding în ≈5 minute</li>
          <li><?= v2_ic('check') ?>Live în maximum o zi</li>
        </ul>
      </div>

      <div class="pt-stage" id="pt-stage" aria-hidden="true">
        <div class="pt-scene" id="pt-scene">
          <!-- dashboard -->
          <div class="pt-dash pt-part" style="--d:380ms">
            <div class="pt-win"><i></i><i></i><i></i><span>bilete.online · Panou operator</span></div>
            <div class="pt-dash-body">
              <div class="pt-dash-nav"><b class="is-on"></b><b></b><b></b><b></b><b></b></div>
              <div class="pt-dash-main">
                <div class="pt-kpis">
                  <div><small>Vânzări azi</small><b><span id="pt-kpi-sales">12.480</span> lei</b><em><?= v2_ic('trend-up') ?>18%</em></div>
                  <div><small>Bilete emise</small><b id="pt-kpi-tickets">286</b><em><?= v2_ic('trend-up') ?>42</em></div>
                  <div><small>Ocupare sloturi</small><b>84%</b><em class="is-soft">azi</em></div>
                </div>
                <div class="pt-chart">
                  <svg viewBox="0 0 300 92" preserveAspectRatio="none" focusable="false">
                    <defs><linearGradient id="pt-area-g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2BB673" stop-opacity=".35"/><stop offset="1" stop-color="#2BB673" stop-opacity="0"/></linearGradient></defs>
                    <path class="pt-area" d="M0 78 C 30 70, 45 60, 70 62 S 115 40, 140 46 S 185 22, 210 30 S 255 12, 300 8 L300 92 L0 92 Z"/>
                    <path class="pt-curve" pathLength="1" d="M0 78 C 30 70, 45 60, 70 62 S 115 40, 140 46 S 185 22, 210 30 S 255 12, 300 8"/>
                  </svg>
                  <span class="pt-chart-tag">Ultimele 7 zile</span>
                </div>
                <ul class="pt-orders" id="pt-orders">
                  <li><i class="is-green"></i><b>Escape room · Camera 2</b><small>4 bilete · 18:00</small><em>180 lei</em></li>
                  <li><i class="is-yellow"></i><b>Muzeu · Acces general</b><small>2 bilete · 11:30</small><em>60 lei</em></li>
                  <li><i class="is-red"></i><b>Tur ghidat · Centrul vechi</b><small>6 bilete · 16:00</small><em>210 lei</em></li>
                </ul>
              </div>
            </div>
          </div>

          <!-- ticket office -->
          <div class="pt-pos pt-part" style="--d:620ms">
            <div class="pt-printer"><span>Ghișeu 1</span><i></i></div>
            <div class="pt-feed"><div class="pt-receipt" id="pt-receipt">
              <p class="pt-r-brand">bilete.online</p>
              <p class="pt-r-meta">Bon nr. 0147 · 14:32</p>
              <ul><li><span>2 × Adult</span><b>90,00</b></li><li><span>1 × Copil</span><b>25,00</b></li></ul>
              <p class="pt-r-total"><span>Total</span><b>115,00 lei</b></p>
              <p class="pt-r-pay">Card · aprobat</p>
              <span class="pt-r-qr"><?= $ptQr('receipt', 21) ?></span>
            </div></div>
          </div>

          <!-- scanning phone -->
          <div class="pt-phone pt-part" style="--d:820ms">
            <div class="pt-phone-in">
              <p class="pt-ph-h">Scanare · Intrarea 1</p>
              <div class="pt-view"><span class="pt-qr"><?= $ptQr('ticket', 21) ?></span><i class="pt-scanline"></i><i class="pt-corners"></i></div>
              <p class="pt-result" id="pt-result"><?= v2_ic('check-circle') ?><span id="pt-result-t">Bilet valid</span></p>
              <p class="pt-count"><b id="pt-in">128</b> / 150 au intrat</p>
            </div>
          </div>

          <!-- live events -->
          <p class="pt-toast is-a" id="pt-toast-a"><span class="pt-t-ic"><?= v2_ic('ticket') ?></span><span><b>Rezervare nouă</b><small>Slot 18:00 · 4 persoane</small></span></p>
          <p class="pt-toast is-b" id="pt-toast-b"><span class="pt-t-ic is-yellow"><?= v2_ic('coins') ?></span><span><b>Decont cerut</b><small>4.320 lei · în procesare</small></span></p>
          <span class="pt-mock">Machetă ilustrativă</span>
        </div>
      </div>
    </div>
    <a class="pt-cue" href="#cifre"><span>Derulează</span><i aria-hidden="true"></i></a>
    <svg class="pt-hero-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== NUMBERS ===================== -->
  <section class="pt-stats" id="cifre" aria-labelledby="pt-stats-h">
    <div class="wrap">
      <p class="pt-stats-cap" id="pt-stats-h">Rezultate reale în ecosistemul Tixello — pe care e construit bilete.online</p>
      <ul class="pt-stats-grid">
        <?php foreach ($ptStats as [$statValue, $statLabel, $statSuffix]): ?>
        <li><b><span class="sr"><?= number_format($statValue, 0, ',', '.') . v2_e($statSuffix) ?></span><span aria-hidden="true"><span data-count="<?= $statValue ?>"><?= number_format($statValue, 0, ',', '.') ?></span><?= v2_e($statSuffix) ?></span></b><span class="pt-stat-l"><?= v2_e($statLabel) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php if ($ptCategories): ?>
    <!-- live categories (the links are in "Pentru cine") -->
    <div class="pt-marquee" aria-hidden="true">
      <div class="pt-marquee-track">
        <?php for ($pass = 0; $pass < 2; $pass++): ?>
        <div class="pt-marquee-set"><?php foreach ($ptCategories as $cat): ?><span><?= v2_e($cat['name']) ?></span><?php endforeach; ?></div>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <!-- ===================== CHAPTERS ===================== -->
  <nav class="pt-nav" id="pt-nav" aria-label="Capitolele paginii">
    <div class="pt-nav-in">
      <ul class="pt-nav-list">
        <?php foreach ($ptChapters as [$chapterId, $chapterLabel]): ?>
        <li><a href="#<?= $chapterId ?>" data-chapter-link="<?= $chapterId ?>"><?= v2_e($chapterLabel) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="pt-nav-cta">
        <a class="pt-nav-demo" href="#demo" data-track-cta="parteneri_nav_demo">Cere demo</a>
        <a class="btn btn-primary" href="/inregistrare-locatie" data-signup data-track-cta="parteneri_nav_signup">Începe gratuit<?= v2_ic('arrow-right') ?></a>
      </div>
    </div>
  </nav>

  <!-- ===================== WHO ===================== -->
  <section class="pt-sec pt-who" id="pentru-cine" data-chapter="pentru-cine" aria-labelledby="pt-who-h">
    <div class="wrap">
      <div class="pt-split">
        <?= $ptHead('pt-who-h', 'Pentru cine', 'Nu vinzi doar bilete. Vinzi o experiență care trebuie descoperită.', 'Platforma este construită pentru activități diferite, cu modele diferite de acces: sloturi orare, bilete simple, pachete, tururi ghidate, acces pe zi, grupuri sau evenimente private.') ?>
        <ul class="pt-who-grid" data-reveal>
          <?php foreach ($ptWho as [$whoIcon, $whoTitle, $whoText]): ?>
          <li class="pt-who-card"><span class="pt-ic"><?= v2_ic($whoIcon) ?></span><h3><?= v2_e($whoTitle) ?></h3><p><?= v2_e($whoText) ?></p></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php if ($ptCategories): ?>
      <div class="pt-cats">
        <h3 class="pt-cats-h">Categoriile disponibile pe bilete.online</h3>
        <ul class="pt-cat-grid" id="grid-categorii" data-reveal>
          <?php foreach ($ptCategories as $cat): ?>
          <li><a class="pt-cat" href="<?= v2_e($cat['href'] ?? '/' . ($cat['slug'] ?? '')) ?>" data-cat-slug="<?= v2_e($cat['slug'] ?? '') ?>">
            <?php if (!empty($cat['image'])): ?>
            <img src="<?= v2_e($cat['thumb'] ?: $cat['image']) ?>"<?= !empty($cat['srcset']) ? ' srcset="' . v2_e($cat['srcset']) . '" sizes="(min-width:1024px) 16vw, (min-width:640px) 33vw, 50vw"' : '' ?> alt="" width="320" height="200" loading="lazy" decoding="async">
            <?php else: ?>
            <span class="pt-cat-ph"><?= v2_ic('ticket') ?></span>
            <?php endif; ?>
            <span class="pt-cat-body"><b><?= v2_e($cat['name']) ?></b><?php if (!empty($cat['desc'])): ?><small><?= v2_e($cat['desc']) ?></small><?php endif; ?></span>
          </a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===================== PROBLEM → FIX ===================== -->
  <section class="pt-sec pt-problem" id="problema" data-chapter="pentru-cine" aria-labelledby="pt-problem-h">
    <div class="wrap">
      <?= $ptHead('pt-problem-h', 'Realitatea de azi', 'Vinzi activități, dar instrumentele te trag înapoi.', 'Comisioane mari scăzute din marja ta. Booking rigid care nu suportă sloturi sau zile. Tracking ciuntit de ad blockere și iOS, care îți umflă costul reclamelor. Și zero ajutor real ca să găsești clienți noi.', 'is-center') ?>
      <ul class="pt-flip-grid" id="pt-flips">
        <?php foreach ($ptProblems as $pi => [$probIcon, $probTitle, $probText, $fixTitle, $fixText]): ?>
        <li class="pt-flip" style="--i:<?= $pi ?>">
          <div class="pt-flip-face is-pain">
            <span class="pt-ic is-red"><?= v2_ic($probIcon) ?></span>
            <h3><span><?= v2_e($probTitle) ?></span></h3>
            <p><?= v2_e($probText) ?></p>
          </div>
          <div class="pt-flip-face is-fix">
            <span class="pt-ic is-green"><?= v2_ic('check') ?></span>
            <p class="pt-fix-k">Cu bilete.online</p>
            <h3><?= v2_e($fixTitle) ?></h3>
            <p><?= v2_e($fixText) ?></p>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
      <div class="pt-center"><a class="btn btn-primary" href="/inregistrare-locatie" data-signup data-track-cta="parteneri_problem_solve">Rezolvă-le pe toate cu bilete.online<?= v2_ic('arrow-right') ?></a></div>
    </div>
  </section>

  <!-- ===================== BOOKING (scene) ===================== -->
  <section class="pt-scene-sec pt-booking" id="booking" data-chapter="booking" aria-labelledby="pt-booking-h">
    <div class="pt-scene-in wrap">
      <div class="pt-booking-copy">
        <p class="pt-k is-dark">Booking gândit pentru activități</p>
        <h2 id="pt-booking-h">Sloturi orare. Zile pe calendar. Rezervare în detaliu.</h2>
        <p class="pt-sub is-dark">bilete.online nu vinde doar „un bilet”. Clientul alege ziua din calendar, slotul orar, numărul de participanți și opțiunile — exact cum funcționează un escape room, un tur ghidat sau un atelier. Tu controlezi capacitatea fiecărui slot.</p>
        <ul class="pt-list is-dark">
          <?php foreach ($ptBookingList as $bookingItem): ?><li><?= v2_ic('check') ?><?= v2_e($bookingItem) ?></li><?php endforeach; ?>
        </ul>
        <a class="btn btn-light pt-mt" href="/inregistrare-locatie" data-signup data-track-cta="parteneri_booking_slots">Vreau booking pe sloturi<?= v2_ic('arrow-right') ?></a>
      </div>

      <!-- animated demo; the text beside it says the same thing, so screen readers skip it -->
      <div class="pt-bk" id="pt-bk" aria-hidden="true">
        <ol class="pt-bk-rail"><?php foreach ($ptBookingSteps as $si => $stepName): ?><li data-rail="<?= $si ?>"<?= $si === 5 ? ' class="is-on"' : ' class="is-past"' ?>><i><?= $si + 1 ?></i><span><?= v2_e($stepName) ?></span></li><?php endforeach; ?></ol>
        <div class="pt-bk-card">
          <div class="pt-bk-head"><b id="pt-bk-title">Gata!</b><span id="pt-bk-count">6/6</span></div>
          <div class="pt-bk-bar"><i id="pt-bk-progress" style="width:100%"></i></div>
          <div class="pt-bk-body">
            <div class="pt-bk-step" data-step="0" hidden>
              <small>Sâmbătă, 19 octombrie</small>
              <div class="pt-bk-days"><?php foreach ([['Joi', 17], ['Vin', 18], ['Sâm', 19], ['Dum', 20], ['Lun', 21]] as $di => [$dayName, $dayNum]): ?><span class="<?= $di === 2 ? 'is-on' : ($di === 4 ? 'is-gone' : '') ?>"><small><?= $dayName ?></small><b><?= $dayNum ?></b></span><?php endforeach; ?></div>
              <small>Sloturi disponibile</small>
              <div class="pt-bk-slots"><?php foreach (['10:00', '12:00', '14:00', '16:00', '18:00', '20:00'] as $slotIndex => $slotTime): ?><span class="pt-bk-slot<?= $slotIndex === 1 ? ' is-gone' : '' ?>"><?= $slotTime ?><em><?= [6, 0, 4, 2, 8, 5][$slotIndex] ?> locuri</em></span><?php endforeach; ?></div>
            </div>
            <div class="pt-bk-step" data-step="1" hidden>
              <small>Alege tipul de bilet</small>
              <?php foreach ([['Acces standard', '1 persoană', '80 lei'], ['Acces + experiență', '1 persoană', '120 lei'], ['Pachet familie', '2 adulți + 2 copii', '260 lei']] as [$tkName, $tkNote, $tkPrice]): ?>
              <div class="pt-bk-row pt-bk-tk"><div><b><?= v2_e($tkName) ?></b><small><?= v2_e($tkNote) ?></small></div><span><?= v2_e($tkPrice) ?><i class="pt-bk-radio"></i></span></div>
              <?php endforeach; ?>
            </div>
            <div class="pt-bk-step" data-step="2" hidden>
              <small>Adaugă extra &amp; rentals</small>
              <?php foreach ([['map-pin', 'Echipament (rental)', '35 lei'], ['star', 'Ghid foto', '25 lei'], ['gift', 'Pachet gustare', '18 lei']] as [$exIcon, $exName, $exPrice]): ?>
              <div class="pt-bk-row pt-bk-extra"><div class="pt-bk-exname"><?= v2_ic($exIcon) ?><b><?= v2_e($exName) ?></b></div><span><?= v2_e($exPrice) ?><i class="pt-bk-plus"></i></span></div>
              <?php endforeach; ?>
            </div>
            <div class="pt-bk-step" data-step="3" hidden>
              <small>Personalizează biletul</small>
              <p class="pt-bk-lbl">Nume pe bilet</p>
              <div class="pt-bk-input"><span id="pt-bk-typed">Andrei Popescu</span><i class="pt-bk-caret"></i></div>
              <p class="pt-bk-lbl">Mesaj cadou (opțional)</p>
              <div class="pt-bk-textarea" id="pt-bk-gift">La mulți ani! Distracție plăcută!</div>
              <p class="pt-bk-ok"><?= v2_ic('check') ?>Trimite biletul pe email &amp; WhatsApp</p>
            </div>
            <div class="pt-bk-step" data-step="4" hidden>
              <small>Sumar comandă</small>
              <div class="pt-bk-sum"><p><span>Acces + experiență</span><span>120 lei</span></p><p><span>Echipament (rental)</span><span>35 lei</span></p><p><span>Ghid foto</span><span>25 lei</span></p><p class="is-total"><span>Total estimat</span><span>180 lei</span></p></div>
              <div class="pt-bk-pay"><span class="is-on">Stripe</span><span>Apple Pay</span><span>Google Pay</span><span>Card Cultural</span></div>
              <div class="pt-bk-bar is-pay"><i id="pt-bk-pay" style="width:100%"></i></div>
              <p class="pt-bk-pay-t" id="pt-bk-pay-t">Plată confirmată</p>
            </div>
            <div class="pt-bk-step is-done" data-step="5">
              <span class="pt-bk-done"><?= v2_ic('check') ?></span>
              <b>Comandă confirmată!</b>
              <p>Bilet MKT-19024 · 14:00</p>
              <ul class="pt-bk-msgs">
                <?php foreach ([['envelope-simple', 'Biletul a fost trimis pe email'], ['phone', 'Confirmare trimisă pe WhatsApp'], ['ticket', 'Bilet QR valabil — îl scanezi la intrare'], ['star', 'Recomandare: „Tur foto la apus” pentru tine']] as [$msgIcon, $msgText]): ?>
                <li class="pt-bk-msg is-on"><?= v2_ic($msgIcon) ?><?= v2_e($msgText) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
          <p class="pt-bk-foot">Demo booking bilete.online · machetă ilustrativă</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== A DAY AT THE VENUE (scene) ===================== -->
  <section class="pt-day" id="o-zi" data-chapter="o-zi" aria-labelledby="pt-day-h">
    <div class="pt-day-sky" aria-hidden="true"><i class="pt-day-dusk"></i><i class="pt-day-sun"></i></div>
    <div class="pt-day-in">
      <div class="wrap pt-day-head">
        <?= $ptHead('pt-day-h', 'O zi la locația ta', 'De la prima vânzare la închiderea casei', 'Aceleași bilete, aceleași rapoarte, indiferent dacă omul a cumpărat de acasă sau de la ghișeu.') ?>
        <p class="pt-day-clock" aria-hidden="true"><?= v2_ic('clock') ?><b id="pt-day-time">08:40</b></p>
      </div>
      <div class="pt-day-viewport">
        <ol class="pt-day-track" id="pt-day-track">
          <?php foreach ($ptDay as $di => [$dayIcon, $dayTime, $dayTitle, $dayText]): ?>
          <li class="pt-day-card" data-time="<?= $dayTime ?>" style="--i:<?= $di ?>">
            <span class="pt-day-ic"><?= v2_ic($dayIcon) ?></span>
            <p class="pt-day-t"><?= $dayTime ?></p>
            <h3><?= v2_e($dayTitle) ?></h3>
            <p><?= v2_e($dayText) ?></p>
          </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </div>
  </section>

  <!-- ===================== OPERATOR PANEL ===================== -->
  <section class="pt-sec pt-panel" id="panou" data-chapter="o-zi" aria-labelledby="pt-panel-h">
    <div class="wrap">
      <?= $ptHead('pt-panel-h', 'Panoul de operator', 'Vinzi online și la ghișeu. <span class="pt-nl">Dintr-un singur cont.</span>', 'Fiecare ecran are un singur scop și se citește dintr-o privire: panoul de operator, POS-ul pentru vânzarea pe loc, aplicația de scanare pe telefon sau tabletă și bonul pe imprimantă termică. Tot ce s-a vândut, oriunde s-a vândut, ajunge în același raport. Alege o secțiune și vezi cum arată.', 'is-center') ?>
      <div class="pt-tabs" role="tablist" aria-label="Secțiuni din panou" data-tabs>
        <?php foreach ([['panou', 'squares-four', 'Panou'], ['vanzari', 'shopping-cart-simple', 'Vânzări'], ['participanti', 'users-three', 'Participanți'], ['sold', 'wallet', 'Sold'], ['marketing', 'megaphone', 'Marketing']] as $i => [$key, $icon, $label]): ?>
        <button class="pt-tab" type="button" role="tab" id="ptt-<?= $key ?>" aria-controls="ptp-<?= $key ?>" aria-selected="<?= $i ? 'false' : 'true' ?>" tabindex="<?= $i ? -1 : 0 ?>"><?= v2_ic($icon) ?><?= v2_e($label) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="pt-screen" id="pt-screen">
        <div class="pt-ui">
          <div class="pt-ui-top"><span class="pt-ui-dots" aria-hidden="true"><i></i><i></i><i></i></span><span class="pt-ui-url" id="pt-url">bilete.online/organizator/panou</span></div>
          <div class="pt-ui-body">
            <div class="pt-ui-side" aria-hidden="true">
              <?= $ptNav('squares-four', 'Panou', true) ?>
              <?= $ptNav('calendar-blank', 'Activități') ?>
              <?= $ptNav('users-three', 'Participanți') ?>
              <?= $ptNav('shopping-cart-simple', 'Vânzări') ?>
              <?= $ptNav('wallet', 'Sold') ?>
              <?= $ptNav('file-text', 'Documente') ?>
              <?= $ptNav('tag', 'Coduri promo') ?>
              <?= $ptNav('code', 'Widget-uri') ?>
              <?= $ptNav('receipt', 'Facturare') ?>
            </div>
            <div class="pt-ui-main">
              <div class="pt-p" id="ptp-panou" role="tabpanel" aria-labelledby="ptt-panou" data-url="bilete.online/organizator/panou" tabindex="0">
                <p class="pt-ui-h">Indicatorii lunii</p>
                <div class="pt-ui-kpis is-four">
                  <?= $ptKpi('Venituri luna aceasta', '18.240 lei', '+12%') ?>
                  <?= $ptKpi('Bilete vândute luna aceasta', '412', '+8%') ?>
                  <?= $ptKpi('Activități în derulare', '6') ?>
                  <?= $ptKpi('Rată de conversie', '4,8%', '+0,6 p.p.') ?>
                </div>
                <p class="pt-ui-h2">Vânzări bilete</p>
                <div class="pt-ui-chart" aria-hidden="true">
                  <?php foreach ([32, 46, 38, 60, 52, 74, 66, 81, 58, 69, 77, 92] as $h): ?><i style="--h:<?= $h ?>%"></i><?php endforeach; ?>
                </div>
                <div class="pt-ui-quick">
                  <span><?= v2_ic('plus') ?>Activitate nouă</span>
                  <span><?= v2_ic('tag') ?>Cod promoțional</span>
                  <span><?= v2_ic('scan') ?>Scanare la intrare</span>
                </div>
              </div>
              <div class="pt-p" id="ptp-vanzari" role="tabpanel" aria-labelledby="ptt-vanzari" data-url="bilete.online/organizator/vanzari" tabindex="0" hidden>
                <p class="pt-ui-h">Vânzări</p>
                <div class="pt-ui-chips"><span class="is-on">Toate canalele</span><span>Online</span><span>Ghișeu</span><span>Luna aceasta</span></div>
                <div class="pt-ui-scroll">
                  <table class="pt-ui-table">
                    <thead><tr><th>Comandă</th><th>Activitate</th><th>Canal</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                      <?php foreach ([['BO-24817', 'Tur ghidat · 11:00', 'Online', '160 lei', 'Plătită', 'is-ok'], ['BO-24816', 'Intrare adult', 'Ghișeu', '45 lei', 'Cash', 'is-info'], ['BO-24815', 'Atelier copii · sâmbătă', 'Online', '220 lei', 'Plătită', 'is-ok'], ['BO-24814', 'Intrare familie', 'Ghișeu', '120 lei', 'Card', 'is-info']] as [$no, $act, $chan, $total, $status, $tone]): ?>
                      <tr><td><b><?= $no ?></b></td><td><?= v2_e($act) ?></td><td><?= $chan ?></td><td class="pt-right"><?= $total ?></td><td><span class="pt-pill <?= $tone ?>"><?= $status ?></span></td></tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="pt-p" id="ptp-participanti" role="tabpanel" aria-labelledby="ptt-participanti" data-url="bilete.online/organizator/participanti" tabindex="0" hidden>
                <p class="pt-ui-h">Participanți</p>
                <div class="pt-ui-search"><?= v2_ic('magnifying-glass') ?><span>Caută după nume, email sau cod bilet</span></div>
                <ul class="pt-ui-list">
                  <?php foreach ([['AM', 'Andrei M.', 'Tur ghidat · 11:00', 'Intrat 10:58', 'is-ok'], ['IR', 'Ioana R.', 'Atelier copii · 12:30', 'Așteptat', ''], ['DP', 'Dan P.', 'Intrare adult', 'Intrat 10:41', 'is-ok'], ['MS', 'Maria S.', 'Intrare familie · 4 pers.', 'Așteptat', '']] as [$ini, $name, $act, $state, $tone]): ?>
                  <li><span class="pt-ui-av"><?= $ini ?></span><span class="pt-ui-t"><b><?= v2_e($name) ?></b><small><?= v2_e($act) ?></small></span><span class="pt-pill <?= $tone ?>"><?= $state ?></span></li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <div class="pt-p" id="ptp-sold" role="tabpanel" aria-labelledby="ptt-sold" data-url="bilete.online/organizator/sold" tabindex="0" hidden>
                <p class="pt-ui-h">Sold</p>
                <div class="pt-ui-kpis">
                  <?= $ptKpi('Sold disponibil', '9.420 lei') ?>
                  <?= $ptKpi('În procesare', '1.180 lei') ?>
                  <?= $ptKpi('Total încasat', '64.700 lei') ?>
                </div>
                <div class="pt-ui-payout"><span><?= v2_ic('bank') ?>Plata se face în contul locației</span><span class="pt-ui-btn">Solicită plata</span></div>
                <ul class="pt-ui-mini">
                  <li><span>Tur ghidat</span><b>3.900 lei</b></li>
                  <li><span>Atelier copii</span><b>2.640 lei</b></li>
                  <li><span>Intrare zilnică</span><b>2.880 lei</b></li>
                </ul>
              </div>
              <div class="pt-p" id="ptp-marketing" role="tabpanel" aria-labelledby="ptt-marketing" data-url="bilete.online/organizator/promo" tabindex="0" hidden>
                <p class="pt-ui-h">Marketing</p>
                <ul class="pt-ui-list">
                  <li><span class="pt-ui-av is-tag"><?= v2_ic('tag') ?></span><span class="pt-ui-t"><b>TOAMNA10</b><small>-10% · 214 utilizări</small></span><span class="pt-pill is-ok">Activ</span></li>
                  <li><span class="pt-ui-av is-tag"><?= v2_ic('percent') ?></span><span class="pt-ui-t"><b>GRUP20</b><small>-20% de la 10 bilete · 38 utilizări</small></span><span class="pt-pill is-ok">Activ</span></li>
                </ul>
                <p class="pt-ui-h2">Widget pentru site-ul tău</p>
                <pre class="pt-ui-code">&lt;script src="bilete.online/widget.js"
  data-locatie="muzeul-tau"&gt;&lt;/script&gt;</pre>
              </div>
            </div>
          </div>
        </div>
        <p class="pt-note"><?= v2_ic('info') ?>Machetă ilustrativă a panoului, cu date de exemplu.</p>
      </div>
    </div>
  </section>

  <!-- ===================== TICKET OFFICE ===================== -->
  <section class="pt-sec pt-dark pt-pos-sec" id="ghiseu" data-chapter="o-zi" aria-labelledby="pt-pos-h">
    <div class="wrap pt-two">
      <div class="pt-two-copy">
        <p class="pt-k is-dark"><?= v2_ic('printer') ?>La fața locului</p>
        <h2 id="pt-pos-h">Ghișeul tău, cu bon și închidere de casă</h2>
        <p class="pt-sub is-dark">Pe lângă dashboard-ul de comenzi online, ai un <strong class="pt-yellow">panou de gestiune a vânzărilor la fața locului</strong>. POS-ul emite biletul pe loc și ține loc de casă în aplicație: coș, încasare cash sau card, bon tipărit pe imprimantă termică de 58 sau 80 mm, direct din browser, fără drivere instalate. Vinzi acces, servicii suplimentare sau închirieri (rentals).</p>
        <ul class="pt-checks is-dark">
          <?php foreach ($ptPosChecks as $t): ?><li><?= v2_ic('check-circle') ?><?= v2_e($t) ?></li><?php endforeach; ?>
        </ul>
        <p class="pt-hint is-dark"><?= v2_ic('info') ?>Modulul de vânzare la fața locului se activează pe contul locației.</p>
        <a class="btn btn-light pt-mt" href="/inregistrare-locatie" data-signup data-track-cta="parteneri_local_sales">Vreau să vând online și local<?= v2_ic('arrow-right') ?></a>
      </div>

      <div class="pt-till" aria-label="POS-ul de la ghișeu, machetă cu date de exemplu" role="group">
        <div class="pt-tb">
          <div class="pt-tb-head"><b><?= v2_ic('ticket') ?>InfoPoint — Emite bilete</b><span class="pt-tb-open">Casă deschisă</span></div>
          <div class="pt-tb-body">
            <div class="pt-tb-items">
              <?php foreach ($ptPosItems as [$itemName, $itemPrice]): ?>
              <button class="pt-tb-item" type="button" data-pos-add data-name="<?= v2_e($itemName) ?>" data-price="<?= (int) $itemPrice ?>"><b><?= v2_e($itemName) ?></b><small><?= (int) $itemPrice ?> lei</small></button>
              <?php endforeach; ?>
            </div>
            <div class="pt-tb-cart">
              <p class="pt-tb-k">Coș</p>
              <ul id="pt-cart" class="pt-tb-lines" aria-live="polite"><li class="pt-tb-empty">Atinge un bilet ca să îl adaugi</li></ul>
              <p class="pt-tb-total"><span>Total</span><b id="pt-total">0 lei</b></p>
              <div class="pt-tb-pay">
                <button class="pt-tb-btn is-cash" type="button" data-pos-pay="cash" disabled><?= v2_ic('coins') ?>Cash</button>
                <button class="pt-tb-btn is-card" type="button" data-pos-pay="card" disabled><?= v2_ic('credit-card') ?>Card</button>
              </div>
              <p class="pt-tb-print" id="pt-print" role="status"><?= v2_ic('printer') ?><span id="pt-print-t">Bon pe imprimanta termică, după fiecare comandă</span></p>
            </div>
          </div>
        </div>
        <div class="pt-slip" id="pt-slip" aria-hidden="true">
          <p class="pt-slip-h">MUZEUL TĂU</p>
          <p class="pt-slip-sub">Bon fără valoare fiscală · exemplu</p>
          <ul id="pt-slip-lines"></ul>
          <p class="pt-slip-total"><span>TOTAL</span><b id="pt-slip-total">0 lei</b></p>
          <span class="pt-slip-qr"><?= $ptQr('slip', 21) ?></span>
        </div>
        <p class="pt-note is-dark"><?= v2_ic('info') ?>Machetă ilustrativă: încearcă, nu se vinde nimic.</p>
      </div>
    </div>
  </section>

  <!-- ===================== SCANNING ===================== -->
  <section class="pt-sec pt-scan" id="scanare" data-chapter="o-zi" aria-labelledby="pt-scan-h">
    <div class="wrap pt-two is-rev">
      <div class="pt-two-copy">
        <p class="pt-k"><?= v2_ic('scan') ?>La intrare</p>
        <h2 id="pt-scan-h">Aplicația de scanare, pe telefon și pe tabletă — Android &amp; iOS</h2>
        <p class="pt-sub">Se instalează pe ecranul telefonului, ca orice aplicație. Scanează cu camera, iar dacă un cod nu se citește, îl tastezi. Răspunsul vine pe loc, cu sunet și vibrație, ca omul de la poartă să nu stea cu ochii pe ecran. Scanezi rapid <strong>inclusiv offline</strong>, cu sincronizare ulterioară, și vezi <strong>live vânzările și traficul</strong>, oriunde te-ai afla.</p>
        <ul class="pt-checks">
          <?php foreach ($ptScanChecks as $t): ?><li><?= v2_ic('check-circle') ?><?= v2_e($t) ?></li><?php endforeach; ?>
        </ul>
        <p class="pt-hint"><?= v2_ic('info') ?>Aplicația de scanare se activează pe contul locației.</p>
      </div>
      <div class="pt-scanwrap" aria-hidden="true">
        <div class="pt-bigphone" id="pt-scanner">
          <div class="pt-bp-top"><span></span></div>
          <div class="pt-bp-screen">
            <p class="pt-bp-bar"><span>Scanare · Poarta 1</span><em class="pt-net" id="pt-net"><i></i><span id="pt-net-t">Online</span></em></p>
            <div class="pt-bp-frame"><span class="pt-qr"><?= $ptQr('gate', 21) ?></span><i class="pt-scanline"></i><i class="pt-corners"></i></div>
            <p class="pt-bp-state is-ok" id="pt-state"><?= v2_ic('check-circle') ?><span id="pt-state-t">ACCES APROBAT</span></p>
            <p class="pt-bp-sub" id="pt-state-sub">Bilet adult · 11:00</p>
            <div class="pt-bp-stats"><span><b id="pt-rate">18</b>scanări/min</span><span><b id="pt-inside">412</b>intrați</span><span><b id="pt-queue">0</b>de sincronizat</span></div>
          </div>
        </div>
        <p class="pt-bp-mock">Machetă ilustrativă</p>
      </div>
    </div>
  </section>

  <!-- ===================== HARDWARE ===================== -->
  <section class="pt-sec pt-hw" id="hardware" data-chapter="o-zi" aria-labelledby="pt-hw-h">
    <div class="wrap">
      <?= $ptHead('pt-hw-h', 'Ce îți trebuie ca să pornești', 'Fără server, fără licențe și fără instalări complicate.', 'Cel mai des, locațiile pornesc cu ce au deja în casă.') ?>
      <ul class="pt-hw-grid" data-reveal>
        <?php foreach ($ptHardware as [$icon, $title, $text]): ?>
        <li><span class="pt-ic"><?= v2_ic($icon) ?></span><h3><?= v2_e($title) ?></h3><p><?= v2_e($text) ?></p></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <?php if ($ptVideo || $ptWall): ?>
  <!-- ===================== PARTNER STORIES ===================== -->
  <section class="pt-sec pt-stories" id="povesti" data-chapter="povesti" aria-labelledby="pt-stories-h">
    <div class="pt-stories-glow" aria-hidden="true"></div>
    <div class="wrap">
      <?= $ptHead('pt-stories-h', 'Povești de la parteneri', 'Nu ne crede pe cuvânt. <span class="pt-nl">Ascultă-i pe ei.</span>', 'Locații care vând deja prin bilete.online, despre cum arată ziua lor acum: la intrare, la ghișeu și în rapoarte.', 'is-dark is-center') ?>

      <?php if ($ptVideo): ?>
      <div class="pt-feature<?= empty($ptVideo['quote']) ? ' is-solo' : '' ?>">
        <div class="pt-video" id="pt-video" data-yt="<?= v2_e($ptVideo['youtube']) ?>" data-title="<?= v2_e($ptVideo['title']) ?>">
          <img class="pt-video-poster" src="https://i.ytimg.com/vi/<?= v2_e($ptVideo['youtube']) ?>/maxresdefault.jpg" data-fallback="https://i.ytimg.com/vi/<?= v2_e($ptVideo['youtube']) ?>/hqdefault.jpg" alt="" width="1280" height="720" loading="lazy" decoding="async">
          <button class="pt-video-play" type="button" data-track-cta="parteneri_video_play" aria-label="Pornește filmul: <?= v2_e($ptVideo['title']) ?><?= !empty($ptVideo['duration']) ? ' (' . v2_e($ptVideo['duration']) . ')' : '' ?>">
            <span class="pt-play" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M8 5.5v13a1 1 0 0 0 1.52.85l10.4-6.5a1 1 0 0 0 0-1.7L9.52 4.65A1 1 0 0 0 8 5.5Z"/></svg></span>
            <span class="pt-video-cta" aria-hidden="true"><b>Vezi filmul</b><small><?= !empty($ptVideo['duration']) ? v2_e($ptVideo['duration']) . ' · ' : '' ?>se încarcă de pe YouTube</small></span>
          </button>
          <p class="pt-video-title"><?= $ptDemoTag($ptVideo) ?><?= v2_e($ptVideo['title']) ?></p>
        </div>
        <?php if (!empty($ptVideo['quote'])): ?>
        <figure class="pt-feature-quote">
          <span class="pt-qmark" aria-hidden="true">“</span>
          <blockquote><p><?= v2_e($ptVideo['quote']) ?></p></blockquote>
          <figcaption><span class="pt-avatar" aria-hidden="true"><?= v2_e($ptInitials($ptVideo['name'])) ?></span><span><b><?= v2_e($ptVideo['name']) ?></b><small><?= v2_e($ptVideo['role'] . ' · ' . $ptVideo['place']) ?></small></span></figcaption>
          <?php if (!empty($ptVideo['results'])): ?><ul class="pt-results"><?php foreach ($ptVideo['results'] as $r): ?><li><?= v2_ic('check') ?><?= v2_e($r) ?></li><?php endforeach; ?></ul><?php endif; ?>
        </figure>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($ptWall): ?>
    <?php
    $ptQuoteCard = static function (array $q, bool $copy) use ($ptInitials, $ptDemoTag): string {
        return '<figure class="pt-quote"' . ($copy ? ' aria-hidden="true"' : '') . '>'
            . '<p class="pt-quote-top"><span class="pt-quote-venue">' . v2_e($q['venue'] . ' · ' . $q['city']) . '</span>' . $ptDemoTag($q) . '</p>'
            . '<blockquote><p>' . v2_e($q['quote']) . '</p></blockquote>'
            . (!empty($q['result']) ? '<p class="pt-quote-result">' . v2_ic('trend-up') . v2_e($q['result']) . '</p>' : '')
            . '<figcaption><span class="pt-avatar" aria-hidden="true">' . v2_e($ptInitials($q['name'])) . '</span><span><b>' . v2_e($q['name']) . '</b><small>' . v2_e($q['role']) . '</small></span></figcaption>'
            . '</figure>';
    };
    ?>
    <div class="pt-wall<?= $ptWallMoves ? ' is-moving' : '' ?>" id="pt-wall">
      <?php if ($ptWallMoves): ?>
      <div class="pt-wall-row">
        <div class="pt-wall-set"><?php foreach ($ptWall as $q) { echo $ptQuoteCard($q, false); } ?></div>
        <div class="pt-wall-set" aria-hidden="true"><?php foreach ($ptWall as $q) { echo $ptQuoteCard($q, true); } ?></div>
      </div>
      <div class="pt-wall-row is-rev" aria-hidden="true">
        <?php for ($pass = 0; $pass < 2; $pass++): ?><div class="pt-wall-set"><?php foreach (array_reverse($ptWall) as $q) { echo $ptQuoteCard($q, true); } ?></div><?php endfor; ?>
      </div>
      <div class="wrap pt-wall-foot"><button class="pt-wall-toggle" id="pt-wall-toggle" type="button" aria-controls="pt-wall" hidden><span class="pt-wall-ic" aria-hidden="true"></span><span id="pt-wall-toggle-t">Oprește derularea</span></button></div>
      <?php else: ?>
      <div class="wrap"><div class="pt-wall-grid"><?php foreach ($ptWall as $q) { echo $ptQuoteCard($q, false); } ?></div></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <!-- ===================== EVERYTHING YOU GET ===================== -->
  <section class="pt-sec pt-get" id="ce-primesti" data-chapter="ce-primesti" aria-labelledby="pt-get-h">
    <div class="wrap">
      <?= $ptHead('pt-get-h', 'Ce primești · SEO · checkout · QR', 'Transformă activitățile tale în bilete care se vând online.', 'Un stack complet pentru vânzarea activităților tale: bilete.online combină pagini publice optimizate, flux de cumpărare, emitere bilete, operațiuni la intrare și instrumente de creștere. Locațiile sunt descoperite organic, vând bilete rapid și gestionează accesul cu QR — fără să construiască de la zero o platformă de ticketing.', 'is-center') ?>
      <ul class="pt-bento" data-reveal>
        <?php foreach ($ptStack as [$stackN, $stackK, $stackTitle, $stackText, $stackCls]): ?>
        <li class="pt-tile <?= $stackCls ?>">
          <p class="pt-tile-k"><span><?= $stackN ?></span><?= v2_e($stackK) ?></p>
          <h3><?= v2_e($stackTitle) ?></h3>
          <p><?= v2_e($stackText) ?></p>
          <div class="pt-tile-art" aria-hidden="true">
            <?php if ($stackCls === 'is-seo'): ?>
            <span class="pt-art-search"><?= v2_ic('magnifying-glass') ?>activități copii brașov</span><span class="pt-art-res"><b>Escape room pentru copii — Brașov</b><small>bilete.online › brasov › activitati-copii</small></span><span class="pt-art-res is-dim"><b></b><small></small></span>
            <?php elseif ($stackCls === 'is-checkout'): ?>
            <span class="pt-art-pay"><i>Card</i><i>Apple Pay</i><i>Google Pay</i><i class="is-on">Card Cultural</i></span>
            <?php elseif ($stackCls === 'is-qr'): ?>
            <span class="pt-art-ticket">
              <span class="pt-art-qr"><?= $ptQr('stack', 21) ?></span>
              <span class="pt-art-tk"><small>Bilet MKT-19024</small><b>Sâm, 19 oct · 14:00</b><span class="pt-art-tk-sub">Acces + experiență · 1 pers.</span><span class="pt-art-ok"><?= v2_ic('check-circle') ?>Valid la intrare</span></span>
            </span>
            <?php elseif ($stackCls === 'is-dash'): ?>
            <span class="pt-art-bars"><?php foreach ([34, 52, 44, 70, 58, 86, 74] as $h): ?><i style="--h:<?= $h ?>%"></i><?php endforeach; ?></span>
            <?php elseif ($stackCls === 'is-growth'): ?>
            <span class="pt-art-codes"><i class="is-on">WEEKEND10</i><i>Puncte duble</i><i>Card cadou</i></span>
            <?php else: ?>
            <span class="pt-art-stars"><?php for ($s = 0; $s < 5; $s++): ?><?= v2_ic('star') ?><?php endfor; ?><b>4,9</b></span>
            <?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>

      <div class="pt-more">
        <h3 class="pt-more-h">O platformă completă pentru vânzarea de activități.</h3>
        <ul class="pt-benefits" data-reveal>
          <?php foreach ($ptBenefits as [$benIcon, $benTitle, $benText]): ?>
          <li><span class="pt-ic is-deep"><?= v2_ic($benIcon) ?></span><h4><?= v2_e($benTitle) ?></h4><p><?= v2_e($benText) ?></p></li>
          <?php endforeach; ?>
        </ul>
        <ul class="pt-small" data-reveal>
          <?php foreach ($ptSmall as [$smIcon, $smTitle, $smText]): ?>
          <li><span class="pt-ic is-soft"><?= v2_ic($smIcon) ?></span><div><h4><?= v2_e($smTitle) ?></h4><p><?= v2_e($smText) ?></p></div></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </section>

  <!-- ===================== MODULES ===================== -->
  <section class="pt-sec pt-mods" id="module" data-chapter="ce-primesti" aria-labelledby="pt-mod-h">
    <div class="wrap pt-split is-wide">
      <?= $ptHead('pt-mod-h', 'Module', 'Alegi ce ai nevoie. Platforma poate crește cu tine.') ?>
      <div class="pt-mod">
        <div class="pt-mod-tabs" role="tablist" aria-label="Module" data-tabs>
          <button type="button" role="tab" id="pt-tab-tickets" aria-controls="pt-panel-tickets" aria-selected="true"><?= v2_ic('ticket') ?>Bilete</button>
          <button type="button" role="tab" id="pt-tab-calendar" aria-controls="pt-panel-calendar" aria-selected="false" tabindex="-1"><?= v2_ic('calendar-blank') ?>Disponibilitate</button>
          <button type="button" role="tab" id="pt-tab-growth" aria-controls="pt-panel-growth" aria-selected="false" tabindex="-1"><?= v2_ic('trend-up') ?>Growth</button>
          <button type="button" role="tab" id="pt-tab-reports" aria-controls="pt-panel-reports" aria-selected="false" tabindex="-1"><?= v2_ic('chart-line-up') ?>Rapoarte</button>
        </div>
        <div class="pt-mod-panel" role="tabpanel" id="pt-panel-tickets" aria-labelledby="pt-tab-tickets" tabindex="0">
          <h3>Tipuri de bilete și pachete</h3>
          <p>Creezi bilete simple, bilete copil/adult, pachete de grup, bilete cu interval orar, extra-opțiuni sau bilete pentru tururi.</p>
          <div class="pt-mod-grid"><div>Adult · 95 lei</div><div>Copil · 45 lei</div><div>Grup · 340 lei</div></div>
        </div>
        <div class="pt-mod-panel" role="tabpanel" id="pt-panel-calendar" aria-labelledby="pt-tab-calendar" tabindex="0" hidden>
          <h3>Disponibilitate și sloturi</h3>
          <p>Controlezi zile, ore, capacitate, închideri, excepții, sezonalitate și intervale cu disponibilitate limitată.</p>
          <div class="pt-mod-days"><?php for ($day = 1; $day <= 14; $day++): ?><span<?= $day % 4 === 0 ? ' class="is-busy"' : '' ?>><?= $day ?></span><?php endfor; ?></div>
        </div>
        <div class="pt-mod-panel" role="tabpanel" id="pt-panel-growth" aria-labelledby="pt-tab-growth" tabindex="0" hidden>
          <h3>Promoții, carduri cadou, puncte</h3>
          <p>Rulezi coduri promo, campanii sezoniere, beneficii prin puncte bonus și eligibilitate pentru carduri cadou sau vouchere.</p>
          <div class="pt-mod-chips"><span class="is-on">WEEKEND10</span><span class="is-mint">Puncte duble</span><span>Card cadou</span></div>
        </div>
        <div class="pt-mod-panel" role="tabpanel" id="pt-panel-reports" aria-labelledby="pt-tab-reports" tabindex="0" hidden>
          <h3>Rapoarte și date utile</h3>
          <p>Vezi ce se vinde, când, pentru cine, care activități performează și ce intervale au conversie mai bună.</p>
          <div class="pt-mod-grid is-stats"><div><small>Vânzări</small><b>18.4k</b></div><div><small>Comenzi</small><b>96</b></div><div><small>Conversie</small><b>4.2%</b></div></div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== VISIBILITY / SEO ===================== -->
  <section class="pt-sec pt-seo" id="vizibilitate" data-chapter="vizibilitate" aria-labelledby="pt-seo-h">
    <div class="wrap">
      <div class="pt-seo-grid">
        <div>
          <?= $ptHead('pt-seo-h', 'SEO engine', 'Nu depinzi doar de reclame.', 'Fiecare activitate poate deveni o pagină de vânzare optimizată. Locația ta poate apărea în pagini de oraș, categorie și intenție — nu doar într-o listă generică.') ?>
          <div class="pt-browser" aria-hidden="true">
            <p class="pt-browser-bar"><?= v2_ic('lock-simple') ?><span>bilete.online</span><b id="pt-typed-url">/brasov/activitati-copii</b><i class="pt-bk-caret"></i></p>
          </div>
          <ul class="pt-urls">
            <?php foreach ($ptSeoUrls as $ui => [$urlPath, $urlLabel]): ?>
            <li data-url-i="<?= $ui ?>"><b><?= v2_e($urlPath) ?></b><span><?= v2_e($urlLabel) ?></span></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <div class="pt-anatomy">
          <div class="pt-anatomy-head"><p>Anatomia unei pagini SEO</p><h3>Activitatea ta devine găsibilă.</h3></div>
          <ol class="pt-anatomy-list" data-reveal>
            <?php foreach ($ptAnatomy as $ai => [$anaTitle, $anaText]): ?>
            <li><span class="pt-anatomy-n"><?= $ai + 1 ?></span><div><b><?= v2_e($anaTitle) ?></b><span><?= v2_e($anaText) ?></span></div></li>
            <?php endforeach; ?>
          </ol>
        </div>
      </div>

      <div class="pt-reach">
        <h3 class="pt-reach-h">Nu vinzi doar dintr-un panou. Vinzi dintr-un loc unde oamenii caută deja.</h3>
        <ul class="pt-reach-nums">
          <?php if ($ptAttractions): ?><li><b><?= v2_thousands($ptAttractions) ?></b><span>atracții în catalog</span></li><?php endif; ?>
          <?php if ($ptCities): ?><li><b><?= $ptCities ?></b><span>orașe cu pagini proprii</span></li><?php endif; ?>
          <?php if ($ptCategories): ?><li><b><?= count($ptCategories) ?></b><span>categorii de experiențe</span></li><?php endif; ?>
          <li><b>2%</b><span>comision, plătit de cumpărător</span></li>
        </ul>
        <ul class="pt-reach-list" data-reveal>
          <?php foreach ($ptReach as [$icon, $title, $text]): ?>
          <li><span class="pt-ic"><?= v2_ic($icon) ?></span><div><h4><?= v2_e($title) ?></h4><p><?= v2_e($text) ?></p></div></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </section>

  <!-- ===================== ANALYTICS + TRACKING ===================== -->
  <section class="pt-sec pt-dark pt-analytics" id="analytics" data-chapter="vizibilitate" aria-labelledby="pt-analytics-h">
    <div class="wrap">
      <?= $ptHead('pt-analytics-h', 'Date care îți cresc vânzările', 'Analytics avansat + tracking 100%. <span class="pt-hl">Reclame până la 60% mai ieftine.</span>', '', 'is-dark') ?>
      <div class="pt-two is-top">
        <div>
          <h3 class="pt-sub-h">De ce contează analytics-ul</h3>
          <ul class="pt-list is-dark is-arrows">
            <?php foreach ($ptAnalytics as $anItem): ?><li><?= v2_ic('arrow-right') ?><?= v2_e($anItem) ?></li><?php endforeach; ?>
          </ul>
          <a class="btn btn-light pt-mt" href="/inregistrare-locatie" data-signup data-track-cta="parteneri_analytics_ads">Vreau reclame mai ieftine<?= v2_ic('arrow-right') ?></a>
        </div>
        <div class="pt-glass">
          <h3 class="pt-sub-h">Tracking complet, fără pierderi</h3>
          <p>bilete.online se integrează cu <strong>toți pixelii de tracking</strong> și cu <strong>Facebook CAPI</strong>. Trimite <strong class="pt-yellow">100% din evenimentele de conversie</strong> server-side — deci nu te mai blochează ad blockerele și nici update-ul iOS care taie majoritatea trackingului.</p>
          <div class="pt-flow" id="pt-flow" aria-hidden="true">
            <div class="pt-flow-row is-lost"><span class="pt-flow-src"><?= v2_ic('globe-simple') ?>Doar pixel în browser</span><span class="pt-flow-line"><i></i><i></i><i></i><b class="pt-flow-wall"><?= v2_ic('x') ?>ad blocker / iOS</b></span><span class="pt-flow-dst is-dim">Reclame</span></div>
            <div class="pt-flow-row is-ok"><span class="pt-flow-src"><?= v2_ic('lightning') ?>Pixel + CAPI server-side</span><span class="pt-flow-line"><i></i><i></i><i></i></span><span class="pt-flow-dst">Reclame</span></div>
          </div>
          <div class="pt-glass-stats"><div><b>100%</b><span>evenimente urmărite</span></div><div><b>−60%</b><span>cost reclame</span></div></div>
          <p class="pt-glass-note">Funcționează cu reclame pe <strong>Facebook, Instagram, TikTok și Google</strong>. Date corecte = algoritmi mai eficienți = cost pe vânzare mai mic.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== PAYMENTS ===================== -->
  <section class="pt-sec pt-pay" id="plati" data-chapter="bani" aria-labelledby="pt-pay-h">
    <div class="wrap pt-two">
      <div>
        <?= $ptHead('pt-pay-h', 'Plăți pentru orice client', 'Toate metodele de plată, la îndemâna cumpărătorului.', 'Cu cât plata e mai simplă, cu atât vinzi mai mult. bilete.online acceptă cele mai folosite metode — clientul plătește în două atingeri, fără fricțiune.') ?>
        <p class="pt-sub">bilete.online încasează plata de la client și îți face <strong>deconturi periodice</strong> — sau la cerere, ori de câte ori vrei să-ți fie decontați banii.</p>
      </div>
      <ul class="pt-pay-grid" data-reveal>
        <?php foreach ($ptPayments as [$payIcon, $payName]): ?><li><?= v2_ic($payIcon) ?><?= v2_e($payName) ?></li><?php endforeach; ?>
        <li class="is-dark">și altele<br>în curând</li>
      </ul>
    </div>
  </section>

  <!-- ===================== THE MONEY ===================== -->
  <section class="pt-sec pt-money" id="bani" data-chapter="bani" aria-labelledby="pt-money-h">
    <div class="wrap">
      <div class="pt-money-top">
        <div>
          <p class="pt-k is-dark">Diferența care schimbă tot</p>
          <h2 id="pt-money-h" class="pt-money-h">Comision <span class="pt-big2" id="pt-big2">2%*</span><span class="pt-nl">Plătit de cumpărător.</span></h2>
        </div>
        <div>
          <p class="pt-sub is-dark">Comisionul de 2%* este adăugat transparent în prețul final și achitat de client. Tu îți stabilești prețul și îl primești <strong class="pt-yellow">integral</strong> la decont — fără să scazi nimic din marja ta. Dacă vinzi biletele și în alte părți, comisionul este de 4%: 2% incluse în preț și 2% adăugate.</p>
          <p class="pt-sub is-dark">Costuri clare, fără infrastructură construită de la zero: plătești pentru infrastructură care vinde, nu pentru promisiuni vagi. Fără abonament lunar, fără cost de instalare și fără taxă pentru fiecare bilet emis la ghișeu.</p>
        </div>
      </div>
      <div class="pt-money-grid">
        <ul class="pt-checks is-dark">
          <li><?= v2_ic('check-circle') ?>Tu setezi prețul — tu primești prețul stabilit</li>
          <li><?= v2_ic('check-circle') ?>Clientul vede clar cei 2%* — onest, fără surprize</li>
          <li><?= v2_ic('check-circle') ?>Zero costuri lunare, zero taxe de pornire</li>
          <li><?= v2_ic('check-circle') ?>Biletele vândute la ghișeu nu au comision de platformă</li>
          <li><?= v2_ic('check-circle') ?>Vezi soldul disponibil și ceri plata când vrei</li>
          <li><?= v2_ic('check-circle') ?>Documentele și facturile rămân în cont</li>
        </ul>

        <div class="pt-compare" id="pt-compare">
          <p class="pt-compare-h">Bilet de 100 lei — ce primești?</p>
          <div class="pt-compare-row">
            <div class="pt-compare-top"><span>Platformă clasică</span><span class="is-red">−9,50 lei</span></div>
            <p class="pt-compare-note">comision 8% + cost tranzacționare card 1–2%, ambele scăzute din banii tăi</p>
            <div class="pt-meter is-red"><i style="--w:90.5%"></i></div>
            <p class="pt-compare-get">Primești: <strong class="is-red">~90,50 lei</strong></p>
          </div>
          <div class="pt-compare-row is-ours">
            <div class="pt-compare-top"><span>bilete.online (2%* pe client)</span><span class="is-green">100%</span></div>
            <div class="pt-meter"><i style="--w:100%"></i></div>
            <p class="pt-compare-get">Primești: <strong class="is-green">100 lei</strong></p>
            <p class="pt-compare-note">Comisionul și costul cardului sunt incluse în prețul plătit de client. Tu primești prețul tău, întreg.</p>
          </div>
          <p class="pt-compare-foot">La volum, diferența devine uriașă.</p>
        </div>

        <div class="pt-calc">
          <p class="pt-calc-h">Cât înseamnă pentru tine</p>
          <div class="pt-calc-row">
            <label for="pt-qty">Bilete vândute online pe lună</label>
            <div class="pt-calc-in"><input id="pt-qty" type="number" inputmode="numeric" min="0" max="100000" step="10" value="400"><input class="pt-range" type="range" min="0" max="5000" step="10" value="400" aria-label="Bilete vândute online pe lună" data-mirror="pt-qty"></div>
          </div>
          <div class="pt-calc-row">
            <label for="pt-price">Preț mediu pe bilet (lei)</label>
            <div class="pt-calc-in"><input id="pt-price" type="number" inputmode="numeric" min="0" max="10000" step="5" value="45"><input class="pt-range" type="range" min="0" max="500" step="5" value="45" aria-label="Preț mediu pe bilet (lei)" data-mirror="pt-price"></div>
          </div>
          <dl class="pt-calc-out">
            <div><dt>Încasezi din bilete</dt><dd id="pt-out-rev">18.000 lei</dd></div>
            <div><dt>Comision 2%, plătit de cumpărător</dt><dd id="pt-out-fee">360 lei</dd></div>
            <div class="is-total"><dt>Rămâne la tine</dt><dd id="pt-out-net">18.000 lei</dd></div>
            <div class="is-vs"><dt>Pe o platformă clasică (−9,5%) ar rămâne</dt><dd id="pt-out-classic">16.290 lei</dd></div>
          </dl>
          <p class="pt-calc-note">Comisionul se adaugă la prețul biletului, deci prețul tău rămâne întreg. Cumpărătorul plătește <span id="pt-out-buyer">45,90 lei</span> pe bilet. Comparația folosește exemplul de alături.</p>
        </div>
      </div>
      <p class="pt-footnote"><strong>*</strong> Comisionul de 2% se aplică pentru vânzarea exclusivă prin bilete.online. bilete.online încasează plata de la client și îți face deconturi periodice sau la cerere.</p>
    </div>
  </section>

  <!-- ===================== FISCAL / ANAF ===================== -->
  <section class="pt-sec pt-fiscal" id="fiscal" data-chapter="bani" aria-labelledby="pt-fiscal-h">
    <div class="wrap">
      <?= $ptHead('pt-fiscal-h', 'Fiscal, fără bătăi de cap', 'Contabilitatea și ANAF, rezolvate automat.', '', 'is-center') ?>
      <ul class="pt-fiscal-grid" data-reveal>
        <?php foreach ($ptFiscal as [$fisIcon, $fisTitle, $fisText]): ?>
        <li><span class="pt-ic"><?= v2_ic($fisIcon) ?></span><h3><?= v2_e($fisTitle) ?></h3><p><?= v2_e($fisText) ?></p></li>
        <?php endforeach; ?>
      </ul>
      <div class="pt-anaf" id="pt-anaf">
        <p class="pt-anaf-k">O vânzare → documente generate automat, în secunde</p>
        <div class="pt-anaf-grid">
          <div class="pt-order">
            <p class="pt-order-top"><span>Comandă nouă</span><i class="pt-live"></i></p>
            <b>Bilet acces + rental</b>
            <p class="pt-order-no">MKT-19024 · 180 lei</p>
            <p class="pt-bk-ok"><?= v2_ic('check') ?>Plată confirmată</p>
          </div>
          <div class="pt-anaf-wire" aria-hidden="true"><i></i></div>
          <div class="pt-docs" aria-hidden="true">
            <?php foreach ($ptDocs as [$docIcon, $docName, $docMeta]): ?>
            <div class="pt-doc is-done"><div class="pt-doc-top"><?= v2_ic($docIcon) ?><span class="pt-doc-state"><?= v2_ic('check') ?></span></div><b><?= v2_e($docName) ?></b><small><?= v2_e($docMeta) ?></small><i class="pt-doc-bar"></i></div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="pt-anaf-foot">
          <p>Zero introducere manuală. Documentele sunt gata în <strong id="pt-elapsed">3s</strong> de la fiecare vânzare.</p>
          <a class="btn btn-light" href="/inregistrare-locatie" data-signup data-track-cta="parteneri_anaf">Vreau fiscalitatea pe pilot automat<?= v2_ic('arrow-right') ?></a>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== HOW TO START ===================== -->
  <section class="pt-sec pt-how" id="cum" data-chapter="cum" aria-labelledby="pt-how-h">
    <div class="wrap">
      <?= $ptHead('pt-how-h', 'De la cont la prima vânzare', 'Patru pași. Sub o zi. Zero costuri de pornire.', '', 'is-center') ?>
      <ol class="pt-steps" id="pt-steps">
        <?php foreach ($ptSteps as $stepIndex => [$stepIcon, $stepTitle, $stepText, $stepTag]): ?>
        <li class="pt-step" style="--i:<?= $stepIndex ?>"><span class="pt-step-n"><?= $stepIndex + 1 ?></span><span class="pt-ic"><?= v2_ic($stepIcon) ?></span><h3><?= v2_e($stepTitle) ?></h3><p><?= v2_e($stepText) ?></p><p class="pt-tag"><?= v2_e($stepTag) ?></p></li>
        <?php endforeach; ?>
      </ol>
      <div class="pt-center"><a class="btn btn-primary" href="/inregistrare-locatie" data-signup data-track-cta="parteneri_how_it_works">Începe acum, gratuit<?= v2_ic('arrow-right') ?></a></div>

      <div class="pt-ops">
        <div class="pt-ops-head">
          <p class="pt-k">Operațional</p>
          <h3>De la listare la check-in.</h3>
          <p>Fluxul este construit pentru echipe mici: publici activitatea, vinzi bilete, scanezi la intrare și urmărești rezultatele.</p>
        </div>
        <ol class="pt-ops-list" data-reveal>
          <?php foreach ($ptOps as $oi => [$opTitle, $opText]): ?>
          <li><span class="pt-ops-n"><?= $oi + 1 ?></span><h4><?= v2_e($opTitle) ?></h4><p><?= v2_e($opText) ?></p></li>
          <?php endforeach; ?>
        </ol>
      </div>
    </div>
  </section>

  <!-- ===================== TIXELLO ===================== -->
  <section class="pt-sec pt-dark pt-tech" id="tehnologie" data-chapter="cum" aria-labelledby="pt-tech-h">
    <div class="pt-tech-rings" aria-hidden="true"><i></i><i></i><i></i></div>
    <div class="wrap pt-two">
      <div>
        <span class="pt-badge">Powered by Tixello</span>
        <h2 class="pt-tech-h" id="pt-tech-h">Infrastructură matură, testată la scară.</h2>
        <p class="pt-sub is-dark">bilete.online rulează pe Tixello — sistemul de ticketing care a procesat deja peste 4,4 milioane EUR în vânzări și peste 301.000 de bilete. Primești tehnologie de producție, fără s-o construiești sau s-o întreții.</p>
        <a class="btn btn-light pt-mt" href="/inregistrare-locatie" data-signup data-track-cta="parteneri_tixello">Devino partener<?= v2_ic('arrow-right') ?></a>
      </div>
      <div class="pt-numbers">
        <p class="pt-numbers-k">În cifre</p>
        <dl>
          <?php foreach ($ptTixello as [$numLabel, $numValue]): ?><div><dt><?= v2_e($numLabel) ?></dt><dd><?= v2_e($numValue) ?></dd></div><?php endforeach; ?>
        </dl>
      </div>
    </div>
  </section>

  <!-- ===================== FAQ ===================== -->
  <section class="pt-sec pt-faq" id="intrebari" data-chapter="intrebari" aria-labelledby="pt-faq-h">
    <div class="wrap pt-faq-grid">
      <div class="pt-faq-side">
        <?= $ptHead('pt-faq-h', 'Întrebări frecvente', 'Ce vrei să știi înainte să începi') ?>
        <p class="pt-faq-alt">Nu găsești răspunsul? Scrie-ne la <a href="mailto:<?= v2_e(SUPPORT_EMAIL) ?>?subject=%C3%8Entrebare%20parteneriat%20bilete.online" data-track-cta="parteneri_faq_email"><?= v2_e(SUPPORT_EMAIL) ?></a>.</p>
      </div>
      <div class="pt-faq-groups">
        <?php foreach ($ptFaqGroups as $gi => [$groupName, $groupFaqs]): ?>
        <div class="pt-faq-group">
          <h3><?= v2_e($groupName) ?></h3>
          <?php foreach ($groupFaqs as $fi => [$faqQ, $faqA]): ?>
          <details class="qa"<?= $gi === 0 && $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php if ($ptPull): ?>
  <!-- ===================== ONE LARGE QUOTE ===================== -->
  <section class="pt-voice" data-chapter="intrebari" aria-label="Ce spune un partener">
    <div class="wrap">
      <figure class="pt-voice-in" id="pt-voice">
        <span class="pt-voice-mark" aria-hidden="true">“</span>
        <blockquote><p><?= v2_e($ptPull['quote']) ?></p></blockquote>
        <figcaption>
          <span class="pt-avatar" aria-hidden="true"><?= v2_e($ptInitials($ptPull['name'])) ?></span>
          <span><b><?= v2_e($ptPull['name']) ?><?= $ptDemoTag($ptPull) ?></b><small><?= v2_e($ptPull['role'] . ' · ' . $ptPull['venue'] . ', ' . $ptPull['city']) ?></small></span>
          <?php if (!empty($ptPull['result'])): ?><span class="pt-quote-result"><?= v2_ic('trend-up') ?><?= v2_e($ptPull['result']) ?></span><?php endif; ?>
        </figcaption>
      </figure>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== FINALE: start alone or talk to us ===================== -->
  <section class="pt-finale" id="demo" data-chapter="cum" aria-labelledby="pt-final-h">
    <div class="pt-finale-bg" aria-hidden="true"><svg class="deco-arches" viewBox="0 0 400 400" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg></div>
    <div class="wrap">
      <div class="pt-final-head">
        <span class="pt-badge">Devino partener</span>
        <h2 id="pt-final-h">Pune-ți activitățile la vânzare <span class="pt-nl">și păstrează prețul tău întreg.</span></h2>
        <p>Fără costuri de pornire. Activități nelimitate. Onboarding în 5 minute, go-live azi. Comision 2%* plătit de client.</p>
      </div>

      <div class="pt-final-grid">
        <div class="pt-self">
          <p class="pt-self-k">Ready to list?</p>
          <h3>Locația ta poate deveni următoarea activitate descoperită online.</h3>
          <p>Dacă ai o activitate pe care oamenii ar trebui să o descopere, bilete.online poate fi infrastructura care o vinde. Îți faci contul singur, adaugi activitățile și mergi live.</p>
          <ol class="pt-self-steps">
            <?php foreach ($ptSteps as $stepIndex => [, $stepTitle, , $stepTag]): ?><li><i><?= $stepIndex + 1 ?></i><b><?= v2_e($stepTitle) ?></b><span><?= v2_e($stepTag) ?></span></li><?php endforeach; ?>
          </ol>
          <a class="btn btn-light pt-self-go" href="/inregistrare-locatie" data-signup data-track-cta="parteneri_final_signup">Vreau să-mi vând activitățile<?= v2_ic('arrow-right') ?></a>
          <p class="pt-self-note">Fără cost de pornire · Activități nelimitate · Anulezi oricând</p>
          <div class="pt-contact">
            <a class="btn btn-outline-light" href="mailto:<?= v2_e(SUPPORT_EMAIL) ?>?subject=%C3%8Entrebare%20parteneriat%20bilete.online" data-track-cta="parteneri_email_contact"><?= v2_ic('envelope-simple') ?>Trimite-ne un email</a>
            <p>Sau sună la <a href="tel:+40750292962" data-track-cta="parteneri_phone">0750 292 962</a>, luni - vineri, 09:00 - 18:00.</p>
          </div>
        </div>

        <div class="pt-form-card">
          <h3 class="pt-form-h">Hai să vedem împreună cum arată locația ta</h3>
          <p class="pt-form-lead">Îți arătăm panoul pe datele tale: ce activități ai publica, ce tipuri de bilete se potrivesc, ce pagini ar trebui create, ce oportunități SEO ai și cum ar arăta ziua de la ghișeu. Fără prezentare lungă.</p>
          <form class="pt-form" id="fv-form" data-lead-source="Parteneri" novalidate>
            <p class="pt-form-err" id="fv-error" role="alert" tabindex="-1" hidden></p>
            <!-- people never see or reach this; a bot that fills it gets a quiet "sent" -->
            <div class="pt-trap" aria-hidden="true"><label for="fv-fax">Fax (nu completa)</label><input id="fv-fax" name="fax" type="text" tabindex="-1" autocomplete="off"></div>
            <div class="pt-fields">
              <p class="pt-field"><label for="fv-name">Nume și prenume<span aria-hidden="true">*</span></label><input id="fv-name" name="contact_name" type="text" autocomplete="name" maxlength="160" required></p>
              <p class="pt-field"><label for="fv-email">Email<span aria-hidden="true">*</span></label><input id="fv-email" name="email" type="email" inputmode="email" autocomplete="email" spellcheck="false" maxlength="190" required placeholder="email@locatie.ro"></p>
              <p class="pt-field"><label for="fv-phone">Telefon</label><input id="fv-phone" name="phone" type="tel" autocomplete="tel" maxlength="40" placeholder="+40..."></p>
              <p class="pt-field"><label for="fv-role">Rolul tău</label><span class="pt-sel"><select id="fv-role" name="role"><option value="">Alege</option><?php foreach ($ptRoles as $role): ?><option><?= v2_e($role) ?></option><?php endforeach; ?></select><?= v2_ic('caret-down') ?></span></p>
              <p class="pt-field"><label for="fv-venue">Numele locației<span aria-hidden="true">*</span></label><input id="fv-venue" name="location_name" type="text" autocomplete="organization" maxlength="200" required placeholder="ex. Mystery Rooms Brașov"></p>
              <p class="pt-field"><label for="fv-city">Orașul<span aria-hidden="true">*</span></label><input id="fv-city" name="city" type="text" autocomplete="address-level2" maxlength="120" required list="ftr-cities" placeholder="ex. Brașov"></p>
              <p class="pt-field"><label for="fv-type">Tipul locației</label><span class="pt-sel"><select id="fv-type" name="venue_type"><option value="">Alege</option><?php foreach ($ptCategories as $c): ?><option value="<?= v2_e($c['slug']) ?>"><?= v2_e($c['name']) ?></option><?php endforeach; ?><option value="other">Altceva</option></select><?= v2_ic('caret-down') ?></span></p>
              <p class="pt-field"><label for="fv-count">Câte activități vinzi?</label><span class="pt-sel"><select id="fv-count" name="activities_count"><option value="">Alege</option><?php foreach ($ptCounts as $count): ?><option><?= v2_e($count) ?></option><?php endforeach; ?></select><?= v2_ic('caret-down') ?></span></p>
              <p class="pt-field is-wide"><label for="fv-message">Ce ai vrea să rezolvi?</label><textarea id="fv-message" name="message" rows="4" maxlength="1800" placeholder="Descrie activitățile, tipurile de bilete, programul și ce probleme ai acum cu vânzarea sau rezervările. De exemplu: vindem doar la ghișeu și vrem și online, sau avem cozi la intrare în weekend."></textarea></p>
              <p class="pt-check is-wide"><input id="fv-consent" name="consent" type="checkbox" required><label for="fv-consent">Accept să fiu contactat pentru o discuție despre listarea locației pe bilete.online. <a href="/confidentialitate">Politica de confidențialitate</a></label></p>
            </div>
            <button class="btn btn-primary pt-submit" id="fv-submit" type="submit">Trimite solicitarea</button>
          </form>
          <div class="pt-done" id="fv-done" hidden>
            <span class="pt-done-ic" aria-hidden="true"><?= v2_ic('check') ?></span>
            <h3 id="fv-done-h" tabindex="-1">Mulțumim!</h3>
            <p>Cererea ta a ajuns la echipa bilete.online. Te contactăm în următoarea zi lucrătoare pe <strong id="fv-done-email"></strong> cu o propunere de demonstrație.</p>
          </div>
          <div class="pt-prep">
            <b>Ce poți pregăti înainte:</b>
            <ul>
              <li><?= v2_ic('check') ?>numele locației și orașul</li>
              <li><?= v2_ic('check') ?>tipurile de activități</li>
              <li><?= v2_ic('check') ?>prețuri și capacitate</li>
              <li><?= v2_ic('check') ?>program și reguli de acces</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- phones: the ask follows until the finale is in view -->
  <div class="pt-bar" id="pt-bar" hidden>
    <span class="pt-bar-t"><b>Vrei să vezi panoul pe datele tale?</b><small>Demonstrație de 20 de minute, fără obligații.</small></span>
    <a class="btn btn-outline-light" href="#demo" data-track-cta="parteneri_bar_demo">Demo</a>
    <a class="btn btn-primary" href="/inregistrare-locatie" data-signup data-track-cta="parteneri_bar_signup">Începe</a>
  </div>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
