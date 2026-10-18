<?php
/** /petreceri: edițiile următoare ca afișe, filtrabile pe oraș, și arhiva edițiilor trecute (cu albumele lor). */
require_once TEMPLATE_DIR . '/lib.php';
$upcoming = pt_upcoming();
$past = pt_past();
$cities = array_values(array_unique(array_filter(array_map('pt_city', $upcoming))));
sort($cities);
$pageTitle = t('Petreceri') . ' — ' . SITE_NAME;
$pageDescription = t('Toate edițiile {name}: teme, orașe, date și bilete, plus arhiva petrecerilor trecute.', ['name' => SITE_NAME]);
$pageSchema = [];
foreach (array_slice($upcoming, 0, 20) as $ev) {
    $pageSchema[] = ts_event_schema($ev, ['type' => 'MusicEvent', 'performers' => [['name' => SITE_NAME, 'type' => 'PerformingGroup']]]);
}
$pageSchema[] = ts_breadcrumb_schema([[t('Acasă'), '/'], [t('Petreceri'), ts_link('events')]]);
$headSolid = true;
include ts_layout('head');
include ts_layout('header');
?>
<section class="pt-page-head">
    <div class="pt-wrap">
        <p class="pt-eyebrow"><span class="pt-rec" aria-hidden="true"></span><?= e(SITE_NAME) ?></p>
        <h1 class="pt-h1"><?= te('Petreceri') ?></h1>
        <p class="pt-lead"><?= $upcoming ? te('{n} ediții în {c} orașe.', ['n' => count($upcoming), 'c' => count($cities)]) : te('Edițiile noi se anunță în curând.') ?></p>
    </div>
</section>
<section class="pt-section pt-section--tight" x-data="{ city: '' }">
    <div class="pt-wrap">
        <?php if (count($cities) > 1): ?>
        <div class="pt-chips" role="group" aria-label="<?= te('Alege orașul') ?>">
            <button type="button" class="pt-chip" :class="!city && 'is-on'" :aria-pressed="!city" @click="city = ''"><?= te('Toate orașele') ?></button>
            <?php foreach ($cities as $c): ?>
            <button type="button" class="pt-chip" :class="city === <?= e(json_encode($c, JSON_UNESCAPED_UNICODE)) ?> && 'is-on'" :aria-pressed="city === <?= e(json_encode($c, JSON_UNESCAPED_UNICODE)) ?>" @click="city = <?= e(json_encode($c, JSON_UNESCAPED_UNICODE)) ?>"><?= e($c) ?></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($upcoming): ?>
        <ul class="pt-flyers" data-stagger>
            <?php foreach ($upcoming as $i => $ev): $c = pt_city($ev); ?>
            <li x-show="!city || city === <?= e(json_encode($c, JSON_UNESCAPED_UNICODE)) ?>"><?= pt_flyer($ev, $i) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="pt-empty"><?= te('Edițiile noi se anunță în curând.') ?> <a href="/#newsletter" class="pt-link"><?= te('Abonează-te') ?></a></p>
        <?php endif; ?>
    </div>
</section>
<?php if ($past): ?>
<section class="pt-section pt-section--alt" id="arhiva" aria-labelledby="pt-past-title">
    <div class="pt-wrap">
        <div class="pt-head2"><div><p class="pt-eyebrow"><?= te('Arhivă') ?></p><h2 class="pt-h2" id="pt-past-title"><?= te('Ediții trecute') ?></h2></div>
            <a href="/galerie" class="pt-btn pt-btn--ghost"><?= te('Toate albumele') ?> <?= PT_ARROW ?></a></div>
        <ul class="pt-rows">
            <?php foreach ($past as $ev): ?><?= pt_row($ev, true) ?><?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>
<?php include ts_layout('footer');
