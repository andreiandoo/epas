<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Finalizare comandă — ' . SITE_NAME;
$pageExtraStyles = '
    .input-field { background: #1A1A1A; border: 1px solid rgba(212,175,55,.2); transition: all .3s ease; }
    .input-field:focus { border-color: #D4AF37; outline: none; }
    .step-indicator.active { background: #D4AF37; color: #0A0A0A; border-color: #D4AF37; }
    .step-indicator.completed { background: #722F37; color: #FFFEF2; border-color: #722F37; }
    .btn-gold:disabled { opacity:.5; cursor:not-allowed; }
';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<div x-data="checkoutPage()" x-init="init()">
    <!-- Steps -->
    <div class="pt-28 pb-8 px-4 lg:px-8">
        <div class="max-w-3xl mx-auto flex items-center justify-center gap-4">
            <template x-for="(label,i) in ['Informații','Plată','Confirmare']" :key="i">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <div :class="step >= i+1 ? (step > i+1 ? 'completed' : 'active') : ''" class="step-indicator w-8 h-8 rounded-full border border-gold/30 flex items-center justify-center text-sm font-medium" x-text="i+1"></div>
                        <span class="hidden sm:inline text-sm" :class="step >= i+1 ? 'text-ivory' : 'text-warm-gray'" x-text="label"></span>
                    </div>
                    <div x-show="i < 2" class="w-12 h-px bg-gold/20"></div>
                </div>
            </template>
        </div>
    </div>

    <main class="pb-16 px-4 lg:px-8">
        <div class="max-w-5xl mx-auto grid lg:grid-cols-3 gap-8">
            <!-- Form -->
            <div class="lg:col-span-2">
                <!-- Step 1 -->
                <div x-show="step === 1" x-transition>
                    <h1 class="font-display text-3xl mb-6">Informații contact</h1>
                    <div class="bg-charcoal rounded-xl p-6 mb-6">
                        <div class="grid md:grid-cols-2 gap-4">
                            <div><label class="block text-sm text-warm-gray mb-2">Prenume *</label><input x-model="form.firstName" class="input-field w-full px-4 py-3 rounded-lg text-ivory" required></div>
                            <div><label class="block text-sm text-warm-gray mb-2">Nume *</label><input x-model="form.lastName" class="input-field w-full px-4 py-3 rounded-lg text-ivory" required></div>
                            <div><label class="block text-sm text-warm-gray mb-2">Email *</label><input type="email" x-model="form.email" class="input-field w-full px-4 py-3 rounded-lg text-ivory" required></div>
                            <div><label class="block text-sm text-warm-gray mb-2">Telefon *</label><input type="tel" x-model="form.phone" class="input-field w-full px-4 py-3 rounded-lg text-ivory" required></div>
                        </div>
                    </div>
                    <label class="flex items-start gap-3 cursor-pointer mb-6"><input type="checkbox" x-model="form.terms" class="w-5 h-5 rounded border-gold/30 bg-midnight text-gold mt-0.5"><span class="text-sm text-ivory/80">Accept <a href="/termeni" class="text-gold hover:underline">termenii</a> și <a href="/confidentialitate" class="text-gold hover:underline">politica de confidențialitate</a> *</span></label>
                    <button @click="if(form.firstName&&form.email&&form.terms) step=2" :disabled="!(form.firstName&&form.email&&form.terms)" class="btn-gold w-full py-4 rounded-lg text-lg">Continuă la plată</button>
                </div>

                <!-- Step 2 -->
                <div x-show="step === 2" x-transition>
                    <h1 class="font-display text-3xl mb-6">Plată</h1>
                    <div class="bg-charcoal rounded-xl p-6 mb-6 space-y-4">
                        <div><label class="block text-sm text-warm-gray mb-2">Număr card</label><input class="input-field w-full px-4 py-3 rounded-lg text-ivory" placeholder="4242 4242 4242 4242"></div>
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm text-warm-gray mb-2">Expirare</label><input class="input-field w-full px-4 py-3 rounded-lg text-ivory" placeholder="LL/AA"></div>
                            <div><label class="block text-sm text-warm-gray mb-2">CVV</label><input class="input-field w-full px-4 py-3 rounded-lg text-ivory" placeholder="123"></div>
                        </div>
                    </div>
                    <div class="bg-gold/10 border border-gold/30 rounded-lg p-4 mb-6 text-sm text-ivory/80">🔒 Mediu demo: la „Plătește" vei fi redirecționat către un gateway de plată simulat (fără bani reali). Comanda și biletele se creează real.</div>
                    <div x-show="error" x-text="error" class="bg-red-900/30 border border-red-500/40 text-red-200 rounded-lg p-3 mb-4 text-sm"></div>
                    <div class="flex gap-4">
                        <button @click="step=1" :disabled="loading" class="btn-outline px-6 py-4 rounded-lg">Înapoi</button>
                        <button @click="pay()" :disabled="loading" class="btn-gold flex-1 py-4 rounded-lg text-lg" x-text="loading ? 'Se procesează...' : ('Plătește ' + total + ' RON')"></button>
                    </div>
                </div>

                <!-- Step 3 -->
                <div x-show="step === 3" x-transition class="text-center py-12">
                    <div class="w-20 h-20 rounded-full bg-gold/10 flex items-center justify-center mx-auto mb-6"><svg class="w-10 h-10 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div>
                    <h1 class="font-display text-3xl mb-3">Comandă confirmată!</h1>
                    <p class="text-ivory/70 mb-8">Îți mulțumim. Biletele vor fi trimise pe email după activarea plății reale.</p>
                    <a href="/" class="btn-gold px-8 py-4 rounded-lg inline-block" @click="localStorage.removeItem('teatru_cart')">Înapoi acasă</a>
                </div>
            </div>

            <!-- Summary -->
            <div class="lg:col-span-1" x-show="step < 3">
                <div class="bg-charcoal rounded-xl p-6 sticky top-28">
                    <h2 class="font-display text-xl mb-6">Sumar comandă</h2>
                    <template x-if="cart && cart.event">
                        <div class="pb-4 border-b border-gold/10 mb-4">
                            <p class="font-display text-lg" x-text="cart.event.title"></p>
                            <p class="text-warm-gray text-sm" x-text="cart.event.date"></p>
                            <div class="flex flex-wrap gap-2 mt-2"><template x-for="(s,i) in cart.seats" :key="i"><span class="bg-midnight px-2 py-1 rounded text-xs" x-text="s.label"></span></template></div>
                        </div>
                    </template>
                    <div class="space-y-3">
                        <div class="flex justify-between"><span class="text-warm-gray">Subtotal</span><span x-text="subtotal + ' RON'"></span></div>
                        <div class="flex justify-between pt-3 border-t border-gold/10"><span class="font-medium">Total</span><span class="font-display text-2xl text-gold" x-text="total + ' RON'"></span></div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
function checkoutPage() {
    return {
        step: 1, cart: null, loading: false, error: '',
        form: { firstName:'', lastName:'', email:'', phone:'', terms:false },
        init() { try { this.cart = JSON.parse(localStorage.getItem('teatru_cart') || 'null'); } catch(e) { this.cart = null; } },
        get subtotal() { return this.cart && this.cart.seats ? this.cart.seats.reduce((s,x)=>s+(x.price||0),0) : 0; },
        get total() { return this.subtotal; },
        async pay() {
            if (this.loading) return;
            if (!this.cart || !this.cart.event || !this.cart.seats || !this.cart.seats.length) { this.error = 'Coșul este gol.'; return; }
            this.loading = true; this.error = '';
            try {
                const payload = {
                    event_id: this.cart.event.id,
                    event_seating_id: this.cart.event_seating_id || null,
                    customer: { first_name: this.form.firstName, last_name: this.form.lastName, email: this.form.email, phone: this.form.phone },
                    seats: this.cart.seats.map(s => ({ seat_uid: s.seat_uid, price: s.price, label: s.label })),
                    success_url: window.location.origin + '/confirmare',
                    cancel_url: window.location.origin + '/cos'
                };
                const r = await fetch('/api/proxy.php?action=checkout', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
                });
                const d = await r.json().catch(() => ({}));
                if (r.ok && d.success && d.redirect_url) {
                    window.location.href = d.redirect_url;
                } else {
                    this.error = d.error || 'Nu am putut iniția plata. Încearcă din nou.';
                    this.loading = false;
                }
            } catch (e) {
                this.error = 'Eroare de conexiune.'; this.loading = false;
            }
        }
    };
}
</script>
<?php include __DIR__ . '/includes/footer.php';
