<?php
/** PAGESET: post — a single blog article. Slug from the route capture. */
$slug = $_GET['slug'] ?? '';
$post = $slug ? kit_post($slug) : null;

if (!$post) {
    http_response_code(404);
    layout('public', ['title' => '404 — ' . kit_cfg('site_name')], function () {
        component('empty-state', ['icon' => '📰', 'message' => t('error.404.msg'), 'action' => ['label' => t('nav.blog'), 'url' => '/blog']]);
    });
    return;
}

layout('public', [
    'title' => $post['title'] . ' — ' . kit_cfg('site_name'),
    'nav' => 'blog', 'og_type' => 'article',
    'description' => $post['excerpt'] ?: null, 'image' => $post['image'] ?: null,
], function () use ($post) { ?>
  <article class="kit-section"><div class="kit-container" style="max-width:44rem">
    <?php component('breadcrumb', ['items' => [['label' => t('common.home'), 'url' => '/'], ['label' => t('nav.blog'), 'url' => '/blog'], ['label' => $post['title']]]]); ?>
    <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin:1rem 0 .5rem"><?= e($post['title']) ?></h1>
    <p class="kit-muted" style="font-size:.85rem;margin-bottom:1.5rem"><?= e(trim(($post['author'] ? $post['author'] . ' · ' : '') . $post['date'], ' ·')) ?></p>
    <?php if ($post['image']): ?><img src="<?= e($post['image']) ?>" alt="<?= e($post['title']) ?>" style="width:100%;border-radius:var(--kit-radius);margin-bottom:1.5rem"><?php endif; ?>
    <div class="kit-prose"><?= kit_html($post['html']) ?></div>
  </div></article>
<?php });
