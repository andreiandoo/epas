<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// Spectacole în curând (toate) + evidențiate
$all      = api_get('/tenant-client/events', ['limit' => 8]);
$events   = $all['success'] ? ($all['data'] ?? []) : [];
$featResp = api_get('/tenant-client/events/featured', ['limit' => 1]);
$featured = $featResp['success'] ? ($featResp['data'][0] ?? null) : null;
if (!$featured && !empty($events)) { $featured = $events[0]; }

$fallback = 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=1600&q=80';

function ev_img($e, $fb) { return asset_url($e['hero_image_url'] ?? $e['poster_url'] ?? null, $fb); }
function ev_date($e) {
    if (empty($e['start_date'])) return '';
    $ts = strtotime($e['start_date']); if (!$ts) return '';
    return date('d.m.Y', $ts) . (!empty($e['start_time']) ? ' · ' . substr($e['start_time'],0,5) : '');
}

$pageTitle = SITE_NAME . ' — Stagiunea curentă';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<!-- Hero -->
<section class="relative min-h-[80vh] flex items-center">
    <div class="absolute inset-0 overflow-hidden">
        <img src="<?= e($featured ? ev_img($featured, $fallback) : $fallback) ?>" alt="" class="w-full h-full object-cover opacity-40">
        <div class="absolute inset-0 bg-gradient-to-t from-midnight via-midnight/60 to-midnight/30"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 lg:px-8 w-full pt-24">
        <p class="text-gold tracking-[0.3em] text-sm mb-4 uppercase">Stagiunea curentă</p>
        <h1 class="font-display text-5xl lg:text-7xl mb-6 max-w-3xl leading-tight">
            <?= e($featured['title'] ?? SITE_NAME) ?>
        </h1>
        <?php if ($featured && !empty($featured['short_description'])): ?>
            <p class="text-ivory/70 text-lg max-w-2xl mb-8"><?= e($featured['short_description']) ?></p>
        <?php else: ?>
            <p class="text-ivory/70 text-lg max-w-2xl mb-8">Descoperiți spectacolele stagiunii — bilete cu loc numerotat, abonamente și evenimente speciale.</p>
        <?php endif; ?>
        <div class="flex flex-wrap gap-4">
            <?php if ($featured && !empty($featured['slug'])): ?>
                <a href="/show.php?slug=<?= e($featured['slug']) ?>" class="btn-gold px-8 py-4 rounded-lg text-lg">Cumpără bilete</a>
            <?php endif; ?>
            <a href="/repertoire.php" class="btn-outline px-8 py-4 rounded-lg text-lg">Vezi repertoriul</a>
        </div>
    </div>
</section>

<!-- Spectacole în curând -->
<section class="py-20 px-4 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <div class="divider-ornate mb-10"><span class="text-gold font-display text-2xl">Spectacole în curând</span></div>
        <?php if (empty($events)): ?>
            <p class="text-center text-warm-gray py-12">Momentan nu sunt spectacole publicate. Reveniți în curând.</p>
        <?php else: ?>
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($events as $e): ?>
            <a href="/show.php?slug=<?= e($e['slug'] ?? '') ?>" class="show-card rounded-lg overflow-hidden group">
                <div class="relative aspect-[3/4]">
                    <img src="<?= e(ev_img($e, $fallback)) ?>" alt="<?= e($e['title'] ?? '') ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <div class="absolute inset-0 bg-gradient-to-t from-midnight via-transparent to-transparent"></div>
                    <?php if (!empty($e['is_sold_out'])): ?><span class="absolute top-4 left-4 bg-gold text-midnight px-3 py-1 rounded text-xs font-bold">SOLD OUT</span><?php endif; ?>
                    <div class="absolute bottom-0 left-0 right-0 p-5">
                        <?php if (!empty($e['category']['name'])): ?><p class="text-gold text-sm mb-1"><?= e($e['category']['name']) ?></p><?php endif; ?>
                        <h3 class="font-display text-xl mb-1"><?= e($e['title'] ?? '') ?></h3>
                        <p class="text-ivory/60 text-sm"><?= e(ev_date($e)) ?></p>
                    </div>
                </div>
                <div class="p-4 flex items-center justify-between border-t border-gold/10">
                    <p class="text-warm-gray text-sm"><?= e($e['venue']['name'] ?? '') ?></p>
                    <?php if (!empty($e['price_from'])): ?><p class="text-gold font-display">de la <?= e((string)$e['price_from']) ?> <?= e($e['currency'] ?? 'RON') ?></p><?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-10"><a href="/repertoire.php" class="btn-outline px-8 py-3 rounded-lg inline-block">Toate spectacolele</a></div>
        <?php endif; ?>
    </div>
</section>

<!-- Abonamente teaser -->
<section class="py-20 px-4 lg:px-8 bg-charcoal/30">
    <div class="max-w-4xl mx-auto text-center">
        <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Fidelitate</p>
        <h2 class="font-display text-4xl mb-4">Abonamente de stagiune</h2>
        <p class="text-ivory/70 mb-8 max-w-2xl mx-auto">Păstrați același loc la toate spectacolele stagiunii. Reduceri pentru elevi, studenți și pensionari.</p>
        <a href="/subscriptions.php" class="btn-gold px-8 py-4 rounded-lg inline-block">Vezi abonamentele</a>
    </div>
</section>
<?php
include __DIR__ . '/includes/footer.php';
