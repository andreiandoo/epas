<?php
/**
 * Organizers Page - Lista Organizatorilor
 * Ambilet Marketplace
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$pageTitle = 'Organizatori';
$pageDescription = 'Descoperă organizatorii de evenimente din România. De la festivaluri mari la petreceri intime, găsește evenimentele perfecte pentru tine.';

// Query params from URL
$page = max(1, (int) ($_GET['page'] ?? 1));
$sort = in_array($_GET['sort'] ?? '', ['events', 'name', 'newest']) ? $_GET['sort'] : 'events';
$search = trim($_GET['search'] ?? '');

$queryParams = ['sort' => $sort, 'per_page' => '18', 'page' => $page];
if ($search !== '') {
    $queryParams['search'] = $search;
}

$apiResponse = api_cached(
    'organizers_' . md5(json_encode($queryParams)),
    fn() => api_get('/marketplace-events/organizers?' . http_build_query($queryParams)),
    120
);

$allOrganizers = [];
$meta = ['total' => 0, 'last_page' => 1, 'current_page' => 1];

if (!empty($apiResponse['success'])) {
    $data = $apiResponse['data'] ?? [];
    // Handle potential nested data (paginated response may nest data inside 'data' key)
    if (isset($data['data']) && is_array($data['data'])) {
        $allOrganizers = $data['data'];
        $meta = array_merge($meta, array_intersect_key($data, array_flip(['total', 'last_page', 'current_page', 'per_page'])));
    } else {
        $allOrganizers = is_array($data) ? $data : [];
    }
    if (!empty($apiResponse['meta'])) {
        $meta = $apiResponse['meta'];
    }
}

$totalOrganizers = $meta['total'] ?? count($allOrganizers);
$lastPage = $meta['last_page'] ?? 1;
$currentPage = $meta['current_page'] ?? $page;

// On first page: first 3 are featured, rest go to grid
// On other pages: all go to grid
$featuredOrganizers = [];
$organizers = $allOrganizers;
if ($currentPage === 1 && empty($search)) {
    $featuredOrganizers = array_slice($allOrganizers, 0, 3);
    $organizers = array_slice($allOrganizers, 3);
}

// Rotating gradient classes for cards
$gradientClasses = [
    'bg-gradient-to-br from-blue-500 to-blue-600',
    'bg-gradient-to-br from-purple-500 to-purple-600',
    'bg-gradient-to-br from-amber-500 to-amber-600',
    'bg-gradient-to-br from-emerald-500 to-emerald-600',
    'bg-gradient-to-br from-primary to-primary-dark',
    'bg-gradient-to-br from-pink-500 to-pink-600',
    'bg-gradient-to-br from-cyan-500 to-cyan-600',
    'bg-gradient-to-br from-indigo-500 to-indigo-600',
];

// CTA features
$ctaFeatures = [
    ['icon' => 'card', 'title' => 'Plăți securizate', 'desc' => 'Primești banii rapid în cont'],
    ['icon' => 'chart', 'title' => 'Statistici detaliate', 'desc' => 'Dashboard complet în timp real'],
    ['icon' => 'users', 'title' => 'Suport dedicat', 'desc' => 'Echipă disponibilă 24/7'],
    ['icon' => 'clock', 'title' => 'Setup rapid', 'desc' => 'Primul eveniment în 5 minute']
];

// Build current URL for sort/pagination links
function buildOrgUrl($params = []) {
    $current = [
        'sort' => $_GET['sort'] ?? 'events',
        'search' => trim($_GET['search'] ?? ''),
        'page' => $_GET['page'] ?? 1,
    ];
    $merged = array_merge($current, $params);
    // Remove defaults
    if ($merged['page'] == 1) unset($merged['page']);
    if ($merged['sort'] === 'events') unset($merged['sort']);
    if ($merged['search'] === '') unset($merged['search']);
    $qs = http_build_query($merged);
    return '/organizatori' . ($qs ? '?' . $qs : '');
}

$cssBundle = 'listing';
require_once __DIR__ . '/includes/head.php';
$transparentHeader = false;
require_once __DIR__ . '/includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="relative px-6 pt-32 pb-16 overflow-hidden text-center bg-gradient-to-br from-slate-800 to-slate-900">
        <div class="absolute rounded-full -top-24 -right-24 w-96 h-96 bg-primary/30 blur-3xl"></div>
        <div class="absolute rounded-full -bottom-36 -left-24 w-80 h-80 bg-primary/20 blur-3xl"></div>
        <div class="relative z-10 max-w-2xl mx-auto">
            <h1 class="mb-4 text-4xl font-extrabold text-white md:text-5xl">Organizatori</h1>
            <p class="text-lg leading-relaxed text-white/90">Descoperă organizatorii de evenimente din România. De la festivaluri mari la petreceri intime, găsește evenimentele perfecte pentru tine.</p>
        </div>
    </section>

    <!-- Search Section -->
    <section class="relative z-10 max-w-2xl px-6 mx-auto -mt-7">
        <form action="/organizatori" method="GET" class="flex items-center p-2 bg-white shadow-xl rounded-2xl">
            <?php if ($sort !== 'events'): ?>
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
            <?php endif; ?>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="flex-1 px-5 py-4 text-base placeholder-gray-400 bg-transparent outline-none text-secondary" placeholder="Caută un organizator...">
            <button type="submit" class="flex items-center gap-2 px-7 py-3.5 bg-gradient-to-r from-primary to-primary-dark rounded-xl text-white font-semibold hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/35 transition-all">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
                Caută
            </button>
        </form>
    </section>

    <main class="max-w-6xl px-6 py-12 mx-auto">
        <!-- Stats Section -->
        <?php if ($totalOrganizers > 0): ?>
        <section class="grid gap-5 mb-12 lg:grid-cols-4">
            <div class="p-6 text-center bg-white border border-gray-200 rounded-2xl">
                <div class="mb-1 text-3xl font-extrabold lg:text-4xl text-primary"><?= number_format($totalOrganizers) ?></div>
                <div class="text-sm text-gray-500">Organizatori activi</div>
            </div>
        </section>
        <?php endif; ?>

        <?php if (empty($allOrganizers)): ?>
        <!-- No results -->
        <div class="py-20 text-center">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <?php if (!empty($search)): ?>
            <h3 class="mb-2 text-xl font-bold text-secondary">Niciun rezultat pentru „<?= htmlspecialchars($search) ?>"</h3>
            <p class="mb-6 text-gray-500">Încearcă un alt termen de căutare.</p>
            <a href="/organizatori" class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-white transition-all bg-gradient-to-r from-primary to-primary-dark rounded-xl hover:-translate-y-0.5">
                Vezi toți organizatorii
            </a>
            <?php else: ?>
            <h3 class="mb-2 text-xl font-bold text-secondary">Niciun organizator momentan</h3>
            <p class="text-gray-500">Revino mai târziu pentru a descoperi organizatorii de evenimente.</p>
            <?php endif; ?>
        </div>
        <?php else: ?>

        <!-- Featured Organizers (first page only, no search) -->
        <?php if (!empty($featuredOrganizers)): ?>
        <section class="mb-12">
            <div class="flex items-center justify-between mb-6">
                <h2 class="flex items-center gap-2 text-2xl font-bold text-secondary">
                    <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                    Organizatori de top
                </h2>
            </div>
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($featuredOrganizers as $fi => $org):
                    $featuredGradient = $gradientClasses[$fi % count($gradientClasses)];
                    $logoUrl = $org['logo'] ?? null;
                ?>
                <a href="/organizator/<?= htmlspecialchars($org['slug']) ?>" class="overflow-hidden transition-all group bg-gradient-to-br from-slate-800 to-slate-700 rounded-2xl hover:-translate-y-1 hover:shadow-2xl">
                    <div class="relative h-28 <?= $featuredGradient ?>">
                        <div class="absolute inset-0 bg-gradient-to-b from-transparent to-slate-800/80"></div>
                    </div>
                    <div class="relative p-5 -mt-12">
                        <div class="relative z-10 flex items-center justify-center w-20 h-20 mb-4 overflow-hidden bg-white border-4 rounded-2xl border-slate-800">
                            <?php if ($logoUrl): ?>
                            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($org['name']) ?>" class="object-cover w-full h-full" loading="lazy">
                            <?php else: ?>
                            <span class="text-2xl font-bold text-primary"><?= mb_substr($org['name'], 0, 1) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($org['verified'])): ?>
                        <div class="absolute top-[-30px] right-5">
                            <span class="flex items-center gap-1 px-2.5 py-1.5 bg-blue-500 rounded-md text-[11px] font-semibold text-white">
                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                                </svg>
                                Verificat
                            </span>
                        </div>
                        <?php endif; ?>
                        <h3 class="mb-1 text-xl font-bold text-white"><?= htmlspecialchars($org['name']) ?></h3>
                        <?php if (!empty($org['city'])): ?>
                        <p class="flex items-center gap-1.5 mb-4 text-sm text-white/90">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                            </svg>
                            <?= htmlspecialchars($org['city']) ?>
                        </p>
                        <?php else: ?>
                        <div class="mb-4"></div>
                        <?php endif; ?>
                        <div class="flex gap-5">
                            <div class="text-center">
                                <div class="text-lg font-bold text-white"><?= (int) ($org['event_count'] ?? 0) ?></div>
                                <div class="text-[11px] text-white/90 uppercase tracking-wider">Evenimente</div>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Filters Bar -->
        <?php if (!empty($organizers)): ?>
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div class="flex items-center gap-3">
                <select onchange="window.location.href=this.value" class="py-3 px-4 pr-10 bg-white border border-gray-200 rounded-xl text-sm font-medium text-secondary cursor-pointer appearance-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2216%22%20height%3D%2216%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2394A3B8%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22M6%209l6%206%206-6%22%2F%3E%3C%2Fsvg%3E')] bg-no-repeat bg-[right_12px_center] focus:outline-none focus:border-primary">
                    <option value="<?= htmlspecialchars(buildOrgUrl(['sort' => 'events', 'page' => 1])) ?>" <?= $sort === 'events' ? 'selected' : '' ?>>Cele mai multe evenimente</option>
                    <option value="<?= htmlspecialchars(buildOrgUrl(['sort' => 'name', 'page' => 1])) ?>" <?= $sort === 'name' ? 'selected' : '' ?>>Alfabetic</option>
                    <option value="<?= htmlspecialchars(buildOrgUrl(['sort' => 'newest', 'page' => 1])) ?>" <?= $sort === 'newest' ? 'selected' : '' ?>>Adăugați recent</option>
                </select>
            </div>
            <span class="text-sm text-gray-500">
                <?php if (!empty($search)): ?>
                Rezultate pentru „<strong class="text-secondary"><?= htmlspecialchars($search) ?></strong>": <strong class="text-secondary"><?= $totalOrganizers ?></strong>
                <?php else: ?>
                Se afișează <strong class="text-secondary"><?= number_format($totalOrganizers) ?> organizatori</strong>
                <?php endif; ?>
            </span>
        </div>
        <?php endif; ?>

        <!-- Organizers Grid -->
        <?php if (!empty($organizers)): ?>
        <div class="grid grid-cols-1 gap-6 mb-12 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($organizers as $gi => $org):
                $gradient = $gradientClasses[$gi % count($gradientClasses)];
                $logoUrl = $org['logo'] ?? null;
                $eventCount = (int) ($org['event_count'] ?? 0);
            ?>
            <a href="/organizator/<?= htmlspecialchars($org['slug']) ?>" class="overflow-hidden transition-all bg-white border border-gray-200 group rounded-2xl hover:-translate-y-1 hover:shadow-xl hover:border-primary">
                <div class="h-24 <?= $gradient ?> relative">
                    <div class="absolute -bottom-9 left-6 w-[72px] h-[72px] rounded-2xl border-4 border-white bg-white overflow-hidden shadow-lg flex items-center justify-center">
                        <?php if ($logoUrl): ?>
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= htmlspecialchars($org['name']) ?>" class="object-cover w-full h-full" loading="lazy">
                        <?php else: ?>
                        <span class="text-xl font-bold text-primary"><?= mb_substr($org['name'], 0, 1) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($org['verified'])): ?>
                    <div class="absolute -bottom-2 left-[80px] w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center border-2 border-white">
                        <svg class="w-3 h-3 text-white" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                        </svg>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="px-6 pt-12 pb-6">
                    <h3 class="mb-1 text-lg font-bold text-secondary"><?= htmlspecialchars($org['name']) ?></h3>
                    <?php if (!empty($org['city'])): ?>
                    <p class="flex items-center gap-1.5 text-sm text-gray-400 mb-4">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                        </svg>
                        <?= htmlspecialchars($org['city']) ?>
                    </p>
                    <?php else: ?>
                    <div class="mb-4"></div>
                    <?php endif; ?>
                    <div class="flex gap-4 pt-4 border-t border-gray-100">
                        <div class="flex-1 text-center">
                            <div class="text-base font-bold text-secondary"><?= $eventCount ?></div>
                            <div class="text-[11px] text-gray-400 uppercase tracking-wider">Evenimente</div>
                        </div>
                    </div>
                </div>
                <?php if ($eventCount > 0): ?>
                <div class="px-6 pb-6">
                    <div class="flex items-center gap-2 px-4 py-3 text-sm font-semibold bg-red-50 rounded-xl text-primary">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <?= $eventCount ?> <?= $eventCount === 1 ? 'eveniment' : 'evenimente' ?>
                    </div>
                </div>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($lastPage > 1): ?>
        <div class="flex items-center justify-center gap-2 mb-12">
            <?php if ($currentPage > 1): ?>
            <a href="<?= htmlspecialchars(buildOrgUrl(['page' => $currentPage - 1])) ?>" class="flex items-center justify-center w-10 h-10 text-gray-500 transition-all bg-white border border-gray-200 rounded-xl hover:border-gray-300 hover:text-secondary">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </a>
            <?php else: ?>
            <span class="flex items-center justify-center w-10 h-10 text-gray-500 bg-white border border-gray-200 opacity-50 cursor-not-allowed rounded-xl">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 18 9 12 15 6"/>
                </svg>
            </span>
            <?php endif; ?>

            <?php
            // Build page numbers to show
            $pagesToShow = [];
            $pagesToShow[] = 1;
            if ($lastPage > 1) $pagesToShow[] = $lastPage;
            for ($p = max(2, $currentPage - 1); $p <= min($lastPage - 1, $currentPage + 1); $p++) {
                $pagesToShow[] = $p;
            }
            sort($pagesToShow);
            $pagesToShow = array_unique($pagesToShow);
            $prevPage = 0;
            foreach ($pagesToShow as $p):
                if ($prevPage && $p - $prevPage > 1): ?>
                    <span class="flex items-center justify-center w-10 h-10 text-gray-400">...</span>
                <?php endif;
                if ($p == $currentPage): ?>
                    <span class="flex items-center justify-center w-10 h-10 font-semibold text-white border bg-gradient-to-r from-primary to-primary-dark border-primary rounded-xl"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= htmlspecialchars(buildOrgUrl(['page' => $p])) ?>" class="flex items-center justify-center w-10 h-10 font-semibold text-gray-500 transition-all bg-white border border-gray-200 rounded-xl hover:border-gray-300 hover:text-secondary"><?= $p ?></a>
                <?php endif;
                $prevPage = $p;
            endforeach; ?>

            <?php if ($currentPage < $lastPage): ?>
            <a href="<?= htmlspecialchars(buildOrgUrl(['page' => $currentPage + 1])) ?>" class="flex items-center justify-center w-10 h-10 text-gray-500 transition-all bg-white border border-gray-200 rounded-xl hover:border-gray-300 hover:text-secondary">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>
            <?php else: ?>
            <span class="flex items-center justify-center w-10 h-10 text-gray-500 bg-white border border-gray-200 opacity-50 cursor-not-allowed rounded-xl">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; /* end empty check */ ?>

        <!-- CTA Section -->
        <section class="relative grid items-center grid-cols-1 gap-12 p-12 overflow-hidden bg-gradient-to-br from-slate-800 to-slate-700 rounded-3xl lg:grid-cols-2">
            <div class="absolute rounded-full -top-24 -right-24 w-72 h-72 bg-primary/30 blur-3xl"></div>
            <div class="relative z-10">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-primary/20 border border-primary/30 rounded-full text-xs font-semibold text-red-300 mb-4">Pentru organizatori</span>
                <h2 class="mb-3 text-3xl font-extrabold text-white">Organizezi evenimente?</h2>
                <p class="mb-6 leading-relaxed text-white/90">Alătură-te comunității de organizatori și ajunge la mii de pasionați de evenimente. Vânzări online, statistici în timp real și suport dedicat.</p>
                <a href="/become-organizer" class="inline-flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-primary to-primary-dark rounded-xl text-white font-semibold hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/40 transition-all">
                    Începe acum gratuit
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
            <div class="relative z-10 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <?php foreach ($ctaFeatures as $feature): ?>
                <div class="flex items-start gap-4">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-white bg-white/10 rounded-xl">
                        <?php if ($feature['icon'] === 'card'): ?>
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                        <?php elseif ($feature['icon'] === 'chart'): ?>
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
                        </svg>
                        <?php elseif ($feature['icon'] === 'users'): ?>
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                        <?php elseif ($feature['icon'] === 'clock'): ?>
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h4 class="mb-1 text-base font-bold text-white"><?= $feature['title'] ?></h4>
                        <p class="text-sm text-white/90"><?= $feature['desc'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/includes/footer.php'; ?>
    <?php require_once __DIR__ . '/includes/scripts.php'; ?>
