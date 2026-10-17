<?php
/**
 * /program: orarul pe ore, o coloană pe scenă, pentru fiecare zi. Concertele de după
 * miezul nopții rămân în ziua lor (02:30 vineri noaptea e tot „Vineri”). „Programul
 * meu” = favoritele din lineup (localStorage, comune cu /lineup).
 */
require_once TEMPLATE_DIR . '/lib.php';
$ev = fest_event();
$l = fest_lineup();
$pageTitle = t('Program') . ' — ' . SITE_NAME;
$headSolid = true;
$days = [];
foreach ($l['days'] as $d) {
    $days[] = ['id' => (int) $d['id'], 'name' => $d['name'], 'date' => ts_date($d['date'], false, false), 'open' => $d['gates_open'] ?: '15:00'];
}
$stages = array_map(fn ($s) => ['id' => (int) $s['id'], 'name' => $s['name'], 'color' => $s['color'] ?: ''], $l['stages']);
$slots = [];
foreach ($l['slots'] as $x) {
    $slots[] = ['id' => (int) $x['id'], 'day' => (int) $x['day_id'], 'stage' => (int) $x['stage_id'], 'start' => $x['start'], 'end' => $x['end'],
        'name' => $x['name'] ?: t('Invitat surpriză'), 'tier' => $x['tier'], 'artist' => (int) ($x['artist']['id'] ?? 0)];
}
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
include ts_layout('head');
include ts_layout('header');
?>
<section class="fx-page-head">
    <div class="fx-wrap">
        <p class="fx-eyebrow"><?= e(fest_date_range($ev)) ?><?= fest_place($ev) !== '' ? ' · ' . e(fest_place($ev)) : '' ?></p>
        <h1 class="fx-h1"><?= te('Program') ?></h1>
        <p class="fx-lead"><?= te('Cine cântă, unde și la ce oră. Apasă pe un concert ca să-l pui în programul tău.') ?></p>
    </div>
</section>

<section class="fx-section fx-section--tight" x-data="programPage(<?= e(json_encode(['days' => $days, 'stages' => $stages, 'slots' => $slots], $flags)) ?>)">
    <div class="fx-wrap">
        <?php if (!$slots): ?>
        <p class="fx-empty"><?= te('Programul pe ore se anunță în curând.') ?></p>
        <?php else: ?>
        <div class="fx-filters">
            <div class="fx-chips" role="tablist" aria-label="<?= te('Zile') ?>">
                <template x-for="d in days" :key="d.id"><button type="button" role="tab" @click="day = d.id" :class="day === d.id && 'is-on'" :aria-selected="day === d.id"><span x-text="d.name"></span> <small x-text="d.date"></small></button></template>
            </div>
            <div class="fx-chips">
                <button type="button" @click="favOnly = !favOnly" :class="favOnly && 'is-on'" :aria-pressed="favOnly">♥ <?= te('Doar programul meu') ?> <small x-text="'(' + favCount + ')'"></small></button>
            </div>
        </div>

        <div class="fx-tt" :style="'--rows:' + rows + ';--cols:' + stages.length">
            <div class="fx-tt__hours" aria-hidden="true">
                <template x-for="h in hours" :key="h.i"><span :style="'grid-row:' + (h.i * 4 + 2) + ' / span 4'" x-text="h.label"></span></template>
            </div>
            <template x-for="(s, si) in stages" :key="s.id">
                <div class="fx-tt__col" :id="'s' + s.id" :style="'--stage:' + (s.color || 'rgb(var(--c-primary))')">
                    <h3 class="fx-tt__stage" x-text="s.name"></h3>
                    <template x-for="x in slotsFor(s.id)" :key="x.id">
                        <button type="button" class="fx-tt__slot" :class="['fx-tt__slot--' + x.tier, isFav(x.id) && 'is-fav', favOnly && !isFav(x.id) && 'is-dim']"
                                :style="'grid-row:' + rowOf(x.start) + ' / ' + rowOf(x.end, true)" @click="toggleFav(x.id)"
                                :aria-pressed="isFav(x.id)" :aria-label="x.name + ', ' + x.start + '–' + x.end + ', ' + s.name">
                            <b x-text="x.name"></b><small x-text="x.start + '–' + x.end"></small><i x-show="isFav(x.id)">♥</i>
                        </button>
                    </template>
                </div>
            </template>
        </div>
        <p class="fx-note"><?= te('Programul tău se salvează în acest browser.') ?> <a href="/lineup" class="fx-link"><?= te('Vezi artiștii') ?></a></p>
        <?php endif; ?>
    </div>
</section>
<?php include ts_layout('footer');
