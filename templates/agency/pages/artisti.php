<?php
/**
 * /artisti: tot rosterul, cu căutare și filtre după tip, gen și teritoriu (rosterPage din
 * assets/fx.js, în browser). Filtrele se țin în adresă (?tip=&gen=&teritoriu=&q=), ca o
 * listă filtrată să poată fi trimisă mai departe.
 */
require_once TEMPLATE_DIR . '/lib.php';
$roster = ag_roster();
$genres = $terr = $kinds = [];
foreach ($roster as $a) {
    foreach ((array) ($a['genres'] ?? []) as $g) { $genres[$g] = true; }
    foreach ((array) ($a['territories'] ?? []) as $x) { $terr[$x] = true; }
    $kinds[$a['kind'] ?? 'solo'] = ag_kind($a['kind'] ?? null);
}
ksort($genres);
$cards = array_map(fn ($a) => [
    'slug' => $a['slug'], 'name' => $a['name'], 'kind' => $a['kind'] ?? 'solo', 'genres' => array_values((array) ($a['genres'] ?? [])),
    'territories' => array_values((array) ($a['territories'] ?? [])), 'city' => (string) ($a['city'] ?? ''), 'html' => ag_card($a),
], $roster);
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;

$pageTitle = t('Artiști') . ' — ' . SITE_NAME;
$pageDescription = t('Rosterul {name}: {n} artiști și trupe pentru concerte, festivaluri și evenimente private.', ['name' => SITE_NAME, 'n' => count($roster)]);
$pageSchema = [[
    '@type' => 'ItemList', 'name' => t('Artiști') . ' — ' . SITE_NAME,
    'itemListElement' => array_map(fn ($a, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => ts_abs_url(ag_artist_url($a)), 'name' => $a['name']], $roster, array_keys($roster)),
], ts_breadcrumb_schema([[t('Acasă'), '/'], [t('Artiști'), ts_link('artists')]])];
$headSolid = true;
include ts_layout('head');
include ts_layout('header');
?>
<section class="ag-page-head">
    <div class="ag-wrap">
        <p class="ag-eyebrow"><?= e(SITE_NAME) ?></p>
        <h1 class="ag-h1"><?= te('Artiști') ?></h1>
        <p class="ag-lead"><?= te('{n} artiști și trupe pe care îi reprezentăm pentru concerte, festivaluri și evenimente private.', ['n' => count($roster)]) ?></p>
    </div>
</section>
<section class="ag-section ag-section--tight" x-data="rosterPage(<?= e(json_encode(['cards' => $cards], $flags)) ?>)">
    <div class="ag-wrap">
        <?php if (!$roster): ?>
        <p class="ag-empty"><?= te('Rosterul se anunță în curând.') ?></p>
        <?php else: ?>
        <div class="ag-filters" role="search">
            <label class="ag-search"><span class="sr-only"><?= te('Caută un artist') ?></span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <input type="search" x-model.debounce.200ms="q" placeholder="<?= te('Caută după nume, gen, oraș') ?>"></label>
            <label class="ag-select"><span><?= te('Tip') ?></span><select x-model="kind"><option value=""><?= te('Toate') ?></option><?php foreach ($kinds as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
            <label class="ag-select"><span><?= te('Gen') ?></span><select x-model="genre"><option value=""><?= te('Toate') ?></option><?php foreach (array_keys($genres) as $g): ?><option value="<?= e($g) ?>"><?= e($g) ?></option><?php endforeach; ?></select></label>
            <?php if ($terr): ?>
            <label class="ag-select"><span><?= te('Teritoriu') ?></span><select x-model="territory"><option value=""><?= te('Oriunde') ?></option><?php foreach (array_keys($terr) as $x): ?><option value="<?= e($x) ?>"><?= e($x) ?></option><?php endforeach; ?></select></label>
            <?php endif; ?>
            <p class="ag-filters__n" aria-live="polite"><span x-text="list.length"></span> / <?= count($roster) ?></p>
        </div>
        <ul class="ag-cards ag-cards--all">
            <template x-for="c in list" :key="c.slug"><li x-html="c.html"></li></template>
        </ul>
        <div class="ag-empty" x-show="!list.length" x-cloak>
            <p><?= te('Niciun artist pentru filtrele alese.') ?></p>
            <button type="button" class="ag-btn ag-btn--ghost" @click="reset()"><?= te('Șterge filtrele') ?></button>
        </div>
        <noscript><ul class="ag-cards ag-cards--all"><?php foreach ($roster as $a): ?><li><?= ag_card($a) ?></li><?php endforeach; ?></ul></noscript>
        <?php endif; ?>
    </div>
</section>
<section class="ag-cta ag-cta--slim">
    <div class="ag-wrap">
        <h2 class="ag-cta__title"><?= te('Nu știi pe cine să alegi?') ?></h2>
        <p class="ag-lead"><?= te('Spune-ne despre eveniment și îți propunem noi artiștii potriviți.') ?></p>
        <a href="/booking" class="ag-btn ag-btn--lg"><?= te('Cere un booking') ?> <?= AG_ARROW ?></a>
    </div>
</section>
<?php include ts_layout('footer');
