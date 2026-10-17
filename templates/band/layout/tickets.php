<?php
/**
 * Biletele unui concert. Tipurile cu loc numerotat (has_seating) se aleg pe harta sălii
 * (seatmap.js, în pagină); celelalte (acces general, pachete) cu cantități, prin
 * bandTickets (assets/fx.js). Ambele ajung în același coș. Variabilă: $ev.
 */
$types = [];
$seated = false;
foreach ((array) ($ev['ticket_types'] ?? []) as $t) {
    if (($t['status'] ?? 'active') !== 'active' || !empty($t['is_parking'])) {
        continue;
    }
    if (!empty($t['has_seating'])) {
        $seated = true;
        continue;
    }
    $avail = $t['available'] ?? null;
    $price = (float) ($t['sale_price'] ?? 0) ?: (float) ($t['price'] ?? 0);
    $types[] = [
        'id' => (int) $t['id'], 'name' => (string) $t['name'], 'description' => (string) ($t['description'] ?? ''),
        'price' => $price, 'perks' => array_values((array) ($t['perks'] ?? [])),
        'soldOut' => $avail !== null && (int) $avail <= 0,
        'left' => $avail !== null && (int) $avail > 0 && (int) $avail <= 15 ? (int) $avail : null,
    ];
}
$cartEvent = bd_cart_event($ev);
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
if (!$seated && !$types) {
    echo '<p class="bd-empty">' . te('Biletele se pun în vânzare în curând.') . '</p>';
    return;
}
?>
<?php if ($seated): ?>
<div class="bd-seats">
    <p class="bd-tix__hint"><?= te('Alege locurile direct pe harta sălii. Fiecare loc ales îți rămâne rezervat 15 minute.') ?></p>
    <div data-ts-seatmap-inline="<?= e(json_encode($cartEvent, JSON_UNESCAPED_UNICODE)) ?>"></div>
</div>
<?php endif; ?>
<?php if ($types): ?>
<div class="bd-tix" x-data="bandTickets(<?= e(json_encode(['event' => $cartEvent, 'types' => $types], $flags)) ?>)">
    <?php if ($seated): ?><h3 class="bd-tix__title"><?= te('Bilete fără loc numerotat') ?></h3><?php endif; ?>
    <div class="bd-tix__list">
        <?php foreach ($types as $i => $t): $id = $t['id']; ?>
        <article class="bd-ticket<?= $t['soldOut'] ? ' is-out' : '' ?>" :class="qty[<?= $id ?>] > 0 && 'is-on'" data-reveal>
            <div class="bd-ticket__main">
                <h4><?= e($t['name']) ?></h4>
                <?php if ($t['description'] !== ''): ?><p><?= e($t['description']) ?></p><?php endif; ?>
                <?php if ($t['perks']): ?><ul><?php foreach ($t['perks'] as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul><?php endif; ?>
            </div>
            <div class="bd-ticket__buy">
                <?php if ($t['left']): ?><span class="bd-pill bd-pill--hot"><?= te('Ultimele {n}', ['n' => $t['left']]) ?></span><?php endif; ?>
                <b class="bd-price"><?= e(bd_money($t['price'])) ?></b>
                <?php if ($t['soldOut']): ?>
                <span class="bd-pill bd-pill--out"><?= te('Epuizat') ?></span>
                <?php else: ?>
                <div class="bd-qty" role="group" aria-label="<?= te('Cantitate') ?> — <?= e($t['name']) ?>">
                    <button type="button" @click="dec(<?= $id ?>)" :disabled="!qty[<?= $id ?>]" aria-label="<?= te('Scade') ?>">−</button>
                    <output x-text="qty[<?= $id ?>]" aria-live="polite">0</output>
                    <button type="button" @click="inc(<?= $id ?>)" :disabled="qty[<?= $id ?>] >= max(<?= $id ?>)" aria-label="<?= te('Adaugă') ?>">+</button>
                </div>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <div class="bd-buybar" x-show="count > 0" x-cloak x-transition>
        <div class="bd-wrap bd-buybar__row">
            <div><span x-text="TS.ticketsLabel(count)"></span><b x-text="TS.lei(total)"></b></div>
            <button type="button" class="bd-btn" @click="save()"><?= te('Adaugă în coș') ?> <?= BD_ARROW ?></button>
        </div>
    </div>
</div>
<?php endif; ?>
