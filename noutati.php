<?php
/**
 * Noutăți (index) — Marketplace-scoped changelog / announcements list.
 *
 * Month-grouped, infinite-scroll layout. The shell is rendered PHP-side
 * (hero + sticky category tabs + skeleton for LCP); the grouped grid is
 * hydrated by /assets/js/pages/noutati.js which pulls pages via
 * AmbiletAPI.get('/system-updates?...') and appends into per-month
 * sections as the visitor scrolls near the bottom.
 */

$pageCacheTTL = 300; // 5 minutes
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/config.php';

$activeCategory = $_GET['cat'] ?? '';
$allowedCategories = ['interfata', 'organizator', 'client', 'aplicatie-mobila'];
if (!in_array($activeCategory, $allowedCategories, true)) {
    $activeCategory = '';
}

$categoryLabels = [
    ''            => 'Toate',
    'interfata'   => 'Interfață',
    'organizator' => 'Organizatori',
    'client'      => 'Clienți',
    'aplicatie-mobila' => 'Aplicație mobilă',
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
        <div class="absolute -top-[300px] -right-[200px] w-[700px] h-[700px] bg-[radial-gradient(circle,rgba(165,28,48,0.2)_0%,transparent_65%)] pointer-events-none"></div>
        <div class="absolute -bottom-[200px] -left-[200px] w-[500px] h-[500px] bg-[radial-gradient(circle,rgba(59,130,246,0.08)_0%,transparent_65%)] pointer-events-none"></div>
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
                    $iconSvg = match ($key) {
                        'interfata'   => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
                        'organizator' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
                        'client'      => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
                        'aplicatie-mobila' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a1 1 0 001-1V4a1 1 0 00-1-1H8a1 1 0 00-1 1v16a1 1 0 001 1z"/></svg>',
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
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <main class="max-w-6xl mx-auto px-6 md:px-12 py-12 md:py-16">
        <!-- Month-grouped container (populated by JS) -->
        <div id="noutati-groups" data-active-category="<?= htmlspecialchars($activeCategory) ?>">
            <!-- Initial skeleton shown until page 1 arrives; JS clears it. -->
            <div id="noutati-initial-skeleton">
                <div class="flex items-center gap-4 mb-8">
                    <div class="h-px bg-slate-200 flex-1"></div>
                    <div class="h-4 w-32 bg-slate-100 rounded animate-pulse"></div>
                    <div class="h-px bg-slate-200 flex-1"></div>
                </div>
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
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
            </div>
        </div>

        <!-- Loading spinner shown while more pages fetch. -->
        <div id="noutati-loading" class="hidden text-center py-12">
            <div class="inline-flex items-center gap-3 text-slate-500">
                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" opacity="0.25"/>
                    <path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span class="text-sm font-medium">Se încarcă mai multe noutăți...</span>
            </div>
        </div>

        <!-- End marker shown when all pages have loaded. -->
        <div id="noutati-end" class="hidden text-center py-16">
            <div class="inline-flex items-center gap-3 px-6 py-3 rounded-full bg-slate-100 text-slate-500 text-sm font-medium">
                <span class="text-lg">✨</span>
                <span>Ai văzut toate noutățile</span>
            </div>
        </div>

        <!-- Error state (shown if a fetch fails). -->
        <div id="noutati-error" class="hidden text-center py-12">
            <div class="inline-flex flex-col items-center gap-3 text-slate-500">
                <p class="text-sm">Nu am putut încărca mai multe noutăți. Verifică conexiunea.</p>
                <button id="noutati-retry" type="button" class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-slate-900 text-white text-sm font-semibold hover:bg-primary transition-colors">
                    Reîncearcă
                </button>
            </div>
        </div>

        <!-- Empty state — no updates at all in this category. -->
        <div id="noutati-empty" class="hidden text-center py-20">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-slate-100 mb-6">
                <svg class="w-10 h-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-slate-700 mb-2">Nu există noutăți încă</h3>
            <p class="text-slate-500 max-w-md mx-auto">Reveniți curând — publicăm updates regulat cu noi funcționalități și îmbunătățiri.</p>
        </div>

        <!-- Sentinel for IntersectionObserver — triggers next-page load
             ~400px before it enters the viewport so the spinner appears
             seamless as the visitor scrolls. -->
        <div id="noutati-sentinel" aria-hidden="true" class="h-1"></div>
    </main>

    <style>
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
        /* Month section spacing — extra margin between groups to make the
           timeline read as distinct chapters. */
        .noutati-month-section + .noutati-month-section {
            margin-top: 3.5rem;
        }
    </style>

<?php
require_once __DIR__ . '/includes/footer.php';
$scriptsExtra = ($scriptsExtra ?? '')
    . '<script defer src="' . asset('assets/js/pages/noutati.js') . '"></script>';
require_once __DIR__ . '/includes/scripts.php';
?>
