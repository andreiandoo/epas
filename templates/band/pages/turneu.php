<?php
/** /turneu: toate concertele viitoare și arhiva concertelor trecute. */
require_once TEMPLATE_DIR . '/lib.php';
$upcoming = bd_upcoming();
$past = bd_past();
$pageTitle = t('Turneu') . ' — ' . SITE_NAME;
$pageDescription = t('Toate concertele {artist}: date, orașe, săli și bilete.', ['artist' => SITE_NAME]);
$pageSchema = [];
foreach (array_slice($upcoming, 0, 20) as $ev) {
    $pageSchema[] = ts_event_schema($ev, ['type' => 'MusicEvent', 'performers' => [['name' => SITE_NAME, 'type' => 'MusicGroup']]]);
}
$pageSchema[] = ts_breadcrumb_schema([[t('Acasă'), '/'], [t('Turneu'), ts_link('events')]]);
$headSolid = true;
include ts_layout('head');
include ts_layout('header');
?>
<section class="bd-page-head">
    <div class="bd-wrap">
        <p class="bd-eyebrow"><?= e(SITE_NAME) ?></p>
        <h1 class="bd-h1"><?= te('Turneu') ?></h1>
        <p class="bd-lead"><?= $upcoming ? te('{n} concerte în {c} orașe.', ['n' => count($upcoming), 'c' => count(array_unique(array_map('bd_city', $upcoming)))]) : te('Noile date de concert se anunță în curând.') ?></p>
    </div>
</section>
<section class="bd-section bd-section--tight">
    <div class="bd-wrap">
        <?php if ($upcoming): ?>
        <ul class="bd-rows" data-bd-rows>
            <?php foreach ($upcoming as $ev): ?><?= bd_row($ev) ?><?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <div class="bd-follow" aria-hidden="true" data-bd-follow><img alt=""></div>
</section>
<?php if ($past): ?>
<section class="bd-section bd-section--alt" id="arhiva" aria-labelledby="bd-past-title">
    <div class="bd-wrap">
        <div class="bd-head2"><div><p class="bd-eyebrow"><?= te('Arhivă') ?></p><h2 class="bd-h2" id="bd-past-title"><?= te('Concerte trecute') ?></h2></div></div>
        <ul class="bd-rows bd-rows--past">
            <?php foreach ($past as $ev): ?><?= bd_row($ev, true) ?><?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>
<?php include ts_layout('footer');
