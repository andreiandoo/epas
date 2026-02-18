<?php
/**
 * TICS.ro - Blog Listing Page
 * Featured post, category filter, posts grid, sidebar
 */

require_once __DIR__ . '/includes/config.php';

// Demo blog categories
$blogCategories = [
    ['name' => 'Toate', 'slug' => '', 'icon' => ''],
    ['name' => 'Festivaluri', 'slug' => 'festivaluri', 'icon' => '🎪'],
    ['name' => 'Interviuri', 'slug' => 'interviuri', 'icon' => '🎤'],
    ['name' => 'Ghiduri', 'slug' => 'ghiduri', 'icon' => '📋'],
    ['name' => 'Știri', 'slug' => 'stiri', 'icon' => '📰'],
    ['name' => 'Recenzii', 'slug' => 'recenzii', 'icon' => '🎵'],
    ['name' => 'Tips & Tricks', 'slug' => 'tips', 'icon' => '💡'],
];

// Demo featured post
$featuredPost = [
    'slug' => 'ghidul-festivalurilor-romania-2026',
    'title' => 'Ghidul complet al festivalurilor din România 2026: ce merită și ce nu',
    'excerpt' => 'Am analizat toate festivalurile confirmate pentru 2026, de la Untold și Electric Castle la cele mai mici festivaluri boutique. Descoperă ce te așteaptă vara aceasta.',
    'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=800&h=500&fit=crop',
    'category' => ['name' => 'Ghid', 'color' => 'indigo'],
    'readTime' => '10 min citire',
    'author' => ['name' => 'Andrei Popescu', 'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=60&h=60&fit=crop'],
    'date' => '5 Feb 2026',
];

// Demo posts
$posts = [
    [
        'slug' => 'interviu-irina-rimes-noul-album',
        'title' => 'Interviu exclusiv: Irina Rimes despre noul album și turneul acustic',
        'excerpt' => 'Am stat de vorbă cu Irina Rimes despre procesul creativ din spatele celui de-al patrulea album de studio.',
        'image' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=500&h=320&fit=crop',
        'category' => ['name' => 'Interviu', 'color' => 'purple'],
        'readTime' => '8 min',
        'author' => ['name' => 'Maria Ionescu', 'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=40&h=40&fit=crop'],
        'date' => '3 Feb 2026',
    ],
    [
        'slug' => '10-lucruri-de-luat-la-festival',
        'title' => '10 lucruri de luat la festival pe care sigur le uiți',
        'excerpt' => 'De la baterie externă la dopuri de urechi, iată lista completă a obiectelor esențiale pentru orice festival.',
        'image' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=500&h=320&fit=crop',
        'category' => ['name' => 'Tips', 'color' => 'green'],
        'readTime' => '5 min',
        'author' => ['name' => 'Andrei Popescu', 'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=40&h=40&fit=crop'],
        'date' => '1 Feb 2026',
    ],
    [
        'slug' => 'electric-castle-2026-primele-nume',
        'title' => 'Electric Castle 2026: primele nume confirmate și prețuri bilete',
        'excerpt' => 'Organizatorii Electric Castle au anunțat primul val de artiști pentru ediția 2026. Descoperă line-up-ul.',
        'image' => 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=500&h=320&fit=crop',
        'category' => ['name' => 'Știri', 'color' => 'blue'],
        'readTime' => '3 min',
        'author' => ['name' => 'Radu Marin', 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=40&h=40&fit=crop'],
        'date' => '28 Ian 2026',
    ],
    [
        'slug' => 'recenzie-concert-subcarpati-arenele-romane',
        'title' => 'Recenzie: Concertul Subcarpați de la Arenele Romane a fost magic',
        'excerpt' => 'Un show de 3 ore care a reunit generații. Iată cum a fost concertul Subcarpați din ianuarie.',
        'image' => 'https://images.unsplash.com/photo-1524368535928-5b5e00ddc76b?w=500&h=320&fit=crop',
        'category' => ['name' => 'Recenzie', 'color' => 'orange'],
        'readTime' => '7 min',
        'author' => ['name' => 'Maria Ionescu', 'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=40&h=40&fit=crop'],
        'date' => '25 Ian 2026',
    ],
    [
        'slug' => 'cum-organizezi-eveniment-de-la-zero',
        'title' => 'Cum să organizezi un eveniment de la zero: ghid pas cu pas',
        'excerpt' => 'Tot ce trebuie să știi despre organizarea unui eveniment, de la buget la promovare și logistică.',
        'image' => 'https://images.unsplash.com/photo-1429962714451-bb934ecdc4ec?w=500&h=320&fit=crop',
        'category' => ['name' => 'Ghid', 'color' => 'indigo'],
        'readTime' => '12 min',
        'author' => ['name' => 'Andrei Popescu', 'avatar' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=40&h=40&fit=crop'],
        'date' => '20 Ian 2026',
    ],
    [
        'slug' => 'jazz-in-the-park-2026-schimbari',
        'title' => 'Jazz in the Park 2026: ce schimbări aduce noua ediție',
        'excerpt' => 'Festivalul de jazz din Cluj-Napoca promite o ediție specială cu artiști internaționali de renume.',
        'image' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=500&h=320&fit=crop',
        'category' => ['name' => 'Festivaluri', 'color' => 'pink'],
        'readTime' => '6 min',
        'author' => ['name' => 'Radu Marin', 'avatar' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=40&h=40&fit=crop'],
        'date' => '15 Ian 2026',
    ],
];

// Demo popular articles
$popularPosts = [
    ['title' => 'Ghidul festivalurilor 2026', 'views' => '12.4K'],
    ['title' => "Interviu cu Carla's Dreams", 'views' => '8.7K'],
    ['title' => '10 lucruri de luat la festival', 'views' => '6.2K'],
    ['title' => 'Electric Castle: primele nume', 'views' => '5.1K'],
];

// Demo tags
$tags = ['Untold', 'Electric Castle', 'Bilete', 'Concerte', 'Organizatori', 'Festivaluri', 'Pop', 'Rock'];

// Page settings
$pageTitle = 'Blog — Știri, ghiduri și noutăți din lumea evenimentelor';
$pageDescription = 'Citește cele mai noi articole despre evenimente, festivaluri, concerte și ticketing pe blogul TICS.ro.';
$bodyClass = 'bg-gray-50';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Blog', 'url' => null],
];

setLoginState($isLoggedIn, $loggedInUser);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Hero / Featured Post -->
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
            <div class="text-center mt-10"><button class="inline-flex items-center gap-2 px-8 py-3 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-full hover:border-gray-300 hover:bg-gray-50 transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>Mai multe articole</button></div>
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

<script>function toggleCat(b){b.parentElement.querySelectorAll('button').forEach(c=>c.classList.remove('chip-active'));b.classList.add('chip-active')}</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
