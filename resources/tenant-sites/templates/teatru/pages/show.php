<?php

$slug = $_GET['slug'] ?? '';
if ($slug === '') { header('Location: /repertoriu'); exit; }

$resp  = api_get('/tenant-client/events/' . rawurlencode($slug), [], 60);
$event = ($resp['success'] && !empty($resp['data'])) ? tc_norm_event($resp['data']) : null;

if (!$event) {
    http_response_code(404);
    $pageTitle = 'Spectacol negăsit — ' . SITE_NAME;
    include ts_layout('head');
    include ts_layout('header');
    echo '<section class="pt-40 pb-40 px-4 text-center"><h1 class="font-display text-4xl mb-4">Spectacol negăsit</h1><a href="/repertoriu" class="btn-primary px-6 py-3 rounded-lg inline-block mt-4">Vezi repertoriul</a></section>';
    include ts_layout('footer');
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
$fallback = 'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=1920&q=80';
$poster   = asset_url($event['poster_url'] ?? $event['hero_image_url'] ?? null, 'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=600&q=80');
$th        = $event['theater'] ?? [];
$director  = $th['director'] ?? null;
$lead      = $th['lead'] ?? ($event['artists'][0]['name'] ?? null);
$duration  = $th['duration'] ?? null;
$castList  = $th['cast'] ?? [];
$creative  = $th['creative'] ?? [];

// Bilete fără loc (acces general): tipurile active, cu prețul redus dacă există
$types = [];
foreach (($event['ticket_types'] ?? []) as $t) {
    if (($t['status'] ?? 'active') !== 'active' || empty($t['id'])) { continue; }
    $full = (float) ($t['price'] ?? 0);
    $sale = isset($t['sale_price']) && $t['sale_price'] !== null ? (float) $t['sale_price'] : null;
    $types[] = [
        'id'        => (int) $t['id'],
        'name'      => (string) ($t['name'] ?? 'Bilet'),
        'desc'      => trim(strip_tags((string) ($t['description'] ?? ''))),
        'price'     => ($sale !== null && $sale > 0 && $sale < $full) ? $sale : $full,
        'full'      => $full,
        'available' => (int) ($t['available'] ?? 0),
    ];
}
// Evenimentul așa cum îl ține coșul comun (commerce.js)
$cartEvent = [
    'id'     => $eventId,
    'slug'   => (string) ($event['slug'] ?? $slug),
    'title'  => (string) ($event['title'] ?? ''),
    'date'   => $dateStr,
    'place'  => $venueName,
    'poster' => $poster,
    'url'    => ts_link('event', ['slug' => $event['slug'] ?? $slug]),
];
$pageExtraStyles = '
    .seat { width: 24px; height: 24px; border-radius: 5px 5px 3px 3px; cursor: pointer; display:flex; align-items:center; justify-content:center; font-size:9px; font-weight:700; transition: all .15s ease; }
    .seat.available { background: rgb(var(--c-accent-dark)); border: 1px solid rgb(var(--c-accent)); }
    .seat.available:hover { background: rgb(var(--c-accent)); transform: translateY(-2px); }
    .seat.available.vip { background: rgb(var(--c-primary) / .18); border: 1px solid rgb(var(--c-primary) / .5); }
    .seat.available.vip:hover { background: rgb(var(--c-primary)); color:rgb(var(--c-canvas)); }
    .seat.selected { background: rgb(var(--c-primary)); border-color:rgb(var(--c-primary)); color:rgb(var(--c-canvas)); }
    .seat.taken { background: rgb(var(--c-surface)); border: 1px solid rgb(var(--c-surface)); cursor: not-allowed; opacity:.35; }
    .seat.busy { opacity:.5; pointer-events:none; }
    .stage { background: linear-gradient(180deg, rgb(var(--c-surface)) 0%, rgb(var(--c-canvas)) 100%); border: 1px solid rgb(var(--c-primary) / .3); border-radius: 4px 4px 50% 50% / 4px 4px 20% 20%; }
';
include ts_layout('head');
include ts_layout('header');
?>
<!-- Hero -->
<section class="relative pt-20">
    <div class="absolute inset-0 h-[50vh]">
        <img src="<?= e($hero) ?>" alt="<?= e($event['title'] ?? '') ?>" class="w-full h-full object-cover opacity-30">
        <div class="absolute inset-0 bg-gradient-to-t from-canvas via-canvas/80 to-canvas/40"></div>
    </div>
    <div class="relative z-10 max-w-7xl mx-auto px-4 lg:px-8 pt-10 pb-8">
        <a href="/program" class="text-fg/70 hover:text-primary text-sm transition-colors">← Înapoi la program</a>
        <div class="flex flex-col lg:flex-row gap-8 mt-6">
            <!-- Poster -->
            <div class="lg:w-80 flex-shrink-0">
                <img src="<?= e($poster) ?>" alt="<?= e($event['title'] ?? '') ?>" class="w-full rounded-lg border border-primary/20 shadow-2xl aspect-[3/4] object-cover">
            </div>
            <!-- Info -->
            <div class="flex-1">
                <?php if (!empty($event['category']['name'])): ?>
                    <div class="flex items-center gap-3 mb-4"><span class="text-primary"><?= e($event['category']['name']) ?></span></div>
                <?php endif; ?>
                <?php if ($lead): ?><p class="text-primary tracking-wider text-sm mb-2 uppercase"><?= e($lead) ?></p><?php endif; ?>
                <div class="flex items-start justify-between gap-4">
                    <h1 class="font-display text-5xl lg:text-7xl italic mb-3"><?= e($event['title'] ?? 'Spectacol') ?></h1>
                    <button x-data="favBtn('event', <?= $eventId ?>, <?= htmlspecialchars(json_encode(['title' => $event['title'] ?? '', 'slug' => $slug, 'category' => $event['category']['name'] ?? ''], JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)" x-init="init()" @click="toggle()" :aria-pressed="on" title="Adaugă la favorite"
                        class="mt-2 grid h-12 w-12 shrink-0 place-items-center rounded-full border transition-colors"
                        :class="on ? 'border-primary bg-primary/15 text-primary' : 'border-primary/25 text-fg/70 hover:border-primary/60 hover:text-primary'">
                        <svg class="h-6 w-6" :fill="on ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M12 21s-7.5-4.6-10-9.3C.5 8.4 2 4.8 5.3 4.8c2 0 3.3 1.2 4.2 2.5.9-1.3 2.2-2.5 4.2-2.5 3.3 0 4.8 3.6 3.3 6.9C19.5 16.4 12 21 12 21Z"/></svg>
                    </button>
                </div>
                <?php if (!empty($event['subtitle'])): ?>
                    <p class="text-fg/60 font-display text-xl lg:text-2xl italic mb-6"><?= e($event['subtitle']) ?></p>
                <?php endif; ?>
                <?php if (!empty($event['short_description'])): ?>
                    <p class="text-fg/70 text-lg leading-relaxed mb-8 max-w-2xl"><?= e($event['short_description']) ?></p>
                <?php endif; ?>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-4">
                    <?php if ($director): ?><div><p class="text-muted text-sm">Regia</p><p class="font-display text-lg"><?= e($director) ?></p></div><?php endif; ?>
                    <?php if ($lead): ?><div><p class="text-muted text-sm">În rolul principal</p><p class="font-display text-lg"><?= e($lead) ?></p></div><?php endif; ?>
                    <?php if ($duration): ?><div><p class="text-muted text-sm">Durata</p><p class="font-display text-lg"><?= e($duration) ?></p></div><?php endif; ?>
                    <?php if ($venueName): ?><div><p class="text-muted text-sm">Sală</p><p class="font-display text-lg"><?= e($venueName) ?></p></div><?php endif; ?>
                    <?php if ($dateStr): ?><div><p class="text-muted text-sm">Data</p><p class="font-display text-lg"><?= e($dateStr) ?></p></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Seat selection -->
<section class="py-10 px-4 lg:px-8 bg-surface/30" x-data="seatMap(<?= $eventId ?>)" x-init="load()">
    <div class="max-w-7xl mx-auto">
        <div class="divider-ornate mb-8"><span class="text-primary font-display text-xl">Alege locurile</span></div>

        <!-- Loading -->
        <div x-show="loading" class="text-center py-16 text-muted">Se încarcă harta sălii…</div>

        <!-- No seating configured -->
        <div x-show="!loading && !hasSeating">
            <?php if ($types): ?>
            <div x-data='ticketPicker(<?= e(json_encode($cartEvent, JSON_UNESCAPED_UNICODE)) ?>, <?= e(json_encode($types, JSON_UNESCAPED_UNICODE)) ?>)' class="max-w-2xl mx-auto">
                <div class="space-y-3">
                    <template x-for="t in types" :key="t.id">
                        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl bg-surface/60 border border-primary/10 px-5 py-4">
                            <div class="min-w-0">
                                <p class="font-display text-lg" x-text="t.name"></p>
                                <p class="text-sm text-muted" x-show="t.desc" x-text="t.desc"></p>
                                <p class="mt-1 text-primary font-display">
                                    <span x-text="lei(t.price)"></span>
                                    <s class="ml-2 text-sm text-muted" x-show="t.full > t.price" x-text="lei(t.full)"></s>
                                </p>
                            </div>
                            <div class="inline-flex items-center rounded-full border border-primary/20">
                                <button type="button" class="h-10 w-10 text-xl disabled:opacity-30" @click="dec(t)" :disabled="!qty[t.id]" :aria-label="'Scade ' + t.name">−</button>
                                <output class="min-w-[2rem] text-center font-semibold" x-text="qty[t.id]"></output>
                                <button type="button" class="h-10 w-10 text-xl disabled:opacity-30" @click="inc(t)" :disabled="qty[t.id] >= limit(t)" :aria-label="'Adaugă ' + t.name">+</button>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-xl bg-surface p-5">
                    <div><p class="text-sm text-muted" x-text="ticketsLabel(count)"></p><p data-total class="font-display text-3xl text-primary" x-text="lei(total)"></p></div>
                    <button type="button" @click="save()" class="btn-primary rounded-lg px-8 py-4 text-lg" :disabled="!count">Adaugă în coș</button>
                </div>
            </div>
            <?php else: ?>
            <p class="text-center py-12 text-muted">Biletele pentru acest spectacol nu sunt încă puse în vânzare.</p>
            <?php endif; ?>
        </div>

        <!-- Seat map -->
        <div x-show="!loading && hasSeating">
            <div class="bg-surface rounded-xl p-6 overflow-x-auto">
                <div class="stage w-64 h-12 mx-auto mb-10 flex items-center justify-center text-muted text-sm tracking-widest">SCENA</div>
                <div class="flex flex-col items-center gap-8 min-w-[640px]">
                    <template x-for="section in sections" :key="section.name">
                        <div class="w-full">
                            <p class="text-center text-xs tracking-wider mb-3" :style="'color:' + (section.color || 'rgb(var(--c-primary))')">
                                <span x-text="section.name.toUpperCase()"></span>
                                <span x-show="section.price" x-text="' — ' + section.price + ' RON'"></span>
                            </p>
                            <div class="flex flex-col items-center gap-1.5">
                                <template x-for="row in section.rows" :key="row.label">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-5 text-xs text-muted text-right" x-text="row.label"></span>
                                        <template x-for="seat in row.seats" :key="seat.seat_uid">
                                            <div
                                                @click="toggle(seat, section)"
                                                :class="seatClass(seat, section)"
                                                class="seat"
                                                :title="seat.status === 'sold' ? 'Ocupat' : (section.name + ' ' + row.label + seat.seat_label + ' — ' + (seat.price||section.price||'') + ' RON')">
                                                <span x-show="isSelected(seat)" x-text="seat.seat_label"></span>
                                            </div>
                                        </template>
                                        <span class="w-5 text-xs text-muted" x-text="row.label"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Legend -->
                <div class="flex flex-wrap justify-center gap-6 mt-10 text-sm">
                    <div class="flex items-center gap-2"><div class="seat available"></div><span class="text-muted">Disponibil</span></div>
                    <div class="flex items-center gap-2"><div class="seat available vip"></div><span class="text-muted">Categorie superioară</span></div>
                    <div class="flex items-center gap-2"><div class="seat selected"></div><span class="text-muted">Selectat</span></div>
                    <div class="flex items-center gap-2"><div class="seat taken"></div><span class="text-muted">Ocupat</span></div>
                </div>
            </div>

            <!-- Summary -->
            <div x-show="selected.length > 0" x-transition class="mt-8 bg-surface rounded-xl p-6 max-w-2xl mx-auto">
                <h3 class="font-display text-lg mb-4">Locurile tale <span class="text-muted text-sm">(rezervate 15 min)</span></h3>
                <div class="space-y-2 mb-6">
                    <template x-for="s in selected" :key="s.seat_uid">
                        <div class="flex items-center justify-between py-2 border-b border-primary/10">
                            <div>
                                <p class="font-medium" x-text="s.section"></p>
                                <p class="text-sm text-muted" x-text="'Rând ' + s.row + ', Loc ' + s.seat_label"></p>
                            </div>
                            <div class="flex items-center gap-4">
                                <p class="text-primary font-display" x-text="(s.price||0) + ' RON'"></p>
                                <button @click="remove(s)" class="text-muted hover:text-accent">✕</button>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="flex items-center justify-between mb-6">
                    <p class="text-lg">Total</p>
                    <p class="font-display text-3xl text-primary" x-text="total + ' RON'"></p>
                </div>
                <button @click="checkout()" class="btn-primary w-full py-4 rounded-lg text-lg block text-center">Continuă spre plată</button>
                <p x-show="error" x-text="error" class="text-accent-light text-sm mt-3 text-center"></p>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($event['description']) || !empty($castList) || !empty($creative)): ?>
<!-- Despre spectacol -->
<section class="py-16 px-4 lg:px-8">
    <div class="max-w-4xl mx-auto">
        <?php if (!empty($event['description'])): ?>
        <h2 class="font-display text-3xl mb-6">Despre spectacol</h2>
        <div class="prose prose-invert prose-lg max-w-none text-fg/70"><?= $event['description'] ?></div>
        <?php endif; ?>

        <?php if (!empty($castList)): ?>
        <div class="mt-12">
            <h3 class="font-display text-2xl mb-6">Distribuție</h3>
            <div class="grid md:grid-cols-2 gap-3">
                <?php foreach ($castList as $c): ?>
                <div class="flex items-center justify-between gap-4 bg-surface/50 rounded-lg px-5 py-4">
                    <?php if (!empty($c['slug'])): ?>
                        <a href="/artist/<?= e($c['slug']) ?>" class="font-display text-lg text-primary hover:text-primary/80 transition-colors"><?= e($c['name'] ?? '') ?></a>
                    <?php else: ?>
                        <p class="font-display text-lg"><?= e($c['name'] ?? '') ?></p>
                    <?php endif; ?>
                    <?php if (!empty($c['role'])): ?><p class="text-muted text-sm text-right"><?= e($c['role']) ?></p><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($creative)): ?>
        <div class="mt-12">
            <h3 class="font-display text-2xl mb-6">Echipa creativă</h3>
            <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-4">
                <?php foreach ($creative as $cr): ?>
                <div class="bg-surface/40 rounded-lg p-4">
                    <p class="text-muted text-sm"><?= e($cr['role'] ?? '') ?></p>
                    <?php if (!empty($cr['slug'])): ?>
                        <a href="/artist/<?= e($cr['slug']) ?>" class="font-display text-lg text-primary hover:text-primary/80 transition-colors"><?= e($cr['name'] ?? '') ?></a>
                    <?php else: ?>
                        <p class="font-display text-lg"><?= e($cr['name'] ?? '') ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($event['gallery'])): ?>
<!-- Galerie foto -->
<section class="py-16 px-4 lg:px-8 bg-surface/20">
    <div class="max-w-6xl mx-auto">
        <div class="divider-ornate mb-10"><span class="text-primary font-display text-2xl">Galerie</span></div>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <?php foreach ($event['gallery'] as $img): ?>
            <a href="<?= e($img) ?>" target="_blank" class="block aspect-[4/3] overflow-hidden rounded-lg border border-primary/10 group">
                <img src="<?= e($img) ?>" alt="<?= e($event['title'] ?? '') ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
            </a>
            <?php endforeach; ?>
        </div>
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
                // colorare: prima secțiune (după preț) = accent, restul = vip/primary
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
                sections.forEach(s => { s.vip = (s.price || 0) === maxPrice && maxPrice > 0; s.color = s.vip ? 'rgb(var(--c-primary))' : 'rgb(var(--c-accent))'; });
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
            // Coșul comun (commerce.js): locurile sunt deja blocate pe server, cât ține rezervarea
            const seats = this.selected.map(s => ({ seat_uid: s.seat_uid, section: s.section, row: s.row, seat: s.seat_label, price: s.price, label: s.section + ' · Rând ' + s.row + ' · Loc ' + s.seat_label }));
            const prev = window.TS ? TS.Cart.get() : null;
            const same = prev && prev.event.id === <?= (int) $eventId ?> && prev.seats.length;
            TS.Cart.set({
                event: <?= json_encode($cartEvent, JSON_UNESCAPED_UNICODE) ?>,
                items: [], seats: seats, event_seating_id: this.eventSeatingId,
                expires_at: same ? prev.expires_at : Date.now() + TS.SEAT_HOLD_MS,
                coupon_code: same ? (prev.coupon_code || null) : null
            });
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
include ts_layout('footer');
