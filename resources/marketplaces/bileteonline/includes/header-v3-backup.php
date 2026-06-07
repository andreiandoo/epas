<?php
/**
 * bilete.online — site header (v3 design)
 *
 * Opens <body>, renders the sticky header (top strip, primary nav with mega
 * menus for categories + cities, command search overlay, cart drawer, account
 * dropdown, mobile drawer), then opens <main id="top"> so pages drop their
 * <section> blocks directly. footer.php closes </main>, the footer and the doc.
 *
 * Dynamic data:
 *   - Categories / cities pulled live from the API (navGetCategories/Cities),
 *     rendered server-side for SEO inside the mega menus + mobile drawer.
 *   - Auth state (login pill vs account dropdown) + user name/email/initials +
 *     dashboard stats hydrated client-side from BileteOnlineAuth / BileteOnlineAPI.
 *   - Cart drawer bound to BileteOnlineCart (live items, qty, subtotal).
 *   - Search overlay: suggestions seeded from real categories/cities; submit
 *     navigates to /cauta?q=…
 *
 * Variables a page can set BEFORE include:
 *   $currentPage     — slug used for aria-current="page"
 *   $navCategories   — override mega-menu category items
 *   $navCities       — override mega-menu city items
 *   $bodyClass       — extra classes appended to <body>
 *   $skipMainTag     — true if the page renders its own <main>
 */

if (!defined('BILETEONLINE_ROOT')) {
    require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/nav-helpers.php';

$currentPage = $currentPage ?? '';
$bodyClass   = $bodyClass ?? '';
$skipMainTag = $skipMainTag ?? false;

// Mega menu data (live, cached). Pages can override before include.
$navCategories = $navCategories ?? navGetCategories(8);
$navCities     = $navCities ?? navGetCities(8);

// Curated quick-search chips + guide links — all point at real routes
// (category/city pages + programmatic SEO intent hubs).
$navQuickSearches = [
    ['label' => 'Idei de weekend',      'href' => '/activitati-weekend'],
    ['label' => 'Cu copiii',            'href' => '/activitati-copii'],
    ['label' => 'Indoor când plouă',    'href' => '/activitati-zile-ploioase'],
    ['label' => 'Sub 50 lei',           'href' => '/activitati-sub-50-lei'],
    ['label' => 'Experiențe cadou',     'href' => '/card-cadou'],
    ['label' => 'Pentru cupluri',       'href' => '/activitati-cupluri'],
];
$navGuides = [
    ['title' => 'Idei de weekend',          'meta' => 'activități, tururi, idei locale',     'href' => '/activitati-weekend'],
    ['title' => 'Activități cu copiii',     'meta' => 'ateliere, muzee, locuri indoor',      'href' => '/activitati-copii'],
    ['title' => 'Experiențe cadou',         'meta' => 'pentru cupluri, familie, prieteni',   'href' => '/card-cadou'],
];

// Search overlay suggestions (client-filtered). Built from live categories +
// cities so the suggestions reflect the real catalogue, not a hardcoded list.
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
// for an HTML attribute Alpine parses. Do NOT add JSON_HEX_* here — that emits
// " for structural quotes, which is invalid as an object key in the x-data expr.
$headerSeed = json_encode([
    'searchItems'   => $searchItems,
    'quickSearches' => $navQuickSearches,
], JSON_UNESCAPED_UNICODE);
?>
<body class="grain font-sans antialiased selection:bg-vermilion selection:text-paper<?= $bodyClass ? ' ' . htmlspecialchars($bodyClass, ENT_QUOTES) : '' ?>">

<a href="#top" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[100] focus:px-4 focus:py-2 focus:bg-ink focus:text-paper focus:rounded-md">Sari la conținut</a>

<header x-data="bileteOnlineHeader(<?= htmlspecialchars($headerSeed, ENT_QUOTES) ?>)"
        x-init="initHeader()"
        @keydown.escape.window="closeAll()"
        class="sticky top-0 z-50"
        role="banner">

    <!-- Top utility strip -->
    <div class="bg-ink text-paper">
        <div class="mx-auto flex h-9 max-w-[1500px] items-center justify-between gap-4 px-4 text-[11px] font-mono tracking-[.16em] sm:px-6">
            <a href="/activitati-weekend" class="hidden items-center gap-2 text-paper/75 transition hover:text-ochre md:flex">
                <span class="h-1.5 w-1.5 rounded-full bg-vermilion pulse-dot"></span>
                <span>IDEI DE WEEKEND · ACTIVITĂȚI NOI · CARDURI CADOU</span>
            </a>
            <button @click="searchOpen=true" class="flex min-w-0 items-center gap-2 text-paper/75 transition hover:text-ochre md:hidden">
                <span class="h-1.5 w-1.5 rounded-full bg-vermilion pulse-dot"></span>
                <span>Caută activități</span>
            </button>
            <div class="flex shrink-0 items-center gap-4">
                <a href="/recuperare-comanda" class="hidden text-ochre underline-wobble sm:inline">Recuperează comanda</a>
                <a href="/pentru-locatii" x-show="!isClient" class="hidden text-paper/60 transition hover:text-ochre lg:inline">Pentru locații</a>
                <a href="/card-cadou" class="text-paper/60 transition hover:text-ochre">Card cadou</a>
            </div>
        </div>
    </div>

    <!-- Main nav -->
    <div :class="scrolled ? 'bg-paper/95 shadow-header-scroll backdrop-blur-xl border-b border-ink/10' : 'bg-paper/85 backdrop-blur-md border-b border-ink/5'"
         class="relative transition-all duration-300">
        <div class="bo-grain pointer-events-none absolute inset-0"></div>

        <nav class="relative mx-auto grid h-[76px] max-w-[1500px] grid-cols-[auto_1fr_auto] items-center gap-3 px-4 sm:px-6" aria-label="Navigare principală">
            <!-- Logo -->
            <a href="/" class="group flex items-center gap-3" aria-label="<?= htmlspecialchars(SITE_NAME, ENT_QUOTES) ?> — acasă">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-vermilion text-paper rotate-[-4deg] shadow-logo transition group-hover:rotate-[4deg] group-hover:scale-105">
                    <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4Z"/>
                        <path d="M9 7v10" stroke-dasharray="2 2"/>
                    </svg>
                </span>
                <span>
                    <span class="block font-display text-2xl font-bold leading-none">bilete<span class="text-vermilion">.</span>online</span>
                    <span class="hidden text-xs font-bold text-ink-soft sm:block">activități, experiențe, locuri de descoperit</span>
                </span>
            </a>

            <!-- Desktop nav center -->
            <div class="hidden justify-center lg:flex">
                <div class="flex items-center gap-1 rounded-full border border-ink/10 bg-paper-2/70 p-1">
                    <button @mouseenter="mega='categories'" @focus="mega='categories'" @click="mega = mega==='categories' ? null : 'categories'"
                            :class="mega==='categories' ? 'bg-ink text-paper' : 'hover:bg-paper'"
                            class="rounded-full px-4 py-2.5 text-sm font-bold transition" aria-label="Categorii">Categorii</button>
                    <button @mouseenter="mega='cities'" @focus="mega='cities'" @click="mega = mega==='cities' ? null : 'cities'"
                            :class="mega==='cities' ? 'bg-ink text-paper' : 'hover:bg-paper'"
                            class="rounded-full px-4 py-2.5 text-sm font-bold transition" aria-label="Orașe">Orașe</button>
                    <a href="/ghiduri" @mouseenter="mega=null" class="rounded-full px-4 py-2.5 text-sm font-bold transition hover:bg-paper"<?= $currentPage === 'ghiduri' ? ' aria-current="page"' : '' ?>>Ghiduri</a>
                    <a href="/operatori" @mouseenter="mega=null" class="rounded-full px-4 py-2.5 text-sm font-bold transition hover:bg-paper"<?= $currentPage === 'operatori' ? ' aria-current="page"' : '' ?>>Operatori</a>
                    <a href="/faqs" @mouseenter="mega=null" class="rounded-full px-4 py-2.5 text-sm font-bold transition hover:bg-paper"<?= $currentPage === 'faqs' ? ' aria-current="page"' : '' ?>>Ajutor</a>
                </div>
            </div>

            <!-- Right actions -->
            <div class="flex items-center justify-end gap-2">
                <button @click="searchOpen=true" class="hidden items-center gap-2 rounded-full border-2 border-ink bg-paper px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper xl:flex">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <span>Caută</span>
                    <span class="rounded-full bg-paper-2 px-2 py-0.5 text-[11px] text-ink-soft">Ctrl K</span>
                </button>

                <!-- Cart -->
                <button @click="openCart()" class="relative grid h-11 w-11 place-items-center rounded-full border-2 border-ink bg-paper text-ink transition hover:bg-ink hover:text-paper" aria-label="Deschide coșul">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6 5 3H2"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                    <span x-show="cartCount() > 0" x-cloak class="absolute -right-1 -top-1 grid h-5 min-w-5 place-items-center rounded-full bg-vermilion px-1 text-[11px] font-bold text-paper" x-text="cartCount()"></span>
                </button>

                <!-- Logged OUT: login pill -->
                <a href="/login" x-show="!loggedIn" x-cloak class="hidden items-center gap-2 rounded-full bg-ink py-2.5 pl-4 pr-4 text-sm font-bold text-paper transition hover:bg-vermilion sm:flex">Intră în cont</a>

                <!-- Logged IN: account dropdown -->
                <div class="relative hidden sm:block" x-show="loggedIn" x-cloak>
                    <button @click="accountOpen=!accountOpen; cartOpen=false" class="flex items-center gap-2 rounded-full bg-ink py-2 pl-2 pr-4 text-sm font-bold text-paper transition hover:bg-vermilion" aria-haspopup="menu" :aria-expanded="accountOpen.toString()">
                        <span class="grid h-7 w-7 place-items-center rounded-full bg-paper text-ink" x-text="acct.initials">?</span>
                        <span x-text="acct.firstName">Cont</span>
                        <svg viewBox="0 0 24 24" class="h-4 w-4 transition" :class="accountOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
                    </button>

                    <div x-show="accountOpen" x-cloak x-transition.origin.top.right @click.outside="accountOpen=false" class="absolute right-0 top-[calc(100%+12px)] w-[360px] overflow-hidden rounded-[2rem] border-2 border-ink bg-paper text-ink shadow-deep" role="menu">
                        <div class="bo-grain relative">
                            <div class="relative border-b-2 border-dashed border-ink/15 bg-ink p-5 text-paper">
                                <div class="flex items-center gap-3">
                                    <span class="grid h-12 w-12 place-items-center rounded-full bg-paper text-xl font-bold text-ink" x-text="acct.initials">?</span>
                                    <div class="min-w-0">
                                        <p class="font-display text-3xl font-bold leading-none" x-text="acct.firstName">Cont</p>
                                        <p class="truncate text-sm text-paper/50" x-text="acct.email"></p>
                                    </div>
                                </div>
                                <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                    <div class="rounded-2xl bg-paper/10 p-3"><p class="font-display text-2xl font-bold" x-text="stats.tickets"></p><p class="text-[11px] text-paper/50">bilete</p></div>
                                    <div class="rounded-2xl bg-paper/10 p-3"><p class="font-display text-2xl font-bold" x-text="stats.points"></p><p class="text-[11px] text-paper/50">puncte</p></div>
                                    <div class="rounded-2xl bg-paper/10 p-3"><p class="font-display text-2xl font-bold"><span x-text="stats.profile"></span>%</p><p class="text-[11px] text-paper/50">profil</p></div>
                                </div>
                            </div>
                            <nav class="relative p-3 text-sm font-bold" role="none">
                                <a href="/cont" class="flex items-center justify-between rounded-2xl px-4 py-3 transition hover:bg-ink hover:text-paper" role="menuitem"><span>Dashboard</span><span aria-hidden="true">→</span></a>
                                <a href="/cont/bilete" class="flex items-center justify-between rounded-2xl px-4 py-3 transition hover:bg-ink hover:text-paper" role="menuitem"><span>Biletele mele</span><span x-show="stats.tickets" class="rounded-full bg-vermilion px-2 py-0.5 text-xs text-paper" x-text="stats.tickets"></span></a>
                                <a href="/cont/comenzi" class="flex items-center justify-between rounded-2xl px-4 py-3 transition hover:bg-ink hover:text-paper" role="menuitem"><span>Comenzile mele</span><span x-text="stats.orders"></span></a>
                                <a href="/cont/puncte" class="flex items-center justify-between rounded-2xl px-4 py-3 transition hover:bg-ink hover:text-paper" role="menuitem"><span>Punctele mele</span><span x-text="stats.points"></span></a>
                                <a href="/cont/recomandari" class="flex items-center justify-between rounded-2xl px-4 py-3 transition hover:bg-ink hover:text-paper" role="menuitem"><span>Recomandări</span><span class="text-vermilion">nou</span></a>
                                <a href="/cont/tichete-support" class="flex items-center justify-between rounded-2xl px-4 py-3 transition hover:bg-ink hover:text-paper" role="menuitem"><span>Tichete support</span><span x-show="stats.support" x-text="stats.support"></span></a>
                                <a href="/cont/setari" class="flex items-center justify-between rounded-2xl px-4 py-3 transition hover:bg-ink hover:text-paper" role="menuitem"><span>Setări cont</span><span aria-hidden="true">→</span></a>
                            </nav>
                            <div class="relative border-t border-ink/10 p-3">
                                <button type="button" @click="logout()" class="flex w-full items-center justify-center rounded-full border-2 border-ink px-4 py-3 text-sm font-bold transition hover:bg-ink hover:text-paper">Ieși din cont</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile burger -->
                <button @click="mobileOpen=!mobileOpen" class="grid h-11 w-11 place-items-center rounded-full border-2 border-ink bg-paper text-ink transition hover:bg-ink hover:text-paper lg:hidden" aria-label="Deschide meniul" :aria-expanded="mobileOpen.toString()">
                    <svg x-show="!mobileOpen" viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <svg x-show="mobileOpen" x-cloak viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
        </nav>

        <!-- Desktop mega menu -->
        <div x-show="mega" x-cloak x-transition.opacity.duration.180ms @mouseleave="mega=null"
             class="absolute inset-x-0 top-full hidden border-b-2 border-ink bg-paper shadow-deep lg:block" role="menu">
            <div class="bo-grain relative">
                <div class="relative mx-auto max-w-[1500px] px-6 py-6">
                    <!-- Categories mega -->
                    <div x-show="mega==='categories'" class="grid gap-6 xl:grid-cols-[1.2fr_.8fr_.8fr]">
                        <section class="rounded-[2rem] border-2 border-ink bg-ink p-6 text-paper">
                            <p class="font-mono text-xs tracking-[.18em] text-ochre">DESCOPERĂ</p>
                            <h2 class="mt-3 font-display text-5xl font-bold leading-[.9]">Alege după ce vrei să faci, nu după ce trebuie să cauți.</h2>
                            <p class="mt-4 text-paper/60">Activități pentru weekend, copii, grupuri, cadouri, vreme ploioasă sau ieșiri rapide după muncă.</p>
                            <div class="mt-5 flex flex-wrap gap-2">
                                <a href="/activitati-weekend" class="rounded-full bg-paper px-4 py-2 text-sm font-bold text-ink transition hover:bg-vermilion hover:text-paper">Weekend</a>
                                <a href="/activitati-copii" class="rounded-full bg-paper px-4 py-2 text-sm font-bold text-ink transition hover:bg-vermilion hover:text-paper">Cu copiii</a>
                                <a href="/card-cadou" class="rounded-full bg-paper px-4 py-2 text-sm font-bold text-ink transition hover:bg-vermilion hover:text-paper">Cadouri</a>
                            </div>
                        </section>

                        <section class="rounded-[2rem] border border-ink/10 bg-paper-2 p-5">
                            <p class="font-mono text-xs tracking-[.18em] text-ink-soft">CATEGORII PRINCIPALE</p>
                            <div class="mt-4 grid gap-2">
                                <?php foreach ($navCategories as $cat): ?>
                                    <a href="<?= htmlspecialchars($cat['href'], ENT_QUOTES) ?>" @click="mega=null" class="group flex items-center justify-between rounded-2xl bg-paper px-4 py-3 font-bold transition hover:bg-ink hover:text-paper">
                                        <span class="flex items-center gap-2">
                                            <?php if (!empty($cat['icon_emoji'])): ?><span aria-hidden="true"><?= htmlspecialchars($cat['icon_emoji']) ?></span><?php endif; ?>
                                            <span><?= htmlspecialchars($cat['label']) ?></span>
                                        </span>
                                        <span class="text-xs text-ink-soft group-hover:text-paper/50"><?= htmlspecialchars($cat['count'] ?? '') ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <a href="/categorii" @click="mega=null" class="mt-3 inline-flex font-bold text-vermilion underline-wobble">Vezi toate categoriile →</a>
                        </section>

                        <section class="rounded-[2rem] border border-ink/10 bg-paper-2 p-5">
                            <p class="font-mono text-xs tracking-[.18em] text-ink-soft">CĂUTĂRI RAPIDE</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <?php foreach ($navQuickSearches as $tag): ?>
                                    <a href="<?= htmlspecialchars($tag['href'], ENT_QUOTES) ?>" @click="mega=null" class="rounded-full bg-paper px-4 py-2 text-sm font-bold transition hover:bg-vermilion hover:text-paper"><?= htmlspecialchars($tag['label']) ?></a>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-5 rounded-2xl border border-forest/20 bg-mint p-4">
                                <p class="font-bold text-forest">Ai puncte bonus?</p>
                                <p class="mt-1 text-sm text-ink-soft">Intră în cont și vezi activitățile unde poți aplica reducere.</p>
                                <a href="/cont/recomandari" class="mt-3 inline-flex font-bold text-forest underline-wobble">Vezi recomandări</a>
                            </div>
                        </section>
                    </div>

                    <!-- Cities mega -->
                    <div x-show="mega==='cities'" class="grid gap-6 xl:grid-cols-[.9fr_1.1fr_1fr]">
                        <section class="rounded-[2rem] border-2 border-ink bg-vermilion p-6 text-paper">
                            <p class="font-mono text-xs tracking-[.18em] text-paper/60">ORAȘE</p>
                            <h2 class="mt-3 font-display text-5xl font-bold leading-[.9]">Ce poți face aproape de tine?</h2>
                            <p class="mt-4 text-paper/75">Alege orașul și vezi activități, locații, ghiduri și recomandări locale.</p>
                            <a href="/orase" class="mt-5 inline-flex rounded-full bg-paper px-5 py-3 font-bold text-ink transition hover:bg-ink hover:text-paper">Toate orașele</a>
                        </section>

                        <section class="rounded-[2rem] border border-ink/10 bg-paper-2 p-5">
                            <p class="font-mono text-xs tracking-[.18em] text-ink-soft">POPULARE</p>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <?php foreach ($navCities as $city): ?>
                                    <a href="<?= htmlspecialchars($city['href'], ENT_QUOTES) ?>" @click="mega=null" class="rounded-2xl bg-paper p-4 transition hover:bg-ink hover:text-paper">
                                        <span class="block font-display text-2xl font-bold leading-none"><?= htmlspecialchars($city['label']) ?></span>
                                        <span class="mt-1 block text-xs font-bold text-ink-soft">Activități locale</span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <section class="rounded-[2rem] border border-ink/10 bg-paper-2 p-5">
                            <p class="font-mono text-xs tracking-[.18em] text-ink-soft">GHIDURI & IDEI</p>
                            <div class="mt-4 space-y-3">
                                <?php foreach ($navGuides as $guide): ?>
                                    <a href="<?= htmlspecialchars($guide['href'], ENT_QUOTES) ?>" @click="mega=null" class="group block rounded-2xl bg-paper p-4 transition hover:bg-ink hover:text-paper">
                                        <span class="block font-bold"><?= htmlspecialchars($guide['title']) ?></span>
                                        <span class="mt-1 block text-sm text-ink-soft group-hover:text-paper/50"><?= htmlspecialchars($guide['meta']) ?></span>
                                    </a>
                                <?php endforeach; ?>
                                <a href="/ghiduri" @click="mega=null" class="inline-flex font-bold text-vermilion underline-wobble">Toate ghidurile →</a>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile menu -->
        <div x-show="mobileOpen" x-cloak x-collapse class="border-t border-ink/10 bg-paper lg:hidden">
            <div class="mx-auto max-w-[1500px] px-4 py-5 sm:px-6">
                <!-- logged-in mini card -->
                <div x-show="loggedIn" x-cloak class="mb-4 flex items-center gap-3 rounded-2xl border-2 border-ink bg-paper-2 p-4">
                    <span class="grid h-11 w-11 place-items-center rounded-full bg-forest text-paper font-bold" x-text="acct.initials">?</span>
                    <div class="min-w-0">
                        <p class="font-bold leading-tight" x-text="acct.firstName">Cont</p>
                        <a href="/cont" class="text-sm text-vermilion">Mergi în cont →</a>
                    </div>
                </div>

                <button @click="searchOpen=true; mobileOpen=false" class="mb-4 flex w-full items-center gap-3 rounded-2xl border-2 border-ink bg-paper-2 px-4 py-4 text-left font-bold">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    Caută activități, orașe, locații
                </button>

                <div class="grid gap-3">
                    <a href="/categorii" class="rounded-2xl bg-ink px-4 py-4 font-display text-3xl font-bold text-paper">Categorii</a>
                    <a href="/orase" class="rounded-2xl bg-paper-2 px-4 py-4 font-display text-3xl font-bold">Orașe</a>
                    <a href="/operatori" class="rounded-2xl bg-paper-2 px-4 py-4 font-display text-3xl font-bold">Operatori</a>
                    <a href="/ghiduri" class="rounded-2xl bg-paper-2 px-4 py-4 font-display text-3xl font-bold">Ghiduri</a>
                    <a href="/card-cadou" class="rounded-2xl bg-mint px-4 py-4 font-display text-3xl font-bold text-forest">Card cadou</a>
                    <a href="/pentru-locatii" x-show="!isClient" class="rounded-2xl bg-vermilion px-4 py-4 font-display text-3xl font-bold text-paper">Pentru locații</a>
                </div>

                <!-- quick city chips (live) -->
                <div class="mt-5">
                    <p class="font-mono text-[10px] tracking-[.2em] text-ink-soft mb-2">ORAȘE POPULARE</p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach (array_slice($navCities, 0, 6) as $city): ?>
                            <a href="<?= htmlspecialchars($city['href'], ENT_QUOTES) ?>" class="rounded-full border border-ink/15 bg-paper-2 px-3 py-1.5 text-sm font-bold"><?= htmlspecialchars($city['label']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-3">
                    <button @click="openCart(); mobileOpen=false" class="rounded-full bg-vermilion px-5 py-3 text-center font-bold text-paper">Coș (<span x-text="cartCount()"></span>)</button>
                    <a x-show="loggedIn" href="/cont" class="rounded-full bg-ink px-5 py-3 text-center font-bold text-paper">Cont</a>
                    <a x-show="!loggedIn" href="/login" class="rounded-full bg-ink px-5 py-3 text-center font-bold text-paper">Intră în cont</a>
                    <a href="/recuperare-comanda" class="col-span-2 rounded-full border-2 border-ink px-5 py-3 text-center font-bold">Recuperează comanda</a>
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
            <div class="relative border-b-2 border-dashed border-ink/15 bg-ink p-5 text-paper sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-mono text-xs tracking-[.18em] text-ochre">COȘUL TĂU</p>
                        <h2 class="mt-2 font-display text-5xl font-bold leading-none">Coș</h2>
                        <p class="mt-2 text-paper/60"><span x-text="cartCount()"></span> bilete · subtotal <strong class="text-paper" x-text="formatMoney(cartSubtotal())"></strong></p>
                    </div>
                    <button @click="cartOpen=false" class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-paper text-2xl font-bold text-ink transition hover:bg-vermilion hover:text-paper" aria-label="Închide coșul">×</button>
                </div>
                <div x-show="loggedIn && stats.points > 0" x-cloak class="mt-5 rounded-2xl border border-paper/10 bg-paper/10 p-4">
                    <div class="flex items-center justify-between gap-4">
                        <span class="font-bold">Puncte disponibile</span>
                        <span class="font-display text-2xl font-bold text-ochre" x-text="stats.points"></span>
                    </div>
                    <p class="mt-1 text-sm text-paper/50">Le poți aplica în checkout, dacă activitatea este eligibilă.</p>
                </div>
            </div>

            <div class="relative flex-1 overflow-auto p-4 sm:p-5">
                <template x-if="cartItems.length === 0">
                    <div class="grid h-full place-items-center text-center">
                        <div>
                            <p class="font-display text-5xl font-bold leading-none">Coșul este gol.</p>
                            <p class="mt-3 text-ink-soft">Alege o activitate și adaugă bilete pentru a continua.</p>
                            <a href="/categorii" class="mt-5 inline-flex rounded-full bg-vermilion px-6 py-4 font-bold text-paper transition hover:bg-vermilion-d">Explorează activități</a>
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
                                            <a :href="item.url" class="font-display text-2xl font-bold leading-none hover:text-vermilion" x-text="item.title"></a>
                                            <p class="mt-1 text-sm text-ink-soft" x-text="item.location"></p>
                                        </div>
                                        <button @click="removeCartItem(item.key)" class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-rose text-vermilion transition hover:bg-vermilion hover:text-paper" aria-label="Șterge produsul">×</button>
                                    </div>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <span x-show="item.date" class="rounded-full bg-paper-2 px-3 py-1 text-xs font-bold" x-text="item.date"></span>
                                        <span x-show="item.type" class="rounded-full bg-mint px-3 py-1 text-xs font-bold text-forest" x-text="item.type"></span>
                                    </div>
                                    <div class="mt-4 flex items-center justify-between gap-3">
                                        <div class="inline-flex items-center overflow-hidden rounded-full border-2 border-ink">
                                            <button @click="decreaseQty(item.key)" class="grid h-10 w-10 place-items-center font-bold transition hover:bg-ink hover:text-paper" aria-label="Scade">−</button>
                                            <span class="grid h-10 w-10 place-items-center border-x-2 border-ink font-bold" x-text="item.qty"></span>
                                            <button @click="increaseQty(item.key)" class="grid h-10 w-10 place-items-center font-bold transition hover:bg-ink hover:text-paper" aria-label="Crește">+</button>
                                        </div>
                                        <p class="font-display text-2xl font-bold" x-text="formatMoney(item.qty * item.price)"></p>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>
            </div>

            <div class="relative border-t-2 border-dashed border-ink/15 bg-paper-2 p-5 sm:p-6" x-show="cartItems.length">
                <div class="flex items-end justify-between gap-4">
                    <span class="font-bold">Subtotal</span>
                    <strong class="font-display text-4xl leading-none" x-text="formatMoney(cartSubtotal())"></strong>
                </div>
                <div class="mt-5 grid gap-2">
                    <a href="/checkout" class="rounded-full bg-vermilion px-6 py-4 text-center font-bold text-paper transition hover:bg-vermilion-d">Continuă la checkout</a>
                    <a href="/cos" class="rounded-full border-2 border-ink px-6 py-4 text-center font-bold transition hover:bg-ink hover:text-paper">Vezi pagina coșului</a>
                </div>
                <p class="mt-3 text-xs leading-relaxed text-ink-soft">Taxele finale, comisionul, punctele aplicabile și protecția bilet se calculează în checkout.</p>
            </div>
        </aside>
    </div>

    <!-- Search overlay -->
    <div x-show="searchOpen" x-cloak x-transition.opacity.duration.180ms class="fixed inset-0 z-[60] bg-ink/75 p-3 backdrop-blur-sm sm:p-5" role="dialog" aria-modal="true" aria-label="Caută pe bilete.online">
        <div @click.outside="searchOpen=false" class="mx-auto mt-12 max-w-4xl overflow-hidden rounded-[2rem] border-2 border-ink bg-paper shadow-deep">
            <div class="bo-grain relative">
                <form @submit.prevent="doSearch()" class="relative border-b-2 border-dashed border-ink/15 p-4 sm:p-5">
                    <div class="flex items-center gap-3">
                        <svg viewBox="0 0 24 24" class="h-6 w-6 shrink-0 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <input x-ref="searchInput" x-model="searchQuery" type="search" placeholder="Caută escape rooms, activități copii, muzee, Brașov..." class="w-full bg-transparent py-3 text-xl font-bold outline-none placeholder:text-ink-soft/70 sm:text-2xl">
                        <button type="button" @click="searchOpen=false" class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-ink text-xl font-bold text-paper transition hover:bg-vermilion">×</button>
                    </div>
                </form>

                <div class="relative grid max-h-[70vh] overflow-auto lg:grid-cols-[1fr_320px]">
                    <section class="p-5 sm:p-6">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">SUGESTII</p>
                        <div class="mt-4 grid gap-3">
                            <template x-for="item in filteredSearch()" :key="item.href">
                                <a :href="item.href" class="group rounded-3xl border border-ink/10 bg-paper-2 p-4 transition hover:border-ink hover:bg-paper">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <span class="rounded-full bg-paper px-3 py-1 text-xs font-bold text-ink-soft" x-text="item.type"></span>
                                            <h3 class="mt-3 font-display text-3xl font-bold leading-none group-hover:text-vermilion" x-text="item.title"></h3>
                                            <p class="mt-1 text-ink-soft" x-text="item.meta"></p>
                                        </div>
                                        <span class="text-2xl transition group-hover:translate-x-1" aria-hidden="true">→</span>
                                    </div>
                                </a>
                            </template>
                            <template x-if="filteredSearch().length === 0">
                                <button type="button" @click="doSearch()" class="rounded-3xl border-2 border-dashed border-ink/20 p-5 text-left">
                                    <span class="font-bold">Caută „<span x-text="searchQuery"></span>” pe tot site-ul</span>
                                    <span class="mt-1 block text-sm text-ink-soft">Apasă Enter pentru rezultate complete.</span>
                                </button>
                            </template>
                        </div>
                    </section>

                    <aside class="border-t-2 border-dashed border-ink/15 bg-paper-2/60 p-5 sm:p-6 lg:border-l-2 lg:border-t-0">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">RAPID</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <template x-for="tag in quickSearches" :key="tag.href">
                                <a :href="tag.href" class="rounded-full bg-paper px-4 py-2 text-sm font-bold transition hover:bg-ink hover:text-paper" x-text="tag.label"></a>
                            </template>
                        </div>
                        <div class="mt-6 rounded-3xl bg-ink p-5 text-paper">
                            <p class="font-mono text-xs tracking-[.18em] text-paper/40">CONT</p>
                            <h3 class="mt-2 font-display text-4xl font-bold leading-none">Ai bilete cumpărate?</h3>
                            <p class="mt-3 text-paper/60">Le găsești rapid în cont sau prin recuperare comandă.</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="/cont/bilete" class="rounded-full bg-vermilion px-4 py-2 text-sm font-bold text-paper">Biletele mele</a>
                                <a href="/recuperare-comanda" class="rounded-full bg-paper px-4 py-2 text-sm font-bold text-ink">Recuperează</a>
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
 * Header Alpine component (v3). Auth state (login pill vs account dropdown),
 * user info + dashboard stats, cart drawer and search overlay are all driven
 * by live data: BileteOnlineAuth / BileteOnlineAPI / BileteOnlineCart.
 */
function bileteOnlineHeader(seed) {
    return {
        scrolled: false,
        mobileOpen: false,
        searchOpen: false,
        cartOpen: false,
        accountOpen: false,
        mega: null,
        searchQuery: '',
        loggedIn: false,
        isClient: false,
        acct: { firstName: 'Cont', name: 'Client', email: '', initials: '?' },
        stats: { tickets: 0, points: 0, orders: 0, profile: 0, support: 0 },
        cartItems: [],
        searchItems: (seed && seed.searchItems) || [],
        quickSearches: (seed && seed.quickSearches) || [],
        activityResults: [],
        _searchTimer: null,

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
                    this.cartOpen = false; this.accountOpen = false; this.mega = null;
                    this.$nextTick(() => this.$refs.searchInput && this.$refs.searchInput.focus());
                }
            });
            // Live activity search (debounced). The static suggestions only cover
            // categories/cities, so query the activities API for matching names.
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

            // Cart: load now + whenever it changes. Also re-run on window load,
            // since cart.js is a deferred footer script that may execute after
            // Alpine's init (so the badge shows the right count on first paint).
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
        openCart() { this.cartOpen = true; this.accountOpen = false; this.mega = null; this.loadCart(); },
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
            // Live activity matches first, then category/city suggestions.
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
        closeAll() { this.searchOpen = false; this.mobileOpen = false; this.cartOpen = false; this.accountOpen = false; this.mega = null; },
    };
}
</script>

<?php if (!$skipMainTag): ?>
<main id="top" role="main">
<?php endif; ?>
