<?php
/**
 * Help Center Page - Centru de Ajutor
 * Ambilet Marketplace
 */
require_once 'includes/config.php';
require_once 'includes/api.php';

$pageTitle = 'Centru de Ajutor — ' . SITE_NAME;
$transparentHeader = false;
$pageDescription = 'Găsește răspunsuri la întrebările tale despre bilete, plăți, cont și multe altele. Centrul de ajutor Ambilet.';

// Fetch KB categories from API
$categoriesResponse = api_get('/kb/categories');
$categories = $categoriesResponse['data']['categories'] ?? [];

// Fetch popular/featured articles
$popularResponse = api_get('/kb/articles/popular?limit=7');
$popularArticles = $popularResponse['data']['articles'] ?? [];

// Build popular topics from popular articles
$popularTopics = [];
foreach ($popularArticles as $article) {
    $popularTopics[] = $article['type'] === 'faq' ? $article['question'] : $article['title'];
}

// Fetch recent/featured articles
$featuredResponse = api_get('/kb/articles/featured?limit=5');
$recentArticles = $featuredResponse['data']['articles'] ?? [];

// Fetch contact info from marketplace settings
$contactInfo = get_contact_info();
$contactEmail = $contactInfo['email'] ?? SUPPORT_EMAIL;
$contactPhone = $contactInfo['phone'] ?? SUPPORT_PHONE;
$operatingHours = $contactInfo['operating_hours'] ?? 'L-V 9:00-18:00';

// Fallback to static data if API fails
if (empty($categories)) {
    $categories = [
        ['slug' => 'tickets', 'name' => 'Bilete', 'description' => 'Descărcare bilete, transferuri, coduri QR, acces la eveniment.', 'article_count' => 15, 'icon' => 'heroicon-o-ticket', 'color' => '#DC2626'],
        ['slug' => 'payments', 'name' => 'Plăți', 'description' => 'Metode de plată, facturi, probleme cu tranzacțiile.', 'article_count' => 12, 'icon' => 'heroicon-o-credit-card', 'color' => '#2563EB'],
        ['slug' => 'account', 'name' => 'Cont & Profil', 'description' => 'Setări cont, parolă, date personale, notificări.', 'article_count' => 10, 'icon' => 'heroicon-o-user', 'color' => '#059669'],
        ['slug' => 'events', 'name' => 'Evenimente', 'description' => 'Informații eveniment, locație, program, restricții.', 'article_count' => 8, 'icon' => 'heroicon-o-calendar', 'color' => '#D97706'],
        ['slug' => 'refunds', 'name' => 'Rambursări', 'description' => 'Politica de rambursare, cereri, timp de procesare.', 'article_count' => 7, 'icon' => 'heroicon-o-arrow-path', 'color' => '#7C3AED'],
        ['slug' => 'organizers', 'name' => 'Pentru Organizatori', 'description' => 'Creare eveniment, vânzări, dashboard, setări.', 'article_count' => 20, 'icon' => 'heroicon-o-user-group', 'color' => '#DB2777'],
    ];
}

if (empty($popularTopics)) {
    $popularTopics = [
        'Cum descarc biletul?',
        'Rambursare bilet',
        'Transfer bilet',
        'Resetare parolă',
        'Eveniment anulat',
        'Modificare date',
        'Plata nu a trecut'
    ];
}

// Helper function to get icon SVG based on heroicon name
function getIconSvg($iconName, $size = 'w-8 h-8') {
    $icons = [
        'heroicon-o-ticket' => '<svg class="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 3 3v1a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-1a3 3 0 0 1 0-6V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v1a3 3 0 0 1-3 3Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>',
        'heroicon-o-credit-card' => '<svg class="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
        'heroicon-o-user' => '<svg class="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'heroicon-o-calendar' => '<svg class="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'heroicon-o-arrow-path' => '<svg class="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
        'heroicon-o-user-group' => '<svg class="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'heroicon-o-document-text' => '<svg class="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        'heroicon-o-question-mark-circle' => '<svg class="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    ];
    return $icons[$iconName] ?? $icons['heroicon-o-document-text'];
}

// Helper function to generate color classes based on hex color
function getColorClasses($color) {
    // Map hex colors to Tailwind gradient classes
    $colorMap = [
        '#DC2626' => 'bg-gradient-to-br from-red-100 to-red-200 text-red-600',
        '#2563EB' => 'bg-gradient-to-br from-blue-100 to-blue-200 text-blue-600',
        '#059669' => 'bg-gradient-to-br from-emerald-100 to-emerald-200 text-emerald-600',
        '#D97706' => 'bg-gradient-to-br from-amber-100 to-amber-200 text-amber-600',
        '#7C3AED' => 'bg-gradient-to-br from-purple-100 to-purple-200 text-purple-600',
        '#DB2777' => 'bg-gradient-to-br from-pink-100 to-pink-200 text-pink-600',
    ];
    return $colorMap[$color] ?? 'bg-gradient-to-br from-gray-100 to-gray-200 text-gray-600';
}
$cssBundle = 'static';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <?php include 'includes/head.php'; ?>
</head>
<body class="min-h-screen font-body bg-surface text-secondary">
    <?php include 'includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="relative px-6 pt-32 pb-8 overflow-hidden text-center bg-gradient-to-br from-slate-800 to-slate-900">
        <div class="absolute rounded-full -top-24 -right-24 w-96 h-96 bg-primary/30 blur-3xl"></div>
        <div class="relative z-10 max-w-2xl mx-auto">
            <h1 class="mb-4 text-4xl font-extrabold text-white md:text-5xl">Cum te putem ajuta?</h1>
            <p class="mb-8 text-lg text-white/90">Caută în baza noastră de cunoștințe sau explorează categoriile de mai jos.</p>

            <!-- Search Box -->
            <div class="relative max-w-xl mx-auto">
                <svg class="absolute w-5 h-5 text-gray-400 -translate-y-1/2 left-5 top-1/2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                </svg>
                <input type="text" id="kb-search" class="w-full px-6 py-5 text-base placeholder-gray-400 bg-white shadow-xl pl-14 rounded-2xl text-secondary focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Caută articole, întrebări sau subiecte...">
                <div id="kb-search-results" class="absolute left-0 right-0 z-50 hidden mt-2 overflow-hidden bg-white shadow-xl rounded-2xl"></div>
            </div>
        </div>
    </section>

    <main class="max-w-6xl px-6 py-12 mx-auto">
        <!-- Popular Topics -->
        <?php if (!empty($popularTopics)): ?>
        <div class="relative z-10 p-8 mb-12 -mt-10 bg-white border border-gray-100 shadow-lg rounded-2xl">
            <div class="flex items-center gap-2 mb-5">
                <svg class="w-6 h-6 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                <h3 class="text-lg font-bold text-secondary">Subiecte populare</h3>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($popularTopics as $topic): ?>
                <a href="/ajutor/cautare?q=<?= urlencode($topic) ?>" class="px-5 py-2.5 bg-gray-50 border border-gray-200 rounded-full text-sm font-medium text-gray-600 hover:bg-primary hover:border-primary hover:text-white transition-all">
                    <?= htmlspecialchars($topic) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Categories Section -->
        <h2 class="flex items-center gap-3 mb-6 text-2xl font-bold text-secondary">
            <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
            </svg>
            Explorează categoriile
        </h2>

        <div class="grid grid-cols-1 gap-6 mb-12 md:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($categories as $category): ?>
            <a href="/ajutor/<?= htmlspecialchars($category['slug']) ?>" class="p-8 transition-all bg-white border border-gray-200 group rounded-2xl hover:-translate-y-1 hover:shadow-xl hover:border-primary">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-5 <?= getColorClasses($category['color'] ?? '#6B7280') ?>">
                    <?= getIconSvg($category['icon'] ?? 'heroicon-o-document-text') ?>
                </div>
                <h3 class="mb-2 text-xl font-bold text-secondary"><?= htmlspecialchars($category['name']) ?></h3>
                <p class="mb-4 text-sm leading-relaxed text-gray-500"><?= htmlspecialchars($category['description'] ?? '') ?></p>
                <span class="text-xs text-gray-400"><?= $category['article_count'] ?? 0 ?> articole</span>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Recent/Featured Articles Section -->
        <?php if (!empty($recentArticles)): ?>
        <h2 class="flex items-center gap-3 mb-6 text-2xl font-bold text-secondary">
            <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
            </svg>
            Articole recente
        </h2>

        <div class="mb-12 overflow-hidden bg-white border border-gray-200 rounded-2xl">
            <?php foreach ($recentArticles as $index => $article): ?>
            <a href="/ajutor/articol/<?= htmlspecialchars($article['slug']) ?>" class="flex items-center justify-between px-6 py-5 <?= $index < count($recentArticles) - 1 ? 'border-b border-gray-200' : '' ?> hover:bg-gray-50 group transition-colors">
                <div class="flex items-center gap-4">
                    <div class="flex items-center justify-center text-gray-500 bg-gray-100 w-11 h-11 rounded-xl">
                        <?= getIconSvg($article['icon'] ?? ($article['type'] === 'faq' ? 'heroicon-o-question-mark-circle' : 'heroicon-o-document-text'), 'w-5 h-5') ?>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-secondary mb-0.5">
                            <?= htmlspecialchars($article['type'] === 'faq' ? $article['question'] : $article['title']) ?>
                        </div>
                        <div class="text-xs text-gray-400">
                            <?= htmlspecialchars($article['category']['name'] ?? '') ?>
                            <?php if (!empty($article['updated_at'])): ?>
                            • Actualizat <?= date('d M Y', strtotime($article['updated_at'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="text-gray-300 transition-transform group-hover:translate-x-1">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Contact Section -->
        <h2 class="flex items-center gap-3 mb-6 text-2xl font-bold text-secondary">
            <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            Contactează-ne
        </h2>

        <div class="grid grid-cols-1 gap-6 mb-12 md:grid-cols-3">
            <!-- Chat -->
            <div class="p-8 text-center transition-all bg-white border border-gray-200 rounded-2xl hover:-translate-y-1 hover:shadow-xl">
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-5 text-white bg-gradient-to-br from-primary to-primary-dark rounded-2xl">
                    <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-bold text-secondary">Chat Live</h3>
                <p class="mb-4 text-sm text-gray-500">Vorbește cu un agent în timp real pentru asistență imediată.</p>
                <a href="#" class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-gray-600 transition-all bg-gray-100 rounded-xl hover:bg-primary hover:text-white">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                    Începe conversația
                </a>
                <p class="mt-3 text-xs text-gray-400">Disponibil: <?= htmlspecialchars($operatingHours) ?></p>
            </div>

            <!-- Email -->
            <div class="p-8 text-center transition-all bg-white border border-gray-200 rounded-2xl hover:-translate-y-1 hover:shadow-xl">
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-5 text-white bg-gradient-to-br from-primary to-primary-dark rounded-2xl">
                    <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-bold text-secondary">Email</h3>
                <p class="mb-4 text-sm text-gray-500">Trimite-ne un email și răspundem în maxim 24 de ore.</p>
                <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-gray-600 transition-all bg-gray-100 rounded-xl hover:bg-primary hover:text-white">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    <?= htmlspecialchars($contactEmail) ?>
                </a>
                <p class="mt-3 text-xs text-gray-400">Răspuns în 24h</p>
            </div>

            <!-- Phone -->
            <div class="p-8 text-center transition-all bg-white border border-gray-200 rounded-2xl hover:-translate-y-1 hover:shadow-xl">
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-5 text-white bg-gradient-to-br from-primary to-primary-dark rounded-2xl">
                    <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"/>
                    </svg>
                </div>
                <h3 class="mb-2 text-lg font-bold text-secondary">Telefon</h3>
                <p class="mb-4 text-sm text-gray-500">Sună-ne pentru probleme urgente sau întrebări complexe.</p>
                <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $contactPhone)) ?>" class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-gray-600 transition-all bg-gray-100 rounded-xl hover:bg-primary hover:text-white">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"/>
                    </svg>
                    <?= htmlspecialchars($contactPhone) ?>
                </a>
                <p class="mt-3 text-xs text-gray-400"><?= htmlspecialchars($operatingHours) ?></p>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="bg-gradient-to-br from-slate-800 to-slate-700 rounded-3xl p-12 grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-8 items-center relative overflow-hidden">
            <div class="absolute w-48 h-48 rounded-full -top-12 -right-12 bg-primary/30 blur-2xl"></div>
            <div class="relative z-10">
                <h2 class="mb-3 text-2xl font-bold text-white">Nu ai găsit răspunsul?</h2>
                <p class="text-white/90">Echipa noastră de suport este aici să te ajute cu orice întrebare ai avea.</p>
            </div>
            <a href="/contact" class="relative z-10 inline-flex items-center justify-center gap-2 px-8 py-4 bg-gradient-to-r from-primary to-primary-dark rounded-xl text-white font-semibold hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/40 transition-all">
                Trimite o cerere
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/scripts.php'; ?>

    <script>
    // Live search functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('kb-search');
        const searchResults = document.getElementById('kb-search-results');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length < 2) {
                searchResults.classList.add('hidden');
                return;
            }

            searchTimeout = setTimeout(async function() {
                try {
                    const response = await fetch(`<?= API_BASE_URL ?>/kb/articles/search?q=${encodeURIComponent(query)}`, {
                        headers: {
                            'X-API-Key': '<?= API_KEY ?>',
                            'Accept': 'application/json'
                        }
                    });
                    const data = await response.json();

                    if (data.success && data.data.results.length > 0) {
                        let html = '<div class="p-2">';
                        data.data.results.forEach(function(article) {
                            const title = article.type === 'faq' ? article.question : article.title;
                            const category = article.category ? article.category.name : '';
                            html += `
                                <a href="/ajutor/articol/${article.slug}" class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-gray-50 transition-colors">
                                    <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                                        ${article.type === 'faq' ? '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>' : '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>'}
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-secondary">${title}</div>
                                        <div class="text-xs text-gray-400">${category}</div>
                                    </div>
                                </a>
                            `;
                        });
                        html += '</div>';
                        searchResults.innerHTML = html;
                        searchResults.classList.remove('hidden');
                    } else {
                        searchResults.innerHTML = '<div class="p-4 text-center text-gray-500 text-sm">Nu am găsit rezultate</div>';
                        searchResults.classList.remove('hidden');
                    }
                } catch (error) {
                    console.error('Search error:', error);
                }
            }, 300);
        });

        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.add('hidden');
            }
        });
    });
    </script>
</body>
</html>
