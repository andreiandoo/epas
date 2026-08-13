<?php
/** PAGESET: giftcards — gift card teaser + amount picker (feature 'gift_cards'). */
$PAGE = $PAGE ?? [];
$nav  = $PAGE['nav'] ?? 'giftcards';
layout('public', ['title' => 'Carduri cadou — ' . kit_cfg('site_name'), 'nav' => $nav],
function () { ?>
  <section class="kit-section"><div class="kit-container" style="max-width:44rem;text-align:center">
    <p class="kit-event-card__cat">Cadoul perfect</p>
    <h1 class="kit-display" style="font-size:clamp(2rem,5vw,3rem);margin:.25rem 0 1rem">Carduri cadou</h1>
    <p class="kit-muted" style="margin-bottom:2rem">Oferă acces la <?= e(kit_term('events', 'evenimente')) ?>. Valabil 12 luni.</p>
    <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap" x-data="{ v:100 }">
      <?php foreach ([50, 100, 200, 500] as $amt): ?>
        <button class="kit-btn kit-btn--outline" :class="v===<?= $amt ?>?'kit-btn--primary':''" @click="v=<?= $amt ?>"><?= $amt ?> <?= e(kit_cfg('currency','RON')) ?></button>
      <?php endforeach; ?>
      <a href="/cos" class="kit-btn kit-btn--primary">Cumpără</a>
    </div>
  </div></section>
<?php });
