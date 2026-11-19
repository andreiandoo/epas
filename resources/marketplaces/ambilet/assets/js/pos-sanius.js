/**
 * Blocul Sanius din leisure-pos: la intrare, casiera vinde biletele de acces si
 * ciclurile de sanie pe acelasi bon. Ciclurile se trec ca si credit pe contul
 * tichetului de parcare; tot aici se face contractul (tableta sau hartie).
 *
 * leisure-pos foloseste doar: init, active, cartLine, validate, checkout, reset.
 */
(function () {
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = (n) => (Number(n) || 0).toFixed(2).replace('.', ',') + ' lei';
    const icon = (name, cls) => (typeof ambIcon === 'function' ? ambIcon(name, cls) : '');
    const el = (id) => document.getElementById(id);
    const hhmm = (d) => d ? new Date(d).toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' }) : '—';

    const S = {
        eventId: null, opts: null, enabled: false, isTest: false, quote: null,
        tab: null, sleds: 0, cycles: 1, busy: false, contract: null, pollTimer: null,
    };
    const base = () => `/organizer/events/${S.eventId}/leisure/sanius`;

    function changed() { render(); if (S.opts && typeof S.opts.onChange === 'function') S.opts.onChange(); }
    function flash(msg, ok) {
        const box = el('lv-sn-msg');
        if (!box) return;
        box.className = 'mt-2 p-2 text-xs rounded-lg border ' + (ok ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900');
        box.textContent = msg;
        box.classList.remove('hidden');
        clearTimeout(flash.t);
        flash.t = setTimeout(() => box.classList.add('hidden'), 9000);
    }

    async function init(opts) {
        S.opts = opts || {};
        S.eventId = S.opts.eventId;
        S.enabled = false;
        const box = el('lv-sanius-section');
        if (!box || !S.eventId) return;
        try {
            const me = await AmbiletAPI.get(base() + '/me');
            const d = me.data || {};
            if (d.mode === 'off' || !['owner', 'manager', 'cashier'].includes(d.web_role)) return;
            S.isTest = !!d.is_test;
            const q = await AmbiletAPI.get(base() + '/prepay/quote', { sleds: 1, cycles: 1 });
            S.quote = q.data || null;
            S.enabled = true;
            box.classList.remove('hidden');
            render();
            // pretul zilei se reia periodic (trecerea la weekend, schimbari de pret)
            setInterval(async () => { await refreshQuote(); if (S.enabled) changed(); }, 5 * 60 * 1000);
        } catch (e) {
            // circuitul nu e disponibil pentru acest cont: blocul ramane ascuns
        }
    }

    async function refreshQuote() {
        try {
            const q = await AmbiletAPI.get(base() + '/prepay/quote', { sleds: 1, cycles: 1 });
            if (q.data) S.quote = q.data;
        } catch (e) { /* ramane pretul vechi; serverul refuza oricum un pret gresit */ }
    }

    function active() { return S.enabled && S.sleds > 0 && S.cycles > 0; }
    function sledCycles() { return S.sleds * S.cycles; }
    function unitPrice() { return S.isTest ? 0 : Number(S.quote?.unit_price || 0); }

    /** Linia din cos + sumele pentru totalul leisure-pos. */
    function cartLine() {
        if (!active()) return null;
        const qty = sledCycles();
        const unit = unitPrice();
        const subtotal = Math.round(unit * qty * 100) / 100;
        const commission = S.isTest ? 0 : Math.round((S.opts.commissionPerTicket ? S.opts.commissionPerTicket(unit) : 0) * qty * 100) / 100;
        const dayLabel = S.quote?.day_type === 'weekend' ? 'weekend' : 'L–V';
        const html = `<div class="p-2 border rounded-lg bg-sky-50 border-sky-200">
            <div class="flex items-center gap-2">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-secondary">${icon('snowflake', 'w-4 h-4')} Sanius · ${S.sleds} ${S.sleds === 1 ? 'sanie' : 'sănii'} × ${S.cycles} ${S.cycles === 1 ? 'ciclu' : 'cicluri'}</p>
                    <p class="text-[11px] text-muted">${qty} × ${money(unit)} (${dayLabel})${S.tab ? ' · tichet ' + esc(S.tab.parking_number || S.tab.parking_code) : ' · scanează tichetul de parcare'}</p>
                    ${S.isTest ? '<p class="text-[11px] font-semibold text-amber-700">TEST: săniile nu se încasează</p>' : ''}
                </div>
                <div class="w-20 text-sm font-bold text-right">${(subtotal).toFixed(2)}</div>
                <button type="button" data-sn-clear class="text-rose-500 hover:text-rose-700" title="Scoate săniile">✕</button>
            </div>
            ${commission > 0 ? `<div class="text-[10px] text-muted pl-1">+ Comision ticketing: ${commission.toFixed(2)} RON</div>` : ''}
        </div>`;
        return { html, subtotal, commission };
    }

    /** Mesaj de eroare daca vanzarea nu poate pleca; null daca e ok. */
    function validate(payment) {
        if (!active()) return null;
        if (!S.tab) return 'Scanează tichetul de parcare al clientului în blocul Sanius.';
        if (payment !== 'cash' && payment !== 'card') return 'Săniile se încasează doar cash sau card.';
        if (!S.isTest && !(S.quote && S.quote.available && Number(S.quote.unit_price) > 0)) {
            return 'Ciclul de sanie nu are produs sau preț. Verifică setările Sanius.';
        }
        return null;
    }

    async function checkout(saleBody) {
        const body = {
            parking_code: S.tab.parking_code,
            sleds: S.sleds,
            cycles: S.cycles,
            expected_unit_price: S.isTest ? undefined : Number(S.quote.unit_price),
            sale: saleBody,
        };
        let res;
        try {
            res = await AmbiletAPI.post(base() + '/prepay', body);
        } catch (e) {
            // pretul s-a schimbat (alta zi, alt pret): luam pretul nou ca urmatoarea incercare sa mearga
            if (/s-a schimbat/i.test(e?.message || '')) { await refreshQuote(); changed(); }
            throw e;
        }
        const d = res.data || {};
        const sum = d.sanius || null;
        // Tichetul se goleste dupa vanzare, ca urmatorul client sa nu ajunga pe el din greseala.
        S.tab = null;
        S.summary = null;
        S.sleds = 0;
        S.cycles = 1;
        render();
        const left = sum?.prepaid?.sled_cycles;
        const needsContract = sum && sum.contract && !sum.contract.signed;
        showAfterSale((res.message || 'Gata.') + (left !== undefined ? ` Credit pe tichet: ${left} sanie-cicluri.` : ''), needsContract ? sum : null);
        return res;
    }

    /** Mesajul de dupa vanzare; daca lipseste contractul, buton care redeschide tichetul pentru contract. */
    function showAfterSale(msg, sumForContract) {
        const box = el('lv-sn-msg');
        if (!box) return;
        box.className = 'mt-2 p-2 text-xs rounded-lg border bg-emerald-50 border-emerald-200 text-emerald-900';
        box.innerHTML = esc(msg) + (sumForContract
            ? `<button type="button" id="lv-sn-after-contract" class="block w-full mt-2 px-2 py-1.5 font-semibold border rounded-lg ${sumForContract.contract.required ? 'border-rose-300 bg-rose-50 text-rose-800' : 'border-sky-300 bg-sky-50 text-sky-900'}">${icon('scroll-text', 'w-3.5 h-3.5')} Contract pentru tichetul ${esc(sumForContract.tab.parking_number || sumForContract.tab.parking_code)}${sumForContract.contract.required ? ' (obligatoriu)' : ''}</button>`
            : '');
        box.classList.remove('hidden');
        el('lv-sn-after-contract')?.addEventListener('click', () => {
            applySummary(sumForContract);
            openContract();
        });
    }

    function reset() {
        S.tab = null; S.sleds = 0; S.cycles = 1; S.contract = null;
        stopPoll();
        render();
    }

    function applySummary(sum) {
        S.tab = sum.tab || S.tab;
        S.summary = sum;
        render();
    }

    async function resolveParking(code) {
        if (!code || S.busy) return;
        S.busy = true;
        try {
            const res = await AmbiletAPI.post(base() + '/tabs/resolve', { code });
            applySummary(res.data || {});
            if (S.sleds === 0) S.sleds = 1;
            changed();
        } catch (e) {
            flash(e?.message || 'Tichetul nu a putut fi citit.', false);
        } finally {
            S.busy = false;
        }
    }

    async function linkTicket(code) {
        if (!code || !S.tab) return;
        try {
            const res = await AmbiletAPI.post(base() + `/tabs/${S.tab.id}/link`, { ticket_code: code, point: 'cashier' });
            applySummary(res.data || {});
            flash(res.message || 'Bilet legat.', true);
        } catch (e) {
            flash(e?.message || 'Biletul nu a putut fi legat.', false);
        }
    }

    function render() {
        const box = el('lv-sanius-body');
        if (!box || !S.enabled) return;
        const q = S.quote || {};
        const r = q.rules || {};
        const sum = S.summary || {};
        const pre = sum.prepaid || {};
        const con = sum.contract || {};
        const dayLabel = q.day_type === 'weekend' ? 'Weekend / sărbătoare' : 'Luni–vineri';

        let tabHtml = '';
        if (S.tab) {
            const tickets = (sum.tickets || []).length;
            const conLabel = con.signed
                ? `<span class="font-semibold text-emerald-700">${icon('circle-check', 'w-3.5 h-3.5')} Contract semnat${con.signer_name ? ' (' + esc(con.signer_name) + ')' : ''}</span>`
                : (con.status === 'awaiting_signature' ? '<span class="font-semibold text-amber-700">Contract: se așteaptă semnătura</span>'
                    : (con.status === 'awaiting_paper' ? '<span class="font-semibold text-amber-700">Contract tipărit, nesemnat</span>'
                        : `<span class="font-semibold ${con.required ? 'text-rose-700' : 'text-muted'}">Fără contract${con.required ? ' (obligatoriu)' : ''}</span>`));
            tabHtml = `<div class="p-2 mt-2 text-xs bg-white border rounded-lg border-border space-y-1">
                <div class="flex justify-between gap-2"><span>Tichet <strong>${esc(S.tab.parking_number || S.tab.parking_code)}</strong></span><button type="button" id="lv-sn-unset" class="underline text-muted">alt tichet</button></div>
                <div>${tickets} ${tickets === 1 ? 'bilet legat' : 'bilete legate'} · ${pre.sled_cycles || 0} sanie-cicluri deja plătite</div>
                <div>${conLabel}</div>
                <form id="lv-sn-link-form" class="flex gap-1 pt-1"><input id="lv-sn-link" type="text" placeholder="Leagă bilet cumpărat online (scanează)" class="flex-1 px-2 py-1 border rounded border-border"><button class="px-2 py-1 border rounded border-border">${icon('link', 'w-3.5 h-3.5')}</button></form>
                <button type="button" id="lv-sn-contract" class="w-full mt-1 px-2 py-1.5 font-semibold border rounded-lg border-sky-300 bg-sky-50 hover:bg-sky-100">${icon('scroll-text', 'w-3.5 h-3.5')} ${con.signed ? 'Contract nou / reîncarcă' : 'Contract'}</button>
            </div>`;
        } else {
            tabHtml = `<form id="lv-sn-code-form" class="flex gap-1 mt-2"><input id="lv-sn-code" type="text" inputmode="numeric" placeholder="Scanează tichetul de parcare" class="flex-1 px-2 py-1.5 text-sm border rounded-lg border-border"><button class="px-3 py-1.5 text-sm font-semibold border rounded-lg border-border">${icon('scan-line', 'w-4 h-4')}</button></form>`;
        }

        const step = (key, val, label) => `<div class="flex items-center justify-between gap-2">
            <span class="text-xs text-muted">${label}</span>
            <div class="flex items-center gap-1">
                <button type="button" data-sn-step="${key}" data-d="-1" class="w-7 h-7 bg-white border rounded border-border hover:bg-slate-100">−</button>
                <span class="w-6 text-sm font-semibold text-center">${val}</span>
                <button type="button" data-sn-step="${key}" data-d="1" class="w-7 h-7 bg-white border rounded border-border hover:bg-slate-100">+</button>
            </div></div>`;

        box.innerHTML = `
            ${S.isTest ? '<p class="mb-2 text-[11px] font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded p-1.5">Modul Test: biletele se vând normal, săniile se trec pe tichet fără încasare.</p>' : ''}
            ${q.available === false ? '<p class="mb-2 text-[11px] font-semibold text-rose-800">Lipsește produsul ciclului de sanie pentru azi. Generează-l din setările Sanius.</p>' : ''}
            <p class="text-[11px] text-muted">${dayLabel}: <strong>${money(q.unit_price)}</strong> / ciclu de ${r.cycle_minutes || '—'} min · ${r.tows_per_cycle ?? '—'} tractări incluse pe ciclu</p>
            ${tabHtml}
            <div class="mt-2 space-y-1.5">
                ${step('sleds', S.sleds, 'Sănii')}
                ${step('cycles', S.cycles, 'Cicluri pe sanie')}
            </div>
            <div id="lv-sn-msg" class="hidden"></div>`;

        box.querySelectorAll('[data-sn-step]').forEach(b => b.addEventListener('click', () => {
            const d = parseInt(b.dataset.d, 10);
            if (b.dataset.snStep === 'sleds') S.sleds = Math.max(0, Math.min(50, S.sleds + d));
            else S.cycles = Math.max(1, Math.min(24, S.cycles + d));
            changed();
        }));
        el('lv-sn-code-form')?.addEventListener('submit', (e) => { e.preventDefault(); resolveParking(el('lv-sn-code').value.trim()); });
        el('lv-sn-link-form')?.addEventListener('submit', (e) => { e.preventDefault(); const v = el('lv-sn-link').value.trim(); el('lv-sn-link').value = ''; linkTicket(v); });
        el('lv-sn-unset')?.addEventListener('click', () => { S.tab = null; S.summary = null; changed(); });
        el('lv-sn-contract')?.addEventListener('click', openContract);
    }

    // ------------------------------------------------------------------
    // Contract
    // ------------------------------------------------------------------

    function ensureModal() {
        if (el('lv-sn-modal')) return;
        const stations = (S.quote?.contract?.stations || ['Casa 1']);
        const div = document.createElement('div');
        div.id = 'lv-sn-modal';
        div.className = 'fixed inset-0 z-50 items-center justify-center hidden p-4 bg-black/60';
        div.innerHTML = `<div class="w-full max-w-lg p-5 bg-white shadow-xl rounded-2xl max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-3"><h3 class="text-lg font-bold text-secondary">Contract sănii</h3><button type="button" id="lv-sn-m-close" class="px-2 text-xl text-muted">✕</button></div>
            <div id="lv-sn-m-body"></div>
        </div>`;
        document.body.appendChild(div);
        el('lv-sn-m-close').addEventListener('click', closeModal);
        div.dataset.stations = JSON.stringify(stations);
    }

    function closeModal() {
        stopPoll();
        const m = el('lv-sn-modal');
        if (m) { m.classList.add('hidden'); m.classList.remove('flex'); }
    }

    function openContract() {
        if (!S.tab) return;
        ensureModal();
        const stations = JSON.parse(el('lv-sn-modal').dataset.stations || '["Casa 1"]');
        let saved = '';
        try { saved = localStorage.getItem('lv-sn-station') || ''; } catch (e) {}
        const stationOpts = stations.map(s => `<option ${s === saved ? 'selected' : ''}>${esc(s)}</option>`).join('');
        const input = (id, label, attrs = '') => `<label class="block text-xs font-semibold text-muted">${label}<input id="${id}" ${attrs} class="w-full px-3 py-2 mt-1 text-sm border rounded-lg border-border"></label>`;
        el('lv-sn-m-body').innerHTML = `<form id="lv-sn-m-form" class="space-y-2" autocomplete="off">
            ${input('lv-sn-c-name', 'Nume și prenume *', 'required maxlength="120"')}
            <div class="grid grid-cols-3 gap-2">
                <label class="block text-xs font-semibold text-muted">Act<select id="lv-sn-c-doc" class="w-full px-2 py-2 mt-1 text-sm border rounded-lg border-border"><option value="ci">Buletin / CI</option><option value="passport">Pașaport</option><option value="other">Alt act</option></select></label>
                ${input('lv-sn-c-series', 'Serie', 'maxlength="12" style="text-transform:uppercase"')}
                ${input('lv-sn-c-number', 'Număr *', 'required maxlength="20" style="text-transform:uppercase"')}
            </div>
            ${input('lv-sn-c-phone', 'Telefon', 'maxlength="30" inputmode="tel"')}
            ${input('lv-sn-c-address', 'Adresă', 'maxlength="255"')}
            <div class="grid grid-cols-2 gap-2 pt-2">
                <label class="block text-xs font-semibold text-muted">Tableta de semnat<select id="lv-sn-c-station" class="w-full px-2 py-2 mt-1 text-sm border rounded-lg border-border">${stationOpts}</select></label>
                <div class="flex items-end"><button type="submit" data-method="tablet" class="w-full px-3 py-2 text-sm font-bold text-white rounded-lg bg-primary">${icon('smartphone', 'w-4 h-4')} Trimite la tabletă</button></div>
            </div>
            <button type="submit" data-method="paper" class="w-full px-3 py-2 text-sm font-semibold border rounded-lg border-border">${icon('printer', 'w-4 h-4')} Tipăresc și semnează pe hârtie</button>
        </form>`;
        let method = 'tablet';
        el('lv-sn-m-form').querySelectorAll('button[type=submit]').forEach(b => b.addEventListener('click', () => { method = b.dataset.method; }));
        el('lv-sn-m-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const station = el('lv-sn-c-station').value;
            try { localStorage.setItem('lv-sn-station', station); } catch (err) {}
            try {
                const res = await AmbiletAPI.post(base() + '/contracts', {
                    tab_id: S.tab.id,
                    signer_name: el('lv-sn-c-name').value.trim(),
                    doc_type: el('lv-sn-c-doc').value,
                    doc_series: el('lv-sn-c-series').value.trim() || null,
                    doc_number: el('lv-sn-c-number').value.trim(),
                    phone: el('lv-sn-c-phone').value.trim() || null,
                    address: el('lv-sn-c-address').value.trim() || null,
                    method,
                    station,
                });
                S.contract = res.data?.contract || null;
                renderContractState();
                if (method === 'tablet') startPoll();
            } catch (err) {
                alert(err?.message || 'Contractul nu a putut fi creat.');
            }
        });
        const m = el('lv-sn-modal');
        m.classList.remove('hidden');
        m.classList.add('flex');
        setTimeout(() => el('lv-sn-c-name')?.focus(), 50);
    }

    function renderContractState() {
        const c = S.contract;
        if (!c) return;
        const signed = c.status === 'signed' || c.status === 'signed_paper';
        let html = `<div class="p-3 text-sm border rounded-lg border-border bg-slate-50"><p class="font-semibold">${esc(c.signer_name)} · ${esc((c.doc_series || '') + ' ' + c.doc_number)}</p>`;
        if (c.status === 'awaiting_signature') {
            html += `<p class="mt-2 font-semibold text-amber-700">Se așteaptă semnătura pe tableta „${esc(c.station)}”…</p>
                <p class="mt-1 text-xs text-muted">Clientul citește contractul pe tabletă și semnează cu degetul.</p>
                <div class="flex gap-2 mt-3"><button type="button" id="lv-sn-c-print" class="px-3 py-2 text-xs font-semibold border rounded-lg border-border">${icon('printer', 'w-3.5 h-3.5')} Treci pe hârtie</button>
                <button type="button" id="lv-sn-c-void" class="px-3 py-2 text-xs font-semibold border rounded-lg text-rose-700 border-rose-300">Anulează</button></div>`;
        } else if (c.status === 'awaiting_paper') {
            html += `<p class="mt-2 text-xs text-muted">Tipărește contractul A4, dă-l clientului să-l semneze, apoi confirmă.</p>
                <div class="flex flex-wrap gap-2 mt-3"><button type="button" id="lv-sn-c-print" class="px-3 py-2 text-xs font-semibold border rounded-lg border-border">${icon('printer', 'w-3.5 h-3.5')} Tipărește A4</button>
                <button type="button" id="lv-sn-c-paper" class="px-3 py-2 text-xs font-bold text-white rounded-lg bg-emerald-600">${icon('check', 'w-3.5 h-3.5')} Clientul a semnat</button>
                <button type="button" id="lv-sn-c-void" class="px-3 py-2 text-xs font-semibold border rounded-lg text-rose-700 border-rose-300">Anulează</button></div>`;
        } else if (signed) {
            html += `<p class="mt-2 font-semibold text-emerald-700">${icon('circle-check', 'w-4 h-4')} Contract semnat${c.status === 'signed_paper' ? ' pe hârtie' : ''}.</p>
                <div class="flex gap-2 mt-3"><button type="button" id="lv-sn-c-print" class="px-3 py-2 text-xs font-semibold border rounded-lg border-border">${icon('printer', 'w-3.5 h-3.5')} Tipărește A4</button>
                <button type="button" id="lv-sn-c-done" class="px-3 py-2 text-xs font-bold text-white rounded-lg bg-primary">Gata</button></div>`;
        } else {
            html += `<p class="mt-2 font-semibold text-rose-700">Contract anulat.</p>`;
        }
        html += '</div>';
        el('lv-sn-m-body').innerHTML = html;
        el('lv-sn-c-print')?.addEventListener('click', printContract);
        el('lv-sn-c-done')?.addEventListener('click', closeModal);
        el('lv-sn-c-void')?.addEventListener('click', () => contractAction('void'));
        el('lv-sn-c-paper')?.addEventListener('click', () => contractAction('paper'));
    }

    async function contractAction(action) {
        if (!S.contract) return;
        try {
            const res = await AmbiletAPI.post(base() + `/contracts/${S.contract.id}/${action}`, {});
            S.contract = res.data?.contract || S.contract;
            if (action === 'paper' || action === 'void') stopPoll();
            renderContractState();
            refreshTab();
        } catch (e) { alert(e?.message || 'Eroare'); }
    }

    async function printContract() {
        if (!S.contract) return;
        // Fereastra se deschide inainte de await, altfel o blocheaza browserul.
        const w = window.open('', '_blank');
        if (S.contract.status === 'awaiting_signature') {
            // trecem pe hartie: tableta nu mai primeste contractul
            try {
                await AmbiletAPI.post(base() + `/contracts/${S.contract.id}/void`, {});
                const res = await AmbiletAPI.post(base() + '/contracts', {
                    tab_id: S.tab.id, signer_name: S.contract.signer_name, doc_type: S.contract.doc_type,
                    doc_series: S.contract.doc_series, doc_number: S.contract.doc_number, phone: S.contract.phone,
                    address: S.contract.address, method: 'paper',
                });
                S.contract = res.data?.contract || S.contract;
                stopPoll();
                renderContractState();
            } catch (e) { if (w) w.close(); alert(e?.message || 'Eroare'); return; }
        }
        try {
            const url = AmbiletAPI.getApiUrl() + `?action=organizer.event.leisure.sanius&event=${S.eventId}&sp=${encodeURIComponent('contracts/' + S.contract.id + '/pdf')}`;
            const token = typeof AmbiletAuth !== 'undefined' ? AmbiletAuth.getToken() : null;
            const r = await fetch(url, { headers: token ? { Authorization: 'Bearer ' + token } : {} });
            if (!r.ok) throw new Error('PDF-ul nu a putut fi generat.');
            const blobUrl = URL.createObjectURL(await r.blob());
            if (w) w.location = blobUrl; else window.open(blobUrl, '_blank');
        } catch (e) {
            if (w) w.close();
            alert(e?.message || 'Eroare la tipărire');
        }
    }

    function startPoll() {
        stopPoll();
        S.pollTimer = setInterval(async () => {
            if (!S.contract) return stopPoll();
            try {
                const res = await AmbiletAPI.get(base() + `/contracts/${S.contract.id}`);
                const c = res.data?.contract;
                if (c && c.status !== S.contract.status) {
                    S.contract = c;
                    renderContractState();
                    refreshTab();
                }
                if (!c || c.status !== 'awaiting_signature') stopPoll();
            } catch (e) { /* reincercam la urmatorul pas */ }
        }, 2000);
    }
    function stopPoll() { if (S.pollTimer) { clearInterval(S.pollTimer); S.pollTimer = null; } }

    async function refreshTab() {
        if (!S.tab) return;
        try {
            const res = await AmbiletAPI.get(base() + '/tabs/lookup', { q: S.tab.parking_code });
            if (res.data?.found) applySummary(res.data);
        } catch (e) { /* nimic */ }
    }

    function clearSleds() { S.sleds = 0; S.cycles = 1; changed(); }

    window.PosSanius = { init, active, cartLine, validate, checkout, reset, clearSleds };
})();
