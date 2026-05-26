<?php
/**
 * bilete.online — site header
 *
 * Opens <body>, renders the sticky header (top strip, primary nav with mega
 * menu, user dropdown, mobile drawer), and opens <main id="top"> so pages can
 * drop in their <section> blocks directly. footer.php closes </main>, the
 * footer element, and </body></html>.
 *
 * Variables a page can set BEFORE include:
 *   $currentPage     — slug used for aria-current="page"
 *   $navCategories   — array of mega-menu category items
 *                       each: ['label','href','count','accent' (vermilion|sky|forest|ochre), 'icon' (svg path d)]
 *   $navCities       — array of ['label','href'] city items
 *   $bodyClass       — extra classes appended to <body>
 *   $skipMainTag     — true if the page renders its own <main> (e.g. embed pages)
 */

if (!defined('BILETEONLINE_ROOT')) {
    require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/nav-helpers.php';

$currentPage = $currentPage ?? '';
$bodyClass = $bodyClass ?? '';
$skipMainTag = $skipMainTag ?? false;

// Mega menu data pulled from the marketplace API via cached helpers.
// Pages can override $navCategories / $navCities before include if they want
// to render a different set (e.g. cross-link nav on /categorii itself).
$navCategories = $navCategories ?? navGetCategories(6);
$navCities = $navCities ?? navGetCities(8);

// Accent → Tailwind class map (allowlist, safelisted in tailwind.config.cjs)
$accentMap = [
    'vermilion' => ['icon' => 'bg-vermilion/10 text-vermilion', 'hover' => 'group-hover:bg-vermilion group-hover:text-paper'],
    'sky'       => ['icon' => 'bg-sky/10 text-sky',             'hover' => 'group-hover:bg-sky group-hover:text-paper'],
    'forest'    => ['icon' => 'bg-forest/10 text-forest',       'hover' => 'group-hover:bg-forest group-hover:text-paper'],
    'ochre'     => ['icon' => 'bg-ochre/15 text-ochre',         'hover' => 'group-hover:bg-ochre group-hover:text-paper'],
];
?>
<body class="grain font-sans antialiased selection:bg-vermilion selection:text-paper<?= $bodyClass ? ' ' . htmlspecialchars($bodyClass, ENT_QUOTES) : '' ?>">

<a href="#top" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[100] focus:px-4 focus:py-2 focus:bg-ink focus:text-paper focus:rounded-md">Sari la conținut</a>

<header x-data="{ open:false, scrolled:false, mega:false, user:false }"
        x-init="window.addEventListener('scroll', () => scrolled = window.scrollY > 20)"
        @keydown.escape.window="mega=false; user=false; open=false"
        class="sticky top-0 z-50"
        role="banner">

    <!-- top admission strip -->
    <div class="bg-ink text-paper/80 text-[11px] sm:text-xs font-mono tracking-wider">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-8 flex items-center justify-between">
            <span class="flex items-center gap-2">
                <span class="inline-block w-1.5 h-1.5 rounded-full bg-vermilion animate-pulse"></span>
                ADMISSION · ROMÂNIA · AGREMENT
            </span>
            <span class="hidden sm:flex items-center gap-4">
                <a href="/card-cadou" class="text-ochre underline-wobble">Carduri cadou</a>
                <span class="text-paper/40">operat de <a href="https://tixello.ro" class="text-ochre underline-wobble" rel="noopener">Tixello</a></span>
            </span>
        </div>
    </div>

    <div :class="scrolled ? 'bg-paper/95 backdrop-blur-md border-b border-ink/10 shadow-header-scroll' : 'bg-paper/70 backdrop-blur-sm border-b border-ink/5'"
         class="transition-all duration-300">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4" aria-label="Navigare principală">

            <!-- logo -->
            <a href="/" class="flex items-center gap-2.5 group shrink-0" aria-label="<?= htmlspecialchars(SITE_NAME, ENT_QUOTES) ?> — acasă">
                <span class="relative grid place-items-center w-9 h-9 bg-vermilion text-paper rounded-md -rotate-3 group-hover:rotate-3 transition-transform duration-300 shadow-logo">
                    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4Z"/>
                        <path d="M9 7v10" stroke-dasharray="2 2"/>
                    </svg>
                </span>
                <span class="font-display text-xl font-700 tracking-tight">bilete<span class="text-vermilion">.</span>online</span>
            </a>

            <!-- desktop nav + MEGA trigger -->
            <div class="hidden lg:flex items-center gap-1 text-[15px] font-500" @mouseleave="mega=false">
                <button @mouseenter="mega=true" @click="mega=!mega" :aria-expanded="mega"
                        :class="mega ? 'bg-ink text-paper' : 'hover:bg-ink/5'"
                        class="flex items-center gap-1.5 px-3.5 py-2 rounded-full transition-colors duration-200"
                        aria-label="Deschide meniul de categorii">
                    Explorează
                    <svg :class="mega && 'rotate-180'" class="w-4 h-4 transition-transform duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <a @mouseenter="mega=false" href="/cum-functioneaza" class="px-3 py-2 rounded-full hover:bg-ink/5 transition"<?= $currentPage === 'cum-functioneaza' ? ' aria-current="page"' : '' ?>>Cum funcționează</a>
                <a @mouseenter="mega=false" href="/card-cadou" class="px-3 py-2 rounded-full hover:bg-ink/5 transition"<?= $currentPage === 'card-cadou' ? ' aria-current="page"' : '' ?>>Carduri cadou</a>
                <a @mouseenter="mega=false" href="/pentru-locatii" class="px-3 py-2 rounded-full hover:bg-ink/5 transition"<?= $currentPage === 'pentru-locatii' ? ' aria-current="page"' : '' ?>>Pentru locații</a>

                <!-- ============ MEGA PANEL ============ -->
                <!-- top-[5.5rem] = sits flush right below the nav row (top strip 2rem + nav 4rem - 0.5rem overlap)
                     so mouse can move from "Explorează" into the panel without losing hover. Also captures
                     mouseenter on the panel itself to keep `mega=true` while pointer is inside. -->
                <div x-show="mega" x-cloak
                     @mouseenter="mega=true"
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="fixed left-1/2 -translate-x-1/2 top-[5.5rem] pt-2 w-[min(1080px,calc(100vw-2rem))] grain"
                     role="menu" aria-label="Categorii și orașe">
                    <div class="bg-paper border-2 border-ink rounded-2xl shadow-mega overflow-hidden grid grid-cols-12">

                        <!-- categories -->
                        <div class="col-span-7 p-7 border-r-2 border-dashed border-ink/15">
                            <p class="font-mono text-[10px] tracking-[.2em] text-ink-soft mb-4">EXPLOREAZĂ DUPĂ CATEGORIE</p>
                            <div class="grid grid-cols-2 gap-1.5">
                                <?php foreach ($navCategories as $cat):
                                    $a = $accentMap[$cat['accent']] ?? $accentMap['vermilion'];
                                ?>
                                <a href="<?= htmlspecialchars($cat['href'], ENT_QUOTES) ?>" @click="mega=false" class="mega-link group flex items-start gap-3 p-3 rounded-xl hover:bg-paper-2">
                                    <span class="grid place-items-center w-10 h-10 rounded-lg <?= $a['icon'] ?> shrink-0 <?= $a['hover'] ?> transition-colors text-xl leading-none" aria-hidden="true">
                                        <?php if (!empty($cat['icon_emoji'])): ?>
                                            <?= htmlspecialchars($cat['icon_emoji']) ?>
                                        <?php elseif (!empty($cat['icon'])): ?>
                                            <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="<?= htmlspecialchars($cat['icon'], ENT_QUOTES) ?>"/></svg>
                                        <?php else: ?>
                                            🎫
                                        <?php endif; ?>
                                    </span>
                                    <span>
                                        <span class="block font-600 leading-tight"><?= htmlspecialchars($cat['label']) ?></span>
                                        <span class="block text-xs text-ink-soft mt-0.5"><?= htmlspecialchars($cat['count']) ?></span>
                                    </span>
                                </a>
                                <?php endforeach; ?>
                            </div>
                            <a href="/categorii" @click="mega=false" class="inline-flex items-center gap-1.5 mt-4 text-sm font-600 text-vermilion underline-wobble">
                                Vezi toate categoriile
                                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                            </a>
                        </div>

                        <!-- cities + gift promo -->
                        <div class="col-span-5 p-7 flex flex-col bg-paper-2/40">
                            <p class="font-mono text-[10px] tracking-[.2em] text-ink-soft mb-4">ORAȘE POPULARE</p>
                            <div class="grid grid-cols-2 gap-x-5 gap-y-2 text-[15px]">
                                <?php foreach ($navCities as $city): ?>
                                <a href="<?= htmlspecialchars($city['href'], ENT_QUOTES) ?>" @click="mega=false" class="mega-link py-1 hover:text-vermilion"><?= htmlspecialchars($city['label']) ?></a>
                                <?php endforeach; ?>
                            </div>
                            <a href="/orase" @click="mega=false" class="mt-3 text-sm font-600 text-vermilion underline-wobble">Vezi toate orașele →</a>

                            <!-- gift card promo -->
                            <a href="/card-cadou" @click="mega=false" class="ticket ticket-lift mt-6 block relative bg-ink text-paper rounded-xl overflow-hidden p-4" style="--perf:100%">
                                <div class="absolute inset-0 foil opacity-50"></div>
                                <div class="relative flex items-center gap-3">
                                    <span class="grid place-items-center w-11 h-11 rounded-lg bg-vermilion text-paper shrink-0">
                                        <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 22V7m0 0S9.5 2 7 4s5 3 5 3Zm0 0s2.5-5 5-3-5 3-5 3Z"/></svg>
                                    </span>
                                    <div>
                                        <p class="font-display text-lg font-700 leading-tight">Card cadou</p>
                                        <p class="text-xs text-paper/60">Dăruiește o experiență, nu un obiect</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- right cluster -->
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <a href="/pentru-locatii" class="hidden md:inline-flex px-4 py-2 rounded-full border-2 border-ink text-sm font-600 hover:bg-ink hover:text-paper transition-colors duration-300">Listează-ți locația</a>

                <!-- LOGGED OUT (default) -->
                <a href="/login" class="hidden lg:inline-flex px-4 py-2 rounded-full bg-ink text-paper text-sm font-600 hover:bg-ink-2 transition" x-show="!user">
                    Intră în cont
                </a>

                <!-- LOGGED IN (Alpine flips to true when JS detects auth) -->
                <div class="relative hidden lg:block" x-show="user" x-cloak @click.outside="user=false">
                    <button @click="user=!user" :aria-expanded="user" class="flex items-center gap-2 pl-1 pr-2.5 py-1 rounded-full border-2 border-ink hover:bg-ink/5 transition" aria-label="Meniul utilizatorului">
                        <span class="relative grid place-items-center w-8 h-8 rounded-full bg-forest text-paper font-600 text-sm" x-text="(window.BileteOnlineAuth && BileteOnlineAuth.getUser && BileteOnlineAuth.getUser()?.first_name?.charAt(0) || '?') + ''">?</span>
                        <svg :class="user && 'rotate-180'" class="w-4 h-4 transition-transform duration-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>

                    <!-- dropdown ticket -->
                    <div x-show="user" x-cloak
                         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95 -translate-y-1" x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         class="ticket absolute right-0 top-[calc(100%+12px)] w-64 bg-paper border-2 border-ink rounded-xl shadow-2xl overflow-hidden origin-top-right" style="--perf:100%"
                         role="menu">
                        <div class="bg-forest text-paper p-4 relative">
                            <div class="absolute inset-0 opacity-15 bg-dotgrid-light"></div>
                            <p class="relative font-display text-lg font-700 leading-tight" data-user-name>Bun venit</p>
                            <p class="relative font-mono text-[11px] text-paper/60" data-user-email></p>
                        </div>
                        <div class="p-2 text-[15px]">
                            <a href="/cont/bilete" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-paper-2 transition">
                                <svg viewBox="0 0 24 24" class="w-4 h-4 text-ink-soft" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4Z"/></svg>
                                Biletele mele
                            </a>
                            <a href="/cont/carduri-cadou" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-paper-2 transition">
                                <svg viewBox="0 0 24 24" class="w-4 h-4 text-ink-soft" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 22V7"/></svg>
                                Cardurile mele cadou
                            </a>
                            <a href="/cont/favorite" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-paper-2 transition">
                                <svg viewBox="0 0 24 24" class="w-4 h-4 text-ink-soft" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21s-7-4.5-9.5-9A4.5 4.5 0 0 1 12 6a4.5 4.5 0 0 1 9.5 6c-2.5 4.5-9.5 9-9.5 9Z"/></svg>
                                Favorite
                            </a>
                            <a href="/cont/setari" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-paper-2 transition">
                                <svg viewBox="0 0 24 24" class="w-4 h-4 text-ink-soft" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.5-2.4 1a7 7 0 0 0-1.7-1l-.3-2.5h-4l-.3 2.5a7 7 0 0 0-1.7 1l-2.4-1-2 3.5L4.1 11a7 7 0 0 0 0 2l-2 1.5 2 3.5 2.4-1a7 7 0 0 0 1.7 1l.3 2.5h4l.3-2.5a7 7 0 0 0 1.7-1l2.4 1 2-3.5-2-1.5c.1-.3.1-.7.1-1Z"/></svg>
                                Setări cont
                            </a>
                        </div>
                        <div class="border-t-2 border-dashed border-ink/15 p-2">
                            <button type="button" data-logout-trigger class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-vermilion hover:bg-vermilion/5 transition">
                                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4m7 14 5-5-5-5m5 5H9"/></svg>
                                Deconectare
                            </button>
                        </div>
                    </div>
                </div>

                <!-- mobile burger -->
                <button @click="open=!open" class="lg:hidden grid place-items-center w-10 h-10 rounded-md border border-ink/15" aria-label="Deschide meniul" :aria-expanded="open">
                    <svg x-show="!open" viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <svg x-show="open" x-cloak viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
        </nav>

        <!-- mobile drawer -->
        <div x-show="open" x-cloak x-transition.origin.top class="lg:hidden border-t border-ink/10 bg-paper px-5 py-5 text-lg font-500 max-h-[80vh] overflow-y-auto" role="menu">
            <div class="flex items-center gap-3 pb-4 mb-3 border-b border-ink/10" x-show="user" x-cloak>
                <span class="grid place-items-center w-10 h-10 rounded-full bg-forest text-paper font-600" data-user-initials>?</span>
                <div>
                    <p class="text-base font-600 leading-tight" data-user-name>Bun venit</p>
                    <a href="/cont/bilete" class="text-sm text-vermilion">Biletele mele →</a>
                </div>
            </div>
            <a x-show="!user" href="/login" @click="open=false" class="block py-2 font-600 text-vermilion">Intră în cont</a>
            <a @click="open=false" href="/categorii" class="block py-2">Categorii</a>
            <a @click="open=false" href="/cum-functioneaza" class="block py-2">Cum funcționează</a>
            <a @click="open=false" href="/experiente" class="block py-2">Experiențe</a>
            <a @click="open=false" href="/card-cadou" class="block py-2">Carduri cadou</a>
            <a @click="open=false" href="/pentru-locatii" class="block py-2">Pentru locații</a>
            <a @click="open=false" href="/pentru-locatii" class="block mt-3 px-4 py-3 rounded-full bg-ink text-paper text-center font-600">Listează-ți locația</a>
        </div>
    </div>
</header>
<?php if (!$skipMainTag): ?>
<main id="top" role="main">
<?php endif; ?>
