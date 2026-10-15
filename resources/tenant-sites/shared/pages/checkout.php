<?php
$pageTitle = 'Finalizare comandă — ' . SITE_NAME;
$gami = tc_gamification();
$badgeMap = [
    'card'          => ['badge' => 'DEMO', 'class' => 'bg-gradient-to-r from-amber-600 to-amber-800'],
    'card_cultural' => ['badge' => 'CULTURAL', 'class' => 'bg-gradient-to-r from-purple-600 to-purple-800'],
];
$pmDisplay = array_map(fn ($m) => [
    'id'                => $m['id'] ?? 'card',
    'name'              => $m['name'] ?? 'Card',
    'hint'              => $m['hint'] ?? '',
    'surcharge_percent' => (float) ($m['surcharge_percent'] ?? 0),
    'badge'             => $badgeMap[$m['id'] ?? 'card']['badge'] ?? 'CARD',
    'badgeClass'        => $badgeMap[$m['id'] ?? 'card']['class'] ?? 'bg-gradient-to-r from-amber-600 to-amber-800',
], tc_payment_methods());
$pageExtraStyles = '
    .input-field { background: rgb(var(--c-surface)); border: 1px solid rgb(var(--c-primary) / .2); transition: all .3s ease; }
    .input-field:focus { border-color: rgb(var(--c-primary)); outline: none; box-shadow: 0 0 0 3px rgb(var(--c-primary) / .1); }
    .btn-primary:disabled { opacity:.5; cursor:not-allowed; }
    .pay-option { border: 2px solid rgb(var(--c-primary) / .15); transition: all .25s ease; cursor: pointer; }
    .pay-option.selected { border-color: rgb(var(--c-primary)); background: rgb(var(--c-primary) / .06); }
    .pay-radio { width: 18px; height: 18px; border-radius: 999px; border: 2px solid rgb(var(--c-primary) / .4); flex-shrink: 0; position: relative; }
    .pay-option.selected .pay-radio { border-color: rgb(var(--c-primary)); }
    .pay-option.selected .pay-radio::after { content: ""; position: absolute; inset: 3px; border-radius: 999px; background: rgb(var(--c-primary)); }
    .num-badge { width: 32px; height: 32px; border-radius: 9px; background: rgb(var(--c-primary) / .12); color: rgb(var(--c-primary)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .9rem; }
';
include ts_layout('head');
include ts_layout('header');
?>
<div x-data="checkoutPage()" x-init="init()" class="pt-28 pb-16 px-4 lg:px-8">
    <div class="max-w-5xl mx-auto">
        <h1 class="font-display text-4xl mb-8">Finalizare comandă</h1>
        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Left: form -->
            <div class="lg:col-span-2 space-y-6">

                <!-- 1. Contact -->
                <div class="bg-surface rounded-2xl border border-primary/10 p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="flex items-center gap-3 font-display text-xl"><span class="num-badge">1</span> Date de contact</h2>
                        <button type="button" x-show="!loggedIn" @click="goLogin()" class="btn-outline px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                            Am deja cont
                        </button>
                        <span x-show="loggedIn" x-cloak class="text-sm text-primary" x-text="'Autentificat: ' + (user?.email || '')"></span>
                    </div>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div><label class="block text-sm text-muted mb-2">Nume *</label><input x-model="form.lastName" class="input-field w-full px-4 py-3 rounded-lg text-fg" placeholder="Popescu" required></div>
                        <div><label class="block text-sm text-muted mb-2">Prenume *</label><input x-model="form.firstName" class="input-field w-full px-4 py-3 rounded-lg text-fg" placeholder="Ion" required></div>
                        <div><label class="block text-sm text-muted mb-2">Email *</label><input type="email" x-model="form.email" autocomplete="off" class="input-field w-full px-4 py-3 rounded-lg text-fg" required></div>
                        <div>
                            <label class="block text-sm text-muted mb-2">Confirmă email *</label>
                            <input type="email" x-model="form.emailConfirm" autocomplete="new-password" onpaste="return false;" ondrop="return false;" oncontextmenu="return false;" class="input-field w-full px-4 py-3 rounded-lg text-fg" required>
                            <p x-show="form.emailConfirm && form.email !== form.emailConfirm" class="text-red-300 text-xs mt-1">Adresele de email nu coincid.</p>
                        </div>
                        <div class="md:col-span-2"><label class="block text-sm text-muted mb-2">Telefon *</label><input type="tel" x-model="form.phone" class="input-field w-full px-4 py-3 rounded-lg text-fg" required></div>
                    </div>
                    <!-- Auto-create account -->
                    <div x-show="!loggedIn" class="mt-4">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" x-model="form.createAccount" class="w-5 h-5 rounded border-primary/30 bg-canvas text-primary">
                            <span class="text-sm text-fg/80">Creează-mi automat un cont pentru comenzile viitoare</span>
                        </label>
                        <div x-show="form.createAccount" x-cloak class="mt-3">
                            <label class="block text-sm text-muted mb-2">Alege o parolă *</label>
                            <input type="password" x-model="form.password" autocomplete="new-password" class="input-field w-full px-4 py-3 rounded-lg text-fg" placeholder="Minim 8 caractere">
                        </div>
                    </div>
                </div>

                <!-- 2. Beneficiaries -->
                <div class="bg-surface rounded-2xl border border-primary/10 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="flex items-center gap-3 font-display text-xl"><span class="num-badge">2</span> Deținători bilete</h2>
                        <span class="text-muted text-sm" x-text="seatCount + ' bilet' + (seatCount === 1 ? '' : 'e')"></span>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-canvas/60 rounded-xl gap-3 flex-wrap">
                        <div class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-primary flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="text-sm">Toate biletele sunt trimise pe emailul cumpărătorului.</p>
                        </div>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" x-model="form.differentBeneficiaries" class="w-5 h-5 rounded border-primary/30 bg-canvas text-primary">
                            <span class="text-sm font-medium">Folosește date diferite pentru fiecare bilet</span>
                        </label>
                    </div>
                    <div x-show="form.differentBeneficiaries" x-cloak class="mt-5 space-y-4">
                        <template x-for="(b, i) in beneficiaries" :key="i">
                            <div class="bg-canvas/40 rounded-xl p-4 border border-primary/5">
                                <p class="text-primary text-sm mb-3" x-text="'Bilet ' + (i+1) + (cart?.seats?.[i]?.label ? ' — ' + cart.seats[i].label : '')"></p>
                                <div class="grid md:grid-cols-2 gap-3">
                                    <input x-model="b.name" class="input-field w-full px-4 py-2.5 rounded-lg text-fg text-sm" placeholder="Nume deținător">
                                    <input type="email" x-model="b.email" class="input-field w-full px-4 py-2.5 rounded-lg text-fg text-sm" placeholder="Email (opțional)">
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- 3. Terms & newsletter -->
                <div class="bg-surface rounded-2xl border border-primary/10 p-6 space-y-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" x-model="form.terms" class="w-5 h-5 rounded border-primary/30 bg-canvas text-primary mt-0.5">
                        <span class="text-sm text-fg/80">Sunt de acord cu <a href="/termeni" class="text-primary hover:underline">termenii și condițiile</a> și <a href="/confidentialitate" class="text-primary hover:underline">politica de confidențialitate</a>. *</span>
                    </label>
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" x-model="form.newsletter" class="w-5 h-5 rounded border-primary/30 bg-canvas text-primary mt-0.5">
                        <span class="text-sm text-fg/80">Doresc să primesc newsletter și notificări despre spectacole de la <?= e(SITE_NAME) ?>.</span>
                    </label>
                </div>

                <!-- 4. Payment method -->
                <div class="bg-surface rounded-2xl border border-primary/10 p-6">
                    <h2 class="flex items-center gap-3 font-display text-xl mb-4"><span class="num-badge">3</span> Metodă de plată</h2>
                    <div class="space-y-3">
                        <template x-for="m in paymentMethods" :key="m.id">
                            <div class="pay-option flex items-center gap-4 p-4 rounded-xl" :class="form.paymentMethod === m.id ? 'selected' : ''" @click="form.paymentMethod = m.id">
                                <div class="pay-radio"></div>
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-8 rounded flex items-center justify-center text-[9px] font-bold text-white" :class="m.badgeClass" x-text="m.badge"></div>
                                    <div><p class="font-medium text-sm" x-text="m.name"></p><p class="text-xs text-muted" x-text="m.hint"></p></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div x-show="error" x-text="error" class="bg-red-900/30 border border-red-500/40 text-red-200 rounded-lg p-3 text-sm"></div>
            </div>

            <!-- Right: summary -->
            <div class="lg:col-span-1">
                <div class="bg-surface rounded-2xl border border-primary/10 p-6 sticky top-28">
                    <h2 class="font-display text-xl mb-5">Sumar comandă</h2>
                    <template x-if="cart && cart.event">
                        <div class="pb-4 border-b border-primary/10 mb-4">
                            <p class="font-display text-lg" x-text="cart.event.title"></p>
                            <p class="text-muted text-sm" x-text="cart.event.date"></p>
                            <div class="flex flex-wrap gap-2 mt-3"><template x-for="(s,i) in cart.seats" :key="i"><span class="bg-canvas px-2 py-1 rounded text-xs" x-text="s.label"></span></template></div>
                        </div>
                    </template>
                    <div class="space-y-2 mb-4 text-sm">
                        <div class="flex justify-between"><span class="text-muted">Subtotal</span><span x-text="subtotal + ' RON'"></span></div>
                        <div x-show="surcharge() > 0" x-cloak class="flex justify-between"><span class="text-muted" x-text="'Comision ' + (selectedMethod()?.name || '')"></span><span x-text="'+' + surcharge() + ' RON'"></span></div>
                    </div>
                    <div class="flex justify-between items-center mb-4 pt-3 border-t border-primary/10">
                        <span class="font-medium">Total</span>
                        <span class="font-display text-2xl text-primary" x-text="grandTotal + ' RON'"></span>
                    </div>
                    <div x-show="earnEnabled && earnedPoints() > 0" x-cloak class="flex items-center gap-3 bg-primary/10 border border-primary/20 rounded-lg p-3 mb-6">
                        <span class="text-2xl">🎁</span>
                        <p class="text-sm">Vei câștiga <span class="text-primary font-semibold" x-text="earnedPoints()"></span> <span x-text="earnName"></span> la această comandă.</p>
                    </div>
                    <button @click="pay()" :disabled="loading || !canPay" class="btn-primary w-full py-4 rounded-lg text-lg" x-text="loading ? 'Se procesează...' : 'Plătește ' + grandTotal + ' RON'"></button>
                    <p class="text-muted text-xs text-center mt-3">Vei fi redirecționat către gateway-ul de plată securizat.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function checkoutPage() {
    return {
        cart: null, loading: false, error: '', loggedIn: false, user: null,
        earnEnabled: <?= !empty($gami['enabled']) ? 'true' : 'false' ?>,
        earnPct: <?= (float) ($gami['earn_percentage'] ?? 0) ?>,
        earnMin: <?= (float) ($gami['min_order'] ?? 0) ?>,
        earnName: <?= json_encode($gami['points_name'] ?? 'puncte', JSON_UNESCAPED_UNICODE) ?>,
        earnedPoints() { return (this.earnEnabled && this.grandTotal >= this.earnMin) ? Math.floor(this.grandTotal * this.earnPct) : 0; },
        form: { firstName:'', lastName:'', email:'', emailConfirm:'', phone:'', createAccount:false, password:'', differentBeneficiaries:false, terms:false, newsletter:false, paymentMethod:'card' },
        beneficiaries: [],
        paymentMethods: <?= json_encode($pmDisplay, JSON_UNESCAPED_UNICODE) ?>,
        selectedMethod() { return this.paymentMethods.find(m => m.id === this.form.paymentMethod) || this.paymentMethods[0]; },
        surcharge() { const m = this.selectedMethod(); return m && m.surcharge_percent ? Math.round(this.subtotal * m.surcharge_percent / 100) : 0; },
        get grandTotal() { return this.subtotal + this.surcharge(); },
        init() {
            try { this.cart = JSON.parse(localStorage.getItem('site_cart') || 'null'); } catch(e) { this.cart = null; }
            this.beneficiaries = (this.cart?.seats || []).map(() => ({ name:'', email:'' }));
            let a = null; try { a = JSON.parse(localStorage.getItem('site_auth') || 'null'); } catch(e) {}
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
<?php include ts_layout('footer');
