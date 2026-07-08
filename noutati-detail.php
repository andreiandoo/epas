<?php
/**
 * Noutăți (single) — Detail page for one changelog entry.
 *
 * SSR fetches the update server-side so <title>, meta description,
 * OG tags and the hero image (LCP) reflect the real content BEFORE
 * JS runs — critical for Google + social crawlers that don't execute JS.
 * The body itself is also rendered by PHP so pressing "Vezi sursa" or
 * loading with JS disabled still shows content. JS only enhances
 * (reactions, share, reading progress, sticky sub-nav).
 */

$pageCacheTTL = 900; // 15 minutes
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// Resolve slug from query or REQUEST_URI (mirrors artist-single.php pattern).
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/noutati/([a-z0-9-]+)#i', $uri, $m)) {
        $slug = $m[1];
    }
}

if (empty($slug)) {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
    exit;
}

// Server-side fetch — cached 5 min. Also gives us related + prev/next
// + initial reaction counts in one round-trip.
$updateData = api_cached('system_update_' . $slug, function () use ($slug) {
    return api_get('/system-updates/' . urlencode($slug));
}, 300);

if (empty($updateData['success']) || empty($updateData['data']['update'])) {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
    exit;
}

$update  = $updateData['data']['update'];
$related = $updateData['data']['related'] ?? [];
$prev    = $updateData['data']['prev'] ?? null;
$next    = $updateData['data']['next'] ?? null;
$reactionCounts = $updateData['data']['reaction_counts'] ?? [
    'thumbs_up' => 0, 'heart' => 0, 'rocket' => 0, 'party' => 0,
];

// SEO overrides fall back to auto-generated versions.
$pageTitle       = (!empty($update['meta_title']) ? $update['meta_title'] : $update['title']) . ' | ' . SITE_NAME;
$pageDescription = !empty($update['meta_description'])
    ? $update['meta_description']
    : (!empty($update['excerpt']) ? $update['excerpt'] : ('Update ' . SITE_NAME . ': ' . $update['title']));
if (!empty($update['featured_image'])) {
    $pageImage = $update['featured_image'];
}

// Category rendered as a sentence with icon instead of a coloured pill —
// user feedback: pill was noisy, sentence reads more editorial.
$categoryPhrase = match ($update['category']) {
    'interfata'   => 'Îmbunătățire de Interfață',
    'organizator' => 'Feature pentru Organizatori',
    'client'      => 'Feature pentru Clienți',
    default       => ucfirst((string) $update['category']),
};
$categoryIcon = match ($update['category']) {
    'interfata'   => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
    'organizator' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>',
    'client'      => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
    default       => '',
};

// Reading time — rough estimate at 200 words/minute of Romanian prose.
$bodyText = strip_tags((string) ($update['body'] ?? ''));
$wordCount = str_word_count($bodyText, 0, 'ăâîșțĂÂÎȘȚ');
$readingMinutes = max(1, (int) round($wordCount / 200));

$bodyClass = 'page-noutati-detail';
$cssBundle = 'single';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Reading progress bar (fixed under header) -->
<div id="noutati-progress" class="fixed left-0 right-0 z-40 h-1 bg-slate-100" style="top: 4rem;">
    <div id="noutati-progress-fill" class="h-full bg-gradient-to-r from-primary to-red-600 transition-all duration-100" style="width: 0%"></div>
</div>

<!-- Sticky sub-nav that appears on scroll (compact "back + title") -->
<div id="noutati-subnav"
     class="fixed left-0 right-0 z-30 bg-white/95 backdrop-blur-md border-b border-slate-200 shadow-sm opacity-0 -translate-y-full transition-all duration-300 pointer-events-none"
     style="top: 4rem;">
    <div class="max-w-6xl mx-auto px-6 md:px-12 py-3 flex items-center gap-4">
        <a href="/noutati" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-600 hover:text-primary transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span class="hidden sm:inline">Noutăți</span>
        </a>
        <span class="text-slate-300">·</span>
        <span class="text-sm font-medium text-slate-700 truncate flex-1"><?= htmlspecialchars($update['title']) ?></span>
        <button id="noutati-share-toggle-mini" class="p-2 rounded-lg text-slate-500 hover:text-primary hover:bg-slate-100 transition-colors" aria-label="Distribuie">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
        </button>
    </div>
</div>

<!-- Hero band with breadcrumb + category + title + meta -->
<section class="relative bg-gradient-to-br from-slate-50 to-slate-100 border-b border-slate-200 overflow-hidden">
    <!-- Decorative gradient blob -->
    <div class="absolute -top-[200px] -right-[200px] w-[600px] h-[600px] bg-[radial-gradient(circle,rgba(165,28,48,0.06)_0%,transparent_65%)] pointer-events-none"></div>

    <div class="relative max-w-4xl mx-auto px-6 md:px-12 pt-8 pb-16 md:pt-12 md:pb-20">
        <!-- Breadcrumb -->
        <nav class="mb-6 text-sm">
            <a href="/" class="text-slate-500 hover:text-primary transition-colors">Acasă</a>
            <svg class="inline w-3 h-3 mx-1.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <a href="/noutati" class="text-slate-500 hover:text-primary transition-colors">Noutăți</a>
            <svg class="inline w-3 h-3 mx-1.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-slate-700 font-medium">Update</span>
        </nav>

        <!-- Category sentence (icon + text) -->
        <div class="inline-flex items-center gap-2 mb-5 text-primary">
            <?= $categoryIcon ?>
            <span class="text-sm font-bold uppercase tracking-wider"><?= htmlspecialchars($categoryPhrase) ?></span>
        </div>

        <!-- Title -->
        <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-slate-900 leading-[1.1] mb-6 tracking-tight">
            <?= htmlspecialchars($update['title']) ?>
        </h1>

        <!-- Excerpt as lede -->
        <?php if (!empty($update['excerpt'])): ?>
        <p class="text-lg md:text-xl text-slate-600 leading-relaxed mb-8 max-w-3xl">
            <?= htmlspecialchars($update['excerpt']) ?>
        </p>
        <?php endif; ?>

        <!-- Meta row: date, reading time, share -->
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <?php if (!empty($update['published_at_human'])): ?>
                <div class="inline-flex items-center gap-2 text-slate-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <time datetime="<?= htmlspecialchars($update['published_at']) ?>">
                        <?= htmlspecialchars($update['published_at_human']) ?>
                    </time>
                </div>
                <span class="text-slate-300">·</span>
            <?php endif; ?>
            <div class="inline-flex items-center gap-2 text-slate-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span><?= $readingMinutes ?> min citit</span>
            </div>
            <span class="text-slate-300">·</span>
            <!-- Share dropdown -->
            <div class="relative">
                <button id="noutati-share-toggle"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-slate-600 hover:text-primary hover:bg-white border border-transparent hover:border-slate-200 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    <span>Distribuie</span>
                </button>
                <div id="noutati-share-menu" class="hidden absolute left-0 top-full mt-2 w-56 bg-white border border-slate-200 rounded-xl shadow-xl overflow-hidden z-20">
                    <a href="#" data-share="facebook" class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-slate-50 transition-colors">
                        <svg class="w-4 h-4 text-[#1877F2]" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg>
                        Facebook
                    </a>
                    <a href="#" data-share="whatsapp" class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-slate-50 transition-colors">
                        <svg class="w-4 h-4 text-[#25D366]" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        WhatsApp
                    </a>
                    <a href="#" data-share="email" class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-slate-50 transition-colors">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Email
                    </a>
                    <a href="#" data-share="copy" class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-slate-50 transition-colors border-t border-slate-100">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span id="noutati-copy-label">Copiază link</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<article class="max-w-4xl mx-auto px-6 md:px-12 py-10 md:py-16">
    <!-- Hero image (if any) — pulled out of the intro band so it sits ABOVE
         the body but visually separates the metadata band from the article. -->
    <?php if (!empty($update['featured_image'])): ?>
    <figure class="mb-12 -mx-6 md:mx-0">
        <img src="<?= htmlspecialchars($update['featured_image']) ?>"
             alt="<?= htmlspecialchars($update['title']) ?>"
             class="w-full aspect-video object-cover md:rounded-3xl shadow-xl"
             fetchpriority="high"
             width="1200" height="675">
    </figure>
    <?php endif; ?>

    <!-- Body — already sanitized server-side via HTMLPurifier profile
         "system_update", so echoing raw is safe.
         NOTE: Tailwind Typography's `prose` classes aren't part of the
         ambilet build, so styling is done via the scoped stylesheet
         below instead of prose-* utilities. -->
    <div id="noutati-body">
        <?= $update['body'] ?? '' ?>
    </div>

    <!-- Reactions row -->
    <div class="mt-14 pt-8 border-t border-slate-100">
        <div class="text-center mb-5">
            <h3 class="text-lg font-bold text-slate-700 mb-1">Ce părere ai despre acest update?</h3>
            <p class="text-sm text-slate-500">Apasă pe o reacție să ne spui.</p>
        </div>
        <div id="noutati-reactions"
             class="flex flex-wrap justify-center gap-3"
             data-slug="<?= htmlspecialchars($update['slug']) ?>">
            <?php
            $reactionButtons = [
                'thumbs_up' => ['emoji' => '👍', 'label' => 'Util'],
                'heart'     => ['emoji' => '❤️', 'label' => 'Îmi place'],
                'rocket'    => ['emoji' => '🚀', 'label' => 'Wow'],
                'party'     => ['emoji' => '🎉', 'label' => 'În sfârșit!'],
            ];
            foreach ($reactionButtons as $type => $meta):
                $count = $reactionCounts[$type] ?? 0;
            ?>
            <button type="button"
                    class="noutati-reaction group inline-flex items-center gap-2 px-5 py-3 rounded-2xl bg-white border-2 border-slate-200 hover:border-primary hover:shadow-lg hover:-translate-y-0.5 transition-all"
                    data-type="<?= $type ?>"
                    data-count="<?= $count ?>">
                <span class="text-2xl transform group-hover:scale-125 transition-transform"><?= $meta['emoji'] ?></span>
                <div class="text-left leading-tight">
                    <div class="text-xs font-semibold text-slate-600 group-hover:text-primary transition-colors"><?= $meta['label'] ?></div>
                    <div class="reaction-count text-sm font-black text-slate-800"><?= $count ?></div>
                </div>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Prev / Next navigation -->
    <?php if ($prev || $next): ?>
    <nav class="mt-14 pt-8 border-t border-slate-100 grid md:grid-cols-2 gap-4">
        <?php if ($prev): ?>
        <a href="<?= htmlspecialchars($prev['url']) ?>"
           class="group block p-5 rounded-2xl border border-slate-200 bg-white hover:border-primary hover:shadow-lg transition-all">
            <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                Update precedent
            </div>
            <div class="font-bold text-slate-800 group-hover:text-primary transition-colors line-clamp-2">
                <?= htmlspecialchars($prev['title']) ?>
            </div>
        </a>
        <?php else: ?><div></div><?php endif; ?>

        <?php if ($next): ?>
        <a href="<?= htmlspecialchars($next['url']) ?>"
           class="group block p-5 rounded-2xl border border-slate-200 bg-white hover:border-primary hover:shadow-lg transition-all text-right">
            <div class="flex items-center justify-end gap-1.5 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                Update următor
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </div>
            <div class="font-bold text-slate-800 group-hover:text-primary transition-colors line-clamp-2">
                <?= htmlspecialchars($next['title']) ?>
            </div>
        </a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

    <!-- Back link -->
    <div class="mt-12 text-center">
        <a href="/noutati" class="inline-flex items-center gap-2 px-6 py-3 rounded-full bg-slate-900 text-white font-semibold hover:bg-primary hover:shadow-lg transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Înapoi la toate noutățile
        </a>
    </div>
</article>

<?php if (!empty($related)): ?>
<!-- Related updates -->
<section class="bg-slate-50 border-t border-slate-100">
    <div class="max-w-6xl mx-auto px-6 md:px-12 py-12 md:py-16">
        <h2 class="text-2xl md:text-3xl font-black text-slate-800 mb-8">Continuă să citești</h2>
        <div class="grid md:grid-cols-3 gap-6">
            <?php foreach ($related as $r):
                $rPhrase = match ($r['category']) {
                    'interfata'   => 'Interfață',
                    'organizator' => 'Pentru Organizatori',
                    'client'      => 'Pentru Clienți',
                    default       => ucfirst((string) $r['category']),
                };
            ?>
            <a href="<?= htmlspecialchars($r['url']) ?>"
               class="group bg-white rounded-2xl overflow-hidden border border-slate-200 hover:shadow-xl hover:-translate-y-1 transition-all block">
                <?php if (!empty($r['featured_image'])): ?>
                <div class="aspect-video overflow-hidden bg-slate-100">
                    <img src="<?= htmlspecialchars($r['featured_image']) ?>"
                         alt="<?= htmlspecialchars($r['title']) ?>"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                         loading="lazy"
                         width="600" height="338">
                </div>
                <?php else: ?>
                <div class="aspect-video bg-gradient-to-br from-slate-100 via-slate-50 to-slate-100"></div>
                <?php endif; ?>
                <div class="p-5">
                    <div class="text-[11px] font-bold text-primary uppercase tracking-wider mb-2">
                        <?= htmlspecialchars($rPhrase) ?>
                    </div>
                    <h3 class="font-bold text-slate-800 leading-snug group-hover:text-primary transition-colors line-clamp-2 mb-2">
                        <?= htmlspecialchars($r['title']) ?>
                    </h3>
                    <?php if (!empty($r['excerpt'])): ?>
                    <p class="text-sm text-slate-500 line-clamp-2">
                        <?= htmlspecialchars($r['excerpt']) ?>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($r['published_at_human'])): ?>
                    <div class="mt-3 text-xs text-slate-400">
                        <?= htmlspecialchars($r['published_at_human']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<style>
    /* Scoped WYSIWYG typography for the changelog body. Kept inline
       to guarantee it ships with the page — the ambilet Tailwind
       build has no @tailwindcss/typography plugin, so `prose`
       classes would be no-ops. */
    #noutati-body {
        color: #334155;              /* slate-700 */
        font-size: 1.0625rem;         /* ~17px */
        line-height: 1.75;
    }
    #noutati-body > * + * { margin-top: 1.25em; }
    #noutati-body h1, #noutati-body h2, #noutati-body h3,
    #noutati-body h4, #noutati-body h5, #noutati-body h6 {
        color: #1e293b;              /* slate-800 */
        font-weight: 800;
        line-height: 1.25;
        margin-top: 2em;
        margin-bottom: 0.6em;
        letter-spacing: -0.01em;
    }
    #noutati-body h1 { font-size: 2.25rem; }
    #noutati-body h2 { font-size: 1.875rem; }
    #noutati-body h3 { font-size: 1.5rem; }
    #noutati-body h4 { font-size: 1.25rem; }
    #noutati-body h5 { font-size: 1.125rem; }
    #noutati-body h6 { font-size: 1rem; text-transform: uppercase; letter-spacing: 0.05em; }
    #noutati-body p { margin: 0 0 1em 0; }
    #noutati-body a {
        color: var(--primary, #A51C30);
        font-weight: 600;
        text-decoration: underline;
        text-underline-offset: 3px;
        text-decoration-thickness: 1px;
    }
    #noutati-body a:hover {
        color: var(--primary-dark, #8B1728);
        text-decoration-thickness: 2px;
    }
    #noutati-body strong, #noutati-body b { font-weight: 700; color: #1e293b; }
    #noutati-body em, #noutati-body i { font-style: italic; }
    #noutati-body u { text-decoration: underline; }
    #noutati-body s { text-decoration: line-through; opacity: 0.7; }
    #noutati-body ul, #noutati-body ol { margin: 1em 0; padding-left: 1.75rem; }
    #noutati-body ul { list-style: disc; }
    #noutati-body ol { list-style: decimal; }
    #noutati-body ul ul { list-style: circle; }
    #noutati-body ul ul ul { list-style: square; }
    #noutati-body li { margin-bottom: 0.5em; }
    #noutati-body li > p { margin-bottom: 0.25em; }
    #noutati-body blockquote {
        margin: 1.5em 0;
        padding: 0.75em 1.25em;
        border-left: 4px solid var(--primary, #A51C30);
        background: #f8fafc;
        color: #475569;
        font-style: italic;
        border-radius: 0 0.5rem 0.5rem 0;
    }
    #noutati-body blockquote p { margin-bottom: 0.5em; }
    #noutati-body blockquote p:last-child { margin-bottom: 0; }
    #noutati-body pre {
        margin: 1.5em 0;
        padding: 1rem 1.25rem;
        background: #0f172a;
        color: #e2e8f0;
        border-radius: 0.75rem;
        overflow-x: auto;
        font-family: 'Fira Code', ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
        font-size: 0.9em;
        line-height: 1.6;
    }
    #noutati-body pre code { background: none; padding: 0; color: inherit; }
    #noutati-body code {
        background: #f1f5f9;
        color: #dc2626;
        padding: 0.15rem 0.4rem;
        border-radius: 0.375rem;
        font-family: 'Fira Code', ui-monospace, SFMono-Regular, Menlo, Monaco, monospace;
        font-size: 0.9em;
    }
    #noutati-body hr { margin: 2.5em 0; border: 0; border-top: 1px solid #e2e8f0; }
    #noutati-body img {
        max-width: 100%;
        height: auto;
        border-radius: 1rem;
        margin: 1.5em auto;
        display: block;
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }
    #noutati-body iframe {
        max-width: 100%;
        border-radius: 1rem;
        margin: 1.5em auto;
        display: block;
        aspect-ratio: 16 / 9;
        width: 100%;
        height: auto;
        border: 0;
    }
    #noutati-body figure { margin: 1.5em 0; }
    #noutati-body figcaption {
        text-align: center;
        color: #64748b;
        font-size: 0.875rem;
        margin-top: 0.5rem;
        font-style: italic;
    }
    #noutati-body table {
        width: 100%;
        border-collapse: collapse;
        margin: 1.5em 0;
        font-size: 0.9375rem;
    }
    #noutati-body th, #noutati-body td {
        border: 1px solid #e2e8f0;
        padding: 0.5rem 0.75rem;
        text-align: left;
    }
    #noutati-body th { background: #f1f5f9; font-weight: 700; color: #1e293b; }

    /* Reaction "active" state — the visitor's already-clicked reaction. */
    .noutati-reaction.is-active {
        border-color: var(--primary, #A51C30);
        background: linear-gradient(135deg, rgba(165,28,48,0.06), rgba(220,38,38,0.03));
        box-shadow: 0 4px 12px rgba(165, 28, 48, 0.15);
    }
    .noutati-reaction.is-active .reaction-count {
        color: var(--primary, #A51C30);
    }
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
$scriptsExtra = ($scriptsExtra ?? '')
    . '<script defer src="' . asset('assets/js/pages/noutati-detail.js') . '"></script>';
require_once __DIR__ . '/includes/scripts.php';
?>
