<?php
/** /gale: galele viitoare și arhiva cu rezultate. */
require_once TEMPLATE_DIR . '/lib.php';
$pageTitle = t('Gale') . ' — ' . SITE_NAME;
$headSolid = true;
$upcoming = gx_upcoming();
$pastList = gx_past();
include ts_layout('head');
include ts_layout('header');
?>
<section class="gx-page-head">
    <div class="gx-wrap">
        <p class="gx-eyebrow"><?= e(SITE_NAME) ?></p>
        <h1 class="gx-h1"><?= te('Gale') ?></h1>
    </div>
</section>
<section class="gx-section gx-section--tight">
    <div class="gx-wrap">
        <h2 class="gx-h3"><?= te('Urmează') ?></h2>
        <?php if (!$upcoming): ?>
        <p class="gx-empty"><?= te('Următoarea gală se anunță în curând.') ?></p>
        <?php else: ?>
        <div class="gx-events" data-stagger><?php foreach ($upcoming as $e): $past = false; include TEMPLATE_DIR . '/layout/event-card.php'; endforeach; ?></div>
        <?php endif; ?>
    </div>
</section>
<?php if ($pastList): ?>
<section class="gx-section gx-section--alt" id="rezultate">
    <div class="gx-wrap">
        <h2 class="gx-h3"><?= te('Arhivă și rezultate') ?></h2>
        <div class="gx-events" data-stagger><?php foreach ($pastList as $e): $past = true; include TEMPLATE_DIR . '/layout/event-card.php'; endforeach; ?></div>
    </div>
</section>
<?php endif; ?>
<?php include ts_layout('footer');
