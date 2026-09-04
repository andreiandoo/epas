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
        <div class="bg-white border rounded-2xl border-border p-4 mb-6 space-y-3">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[200px]">
                    <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-2">Filtru perioadă</p>
                    <div class="flex flex-wrap gap-2">
                        <button data-range="7" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">7 zile</button>
                        <button data-range="14" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">14 zile</button>
                        <button data-range="30" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">1 lună</button>
                        <button data-range="90" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">3 luni</button>
                        <button data-range="180" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">6 luni</button>
                    </div>
                </div>
                <div class="flex items-end gap-2">
                    <label class="block">
                        <span class="text-xs font-semibold text-muted">Grupare</span>
                        <select id="lv-groupby" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
                            <option value="day">Pe zi</option>
                            <option value="week">Pe săptămână</option>
                            <option value="month">Pe lună</option>
                        </select>
                    </label>
                </div>
            </div>
            <!-- Row 2: perioada custom + export CSV -->
            <div class="flex flex-wrap items-end gap-3 pt-3 border-t border-slate-100">
                <div class="flex flex-wrap items-end gap-2">
                    <label class="block">
                        <span class="text-xs font-semibold text-muted">De la data</span>
                        <input id="lv-date-from" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted">Până la data</span>
                        <input id="lv-date-to" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
                    </label>
                    <button id="lv-apply-custom" type="button" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-primary bg-primary text-white hover:opacity-90">
                        Aplică
                    </button>
                </div>
                <div class="flex items-end gap-2 ml-auto">
                    <button id="lv-export-csv" type="button" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-emerald-600 bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 flex items-center gap-1">
                        <span>📥</span>
                        <span>Export CSV vânzări</span>
                    </button>
                </div>
                <span id="lv-range-label" class="text-xs text-muted w-full">Ultimele 7 zile</span>
            </div>
        </div>

        <!-- Stats principale: Total vandut / Comision / Net (cu split online-vs-POS) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <div class="p-4 bg-white border-2 rounded-2xl border-emerald-200">
                <p class="text-xs uppercase tracking-wider text-emerald-700 font-bold mb-1">💰 Total vândut</p>
                <p class="text-2xl font-extrabold text-emerald-900"><span id="lv-stat-total">0</span> <span class="text-sm text-emerald-700">RON</span></p>
                <div class="mt-2 pt-2 border-t border-emerald-100 text-xs space-y-0.5">
                    <div class="flex justify-between"><span class="text-muted">🌐 Online</span><span class="tabular-nums font-semibold" id="lv-rev-online">0</span></div>
                    <div class="flex justify-between"><span class="text-muted">🏪 POS</span><span class="tabular-nums font-semibold" id="lv-rev-pos">0</span></div>
                </div>
            </div>
            <div class="p-4 bg-white border-2 rounded-2xl border-amber-200">
                <p class="text-xs uppercase tracking-wider text-amber-700 font-bold mb-1">🧾 Comision ticketing</p>
                <p class="text-2xl font-extrabold text-amber-900"><span id="lv-stat-comm">0</span> <span class="text-sm text-amber-700">RON</span></p>
                <div class="mt-2 pt-2 border-t border-amber-100 text-xs space-y-0.5">
                    <div class="flex justify-between"><span class="text-muted">🌐 Online</span><span class="tabular-nums font-semibold" id="lv-comm-online">0</span></div>
                    <div class="flex justify-between"><span class="text-muted">🏪 POS</span><span class="tabular-nums font-semibold" id="lv-comm-pos">0</span></div>
                </div>
            </div>
            <div class="p-4 bg-white border-2 rounded-2xl border-sky-200">
                <p class="text-xs uppercase tracking-wider text-sky-700 font-bold mb-1">✅ Total net (după comision)</p>
                <p class="text-2xl font-extrabold text-sky-900"><span id="lv-stat-net">0</span> <span class="text-sm text-sky-700">RON</span></p>
                <div class="mt-2 pt-2 border-t border-sky-100 text-xs space-y-0.5">
                    <div class="flex justify-between"><span class="text-muted">🌐 Online</span><span class="tabular-nums font-semibold" id="lv-net-online">0</span></div>
                    <div class="flex justify-between"><span class="text-muted">🏪 POS</span><span class="tabular-nums font-semibold" id="lv-net-pos">0</span></div>
                </div>
            </div>
        </div>

        <!-- Stats secundare: Comenzi / Cos mediu / Bilete fizice / Bilete tranzactii + breakdown categorii -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">🧾 Comenzi</p>
                <p class="text-2xl font-bold text-secondary"><span id="lv-stat-orders">0</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">🛒 Coș mediu</p>
                <p class="text-2xl font-bold text-secondary"><span id="lv-stat-avg">0</span> <span class="text-sm text-muted">RON</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">🎟️ Bilete emise</p>
                <p class="text-2xl font-bold text-secondary"><span id="lv-stat-tickets-physical">0</span></p>
                <p class="mt-1 text-[10px] leading-tight text-muted"><span id="lv-stat-tickets-transactions">0</span> tranzacții cu valoare (pachet = 1)</p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">📂 Pe categorie</p>
                <div id="lv-cat-breakdown" class="text-xs space-y-0.5">
                    <p class="text-muted">—</p>
                </div>
            </div>
        </div>

        <!-- Cash / Card POS (brut) + sesiuni operatori POS -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">💵 Cash POS (brut)</p>
                <p class="text-xl font-bold text-secondary"><span id="lv-stat-cash">0</span> <span class="text-xs text-muted">RON</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">💳 Card POS (brut)</p>
                <p class="text-xl font-bold text-secondary"><span id="lv-stat-card">0</span> <span class="text-xs text-muted">RON</span></p>
            </div>
            <a href="/organizator/leisure-sessions" class="p-4 bg-white border rounded-2xl border-border hover:bg-slate-50 hover:border-primary transition-colors block">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">👤 Sesiuni casă POS</p>
                <p class="text-xl font-bold text-secondary" id="lv-sessions-count">0</p>
                <p class="mt-1 text-[10px] leading-tight text-primary font-semibold">→ Vezi toate detaliile ture</p>
            </a>
        </div>

        <!-- Chart + breakdown grid -->
        <div class="grid lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2 bg-white border rounded-2xl border-border p-5">
                <h2 class="font-bold text-secondary mb-3">Vânzări în timp</h2>
                <div class="h-64"><canvas id="lv-chart"></canvas></div>
            </div>
            <div class="bg-white border rounded-2xl border-border p-5 space-y-5">
                <div>
                    <h2 class="font-bold text-secondary mb-3">Pe categorii</h2>
                    <div id="lv-categories" class="space-y-2 text-sm">
                        <p class="text-muted text-center py-4">Selectează o perioadă pentru raport.</p>
                    </div>
                </div>
                <div class="pt-4 border-t border-border">
                    <h2 class="font-bold text-secondary mb-3">Pe metodă plată</h2>
                    <div id="lv-payment-methods" class="space-y-2 text-sm">
                        <p class="text-muted text-center py-4">Selectează o perioadă pentru raport.</p>
                    </div>
                </div>
                <div class="pt-4 border-t border-border">
                    <h2 class="font-bold text-secondary mb-3">Pe tip bilet</h2>
                    <div id="lv-ticket-types" class="space-y-2 text-sm max-h-96 overflow-y-auto pr-1">
                        <p class="text-muted text-center py-4">Selectează o perioadă pentru raport.</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="lv-error" class="hidden mt-6 p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-900"></div>
    </main>
</div>
<script>
(function(){
    const $ = (id) => document.getElementById(id);
    let chart = null;
    let currentEventId = null;
    let currentFrom = null;
    let currentTo = null;
    let currentGroupBy = 'day';
    let currentDays = '7';

    function fmtMoney(v) {
        return Number(v || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function categoryLabel(c) {
        const m = { 'access': 'Acces', 'parking': 'Parcare', 'rental': 'Închirieri', 'activity': 'Activități', 'extra': 'Extra' };
        return m[c] || c;
    }

    function categoryColor(c) {
        const m = { 'access': '#3B82F6', 'parking': '#8B5CF6', 'rental': '#F59E0B', 'activity': '#10B981', 'extra': '#64748B' };
        return m[c] || '#94A3B8';
    }

    function renderChart(rows, groupBy) {
        if (chart) { chart.destroy(); chart = null; }
        const labels = rows.map(r => {
            if (groupBy === 'month') return r.date;
            if (groupBy === 'week')  return r.date;
            try { return new Date(r.date + 'T00:00:00').toLocaleDateString('ro-RO', { day: '2-digit', month: 'short' }); }
            catch { return r.date; }
        });
        const data = rows.map(r => Number(r.revenue || 0));
        const ctx = $('lv-chart').getContext('2d');
        chart = new Chart(ctx, {
            type: 'line',
            data: { labels, datasets: [{ label: 'Vânzări (RON)', data, borderColor: '#22C55E', backgroundColor: 'rgba(34,197,94,0.12)', fill: true, tension: 0.3, pointRadius: 3 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    // Breakdown per metoda plata (cash / card / online)
    const PM_META = {
        cash:   { label: '💵 Cash',        color: '#F59E0B' }, // amber
        card:   { label: '💳 Card',        color: '#6366F1' }, // indigo
        online: { label: '🌐 Online',      color: '#10B981' }, // emerald
    };
    function renderPaymentMethods(rows) {
        const wrap = $('lv-payment-methods');
        if (!rows || !rows.length) {
            wrap.innerHTML = '<p class="text-muted text-center py-4">Nicio vânzare în această perioadă.</p>';
            return;
        }
        const totalRev = rows.reduce((s, r) => s + Number(r.revenue || 0), 0);
        const orderByRev = [...rows].sort((a, b) => Number(b.revenue || 0) - Number(a.revenue || 0));
        wrap.innerHTML = orderByRev.map(r => {
            const meta = PM_META[r.method] || { label: r.method, color: '#94A3B8' };
            const rev = Number(r.revenue || 0);
            const pct = totalRev > 0 ? Math.round((rev / totalRev) * 100) : 0;
            return `
            <div>
                <div class="flex justify-between text-xs mb-1 gap-2">
                    <span class="font-medium" style="color:${meta.color}">${meta.label}</span>
                    <span class="text-muted whitespace-nowrap">${r.orders} comenzi · ${r.tickets} bilete · <strong>${fmtMoney(rev)} RON</strong> · ${pct}%</span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full" style="width:${pct}%;background:${meta.color}"></div>
                </div>
            </div>`;
        }).join('');
    }

    // Breakdown per tip bilet (nume + count + venit + procent din total)
    function renderTicketTypes(rows) {
        const wrap = $('lv-ticket-types');
        if (!rows || !rows.length) {
            wrap.innerHTML = '<p class="text-muted text-center py-4">Nicio vânzare în această perioadă.</p>';
            return;
        }
        const totalTickets = rows.reduce((s, r) => s + Number(r.tickets || 0), 0);
        wrap.innerHTML = rows.map(r => {
            const pct = totalTickets > 0 ? Math.round((r.tickets / totalTickets) * 100) : 0;
            const color = categoryColor(r.service_category || 'access');
            const rev = Number(r.revenue || 0);
            const safeName = String(r.name || 'Bilet').replace(/[<>&]/g, s => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[s]));
            return `
            <div>
                <div class="flex justify-between text-xs mb-1 gap-2">
                    <span class="font-medium truncate" style="color:${color}" title="${safeName}">${safeName}</span>
                    <span class="text-muted whitespace-nowrap">${r.tickets} × · ${fmtMoney(rev)} RON</span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full" style="width:${pct}%;background:${color}"></div>
                </div>
            </div>`;
        }).join('');
    }

    function renderCategories(byCat) {
        const wrap = $('lv-categories');
        const entries = Object.entries(byCat || {});
        if (!entries.length) {
            wrap.innerHTML = '<p class="text-muted text-center py-4">Nicio vânzare în această perioadă.</p>';
            return;
        }
        const total = entries.reduce((s, [, n]) => s + Number(n), 0);
        wrap.innerHTML = entries
            .sort((a, b) => b[1] - a[1])
            .map(([cat, n]) => {
                const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                const color = categoryColor(cat);
                return `
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="font-medium" style="color:${color}">${categoryLabel(cat)}</span>
                        <span class="text-muted">${n} bilete · ${pct}%</span>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full" style="width:${pct}%;background:${color}"></div>
                    </div>
                </div>`;
            })
            .join('');
    }

    function setRange(days) {
        currentDays = days;
        // Fix bg-white conflict: elimin bg-white cand adaug bg-primary + repopulez la deselect
        document.querySelectorAll('.lv-range-btn').forEach(b => {
            b.classList.remove('bg-primary', 'text-white', 'border-primary');
            b.classList.add('bg-white');
        });
        const btn = document.querySelector(`.lv-range-btn[data-range="${days}"]`);
        if (btn) { btn.classList.remove('bg-white'); btn.classList.add('bg-primary', 'text-white', 'border-primary'); }
        const labels = { '7': '7 zile', '14': '14 zile', '30': '1 lună', '90': '3 luni', '180': '6 luni' };
        $('lv-range-label').textContent = 'Ultimele ' + (labels[days] || days);
        const to = new Date();
        const from = new Date(Date.now() - parseInt(days, 10) * 86400000);
        currentFrom = from.toISOString().slice(0, 10);
        currentTo = to.toISOString().slice(0, 10);
        // Sync inputs date pentru vizibilitate + ca export CSV sa functioneze cu presetul curent
        const dfEl = $('lv-date-from'); if (dfEl) dfEl.value = currentFrom;
        const dtEl = $('lv-date-to');   if (dtEl) dtEl.value = currentTo;
        loadTimeline();
    }

    // Interval custom: iau valorile din inputs, valideaza, aplica.
    function applyCustomRange() {
        const df = $('lv-date-from').value;
        const dt = $('lv-date-to').value;
        if (!df || !dt) { alert('Selectează ambele date (de la / până la).'); return; }
        if (df > dt) { alert('„De la" trebuie să fie înainte de „Până la".'); return; }
        // Deselecteaza toate presetele - suntem in custom
        document.querySelectorAll('.lv-range-btn').forEach(b => {
            b.classList.remove('bg-primary', 'text-white', 'border-primary');
            b.classList.add('bg-white');
        });
        currentDays = null;
        currentFrom = df;
        currentTo = dt;
        const fmt = s => { try { return new Date(s + 'T00:00:00').toLocaleDateString('ro-RO', { day: '2-digit', month: 'short', year: 'numeric' }); } catch { return s; } };
        $('lv-range-label').textContent = 'Interval custom · ' + fmt(df) + ' – ' + fmt(dt);
        loadTimeline();
    }

    // Descarca CSV cu vanzarile din intervalul curent (currentFrom - currentTo).
    // Foloseste AmBiletAPI cu Authorization header (endpointul e proteged).
    async function exportCsv() {
        if (!currentEventId) { alert('Nu e configurat evenimentul.'); return; }
        if (!currentFrom || !currentTo) { alert('Selectează perioada înainte de export.'); return; }
        const btn = $('lv-export-csv');
        const orig = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<span>⏳</span><span>Se generează...</span>';
        try {
            const proxyBase = (window.AMBILET && window.AMBILET.apiUrl) || '/api/proxy.php';
            const csvUrl = proxyBase + '?action=organizer.event.leisure.sales.range-csv'
                + '&event=' + encodeURIComponent(currentEventId)
                + '&from=' + encodeURIComponent(currentFrom)
                + '&to=' + encodeURIComponent(currentTo);
            const token = localStorage.getItem('ambilet_organizer_token') || localStorage.getItem('organizer_token') || '';
            const resp = await fetch(csvUrl, { headers: token ? { 'Authorization': 'Bearer ' + token } : {} });
            if (!resp.ok) throw new Error('HTTP ' + resp.status);
            const blob = await resp.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'vanzari-' + currentFrom + '_' + currentTo + '.csv';
            document.body.appendChild(a); a.click();
            setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 100);
        } catch (e) {
            alert('Eroare la export CSV: ' + (e?.message || 'necunoscut'));
        } finally {
            btn.disabled = false; btn.innerHTML = orig;
        }
    }

    // Toggle loading state pe cardurile care se schimba la fiecare loadTimeline.
    // Aplic opacity + pointer-events blocat + spinner label peste chart si peste
    // fiecare din cele 3 tab-uri (categorii / metoda plata / tip bilet). Nu blocam
    // butoanele de sus (Aplica / range / groupby) - user poate schimba din nou
    // in timp ce se incarca.
    const LOADING_TARGETS = ['lv-chart', 'lv-categories', 'lv-payment-methods', 'lv-ticket-types'];
    function setLoading(on) {
        LOADING_TARGETS.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            const parent = el.closest('.bg-white') || el.parentElement;
            if (!parent) return;
            if (on) {
                parent.style.position = parent.style.position || 'relative';
                parent.classList.add('lv-is-loading');
                if (!parent.querySelector('.lv-loading-overlay')) {
                    const overlay = document.createElement('div');
                    overlay.className = 'lv-loading-overlay absolute inset-0 flex items-center justify-center bg-white/70 backdrop-blur-sm rounded-2xl z-10';
                    overlay.innerHTML = '<div class="flex items-center gap-2 text-sm text-primary"><svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"></circle><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" class="opacity-75"></path></svg><span>Se încarcă…</span></div>';
                    parent.appendChild(overlay);
                }
            } else {
                parent.classList.remove('lv-is-loading');
                const overlay = parent.querySelector('.lv-loading-overlay');
                if (overlay) overlay.remove();
            }
        });
        // Cards de sus (Total / Comision / Net etc) — opacity subtila
        document.querySelectorAll('[id^="lv-stat-"]').forEach(el => {
            el.style.opacity = on ? '0.4' : '';
            el.style.transition = 'opacity 0.15s';
        });
    }

    async function loadTimeline() {
        $('lv-error').classList.add('hidden');
        if (!currentEventId) {
            $('lv-error').textContent = 'Nu există un eveniment de tip Locație de agrement asociat.';
            $('lv-error').classList.remove('hidden');
            return;
        }
        setLoading(true);
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/sales-timeline`, {
                from: currentFrom,
                to: currentTo,
                group_by: currentGroupBy,
            });
            const data = res.data || {};
            renderChart(data.rows || [], data.group_by || 'day');
            renderCategories(data.by_category || {});
            renderPaymentMethods(data.by_payment_method || []);
            renderTicketTypes(data.by_ticket_type || []);
        } catch (e) {
            console.error('[leisure-sales] load failed', e);
            $('lv-error').textContent = 'Eroare la încărcarea datelor: ' + (e?.message || 'necunoscut');
            $('lv-error').classList.remove('hidden');
        } finally {
            setLoading(false);
        }
        // Load summary in paralel (cifre pentru toate cardurile)
        loadSummary();
    }

    // Populare carduri Total/Comision/Net (cu split online-POS) + comenzi/cos/bilete
    // + cash/card POS + sesiuni operatori. Endpoint dedicat sales-summary.
    async function loadSummary() {
        if (!currentEventId || !currentFrom || !currentTo) return;
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/sales/summary`, {
                from: currentFrom, to: currentTo,
            });
            const d = res.data || {}; const t = d.totals || {}; const pos = d.pos || {};
            // Row 1: Total vandut / Comision / Net (cu split online + POS)
            $('lv-stat-total').textContent = fmtMoney(t.revenue_total);
            $('lv-rev-online').textContent = fmtMoney(t.revenue_online) + ' RON';
            $('lv-rev-pos').textContent    = fmtMoney(t.revenue_pos) + ' RON';
            $('lv-stat-comm').textContent  = fmtMoney(t.commission_total);
            $('lv-comm-online').textContent = fmtMoney(t.commission_online) + ' RON';
            $('lv-comm-pos').textContent    = fmtMoney(t.commission_pos) + ' RON';
            $('lv-stat-net').textContent   = fmtMoney(t.net_total);
            $('lv-net-online').textContent = fmtMoney(t.net_online) + ' RON';
            $('lv-net-pos').textContent    = fmtMoney(t.net_pos) + ' RON';
            // Row 2: Comenzi / Cos mediu / Bilete / Categorii
            $('lv-stat-orders').textContent = t.orders || 0;
            $('lv-stat-avg').textContent = fmtMoney(t.avg_order);
            $('lv-stat-tickets-physical').textContent = t.tickets_physical || 0;
            $('lv-stat-tickets-transactions').textContent = t.tickets_transactions || 0;
            const cat = t.tickets_by_category || {};
            const catWrap = $('lv-cat-breakdown');
            if (Object.keys(cat).length === 0) {
                catWrap.innerHTML = '<p class="text-muted">—</p>';
            } else {
                catWrap.innerHTML = Object.entries(cat)
                    .sort((a, b) => b[1] - a[1])
                    .map(([c, n]) => `<div class="flex justify-between"><span style="color:${categoryColor(c)}">${categoryLabel(c)}</span><span class="tabular-nums font-semibold">${n}</span></div>`)
                    .join('');
            }
            // Row 3: Cash / Card / Sesiuni POS
            $('lv-stat-cash').textContent = fmtMoney(pos.cash_gross);
            $('lv-stat-card').textContent = fmtMoney(pos.card_gross);
            const sessions = Array.isArray(d.sessions) ? d.sessions : [];
            $('lv-sessions-count').textContent = sessions.length;
            // Detaliile sesiunilor: pagina dedicata /organizator/leisure-sessions
        } catch (e) {
            console.warn('[leisure-sales] summary failed', e);
        }
    }

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined' || typeof Chart === 'undefined') {
            $('lv-error').textContent = 'Resurse JS indisponibile — reîncarcă pagina.';
            $('lv-error').classList.remove('hidden');
            return;
        }
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const events = res.data || [];
            const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
            if (leisure.length > 0) currentEventId = leisure[0].id;
        } catch (e) { console.error(e); }

        document.querySelectorAll('.lv-range-btn').forEach(b => b.addEventListener('click', () => setRange(b.dataset.range)));
        $('lv-groupby').addEventListener('change', (e) => { currentGroupBy = e.target.value; loadTimeline(); });
        $('lv-apply-custom')?.addEventListener('click', applyCustomRange);
        $('lv-export-csv')?.addEventListener('click', exportCsv);
        setRange('7');
    });
})();
</script>
<?php
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
