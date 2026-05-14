<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Curse bărci active';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_rentals';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl flex items-center gap-2">
                    🛶 Curse bărci active
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse" title="Live"></span>
                </h1>
                <p class="mt-1 text-sm text-muted">Panou operator pentru pornire, închidere și finalizare curse. Refresh automat la 10s.</p>
            </div>
            <div class="flex items-center gap-2">
                <select id="lv-tt-select" class="px-3 py-2 text-sm border border-border rounded-lg bg-white"></select>
                <button id="lv-start-btn" class="px-4 py-2 bg-emerald-600 text-white font-semibold rounded-lg hover:bg-emerald-700">+ Pornire cursă</button>
            </div>
        </div>

        <div id="lv-error" class="hidden mb-4 p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-900"></div>

        <div id="lv-active-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4"></div>
        <div id="lv-empty" class="hidden p-8 text-center bg-white border rounded-2xl border-border text-muted">Nicio cursă activă. Apasă „Pornire cursă" pentru a începe.</div>
    </main>
</div>

<!-- Modal Start cursă -->
<div id="lv-start-modal" class="hidden fixed inset-0 bg-black/50 z-50 items-start justify-center p-6 overflow-y-auto">
    <div class="bg-white rounded-2xl border border-border max-w-md w-full my-6 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-secondary text-lg">Pornire cursă nouă</h3>
            <button id="lv-start-close" type="button" class="text-2xl text-muted leading-none">×</button>
        </div>
        <div class="space-y-3">
            <label class="block">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider">Bilet acces / barcă scanat (opțional)</span>
                <input id="lv-start-ticket-code" type="text" placeholder="cod bilet sau lasă gol pentru vânzare on-site" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg">
            </label>
            <label class="block">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider">Variantă durată</span>
                <select id="lv-start-variant" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg">
                    <option value="">— Folosește prețul de bază —</option>
                </select>
            </label>
            <label class="block">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider">Barca *</span>
                <select id="lv-start-boat" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg"></select>
            </label>
            <label class="block">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider">Note (opțional)</span>
                <input id="lv-start-notes" type="text" maxlength="500" class="mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg">
            </label>
        </div>
        <div class="mt-5 flex gap-2">
            <button id="lv-start-cancel" class="flex-1 px-3 py-2 text-sm border border-border rounded-lg hover:bg-slate-50">Renunță</button>
            <button id="lv-start-confirm" class="flex-1 px-4 py-2 text-sm bg-emerald-600 text-white font-semibold rounded-lg hover:bg-emerald-700">🚣 Pornește cursa</button>
        </div>
    </div>
</div>

<script>
(function(){
    const $ = (id) => document.getElementById(id);
    let currentEventId = null;
    let rentalTypes = []; // toate TicketType-urile cu service_category=rental
    let currentTtId = null;
    let boatsCache = [];
    let pollHandle = null;

    function fmtTime(d) {
        if (!d) return '—';
        const dt = (d instanceof Date) ? d : new Date(d);
        return dt.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
    function fmtDuration(start, end) {
        if (!start) return '—';
        const s = new Date(start);
        const e = end ? new Date(end) : new Date();
        const sec = Math.max(0, Math.floor((e - s) / 1000));
        const mm = Math.floor(sec / 60);
        const ss = sec % 60;
        return mm.toString().padStart(2, '0') + ':' + ss.toString().padStart(2, '0');
    }

    async function loadEvent() {
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const events = res.data || [];
            const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
            if (leisure.length === 0) throw new Error('Nu există event leisure.');
            currentEventId = leisure[0].id;
        } catch (e) {
            $('lv-error').textContent = 'Nu există un event de tip leisure asociat.';
            $('lv-error').classList.remove('hidden');
            throw e;
        }
    }
    async function loadProducts() {
        const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/config`);
        const all = res.data?.ticket_types || [];
        // Filtrez cele cu physical_inventory.enabled (= bărci)
        rentalTypes = all.filter(t => t.physical_inventory && t.physical_inventory.enabled);
        const sel = $('lv-tt-select');
        sel.innerHTML = '';
        rentalTypes.forEach(t => {
            const o = document.createElement('option');
            o.value = t.id;
            const name = typeof t.name === 'string' ? t.name : (t.name?.ro || 'Produs #' + t.id);
            o.textContent = name + ' (' + (t.physical_inventory.count || 0) + ' unități)';
            sel.appendChild(o);
        });
        if (rentalTypes.length > 0) currentTtId = rentalTypes[0].id;
    }
    async function loadBoats() {
        if (!currentTtId) return;
        const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/boats`, { ticket_type_id: currentTtId });
        boatsCache = res.data?.boats || [];
    }
    async function refreshRentals() {
        if (!currentEventId || !currentTtId) return;
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/active-rentals`, { ticket_type_id: currentTtId });
            renderRentals(res.data?.rentals || []);
        } catch (e) {
            console.error('[rentals] refresh', e);
        }
    }
    function renderRentals(rentals) {
        const wrap = $('lv-active-list');
        const empty = $('lv-empty');
        if (!rentals.length) {
            wrap.innerHTML = '';
            empty.classList.remove('hidden');
            return;
        }
        empty.classList.add('hidden');
        wrap.innerHTML = rentals.map(r => {
            const overdue = new Date() > new Date(r.planned_end_at);
            const elapsed = fmtDuration(r.started_at);
            return `<div class="bg-white border-2 rounded-2xl p-5 ${overdue ? 'border-rose-400' : 'border-border'}">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-muted font-semibold">Barca</p>
                        <p class="text-3xl font-bold text-secondary">#${r.boat_number}</p>
                    </div>
                    <span class="text-[10px] uppercase font-bold ${overdue ? 'text-rose-700 bg-rose-100' : 'text-emerald-700 bg-emerald-100'} px-2 py-1 rounded">
                        ${overdue ? '⚠️ Depășit' : '✓ În progres'}
                    </span>
                </div>
                <div class="text-xs text-muted">${r.ticket_type || ''}</div>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div>
                        <p class="text-[9px] uppercase text-muted">Start</p>
                        <p class="text-xs font-bold">${fmtTime(r.started_at)}</p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase text-muted">Planificat</p>
                        <p class="text-xs font-bold">${fmtTime(r.planned_end_at)}</p>
                    </div>
                    <div>
                        <p class="text-[9px] uppercase text-muted">Timp</p>
                        <p class="text-base font-bold tabular-nums" data-elapsed="${r.started_at}">${elapsed}</p>
                    </div>
                </div>
                <div class="mt-4 flex gap-2">
                    <button data-end="${r.id}" class="flex-1 px-3 py-2 text-sm font-semibold bg-amber-500 text-white rounded-lg hover:bg-amber-600">⏹ Închide timer</button>
                </div>
                <div id="lv-end-result-${r.id}" class="hidden mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm"></div>
            </div>`;
        }).join('');
        // Bind end buttons
        wrap.querySelectorAll('button[data-end]').forEach(b => {
            b.addEventListener('click', () => endRental(parseInt(b.dataset.end, 10)));
        });
    }
    // Update elapsed timer per card every second
    setInterval(() => {
        document.querySelectorAll('[data-elapsed]').forEach(el => {
            el.textContent = fmtDuration(el.dataset.elapsed);
        });
    }, 1000);

    async function endRental(rentalId) {
        if (!confirm('Închizi timer-ul pentru această cursă?')) return;
        try {
            const res = await AmbiletAPI.post(`/organizer/events/${currentEventId}/leisure/boat-rentals/${rentalId}/end`, {});
            const data = res.data || {};
            const resultEl = $('lv-end-result-' + rentalId);
            if (resultEl) {
                const extra = parseInt(data.extra_calupuri || 0, 10);
                const total = parseFloat(data.extra_charge_total || 0);
                if (extra > 0) {
                    resultEl.innerHTML = `<div class="text-amber-900 font-semibold mb-2">⚠️ Depășire: ${extra} calup(uri) extra · <strong>${total.toFixed(2)} RON</strong></div>
                        <button class="px-3 py-1.5 text-xs bg-rose-600 text-white rounded font-semibold" onclick="window.lvFinalize(${rentalId}, ${total})">Încasează ${total.toFixed(2)} RON + Finalizează</button>`;
                } else {
                    resultEl.innerHTML = `<div class="text-emerald-700 font-semibold mb-2">✓ În limita planificată</div>
                        <button class="px-3 py-1.5 text-xs bg-emerald-600 text-white rounded font-semibold" onclick="window.lvFinalize(${rentalId}, 0)">Finalizează</button>`;
                }
                resultEl.classList.remove('hidden');
            }
        } catch (e) {
            alert('Eroare: ' + (e?.message || ''));
        }
    }

    window.lvFinalize = async function(rentalId, extraAmount) {
        try {
            const res = await AmbiletAPI.post(`/organizer/events/${currentEventId}/leisure/boat-rentals/${rentalId}/finalize`, {});
            if (res.success) {
                refreshRentals();
            } else {
                alert('Eroare: ' + (res.message || ''));
            }
        } catch (e) {
            alert('Eroare: ' + (e?.message || ''));
        }
    };

    // Modal start
    function openStartModal() {
        if (!currentTtId) { alert('Selectează un produs.'); return; }
        const tt = rentalTypes.find(t => t.id == currentTtId);
        // Variante
        const varSel = $('lv-start-variant');
        varSel.innerHTML = '<option value="">— Folosește prețul de bază —</option>';
        (tt?.variants || []).forEach(v => {
            const o = document.createElement('option');
            o.value = v.id;
            o.textContent = v.label + ' (' + parseFloat(v.price).toFixed(2) + ' RON)';
            varSel.appendChild(o);
        });
        // Bărci available
        const boatSel = $('lv-start-boat');
        boatSel.innerHTML = '';
        boatsCache.filter(b => b.status === 'available').forEach(b => {
            const o = document.createElement('option');
            o.value = b.id;
            o.textContent = (b.label || 'Barca #' + b.number);
            boatSel.appendChild(o);
        });
        if (boatSel.options.length === 0) {
            alert('Nicio barcă disponibilă în acest moment.');
            return;
        }
        $('lv-start-ticket-code').value = '';
        $('lv-start-notes').value = '';
        $('lv-start-modal').classList.remove('hidden');
        $('lv-start-modal').classList.add('flex');
    }
    function closeStartModal() {
        $('lv-start-modal').classList.add('hidden');
        $('lv-start-modal').classList.remove('flex');
    }
    async function confirmStart() {
        const body = {
            ticket_type_id: parseInt(currentTtId, 10),
            boat_id: parseInt($('lv-start-boat').value, 10),
            variant_id: $('lv-start-variant').value || null,
            notes: $('lv-start-notes').value.trim() || null,
        };
        const ticketCode = $('lv-start-ticket-code').value.trim();
        if (ticketCode) {
            // TODO: lookup ticket by code → trimite rental_ticket_id sau access_ticket_id
            body.notes = (body.notes ? body.notes + ' · ' : '') + 'Cod: ' + ticketCode;
        }
        try {
            const res = await AmbiletAPI.post(`/organizer/events/${currentEventId}/leisure/boat-rentals/start`, body);
            if (res.success) {
                closeStartModal();
                await loadBoats();
                refreshRentals();
            } else {
                alert('Eroare: ' + (res.message || ''));
            }
        } catch (e) {
            alert('Eroare: ' + (e?.message || ''));
        }
    }

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined') return;
        try {
            await loadEvent();
            await loadProducts();
            if (currentTtId) {
                await loadBoats();
                await refreshRentals();
            }
        } catch (e) {
            console.error(e);
            return;
        }
        $('lv-tt-select').addEventListener('change', async e => {
            currentTtId = parseInt(e.target.value, 10);
            await loadBoats();
            refreshRentals();
        });
        $('lv-start-btn').addEventListener('click', openStartModal);
        $('lv-start-close').addEventListener('click', closeStartModal);
        $('lv-start-cancel').addEventListener('click', closeStartModal);
        $('lv-start-confirm').addEventListener('click', confirmStart);
        pollHandle = setInterval(refreshRentals, 10000);
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
