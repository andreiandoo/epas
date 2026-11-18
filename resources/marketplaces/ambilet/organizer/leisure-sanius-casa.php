<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Casierie Sanius';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_sanius_casa';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-3 sm:p-4 lg:p-8">
        <div class="w-full mx-auto space-y-4" style="max-width:980px;">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="flex items-center gap-2 text-2xl font-bold text-secondary lg:text-3xl">
                    <?= amb_icon('banknote', 'w-7 h-7') ?> Casierie Sanius
                    <span id="sc-mode" class="text-[11px] font-bold uppercase px-2 py-1 rounded bg-slate-200 text-slate-700">—</span>
                </h1>
                <a href="/organizator/leisure-sanius" class="text-sm underline text-muted">Setări circuit</a>
            </div>

            <div id="sc-test-banner" class="hidden p-3 text-sm font-semibold border rounded-xl bg-amber-50 border-amber-300 text-amber-900">
                MOD TEST: plata e simulată. Nu se creează comandă și nu se tipărește bon. Nu valida tichete în Mohos pe baza acestui ecran.
            </div>
            <div id="sc-error" class="hidden p-4 text-sm border rounded-xl bg-rose-50 border-rose-200 text-rose-900"></div>

            <form id="sc-form" class="p-4 bg-white border shadow-sm rounded-2xl border-border" autocomplete="off">
                <label class="text-xs font-semibold tracking-wider uppercase text-muted" for="sc-q">Scanează tichetul de parcare (sau bilet, sanie, nr. auto)</label>
                <div class="flex gap-2 mt-2">
                    <input id="sc-q" type="text" autofocus class="flex-1 px-4 py-3 text-lg font-semibold border-2 rounded-xl border-border focus:border-primary focus:outline-none" placeholder="Scanează…">
                    <button class="px-5 py-3 text-sm font-bold text-white rounded-xl bg-primary hover:bg-primary-dark"><?= amb_icon('search', 'w-5 h-5') ?></button>
                </div>
            </form>

            <div id="sc-result"></div>
        </div>
    </main>
</div>

<!-- Poza avariei -->
<div id="sc-photo-modal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 bg-black/70">
    <div class="relative max-w-3xl w-full bg-white rounded-2xl p-3">
        <button id="sc-photo-close" type="button" class="absolute px-3 py-1 text-sm font-bold bg-white border rounded-lg top-3 right-3 border-border">Închide</button>
        <img id="sc-photo-img" alt="Poza avariei" class="w-full rounded-xl">
    </div>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const money = (n) => (Number(n) || 0).toFixed(2).replace('.', ',') + ' lei';
    const hhmm = (d) => d ? new Date(d).toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' }) : '—';
    const DAMAGE = { small: 'mică', medium: 'medie', large: 'mare' };
    const RENTAL_STATUS = { active: 'Nereturnată', pending_sync: 'Așteaptă sincronizarea', closed: 'Returnată', lost: 'Pierdută' };

    let eventId = null;
    let mode = 'off';
    let lastQuery = '';
    let vehicleType = 'car';
    let current = null;
    let busy = false;

    const base = () => `/organizer/events/${eventId}/leisure/sanius`;
    function showError(msg) { const el = $('sc-error'); el.textContent = msg; el.classList.remove('hidden'); setTimeout(() => el.classList.add('hidden'), 8000); }
    function refocus() { setTimeout(() => $('sc-q').focus(), 50); }

    async function init() {
        const res = await AmbiletAPI.getAllOrganizerEvents();
        const leisure = (res.data || []).filter(e => (e.display_template || 'standard') === 'leisure_venue');
        const wanted = parseInt(new URLSearchParams(location.search).get('event') || '0', 10);
        const ev = leisure.find(e => e.id === wanted) || leisure[0];
        if (!ev) throw new Error('Nu există un eveniment de tip Locație de agrement.');
        eventId = ev.id;
        const me = await AmbiletAPI.get(base() + '/me');
        mode = me.data?.mode || 'off';
        const badge = $('sc-mode');
        badge.textContent = { off: 'Oprit', test: 'Test', live: 'Activ' }[mode];
        badge.className = 'text-[11px] font-bold uppercase px-2 py-1 rounded ' + ({ off: 'bg-slate-200 text-slate-700', test: 'bg-amber-100 text-amber-800', live: 'bg-emerald-100 text-emerald-800' }[mode]);
        $('sc-test-banner').classList.toggle('hidden', mode !== 'test');
        if (mode === 'off') {
            $('sc-result').innerHTML = '<div class="p-6 text-center bg-white border rounded-2xl border-border text-muted">Circuitul Sanius este oprit.</div>';
            $('sc-q').disabled = true;
        }
    }

    async function lookup(q) {
        if (!q || busy) return;
        busy = true;
        lastQuery = q;
        try {
            const res = await AmbiletAPI.get(base() + '/tabs/lookup', { q, vehicle_type: vehicleType });
            current = res.data || {};
            if (current.found && current.parking && current.parking.vehicle_type) vehicleType = current.parking.vehicle_type;
            render();
        } catch (e) {
            showError(e?.message || 'Eroare la căutare');
        } finally {
            busy = false;
            $('sc-q').value = '';
            refocus();
        }
    }

    function render() {
        const d = current || {};
        if (!d.found) {
            $('sc-result').innerHTML = d.parking_ticket
                ? `<div class="p-6 text-center border-2 rounded-2xl border-emerald-400 bg-emerald-50">
                        <p class="text-2xl font-extrabold text-emerald-800">${ambIcon('circle-check', 'w-7 h-7')} Nimic de plată la Sanius</p>
                        <p class="mt-2 text-sm text-emerald-900">Tichetul nu are sănii închiriate. Continuă în Mohos ca de obicei.</p></div>`
                : `<div class="p-6 text-center bg-white border rounded-2xl border-border text-muted">${esc(d.message || 'Nu am găsit niciun cont Sanius.')}</div>`;
            return;
        }

        const tab = d.tab || {};
        const settled = tab.status === 'settled';
        const pk = d.parking || {};

        const rentals = (d.rentals || []).map(r => {
            const a = r.amounts || {};
            const parts = [];
            if (a.rent !== undefined) parts.push(`chirie ${money(a.rent)}`);
            if (a.tows_count !== undefined) parts.push(`${a.tows_count} tractări (${a.tows_included} incluse, ${a.tows_paid} × ${money(a.tow_unit)})`);
            if (a.damage) parts.push(`avarie ${DAMAGE[a.damage_level] || ''} ${money(a.damage)}`);
            if (a.lost) parts.push(`sanie pierdută ${money(a.lost)}`);
            const done = r.status === 'closed' || r.status === 'lost';
            return `<div class="flex flex-wrap items-center justify-between gap-2 p-3 border rounded-xl ${done ? 'border-border' : 'border-rose-300 bg-rose-50'}">
                <div class="min-w-0">
                    <p class="font-bold text-secondary">${esc(r.sled)} <span class="text-xs font-semibold ${done ? 'text-muted' : 'text-rose-700'}">· ${RENTAL_STATUS[r.status] || r.status}</span></p>
                    <p class="text-xs text-muted">${hhmm(r.started_at)} → ${hhmm(r.returned_at)}${parts.length ? ' · ' + parts.join(' · ') : ''}</p>
                    ${r.has_photo ? `<button type="button" data-photo="${r.id}" class="mt-1 text-xs font-semibold underline text-primary">${ambIcon('camera', 'w-3.5 h-3.5')} Vezi poza avariei</button>` : ''}
                </div>
                <p class="text-lg font-bold tabular-nums text-secondary">${done ? money(a.total) : '—'}</p>
            </div>`;
        }).join('');

        const tickets = (d.tickets || []).map(t => `<span class="inline-block px-2 py-0.5 mr-1 mb-1 text-xs rounded bg-surface border border-border">${esc(t.type || 'Bilet')} · ${esc(t.code)}</span>`).join('');
        const blockers = (d.blockers || []).filter(b => !settled || !/achitat/i.test(b));

        const vehicleBtns = ['car', 'bus'].map(v => `<button type="button" data-vehicle="${v}" ${settled ? 'disabled' : ''} class="px-3 py-1.5 text-xs font-bold rounded-md ${vehicleType === v ? 'bg-white shadow text-secondary' : 'text-muted'}">${v === 'car' ? ambIcon('car', 'w-4 h-4') + ' Auto' : ambIcon('bus', 'w-4 h-4') + ' Autobuz'}</button>`).join('');

        let footer;
        if (settled) {
            const s = tab.settled_simulated ? ' (TEST, simulat)' : '';
            footer = `<div class="p-5 text-center border-2 rounded-2xl border-emerald-400 bg-emerald-50">
                <p class="text-2xl font-extrabold text-emerald-800">${ambIcon('circle-check', 'w-7 h-7')} Achitat la ${hhmm(tab.settled_at)}${s}</p>
                <p class="mt-1 text-sm text-emerald-900">${tab.settled_simulated ? 'Test: nu valida în Mohos.' : 'Poți valida tichetul de parcare în Mohos.'}</p></div>`;
        } else if (blockers.length) {
            footer = `<div class="p-4 border-2 rounded-2xl border-rose-400 bg-rose-50">
                <p class="text-lg font-extrabold text-rose-800">${ambIcon('triangle-alert', 'w-5 h-5')} Nu se poate încasa încă</p>
                <ul class="mt-1 text-sm list-disc list-inside text-rose-900">${blockers.map(b => `<li>${esc(b)}</li>`).join('')}</ul>
                <p class="mt-2 text-xs text-rose-800">NU valida tichetul în Mohos.</p>
                <button type="button" id="sc-reload" class="mt-3 px-3 py-1.5 text-xs font-semibold bg-white border rounded-lg border-rose-300">${ambIcon('refresh-cw', 'w-3.5 h-3.5')} Verifică din nou</button></div>`;
        } else {
            footer = `<div class="grid grid-cols-2 gap-3">
                <button type="button" data-pay="cash" class="px-4 py-4 text-lg font-extrabold text-white rounded-2xl bg-emerald-600 hover:bg-emerald-700">${ambIcon('banknote', 'w-6 h-6')} Cash</button>
                <button type="button" data-pay="card" class="px-4 py-4 text-lg font-extrabold text-white rounded-2xl bg-secondary hover:opacity-90">${ambIcon('credit-card', 'w-6 h-6')} Card</button>
            </div>`;
        }

        $('sc-result').innerHTML = `
        <div class="p-5 space-y-4 bg-white border shadow-sm rounded-2xl border-border">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold tracking-wider uppercase text-muted">Tichet parcare</p>
                    <p class="text-xl font-extrabold text-secondary tabular-nums">nr. ${esc(tab.parking_number || '—')} <span class="text-sm font-semibold text-muted">· ${esc(tab.parking_code)}</span></p>
                    ${tab.is_test ? '<span class="inline-block mt-1 text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-amber-100 text-amber-800">Cont de test</span>' : ''}
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold tracking-wider uppercase text-muted">Total de plată</p>
                    <p class="text-4xl font-extrabold tabular-nums ${settled ? 'text-emerald-700' : 'text-primary'}">${money(d.total)}</p>
                </div>
            </div>

            <div>${tickets || '<span class="text-xs text-muted">Niciun bilet de acces legat.</span>'}</div>

            <div class="space-y-2">${rentals || '<p class="text-sm text-muted">Nicio sanie pe acest tichet.</p>'}
                <div class="flex justify-between px-3 text-sm"><span class="text-muted">Total sănii</span><strong class="tabular-nums">${money(d.rentals_total)}</strong></div>
            </div>

            <div class="p-3 border rounded-xl border-border">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-bold text-secondary">Parcare</p>
                    <div class="inline-flex p-1 rounded-lg bg-slate-100">${vehicleBtns}</div>
                </div>
                <p class="mt-1 text-sm text-muted">Intrat la ${hhmm(pk.entry_at)} · ${Math.floor((pk.minutes || 0) / 60)} h ${(pk.minutes || 0) % 60} min · ${pk.has_access_ticket ? 'cu bilet de acces' : 'fără bilet de acces'}
                    · de plată ${pk.billable_minutes || 0} min = ${pk.units || 0} × ${money(pk.unit_price)}</p>
                <p class="mt-1 text-right text-lg font-bold tabular-nums">${money(pk.amount)}</p>
            </div>

            ${settled ? '' : `
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <form id="sc-link-form" class="flex gap-2"><input id="sc-link" type="text" placeholder="Leagă un bilet de acces (scanează)" class="flex-1 px-3 py-2 text-sm border rounded-lg border-border"><button class="px-3 py-2 text-sm font-semibold border rounded-lg border-border hover:bg-slate-50">${ambIcon('link', 'w-4 h-4')}</button></form>
                <form id="sc-plate-form" class="flex gap-2"><input id="sc-plate" type="text" value="${esc(tab.vehicle_plate || '')}" placeholder="Nr. înmatriculare (opțional)" class="flex-1 px-3 py-2 text-sm uppercase border rounded-lg border-border"><button class="px-3 py-2 text-sm font-semibold border rounded-lg border-border hover:bg-slate-50">${ambIcon('check', 'w-4 h-4')}</button></form>
            </div>`}

            ${footer}
        </div>`;

        document.querySelectorAll('#sc-result [data-vehicle]').forEach(b => b.addEventListener('click', () => { vehicleType = b.dataset.vehicle; lookup(lastQuery); }));
        document.querySelectorAll('#sc-result [data-pay]').forEach(b => b.addEventListener('click', () => settle(b.dataset.pay)));
        document.querySelectorAll('#sc-result [data-photo]').forEach(b => b.addEventListener('click', () => showPhoto(b.dataset.photo)));
        const reload = $('sc-reload'); if (reload) reload.addEventListener('click', () => lookup(lastQuery));
        const lf = $('sc-link-form'); if (lf) lf.addEventListener('submit', (e) => { e.preventDefault(); linkTicket(); });
        const pf = $('sc-plate-form'); if (pf) pf.addEventListener('submit', (e) => { e.preventDefault(); savePlate(); });
    }

    async function settle(method) {
        const d = current || {};
        if (!d.tab) return;
        const label = method === 'cash' ? 'cash' : 'card';
        if (!confirm(`Încasezi ${money(d.total)} ${label}?`)) return;
        try {
            const res = await AmbiletAPI.post(base() + `/tabs/${d.tab.id}/settle`, { vehicle_type: vehicleType, payment_method: method });
            current = Object.assign({ found: true }, res.data || {});
            render();
        } catch (e) {
            showError(e?.message || 'Încasarea a eșuat');
            lookup(lastQuery);
        }
        refocus();
    }

    async function linkTicket() {
        const code = $('sc-link').value.trim();
        if (!code || !current?.tab) return;
        try {
            const res = await AmbiletAPI.post(base() + `/tabs/${current.tab.id}/link`, { ticket_code: code, point: 'cashier' });
            if ((res.data?.link?.elsewhere || []).length) showError(res.message);
            lookup(lastQuery);
        } catch (e) { showError(e?.message || 'Biletul nu a putut fi legat'); }
    }

    async function savePlate() {
        if (!current?.tab) return;
        try {
            await AmbiletAPI.post(base() + `/tabs/${current.tab.id}/vehicle`, { plate: $('sc-plate').value.trim() });
            lookup(lastQuery);
        } catch (e) { showError(e?.message || 'Numărul nu a putut fi salvat'); }
    }

    async function showPhoto(rentalId) {
        try {
            const url = AmbiletAPI.getApiUrl() + `?action=organizer.event.leisure.sanius&event=${eventId}&sp=${encodeURIComponent('rentals/' + rentalId + '/photo')}`;
            const token = typeof AmbiletAuth !== 'undefined' ? AmbiletAuth.getToken() : null;
            const r = await fetch(url, { headers: token ? { Authorization: 'Bearer ' + token } : {} });
            if (!r.ok) throw new Error('Poza nu a putut fi încărcată.');
            const blob = await r.blob();
            $('sc-photo-img').src = URL.createObjectURL(blob);
            $('sc-photo-modal').classList.remove('hidden');
            $('sc-photo-modal').classList.add('flex');
        } catch (e) { showError(e.message); }
    }

    window.addEventListener('load', async () => {
        if (typeof AmbiletAuth !== 'undefined') AmbiletAuth.requireOrganizerAuth();
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 20) { await new Promise(r => setTimeout(r, 100)); retries++; }
        try { await init(); } catch (e) { showError(e?.message || 'Nu am putut încărca circuitul.'); return; }
        $('sc-form').addEventListener('submit', (e) => { e.preventDefault(); lookup($('sc-q').value.trim()); });
        $('sc-photo-close').addEventListener('click', () => { $('sc-photo-modal').classList.add('hidden'); $('sc-photo-modal').classList.remove('flex'); refocus(); });
        refocus();
    });
})();
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
