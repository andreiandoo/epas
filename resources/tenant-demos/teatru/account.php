<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
$pageTitle = 'Contul meu — ' . SITE_NAME;

// Evenimente (pentru consumul abonamentului) — injectate server-side
$eventsResp = api_get('/tenant-client/events', ['limit' => 30], 60);
$evList = [];
foreach (tc_events($eventsResp) as $ev) {
    $when = '';
    if (!empty($ev['start_date'])) { $ts = strtotime($ev['start_date']); if ($ts) { $when = date('d.m.Y', $ts); } }
    $evList[] = ['id' => $ev['id'] ?? 0, 'title' => $ev['title'] ?? '', 'date' => $when];
}

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>
<section class="pt-32 pb-24 px-4 min-h-[60vh]" x-data="accountPage()" x-init="init()">
    <div class="max-w-3xl mx-auto w-full">
        <div x-show="loading" class="text-center py-16"><svg class="w-8 h-8 animate-spin text-gold mx-auto" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg></div>

        <div x-show="!loading && !user" x-cloak class="text-center py-12">
            <h1 class="font-display text-4xl mb-4">Contul meu</h1>
            <p class="text-warm-gray mb-8">Autentifică-te pentru a vedea biletele și abonamentele tale.</p>
            <a href="/autentificare" class="btn-gold px-8 py-3 rounded-lg inline-block">Autentificare</a>
        </div>

        <div x-show="!loading && user" x-cloak>
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-gold/15 border border-gold/30 flex items-center justify-center"><span class="font-display text-gold text-xl" x-text="initials()"></span></div>
                    <div>
                        <h1 class="font-display text-2xl leading-tight" x-text="user?.name || 'Client'"></h1>
                        <p class="text-warm-gray text-sm" x-text="user?.email"></p>
                    </div>
                </div>
                <button @click="logout()" class="btn-outline px-4 py-2 rounded-lg text-sm">Ieși din cont</button>
            </div>

            <!-- Abonamente -->
            <div class="mb-8">
                <h2 class="font-display text-2xl mb-4">Abonamentele mele</h2>
                <div x-show="subs.length === 0" class="bg-charcoal rounded-2xl border border-gold/10 p-6 text-center">
                    <p class="text-warm-gray mb-4">Nu ai niciun abonament activ.</p>
                    <a href="/abonamente" class="btn-gold px-6 py-3 rounded-lg inline-block">Vezi abonamentele</a>
                </div>
                <div class="space-y-5">
                    <template x-for="s in subs" :key="s.id">
                        <div class="bg-charcoal rounded-2xl border border-gold/10 overflow-hidden">
                            <div class="flex items-center justify-between p-5 border-b border-gold/10">
                                <div>
                                    <h3 class="font-display text-xl" x-text="'Abonament ' + s.name"></h3>
                                    <p class="text-warm-gray text-sm" x-show="s.valid_until" x-text="'Valabil până la ' + s.valid_until"></p>
                                </div>
                                <span class="px-3 py-1 text-xs font-semibold rounded-full"
                                    :class="s.status === 'active' ? 'bg-gold/15 text-gold' : 'bg-white/10 text-warm-gray'"
                                    x-text="s.status === 'active' ? 'Activ' : s.status"></span>
                            </div>
                            <div class="p-5">
                                <!-- Consumption -->
                                <div class="mb-4" x-show="s.shows_included">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-warm-gray">Spectacole folosite</span>
                                        <span x-text="s.shows_used + ' / ' + s.shows_included"></span>
                                    </div>
                                    <div class="h-2 rounded-full bg-midnight overflow-hidden">
                                        <div class="h-full bg-gold" :style="'width:' + (s.shows_included ? Math.round(100*s.shows_used/s.shows_included) : 0) + '%'"></div>
                                    </div>
                                </div>
                                <!-- Seats -->
                                <div class="mb-4" x-show="s.seat_labels && s.seat_labels.length">
                                    <p class="text-warm-gray text-xs uppercase tracking-wider mb-1">Locurile tale</p>
                                    <div class="flex flex-wrap gap-2"><template x-for="l in s.seat_labels" :key="l"><span class="bg-midnight px-2 py-1 rounded text-xs" x-text="l"></span></template></div>
                                </div>
                                <!-- Benefits -->
                                <div class="mb-4" x-show="s.benefits && s.benefits.length">
                                    <p class="text-warm-gray text-xs uppercase tracking-wider mb-2">Beneficii</p>
                                    <ul class="space-y-1">
                                        <template x-for="b in s.benefits" :key="b"><li class="flex items-center gap-2 text-sm"><span class="text-gold">✓</span><span x-text="b"></span></li></template>
                                    </ul>
                                </div>
                                <!-- Redeemed -->
                                <div class="mb-4" x-show="s.redeemed_events && s.redeemed_events.length">
                                    <p class="text-warm-gray text-xs uppercase tracking-wider mb-2">Spectacole rezervate</p>
                                    <div class="space-y-1">
                                        <template x-for="r in s.redeemed_events" :key="r.event_id">
                                            <div class="flex items-center justify-between text-sm border-b border-gold/5 pb-1"><span x-text="r.title"></span><span class="text-warm-gray" x-text="r.date"></span></div>
                                        </template>
                                    </div>
                                </div>
                                <!-- Redeem -->
                                <div x-show="s.status === 'active' && s.shows_remaining > 0" class="pt-4 border-t border-gold/10">
                                    <p class="text-sm mb-2">Folosește abonamentul la un spectacol (<span x-text="s.shows_remaining"></span> rămase):</p>
                                    <div class="flex gap-2 flex-wrap">
                                        <select x-model="redeemPick[s.id]" class="flex-1 bg-midnight border border-gold/20 rounded-lg px-3 py-2 text-sm text-ivory">
                                            <option value="">Alege spectacolul...</option>
                                            <template x-for="ev in availableEvents(s)" :key="ev.id">
                                                <option :value="ev.id" x-text="ev.title + (ev.date ? ' — ' + ev.date : '')"></option>
                                            </template>
                                        </select>
                                        <button @click="redeem(s)" :disabled="!redeemPick[s.id] || busy" class="btn-gold px-4 py-2 rounded-lg text-sm">Rezervă</button>
                                    </div>
                                    <p x-show="redeemErr[s.id]" x-text="redeemErr[s.id]" class="text-red-300 text-xs mt-2"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Bilete -->
            <div class="bg-charcoal rounded-2xl border border-gold/10 p-6">
                <h2 class="font-display text-xl mb-2">Biletele mele</h2>
                <p class="text-warm-gray text-sm">Biletele achiziționate îți sunt trimise pe email după fiecare comandă. Prezintă codul QR la intrare.</p>
                <a href="/repertoriu" class="btn-gold px-6 py-3 rounded-lg inline-block mt-5">Vezi spectacolele</a>
            </div>
        </div>
    </div>
</section>
<script>
function accountPage() {
    return {
        loading: true, user: null, subs: [], busy: false,
        events: <?= json_encode($evList, JSON_UNESCAPED_UNICODE) ?>,
        redeemPick: {}, redeemErr: {},
        auth() { try { return JSON.parse(localStorage.getItem('teatru_auth') || 'null'); } catch(e) { return null; } },
        initials() { const n = (this.user?.name || 'C').trim().split(/\s+/); return ((n[0]?.[0]||'')+(n[1]?.[0]||'')).toUpperCase() || 'C'; },
        async init() {
            const a = this.auth();
            if (!a || !a.token) { this.loading = false; return; }
            try {
                const r = await fetch('/api/proxy.php?action=me', { headers: { 'Authorization': 'Bearer ' + a.token } });
                const d = await r.json().catch(()=>({}));
                if (r.ok && d.success && d.data) { this.user = d.data; await this.loadSubs(); }
                else { localStorage.removeItem('teatru_auth'); }
            } catch (e) {}
            this.loading = false;
        },
        async loadSubs() {
            const a = this.auth();
            try {
                const r = await fetch('/api/proxy.php?action=my-subscriptions', { headers: { 'Authorization': 'Bearer ' + a.token } });
                const d = await r.json().catch(()=>({}));
                if (r.ok && d.success && Array.isArray(d.data)) { this.subs = d.data; }
            } catch (e) {}
        },
        availableEvents(s) {
            const used = (s.redeemed_events || []).map(r => r.event_id);
            return this.events.filter(e => e.id && !used.includes(e.id));
        },
        async redeem(s) {
            const eventId = this.redeemPick[s.id];
            if (!eventId || this.busy) return;
            this.busy = true; this.redeemErr[s.id] = '';
            const a = this.auth();
            try {
                const r = await fetch('/api/proxy.php?action=redeem&sub=' + s.id, {
                    method: 'POST', headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + a.token },
                    body: JSON.stringify({ event_id: parseInt(eventId) })
                });
                const d = await r.json().catch(()=>({}));
                if (r.ok && d.success) { this.redeemPick[s.id] = ''; await this.loadSubs(); }
                else { this.redeemErr[s.id] = d.error || 'Nu am putut rezerva.'; }
            } catch (e) { this.redeemErr[s.id] = 'Eroare de conexiune.'; }
            this.busy = false;
        },
        async logout() {
            const a = this.auth();
            try { await fetch('/api/proxy.php?action=logout', { method: 'POST', headers: { 'Authorization': 'Bearer ' + (a?.token||'') } }); } catch (e) {}
            localStorage.removeItem('teatru_auth'); window.location.href = '/';
        }
    };
}
</script>
<?php include __DIR__ . '/includes/footer.php';
