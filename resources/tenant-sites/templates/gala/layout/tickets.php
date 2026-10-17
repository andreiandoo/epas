<?php
/**
 * Biletele unei gale, pe zone (peluză → VIP), cu cantități și bara de total;
 * „Continuă” pune biletele în coșul comun (galaTickets din assets/fx.js).
 * Variabilă: $ev (gala, cu ticket_types).
 */
$types = [];
foreach ((array) ($ev['ticket_types'] ?? []) as $t) {
    $avail = $t['available'] ?? null;
    $price = (float) ($t['sale_price'] ?? 0) ?: (float) ($t['price'] ?? 0);
    $types[] = [
        'id' => (int) $t['id'], 'name' => (string) $t['name'], 'description' => (string) ($t['description'] ?? ''),
        'price' => $price, 'perks' => array_values((array) ($t['perks'] ?? [])),
        'soldOut' => $avail !== null && (int) $avail <= 0, 'left' => $avail !== null && (int) $avail > 0 && (int) $avail <= 20 ? (int) $avail : null,
    ];
}
if (!$types) {
    echo '<p class="gx-empty">' . te('Biletele se pun în vânzare în curând.') . '</p>';
    return;
}
$cfg = ['event' => gx_cart_event($ev), 'types' => $types];
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
$top = count($types) - 1;
?>
<div class="gx-tix" x-data="galaTickets(<?= e(json_encode($cfg, $flags)) ?>)">
    <div class="gx-zones">
        <?php foreach ($types as $i => $t): $id = $t['id']; ?>
        <article class="gx-zone<?= $i === $top ? ' gx-zone--top' : '' ?><?= $t['soldOut'] ? ' is-out' : '' ?>" data-reveal>
            <span class="gx-zone__lvl" aria-hidden="true"><?php for ($k = 0; $k <= $i; $k++): ?><i></i><?php endfor; ?></span>
            <div class="gx-zone__main">
                <h4><?= e($t['name']) ?></h4>
                <?php if ($t['description'] !== ''): ?><p><?= e($t['description']) ?></p><?php endif; ?>
                <?php if ($t['perks']): ?><ul><?php foreach ($t['perks'] as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul><?php endif; ?>
            </div>
            <div class="gx-zone__buy">
                <?php if ($t['left']): ?><span class="gx-chip gx-chip--hot"><?= te('Ultimele {n} locuri', ['n' => $t['left']]) ?></span><?php endif; ?>
                <b class="gx-price"><?= e(gx_money($t['price'])) ?></b>
                <?php if ($t['soldOut']): ?>
                <span class="gx-chip"><?= te('Epuizat') ?></span>
                <?php else: ?>
                <div class="gx-qty" role="group" aria-label="<?= te('Cantitate') ?> — <?= e($t['name']) ?>">
                    <button type="button" @click="dec(<?= $id ?>)" :disabled="!qty[<?= $id ?>]" aria-label="<?= te('Scade') ?>">−</button>
                    <output x-text="qty[<?= $id ?>]">0</output>
                    <button type="button" @click="inc(<?= $id ?>)" aria-label="<?= te('Adaugă') ?>">+</button>
                </div>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <div class="gx-buybar" x-show="count > 0" x-cloak x-transition>
        <div class="gx-wrap gx-buybar__row">
            <div><span x-text="TS.ticketsLabel(count)"></span><b x-text="TS.lei(total)"></b></div>
            <button type="button" class="gx-btn" @click="save()"><?= te('Continuă spre coș') ?> <?= GX_ARROW ?></button>
        </div>
    </div>
</div>
