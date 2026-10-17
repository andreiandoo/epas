<?php
require_once TEMPLATE_DIR . '/lib.php';
$events = comp_events();
$next   = $events[0] ?? null;
$eventsUrl = ts_link('events');
// Butonul principal: calendarul (mai multe competiții), competiția (una) sau linkul din panou (niciuna)
if (count($events) > 1) {
    $ctaUrl = $eventsUrl;
    $ctaLabel = $s['button_label'] ?? 'Vezi competițiile';
} elseif ($next) {
    $ctaUrl = ts_link('event', ['slug' => $next['slug']]);
    $ctaLabel = $s['button_single_label'] ?? 'Cumpără bilete';
} else {
    $ctaUrl = ts_href($s['empty_url'] ?? '', $eventsUrl);
    $ctaLabel = $s['empty_label'] ?? 'Calendarul competițiilor';
}
?>
<section class="hero">
    <?php if (!empty($s['show_3d'])): ?>
    <canvas class="hero__canvas" data-hero3d aria-hidden="true"></canvas>
    <script type="module" src="<?= e(ts_asset('hero3d.js')) ?>"></script>
    <?php endif; ?>
    <div class="hero__veil"></div>
    <div class="wrap hero__inner">
        <div>
            <span class="label" data-enter="0"><?= e(($s['eyebrow'] ?? '') !== '' ? $s['eyebrow'] : SITE_NAME) ?></span>
            <h1 class="hero__title" data-split data-split-now><?= comp_md($s['title'] ?? SITE_NAME) ?></h1>
        </div>
        <div class="hero__foot">
            <div data-enter="0.5">
                <?php if (!empty($s['lead'])): ?><p class="hero__lead"><?= e($s['lead']) ?></p><?php endif; ?>
                <div class="hero__cta">
                    <?= part_btn((string) $ctaLabel, $ctaUrl) ?>
                    <?php if (!empty($s['secondary_label']) && !empty($s['secondary_url'])): ?>
                    <a class="btn btn--ghost" href="<?= e(ts_href($s['secondary_url'])) ?>"><?= e($s['secondary_label']) ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($next && !empty($s['show_next'])): ?>
            <a class="next" href="<?= e(ts_link('event', ['slug' => $next['slug']])) ?>" data-enter="0.75">
                <div class="next__poster"><?= part_poster($next, 'eager') ?></div>
                <div class="next__body">
                    <span class="label label--red">Urmează</span>
                    <h2 class="next__title"><?= e($next['title']) ?></h2>
                    <p class="next__meta"><?= e(ev_date_label($next)) ?><br><?= e(ev_place($next)) ?></p>
                </div>
                <div class="next__row">
                    <?= part_countdown($next) ?>
                    <span class="btn btn--sm">Bilete<span class="btn__arrow"><?= ICON_ARROW ?></span></span>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="hero__scroll" aria-hidden="true"><i></i>Derulează</div>
</section>
