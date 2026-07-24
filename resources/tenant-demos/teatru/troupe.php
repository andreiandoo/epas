<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Trupa — ' . SITE_NAME;
$activeNav = 'troupe';
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
            <p class="text-ivory/70 text-lg max-w-2xl">Actori consacrați și tinere talente care dau viață scenei de peste un secol.</p>
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
    <section class="pb-8 px-4 lg:px-8" x-show="filter==='all' || filter==='actor'">
        <div class="max-w-7xl mx-auto">
            <h2 class="font-display text-2xl mb-8 text-gold">Actori de onoare</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <template x-for="a in featured" :key="a.id">
                    <a :href="'/artist/' + a.id" class="actor-card group relative rounded-xl overflow-hidden block">
                        <div class="aspect-[3/4] overflow-hidden">
                            <img :src="a.image" :alt="a.name" class="w-full h-full object-cover transition-transform duration-500">
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
                    <a :href="'/artist/' + a.id" class="actor-card group relative rounded-lg overflow-hidden bg-charcoal block">
                        <div class="aspect-square overflow-hidden">
                            <img :src="a.image" :alt="a.name" class="w-full h-full object-cover transition-transform duration-500">
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
function troupePage() {
    return {
        filter: 'all',
        featured: [
            { id: 1, name: 'Victor Rebengiuc', title: 'ACTOR DE ONOARE', image: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&q=80' },
            { id: 2, name: 'Maia Morgenstern', title: 'ACTRIȚĂ DE ONOARE', image: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=400&q=80' },
            { id: 3, name: 'Marcel Iureș', title: 'ACTOR DE ONOARE', image: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=400&q=80' },
            { id: 4, name: 'Oana Pellea', title: 'ACTRIȚĂ DE ONOARE', image: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=400&q=80' }
        ],
        artists: [
            { id: 5, name: 'Ana Ularu', category: 'Actriță', type: 'actor', image: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=300&q=80' },
            { id: 6, name: 'Florin Piersic Jr.', category: 'Actor', type: 'actor', image: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=300&q=80' },
            { id: 7, name: 'Marius Manole', category: 'Actor', type: 'actor', image: 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=300&q=80' },
            { id: 8, name: 'Medeea Marinescu', category: 'Actriță', type: 'actor', image: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300&q=80' },
            { id: 9, name: 'Alexandru Dabija', category: 'Regizor', type: 'director', image: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=300&q=80' },
            { id: 10, name: 'Andrei Șerban', category: 'Regizor', type: 'director', image: 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=300&q=80' },
            { id: 11, name: 'Dragoș Buhagiar', category: 'Scenograf', type: 'scenographer', image: 'https://images.unsplash.com/photo-1507591064344-4c6ce005b128?w=300&q=80' },
            { id: 12, name: 'Diana Cavallioti', category: 'Actriță', type: 'actor', image: 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=300&q=80' },
            { id: 13, name: 'Richard Bovnoczki', category: 'Actor', type: 'actor', image: 'https://images.unsplash.com/photo-1463453091185-61582044d556?w=300&q=80' },
            { id: 14, name: 'Lia Manțoc', category: 'Scenograf', type: 'scenographer', image: 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?w=300&q=80' },
            { id: 15, name: 'Silviu Purcărete', category: 'Regizor', type: 'director', image: 'https://images.unsplash.com/photo-1504257432389-52343af06ae3?w=300&q=80' },
            { id: 16, name: 'Emilia Popescu', category: 'Actriță', type: 'actor', image: 'https://images.unsplash.com/photo-1489424731084-a5d8b219a5bb?w=300&q=80' },
            { id: 17, name: 'Vlad Ivanov', category: 'Actor', type: 'actor', image: 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?w=300&q=80' },
            { id: 18, name: 'Radu Afrim', category: 'Regizor', type: 'director', image: 'https://images.unsplash.com/photo-1507537297725-24a1c029d3ca?w=300&q=80' }
        ],
        get filtered() { return this.filter==='all' ? this.artists : this.artists.filter(a => a.type===this.filter); },
        sectionTitle() { return {all:'Toți artiștii',actor:'Actori',director:'Regizori',scenographer:'Scenografi'}[this.filter]; }
    };
}
</script>
<?php include __DIR__ . '/includes/footer.php';
