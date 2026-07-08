<?php
/**
 * Noutăți (single) — Detail page for one changelog entry.
 *
 * SSR fetches the update server-side so <title>, meta description,
 * OG tags and the hero image (LCP) reflect the real content BEFORE
 * JS runs — critical for Google + social crawlers that don't execute JS.
 * The body itself is also rendered by PHP so pressing "Vezi sursa" or
 * loading with JS disabled still shows content. JS only enhances
 * ("Alte noutăți" carousel + interactive touches).
 */

$pageCacheTTL = 900; // 15 minutes
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// Resolve slug from query or REQUEST_URI (mirrors artist-single.php pattern).
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/noutati/([a-z0-9-]+)#i', $uri, $m)) {
        $slug = $m[1];
    }
}

if (empty($slug)) {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
    exit;
}

// Server-side fetch of the update — cached 5 min in /tmp/ambilet_cache to
// keep repeated hits cheap. Also gives us the related list in one round-trip.
$updateData = api_cached('system_update_' . $slug, function () use ($slug) {
    return api_get('/system-updates/' . urlencode($slug));
}, 300);

if (empty($updateData['success']) || empty($updateData['data']['update'])) {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
    exit;
}

$update  = $updateData['data']['update'];
$related = $updateData['data']['related'] ?? [];

// SEO overrides fall back to auto-generated versions.
$pageTitle       = (!empty($update['meta_title']) ? $update['meta_title'] : $update['title']) . ' | ' . SITE_NAME;
$pageDescription = !empty($update['meta_description'])
    ? $update['meta_description']
    : (!empty($update['excerpt']) ? $update['excerpt'] : ('Update ' . SITE_NAME . ': ' . $update['title']));
if (!empty($update['featured_image'])) {
    $pageImage = $update['featured_image'];
}

$bodyClass = 'page-noutati-detail';
$cssBundle = 'single';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';

// Category badge color mapping matches the Filament admin's palette so
// the same category reads the same way across surfaces.
$categoryColorMap = [
    'interfata'   => 'bg-sky-100 text-sky-700',
    'organizator' => 'bg-amber-100 text-amber-700',
    'client'      => 'bg-emerald-100 text-emerald-700',
];
$categoryClass = $categoryColorMap[$update['category']] ?? 'bg-slate-100 text-slate-700';
?>

    <!-- Breadcrumb -->
    <nav class="max-w-4xl mx-auto px-6 md:px-12 pt-8 text-sm text-slate-500">
        <a href="/" class="hover:text-primary">Acasă</a>
        <span class="mx-2">/</span>
        <a href="/noutati" class="hover:text-primary">Noutăți</a>
        <span class="mx-2">/</span>
        <span class="text-slate-700 font-medium"><?= htmlspecialchars($update['title']) ?></span>
    </nav>

    <article class="max-w-4xl mx-auto px-6 md:px-12 py-8 md:py-12">
        <!-- Meta row -->
        <div class="flex flex-wrap items-center gap-3 mb-5">
            <span class="inline-flex items-center px-3 py-1 text-xs font-bold uppercase tracking-wide rounded-full <?= $categoryClass ?>">
                <?= htmlspecialchars($update['category_label'] ?? ucfirst($update['category'])) ?>
            </span>
            <?php if (!empty($update['published_at_human'])): ?>
                <time class="text-sm text-slate-500" datetime="<?= htmlspecialchars($update['published_at']) ?>">
                    <?= htmlspecialchars($update['published_at_human']) ?>
                </time>
            <?php endif; ?>
        </div>

        <!-- Title -->
        <h1 class="text-3xl md:text-5xl font-extrabold text-slate-900 leading-tight mb-6">
            <?= htmlspecialchars($update['title']) ?>
        </h1>

        <!-- Excerpt as lede -->
        <?php if (!empty($update['excerpt'])): ?>
        <p class="text-lg md:text-xl text-slate-500 leading-relaxed mb-8">
            <?= htmlspecialchars($update['excerpt']) ?>
        </p>
        <?php endif; ?>

        <!-- Hero image -->
        <?php if (!empty($update['featured_image'])): ?>
        <figure class="mb-10 -mx-6 md:mx-0">
            <img src="<?= htmlspecialchars($update['featured_image']) ?>"
                 alt="<?= htmlspecialchars($update['title']) ?>"
                 class="w-full aspect-video object-cover md:rounded-3xl shadow-lg"
                 fetchpriority="high"
                 width="1200" height="675">
        </figure>
        <?php endif; ?>

        <!-- Body — already sanitized server-side via HTMLPurifier profile
             "system_update", so echoing raw is safe. -->
        <div id="noutati-body" class="prose prose-slate max-w-none prose-lg
             prose-headings:font-extrabold prose-headings:text-slate-800
             prose-a:text-primary prose-a:no-underline hover:prose-a:underline
             prose-img:rounded-2xl prose-iframe:rounded-2xl">
            <?= $update['body'] ?? '' ?>
        </div>

        <!-- Back link -->
        <div class="mt-12 pt-8 border-t border-slate-100">
            <a href="/noutati" class="inline-flex items-center gap-2 text-primary font-semibold hover:gap-3 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Înapoi la Noutăți
            </a>
        </div>
    </article>

    <?php if (!empty($related)): ?>
    <!-- Related updates -->
    <section class="bg-slate-50 border-t border-slate-100">
        <div class="max-w-6xl mx-auto px-6 md:px-12 py-12 md:py-16">
            <h2 class="text-2xl font-bold text-slate-800 mb-8">Alte noutăți</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <?php foreach ($related as $r):
                    $rClass = $categoryColorMap[$r['category']] ?? 'bg-slate-100 text-slate-700';
                ?>
                <a href="<?= htmlspecialchars($r['url']) ?>"
                   class="group bg-white rounded-2xl overflow-hidden border border-slate-200 hover:shadow-lg hover:-translate-y-1 transition-all block">
                    <?php if (!empty($r['featured_image'])): ?>
                    <div class="aspect-video overflow-hidden bg-slate-100">
                        <img src="<?= htmlspecialchars($r['featured_image']) ?>"
                             alt="<?= htmlspecialchars($r['title']) ?>"
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform"
                             loading="lazy"
                             width="600" height="338">
                    </div>
                    <?php else: ?>
                    <div class="aspect-video bg-gradient-to-br from-slate-100 to-slate-200"></div>
                    <?php endif; ?>
                    <div class="p-5">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="inline-flex px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide rounded-full <?= $rClass ?>">
                                <?= htmlspecialchars($r['category_label'] ?? '') ?>
                            </span>
                            <?php if (!empty($r['published_at_human'])): ?>
                            <span class="text-xs text-slate-400"><?= htmlspecialchars($r['published_at_human']) ?></span>
                            <?php endif; ?>
                        </div>
                        <h3 class="font-bold text-slate-800 leading-snug group-hover:text-primary transition-colors line-clamp-2">
                            <?= htmlspecialchars($r['title']) ?>
                        </h3>
                        <?php if (!empty($r['excerpt'])): ?>
                        <p class="mt-2 text-sm text-slate-500 line-clamp-2">
                            <?= htmlspecialchars($r['excerpt']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
require_once __DIR__ . '/includes/scripts.php';
?>
