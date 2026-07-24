<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$slug = $_GET['slug'] ?? '';
if ($slug === '') { header('Location: /repertoriu'); exit; }

$resp  = api_get('/tenant-client/events/' . rawurlencode($slug), [], 60);
$event = $resp['success'] ? ($resp['data'] ?? null) : null;

if (!$event) {
    http_response_code(404);
    $pageTitle = 'Spectacol negăsit — ' . SITE_NAME;
    include __DIR__ . '/includes/head.php';
    include __DIR__ . '/includes/header.php';
    echo '<section class="pt-40 pb-40 px-4 text-center"><h1 class="font-display text-4xl mb-4">Spectacol negăsit</h1><a href="/repertoriu" class="btn-gold px-6 py-3 rounded-lg inline-block mt-4">Vezi repertoriul</a></section>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$eventId  = (int) ($event['id'] ?? 0);
$fallback = 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=1200&q=80';
$hero     = asset_url($event['hero_image_url'] ?? $event['poster_url'] ?? null, $fallback);
$venueName = $event['venue']['name'] ?? '';
$dateStr = '';
if (!empty($event['start_date'])) {
    $ts = strtotime($event['start_date']);
    if ($ts) { $dateStr = date('d.m.Y', $ts) . (!empty($event['start_time']) ? ' · ' . substr($event['start_time'], 0, 5) : ''); }
}

$pageTitle = ($event['title'] ?? 'Spectacol') . ' — ' . SITE_NAME;
$pageDescription = $event['short_description'] ?? '';
$pageExtraStyles = '
    .seat { width: 22px; height: 22px; border-radius: 6px 6px 3px 3px; cursor: pointer; display:flex; align-items:center; justify-content:center; font-size:9px; font-weight:700; transition: all .15s ease; }
    .seat.available { background: rgba(114,47,55,.55); }
    .seat.available:hover { background: #8B3A44; transform: translateY(-2px); }
    .seat.available.vip { background: rgba(212,175,55,.35); }
    .seat.available.vip:hover { background: #D4AF37; color:#0A0A0A; }
    .seat.selected { background: #D4AF37; color:#0A0A0A; }
    .seat.taken { background: #2A2A2A; cursor: not-allowed; opacity:.5; }
    .seat.busy { opacity:.5; pointer-events:none; }
    .stage { background: linear-gradient(180deg, rgba(212,175,55,.15), transparent); border-top: 2px solid rgba(212,175,55,.4); border-radius: 50% 50% 0 0 / 100% 100% 0 0; }
    .date-btn { border: 1px solid rgba(212,175,55,.2); transition: all .2s ease; }
    .date-btn:hover { border-color:#D4AF37; }
    .date-btn.selected { background:#D4AF37; color:#0A0A0A; border-color:#D4AF37; }
';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<!-- Hero -->
<section class="relative pt-20">
    <div class="absolute inset-0 h-[420px] overflow-hidden">
        <img src="<?= e($hero) ?>" alt="<?= e($event['title'] ?? '') ?>" class="w-full h-full object-cover opacity-40">
        <div class="absolute inset-0 bg-gradient-to-t from-midnight via-midnight/70 to-transparent"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 lg:px-8 pt-32 pb-8">
        <?php if (!empty($event['category']['name'])): ?>
            <p class="text-gold tracking-[0.2em] text-sm mb-3 uppercase"><?= e($event['category']['name']) ?></p>
        <?php endif; ?>
        <h1 class="font-display text-4xl lg:text-6xl mb-4"><?= e($event['title'] ?? 'Spectacol') ?></h1>
        <div class="flex flex-wrap gap-6 text-warm-gray">
            <?php if ($venueName): ?><div><span class="text-ivory/50">Locație:</span> <span class="font-display text-lg text-ivory"><?= e($venueName) ?></span></div><?php endif; ?>
            <?php if ($dateStr): ?><div><span class="text-ivory/50">Data:</span> <span class="font-display text-lg text-ivory"><?= e($dateStr) ?></span></div><?php endif; ?>
        </div>
    </div>
</section>

<!-- Seat selection -->
<section class="py-10 px-4 lg:px-8 bg-charcoal/30" x-data="seatMap(<?= $eventId ?>)" x-init="load()">
    <div class="max-w-7xl mx-auto">
        <div class="divider-ornate mb-8"><span class="text-gold font-display text-xl">Alege locurile</span></div>

        <!-- Loading -->
        <div x-show="loading" class="text-center py-16 text-warm-gray">Se încarcă harta sălii…</div>

        <!-- No seating configured -->
        <div x-show="!loading && !hasSeating" class="text-center py-12 text-warm-gray">
            <p>Harta de sală pentru acest spectacol nu este încă disponibilă.</p>
        </div>

        <!-- Seat map -->
        <div x-show="!loading && hasSeating">
            <div class="bg-charcoal rounded-xl p-6 overflow-x-auto">
                <div class="stage w-64 h-12 mx-auto mb-10 flex items-center justify-center text-warm-gray text-sm tracking-widest">SCENA</div>
                <div class="flex flex-col items-center gap-8 min-w-[640px]">
                    <template x-for="section in sections" :key="section.name">
                        <div class="w-full">
                            <p class="text-center text-xs tracking-wider mb-3" :style="'color:' + (section.color || '#D4AF37')">
                                <span x-text="section.name.toUpperCase()"></span>
                                <span x-show="section.price" x-text="' — ' + section.price + ' RON'"></span>
                            </p>
                            <div class="flex flex-col items-center gap-1.5">
                                <template x-for="row in section.rows" :key="row.label">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-5 text-xs text-warm-gray text-right" x-text="row.label"></span>
                                        <template x-for="seat in row.seats" :key="seat.seat_uid">
                                            <div
                                                @click="toggle(seat, section)"
                                                :class="seatClass(seat, section)"
                                                class="seat"
                                                :title="seat.status === 'sold' ? 'Ocupat' : (section.name + ' ' + row.label + seat.seat_label + ' — ' + (seat.price||section.price||'') + ' RON')">
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

                <!-- Legend -->
                <div class="flex flex-wrap justify-center gap-6 mt-10 text-sm">
                    <div class="flex items-center gap-2"><div class="seat available"></div><span class="text-warm-gray">Disponibil</span></div>
                    <div class="flex items-center gap-2"><div class="seat available vip"></div><span class="text-warm-gray">Categorie superioară</span></div>
                    <div class="flex items-center gap-2"><div class="seat selected"></div><span class="text-warm-gray">Selectat</span></div>
                    <div class="flex items-center gap-2"><div class="seat taken"></div><span class="text-warm-gray">Ocupat</span></div>
                </div>
            </div>

            <!-- Summary -->
            <div x-show="selected.length > 0" x-transition class="mt-8 bg-charcoal rounded-xl p-6 max-w-2xl mx-auto">
                <h3 class="font-display text-lg mb-4">Locurile tale <span class="text-warm-gray text-sm">(rezervate 10 min)</span></h3>
                <div class="space-y-2 mb-6">
                    <template x-for="s in selected" :key="s.seat_uid">
                        <div class="flex items-center justify-between py-2 border-b border-gold/10">
                            <div>
                                <p class="font-medium" x-text="s.section"></p>
                                <p class="text-sm text-warm-gray" x-text="'Rând ' + s.row + ', Loc ' + s.seat_label"></p>
                            </div>
                            <div class="flex items-center gap-4">
                                <p class="text-gold font-display" x-text="(s.price||0) + ' RON'"></p>
                                <button @click="remove(s)" class="text-warm-gray hover:text-burgundy">✕</button>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="flex items-center justify-between mb-6">
                    <p class="text-lg">Total</p>
                    <p class="font-display text-3xl text-gold" x-text="total + ' RON'"></p>
                </div>
                <button @click="checkout()" class="btn-gold w-full py-4 rounded-lg text-lg block text-center">Continuă spre plată</button>
                <p x-show="error" x-text="error" class="text-burgundy-light text-sm mt-3 text-center"></p>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($event['description'])): ?>
<!-- Despre spectacol -->
<section class="py-16 px-4 lg:px-8">
    <div class="max-w-4xl mx-auto">
        <h2 class="font-display text-3xl mb-6">Despre spectacol</h2>
        <div class="prose prose-invert prose-lg max-w-none text-ivory/70"><?= $event['description'] ?></div>
        <?php if (!empty($event['artists'])): ?>
        <div class="mt-12">
            <h3 class="font-display text-2xl mb-6">Distribuție</h3>
            <div class="grid md:grid-cols-2 gap-4">
                <?php foreach ($event['artists'] as $a): ?>
                <div class="flex items-center gap-4 bg-charcoal/50 rounded-lg p-4">
                    <?php if (!empty($a['image'])): ?><img src="<?= e($a['image']) ?>" alt="<?= e($a['name']) ?>" class="w-16 h-16 rounded-full object-cover"><?php endif; ?>
                    <p class="font-display text-lg"><?= e($a['name']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<script>
function seatMap(eventId) {
    return {
        eventId, loading: true, hasSeating: false,
        eventSeatingId: null, sections: [], selected: [], error: '', busy: false,

        async load() {
            try {
                const meta = await (await fetch('/api/proxy.php?action=seating&event=' + this.eventId)).json();
                if (!meta || !meta.event_seating_id) { this.loading = false; return; }
                this.eventSeatingId = meta.event_seating_id;
                const tiers = {};
                (meta.price_tiers || []).forEach(t => { tiers[t.id] = t; });

                const seatsResp = await (await fetch('/api/proxy.php?action=seats&event=' + this.eventId + '&limit=5000')).json();
                const seats = seatsResp.data || seatsResp.seats || seatsResp || [];
                if (!Array.isArray(seats) || seats.length === 0) { this.loading = false; return; }

                // grupare secțiune -> rând -> locuri
                const secMap = {};
                seats.forEach(s => {
                    const price = s.price_cents ? Math.round(s.price_cents / 100) : (tiers[s.price_tier_id] ? Math.round((tiers[s.price_tier_id].price_cents||0)/100) : null);
                    if (!secMap[s.section_name]) secMap[s.section_name] = { name: s.section_name, rows: {}, price: price, color: null };
                    if (price && !secMap[s.section_name].price) secMap[s.section_name].price = price;
                    if (!secMap[s.section_name].rows[s.row_label]) secMap[s.section_name].rows[s.row_label] = [];
                    secMap[s.section_name].rows[s.row_label].push({ ...s, price });
                });
                // colorare: prima secțiune (după preț) = burgundy, restul = vip/gold
                const sections = Object.values(secMap).map(sec => ({
                    name: sec.name,
                    price: sec.price,
                    color: null,
                    vip: false,
                    rows: Object.keys(sec.rows).sort().map(label => ({
                        label,
                        seats: sec.rows[label].sort((a,b) => (parseInt(a.seat_label)||0) - (parseInt(b.seat_label)||0))
                    }))
                }));
                // secțiunea cu preț mai mare = "vip" (aur)
                const prices = sections.map(s => s.price || 0);
                const maxPrice = Math.max(...prices);
                sections.forEach(s => { s.vip = (s.price || 0) === maxPrice && maxPrice > 0; s.color = s.vip ? '#D4AF37' : '#722F37'; });
                this.sections = sections;
                this.hasSeating = true;
            } catch (e) { console.error(e); }
            this.loading = false;
        },

        isSelected(seat) { return this.selected.some(s => s.seat_uid === seat.seat_uid); },
        seatClass(seat, section) {
            if (seat.status === 'sold' || seat.status === 'held') return 'taken';
            let c = this.isSelected(seat) ? 'selected' : 'available';
            if (!this.isSelected(seat) && section.vip) c += ' vip';
            if (this.busy) c += ' busy';
            return c;
        },
        get total() { return this.selected.reduce((s, x) => s + (x.price || 0), 0); },

        async toggle(seat, section) {
            if (seat.status === 'sold' || seat.status === 'held' || this.busy) return;
            if (this.isSelected(seat)) { return this.remove(this.selected.find(s => s.seat_uid === seat.seat_uid)); }
            if (this.selected.length >= 10) { this.error = 'Maxim 10 locuri per comandă.'; return; }
            this.busy = true; this.error = '';
            try {
                const r = await fetch('/api/proxy.php?action=hold', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ event_seating_id: this.eventSeatingId, seat_uids: [seat.seat_uid] })
                });
                if (!r.ok) { const d = await r.json().catch(()=>({})); this.error = d.error || d.message || 'Locul nu mai este disponibil.'; seat.status = 'held'; }
                else { this.selected.push({ seat_uid: seat.seat_uid, section: section.name, row: seat_row(section, seat), seat_label: seat.seat_label, price: seat.price || section.price || 0 }); }
            } catch (e) { this.error = 'Eroare de conexiune.'; }
            this.busy = false;
        },
        async remove(s) {
            if (!s) return;
            this.selected = this.selected.filter(x => x.seat_uid !== s.seat_uid);
            try {
                await fetch('/api/proxy.php?action=release', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ event_seating_id: this.eventSeatingId, seat_uids: [s.seat_uid] })
                });
            } catch (e) {}
        },
        checkout() {
            if (this.selected.length === 0) return;
            localStorage.setItem('teatru_cart', JSON.stringify(this.selected));
            window.location.href = '/cos';
        }
    };
}
function seat_row(section, seat) {
    for (const row of section.rows) { if (row.seats.some(x => x.seat_uid === seat.seat_uid)) return row.label; }
    return '';
}
</script>
<?php
include __DIR__ . '/includes/footer.php';
