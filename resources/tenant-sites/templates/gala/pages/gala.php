<?php
/** /gala/{slug}: afișul galei, fight card-ul, biletele (sau rezultatele, dacă a trecut) și locul. */
require_once TEMPLATE_DIR . '/lib.php';
$ev = gx_event((string) ($_GET['slug'] ?? ''));
if (!$ev) {
    ts_not_found();
}
$card = gx_card($ev['slug']);
$past = strtotime(substr((string) ($ev['start_date'] ?? ''), 0, 10) . ' 23:59') < time();
$pageTitle = $ev['title'] . ' — ' . SITE_NAME;
$pageDescription = strip_tags((string) ($ev['short_description'] ?? $ev['description'] ?? ''));
$pageImage = $ev['poster_url'] ?? null;
$fighters = [];
foreach ($card as $b) {
    foreach (['a', 'b'] as $k) {
        $fighters[] = ['name' => (string) ($b[$k]['name'] ?? '')];
    }
}
$pageSchema = [
    ts_event_schema($ev, ['type' => 'SportsEvent', 'performers' => $fighters]),
    ts_breadcrumb_schema([[t('Acasă'), '/'], [t('Gale'), ts_link('events')], [$ev['title'], ts_link('event', ['slug' => $ev['slug']])]]),
];
$h1 = true;
$eyebrow = $past ? t('Gală disputată') : null;
include ts_layout('head');
include ts_layout('header');
include TEMPLATE_DIR . '/layout/faceoff.php';
?>
<section class="gx-section" id="fight-card">
    <div class="gx-wrap">
        <div class="gx-head2" data-reveal>
            <p class="gx-eyebrow"><?= $past ? te('Rezultate') : te('Fight card') ?></p>
            <h2 class="gx-h2"><?= te('{n} meciuri', ['n' => count($card)]) ?></h2>
        </div>
        <?php include TEMPLATE_DIR . '/layout/card.php'; ?>
    </div>
</section>
<?php if (!$past): ?>
<section class="gx-section gx-section--alt" id="bilete">
    <div class="gx-wrap">
        <div class="gx-head2" data-reveal>
            <p class="gx-eyebrow"><?= te('Bilete') ?></p>
            <h2 class="gx-h2"><?= te('Alege-ți locul') ?></h2>
        </div>
        <?php include TEMPLATE_DIR . '/layout/tickets.php'; ?>
    </div>
</section>
<?php endif; ?>
<section class="gx-section">
    <div class="gx-wrap gx-venue">
        <div data-reveal>
            <p class="gx-eyebrow"><?= te('Locația') ?></p>
            <h2 class="gx-h2"><?= e($ev['venue']['name'] ?? '') ?></h2>
            <p class="gx-lead"><?= e(trim(($ev['venue']['address'] ?? '') . (!empty($ev['venue']['city']) ? ', ' . $ev['venue']['city'] : ''), ', ')) ?></p>
            <?php if (!empty($ev['description'])): ?><div class="gx-prose"><?= $ev['description'] ?></div><?php endif; ?>
        </div>
        <ul class="gx-facts" data-reveal>
            <li><span><?= te('Data') ?></span><b><?= e(gx_when($ev, false)) ?></b></li>
            <?php if (!empty($ev['door_time'])): ?><li><span><?= te('Deschiderea porților') ?></span><b><?= e(substr((string) $ev['door_time'], 0, 5)) ?></b></li><?php endif; ?>
            <?php if (!empty($ev['start_time'])): ?><li><span><?= te('Primul meci') ?></span><b><?= e(substr((string) $ev['start_time'], 0, 5)) ?></b></li><?php endif; ?>
            <li><span><?= te('Bilete') ?></span><b><?= $past ? te('Gala s-a încheiat') : te('Online, pe email, cu cod QR') ?></b></li>
        </ul>
    </div>
</section>
<?php include ts_layout('footer');
