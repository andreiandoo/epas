<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Coșul tău — ' . SITE_NAME;
$gami = tc_gamification();
$pageExtraStyles = '.cart-item { background: linear-gradient(180deg,#1A1A1A 0%,#0A0A0A 100%); border: 1px solid rgba(212,175,55,.1); }';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<main class="pt-32 pb-20 px-4 lg:px-8" x-data="cartPage()" x-init="init()">
    <div class="max-w-7xl mx-auto">
        <h1 class="font-display text-4xl mb-8">Coșul tău</h1>

        <!-- With items -->
        <div x-show="cart && cart.seats && cart.seats.length > 0">
            <div class="grid lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 space-y-4">
                    <div class="cart-item rounded-xl p-5 flex flex-col sm:flex-row gap-5">
                        <img :src="cart.event.image" :alt="cart.event.title" class="w-full sm:w-32 h-32 rounded-lg object-cover">
                        <div class="flex-1">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <p class="text-gold text-sm" x-text="cart.event.author"></p>
                                    <h3 class="font-display text-xl" x-text="cart.event.title"></h3>
                                </div>
                                <button @click="clear()" class="text-warm-gray hover:text-burgundy transition-colors p-1"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                            </div>
                            <p class="text-warm-gray text-sm mb-3" x-text="[cart.event.date, cart.event.venue].filter(Boolean).join(' • ')"></p>
                            <div class="flex flex-wrap gap-2 mb-4">
                                <template x-for="(s,i) in cart.seats" :key="i"><span class="bg-charcoal px-3 py-1 rounded text-sm" x-text="s.label"></span></template>
                            </div>
                            <div class="flex items-center justify-between">
                                <p class="text-sm text-warm-gray" x-text="cart.seats.length + ' bilete'"></p>
                                <p class="font-display text-xl text-gold" x-text="subtotal + ' RON'"></p>
                            </div>
                        </div>
                    </div>
                    <a href="/program" class="block text-center py-4 border border-dashed border-gold/20 rounded-xl text-warm-gray hover:border-gold/40 hover:text-ivory transition-colors">+ Adaugă alte bilete</a>
                </div>

                <div class="lg:col-span-1">
                    <div class="bg-charcoal rounded-xl p-6 sticky top-28">
                        <h2 class="font-display text-xl mb-6">Sumar comandă</h2>
                        <div class="space-y-3 pb-4 border-b border-gold/10">
                            <div class="flex justify-between"><span class="text-warm-gray">Subtotal</span><span x-text="subtotal + ' RON'"></span></div>
                        </div>
                        <div class="flex justify-between py-4"><span class="font-medium text-lg">Total</span><span class="font-display text-2xl text-gold" x-text="total + ' RON'"></span></div>
                        <div x-show="earnEnabled && earnedPoints() > 0" x-cloak class="flex items-center gap-3 bg-gold/10 border border-gold/20 rounded-lg p-3 mb-4">
                            <span class="text-2xl">🎁</span>
                            <p class="text-sm">Câștigi <span class="text-gold font-semibold" x-text="earnedPoints()"></span> <span x-text="earnName"></span>.</p>
                        </div>
                        <div class="mb-2"></div>
                        <div class="bg-burgundy/20 rounded-lg p-4 mb-6 flex items-center gap-3">
                            <svg class="w-5 h-5 text-gold flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div><p class="text-sm">Locurile sunt rezervate pentru</p><p class="font-display text-gold" x-text="timer"></p></div>
                        </div>
                        <a href="/finalizare" class="btn-gold w-full py-4 rounded-lg text-center block text-lg">Finalizează comanda</a>
                        <div class="mt-6 flex items-center justify-center gap-2 text-warm-gray"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg><span class="text-xs">Plată securizată SSL</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty -->
        <div x-show="!cart || !cart.seats || cart.seats.length === 0" class="text-center py-20">
            <div class="w-24 h-24 rounded-full bg-charcoal mx-auto mb-6 flex items-center justify-center"><svg class="w-12 h-12 text-warm-gray" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg></div>
            <h2 class="font-display text-2xl mb-3">Coșul tău este gol</h2>
            <p class="text-warm-gray mb-8">Explorează repertoriul și alege spectacolele care te inspiră.</p>
            <a href="/program" class="btn-gold px-8 py-4 rounded-lg inline-block">Vezi programul</a>
        </div>
    </div>
</main>
<script>
function cartPage() {
    return {
        cart: null, timerSeconds: 600,
        earnEnabled: <?= !empty($gami['enabled']) ? 'true' : 'false' ?>,
        earnPct: <?= (float) ($gami['earn_percentage'] ?? 0) ?>,
        earnMin: <?= (float) ($gami['min_order'] ?? 0) ?>,
        earnName: <?= json_encode($gami['points_name'] ?? 'puncte', JSON_UNESCAPED_UNICODE) ?>,
        earnedPoints() { return (this.earnEnabled && this.subtotal >= this.earnMin) ? Math.floor(this.subtotal * this.earnPct) : 0; },
        init() {
            try { this.cart = JSON.parse(localStorage.getItem('teatru_cart') || 'null'); } catch(e) { this.cart = null; }
            setInterval(() => { if (this.timerSeconds > 0) this.timerSeconds--; }, 1000);
        },
        get subtotal() { return this.cart && this.cart.seats ? this.cart.seats.reduce((s,x)=>s+(x.price||0),0) : 0; },
        get total() { return this.subtotal; },
        get timer() { const m = Math.floor(this.timerSeconds/60), s = this.timerSeconds%60; return `${m}:${s.toString().padStart(2,'0')}`; },
        clear() { localStorage.removeItem('teatru_cart'); this.cart = null; }
    };
}
</script>
<?php include __DIR__ . '/includes/footer.php';
