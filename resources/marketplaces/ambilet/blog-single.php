<?php
$pageCacheTTL = 300; // 5 minutes
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/blog-helpers.php';

$slug = (string) ($_GET['slug'] ?? '');
if ($slug === '' && preg_match('#/blog/([a-z0-9-]+)#i', $_SERVER['REQUEST_URI'] ?? '', $m)) {
    $slug = $m[1];
}
if (!preg_match('/^[a-z0-9-]{1,190}$/', $slug)) {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
    exit;
}

$resp = api_cached('blog_article_' . $slug, fn () => api_get('/blog-articles/' . rawurlencode($slug)), 300);
$article = !empty($resp['success']) && !empty($resp['data']['slug']) ? $resp['data'] : null;
if (!$article) {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
    exit;
}

$image = blog_image_url($article['image_url'] ?? null);
$date = blog_date($article['published_at'] ?? $article['created_at'] ?? null);
$categoryName = $article['category']['name'] ?? '';
$categorySlug = $article['category']['slug'] ?? '';
$articleUrl = SITE_URL . blog_url($article['slug']);

// Headings get ids so the table of contents can jump to them.
$content = (string) ($article['content'] ?? '');
$toc = [];
$usedIds = [];
$content = preg_replace_callback('#<h2([^>]*)>(.*?)</h2>#is', function ($m) use (&$toc, &$usedIds) {
    $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (preg_match('/\sid=["\']([^"\']+)["\']/i', $m[1], $idMatch)) {
        $id = $idMatch[1];
        $attrs = $m[1];
    } else {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: '')), '-') ?: 'sectiune';
        $id = $base;
        for ($n = 2; isset($usedIds[$id]); $n++) {
            $id = $base . '-' . $n;
        }
        $attrs = $m[1] . ' id="' . $id . '"';
    }
    $usedIds[$id] = true;
    if ($text !== '') {
        $toc[] = ['id' => $id, 'text' => $text];
    }
    return '<h2' . $attrs . '>' . $m[2] . '</h2>';
}, $content);

$related = [];
if ($categorySlug !== '') {
    $relatedResp = api_cached('blog_related_' . $categorySlug, fn () => api_get('/blog-articles', ['category' => $categorySlug, 'per_page' => 4]), 600);
    foreach ((!empty($relatedResp['success']) ? $relatedResp['data'] : []) as $r) {
        if (($r['slug'] ?? '') !== $article['slug'] && count($related) < 3) {
            $related[] = $r;
        }
    }
}

$faqs = is_array($article['faqs'] ?? null) ? $article['faqs'] : [];

$pageTitle = $article['title'] . ' - Blog';
$pageDescription = $article['excerpt'] ?? '';
$canonicalUrl = $articleUrl;
if ($image) {
    $pageImage = $image;
}

$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $article['title'],
    'description' => $article['excerpt'] ?? '',
    'image' => $image ?: null,
    'datePublished' => $article['published_at'] ?? null,
    'author' => ['@type' => 'Organization', 'name' => SITE_NAME],
    'publisher' => ['@type' => 'Organization', 'name' => SITE_NAME, 'url' => SITE_URL],
    'mainEntityOfPage' => $articleUrl,
];
$headExtra = '<script type="application/ld+json">' . json_encode(array_filter($schema), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
if ($faqs) {
    $headExtra .= '<script type="application/ld+json">' . json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $faqs),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
}

$transparentHeader = false;
$cssBundle = 'static';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Article Hero -->
    <section class="bg-gradient-to-br from-slate-800 to-slate-900 py-12 md:py-16 px-6 md:px-12 pb-20 relative overflow-hidden">
        <div class="absolute -top-[200px] -right-[200px] w-[600px] h-[600px] bg-[radial-gradient(circle,rgba(165,28,48,0.15)_0%,transparent_70%)] pointer-events-none"></div>
        <div class="max-w-3xl mx-auto relative z-10">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 mb-6" aria-label="Breadcrumb">
                <a href="/" class="text-sm text-white/90 hover:text-white transition-colors">Acasă</a>
                <span class="text-sm text-white/40">/</span>
                <a href="/blog" class="text-sm text-white/90 hover:text-white transition-colors">Blog</a>
                <?php if ($categoryName !== ''): ?>
                <span class="text-sm text-white/40">/</span>
                <a href="/blog?cat=<?= urlencode($categorySlug) ?>" class="text-sm text-white/80 hover:text-white transition-colors"><?= htmlspecialchars($categoryName) ?></a>
                <?php endif; ?>
            </nav>

            <?php if ($categoryName !== ''): ?>
            <!-- Category Badge -->
            <span class="inline-block px-3.5 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-xs font-bold text-white uppercase tracking-wide mb-5"><?= htmlspecialchars($categoryName) ?></span>
            <?php endif; ?>

            <!-- Title -->
            <h1 class="text-3xl md:text-[44px] font-extrabold text-white leading-tight mb-6 tracking-tight"><?= htmlspecialchars($article['title']) ?></h1>

            <!-- Author & Meta -->
            <div class="flex flex-wrap items-center gap-4 md:gap-8">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-primary to-red-600 flex items-center justify-center text-base font-bold text-white">AB</div>
                    <div>
                        <div class="text-[15px] font-semibold text-white"><?= BLOG_AUTHOR ?></div>
                    </div>
                </div>
                <div class="flex items-center gap-5">
                    <?php if ($date !== ''): ?>
                    <span class="flex items-center gap-1.5 text-sm text-white/90">
                        <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <?= htmlspecialchars($date) ?>
                    </span>
                    <?php endif; ?>
                    <span class="flex items-center gap-1.5 text-sm text-white/90">
                        <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?= (int) ($article['read_time'] ?? 5) ?> min de citit
                    </span>
                </div>
            </div>
        </div>
    </section>

    <!-- Article Image -->
    <div class="max-w-4xl mx-auto -mt-10 px-6 md:px-12 relative z-10">
        <div class="rounded-2xl overflow-hidden shadow-[0_20px_60px_rgba(0,0,0,0.2)]">
            <?php if ($image): ?>
            <img src="<?= htmlspecialchars($image) ?>" alt="" class="w-full h-[280px] md:h-[450px] object-cover" width="1200" height="630" fetchpriority="high">
            <?php else: ?>
            <div class="h-[280px] md:h-[450px] bg-gradient-to-br from-primary to-red-800 flex items-center justify-center">
                <svg class="w-16 h-16 md:w-20 md:h-20 text-white/30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Article Layout -->
    <div class="max-w-6xl mx-auto px-6 md:px-12 py-12 md:py-16 grid lg:grid-cols-[1fr_300px] gap-12 md:gap-16">
        <!-- Article Content -->
        <article class="min-w-0">
            <div class="prose prose-lg max-w-none text-slate-600 leading-relaxed
                        [&>p]:mb-6 [&>p]:text-lg
                        [&>h2]:text-[28px] [&>h2]:font-bold [&>h2]:text-slate-800 [&>h2]:mt-12 [&>h2]:mb-5 [&>h2]:scroll-mt-28
                        [&>h3]:text-[22px] [&>h3]:font-bold [&>h3]:text-slate-800 [&>h3]:mt-9 [&>h3]:mb-4
                        [&>ul]:my-5 [&>ul]:ml-6 [&>ul]:list-disc [&>ul>li]:mb-3 [&>ul>li]:text-lg
                        [&>ol]:my-5 [&>ol]:ml-6 [&>ol]:list-decimal [&>ol>li]:mb-3 [&>ol>li]:text-lg
                        [&>blockquote]:my-8 [&>blockquote]:py-6 [&>blockquote]:px-8 [&>blockquote]:bg-gradient-to-br [&>blockquote]:from-primary/5 [&>blockquote]:to-primary/[0.02] [&>blockquote]:border-l-4 [&>blockquote]:border-primary [&>blockquote]:rounded-r-xl [&>blockquote]:text-xl [&>blockquote]:italic [&>blockquote]:text-slate-800
                        [&_img]:rounded-xl [&_img]:my-8
                        [&_a]:text-primary [&_a]:underline
                        [&_strong]:text-slate-800 [&_strong]:font-semibold">
                <?= $content ?>
            </div>

            <?php if ($faqs): ?>
            <!-- FAQ -->
            <section class="mt-12">
                <h2 class="text-[28px] font-bold text-slate-800 mb-5">Întrebări frecvente</h2>
                <div class="space-y-3">
                    <?php foreach ($faqs as $faq): ?>
                    <details class="group bg-white border border-slate-200 rounded-xl px-6 py-4">
                        <summary class="flex items-center justify-between gap-4 cursor-pointer list-none text-base font-semibold text-slate-800">
                            <?= htmlspecialchars($faq['q']) ?>
                            <svg class="w-5 h-5 text-slate-400 flex-shrink-0 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                        </summary>
                        <p class="mt-3 text-slate-600 leading-relaxed"><?= htmlspecialchars($faq['a']) ?></p>
                    </details>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!empty($article['event']['slug'])): ?>
            <!-- Linked event -->
            <a href="/bilete/<?= htmlspecialchars($article['event']['slug']) ?>" class="mt-12 flex items-center justify-between gap-4 p-6 bg-gradient-to-br from-primary to-red-600 rounded-2xl text-white hover:-translate-y-0.5 transition-all">
                <div>
                    <div class="text-sm text-white/90 mb-1">Evenimentul din articol</div>
                    <div class="text-lg font-bold"><?= htmlspecialchars($article['event']['title'] ?? '') ?></div>
                </div>
                <span class="px-5 py-3 bg-white rounded-xl text-sm font-bold text-primary flex-shrink-0">Vezi bilete</span>
            </a>
            <?php endif; ?>

            <!-- Share -->
            <div class="mt-12 pt-8 border-t border-slate-200">
                <div class="text-sm font-semibold text-slate-500 mb-3">Distribuie articolul</div>
                <div class="flex gap-3">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($articleUrl) ?>" target="_blank" rel="noopener" class="w-11 h-11 flex items-center justify-center bg-slate-100 rounded-xl text-slate-500 hover:bg-primary hover:text-white transition-all" aria-label="Distribuie pe Facebook">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                    </a>
                    <a href="https://wa.me/?text=<?= urlencode($article['title'] . ' ' . $articleUrl) ?>" target="_blank" rel="noopener" class="w-11 h-11 flex items-center justify-center bg-slate-100 rounded-xl text-slate-500 hover:bg-primary hover:text-white transition-all" aria-label="Trimite pe WhatsApp">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.3-.4.8-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.4.8 3.2.7.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.2-.2-.4-.3z"/></svg>
                    </a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($articleUrl) ?>" target="_blank" rel="noopener" class="w-11 h-11 flex items-center justify-center bg-slate-100 rounded-xl text-slate-500 hover:bg-primary hover:text-white transition-all" aria-label="Distribuie pe LinkedIn">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
                    </a>
                    <button type="button" id="blogCopyLink" data-url="<?= htmlspecialchars($articleUrl) ?>" class="w-11 h-11 flex items-center justify-center bg-slate-100 rounded-xl text-slate-500 hover:bg-primary hover:text-white transition-all" aria-label="Copiază linkul">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                    </button>
                    <span id="blogCopyDone" class="self-center text-sm text-slate-500 hidden" role="status">Link copiat</span>
                </div>
            </div>
        </article>

        <!-- Sidebar -->
        <aside class="space-y-6">
            <?php if (count($toc) > 1): ?>
            <!-- TOC -->
            <div class="bg-white rounded-2xl border border-slate-200 p-7">
                <h3 class="text-base font-bold text-slate-800 mb-5">Cuprins</h3>
                <nav>
                    <?php foreach ($toc as $item): ?>
                    <a href="#<?= htmlspecialchars($item['id']) ?>" class="block py-2.5 text-sm text-slate-500 hover:text-primary border-b border-slate-100 last:border-0 transition-colors"><?= htmlspecialchars($item['text']) ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <?php endif; ?>

            <!-- Author Bio -->
            <div class="bg-white rounded-2xl border border-slate-200 p-7">
                <h3 class="text-base font-bold text-slate-800 mb-5">Despre autor</h3>
                <div class="flex gap-4 items-start">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-br from-primary to-red-600 flex items-center justify-center text-lg font-bold text-white flex-shrink-0">AB</div>
                    <div>
                        <div class="text-[15px] font-bold text-slate-800 mb-1"><?= BLOG_AUTHOR ?></div>
                        <div class="text-sm text-slate-500 leading-relaxed">Echipa AmBilet scrie ghiduri și sfaturi pentru spectatori și organizatori de evenimente.</div>
                    </div>
                </div>
            </div>

            <!-- Newsletter -->
            <div class="bg-gradient-to-br from-slate-800 to-slate-700 rounded-2xl p-7">
                <h3 class="text-base font-bold text-white mb-5">Newsletter</h3>
                <p class="text-sm text-white/90 mb-4">Primeste articole noi direct in inbox.</p>
                <input type="email" placeholder="Email-ul tau" class="w-full px-4 py-3.5 bg-white/10 border border-white/20 rounded-xl text-sm text-white placeholder:text-white/90 mb-3 focus:outline-none focus:border-white/40">
                <button class="w-full py-3.5 bg-gradient-to-br from-primary to-red-600 rounded-xl text-sm font-bold text-white hover:-translate-y-0.5 hover:shadow-[0_8px_20px_rgba(165,28,48,0.4)] transition-all">Aboneaza-te</button>
            </div>

            <?php if ($related): ?>
            <!-- Related Articles -->
            <div class="bg-white rounded-2xl border border-slate-200 p-7">
                <h3 class="text-base font-bold text-slate-800 mb-5">Articole similare</h3>
                <div class="space-y-4">
                    <?php foreach ($related as $i => $r):
                        $rImage = blog_image_url($r['image_url'] ?? null);
                    ?>
                    <a href="<?= htmlspecialchars(blog_url($r['slug'])) ?>" class="flex gap-4 group<?= $i > 0 ? ' pt-4 border-t border-slate-100' : '' ?>">
                        <?php if ($rImage): ?>
                        <img src="<?= htmlspecialchars($rImage) ?>" alt="" class="w-20 h-[60px] rounded-lg object-cover flex-shrink-0" width="80" height="60" loading="lazy">
                        <?php else: ?>
                        <div class="w-20 h-[60px] rounded-lg bg-gradient-to-br from-slate-200 to-slate-300 flex-shrink-0"></div>
                        <?php endif; ?>
                        <div>
                            <div class="text-sm font-semibold text-slate-800 leading-snug mb-1 group-hover:text-primary transition-colors"><?= htmlspecialchars($r['title']) ?></div>
                            <div class="text-xs text-slate-400"><?= htmlspecialchars(blog_date($r['published_at'] ?? null)) ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </aside>
    </div>

    <script>
    (function () {
        var btn = document.getElementById('blogCopyLink');
        var done = document.getElementById('blogCopyDone');
        if (!btn || !navigator.clipboard) return;
        btn.addEventListener('click', function () {
            navigator.clipboard.writeText(btn.dataset.url).then(function () {
                done.classList.remove('hidden');
                setTimeout(function () { done.classList.add('hidden'); }, 2000);
            });
        });
    })();
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
