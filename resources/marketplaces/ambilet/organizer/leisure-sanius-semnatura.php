<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Semnare contract Sanius';
$bodyClass = 'min-h-screen bg-slate-100';
$currentPage = 'leisure_sanius_semnatura';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
?>
<!-- Tableta întoarsă spre client: fără meniu, doar contractul și semnătura. -->
<main class="min-h-screen p-3 sm:p-6">
    <div class="w-full mx-auto space-y-4" style="max-width:900px;">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="flex items-center gap-2 text-sm font-semibold text-muted"><?= amb_icon('scroll-text', 'w-5 h-5') ?> Contract Sanius <span id="sg-test" class="hidden text-[11px] font-bold uppercase px-2 py-0.5 rounded bg-amber-100 text-amber-800">Test</span></p>
            <label class="text-xs text-muted">Tableta
                <select id="sg-station" class="px-2 py-1 ml-1 text-sm bg-white border rounded-lg border-border"></select>
            </label>
        </div>

        <div id="sg-error" class="hidden p-3 text-sm border rounded-xl bg-rose-50 border-rose-200 text-rose-900"></div>

        <!-- Așteptare -->
        <div id="sg-wait" class="flex flex-col items-center justify-center p-10 text-center bg-white border rounded-2xl border-border" style="min-height:60vh">
            <p class="text-3xl font-extrabold text-secondary">Bine ați venit!</p>
            <p class="mt-3 text-lg text-muted">Willkommen! · Üdvözöljük! · Welcome!</p>
            <p class="mt-8 text-sm text-muted">Contractul pentru sănii apare aici după ce casiera completează datele.</p>
        </div>

        <!-- Contract -->
        <div id="sg-contract" class="hidden p-5 space-y-4 bg-white border shadow-sm rounded-2xl border-border">
            <h1 id="sg-title" class="text-xl font-extrabold text-center text-secondary"></h1>
            <div id="sg-body" class="space-y-3 overflow-y-auto text-base leading-relaxed text-secondary" style="max-height:38vh"></div>
            <div id="sg-data" class="p-3 text-sm rounded-xl bg-surface"></div>

            <div>
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-secondary">Semnați în chenar, cu degetul</p>
                    <button type="button" id="sg-clear" class="px-3 py-1 text-sm font-semibold border rounded-lg border-border">Șterge semnătura</button>
                </div>
                <canvas id="sg-pad" class="w-full mt-2 bg-white border-2 border-dashed rounded-xl border-slate-400" style="height:220px;touch-action:none"></canvas>
            </div>

            <label class="flex items-start gap-3 text-base"><input id="sg-accept" type="checkbox" class="w-6 h-6 mt-0.5"> <span>Am citit contractul, datele mele sunt corecte și sunt de acord cu el.</span></label>
            <p class="text-xs text-muted">Datele dumneavoastră se folosesc doar pentru acest contract de închiriere și se păstrează cât cere legea.</p>
            <button type="button" id="sg-sign" class="w-full px-4 py-4 text-xl font-extrabold text-white rounded-2xl bg-primary disabled:opacity-50">Semnez</button>
        </div>

        <!-- Mulțumim -->
        <div id="sg-done" class="hidden flex-col items-center justify-center p-10 text-center border-2 rounded-2xl border-emerald-400 bg-emerald-50" style="min-height:60vh">
            <p class="text-3xl font-extrabold text-emerald-800"><?= amb_icon('circle-check', 'w-9 h-9') ?> Mulțumim!</p>
            <p class="mt-3 text-lg text-emerald-900">Contractul a fost semnat. Puteți preda tableta casierei.</p>
        </div>
    </div>
</main>

<?php
$scriptsExtra = <<<'JS'
<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const DOC = { ci: 'Carte de identitate', passport: 'Pașaport', other: 'Act de identitate' };

    let eventId = null;
    let station = null;
    let current = null;
    let timer = null;
    let strokes = 0;

    const base = () => `/organizer/events/${eventId}/leisure/sanius`;
    function showError(msg) { const el = $('sg-error'); el.textContent = msg; el.classList.remove('hidden'); setTimeout(() => el.classList.add('hidden'), 8000); }
    function show(which) {
        $('sg-wait').classList.toggle('hidden', which !== 'wait');
        $('sg-contract').classList.toggle('hidden', which !== 'contract');
        $('sg-done').classList.toggle('hidden', which !== 'done');
        $('sg-done').classList.toggle('flex', which === 'done');
    }

    // ---------- Semnatura ----------
    const pad = $('sg-pad');
    const ctx = pad.getContext('2d');
    let drawing = false;
    let last = null;

    function sizePad() {
        const r = pad.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        pad.width = Math.round(r.width * dpr);
        pad.height = Math.round(r.height * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.lineWidth = 2.6;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#0f172a';
        strokes = 0;
        updateButton();
    }
    function pos(e) { const r = pad.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; }
    pad.addEventListener('pointerdown', (e) => { drawing = true; last = pos(e); pad.setPointerCapture(e.pointerId); });
    pad.addEventListener('pointermove', (e) => {
        if (!drawing) return;
        const p = pos(e);
        ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke();
        last = p;
        strokes++;
        if (strokes === 15) updateButton();
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(t => pad.addEventListener(t, () => { drawing = false; }));
    function clearPad() { ctx.clearRect(0, 0, pad.width, pad.height); strokes = 0; updateButton(); }
    function updateButton() { $('sg-sign').disabled = !(strokes >= 15 && $('sg-accept').checked); }

    // ---------- Contract ----------
    function renderContract(c) {
        current = c;
        $('sg-title').textContent = c.title;
        $('sg-body').innerHTML = String(c.body || '').split(/\n\s*\n/).map(p => `<p>${esc(p.trim()).replace(/\n/g, '<br>')}</p>`).join('');
        $('sg-data').innerHTML = `<p><strong>${esc(c.signer_name)}</strong></p><p>${esc(DOC[c.doc_type] || 'Act')} ${esc(c.doc_series || '')} ${esc(c.doc_number)}${c.phone ? ' · ' + esc(c.phone) : ''}</p><p class="text-xs text-muted">Tichet parcare ${esc(c.parking_number || '')}</p>`;
        $('sg-accept').checked = false;
        show('contract');
        sizePad();
        $('sg-body').scrollTop = 0;
    }

    async function poll() {
        if (current) return;
        try {
            const res = await AmbiletAPI.get(base() + '/contracts/pending', { station });
            const d = res.data || {};
            $('sg-test').classList.toggle('hidden', !d.is_test);
            if (d.contract) renderContract(d.contract);
        } catch (e) { /* reincercam */ }
    }

    async function sign() {
        if (!current || $('sg-sign').disabled) return;
        $('sg-sign').disabled = true;
        try {
            await AmbiletAPI.post(base() + `/contracts/${current.id}/sign`, { signature: pad.toDataURL('image/png'), accepted: true });
            current = null;
            show('done');
            setTimeout(() => { if (!current) show('wait'); }, 5000);
        } catch (e) {
            showError(e?.message || 'Semnătura nu a putut fi salvată. Încercați din nou.');
            // contract anulat sau semnat deja: revenim la asteptare
            if (/anulat|deja/i.test(e?.message || '')) { current = null; show('wait'); }
            updateButton();
        }
    }

    window.addEventListener('load', async () => {
        if (typeof AmbiletAuth !== 'undefined') AmbiletAuth.requireOrganizerAuth();
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 20) { await new Promise(r => setTimeout(r, 100)); retries++; }
        try {
            const res = await AmbiletAPI.getAllOrganizerEvents();
            const leisure = (res.data || []).filter(e => (e.display_template || 'standard') === 'leisure_venue');
            const wanted = parseInt(new URLSearchParams(location.search).get('event') || '0', 10);
            const ev = leisure.find(e => e.id === wanted) || leisure[0];
            if (!ev) throw new Error('Nu există un eveniment de tip Locație de agrement.');
            eventId = ev.id;
            const first = await AmbiletAPI.get(base() + '/contracts/pending');
            const stations = first.data?.stations || ['Casa 1'];
            let saved = '';
            try { saved = localStorage.getItem('sg-station') || ''; } catch (e) {}
            station = stations.includes(saved) ? saved : stations[0];
            $('sg-station').innerHTML = stations.map(s => `<option ${s === station ? 'selected' : ''}>${esc(s)}</option>`).join('');
        } catch (e) {
            showError(e?.message || 'Pagina nu a putut porni.');
            return;
        }
        $('sg-station').addEventListener('change', () => {
            station = $('sg-station').value;
            try { localStorage.setItem('sg-station', station); } catch (e) {}
            current = null;
            show('wait');
            poll();
        });
        $('sg-clear').addEventListener('click', clearPad);
        $('sg-accept').addEventListener('change', updateButton);
        $('sg-sign').addEventListener('click', sign);
        window.addEventListener('resize', () => { if (current) sizePad(); });
        show('wait');
        poll();
        timer = setInterval(poll, 2000);
    });
})();
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
