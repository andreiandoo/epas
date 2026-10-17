<?php
/**
 * Restul sezonului: galerie orizontală (4+ competiții), grilă (1–3) sau mesaj
 * când nu mai e nimic după competiția din prim-plan.
 */
require_once TEMPLATE_DIR . '/lib.php';
$events = comp_events();
$rest = array_slice($events, 1);
$eventsUrl = ts_link('events');
$eyebrow = $s['eyebrow'] ?? 'Calendar';
$title = $s['title'] ?? 'Mai departe în sezon';
$linkLabel = $s['link_label'] ?? 'Tot calendarul';
$extLabel = trim((string) ($s['external_label'] ?? ''));
$extUrl = trim((string) ($s['external_url'] ?? ''));
?>
<?php if (count($rest) >= 4): ?>
<section class="hs" data-hscroll>
    <div class="hs__pin">
        <div class="wrap">
            <div class="sec__head">
                <div>
                    <span class="label"><?= e($eyebrow) ?></span>
                    <h2 data-split><?= e($title) ?></h2>
                </div>
                <a class="link" href="<?= e($eventsUrl) ?>"><?= e($linkLabel) ?></a>
            </div>
        </div>
        <div class="hs__track">
            <?php foreach ($rest as $ev): ?><?= part_card($ev) ?><?php endforeach; ?>
            <div class="hs__end">
                <span class="label">Tot sezonul</span>
                <h3>Vezi toate competițiile</h3>
                <?= part_btn('Calendar complet', $eventsUrl, 'btn--light') ?>
            </div>
        </div>
    </div>
</section>
<?php elseif ($rest): ?>
<section class="sec">
    <div class="wrap">
        <div class="sec__head">
            <div>
                <span class="label"><?= e($eyebrow) ?></span>
                <h2 data-split><?= e($title) ?></h2>
            </div>
            <a class="link" href="<?= e($eventsUrl) ?>"><?= e($linkLabel) ?></a>
        </div>
        <div class="grid" data-reveal-group>
            <?php foreach ($rest as $ev): ?><div class="grid__cell"><?= part_card($ev) ?></div><?php endforeach; ?>
        </div>
    </div>
</section>
<?php else: ?>
<section class="sec"<?= $events ? ' style="padding-bottom:0"' : '' ?>>
    <div class="wrap">
        <div class="soon" data-reveal>
            <div>
                <span class="label"><?= e($eyebrow) ?></span>
                <h3 style="margin-top:14px"><?= e($events ? ($s['one_title'] ?? 'Următoarele competiții se anunță în curând') : ($s['empty_title'] ?? 'Calendarul se anunță în curând')) ?></h3>
                <p><?= e($events ? ($s['one_text'] ?? 'Deocamdată sunt bilete în vânzare la o singură competiție.') : ($s['empty_text'] ?? 'Momentan nu există competiții cu bilete puse în vânzare.')) ?></p>
            </div>
            <?php if ($extLabel !== '' && $extUrl !== ''): ?>
            <a class="btn btn--ghost" href="<?= e(ts_href($extUrl)) ?>" target="_blank" rel="noopener"><?= e($extLabel) ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>
