<?php
/**
 * /lineup: toți artiștii, filtrabili pe zi și pe scenă; fișa artistului se deschide
 * peste pagină (bio, genuri, când și unde cântă, ascultă, „în programul meu”).
 * /lineup#a{id} deschide direct fișa (linkurile din afișul de pe prima pagină).
 */
require_once TEMPLATE_DIR . '/lib.php';
$ev = fest_event();
$l = fest_lineup();
$pageTitle = t('Lineup') . ' — ' . SITE_NAME;
$headSolid = true;
$artists = [];
foreach ($l['slots'] as $x) {
    if (empty($x['name'])) {
        continue;
    }
    $artists[] = [
        'id' => (int) ($x['artist']['id'] ?? 0) ?: 's' . $x['id'],
        'slot' => (int) $x['id'],
        'name' => $x['name'],
        'image' => $x['image'] ?? '',
        'portrait' => $x['artist']['portrait'] ?? ($x['image'] ?? ''),
        'tier' => $x['tier'],
        'day' => (int) $x['day_id'],
        'stage' => (int) $x['stage_id'],
        'start' => $x['start'],
        'end' => $x['end'],
        'bio' => trim(strip_tags((string) ($x['artist']['bio'] ?? $x['description'] ?? ''))),
        'genres' => array_values((array) ($x['artist']['genres'] ?? [])),
        'country' => $x['artist']['country'] ?? '',
        'spotify' => $x['artist']['spotify_url'] ?? '',
        'youtube' => $x['artist']['youtube_url'] ?? '',
        'instagram' => $x['artist']['instagram_url'] ?? '',
    ];
}
$tierRank = ['headliner' => 0, 'sub_headliner' => 1, 'regular' => 2, 'support' => 3, 'emerging' => 4];
usort($artists, fn ($a, $b) => [$tierRank[$a['tier']] ?? 9, $a['day'], $a['start']] <=> [$tierRank[$b['tier']] ?? 9, $b['day'], $b['start']]);
$days = array_map(fn ($d) => ['id' => (int) $d['id'], 'name' => $d['name'], 'date' => ts_date($d['date'], false, false)], $l['days']);
$stages = array_map(fn ($s) => ['id' => (int) $s['id'], 'name' => $s['name'], 'color' => $s['color'] ?: ''], $l['stages']);
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
include ts_layout('head');
include ts_layout('header');
?>
<section class="fx-page-head">
    <div class="fx-wrap">
        <p class="fx-eyebrow"><?= e(fest_date_range($ev)) ?></p>
        <h1 class="fx-h1"><?= te('Lineup') ?></h1>
        <p class="fx-lead"><?= te('{n} artiști pe {s} scene, în {d} zile.', ['n' => count(array_unique(array_column($artists, 'name'))), 's' => count($stages), 'd' => count($days)]) ?></p>
    </div>
</section>

<section class="fx-section fx-section--tight" x-data="lineupPage(<?= e(json_encode(['artists' => $artists, 'days' => $days, 'stages' => $stages], $flags)) ?>)">
    <div class="fx-wrap">
        <?php if (!$artists): ?>
        <p class="fx-empty"><?= te('Lineup-ul se anunță în curând.') ?></p>
        <?php else: ?>
        <div class="fx-filters" role="toolbar" aria-label="<?= te('Filtre') ?>">
            <div class="fx-chips">
                <button type="button" @click="day = 0" :class="day === 0 && 'is-on'" :aria-pressed="day === 0"><?= te('Toate zilele') ?></button>
                <template x-for="d in days" :key="d.id"><button type="button" @click="day = d.id" :class="day === d.id && 'is-on'" :aria-pressed="day === d.id"><span x-text="d.name"></span> <small x-text="d.date"></small></button></template>
            </div>
            <div class="fx-chips">
                <button type="button" @click="stage = 0" :class="stage === 0 && 'is-on'" :aria-pressed="stage === 0"><?= te('Toate scenele') ?></button>
                <template x-for="s in stages" :key="s.id"><button type="button" @click="stage = s.id" :class="stage === s.id && 'is-on'" :aria-pressed="stage === s.id" :style="'--stage:' + (s.color || 'rgb(var(--c-primary))')"><i class="fx-dot"></i><span x-text="s.name"></span></button></template>
                <button type="button" @click="favOnly = !favOnly" :class="favOnly && 'is-on'" :aria-pressed="favOnly">♥ <?= te('Programul meu') ?></button>
            </div>
        </div>

        <p class="fx-count" x-text="list.length + ' ' + (list.length === 1 ? TS.t('artist') : TS.t('artiști'))" aria-live="polite"></p>
        <div class="fx-artists">
            <template x-for="a in list" :key="a.slot">
                <button type="button" class="fx-artist" :class="'fx-artist--' + a.tier" @click="open(a)">
                    <span class="fx-artist__img"><img :src="a.image" :alt="a.name" loading="lazy"></span>
                    <span class="fx-artist__meta">
                        <b x-text="a.name"></b>
                        <small x-text="dayName(a.day) + ' · ' + a.start + ' · ' + stageName(a.stage)"></small>
                    </span>
                    <span class="fx-artist__fav" x-show="isFav(a.slot)" aria-label="<?= te('În programul meu') ?>">♥</span>
                </button>
            </template>
        </div>
        <p class="fx-empty" x-show="!list.length" x-cloak><?= te('Niciun artist pentru filtrele alese.') ?></p>
        <?php endif; ?>
    </div>

    <div class="fx-modal" x-show="cur" x-cloak x-transition.opacity @keydown.escape.window="close()" @click.self="close()" role="dialog" aria-modal="true" :aria-label="cur ? cur.name : ''">
        <article class="fx-modal__card" x-show="cur" x-transition>
            <button type="button" class="fx-modal__x" @click="close()" aria-label="<?= te('Închide') ?>">×</button>
            <div class="fx-modal__img"><img :src="cur ? cur.portrait : ''" :alt="cur ? cur.name : ''"></div>
            <div class="fx-modal__body">
                <p class="fx-eyebrow" x-text="cur ? [cur.country, (cur.genres || []).join(', ')].filter(Boolean).join(' · ') : ''"></p>
                <h2 class="fx-h2" x-text="cur ? cur.name : ''"></h2>
                <p class="fx-modal__when" x-text="cur ? dayName(cur.day) + ' · ' + cur.start + '–' + cur.end + ' · ' + stageName(cur.stage) : ''"></p>
                <p class="fx-modal__bio" x-text="cur ? cur.bio : ''"></p>
                <div class="fx-modal__actions">
                    <button type="button" class="fx-btn" @click="toggleFav(cur.slot)" x-text="cur && isFav(cur.slot) ? '♥ ' + TS.t('În programul meu') : '+ ' + TS.t('Adaugă în programul meu')"></button>
                    <a x-show="cur && cur.spotify" :href="cur ? cur.spotify : '#'" target="_blank" rel="noopener" class="fx-btn fx-btn--ghost">Spotify</a>
                    <a x-show="cur && cur.youtube" :href="cur ? cur.youtube : '#'" target="_blank" rel="noopener" class="fx-btn fx-btn--ghost">YouTube</a>
                    <a href="/bilete" class="fx-link"><?= te('Bilete') ?> →</a>
                </div>
            </div>
        </article>
    </div>
</section>
<?php include ts_layout('footer');
