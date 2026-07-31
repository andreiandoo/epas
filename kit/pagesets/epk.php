<?php
/**
 * PAGESET: epk  (artist) — electronic press kit: bio, stats, downloads, tour.
 * Feature 'epk'. Replace placeholder download links with real asset URLs.
 */
$PAGE = $PAGE ?? [];
$nav  = $PAGE['nav'] ?? 'epk';
$tour = kit_events(['per_page' => 6]);
layout('public', ['title' => 'Press Kit — ' . kit_cfg('site_name'), 'nav' => $nav],
function () use ($tour) { ?>
  <section class="kit-section"><div class="kit-container" style="max-width:52rem">
    <p class="kit-event-card__cat">Press / EPK</p>
    <h1 class="kit-display" style="font-size:clamp(2rem,5vw,3rem);margin:.25rem 0 1rem"><?= e(kit_cfg('site_name')) ?></h1>
    <p style="font-size:1.05rem;line-height:1.7;color:var(--kit-text-muted)">Bio scurt al artistului/formației. Înlocuiește cu textul oficial pentru presă, plus one-liner și long-bio.</p>

    <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));margin:2rem 0">
      <?php component('stat-tile', ['num' => '1.2M', 'label' => 'ascultători lunar']); ?>
      <?php component('stat-tile', ['num' => '48', 'label' => 'concerte / an']); ?>
      <?php component('stat-tile', ['num' => '3', 'label' => 'albume']); ?>
    </div>

    <h2 class="kit-display" style="font-size:1.4rem;margin:1.5rem 0 1rem">Descărcări</h2>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap">
      <a class="kit-btn kit-btn--outline" href="#">📷 Poze de presă</a>
      <a class="kit-btn kit-btn--outline" href="#">📄 Tech rider</a>
      <a class="kit-btn kit-btn--outline" href="#">🎚 Stage plan</a>
      <a class="kit-btn kit-btn--outline" href="#">🎵 Press track</a>
    </div>

    <?php if ($tour): ?>
      <h2 class="kit-display" style="font-size:1.4rem;margin:2.5rem 0 1rem">Date de turneu</h2>
      <div style="display:flex;flex-direction:column;gap:.75rem">
        <?php foreach ($tour as $e) component('schedule-row', ['event' => $e]); ?>
      </div>
    <?php endif; ?>

    <?php component('cta', ['title' => 'Booking', 'text' => 'Pentru concerte și evenimente private.', 'action' => ['label' => 'Contact', 'url' => '/contact']]); ?>
  </div></section>
<?php });
