<?php
/**
 * Noutăți (index) — Marketplace-scoped changelog / announcements list.
 *
 * Shell rendered PHP-side (hero, filter tabs, skeleton grid) for
 * fast LCP; the actual cards are hydrated by /assets/js/pages/noutati.js
 * via AmbiletAPI.get('/system-updates?...').
 */

$pageCacheTTL = 300; // 5 minutes
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/config.php';

$activeCategory = $_GET['cat'] ?? '';
$allowedCategories = ['interfata', 'organizator', 'client'];
if (!in_array($activeCategory, $allowedCategories, true)) {
    $activeCategory = '';
}

$categoryLabels = [
    ''            => 'Toate',
    'interfata'   => 'Interfață',
    'organizator' => 'Organizator',
    'client'      => 'Client',
];

$pageTitle       = 'Noutăți | ' . SITE_NAME;
$pageDescription = 'Noutățile, îmbunătățirile și update-urile aplicate în ' . SITE_NAME . '. Vezi tot ce e nou în interfață, pentru organizatori și pentru clienți.';
$transparentHeader = false;
$bodyClass = 'page-noutati';

$cssBundle = 'static';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Hero -->
    <section class="bg-gradient-to-br from-slate-800 to-slate-900 py-16 md:py-20 px-6 md:px-12 relative overflow-hidden">
        <div class="absolute -top-[200px] -right-[200px] w-[600px] h-[600px] bg-[radial-gradient(circle,rgba(165,28,48,0.15)_0%,transparent_70%)] pointer-events-none"></div>
        <div class="max-w-3xl mx-auto text-center relative z-10">
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/10 border border-white/20 mb-5">
                <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                <span class="text-xs font-semibold text-white uppercase tracking-wider">Changelog</span>
            </div>
            <h1 class="text-4xl md:text-5xl font-extrabold text-white mb-4">Noutăți</h1>
            <p class="text-lg text-white/90 leading-relaxed">Îmbunătățiri, funcționalități noi și update-uri în <?= htmlspecialchars(SITE_NAME) ?></p>
        </div>
    </section>

    <main class="max-w-6xl mx-auto px-6 md:px-12 py-12 md:py-16">
        <!-- Category filter tabs -->
        <div id="noutati-categories" class="flex flex-wrap gap-3 justify-center mb-12">
            <?php foreach ($categoryLabels as $key => $label):
                $isActive = $activeCategory === $key;
                $href = $key === '' ? '/noutati' : '/noutati?cat=' . urlencode($key);
            ?>
            <a href="<?= $href ?>"
               data-category="<?= htmlspecialchars($key) ?>"
               class="px-5 py-2.5 rounded-full text-sm font-semibold transition-all <?= $isActive
                    ? 'bg-gradient-to-br from-primary to-red-600 text-white shadow-md'
                    : 'bg-white border border-slate-200 text-slate-500 hover:border-primary hover:text-primary' ?>">
                <?= htmlspecialchars($label) ?>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Updates grid (populated by JS) -->
        <div id="noutati-grid" class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8 mb-8"
             data-active-category="<?= htmlspecialchars($activeCategory) ?>">
            <!-- Skeleton cards until JS loads -->
            <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="bg-white rounded-2xl overflow-hidden border border-slate-200 animate-pulse">
                <div class="h-[200px] bg-slate-100"></div>
                <div class="p-6">
                    <div class="h-4 w-20 bg-slate-100 rounded mb-3"></div>
                    <div class="h-6 w-3/4 bg-slate-100 rounded mb-2"></div>
                    <div class="h-4 w-full bg-slate-100 rounded mb-2"></div>
                    <div class="h-4 w-2/3 bg-slate-100 rounded"></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <!-- Empty state (JS toggles visibility) -->
        <div id="noutati-empty" class="hidden text-center py-16">
            <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <p class="text-slate-500">Nu există noutăți publicate în această categorie deocamdată.</p>
        </div>

        <!-- Pagination (populated by JS) -->
        <nav id="noutati-pagination" class="flex items-center justify-center gap-2 mt-12"></nav>
    </main>

<?php
require_once __DIR__ . '/includes/footer.php';
$scriptsExtra = ($scriptsExtra ?? '')
    . '<script defer src="' . asset('assets/js/pages/noutati.js') . '"></script>';
require_once __DIR__ . '/includes/scripts.php';
?>
