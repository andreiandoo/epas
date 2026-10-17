<?php
/** /bilete: toate biletele festivalului, cu pașii de cumpărare și întrebările despre bilete. */
require_once TEMPLATE_DIR . '/lib.php';
$ev = fest_event();
$pageTitle = t('Bilete') . ' — ' . SITE_NAME;
$headSolid = true;
$steps = [
    [t('Alegi biletele'), t('Abonament, bilet de o zi sau extra: camping, parcare.')],
    [t('Plătești cu cardul'), t('Nu ai nevoie de cont. Biletele îți sunt rezervate cât completezi datele.')],
    [t('Intri cu QR-ul'), t('Biletele vin pe email; la intrare le schimbi pe brățara cu cip.')],
];
include ts_layout('head');
include ts_layout('header');
?>
<section class="fx-page-head">
    <div class="fx-wrap">
        <p class="fx-eyebrow"><?= e(fest_date_range($ev)) ?><?= fest_place($ev) !== '' ? ' · ' . e(fest_place($ev)) : '' ?></p>
        <h1 class="fx-h1"><?= te('Bilete') ?></h1>
        <?= fest_countdown('fx-cd--inline') ?>
    </div>
</section>
<section class="fx-section fx-section--tight">
    <div class="fx-wrap">
        <?php include TEMPLATE_DIR . '/layout/tickets.php'; ?>
        <ol class="fx-steps">
            <?php foreach ($steps as $i => [$h, $p]): ?>
            <li><span><?= $i + 1 ?></span><b><?= e($h) ?></b><p><?= e($p) ?></p></li>
            <?php endforeach; ?>
        </ol>
    </div>
</section>
<?php include ts_layout('footer');
