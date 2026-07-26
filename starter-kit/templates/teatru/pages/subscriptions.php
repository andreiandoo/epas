<?php
/**
 * PAGE: /abonamente  — demonstrates subscription-card + cta.
 */
require __DIR__ . '/../includes/bootstrap.php';

$plans = kit_subscriptions();   // raw tenant plans → map to subscription-card input

layout('public', ['title' => 'Abonamente — ' . kit_cfg('site_name'), 'nav' => 'subscriptions'], function () use ($plans) { ?>
  <section class="kit-section">
    <div class="kit-container">
      <div style="text-align:center;margin-bottom:2rem">
        <p class="kit-event-card__cat">Stagiunea 2026</p>
        <h1 class="kit-display" style="font-size:clamp(2rem,5vw,3rem)">Abonamente</h1>
      </div>
      <div class="kit-grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr));align-items:start">
        <?php foreach ($plans as $p): ?>
          <?php component('subscription-card', ['plan' => [
              'name' => $p['name'] ?? '', 'price' => $p['price'] ?? null, 'currency' => $p['currency'] ?? 'RON',
              'subtitle' => $p['subtitle'] ?? '', 'is_featured' => !empty($p['is_featured']),
              'benefits' => $p['benefits'] ?? [], 'cta_label' => 'Abonează-te', 'cta_url' => '/abonament?plan=' . ($p['id'] ?? ''),
          ]]); ?>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php component('cta', ['title' => 'Ai întrebări despre abonamente?', 'text' => 'Echipa noastră îți răspunde rapid.', 'action' => ['label' => 'Contact', 'url' => '/contact']]); ?>
<?php });
