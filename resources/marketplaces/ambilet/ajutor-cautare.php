<?php
/**
 * KB Search Page - Cautare Articole
 * Ambilet Marketplace
 */
require_once 'includes/config.php';
require_once 'includes/api.php';

// Get search query from URL
$query = $_GET['q'] ?? '';
$results = [];
$totalResults = 0;

if (!empty($query) && strlen($query) >= 2) {
    // Search articles via API
    $searchResponse = api_get('/kb/articles/search', ['q' => $query]);
    if ($searchResponse['success']) {
        $results = $searchResponse['data']['results'] ?? [];
        $totalResults = $searchResponse['data']['total'] ?? count($results);
    }
}

$pageTitle = (!empty($query) ? 'Rezultate pentru "' . htmlspecialchars($query) . '"' : 'Cautare') . ' — Centru de Ajutor — ' . SITE_NAME;
$pageDescription = 'Cauta in baza de cunostinte Ambilet pentru a gasi raspunsuri la intrebarile tale.';

// Helper function to get icon SVG
function getIconSvg($iconName, $size = 'w-8 h-8') {
    $icons = [
        'heroicon-o-ticket' => '<svg class="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 3 3v1a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1a3 3 0 0 1 0-6V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v1a3 3 0 0 1-3 3Z"/></svg>',
        'heroicon-o-document-text' => '<svg class="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        'heroicon-o-question-mark-circle' => '<svg class="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    ];
    return $icons[$iconName] ?? $icons['heroicon-o-document-text'];
}

// Helper to highlight search terms
function highlightTerms($text, $query) {
    if (empty($query)) return htmlspecialchars($text);
    $terms = preg_split('/\s+/', $query);
    $result = htmlspecialchars($text);
    foreach ($terms as $term) {
        if (strlen($term) >= 2) {
            $result = preg_replace('/(' . preg_quote(htmlspecialchars($term), '/') . ')/iu', '<mark class="bg-yellow-200 text-yellow-900 rounded px-0.5">$1</mark>', $result);
        }
    }
    return $result;
}
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <?php include 'includes/head.php'; ?>
</head>
<body class="min-h-screen font-body bg-surface text-secondary">
    <?php include 'includes/header.php'; ?>

    <!-- Search Header -->
    <section class="px-6 py-8 bg-white border-b border-gray-200 mt-28 mobile:mt-18">
        <div class="max-w-4xl mx-auto">
            <!-- Breadcrumb -->
            <nav class="flex items-center gap-2 mb-6 text-sm text-gray-500">
                <a href="/" class="hover:text-primary">Acasa</a>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
                <a href="/ajutor" class="hover:text-primary">Centru de Ajutor</a>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
                <span class="text-secondary font-medium">Cautare</span>
            </nav>

            <h1 class="mb-6 text-3xl font-bold text-secondary">Cautare in baza de cunostinte</h1>

            <!-- Search Form -->
            <form action="/ajutor/cautare" method="GET" class="relative">
                <svg class="absolute w-5 h-5 text-gray-400 -translate-y-1/2 left-5 top-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
                <input
                    type="text"
                    name="q"
                    value="<?= htmlspecialchars($query) ?>"
                    class="w-full px-6 py-4 text-base placeholder-gray-400 bg-white border border-gray-200 pl-14 rounded-2xl text-secondary focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
                    placeholder="Cauta articole, intrebari sau subiecte..."
                    autofocus
                >
                <button type="submit" class="absolute px-6 py-2 text-sm font-semibold text-white transition-all -translate-y-1/2 bg-primary rounded-xl right-3 top-1/2 hover:bg-primary-dark">
                    Cauta
                </button>
            </form>
        </div>
    </section>

    <main class="max-w-4xl px-6 py-10 mx-auto">
        <?php if (empty($query)): ?>
        <!-- No Query -->
        <div class="p-12 text-center bg-white border border-gray-200 rounded-2xl">
            <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 text-gray-400 bg-gray-100 rounded-2xl">
                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
            </div>
            <h3 class="mb-2 text-lg font-semibold text-secondary">Cauta in baza de cunostinte</h3>
            <p class="text-gray-500">Introdu un termen de cautare pentru a gasi articole si raspunsuri.</p>
        </div>

        <?php elseif (strlen($query) < 2): ?>
        <!-- Query too short -->
        <div class="p-8 text-center bg-yellow-50 border border-yellow-200 rounded-2xl">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 text-yellow-600 bg-yellow-100 rounded-xl">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <p class="text-yellow-800">Termenul de cautare trebuie sa aiba cel putin 2 caractere.</p>
        </div>

        <?php elseif (empty($results)): ?>
        <!-- No Results -->
        <div class="p-12 text-center bg-white border border-gray-200 rounded-2xl">
            <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 text-gray-400 bg-gray-100 rounded-2xl">
                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="M21 21l-4.35-4.35"/>
                    <line x1="8" y1="8" x2="14" y2="14"/>
                    <line x1="14" y1="8" x2="8" y2="14"/>
                </svg>
            </div>
            <h3 class="mb-2 text-lg font-semibold text-secondary">Niciun rezultat gasit</h3>
            <p class="mb-6 text-gray-500">Nu am gasit articole care sa contina "<strong><?= htmlspecialchars($query) ?></strong>".</p>
            <div class="text-sm text-gray-500">
                <p class="mb-2">Sugestii:</p>
                <ul class="text-left max-w-xs mx-auto space-y-1">
                    <li>• Verifica daca ai scris corect</li>
                    <li>• Incearca cuvinte cheie mai simple</li>
                    <li>• Foloseste termeni mai generali</li>
                </ul>
            </div>
            <a href="/ajutor" class="inline-flex items-center gap-2 px-6 py-3 mt-6 text-sm font-semibold text-white transition-all bg-primary rounded-xl hover:bg-primary-dark">
                Exploreaza categoriile
            </a>
        </div>

        <?php else: ?>
        <!-- Results Found -->
        <div class="mb-6">
            <p class="text-gray-500">
                <?= $totalResults ?> rezultat<?= $totalResults !== 1 ? 'e' : '' ?> pentru "<strong class="text-secondary"><?= htmlspecialchars($query) ?></strong>"
            </p>
        </div>

        <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl">
            <?php foreach ($results as $index => $article): ?>
            <a href="/ajutor/articol/<?= htmlspecialchars($article['slug']) ?>" class="flex items-start gap-4 px-6 py-5 <?= $index < count($results) - 1 ? 'border-b border-gray-200' : '' ?> hover:bg-gray-50 group transition-colors">
                <div class="flex items-center justify-center flex-shrink-0 text-gray-500 bg-gray-100 w-11 h-11 rounded-xl">
                    <?= getIconSvg($article['icon'] ?? ($article['type'] === 'faq' ? 'heroicon-o-question-mark-circle' : 'heroicon-o-document-text'), 'w-5 h-5') ?>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-base font-semibold text-secondary mb-1 group-hover:text-primary transition-colors">
                        <?= highlightTerms($article['type'] === 'faq' ? ($article['question'] ?? '') : ($article['title'] ?? ''), $query) ?>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-gray-400">
                        <?php if ($article['type'] === 'faq'): ?>
                        <span class="px-2 py-0.5 bg-purple-100 text-purple-600 rounded-full font-medium">FAQ</span>
                        <?php endif; ?>
                        <?php if (!empty($article['category'])): ?>
                        <span><?= htmlspecialchars($article['category']['name']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($article['view_count'])): ?>
                        <span><?= number_format($article['view_count']) ?> vizualizari</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-gray-300 transition-transform group-hover:translate-x-1 flex-shrink-0 mt-2">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Back Link -->
        <div class="mt-8">
            <a href="/ajutor" class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 transition-colors hover:text-primary">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Inapoi la Centrul de Ajutor
            </a>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/scripts.php'; ?>
</body>
</html>
