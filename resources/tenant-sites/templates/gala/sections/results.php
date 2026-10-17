<?php
/** Rezultatele ultimei gale: fiecare meci cu câștigătorul și metoda. */
require_once TEMPLATE_DIR . '/lib.php';
$last = gx_past()[0] ?? null;
if (!$last) {
    return;
}
$bouts = array_values(array_filter(gx_card($last['slug']), fn ($b) => !empty($b['result'])));
if (!$bouts) {
    return;
}
?>
<section class="gx-section gx-section--alt" id="rezultate">
    <div class="gx-wrap">
        <div class="gx-head2 gx-head2--split" data-reveal>
            <div>
                <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><p class="gx-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
                <h2 class="gx-h2"><?= ts_md($s['title'] ?? '') ?></h2>
            </div>
            <p class="gx-lead"><?= e($last['title']) ?> · <?= e(gx_when($last, false)) ?></p>
        </div>
        <ol class="gx-results">
            <?php foreach ($bouts as $b): $r = $b['result']; $w = $r['winner'] === 'b' ? $b['b'] : $b['a']; $l = $r['winner'] === 'b' ? $b['a'] : $b['b']; $draw = in_array($r['winner'], ['draw', 'nc'], true); ?>
            <li data-reveal>
                <span class="gx-results__sec"><?= e(gx_section_label($b['section'])) ?></span>
                <span class="gx-results__who">
                    <?php if ($draw): ?>
                    <b><?= e($b['a']['name']) ?></b> <i><?= te('egal cu') ?></i> <b><?= e($b['b']['name']) ?></b>
                    <?php else: ?>
                    <b><?= e($w['name']) ?></b> <i><?= te('l-a învins pe') ?></i> <span><?= e($l['name']) ?></span>
                    <?php endif; ?>
                </span>
                <span class="gx-results__how"><?= e($r['method'] ?? '') ?><?= $r['round'] ? ' · ' . te('R{n}', ['n' => $r['round']]) : '' ?><?= $r['time'] ? ' · ' . e($r['time']) : '' ?></span>
            </li>
            <?php endforeach; ?>
        </ol>
        <p class="gx-center"><a href="<?= e(ts_link('event', ['slug' => $last['slug']])) ?>" class="gx-link"><?= te('Toată gala') ?> →</a></p>
    </div>
</section>
