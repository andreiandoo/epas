<?php
/**
 * Biletele unei ediții, în ordinea în care le caută un petrecăreț:
 *  1. valurile de preț (early bird → valul 2 → valul 3), ca o bandă de progres: valul
 *     epuizat tăiat, cel în vânzare activ, următoarele cu data de deschidere;
 *  2. pachetele de grup;
 *  3. mesele VIP pe harta clubului (has_seating → seatmap.js, câte un loc la masă).
 * Cantitățile trec prin partyTickets (assets/fx.js); totul ajunge în același coș. Variabilă: $ev.
 */
$types = [];
$seated = false;
$waves = pt_waves($ev);
$waveIds = array_column($waves, 'state', 'id');
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
    $state = $waveIds[(int) $t['id']] ?? null;
    $opens = !empty($t['sales_start_at']) && strtotime((string) $t['sales_start_at']) > time() ? (string) $t['sales_start_at'] : null;
    $types[] = [
        'id' => (int) $t['id'], 'name' => (string) $t['name'], 'description' => (string) ($t['description'] ?? ''),
        'price' => $price, 'perks' => array_values((array) ($t['perks'] ?? [])),
        'wave' => $state !== null, 'state' => $state,
        'soldOut' => $avail !== null && (int) $avail <= 0,
        'closed' => (bool) $opens || $state === 'next', 'opens' => $opens,
        'left' => $avail !== null && (int) $avail > 0 && (int) $avail <= 30 ? (int) $avail : null,
    ];
}
$cartEvent = pt_cart_event($ev);
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
// În coș intră doar ce se poate cumpăra acum (nu valurile încă închise)
$buyable = array_values(array_filter($types, fn ($t) => !$t['soldOut'] && !$t['closed']));
if (!$seated && !$types) {
    echo '<p class="pt-empty">' . te('Biletele se pun în vânzare în curând.') . '</p>';
    return;
}
?>
<div class="pt-tix" x-data="partyTickets(<?= e(json_encode(['event' => $cartEvent, 'types' => $buyable], $flags)) ?>)">
    <?php if ($waves): ?>
    <ol class="pt-waves" aria-label="<?= te('Valurile de preț') ?>">
        <?php foreach ($waves as $w): ?>
        <li class="pt-wave pt-wave--<?= e($w['state']) ?>">
            <span class="pt-wave__bar" aria-hidden="true"></span>
            <b><?= e($w['name']) ?></b>
            <span class="pt-wave__price"><?= e(pt_money($w['price'])) ?></span>
            <small><?= $w['state'] === 'out' ? te('Epuizat') : ($w['state'] === 'on' ? te('În vânzare acum') : ($w['opens'] ? te('Se deschide {d}', ['d' => pt_opens($w['opens'])]) : te('După valul curent'))) ?></small>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>
    <div class="pt-tix__list">
        <?php foreach ($types as $t): $id = $t['id']; $off = $t['soldOut'] || $t['closed']; ?>
        <article class="pt-ticket<?= $off ? ' is-out' : '' ?><?= $t['state'] === 'on' ? ' is-now' : '' ?>" <?= $off ? '' : ':class="qty[' . $id . '] > 0 && \'is-on\'"' ?>>
            <div class="pt-ticket__main">
                <h4><?= e($t['name']) ?><?php if ($t['state'] === 'on'): ?> <span class="pt-pill pt-pill--hot"><?= te('Acum') ?></span><?php endif; ?></h4>
                <?php if ($t['description'] !== ''): ?><p><?= e($t['description']) ?></p><?php endif; ?>
                <?php if ($t['perks']): ?><ul><?php foreach ($t['perks'] as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul><?php endif; ?>
            </div>
            <div class="pt-ticket__buy">
                <?php if ($t['left'] && !$off): ?><span class="pt-pill pt-pill--hot"><?= te('Ultimele {n}', ['n' => $t['left']]) ?></span><?php endif; ?>
                <b class="pt-price"><?= e(pt_money($t['price'])) ?></b>
                <?php if ($t['soldOut']): ?>
                <span class="pt-pill pt-pill--out"><?= te('Epuizat') ?></span>
                <?php elseif ($t['closed']): ?>
                <span class="pt-pill"><?= $t['opens'] ? te('Se deschide {d}', ['d' => pt_opens($t['opens'])]) : te('După valul curent') ?></span>
                <?php else: ?>
                <div class="pt-qty" role="group" aria-label="<?= te('Cantitate') ?> — <?= e($t['name']) ?>">
                    <button type="button" @click="dec(<?= $id ?>)" :disabled="!qty[<?= $id ?>]" aria-label="<?= te('Scade') ?>">−</button>
                    <output x-text="qty[<?= $id ?>]" aria-live="polite">0</output>
                    <button type="button" @click="inc(<?= $id ?>)" :disabled="qty[<?= $id ?>] >= max(<?= $id ?>)" aria-label="<?= te('Adaugă') ?>">+</button>
                </div>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <div class="pt-buybar" x-show="count > 0" x-cloak x-transition>
        <div class="pt-wrap pt-buybar__row">
            <div><span x-text="TS.ticketsLabel(count)"></span><b x-text="TS.lei(total)"></b></div>
            <button type="button" class="pt-btn" @click="save($event)"><?= te('Adaugă în coș') ?> <?= PT_ARROW ?></button>
        </div>
    </div>
</div>
<?php if ($seated): ?>
<div class="pt-seats" id="mese">
    <h3 class="pt-tix__title"><?= te('Mese VIP') ?></h3>
    <p class="pt-tix__hint"><?= te('Alege locurile direct pe harta clubului: o masă întreagă pentru gașcă sau doar câteva locuri. Fiecare loc ales îți rămâne rezervat 15 minute.') ?></p>
    <div data-ts-seatmap-inline="<?= e(json_encode($cartEvent, JSON_UNESCAPED_UNICODE)) ?>"></div>
</div>
<?php endif; ?>
