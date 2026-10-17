<?php
/**
 * Fight card-ul unei gale: secțiunile în ordine (main event, co-main, main card,
 * preliminarii), fiecare meci cu cei doi luptători față în față. Variabile:
 *   $card   meciurile (gx_card)
 *   $past   gala s-a disputat (arată rezultatele)
 * Click pe un luptător = fișa lui pe /luptatori.
 */
$card = $card ?? [];
$past = $past ?? false;
if (!$card) {
    echo '<p class="gx-empty">' . te('Fight card-ul se anunță în curând.') . '</p>';
    return;
}
$bySection = [];
foreach ($card as $b) {
    $bySection[$b['section']][] = $b;
}
$side = function (array $f, string $corner, ?array $result, bool $big) use ($past): string {
    [$first, $last] = gx_name_parts((string) $f['name']);
    $won = $result && $result['winner'] === ($corner === 'red' ? 'a' : 'b');
    $lost = $result && in_array($result['winner'], ['a', 'b'], true) && !$won;
    $href = !empty($f['id']) ? '/luptatori#f' . (int) $f['id'] : null;
    $tag = $href ? 'a href="' . e($href) . '"' : 'div';
    $out = '<' . $tag . ' class="gx-side gx-side--' . $corner . ($won ? ' is-win' : '') . ($lost ? ' is-loss' : '') . '">';
    $out .= gx_portrait($f, $corner, $big ? 'gx-pic--big' : '');
    $out .= '<span class="gx-side__txt">';
    if (!empty($f['nickname'])) {
        $out .= '<small class="gx-side__nick">' . e(gx_nick($f['nickname'])) . '</small>';
    }
    $out .= '<span class="gx-side__name"><span>' . e($first) . '</span><b>' . e($last) . '</b></span>';
    $meta = array_filter([gx_flag($f['country'] ?? null) . ' ' . ($f['country'] ?? ''), gx_record($f)]);
    if ($meta) {
        $out .= '<small class="gx-side__meta">' . e(implode(' · ', array_map('trim', $meta))) . '</small>';
    }
    if ($won) {
        $out .= '<em class="gx-side__res">' . te('Victorie') . '</em>';
    }
    $out .= '</span></' . ($href ? 'a' : 'div') . '>';
    return $out;
};
?>
<div class="gx-card">
    <?php foreach (['main_event', 'co_main', 'main_card', 'prelims', 'early_prelims'] as $sec): if (empty($bySection[$sec])) { continue; } $big = in_array($sec, ['main_event', 'co_main'], true); ?>
    <div class="gx-card__sec gx-card__sec--<?= $sec ?>">
        <h3 class="gx-card__label"><span><?= e(gx_section_label($sec)) ?></span></h3>
        <?php foreach ($bySection[$sec] as $b): $r = $b['result'] ?? null; ?>
        <article class="gx-bout<?= $big ? ' gx-bout--big' : '' ?><?= $b['title_fight'] ? ' gx-bout--title' : '' ?>" data-reveal>
            <?= $side($b['a'], 'red', $r, $big) ?>
            <div class="gx-bout__mid">
                <?php if ($b['title_fight']): ?><span class="gx-belt"><?= GX_BELT ?><?= e($b['title_name'] ?: t('Meci pentru centură')) ?></span><?php endif; ?>
                <b class="gx-vs">VS</b>
                <span class="gx-bout__wc"><?= e($b['weight_class'] ?? '') ?></span>
                <span class="gx-bout__rd"><?= e(gx_discipline($b['discipline'] ?? null)) ?> · <?= te('{n} runde × {m} min', ['n' => $b['rounds'], 'm' => $b['round_minutes']]) ?></span>
                <?php if ($r): ?>
                <span class="gx-bout__result"><?= e($r['method'] ?? '') ?><?= $r['round'] ? ' · ' . te('R{n}', ['n' => $r['round']]) : '' ?><?= $r['time'] ? ' · ' . e($r['time']) : '' ?></span>
                <?php endif; ?>
            </div>
            <?= $side($b['b'], 'blue', $r, $big) ?>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>
