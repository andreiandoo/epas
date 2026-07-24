<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// Evenimente reale ale tenantului (Repertoriu = toate cele viitoare)
$resp   = api_get('/tenant-client/events', ['limit' => 60]);
$events = $resp['success'] ? ($resp['data'] ?? []) : [];

$fallbackImg = 'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=600&q=80';

// Mapare la forma de card + colectare categorii pentru filtre
$shows = [];
$cats  = [];
foreach ($events as $e) {
    $catSlug = $e['category']['slug'] ?? '';
    $catName = $e['category']['name'] ?? 'Spectacol';
    if ($catSlug && !isset($cats[$catSlug])) { $cats[$catSlug] = $catName; }

    $shows[] = [
        'slug'      => $e['slug'] ?? '',
        'title'     => $e['title'] ?? 'Spectacol',
        'author'    => $catName,
        'genre'     => $catName,
        'venue'     => $e['venue']['name'] ?? '',
        'price'     => $e['price_from'] ?? null,
        'currency'  => $e['currency'] ?? 'RON',
        'image'     => asset_url($e['poster_url'] ?? $e['hero_image_url'] ?? null, $fallbackImg),
        'isSoldOut' => (bool) ($e['is_sold_out'] ?? false),
        'category'  => $catSlug,
    ];
}

$pageTitle = 'Repertoriu — ' . SITE_NAME;
$activeNav = 'repertoire';
$pageExtraStyles = '
    .filter-btn { transition: all 0.3s ease; border: 1px solid rgba(212, 175, 55, 0.2); }
    .filter-btn:hover { border-color: #D4AF37; }
    .filter-btn.active { background: #D4AF37; color: #0A0A0A; border-color: #D4AF37; }
';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<div x-data='<?= e(json_encode(["shows" => $shows, "cats" => $cats, "filter" => "all"], JSON_UNESCAPED_UNICODE)) ?>'>
    <!-- Header -->
    <section class="pt-32 pb-16 px-4 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Stagiunea curentă</p>
            <h1 class="font-display text-5xl lg:text-6xl mb-6">Repertoriu</h1>
            <p class="text-ivory/70 text-lg max-w-2xl">
                Descoperiți spectacolele din stagiunea curentă — de la clasici ai dramaturgiei universale la producții contemporane îndrăznețe.
            </p>
        </div>
    </section>

    <!-- Filters -->
    <section class="pb-8 px-4 lg:px-8 sticky top-20 z-40 bg-midnight/95 backdrop-blur-md" x-show="Object.keys(cats).length > 0">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-wrap gap-3">
                <button @click="filter = 'all'" :class="filter === 'all' ? 'active' : ''" class="filter-btn px-5 py-2 rounded-full text-sm">Toate</button>
                <template x-for="(name, slug) in cats" :key="slug">
                    <button @click="filter = slug" :class="filter === slug ? 'active' : ''" class="filter-btn px-5 py-2 rounded-full text-sm" x-text="name"></button>
                </template>
            </div>
        </div>
    </section>

    <!-- Shows Grid -->
    <section class="pb-24 px-4 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <div class="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <template x-for="show in (filter === 'all' ? shows : shows.filter(s => s.category === filter))" :key="show.slug">
                    <a :href="'/show.php?slug=' + show.slug" class="show-card rounded-lg overflow-hidden group">
                        <div class="relative aspect-[3/4]">
                            <img :src="show.image" :alt="show.title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <div class="absolute inset-0 bg-gradient-to-t from-midnight via-transparent to-transparent"></div>
                            <div class="absolute top-4 left-4 flex gap-2">
                                <span x-show="show.isSoldOut" class="bg-gold text-midnight px-3 py-1 rounded text-xs font-bold">SOLD OUT</span>
                            </div>
                            <div class="absolute bottom-0 left-0 right-0 p-5">
                                <p class="text-gold text-sm mb-1" x-text="show.author"></p>
                                <h3 class="font-display text-xl mb-2" x-text="show.title"></h3>
                                <p class="text-ivory/60 text-sm" x-text="show.venue"></p>
                            </div>
                        </div>
                        <div class="p-4 flex items-center justify-between border-t border-gold/10">
                            <p class="text-warm-gray text-sm" x-text="show.venue"></p>
                            <p class="text-gold font-display" x-show="show.price" x-text="'de la ' + show.price + ' ' + show.currency"></p>
                        </div>
                    </a>
                </template>
            </div>

            <!-- Empty state -->
            <div x-show="shows.length === 0" class="text-center py-16">
                <p class="text-warm-gray text-lg">Momentan nu sunt spectacole publicate. Reveniți în curând.</p>
            </div>
        </div>
    </section>
</div>
<?php
if (DEBUG && !$resp['success']) {
    echo '<pre style="color:#f88;padding:1rem;">API error: ' . e($resp['error']) . '</pre>';
}
include __DIR__ . '/includes/footer.php';
