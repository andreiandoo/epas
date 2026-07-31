<?php
/**
 * PAGESET: page — a CMS page (terms, privacy, custom) from the API.
 * $PAGE['slug'] selects which page; $PAGE['nav'] highlights a menu item.
 * Falls back to an empty-state if the page isn't published yet.
 */
$PAGE = $PAGE ?? [];
$slug = $PAGE['slug'] ?? 'page';
$page = kit_page($slug);

layout('public', [
    'title' => ($page['title'] ?? ucfirst($slug)) . ' — ' . kit_cfg('site_name'),
    'nav'   => $PAGE['nav'] ?? '',
    'description' => $page['description'] ?? null,
    'og_type' => 'article',
], function () use ($page, $slug) { ?>
  <section class="kit-section"><div class="kit-container" style="max-width:48rem">
    <?php if ($page && ($page['html'] || $page['title'])): ?>
      <h1 class="kit-display" style="font-size:clamp(2rem,5vw,3rem);margin-bottom:1.5rem"><?= e($page['title']) ?></h1>
      <div class="kit-prose"><?= kit_html($page['html']) ?></div>
    <?php else: ?>
      <?php component('empty-state', ['icon' => '📄', 'message' => 'Această pagină nu este disponibilă momentan.', 'action' => ['label' => t('common.home'), 'url' => '/']]); ?>
    <?php endif; ?>
  </div></section>
<?php });
