<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Artist — ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<div x-data="artistPage()" x-init="init()">
    <section class="pt-28 pb-2 px-4 lg:px-8">
        <div class="max-w-5xl mx-auto text-sm text-warm-gray">
            <a href="/trupa" class="hover:text-gold transition-colors">Trupa</a>
            <span class="mx-2">/</span><span class="text-ivory/70" x-text="artist.name"></span>
        </div>
    </section>
    <section class="py-8 px-4 lg:px-8">
        <div class="max-w-5xl mx-auto grid md:grid-cols-[320px_1fr] gap-10 items-start">
            <div class="rounded-xl overflow-hidden border border-gold/10">
                <img :src="artist.image" :alt="artist.name" class="w-full aspect-[3/4] object-cover">
            </div>
            <div>
                <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase" x-text="artist.title || artist.category"></p>
                <h1 class="font-display text-4xl lg:text-5xl mb-6" x-text="artist.name"></h1>
                <div class="prose prose-invert prose-lg max-w-none text-ivory/70 space-y-4">
                    <template x-for="(para,i) in artist.bio" :key="i"><p x-text="para"></p></template>
                </div>
                <div class="flex flex-wrap gap-4 mt-8">
                    <a href="/program" class="btn-gold px-6 py-3 rounded-lg">Vezi spectacolele</a>
                    <a href="/trupa" class="btn-outline px-6 py-3 rounded-lg">Înapoi la trupă</a>
                </div>
            </div>
        </div>
    </section>
</div>
<script>
function artistPage() {
    return {
        artist: {},
        roster: [
            { id: 1, name: 'Victor Rebengiuc', title: 'Actor de onoare', image: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=600&q=80' },
            { id: 2, name: 'Maia Morgenstern', title: 'Actriță de onoare', image: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=600&q=80' },
            { id: 3, name: 'Marcel Iureș', title: 'Actor de onoare', image: 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=600&q=80' },
            { id: 4, name: 'Oana Pellea', title: 'Actriță de onoare', image: 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=600&q=80' },
            { id: 5, name: 'Ana Ularu', category: 'Actriță', image: 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=600&q=80' },
            { id: 6, name: 'Florin Piersic Jr.', category: 'Actor', image: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=600&q=80' },
            { id: 7, name: 'Marius Manole', category: 'Actor', image: 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=600&q=80' },
            { id: 8, name: 'Medeea Marinescu', category: 'Actriță', image: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=600&q=80' },
            { id: 9, name: 'Alexandru Dabija', category: 'Regizor', image: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=600&q=80' }
        ],
        init() {
            const id = parseInt(new URLSearchParams(location.search).get('id')) || 1;
            const f = this.roster.find(a => a.id === id) || this.roster[0];
            this.artist = { ...f, bio: [
                f.name + ' este unul dintre numele de referință ale scenei românești, cu o carieră de decenii pe scena teatrului.',
                'Roluri memorabile în producții clasice și contemporane, apreciate de public și critică, au consacrat un stil marcat de rigoare și prezență scenică remarcabilă.',
                'A colaborat cu cei mai importanți regizori ai teatrului românesc, contribuind la spectacole care au definit stagiuni întregi.'
            ]};
            document.title = f.name + ' — <?= e(SITE_NAME) ?>';
        }
    };
}
</script>
<?php include __DIR__ . '/includes/footer.php';
