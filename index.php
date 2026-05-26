<?php
/**
 * bilete.online — Homepage
 * Path: /
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// =========================================================================
// DATA — event categories (parinte) + activitati publicate
// =========================================================================
// "Alege-ți genul de aventură" pulls top-level EVENT categories from
// marketplace_event_categories (parent_id IS NULL). The endpoint accepts
// `all=1` to bypass the "has-published-event" gate (without it the rail
// would be empty when the marketplace has only activities), and
// `parents_only=1` to skip subcategories.
$categoriesResp = api_cached('home_event_top_categories', fn () => api_get('/events/categories', ['all' => 1, 'parents_only' => 1]), 600);
$rawCategories = $categoriesResp['data']['categories'] ?? [];
$homeCategories = [];
foreach ((is_array($rawCategories) ? $rawCategories : []) as $c) {
    $homeCategories[] = [
        't'     => $c['name'] ?? $c['slug'] ?? '',
        'd'     => $c['description'] ?? '',
        'url'   => '/' . ($c['slug'] ?? ''),
        'count' => isset($c['event_count']) && (int) $c['event_count'] > 0 ? (int) $c['event_count'] . ' opțiuni' : '',
        'emoji' => $c['icon_emoji'] ?? null,
        'color' => $c['color'] ?? null,
    ];
}

// Featured published activities for the "Experiențe de pus în calendar" rail.
// Sorted by featured first then by cheapest_price ascending so the rail surfaces
// promoted experiences without manual curation.
$activitiesResp = api_cached('home_featured_activities', fn () => api_get('/activities', ['sort' => 'recent', 'per_page' => 8]), 300);
$rawActivities = $activitiesResp['data']['items'] ?? [];
$homeActivities = [];
foreach ((is_array($rawActivities) ? $rawActivities : []) as $a) {
    $cents = (int) ($a['cheapest_price_cents'] ?? 0);
    $priceLei = $cents > 0 ? round($cents / 100) : 0;
    $homeActivities[] = [
        't'      => $a['title'] ?? '',
        'city'   => $a['city']['name'] ?? '',
        'tag'    => $a['category']['name'] ?? 'Activitate',
        'cat'    => $a['category']['slug'] ?? 'all',
        'price'  => $priceLei,
        'url'    => '/activitate/' . ($a['slug'] ?? ''),
        'img'    => $a['cover_image_url'] ?? null,
        'c'      => 'vermilion',
        'dur'    => isset($a['duration_minutes']) ? ((int) $a['duration_minutes']) . ' min' : '—',
    ];
}

// =========================================================================
// SEO
// =========================================================================
$pageTitleRaw = 'bilete.online — Bilete pentru escape rooms, muzee, parcuri de distracții & experiențe de agrement în România';
$pageDescription = 'Descoperă și cumpără bilete pentru escape rooms, parcuri de distracții, muzee, parcuri de aventură și experiențe de agrement din toată România. Rezervi în 30 de secunde, intri cu QR. Platformă operată de Tixello.';
$pageKeywords = 'bilete online, escape room bilete, bilete muzee, parc de distracții bilete, bilete experiențe agrement, parc aventură bilete, bilete activități familie România';
$canonicalUrl = SITE_URL . '/';
$ogImage = SITE_URL . '/assets/images/og-home.jpg';
$cssBundle = 'home';
$currentPage = 'home';

// Per-homepage JSON-LD: FAQ
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => [
        ['@type' => 'Question', 'name' => 'Ce tip de bilete găsesc pe bilete.online?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Bilete pentru experiențe de agrement: escape rooms, parcuri de distracții, muzee, parcuri de aventură, acvarii, grădini zoologice, planetarii și ateliere — toate locurile unde te duci să faci ceva.']],
        ['@type' => 'Question', 'name' => 'Cum primesc biletul după cumpărare?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Imediat după plată primești biletul cu cod QR pe email și în cont. La intrare doar îl scanezi — fără tipărire obligatorie.']],
        ['@type' => 'Question', 'name' => 'Pot anula sau reprograma un bilet?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Politica de anulare este stabilită de fiecare locație și e afișată clar pe pagina experienței înainte de plată.']],
        ['@type' => 'Question', 'name' => 'Sunt owner de locație. Cum îmi listez activitatea?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Îți creezi cont de organizator, primești un landing page dedicat optimizat SEO și începi să vinzi cu un comision de 1%. Platforma e operată tehnologic de Tixello.']],
        ['@type' => 'Question', 'name' => 'Pot oferi un card cadou?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Da. Cumperi un card cadou de orice valoare, scrii un mesaj personalizat și ajunge instant pe email. Se poate folosi la orice locație de pe platformă, timp de 12 luni.']],
        ['@type' => 'Question', 'name' => 'E nevoie de cont pentru a cumpăra?',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Nu. Poți cumpăra ca invitat doar cu un email. Contul îți ajută însă să-ți regăsești biletele și istoricul.']],
    ],
]];

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- ====================================================== HERO ====================================================== -->
<section class="relative overflow-hidden" aria-labelledby="hero-h1">
    <div class="absolute inset-0 -z-10 bg-gradient-to-b from-paper via-paper to-paper-2"></div>
    <div class="absolute -z-10 -top-32 -right-40 w-[640px] h-[640px] rounded-full bg-vermilion/10 blur-3xl" aria-hidden="true"></div>
    <div class="absolute -z-10 top-40 -left-40 w-[520px] h-[520px] rounded-full bg-forest/10 blur-3xl" aria-hidden="true"></div>
    <div class="absolute inset-0 -z-10 opacity-[.35] bg-ruled-vertical" aria-hidden="true"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 lg:pt-16 pb-20 grid lg:grid-cols-[1.05fr_.95fr] gap-10 lg:gap-6 items-center">

        <!-- left: headline + search -->
        <div>
            <div class="rise inline-flex items-center gap-2 mb-6 pl-1 pr-3 py-1 rounded-full bg-ink/5 border border-ink/10 text-xs font-mono tracking-wider" style="animation-delay:.05s">
                <span class="stamp text-forest px-2 py-0.5 text-[10px] font-600 -rotate-3">NOU</span>
                peste 1.200 de locuri de descoperit în toată România
            </div>

            <h1 id="hero-h1" class="rise font-display font-700 leading-[0.92] tracking-tight text-[clamp(2.6rem,7vw,5.4rem)]" style="animation-delay:.1s">
                Permisul tău<br>
                pentru <span class="ital text-vermilion relative inline-block">lucruri de făcut
                    <svg class="absolute -bottom-2 left-0 w-full" viewBox="0 0 300 12" fill="none" preserveAspectRatio="none" aria-hidden="true"><path d="M2 9C70 3 150 3 298 7" stroke="#DA9A33" stroke-width="3" stroke-linecap="round"/></svg>
                </span>.
            </h1>

            <p class="rise mt-7 text-lg sm:text-xl text-ink-soft max-w-xl leading-relaxed" style="animation-delay:.18s">
                Escape rooms, muzee, parcuri de distracții, aventură pe sus, ateliere și locuri de descoperit.
                Cauți, rezervi în 30 de secunde și <strong class="text-ink font-600">intri direct cu QR</strong>.
            </p>

            <!-- search card (Alpine, shares global store with the catalog below) -->
            <div class="rise mt-9 max-w-xl" style="animation-delay:.26s"
                 x-data="{ q:'', cat:'all', go(){ $store.catalog.query=this.q; $store.catalog.category=this.cat; document.getElementById('experiente').scrollIntoView({behavior:'smooth'}); } }">
                <form @submit.prevent="go()" role="search" aria-label="Caută bilete">
                    <div class="ticket bg-paper border-2 border-ink rounded-2xl p-2 shadow-card-cta flex flex-col sm:flex-row items-stretch gap-2" style="--punch:#F4EFE3">
                        <label class="sr-only" for="hero-search">Termen de căutare</label>
                        <div class="flex-1 flex items-center gap-3 px-3">
                            <svg viewBox="0 0 24 24" class="w-5 h-5 text-ink-soft shrink-0" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                            <input id="hero-search" x-model="q" type="text" placeholder="Ce vrei să faci? (ex. escape room Cluj)" class="w-full bg-transparent py-3 text-base placeholder:text-ink-soft/60 focus:outline-none" />
                        </div>
                        <div class="relative sm:border-l-2 sm:border-dashed sm:border-ink/20">
                            <label class="sr-only" for="hero-cat">Categorie</label>
                            <select id="hero-cat" x-model="cat" class="appearance-none bg-transparent h-full pl-4 pr-9 py-3 text-base font-500 focus:outline-none cursor-pointer">
                                <option value="all">Orice categorie</option>
                                <option value="escape">Escape rooms</option>
                                <option value="parc">Parcuri de distracții</option>
                                <option value="muzeu">Muzee</option>
                                <option value="aventura">Parcuri de aventură</option>
                                <option value="acvariu">Acvarii &amp; zoo</option>
                                <option value="atelier">Experiențe &amp; ateliere</option>
                            </select>
                            <svg viewBox="0 0 24 24" class="w-4 h-4 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </div>
                        <button type="submit" class="bg-vermilion text-paper font-600 px-6 py-3 rounded-xl hover:bg-vermilion-d transition-colors duration-300 whitespace-nowrap">
                            Caută bilete
                        </button>
                    </div>
                </form>
                <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-ink-soft font-mono text-xs tracking-wide">POPULARE:</span>
                    <template x-for="s in ['Escape room București','Muzeu copii','Parc aventură Brașov','Acvariu Constanța']">
                        <button type="button" @click="q=s; go()" class="px-3 py-1 rounded-full bg-ink/5 hover:bg-ink hover:text-paper border border-ink/10 transition" x-text="s"></button>
                    </template>
                </div>
            </div>
        </div>

        <!-- right: stacked tickets visual -->
        <div class="rise relative h-[420px] sm:h-[500px] lg:h-[560px] hidden sm:block" style="animation-delay:.34s" aria-hidden="true">
            <article class="ticket ticket-lift absolute top-8 right-2 w-[78%] rotate-6 bg-forest text-paper rounded-2xl overflow-hidden border-2 border-ink shadow-2xl" style="--perf:70%; --punch:#1E4A3D">
                <div class="duotone text-forest-l h-32 bg-gradient-to-br from-forest-l to-forest">
                    <div class="grid-tex"></div>
                    <svg viewBox="0 0 24 24" class="w-14 h-14 absolute right-5 bottom-3 text-paper/30" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1M9 13h1M14 9h1M14 13h1M10 21v-4h4v4"/></svg>
                </div>
                <div class="p-5 flex justify-between items-end">
                    <div><p class="font-mono text-[10px] text-paper/60">MUZEU · IAȘI</p><h3 class="font-display text-2xl font-600 leading-tight">Aripa<br>Dinozaurilor</h3></div>
                </div>
                <div class="notch top"></div><div class="notch bot"></div><div class="perf"></div>
            </article>

            <article class="ticket ticket-lift absolute bottom-2 left-0 w-[80%] -rotate-6 bg-paper rounded-2xl overflow-hidden border-2 border-ink shadow-2xl z-10" style="--perf:68%; --punch:#F4EFE3">
                <div class="duotone text-vermilion h-36 bg-gradient-to-br from-vermilion to-vermilion-d">
                    <div class="grid-tex"></div>
                    <span class="stamp absolute top-4 left-4 text-paper/80 px-3 py-1 text-[10px] font-mono -rotate-6">ESCAPE</span>
                    <svg viewBox="0 0 24 24" class="w-16 h-16 absolute right-5 bottom-3 text-paper/30" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 14a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm2-3h10m-3-2v4"/></svg>
                </div>
                <div class="p-5">
                    <p class="font-mono text-[10px] text-ink-soft">ESCAPE ROOM · CLUJ-NAPOCA</p>
                    <h3 class="font-display text-2xl font-700 leading-tight mt-1">Camera 13</h3>
                    <div class="mt-4 flex items-center justify-between">
                        <div><p class="font-mono text-[10px] text-ink-soft">DE LA</p><p class="font-display text-3xl font-700">60<span class="text-base font-sans text-ink-soft"> lei</span></p></div>
                        <span class="px-4 py-2 rounded-full bg-ink text-paper text-sm font-600">Rezervă</span>
                    </div>
                </div>
                <div class="notch top"></div><div class="notch bot"></div><div class="perf"></div>
            </article>

            <div class="absolute top-0 left-6 z-20 stamp text-ochre bg-paper w-24 h-24 rounded-full grid place-items-center text-center -rotate-12 shadow-lg">
                <div class="font-mono text-[9px] leading-tight">INTRARE<br><span class="font-display text-lg font-700 text-ink">QR</span><br>VALABIL</div>
            </div>
        </div>
    </div>

    <!-- trust bar -->
    <div class="border-y border-ink/10 bg-paper/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-5 grid grid-cols-2 sm:grid-cols-4 gap-4 text-center"
             x-data="{ stats:[{n:'1.200+',l:'Locații &amp; activități'},{n:'42',l:'Orașe acoperite'},{n:'30s',l:'Timp mediu de rezervare'},{n:'1%',l:'Comision pentru organizatori'}] }">
            <template x-for="s in stats">
                <div data-stagger>
                    <p class="font-display text-3xl sm:text-4xl font-700 text-ink" x-text="s.n"></p>
                    <p class="text-xs sm:text-sm text-ink-soft mt-1" x-text="s.l"></p>
                </div>
            </template>
        </div>
    </div>
</section>

<!-- ====================================================== MARQUEE ====================================================== -->
<section class="bg-ink text-paper py-4 overflow-hidden marquee-wrap border-b border-ink" aria-hidden="true">
    <div class="flex whitespace-nowrap marquee">
        <template x-for="i in 2">
            <div class="flex items-center font-display text-2xl sm:text-3xl font-500 ital">
                <template x-for="w in ['Escape Rooms','Parcuri de distracții','Muzee','Aventură pe sus','Acvarii','Planetarii','Ateliere','Grădini zoo','Carduri cadou','Experiențe']">
                    <span class="flex items-center">
                        <span x-text="w" class="px-6"></span>
                        <span class="text-vermilion">✦</span>
                    </span>
                </template>
            </div>
        </template>
    </div>
</section>

<!-- ====================================================== CATEGORII ====================================================== -->
<section id="categorii" class="max-w-7xl mx-auto px-4 sm:px-6 py-20 lg:py-28" aria-labelledby="cat-h2">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-12">
        <div>
            <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-3">01 — EXPLOREAZĂ</p>
            <h2 id="cat-h2" class="font-display text-[clamp(2rem,5vw,3.4rem)] font-700 leading-[0.95]">Alege-ți<br>genul de aventură</h2>
        </div>
        <p class="text-ink-soft max-w-sm">Fiecare categorie are propria sa pagină dedicată, optimizată să fie găsită exact când cineva caută în Google.</p>
    </div>

    <?php if (empty($homeCategories)): ?>
        <div class="rounded-2xl border-2 border-ink bg-paper-2 p-8 text-center">
            <p class="font-display text-xl font-700">Nicio categorie configurată încă.</p>
            <p class="mt-2 text-ink-soft">Admin: adaugă categorii părinte la /marketplace/event-categories.</p>
        </div>
    <?php else: ?>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php
            // Color palette rotation — gives each card a unique tone. If the
            // category has its own color from DB (hex), we use that as inline
            // gradient; otherwise we cycle through 4 named palette options.
            $palette = ['vermilion', 'forest', 'sky', 'ochre'];
            $paletteClasses = [
                'vermilion' => 'bg-gradient-to-br from-vermilion to-vermilion-d',
                'forest'    => 'bg-gradient-to-br from-forest-l to-forest',
                'sky'       => 'bg-gradient-to-br from-sky to-ink',
                'ochre'     => 'bg-gradient-to-br from-ochre to-vermilion-d',
            ];

            foreach ($homeCategories as $idx => $cat):
                $tone = $palette[$idx % count($palette)];
                $bgClass = $paletteClasses[$tone];
                $emoji = $cat['emoji'] ?: '🎯';
                ?>
                <a href="<?= htmlspecialchars($cat['url']) ?>" class="ticket ticket-lift group bg-paper border-2 border-ink rounded-2xl overflow-hidden" style="--perf:100%">
                    <div class="duotone h-40 flex items-end p-5 <?= $bgClass ?> text-paper relative">
                        <div class="grid-tex"></div>
                        <span class="absolute right-4 top-4 text-5xl"><?= $emoji ?></span>
                        <?php if (! empty($cat['count'])): ?>
                            <span class="relative font-mono text-[10px] text-paper/80 tracking-wider"><?= htmlspecialchars(strtoupper($cat['count'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="p-5 flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-display text-2xl font-700 leading-tight"><?= htmlspecialchars($cat['t']) ?></h3>
                            <?php if (! empty($cat['d'])): ?>
                                <p class="text-sm text-ink-soft mt-1.5"><?= htmlspecialchars(mb_substr(strip_tags($cat['d']), 0, 110)) ?></p>
                            <?php endif; ?>
                        </div>
                        <span class="shrink-0 grid place-items-center w-9 h-9 rounded-full border-2 border-ink group-hover:bg-vermilion group-hover:border-vermilion group-hover:text-paper transition-colors duration-300">
                            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M7 17 17 7M9 7h8v8"/></svg>
                        </span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- ====================================================== CUM FUNCȚIONEAZĂ ====================================================== -->
<section id="cum" class="relative overflow-hidden bg-paper-2 border-y border-ink/10" aria-labelledby="cum-h2">
    <div class="absolute inset-0 opacity-[.4] bg-dotgrid-ink" aria-hidden="true"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 py-20 lg:py-28">
        <div class="text-center mb-12">
            <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-3">02 — SIMPLU CA UN BILET</p>
            <h2 id="cum-h2" class="font-display text-[clamp(2rem,5vw,3.4rem)] font-700 leading-[0.96]">Trei rupturi de bilet<br class="hidden sm:block"> și ești <span class="ital text-vermilion">înăuntru</span>.</h2>
        </div>

        <div class="ticket relative max-w-5xl mx-auto bg-paper border-2 border-ink rounded-3xl shadow-ticket overflow-hidden">
            <div class="flex items-center justify-between bg-ink text-paper px-5 sm:px-7 py-2.5 font-mono text-[10px] sm:text-xs tracking-wider">
                <span class="flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-vermilion"></span> BOARDING PASS · DISTRACȚIE</span>
                <span class="text-paper/50">bilete.online · ADMIT ONE</span>
            </div>

            <div class="grid md:grid-cols-3 relative">
                <div class="hidden md:block absolute top-6 bottom-6 left-1/3 border-l-2 border-dashed border-ink/25" aria-hidden="true"></div>
                <div class="hidden md:block absolute top-6 bottom-6 left-2/3 border-l-2 border-dashed border-ink/25" aria-hidden="true"></div>
                <div class="hidden md:block absolute left-1/3 -top-px w-5 h-5 rounded-full -translate-x-1/2 -translate-y-1/2 notch-paper2" aria-hidden="true"></div>
                <div class="hidden md:block absolute left-1/3 -bottom-px w-5 h-5 rounded-full -translate-x-1/2 translate-y-1/2 notch-paper2" aria-hidden="true"></div>
                <div class="hidden md:block absolute left-2/3 -top-px w-5 h-5 rounded-full -translate-x-1/2 -translate-y-1/2 notch-paper2" aria-hidden="true"></div>
                <div class="hidden md:block absolute left-2/3 -bottom-px w-5 h-5 rounded-full -translate-x-1/2 translate-y-1/2 notch-paper2" aria-hidden="true"></div>

                <div data-stagger class="relative p-7 sm:p-9">
                    <span class="absolute top-2 right-5 font-display text-[6.5rem] leading-none font-900 text-ink/[0.05] select-none pointer-events-none" aria-hidden="true">1</span>
                    <span class="relative grid place-items-center w-12 h-12 rounded-xl bg-vermilion/10 text-vermilion mb-5">
                        <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    </span>
                    <h3 class="font-display text-2xl font-700">Caută &amp; descoperă</h3>
                    <p class="text-ink-soft mt-2 leading-relaxed">Filtrezi după oraș, categorie sau pur și simplu după ce ai chef. Fiecare loc are pagina lui, cu poze, recenzii și prețuri clare.</p>
                </div>

                <div data-stagger class="relative p-7 sm:p-9" style="transition-delay:120ms">
                    <span class="absolute top-2 right-5 font-display text-[6.5rem] leading-none font-900 text-ink/[0.05] select-none pointer-events-none" aria-hidden="true">2</span>
                    <span class="relative grid place-items-center w-12 h-12 rounded-xl bg-ochre/15 text-ochre mb-5">
                        <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 9h18M7 3v4M17 3v4M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z"/></svg>
                    </span>
                    <h3 class="font-display text-2xl font-700">Alege data &amp; plătești</h3>
                    <p class="text-ink-soft mt-2 leading-relaxed">Selectezi ziua și ora disponibile și plătești securizat în câteva secunde. Poți cumpăra și ca invitat, fără cont.</p>
                </div>

                <div data-stagger class="relative p-7 sm:p-9" style="transition-delay:240ms">
                    <span class="absolute top-2 right-5 font-display text-[6.5rem] leading-none font-900 text-ink/[0.05] select-none pointer-events-none" aria-hidden="true">3</span>
                    <div class="relative w-12 h-12 rounded-xl bg-forest/10 mb-5 grid place-items-center overflow-hidden">
                        <svg viewBox="0 0 24 24" class="w-6 h-6 text-forest" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 4h6v6H4Zm10 0h6v6h-6ZM4 14h6v6H4Zm10 0h.01M17 14h3v3m0 3h-3v-3"/></svg>
                        <span class="scanline absolute left-1.5 right-1.5 h-0.5 bg-vermilion rounded-full"></span>
                    </div>
                    <h3 class="font-display text-2xl font-700">Intri cu QR</h3>
                    <p class="text-ink-soft mt-2 leading-relaxed">Primești biletul pe email și pe telefon, instant. La intrare doar îl scanezi — printarea e complet opțională.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================== EXPERIENȚE (filtrabile) ====================================================== -->
<section id="experiente" class="max-w-7xl mx-auto px-4 sm:px-6 py-20 lg:py-28" aria-labelledby="exp-h2"
    x-data='{
        items: <?= json_encode($homeActivities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        get filtered(){
            const q = ($store.catalog.query||"").toLowerCase().trim();
            const c = $store.catalog.category;
            return this.items.filter(it => {
                const okCat = c==="all" || it.cat===c;
                const okQ = !q || (it.t+" "+it.city+" "+it.tag).toLowerCase().includes(q);
                return okCat && okQ;
            });
        }
    }'>

    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-10">
        <div>
            <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-3">03 — SELECȚIA EDITORIALĂ</p>
            <h2 id="exp-h2" class="font-display text-[clamp(2rem,5vw,3.4rem)] font-700 leading-[0.95]">Experiențe<br>de pus în calendar</h2>
        </div>
        <a href="/cauta" class="self-start sm:self-end inline-flex items-center gap-2 font-600 underline-wobble">Vezi toate experiențele
            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
        </a>
    </div>

    <!-- filter chips bound to global store -->
    <div class="flex gap-2 overflow-x-auto no-bar pb-4 mb-8" role="tablist" aria-label="Filtrează după categorie">
        <template x-for="f in [{v:'all',l:'Toate'},{v:'escape',l:'Escape rooms'},{v:'parc',l:'Parcuri distracții'},{v:'muzeu',l:'Muzee'},{v:'aventura',l:'Aventură'},{v:'acvariu',l:'Acvarii &amp; zoo'},{v:'atelier',l:'Ateliere'}]">
            <button type="button" @click="$store.catalog.category=f.v" :aria-pressed="$store.catalog.category===f.v"
                    :class="$store.catalog.category===f.v ? 'bg-ink text-paper border-ink' : 'bg-transparent border-ink/20 hover:border-ink'"
                    class="shrink-0 px-4 py-2 rounded-full border-2 text-sm font-600 transition-colors duration-200" x-text="f.l"></button>
        </template>
    </div>

    <!-- grid -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <template x-for="it in filtered" :key="it.url">
            <a :href="it.url" class="ticket ticket-lift group bg-paper border-2 border-ink rounded-2xl overflow-hidden flex flex-col" style="--perf:100%">
                <div class="duotone h-32 p-4 flex items-start justify-between relative bg-gradient-to-br from-vermilion to-vermilion-d text-vermilion overflow-hidden">
                    <template x-if="it.img">
                        <img :src="it.img" :alt="it.t" class="absolute inset-0 w-full h-full object-cover mix-blend-multiply opacity-70" loading="lazy">
                    </template>
                    <div class="grid-tex"></div>
                    <span class="relative font-mono text-[10px] text-paper/90 bg-ink/25 px-2 py-1 rounded" x-text="it.tag"></span>
                    <span class="relative font-mono text-[11px] text-paper/90 flex items-center gap-1" x-show="it.dur">
                        <span x-text="it.dur"></span>
                    </span>
                </div>
                <div class="p-4 flex-1 flex flex-col">
                    <p class="font-mono text-[10px] text-ink-soft" x-text="it.city ? it.city.toUpperCase() : ''"></p>
                    <h3 class="font-display text-xl font-700 leading-tight mt-1" x-text="it.t"></h3>
                    <div class="mt-auto pt-4 flex items-center justify-between">
                        <p x-show="it.price > 0"><span class="text-xs text-ink-soft">de la </span><span class="font-display text-2xl font-700" x-text="it.price"></span><span class="text-sm text-ink-soft"> lei</span></p>
                        <span class="px-3 py-1.5 rounded-full bg-ink text-paper text-xs font-600 group-hover:bg-vermilion transition-colors">Rezervă</span>
                    </div>
                </div>
            </a>
        </template>
    </div>

    <?php if (empty($homeActivities)): ?>
        <div class="text-center py-16">
            <p class="font-display text-2xl font-600">Nicio activitate disponibilă încă.</p>
            <p class="text-ink-soft mt-2">Verifică mai târziu — în curând adăugăm experiențele noi.</p>
        </div>
    <?php endif; ?>

    <div x-show="filtered.length===0" x-cloak class="text-center py-16">
        <p class="font-display text-2xl font-600">Nimic pe căutarea asta — încă.</p>
        <p class="text-ink-soft mt-2">Încearcă altă categorie sau alt oraș.</p>
        <button type="button" @click="$store.catalog.category='all'; $store.catalog.query=''" class="mt-4 px-5 py-2.5 rounded-full bg-ink text-paper font-600">Resetează filtrele</button>
    </div>
</section>

<!-- ====================================================== CARD CADOU ====================================================== -->
<section id="card-cadou" class="relative overflow-hidden bg-paper border-y border-ink/10" aria-labelledby="cadou-h2">
    <div class="absolute -top-24 right-10 w-[460px] h-[460px] rounded-full bg-ochre/15 blur-3xl" aria-hidden="true"></div>
    <div class="absolute bottom-0 left-0 w-[380px] h-[380px] rounded-full bg-vermilion/10 blur-3xl" aria-hidden="true"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 py-20 lg:py-28 grid lg:grid-cols-2 gap-16 items-center"
         x-data="{ amount:150, custom:false, msg:'La mulți ani! Alege orice experiență ai chef.' }">

        <div data-stagger>
            <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-3">CARDURI CADOU</p>
            <h2 id="cadou-h2" class="font-display text-[clamp(2rem,5vw,3.6rem)] font-700 leading-[0.95]">Dăruiește o <span class="ital text-vermilion">experiență</span>,<br>nu încă un obiect.</h2>
            <p class="mt-5 text-lg text-ink-soft max-w-lg leading-relaxed">Un card cadou bilete.online se folosește la orice locație de pe platformă — de la escape rooms la muzee și parcuri de aventură. Tu alegi suma, ei aleg aventura.</p>

            <p class="mt-8 font-mono text-[10px] tracking-[.2em] text-ink-soft mb-3">ALEGE VALOAREA</p>
            <div class="flex flex-wrap gap-2">
                <template x-for="v in [50,100,150,200,300]" :key="v">
                    <button type="button" @click="amount=v; custom=false"
                            :class="(amount===v && !custom) ? 'bg-ink text-paper border-ink' : 'border-ink/20 hover:border-ink'"
                            class="px-5 py-2.5 rounded-full border-2 font-600 transition" x-text="v+' lei'"></button>
                </template>
                <button type="button" @click="custom=true; amount=500"
                        :class="custom ? 'bg-ink text-paper border-ink' : 'border-ink/20 hover:border-ink'"
                        class="px-5 py-2.5 rounded-full border-2 font-600 transition">Altă sumă</button>
            </div>
            <div x-show="custom" x-cloak class="mt-4 flex items-center gap-4">
                <input type="range" min="50" max="1000" step="50" x-model.number="amount" class="w-full accent-vermilion" aria-label="Valoare card cadou">
                <span class="font-display text-xl font-700 w-24 text-right shrink-0" x-text="amount+' lei'"></span>
            </div>

            <div class="mt-9 grid sm:grid-cols-3 gap-3">
                <template x-for="(s,i) in [
                    {t:'Alegi suma', ic:'M12 2v20m5-17H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'},
                    {t:'Scrii mesajul', ic:'M4 4h16v12H7l-3 3V4Z'},
                    {t:'Ajunge pe email', ic:'M3 5h18v14H3zM3 6l9 7 9-7'}
                ]" :key="i">
                    <div class="flex items-center gap-3 p-3 rounded-xl bg-paper-2/60 border border-ink/10">
                        <svg viewBox="0 0 24 24" class="w-5 h-5 text-vermilion shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path :d="s.ic"/></svg>
                        <span class="text-sm font-600" x-text="s.t"></span>
                    </div>
                </template>
            </div>

            <a href="/card-cadou" class="mt-9 inline-flex items-center gap-2 px-7 py-4 rounded-full bg-vermilion text-paper font-600 hover:bg-vermilion-d transition">
                Cumpără card cadou
                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </a>
        </div>

        <!-- live gift card preview -->
        <div data-stagger class="relative" style="transition-delay:120ms">
            <div class="ticket relative rounded-3xl overflow-hidden border-2 border-ink shadow-ticket-lg aspect-[1.6/1] bg-gradient-to-br from-ink via-forest to-ink text-paper" style="--perf:100%">
                <div class="absolute inset-0 foil opacity-50" aria-hidden="true"></div>
                <div class="absolute inset-0 opacity-20 bg-dotgrid-light-md" aria-hidden="true"></div>

                <div class="relative h-full p-6 sm:p-7 flex flex-col justify-between">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2">
                            <span class="grid place-items-center w-9 h-9 bg-vermilion rounded-md -rotate-3">
                                <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4Z"/></svg>
                            </span>
                            <span class="font-display text-lg font-700">bilete<span class="text-vermilion">.</span>online</span>
                        </div>
                        <span class="stamp text-paper/70 px-3 py-1 text-[10px] font-mono rotate-3">CADOU</span>
                    </div>

                    <div>
                        <p class="font-mono text-[10px] text-paper/50">VALOARE CARD</p>
                        <p class="font-display font-900 leading-none"><span class="text-5xl sm:text-6xl" x-text="amount"></span><span class="text-2xl font-sans font-500 text-paper/70"> lei</span></p>
                    </div>

                    <div class="flex items-end justify-between">
                        <div>
                            <p class="font-mono text-[10px] text-paper/40">COD</p>
                            <p class="font-mono text-sm tracking-widest">GIFT-7K2P-9XQM</p>
                        </div>
                        <div class="text-right">
                            <p class="font-mono text-[10px] text-paper/40">VALABIL</p>
                            <p class="font-mono text-sm">12 luni</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="absolute -bottom-6 -left-2 sm:-left-5 max-w-[240px] bg-paper border-2 border-ink rounded-2xl rounded-bl-none p-4 shadow-xl -rotate-3">
                <p class="font-mono text-[10px] text-ink-soft mb-1">MESAJUL TĂU</p>
                <p class="font-display text-[15px] leading-snug ital" x-text="'„'+msg+'”'"></p>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================== PENTRU OWNERI ====================================================== -->
<section id="owneri" class="relative bg-ink text-paper overflow-hidden" aria-labelledby="own-h2">
    <div class="absolute -top-20 -left-20 w-[460px] h-[460px] rounded-full bg-vermilion/15 blur-3xl" aria-hidden="true"></div>
    <div class="absolute bottom-0 right-0 w-[420px] h-[420px] rounded-full bg-forest/30 blur-3xl" aria-hidden="true"></div>
    <div class="absolute inset-0 opacity-[.06] bg-paper-grid" aria-hidden="true"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 py-20 lg:py-28 grid lg:grid-cols-2 gap-14 items-center">
        <div>
            <p class="font-mono text-xs tracking-[.2em] text-ochre mb-4">PENTRU LOCAȚII &amp; ORGANIZATORI</p>
            <h2 id="own-h2" class="font-display text-[clamp(2.1rem,5.5vw,4rem)] font-700 leading-[0.95]">
                Ai un loc unde<br>se întâmplă lucruri?<br>
                <span class="ital text-vermilion">Pune-l pe hartă.</span>
            </h2>
            <p class="mt-6 text-lg text-paper/70 max-w-lg leading-relaxed">
                bilete.online îți dă o vitrină online optimizată pentru Google, sistem de rezervări, scanare QR la intrare și plăți rapide.
                Toată tehnologia e construită și operată de <a href="https://tixello.ro" class="text-ochre underline-wobble" rel="noopener">Tixello</a> — tu te ocupi doar de experiență.
            </p>

            <div class="mt-8 grid sm:grid-cols-2 gap-4">
                <template x-for="b in [
                    {t:'Comision 1%', d:'Atât. Fără abonamente, fără costuri ascunse.', ic:'M12 2v20m5-17H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6'},
                    {t:'Landing page SEO dedicat', d:'O pagină proprie făcută să fie găsită în căutări.', ic:'M11 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14Zm9 16-3.5-3.5'},
                    {t:'Scanare QR la intrare', d:'Compatibil cu scannere USB (ex. Zebra DS2208) sau telefon.', ic:'M4 4h6v6H4Zm10 0h6v6h-6ZM4 14h6v6H4Zm10 0h.01M17 14h3v3m0 3h-3v-3'},
                    {t:'Plăți &amp; decont rapid', d:'Banii ajung direct, transparent, fără să fim intermediar de fonduri.', ic:'M3 9h18M3 5h18v14H3ZM7 15h4'}
                ]">
                    <div class="flex gap-3 p-4 rounded-xl bg-paper/[.04] border border-paper/10">
                        <span class="shrink-0 grid place-items-center w-10 h-10 rounded-lg bg-vermilion/15 text-vermilion">
                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path :d="b.ic"/></svg>
                        </span>
                        <div><h3 class="font-600" x-text="b.t"></h3><p class="text-sm text-paper/60 mt-0.5" x-text="b.d"></p></div>
                    </div>
                </template>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="/pentru-locatii" class="px-6 py-3.5 rounded-full bg-vermilion text-paper font-600 hover:bg-vermilion-d transition">Listează-ți locația gratuit</a>
                <a href="/pentru-locatii#demo" class="px-6 py-3.5 rounded-full border border-paper/25 font-600 hover:bg-paper/10 transition">Vezi un demo</a>
            </div>
        </div>

        <!-- owner dashboard mock ticket -->
        <div class="relative">
            <div class="ticket bg-paper text-ink rounded-2xl border-2 border-paper/10 shadow-2xl overflow-hidden" style="--perf:100%">
                <div class="bg-forest text-paper p-5 flex items-center justify-between">
                    <div><p class="font-mono text-[10px] text-paper/60">PANOU ORGANIZATOR</p><p class="font-display text-xl font-700">Escape Room Cluj</p></div>
                    <span class="stamp text-paper/80 px-3 py-1 text-[10px] font-mono">LIVE</span>
                </div>
                <div class="p-5 grid grid-cols-3 gap-3 border-b border-ink/10">
                    <template x-for="m in [{v:'342',l:'Bilete luna asta'},{v:'18.4k',l:'lei încasați'},{v:'4.9★',l:'Rating'}]">
                        <div class="text-center"><p class="font-display text-2xl font-700" x-text="m.v"></p><p class="text-[11px] text-ink-soft" x-text="m.l"></p></div>
                    </template>
                </div>
                <div class="p-5">
                    <p class="font-mono text-[10px] text-ink-soft mb-3">VÂNZĂRI ULTIMELE 7 ZILE</p>
                    <div class="flex items-end gap-2 h-24" x-data="{ bars:[40,65,55,80,70,95,60] }">
                        <template x-for="(h,idx) in bars">
                            <div class="flex-1 rounded-t-md bg-gradient-to-t from-vermilion-d to-vermilion" :style="`height:${h}%`"></div>
                        </template>
                    </div>
                </div>
                <div class="px-5 pb-5">
                    <div class="flex items-center justify-between p-3 rounded-lg bg-paper-2">
                        <span class="font-mono text-xs">Următoarea sesiune · 18:30</span>
                        <span class="text-xs font-600 text-forest">6/8 locuri</span>
                    </div>
                </div>
            </div>
            <div class="absolute -bottom-5 -left-5 stamp text-ochre bg-ink w-20 h-20 rounded-full grid place-items-center text-center rotate-12">
                <div class="font-mono text-[8px] leading-tight text-paper">POWERED<br>BY<br><span class="font-display text-sm font-700 text-ochre">TIXELLO</span></div>
            </div>
        </div>
    </div>
</section>

<!-- ====================================================== SEO CONTENT + FAQ ====================================================== -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-20 lg:py-28 grid lg:grid-cols-[1fr_.85fr] gap-14" aria-labelledby="about-h2">
    <div>
        <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-3">DESPRE PLATFORMĂ</p>
        <h2 id="about-h2" class="font-display text-[clamp(1.9rem,4vw,3rem)] font-700 leading-[0.98] mb-6">
            Locul în care găsești <span class="ital text-vermilion">ce să faci</span> în weekend, în vacanță sau într-o după-amiază liberă.
        </h2>
        <div class="prose-custom space-y-4 text-ink-soft leading-relaxed text-[17px] max-w-2xl">
            <p><strong class="text-ink font-600">bilete.online</strong> este platforma de bilete dedicată exclusiv experiențelor de agrement din România — locurile unde te duci efectiv să faci ceva: un <a href="/escape-rooms" class="text-vermilion underline-wobble">escape room</a> cu prietenii, o zi la <a href="/parcuri-de-distractii" class="text-vermilion underline-wobble">parcul de distracții</a> cu copiii, o vizită la <a href="/muzee" class="text-vermilion underline-wobble">muzeu</a> sau o tiroliană într-un <a href="/parcuri-aventura" class="text-vermilion underline-wobble">parc de aventură</a>.</p>
            <p>Fiecare locație are propria pagină detaliată, cu prețuri actualizate, program, disponibilitate în timp real și opțiunea de a rezerva pe loc. Căutarea funcționează după oraș, categorie sau pur și simplu după ce ai chef — fie că ești în <a href="/bucuresti" class="text-vermilion underline-wobble">București</a>, <a href="/cluj-napoca" class="text-vermilion underline-wobble">Cluj-Napoca</a>, <a href="/brasov" class="text-vermilion underline-wobble">Brașov</a> sau <a href="/constanta" class="text-vermilion underline-wobble">Constanța</a>.</p>
            <p>Platforma e operată tehnologic de <a href="https://tixello.ro" class="text-vermilion underline-wobble" rel="noopener">Tixello</a>, infrastructura de ticketing folosită de teatre, muzee și operatori de agrement din toată țara. Asta înseamnă bilete cu cod QR, scanare rapidă la intrare și plăți securizate — la un comision de doar 1% pentru organizatori.</p>
        </div>

        <div class="mt-10">
            <p class="font-mono text-xs tracking-[.2em] text-ink-soft mb-4">CĂUTĂRI POPULARE</p>
            <div class="flex flex-wrap gap-2">
                <?php
                $popularSearches = [
                    ['Escape room București', '/cauta?q=escape+room+bucuresti'],
                    ['Muzee pentru copii Cluj', '/cauta?q=muzee+copii+cluj'],
                    ['Parc aventură Brașov', '/cauta?q=parc+aventura+brasov'],
                    ['Acvariu Constanța', '/cauta?q=acvariu+constanta'],
                    ['Bilete planetariu Timișoara', '/cauta?q=planetariu+timisoara'],
                    ['Ateliere creative Sibiu', '/cauta?q=ateliere+sibiu'],
                    ['Parc distracții Oradea', '/cauta?q=parc+distractii+oradea'],
                    ['Grădina zoologică Iași', '/cauta?q=zoo+iasi'],
                ];
                foreach ($popularSearches as [$label, $href]):
                ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" class="px-3.5 py-1.5 rounded-full bg-paper-2 border border-ink/10 text-sm hover:bg-ink hover:text-paper transition"><?= htmlspecialchars($label) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- FAQ accordion -->
    <div x-data="{ active:0 }" aria-labelledby="faq-h2">
        <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-3">ÎNTREBĂRI FRECVENTE</p>
        <h2 id="faq-h2" class="font-display text-3xl font-700 mb-7">Bune de știut</h2>
        <div class="space-y-3">
            <template x-for="(f,i) in [
                {q:'Ce tip de bilete găsesc aici?', a:'Experiențe de agrement: escape rooms, parcuri de distracții, muzee, parcuri de aventură, acvarii, grădini zoologice, planetarii și ateliere — toate locurile unde te duci să faci ceva.'},
                {q:'Cum primesc biletul?', a:'Imediat după plată, pe email și în contul tău, cu un cod QR. La intrare doar îl scanezi de pe telefon — printarea e opțională.'},
                {q:'Pot anula sau reprograma?', a:'Politica de anulare este stabilită de fiecare locație și e afișată clar pe pagina experienței înainte de plată.'},
                {q:'Sunt owner de locație. Cum încep?', a:'Îți creezi cont de organizator, primești un landing page dedicat optimizat SEO și începi să vinzi cu un comision de 1%. Tehnologia e oferită de Tixello.'},
                {q:'Pot oferi un card cadou?', a:'Da. Cumperi un card cadou de orice valoare, scrii un mesaj personalizat și ajunge instant pe email. Se poate folosi la orice locație de pe platformă, timp de 12 luni.'},
                {q:'E nevoie de cont pentru a cumpăra?', a:'Nu. Poți cumpăra ca invitat doar cu un email. Contul îți ajută însă să-ți regăsești biletele și istoricul.'}
            ]" :key="i">
                <div class="border-2 border-ink rounded-xl overflow-hidden bg-paper">
                    <button type="button" @click="active = active===i ? null : i" :aria-expanded="active===i" class="w-full flex items-center justify-between gap-4 text-left px-5 py-4">
                        <span class="font-600 text-[17px]" x-text="f.q"></span>
                        <span class="shrink-0 grid place-items-center w-7 h-7 rounded-full border-2 border-ink transition-transform duration-300" :class="active===i && 'rotate-45 bg-vermilion border-vermilion text-paper'">
                            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        </span>
                    </button>
                    <div x-show="active===i" x-collapse x-cloak>
                        <p class="px-5 pb-5 text-ink-soft leading-relaxed" x-text="f.a"></p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</section>

<!-- ====================================================== CTA FINAL ====================================================== -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 pb-20" aria-labelledby="cta-h2">
    <div class="ticket relative bg-vermilion text-paper rounded-3xl overflow-hidden border-2 border-ink p-10 sm:p-14 text-center" style="--perf:100%; --punch:#F4EFE3">
        <div class="absolute inset-0 opacity-20 bg-dotgrid-cta" aria-hidden="true"></div>
        <div class="relative">
            <h2 id="cta-h2" class="font-display text-[clamp(2rem,5vw,3.6rem)] font-700 leading-[0.95]">Următoarea ta aventură<br>e la un bilet distanță.</h2>
            <p class="mt-4 text-paper/85 text-lg max-w-xl mx-auto">Caută, rezervă, intră cu QR. Atât.</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <a href="#experiente" class="px-7 py-4 rounded-full bg-ink text-paper font-600 hover:bg-ink-2 transition">Explorează experiențe</a>
                <a href="/pentru-locatii" class="px-7 py-4 rounded-full bg-paper text-ink font-600 hover:bg-paper-2 transition">Am o locație de listat</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
