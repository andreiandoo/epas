<?php
/** /evenimente: toate concertele produse de agenție și arhiva celor trecute. */
require_once TEMPLATE_DIR . '/lib.php';
$upcoming = ag_upcoming();
$past = ag_past();
$pageTitle = t('Evenimente') . ' — ' . SITE_NAME;
$pageDescription = t('Concertele produse de {artist}: artiști, date, orașe și bilete.', ['artist' => SITE_NAME]);
$pageSchema = [];
foreach (array_slice($upcoming, 0, 20) as $ev) {
    $pageSchema[] = ts_event_schema($ev, ['type' => 'MusicEvent', 'performers' => [['name' => ag_show_name($ev), 'type' => 'MusicGroup']]]);
}
$pageSchema[] = ts_breadcrumb_schema([[t('Acasă'), '/'], [t('Evenimente'), ts_link('events')]]);
$headSolid = true;
include ts_layout('head');
include ts_layout('header');
?>
<section class="ag-page-head">
    <div class="ag-wrap">
        <p class="ag-eyebrow"><?= e(SITE_NAME) ?></p>
        <h1 class="ag-h1"><?= te('Evenimente') ?></h1>
        <p class="ag-lead"><?= $upcoming ? te('{n} concerte în {c} orașe.', ['n' => count($upcoming), 'c' => count(array_unique(array_map('ag_city', $upcoming)))]) : te('Noile date de concert se anunță în curând.') ?></p>
    </div>
</section>
<section class="ag-section ag-section--tight">
    <div class="ag-wrap">
        <?php if ($upcoming): ?>
        <ul class="ag-rows" data-ag-rows>
            <?php foreach ($upcoming as $ev): ?><?= ag_row($ev) ?><?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <div class="ag-follow" aria-hidden="true" data-ag-follow><img alt=""></div>
</section>
<?php if ($past): ?>
<section class="ag-section ag-section--alt" id="arhiva" aria-labelledby="ag-past-title">
    <div class="ag-wrap">
        <div class="ag-head2"><div><p class="ag-eyebrow"><?= te('Arhivă') ?></p><h2 class="ag-h2" id="ag-past-title"><?= te('Concerte trecute') ?></h2></div></div>
        <ul class="ag-rows ag-rows--past">
            <?php foreach ($past as $ev): ?><?= ag_row($ev, true) ?><?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>
<?php include ts_layout('footer');
