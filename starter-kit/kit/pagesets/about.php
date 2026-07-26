<?php
/** PAGESET: about — static, brand-driven. Edit copy per site. */
$PAGE = $PAGE ?? [];
layout('public', ['title' => 'Despre — ' . kit_cfg('site_name'), 'nav' => $PAGE['nav'] ?? 'about'],
function () { ?>
  <section class="kit-section"><div class="kit-container" style="max-width:48rem">
    <h1 class="kit-display" style="font-size:clamp(2rem,5vw,3rem);margin-bottom:1rem">Despre <?= e(kit_cfg('site_name')) ?></h1>
    <p style="font-size:1.05rem;line-height:1.7;color:var(--kit-text-muted)">
      Aici scrii povestea <?= e(kit_cfg('site_name')) ?><?= kit_cfg('site_city') ? ' din ' . e(kit_cfg('site_city')) : '' ?>.
      Înlocuiește acest text (pagina <code>about</code>) cu conținutul real: istoric, misiune, echipă.
    </p>
    <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));margin-top:2rem">
      <?php component('stat-tile', ['num' => '25+', 'label' => 'ani de activitate']); ?>
      <?php component('stat-tile', ['num' => '500+', 'label' => kit_term('events', 'evenimente')]); ?>
      <?php component('stat-tile', ['num' => '100k+', 'label' => 'spectatori']); ?>
    </div>
  </div></section>
<?php });
