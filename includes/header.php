<?php
/**
 * bilete.online — site header (v6 context-aware design)
 *
 * Opens <body>, renders the sticky single-bar header (logo, center command
 * search, context-aware mega menus for Explore / Activities / Inspiration,
 * language+currency popover, cart drawer, account dropdown, mobile drawer),
 * then opens <main id="top"> so pages drop their <section> blocks directly.
 * footer.php closes </main>, the footer and the doc.
 *
 * "Context-aware": the primary Explore button label, the search placeholder
 * and the Explore mega hero adapt to the page the header sits on. A page sets
 * the context BEFORE including this file via $headerContext (see below).
 *
 * Dynamic data (unchanged from v3 — all live):
 *   - Categories / cities pulled live from the API (navGetCategories/Cities),
 *     rendered server-side for SEO inside the mega menus + mobile drawer.
 *   - Guides (Inspiration mega) pulled from /blog-articles, with a curated
 *     intent-hub fallback when the guides API is empty.
 *   - Auth state (login pills vs account dropdown) + user name/email/initials +
 *     dashboard stats hydrated client-side from BileteOnlineAuth / BileteOnlineAPI.
 *   - Cart drawer bound to BileteOnlineCart (live items, qty, subtotal).
 *   - Search overlay: suggestions seeded from real categories/cities + live
 *     activity search; submit navigates to /cauta?q=…
 *
 * Variables a page can set BEFORE include:
 *   $currentPage     — slug used for aria-current="page"
 *   $headerContext   — ['type' => 'homepage'|'category'|'city'|'activity',
 *                       'label' => 'Sinaia', 'slug' => 'sinaia']
 *                      (label/slug optional; only used for category/city/activity)
 *   $navCategories   — override mega-menu category items
 *   $navCities       — override mega-menu city items
 *   $bodyClass       — extra classes appended to <body>
 *   $skipMainTag     — true if the page renders its own <main>
 *
 * NOTE: the previous v3 header is preserved at includes/header-v3-backup.php.
 */

if (!defined('BILETEONLINE_ROOT')) {
    require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/nav-helpers.php';

$currentPage = $currentPage ?? '';
$bodyClass   = $bodyClass ?? '';
$skipMainTag = $skipMainTag ?? false;

// ---- Context resolution -------------------------------------------------
$headerContext = $headerContext ?? ['type' => 'homepage'];
$ctxType  = $headerContext['type']  ?? 'homepage';
$ctxLabel = trim((string) ($headerContext['label'] ?? ''));
$ctxSlug  = trim((string) ($headerContext['slug'] ?? ''));
if (!in_array($ctxType, ['homepage', 'category', 'city', 'activity'], true)) {
    $ctxType = 'homepage';
}

// Primary "Explorează …" button label + search placeholder + explore hero,
// computed server-side from the context so they're correct on first paint.
$ctxExploreLabel = 'Explorează România';
$ctxPlaceholder  = 'Caută orașe, activități, locații sau experiențe';
$ctxHero = [
    'kicker' => 'DESCOPERIRE',
    'title'  => 'Explorează România',
    'desc'   => 'Activități, experiențe, orașe și locuri populare din toată țara.',
    'cta'    => 'Vezi orașe',
    'href'   => '/orase',
];

if (($ctxType === 'city' || $ctxType === 'activity') && $ctxLabel !== '') {
    $ctxExploreLabel = 'Explorează ' . $ctxLabel;
    $ctxPlaceholder  = 'Caută activități, atracții sau locații în ' . $ctxLabel;
    $ctxHero = [
        'kicker' => 'DESTINAȚIE',
        'title'  => $ctxLabel,
        'desc'   => 'Activități, locații și idei pe care le poți combina într-o ieșire în ' . $ctxLabel . '.',
        'cta'    => 'Vezi orașul',
        'href'   => $ctxSlug !== '' ? '/' . ltrim($ctxSlug, '/') : '/orase',
    ];
} elseif ($ctxType === 'category' && $ctxLabel !== '') {
    $ctxExploreLabel = 'Explorează ' . $ctxLabel;
    $ctxPlaceholder  = 'Caută ' . mb_strtolower($ctxLabel) . ', orașe sau experiențe similare';
    $ctxHero = [
        'kicker' => 'CATEGORIE',
        'title'  => $ctxLabel,
        'desc'   => 'Vezi unde găsești ' . mb_strtolower($ctxLabel) . ' și alege orașul potrivit.',
        'cta'    => 'Vezi categoria',
        'href'   => $ctxSlug !== '' ? '/' . ltrim($ctxSlug, '/') : '/categorii',
    ];
}

// ---- Mega menu data (live, cached). Pages can override before include. ----
$navCategories = $navCategories ?? navGetCategories(8);
$navCities     = $navCities ?? navGetCities(8);

// Curated quick-search chips + intent hubs — all point at real routes
// (category/city pages + programmatic SEO intent hubs).
$navQuickSearches = [
    ['label' => 'Idei de weekend',      'href' => '/activitati-weekend'],
    ['label' => 'Cu copiii',            'href' => '/activitati-copii'],
    ['label' => 'Indoor când plouă',    'href' => '/activitati-zile-ploioase'],
    ['label' => 'Sub 50 lei',           'href' => '/activitati-sub-50-lei'],
    ['label' => 'Experiențe cadou',     'href' => '/card-cadou'],
    ['label' => 'Pentru cupluri',       'href' => '/activitati-cupluri'],
];

// Inspiration mega → real guides from the blog API, with a curated fallback.
$navGuides = [];
try {
    $guidesResp = api_cached('nav_guides', fn () => api_get('/blog-articles', ['per_page' => 6, 'status' => 'published']), 300);
    $rawGuides  = $guidesResp['data']['articles'] ?? $guidesResp['data']['items'] ?? $guidesResp['data'] ?? [];
    if (is_array($rawGuides)) {
        foreach ($rawGuides as $g) {
            $gt = navFlatName($g['title'] ?? '');
            $gs = $g['slug'] ?? '';
            if ($gt === '' || $gs === '') continue;
            $navGuides[] = [
                'title' => $gt,
                'meta'  => navFlatName($g['category']['name'] ?? '') ?: 'Ghid',
                'href'  => '/ghiduri/' . ltrim($gs, '/'),
            ];
            if (count($navGuides) >= 6) break;
        }
    }
} catch (\Throwable $e) {
    $navGuides = [];
}
if (empty($navGuides)) {
    $navGuides = [
        ['title' => 'Idei de weekend',      'meta' => 'activități, tururi, idei locale',   'href' => '/activitati-weekend'],
        ['title' => 'Activități cu copiii', 'meta' => 'ateliere, muzee, locuri indoor',    'href' => '/activitati-copii'],
        ['title' => 'Experiențe cadou',     'meta' => 'pentru cupluri, familie, prieteni', 'href' => '/card-cadou'],
    ];
}

// Search overlay suggestions (client-filtered), built from live data.
$searchItems = [];
foreach ($navCategories as $c) {
    $searchItems[] = ['title' => $c['label'], 'type' => 'Categorie', 'meta' => ($c['count'] ?? ''), 'href' => $c['href']];
}
foreach ($navCities as $c) {
    $searchItems[] = ['title' => $c['label'], 'type' => 'Oraș', 'meta' => 'Activități locale', 'href' => $c['href']];
}
$searchItems[] = ['title' => 'Card cadou pentru experiențe', 'type' => 'Cadou', 'meta' => 'Alegi suma, destinatarul alege activitatea', 'href' => '/card-cadou'];
$searchItems[] = ['title' => 'Recuperează comanda', 'type' => 'Ajutor', 'meta' => 'Găsește biletele după email și comandă', 'href' => '/recuperare-comanda'];

// json_encode (UTF-8 kept) + htmlspecialchars(ENT_QUOTES) is the correct escaping
// for an HTML attribute Alpine parses. Do NOT add JSON_HEX_* here.
$headerSeed = json_encode([
    'searchItems'   => $searchItems,
    'quickSearches' => $navQuickSearches,
    'context'       => [
        'type'         => $ctxType,
        'label'        => $ctxLabel,
        'exploreLabel' => $ctxExploreLabel,
        'placeholder'  => $ctxPlaceholder,
    ],
], JSON_UNESCAPED_UNICODE);

// Explore mega tab labels (context-aware copy on the section headers).
$tabPlacesLabel = ($ctxType === 'city' || $ctxType === 'activity')
    ? 'Aproape de ' . ($ctxLabel ?: 'tine')
    : (($ctxType === 'category' && $ctxLabel !== '') ? 'Orașe pentru ' . $ctxLabel : 'Orașe populare');
?>
<body class="grain bg-white font-sans antialiased selection:bg-vermilion selection:text-paper<?= $bodyClass ? ' ' . htmlspecialchars($bodyClass, ENT_QUOTES) : '' ?>">

<a href="#top" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[100] focus:px-4 focus:py-2 focus:bg-ink focus:text-paper focus:rounded-md">Sari la conținut</a>

<header x-data="bileteOnlineHeader(<?= htmlspecialchars($headerSeed, ENT_QUOTES) ?>)"
        x-init="initHeader()"
        @keydown.escape.window="closeAll()"
        class="sticky top-0 z-50"
        role="banner">

    <div :class="scrolled ? 'shadow-header-scroll' : ''"
         class="relative transition-all duration-300 border-b border-ink/10 bg-paper/95 backdrop-blur-xl">
        <div class="absolute inset-0 pointer-events-none bo-grain"></div>

        <nav class="relative mx-auto flex h-[72px] max-w-[1500px] items-center gap-3 px-4 sm:px-6" aria-label="Navigare principală">
            <!-- Logo -->
            <a href="/" class="group flex shrink-0 items-center gap-2.5" aria-label="<?= htmlspecialchars(SITE_NAME, ENT_QUOTES) ?> — acasă">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-vermilion text-paper rotate-[-4deg] shadow-logo transition group-hover:rotate-[4deg] group-hover:scale-105">
                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4Z"/>
                        <path d="M9 7v10" stroke-dasharray="2 2"/>
                    </svg>
                </span>
                <span>
                    <span class="block text-2xl font-bold leading-none font-display">bilete<span class="text-vermilion">.</span>online</span>
                    <span class="hidden text-xs font-bold text-ink-soft sm:block">activități, experiențe, locuri de descoperit</span>
                </span>
            </a>

            <!-- Center command-search -->
            <div class="flex-1 hidden min-w-0 px-2 md:block">
                <button type="button" @click="searchOpen=true"
                        class="mx-auto flex h-12 w-full max-w-[620px] items-center gap-3 rounded-full border-2 border-ink/15 bg-white px-4 text-left transition hover:border-ink/40 hover:bg-paper-2">
                    <svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <span class="flex-1 min-w-0 text-sm font-bold truncate text-ink-soft" x-text="searchPlaceholder()"></span>
                    <span class="hidden rounded-full bg-paper-2 px-2.5 py-1 text-[11px] font-bold text-ink-soft lg:inline">Ctrl K</span>
                </button>
            </div>

            <!-- Right actions (desktop) -->
            <div class="items-center hidden gap-1 ml-auto lg:flex" @mouseleave="scheduleMegaClose()" @mouseenter="cancelMegaClose()">
                <button @mouseenter="openMega('explore','places')" @focus="openMega('explore','places')" @click="toggleMega('explore','places')"
                        :class="mega==='explore' ? 'bg-paper-2' : 'hover:bg-paper-2'"
                        class="max-w-[220px] truncate rounded-full px-4 py-2.5 text-sm font-bold transition" x-text="primaryExploreLabel()"></button>

                <button @mouseenter="openMega('explore','things')" @focus="openMega('explore','things')" @click="toggleMega('explore','things')"
                        :class="mega==='explore' && megaTab==='things' ? 'bg-paper-2' : 'hover:bg-paper-2'"
                        class="rounded-full px-4 py-2.5 text-sm font-bold transition">Activități</button>

                <button @mouseenter="openMega('inspiration')" @focus="openMega('inspiration')" @click="toggleMega('inspiration')"
                        :class="mega==='inspiration' ? 'bg-paper-2' : 'hover:bg-paper-2'"
                        class="rounded-full px-4 py-2.5 text-sm font-bold transition">Inspirație</button>

                <a href="/card-cadou" @mouseenter="mega=null" class="rounded-full px-4 py-2.5 text-sm font-bold transition hover:bg-paper-2"<?= $currentPage === 'card-cadou' ? ' aria-current="page"' : '' ?>>Card cadou</a>

                <!-- Cart -->
                <button @click="openCart()" class="relative grid transition rounded-full h-11 w-11 place-items-center text-ink hover:bg-paper-2" aria-label="Deschide coșul">
                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6 5 3H2"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                    <span x-show="cartCount() > 0" x-cloak class="absolute right-0 top-0 grid h-5 min-w-5 place-items-center rounded-full bg-vermilion px-1 text-[11px] font-bold text-paper" x-text="cartCount()"></span>
                </button>

                <!-- Language / currency -->
                <button @click="languageOpen=!languageOpen; accountOpen=false; mega=null" class="rounded-full px-3 py-2.5 text-sm font-bold transition hover:bg-paper-2">RO/RON</button>

                <!-- Logged OUT: login + register -->
                <template x-if="!loggedIn">
                    <div class="flex items-center gap-2" x-cloak>
                        <a href="/login" class="rounded-full px-4 py-2.5 text-sm font-bold transition hover:bg-paper-2">Intră în cont</a>
                        <a href="/register" class="rounded-full bg-ink px-4 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion">Creează cont</a>
                    </div>
                </template>

                <!-- Logged IN: account dropdown -->
                <template x-if="loggedIn">
                    <div class="relative" x-cloak>
                        <button @click="accountOpen=!accountOpen; languageOpen=false; mega=null" class="grid text-sm font-bold transition rounded-full h-11 w-11 place-items-center bg-ink text-paper hover:bg-vermilion" aria-haspopup="menu" :aria-expanded="accountOpen.toString()" x-text="acct.initials">A</button>

                        <div x-show="accountOpen" x-cloak x-transition.origin.top.right @click.outside="accountOpen=false" class="absolute right-0 top-[calc(100%+12px)] w-[360px] overflow-hidden rounded-[2rem] border-2 border-ink bg-paper text-ink shadow-deep" role="menu">
                            <div class="relative bo-grain">
                                <div class="relative p-5 border-b-2 border-dashed border-ink/15 bg-ink text-paper">
                                    <div class="flex items-center gap-3">
                                        <span class="grid w-12 h-12 text-xl font-bold rounded-full place-items-center bg-paper text-ink" x-text="acct.initials">?</span>
                                        <div class="min-w-0">
                                            <p class="text-3xl font-bold leading-none font-display" x-text="acct.firstName">Cont</p>
                                            <p class="text-sm truncate text-paper/50" x-text="acct.email"></p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-3 gap-2 mt-4 text-center">
                                        <div class="p-3 rounded-2xl bg-paper/10"><p class="text-2xl font-bold font-display" x-text="stats.tickets"></p><p class="text-[11px] text-paper/50">bilete</p></div>
                                        <div class="p-3 rounded-2xl bg-paper/10"><p class="text-2xl font-bold font-display" x-text="stats.points"></p><p class="text-[11px] text-paper/50">puncte</p></div>
                                        <div class="p-3 rounded-2xl bg-paper/10"><p class="text-2xl font-bold font-display"><span x-text="stats.profile"></span>%</p><p class="text-[11px] text-paper/50">profil</p></div>
                                    </div>
                                </div>
                                <nav class="relative p-3 text-sm font-bold" role="none">
                                    <a href="/cont" class="flex items-center justify-between px-4 py-3 transition rounded-2xl hover:bg-ink hover:text-paper" role="menuitem"><span>Dashboard</span><span aria-hidden="true">→</span></a>
                                    <a href="/cont/bilete" class="flex items-center justify-between px-4 py-3 transition rounded-2xl hover:bg-ink hover:text-paper" role="menuitem"><span>Biletele mele</span><span x-show="stats.tickets" class="rounded-full bg-vermilion px-2 py-0.5 text-xs text-paper" x-text="stats.tickets"></span></a>
                                    <a href="/cont/comenzi" class="flex items-center justify-between px-4 py-3 transition rounded-2xl hover:bg-ink hover:text-paper" role="menuitem"><span>Comenzile mele</span><span x-text="stats.orders"></span></a>
                                    <a href="/cont/puncte" class="flex items-center justify-between px-4 py-3 transition rounded-2xl hover:bg-ink hover:text-paper" role="menuitem"><span>Punctele mele</span><span x-text="stats.points"></span></a>
                                    <a href="/cont/recomandari" class="flex items-center justify-between px-4 py-3 transition rounded-2xl hover:bg-ink hover:text-paper" role="menuitem"><span>Recomandări</span><span class="text-vermilion">nou</span></a>
                                    <a href="/cont/setari" class="flex items-center justify-between px-4 py-3 transition rounded-2xl hover:bg-ink hover:text-paper" role="menuitem"><span>Setări cont</span><span aria-hidden="true">→</span></a>
                                </nav>
                                <div class="relative p-3 border-t border-ink/10">
                                    <button type="button" @click="logout()" class="flex items-center justify-center w-full px-4 py-3 text-sm font-bold transition border-2 rounded-full border-ink hover:bg-ink hover:text-paper">Ieși din cont</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Right actions (mobile) -->
            <div class="flex items-center gap-1 ml-auto lg:hidden">
                <button @click="openCart()" class="relative grid transition rounded-full h-11 w-11 place-items-center text-ink hover:bg-paper-2" aria-label="Coș">
                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6 5 3H2"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                    <span x-show="cartCount() > 0" x-cloak class="absolute right-0 top-0 grid h-5 min-w-5 place-items-center rounded-full bg-vermilion px-1 text-[11px] font-bold text-paper" x-text="cartCount()"></span>
                </button>
                <button @click="searchOpen=true" class="grid rounded-full h-11 w-11 place-items-center hover:bg-paper-2" aria-label="Caută">
                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </button>
                <button @click="mobileOpen=!mobileOpen" class="grid rounded-full h-11 w-11 place-items-center hover:bg-paper-2" aria-label="Meniu" :aria-expanded="mobileOpen.toString()">
                    <svg x-show="!mobileOpen" viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <svg x-show="mobileOpen" x-cloak viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
        </nav>

        <!-- Language / currency popover -->
        <div x-show="languageOpen" x-cloak x-transition.origin.top.right @click.outside="languageOpen=false" class="absolute right-[88px] top-[calc(100%+10px)] z-[51] hidden w-[320px] rounded-[1.6rem] border-2 border-ink bg-paper p-4 shadow-deep lg:block">
            <p class="text-3xl font-bold leading-none font-display">Limbă și monedă</p>
            <div class="grid gap-3 mt-4">
                <label><span class="block mb-1 text-sm font-bold text-ink-soft">Limbă</span><select class="w-full px-4 py-3 font-bold border-2 outline-none rounded-2xl border-ink/10 bg-paper-2"><option>Română</option><option disabled>English (în curând)</option></select></label>
                <label><span class="block mb-1 text-sm font-bold text-ink-soft">Monedă</span><select class="w-full px-4 py-3 font-bold border-2 outline-none rounded-2xl border-ink/10 bg-paper-2"><option>RON</option><option disabled>EUR (în curând)</option></select></label>
                <button @click="languageOpen=false" class="px-5 py-3 font-bold transition rounded-full bg-ink text-paper hover:bg-vermilion">Aplică</button>
            </div>
        </div>

        <!-- Mega menu -->
        <div x-show="mega" x-cloak x-transition.opacity.duration.160ms @mouseenter="cancelMegaClose()" @mouseleave="scheduleMegaClose()"
             class="absolute inset-x-0 hidden border-b-2 top-full border-ink bg-paper shadow-deep lg:block" role="menu">
            <div class="relative bo-grain">

                <!-- EXPLORE / ACTIVITIES mega -->
                <div x-show="mega==='explore'" class="relative mx-auto grid max-w-[1500px] grid-cols-[300px_1fr] gap-0 px-6 py-6">
                    <aside class="pr-5 border-r border-ink/10">
                        <button @mouseenter="megaTab='places'" @focus="megaTab='places'" :class="megaTab==='places' ? 'bg-ink text-paper' : 'hover:bg-paper-2'" class="flex items-center justify-between w-full px-4 py-3 font-bold text-left transition rounded-2xl"><span><?= htmlspecialchars($tabPlacesLabel) ?></span><span>→</span></button>
                        <button @mouseenter="megaTab='things'" @focus="megaTab='things'" :class="megaTab==='things' ? 'bg-ink text-paper' : 'hover:bg-paper-2'" class="flex items-center justify-between w-full px-4 py-3 mt-1 font-bold text-left transition rounded-2xl"><span>Categorii populare</span><span>→</span></button>
                        <button @mouseenter="megaTab='nearby'" @focus="megaTab='nearby'" :class="megaTab==='nearby' ? 'bg-ink text-paper' : 'hover:bg-paper-2'" class="flex items-center justify-between w-full px-4 py-3 mt-1 font-bold text-left transition rounded-2xl"><span>Idei rapide</span><span>→</span></button>

                        <div class="mt-5 rounded-[1.5rem] bg-mint p-5">
                            <p class="font-mono text-xs tracking-[.16em] text-forest"><?= htmlspecialchars($ctxHero['kicker']) ?></p>
                            <h3 class="mt-2 text-4xl font-bold leading-none font-display"><?= htmlspecialchars($ctxHero['title']) ?></h3>
                            <p class="mt-2 text-sm text-ink-soft"><?= htmlspecialchars($ctxHero['desc']) ?></p>
                            <a href="<?= htmlspecialchars($ctxHero['href'], ENT_QUOTES) ?>" @click="mega=null" class="inline-flex px-4 py-2 mt-4 text-sm font-bold transition rounded-full bg-forest text-paper hover:bg-ink"><?= htmlspecialchars($ctxHero['cta']) ?></a>
                        </div>
                    </aside>

                    <section class="pl-6">
                        <!-- Places / cities -->
                        <div x-show="megaTab==='places'">
                            <div class="flex items-end justify-between gap-6">
                                <div>
                                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">ORAȘE</p>
                                    <h2 class="mt-2 text-5xl font-bold leading-none font-display"><?= htmlspecialchars($tabPlacesLabel) ?></h2>
                                    <p class="max-w-3xl mt-3 text-ink-soft">Alege un oraș și descoperă activități, ghiduri și locații locale.</p>
                                </div>
                                <a href="/orase" @click="mega=null" class="px-5 py-3 font-bold transition rounded-full shrink-0 bg-ink text-paper hover:bg-vermilion">Toate orașele</a>
                            </div>
                            <div class="grid gap-4 mt-7 md:grid-cols-2 xl:grid-cols-3">
                                <?php foreach ($navCities as $city): ?>
                                    <a href="<?= htmlspecialchars($city['href'], ENT_QUOTES) ?>" @click="mega=null" class="group rounded-[1.5rem] border border-ink/10 bg-paper-2 p-5 transition hover:-translate-y-0.5 hover:bg-paper hover:shadow-deep">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <span class="px-3 py-1 text-xs font-bold rounded-full bg-paper text-ink-soft">Oraș</span>
                                                <p class="mt-4 text-3xl font-bold leading-none font-display group-hover:text-vermilion"><?= htmlspecialchars($city['label']) ?></p>
                                                <p class="mt-2 text-sm text-ink-soft">Activități și experiențe locale</p>
                                            </div>
                                            <span class="text-2xl transition group-hover:translate-x-1">→</span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Things / categories -->
                        <div x-show="megaTab==='things'" x-cloak>
                            <div class="flex items-end justify-between gap-6">
                                <div>
                                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">ACTIVITĂȚI</p>
                                    <h2 class="mt-2 text-5xl font-bold leading-none font-display">Alege după ce vrei să faci</h2>
                                    <p class="max-w-3xl mt-3 text-ink-soft">Categorii clare pentru ieșiri, weekenduri, copii, cadouri și experiențe locale.</p>
                                </div>
                                <a href="/categorii" @click="mega=null" class="px-5 py-3 font-bold transition rounded-full shrink-0 bg-ink text-paper hover:bg-vermilion">Toate categoriile</a>
                            </div>
                            <div class="grid gap-4 mt-7 md:grid-cols-2 xl:grid-cols-3">
                                <?php foreach ($navCategories as $cat): ?>
                                    <a href="<?= htmlspecialchars($cat['href'], ENT_QUOTES) ?>" @click="mega=null" class="group rounded-[1.5rem] border border-ink/10 bg-paper-2 p-5 transition hover:-translate-y-0.5 hover:bg-paper hover:shadow-deep">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <span class="px-3 py-1 text-xs font-bold rounded-full bg-paper text-ink-soft"><?= !empty($cat['icon_emoji']) ? htmlspecialchars($cat['icon_emoji']) . ' ' : '' ?><?= htmlspecialchars($cat['count'] ?? '') ?></span>
                                                <p class="mt-4 text-3xl font-bold leading-none font-display group-hover:text-vermilion"><?= htmlspecialchars($cat['label']) ?></p>
                                                <p class="mt-2 text-sm text-ink-soft">Vezi activitățile din categorie</p>
                                            </div>
                                            <span class="text-2xl transition group-hover:translate-x-1">→</span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Nearby / quick ideas -->
                        <div x-show="megaTab==='nearby'" x-cloak>
                            <div class="flex items-end justify-between gap-6">
                                <div>
                                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">IDEI RAPIDE</p>
                                    <h2 class="mt-2 text-5xl font-bold leading-none font-display">Scurtături utile</h2>
                                    <p class="max-w-3xl mt-3 text-ink-soft">Pagini care se potrivesc sezonului și intereselor frecvente.</p>
                                </div>
                                <a href="/ghiduri" @click="mega=null" class="px-5 py-3 font-bold transition rounded-full shrink-0 bg-ink text-paper hover:bg-vermilion">Vezi ghidurile</a>
                            </div>
                            <div class="grid gap-4 mt-7 md:grid-cols-2 xl:grid-cols-3">
                                <?php foreach ($navQuickSearches as $tag): ?>
                                    <a href="<?= htmlspecialchars($tag['href'], ENT_QUOTES) ?>" @click="mega=null" class="group rounded-[1.5rem] border border-ink/10 bg-paper-2 p-5 transition hover:-translate-y-0.5 hover:bg-paper hover:shadow-deep">
                                        <div class="flex items-center justify-between gap-4">
                                            <p class="text-3xl font-bold leading-none font-display group-hover:text-vermilion"><?= htmlspecialchars($tag['label']) ?></p>
                                            <span class="text-2xl transition group-hover:translate-x-1">→</span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- INSPIRATION mega -->
                <div x-show="mega==='inspiration'" x-cloak class="relative mx-auto grid max-w-[1500px] grid-cols-[300px_1fr] gap-0 px-6 py-6">
                    <aside class="pr-5 border-r border-ink/10">
                        <p class="mb-3 px-4 font-mono text-xs tracking-[.18em] text-ink-soft">INSPIRAȚIE</p>
                        <a href="/ghiduri" @click="mega=null" class="flex items-center justify-between w-full px-4 py-3 font-bold text-left transition rounded-2xl bg-ink text-paper hover:bg-vermilion"><span>Toate ghidurile</span><span>→</span></a>
                        <a href="/activitati-weekend" @click="mega=null" class="flex items-center justify-between w-full px-4 py-3 mt-1 font-bold text-left transition rounded-2xl hover:bg-paper-2"><span>Idei de weekend</span><span>→</span></a>
                        <a href="/activitati-copii" @click="mega=null" class="flex items-center justify-between w-full px-4 py-3 mt-1 font-bold text-left transition rounded-2xl hover:bg-paper-2"><span>Cu copiii</span><span>→</span></a>
                        <a href="/card-cadou" @click="mega=null" class="flex items-center justify-between w-full px-4 py-3 mt-1 font-bold text-left transition rounded-2xl hover:bg-paper-2"><span>Experiențe cadou</span><span>→</span></a>

                        <div class="mt-5 rounded-[1.5rem] border border-forest/20 bg-mint p-5">
                            <p class="font-bold text-forest">Ai puncte bonus?</p>
                            <p class="mt-1 text-sm text-ink-soft">Intră în cont și vezi activitățile unde poți aplica reducere.</p>
                            <a href="/cont/recomandari" @click="mega=null" class="inline-flex mt-3 font-bold text-forest underline-wobble">Vezi recomandări</a>
                        </div>
                    </aside>

                    <section class="pl-6">
                        <div class="flex items-end justify-between gap-6">
                            <div>
                                <p class="font-mono text-xs tracking-[.18em] text-ink-soft">GHIDURI</p>
                                <h2 class="mt-2 text-5xl font-bold leading-none font-display">Idei și recomandări editoriale</h2>
                                <p class="max-w-3xl mt-3 text-ink-soft">Ghiduri pentru orașe, weekenduri, atracții și activități de pus pe listă.</p>
                            </div>
                            <a href="/ghiduri" @click="mega=null" class="px-5 py-3 font-bold transition rounded-full shrink-0 bg-ink text-paper hover:bg-vermilion">Toate ghidurile</a>
                        </div>
                        <div class="grid gap-4 mt-7 md:grid-cols-2 xl:grid-cols-3">
                            <?php foreach ($navGuides as $guide): ?>
                                <a href="<?= htmlspecialchars($guide['href'], ENT_QUOTES) ?>" @click="mega=null" class="group rounded-[1.5rem] border border-ink/10 bg-paper-2 p-5 transition hover:-translate-y-0.5 hover:bg-paper hover:shadow-deep">
                                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-paper text-ink-soft"><?= htmlspecialchars($guide['meta']) ?></span>
                                    <p class="mt-4 text-3xl font-bold leading-none font-display group-hover:text-vermilion"><?= htmlspecialchars($guide['title']) ?></p>
                                    <p class="mt-2 text-sm text-ink-soft">Citește ghidul →</p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
        <div x-show="mobileOpen" x-cloak x-collapse class="border-t border-ink/10 bg-paper lg:hidden">
            <div class="mx-auto max-w-[1500px] px-4 py-5 sm:px-6">
                <div x-show="loggedIn" x-cloak class="flex items-center gap-3 p-4 mb-4 border-2 rounded-2xl border-ink bg-paper-2">
                    <span class="grid font-bold rounded-full h-11 w-11 place-items-center bg-forest text-paper" x-text="acct.initials">?</span>
                    <div class="min-w-0">
                        <p class="font-bold leading-tight" x-text="acct.firstName">Cont</p>
                        <a href="/cont" class="text-sm text-vermilion">Mergi în cont →</a>
                    </div>
                </div>

                <button @click="searchOpen=true; mobileOpen=false" class="flex items-center w-full gap-3 px-4 py-4 mb-4 font-bold text-left border-2 rounded-2xl border-ink bg-paper-2">
                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <span x-text="searchPlaceholder()">Caută</span>
                </button>

                <div class="grid gap-3">
                    <a href="<?= htmlspecialchars($ctxHero['href'], ENT_QUOTES) ?>" class="px-4 py-4 text-3xl font-bold rounded-2xl bg-ink font-display text-paper" x-text="primaryExploreLabel()"><?= htmlspecialchars($ctxExploreLabel) ?></a>
                    <a href="/categorii" class="px-4 py-4 text-3xl font-bold rounded-2xl bg-paper-2 font-display">Activități</a>
                    <a href="/orase" class="px-4 py-4 text-3xl font-bold rounded-2xl bg-paper-2 font-display">Orașe</a>
                    <a href="/operatori" class="px-4 py-4 text-3xl font-bold rounded-2xl bg-paper-2 font-display">Operatori</a>
                    <a href="/ghiduri" class="px-4 py-4 text-3xl font-bold rounded-2xl bg-paper-2 font-display">Inspirație</a>
                    <a href="/card-cadou" class="px-4 py-4 text-3xl font-bold rounded-2xl bg-mint font-display text-forest">Card cadou</a>
                </div>

                <div class="mt-5">
                    <p class="font-mono text-[10px] tracking-[.2em] text-ink-soft mb-2">ORAȘE POPULARE</p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach (array_slice($navCities, 0, 6) as $city): ?>
                            <a href="<?= htmlspecialchars($city['href'], ENT_QUOTES) ?>" class="rounded-full border border-ink/15 bg-paper-2 px-3 py-1.5 text-sm font-bold"><?= htmlspecialchars($city['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-5">
                    <button @click="openCart(); mobileOpen=false" class="px-5 py-3 font-bold text-center rounded-full bg-vermilion text-paper">Coș (<span x-text="cartCount()"></span>)</button>
                    <a x-show="loggedIn" href="/cont" class="px-5 py-3 font-bold text-center rounded-full bg-ink text-paper">Cont</a>
                    <a x-show="!loggedIn" href="/login" class="px-5 py-3 font-bold text-center rounded-full bg-ink text-paper">Intră în cont</a>
                    <a href="/recuperare-comanda" class="col-span-2 px-5 py-3 font-bold text-center border-2 rounded-full border-ink">Recuperează comanda</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Cart drawer -->
    <div x-show="cartOpen" x-cloak x-transition.opacity.duration.180ms class="fixed inset-0 z-[70] bg-ink/70 backdrop-blur-sm" role="dialog" aria-modal="true" aria-label="Coșul tău">
        <div @click="cartOpen=false" class="absolute inset-0"></div>
        <aside x-show="cartOpen"
               x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
               class="bo-grain absolute right-0 top-0 flex h-full w-full max-w-[520px] flex-col overflow-hidden border-l-2 border-ink bg-paper text-ink shadow-deep">
            <div class="relative p-5 border-b-2 border-dashed border-ink/15 bg-ink text-paper sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-mono text-xs tracking-[.18em] text-ochre">COȘUL TĂU</p>
                        <h2 class="mt-2 text-5xl font-bold leading-none font-display">Coș</h2>
                        <p class="mt-2 text-paper/60"><span x-text="cartCount()"></span> bilete · subtotal <strong class="text-paper" x-text="formatMoney(cartSubtotal())"></strong></p>
                    </div>
                    <button @click="cartOpen=false" class="grid text-2xl font-bold transition rounded-full h-11 w-11 shrink-0 place-items-center bg-paper text-ink hover:bg-vermilion hover:text-paper" aria-label="Închide coșul">×</button>
                </div>
                <div x-show="loggedIn && stats.points > 0" x-cloak class="p-4 mt-5 border rounded-2xl border-paper/10 bg-paper/10">
                    <div class="flex items-center justify-between gap-4">
                        <span class="font-bold">Puncte disponibile</span>
                        <span class="text-2xl font-bold font-display text-ochre" x-text="stats.points"></span>
                    </div>
                    <p class="mt-1 text-sm text-paper/50">Le poți aplica în checkout, dacă activitatea este eligibilă.</p>
                </div>
            </div>

            <div class="relative flex-1 p-4 overflow-auto sm:p-5">
                <template x-if="cartItems.length === 0">
                    <div class="grid h-full text-center place-items-center">
                        <div>
                            <p class="text-5xl font-bold leading-none font-display">Coșul este gol.</p>
                            <p class="mt-3 text-ink-soft">Alege o activitate și adaugă bilete pentru a continua.</p>
                            <a href="/categorii" class="inline-flex px-6 py-4 mt-5 font-bold transition rounded-full bg-vermilion text-paper hover:bg-vermilion-d">Explorează activități</a>
                        </div>
                    </div>
                </template>

                <div class="space-y-3" x-show="cartItems.length">
                    <template x-for="item in cartItems" :key="item.key">
                        <article class="overflow-hidden rounded-[1.5rem] border-2 border-ink bg-paper shadow-ticket">
                            <div class="grid grid-cols-[112px_1fr]">
                                <img :src="item.image || '/assets/images/placeholder.jpg'" :alt="item.title" loading="lazy" class="h-full min-h-[150px] w-full object-cover bg-paper-2">
                                <div class="p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <a :href="item.url" class="text-2xl font-bold leading-none font-display hover:text-vermilion" x-text="item.title"></a>
                                            <p class="mt-1 text-sm text-ink-soft" x-text="item.location"></p>
                                        </div>
                                        <button @click="removeCartItem(item.key)" class="grid w-8 h-8 transition rounded-full shrink-0 place-items-center bg-rose text-vermilion hover:bg-vermilion hover:text-paper" aria-label="Șterge produsul">×</button>
                                    </div>
                                    <div class="flex flex-wrap gap-2 mt-3">
                                        <span x-show="item.date" class="px-3 py-1 text-xs font-bold rounded-full bg-paper-2" x-text="item.date"></span>
                                        <span x-show="item.type" class="px-3 py-1 text-xs font-bold rounded-full bg-mint text-forest" x-text="item.type"></span>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 mt-4">
                                        <div class="inline-flex items-center overflow-hidden border-2 rounded-full border-ink">
                                            <button @click="decreaseQty(item.key)" class="grid w-10 h-10 font-bold transition place-items-center hover:bg-ink hover:text-paper" aria-label="Scade">−</button>
                                            <span class="grid w-10 h-10 font-bold place-items-center border-x-2 border-ink" x-text="item.qty"></span>
                                            <button @click="increaseQty(item.key)" class="grid w-10 h-10 font-bold transition place-items-center hover:bg-ink hover:text-paper" aria-label="Crește">+</button>
                                        </div>
                                        <p class="text-2xl font-bold font-display" x-text="formatMoney(item.qty * item.price)"></p>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>
            </div>

            <div class="relative p-5 border-t-2 border-dashed border-ink/15 bg-paper-2 sm:p-6" x-show="cartItems.length">
                <div class="flex items-end justify-between gap-4">
                    <span class="font-bold">Subtotal</span>
                    <strong class="text-4xl leading-none font-display" x-text="formatMoney(cartSubtotal())"></strong>
                </div>
                <div class="grid gap-2 mt-5">
                    <a href="/checkout" class="px-6 py-4 font-bold text-center transition rounded-full bg-vermilion text-paper hover:bg-vermilion-d">Continuă la checkout</a>
                    <a href="/cos" class="px-6 py-4 font-bold text-center transition border-2 rounded-full border-ink hover:bg-ink hover:text-paper">Vezi pagina coșului</a>
                </div>
                <p class="mt-3 text-xs leading-relaxed text-ink-soft">Taxele finale, comisionul, punctele aplicabile și protecția bilet se calculează în checkout.</p>
            </div>
        </aside>
    </div>

    <!-- Search overlay -->
    <div x-show="searchOpen" x-cloak x-transition.opacity.duration.180ms class="fixed inset-0 z-[60] bg-ink/75 p-3 backdrop-blur-sm sm:p-5" role="dialog" aria-modal="true" aria-label="Caută pe bilete.online">
        <div @click.outside="searchOpen=false" class="mx-auto mt-12 max-w-4xl overflow-hidden rounded-[2rem] border-2 border-ink bg-paper shadow-deep">
            <div class="relative bo-grain">
                <form @submit.prevent="doSearch()" class="relative p-4 border-b-2 border-dashed border-ink/15 sm:p-5">
                    <div class="flex items-center gap-3">
                        <svg viewBox="0 0 24 24" class="w-6 h-6 shrink-0 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input x-ref="searchInput" x-model="searchQuery" type="search" :placeholder="searchPlaceholder()" class="w-full py-3 text-xl font-bold bg-transparent outline-none placeholder:text-ink-soft/70 sm:text-2xl">
                        <button type="button" @click="searchOpen=false" class="grid w-10 h-10 text-xl font-bold transition rounded-full shrink-0 place-items-center bg-ink text-paper hover:bg-vermilion">×</button>
                    </div>
                </form>

                <div class="relative grid max-h-[70vh] overflow-auto lg:grid-cols-[1fr_320px]">
                    <section class="p-5 sm:p-6">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">SUGESTII</p>
                        <div class="grid gap-3 mt-4">
                            <template x-for="item in filteredSearch()" :key="item.href">
                                <a :href="item.href" class="p-4 transition border group rounded-3xl border-ink/10 bg-paper-2 hover:border-ink hover:bg-paper">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <span class="px-3 py-1 text-xs font-bold rounded-full bg-paper text-ink-soft" x-text="item.type"></span>
                                            <h3 class="mt-3 text-3xl font-bold leading-none font-display group-hover:text-vermilion" x-text="item.title"></h3>
                                            <p class="mt-1 text-ink-soft" x-text="item.meta"></p>
                                        </div>
                                        <span class="text-2xl transition group-hover:translate-x-1" aria-hidden="true">→</span>
                                    </div>
                                </a>
                            </template>
                            <template x-if="filteredSearch().length === 0">
                                <button type="button" @click="doSearch()" class="p-5 text-left border-2 border-dashed rounded-3xl border-ink/20">
                                    <span class="font-bold">Caută „<span x-text="searchQuery"></span>” pe tot site-ul</span>
                                    <span class="block mt-1 text-sm text-ink-soft">Apasă Enter pentru rezultate complete.</span>
                                </button>
                            </template>
                        </div>
                    </section>

                    <aside class="p-5 border-t-2 border-dashed border-ink/15 bg-paper-2/60 sm:p-6 lg:border-l-2 lg:border-t-0">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">RAPID</p>
                        <div class="flex flex-wrap gap-2 mt-4">
                            <template x-for="tag in quickSearches" :key="tag.href">
                                <a :href="tag.href" class="px-4 py-2 text-sm font-bold transition rounded-full bg-paper hover:bg-ink hover:text-paper" x-text="tag.label"></a>
                            </template>
                        </div>
                        <div class="p-5 mt-6 rounded-3xl bg-ink text-paper">
                            <p class="font-mono text-xs tracking-[.18em] text-paper/40">CONT</p>
                            <h3 class="mt-2 text-4xl font-bold leading-none font-display">Ai bilete cumpărate?</h3>
                            <p class="mt-3 text-paper/60">Le găsești rapid în cont sau prin recuperare comandă.</p>
                            <div class="flex flex-wrap gap-2 mt-4">
                                <a href="/cont/bilete" class="px-4 py-2 text-sm font-bold rounded-full bg-vermilion text-paper">Biletele mele</a>
                                <a href="/recuperare-comanda" class="px-4 py-2 text-sm font-bold rounded-full bg-paper text-ink">Recuperează</a>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
/**
 * Header Alpine component (v6). Context (Explore label + search placeholder)
 * comes from the PHP seed. Auth state, user info + dashboard stats, cart drawer
 * and search overlay are all driven by live data: BileteOnlineAuth /
 * BileteOnlineAPI / BileteOnlineCart. Mega menus are server-rendered (SEO);
 * this component only toggles their open/close + active tab state.
 */
function bileteOnlineHeader(seed) {
    return {
        scrolled: false,
        mobileOpen: false,
        searchOpen: false,
        cartOpen: false,
        accountOpen: false,
        languageOpen: false,
        mega: null,
        megaTab: 'places',
        megaCloseTimer: null,
        searchQuery: '',
        loggedIn: false,
        isClient: false,
        ctx: (seed && seed.context) || { type: 'homepage', label: '', exploreLabel: 'Explorează România', placeholder: 'Caută activități' },
        acct: { firstName: 'Cont', name: 'Client', email: '', initials: '?' },
        stats: { tickets: 0, points: 0, orders: 0, profile: 0, support: 0 },
        cartItems: [],
        searchItems: (seed && seed.searchItems) || [],
        quickSearches: (seed && seed.quickSearches) || [],
        activityResults: [],
        _searchTimer: null,

        primaryExploreLabel() { return (this.ctx && this.ctx.exploreLabel) || 'Explorează'; },
        searchPlaceholder() { return (this.ctx && this.ctx.placeholder) || 'Caută activități'; },

        // ---- mega ----
        openMega(menu, tab) {
            this.cancelMegaClose();
            this.mega = menu;
            if (menu === 'explore') this.megaTab = tab || (this.megaTab || 'places');
            this.languageOpen = false;
            this.accountOpen = false;
        },
        toggleMega(menu, tab) {
            if (this.mega === menu && (menu !== 'explore' || this.megaTab === (tab || this.megaTab))) { this.mega = null; }
            else { this.openMega(menu, tab); }
        },
        scheduleMegaClose() { this.cancelMegaClose(); this.megaCloseTimer = setTimeout(() => { this.mega = null; }, 180); },
        cancelMegaClose() { if (this.megaCloseTimer) { clearTimeout(this.megaCloseTimer); this.megaCloseTimer = null; } },

        initHeader() {
            this.scrolled = window.scrollY > 20;
            window.addEventListener('scroll', () => { this.scrolled = window.scrollY > 20; }, { passive: true });

            // Ctrl/⌘ + K opens search.
            window.addEventListener('keydown', (e) => {
                if ((e.metaKey || e.ctrlKey) && e.key && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    this.searchOpen = true;
                }
            });
            this.$watch('searchOpen', (open) => {
                if (open) {
                    this.cartOpen = false; this.accountOpen = false; this.mega = null; this.languageOpen = false;
                    this.$nextTick(() => this.$refs.searchInput && this.$refs.searchInput.focus());
                }
            });
            // Live activity search (debounced).
            this.$watch('searchQuery', (q) => {
                clearTimeout(this._searchTimer);
                const term = (q || '').trim();
                if (term.length < 2) { this.activityResults = []; return; }
                this._searchTimer = setTimeout(() => this.searchActivities(term), 250);
            });

            // Auth: re-sync on auth.js events + a short timeout (auth.js may boot
            // after Alpine). window.BileteOnlineAuth is exposed by auth.js.
            const syncAuth = () => {
                this.loggedIn = !!(window.BileteOnlineAuth && typeof BileteOnlineAuth.isLoggedIn === 'function' && BileteOnlineAuth.isLoggedIn());
                this.isClient = !!(window.BileteOnlineAuth && typeof BileteOnlineAuth.isCustomer === 'function' && BileteOnlineAuth.isCustomer());
                this.hydrateUser();
                if (this.loggedIn) this.loadStats();
            };
            syncAuth();
            ['bileteonline:auth:init', 'bileteonline:auth:login', 'bileteonline:auth:update', 'bileteonline:auth:logout']
                .forEach(ev => window.addEventListener(ev, syncAuth));
            setTimeout(syncAuth, 300);
            window.addEventListener('load', syncAuth);

            // Cart: load now + whenever it changes.
            this.loadCart();
            window.addEventListener('bileteonline:cart:update', () => this.loadCart());
            window.addEventListener('load', () => this.loadCart());
        },

        hydrateUser() {
            try {
                const u = (window.BileteOnlineAuth && BileteOnlineAuth.getUser) ? BileteOnlineAuth.getUser() : null;
                if (! u) return;
                const full = ((u.first_name || '') + (u.last_name ? ' ' + u.last_name : '')).trim() || u.name || u.email || 'Client';
                this.acct = {
                    firstName: u.first_name || (full.split(' ')[0]) || 'Cont',
                    name: full,
                    email: u.email || '',
                    initials: (full || '?').split(/\s+/).filter(Boolean).map(s => s[0]).join('').slice(0, 2).toUpperCase() || '?',
                };
                if (typeof u.points === 'number') this.stats.points = u.points;
                if (u.profile_completion && typeof u.profile_completion.percentage === 'number') this.stats.profile = u.profile_completion.percentage;
            } catch (e) {}
        },

        loadStats() {
            try {
                if (! window.BileteOnlineAPI || ! BileteOnlineAPI.customer || ! BileteOnlineAPI.customer.getDashboardStats) return;
                BileteOnlineAPI.customer.getDashboardStats().then(r => {
                    const s = (r && r.data && (r.data.stats || r.data)) || null;
                    if (! s) return;
                    this.stats.tickets = s.upcoming_tickets_count ?? s.tickets_count ?? this.stats.tickets;
                    this.stats.orders  = s.orders_count ?? s.total_orders ?? this.stats.orders;
                    this.stats.points  = s.points_balance ?? (s.points && s.points.balance) ?? this.stats.points;
                    this.stats.support = s.open_support_tickets ?? this.stats.support;
                    if (typeof s.profile_completion === 'number') this.stats.profile = s.profile_completion;
                }).catch(() => {});
            } catch (e) {}
        },

        // ---- cart ----
        openCart() { this.cartOpen = true; this.accountOpen = false; this.mega = null; this.languageOpen = false; this.loadCart(); },
        loadCart() {
            try {
                if (! window.BileteOnlineCart || ! BileteOnlineCart.getItems) { this.cartItems = []; return; }
                this.cartItems = (BileteOnlineCart.getItems() || []).map(it => this.normalizeCartItem(it)).filter(Boolean);
            } catch (e) { this.cartItems = []; }
        },
        normalizeCartItem(it) {
            const isActivity = it.type === 'activity';
            const ev = it.event || {};
            const act = it.activity || {};
            const tt = it.ticketType || {};
            const va = it.variant || {};
            const title = ev.title || act.title || it.title || 'Bilet';
            const slug = ev.slug || act.slug || '';
            const city = ev.city || act.city || (ev.venue && ev.venue.city) || (act.venue && act.venue.city) || '';
            const venue = (ev.venue && (ev.venue.name || ev.venue)) || (act.venue && (act.venue.name || act.venue)) || '';
            const date = ev.performance_date || ev.date || it.booking_date || '';
            const time = ev.performance_time || ev.time || it.start || '';
            const price = (typeof tt.price === 'number' ? tt.price : (typeof va.price === 'number' ? va.price : (typeof it.price === 'number' ? it.price : 0)));
            return {
                key: it.key,
                title: title,
                location: [typeof venue === 'string' ? venue : '', city].filter(Boolean).join(' · '),
                date: [date, time].filter(Boolean).join(' · '),
                type: tt.name || va.name || (isActivity ? 'Activitate' : 'Bilet'),
                qty: it.quantity || 1,
                price: price,
                image: ev.image || act.image || it.image || '',
                url: isActivity ? ('/activitate/' + slug) : (slug ? ('/eveniment/' + slug) : '/cos'),
            };
        },
        cartCount() { return this.cartItems.reduce((s, i) => s + (i.qty || 0), 0); },
        cartSubtotal() { return this.cartItems.reduce((s, i) => s + (i.qty || 0) * (i.price || 0), 0); },
        formatMoney(v) {
            try { return new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON', maximumFractionDigits: 2 }).format(v || 0); }
            catch (e) { return (Math.round((v || 0) * 100) / 100) + ' lei'; }
        },
        increaseQty(key) {
            const i = this.cartItems.find(x => x.key === key); if (! i) return;
            if (window.BileteOnlineCart && BileteOnlineCart.updateQuantity) { BileteOnlineCart.updateQuantity(key, i.qty + 1); this.loadCart(); }
        },
        decreaseQty(key) {
            const i = this.cartItems.find(x => x.key === key); if (! i) return;
            if (! window.BileteOnlineCart) return;
            if (i.qty > 1) { BileteOnlineCart.updateQuantity(key, i.qty - 1); }
            else if (BileteOnlineCart.removeItem) { BileteOnlineCart.removeItem(key); }
            this.loadCart();
        },
        removeCartItem(key) {
            if (window.BileteOnlineCart && BileteOnlineCart.removeItem) { BileteOnlineCart.removeItem(key); this.loadCart(); }
        },

        // ---- search ----
        doSearch() {
            const q = (this.searchQuery || '').trim();
            window.location.href = q ? ('/cauta?q=' + encodeURIComponent(q)) : '/cauta';
        },
        searchActivities(term) {
            const base = (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php';
            fetch(base + '?action=activities&per_page=6&search=' + encodeURIComponent(term), { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(d => {
                    const items = (d && d.data && (d.data.items || d.data)) || [];
                    this.activityResults = (Array.isArray(items) ? items : []).map(a => ({
                        title: (a.title && typeof a.title === 'object') ? (a.title.ro || a.title.en || Object.values(a.title)[0] || '') : (a.title || ''),
                        type: 'Activitate',
                        meta: (a.city && a.city.name) || (a.category && a.category.name) || '',
                        href: '/activitate/' + (a.slug || ''),
                    })).filter(x => x.title && x.href !== '/activitate/');
                })
                .catch(() => { this.activityResults = []; });
        },
        filteredSearch() {
            const q = (this.searchQuery || '').toLowerCase().trim();
            if (! q) return this.searchItems.slice(0, 6);
            const staticMatches = this.searchItems.filter(i => JSON.stringify(i).toLowerCase().includes(q));
            return [...this.activityResults, ...staticMatches].slice(0, 10);
        },

        // ---- misc ----
        logout() {
            try {
                if (window.BileteOnlineAuth && BileteOnlineAuth.logoutCustomer) { BileteOnlineAuth.logoutCustomer(); return; }
                if (window.BileteOnlineAuth && BileteOnlineAuth.logout) { BileteOnlineAuth.logout(); return; }
            } catch (e) {}
            window.location.href = '/';
        },
        closeAll() { this.searchOpen = false; this.mobileOpen = false; this.cartOpen = false; this.accountOpen = false; this.languageOpen = false; this.mega = null; },
    };
}
</script>

<?php if (!$skipMainTag): ?>
<main id="top" role="main">
<?php endif; ?>
