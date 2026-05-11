<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Vânzări';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_sales';
$cssBundle = 'organizer';
$headExtra = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Vânzări</h1>
            <p class="mt-1 text-sm text-muted">Cifre detaliate pe perioade — pe categorii, societăți emitente, ore vârf.</p>
        </div>

        <!-- Date range -->
        <div class="bg-white border rounded-2xl border-border p-4 mb-6 flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[200px]">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-2">Filtru perioadă</p>
                <div class="flex flex-wrap gap-2">
                    <button data-range="7" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">7 zile</button>
                    <button data-range="14" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">14 zile</button>
                    <button data-range="30" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">1 lună</button>
                    <button data-range="90" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">3 luni</button>
                    <button data-range="180" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">6 luni</button>
                    <button data-range="custom" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">Custom</button>
                </div>
            </div>
            <span id="lv-range-label" class="text-xs text-muted">Ultimele 7 zile</span>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Total vândut</p>
                <p class="text-2xl font-bold text-secondary"><span id="lv-stat-total">0</span> <span class="text-sm text-muted">RON</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Bilete vândute</p>
                <p class="text-2xl font-bold text-secondary"><span id="lv-stat-tickets">0</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Comenzi</p>
                <p class="text-2xl font-bold text-secondary"><span id="lv-stat-orders">0</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Coș mediu</p>
                <p class="text-2xl font-bold text-secondary"><span id="lv-stat-avg">0</span> <span class="text-sm text-muted">RON</span></p>
            </div>
        </div>

        <!-- Chart + breakdown grid -->
        <div class="grid lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2 bg-white border rounded-2xl border-border p-5">
                <h2 class="font-bold text-secondary mb-3">Vânzări în timp</h2>
                <div class="h-64"><canvas id="lv-chart"></canvas></div>
            </div>
            <div class="bg-white border rounded-2xl border-border p-5">
                <h2 class="font-bold text-secondary mb-3">Per societate emitentă</h2>
                <div id="lv-issuers" class="space-y-2 text-sm">
                    <p class="text-muted text-center py-4">(Conectează endpoint by-issuer)</p>
                </div>
            </div>
        </div>

        <div class="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-900">
            ℹ️ <strong>Implementare în curs:</strong> graficul + tabelele se conectează la <code class="bg-amber-100 px-1 rounded">/leisure/sales</code> (F5.5). Pentru moment, statisticile sunt 0. <code>by-issuer</code> e deja LIVE — accesează tab "Sumar & raport" din pagina <a href="/organizator/leisure" class="underline font-medium">Conținut pagină</a> pentru raport per societate.
        </div>
    </main>
</div>
<script>
(function(){
    const $ = (id) => document.getElementById(id);
    let chart = null;
    function setRange(days) {
        document.querySelectorAll('.lv-range-btn').forEach(b => b.classList.remove('bg-primary', 'text-white', 'border-primary'));
        const btn = document.querySelector(`.lv-range-btn[data-range="${days}"]`);
        if (btn) btn.classList.add('bg-primary', 'text-white', 'border-primary');
        const labels = { '7': '7 zile', '14': '14 zile', '30': '1 lună', '90': '3 luni', '180': '6 luni', 'custom': 'Custom' };
        $('lv-range-label').textContent = 'Ultimele ' + (labels[days] || days);
        // TODO F5.5: fetch + render chart
        if (chart) chart.destroy();
        const ctx = $('lv-chart').getContext('2d');
        chart = new Chart(ctx, {
            type: 'line',
            data: { labels: ['Date'], datasets: [{ label: 'Vânzări (RON)', data: [0], borderColor: '#22C55E', backgroundColor: 'rgba(34,197,94,0.1)', fill: true, tension: 0.3 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
    }
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.lv-range-btn').forEach(b => b.addEventListener('click', () => setRange(b.dataset.range)));
        setRange('7');
    });
})();
</script>
<?php
require_once dirname(__DIR__) . '/includes/organizer-footer.php';
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
