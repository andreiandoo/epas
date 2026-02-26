<?php
/**
 * KB Article Page - Articol Individual
 * Ambilet Marketplace
 */
require_once 'includes/config.php';
require_once 'includes/api.php';

// Get article slug from URL
$articleSlug = $_GET['slug'] ?? '';

if (empty($articleSlug)) {
    header('Location: /ajutor');
    exit;
}

// Fetch article from API
$articleResponse = api_get('/kb/articles/' . urlencode($articleSlug));

if (!$articleResponse['success'] || empty($articleResponse['data']['article'])) {
    // Article not found - redirect to help center
    header('Location: /ajutor');
    exit;
}

$article = $articleResponse['data']['article'];
$category = $article['category'] ?? null;

// Record article view (fire-and-forget)
if (!empty($article['id'])) {
    record_article_view($article['id']);
}

// Fetch related articles from same category
$relatedArticles = [];
if ($category && !empty($category['slug'])) {
    $categoryResponse = api_get('/kb/categories/' . urlencode($category['slug']));
    if ($categoryResponse['success'] && !empty($categoryResponse['data']['articles'])) {
        // Filter out current article and limit to 5
        $relatedArticles = array_filter($categoryResponse['data']['articles'], function($a) use ($article) {
            return $a['id'] !== $article['id'];
        });
        $relatedArticles = array_slice($relatedArticles, 0, 5);
    }
}

// Determine title/question based on type
$isFaq = $article['type'] === 'faq';
$articleTitle = $isFaq ? ($article['question'] ?? 'FAQ') : ($article['title'] ?? 'Articol');
$articleContent = $isFaq ? ($article['answer'] ?? '') : ($article['content'] ?? '');

$pageTitle = htmlspecialchars($articleTitle) . ' — Centru de Ajutor — ' . SITE_NAME;
$pageDescription = $article['meta_description'] ?? mb_substr(strip_tags($articleContent), 0, 160);

// Helper function to get icon SVG
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

// Helper function to generate color classes
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
    <style>
        .article-content h1 { font-size: 1.875rem; font-weight: 700; margin-bottom: 1rem; margin-top: 2rem; }
        .article-content h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem; margin-top: 1.5rem; }
        .article-content h3 { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.5rem; margin-top: 1.25rem; }
        .article-content p { margin-bottom: 1rem; line-height: 1.75; }
        .article-content ul, .article-content ol { margin-bottom: 1rem; padding-left: 1.5rem; }
        .article-content li { margin-bottom: 0.5rem; line-height: 1.75; }
        .article-content ul { list-style-type: disc; }
        .article-content ol { list-style-type: decimal; }
        .article-content a { color: #A51C30; text-decoration: underline; }
        .article-content a:hover { color: #8B1728; }
        .article-content blockquote { border-left: 4px solid #A51C30; padding-left: 1rem; margin: 1rem 0; font-style: italic; color: #64748B; }
        .article-content code { background: #F1F5F9; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.875rem; }
        .article-content pre { background: #1E293B; color: #E2E8F0; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; margin: 1rem 0; }
        .article-content pre code { background: none; padding: 0; }
        .article-content img { max-width: 100%; height: auto; border-radius: 0.5rem; margin: 1rem 0; }
        .article-content table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        .article-content th, .article-content td { border: 1px solid #E2E8F0; padding: 0.75rem; text-align: left; }
        .article-content th { background: #F8FAFC; font-weight: 600; }
    </style>
</head>
<body class="min-h-screen font-body bg-surface text-secondary">
    <?php include 'includes/header.php'; ?>

    <!-- Breadcrumb & Header -->
    <section class="px-6 py-8 bg-white border-b border-gray-200 mt-18 mobile:mt-18">
        <div class="max-w-4xl mx-auto">
            <!-- Breadcrumb -->
            <nav class="flex flex-wrap items-center gap-2 mb-6 text-sm text-gray-500">
                <a href="/" class="hover:text-primary">Acasa</a>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
                <a href="/ajutor" class="hover:text-primary">Centru de Ajutor</a>
                <?php if ($category): ?>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
                <a href="/ajutor/<?= htmlspecialchars($category['slug']) ?>" class="hover:text-primary"><?= htmlspecialchars($category['name']) ?></a>
                <?php endif; ?>
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
                <span class="font-medium text-secondary"><?= htmlspecialchars(mb_substr($articleTitle, 0, 40)) ?><?= mb_strlen($articleTitle) > 40 ? '...' : '' ?></span>
            </nav>

            <!-- Article Header -->
            <div class="flex items-start gap-5">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center flex-shrink-0 <?= $isFaq ? 'bg-gradient-to-br from-purple-100 to-purple-200 text-purple-600' : 'bg-gradient-to-br from-gray-100 to-gray-200 text-gray-600' ?>">
                    <?= getIconSvg($article['icon'] ?? ($isFaq ? 'heroicon-o-question-mark-circle' : 'heroicon-o-document-text'), 'w-6 h-6') ?>
                </div>
                <div class="flex-1">
                    <?php if ($isFaq): ?>
                    <span class="inline-block px-2 py-0.5 mb-2 text-xs font-medium text-purple-600 bg-purple-100 rounded-full">FAQ</span>
                    <?php endif; ?>
                    <h1 class="text-2xl font-bold text-secondary md:text-3xl"><?= htmlspecialchars($articleTitle) ?></h1>
                    <div class="flex flex-wrap items-center gap-4 mt-3 text-sm text-gray-400">
                        <?php if ($category): ?>
                        <a href="/ajutor/<?= htmlspecialchars($category['slug']) ?>" class="flex items-center gap-1.5 hover:text-primary transition-colors">
                            <div class="w-5 h-5 rounded flex items-center justify-center <?= getColorClasses($category['color'] ?? '#6B7280') ?>">
                                <?= getIconSvg($category['icon'] ?? 'heroicon-o-document-text', 'w-3 h-3') ?>
                            </div>
                            <?= htmlspecialchars($category['name']) ?>
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($article['view_count'])): ?>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <?= number_format($article['view_count']) ?> vizualizari
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($article['updated_at'])): ?>
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            Actualizat <?= date('d M Y', strtotime($article['updated_at'])) ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <main class="max-w-4xl px-6 py-10 mx-auto">
        <!-- Article Content -->
        <div class="p-8 mb-8 bg-white border border-gray-200 rounded-2xl md:p-10">
            <div class="article-content">
                <?= $articleContent ?>
            </div>

            <?php if (!empty($article['tags'])): ?>
            <!-- Tags -->
            <div class="flex flex-wrap gap-2 pt-6 mt-8 border-t border-gray-200">
                <?php foreach ($article['tags'] as $tag): ?>
                <span class="px-3 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded-full"><?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Helpfulness Vote -->
        <div class="p-8 mb-8 text-center bg-white border border-gray-200 rounded-2xl">
            <p class="mb-4 text-lg font-semibold text-secondary">A fost util acest articol?</p>
            <div class="flex items-center justify-center gap-4">
                <button onclick="voteArticle(<?= $article['id'] ?>, true)" class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-green-600 transition-all bg-green-100 rounded-xl hover:bg-green-200" id="btn-helpful">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>
                    </svg>
                    Da, m-a ajutat
                </button>
                <button onclick="voteArticle(<?= $article['id'] ?>, false)" class="inline-flex items-center gap-2 px-6 py-3 text-sm font-semibold text-red-600 transition-all bg-red-100 rounded-xl hover:bg-red-200" id="btn-not-helpful">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"/>
                    </svg>
                    Nu, nu m-a ajutat
                </button>
            </div>
            <div id="vote-result" class="hidden mt-4 text-sm text-gray-500"></div>
        </div>

        <?php if (!empty($relatedArticles)): ?>
        <!-- Related Articles from Same Category -->
        <div class="p-8 mb-8 bg-white border border-gray-200 rounded-2xl">
            <h3 class="flex items-center gap-2 mb-5 text-lg font-semibold text-secondary">
                <svg class="w-5 h-5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
                Alte articole din <?= htmlspecialchars($category['name']) ?>
            </h3>
            <div class="space-y-3">
                <?php foreach ($relatedArticles as $related): ?>
                <?php
                    $relatedIsFaq = ($related['type'] ?? '') === 'faq';
                    $relatedTitle = $relatedIsFaq ? ($related['question'] ?? '') : ($related['title'] ?? '');
                ?>
                <a href="/ajutor/articol/<?= htmlspecialchars($related['slug']) ?>" class="flex items-center gap-3 p-3 -mx-3 transition-colors rounded-xl hover:bg-gray-50 group">
                    <div class="flex items-center justify-center flex-shrink-0 w-9 h-9 rounded-lg <?= $relatedIsFaq ? 'bg-purple-100 text-purple-600' : 'bg-gray-100 text-gray-500' ?>">
                        <?= getIconSvg($related['icon'] ?? ($relatedIsFaq ? 'heroicon-o-question-mark-circle' : 'heroicon-o-document-text'), 'w-4 h-4') ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="text-sm font-medium transition-colors text-secondary group-hover:text-primary line-clamp-1"><?= htmlspecialchars($relatedTitle) ?></span>
                        <?php if ($relatedIsFaq): ?>
                        <span class="ml-2 px-1.5 py-0.5 text-xs bg-purple-100 text-purple-600 rounded font-medium">FAQ</span>
                        <?php endif; ?>
                    </div>
                    <svg class="flex-shrink-0 w-4 h-4 text-gray-300 transition-transform group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </a>
                <?php endforeach; ?>
            </div>
            <a href="/ajutor/<?= htmlspecialchars($category['slug']) ?>" class="inline-flex items-center gap-2 mt-4 text-sm font-medium transition-colors text-primary hover:text-primary-dark">
                Vezi toate articolele din aceasta categorie
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
        <?php endif; ?>

        <!-- Navigation -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <?php if ($category): ?>
            <a href="/ajutor/<?= htmlspecialchars($category['slug']) ?>" class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 transition-colors hover:text-primary">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Inapoi la <?= htmlspecialchars($category['name']) ?>
            </a>
            <?php else: ?>
            <a href="/ajutor" class="inline-flex items-center gap-2 text-sm font-medium text-gray-500 transition-colors hover:text-primary">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
                Inapoi la Centrul de Ajutor
            </a>
            <?php endif; ?>

            <a href="/contact" class="inline-flex items-center gap-2 text-sm font-medium transition-colors text-primary hover:text-primary-dark">
                Mai ai intrebari? Contacteaza-ne
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    <?php include 'includes/scripts.php'; ?>

    <script>
    async function voteArticle(articleId, helpful) {
        const btnHelpful = document.getElementById('btn-helpful');
        const btnNotHelpful = document.getElementById('btn-not-helpful');
        const voteResult = document.getElementById('vote-result');

        // Disable buttons
        btnHelpful.disabled = true;
        btnNotHelpful.disabled = true;
        btnHelpful.classList.add('opacity-50', 'cursor-not-allowed');
        btnNotHelpful.classList.add('opacity-50', 'cursor-not-allowed');

        try {
            const response = await fetch(`<?= API_BASE_URL ?>/kb/articles/${articleId}/vote`, {
                method: 'POST',
                headers: {
                    'X-API-Key': '<?= API_KEY ?>',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ helpful: helpful })
            });

            const data = await response.json();

            if (data.success) {
                voteResult.textContent = 'Multumim pentru feedback!';
                voteResult.classList.remove('hidden', 'text-red-500');
                voteResult.classList.add('text-green-600');
            } else {
                throw new Error(data.message || 'Eroare la inregistrarea votului');
            }
        } catch (error) {
            voteResult.textContent = 'A aparut o eroare. Te rugam sa incerci din nou.';
            voteResult.classList.remove('hidden', 'text-green-600');
            voteResult.classList.add('text-red-500');

            // Re-enable buttons on error
            btnHelpful.disabled = false;
            btnNotHelpful.disabled = false;
            btnHelpful.classList.remove('opacity-50', 'cursor-not-allowed');
            btnNotHelpful.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }
    </script>
</body>
</html>
