<?php
/**
 * TICS.ro - Past Events Archive
 * Year tabs, month markers, event grid
 */

require_once __DIR__ . '/includes/config.php';

// Page settings
$pageTitle = 'Evenimente trecute';
$pageDescription = 'Arhiva completă a evenimentelor organizate prin TICS. Revizuiește momentele memorabile din concerte, festivaluri și spectacole.';
$bodyClass = 'bg-gray-50';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => '/'],
    ['name' => 'Evenimente trecute', 'url' => null],
];

// Demo stats
$stats = [
    ['value' => '847', 'label' => 'Evenimente în 2025'],
    ['value' => '342K', 'label' => 'Bilete vândute'],
    ['value' => '156', 'label' => 'Artiști unici'],
    ['value' => '42', 'label' => 'Orașe'],
];

// Year tabs
$years = [2024, 2025, 2026];
$activeYear = 2025;

// Filter chips
$filterChips = ['Toate', '&#x1F3A4; Concerte', '&#x1F3AA; Festivaluri', '&#x1F602; Stand-up', '&#x1F3AD; Teatru', '&#x1F3A7; Cluburi & DJ'];

// City options
$cityOptions = ['Toate orașele', 'București', 'Cluj-Napoca', 'Timișoara', 'Iași', 'Brașov'];

// Month options
$monthOptions = ['Toate lunile', 'Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];

// Demo past events grouped by month
$pastEventsByMonth = [
    'Decembrie 2025' => [
        [
            'title' => 'Carla\'s Dreams - Concert de Crăciun',
            'slug' => 'carlas-dreams-concert-craciun',
            'venue' => 'Sala Palatului, București',
            'date' => '15 Dec 2025',
            'image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=500&h=280&fit=crop',
            'status' => 'Sold Out ✓',
            'statusClass' => 'bg-gray-900/80',
            'rating' => '4.9',
            'attendees' => '4.200 participanți',
            'likes' => '1.8K aprecieri',
        ],
        [
            'title' => 'Subcarpați - Turneu Final',
            'slug' => 'subcarpati-turneu-final',
            'venue' => 'Fratelli Studios, București',
            'date' => '12 Dec 2025',
            'image' => 'https://images.unsplash.com/photo-1524368535928-5b5e00ddc76b?w=500&h=280&fit=crop',
            'status' => 'Sold Out ✓',
            'statusClass' => 'bg-gray-900/80',
            'rating' => null,
            'attendees' => '2.500 participanți',
            'likes' => '923 aprecieri',
        ],
        [
            'title' => 'Irina Rimes - Concert Intim',
            'slug' => 'irina-rimes-concert-intim',
            'venue' => 'Teatrul Național, Cluj',
            'date' => '8 Dec 2025',
            'image' => 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=500&h=280&fit=crop',
            'status' => 'Încheiat',
            'statusClass' => 'bg-gray-600/80',
            'rating' => null,
            'attendees' => '1.100 participanți',
            'likes' => '654 aprecieri',
        ],
    ],
    'Noiembrie 2025' => [
        [
            'title' => 'The Motans - Până la soare',
            'slug' => 'the-motans-pana-la-soare',
            'venue' => 'Arenele Romane, București',
            'date' => '22 Nov 2025',
            'image' => 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=500&h=280&fit=crop',
            'status' => 'Sold Out ✓',
            'statusClass' => 'bg-gray-900/80',
            'rating' => '4.8',
            'attendees' => '5.000 participanți',
            'likes' => '2.1K aprecieri',
        ],
        [
            'title' => 'Delia - Show Aniversar',
            'slug' => 'delia-show-aniversar',
            'venue' => 'BT Arena, Cluj-Napoca',
            'date' => '15 Nov 2025',
            'image' => 'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?w=500&h=280&fit=crop',
            'status' => 'Încheiat',
            'statusClass' => 'bg-gray-600/80',
            'rating' => null,
            'attendees' => '3.800 participanți',
            'likes' => '1.4K aprecieri',
        ],
        [
            'title' => 'Luiza Zan - Jazz Night',
            'slug' => 'luiza-zan-jazz-night',
            'venue' => 'Green Hours, București',
            'date' => '8 Nov 2025',
            'image' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=500&h=280&fit=crop',
            'status' => 'Încheiat',
            'statusClass' => 'bg-gray-600/80',
            'rating' => null,
            'attendees' => '220 participanți',
            'likes' => '187 aprecieri',
        ],
    ],
];

$totalEvents = 847;
$displayedEvents = 6;

setLoginState($isLoggedIn, $loggedInUser);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

    <!-- Page Header -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 py-8">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
                <a href="/" class="hover:text-gray-900 transition-colors">Acasă</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-gray-900 font-medium">Evenimente trecute</span>
            </div>
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-gray-900 mb-2">Evenimente trecute</h1>
                    <p class="text-gray-600">Arhiva completă a evenimentelor organizate prin TICS. Revizuiește momentele memorabile.</p>
                </div>
                <!-- Year Tabs -->
                <div class="flex items-center gap-1 bg-gray-100 rounded-xl p-1">
                    <?php foreach ($years as $year): ?>
                    <button class="year-tab<?= $year === $activeYear ? ' active' : '' ?> px-4 py-2 rounded-lg text-sm font-medium<?= $year !== $activeYear ? ' text-gray-600' : '' ?>" onclick="switchYear(this)"><?= e((string)$year) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 py-4">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <?php foreach ($stats as $stat): ?>
                <div class="stats-card bg-gray-50 rounded-xl p-4 text-center">
                    <p class="text-2xl font-bold text-gray-900"><?= e($stat['value']) ?></p>
                    <p class="text-xs text-gray-500 mt-0.5"><?= e($stat['label']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="sticky top-16 z-30 bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 lg:px-8">
            <div class="flex items-center gap-2 py-3 overflow-x-auto no-scrollbar">
                <?php foreach ($filterChips as $index => $chip): ?>
                <button class="<?= $index === 0 ? 'chip-active ' : '' ?>px-3.5 py-1.5 rounded-full border border-gray-200 text-xs font-medium<?= $index > 0 ? ' text-gray-600' : '' ?> whitespace-nowrap<?= $index > 0 ? ' hover:border-gray-300' : '' ?> transition-colors" onclick="toggleChip(this)"><?= $chip ?></button>
                <?php endforeach; ?>
                <div class="w-px h-6 bg-gray-200 mx-1"></div>
                <select class="bg-gray-50 border border-gray-200 rounded-full px-3 py-1.5 text-xs font-medium text-gray-700 cursor-pointer focus:outline-none">
                    <?php foreach ($cityOptions as $city): ?>
                    <option><?= e($city) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="bg-gray-50 border border-gray-200 rounded-full px-3 py-1.5 text-xs font-medium text-gray-700 cursor-pointer focus:outline-none">
                    <?php foreach ($monthOptions as $month): ?>
                    <option><?= e($month) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <main class="max-w-7xl mx-auto px-4 lg:px-8 py-8">
        <?php foreach ($pastEventsByMonth as $monthLabel => $events): ?>
        <!-- Month: <?= e($monthLabel) ?> -->
        <div class="month-marker flex items-center justify-center mb-6">
            <span class="text-sm font-semibold text-gray-900 uppercase tracking-wider"><?= e($monthLabel) ?></span>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-10">
            <?php foreach ($events as $event): ?>
            <a href="<?= eventUrl($event['slug']) ?>" class="event-card bg-white rounded-2xl overflow-hidden border border-gray-200 group">
                <div class="relative aspect-[16/9] overflow-hidden">
                    <img src="<?= e($event['image']) ?>" class="absolute inset-0 w-full h-full object-cover event-img grayscale-[30%]" alt="<?= e($event['title']) ?>">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                    <div class="absolute top-3 left-3">
                        <span class="px-2.5 py-1 <?= e($event['statusClass']) ?> backdrop-blur text-white text-xs font-medium rounded-full"><?= e($event['status']) ?></span>
                    </div>
                    <?php if ($event['rating']): ?>
                    <div class="absolute top-3 right-3 flex items-center gap-1 px-2 py-1 bg-white/90 rounded-full">
                        <svg class="w-3.5 h-3.5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <span class="text-xs font-semibold text-gray-700"><?= e($event['rating']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="absolute bottom-3 left-3">
                        <p class="text-white text-xs font-medium bg-white/20 backdrop-blur px-2.5 py-1 rounded-full"><?= e($event['date']) ?></p>
                    </div>
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-gray-900 mb-1 group-hover:text-indigo-600 transition-colors"><?= e($event['title']) ?></h3>
                    <p class="text-sm text-gray-500 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        <?= e($event['venue']) ?>
                    </p>
                    <div class="flex items-center gap-3 mt-3 pt-3 border-t border-gray-100">
                        <span class="flex items-center gap-1 text-xs text-gray-500">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <?= e($event['attendees']) ?>
                        </span>
                        <span class="flex items-center gap-1 text-xs text-gray-500">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                            <?= e($event['likes']) ?>
                        </span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <!-- Load more -->
        <div class="text-center">
            <button class="inline-flex items-center gap-2 px-8 py-3 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-full hover:border-gray-300 hover:bg-gray-50 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                Încarcă mai multe evenimente
            </button>
            <p class="text-xs text-gray-400 mt-3">Se afișează <?= $displayedEvents ?> din <?= number_format($totalEvents, 0, ',', '.') ?> evenimente</p>
        </div>
    </main>

<?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
    function switchYear(btn){document.querySelectorAll('.year-tab').forEach(t=>{t.classList.remove('active');t.style.background='';t.style.color=''});btn.classList.add('active')}
    function toggleChip(btn){btn.parentElement.querySelectorAll('button').forEach(c=>{c.classList.remove('chip-active')});btn.classList.add('chip-active')}
    </script>
</body>
</html>
