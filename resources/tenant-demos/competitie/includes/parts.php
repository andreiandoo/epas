<?php
/** Fragmente reutilizate pe mai multe pagini. */

/** Afișul competiției sau placeholder tipografic când lipsește. */
function part_poster(array $ev, string $loading = 'lazy'): string {
    $src = $ev['poster_url'] ?? ($ev['hero_image_url'] ?? null);
    if ($src) {
        return '<img src="' . e($src) . '" alt="Afiș ' . e($ev['title'] ?? '') . '" loading="' . $loading . '">';
    }
    return '<div class="poster-ph"><span>' . e($ev['title'] ?? '') . '</span></div>';
}

/** Card de competiție pentru grilă. */
function part_card(array $ev): string {
    $d    = ev_date_parts($ev);
    $from = $ev['price_from'] ?? null;
    $cat  = $ev['category'] ?? null;
    ob_start(); ?>
    <a class="card" href="/competitii/<?= e($ev['slug']) ?>" data-cat="<?= e($cat['slug'] ?? '') ?>">
        <div class="card__media">
            <?= part_poster($ev) ?>
            <span class="card__date"><?= e($d['days'] . ' ' . $d['month'] . ' ' . $d['year']) ?></span>
        </div>
        <div class="card__body">
            <?php if (!empty($cat['name'])): ?><span class="card__cat"><?= e($cat['name']) ?></span><?php endif; ?>
            <span class="card__title"><?= e($ev['title']) ?></span>
            <span class="card__place"><?= e(ev_place($ev)) ?></span>
            <span class="card__foot">
                <?php if (!empty($ev['is_sold_out'])): ?>
                    <span>Bilete epuizate</span>
                <?php elseif ($from !== null): ?>
                    <span>de la <b><?= e(lei($from)) ?></b></span>
                <?php else: ?>
                    <span>Bilete în curând</span>
                <?php endif; ?>
                <span class="card__go">Bilete →</span>
            </span>
        </div>
    </a>
    <?php return ob_get_clean();
}

/** Indicatorul de pași Coș → Date → Confirmare ($on = 1..3). */
function part_flow(int $on): string {
    $out = '<ol class="flow">';
    foreach (['Coș', 'Date și plată', 'Confirmare'] as $i => $label) {
        $cls = ($i + 1) < $on ? 'is-done' : (($i + 1) === $on ? 'is-on' : '');
        $out .= '<li class="' . $cls . '">' . $label . '</li>';
    }
    return $out . '</ol>';
}
