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
        <!-- ===== HERO ===== -->
        <section class="relative mb-6 overflow-hidden shadow-lg rounded-3xl"
                 style="background:linear-gradient(135deg,#064e3b 0%,#065f46 55%,#0f766e 100%);">
            <div class="absolute inset-0 opacity-40" style="background:radial-gradient(1000px 260px at 85% -20%, rgba(16,185,129,.55), transparent 60%);"></div>
            <div class="relative flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between lg:p-8">
                <div class="min-w-0">
                    <span class="inline-flex items-center gap-2 px-3 py-1 mb-2 text-[11px] font-bold tracking-wide text-white uppercase rounded-full bg-white/10 backdrop-blur">
                        <span class="relative flex w-2 h-2"><span class="absolute inline-flex w-full h-full rounded-full opacity-75 animate-ping bg-emerald-300"></span><span class="relative inline-flex w-2 h-2 rounded-full bg-emerald-300"></span></span>
                        Live
                    </span>
                    <h1 class="text-2xl font-extrabold text-white truncate lg:text-3xl" id="lv-event-name">Dashboard live</h1>
                    <p class="mt-1 text-sm text-emerald-100/80">Snapshot real-time · actualizare la 10s · ultimul refresh: <span id="lv-last-refresh">—</span></p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a id="lv-public-link" href="#" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-bold text-white transition-all rounded-xl bg-white/10 backdrop-blur hover:bg-white/20">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        Pagină publică
                    </a>
                    <a href="/organizator/leisure-pos" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-bold text-emerald-900 transition-all bg-white rounded-xl hover:bg-emerald-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Emite bilete
                    </a>
                </div>
            </div>
        </section>

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

        <!-- Two columns: gates live + stream -->
        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white border rounded-2xl border-border">
                <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                    <h2 class="font-bold text-secondary">Activitate scanări (ultima oră)</h2>
                    <span class="text-xs text-muted">Bucket-uri 5 min</span>
                </div>
                <div id="lv-gates" class="p-5">
                    <p class="text-sm text-muted text-center py-8">Niciun check-in în ultima oră.</p>
                </div>
            </div>
            <div class="bg-white border rounded-2xl border-border">
                <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                    <h2 class="font-bold text-secondary">Ultimele activități</h2>
                    <span class="text-xs text-emerald-600 font-semibold">● Live</span>
                </div>
                <div id="lv-stream" class="p-3 max-h-[420px] overflow-y-auto divide-y divide-border">
                    <p class="text-sm text-muted text-center py-8">Nicio activitate recentă.</p>
                </div>
            </div>
        </div>

        <div id="lv-error" class="hidden mt-6 p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-900"></div>
    </main>
</div>
<script>
(function(){
    const $ = (id) => document.getElementById(id);
    let currentEventId = null;
    let pollHandle = null;
    let isPolling = false;

    function fmtMoney(v) {
        return Number(v || 0).toLocaleString('ro-RO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function timeAgo(iso) {
        if (!iso) return '—';
        try {
            const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
            if (diff < 60) return diff + 's';
            if (diff < 3600) return Math.floor(diff / 60) + 'm';
            return Math.floor(diff / 3600) + 'h';
        } catch { return '—'; }
    }

    function renderGates(buckets) {
        const wrap = $('lv-gates');
        if (!buckets || !buckets.length) {
            wrap.innerHTML = '<p class="text-sm text-muted text-center py-8">Niciun check-in în ultima oră.</p>';
            return;
        }
        const max = Math.max(1, ...buckets.map(b => b.count));
        wrap.innerHTML = '<div class="flex items-end gap-1 h-32">' +
            buckets.map(b => {
                const h = Math.round((b.count / max) * 100);
                return `<div class="flex-1 flex flex-col items-center gap-1" title="${b.time}: ${b.count}">
                    <div class="text-[10px] font-bold text-secondary">${b.count}</div>
                    <div class="w-full bg-emerald-500 rounded-t" style="height:${h}%;min-height:4px"></div>
                    <div class="text-[10px] text-muted">${b.time}</div>
                </div>`;
            }).join('') +
        '</div>';
    }

    function renderStream(stream) {
        const wrap = $('lv-stream');
        if (!stream || !stream.length) {
            wrap.innerHTML = '<p class="text-sm text-muted text-center py-8">Nicio activitate recentă.</p>';
            return;
        }
        wrap.innerHTML = stream.map(ev => {
            let icon = '🎟️';
            let colorRing = 'bg-emerald-100';
            if (ev.type === 'sale') { icon = '💰'; colorRing = 'bg-amber-100'; }
            else if (ev.type === 'staff_scan') { icon = '👷'; colorRing = 'bg-sky-100'; }
            return `<div class="px-2 py-2 flex items-start gap-3">
                <div class="w-9 h-9 ${colorRing} rounded-full flex items-center justify-center text-base flex-shrink-0">${icon}</div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-semibold text-secondary">${ev.label}</div>
                    <div class="text-xs text-muted truncate">${ev.detail || ''}</div>
                </div>
                <div class="text-[11px] text-muted whitespace-nowrap">${timeAgo(ev.at)}</div>
            </div>`;
        }).join('');
    }

    async function refreshDashboard() {
        if (isPolling || !currentEventId) return;
        isPolling = true;
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/dashboard/live`);
            const data = res.data || {};
            const s = data.stats || {};
            $('lv-stat-sold').textContent = s.sold_today || 0;
            $('lv-stat-scanned').textContent = s.scanned_today || 0;
            $('lv-stat-occupancy').textContent = s.occupancy || 0;
            $('lv-stat-revenue').textContent = fmtMoney(s.revenue_today);
            renderGates(data.gates_activity || []);
            renderStream(data.stream || []);
            $('lv-last-refresh').textContent = new Date().toLocaleTimeString('ro-RO');
            $('lv-error').classList.add('hidden');
        } catch (e) {
            console.error('[leisure-dashboard] live failed', e);
            $('lv-error').textContent = 'Eroare la încărcarea snapshot-ului live: ' + (e?.message || 'necunoscut');
            $('lv-error').classList.remove('hidden');
        } finally {
            isPolling = false;
        }
    }

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined') {
            $('lv-error').textContent = 'API indisponibil — reîncarcă pagina.';
            $('lv-error').classList.remove('hidden');
            return;
        }
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const events = res.data || [];
            const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
            if (leisure.length > 0) {
                const ev = leisure[0];
                currentEventId = ev.id;
                const nameEl = $('lv-event-name');
                if (nameEl && ev.name) nameEl.textContent = ev.name;
                const pubLink = $('lv-public-link');
                if (pubLink) pubLink.href = '/bilete/' + (ev.slug || ev.id) + (ev.is_published ? '' : '?preview=1');
            }
        } catch (e) { console.error(e); }

        if (!currentEventId) {
            $('lv-error').textContent = 'Nu există un eveniment de tip Locație de agrement.';
            $('lv-error').classList.remove('hidden');
            return;
        }

        refreshDashboard();
        pollHandle = setInterval(refreshDashboard, 10000);

        // Oprește polling-ul când tab-ul nu e activ
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                clearInterval(pollHandle);
                pollHandle = null;
            } else if (!pollHandle) {
                refreshDashboard();
                pollHandle = setInterval(refreshDashboard, 10000);
            }
        });
    });
})();
</script>
<?php
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
