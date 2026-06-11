<?php
/**
 * TICS.ro - Search Results Page
 * Filter chips, sort dropdown, event cards grid, newsletter CTA
 */

require_once __DIR__ . '/includes/config.php';

// Page settings
$searchQuery = $_GET['q'] ?? 'Concert Brașov';
$pageTitle = 'Rezultate căutare „' . $searchQuery . '"';
$pageDescription = 'Rezultate căutare pentru „' . $searchQuery . '" pe TICS.ro — concerte, festivaluri, teatru și alte evenimente.';
$bodyClass = 'bg-gray-50';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Căutare', 'url' => null],
];

// Filter chips
$filterChips = [
    ['label' => 'Toate (24)', 'active' => true],
    ['label' => '&#x1F3B5; Concerte', 'active' => false],
    ['label' => '&#x1F3AA; Festivaluri', 'active' => false],
    ['label' => '&#x1F602; Stand-up', 'active' => false],
    ['label' => '&#x1F3AD; Teatru', 'active' => false],
    ['label' => 'Săptămâna asta', 'active' => false],
    ['label' => 'Sub 100 RON', 'active' => false],
];

// Sort options
$sortOptions = [
    'Dată (cele mai apropiate)',
    'Preț (crescător)',
    'Preț (descrescător)',
    'Popularitate',
];
$activeSort = 'Dată (apropiate)';

// Demo search results
$totalResults = 24;
$thisMonthResults = 8;

$featuredResult = [
    'title' => 'Carla\'s Dreams — Nocturn Tour',
    'slug' => 'carlas-dreams-nocturn-tour-brasov',
    'category' => 'Concert',
    'categoryColor' => 'bg-indigo-50 text-indigo-700',
    'date' => '14 mar 2026',
    'description' => 'Cel mai așteptat turneu al anului 2026 ajunge la Brașov. O experiență completă cu producție vizuală de top.',
    'venue' => 'Aro Palace',
    'price' => 'de la 89 RON',
    'gradient' => 'from-indigo-300 to-violet-400',
    'badges' => ['DISPONIBIL', '&#x2B50; Recomandat'],
    'urgency' => null,
];

$searchResults = [
    [
        'title' => 'Micutzu — Sold Out Show',
        'slug' => 'micutzu-sold-out-show-brasov',
        'category' => 'Stand-up',
        'categoryColor' => 'bg-rose-50 text-rose-700',
        'dateMonth' => 'Mar',
        'dateDay' => '22',
        'venue' => 'Teatrul Sică Alexandrescu',
        'price' => 'de la 75 RON',
        'gradient' => 'from-rose-300 to-pink-400',
        'status' => 'DISPONIBIL',
        'statusColor' => 'bg-green-500',
        'urgency' => 'Ultimele 12 locuri',
    ],
    [
        'title' => 'Subcarpați — Acustic',
        'slug' => 'subcarpati-acustic-brasov',
        'category' => 'Concert',
        'categoryColor' => 'bg-emerald-50 text-emerald-700',
        'dateMonth' => 'Apr',
        'dateDay' => '05',
        'venue' => 'Piața Sfatului',
        'price' => 'de la 60 RON',
        'gradient' => 'from-emerald-300 to-teal-400',
        'status' => 'DISPONIBIL',
        'statusColor' => 'bg-green-500',
        'urgency' => null,
    ],
    [
        'title' => 'O Scrisoare Pierdută',
        'slug' => 'o-scrisoare-pierduta-brasov',
        'category' => 'Teatru',
        'categoryColor' => 'bg-violet-50 text-violet-700',
        'dateMonth' => 'Apr',
        'dateDay' => '12',
        'venue' => 'Teatrul Dramatic',
        'price' => 'de la 45 RON',
        'gradient' => 'from-amber-300 to-orange-400',
        'status' => 'APROAPE SOLD OUT',
        'statusColor' => 'bg-amber-500',
        'urgency' => 'Ultimele 5 locuri',
    ],
    [
        'title' => 'Vama — Best Of Tour',
        'slug' => 'vama-best-of-tour-brasov',
        'category' => 'Concert',
        'categoryColor' => 'bg-sky-50 text-sky-700',
        'dateMonth' => 'Apr',
        'dateDay' => '19',
        'venue' => 'Aro Palace',
        'price' => 'de la 70 RON',
        'gradient' => 'from-sky-300 to-blue-400',
        'status' => 'DISPONIBIL',
        'statusColor' => 'bg-green-500',
        'urgency' => null,
    ],
    [
        'title' => 'The Mono Jacks',
        'slug' => 'the-mono-jacks-brasov',
        'category' => 'Concert',
        'categoryColor' => 'bg-indigo-50 text-indigo-700',
        'dateMonth' => 'Mai',
        'dateDay' => '03',
        'venue' => 'Kruhnen Musik Hull',
        'price' => 'de la 55 RON',
        'gradient' => 'from-violet-300 to-purple-400',
        'status' => 'DISPONIBIL',
        'statusColor' => 'bg-green-500',
        'urgency' => null,
    ],
];

// No-results suggestions
$noResultsSuggestions = ['Concerte România', 'Festivaluri 2026', 'Stand-up comedy'];

setLoginState($isLoggedIn, $loggedInUser);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

    <main class="max-w-6xl mx-auto px-4 lg:px-8 py-6">

        <!-- Results meta + filters bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
            <div>
                <h1 class="text-lg font-bold text-gray-900"><span class="text-gray-400 font-normal">Rezultate pentru</span> &ldquo;<?= e($searchQuery) ?>&rdquo;</h1>
                <p class="text-sm text-gray-500 mt-0.5"><?= $totalResults ?> evenimente găsite &middot; <span class="text-green-600 font-medium"><?= $thisMonthResults ?> în această lună</span></p>
            </div>
            <div class="flex items-center gap-2">
                <!-- Sort -->
                <div class="relative" id="sortWrap">
                    <button onclick="document.getElementById('sortDrop').classList.toggle('hidden')" class="flex items-center gap-1.5 px-3 py-2 border border-gray-200 rounded-lg text-sm text-gray-600 hover:bg-white transition-colors bg-white">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
                        <span id="sortLabel"><?= e($activeSort) ?></span>
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div id="sortDrop" class="hidden absolute right-0 top-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg py-1 w-48 z-20">
                        <?php foreach ($sortOptions as $index => $opt): ?>
                        <button class="sort-opt<?= $index === 0 ? ' active' : '' ?> w-full text-left px-3 py-2 text-sm<?= $index > 0 ? ' text-gray-500' : '' ?>" onclick="setSort('<?= e($opt) ?>')"><?= e($opt) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- View toggle -->
                <div class="flex items-center bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <button class="view-btn active p-2" onclick="setView('grid',this)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg></button>
                    <button class="view-btn p-2" onclick="setView('list',this)"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                </div>
            </div>
        </div>

        <!-- Filter chips -->
        <div class="flex items-center gap-2 overflow-x-auto pb-4 mb-2 scrollbar-hide">
            <?php foreach ($filterChips as $chip): ?>
            <button class="filter-chip<?= $chip['active'] ? ' active' : '' ?> px-4 py-2 border border-gray-200 rounded-full text-sm font-medium<?= !$chip['active'] ? ' text-gray-600' : '' ?> whitespace-nowrap" onclick="toggleChip(this)"><?= $chip['label'] ?></button>
            <?php endforeach; ?>
            <span class="text-gray-300 mx-1">|</span>
            <button class="filter-chip px-3 py-2 border border-gray-200 rounded-full text-sm text-gray-500 whitespace-nowrap flex items-center gap-1" onclick="toggleChip(this)">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                Filtre
            </button>
        </div>

        <!-- Results Grid -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4" id="resultsGrid">

            <!-- Featured result -->
            <a href="<?= eventUrl($featuredResult['slug']) ?>" class="anim event-card sm:col-span-2 lg:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden group block" style="animation-delay:.05s">
                <div class="grid sm:grid-cols-2">
                    <div class="relative overflow-hidden aspect-[16/10] sm:aspect-auto">
                        <div class="card-img absolute inset-0 bg-gradient-to-br <?= e($featuredResult['gradient']) ?>"></div>
                        <div class="absolute top-3 left-3 flex gap-1.5">
                            <span class="px-2.5 py-1 bg-green-500 text-white text-[11px] font-bold rounded-full shadow-sm"><?= $featuredResult['badges'][0] ?></span>
                            <span class="px-2.5 py-1 bg-white/90 date-badge text-gray-900 text-[11px] font-bold rounded-full shadow-sm"><?= $featuredResult['badges'][1] ?></span>
                        </div>
                    </div>
                    <div class="p-5 flex flex-col justify-center">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="px-2 py-0.5 <?= e($featuredResult['categoryColor']) ?> text-[11px] font-semibold rounded-full"><?= e($featuredResult['category']) ?></span>
                            <span class="text-xs text-gray-400"><?= e($featuredResult['date']) ?></span>
                        </div>
                        <h3 class="font-bold text-gray-900 text-lg mb-1 group-hover:text-indigo-600 transition-colors"><?= e($featuredResult['title']) ?></h3>
                        <p class="text-sm text-gray-500 mb-3 line-clamp-2"><?= e($featuredResult['description']) ?></p>
                        <div class="flex items-center gap-3 text-sm">
                            <span class="flex items-center gap-1 text-gray-500">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <?= e($featuredResult['venue']) ?>
                            </span>
                            <span class="font-bold text-emerald-600 price-glow"><?= e($featuredResult['price']) ?></span>
                        </div>
                    </div>
                </div>
            </a>

            <!-- Regular cards -->
            <?php foreach ($searchResults as $index => $result): ?>
            <a href="<?= eventUrl($result['slug']) ?>" class="anim event-card bg-white rounded-2xl border border-gray-200 overflow-hidden group block" style="animation-delay:<?= ($index + 2) * 0.05 ?>s">
                <div class="relative overflow-hidden aspect-[16/10]">
                    <div class="card-img absolute inset-0 bg-gradient-to-br <?= e($result['gradient']) ?>"></div>
                    <div class="absolute top-3 left-3"><span class="px-2.5 py-1 <?= e($result['statusColor']) ?> text-white text-[11px] font-bold rounded-full shadow-sm"><?= e($result['status']) ?></span></div>
                    <div class="absolute bottom-3 right-3 date-badge bg-white/90 rounded-lg px-2.5 py-1.5 text-center shadow-sm">
                        <p class="text-[10px] text-gray-500 font-medium uppercase leading-none"><?= e($result['dateMonth']) ?></p>
                        <p class="text-lg font-bold text-gray-900 leading-none"><?= e($result['dateDay']) ?></p>
                    </div>
                </div>
                <div class="p-4">
                    <div class="flex items-center gap-2 mb-1.5"><span class="px-2 py-0.5 <?= e($result['categoryColor']) ?> text-[11px] font-semibold rounded-full"><?= e($result['category']) ?></span></div>
                    <h3 class="font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors mb-1"><?= e($result['title']) ?></h3>
                    <p class="text-xs text-gray-500 flex items-center gap-1 mb-2">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        <?= e($result['venue']) ?>
                    </p>
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-emerald-600"><?= e($result['price']) ?></span>
                        <?php if ($result['urgency']): ?>
                        <span class="text-[11px] text-amber-600 font-medium bg-amber-50 px-2 py-0.5 rounded-full"><?= e($result['urgency']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Load more -->
        <div class="text-center mt-8 mb-4">
            <p class="text-xs text-gray-400 mb-3">Afișezi 6 din <?= $totalResults ?> rezultate</p>
            <button class="px-8 py-3 bg-white border border-gray-200 text-gray-700 text-sm font-semibold rounded-full hover:bg-gray-50 hover:border-gray-300 transition-colors">Mai multe rezultate</button>
        </div>

        <!-- No results alert (hidden by default) -->
        <div class="hidden mt-8 text-center py-16" id="noResults">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center text-4xl mx-auto mb-5">&#x1F50D;</div>
            <h2 class="text-xl font-bold text-gray-900 mb-2">Niciun rezultat pentru căutarea ta</h2>
            <p class="text-sm text-gray-500 max-w-sm mx-auto mb-6">Încearcă alte cuvinte cheie sau filtre mai puțin restrictive.</p>
            <div class="flex items-center justify-center gap-2 flex-wrap">
                <?php foreach ($noResultsSuggestions as $suggestion): ?>
                <a href="/cauta?q=<?= urlencode($suggestion) ?>" class="px-4 py-2 bg-gray-100 text-gray-700 text-sm rounded-full hover:bg-gray-200 transition-colors"><?= e($suggestion) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Newsletter CTA -->
        <div class="bg-gradient-to-r from-gray-900 to-gray-800 rounded-2xl p-6 lg:p-8 mt-10 mb-6 flex flex-col sm:flex-row items-center gap-6 overflow-hidden relative">
            <div class="absolute -right-20 -top-20 w-60 h-60 bg-indigo-500/10 rounded-full blur-3xl"></div>
            <div class="flex-1 relative">
                <h3 class="font-bold text-white text-lg mb-1">Nu rata niciun concert în <?= e($searchQuery) ?> &#x1F3B5;</h3>
                <p class="text-sm text-gray-400">Primești notificare când apar evenimente noi în zona ta.</p>
            </div>
            <div class="flex gap-2 flex-shrink-0 w-full sm:w-auto relative">
                <input type="email" placeholder="Email-ul tău" class="flex-1 sm:w-56 px-4 py-3 bg-white/10 border border-white/10 rounded-xl text-white text-sm placeholder:text-gray-500 outline-none focus:border-white/30">
                <button class="px-5 py-3 bg-indigo-500 text-white text-sm font-semibold rounded-xl hover:bg-indigo-400 transition-colors flex-shrink-0">Abonează-te</button>
            </div>
        </div>
    </main>

<?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
    function toggleChip(el){el.classList.toggle('active');el.classList.toggle('text-gray-600')}
    function setSort(label){document.getElementById('sortLabel').textContent=label;document.getElementById('sortDrop').classList.add('hidden');document.querySelectorAll('.sort-opt').forEach(s=>{s.classList.remove('active');s.classList.add('text-gray-500');if(s.textContent.includes(label)){s.classList.add('active');s.classList.remove('text-gray-500')}})}
    function setView(mode,btn){document.querySelectorAll('.view-btn').forEach(b=>b.classList.remove('active'));btn.classList.add('active');const g=document.getElementById('resultsGrid');if(mode==='list'){g.className='space-y-3'}else{g.className='grid sm:grid-cols-2 lg:grid-cols-3 gap-4'}}
    document.addEventListener('click',e=>{if(!document.getElementById('sortWrap').contains(e.target))document.getElementById('sortDrop').classList.add('hidden')});
    </script>
</body>
</html>
