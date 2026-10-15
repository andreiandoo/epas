<?php
require_once __DIR__ . '/_events.php';
$limit = max(1, min(24, (int) ($s['limit'] ?? 6)));
$events = tc_events(api_get('/tenant-client/events', ['per_page' => $limit + 1]));
// Fără spectacolul deja arătat de secțiunea „recomandat” de pe aceeași pagină
$pageSections = [];
foreach ((array) site('pages', []) as $p) {
    if (($p['path'] ?? '') === '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/')) {
        $pageSections = array_column((array) ($p['sections'] ?? []), 'type');
    }
}
if (in_array('featured_event', $pageSections, true) && ($featured = teatru_featured())) {
    $events = array_values(array_filter($events, fn ($ev) => ($ev['slug'] ?? '') !== ($featured['slug'] ?? '')));
}
$grid = array_slice($events, 0, $limit);
?>
<section class="py-24 px-4 lg:px-8 bg-surface/50">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-12 gap-6">
            <div>
                <?= teatru_eyebrow($s['eyebrow'] ?? '') ?>
                <?php if (!empty($s['title'])): ?><h2 class="font-display text-4xl"><?= ts_md($s['title']) ?></h2><?php endif; ?>
            </div>
            <?= teatru_button($s['button_label'] ?? '', $s['button_url'] ?? '', 'btn-outline px-5 py-2.5 rounded hidden sm:block') ?>
        </div>
        <?php if (empty($grid)): ?>
            <p class="text-center text-muted py-12"><?= e($s['empty_text'] ?? 'Momentan nu sunt evenimente publicate.') ?></p>
        <?php else: ?>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($grid as $ev): ?>
            <a href="<?= e(ts_link('event', ['slug' => $ev['slug'] ?? ''])) ?>" class="show-card rounded-lg overflow-hidden group">
                <div class="relative aspect-[3/4]">
                    <img src="<?= e(teatru_ev_img($ev)) ?>" alt="<?= e($ev['title'] ?? '') ?>" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <div class="absolute inset-0 bg-gradient-to-t from-canvas via-transparent to-transparent"></div>
                    <?php if (!empty($ev['is_sold_out'])): ?><div class="absolute top-4 right-4 bg-primary text-on-primary px-3 py-1 rounded text-xs font-bold">SOLD OUT</div><?php endif; ?>
                    <div class="absolute bottom-0 left-0 right-0 p-6">
                        <?php if (!empty($ev['category']['name'])): ?><p class="text-primary text-sm mb-1"><?= e($ev['category']['name']) ?></p><?php endif; ?>
                        <h3 class="font-display text-2xl mb-2"><?= e($ev['title'] ?? '') ?></h3>
                        <p class="text-fg/60 text-sm"><?= e($ev['venue']['name'] ?? '') ?></p>
                    </div>
                </div>
                <div class="p-5 flex items-center justify-between border-t border-primary/10">
                    <div><p class="text-muted text-xs">Reprezentație</p><p class="font-semibold"><?= e(teatru_ev_date($ev) ?: '—') ?></p></div>
                    <?php if (!empty($ev['price_from'])): ?><p class="text-primary font-display text-lg">de la <?= e((string) $ev['price_from']) ?> <?= e($ev['currency'] ?? 'RON') ?></p><?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="mt-10 text-center sm:hidden"><?= teatru_button($s['button_label'] ?? '', $s['button_url'] ?? '', 'btn-outline px-5 py-2.5 rounded inline-block') ?></div>
        <?php endif; ?>
    </div>
</section>
