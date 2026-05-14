<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Bărci & QR-uri';
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
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl">🛶 Bărci & QR-uri</h1>
                <p class="mt-1 text-sm text-muted">Lista unităților fizice (bărci/kayak-uri) și QR-uri printabile pentru fiecare.</p>
            </div>
            <div class="flex items-center gap-2">
                <select id="lv-tt-select" class="px-3 py-2 text-sm border border-border rounded-lg bg-white"></select>
                <button id="lv-sync-btn" class="px-3 py-2 text-sm font-medium bg-white border border-border rounded-lg hover:bg-slate-50">🔄 Sincronizează</button>
                <button id="lv-print-btn" class="px-3 py-2 text-sm font-semibold bg-primary text-white rounded-lg">🖨 Print QR-uri</button>
            </div>
        </div>

        <div id="lv-error" class="hidden mb-4 p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-900 print:hidden"></div>
        <div id="lv-loading" class="p-8 text-center print:hidden"><div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div></div>

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
        if (leisure.length === 0) throw new Error('Nu există event leisure.');
        currentEventId = leisure[0].id;
    }
    async function loadProducts() {
        const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/config`);
        const all = res.data?.ticket_types || [];
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
        $('lv-loading').classList.remove('hidden');
        $('lv-boats-grid').innerHTML = '';
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
            grid.innerHTML = '<p class="col-span-full text-center text-muted py-8">Nicio barcă. Apasă Sincronizează ca să le generezi din meta.physical_inventory.count.</p>';
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
    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined') return;
        try {
            await loadEvent();
            await loadProducts();
            await loadBoats();
        } catch (e) {
            $('lv-error').textContent = e.message || 'Eroare';
            $('lv-error').classList.remove('hidden');
            return;
        }
        $('lv-tt-select').addEventListener('change', e => { currentTtId = parseInt(e.target.value, 10); loadBoats(); });
        $('lv-sync-btn').addEventListener('click', syncBoats);
        $('lv-print-btn').addEventListener('click', () => window.print());
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
