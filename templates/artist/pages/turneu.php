<?php
/** /turneu: toate concertele viitoare și arhiva concertelor trecute. */
require_once TEMPLATE_DIR . '/lib.php';
$upcoming = ar_upcoming();
$past = ar_past();
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
<section class="ar-page-head">
    <div class="ar-wrap">
        <p class="ar-eyebrow"><?= e(SITE_NAME) ?></p>
        <h1 class="ar-h1"><?= te('Turneu') ?></h1>
        <p class="ar-lead"><?= $upcoming ? te('{n} concerte în {c} orașe.', ['n' => count($upcoming), 'c' => count(array_unique(array_map('ar_city', $upcoming)))]) : te('Noile date de concert se anunță în curând.') ?></p>
    </div>
</section>
<section class="ar-section ar-section--tight">
    <div class="ar-wrap">
        <?php if ($upcoming): ?>
        <ul class="ar-rows" data-ar-rows>
            <?php foreach ($upcoming as $ev): ?><?= ar_row($ev) ?><?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <div class="ar-follow" aria-hidden="true" data-ar-follow><img alt=""></div>
</section>
<?php if ($past): ?>
<section class="ar-section ar-section--alt" id="arhiva" aria-labelledby="ar-past-title">
    <div class="ar-wrap">
        <div class="ar-head2"><div><p class="ar-eyebrow"><?= te('Arhivă') ?></p><h2 class="ar-h2" id="ar-past-title"><?= te('Concerte trecute') ?></h2></div></div>
        <ul class="ar-rows ar-rows--past">
            <?php foreach ($past as $ev): ?><?= ar_row($ev, true) ?><?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>
<?php include ts_layout('footer');
