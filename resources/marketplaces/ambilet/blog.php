<?php
$pageCacheTTL = 300; // 5 minutes
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/blog-helpers.php';

$activeCategory = preg_match('/^[a-z0-9-]{1,80}$/', (string) ($_GET['cat'] ?? '')) ? $_GET['cat'] : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;

$categoriesResp = api_cached('blog_categories', fn () => api_get('/blog-categories'), 600);
$categories = !empty($categoriesResp['success']) && is_array($categoriesResp['data']) ? $categoriesResp['data'] : [];

$params = ['per_page' => $perPage, 'page' => $page];
if ($activeCategory !== '') {
    $params['category'] = $activeCategory;
}
$articlesResp = api_cached('blog_articles_' . md5(json_encode($params)), fn () => api_get('/blog-articles', $params), 300);
$articles = !empty($articlesResp['success']) && is_array($articlesResp['data']) ? $articlesResp['data'] : [];
$lastPage = (int) ($articlesResp['meta']['last_page'] ?? 1);

// The big card on top: the featured article (or the newest) on the first page of "Toate".
$featuredArticle = null;
if ($page === 1 && $activeCategory === '' && $articles) {
    $featuredIndex = 0;
    foreach ($articles as $i => $a) {
        if (!empty($a['is_featured'])) {
            $featuredIndex = $i;
            break;
        }
    }
    $featuredArticle = $articles[$featuredIndex];
    array_splice($articles, $featuredIndex, 1);
}

$activeCategoryName = '';
foreach ($categories as $c) {
    if (($c['slug'] ?? '') === $activeCategory) {
        $activeCategoryName = $c['name'] ?? '';
    }
}

$pageTitle = $activeCategoryName !== '' ? $activeCategoryName . ' - Blog' : 'Blog';
$pageDescription = 'Ghiduri, sfaturi și inspirație din lumea evenimentelor: cum cumperi bilete, cum alegi locurile, idei de ieșit și resurse pentru organizatori.';
$transparentHeader = false;

$pageLink = function (int $p) use ($activeCategory) {
    $q = array_filter(['cat' => $activeCategory, 'page' => $p > 1 ? $p : null]);
    return '/blog' . ($q ? '?' . http_build_query($q) : '');
};

$cssBundle = 'static';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

    <!-- Hero -->
    <section class="bg-gradient-to-br from-slate-800 to-slate-900 py-16 md:py-20 px-6 md:px-12 relative overflow-hidden">
        <div class="absolute -top-[200px] -right-[200px] w-[600px] h-[600px] bg-[radial-gradient(circle,rgba(165,28,48,0.15)_0%,transparent_70%)] pointer-events-none"></div>
        <div class="max-w-3xl mx-auto text-center relative z-10">
            <h1 class="text-4xl md:text-5xl font-extrabold text-white mb-4">Blog</h1>
            <p class="text-lg text-white/90 leading-relaxed">Ghiduri, sfaturi și inspirație din lumea evenimentelor</p>
        </div>
    </section>

    <main class="max-w-6xl mx-auto px-6 md:px-12 py-12 md:py-16">
        <?php if ($categories): ?>
        <!-- Categories -->
        <div class="flex flex-wrap gap-3 justify-center mb-12">
            <?php
            $pills = array_merge([['slug' => '', 'name' => 'Toate']], $categories);
            foreach ($pills as $cat):
                $isActive = $activeCategory === $cat['slug'];
            ?>
            <a href="<?= $cat['slug'] === '' ? '/blog' : '/blog?cat=' . urlencode($cat['slug']) ?>" class="px-5 py-2.5 rounded-full text-sm font-semibold transition-all <?= $isActive ? 'bg-gradient-to-br from-primary to-red-600 text-white' : 'bg-white border border-slate-200 text-slate-500 hover:border-primary hover:text-primary' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><?= htmlspecialchars($cat['name']) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($featuredArticle):
            $fImage = blog_image_url($featuredArticle['image_url'] ?? null);
            $fUrl = blog_url($featuredArticle['slug']);
        ?>
        <!-- Featured Article -->
        <div class="mb-16">
            <a href="<?= htmlspecialchars($fUrl) ?>" class="group grid md:grid-cols-[1.2fr_1fr] gap-0 bg-white rounded-3xl overflow-hidden border border-slate-200 hover:shadow-[0_20px_60px_rgba(0,0,0,0.1)] hover:-translate-y-1 transition-all">
                <div class="h-[250px] md:h-[400px] bg-gradient-to-br from-primary to-red-800 relative overflow-hidden">
                    <?php if ($fImage): ?>
                    <img src="<?= htmlspecialchars($fImage) ?>" alt="" class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500" width="1200" height="630" fetchpriority="high">
                    <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center">
                        <svg class="w-16 h-16 md:w-20 md:h-20 text-white/30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="p-8 md:p-12 flex flex-col justify-center">
                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-br from-primary to-red-600 rounded-md text-[11px] font-bold text-white uppercase tracking-wide w-fit mb-4">Articol principal</div>
                    <div class="flex items-center gap-4 mb-4">
                        <?php if (!empty($featuredArticle['category']['name'])): ?>
                        <span class="text-sm font-semibold text-primary"><?= htmlspecialchars($featuredArticle['category']['name']) ?></span>
                        <?php endif; ?>
                        <span class="text-sm text-slate-400"><?= htmlspecialchars(blog_date($featuredArticle['published_at'] ?? $featuredArticle['created_at'] ?? null)) ?></span>
                    </div>
                    <h2 class="text-2xl md:text-[32px] font-extrabold text-slate-800 leading-tight mb-4 group-hover:text-primary transition-colors"><?= htmlspecialchars($featuredArticle['title']) ?></h2>
                    <p class="text-base text-slate-500 leading-relaxed mb-6"><?= htmlspecialchars($featuredArticle['excerpt'] ?? '') ?></p>
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-full bg-gradient-to-br from-slate-200 to-slate-300 flex items-center justify-center text-base font-bold text-slate-500">AB</div>
                        <div>
                            <div class="text-sm font-semibold text-slate-800"><?= BLOG_AUTHOR ?></div>
                            <div class="text-sm text-slate-400"><?= (int) ($featuredArticle['read_time'] ?? 5) ?> min de citit</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php endif; ?>

        <?php if ($articles): ?>
        <!-- Articles Grid -->
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-slate-800"><?= $activeCategoryName !== '' ? htmlspecialchars($activeCategoryName) : 'Articole recente' ?></h2>
        </div>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8 mb-16">
            <?php foreach ($articles as $article):
                $img = blog_image_url($article['image_url'] ?? null);
                $url = blog_url($article['slug']);
            ?>
            <article class="group relative bg-white rounded-2xl overflow-hidden border border-slate-200 hover:shadow-[0_12px_40px_rgba(0,0,0,0.08)] hover:-translate-y-1 transition-all">
                <div class="h-[200px] bg-gradient-to-br from-slate-200 to-slate-300 relative overflow-hidden">
                    <?php if ($img): ?>
                    <img src="<?= htmlspecialchars($img) ?>" alt="" class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500" width="1200" height="630" loading="lazy">
                    <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center">
                        <svg class="w-12 h-12 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <?php if (!empty($article['category']['name'])): ?>
                        <span class="text-xs font-semibold text-primary bg-primary/10 px-2.5 py-1 rounded"><?= htmlspecialchars($article['category']['name']) ?></span>
                        <?php endif; ?>
                        <span class="text-xs text-slate-400"><?= htmlspecialchars(blog_date($article['published_at'] ?? $article['created_at'] ?? null)) ?></span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 leading-snug mb-3">
                        <a href="<?= htmlspecialchars($url) ?>" class="group-hover:text-primary transition-colors after:absolute after:inset-0"><?= htmlspecialchars($article['title']) ?></a>
                    </h3>
                    <p class="text-sm text-slate-500 leading-relaxed mb-4 line-clamp-3"><?= htmlspecialchars($article['excerpt'] ?? '') ?></p>
                    <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-slate-200 flex items-center justify-center text-[11px] font-bold text-slate-500">AB</div>
                            <span class="text-sm font-medium text-slate-500"><?= BLOG_AUTHOR ?></span>
                        </div>
                        <span class="text-xs text-slate-400 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <?= (int) ($article['read_time'] ?? 5) ?> min
                        </span>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php elseif (!$featuredArticle): ?>
        <!-- Empty -->
        <div class="max-w-xl mx-auto text-center py-16 mb-16">
            <div class="w-16 h-16 mx-auto mb-6 rounded-2xl bg-primary/10 text-primary flex items-center justify-center">
                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <h2 class="text-2xl font-bold text-slate-800 mb-3"><?= $activeCategory !== '' ? 'Niciun articol în această categorie' : 'Primele articole sunt pe drum' ?></h2>
            <p class="text-slate-500 leading-relaxed mb-6">Până atunci, vezi ce evenimente urmează sau caută răspunsuri în centrul de ajutor.</p>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="/evenimente" class="px-6 py-3 bg-gradient-to-br from-primary to-red-600 rounded-xl text-sm font-bold text-white">Vezi evenimentele</a>
                <a href="<?= $activeCategory !== '' ? '/blog' : '/ajutor' ?>" class="px-6 py-3 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-600 hover:border-primary hover:text-primary transition-all"><?= $activeCategory !== '' ? 'Toate articolele' : 'Centrul de ajutor' ?></a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Newsletter -->
        <div class="bg-gradient-to-br from-slate-800 to-slate-700 rounded-3xl p-10 md:p-16 text-center relative overflow-hidden mb-16">
            <div class="absolute -top-[100px] -right-[100px] w-[300px] h-[300px] bg-[radial-gradient(circle,rgba(165,28,48,0.2),transparent_70%)] pointer-events-none"></div>
            <div class="relative z-10 max-w-lg mx-auto">
                <h2 class="text-2xl md:text-[28px] font-extrabold text-white mb-3">Abonează-te la newsletter</h2>
                <p class="text-base text-white/90 mb-8">Primește cele mai noi articole și evenimente direct în inbox.</p>
                <form data-newsletter>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <label class="sr-only" for="blogNl">Adresa de email</label>
                        <input id="blogNl" type="email" name="email" required autocomplete="email" placeholder="Adresa ta de email" class="flex-1 px-5 py-4 bg-white/10 border border-white/20 rounded-xl text-[15px] text-white placeholder:text-white/70 focus:outline-none focus:border-primary focus:bg-white/15">
                        <button type="submit" class="px-7 py-4 bg-gradient-to-br from-primary to-red-600 rounded-xl text-[15px] font-bold text-white hover:-translate-y-0.5 hover:shadow-[0_8px_24px_rgba(165,28,48,0.4)] transition-all disabled:opacity-60">Abonează-te</button>
                    </div>
                    <p data-newsletter-msg class="hidden mt-3 text-sm" role="status"></p>
                </form>
            </div>
        </div>

        <?php if ($lastPage > 1): ?>
        <!-- Pagination -->
        <nav class="flex items-center justify-center gap-2" aria-label="Paginare">
            <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars($pageLink($page - 1)) ?>" aria-label="Pagina anterioară" class="w-11 h-11 flex items-center justify-center bg-slate-50 border border-slate-200 rounded-xl text-slate-500 hover:bg-primary hover:text-white hover:border-primary transition-all">
                <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $lastPage; $p++): ?>
            <?php if ($p === $page): ?>
            <span aria-current="page" class="w-11 h-11 flex items-center justify-center bg-gradient-to-br from-primary to-red-600 rounded-xl text-sm font-semibold text-white"><?= $p ?></span>
            <?php else: ?>
            <a href="<?= htmlspecialchars($pageLink($p)) ?>" class="w-11 h-11 flex items-center justify-center bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-500 hover:border-primary hover:text-primary transition-all"><?= $p ?></a>
            <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $lastPage): ?>
            <a href="<?= htmlspecialchars($pageLink($page + 1)) ?>" aria-label="Pagina următoare" class="w-11 h-11 flex items-center justify-center bg-slate-50 border border-slate-200 rounded-xl text-slate-500 hover:bg-primary hover:text-white hover:border-primary transition-all">
                <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </main>
    <?php blog_newsletter_script(); ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>
