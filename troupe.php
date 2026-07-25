<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Trupa — ' . SITE_NAME;
$activeNav = 'troupe';

// Date reale din core (tenant-client artists)
$rawArtists = tc_artists();

// Derivă tipul pentru filtre din rolul textual
$deriveType = function (?string $role): string {
    $r = mb_strtolower((string) $role);
    if (preg_match('/regiz|director/u', $r)) { return 'director'; }
    if (preg_match('/scenograf|scenog/u', $r)) { return 'scenographer'; }
    return 'actor';
};

$featured = [];
$grid = [];
foreach ($rawArtists as $a) {
    $item = [
        'id'       => $a['id'] ?? null,
        'slug'     => $a['slug'] ?? (string) ($a['id'] ?? ''),
        'name'     => $a['name'] ?? '',
        'category' => $a['role'] ?? '',
        'title'    => mb_strtoupper((string) ($a['role'] ?? 'ARTIST')),
        'type'     => $deriveType($a['role'] ?? null),
        'image'    => $a['photo_url'] ?? null,
    ];
    $grid[] = $item;
    if (!empty($a['is_resident'])) { $featured[] = $item; }
}
// Maxim 4 în banda „de onoare"
$featured = array_slice($featured, 0, 4);

$pageExtraStyles = '
    .actor-card { transition: all .4s ease; }
    .actor-card:hover { transform: translateY(-8px); }
    .actor-card:hover img { transform: scale(1.05); }
    .filter-btn { transition: all .3s ease; border: 1px solid rgba(212,175,55,.2); }
    .filter-btn:hover { border-color: #D4AF37; }
    .filter-btn.active { background: #D4AF37; color: #0A0A0A; border-color: #D4AF37; }
';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<div x-data="troupePage()">
    <!-- Header -->
    <section class="pt-32 pb-12 px-4 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase">Artiștii noștri</p>
            <h1 class="font-display text-5xl lg:text-6xl mb-6">Trupa</h1>
            <p class="text-ivory/70 text-lg max-w-2xl">Actori consacrați și tinere talente care dau viață scenei.</p>
        </div>
    </section>

    <!-- Filters -->
    <section class="pb-8 px-4 lg:px-8 sticky top-20 z-40 bg-midnight/95 backdrop-blur-md">
        <div class="max-w-7xl mx-auto flex flex-wrap gap-3">
            <button @click="filter='all'" :class="filter==='all'?'active':''" class="filter-btn px-5 py-2 rounded-full text-sm">Toți artiștii</button>
            <button @click="filter='actor'" :class="filter==='actor'?'active':''" class="filter-btn px-5 py-2 rounded-full text-sm">Actori</button>
            <button @click="filter='director'" :class="filter==='director'?'active':''" class="filter-btn px-5 py-2 rounded-full text-sm">Regizori</button>
            <button @click="filter='scenographer'" :class="filter==='scenographer'?'active':''" class="filter-btn px-5 py-2 rounded-full text-sm">Scenografi</button>
        </div>
    </section>

    <!-- Featured -->
    <section class="pb-8 px-4 lg:px-8" x-show="(filter==='all' || filter==='actor') && featured.length" x-cloak>
        <div class="max-w-7xl mx-auto">
            <h2 class="font-display text-2xl mb-8 text-gold">Actori de onoare</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <template x-for="a in featured" :key="a.id">
                    <a :href="'/artist/' + a.slug" class="actor-card group relative rounded-xl overflow-hidden block">
                        <div class="aspect-[3/4] overflow-hidden bg-charcoal">
                            <img :src="a.image || PLACEHOLDER" :alt="a.name" class="w-full h-full object-cover transition-transform duration-500">
                            <div class="absolute inset-0 bg-gradient-to-t from-midnight via-transparent to-transparent"></div>
                        </div>
                        <div class="absolute bottom-0 left-0 right-0 p-5">
                            <p class="text-gold text-xs tracking-widest mb-1" x-text="a.title"></p>
                            <h3 class="font-display text-xl" x-text="a.name"></h3>
                        </div>
                    </a>
                </template>
            </div>
        </div>
    </section>

    <!-- Grid -->
    <section class="pb-24 px-4 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <h2 class="font-display text-2xl mb-8 text-gold" x-text="sectionTitle()"></h2>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                <template x-for="a in filtered" :key="a.id">
                    <a :href="'/artist/' + a.slug" class="actor-card group relative rounded-lg overflow-hidden bg-charcoal block">
                        <div class="aspect-square overflow-hidden">
                            <img :src="a.image || PLACEHOLDER" :alt="a.name" class="w-full h-full object-cover transition-transform duration-500">
                        </div>
                        <div class="p-3">
                            <h3 class="font-display text-sm leading-tight" x-text="a.name"></h3>
                            <p class="text-warm-gray text-xs mt-1" x-text="a.category"></p>
                        </div>
                    </a>
                </template>
            </div>
            <div x-show="filtered.length===0" class="text-center py-16"><p class="text-warm-gray text-lg">Nu există artiști în această categorie.</p></div>
        </div>
    </section>

    <!-- Join -->
    <section class="py-16 px-4 lg:px-8 bg-charcoal/30">
        <div class="max-w-4xl mx-auto text-center">
            <h2 class="font-display text-3xl mb-4">Devino parte din echipă</h2>
            <p class="text-ivory/70 mb-8">Organizăm periodic audiții pentru actori și colaboratori.</p>
            <a href="/contact" class="btn-gold px-8 py-4 rounded-lg inline-block">Trimite-ne CV-ul</a>
        </div>
    </section>
</div>
<script>
const PLACEHOLDER = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='300'%3E%3Crect width='300' height='300' fill='%23181818'/%3E%3Cpath d='M150 90a35 35 0 100 70 35 35 0 000-70zm0 88c-40 0-72 20-72 45v7h144v-7c0-25-32-45-72-45z' fill='%23D4AF37' opacity='.35'/%3E%3C/svg%3E";
function troupePage() {
    return {
        filter: 'all',
        featured: <?= json_encode($featured, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        artists: <?= json_encode($grid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
        get filtered() { return this.filter==='all' ? this.artists : this.artists.filter(a => a.type===this.filter); },
        sectionTitle() { return {all:'Toți artiștii',actor:'Actori',director:'Regizori',scenographer:'Scenografi'}[this.filter]; }
    };
}
</script>
<?php include __DIR__ . '/includes/footer.php';
