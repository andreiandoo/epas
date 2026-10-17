<?php
/**
 * Biletele festivalului, grupate (abonamente / bilete de o zi / camping și extra),
 * cu cantități și bara de total; „Continuă” pune totul în coșul comun (festTickets
 * din assets/fx.js). Primul grup se afișează ca bilete mari, celelalte ca rânduri.
 */
$ev = fest_event();
$groups = fest_ticket_groups();
if (!$ev || !$groups) {
    echo '<p class="fx-empty">' . te('Biletele se pun în vânzare în curând.') . '</p>';
    return;
}
$cfg = ['event' => fest_cart_event($ev), 'types' => []];
foreach ($groups as $g) {
    foreach ($g['items'] as $t) {
        $cfg['types'][] = fest_ticket_js($t);
    }
}
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<div class="fx-tickets" x-data="festTickets(<?= e(json_encode($cfg, $flags)) ?>)">
    <?php foreach ($groups as $gi => $g): ?>
    <div class="fx-tgroup<?= $gi === 0 ? ' fx-tgroup--main' : '' ?>">
        <h3 class="fx-tgroup__title"><?= e($g['name']) ?></h3>
        <div class="<?= $gi === 0 ? 'fx-passes' : 'fx-rows' ?>">
            <?php foreach ($g['items'] as $t): $j = fest_ticket_js($t); $id = (int) $j['id']; ?>
            <?php if ($gi === 0): ?>
            <article class="fx-pass<?= $j['soldOut'] ? ' is-out' : '' ?>" data-reveal>
                <div class="fx-pass__top">
                    <?php if ($j['soldOut']): ?><span class="fx-chip fx-chip--out"><?= te('Epuizat') ?></span><?php elseif ($j['left']): ?><span class="fx-chip fx-chip--hot"><?= te('Ultimele {n}', ['n' => $j['left']]) ?></span><?php endif; ?>
                    <h4><?= e($j['name']) ?></h4>
                    <?php if ($j['description'] !== ''): ?><p><?= e($j['description']) ?></p><?php endif; ?>
                </div>
                <?php if ($j['perks']): ?>
                <ul class="fx-pass__perks"><?php foreach ($j['perks'] as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <div class="fx-pass__foot">
                    <div class="fx-price"><?php if ($j['was']): ?><s><?= e(fest_money($j['was'])) ?></s><?php endif; ?><b><?= e(fest_money($j['price'])) ?></b></div>
                    <?php if (!$j['soldOut']): ?>
                    <div class="fx-qty" role="group" aria-label="<?= te('Cantitate') ?> — <?= e($j['name']) ?>">
                        <button type="button" @click="dec(<?= $id ?>)" :disabled="!qty[<?= $id ?>]" aria-label="<?= te('Scade') ?>">−</button>
                        <output x-text="qty[<?= $id ?>]">0</output>
                        <button type="button" @click="inc(<?= $id ?>)" aria-label="<?= te('Adaugă') ?>">+</button>
                    </div>
                    <?php endif; ?>
                </div>
                <i class="fx-pass__notch" aria-hidden="true"></i>
            </article>
            <?php else: ?>
            <div class="fx-row<?= $j['soldOut'] ? ' is-out' : '' ?>">
                <div class="fx-row__main">
                    <b><?= e($j['name']) ?></b>
                    <span><?= $j['date'] ? e(ts_date($j['date'], true, false)) . ' · ' : '' ?><?= e($j['perks'][0] ?? $j['description']) ?></span>
                </div>
                <div class="fx-price"><?php if ($j['was']): ?><s><?= e(fest_money($j['was'])) ?></s><?php endif; ?><b><?= e(fest_money($j['price'])) ?></b></div>
                <?php if ($j['soldOut']): ?>
                <span class="fx-chip fx-chip--out"><?= te('Epuizat') ?></span>
                <?php else: ?>
                <div class="fx-qty" role="group" aria-label="<?= te('Cantitate') ?> — <?= e($j['name']) ?>">
                    <button type="button" @click="dec(<?= $id ?>)" :disabled="!qty[<?= $id ?>]" aria-label="<?= te('Scade') ?>">−</button>
                    <output x-text="qty[<?= $id ?>]">0</output>
                    <button type="button" @click="inc(<?= $id ?>)" aria-label="<?= te('Adaugă') ?>">+</button>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="fx-buybar" x-show="count > 0" x-cloak x-transition>
        <div class="fx-wrap fx-buybar__row">
            <div><span x-text="TS.ticketsLabel(count)"></span><b x-text="TS.lei(total)"></b></div>
            <button type="button" class="fx-btn" @click="save()"><?= te('Continuă spre coș') ?> <?= FEST_ARROW ?></button>
        </div>
    </div>
</div>
