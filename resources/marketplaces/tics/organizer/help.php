<?php
/**
 * Organizer Help Center Page
 */

// Load demo data
$demoData = include __DIR__ . '/../data/demo-organizer.php';
$currentOrganizer = $demoData['organizer'];

// Help categories
$helpCategories = [
    [
        'icon' => '🚀',
        'icon_bg' => 'indigo',
        'title' => 'Primii pasi',
        'description' => 'Cum sa iti configurezi contul si sa publici primul eveniment',
        'link' => '#getting-started'
    ],
    [
        'icon' => '🎫',
        'icon_bg' => 'green',
        'title' => 'Gestionare bilete',
        'description' => 'Categorii de bilete, preturi, coduri promotionale',
        'link' => '#tickets'
    ],
    [
        'icon' => '💰',
        'icon_bg' => 'purple',
        'title' => 'Plati si facturare',
        'description' => 'Cum primesti banii, comisioane, facturi',
        'link' => '#payments'
    ],
    [
        'icon' => '📊',
        'icon_bg' => 'amber',
        'title' => 'Analiza si rapoarte',
        'description' => 'Intelege datele despre vanzari si public',
        'link' => '#analytics'
    ],
    [
        'icon' => '📱',
        'icon_bg' => 'red',
        'title' => 'Aplicatia Scanner',
        'description' => 'Cum sa validezi biletele la intrare',
        'link' => '#scanner'
    ],
    [
        'icon' => '🔗',
        'icon_bg' => 'cyan',
        'title' => 'Integrari & API',
        'description' => 'Widget-uri, API, webhook-uri',
        'link' => '#integrations'
    ]
];

// Popular articles
$popularArticles = [
    'Cum creez un eveniment nou?',
    'Cand si cum primesc banii din vanzari?',
    'Cum configurez un cod de reducere?',
    'Cum adaug widget-ul pe site-ul meu?'
];

// Current page for sidebar (not used but included for consistency)
$currentPage = 'help';

// Page config for head
$pageTitle = 'Centru de ajutor';
$pageDescription = 'Tot ce trebuie sa stii pentru a vinde bilete cu succes';

// Include organizer head
include __DIR__ . '/../includes/organizer-head.php';
?>
    <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="/organizator" class="flex items-center gap-2">
                <div class="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center">
                    <span class="text-white font-bold">T</span>
                </div>
                <span class="font-bold text-xl">TICS</span>
                <span class="text-xs text-gray-400 ml-1">Organizer</span>
            </a>
            <a href="/organizator" class="text-sm text-indigo-600 hover:underline">← Dashboard</a>
        </div>
    </header>

    <div class="max-w-6xl mx-auto px-4 py-12">
        <div class="text-center mb-12 animate-fadeInUp">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Centru de ajutor pentru organizatori</h1>
            <p class="text-xl text-gray-500">Tot ce trebuie sa stii pentru a vinde bilete cu succes</p>
            <div class="relative max-w-xl mx-auto mt-8">
                <input type="text" id="searchHelp" class="w-full pl-12 pr-4 py-4 bg-white border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Cauta in documentatie...">
                <svg class="w-5 h-5 text-gray-400 absolute left-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
        </div>

        <!-- Help Categories -->
        <div class="grid md:grid-cols-3 gap-6 mb-12">
            <?php foreach ($helpCategories as $index => $category): ?>
            <a href="<?= htmlspecialchars($category['link']) ?>" class="bg-white rounded-2xl border border-gray-200 p-6 hover:shadow-lg transition-shadow animate-fadeInUp" style="animation-delay: <?= 0.1 + ($index * 0.05) ?>s">
                <div class="w-14 h-14 bg-<?= $category['icon_bg'] ?>-100 rounded-xl flex items-center justify-center mb-4 text-2xl"><?= $category['icon'] ?></div>
                <h3 class="font-bold text-gray-900 mb-2"><?= htmlspecialchars($category['title']) ?></h3>
                <p class="text-sm text-gray-500"><?= htmlspecialchars($category['description']) ?></p>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Popular Articles -->
        <div class="bg-white rounded-2xl border border-gray-200 p-8 mb-8 animate-fadeInUp" style="animation-delay: 0.4s">
            <h2 class="text-xl font-bold text-gray-900 mb-6">📚 Articole populare</h2>
            <div class="space-y-4">
                <?php foreach ($popularArticles as $article): ?>
                <a href="#" class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                    <span><?= htmlspecialchars($article) ?></span>
                    <span class="text-indigo-600">→</span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Contact -->
        <div class="bg-indigo-50 rounded-2xl p-8 text-center animate-fadeInUp" style="animation-delay: 0.5s">
            <h3 class="text-xl font-bold text-gray-900 mb-2">Nu ai gasit raspunsul?</h3>
            <p class="text-gray-600 mb-6">Echipa noastra dedicata organizatorilor este aici pentru tine.</p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="/contact" class="px-6 py-3 bg-indigo-600 text-white font-medium rounded-xl hover:bg-indigo-700 transition-colors">💬 Chat cu suport</a>
                <a href="mailto:organizatori@tics.ro" class="px-6 py-3 bg-white border border-gray-200 text-gray-700 font-medium rounded-xl hover:bg-gray-50 transition-colors">📧 organizatori@tics.ro</a>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchHelp').addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase();
            // In production, this would filter articles or trigger an API search
            console.log('Searching for:', query);
        });
    </script>
</body>
</html>
