<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Coș — ' . SITE_NAME;
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<section class="pt-32 pb-24 px-4 lg:px-8" x-data="cart()" x-init="load()">
    <div class="max-w-3xl mx-auto">
        <h1 class="font-display text-4xl lg:text-5xl mb-8">Coșul tău</h1>

        <div x-show="items.length === 0" class="bg-charcoal/40 rounded-xl p-10 text-center">
            <p class="text-warm-gray mb-6">Coșul este gol.</p>
            <a href="/program" class="btn-gold px-8 py-3 rounded-lg inline-block">Vezi programul</a>
        </div>

        <div x-show="items.length > 0">
            <div class="bg-charcoal/40 rounded-xl p-6 mb-6">
                <p class="text-sm text-warm-gray mb-4">Locuri rezervate (10 minute)</p>
                <div class="space-y-2">
                    <template x-for="s in items" :key="s.seat_uid">
                        <div class="flex items-center justify-between py-2 border-b border-gold/10">
                            <div>
                                <p class="font-medium" x-text="s.section"></p>
                                <p class="text-sm text-warm-gray" x-text="'Rând ' + s.row + ', Loc ' + s.seat_label"></p>
                            </div>
                            <p class="text-gold font-display" x-text="(s.price||0) + ' RON'"></p>
                        </div>
                    </template>
                </div>
                <div class="flex items-center justify-between mt-6">
                    <p class="text-lg">Total</p>
                    <p class="font-display text-3xl text-gold" x-text="total + ' RON'"></p>
                </div>
            </div>

            <div class="bg-gold/10 border border-gold/30 rounded-xl p-6 text-center">
                <p class="text-ivory/80 mb-2">🔒 Finalizarea plății (card, Netopia) se activează în pasul următor al implementării.</p>
                <p class="text-warm-gray text-sm">Locurile rămân rezervate temporar. Această versiune demonstrează selecția de loc pe hartă reală.</p>
            </div>
            <div class="flex gap-4 mt-6">
                <a href="/program" class="btn-outline px-6 py-3 rounded-lg">Continuă selecția</a>
                <button @click="clear()" class="text-warm-gray hover:text-burgundy px-6 py-3">Golește coșul</button>
            </div>
        </div>
    </div>
</section>
<script>
function cart() {
    return {
        items: [],
        load() { try { this.items = JSON.parse(localStorage.getItem('teatru_cart') || '[]'); } catch(e){ this.items = []; } },
        get total() { return this.items.reduce((s,x) => s + (x.price||0), 0); },
        clear() { localStorage.removeItem('teatru_cart'); this.items = []; }
    };
}
</script>
<?php include __DIR__ . '/includes/footer.php';
