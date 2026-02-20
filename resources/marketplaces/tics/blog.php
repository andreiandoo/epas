<?php
/**
 * TICS.ro - Blog Listing Page
 * Featured post, category filter, posts grid, sidebar
 */

require_once __DIR__ . '/includes/config.php';

$selectedCategory = $_GET['categorie'] ?? '';
$currentPage = max(1, intval($_GET['pagina'] ?? 1));

// Fetch categories from real API
$categoriesResponse = callApi('blog-categories');
$apiCategories      = $categoriesResponse['data'] ?? [];

$blogCategories = [['name' => 'Toate', 'slug' => '', 'icon' => '']];
foreach ($apiCategories as $cat) {
    $blogCategories[] = [
        'name' => $cat['name'] ?? '',
        'slug' => $cat['slug'] ?? '',
        'icon' => $cat['icon'] ?? '',
    ];
}

// Helper: map API article to template array
function mapBlogArticle($a) {
    $catColor = 'indigo';
    return [
        'slug'     => $a['slug'] ?? '',
        'title'    => $a['title'] ?? '',
        'excerpt'  => $a['excerpt'] ?? '',
        'image'    => getStorageUrl($a['image_url'] ?? $a['cover_image'] ?? ''),
        'category' => [
            'name'  => $a['category']['name'] ?? '',
            'color' => $catColor,
        ],
        'readTime' => ($a['read_time'] ?? 5) . ' min',
        'author'   => [
            'name'   => $a['author']['name'] ?? 'Redacția TICS',
            'avatar' => getStorageUrl($a['author']['avatar'] ?? ''),
        ],
        'date'     => formatDate($a['published_at'] ?? $a['created_at'] ?? ''),
    ];
}

// Fetch articles from real API (7 = 1 featured + 6 grid)
$articlesParams = [
    'status'   => 'published',
    'per_page' => 7,
    'page'     => $currentPage,
    'category' => $selectedCategory ?: null,
];
$articlesResponse = callApi('blog-articles', $articlesParams);
$apiArticles      = $articlesResponse['data'] ?? [];
$articlesMeta     = $articlesResponse['meta'] ?? [];

// Split: first article is featured, rest are grid
$featuredPost = !empty($apiArticles) ? mapBlogArticle(array_shift($apiArticles)) : null;
$posts        = array_map('mapBlogArticle', $apiArticles);
$hasMoreArticles = ($articlesMeta['current_page'] ?? 1) < ($articlesMeta['last_page'] ?? 1);

// Sidebar: popular posts and tags (static fallback)
$popularPosts = [];
$tags = ['Untold', 'Electric Castle', 'Bilete', 'Concerte', 'Organizatori', 'Festivaluri', 'Pop', 'Rock'];

// Page settings
$pageTitle       = 'Blog — Știri, ghiduri și noutăți din lumea evenimentelor';
$pageDescription = 'Citește cele mai noi articole despre evenimente, festivaluri, concerte și ticketing pe blogul TICS.ro.';
$bodyClass       = 'bg-gray-50';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Blog', 'url' => null],
];

setLoginState($isLoggedIn, $loggedInUser);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Hero / Featured Post -->
<?php if ($featuredPost): ?>
<section class="bg-white border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 lg:px-8 py-10">
        <a href="/blog/<?= e($featuredPost['slug']) ?>" class="group grid lg:grid-cols-2 gap-8 items-center">
            <div class="relative aspect-[16/10] rounded-2xl overflow-hidden">
                <img src="<?= e($featuredPost['image']) ?>" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="<?= e($featuredPost['title']) ?>">
                <div class="absolute top-4 left-4"><span class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-full">Recomandat</span></div>
            </div>
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="px-2.5 py-1 bg-<?= e($featuredPost['category']['color']) ?>-100 text-<?= e($featuredPost['category']['color']) ?>-700 text-xs font-medium rounded-full"><?= e($featuredPost['category']['name']) ?></span>
                    <span class="text-xs text-gray-400">&bull;</span>
                    <span class="text-xs text-gray-500"><?= e($featuredPost['readTime']) ?></span>
                </div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 mb-3 group-hover:text-indigo-600 transition-colors leading-tight"><?= e($featuredPost['title']) ?></h1>
                <p class="text-gray-600 leading-relaxed mb-4 line-clamp-3"><?= e($featuredPost['excerpt']) ?></p>
                <div class="flex items-center gap-3">
                    <img src="<?= e($featuredPost['author']['avatar']) ?>" class="w-10 h-10 rounded-full object-cover" alt="<?= e($featuredPost['author']['name']) ?>">
                    <div><p class="text-sm font-medium text-gray-900"><?= e($featuredPost['author']['name']) ?></p><p class="text-xs text-gray-500"><?= e($featuredPost['date']) ?></p></div>
                </div>
            </div>
        </a>
    </div>
</section>
<?php endif; ?>

<!-- Categories -->
<div class="sticky top-16 z-30 bg-white border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 lg:px-8">
        <div class="flex items-center gap-2 py-3 overflow-x-auto no-scrollbar">
            <?php foreach ($blogCategories as $i => $cat): ?>
            <button class="<?= $i === 0 ? 'chip-active ' : '' ?>px-4 py-2 rounded-full border border-gray-200 text-sm font-medium <?= $i === 0 ? '' : 'text-gray-600 ' ?>whitespace-nowrap hover:border-gray-300 transition-colors" onclick="toggleCat(this)"><?= $cat['icon'] ? e($cat['icon']) . ' ' : '' ?><?= e($cat['name']) ?></button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<main class="max-w-6xl mx-auto px-4 lg:px-8 py-8">
    <div class="lg:flex gap-8">
        <!-- Posts Grid -->
        <div class="flex-1">
            <div class="grid sm:grid-cols-2 gap-6">
                <?php foreach ($posts as $post): ?>
                <a href="/blog/<?= e($post['slug']) ?>" class="blog-card bg-white rounded-2xl overflow-hidden border border-gray-200 group">
                    <div class="relative aspect-[16/10] overflow-hidden"><img src="<?= e($post['image']) ?>" class="absolute inset-0 w-full h-full object-cover blog-img" alt="<?= e($post['title']) ?>"></div>
                    <div class="p-5">
                        <div class="flex items-center gap-2 mb-2"><span class="px-2 py-0.5 bg-<?= e($post['category']['color']) ?>-100 text-<?= e($post['category']['color']) ?>-700 text-xs font-medium rounded-full"><?= e($post['category']['name']) ?></span><span class="text-xs text-gray-400"><?= e($post['readTime']) ?></span></div>
                        <h3 class="font-semibold text-gray-900 mb-2 leading-snug group-hover:text-indigo-600 transition-colors"><?= e($post['title']) ?></h3>
                        <p class="text-sm text-gray-500 line-clamp-2 mb-3"><?= e($post['excerpt']) ?></p>
                        <div class="flex items-center gap-2"><img src="<?= e($post['author']['avatar']) ?>" class="w-7 h-7 rounded-full object-cover" alt="<?= e($post['author']['name']) ?>"><div><p class="text-xs font-medium text-gray-700"><?= e($post['author']['name']) ?></p><p class="text-xs text-gray-400"><?= e($post['date']) ?></p></div></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- Load more -->
            <?php if ($hasMoreArticles): ?>
            <div class="text-center mt-10"><button onclick="loadMoreArticles()" id="loadMoreBlogBtn" class="inline-flex items-center gap-2 px-8 py-3 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-full hover:border-gray-300 hover:bg-gray-50 transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>Mai multe articole</button></div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <aside class="hidden lg:block w-72 flex-shrink-0 space-y-6 mt-0">
            <!-- Newsletter -->
            <div class="bg-gradient-to-br from-gray-900 to-gray-800 rounded-2xl p-6 text-white">
                <div class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center mb-3"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div>
                <h3 class="font-semibold mb-1">Newsletter TICS</h3>
                <p class="text-sm text-white/70 mb-4">Primești săptămânal cele mai bune articole și oferte la bilete.</p>
                <input type="email" placeholder="email@exemplu.ro" class="w-full px-4 py-2.5 bg-white/10 border border-white/20 rounded-xl text-sm text-white placeholder:text-white/40 outline-none focus:border-white/40 mb-2">
                <button class="w-full py-2.5 bg-white text-gray-900 text-sm font-semibold rounded-xl hover:bg-gray-100 transition-colors">Abonează-te</button>
                <p class="text-xs text-white/40 mt-2 text-center">3.200+ abonați</p>
            </div>
            <!-- Popular -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-semibold text-gray-900 mb-4 text-sm">🔥 Cele mai citite</h3>
                <div class="space-y-4">
                    <?php foreach ($popularPosts as $i => $pop): ?>
                    <a href="#" class="flex gap-3 group"><span class="text-2xl font-bold text-gray-200 leading-none mt-0.5"><?= str_pad($i + 1, 2, '0', STR_PAD_LEFT) ?></span><div><h4 class="text-sm font-medium text-gray-900 leading-snug group-hover:text-indigo-600 transition-colors"><?= e($pop['title']) ?></h4><p class="text-xs text-gray-400 mt-0.5"><?= e($pop['views']) ?> vizualizări</p></div></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- Tags -->
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-semibold text-gray-900 mb-3 text-sm">🏷️ Etichete populare</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($tags as $tag): ?>
                    <a href="#" class="px-3 py-1.5 bg-gray-100 text-gray-700 text-xs font-medium rounded-full hover:bg-gray-200 transition-colors"><?= e($tag) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>
    </div>
</main>

<script>
var _blogPage = <?= json_encode($currentPage) ?>;
var _blogCategory = <?= json_encode($selectedCategory) ?>;
var _blogHasMore = <?= json_encode($hasMoreArticles) ?>;
var _storageUrl = <?= json_encode(STORAGE_URL) ?>;

function getImgUrl(path) {
    if (!path) return '/assets/images/placeholder.jpg';
    if (path.startsWith('http')) return path;
    return _storageUrl + '/' + path.replace(/^\//, '');
}

async function loadMoreArticles() {
    if (!_blogHasMore) return;
    const btn = document.getElementById('loadMoreBlogBtn');
    if (btn) btn.disabled = true;

    _blogPage++;
    const params = new URLSearchParams({ endpoint: 'blog-articles', status: 'published', per_page: 6, page: _blogPage });
    if (_blogCategory) params.set('category', _blogCategory);

    try {
        const res = await fetch('/api/proxy.php?' + params.toString());
        const data = await res.json();
        const articles = data.data || [];
        _blogHasMore = _blogPage < (data.meta?.last_page || 1);

        const grid = document.querySelector('.grid.sm\\:grid-cols-2');
        articles.forEach(a => {
            const card = document.createElement('a');
            card.href = '/blog/' + (a.slug || '');
            card.className = 'blog-card bg-white rounded-2xl overflow-hidden border border-gray-200 group';
            const img = getImgUrl(a.image_url || a.cover_image || '');
            const catName = a.category?.name || '';
            const readTime = (a.read_time || 5) + ' min';
            const authorName = a.author?.name || 'Redacția TICS';
            const authorAvatar = getImgUrl(a.author?.avatar || '');
            const date = a.published_at ? new Date(a.published_at).toLocaleDateString('ro-RO', {day:'2-digit',month:'short',year:'numeric'}) : '';
            card.innerHTML = `<div class="relative aspect-[16/10] overflow-hidden"><img src="${img}" class="absolute inset-0 w-full h-full object-cover blog-img" alt="${(a.title||'').replace(/"/g,'&quot;')}"></div><div class="p-5"><div class="flex items-center gap-2 mb-2"><span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-medium rounded-full">${catName}</span><span class="text-xs text-gray-400">${readTime}</span></div><h3 class="font-semibold text-gray-900 mb-2 leading-snug group-hover:text-indigo-600 transition-colors">${a.title||''}</h3><p class="text-sm text-gray-500 line-clamp-2 mb-3">${a.excerpt||''}</p><div class="flex items-center gap-2"><img src="${authorAvatar}" class="w-7 h-7 rounded-full object-cover" alt="${authorName}"><div><p class="text-xs font-medium text-gray-700">${authorName}</p><p class="text-xs text-gray-400">${date}</p></div></div></div>`;
            grid.appendChild(card);
        });

        if (!_blogHasMore) {
            document.getElementById('loadMoreBlogBtn')?.parentElement?.remove();
        } else if (btn) {
            btn.disabled = false;
        }
    } catch(e) {
        if (btn) btn.disabled = false;
    }
}
</script>
<script>function toggleCat(b){b.parentElement.querySelectorAll('button').forEach(c=>c.classList.remove('chip-active'));b.classList.add('chip-active')}</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
