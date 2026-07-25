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
$allowedSections = array_values(array_filter($plan['allowed_sections'] ?? []));

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
';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<div x-data="subCheckout()" x-init="init()" class="pt-28 pb-16 px-4 lg:px-8">
    <div class="max-w-5xl mx-auto">
        <a href="/abonamente" class="text-warm-gray text-sm hover:text-gold">← Înapoi la abonamente</a>
        <h1 class="font-display text-4xl mt-3 mb-2">Abonament <?= e($plan['name'] ?? '') ?></h1>
        <p class="text-ivory/70 mb-8">
            Alege <strong class="text-gold"><?= $ticketsIncluded ?></strong> loc<?= $ticketsIncluded === 1 ? '' : 'uri' ?> — <?= (int) ($plan['shows_included'] ?? 0) ?> spectacole incluse, valabil toată stagiunea.
            <?php if ($allowedSections): ?><br>Zone permise: <span class="text-gold"><?= e(implode(', ', $allowedSections)) ?></span>.<?php endif; ?>
        </p>

        <!-- Auth gate -->
        <div x-show="!authChecked" class="text-center py-16"><svg class="w-8 h-8 animate-spin text-gold mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg></div>

        <div x-show="authChecked" x-cloak class="grid lg:grid-cols-3 gap-8">
            <!-- Seat map -->
            <div class="lg:col-span-2">
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
                                                <div @click="toggle(seat, section)" :class="seatClass(seat, section)" class="seat"
                                                    :title="section.name + ' ' + row.label + seat.seat_label">
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

            <!-- Summary -->
            <div class="lg:col-span-1">
                <div class="bg-charcoal rounded-2xl border border-gold/10 p-6 sticky top-28">
                    <h2 class="font-display text-xl mb-1">Abonament <?= e($plan['name'] ?? '') ?></h2>
                    <p class="font-display text-4xl text-gold mb-5"><?= (int) round((float) ($plan['price'] ?? 0)) ?> <span class="text-lg"><?= e($plan['currency'] ?? 'RON') ?></span></p>

                    <p class="text-sm text-warm-gray mb-2">Locuri alese (<span x-text="selected.length"></span>/<?= $ticketsIncluded ?>)</p>
                    <div class="space-y-2 mb-5 min-h-[2rem]">
                        <template x-for="s in selected" :key="s.seat_uid">
                            <div class="flex items-center justify-between text-sm bg-midnight/60 rounded-lg px-3 py-2">
                                <span x-text="s.section + ' · Rând ' + s.row + ', Loc ' + s.seat_label"></span>
                                <button @click="remove(s)" class="text-warm-gray hover:text-burgundy-light">✕</button>
                            </div>
                        </template>
                        <p x-show="selected.length === 0" class="text-warm-gray text-sm">Niciun loc ales încă.</p>
                    </div>

                    <div x-show="error" x-text="error" class="bg-red-900/30 border border-red-500/40 text-red-200 rounded-lg p-3 text-sm mb-4"></div>
                    <button @click="pay()" :disabled="loading2 || selected.length !== <?= $ticketsIncluded ?>" class="btn-gold w-full py-4 rounded-lg text-lg"
                        x-text="loading2 ? 'Se procesează...' : 'Cumpără abonamentul'"></button>
                    <p class="text-warm-gray text-xs text-center mt-3">Locurile alese devin locurile tale la toate spectacolele incluse.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function subCheckout() {
    return {
        authChecked: false, token: null,
        loading: true, loading2: false, hasSeating: false, sections: [], selected: [], error: '',
        eventSeatingId: null,
        ticketsIncluded: <?= $ticketsIncluded ?>,
        allowed: <?= json_encode($allowedSections, JSON_UNESCAPED_UNICODE) ?>,
        repEventId: <?= $repEventId ?>,
        init() {
            let a = null; try { a = JSON.parse(localStorage.getItem('teatru_auth') || 'null'); } catch(e) {}
            if (!a || !a.token) { window.location.href = '/autentificare?next=' + encodeURIComponent(location.pathname + location.search); return; }
            this.token = a.token; this.authChecked = true;
            this.load();
        },
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
            if (this.selected.length >= this.ticketsIncluded) { this.error = 'Ai ales deja numărul maxim de locuri pentru acest abonament.'; return; }
            this.error = '';
            this.selected.push({ seat_uid: seat.seat_uid, section: section.name, row: this.seatRow(section, seat), seat_label: seat.seat_label });
        },
        remove(s) { if (s) this.selected = this.selected.filter(x => x.seat_uid !== s.seat_uid); },
        async pay() {
            if (this.loading2) return;
            if (this.selected.length !== this.ticketsIncluded) { this.error = 'Alege exact ' + this.ticketsIncluded + ' loc(uri).'; return; }
            this.loading2 = true; this.error = '';
            try {
                const payload = {
                    plan_slug: '<?= e($planSlug) ?>',
                    seat_uids: this.selected.map(s => s.seat_uid),
                    seat_labels: this.selected.map(s => s.section + ' Rând ' + s.row + ', Loc ' + s.seat_label),
                    event_seating_id: this.eventSeatingId,
                    success_url: window.location.origin + '/cont',
                    cancel_url: window.location.origin + '/abonamente'
                };
                const r = await fetch('/api/proxy.php?action=subscribe', {
                    method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + this.token }, body: JSON.stringify(payload)
                });
                const d = await r.json().catch(()=>({}));
                if (r.ok && d.success && d.redirect_url) { window.location.href = d.redirect_url; }
                else { this.error = d.error || 'Nu am putut iniția plata.'; this.loading2 = false; }
            } catch (e) { this.error = 'Eroare de conexiune.'; this.loading2 = false; }
        }
    };
}
</script>
<?php include __DIR__ . '/includes/footer.php';
