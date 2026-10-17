<?php
require_once TEMPLATE_DIR . '/lib.php';
$spot = comp_spot(trim((string) ($s['event_slug'] ?? '')));
if (!$spot) {
    return;
}
$spotTypes = array_values(array_filter((array) ($spot['ticket_types'] ?? []), fn ($t) => ($t['status'] ?? 'active') === 'active'));
usort($spotTypes, fn ($a, $b) => ($a['id'] ?? 0) <=> ($b['id'] ?? 0));
$spotDays = ev_days_count($spot);
$lead = trim(strip_tags((string) ($spot['short_description'] ?? '')));
$url = ts_link('event', ['slug' => $spot['slug']]);
?>
<section class="sec sec--light">
    <div class="wrap">
        <div class="spot">
            <div class="spot__poster" data-reveal>
                <a class="poster3d__in" href="<?= e($url) ?>" data-tilt="8" style="display:block">
                    <?= part_poster($spot) ?>
                    <span class="pcard__glare" aria-hidden="true"></span>
                </a>
            </div>
            <div>
                <span class="label" data-reveal><?= e(ev_kind($spot)) ?> · <?= $spotDays > 1 ? $spotDays . ' zile de concurs' : 'o zi de concurs' ?></span>
                <h2 data-split><?= e($spot['title']) ?></h2>
                <?php if ($lead !== ''): ?><p class="spot__lead" data-reveal><?= e($lead) ?></p><?php endif; ?>
                <ul class="spot__facts" data-reveal>
                    <li><?= ICON_CAL ?><?= e(ev_date_label($spot)) ?><?= ev_time($spot) ? ' · ' . e(ev_time($spot)) : '' ?></li>
                    <?php if (ev_place($spot) !== ''): ?><li><?= ICON_PIN ?><?= e(ev_place($spot)) ?></li><?php endif; ?>
                </ul>
                <?php if ($spotTypes): ?>
                <ul class="spot__prices" data-reveal>
                    <?php foreach ($spotTypes as $t): ?>
                    <li><span><?= e($t['name'] ?? '') ?></span><b><?= e(lei($t['price'] ?? 0)) ?></b></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
                <div class="spot__cta" data-reveal>
                    <?= part_btn(!empty($spot['is_sold_out']) ? 'Vezi competiția' : (string) ($s['button_label'] ?? 'Alege biletele'), $url . (empty($spot['is_sold_out']) ? '#bilete' : '')) ?>
                    <?= part_countdown($spot) ?>
                </div>
            </div>
        </div>
    </div>
</section>
