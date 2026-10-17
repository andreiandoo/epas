<?php
require_once __DIR__ . '/_events.php';
$featured = teatru_featured(trim((string) ($s['event_slug'] ?? '')));
if (!$featured) {
    return;
}
$url = ts_link('event', ['slug' => $featured['slug'] ?? '']);
?>
<section class="py-24 px-4 lg:px-8 spotlight">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-16" data-reveal>
            <?= teatru_eyebrow($s['eyebrow'] ?? '') ?>
            <?php if (!empty($s['title'])): ?><h2 class="font-display text-4xl lg:text-5xl"><?= ts_md($s['title']) ?></h2><?php endif; ?>
        </div>
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <div class="relative" data-reveal="left">
                <div class="absolute -inset-4 bg-gradient-to-r from-accent/20 to-primary/20 blur-2xl opacity-50"></div>
                <img src="<?= e(teatru_ev_img($featured)) ?>" alt="<?= e($featured['title'] ?? '') ?>" class="relative rounded-lg w-full aspect-[4/5] object-cover border border-primary/20">
            </div>
            <div>
                <?php if (!empty($featured['category']['name'])): ?><p class="text-primary tracking-wider text-sm mb-2 uppercase"><?= e($featured['category']['name']) ?></p><?php endif; ?>
                <h3 class="font-display text-5xl lg:text-6xl mb-6 italic"><?= e($featured['title'] ?? '') ?></h3>
                <?php if (!empty($featured['short_description'])): ?><p class="text-fg/70 text-lg leading-relaxed mb-6"><?= e($featured['short_description']) ?></p><?php endif; ?>
                <div class="grid grid-cols-2 gap-4 mb-8">
                    <?php if (!empty($featured['venue']['name'])): ?><div><p class="text-muted text-sm">Locație</p><p class="font-display text-xl"><?= e($featured['venue']['name']) ?></p></div><?php endif; ?>
                    <?php if (teatru_ev_date($featured)): ?><div><p class="text-muted text-sm">Data</p><p class="font-display text-xl"><?= e(teatru_ev_date($featured)) ?></p></div><?php endif; ?>
                    <?php if (!empty($featured['artists'][0]['name'])): ?><div><p class="text-muted text-sm">În distribuție</p><p class="font-display text-xl"><?= e($featured['artists'][0]['name']) ?></p></div><?php endif; ?>
                    <?php if (!empty($featured['price_from'])): ?><div><p class="text-muted text-sm">Prețuri de la</p><p class="font-display text-xl text-primary"><?= e((string) $featured['price_from']) ?> <?= e($featured['currency'] ?? 'RON') ?></p></div><?php endif; ?>
                </div>
                <div class="flex gap-4">
                    <a href="<?= e($url) ?>" class="btn-primary px-6 py-3 rounded">Cumpără bilete</a>
                    <a href="<?= e($url) ?>" class="btn-outline px-6 py-3 rounded">Detalii</a>
                </div>
            </div>
        </div>
    </div>
</section>
