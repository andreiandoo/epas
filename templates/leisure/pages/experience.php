<?php
/** Pagina unui produs de agrement (acces / activitate / închiriere), din datele reale din core. */
require_once TEMPLATE_DIR . '/lib.php';
$slug = isset($_GET['slug']) ? (string) $_GET['slug'] : '';
$b = $slug !== '' ? lv_find($slug) : null;
if (!$b && $slug === '') {
    $b = lv_tickets()[0] ?? null;
}
if (!$b) {
    ts_not_found();
}

$expCat     = (string) ($b['service_category'] ?? 'access');
$expTitle   = (string) ($b['name'] ?? 'Experiență');
$expDesc    = trim(strip_tags((string) ($b['description'] ?? '')));
$expVenue   = (string) ($b['event']['venue'] ?? SITE_NAME);
$expCity    = (string) ($b['event']['city'] ?? '');
$expImg     = lv_image($b, 1800);
$expGallery = lv_gallery($b, 1800);
$expPrice   = lv_price((int) ($b['price_cents'] ?? 0));
$expDur     = lv_duration($b);
$expCatName = ['access' => 'Acces în parc', 'activity' => 'Activitate', 'rental' => 'Închiriere'][$expCat] ?? 'Experiență';
$expUnit    = $expCat === 'rental' ? '/ închiriere' : '/ persoană';
$hours      = trim((string) site('settings.today_hours', ''));
$lastEntry  = trim((string) site('settings.last_entry', ''));
$contactUrl = ts_link('contact');

// Titlul pe două rânduri: ultimul cuvânt în contur, ca în prototip
$words = preg_split('/\s+/u', $expTitle) ?: [$expTitle];
$titleLast = count($words) > 1 ? array_pop($words) : '';
$titleHead = implode(' ', $words);

$expParas = array_values(array_filter(array_map('trim', preg_split('/\n{2,}|\r\n\r\n/', $expDesc) ?: [])));
if (!$expParas) {
    $expParas = [[
        'access'   => 'Biletul de acces îți deschide parcul pentru toată ziua aleasă. Îl rezervi online, îl primești pe email și intri direct cu codul de pe telefon.',
        'activity' => 'O activitate cu oră de start fixă și locuri limitate pe fiecare interval. Alegi ziua și ora, iar locul tău rămâne rezervat până la start.',
        'rental'   => 'Echipamentul se ridică din parc la ora rezervată și se returnează la final. Alegi durata potrivită, plătești online și nu mai stai la coadă.',
    ][$expCat] ?? ''];
}

$expBooking = [
    'ticketTypeId' => (int) $b['ticket_type_id'],
    'name'         => $expTitle,
    'category'     => $expCat,
    'priceCents'   => (int) ($b['price_cents'] ?? 0),
    'variants'     => array_values((array) ($b['duration_variants'] ?? [])),
    'event'        => [
        'id'     => (int) ($b['event']['id'] ?? 0),
        'slug'   => (string) ($b['event']['slug'] ?? ''),
        'title'  => SITE_NAME,
        'place'  => trim($expVenue . ($expCity !== '' && $expCity !== $expVenue ? ', ' . $expCity : '')),
        'poster' => lv_image($b, 600),
        'url'    => '/experienta/' . lv_slug($b),
    ],
];

$expRelated = [];
foreach (lv_tickets() as $o) {
    if ((int) $o['ticket_type_id'] === (int) $b['ticket_type_id']) { continue; }
    $expRelated[] = [
        'title'    => $o['name'] ?? '',
        'badge'    => lv_category_label($o['service_category'] ?? null),
        'duration' => lv_duration($o),
        'price'    => 'de la ' . lv_price((int) ($o['price_cents'] ?? 0)),
        'image'    => lv_image($o, 1200),
        'url'      => '/experienta/' . lv_slug($o),
    ];
    if (count($expRelated) === 3) { break; }
}

$expGalleryJs = [];
foreach ($expGallery as $i => $src) {
    $expGalleryJs[] = ['src' => $src, 'alt' => $expTitle, 'caption' => $i === 0 ? $expTitle : $expVenue];
}

$expFaqs = [
    ['q' => 'Cum primesc biletul?', 'a' => 'Imediat după plată primești biletul pe email, cu un cod QR. La intrare îl arăți direct de pe telefon; nu trebuie printat.'],
    ['q' => 'Pot schimba ziua sau ora?', 'a' => 'Scrie-ne cât mai devreme, cu numărul comenzii. Mutăm rezervarea pe altă zi sau alt interval cu locuri libere.'],
    ['q' => 'Ce se întâmplă dacă vremea e rea?', 'a' => 'Dacă o activitate se suspendă din motive meteo, te anunțăm și îți propunem altă zi sau returnarea banilor.'],
    ['q' => 'Am nevoie de cont ca să rezerv?', 'a' => 'Nu. Poți plăti fără cont; biletele ajung pe email. Cu un cont le găsești oricând și în secțiunea „Biletele mele”.'],
    ['q' => 'Rezervați și pentru grupuri?', 'a' => 'Da. Pentru grupuri organizate, școli sau evenimente private ai o pagină separată, cu ofertă pe măsură.'],
];

$nvNav='experiente'; $nvNoSpacer=true;
$pageTitle = $expTitle . ' — ' . SITE_NAME;
$bodyAttrs = 'x-data="experienceDetail()" class="antialiased"';
$pageExtraStyles = <<<'CSS'
        :root {
            --pine: rgb(var(--c-pine-900));
            --pine-dark: rgb(var(--c-pine-950));
            --acid: rgb(var(--c-primary));
            --ember: #f27b4a;
            --cream: rgb(var(--c-canvas));
            --oat: rgb(var(--c-surface));
            --ink: rgb(var(--c-fg));
        }

        * { box-sizing: border-box; }
        html { background: var(--oat); }
        body {
            margin: 0;
            overflow-x: hidden;
            background: var(--oat);
            color: var(--ink);
            font-family:var(--f-body);
            text-rendering: optimizeLegibility;
        }
        [x-cloak] { display: none !important; }
        ::selection { background: var(--acid); color: var(--pine-dark); }
        button, a { -webkit-tap-highlight-color: transparent; }

        .safe-top { padding-top: max(12px, env(safe-area-inset-top)); }
        .safe-bottom { padding-bottom: max(16px, env(safe-area-inset-bottom)); }

        .grain::after {
            content: '';
            position: absolute;
            inset: 0;
            z-index: 8;
            pointer-events: none;
            opacity: .085;
            mix-blend-mode: soft-light;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='.58'/%3E%3C/svg%3E");
        }

        .topo-dark {
            background-image:
                radial-gradient(circle at 18% 22%, rgb(var(--c-primary) / .13), transparent 20%),
                radial-gradient(circle at 84% 18%, rgba(242,123,74,.11), transparent 17%),
                url("data:image/svg+xml,%3Csvg width='820' height='820' viewBox='0 0 820 820' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23ffffff' stroke-opacity='.05' stroke-width='1.1'%3E%3Cpath d='M88 212c37-105 158-179 273-133 94 37 117 148 65 224-49 72-150 69-199 143-51 77-34 200-133 247-95 45-205-31-192-137 13-103 118-121 127-216 5-55-2-92 59-128Z'/%3E%3Cpath d='M119 231c31-83 127-143 219-107 76 30 95 118 53 180-40 58-121 56-160 115-42 61-28 159-107 197-77 37-165-25-154-110 10-83 94-98 102-174 4-44 0-74 47-101Z'/%3E%3Cpath d='M513 479c52-81 159-106 232-43 72 60 50 172-26 217-68 40-149 11-202 68-45 48-39 134-105 151-74 19-142-53-112-125 28-67 109-63 148-123 31-49 30-97 65-145Z'/%3E%3Cpath d='M548 507c39-60 118-79 173-32 53 45 37 128-19 161-50 30-111 8-150 51-34 36-29 100-79 112-55 14-106-39-83-92 21-50 81-47 110-92 23-36 22-72 48-108Z'/%3E%3C/g%3E%3C/svg%3E");
            background-size: auto, auto, 820px 820px;
        }

        .paper-grid {
            background-image:
                linear-gradient(rgb(var(--c-pine-900) / .045) 1px, transparent 1px),
                linear-gradient(90deg, rgb(var(--c-pine-900) / .045) 1px, transparent 1px);
            background-size: 30px 30px;
        }

        .nav-shell {
            background: rgb(var(--c-pine-950) / .8);
            border: 1px solid rgba(255,255,255,.12);
            box-shadow: 0 24px 70px -42px rgba(0,0,0,.9);
            backdrop-filter: blur(22px) saturate(130%);
            -webkit-backdrop-filter: blur(22px) saturate(130%);
        }

        .hero-photo {
            clip-path: polygon(6% 0, 100% 0, 96% 88%, 72% 100%, 0 94%, 0 10%);
        }

        .outline-word {
            color: transparent;
            -webkit-text-stroke: 1px rgba(255,255,255,.46);
            text-stroke: 1px rgba(255,255,255,.46);
        }

        .route-line {
            fill: none;
            stroke: var(--acid);
            stroke-width: 2.3;
            stroke-linecap: round;
            stroke-dasharray: 9 13;
            filter: drop-shadow(0 0 8px rgb(var(--c-primary) / .38));
        }

        .route-progress {
            fill: none;
            stroke: var(--acid);
            stroke-width: 5;
            stroke-linecap: round;
            filter: drop-shadow(0 0 8px rgb(var(--c-primary) / .3));
        }

        .route-base {
            fill: none;
            stroke: rgba(255,255,255,.14);
            stroke-width: 5;
            stroke-linecap: round;
        }

        .map-node {
            transform-origin: center;
            animation: node-pulse 2.8s ease-in-out infinite;
        }
        @keyframes node-pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.18); opacity: .72; }
        }

        .pulse-dot { animation: pulse-dot 2.2s ease-out infinite; }
        @keyframes pulse-dot {
            0% { box-shadow: 0 0 0 0 rgb(var(--c-primary) / .48); }
            70% { box-shadow: 0 0 0 10px rgb(var(--c-primary) / 0); }
            100% { box-shadow: 0 0 0 0 rgb(var(--c-primary) / 0); }
        }

        .hero-meter {
            background: linear-gradient(90deg, var(--acid) 68%, rgba(255,255,255,.12) 68%);
        }

        .sticky-booking {
            background: rgb(var(--c-canvas) / .92);
            border: 1px solid rgb(var(--c-pine-900) / .1);
            box-shadow: 0 32px 90px -48px rgb(var(--c-pine-950) / .62);
            backdrop-filter: blur(22px) saturate(120%);
            -webkit-backdrop-filter: blur(22px) saturate(120%);
        }

        .date-card,
        .time-card,
        .option-card {
            transition: border-color .25s ease, background .25s ease, transform .25s ease, box-shadow .25s ease;
        }
        .date-card:hover,
        .time-card:hover,
        .option-card:hover { transform: translateY(-2px); }
        .date-card.is-active,
        .time-card.is-active,
        .option-card.is-active {
            border-color: var(--pine);
            background: var(--pine);
            color: white;
            box-shadow: 0 16px 38px -26px rgb(var(--c-pine-950) / .85);
        }

        .segment-card {
            transition: transform .35s ease, border-color .35s ease, box-shadow .35s ease;
        }
        .segment-card.is-active {
            transform: translateY(-6px);
            border-color: rgb(var(--c-primary) / .62);
            box-shadow: 0 24px 58px -38px rgb(var(--c-primary) / .52);
        }

        .gallery-main { clip-path: polygon(0 0, 96% 0, 100% 89%, 88% 100%, 0 96%); }
        .gallery-small-a { clip-path: polygon(6% 0, 100% 0, 96% 100%, 0 92%); }
        .gallery-small-b { clip-path: polygon(0 0, 94% 4%, 100% 90%, 8% 100%); }

        .timeline-line {
            background: linear-gradient(180deg, var(--acid), rgb(var(--c-pine-900) / .12));
        }

        .tilt-card { perspective: 1000px; transform-style: preserve-3d; }
        .tilt-card__surface {
            transform-style: preserve-3d;
            transition: transform .22s ease-out, box-shadow .35s ease, border-color .35s ease;
            will-change: transform;
        }
        .tilt-card:hover .tilt-card__surface {
            box-shadow: 0 36px 90px -48px rgb(var(--c-pine-950) / .78);
            border-color: rgb(var(--c-pine-900) / .24);
        }
        .tilt-card img { transition: transform .9s cubic-bezier(.18,.8,.22,1); }
        .tilt-card:hover img { transform: scale(1.055); }

        .magnetic { position: relative; overflow: hidden; }
        .magnetic::after {
            content: '';
            position: absolute;
            inset: -40%;
            background: radial-gradient(circle, rgba(255,255,255,.25), transparent 50%);
            opacity: 0;
            transform: scale(.3);
            transition: opacity .4s ease, transform .5s ease;
            pointer-events: none;
        }
        .magnetic:hover::after { opacity: 1; transform: scale(1); }

        .reveal-ready [data-reveal] { opacity: 0; transform: translateY(34px); }
        .reveal-ready [data-hero-kicker],
        .reveal-ready [data-hero-title],
        .reveal-ready [data-hero-copy],
        .reveal-ready [data-hero-visual] { opacity: 0; }
        .reveal-ready [data-hero-title] { transform: translateY(54px); }
        .reveal-ready [data-hero-copy], .reveal-ready [data-hero-kicker] { transform: translateY(22px); }
        .reveal-ready [data-hero-visual] { transform: translateX(58px) rotate(1.2deg); }

        .progress-line { transform: scaleX(0); transform-origin: left center; }
        .drawer-shadow { box-shadow: -50px 0 120px -60px rgb(var(--c-pine-950) / .82); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

        @media (max-width: 1023px) {
            .hero-photo { clip-path: polygon(0 0, 100% 0, 100% 94%, 10% 100%, 0 96%); }
        }

        @media (max-width: 639px) {
            .hero-photo,
            .gallery-main,
            .gallery-small-a,
            .gallery-small-b { clip-path: none; border-radius: 24px; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                scroll-behavior: auto !important;
                animation-duration: .001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .001ms !important;
            }
            .reveal-ready [data-reveal],
            .reveal-ready [data-hero-kicker],
            .reveal-ready [data-hero-title],
            .reveal-ready [data-hero-copy],
            .reveal-ready [data-hero-visual] {
                opacity: 1 !important;
                transform: none !important;
            }
        }
    
CSS;
$pageExtraHead = <<<'HTML'
<script defer src="https://cdn.jsdelivr.net/npm/animejs@3.2.2/lib/anime.min.js"></script>
<script>
        document.documentElement.classList.add('reveal-ready');
        window.__revealSafety = window.setTimeout(() => document.documentElement.classList.remove('reveal-ready'), 4200);
    </script>
HTML;
include ts_layout('head');
include ts_layout('header');
?>
    <div id="page-progress" class="progress-line fixed left-0 top-0 z-[180] h-[3px] w-full bg-acid"></div>

    <main>
        <!-- Hero -->
        <section id="top" class="grain topo-dark relative overflow-hidden bg-pine-950 pb-16 pt-28 text-white sm:pb-20 sm:pt-32 lg:min-h-[94svh] lg:pb-20 lg:pt-28">
            <div class="absolute inset-0 bg-gradient-to-b from-pine-950/0 via-pine-950/10 to-pine-950/90"></div>
            <div class="absolute -left-44 top-28 h-[500px] w-[500px] rounded-full border border-acid/10"></div>
            <div class="absolute -right-56 bottom-10 h-[640px] w-[640px] rounded-full border border-white/10"></div>

            <div class="relative z-10 mx-auto max-w-[1540px] px-4 sm:px-6 lg:px-8 xl:px-12">
                <div data-hero-kicker class="mb-5 flex flex-wrap items-center gap-2 text-[10px] font-bold uppercase tracking-[.2em] text-white/[.58] sm:text-xs sm:tracking-[.22em]">
                    <a href="/experiente" class="transition hover:text-acid">Experiențe</a>
                    <span class="text-white/25">/</span>
                    <span class="text-acid"><?= e($expCatName) ?></span>
                </div>

                <div class="grid gap-10 lg:grid-cols-[.88fr_1.12fr] lg:items-center lg:gap-14">
                    <div class="min-w-0 lg:pb-10">
                        <div data-hero-kicker class="mb-5 inline-flex max-w-full items-center gap-2 rounded-full border border-white/[.14] bg-white/[.07] px-3 py-2 text-[9px] font-bold uppercase tracking-[.2em] text-white/[.76] sm:px-4 sm:text-[10px] sm:tracking-[.24em]">
                            <span class="pulse-dot h-2 w-2 flex-none rounded-full bg-acid"></span>
                            <span class="truncate"><?= e($expVenue) ?></span>
                        </div>

                        <h1 class="font-display text-[clamp(52px,12vw,96px)] font-semibold leading-[.9] tracking-[-.05em] lg:text-[clamp(72px,7.4vw,124px)]">
                            <span data-hero-title class="block"><?= e($titleHead) ?></span>
                            <?php if ($titleLast !== ''): ?><span data-hero-title class="outline-word block pb-2 italic"><?= e($titleLast) ?></span><?php endif; ?>
                        </h1>

                        <p data-hero-copy class="mt-7 max-w-xl text-[15px] leading-7 text-white/[.68] sm:text-lg sm:leading-8"><?= e(mb_strimwidth($expParas[0], 0, 220, '…')) ?></p>

                        <div data-hero-copy class="mt-8 grid max-w-[590px] grid-cols-2 gap-2.5 sm:grid-cols-3 sm:gap-3">
                            <div class="rounded-[18px] border border-white/10 bg-white/[.06] px-3 py-3.5 sm:px-4 sm:py-4">
                                <span class="block text-[9px] font-bold uppercase tracking-[.18em] text-white/[.42]">Tip</span>
                                <strong class="mt-1 block font-display text-xl text-acid sm:text-2xl"><?= e(lv_category_label($expCat)) ?></strong>
                            </div>
                            <div class="rounded-[18px] border border-white/10 bg-white/[.06] px-3 py-3.5 sm:px-4 sm:py-4">
                                <span class="block text-[9px] font-bold uppercase tracking-[.18em] text-white/[.42]">Durată</span>
                                <strong class="mt-1 block font-display text-xl text-acid sm:text-2xl"><?= e($expDur) ?></strong>
                            </div>
                            <div class="col-span-2 rounded-[18px] border border-white/10 bg-white/[.06] px-3 py-3.5 sm:col-span-1 sm:px-4 sm:py-4">
                                <span class="block text-[9px] font-bold uppercase tracking-[.18em] text-white/[.42]">De la</span>
                                <strong class="mt-1 block font-display text-xl text-acid sm:text-2xl"><?= e($expPrice) ?></strong>
                            </div>
                        </div>

                        <div data-hero-copy class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <button @click="openBooking()" class="magnetic inline-flex min-h-[54px] items-center justify-center gap-2 whitespace-nowrap rounded-full bg-acid px-6 text-sm font-bold text-pine-950 shadow-acid transition hover:-translate-y-1 sm:text-base">
                                Alege data<?= $expCat === 'activity' ? ' și ora' : '' ?>
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                            </button>
                            <a href="#poveste" class="inline-flex min-h-[54px] items-center justify-center rounded-full border border-white/20 px-6 text-sm font-semibold text-white transition hover:bg-white/10 sm:text-base">Detalii</a>
                        </div>
                    </div>

                    <div data-hero-visual class="relative min-h-[460px] min-w-0 sm:min-h-[600px] lg:min-h-[700px]">
                        <div class="hero-photo absolute inset-0 overflow-hidden border border-white/10 bg-pine-850 shadow-lift">
                            <img id="hero-image" src="<?= e($expImg) ?>" alt="<?= e($expTitle) ?>" class="h-full w-full object-cover object-center">
                            <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-pine-950/10 to-transparent"></div>
                            <div class="absolute inset-0 bg-gradient-to-r from-pine-950/30 via-transparent to-transparent"></div>
                        </div>

                        <?php if ($hours !== ''): ?>
                        <div class="absolute left-4 top-5 z-20 rounded-[20px] border border-white/15 bg-pine-950/70 p-3.5 backdrop-blur-xl sm:left-7 sm:top-8 sm:p-4">
                            <div class="flex items-center gap-2 text-[9px] font-bold uppercase tracking-[.18em] text-acid sm:text-[10px]"><span class="pulse-dot h-2 w-2 rounded-full bg-acid"></span> Program azi</div>
                            <p class="mt-2 text-xs text-white/[.62] sm:text-sm"><?= e($hours) ?><?= $lastEntry !== '' ? ' · ultima intrare ' . e($lastEntry) : '' ?></p>
                        </div>
                        <?php endif; ?>

                        <div class="absolute bottom-5 left-4 right-4 z-20 rounded-[24px] border border-white/15 bg-pine-950/75 p-4 backdrop-blur-xl sm:bottom-8 sm:left-8 sm:right-auto sm:w-[360px] sm:p-5">
                            <span class="text-[9px] font-bold uppercase tracking-[.2em] text-white/[.42]">Următoarea zi cu locuri</span>
                            <p class="mt-1 font-display text-2xl font-semibold" x-text="lvStrip.length ? lvStrip[0].day + ', ' + lvStrip[0].number + (lvStrip[0].label && lvStrip[0].label !== 'azi' ? ' ' + lvStrip[0].label : (lvStrip[0].label === 'azi' ? ' · azi' : '')) : (lvLoadingMonth ? 'Se verifică…' : 'Momentan fără locuri')"></p>
                            <p class="mt-3 text-xs leading-5 text-white/[.54]">Locurile sunt limitate pe fiecare zi<?= $expCat === 'activity' ? ' și pe fiecare interval' : '' ?>. Rezervarea se confirmă imediat după plată.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Trust strip -->
        <section class="relative z-20 -mt-1 bg-oat px-4 pb-8 sm:px-6 lg:px-8">
            <div class="mx-auto grid max-w-[1420px] gap-3 rounded-[28px] border border-pine-900/10 bg-cream p-3 shadow-card sm:grid-cols-2 sm:p-4 lg:grid-cols-[1.2fr_.8fr_.8fr_.8fr]">
                <div class="flex items-center gap-4 rounded-[20px] bg-pine-900 px-4 py-4 text-white sm:px-5">
                    <span class="grid h-11 w-11 flex-none place-items-center rounded-full bg-acid text-pine-950">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 18h2v2h-2z"/></svg>
                    </span>
                    <div><strong class="block text-sm">Biletul vine pe email</strong><span class="mt-1 block text-xs text-white/50">intri cu codul QR de pe telefon</span></div>
                </div>
                <div class="flex items-center gap-3 rounded-[20px] border border-pine-900/10 px-4 py-4">
                    <svg class="h-5 w-5 flex-none text-pine-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    <div><strong class="block text-sm"><?= e($expDur) ?></strong><span class="text-xs text-pine-900/48"><?= $expCat === 'activity' ? 'start la ora aleasă' : 'în ziua aleasă' ?></span></div>
                </div>
                <div class="flex items-center gap-3 rounded-[20px] border border-pine-900/10 px-4 py-4">
                    <svg class="h-5 w-5 flex-none text-pine-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <div><strong class="block text-sm">Locuri limitate</strong><span class="text-xs text-pine-900/48">disponibilitate în timp real</span></div>
                </div>
                <div class="flex items-center gap-3 rounded-[20px] border border-pine-900/10 px-4 py-4">
                    <svg class="h-5 w-5 flex-none text-pine-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <div><strong class="block text-sm">Plată securizată</strong><span class="text-xs text-pine-900/48">cu cardul, online</span></div>
                </div>
            </div>
        </section>

        <!-- Story + desktop booking -->
        <section id="poveste" class="paper-grid relative overflow-hidden px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="mx-auto grid max-w-[1420px] gap-14 lg:grid-cols-[minmax(0,1fr)_400px] lg:items-start lg:gap-16 xl:grid-cols-[minmax(0,1fr)_430px]">
                <div class="min-w-0">
                    <div data-reveal class="max-w-3xl">
                        <p class="text-[10px] font-bold uppercase tracking-[.24em] text-pine-700 sm:text-xs">Despre <?= $expCat === 'rental' ? 'închiriere' : 'experiență' ?></p>
                        <h2 class="mt-4 pb-1 font-display text-[clamp(42px,7vw,76px)] font-semibold leading-[.98] tracking-[-.045em] text-pine-950"><?= e($expTitle) ?></h2>
                        <?php foreach ($expParas as $p): ?>
                        <p class="mt-6 max-w-2xl text-base leading-8 text-pine-900/66 sm:text-lg sm:leading-9"><?= nl2br(e($p)) ?></p>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-12 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <template x-for="feature in features" :key="feature.title">
                            <article data-reveal class="rounded-[24px] border border-pine-900/10 bg-cream p-5 shadow-card sm:p-6">
                                <span class="grid h-11 w-11 place-items-center rounded-full bg-pine-900 text-acid" x-html="feature.icon"></span>
                                <h3 class="mt-5 font-display text-2xl font-semibold text-pine-950" x-text="feature.title"></h3>
                                <p class="mt-3 text-sm leading-6 text-pine-900/58" x-text="feature.copy"></p>
                            </article>
                        </template>
                    </div>

                    <div class="mt-16 grid gap-4 lg:grid-cols-[1.25fr_.75fr]">
                        <button @click="openGallery(0)" data-reveal class="gallery-main relative min-h-[430px] overflow-hidden bg-pine-900 text-left shadow-lift sm:min-h-[560px]">
                            <img id="story-image" src="<?= e($expGallery[1] ?? $expImg) ?>" alt="<?= e($expVenue) ?>" class="absolute inset-0 h-full w-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-pine-950/80 via-transparent to-transparent"></div>
                            <div class="absolute bottom-0 left-0 right-0 p-6 text-white sm:p-8">
                                <p class="text-[10px] font-bold uppercase tracking-[.2em] text-acid"><?= e($expVenue) ?></p>
                                <h3 class="mt-2 max-w-md font-display text-3xl font-semibold sm:text-4xl">Vezi locul înainte să ajungi.</h3>
                            </div>
                        </button>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                            <button @click="openGallery(2)" data-reveal class="gallery-small-a group relative min-h-[230px] overflow-hidden text-left sm:min-h-[280px] lg:min-h-0">
                                <img src="<?= e($expGallery[2] ?? $expImg) ?>" alt="<?= e($expTitle) ?>" class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105">
                                <div class="absolute inset-0 bg-gradient-to-t from-pine-950/75 via-transparent to-transparent"></div>
                                <span class="absolute bottom-5 left-5 rounded-full bg-cream px-3 py-2 text-xs font-bold text-pine-950">Vezi galeria</span>
                            </button>
                            <button @click="openGallery(3)" data-reveal class="gallery-small-b group relative min-h-[230px] overflow-hidden text-left sm:min-h-[280px] lg:min-h-0">
                                <img src="<?= e($expGallery[3] ?? $expImg) ?>" alt="<?= e($expVenue) ?>" class="absolute inset-0 h-full w-full object-cover transition duration-700 group-hover:scale-105">
                                <div class="absolute inset-0 bg-gradient-to-t from-pine-950/75 via-transparent to-transparent"></div>
                                <span class="absolute bottom-5 left-5 text-xs font-bold uppercase tracking-[.18em] text-white"><?= count($expGallery) ?> fotografii</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Desktop booking card -->
                <aside class="hidden lg:block">
                    <div class="sticky-booking sticky top-28 rounded-[28px] p-5 xl:p-6">
                        <div class="flex items-start justify-between gap-5">
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-[.2em] text-pine-700">Rezervă</p>
                                <div class="mt-2 flex items-end gap-2"><strong class="font-display text-4xl text-pine-950" x-text="lvUnit"><?= e($expPrice) ?></strong><span class="pb-1 text-xs text-pine-900/50"><?= e($expUnit) ?></span></div>
                            </div>
                            <span x-show="lvStrip.length" class="rounded-full bg-acid px-3 py-1.5 text-[10px] font-bold uppercase tracking-[.14em] text-pine-950">disponibil</span>
                        </div>
<?php $lvCompact = true; include __DIR__ . '/../layout/booking-steps.php'; ?>
                        <div class="mt-5 flex items-center justify-between border-t border-pine-900/10 pt-5">
                            <div><span class="block text-[10px] uppercase tracking-[.15em] text-pine-900/45">Total estimat</span><strong class="font-display text-3xl text-pine-950" x-text="lvTotal"></strong></div>
                            <span class="text-right text-[10px] leading-4 text-pine-900/42">TVA inclus<br>Plată securizată</span>
                        </div>

                        <button @click="lvAddToCart()" :disabled="!lvReady" class="magnetic mt-5 inline-flex min-h-[54px] w-full items-center justify-center gap-2 rounded-full bg-pine-900 px-5 font-bold text-white transition hover:-translate-y-1 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:translate-y-0">
                            Continuă rezervarea
                            <svg class="h-4 w-4 text-acid" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
                        </button>
                        <p x-show="lvError" x-text="lvError" class="mt-3 text-center text-xs font-semibold text-ember"></p>
                        <p class="mt-3 text-center text-[10px] leading-4 text-pine-900/42">Locul se blochează când finalizezi comanda.</p>
                    </div>
                </aside>
            </div>
        </section>

        <!-- Bine de știut -->
        <section id="siguranta" class="paper-grid bg-oat px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="mx-auto grid max-w-[1420px] gap-10 lg:grid-cols-[.95fr_1.05fr] lg:items-center lg:gap-16">
                <div data-reveal class="relative min-h-[520px] overflow-hidden rounded-[30px] bg-pine-900 shadow-lift sm:min-h-[650px]">
                    <img id="safety-image" src="<?= e($expGallery[3] ?? $expImg) ?>" alt="<?= e($expVenue) ?>" class="absolute inset-0 h-full w-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-pine-950 via-transparent to-pine-950/15"></div>
                    <div class="absolute bottom-5 left-5 right-5 rounded-[24px] border border-white/15 bg-pine-950/72 p-5 text-white backdrop-blur-xl sm:bottom-8 sm:left-8 sm:right-8 sm:p-6">
                        <div class="flex items-start gap-4">
                            <span class="grid h-12 w-12 flex-none place-items-center rounded-full bg-acid text-pine-950">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 11a8 8 0 1 1-16 0V5l8-3 8 3v6Z"/><path d="m9 11 2 2 4-4"/></svg>
                            </span>
                            <div><h3 class="font-display text-2xl font-semibold">Echipa e acolo pentru tine.</h3><p class="mt-2 text-sm leading-6 text-white/[.58]">La intrare și pe tot parcursul vizitei ai oameni ai parcului la care poți apela oricând.</p></div>
                        </div>
                    </div>
                </div>

                <div>
                    <div data-reveal>
                        <p class="text-[10px] font-bold uppercase tracking-[.24em] text-pine-700 sm:text-xs">Înainte de vizită</p>
                        <h2 class="mt-4 pb-1 font-display text-[clamp(42px,7vw,72px)] font-semibold leading-[.98] tracking-[-.045em] text-pine-950">Bine de <span class="italic text-pine-600">știut.</span></h2>
                        <p class="mt-6 max-w-xl text-base leading-8 text-pine-900/64">Câteva lucruri care fac vizita mai simplă, pentru tine și pentru cei din jur.</p>
                    </div>

                    <div class="mt-9 grid gap-3 sm:grid-cols-2">
                        <template x-for="requirement in requirements" :key="requirement.title">
                            <div data-reveal class="rounded-[22px] border border-pine-900/10 bg-cream p-5">
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 grid h-7 w-7 flex-none place-items-center rounded-full bg-pine-900 text-acid">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 12 4 4L19 6"/></svg>
                                    </span>
                                    <div><strong class="block text-sm" x-text="requirement.title"></strong><p class="mt-1 text-xs leading-5 text-pine-900/48" x-text="requirement.copy"></p></div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div data-reveal class="mt-6 rounded-[24px] border border-ember/25 bg-ember/10 p-5 sm:p-6">
                        <div class="flex items-start gap-4">
                            <span class="grid h-10 w-10 flex-none place-items-center rounded-full bg-ember text-pine-950">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4m0 4h.01M10.3 3.8 2.9 17a2 2 0 0 0 1.8 3h14.6a2 2 0 0 0 1.8-3L13.7 3.8a2 2 0 0 0-3.4 0Z"/></svg>
                            </span>
                            <div><strong class="block text-sm">Vremea poate schimba programul</strong><p class="mt-1 text-xs leading-5 text-pine-900/58">La vânt puternic sau furtună, unele activități se pot amâna. Te anunțăm pe email și îți propunem altă zi.</p></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($expRelated): ?>
        <!-- Related -->
        <section class="bg-oat px-4 pb-20 sm:px-6 sm:pb-28 lg:px-8">
            <div class="mx-auto max-w-[1420px]">
                <div data-reveal class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[.24em] text-pine-700 sm:text-xs">Mai departe</p>
                        <h2 class="mt-4 pb-1 font-display text-[clamp(42px,7vw,72px)] font-semibold leading-[.98] tracking-[-.045em] text-pine-950">Adaugă la <span class="italic text-pine-600">aceeași zi.</span></h2>
                    </div>
                    <a href="/experiente" class="inline-flex items-center gap-2 text-sm font-bold text-pine-900">Vezi toate experiențele <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></a>
                </div>

                <div class="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    <template x-for="item in related" :key="item.url">
                        <a :href="item.url" data-reveal class="tilt-card group block">
                            <article class="tilt-card__surface overflow-hidden rounded-[28px] border border-pine-900/10 bg-cream shadow-card">
                                <div class="relative aspect-[4/3] overflow-hidden">
                                    <img :src="item.image" :alt="item.title" class="h-full w-full object-cover" loading="lazy">
                                    <div class="absolute inset-0 bg-gradient-to-t from-pine-950/75 via-transparent to-transparent"></div>
                                    <span class="absolute left-4 top-4 rounded-full bg-cream px-3 py-1.5 text-[9px] font-bold uppercase tracking-[.14em] text-pine-950" x-text="item.badge"></span>
                                    <div class="absolute bottom-4 left-4 right-4 flex items-end justify-between gap-4 text-white">
                                        <h3 class="font-display text-3xl font-semibold" x-text="item.title"></h3>
                                        <span class="grid h-10 w-10 flex-none place-items-center rounded-full bg-acid text-pine-950 transition group-hover:rotate-45"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17 17 7M8 7h9v9"/></svg></span>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between gap-4 p-5">
                                    <span class="text-xs text-pine-900/48" x-text="item.duration"></span>
                                    <strong class="font-display text-xl text-pine-950" x-text="item.price"></strong>
                                </div>
                            </article>
                        </a>
                    </template>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- FAQ -->
        <section id="faq" class="bg-cream px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="mx-auto grid max-w-[1180px] gap-12 lg:grid-cols-[.68fr_1.32fr] lg:gap-16">
                <div data-reveal>
                    <p class="text-[10px] font-bold uppercase tracking-[.24em] text-pine-700 sm:text-xs">Înainte să rezervi</p>
                    <h2 class="mt-4 pb-1 font-display text-[clamp(42px,7vw,68px)] font-semibold leading-[.98] tracking-[-.045em] text-pine-950">Întrebări <span class="italic text-pine-600">firești.</span></h2>
                    <p class="mt-6 text-sm leading-7 text-pine-900/56">Pentru situații medicale, accesibilitate sau grupuri speciale, scrie-ne înainte de rezervare.</p>
                    <a href="<?= e($contactUrl) ?>" class="mt-6 inline-flex items-center gap-2 text-sm font-bold text-pine-900">Contactează echipa <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></a>
                </div>

                <div class="divide-y divide-pine-900/10 border-y border-pine-900/10">
                    <template x-for="(item, index) in faqs" :key="item.q">
                        <article data-reveal>
                            <button @click="faqOpen = faqOpen === index ? null : index" class="flex w-full items-center justify-between gap-5 py-5 text-left sm:py-6">
                                <span class="font-display text-xl font-semibold text-pine-950 sm:text-2xl" x-text="item.q"></span>
                                <span class="grid h-9 w-9 flex-none place-items-center rounded-full border border-pine-900/15 transition" :class="faqOpen === index ? 'rotate-45 bg-pine-900 text-acid' : ''"><svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg></span>
                            </button>
                            <div x-show="faqOpen === index" x-collapse><p class="max-w-3xl pb-6 pr-12 text-sm leading-7 text-pine-900/58" x-text="item.a"></p></div>
                        </article>
                    </template>
                </div>
            </div>
        </section>

        <!-- Final CTA -->
        <section class="grain topo-dark relative overflow-hidden bg-pine-950 px-4 py-16 text-white sm:px-6 sm:py-20 lg:px-8">
            <div class="relative z-10 mx-auto flex max-w-[1420px] flex-col gap-8 rounded-[30px] border border-white/10 bg-white/[.045] p-6 sm:p-10 lg:flex-row lg:items-center lg:justify-between lg:p-12">
                <div data-reveal>
                    <p class="text-[10px] font-bold uppercase tracking-[.24em] text-acid sm:text-xs"><?= e($expTitle) ?></p>
                    <h2 class="mt-3 max-w-3xl pb-1 font-display text-[clamp(38px,6vw,68px)] font-semibold leading-[.98] tracking-[-.045em]">Ziua ta în parc începe cu o <span class="outline-word italic">rezervare.</span></h2>
                </div>
                <div data-reveal class="flex flex-col gap-3 sm:flex-row lg:flex-col xl:flex-row">
                    <button @click="openBooking()" class="magnetic inline-flex min-h-[56px] items-center justify-center whitespace-nowrap rounded-full bg-acid px-7 font-bold text-pine-950 shadow-acid transition hover:-translate-y-1">Alege data</button>
                    <a href="/experiente" class="inline-flex min-h-[56px] items-center justify-center whitespace-nowrap rounded-full border border-white/20 px-7 font-semibold text-white transition hover:bg-white/10">Înapoi la experiențe</a>
                </div>
            </div>
        </section>
    </main>

    <!-- Mobile sticky CTA -->
    <div class="safe-bottom fixed inset-x-0 bottom-0 z-[110] border-t border-pine-900/10 bg-cream/95 px-3 pb-3 pt-3 shadow-[0_-18px_55px_-35px_rgb(var(--c-pine-950)_/_.65)] backdrop-blur-xl lg:hidden">
        <div class="mx-auto flex max-w-xl items-center gap-3">
            <div class="min-w-0 flex-1 pl-1"><span class="block truncate text-[9px] font-bold uppercase tracking-[.15em] text-pine-900/42"><?= e($expTitle) ?> · de la</span><strong class="block truncate font-display text-2xl text-pine-950"><?= e($expPrice) ?></strong></div>
            <button @click="openBooking()" class="inline-flex min-h-[50px] flex-none items-center justify-center whitespace-nowrap rounded-full bg-pine-900 px-5 text-sm font-bold text-white"><span>Rezervă</span><svg class="ml-2 h-4 w-4 text-acid" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
        </div>
    </div>

    <!-- Booking drawer / mobile bottom sheet -->
    <div x-cloak x-show="bookingOpen" class="fixed inset-0 z-[150]" role="dialog" aria-modal="true" aria-label="Rezervă <?= e($expTitle) ?>">
        <div x-show="bookingOpen" x-transition.opacity @click="bookingOpen = false" class="absolute inset-0 bg-pine-950/70 backdrop-blur-sm"></div>
        <aside x-show="bookingOpen" x-transition:enter="transition duration-500 ease-out" x-transition:enter-start="translate-y-full lg:translate-x-full lg:translate-y-0" x-transition:enter-end="translate-y-0 lg:translate-x-0" x-transition:leave="transition duration-350 ease-in" x-transition:leave-start="translate-y-0 lg:translate-x-0" x-transition:leave-end="translate-y-full lg:translate-x-full lg:translate-y-0" class="drawer-shadow safe-bottom absolute bottom-0 left-0 right-0 max-h-[92svh] overflow-y-auto rounded-t-[28px] bg-cream p-4 sm:p-6 lg:bottom-0 lg:left-auto lg:top-0 lg:h-full lg:max-h-none lg:w-[min(92vw,520px)] lg:rounded-none lg:p-8">
            <div class="mx-auto mb-4 h-1.5 w-12 rounded-full bg-pine-900/15 lg:hidden"></div>
            <div class="flex items-start justify-between gap-5">
                <div><p class="text-[10px] font-bold uppercase tracking-[.2em] text-pine-700">Rezervare rapidă</p><h2 class="mt-2 font-display text-3xl font-semibold text-pine-950 sm:text-4xl"><?= e($expTitle) ?></h2><p class="mt-2 text-sm text-pine-900/50"><?= e($expDur) ?> · de la <?= e($expPrice) ?></p></div>
                <button @click="bookingOpen = false" class="grid h-11 w-11 flex-none place-items-center rounded-full border border-pine-900/15" aria-label="Închide rezervarea"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg></button>
            </div>
<?php $lvCompact = false; include __DIR__ . '/../layout/booking-steps.php'; ?>
            <div class="mt-6 rounded-[22px] bg-pine-900 p-5 text-white">
                <div class="flex items-end justify-between gap-4"><div><span class="block text-[9px] font-bold uppercase tracking-[.15em] text-white/40">Total estimat</span><strong class="mt-1 block font-display text-4xl text-acid" x-text="lvTotal"></strong></div><span class="pb-1 text-right text-xs text-white/50" x-text="lvWhen"></span></div>
                <button @click="lvAddToCart()" :disabled="!lvReady" class="magnetic mt-5 inline-flex min-h-[54px] w-full items-center justify-center gap-2 rounded-full bg-acid px-5 font-bold text-pine-950 transition hover:-translate-y-1 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:translate-y-0">Continuă rezervarea <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
                <p x-show="lvError" x-text="lvError" class="mt-3 text-center text-xs font-semibold text-acid"></p>
            </div>
        </aside>
    </div>

    <!-- Gallery modal -->
    <div x-cloak x-show="galleryOpen" class="fixed inset-0 z-[160] grid place-items-center bg-pine-950/95 p-3 sm:p-6" role="dialog" aria-modal="true">
        <button @click="galleryOpen = false" class="absolute right-4 top-4 z-20 grid h-11 w-11 place-items-center rounded-full border border-white/15 bg-pine-950/60 text-white backdrop-blur-xl sm:right-7 sm:top-7" aria-label="Închide galeria"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        <div class="relative h-[min(78svh,760px)] w-full max-w-[1200px] overflow-hidden rounded-[24px] bg-pine-900">
            <img :src="galleryImages[galleryIndex].src" :alt="galleryImages[galleryIndex].alt" class="h-full w-full object-cover">
            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-pine-950/90 to-transparent p-5 pt-24 text-white sm:p-8 sm:pt-32">
                <p class="text-[10px] font-bold uppercase tracking-[.2em] text-acid" x-text="(galleryIndex + 1) + ' / ' + galleryImages.length"></p>
                <h3 class="mt-2 font-display text-3xl font-semibold" x-text="galleryImages[galleryIndex].caption"></h3>
            </div>
            <button @click="galleryIndex = (galleryIndex - 1 + galleryImages.length) % galleryImages.length" class="absolute left-3 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-cream text-pine-950 sm:left-5" aria-label="Fotografia anterioară"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg></button>
            <button @click="galleryIndex = (galleryIndex + 1) % galleryImages.length" class="absolute right-3 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full bg-cream text-pine-950 sm:right-5" aria-label="Fotografia următoare"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
        </div>
    </div>

    
    <script>
        function experienceDetail() {
            return LVBooking.with(<?= json_encode($expBooking, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>, {
                menuOpen: false,
                bookingOpen: false,
                galleryOpen: false,
                galleryIndex: 0,
                faqOpen: 0,
                scrolled: false,

                features: [
                    { title: 'Rezervi în două minute', copy: 'Alegi ziua<?= $expCat === 'activity' ? ' și ora' : '' ?>, plătești cu cardul și primești confirmarea pe loc.', icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 2v4m8-4v4M3 10h18M5 5h14a2 2 0 0 1 2 2v12H3V7a2 2 0 0 1 2-2Z"/></svg>' },
                    { title: 'Intri direct', copy: 'Biletul ajunge pe email cu un cod QR. Îl scanăm la intrare, fără coadă la casă.', icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 18h2v2h-2z"/></svg>' },
                    { title: 'Locul e al tău', copy: 'Locurile sunt limitate pe fiecare zi<?= $expCat === 'activity' ? ' și interval' : '' ?>. Ce ai rezervat te așteaptă.', icon: '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 11a8 8 0 1 1-16 0V5l8-3 8 3v6Z"/><path d="m9 11 2 2 4-4"/></svg>' }
                ],

                requirements: [
                    { title: 'Vino cu 15 minute mai devreme', copy: 'Ai timp de parcare, intrare și, unde e cazul, de echipare.' },
                    { title: 'Biletul pe telefon', copy: 'Codul QR din email e suficient; nu trebuie printat.' },
                    { title: 'Haine și încălțăminte comode', copy: 'Potrivite pentru mers prin parc și pentru vremea zilei.' },
                    { title: 'Copiii, însoțiți', copy: 'Minorii intră în parc împreună cu un adult.' },
                    { title: 'Respectă indicațiile echipei', copy: 'Pentru siguranța ta și a celorlalți vizitatori.' },
                    { title: 'Lasă locul curat', copy: 'Folosește coșurile de gunoi și traseele marcate.' }
                ],

                related: <?= json_encode($expRelated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>,
                faqs: <?= json_encode($expFaqs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
                galleryImages: <?= json_encode($expGalleryJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>,

                init() {
                    this.scrolled = window.scrollY > 24;
                    window.addEventListener('scroll', () => { this.scrolled = window.scrollY > 24; }, { passive: true });
                    this.$watch('menuOpen', () => this.syncBodyLock());
                    this.$watch('bookingOpen', () => this.syncBodyLock());
                    this.$watch('galleryOpen', () => this.syncBodyLock());
                    this.lvInit();
                },
                syncBodyLock() { document.body.style.overflow = this.menuOpen || this.bookingOpen || this.galleryOpen ? 'hidden' : ''; },
                openBooking() { this.bookingOpen = true; },
                openGallery(index = 0) { this.galleryIndex = index % this.galleryImages.length; this.galleryOpen = true; }
            });
        }
    </script>

    <script type="module">
        import { animate, scroll, inView, stagger } from 'https://cdn.jsdelivr.net/npm/motion@12.42.1/+esm';

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.clearTimeout(window.__revealSafety);

        const revealEverything = () => {
            document.documentElement.classList.remove('reveal-ready');
            document.querySelectorAll('[data-reveal], [data-hero-kicker], [data-hero-title], [data-hero-copy], [data-hero-visual]').forEach(el => {
                el.style.opacity = '1';
                el.style.transform = 'none';
            });
        };

        if (!reduceMotion) {
            animate('[data-hero-kicker]', { opacity: [0, 1], y: [22, 0] }, { duration: .72, delay: stagger(.08, { startDelay: .1 }), ease: [0.22, 1, 0.36, 1] });
            animate('[data-hero-title]', { opacity: [0, 1], y: [52, 0] }, { duration: 1.04, delay: stagger(.12, { startDelay: .22 }), ease: [0.16, 1, 0.3, 1] });
            animate('[data-hero-copy]', { opacity: [0, 1], y: [22, 0] }, { duration: .84, delay: stagger(.08, { startDelay: .52 }), ease: [0.22, 1, 0.36, 1] });
            animate('[data-hero-visual]', { opacity: [0, 1], x: [58, 0], rotate: [1.2, 0] }, { duration: 1.18, delay: .4, ease: [0.16, 1, 0.3, 1] });

            document.querySelectorAll('[data-reveal]').forEach((element) => {
                inView(element, () => {
                    animate(element, { opacity: [0, 1], y: [34, 0] }, { duration: .82, ease: [0.22, 1, 0.36, 1] });
                }, { margin: '0px 0px -9% 0px', amount: .12 });
            });

            const hero = document.querySelector('#top');
            const story = document.querySelector('#poveste');
            const safety = document.querySelector('#siguranta');
            if (hero) scroll(animate('#hero-image', { y: ['0%', '11%'], scale: [1.02, 1.1] }), { target: hero, offset: ['start start', 'end start'] });
            if (story) scroll(animate('#story-image', { y: ['-4%', '8%'], scale: [1.05, 1.12] }), { target: story, offset: ['start end', 'end start'] });
            if (safety) scroll(animate('#safety-image', { y: ['-5%', '7%'], scale: [1.04, 1.12] }), { target: safety, offset: ['start end', 'end start'] });

            const heroRoute = document.querySelector('#hero-route');
            const heroMarker = document.querySelector('#hero-marker');
            if (window.anime && heroRoute && heroMarker) {
                const path = anime.path('#hero-route');
                anime({ targets: '#hero-route', strokeDashoffset: [anime.setDashoffset, -260], easing: 'linear', duration: 17000, loop: true });
                anime({ targets: heroMarker, translateX: path('x'), translateY: path('y'), rotate: path('angle'), easing: 'easeInOutSine', duration: 7400, loop: true, direction: 'alternate' });
            }

            const routeProgress = document.querySelector('#route-progress-path');
            if (routeProgress && window.anime) {
                routeProgress.style.strokeDasharray = routeProgress.getTotalLength();
                routeProgress.style.strokeDashoffset = routeProgress.getTotalLength();
                inView(routeProgress, () => {
                    anime({ targets: routeProgress, strokeDashoffset: [anime.setDashoffset, 0], easing: 'easeInOutCubic', duration: 2200 });
                }, { amount: .3 });
            }

            if (window.matchMedia('(hover:hover) and (pointer:fine)').matches) {
                document.querySelectorAll('.tilt-card').forEach(card => {
                    const surface = card.querySelector('.tilt-card__surface');
                    card.addEventListener('pointermove', event => {
                        const rect = card.getBoundingClientRect();
                        const x = (event.clientX - rect.left) / rect.width - .5;
                        const y = (event.clientY - rect.top) / rect.height - .5;
                        surface.style.transform = `rotateY(${x * 4.5}deg) rotateX(${-y * 4.5}deg) translateY(-4px)`;
                    });
                    card.addEventListener('pointerleave', () => { surface.style.transform = ''; });
                });
            }
        } else {
            revealEverything();
        }

        scroll(progress => {
            const line = document.querySelector('#page-progress');
            if (line) line.style.transform = `scaleX(${progress})`;
        });

        window.setTimeout(revealEverything, 3400);
    </script>
<?php include ts_layout('footer');
