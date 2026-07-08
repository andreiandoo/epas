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
             "system_update", so echoing raw is safe.
             NOTE: Tailwind Typography's `prose` classes aren't part of the
             ambilet build, so styling is done via the scoped stylesheet
             right below instead of prose-* utilities. -->
        <div id="noutati-body">
            <?= $update['body'] ?? '' ?>
        </div>
        <style>
            /* Scoped WYSIWYG typography for the changelog body. Kept inline
               to guarantee it ships with the page — the ambilet Tailwind
               build has no @tailwindcss/typography plugin, so `prose`
               classes would be no-ops. */
            #noutati-body {
                color: #334155;              /* slate-700 */
                font-size: 1.0625rem;         /* ~17px */
                line-height: 1.75;
            }
            #noutati-body > * + * { margin-top: 1.25em; }
            #noutati-body h1,
            #noutati-body h2,
            #noutati-body h3,
            #noutati-body h4,
            #noutati-body h5,
            #noutati-body h6 {
                color: #1e293b;              /* slate-800 */
                font-weight: 800;
                line-height: 1.25;
                margin-top: 2em;
                margin-bottom: 0.6em;
                letter-spacing: -0.01em;
            }
            #noutati-body h1 { font-size: 2.25rem; }
            #noutati-body h2 { font-size: 1.875rem; }
            #noutati-body h3 { font-size: 1.5rem; }
            #noutati-body h4 { font-size: 1.25rem; }
            #noutati-body h5 { font-size: 1.125rem; }
            #noutati-body h6 { font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; }
            #noutati-body p { margin: 0 0 1em 0; }
            #noutati-body a {
                color: var(--primary, #A51C30);
                font-weight: 600;
                text-decoration: underline;
                text-underline-offset: 3px;
                text-decoration-thickness: 1px;
            }
            #noutati-body a:hover {
                color: var(--primary-dark, #8B1728);
                text-decoration-thickness: 2px;
            }
            #noutati-body strong,
            #noutati-body b { font-weight: 700; color: #1e293b; }
            #noutati-body em,
            #noutati-body i { font-style: italic; }
            #noutati-body u { text-decoration: underline; }
            #noutati-body s { text-decoration: line-through; opacity: 0.7; }
            #noutati-body ul,
            #noutati-body ol {
                margin: 1em 0;
                padding-left: 1.75rem;
            }
            #noutati-body ul { list-style: disc; }
            #noutati-body ol { list-style: decimal; }
            #noutati-body ul ul { list-style: circle; }
            #noutati-body ul ul ul { list-style: square; }
            #noutati-body li { margin-bottom: 0.5em; }
            #noutati-body li > p { margin-bottom: 0.25em; }
            #noutati-body blockquote {
                margin: 1.5em 0;
                padding: 0.75em 1.25em;
                border-left: 4px solid var(--primary, #A51C30);
                background: #f8fafc;         /* slate-50 */
                color: #475569;              /* slate-600 */
                font-style: italic;
                border-radius: 0 0.5rem 0.5rem 0;
            }
            #noutati-body blockquote p { margin-bottom: 0.5em; }
            #noutati-body blockquote p:last-child { margin-bottom: 0; }
            #noutati-body pre {
                margin: 1.5em 0;
                padding: 1rem 1.25rem;
                background: #0f172a;         /* slate-900 */
                color: #e2e8f0;              /* slate-200 */
                border-radius: 0.75rem;
                overflow-x: auto;
                font-family: 'Fira Code', ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
                font-size: 0.9em;
                line-height: 1.6;
            }
            #noutati-body pre code { background: none; padding: 0; color: inherit; }
            #noutati-body code {
                background: #f1f5f9;         /* slate-100 */
                color: #dc2626;              /* red-600 */
                padding: 0.15rem 0.4rem;
                border-radius: 0.375rem;
                font-family: 'Fira Code', ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
                font-size: 0.9em;
            }
            #noutati-body hr {
                margin: 2.5em 0;
                border: 0;
                border-top: 1px solid #e2e8f0; /* slate-200 */
            }
            #noutati-body img {
                max-width: 100%;
                height: auto;
                border-radius: 1rem;
                margin: 1.5em auto;
                display: block;
                box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            }
            #noutati-body iframe {
                max-width: 100%;
                border-radius: 1rem;
                margin: 1.5em auto;
                display: block;
                aspect-ratio: 16 / 9;
                width: 100%;
                height: auto;
                border: 0;
            }
            #noutati-body figure { margin: 1.5em 0; }
            #noutati-body figcaption {
                text-align: center;
                color: #64748b;              /* slate-500 */
                font-size: 0.875rem;
                margin-top: 0.5rem;
                font-style: italic;
            }
            #noutati-body table {
                width: 100%;
                border-collapse: collapse;
                margin: 1.5em 0;
                font-size: 0.9375rem;
            }
            #noutati-body th,
            #noutati-body td {
                border: 1px solid #e2e8f0;
                padding: 0.5rem 0.75rem;
                text-align: left;
            }
            #noutati-body th {
                background: #f1f5f9;
                font-weight: 700;
                color: #1e293b;
            }
        </style>

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
