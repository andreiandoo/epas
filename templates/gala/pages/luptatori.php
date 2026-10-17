<?php
/**
 * /luptatori: toți luptătorii, pe categorii; fișa se deschide peste pagină (record,
 * date fizice, echipă, bio, ultimele meciuri). /luptatori#f{id} o deschide direct.
 */
require_once TEMPLATE_DIR . '/lib.php';
$pageTitle = t('Luptători') . ' — ' . SITE_NAME;
$headSolid = true;
$fighters = [];
foreach (gx_fighters() as $f) {
    [$first, $last] = gx_name_parts($f['name']);
    $fighters[] = $f + ['first' => $first, 'last' => $last, 'flag' => gx_flag($f['country'] ?? null), 'rec' => gx_record($f),
        'initials' => gx_initials($f['name']), 'nickq' => gx_nick($f['nickname'] ?? null), 'bioText' => trim(strip_tags((string) ($f['bio'] ?? '')))];
}
$classes = array_values(array_unique(array_filter(array_column($fighters, 'weight_class'))));
$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT;
include ts_layout('head');
include ts_layout('header');
?>
<section class="gx-page-head">
    <div class="gx-wrap">
        <p class="gx-eyebrow"><?= e(SITE_NAME) ?></p>
        <h1 class="gx-h1"><?= te('Luptători') ?></h1>
        <p class="gx-lead"><?= te('{n} luptători în {c} categorii.', ['n' => count($fighters), 'c' => count($classes)]) ?></p>
    </div>
</section>
<section class="gx-section gx-section--tight" x-data="fightersPage(<?= e(json_encode(['fighters' => $fighters, 'classes' => $classes], $flags)) ?>)">
    <div class="gx-wrap">
        <?php if (!$fighters): ?>
        <p class="gx-empty"><?= te('Lista luptătorilor se anunță în curând.') ?></p>
        <?php else: ?>
        <div class="gx-chips" role="toolbar" aria-label="<?= te('Categorii') ?>">
            <button type="button" @click="wc = ''" :class="!wc && 'is-on'" :aria-pressed="!wc"><?= te('Toate categoriile') ?></button>
            <template x-for="c in classes" :key="c"><button type="button" @click="wc = c" :class="wc === c && 'is-on'" :aria-pressed="wc === c" x-text="c"></button></template>
        </div>
        <div class="gx-roster">
            <template x-for="f in list" :key="f.id">
                <button type="button" class="gx-fcard" :class="f.is_champion && 'is-champ'" @click="open(f)">
                    <span class="gx-pic gx-pic--card" :class="!f.photo && 'gx-pic--mono'">
                        <template x-if="f.photo"><img :src="f.photo" :alt="f.name" loading="lazy"></template>
                        <template x-if="!f.photo"><span class="gx-mono"><b x-text="f.initials"></b><i x-text="f.flag"></i></span></template>
                    </span>
                    <span class="gx-fcard__txt">
                        <small x-show="f.nickname" x-text="f.nickq"></small>
                        <span x-text="f.first"></span><b x-text="f.last"></b>
                        <em x-text="[f.flag + ' ' + (f.country || ''), f.rec, f.weight_class].filter(Boolean).join(' · ')"></em>
                    </span>
                    <span class="gx-fcard__belt" x-show="f.is_champion"><?= GX_BELT ?></span>
                </button>
            </template>
        </div>
        <?php endif; ?>
    </div>

    <div class="gx-modal" x-show="cur" x-cloak x-transition.opacity @keydown.escape.window="close()" @click.self="close()" role="dialog" aria-modal="true" :aria-label="cur ? cur.name : ''">
        <article class="gx-modal__card" x-show="cur">
            <button type="button" class="gx-modal__x" @click="close()" aria-label="<?= te('Închide') ?>">×</button>
            <div class="gx-modal__pic">
                <template x-if="cur && cur.photo"><img :src="cur.photo" :alt="cur.name"></template>
                <template x-if="cur && !cur.photo"><span class="gx-mono gx-mono--xl"><b x-text="cur.initials"></b><i x-text="cur.flag"></i></span></template>
            </div>
            <div class="gx-modal__body" x-show="cur">
                <p class="gx-eyebrow" x-text="cur ? [cur.weight_class, cur.team].filter(Boolean).join(' · ') : ''"></p>
                <h2 class="gx-h2"><small class="gx-modal__nick" x-show="cur && cur.nickname" x-text="cur ? cur.nickq : ''"></small><span x-text="cur ? cur.first : ''"></span> <b x-text="cur ? cur.last : ''"></b></h2>
                <p class="gx-modal__champ" x-show="cur && cur.is_champion"><?= GX_BELT ?> <?= te('Campion en titre') ?></p>
                <dl class="gx-stats">
                    <div><dt><?= te('Record') ?></dt><dd x-text="cur ? cur.rec : ''"></dd></div>
                    <div><dt><?= te('Țara') ?></dt><dd x-text="cur ? (cur.flag + ' ' + (cur.country || '')) : ''"></dd></div>
                    <div x-show="cur && cur.age"><dt><?= te('Vârsta') ?></dt><dd x-text="cur ? cur.age : ''"></dd></div>
                    <div x-show="cur && cur.height_cm"><dt><?= te('Înălțime') ?></dt><dd x-text="cur ? cur.height_cm + ' cm' : ''"></dd></div>
                    <div x-show="cur && cur.reach_cm"><dt><?= te('Anvergură') ?></dt><dd x-text="cur ? cur.reach_cm + ' cm' : ''"></dd></div>
                    <div x-show="cur && cur.stance"><dt><?= te('Gardă') ?></dt><dd x-text="cur ? cur.stance : ''"></dd></div>
                </dl>
                <p class="gx-modal__bio" x-text="cur ? cur.bioText : ''"></p>
                <template x-if="cur && cur.fights && cur.fights.length">
                    <div class="gx-history">
                        <h3><?= te('Ultimele meciuri') ?></h3>
                        <template x-for="h in cur.fights" :key="h.event + h.opponent">
                            <a :href="'/gala/' + h.event_slug" class="gx-history__row">
                                <b :class="'is-' + h.outcome" x-text="{ win: TS.t('V'), loss: TS.t('Î'), draw: TS.t('E'), nc: 'NC' }[h.outcome]"></b>
                                <span><span x-text="TS.t('vs') + ' ' + h.opponent"></span><small x-text="h.event"></small></span>
                                <em x-text="[h.method, h.round ? 'R' + h.round : ''].filter(Boolean).join(' · ')"></em>
                            </a>
                        </template>
                    </div>
                </template>
            </div>
        </article>
    </div>
</section>
<?php include ts_layout('footer');
