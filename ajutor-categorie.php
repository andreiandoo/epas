<?php
/**
 * KB Category Page - Categorie Articole
 * Ambilet Marketplace
 */
require_once 'includes/config.php';
require_once 'includes/api.php';

// Get category slug from URL
$categorySlug = $_GET['slug'] ?? '';

if (empty($categorySlug)) {
    header('Location: /ajutor');
    exit;
}

// Fetch category with articles from API
$categoryResponse = api_get('/kb/categories/' . urlencode($categorySlug));

if (!$categoryResponse['success'] || empty($categoryResponse['data']['category'])) {
    // Category not found - redirect to help center
    header('Location: /ajutor');
    exit;
}

$category = $categoryResponse['data']['category'];
$articles = $categoryResponse['data']['articles'] ?? [];

$pageTitle = htmlspecialchars($category['name']) . ' — Centru de Ajutor — ' . SITE_NAME;
$pageDescription = htmlspecialchars($category['description'] ?? 'Articole și ghiduri despre ' . $category['name']);

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
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <?php include 'includes/head.php'; ?>
</head>
<body class="min-h-screen font-body bg-surface text-secondary">
    <?php include 'includes/header.php'; ?>

    <!-- Breadcrumb & Header -->
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
                <span class="text-secondary font-medium"><?= htmlspecialchars($category['name']) ?></span>
            </nav>

            <!-- Category Header -->
            <div class="flex items-start gap-5">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center flex-shrink-0 <?= getColorClasses($category['color'] ?? '#6B7280') ?>">
                    <?= getIconSvg($category['icon'] ?? 'heroicon-o-document-text') ?>
                </div>
                <div>
                    <h1 class="mb-2 text-3xl font-bold text-secondary"><?= htmlspecialchars($category['name']) ?></h1>
                    <?php if (!empty($category['description'])): ?>
                    <p class="text-gray-500"><?= htmlspecialchars($category['description']) ?></p>
                    <?php endif; ?>
                    <p class="mt-2 text-sm text-gray-400"><?= count($articles) ?> articole</p>
                </div>
            </div>
        </div>
    </section>

    <main class="max-w-4xl px-6 py-10 mx-auto">
        <?php if (empty($articles)): ?>
        <!-- No Articles -->
        <div class="p-12 text-center bg-white border border-gray-200 rounded-2xl">
            <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 text-gray-400 bg-gray-100 rounded-2xl">
                <svg class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
            </div>
            <h3 class="mb-2 text-lg font-semibold text-secondary">Niciun articol inca</h3>
            <p class="mb-6 text-gray-500">Aceasta categorie nu are inca articole publicate.</p>
            <a href="/ajutor" class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-white transition-all bg-primary rounded-xl hover:bg-primary-dark">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Inapoi la Centrul de Ajutor
            </a>
        </div>
        <?php else: ?>
        <!-- Articles List -->
        <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl">
            <?php foreach ($articles as $index => $article): ?>
            <a href="/ajutor/articol/<?= htmlspecialchars($article['slug']) ?>" class="flex items-center justify-between px-6 py-5 <?= $index < count($articles) - 1 ? 'border-b border-gray-200' : '' ?> hover:bg-gray-50 group transition-colors">
                <div class="flex items-center gap-4">
                    <div class="flex items-center justify-center flex-shrink-0 text-gray-500 bg-gray-100 w-11 h-11 rounded-xl">
                        <?= getIconSvg($article['icon'] ?? ($article['type'] === 'faq' ? 'heroicon-o-question-mark-circle' : 'heroicon-o-document-text'), 'w-5 h-5') ?>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-secondary mb-0.5">
                            <?= htmlspecialchars($article['type'] === 'faq' ? ($article['question'] ?? '') : ($article['title'] ?? '')) ?>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-400">
                            <?php if ($article['type'] === 'faq'): ?>
                            <span class="px-2 py-0.5 bg-purple-100 text-purple-600 rounded-full font-medium">FAQ</span>
                            <?php endif; ?>
                            <?php if (!empty($article['view_count'])): ?>
                            <span><?= number_format($article['view_count']) ?> vizualizari</span>
                            <?php endif; ?>
                            <?php if (!empty($article['updated_at'])): ?>
                            <span>Actualizat <?= date('d M Y', strtotime($article['updated_at'])) ?></span>
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
