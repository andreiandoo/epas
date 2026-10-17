<?php
/** Scenele (din core): culoarea, descrierea, câte apariții și cine e capul de afiș. */
require_once TEMPLATE_DIR . '/lib.php';
$l = fest_lineup();
if (!$l['stages']) {
    return;
}
$count = [];
$top = [];
foreach ($l['slots'] as $x) {
    $count[$x['stage_id']] = ($count[$x['stage_id']] ?? 0) + 1;
    if (!empty($x['name']) && (empty($top[$x['stage_id']]) || in_array($x['tier'], ['headliner', 'sub_headliner'], true))) {
        $top[$x['stage_id']][] = $x['name'];
    }
}
?>
<section class="fx-section" id="scene">
    <div class="fx-wrap">
        <div class="fx-head2 fx-head2--split" data-reveal>
            <div>
                <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><p class="fx-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
                <h2 class="fx-h2"><?= ts_md($s['title'] ?? '') ?></h2>
            </div>
            <?php if (trim((string) ($s['lead'] ?? '')) !== ''): ?><p class="fx-lead"><?= ts_md($s['lead']) ?></p><?php endif; ?>
        </div>
        <div class="fx-stages" data-stagger>
            <?php foreach ($l['stages'] as $i => $st): $c = $st['color'] ?: 'rgb(var(--c-primary))'; ?>
            <a href="/program#s<?= (int) $st['id'] ?>" class="fx-stage" style="--stage: <?= e($c) ?>">
                <span class="fx-stage__n">0<?= $i + 1 ?></span>
                <h3><?= e($st['name']) ?></h3>
                <?php if (!empty($st['description'])): ?><p><?= e($st['description']) ?></p><?php endif; ?>
                <span class="fx-stage__foot">
                    <span><?= te('{n} apariții', ['n' => $count[$st['id']] ?? 0]) ?></span>
                    <?php if (!empty($top[$st['id']])): ?><span><?= e(implode(', ', array_slice(array_unique($top[$st['id']]), -3))) ?></span><?php endif; ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
