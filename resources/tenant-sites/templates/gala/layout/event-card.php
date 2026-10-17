<?php
/** Cardul unei gale (lista de gale). Variabile: $e (eveniment din listă), $past (bool, opțional). */
$d = substr((string) ($e['start_date'] ?? ''), 0, 10);
$ts = $d ? strtotime($d) : null;
$isPast = !empty($past);
$parts = $d ? explode(' ', ts_date($d)) : [];
?>
<a href="<?= e(ts_link('event', ['slug' => $e['slug']])) ?>" class="gx-ev<?= $isPast ? ' gx-ev--past' : '' ?>">
    <span class="gx-ev__img"><?php if (!empty($e['poster_url'])): ?><img src="<?= e($e['poster_url']) ?>" alt="" loading="lazy"><?php endif; ?></span>
    <span class="gx-ev__date"><b><?= $ts ? date('j', $ts) : '' ?></b><span><?= $parts ? e(mb_substr($parts[1], 0, 3)) . ' ' . e($parts[2] ?? '') : '' ?></span></span>
    <span class="gx-ev__body">
        <b class="gx-ev__title"><?= e($e['title']) ?></b>
        <span class="gx-ev__meta"><?= e(gx_place($e)) ?></span>
        <span class="gx-ev__go"><?= $isPast ? te('Rezultate') : te('Fight card și bilete') ?> <?= GX_ARROW ?></span>
    </span>
</a>
