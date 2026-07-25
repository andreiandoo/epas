<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$planSlug = $_GET['plan'] ?? '';
$plans = tc_subscriptions();
$plan = null;
foreach ($plans as $p) { if (($p['slug'] ?? '') === $planSlug) { $plan = $p; break; } }

if (!$plan) {
    $pageTitle = 'Abonament — ' . SITE_NAME;
    include __DIR__ . '/includes/head.php';
    include __DIR__ . '/includes/header.php';
    echo '<section class="pt-40 pb-32 px-4 text-center"><h1 class="font-display text-4xl mb-4">Abonament inexistent</h1><a href="/abonamente" class="btn-gold px-6 py-3 rounded-lg inline-block mt-4">Vezi abonamentele</a></section>';
    include __DIR__ . '/includes/footer.php';
    return;
}

// Eveniment reprezentativ pentru harta de sală (locurile sunt aceleași pe toate)
$eventsResp = api_get('/tenant-client/events', ['limit' => 20], 60);
$events = tc_events($eventsResp);
$repEventId = 0;
foreach ($events as $ev) { if (!empty($ev['id'])) { $repEventId = (int) $ev['id']; break; } }

$ticketsIncluded = (int) ($plan['tickets_included'] ?? 1);
$showsIncluded   = (int) ($plan['shows_included'] ?? 0);
$allowedSections = array_values(array_filter($plan['allowed_sections'] ?? []));
$benefits        = array_values(array_filter($plan['benefits'] ?? []));

$pageTitle = 'Abonament ' . ($plan['name'] ?? '') . ' — ' . SITE_NAME;
$pageExtraStyles = '
    .seat { width: 22px; height: 22px; border-radius: 5px 5px 3px 3px; cursor: pointer; display:flex; align-items:center; justify-content:center; font-size:9px; font-weight:700; transition: all .15s ease; }
    .seat.available { background: #3d1f23; border: 1px solid #722F37; }
    .seat.available:hover { background: #722F37; transform: translateY(-2px); }
    .seat.available.vip { background: #2a2410; border-color: #D4AF37; }
    .seat.available.vip:hover { background: #D4AF37; }
    .seat.selected { background: #D4AF37; color: #0A0A0A; }
    .seat.taken { background: #1a1a1a; border: 1px solid #2a2a2a; cursor: not-allowed; opacity: .5; }
    .stage { background: linear-gradient(180deg, rgba(212,175,55,.15), transparent); border-top: 2px solid #D4AF37; border-radius: 50% 50% 0 0 / 100% 100% 0 0; }
    .input-field { background: #1A1A1A; border: 1px solid rgba(212,175,55,.2); transition: all .3s ease; }
    .input-field:focus { border-color: #D4AF37; outline: none; }
    .choice-card { border: 2px solid rgba(212,175,55,.15); transition: all .25s ease; cursor: pointer; }
    .choice-card:hover { border-color: rgba(212,175,55,.5); transform: translateY(-3px); }
    .step-dot { transition: all .3s ease; }
';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<div x-data="subFlow()" x-init="init()" class="pt-28 pb-16 px-4 lg:px-8">
    <div class="max-w-5xl mx-auto">
        <a href="/abonamente" class="text-warm-gray text-sm hover:text-gold">← Înapoi la abonamente</a>
        <h1 class="font-display text-4xl mt-3 mb-1">Abonament <?= e($plan['name'] ?? '') ?></h1>

        <!-- Step indicator -->
        <div class="flex items-center gap-3 my-6 text-sm">
            <template x-for="(label,i) in ['Locuri','Cont','Plată']" :key="i">
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                        <div class="step-dot w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold"
                            :class="step > i+1 ? 'bg-gold text-midnight' : (step === i+1 ? 'bg-gold/20 text-gold border border-gold' : 'bg-charcoal text-warm-gray')"
                            x-text="i+1"></div>
                        <span :class="step >= i+1 ? 'text-ivory' : 'text-warm-gray'" x-text="label"></span>
                    </div>
                    <div x-show="i < 2" class="w-8 h-px bg-gold/20"></div>
                </div>
            </template>
        </div>

        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Main -->
            <div class="lg:col-span-2">

                <!-- STEP 1: seats -->
                <div x-show="step === 1">
                    <p class="text-ivory/70 mb-5">Alege <strong class="text-gold"><?= $ticketsIncluded ?></strong> loc<?= $ticketsIncluded === 1 ? '' : 'uri' ?>. <?php if ($allowedSections): ?>Zone permise: <span class="text-gold"><?= e(implode(', ', $allowedSections)) ?></span>.<?php endif; ?></p>
                    <div x-show="loading" class="text-center py-12 text-warm-gray">Se încarcă harta sălii...</div>
                    <div x-show="!loading && !hasSeating" class="text-center py-12 text-warm-gray">Harta de sală nu este disponibilă.</div>
                    <div x-show="!loading && hasSeating" class="bg-charcoal rounded-xl p-6 overflow-x-auto">
                        <div class="stage w-64 h-12 mx-auto mb-10 flex items-center justify-center text-warm-gray text-sm tracking-widest">SCENA</div>
                        <div class="flex flex-col items-center gap-8 min-w-[600px]">
                            <template x-for="section in sections" :key="section.name">
                                <div class="w-full" x-show="section.allowed">
                                    <p class="text-center text-xs tracking-wider mb-3" :style="'color:' + (section.color || '#D4AF37')">
                                        <span x-text="section.name.toUpperCase()"></span>
                                        <span x-show="section.price" x-text="' — ' + section.price + ' RON'"></span>
                                    </p>
                                    <div class="flex flex-col items-center gap-1.5">
                                        <template x-for="row in section.rows" :key="row.label">
                                            <div class="flex items-center gap-1.5">
                                                <span class="w-5 text-xs text-warm-gray text-right" x-text="row.label"></span>
                                                <template x-for="seat in row.seats" :key="seat.seat_uid">
                                                    <div @click="toggle(seat, section)" :class="seatClass(seat, section)" class="seat" :title="section.name + ' ' + row.label + seat.seat_label">
                                                        <span x-show="isSelected(seat)" x-text="seat.seat_label"></span>
                                                    </div>
                                                </template>
                                                <span class="w-5 text-xs text-warm-gray" x-text="row.label"></span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                        <div class="flex flex-wrap justify-center gap-6 mt-10 text-sm">
                            <div class="flex items-center gap-2"><div class="seat available"></div><span class="text-warm-gray">Disponibil</span></div>
                            <div class="flex items-center gap-2"><div class="seat selected"></div><span class="text-warm-gray">Ales</span></div>
                            <div class="flex items-center gap-2"><div class="seat taken"></div><span class="text-warm-gray">Indisponibil</span></div>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: account choice -->
                <div x-show="step === 2" x-cloak>
                    <h2 class="font-display text-2xl mb-2">Cum continui?</h2>
                    <p class="text-warm-gray mb-6">Abonamentele necesită un cont, ca să îți poți vedea locurile și consumul.</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="choice-card rounded-2xl p-6" @click="chooseExisting()">
                            <div class="w-12 h-12 rounded-xl bg-gold/10 flex items-center justify-center mb-4"><svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg></div>
                            <h3 class="font-display text-lg mb-1">Am deja cont</h3>
                            <p class="text-warm-gray text-sm">Autentifică-te și mergi direct la plată.</p>
                        </div>
                        <div class="choice-card rounded-2xl p-6" @click="chooseNew()">
                            <div class="w-12 h-12 rounded-xl bg-gold/10 flex items-center justify-center mb-4"><svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg></div>
                            <h3 class="font-display text-lg mb-1">Creează cont nou</h3>
                            <p class="text-warm-gray text-sm">Îți înregistrezi contul direct la finalizarea comenzii.</p>
                        </div>
                    </div>
                    <button @click="step = 1" class="btn-outline px-6 py-3 rounded-lg mt-6">← Înapoi la locuri</button>
                </div>

                <!-- STEP 3: checkout -->
                <div x-show="step === 3" x-cloak>
                    <h2 class="font-display text-2xl mb-5" x-text="mode === 'existing' ? 'Confirmă și plătește' : 'Datele tale'"></h2>
                    <div class="bg-charcoal rounded-2xl border border-gold/10 p-6 mb-6">
                        <template x-if="mode === 'existing'">
                            <div class="flex items-center gap-3">
                                <svg class="w-6 h-6 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <p>Autentificat ca <span class="text-gold" x-text="user?.email"></span></p>
                            </div>
                        </template>
                        <template x-if="mode === 'new'">
                            <div>
                                <div class="bg-gold/10 border border-gold/20 rounded-lg p-3 mb-4 text-sm text-ivory/80">✓ La finalizare îți creăm automat un cont cu aceste date.</div>
                                <div class="grid md:grid-cols-2 gap-4">
                                    <div><label class="block text-sm text-warm-gray mb-2">Nume *</label><input x-model="reg.lastName" class="input-field w-full px-4 py-3 rounded-lg text-ivory" placeholder="Popescu"></div>
                                    <div><label class="block text-sm text-warm-gray mb-2">Prenume *</label><input x-model="reg.firstName" class="input-field w-full px-4 py-3 rounded-lg text-ivory" placeholder="Ion"></div>
                                    <div><label class="block text-sm text-warm-gray mb-2">Email *</label><input type="email" x-model="reg.email" autocomplete="off" class="input-field w-full px-4 py-3 rounded-lg text-ivory"></div>
                                    <div>
                                        <label class="block text-sm text-warm-gray mb-2">Confirmă email *</label>
                                        <input type="email" x-model="reg.emailConfirm" autocomplete="new-password" onpaste="return false;" ondrop="return false;" class="input-field w-full px-4 py-3 rounded-lg text-ivory">
                                        <p x-show="reg.emailConfirm && reg.email !== reg.emailConfirm" class="text-red-300 text-xs mt-1">Adresele nu coincid.</p>
                                    </div>
                                    <div><label class="block text-sm text-warm-gray mb-2">Telefon *</label><input type="tel" x-model="reg.phone" class="input-field w-full px-4 py-3 rounded-lg text-ivory"></div>
                                    <div><label class="block text-sm text-warm-gray mb-2">Parolă * (min 8)</label><input type="password" x-model="reg.password" autocomplete="new-password" class="input-field w-full px-4 py-3 rounded-lg text-ivory"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <label class="flex items-start gap-3 cursor-pointer mb-6">
                        <input type="checkbox" x-model="terms" class="w-5 h-5 rounded border-gold/30 bg-midnight text-gold mt-0.5">
                        <span class="text-sm text-ivory/80">Sunt de acord cu <a href="/termeni" class="text-gold hover:underline">termenii</a> și <a href="/confidentialitate" class="text-gold hover:underline">politica de confidențialitate</a>. *</span>
                    </label>
                    <div x-show="error" x-text="error" class="bg-red-900/30 border border-red-500/40 text-red-200 rounded-lg p-3 text-sm mb-4"></div>
                    <div class="flex gap-3">
                        <button @click="step = 2" :disabled="busy" class="btn-outline px-6 py-4 rounded-lg">← Înapoi</button>
                        <button @click="pay()" :disabled="busy || !canPay" class="btn-gold flex-1 py-4 rounded-lg text-lg" x-text="busy ? 'Se procesează...' : 'Cumpără abonamentul'"></button>
                    </div>
                </div>
            </div>

            <!-- Sidebar summary -->
            <div class="lg:col-span-1">
                <div class="bg-charcoal rounded-2xl border border-gold/10 p-6 sticky top-28">
                    <h2 class="font-display text-xl mb-1">Abonament <?= e($plan['name'] ?? '') ?></h2>
                    <p class="font-display text-4xl text-gold mb-4"><?= (int) round((float) ($plan['price'] ?? 0)) ?> <span class="text-lg"><?= e($plan['currency'] ?? 'RON') ?></span></p>
                    <ul class="space-y-2 mb-5 text-sm">
                        <li class="flex items-center gap-2"><span class="text-gold">✓</span> <?= $showsIncluded ?> spectacole incluse</li>
                        <li class="flex items-center gap-2"><span class="text-gold">✓</span> <?= $ticketsIncluded ?> loc<?= $ticketsIncluded === 1 ? '' : 'uri' ?> per spectacol</li>
                        <?php foreach (array_slice($benefits, 0, 4) as $b): ?>
                        <li class="flex items-center gap-2"><span class="text-gold">✓</span> <?= e($b) ?></li>
                        <?php endforeach; ?>
                    </ul>

                    <p class="text-sm text-warm-gray mb-2">Locuri alese (<span x-text="selected.length"></span>/<?= $ticketsIncluded ?>)</p>
                    <div class="space-y-2 mb-5 min-h-[1.5rem]">
                        <template x-for="s in selected" :key="s.seat_uid">
                            <div class="flex items-center justify-between text-sm bg-midnight/60 rounded-lg px-3 py-2">
                                <span x-text="s.section + ' · R' + s.row + ' L' + s.seat_label"></span>
                                <button x-show="step === 1" @click="remove(s)" class="text-warm-gray hover:text-burgundy-light">✕</button>
                            </div>
                        </template>
                        <p x-show="selected.length === 0" class="text-warm-gray text-sm">Niciun loc ales.</p>
                    </div>

                    <button x-show="step === 1" @click="goStep2()" :disabled="selected.length !== <?= $ticketsIncluded ?>" class="btn-gold w-full py-4 rounded-lg text-lg">Continuă</button>
                    <p x-show="step === 1" class="text-warm-gray text-xs text-center mt-3">Locurile alese devin locurile tale la toate spectacolele incluse.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Login modal -->
    <div x-show="loginModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/70" @click.self="loginModal=false">
        <div class="bg-charcoal rounded-2xl border border-gold/20 p-8 w-full max-w-md">
            <div class="flex items-center justify-between mb-6">
                <h3 class="font-display text-2xl">Autentificare</h3>
                <button @click="loginModal=false" class="text-warm-gray hover:text-ivory text-2xl leading-none">&times;</button>
            </div>
            <div class="bg-gold/10 border border-gold/20 rounded-lg p-3 mb-5 text-xs text-ivory/70">Cont demo: <span class="font-mono">demo@teatru.tixello.ro</span> / <span class="font-mono">demo1234</span></div>
            <div class="space-y-4">
                <div><label class="block text-sm text-warm-gray mb-2">Email</label><input type="email" x-model="loginForm.email" class="input-field w-full px-4 py-3 rounded-lg text-ivory"></div>
                <div><label class="block text-sm text-warm-gray mb-2">Parolă</label><input type="password" x-model="loginForm.password" @keydown.enter="doLogin()" class="input-field w-full px-4 py-3 rounded-lg text-ivory"></div>
                <div x-show="loginErr" x-text="loginErr" class="bg-red-900/30 border border-red-500/40 text-red-200 rounded-lg p-3 text-sm"></div>
                <button @click="doLogin()" :disabled="loginBusy" class="btn-gold w-full py-3 rounded-lg" x-text="loginBusy ? 'Se autentifică...' : 'Intră în cont'"></button>
            </div>
        </div>
    </div>
</div>
<script>
function subFlow() {
    return {
        step: 1, mode: '', busy: false, error: '', terms: false,
        token: null, user: null,
        loginModal: false, loginBusy: false, loginErr: '', loginForm: { email:'', password:'' },
        reg: { firstName:'', lastName:'', email:'', emailConfirm:'', phone:'', password:'' },
        // seat map
        loading: true, hasSeating: false, sections: [], selected: [], eventSeatingId: null,
        ticketsIncluded: <?= $ticketsIncluded ?>,
        allowed: <?= json_encode($allowedSections, JSON_UNESCAPED_UNICODE) ?>,
        repEventId: <?= $repEventId ?>,

        init() {
            let a = null; try { a = JSON.parse(localStorage.getItem('teatru_auth') || 'null'); } catch(e) {}
            if (a && a.token) { this.token = a.token; this.user = a.user; }
            this.load();
        },
        hasToken() { return !!this.token; },
        get canPay() {
            if (!this.terms) return false;
            if (this.mode === 'new') {
                return this.reg.firstName && this.reg.lastName && this.reg.email && this.reg.phone
                    && this.reg.email === this.reg.emailConfirm && this.reg.password && this.reg.password.length >= 8;
            }
            return this.mode === 'existing' && this.token;
        },

        // ---- seat map ----
        sectionAllowed(name) { return !this.allowed.length || this.allowed.some(z => z.toLowerCase() === (name||'').toLowerCase()); },
        async load() {
            if (!this.repEventId) { this.loading = false; return; }
            try {
                const meta = await (await fetch('/api/proxy.php?action=seating&event=' + this.repEventId)).json();
                if (!meta || !meta.event_seating_id) { this.loading = false; return; }
                this.eventSeatingId = meta.event_seating_id;
                const tiers = {}; (meta.price_tiers || []).forEach(t => { tiers[t.id] = t; });
                const seatsResp = await (await fetch('/api/proxy.php?action=seats&event=' + this.repEventId + '&limit=5000')).json();
                const seats = seatsResp.data || seatsResp.seats || seatsResp || [];
                if (!Array.isArray(seats) || seats.length === 0) { this.loading = false; return; }
                const secMap = {};
                seats.forEach(s => {
                    const price = s.price_cents ? Math.round(s.price_cents/100) : (tiers[s.price_tier_id] ? Math.round((tiers[s.price_tier_id].price_cents||0)/100) : null);
                    if (!secMap[s.section_name]) secMap[s.section_name] = { name: s.section_name, rows: {}, price };
                    if (price && !secMap[s.section_name].price) secMap[s.section_name].price = price;
                    if (!secMap[s.section_name].rows[s.row_label]) secMap[s.section_name].rows[s.row_label] = [];
                    secMap[s.section_name].rows[s.row_label].push({ ...s, price });
                });
                const sections = Object.values(secMap).map(sec => ({
                    name: sec.name, price: sec.price, color: null, vip: false, allowed: this.sectionAllowed(sec.name),
                    rows: Object.keys(sec.rows).sort().map(label => ({ label, seats: sec.rows[label].sort((a,b)=>(parseInt(a.seat_label)||0)-(parseInt(b.seat_label)||0)) }))
                }));
                const maxPrice = Math.max(...sections.map(s => s.price || 0));
                sections.forEach(s => { s.vip = (s.price||0) === maxPrice && maxPrice > 0; s.color = s.vip ? '#D4AF37' : '#722F37'; });
                this.sections = sections; this.hasSeating = true;
            } catch (e) { console.error(e); }
            this.loading = false;
        },
        isSelected(seat) { return this.selected.some(s => s.seat_uid === seat.seat_uid); },
        seatClass(seat, section) {
            if (seat.status === 'sold' || seat.status === 'held' || !section.allowed) return 'taken';
            let c = this.isSelected(seat) ? 'selected' : 'available';
            if (!this.isSelected(seat) && section.vip) c += ' vip';
            return c;
        },
        seatRow(section, seat) { for (const r of section.rows) { if (r.seats.some(x => x.seat_uid === seat.seat_uid)) return r.label; } return ''; },
        toggle(seat, section) {
            if (seat.status === 'sold' || seat.status === 'held' || !section.allowed) return;
            if (this.isSelected(seat)) { return this.remove(this.selected.find(s => s.seat_uid === seat.seat_uid)); }
            if (this.selected.length >= this.ticketsIncluded) { this.error = 'Ai ales deja numărul maxim de locuri.'; return; }
            this.error = '';
            this.selected.push({ seat_uid: seat.seat_uid, section: section.name, row: this.seatRow(section, seat), seat_label: seat.seat_label });
        },
        remove(s) { if (s) this.selected = this.selected.filter(x => x.seat_uid !== s.seat_uid); },

        // ---- flow ----
        goStep2() { if (this.selected.length === this.ticketsIncluded) { this.error=''; this.step = 2; } },
        chooseExisting() {
            if (this.hasToken()) { this.mode = 'existing'; this.step = 3; }
            else { this.loginErr=''; this.loginModal = true; }
        },
        chooseNew() { this.mode = 'new'; this.step = 3; },
        async doLogin() {
            if (this.loginBusy) return;
            this.loginBusy = true; this.loginErr = '';
            try {
                const r = await fetch('/api/proxy.php?action=login', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ email:this.loginForm.email, password:this.loginForm.password }) });
                const d = await r.json().catch(()=>({}));
                if (r.ok && d.success && d.data && d.data.token) {
                    this.token = d.data.token; this.user = d.data.user;
                    localStorage.setItem('teatru_auth', JSON.stringify({ token:this.token, user:this.user }));
                    this.loginModal = false; this.mode = 'existing'; this.step = 3;
                } else { this.loginErr = (d.message || (d.errors && d.errors.email && d.errors.email[0])) || 'Date incorecte.'; }
            } catch (e) { this.loginErr = 'Eroare de conexiune.'; }
            this.loginBusy = false;
        },
        async pay() {
            if (this.busy || !this.canPay) return;
            this.busy = true; this.error = '';
            try {
                // Cont nou: înregistrează-l întâi
                if (this.mode === 'new') {
                    const rr = await fetch('/api/proxy.php?action=register', {
                        method:'POST', headers:{'Content-Type':'application/json'},
                        body: JSON.stringify({ first_name:this.reg.firstName, last_name:this.reg.lastName, email:this.reg.email, phone:this.reg.phone, password:this.reg.password })
                    });
                    const rd = await rr.json().catch(()=>({}));
                    if (rr.ok && rd.success && rd.data && rd.data.token) {
                        this.token = rd.data.token; this.user = rd.data.user;
                        localStorage.setItem('teatru_auth', JSON.stringify({ token:this.token, user:this.user }));
                    } else {
                        this.error = (rd.message || (rd.errors && Object.values(rd.errors)[0] && Object.values(rd.errors)[0][0])) || 'Nu am putut crea contul.';
                        this.busy = false; return;
                    }
                }
                // Cumpără abonamentul
                const payload = {
                    plan_slug: '<?= e($planSlug) ?>',
                    seat_uids: this.selected.map(s => s.seat_uid),
                    seat_labels: this.selected.map(s => s.section + ' Rând ' + s.row + ', Loc ' + s.seat_label),
                    event_seating_id: this.eventSeatingId,
                    success_url: window.location.origin + '/cont',
                    cancel_url: window.location.origin + '/abonamente'
                };
                const r = await fetch('/api/proxy.php?action=subscribe', {
                    method:'POST', headers:{'Content-Type':'application/json','Authorization':'Bearer ' + this.token}, body: JSON.stringify(payload)
                });
                const d = await r.json().catch(()=>({}));
                if (r.ok && d.success && d.redirect_url) { window.location.href = d.redirect_url; }
                else { this.error = d.error || 'Nu am putut iniția plata.'; this.busy = false; }
            } catch (e) { this.error = 'Eroare de conexiune.'; this.busy = false; }
        }
    };
}
</script>
<?php include __DIR__ . '/includes/footer.php';
