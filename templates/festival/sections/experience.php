<?php
/**
 * Experiența: galerie orizontală, fixată la scroll pe desktop (GSAP, fx.js), listă
 * glisabilă pe mobil. Imaginile: cele din panou, altfel galeria festivalului din core.
 */
require_once TEMPLATE_DIR . '/lib.php';
$ev = fest_event();
$gallery = array_values(array_filter((array) ($ev['gallery'] ?? [])));
$items = [];
foreach (array_values((array) ($s['items'] ?? [])) as $i => $it) {
    $img = trim((string) ($it['image'] ?? '')) ?: ($gallery[$i] ?? null);
    if ($img) {
        $items[] = ['img' => $img, 'caption' => (string) ($it['caption'] ?? '')];
    }
}
if (!$items) {
    foreach ($gallery as $g) {
        $items[] = ['img' => $g, 'caption' => ''];
    }
}
if (!$items) {
    return;
}
?>
<section class="fx-section fx-exp" data-fx-hscroll>
    <div class="fx-wrap fx-head2" data-reveal>
        <?php if (trim((string) ($s['eyebrow'] ?? '')) !== ''): ?><p class="fx-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
        <h2 class="fx-h2"><?= ts_md($s['title'] ?? '') ?></h2>
    </div>
    <div class="fx-exp__viewport">
        <div class="fx-exp__track" data-fx-htrack>
            <?php foreach ($items as $i => $it): ?>
            <figure class="fx-exp__item<?= $i % 3 === 1 ? ' fx-exp__item--tall' : '' ?>">
                <img src="<?= e($it['img']) ?>" alt="<?= e($it['caption']) ?>" loading="lazy" decoding="async">
                <?php if ($it['caption'] !== ''): ?><figcaption><span>0<?= $i + 1 ?></span><?= e($it['caption']) ?></figcaption><?php endif; ?>
            </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>
