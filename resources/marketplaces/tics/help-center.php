<?php
/**
 * TICS.ro - Help Center Page
 * Knowledge base hub with search, category cards, popular articles, and contact strip
 */

require_once __DIR__ . '/includes/config.php';

// ============================================================================
// HELP CENTER CATEGORIES
// ============================================================================

$helpCategories = [
    [
        'title'       => 'Bilete & comenzi',
        'slug'        => 'bilete-comenzi',
        'description' => "Achizi\xC8\x9Bie, primire, transfer, returnare bilete \xC8\x99i istoricul comenzilor.",
        'emoji'       => '&#127915;',
        'emojiBg'     => 'bg-indigo-50',
        'accent'      => 'indigo',
        'count'       => 12,
        'animDelay'   => 'ad1',
    ],
    [
        'title'       => "Pl\xC4\x83\xC8\x9Bi & facturare",
        'slug'        => 'plati-facturare',
        'description' => "Metode de plat\xC4\x83, carduri beneficii, facturi, ramburs\xC4\x83ri \xC8\x99i probleme de plat\xC4\x83.",
        'emoji'       => '&#128179;',
        'emojiBg'     => 'bg-emerald-50',
        'accent'      => 'emerald',
        'count'       => 8,
        'animDelay'   => 'ad2',
    ],
    [
        'title'       => 'Cont & profil',
        'slug'        => 'cont-profil',
        'description' => "\xC3\x8Enregistrare, autentificare, set\xC4\x83ri profil, parol\xC4\x83, notific\xC4\x83ri \xC8\x99i preferin\xC8\x9Be.",
        'emoji'       => '&#128100;',
        'emojiBg'     => 'bg-violet-50',
        'accent'      => 'violet',
        'count'       => 6,
        'animDelay'   => 'ad3',
    ],
    [
        'title'       => 'Pentru organizatori',
        'slug'        => 'pentru-organizatori',
        'description' => "Creare eveniment, dashboard, set\xC4\x83ri preturi, scanare, fiscalizare \xC8\x99i rapoarte.",
        'emoji'       => '&#127970;',
        'emojiBg'     => 'bg-amber-50',
        'accent'      => 'amber',
        'count'       => 10,
        'animDelay'   => 'ad4',
    ],
    [
        'title'       => "Aplica\xC8\x9Bia mobil\xC4\x83",
        'slug'        => 'aplicatia-mobila',
        'description' => "Instalare, func\xC8\x9Bionalit\xC4\x83\xC8\x9Bi, offline mode, notific\xC4\x83ri push \xC8\x99i troubleshooting.",
        'emoji'       => '&#128241;',
        'emojiBg'     => 'bg-rose-50',
        'accent'      => 'rose',
        'count'       => 5,
        'animDelay'   => 'ad5',
    ],
    [
        'title'       => "API & Integr\xC4\x83ri",
        'slug'        => 'api-integrari',
        'description' => "Documenta\xC8\x9Bie API, webhooks, SDK-uri, autentificare \xC8\x99i exemple de integrare.",
        'emoji'       => '&#9881;&#65039;',
        'emojiBg'     => 'bg-sky-50',
        'accent'      => 'sky',
        'count'       => 4,
        'animDelay'   => 'ad6',
    ],
];

// ============================================================================
// POPULAR ARTICLES
// ============================================================================

$popularArticles = [
    [
        'title'    => "Cum primesc biletul dup\xC4\x83 achizi\xC8\x9Bie?",
        'catSlug'  => 'bilete-comenzi',
        'artSlug'  => 'cum-primesc-biletul',
        'category' => 'Bilete & comenzi',
        'views'    => '4.2k',
    ],
    [
        'title'    => "Ce metode de plat\xC4\x83 accepta\xC8\x9Bi?",
        'catSlug'  => 'plati-facturare',
        'artSlug'  => 'metode-de-plata',
        'category' => "Pl\xC4\x83\xC8\x9Bi & facturare",
        'views'    => '3.8k',
    ],
    [
        'title'    => 'Pot returna sau schimba un bilet?',
        'catSlug'  => 'bilete-comenzi',
        'artSlug'  => 'retur-schimb-bilet',
        'category' => 'Bilete & comenzi',
        'views'    => '2.9k',
    ],
    [
        'title'    => "C\xC3\xA2nd primesc banii din v\xC3\xA2nz\xC4\x83ri ca organizator?",
        'catSlug'  => 'pentru-organizatori',
        'artSlug'  => 'cand-primesc-banii',
        'category' => 'Pentru organizatori',
        'views'    => '2.1k',
    ],
    [
        'title'    => 'Cum transfer un bilet altcuiva?',
        'catSlug'  => 'bilete-comenzi',
        'artSlug'  => 'transfer-bilet',
        'category' => 'Bilete & comenzi',
        'views'    => '1.7k',
    ],
];

// ============================================================================
// QUICK SEARCH LINKS
// ============================================================================

$quickLinks = [
    ['label' => 'Cum primesc biletul', 'catSlug' => 'bilete-comenzi', 'artSlug' => 'cum-primesc-biletul'],
    ['label' => 'Retur bilet',         'catSlug' => 'bilete-comenzi', 'artSlug' => 'retur-schimb-bilet'],
    ['label' => "Metode de plat\xC4\x83",     'catSlug' => 'plati-facturare', 'artSlug' => 'metode-de-plata'],
    ['label' => 'Gift card',           'catSlug' => 'bilete-comenzi', 'artSlug' => 'gift-card'],
];

// ============================================================================
// CONTACT CARDS
// ============================================================================

$contactCards = [
    [
        'emoji'    => '&#128172;',
        'title'    => 'Live Chat',
        'detail'   => "R\xC4\x83spuns \xC3\xAEn sub 5 minute",
        'extra'    => '<span class="inline-flex items-center gap-1 text-xs text-green-600 font-medium"><span class="w-1.5 h-1.5 bg-green-500 rounded-full"></span>Online acum</span>',
    ],
    [
        'emoji'    => '&#9993;&#65039;',
        'title'    => 'Email',
        'detail'   => 'support@tics.ro',
        'extra'    => '<span class="text-xs text-gray-400">R&#259;spuns mediu: &lt; 2h</span>',
    ],
    [
        'emoji'    => '&#128222;',
        'title'    => 'Telefon',
        'detail'   => '+40 368 XXX XXX',
        'extra'    => '<span class="text-xs text-gray-400">L-V, 09:00 - 18:00</span>',
    ],
];

// ============================================================================
// PAGE SETTINGS
// ============================================================================

$pageTitle = 'Centru de Ajutor';
$pageDescription = "G\xC4\x83se\xC8\x99te r\xC4\x83spunsuri rapide \xC3\xAEn documenta\xC8\x9Bia TICS.ro sau contacteaz\xC4\x83-ne direct. Bilete, pl\xC4\x83\xC8\x9Bi, cont, organizatori \xC8\x99i API.";
$bodyClass = 'bg-white';

$breadcrumbs = [
    ['name' => "Acas\xC4\x83", 'url' => '/'],
    ['name' => 'Centru de ajutor', 'url' => null],
];

setLoginState($isLoggedIn, $loggedInUser);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

    <!-- Hero -->
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-gray-950 via-gray-900 to-gray-950"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(99,102,241,.15),transparent_50%)]"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_70%_80%,rgba(139,92,246,.1),transparent_50%)]"></div>
        <!-- Floating shapes -->
        <div class="absolute top-16 right-[15%] w-2 h-2 bg-indigo-400/30 rounded-full float-s"></div>
        <div class="absolute top-28 left-[20%] w-1.5 h-1.5 bg-violet-400/30 rounded-full float-s" style="animation-delay:2s"></div>
        <div class="absolute bottom-20 right-[25%] w-2.5 h-2.5 bg-indigo-400/20 rounded-full float-s" style="animation-delay:4s"></div>

        <div class="max-w-6xl mx-auto px-4 lg:px-8 py-16 lg:py-24 relative">
            <div class="text-center max-w-2xl mx-auto">
                <h1 class="anim text-3xl lg:text-5xl font-bold text-white mb-4 leading-tight">Cu ce te putem ajuta<span class="text-indigo-400">?</span></h1>
                <p class="anim ad1 text-gray-400 text-lg mb-10">Caut&#259; &#238;n documenta&#539;ie sau navigheaz&#259; pe categorii</p>
                <!-- Search -->
                <div class="anim ad2 search-wrap bg-white rounded-2xl border border-gray-200 flex items-center gap-3 px-5 py-4 max-w-xl mx-auto">
                    <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" placeholder="ex: cum returnez un bilet, metode de plata, api..." class="flex-1 bg-transparent text-sm outline-none placeholder:text-gray-400 text-gray-900">
                    <kbd class="hidden md:inline-flex items-center px-2 py-0.5 bg-gray-100 text-gray-400 text-[10px] font-mono rounded border border-gray-200">/</kbd>
                </div>
                <!-- Quick links -->
                <div class="anim ad3 flex items-center justify-center gap-2 mt-5 flex-wrap">
                    <span class="text-xs text-gray-500">Popular:</span>
                    <?php foreach ($quickLinks as $i => $link): ?>
                        <?php if ($i > 0): ?><span class="text-gray-700">&middot;</span><?php endif; ?>
                        <a href="/centru-ajutor/<?= e($link['catSlug']) ?>/<?= e($link['artSlug']) ?>" class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors"><?= e($link['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <main class="max-w-6xl mx-auto px-4 lg:px-8 -mt-4 relative z-10 pb-20">

        <!-- Categories Grid -->
        <section class="mb-16">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($helpCategories as $cat): ?>
                <a href="/centru-ajutor/<?= e($cat['slug']) ?>" class="anim <?= e($cat['animDelay']) ?> help-cat-card bg-white rounded-2xl border border-gray-200 p-6 block" data-accent="<?= e($cat['accent']) ?>">
                    <div class="w-12 h-12 <?= e($cat['emojiBg']) ?> rounded-xl flex items-center justify-center text-2xl mb-4"><?= $cat['emoji'] ?></div>
                    <h3 class="font-semibold text-gray-900 mb-1"><?= e($cat['title']) ?></h3>
                    <p class="text-sm text-gray-500 mb-4 leading-relaxed"><?= e($cat['description']) ?></p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-400"><?= e($cat['count']) ?> articole</span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Popular Articles -->
        <section class="mb-16">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2"><svg class="w-5 h-5 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.214.33-.403.713-.57 1.116-.334.804-.614 1.768-.84 2.734a31.365 31.365 0 00-.613 3.58 2.64 2.64 0 01-.945-1.067c-.328-.68-.398-1.534-.398-2.654A1 1 0 005.05 6.05 6.981 6.981 0 003 11a7 7 0 1011.95-4.95c-.592-.591-.98-.985-1.348-1.467-.363-.476-.724-1.063-1.207-2.03zM12.12 15.12A3 3 0 017 13s.879.5 2.5.5c0-1 .5-4 1.25-4.5.5 1 .786 1.293 1.371 1.879A2.99 2.99 0 0113 13a2.99 2.99 0 01-.879 2.121z"/></svg>Cele mai citite</h2>
                <a href="#" class="text-sm text-gray-500 hover:text-gray-900 transition-colors">Vezi toate &#8594;</a>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 divide-y divide-gray-100">
                <?php foreach ($popularArticles as $i => $article): ?>
                <a href="/centru-ajutor/<?= e($article['catSlug']) ?>/<?= e($article['artSlug']) ?>" class="popular-item flex items-center gap-4 p-4 group">
                    <span class="w-8 h-8 bg-gray-100 rounded-lg flex items-center justify-center text-sm font-bold text-gray-400 flex-shrink-0 group-hover:bg-indigo-100 group-hover:text-indigo-600 transition-colors"><?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 group-hover:text-indigo-600 transition-colors"><?= e($article['title']) ?></p>
                        <p class="text-xs text-gray-400 mt-0.5"><?= e($article['category']) ?> &middot; <?= e($article['views']) ?> vizualiz&#259;ri</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-300 group-hover:text-indigo-400 transition-colors flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Contact strip -->
        <section class="grid sm:grid-cols-3 gap-4">
            <?php foreach ($contactCards as $card): ?>
            <div class="bg-gray-50 rounded-2xl border border-gray-100 p-6 text-center hover:border-gray-200 transition-colors">
                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center text-2xl mx-auto mb-3 shadow-sm"><?= $card['emoji'] ?></div>
                <h3 class="font-semibold text-gray-900 text-sm mb-1"><?= e($card['title']) ?></h3>
                <p class="text-xs text-gray-500 mb-3"><?= e($card['detail']) ?></p>
                <?= $card['extra'] ?>
            </div>
            <?php endforeach; ?>
        </section>
    </main>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
