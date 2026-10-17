<?php
/** /booking: cererea de booking generală, cu artistul ales din roster (sau „propuneți-mi voi”). */
require_once TEMPLATE_DIR . '/lib.php';
$roster = array_values(array_filter(ag_roster(), fn ($a) => !empty($a['bookable'])));
$pageTitle = t('Booking') . ' — ' . SITE_NAME;
$pageDescription = t('Cere un artist din rosterul {name} pentru concert, festival sau eveniment privat.', ['name' => SITE_NAME]);
$pageSchema = [ts_breadcrumb_schema([[t('Acasă'), '/'], [t('Booking'), '/booking']])];
$headSolid = true;
include ts_layout('head');
include ts_layout('header');
?>
<section class="ag-page-head">
    <div class="ag-wrap">
        <p class="ag-eyebrow"><?= e(SITE_NAME) ?></p>
        <h1 class="ag-h1"><?= te('Booking') ?></h1>
        <p class="ag-lead"><?= te('Pentru concerte, festivaluri, evenimente corporate și private. Completezi o dată, îți răspundem cu disponibilitatea și o ofertă în cel mult două zile lucrătoare.') ?></p>
    </div>
</section>
<section class="ag-section ag-section--tight">
    <div class="ag-wrap ag-book ag-book--page">
        <ol class="ag-steps">
            <li><b>01</b><span><?= te('Ne spui ce pregătești și pe cine ai vrea.') ?></span></li>
            <li><b>02</b><span><?= te('Verificăm disponibilitatea și revenim cu o ofertă.') ?></span></li>
            <li><b>03</b><span><?= te('Semnăm contractul și ne ocupăm de restul: rider, transport, cazare.') ?></span></li>
        </ol>
        <?php $bfArtist = (string) ($_GET['artist'] ?? ''); $bfRoster = $roster; include TEMPLATE_DIR . '/layout/booking-form.php'; ?>
    </div>
</section>
<?php include ts_layout('footer');
