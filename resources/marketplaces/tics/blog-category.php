<?php
/**
 * TICS.ro - Blog Category Page
 * Category hero, sort chips, featured post, posts grid, pagination
 */

require_once __DIR__ . '/includes/config.php';

$categorySlug = $_GET['slug'] ?? 'festivaluri';

// Demo category data
$category = [
    'name' => 'Festivaluri',
    'slug' => 'festivaluri',
    'icon' => '🎪',
    'iconBg' => 'pink',
    'description' => 'Ghiduri, știri și recenzii despre festivalurile din România și Europa',
    'articleCount' => 34,
    'totalViews' => '48.2K',
];

// Demo sort options
$sortOptions = ['Cele mai noi', 'Cele mai citite', 'Ghiduri', 'Recenzii', 'Știri'];

// Demo featured post
$featuredPost = [
    'slug' => 'ghidul-festivalurilor-romania-2026',
    'title' => 'Ghidul complet al festivalurilor din România 2026: ce merită și ce nu',
    'excerpt' => 'Am analizat toate festivalurile confirmate pentru 2026, de la Untold și Electric Castle la cele mai mici festivaluri boutique din țară.',
    'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=700&h=450&fit=crop',
    'categories' => [
        ['name' => 'Festivaluri', 'color' => 'pink'],
        ['name' => 'Ghid', 'color' => 'indigo'],
    ],
    'readTime' => '10 min',
    'author' => ['name' => 'Andrei Popescu', 'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=50&h=50&fit=crop'],
    'date' => '5 Feb 2026',
    'views' => '12.4K',
];

// Demo posts grid
$posts = [
    ['slug' => 'electric-castle-2026', 'title' => 'Electric Castle 2026: primele nume confirmate', 'excerpt' => 'Organizatorii au anunțat primul val de artiști pentru ediția din acest an.', 'image' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=500&h=320&fit=crop', 'category' => ['name' => 'Știri', 'color' => 'blue'], 'readTime' => '3 min', 'author' => ['name' => 'Radu Marin', 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=40&h=40&fit=crop'], 'date' => '28 Ian'],
    ['slug' => 'untold-2025-recenzie', 'title' => 'Untold 2025: ce am învățat din cea mai mare ediție', 'excerpt' => 'Cu peste 400.000 de participanți, ediția trecută a ridicat ștacheta.', 'image' => 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=500&h=320&fit=crop', 'category' => ['name' => 'Recenzie', 'color' => 'orange'], 'readTime' => '8 min', 'author' => ['name' => 'Maria Ionescu', 'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=40&h=40&fit=crop'], 'date' => '20 Ian'],
    ['slug' => 'jazz-in-the-park-2026', 'title' => 'Jazz in the Park 2026: ce schimbări aduce noua ediție', 'excerpt' => 'Festivalul din Cluj promite o ediție specială cu artiști de renume.', 'image' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=500&h=320&fit=crop', 'category' => ['name' => 'Festivaluri', 'color' => 'pink'], 'readTime' => '6 min', 'author' => ['name' => 'Radu Marin', 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=40&h=40&fit=crop'], 'date' => '15 Ian'],
    ['slug' => 'camping-la-festival', 'title' => 'Cum alegi camping-ul perfect la festival', 'excerpt' => 'Ghid complet pentru alegerea zonei de camping potrivite.', 'image' => 'https://images.unsplash.com/photo-1429962714451-bb934ecdc4ec?w=500&h=320&fit=crop', 'category' => ['name' => 'Tips', 'color' => 'green'], 'readTime' => '5 min', 'author' => ['name' => 'Andrei Popescu', 'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=40&h=40&fit=crop'], 'date' => '10 Ian'],
    ['slug' => 'neversea-date-2026', 'title' => 'Neversea anunță datele pentru ediția 2026', 'excerpt' => 'Festivalul de pe plajă confirmă revenirea în iulie.', 'image' => 'https://images.unsplash.com/photo-1524368535928-5b5e00ddc76b?w=500&h=320&fit=crop', 'category' => ['name' => 'Știri', 'color' => 'blue'], 'readTime' => '4 min', 'author' => ['name' => 'Maria Ionescu', 'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=40&h=40&fit=crop'], 'date' => '5 Ian'],
    ['slug' => 'comparatie-abonamente-festival', 'title' => 'Comparație: toate abonamentele de festival din 2026', 'excerpt' => 'Am pus cap la cap prețurile și beneficiile fiecărui pachet.', 'image' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=500&h=320&fit=crop', 'category' => ['name' => 'Ghid', 'color' => 'indigo'], 'readTime' => '15 min', 'author' => ['name' => 'Andrei Popescu', 'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=40&h=40&fit=crop'], 'date' => '2 Ian'],
];

// Page settings
$pageTitle = $category['name'] . ' — Blog TICS.ro';
$pageDescription = $category['description'];
$bodyClass = 'bg-gray-50';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Blog', 'url' => '/blog'],
    ['name' => $category['name'], 'url' => null],
];

setLoginState($isLoggedIn, $loggedInUser);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Category Hero -->
<section class="bg-white border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 lg:px-8 py-8">
        <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
            <a href="/" class="hover:text-gray-900 transition-colors">Acasă</a><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <a href="/blog" class="hover:text-gray-900 transition-colors">Blog</a><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-gray-900 font-medium"><?= e($category['name']) ?></span>
        </div>
        <div class="flex items-center gap-4 mb-3">
            <div class="w-14 h-14 bg-<?= e($category['iconBg']) ?>-100 rounded-2xl flex items-center justify-center text-2xl flex-shrink-0"><?= e($category['icon']) ?></div>
            <div>
                <h1 class="text-2xl lg:text-3xl font-bold text-gray-900"><?= e($category['name']) ?></h1>
                <p class="text-gray-600"><?= e($category['description']) ?></p>
            </div>
        </div>
        <div class="flex items-center gap-4 mt-4 text-sm text-gray-500">
            <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg><?= e($category['articleCount']) ?> articole</span>
            <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg><?= e($category['totalViews']) ?> vizualizări totale</span>
        </div>
    </div>
</section>

<!-- Sort bar -->
<div class="sticky top-16 z-30 bg-white border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 lg:px-8 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar">
            <?php foreach ($sortOptions as $i => $opt): ?>
            <button class="<?= $i === 0 ? 'chip-active ' : '' ?>px-3.5 py-1.5 rounded-full border border-gray-200 text-xs font-medium <?= $i > 0 ? 'text-gray-600 ' : '' ?>whitespace-nowrap hover:border-gray-300" onclick="toggleCat(this)"><?= e($opt) ?></button>
            <?php endforeach; ?>
        </div>
        <p class="text-xs text-gray-400 hidden sm:block whitespace-nowrap ml-3"><?= e($category['articleCount']) ?> articole</p>
    </div>
</div>

<main class="max-w-6xl mx-auto px-4 lg:px-8 py-8">
    <!-- Featured in category -->
    <a href="/blog/<?= e($featuredPost['slug']) ?>" class="group block bg-white rounded-2xl overflow-hidden border border-gray-200 mb-8 hover:shadow-lg transition-all">
        <div class="grid md:grid-cols-2">
            <div class="relative aspect-video md:aspect-auto overflow-hidden"><img src="<?= e($featuredPost['image']) ?>" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" alt="<?= e($featuredPost['title']) ?>"><div class="absolute top-4 left-4"><span class="px-3 py-1.5 bg-pink-600 text-white text-xs font-semibold rounded-full">📌 Fixat</span></div></div>
            <div class="p-6 lg:p-8 flex flex-col justify-center">
                <div class="flex items-center gap-2 mb-3">
                    <?php foreach ($featuredPost['categories'] as $cat): ?>
                    <span class="px-2 py-0.5 bg-<?= e($cat['color']) ?>-100 text-<?= e($cat['color']) ?>-700 text-xs font-medium rounded-full"><?= e($cat['name']) ?></span>
                    <?php endforeach; ?>
                    <span class="text-xs text-gray-400"><?= e($featuredPost['readTime']) ?></span>
                </div>
                <h2 class="text-xl lg:text-2xl font-bold text-gray-900 mb-3 group-hover:text-indigo-600 transition-colors leading-tight"><?= e($featuredPost['title']) ?></h2>
                <p class="text-gray-600 mb-4 leading-relaxed line-clamp-3"><?= e($featuredPost['excerpt']) ?></p>
                <div class="flex items-center gap-3"><img src="<?= e($featuredPost['author']['avatar']) ?>" class="w-9 h-9 rounded-full object-cover" alt="<?= e($featuredPost['author']['name']) ?>"><div><p class="text-sm font-medium text-gray-900"><?= e($featuredPost['author']['name']) ?></p><p class="text-xs text-gray-500"><?= e($featuredPost['date']) ?> &bull; <?= e($featuredPost['views']) ?> vizualizări</p></div></div>
            </div>
        </div>
    </a>

    <!-- Grid -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($posts as $post): ?>
        <a href="/blog/<?= e($post['slug']) ?>" class="blog-card bg-white rounded-2xl overflow-hidden border border-gray-200 group">
            <div class="relative aspect-[16/10] overflow-hidden"><img src="<?= e($post['image']) ?>" class="absolute inset-0 w-full h-full object-cover blog-img" alt="<?= e($post['title']) ?>"></div>
            <div class="p-5">
                <div class="flex items-center gap-2 mb-2"><span class="px-2 py-0.5 bg-<?= e($post['category']['color']) ?>-100 text-<?= e($post['category']['color']) ?>-700 text-xs font-medium rounded-full"><?= e($post['category']['name']) ?></span><span class="text-xs text-gray-400"><?= e($post['readTime']) ?></span></div>
                <h3 class="font-semibold text-gray-900 mb-2 leading-snug group-hover:text-indigo-600 transition-colors"><?= e($post['title']) ?></h3>
                <p class="text-sm text-gray-500 line-clamp-2 mb-3"><?= e($post['excerpt']) ?></p>
                <div class="flex items-center gap-2"><img src="<?= e($post['author']['avatar']) ?>" class="w-7 h-7 rounded-full object-cover" alt="<?= e($post['author']['name']) ?>"><p class="text-xs text-gray-500"><?= e($post['author']['name']) ?> &bull; <?= e($post['date']) ?></p></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-center gap-2 mt-10">
        <button class="w-10 h-10 rounded-full border border-gray-200 flex items-center justify-center text-gray-400 cursor-not-allowed"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>
        <button class="w-10 h-10 rounded-full bg-gray-900 text-white text-sm font-medium">1</button>
        <button class="w-10 h-10 rounded-full border border-gray-200 text-gray-600 text-sm font-medium hover:border-gray-300">2</button>
        <button class="w-10 h-10 rounded-full border border-gray-200 text-gray-600 text-sm font-medium hover:border-gray-300">3</button>
        <button class="w-10 h-10 rounded-full border border-gray-200 flex items-center justify-center text-gray-600 hover:border-gray-300"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>
    </div>
</main>

<script>function toggleCat(b){b.parentElement.querySelectorAll('button').forEach(c=>c.classList.remove('chip-active'));b.classList.add('chip-active')}</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
