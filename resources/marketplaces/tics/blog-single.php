<?php
/**
 * TICS.ro - Blog Single Article Page
 * Article with reading progress, TOC, share, related articles
 */

require_once __DIR__ . '/includes/config.php';

$articleSlug = $_GET['slug'] ?? '';

if (!$articleSlug) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

// Fetch article from real API
$articleResponse = callApi('blog-articles/' . urlencode($articleSlug));
$apiArticle      = $articleResponse['data'] ?? null;

if (!$apiArticle) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    exit;
}

// Map API response to template structure
$catName = $apiArticle['category']['name'] ?? '';
$catSlug = $apiArticle['category']['slug'] ?? '';

$article = [
    'slug'         => $apiArticle['slug'] ?? '',
    'title'        => $apiArticle['title'] ?? '',
    'excerpt'      => $apiArticle['excerpt'] ?? '',
    'image'        => getStorageUrl($apiArticle['image_url'] ?? ''),
    'imageCaption' => '',
    'categories'   => $catName
        ? [['name' => $catName, 'slug' => $catSlug, 'color' => 'indigo']]
        : [['name' => 'Blog', 'slug' => 'blog', 'color' => 'indigo']],
    'author'       => [
        'name'   => $apiArticle['author']['name'] ?? 'Redacția TICS',
        'role'   => 'Redacția TICS',
        'bio'    => '',
        'avatar' => getStorageUrl($apiArticle['author']['avatar'] ?? ''),
    ],
    'date'         => formatDate($apiArticle['published_at'] ?? $apiArticle['created_at'] ?? ''),
    'readTime'     => ($apiArticle['read_time'] ?? 5) . ' min citire',
    'views'        => ($apiArticle['view_count'] ?? 0) > 0 ? formatFollowers($apiArticle['view_count']) . ' vizualizări' : '',
    'content'      => $apiArticle['content'] ?? '',
    'event'        => $apiArticle['event'] ?? null,
];

$articleTags = [];

// Extract TOC from h2 headings in content
$tocSections = [];
if (!empty($article['content'])) {
    preg_match_all('/<h2[^>]*\sid=["\']([^"\']+)["\'][^>]*>(.*?)<\/h2>/is', $article['content'], $m);
    if (!empty($m[1])) {
        foreach ($m[1] as $i => $id) {
            $tocSections[] = ['id' => $id, 'title' => strip_tags($m[2][$i])];
        }
    } else {
        // Fallback: use slugified h2 text
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $article['content'], $m2);
        foreach (($m2[1] ?? []) as $title) {
            $clean = strip_tags($title);
            $tocSections[] = [
                'id'    => preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($clean)),
                'title' => $clean,
            ];
        }
    }
}

// Fetch related articles from same category
$relatedArticles = [];
if ($catSlug) {
    $relatedResponse = callApi('blog-articles', ['category' => $catSlug, 'per_page' => 4, 'status' => 'published']);
    foreach ($relatedResponse['data'] ?? [] as $rel) {
        if (($rel['slug'] ?? '') === $articleSlug) continue;
        $relatedArticles[] = [
            'slug'     => $rel['slug'] ?? '',
            'title'    => $rel['title'] ?? '',
            'image'    => getStorageUrl($rel['image_url'] ?? ''),
            'category' => ['name' => $rel['category']['name'] ?? '', 'color' => 'indigo'],
            'readTime' => ($rel['read_time'] ?? 5) . ' min',
            'date'     => formatDate($rel['published_at'] ?? $rel['created_at'] ?? ''),
        ];
        if (count($relatedArticles) === 3) break;
    }
}

// Page settings
$pageTitle       = $article['title'] . ' — Blog TICS.ro';
$pageDescription = $article['excerpt'];
$pageImage       = $article['image'];
$bodyClass       = 'bg-gray-50';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Blog', 'url' => '/blog'],
    ['name' => $article['categories'][0]['name'], 'url' => '/blog/categorie/' . $article['categories'][0]['slug']],
];

setLoginState($isLoggedIn, $loggedInUser);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Reading progress -->
<div class="reading-progress" id="progressBar" style="width:0%"></div>

<!-- Article Header -->
<div class="bg-white border-b border-gray-200">
    <div class="max-w-3xl mx-auto px-4 lg:px-8 py-8 lg:py-12">
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-5">
            <a href="/blog" class="hover:text-gray-900 transition-colors">Blog</a><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <a href="/blog/categorie/<?= e($article['categories'][0]['slug']) ?>" class="hover:text-gray-900 transition-colors"><?= e($article['categories'][0]['name']) ?></a>
        </div>
        <div class="flex items-center gap-2 mb-4">
            <?php foreach ($article['categories'] as $cat): ?>
            <span class="px-2.5 py-1 bg-<?= e($cat['color']) ?>-100 text-<?= e($cat['color']) ?>-700 text-xs font-medium rounded-full"><?= e($cat['name']) ?></span>
            <?php endforeach; ?>
        </div>
        <h1 class="text-3xl lg:text-4xl font-bold text-gray-900 leading-tight mb-5"><?= e($article['title']) ?></h1>
        <p class="text-lg text-gray-600 leading-relaxed mb-6"><?= e($article['excerpt']) ?></p>

        <!-- Author + Meta -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pt-6 border-t border-gray-100">
            <div class="flex items-center gap-3">
                <img src="<?= e($article['author']['avatar']) ?>" class="w-12 h-12 rounded-full object-cover border-2 border-gray-100" alt="<?= e($article['author']['name']) ?>">
                <div><p class="font-medium text-gray-900"><?= e($article['author']['name']) ?></p><p class="text-sm text-gray-500"><?= e($article['author']['role']) ?></p></div>
            </div>
            <div class="flex items-center gap-4 text-sm text-gray-500">
                <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg><?= e($article['date']) ?></span>
                <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><?= e($article['readTime']) ?></span>
                <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg><?= e($article['views']) ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Featured Image -->
<div class="max-w-4xl mx-auto px-4 lg:px-8 -mb-8 relative z-10" style="margin-top:-1px">
    <div class="relative aspect-[2/1] rounded-2xl overflow-hidden shadow-lg">
        <img src="<?= e($article['image']) ?>" class="absolute inset-0 w-full h-full object-cover" alt="<?= e($article['title']) ?>">
    </div>
    <p class="text-xs text-gray-400 text-center mt-2"><?= e($article['imageCaption']) ?></p>
</div>

<main class="max-w-6xl mx-auto px-4 lg:px-8 pt-16 pb-10">
    <div class="lg:flex gap-10">
        <!-- Article Body -->
        <article class="flex-1 max-w-3xl">
            <div class="bg-white rounded-2xl border border-gray-200 p-6 lg:p-10">
                <div class="prose text-base" id="articleContent">
                    <?= $article['content'] ?>
                </div>
            </div>

            <!-- Event Promo Card -->
            <?php if (!empty($article['event'])): ?>
            <a href="<?= e(eventUrl($article['event']['slug'])) ?>" class="block bg-gradient-to-r from-indigo-600 to-violet-600 rounded-2xl p-6 mt-6 text-white hover:from-indigo-700 hover:to-violet-700 transition-all group">
                <p class="text-xs font-semibold uppercase tracking-wider text-white/70 mb-2">🎫 Eveniment promovat</p>
                <div class="flex items-center justify-between gap-4">
                    <h3 class="font-bold text-lg leading-tight group-hover:underline"><?= e($article['event']['title']) ?></h3>
                    <span class="flex-shrink-0 px-5 py-2.5 bg-white text-gray-900 font-bold text-sm rounded-xl group-hover:bg-gray-100 transition-colors whitespace-nowrap">Cumpără bilete &rarr;</span>
                </div>
            </a>
            <?php endif; ?>

            <!-- Tags -->
            <div class="flex flex-wrap items-center gap-2 mt-6">
                <span class="text-xs text-gray-400 font-medium">Etichete:</span>
                <?php foreach ($articleTags as $tag): ?>
                <a href="#" class="px-3 py-1.5 bg-white border border-gray-200 text-gray-600 text-xs font-medium rounded-full hover:border-gray-300 hover:bg-gray-50 transition-all"><?= e($tag) ?></a>
                <?php endforeach; ?>
            </div>

            <!-- Share bar -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5 mt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                <p class="text-sm font-medium text-gray-900">Distribuie articolul:</p>
                <div class="flex items-center gap-2">
                    <a href="#" onclick="shareOnFacebook();return false" class="share-btn w-10 h-10 bg-[#1877f2] text-white rounded-full flex items-center justify-center"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
                    <a href="#" onclick="shareOnX();return false" class="share-btn w-10 h-10 bg-black text-white rounded-full flex items-center justify-center"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
                    <a href="#" onclick="shareOnLinkedIn();return false" class="share-btn w-10 h-10 bg-[#0a66c2] text-white rounded-full flex items-center justify-center"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>
                    <button onclick="copyLink()" class="share-btn w-10 h-10 bg-gray-100 text-gray-600 rounded-full flex items-center justify-center hover:bg-gray-200"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg></button>
                </div>
            </div>

            <!-- Author box -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6 mt-6 flex gap-4">
                <img src="<?= e($article['author']['avatar']) ?>" class="w-16 h-16 rounded-xl object-cover flex-shrink-0" alt="<?= e($article['author']['name']) ?>">
                <div>
                    <h3 class="font-semibold text-gray-900 mb-1"><?= e($article['author']['name']) ?></h3>
                    <p class="text-sm text-gray-600 mb-3"><?= e($article['author']['bio']) ?></p>
                    <a href="#" class="text-sm font-medium text-indigo-600 hover:underline">Vezi toate articolele &rarr;</a>
                </div>
            </div>
        </article>

        <!-- Sidebar -->
        <aside class="hidden lg:block w-64 flex-shrink-0 space-y-6 mt-0">
            <div class="sticky top-24 space-y-6">
                <!-- TOC -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="font-semibold text-gray-900 mb-3 text-sm">Cuprins</h3>
                    <nav class="space-y-1">
                        <?php foreach ($tocSections as $i => $sec): ?>
                        <a href="#<?= e($sec['id']) ?>" class="toc-link <?= $i === 0 ? 'active ' : '' ?>block text-sm text-gray-600 py-1.5 pl-3"><?= e($sec['title']) ?></a>
                        <?php endforeach; ?>
                    </nav>
                </div>
                <!-- CTA -->
                <?php if (!empty($article['event'])): ?>
                <a href="<?= e(eventUrl($article['event']['slug'])) ?>" class="block bg-gradient-to-br from-indigo-600 to-violet-600 rounded-2xl p-5 text-white hover:from-indigo-700 hover:to-violet-700 transition-all">
                    <p class="font-semibold mb-1 text-sm">🎫 Eveniment promovat</p>
                    <p class="text-xs text-white/70 mb-3 line-clamp-2"><?= e($article['event']['title']) ?></p>
                    <span class="block w-full py-2.5 bg-white text-gray-900 text-sm font-semibold rounded-xl text-center hover:bg-gray-100 transition-colors">Cumpără bilete</span>
                </a>
                <?php else: ?>
                <div class="bg-gradient-to-br from-indigo-600 to-violet-600 rounded-2xl p-5 text-white">
                    <p class="font-semibold mb-1 text-sm">🎫 Bilete disponibile</p>
                    <p class="text-xs text-white/70 mb-3">Toate festivalurile menționate au bilete pe TICS.ro</p>
                    <a href="/evenimente" class="block w-full py-2.5 bg-white text-gray-900 text-sm font-semibold rounded-xl text-center hover:bg-gray-100 transition-colors">Cumpără bilete</a>
                </div>
                <?php endif; ?>
                <!-- Newsletter mini -->
                <div class="bg-white rounded-2xl border border-gray-200 p-5">
                    <h3 class="font-semibold text-gray-900 mb-2 text-sm">📬 Newsletter</h3>
                    <p class="text-xs text-gray-500 mb-3">Articole noi în fiecare săptămână.</p>
                    <input type="email" placeholder="email@exemplu.ro" class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-gray-900/10 mb-2">
                    <button class="w-full py-2 bg-gray-900 text-white text-xs font-semibold rounded-lg hover:bg-gray-800 transition-colors">Abonează-te</button>
                </div>
            </div>
        </aside>
    </div>

    <!-- Related Articles -->
    <section class="mt-12">
        <h2 class="text-lg font-semibold text-gray-900 mb-5">Articole similare</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($relatedArticles as $rel): ?>
            <a href="/blog/<?= e($rel['slug']) ?>" class="blog-card bg-white rounded-2xl overflow-hidden border border-gray-200 group">
                <div class="relative aspect-[16/10] overflow-hidden"><img src="<?= e($rel['image']) ?>" class="absolute inset-0 w-full h-full object-cover blog-img" alt="<?= e($rel['title']) ?>"></div>
                <div class="p-5">
                    <div class="flex items-center gap-2 mb-2"><span class="px-2 py-0.5 bg-<?= e($rel['category']['color']) ?>-100 text-<?= e($rel['category']['color']) ?>-700 text-xs font-medium rounded-full"><?= e($rel['category']['name']) ?></span><span class="text-xs text-gray-400"><?= e($rel['readTime']) ?></span></div>
                    <h3 class="font-semibold text-gray-900 mb-2 leading-snug group-hover:text-indigo-600 transition-colors"><?= e($rel['title']) ?></h3>
                    <p class="text-xs text-gray-500"><?= e($rel['date']) ?></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<script>
// Reading progress
window.addEventListener('scroll',()=>{const st=window.scrollY,dh=document.documentElement.scrollHeight-window.innerHeight;document.getElementById('progressBar').style.width=Math.min(100,st/dh*100)+'%'});
// TOC active tracking
const sections=[<?php echo implode(',', array_map(fn($s) => "'" . $s['id'] . "'", $tocSections)); ?>];
window.addEventListener('scroll',()=>{let current='';sections.forEach(id=>{const el=document.getElementById(id);if(el&&el.getBoundingClientRect().top<150)current=id});document.querySelectorAll('.toc-link').forEach(l=>{l.classList.toggle('active',l.getAttribute('href')==='#'+current)})});
// Share functions
function shareOnFacebook(){window.open('https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent(window.location.href),'_blank','width=600,height=400')}
function shareOnX(){window.open('https://twitter.com/intent/tweet?url='+encodeURIComponent(window.location.href)+'&text='+encodeURIComponent(document.title),'_blank','width=600,height=400')}
function shareOnLinkedIn(){window.open('https://www.linkedin.com/sharing/share-offsite/?url='+encodeURIComponent(window.location.href),'_blank','width=600,height=400')}
function copyLink(){navigator.clipboard.writeText(window.location.href)}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
