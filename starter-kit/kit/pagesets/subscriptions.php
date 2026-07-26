<?php
/** PAGESET: subscriptions — pricing cards + CTA. */
$PAGE = $PAGE ?? [];
$nav  = $PAGE['nav'] ?? 'subscriptions';
$plans = kit_subscriptions();
layout('public', ['title' => 'Abonamente — ' . kit_cfg('site_name'), 'nav' => $nav],
function () use ($plans) { ?>
  <section class="kit-section"><div class="kit-container">
    <div style="text-align:center;margin-bottom:2rem">
      <p class="kit-event-card__cat"><?= e(kit_cfg('site_name')) ?></p>
      <h1 class="kit-display" style="font-size:clamp(2rem,5vw,3rem)">Abonamente</h1>
    </div>
    <?php if ($plans): ?>
      <div class="kit-grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));align-items:start">
        <?php foreach ($plans as $p) component('subscription-card', ['plan' => [
            'name' => $p['name'] ?? '', 'price' => $p['price'] ?? null, 'currency' => $p['currency'] ?? 'RON',
            'subtitle' => $p['subtitle'] ?? '', 'is_featured' => !empty($p['is_featured']),
            'benefits' => $p['benefits'] ?? [], 'cta_label' => 'Abonează-te', 'cta_url' => '/abonament?plan=' . ($p['id'] ?? ''),
        ]]); ?>
      </div>
    <?php else: component('empty-state', ['message' => 'Momentan nu sunt abonamente disponibile.']); endif; ?>
  </div></section>
  <?php component('cta', ['title' => 'Ai întrebări despre abonamente?', 'text' => 'Echipa noastră îți răspunde rapid.', 'action' => ['label' => 'Contact', 'url' => '/contact']]); ?>
<?php });
