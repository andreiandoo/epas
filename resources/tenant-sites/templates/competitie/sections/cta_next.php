<?php
require_once TEMPLATE_DIR . '/lib.php';
$next = comp_events()[0] ?? null;
if (!$next) {
    return;
}
?>
<section class="cta">
    <?php if (!empty($s['ghost'])): ?><div class="cta__ghost" data-drift="14" aria-hidden="true"><?= e($s['ghost']) ?></div><?php endif; ?>
    <div class="wrap">
        <h2 data-split><?= e($next['title']) ?></h2>
        <p data-reveal><?= e(ev_date_label($next)) ?><?= ev_place($next) !== '' ? ' · ' . e(ev_place($next)) : '' ?></p>
        <div data-reveal><?= part_btn((string) ($s['button_label'] ?? 'Cumpără bilete'), ts_link('event', ['slug' => $next['slug']]), 'btn--light') ?></div>
    </div>
</section>
