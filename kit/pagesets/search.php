<?php
/** PAGESET: search — results for ?q= via kit_events(['q'=>…]). */
$q = trim((string)($_GET['q'] ?? ''));
$events = $q !== '' ? kit_events(['q' => $q, 'per_page' => 24]) : [];
layout('public', ['title' => t('search.title') . ' — ' . kit_cfg('site_name'), 'nav' => 'search', 'noindex' => true],
function () use ($q, $events) { ?>
  <section class="kit-section"><div class="kit-container">
    <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin-bottom:1.25rem"><?= e(t('search.title')) ?></h1>
    <?php component('search-bar', ['action' => '/cauta', 'value' => $q, 'placeholder' => t('search.prompt')]); ?>
    <?php if ($q !== ''): ?>
      <h2 class="kit-display" style="font-size:1.2rem;margin:1.5rem 0 1rem"><?= e(t('search.results', ['q' => $q])) ?></h2>
      <?php component('event-grid', ['events' => $events, 'empty' => t('search.none')]); ?>
    <?php endif; ?>
  </div></section>
<?php });
