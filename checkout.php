<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Finalizare comandă — ' . SITE_NAME;
$pageExtraStyles = '
    .input-field { background: #1A1A1A; border: 1px solid rgba(212,175,55,.2); transition: all .3s ease; }
    .input-field:focus { border-color: #D4AF37; outline: none; box-shadow: 0 0 0 3px rgba(212,175,55,.1); }
    .btn-gold:disabled { opacity:.5; cursor:not-allowed; }
    .pay-option { border: 2px solid rgba(212,175,55,.15); transition: all .25s ease; cursor: pointer; }
    .pay-option.selected { border-color: #D4AF37; background: rgba(212,175,55,.06); }
    .pay-radio { width: 18px; height: 18px; border-radius: 999px; border: 2px solid rgba(212,175,55,.4); flex-shrink: 0; position: relative; }
    .pay-option.selected .pay-radio { border-color: #D4AF37; }
    .pay-option.selected .pay-radio::after { content: ""; position: absolute; inset: 3px; border-radius: 999px; background: #D4AF37; }
    .num-badge { width: 32px; height: 32px; border-radius: 9px; background: rgba(212,175,55,.12); color: #D4AF37; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .9rem; }
';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<div x-data="checkoutPage()" x-init="init()" class="pt-28 pb-16 px-4 lg:px-8">
    <div class="max-w-5xl mx-auto">
        <h1 class="font-display text-4xl mb-8">Finalizare comandă</h1>
        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Left: form -->
            <div class="lg:col-span-2 space-y-6">

                <!-- 1. Contact -->
                <div class="bg-charcoal rounded-2xl border border-gold/10 p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="flex items-center gap-3 font-display text-xl"><span class="num-badge">1</span> Date de contact</h2>
                        <button type="button" x-show="!loggedIn" @click="goLogin()" class="btn-outline px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                            Am deja cont
                        </button>
                        <span x-show="loggedIn" x-cloak class="text-sm text-gold" x-text="'Autentificat: ' + (user?.email || '')"></span>
                    </div>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div><label class="block text-sm text-warm-gray mb-2">Nume *</label><input x-model="form.lastName" class="input-field w-full px-4 py-3 rounded-lg text-ivory" placeholder="Popescu" required></div>
                        <div><label class="block text-sm text-warm-gray mb-2">Prenume *</label><input x-model="form.firstName" class="input-field w-full px-4 py-3 rounded-lg text-ivory" placeholder="Ion" required></div>
                        <div><label class="block text-sm text-warm-gray mb-2">Email *</label><input type="email" x-model="form.email" autocomplete="off" class="input-field w-full px-4 py-3 rounded-lg text-ivory" required></div>
                        <div>
                            <label class="block text-sm text-warm-gray mb-2">Confirmă email *</label>
                            <input type="email" x-model="form.emailConfirm" autocomplete="new-password" onpaste="return false;" ondrop="return false;" oncontextmenu="return false;" class="input-field w-full px-4 py-3 rounded-lg text-ivory" required>
                            <p x-show="form.emailConfirm && form.email !== form.emailConfirm" class="text-red-300 text-xs mt-1">Adresele de email nu coincid.</p>
                        </div>
                        <div class="md:col-span-2"><label class="block text-sm text-warm-gray mb-2">Telefon *</label><input type="tel" x-model="form.phone" class="input-field w-full px-4 py-3 rounded-lg text-ivory" required></div>
                    </div>
                    <!-- Auto-create account -->
                    <div x-show="!loggedIn" class="mt-4">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" x-model="form.createAccount" class="w-5 h-5 rounded border-gold/30 bg-midnight text-gold">
                            <span class="text-sm text-ivory/80">Creează-mi automat un cont pentru comenzile viitoare</span>
                        </label>
                        <div x-show="form.createAccount" x-cloak class="mt-3">
                            <label class="block text-sm text-warm-gray mb-2">Alege o parolă *</label>
                            <input type="password" x-model="form.password" autocomplete="new-password" class="input-field w-full px-4 py-3 rounded-lg text-ivory" placeholder="Minim 8 caractere">
                        </div>
                    </div>
                </div>

                <!-- 2. Beneficiaries -->
                <div class="bg-charcoal rounded-2xl border border-gold/10 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="flex items-center gap-3 font-display text-xl"><span class="num-badge">2</span> Deținători bilete</h2>
                        <span class="text-warm-gray text-sm" x-text="seatCount + ' bilet' + (seatCount === 1 ? '' : 'e')"></span>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-midnight/60 rounded-xl gap-3 flex-wrap">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-gold flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="text-sm">Toate biletele sunt trimise pe emailul cumpărătorului.</p>
                        </div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" x-model="form.differentBeneficiaries" class="w-5 h-5 rounded border-gold/30 bg-midnight text-gold">
                            <span class="text-sm font-medium">Folosește date diferite pentru fiecare bilet</span>
                        </label>
                    </div>
                    <div x-show="form.differentBeneficiaries" x-cloak class="mt-5 space-y-4">
                        <template x-for="(b, i) in beneficiaries" :key="i">
                            <div class="bg-midnight/40 rounded-xl p-4 border border-gold/5">
                                <p class="text-gold text-sm mb-3" x-text="'Bilet ' + (i+1) + (cart?.seats?.[i]?.label ? ' — ' + cart.seats[i].label : '')"></p>
                                <div class="grid md:grid-cols-2 gap-3">
                                    <input x-model="b.name" class="input-field w-full px-4 py-2.5 rounded-lg text-ivory text-sm" placeholder="Nume deținător">
                                    <input type="email" x-model="b.email" class="input-field w-full px-4 py-2.5 rounded-lg text-ivory text-sm" placeholder="Email (opțional)">
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- 3. Terms & newsletter -->
                <div class="bg-charcoal rounded-2xl border border-gold/10 p-6 space-y-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" x-model="form.terms" class="w-5 h-5 rounded border-gold/30 bg-midnight text-gold mt-0.5">
                        <span class="text-sm text-ivory/80">Sunt de acord cu <a href="/termeni" class="text-gold hover:underline">termenii și condițiile</a> și <a href="/confidentialitate" class="text-gold hover:underline">politica de confidențialitate</a>. *</span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" x-model="form.newsletter" class="w-5 h-5 rounded border-gold/30 bg-midnight text-gold mt-0.5">
                        <span class="text-sm text-ivory/80">Doresc să primesc newsletter și notificări despre spectacole de la <?= e(SITE_NAME) ?>.</span>
                    </label>
                </div>

                <!-- 4. Payment method -->
                <div class="bg-charcoal rounded-2xl border border-gold/10 p-6">
                    <h2 class="flex items-center gap-3 font-display text-xl mb-4"><span class="num-badge">3</span> Metodă de plată</h2>
                    <div class="space-y-3">
                        <template x-for="m in paymentMethods" :key="m.id">
                            <div class="pay-option flex items-center gap-4 p-4 rounded-xl" :class="form.paymentMethod === m.id ? 'selected' : ''" @click="form.paymentMethod = m.id">
                                <div class="pay-radio"></div>
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-8 rounded flex items-center justify-center text-[9px] font-bold text-white" :class="m.badgeClass" x-text="m.badge"></div>
                                    <div><p class="font-medium text-sm" x-text="m.name"></p><p class="text-xs text-warm-gray" x-text="m.hint"></p></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div x-show="error" x-text="error" class="bg-red-900/30 border border-red-500/40 text-red-200 rounded-lg p-3 text-sm"></div>
            </div>

            <!-- Right: summary -->
            <div class="lg:col-span-1">
                <div class="bg-charcoal rounded-2xl border border-gold/10 p-6 sticky top-28">
                    <h2 class="font-display text-xl mb-5">Sumar comandă</h2>
                    <template x-if="cart && cart.event">
                        <div class="pb-4 border-b border-gold/10 mb-4">
                            <p class="font-display text-lg" x-text="cart.event.title"></p>
                            <p class="text-warm-gray text-sm" x-text="cart.event.date"></p>
                            <div class="flex flex-wrap gap-2 mt-3"><template x-for="(s,i) in cart.seats" :key="i"><span class="bg-midnight px-2 py-1 rounded text-xs" x-text="s.label"></span></template></div>
                        </div>
                    </template>
                    <div class="flex justify-between items-center mb-6">
                        <span class="font-medium">Total</span>
                        <span class="font-display text-2xl text-gold" x-text="subtotal + ' RON'"></span>
                    </div>
                    <button @click="pay()" :disabled="loading || !canPay" class="btn-gold w-full py-4 rounded-lg text-lg" x-text="loading ? 'Se procesează...' : 'Plătește ' + subtotal + ' RON'"></button>
                    <p class="text-warm-gray text-xs text-center mt-3">Vei fi redirecționat către gateway-ul de plată securizat.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function checkoutPage() {
    return {
        cart: null, loading: false, error: '', loggedIn: false, user: null,
        form: { firstName:'', lastName:'', email:'', emailConfirm:'', phone:'', createAccount:false, password:'', differentBeneficiaries:false, terms:false, newsletter:false, paymentMethod:'card' },
        beneficiaries: [],
        paymentMethods: [
            { id:'card', name:'Card bancar (demo)', hint:'Visa, Mastercard — gateway simulat', badge:'DEMO', badgeClass:'bg-gradient-to-r from-amber-600 to-amber-800' },
        ],
        init() {
            try { this.cart = JSON.parse(localStorage.getItem('teatru_cart') || 'null'); } catch(e) { this.cart = null; }
            this.beneficiaries = (this.cart?.seats || []).map(() => ({ name:'', email:'' }));
            let a = null; try { a = JSON.parse(localStorage.getItem('teatru_auth') || 'null'); } catch(e) {}
            if (a && a.user) {
                this.loggedIn = true; this.user = a.user;
                const parts = (a.user.name || '').trim().split(/\s+/);
                this.form.lastName = parts[0] || ''; this.form.firstName = parts.slice(1).join(' ') || '';
                this.form.email = a.user.email || ''; this.form.emailConfirm = a.user.email || '';
            }
        },
        get seatCount() { return this.cart?.seats?.length || 0; },
        get subtotal() { return this.cart?.seats ? this.cart.seats.reduce((s,x)=>s+(x.price||0),0) : 0; },
        get canPay() {
            return this.form.firstName && this.form.lastName && this.form.email && this.form.phone
                && this.form.email === this.form.emailConfirm && this.form.terms
                && (!this.form.createAccount || (this.form.password && this.form.password.length >= 8));
        },
        goLogin() { window.location.href = '/autentificare'; },
        async pay() {
            if (this.loading) return;
            if (!this.canPay) { this.error = 'Completează câmpurile obligatorii și acceptă termenii.'; return; }
            if (!this.cart || !this.cart.seats?.length) { this.error = 'Coșul este gol.'; return; }
            this.loading = true; this.error = '';
            try {
                const payload = {
                    event_id: this.cart.event.id,
                    event_seating_id: this.cart.event_seating_id || null,
                    customer: { first_name: this.form.firstName, last_name: this.form.lastName, email: this.form.email, phone: this.form.phone },
                    seats: this.cart.seats.map(s => ({ seat_uid: s.seat_uid, price: s.price, label: s.label })),
                    beneficiaries: this.form.differentBeneficiaries ? this.beneficiaries : [],
                    create_account: this.form.createAccount,
                    password: this.form.createAccount ? this.form.password : null,
                    newsletter: this.form.newsletter,
                    payment_method: this.form.paymentMethod,
                    success_url: window.location.origin + '/confirmare',
                    cancel_url: window.location.origin + '/cos'
                };
                const r = await fetch('/api/proxy.php?action=checkout', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
                const d = await r.json().catch(()=>({}));
                if (r.ok && d.success && d.redirect_url) { window.location.href = d.redirect_url; }
                else { this.error = d.error || 'Nu am putut iniția plata.'; this.loading = false; }
            } catch (e) { this.error = 'Eroare de conexiune.'; this.loading = false; }
        }
    };
}
</script>
<?php include __DIR__ . '/includes/footer.php';
