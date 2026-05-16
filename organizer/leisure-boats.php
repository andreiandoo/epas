<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Gestiune — inventar fizic & QR';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_boats';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<style>
@media print {
    body * { visibility: hidden; }
    #lv-print-area, #lv-print-area * { visibility: visible; }
    #lv-print-area { position: absolute; left: 0; top: 0; width: 100%; }
    .lv-boat-print { page-break-inside: avoid; }
}
</style>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
            <div>
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl">🧰 Gestiune — inventar fizic</h1>
                <p class="mt-1 text-sm text-muted">Unitățile fizice ale produselor (bărci, sănii, kayak-uri, vaporașe). QR-uri printabile pentru fiecare unitate.</p>
            </div>
            <div class="flex items-center gap-2">
                <select id="lv-tt-select" class="px-3 py-2 text-sm border border-border rounded-lg bg-white hidden"></select>
                <button id="lv-sync-btn" class="px-3 py-2 text-sm font-medium bg-white border border-border rounded-lg hover:bg-slate-50 hidden">🔄 Sincronizează</button>
                <button id="lv-print-btn" class="px-3 py-2 text-sm font-semibold bg-primary text-white rounded-lg hidden">🖨 Print QR-uri</button>
            </div>
        </div>

        <div id="lv-error" class="hidden mb-4 p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-900 print:hidden"></div>
        <div id="lv-loading" class="p-8 text-center print:hidden"><div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div></div>

        <!-- Empty state: nu există produse cu inventar fizic activat -->
        <div id="lv-empty" class="hidden p-6 bg-white border border-border rounded-2xl print:hidden">
            <div class="flex items-start gap-4">
                <div class="text-4xl">📦</div>
                <div class="flex-1">
                    <h2 class="text-lg font-bold text-secondary mb-2">Nu există produse cu inventar fizic activat</h2>
                    <p class="text-sm text-muted mb-4">Pentru a gestiona bărci, sănii, kayak-uri sau alte unități fizice, trebuie să activezi <strong>inventarul fizic</strong> pe produs.</p>
                    <ol class="text-sm text-secondary list-decimal list-inside space-y-1.5 mb-4">
                        <li>Mergi la <a href="/organizator/leisure" class="text-primary font-medium underline">Setări leisure</a></li>
                        <li>Editează un produs de tip <em>Închiriere</em> sau <em>Activitate</em></li>
                        <li>Activează „Inventar fizic limitat" și setează numărul de unități</li>
                        <li>Revino aici și apasă „Sincronizează" pentru a genera QR-urile</li>
                    </ol>
                    <a href="/organizator/leisure" class="inline-block px-4 py-2 text-sm font-semibold bg-primary text-white rounded-lg">⚙ Mergi la Setări produse</a>
                </div>
            </div>
        </div>

        <!-- Info bar — produsul curent + cum se procedează -->
        <div id="lv-info-bar" class="hidden mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-900 print:hidden flex items-center gap-3">
            <span>ℹ️</span>
            <span id="lv-info-text"></span>
        </div>

        <!-- Lista bărci -->
        <div id="lv-print-area">
            <div id="lv-boats-grid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4"></div>
        </div>
    </main>
</div>
<script>
(function(){
    const $ = (id) => document.getElementById(id);
    let currentEventId = null;
    let rentalTypes = [];
    let currentTtId = null;

    function qrUrl(code, size) {
        size = size || 200;
        return `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(code)}`;
    }
    function statusColor(s) {
        return { available: 'emerald', in_use: 'amber', maintenance: 'slate', retired: 'rose' }[s] || 'slate';
    }
    function statusLabel(s) {
        return { available: '✓ Disponibilă', in_use: '🛶 În uz', maintenance: '🔧 Mentenanță', retired: '🗑 Retrasă' }[s] || s;
    }

    async function loadEvent() {
        const res = await AmbiletAPI.get('/organizer/events');
        const events = res.data || [];
        const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
        if (leisure.length === 0) throw new Error('Nu există un eveniment de tip Locație de agrement.');
        currentEventId = leisure[0].id;
    }
    async function loadProducts() {
        const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/config`);
        const all = res.data?.ticket_types || [];
        // Suport multi-produs: orice produs cu physical_inventory.enabled apare in dropdown.
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
    function updateInfoBar() {
        const tt = rentalTypes.find(t => t.id === currentTtId);
        if (!tt) { $('lv-info-bar').classList.add('hidden'); return; }
        const name = typeof tt.name === 'string' ? tt.name : (tt.name?.ro || 'Produs');
        const count = tt.physical_inventory?.count || 0;
        $('lv-info-text').innerHTML = `Produs activ: <strong>${name}</strong> · Configurat pentru <strong>${count}</strong> unități. Apasă <em>Sincronizează</em> ca să generezi/aliniezi unitățile cu QR-uri.`;
        $('lv-info-bar').classList.remove('hidden');
    }
    async function loadBoats() {
        // ALWAYS hide loading at the end — even pentru early returns
        $('lv-loading').classList.remove('hidden');
        $('lv-boats-grid').innerHTML = '';
        if (!currentTtId) {
            $('lv-loading').classList.add('hidden');
            return;
        }
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/boats`, { ticket_type_id: currentTtId });
            renderBoats(res.data?.boats || []);
        } catch (e) {
            $('lv-error').textContent = 'Eroare: ' + (e?.message || '');
            $('lv-error').classList.remove('hidden');
        } finally {
            $('lv-loading').classList.add('hidden');
        }
    }
    function renderBoats(boats) {
        const grid = $('lv-boats-grid');
        if (!boats.length) {
            grid.innerHTML = '<div class="col-span-full p-6 bg-amber-50 border border-amber-200 rounded-xl text-center"><p class="text-sm text-amber-900 mb-3">Nu există încă unități pentru acest produs.</p><p class="text-xs text-amber-700">Apasă <strong>🔄 Sincronizează</strong> sus pentru a genera unitățile + QR-urile bazate pe „Inventar fizic" din Setări produs.</p></div>';
            return;
        }
        grid.innerHTML = boats.map(b => {
            const color = statusColor(b.status);
            return `<div class="bg-white border-2 border-${color}-200 rounded-2xl p-4 lv-boat-print">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-3xl font-bold text-secondary">#${b.number}</p>
                    <span class="text-[10px] font-bold uppercase text-${color}-700 bg-${color}-100 px-2 py-1 rounded">${statusLabel(b.status)}</span>
                </div>
                <p class="text-sm font-medium text-secondary mb-3">${b.label || ''}</p>
                <div class="flex justify-center mb-2">
                    <img src="${qrUrl(b.qr_code, 200)}" alt="QR ${b.qr_code}" class="w-full max-w-[200px]">
                </div>
                <p class="text-[10px] font-mono text-center text-muted">${b.qr_code}</p>
            </div>`;
        }).join('');
    }
    async function syncBoats() {
        if (!currentTtId) return;
        if (!confirm('Sincronizezi numărul de bărci cu meta.physical_inventory.count?')) return;
        try {
            const res = await AmbiletAPI.post(`/organizer/events/${currentEventId}/leisure/boats/sync`, { ticket_type_id: currentTtId });
            const d = res.data || {};
            alert(`Sincronizare: ${d.created || 0} noi create, ${d.deactivated || 0} dezactivate. Total acum: ${d.total_now || 0}.`);
            loadBoats();
        } catch (e) {
            alert('Eroare: ' + (e?.message || ''));
        }
    }
    function showEmptyState() {
        $('lv-empty').classList.remove('hidden');
        $('lv-tt-select').classList.add('hidden');
        $('lv-sync-btn').classList.add('hidden');
        $('lv-print-btn').classList.add('hidden');
        $('lv-info-bar').classList.add('hidden');
    }
    function showToolbar() {
        $('lv-empty').classList.add('hidden');
        $('lv-tt-select').classList.remove('hidden');
        $('lv-sync-btn').classList.remove('hidden');
        $('lv-print-btn').classList.remove('hidden');
    }

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined') {
            $('lv-loading').classList.add('hidden');
            $('lv-error').textContent = 'API indisponibil.';
            $('lv-error').classList.remove('hidden');
            return;
        }
        try {
            await loadEvent();
            await loadProducts();
        } catch (e) {
            $('lv-loading').classList.add('hidden');
            $('lv-error').textContent = e.message || 'Eroare';
            $('lv-error').classList.remove('hidden');
            return;
        }
        // Empty state: niciun produs cu inventar fizic activat
        if (!rentalTypes.length) {
            $('lv-loading').classList.add('hidden');
            showEmptyState();
            return;
        }
        showToolbar();
        updateInfoBar();
        await loadBoats();

        $('lv-tt-select').addEventListener('change', e => {
            currentTtId = parseInt(e.target.value, 10);
            updateInfoBar();
            loadBoats();
        });
        $('lv-sync-btn').addEventListener('click', syncBoats);
        $('lv-print-btn').addEventListener('click', () => window.print());
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
