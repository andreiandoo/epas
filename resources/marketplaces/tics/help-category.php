<?php
/**
 * TICS.ro - Help Category Page
 * Breadcrumb + hero with icon, sidebar with category nav, sub-category tabs, article list with cards
 */

require_once __DIR__ . '/includes/config.php';

// ============================================================================
// DEMO DATA: Current Category
// ============================================================================

$category = [
    'name'        => 'Bilete & comenzi',
    'slug'        => 'bilete-comenzi',
    'icon'        => '🎫',
    'description' => 'Totul despre achiziția, primirea, transferul și returnarea biletelor',
    'articleCount' => 12,
];

// ============================================================================
// DEMO DATA: Subcategories (tab filters)
// ============================================================================

$subcategories = [
    ['name' => 'Toate (12)', 'slug' => 'all',          'active' => true],
    ['name' => 'Achiziție',  'slug' => 'achizitie',    'active' => false],
    ['name' => 'Primire bilet', 'slug' => 'primire',   'active' => false],
    ['name' => 'Transfer',   'slug' => 'transfer',     'active' => false],
    ['name' => 'Returnare',  'slug' => 'returnare',    'active' => false],
];

// ============================================================================
// DEMO DATA: Sidebar Categories
// ============================================================================

$sidebarCategories = [
    ['name' => 'Bilete & comenzi',    'slug' => 'bilete-comenzi',    'icon' => '🎫', 'active' => true],
    ['name' => 'Plăți & facturare',   'slug' => 'plati-facturare',   'icon' => '💳', 'active' => false],
    ['name' => 'Cont & profil',       'slug' => 'cont-profil',       'icon' => '👤', 'active' => false],
    ['name' => 'Pentru organizatori', 'slug' => 'pentru-organizatori','icon' => '🏢', 'active' => false],
    ['name' => 'Aplicația mobilă',    'slug' => 'aplicatia-mobila',  'icon' => '📱', 'active' => false],
    ['name' => 'API & Integrări',     'slug' => 'api-integrari',     'icon' => '⚙️', 'active' => false],
];

// ============================================================================
// DEMO DATA: Articles
// ============================================================================

$articles = [
    [
        'title'      => 'Cum primesc biletul după achiziție?',
        'slug'       => 'cum-primesc-biletul-dupa-achizitie',
        'excerpt'    => 'Biletul digital este trimis automat pe email imediat după confirmarea plății.',
        'badge'      => 'POPULAR',
        'badgeBg'    => 'bg-amber-100',
        'badgeColor' => 'text-amber-700',
        'updated'    => 'Actualizat acum 3 zile',
        'views'      => '4.2k vizualizări',
        'helpful'    => '94% util',
    ],
    [
        'title'      => 'Pot returna sau schimba un bilet?',
        'slug'       => 'pot-returna-sau-schimba-un-bilet',
        'excerpt'    => 'Politica de retur depinde de organizatorul evenimentului. Unele evenimente permit returnarea.',
        'badge'      => null,
        'updated'    => 'Actualizat acum 1 săptămână',
        'views'      => '2.9k vizualizări',
        'helpful'    => '87% util',
    ],
    [
        'title'      => 'Cum transfer un bilet altcuiva?',
        'slug'       => 'cum-transfer-un-bilet-altcuiva',
        'excerpt'    => 'Transferul de bilete este gratuit și instant din secțiunea Biletele mele.',
        'badge'      => null,
        'updated'    => 'Actualizat acum 2 săptămâni',
        'views'      => '1.7k vizualizări',
        'helpful'    => '91% util',
    ],
    [
        'title'      => 'De ce nu am primit biletul pe email?',
        'slug'       => 'de-ce-nu-am-primit-biletul-pe-email',
        'excerpt'    => 'Verifică folderul Spam/Junk și asigură-te că adresa de email este corectă.',
        'badge'      => null,
        'updated'    => 'Actualizat acum 5 zile',
        'views'      => '1.4k vizualizări',
        'helpful'    => null,
    ],
    [
        'title'      => 'Cum cumpăr bilete ca și cadou?',
        'slug'       => 'cum-cumpar-bilete-ca-si-cadou',
        'excerpt'    => 'Poți trimite un bilet sau un Gift Card direct pe email-ul destinatarului.',
        'badge'      => null,
        'updated'    => 'Actualizat acum 1 lună',
        'views'      => '980 vizualizări',
        'helpful'    => null,
    ],
    [
        'title'      => 'Ce se întâmplă dacă evenimentul este anulat?',
        'slug'       => 'ce-se-intampla-daca-evenimentul-este-anulat',
        'excerpt'    => 'Biletele sunt rambursate automat în termen de 5-10 zile lucrătoare pe cardul de achiziție.',
        'badge'      => null,
        'updated'    => 'Actualizat acum 3 săptămâni',
        'views'      => '2.3k vizualizări',
        'helpful'    => null,
    ],
    [
        'title'      => 'Pot cumpăra mai multe bilete într-o singură comandă?',
        'slug'       => 'pot-cumpara-mai-multe-bilete-intr-o-singura-comanda',
        'excerpt'    => 'Da, poți adăuga până la 10 bilete per comandă cu date diferite de participanți.',
        'badge'      => null,
        'updated'    => 'Actualizat acum 2 luni',
        'views'      => '670 vizualizări',
        'helpful'    => null,
    ],
];

// ============================================================================
// PAGE SETTINGS
// ============================================================================

$pageTitle       = e($category['name']) . ' — Centru de Ajutor';
$pageDescription = e($category['description']) . ' · ' . $category['articleCount'] . ' articole';
$bodyClass       = 'bg-gray-50';

$breadcrumbs = [
    ['name' => 'Centru de ajutor', 'url' => '/centru-ajutor'],
    ['name' => $category['name'],  'url' => null],
];

setLoginState($isLoggedIn, $loggedInUser);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

    <!-- Breadcrumb + Hero -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-6xl mx-auto px-4 lg:px-8 py-8">
            <nav class="flex items-center gap-2 text-sm text-gray-500 mb-5">
                <a href="/centru-ajutor" class="hover:text-gray-900 transition-colors">Centru de ajutor</a>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-gray-900 font-medium"><?= e($category['name']) ?></span>
            </nav>
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-gradient-to-br from-indigo-100 to-indigo-50 rounded-2xl flex items-center justify-center text-3xl shadow-sm"><?= $category['icon'] ?></div>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900"><?= e($category['name']) ?></h1>
                    <p class="text-gray-500 text-sm mt-0.5"><?= e($category['description']) ?> · <?= (int) $category['articleCount'] ?> articole</p>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-6xl mx-auto px-4 lg:px-8 py-8">
        <div class="flex gap-8">

            <!-- Sidebar -->
            <aside class="hidden lg:block w-56 flex-shrink-0">
                <div class="sticky top-24">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-3">Categorii</p>
                    <nav class="space-y-0.5">
                        <?php foreach ($sidebarCategories as $sc): ?>
                        <a href="/centru-ajutor/<?= e($sc['slug']) ?>" class="sidebar-link<?= $sc['active'] ? ' active' : '' ?> block px-3 py-2.5 text-sm<?= $sc['active'] ? '' : ' text-gray-600' ?> rounded-lg"><?= $sc['icon'] ?> <?= e($sc['name']) ?></a>
                        <?php endforeach; ?>
                    </nav>
                    <div class="mt-8 bg-white rounded-xl border border-gray-200 p-4">
                        <p class="text-xs font-medium text-gray-900 mb-1">Nevoie de ajutor?</p>
                        <p class="text-xs text-gray-500 mb-3">Echipa noastră este online.</p>
                        <a href="#" class="block w-full py-2 bg-gray-900 text-white text-xs font-medium rounded-lg text-center hover:bg-gray-800 transition-colors">Deschide chat</a>
                    </div>
                </div>
            </aside>

            <!-- Content -->
            <div class="flex-1 min-w-0">
                <!-- Sub-categories -->
                <div class="flex items-center gap-2 mb-6 overflow-x-auto pb-1">
                    <?php foreach ($subcategories as $sub): ?>
                    <button class="sub-cat<?= $sub['active'] ? ' active' : '' ?> px-4 py-2 text-sm<?= $sub['active'] ? '' : ' text-gray-500' ?> rounded-lg whitespace-nowrap" onclick="toggleSubCat(this)"><?= e($sub['name']) ?></button>
                    <?php endforeach; ?>
                </div>

                <!-- Article List -->
                <div class="space-y-2">
                    <?php foreach ($articles as $article): ?>
                    <a href="/centru-ajutor/<?= e($category['slug']) ?>/<?= e($article['slug']) ?>" class="art-card bg-white rounded-xl border border-gray-200 p-5 flex items-center gap-4 group block">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <h3 class="font-medium text-gray-900 text-[15px] group-hover:text-indigo-600 transition-colors"><?= e($article['title']) ?></h3>
                                <?php if (!empty($article['badge'])): ?>
                                <span class="px-2 py-0.5 <?= e($article['badgeBg'] ?? 'bg-amber-100') ?> <?= e($article['badgeColor'] ?? 'text-amber-700') ?> text-[10px] font-bold rounded-full flex-shrink-0"><?= e($article['badge']) ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-gray-500 line-clamp-1"><?= e($article['excerpt']) ?></p>
                            <div class="flex items-center gap-3 mt-2 text-xs text-gray-400">
                                <span><?= e($article['updated']) ?></span>
                                <span>&middot;</span>
                                <span><?= e($article['views']) ?></span>
                                <?php if (!empty($article['helpful'])): ?>
                                <span>&middot;</span>
                                <span class="flex items-center gap-1"><svg class="w-3 h-3 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg><?= e($article['helpful']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <svg class="art-arrow w-5 h-5 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleSubCat(btn){btn.parentElement.querySelectorAll('.sub-cat').forEach(b=>{b.classList.remove('active');b.classList.add('text-gray-500')});btn.classList.add('active');btn.classList.remove('text-gray-500')}
    </script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
