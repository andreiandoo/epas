<?php
/**
 * Noutăți (index) — Marketplace-scoped changelog / announcements list.
 *
 * Magazine-style layout: hero → sticky filter tabs → big featured card
 * for the most recent update → 3-col grid for the rest. Hydrated by
 * /assets/js/pages/noutati.js which calls
 * AmbiletAPI.get('/system-updates?...') via the local proxy.
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
    'organizator' => 'Organizatori',
    'client'      => 'Clienți',
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
    <section class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 py-20 md:py-28 px-6 md:px-12">
        <!-- Decorative radial glows -->
        <div class="absolute -top-[300px] -right-[200px] w-[700px] h-[700px] bg-[radial-gradient(circle,rgba(165,28,48,0.2)_0%,transparent_65%)] pointer-events-none"></div>
        <div class="absolute -bottom-[200px] -left-[200px] w-[500px] h-[500px] bg-[radial-gradient(circle,rgba(59,130,246,0.08)_0%,transparent_65%)] pointer-events-none"></div>
        <!-- Grid pattern overlay -->
        <div class="absolute inset-0 opacity-[0.04] pointer-events-none"
             style="background-image: linear-gradient(rgba(255,255,255,0.5) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.5) 1px, transparent 1px); background-size: 40px 40px;"></div>

        <div class="relative z-10 max-w-4xl mx-auto text-center">
            <div class="inline-flex items-center gap-2 px-4 py-2 mb-6 rounded-full bg-white/10 backdrop-blur-sm border border-white/20">
                <span class="relative flex w-2 h-2">
                    <span class="absolute inset-0 rounded-full bg-primary animate-ping opacity-75"></span>
                    <span class="relative rounded-full w-2 h-2 bg-primary"></span>
                </span>
                <span class="text-xs font-semibold text-white uppercase tracking-widest">Changelog</span>
            </div>
            <h1 class="text-5xl md:text-6xl font-black text-white mb-5 tracking-tight">Noutăți</h1>
            <p class="text-lg md:text-xl text-white/80 leading-relaxed max-w-2xl mx-auto">
                Îmbunătățiri, funcționalități noi și update-uri.<br class="hidden md:block">
                Vezi ce am construit recent în <?= htmlspecialchars(SITE_NAME) ?>.
            </p>
        </div>
    </section>

    <!-- Sticky filter bar -->
    <div id="noutati-filter-bar" class="sticky top-16 z-30 bg-white/95 backdrop-blur-md border-b border-slate-200 shadow-sm">
        <div class="max-w-6xl mx-auto px-6 md:px-12 py-4">
            <div id="noutati-categories" class="flex flex-wrap gap-2 justify-center">
                <?php foreach ($categoryLabels as $key => $label):
                    $isActive = $activeCategory === $key;
                    $href = $key === '' ? '/noutati' : '/noutati?cat=' . urlencode($key);
                    // Category icons for the pill.
                    $iconSvg = match ($key) {
                        'interfata'   => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
                        'organizator' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
                        'client'      => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
                        default       => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>',
                    };
                ?>
                <a href="<?= $href ?>"
                   data-category="<?= htmlspecialchars($key) ?>"
                   class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold transition-all <?= $isActive
                        ? 'bg-gradient-to-r from-primary to-red-600 text-white shadow-md shadow-red-500/25'
                        : 'bg-white border border-slate-200 text-slate-600 hover:border-primary hover:text-primary hover:shadow-sm' ?>">
                    <?= $iconSvg ?>
                    <span><?= htmlspecialchars($label) ?></span>
                    <span class="filter-count text-xs opacity-70" data-category-key="<?= htmlspecialchars($key) ?>"></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <main class="max-w-6xl mx-auto px-6 md:px-12 py-12 md:py-16">
        <!-- Featured card (populated by JS with the newest update) -->
        <div id="noutati-featured" class="mb-12 hidden"></div>

        <!-- "Latest updates" section heading -->
        <div id="noutati-grid-heading" class="hidden flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-slate-800">Alte noutăți</h2>
            <span id="noutati-count" class="text-sm text-slate-500"></span>
        </div>

        <!-- Grid of remaining cards (populated by JS) -->
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
        <div id="noutati-empty" class="hidden text-center py-20">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-slate-100 mb-6">
                <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-slate-700 mb-2">Nu există noutăți încă</h3>
            <p class="text-slate-500 max-w-md mx-auto">Reveniți curând — publicăm updates regulat cu noi funcționalități și îmbunătățiri.</p>
        </div>

        <!-- Pagination (populated by JS) -->
        <nav id="noutati-pagination" class="flex items-center justify-center gap-2 mt-12"></nav>
    </main>

    <style>
        /* NEW badge for updates published < 7 days ago (injected via JS). */
        .noutati-new-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #fff;
            background: linear-gradient(135deg, #A51C30, #dc2626);
            border-radius: 9999px;
            box-shadow: 0 4px 12px rgba(165, 28, 48, 0.4);
            z-index: 10;
        }
        .noutati-new-badge::before {
            content: "";
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #fff;
            animation: noutati-pulse 2s ease-in-out infinite;
        }
        @keyframes noutati-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* Featured hero card polish. */
        .noutati-featured-card {
            position: relative;
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 1.75rem;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .noutati-featured-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
        }
        .noutati-featured-image {
            position: relative;
            min-height: 340px;
            overflow: hidden;
        }
        .noutati-featured-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s;
        }
        .noutati-featured-card:hover .noutati-featured-image img {
            transform: scale(1.05);
        }
        .noutati-featured-content {
            padding: 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        @media (max-width: 768px) {
            .noutati-featured-card {
                grid-template-columns: 1fr;
            }
            .noutati-featured-image { min-height: 220px; }
            .noutati-featured-content { padding: 1.75rem; }
        }
    </style>

<?php
require_once __DIR__ . '/includes/footer.php';
$scriptsExtra = ($scriptsExtra ?? '')
    . '<script defer src="' . asset('assets/js/pages/noutati.js') . '"></script>';
require_once __DIR__ . '/includes/scripts.php';
?>
