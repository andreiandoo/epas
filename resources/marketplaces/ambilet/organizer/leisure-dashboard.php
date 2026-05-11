<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Dashboard live';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_dashboard';
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
                    Dashboard live
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse" title="Live"></span>
                </h1>
                <p class="mt-1 text-sm text-muted">Snapshot real-time. Actualizare automată la 10s.</p>
            </div>
            <div class="text-xs text-muted">Ultimul refresh: <span id="lv-last-refresh">—</span></div>
        </div>

        <!-- Stats grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="p-5 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Vândut azi</p>
                <p class="text-3xl font-bold text-secondary"><span id="lv-stat-sold">0</span></p>
                <p class="text-xs text-muted mt-1">bilete</p>
            </div>
            <div class="p-5 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Scanat azi</p>
                <p class="text-3xl font-bold text-secondary"><span id="lv-stat-scanned">0</span></p>
                <p class="text-xs text-muted mt-1">check-in-uri</p>
            </div>
            <div class="p-5 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Ocupare curentă</p>
                <p class="text-3xl font-bold text-emerald-600"><span id="lv-stat-occupancy">0</span></p>
                <p class="text-xs text-muted mt-1">persoane în locație</p>
            </div>
            <div class="p-5 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Venit azi</p>
                <p class="text-3xl font-bold text-secondary"><span id="lv-stat-revenue">0</span> <span class="text-sm text-muted">RON</span></p>
                <p class="text-xs text-muted mt-1">total brut</p>
            </div>
        </div>

        <!-- Two columns: gates live + staff online -->
        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border rounded-2xl border-border">
                <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                    <h2 class="font-bold text-secondary">Activitate pe porți (ultima oră)</h2>
                    <span class="text-xs text-muted">Real-time</span>
                </div>
                <div id="lv-gates" class="p-5 space-y-3">
                    <p class="text-sm text-muted text-center py-8">Niciun check-in în ultima oră. (Implementare completă în F5.6)</p>
                </div>
            </div>
            <div class="bg-white border rounded-2xl border-border">
                <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                    <h2 class="font-bold text-secondary">Staff online</h2>
                    <span class="text-xs text-emerald-600 font-semibold">● Live</span>
                </div>
                <div id="lv-staff" class="p-5 space-y-2">
                    <p class="text-sm text-muted text-center py-8">Niciun membru activ acum. (Implementare completă în F5.6)</p>
                </div>
            </div>
        </div>

        <!-- Live stream -->
        <div class="mt-6 bg-white border rounded-2xl border-border">
            <div class="px-5 py-4 border-b border-border">
                <h2 class="font-bold text-secondary">Ultimele activități</h2>
            </div>
            <div id="lv-stream" class="p-5 divide-y divide-border">
                <p class="text-sm text-muted text-center py-8">Nicio activitate recentă. (Implementare completă în F5.6)</p>
            </div>
        </div>

        <!-- TODO note -->
        <div class="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-900">
            ℹ️ <strong>Implementare în curs:</strong> dashboard-ul live se conectează la API-ul real în F5.6. Pentru moment, valorile sunt 0. Punctele de check-in efective vor proveni din endpoint-ul <code class="bg-amber-100 px-1 rounded">/organizer/events/{event}/leisure/dashboard/live</code>.
        </div>
    </main>
</div>
<script>
(function(){
    const $ = (id) => document.getElementById(id);
    let currentEventId = null;
    async function refreshDashboard() {
        $('lv-last-refresh').textContent = new Date().toLocaleTimeString('ro-RO');
        if (!currentEventId) return;
        // TODO F5.6: fetch real-time data
        // try { const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/dashboard/live`); ... } catch {}
    }
    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined') return;
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const events = res.data || [];
            const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
            if (leisure.length > 0) currentEventId = leisure[0].id;
        } catch (e) { console.error(e); }
        refreshDashboard();
        setInterval(refreshDashboard, 10000);
    });
})();
</script>
<?php
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
