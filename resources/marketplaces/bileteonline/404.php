<?php
/**
 * 404 — page not found.
 * Included by ErrorDocument 404 directive in .htaccess AND directly via
 * `require __DIR__ . '/404.php'` from category.php / city-intent.php when
 * a slug doesn't resolve.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/nav-helpers.php';

if (!headers_sent()) {
    http_response_code(404);
}

$pageTitleRaw = 'Pagina nu a fost găsită · bilete.online';
$pageDescription = 'Pagina pe care o cauți nu există sau a fost mutată. Caută în alte categorii sau orașe.';
$canonicalUrl = SITE_URL . '/404';
$noindex = true;
$currentPage = '404';

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';

// Top categories + cities for recovery suggestions
$topCats = navGetCategories(6);
$topCities = navGetCities(8);
?>

<section class="max-w-7xl mx-auto px-4 sm:px-6 py-20 lg:py-28">
    <div class="grid lg:grid-cols-[1.2fr_.8fr] gap-12 items-center">

        <div>
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion -rotate-2">404 · NOT FOUND</p>
            <h1 class="mt-6 font-display text-[clamp(3rem,8vw,6rem)] font-700 leading-[.9]">
                Pagina <span class="ital text-vermilion">nu e</span><br>pe hartă.
            </h1>
            <p class="mt-6 text-lg text-ink-soft max-w-xl leading-relaxed">
                Linkul pe care ai venit nu mai duce nicăieri sau a fost mutat. Hai să te aducem înapoi la ce vrei să faci.
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="/" class="px-6 py-3.5 rounded-full bg-vermilion text-paper font-600 hover:bg-vermilion-d transition">
                    ← Înapoi la homepage
                </a>
                <a href="/categorii" class="px-6 py-3.5 rounded-full border-2 border-ink font-600 hover:bg-ink hover:text-paper transition">
                    Vezi toate categoriile
                </a>
            </div>
        </div>

        <!-- decorative torn ticket -->
        <div class="relative hidden lg:block h-72" aria-hidden="true">
            <div class="ticket absolute right-4 top-6 w-[88%] -rotate-6 bg-ink text-paper rounded-2xl overflow-hidden border-2 border-ink shadow-2xl" style="--perf:55%; --punch:#1B1714">
                <div class="duotone h-32 bg-gradient-to-br from-vermilion to-vermilion-d text-vermilion">
                    <div class="grid-tex"></div>
                    <span class="stamp absolute top-4 left-4 text-paper/80 px-3 py-1 text-[10px] font-mono -rotate-6">VOID</span>
                    <span class="absolute right-5 bottom-2 font-display text-7xl text-paper/30 leading-none">404</span>
                </div>
                <div class="p-5">
                    <p class="font-mono text-[10px] text-paper/50">BILET INVALID</p>
                    <h3 class="font-display text-xl font-700 mt-1">Acces respins</h3>
                    <div class="mt-3 flex items-center justify-between">
                        <span class="font-mono text-xs text-paper/60">QR illegible</span>
                        <span class="px-3 py-1.5 rounded-full bg-paper/10 text-paper text-xs font-600">×</span>
                    </div>
                </div>
                <div class="notch top"></div><div class="notch bot"></div><div class="perf"></div>
            </div>
        </div>
    </div>

    <!-- Recovery: top categories -->
    <?php if (!empty($topCats)): ?>
    <div class="mt-16 pt-10 border-t border-ink/10">
        <p class="font-mono text-xs tracking-[.2em] text-ink-soft mb-4">POATE CĂUTAI</p>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($topCats as $cat): ?>
                <a href="<?= htmlspecialchars($cat['href'], ENT_QUOTES) ?>" class="px-4 py-2 rounded-full bg-paper-2 border border-ink/15 hover:border-ink hover:bg-ink hover:text-paper transition text-sm font-600">
                    <?php if (!empty($cat['icon_emoji'])): ?><span class="mr-1"><?= htmlspecialchars($cat['icon_emoji']) ?></span><?php endif; ?>
                    <?= htmlspecialchars($cat['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Cities -->
    <?php if (!empty($topCities)): ?>
    <div class="mt-10">
        <p class="font-mono text-xs tracking-[.2em] text-ink-soft mb-4">SAU ALEGE UN ORAȘ</p>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($topCities as $c): ?>
                <a href="<?= htmlspecialchars($c['href'], ENT_QUOTES) ?>" class="px-4 py-2 rounded-full bg-paper border border-ink/15 hover:border-ink hover:bg-ink hover:text-paper transition text-sm font-600">
                    <?= htmlspecialchars($c['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
