<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$all      = api_get('/tenant-client/events', ['limit' => 9]);
$events   = $all['success'] ? ($all['data'] ?? []) : [];
$featResp = api_get('/tenant-client/events/featured', ['limit' => 1]);
$featured = $featResp['success'] ? ($featResp['data'][0] ?? null) : null;
if (!$featured && !empty($events)) { $featured = $events[0]; }
// Excludem evenimentul featured din grila "în program"
$grid = array_values(array_filter($events, fn($e) => !$featured || ($e['slug'] ?? '') !== ($featured['slug'] ?? '')));
$grid = array_slice($grid, 0, 6);

$fallback = 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=1600&q=80';
function ev_img($e, $fb) { return asset_url($e['hero_image_url'] ?? $e['poster_url'] ?? null, $fb); }
function ev_date($e) {
    if (empty($e['start_date'])) return '';
    $ts = strtotime($e['start_date']); if (!$ts) return '';
    $zi = ['Duminică','Luni','Marți','Miercuri','Joi','Vineri','Sâmbătă'][(int)date('w',$ts)];
    return $zi . ', ' . date('d.m', $ts) . (!empty($e['start_time']) ? ' • ' . substr($e['start_time'],0,5) : '');
}

$pageTitle = SITE_NAME . ' — Stagiunea curentă';
$pageExtraStyles = '
    .curtain-texture::before { content:""; position:absolute; inset:0; background: repeating-linear-gradient(90deg,transparent,transparent 2px,rgba(114,47,55,.03) 2px,rgba(114,47,55,.03) 4px); pointer-events:none; }
    .spotlight { background: radial-gradient(ellipse at top, rgba(212,175,55,.06), transparent 60%); }
';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<!-- Hero -->
<section class="relative min-h-screen flex items-center justify-center overflow-hidden curtain-texture">
    <div class="absolute inset-0">
        <img src="<?= e($featured ? ev_img($featured, $fallback) : $fallback) ?>" alt="" class="w-full h-full object-cover opacity-40">
        <div class="absolute inset-0 bg-gradient-to-t from-midnight via-midnight/80 to-midnight/40"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-burgundy/20 via-transparent to-burgundy/20"></div>
    </div>
    <div class="relative z-10 text-center px-4 max-w-4xl mx-auto pt-20">
        <p class="text-gold tracking-[0.3em] text-sm mb-6 uppercase">Stagiunea curentă</p>
        <h1 class="font-display text-5xl sm:text-7xl lg:text-8xl mb-6 leading-tight">
            <span class="italic">Arta</span> care<br><span class="text-gold-gradient">transformă</span> suflete
        </h1>
        <p class="text-xl text-ivory/70 max-w-2xl mx-auto mb-10">Peste un secol de tradiție teatrală. Spectacole care emoționează, inspiră și provoacă gândirea.</p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="/program" class="btn-gold px-8 py-4 rounded text-lg">Vezi programul</a>
            <a href="/abonamente" class="btn-outline px-8 py-4 rounded text-lg">Abonamente stagiune</a>
        </div>
    </div>
    <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 text-gold/50">
        <span class="text-xs tracking-widest uppercase">Descoperă</span>
        <svg class="w-5 h-5 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
    </div>
</section>

<?php if ($featured): ?>
<!-- Featured Show -->
<section class="py-24 px-4 lg:px-8 spotlight">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-16">
            <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Spotlight</p>
            <h2 class="font-display text-4xl lg:text-5xl">Spectacolul lunii</h2>
        </div>
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <div class="relative">
                <div class="absolute -inset-4 bg-gradient-to-r from-burgundy/20 to-gold/20 blur-2xl opacity-50"></div>
                <img src="<?= e(ev_img($featured, $fallback)) ?>" alt="<?= e($featured['title'] ?? '') ?>" class="relative rounded-lg w-full aspect-[4/5] object-cover border border-gold/20">
            </div>
            <div>
                <?php if (!empty($featured['category']['name'])): ?><p class="text-gold tracking-wider text-sm mb-2 uppercase"><?= e($featured['category']['name']) ?></p><?php endif; ?>
                <h3 class="font-display text-5xl lg:text-6xl mb-6 italic"><?= e($featured['title'] ?? '') ?></h3>
                <?php if (!empty($featured['short_description'])): ?><p class="text-ivory/70 text-lg leading-relaxed mb-6"><?= e($featured['short_description']) ?></p><?php endif; ?>
                <div class="grid grid-cols-2 gap-4 mb-8">
                    <?php if (!empty($featured['venue']['name'])): ?><div><p class="text-warm-gray text-sm">Locație</p><p class="font-display text-xl"><?= e($featured['venue']['name']) ?></p></div><?php endif; ?>
                    <?php if (ev_date($featured)): ?><div><p class="text-warm-gray text-sm">Data</p><p class="font-display text-xl"><?= e(ev_date($featured)) ?></p></div><?php endif; ?>
                    <?php if (!empty($featured['artists'][0]['name'])): ?><div><p class="text-warm-gray text-sm">În distribuție</p><p class="font-display text-xl"><?= e($featured['artists'][0]['name']) ?></p></div><?php endif; ?>
                    <?php if (!empty($featured['price_from'])): ?><div><p class="text-warm-gray text-sm">Prețuri de la</p><p class="font-display text-xl text-gold"><?= e((string)$featured['price_from']) ?> <?= e($featured['currency'] ?? 'RON') ?></p></div><?php endif; ?>
                </div>
                <div class="flex gap-4">
                    <a href="/spectacol/<?= e($featured['slug'] ?? '') ?>" class="btn-gold px-6 py-3 rounded">Cumpără bilete</a>
                    <a href="/spectacol/<?= e($featured['slug'] ?? '') ?>" class="btn-outline px-6 py-3 rounded">Detalii spectacol</a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Upcoming Shows -->
<section class="py-24 px-4 lg:px-8 bg-charcoal/50">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-12">
            <div>
                <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Repertoriu</p>
                <h2 class="font-display text-4xl">Spectacole în program</h2>
            </div>
            <a href="/repertoriu" class="btn-outline px-5 py-2.5 rounded hidden sm:block">Vezi tot repertoriul →</a>
        </div>
        <?php if (empty($grid)): ?>
            <p class="text-center text-warm-gray py-12">Momentan nu sunt spectacole publicate. Reveniți în curând.</p>
        <?php else: ?>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($grid as $e): ?>
            <a href="/spectacol/<?= e($e['slug'] ?? '') ?>" class="show-card rounded-lg overflow-hidden group">
                <div class="relative aspect-[3/4]">
                    <img src="<?= e(ev_img($e, $fallback)) ?>" alt="<?= e($e['title'] ?? '') ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    <div class="absolute inset-0 bg-gradient-to-t from-midnight via-transparent to-transparent"></div>
                    <?php if (!empty($e['is_sold_out'])): ?><div class="absolute top-4 right-4 bg-gold text-midnight px-3 py-1 rounded text-xs font-bold">SOLD OUT</div><?php endif; ?>
                    <div class="absolute bottom-0 left-0 right-0 p-6">
                        <?php if (!empty($e['category']['name'])): ?><p class="text-gold text-sm mb-1"><?= e($e['category']['name']) ?></p><?php endif; ?>
                        <h3 class="font-display text-2xl mb-2"><?= e($e['title'] ?? '') ?></h3>
                        <p class="text-ivory/60 text-sm"><?= e($e['venue']['name'] ?? '') ?></p>
                    </div>
                </div>
                <div class="p-5 flex items-center justify-between border-t border-gold/10">
                    <div><p class="text-warm-gray text-xs">Reprezentație</p><p class="font-semibold"><?= e(ev_date($e) ?: '—') ?></p></div>
                    <?php if (!empty($e['price_from'])): ?><p class="text-gold font-display text-lg">de la <?= e((string)$e['price_from']) ?> <?= e($e['currency'] ?? 'RON') ?></p><?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Abonamente -->
<section class="py-24 px-4 lg:px-8">
    <div class="max-w-4xl mx-auto text-center">
        <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Fidelitate</p>
        <h2 class="font-display text-4xl mb-4">Abonamente de stagiune</h2>
        <p class="text-ivory/70 mb-8 max-w-2xl mx-auto">Păstrați același loc la toate spectacolele stagiunii. Reduceri pentru elevi, studenți și pensionari.</p>
        <a href="/abonamente" class="btn-gold px-8 py-4 rounded-lg inline-block">Vezi abonamentele</a>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php';
