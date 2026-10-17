<?php
/**
 * Afișul lineup-ului, ca un poster de festival: o coloană pe zi, headlinerii mari,
 * apoi restul pe rânduri tot mai mici. La hover pe un nume apare fotografia artistului.
 */
require_once TEMPLATE_DIR . '/lib.php';
$l = fest_lineup();
if (!$l['slots']) {
    return;
}
$tierRank = ['headliner' => 0, 'sub_headliner' => 1, 'regular' => 2, 'support' => 3, 'emerging' => 4];
$byDay = [];
foreach ($l['slots'] as $slot) {
    if (empty($slot['name'])) {
        continue;
    }
    $byDay[$slot['day_id']][] = $slot;
}
?>
<section class="fx-section fx-poster" id="lineup">
    <div class="fx-wrap">
        <div class="fx-head2" data-reveal>
            <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><p class="fx-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
            <h2 class="fx-h2"><?= ts_md($s['title'] ?? '') ?></h2>
        </div>
        <div class="fx-poster__days">
            <?php foreach ($l['days'] as $d): $slots = $byDay[$d['id']] ?? []; if (!$slots) { continue; }
                usort($slots, fn ($a, $b) => [$tierRank[$a['tier']] ?? 9, $a['position']] <=> [$tierRank[$b['tier']] ?? 9, $b['position']]);
                $rows = [];
                foreach ($slots as $x) {
                    $r = min(2, $tierRank[$x['tier']] ?? 2);
                    $rows[$r][$x['name']] = $x;
                } ?>
            <div class="fx-poster__day" data-reveal>
                <p class="fx-poster__date"><b><?= e($d['name']) ?></b> <?= e(ts_date($d['date'], false, false)) ?></p>
                <?php foreach ([0, 1, 2] as $r): if (empty($rows[$r])) { continue; } ?>
                <p class="fx-poster__row fx-poster__row--<?= $r ?>">
                    <?php $i = 0; foreach ($rows[$r] as $name => $x): ?><?= $i++ ? '<i aria-hidden="true">·</i>' : '' ?><a href="/lineup#a<?= (int) ($x['artist']['id'] ?? 0) ?>" data-fx-peek="<?= e($x['image'] ?? '') ?>"><?= e($name) ?></a><?php endforeach; ?>
                </p>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (trim((string) ($s['button_label'] ?? '')) !== ''): ?>
        <div class="fx-center" data-reveal><a href="/lineup" class="fx-btn fx-btn--ghost"><?= e($s['button_label']) ?> <?= FEST_ARROW ?></a> <a href="/program" class="fx-link"><?= te('Programul pe ore') ?></a></div>
        <?php endif; ?>
    </div>
    <img class="fx-peek" alt="" aria-hidden="true" data-fx-peek-img>
</section>
