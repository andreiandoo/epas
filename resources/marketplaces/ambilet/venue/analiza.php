<?php
/**
 * Venue Owner — /venue/analiza
 *
 * Ports the 9-tab Filament VenueAnalytics page. Each tab loads on
 * demand from /api/marketplace-client/venue-owner/analytics/{tab} via
 * AmbiletVenueAPI, so a user visiting the page and staying on the
 * Overview tab pays for one API call rather than 28.
 *
 * Chart.js is loaded from cdnjs (same source used across Ambilet).
 * All tab switching is plain click handlers — no Alpine dep so
 * this page keeps its own footprint minimal.
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle       = 'Analiză Locație';
$venuePageTitle  = 'Analiză';
$bodyClass       = 'min-h-screen flex bg-slate-100';
$currentPage     = 'venue_analiza';
$cssBundle       = 'organizer';
$headExtra       = '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/venue-sidebar.php';
?>

    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/venue-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8">
            <!-- Venue picker -->
            <div class="flex flex-wrap items-center gap-3 mb-6">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Analiză</h2>
                    <p class="mt-1 text-sm text-slate-500">Metrici, tendințe și recomandări pentru locațiile tale.</p>
                </div>
                <div class="ml-auto">
                    <select id="venue-picker" class="px-4 py-2 text-sm bg-white border rounded-lg border-slate-200">
                        <option value="all">Toate locațiile</option>
                    </select>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-2 mb-4 overflow-x-auto border-b border-slate-200">
                <?php
                $tabs = [
                    ['id' => 'overview',      'label' => 'Vedere generală'],
                    ['id' => 'financial',     'label' => 'Financiar'],
                    ['id' => 'audience',      'label' => 'Public'],
                    ['id' => 'artists',       'label' => 'Artiști'],
                    ['id' => 'scheduling',    'label' => 'Programare'],
                    ['id' => 'opportunities', 'label' => 'Oportunități'],
                    ['id' => 'promotion',     'label' => 'Promovare'],
                    ['id' => 'upcoming',      'label' => 'Următoare'],
                    ['id' => 'actions',       'label' => 'Acțiuni'],
                ];
                foreach ($tabs as $t): ?>
                    <button type="button" data-tab="<?= $t['id'] ?>"
                            class="analiza-tab-btn px-4 py-3 text-sm font-semibold text-slate-500 border-b-2 border-transparent whitespace-nowrap transition-colors">
                        <?= $t['label'] ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Tab panels -->
            <?php foreach ($tabs as $t): ?>
                <div id="tab-<?= $t['id'] ?>" class="analiza-tab-panel hidden">
                    <div class="analiza-loading p-12 text-center bg-white border rounded-2xl border-slate-200">
                        <div class="inline-block w-8 h-8 border-4 rounded-full animate-spin border-blue-200 border-t-blue-500"></div>
                        <p class="mt-3 text-sm text-slate-500">Se încarcă…</p>
                    </div>
                    <div class="analiza-content hidden space-y-6"></div>
                </div>
            <?php endforeach; ?>
        </main>
    </div>

<style>
    .analiza-tab-btn.active { color:#3b82f6; border-color:#3b82f6; }
    .analiza-tab-btn:hover:not(.active) { color:#1e293b; }
    .analiza-section { padding:1.5rem; background:#fff; border:1px solid #e2e8f0; border-radius:1rem; }
    .analiza-section h3 { font-size:1rem; font-weight:700; color:#0f172a; margin-bottom:1rem; }
    .analiza-kpi-grid { display:grid; gap:1rem; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); }
    .analiza-kpi { padding:1rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:.75rem; }
    .analiza-kpi__value { font-size:1.5rem; font-weight:700; color:#0f172a; }
    .analiza-kpi__label { font-size:.75rem; color:#64748b; margin-top:.25rem; text-transform:uppercase; letter-spacing:.05em; }
    .analiza-table { width:100%; border-collapse:collapse; font-size:.875rem; }
    .analiza-table th { padding:.5rem .75rem; text-align:left; font-size:.7rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#64748b; background:#f8fafc; }
    .analiza-table td { padding:.5rem .75rem; border-top:1px solid #f1f5f9; color:#0f172a; }
    .analiza-table tr:hover td { background:#f8fafc; }
    .analiza-tag { display:inline-block; padding:.15rem .5rem; font-size:.7rem; font-weight:600; border-radius:.25rem; }
    .analiza-tag--info    { background:rgba(59,130,246,.1); color:#3b82f6; }
    .analiza-tag--success { background:rgba(16,185,129,.1); color:#059669; }
    .analiza-tag--warn    { background:rgba(245,158,11,.1); color:#f59e0b; }
    .analiza-tag--danger  { background:rgba(239,68,68,.1); color:#dc2626; }
</style>

<script>
(async function () {
    if (typeof AmbiletVenueAPI === 'undefined') return;

    // ── Helpers ─────────────────────────────────────────────────
    const fmtInt = n => Number(n || 0).toLocaleString('ro-RO');
    const fmtMoney = n => Number(n || 0).toLocaleString('ro-RO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    const fmtPct = n => (Number(n || 0)).toFixed(1) + '%';
    const escapeHtml = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
    const titleOf = e => (e && e.title && (e.title.ro || e.title.en)) || (e && e.name) || '—';

    // ── State ───────────────────────────────────────────────────
    const state = {
        venueId: 'all',
        loaded: {}, // { overview: true, ... } so we don't re-fetch on tab switch
        activeTab: 'overview',
    };

    // ── Venue picker ────────────────────────────────────────────
    async function initVenuePicker() {
        try {
            const res = await AmbiletVenueAPI.venues();
            if (res && res.success && res.data && Array.isArray(res.data.venues)) {
                const sel = document.getElementById('venue-picker');
                res.data.venues.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.id;
                    opt.textContent = v.name + (v.city ? ' (' + v.city + ')' : '');
                    sel.appendChild(opt);
                });
                // Pre-select from URL if present
                const urlVenueId = new URLSearchParams(window.location.search).get('venue_id');
                if (urlVenueId) {
                    sel.value = urlVenueId;
                    state.venueId = urlVenueId;
                }
                sel.addEventListener('change', () => {
                    state.venueId = sel.value;
                    state.loaded = {}; // invalidate cache
                    Object.keys(state.loaded).forEach(k => state.loaded[k] = false);
                    loadTab(state.activeTab, true);
                });
            }
        } catch (e) { console.error(e); }
    }

    // ── Tab switching ───────────────────────────────────────────
    function switchTab(id) {
        state.activeTab = id;
        document.querySelectorAll('.analiza-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === id));
        document.querySelectorAll('.analiza-tab-panel').forEach(p => p.classList.toggle('hidden', p.id !== 'tab-' + id));
        if (!state.loaded[id]) loadTab(id);
    }

    async function loadTab(id, force) {
        const panel = document.getElementById('tab-' + id);
        const loading = panel.querySelector('.analiza-loading');
        const content = panel.querySelector('.analiza-content');
        loading.classList.remove('hidden');
        content.classList.add('hidden');
        content.innerHTML = '';

        try {
            const venueId = state.venueId !== 'all' ? state.venueId : null;
            const res = await AmbiletVenueAPI.analytics(id, venueId);
            if (res && res.success && res.data) {
                renderTab(id, res.data, content);
                state.loaded[id] = true;
            } else {
                content.innerHTML = '<div class="analiza-section"><p class="text-sm text-slate-500">Nu există date pentru acest tab.</p></div>';
            }
        } catch (e) {
            console.error('tab load failed', id, e);
            content.innerHTML = '<div class="analiza-section"><p class="text-sm text-red-500">Eroare la încărcare.</p></div>';
        }

        loading.classList.add('hidden');
        content.classList.remove('hidden');
    }

    // ── Renderers per tab ───────────────────────────────────────

    function renderTab(id, data, root) {
        switch (id) {
            case 'overview':      return renderOverview(data, root);
            case 'financial':     return renderFinancial(data, root);
            case 'audience':      return renderAudience(data, root);
            case 'artists':       return renderArtists(data, root);
            case 'scheduling':    return renderScheduling(data, root);
            case 'opportunities': return renderOpportunities(data, root);
            case 'promotion':     return renderPromotion(data, root);
            case 'upcoming':      return renderUpcoming(data, root);
            case 'actions':       return renderActions(data, root);
        }
    }

    // ── Overview ────────────────────────────────────────────────
    function renderOverview(d, root) {
        const kpis = d.kpis || {};
        const health = d.health_score || {};
        const momentum = d.monthly_momentum || {};
        const ys = d.yearly_series || {};

        // KPI strip
        const kpiSection = section('Indicatori cheie', `
            <div class="analiza-kpi-grid">
                <div class="analiza-kpi"><div class="analiza-kpi__value">${fmtInt(kpis.total_events)}</div><div class="analiza-kpi__label">Evenimente</div></div>
                <div class="analiza-kpi"><div class="analiza-kpi__value">${fmtInt(kpis.total_tickets)}</div><div class="analiza-kpi__label">Bilete vândute</div></div>
                <div class="analiza-kpi"><div class="analiza-kpi__value">${fmtMoney(kpis.total_revenue)} RON</div><div class="analiza-kpi__label">Venit total</div></div>
                <div class="analiza-kpi"><div class="analiza-kpi__value">${fmtInt(kpis.unique_buyers)}</div><div class="analiza-kpi__label">Cumpărători unici</div></div>
                <div class="analiza-kpi"><div class="analiza-kpi__value">${fmtPct(kpis.avg_occupancy)}</div><div class="analiza-kpi__label">Ocupare medie</div></div>
                <div class="analiza-kpi"><div class="analiza-kpi__value">${fmtMoney(kpis.avg_ticket_price)} RON</div><div class="analiza-kpi__label">Preț mediu</div></div>
            </div>
        `);

        // Health score
        const healthColor = (health.score || 0) >= 80 ? '#10b981' : (health.score || 0) >= 60 ? '#f59e0b' : '#ef4444';
        const healthSection = section('Scor sănătate locație', `
            <div class="flex items-center gap-6">
                <div class="relative w-24 h-24">
                    <svg viewBox="0 0 36 36" class="w-full h-full">
                        <path d="M18 2 a 16 16 0 1 1 0 32 a 16 16 0 1 1 0 -32" fill="none" stroke="#e2e8f0" stroke-width="3"/>
                        <path d="M18 2 a 16 16 0 1 1 0 32 a 16 16 0 1 1 0 -32" fill="none" stroke="${healthColor}" stroke-width="3" stroke-dasharray="${(health.score || 0)}, 100"/>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-2xl font-bold" style="color:${healthColor};">${Math.round(health.score || 0)}</span>
                    </div>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-slate-900">${escapeHtml(health.status || '')}</p>
                    <p class="text-xs text-slate-500 mt-1">${escapeHtml(health.summary || '')}</p>
                </div>
            </div>
        `);

        // Monthly Momentum
        let momentumHtml = '';
        if (momentum.metrics && momentum.metrics.length) {
            momentumHtml = section('Impuls lunar', `
                <div class="analiza-kpi-grid">
                    ${momentum.metrics.map(m => {
                        const dir = m.direction === 'up' ? '↑' : m.direction === 'down' ? '↓' : '→';
                        const color = m.direction === 'up' ? '#10b981' : m.direction === 'down' ? '#ef4444' : '#94a3b8';
                        return `<div class="analiza-kpi">
                            <div class="analiza-kpi__value">${escapeHtml(m.value || '')} <span style="color:${color}; font-size:1rem;">${dir}</span></div>
                            <div class="analiza-kpi__label">${escapeHtml(m.label || '')}</div>
                        </div>`;
                    }).join('')}
                </div>
            `);
        }

        // Yearly chart
        const chartId = 'chart-overview-yearly';
        const yearlyHtml = section('Evoluție 12 luni', `<canvas id="${chartId}" height="80"></canvas>`);

        // Event Performance
        const perf = Array.isArray(d.event_performance) ? d.event_performance : [];
        const perfHtml = section('Performanță evenimente recente', renderTable(
            ['Eveniment', 'Data', 'Sold', 'Ocupare', 'Venit'],
            perf.slice(0, 15).map(e => [
                escapeHtml(titleOf(e)),
                escapeHtml(e.event_date || ''),
                fmtInt(e.tickets_sold),
                fmtPct(e.occupancy),
                fmtMoney(e.revenue) + ' RON',
            ]),
            'Nu există evenimente'
        ));

        // Competitor Benchmark
        const cb = d.competitor_benchmark || {};
        const cbHtml = section('Comparație oraș', `
            <p class="text-sm text-slate-600">Poziție ocupare: <strong>${escapeHtml(cb.position || '—')}</strong></p>
            <p class="text-sm text-slate-500 mt-2">${escapeHtml(cb.summary || '')}</p>
        `);

        root.innerHTML = kpiSection + healthSection + momentumHtml + yearlyHtml + perfHtml + cbHtml;

        // Init chart
        if (ys.months && ys.months.length) {
            const ctx = document.getElementById(chartId);
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ys.months,
                    datasets: [
                        { label: 'Bilete', data: ys.tickets, backgroundColor: 'rgba(59,130,246,0.7)', yAxisID: 'y1' },
                        { label: 'Venit (RON)', data: ys.revenue, type: 'line', borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.2)', yAxisID: 'y2', tension: 0.3 },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: {
                        y1: { position: 'left', beginAtZero: true, title: { display: true, text: 'Bilete' } },
                        y2: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false }, title: { display: true, text: 'RON' } },
                    },
                },
            });
        }
    }

    // ── Financial ───────────────────────────────────────────────
    function renderFinancial(d, root) {
        const rb = d.revenue_breakdown || {};
        const pricing = d.pricing_intelligence || {};
        const refunds = d.refund_analysis || {};
        const forecast = d.revenue_forecast || {};
        const perSeat = d.revenue_per_seat || {};

        let html = '';

        html += section('Distribuție venituri', renderTable(
            ['Categorie', 'Bilete', 'Venit', 'Pondere'],
            (rb.by_category || rb.categories || []).slice(0, 20).map(r => [
                escapeHtml(r.label || r.name || ''),
                fmtInt(r.tickets),
                fmtMoney(r.revenue) + ' RON',
                fmtPct(r.share || r.percentage),
            ]),
            'Fără date'
        ));

        html += section('Preț optim', `
            <p class="text-sm text-slate-600">Preț sweet-spot estimat: <strong>${fmtMoney(pricing.sweet_spot)} RON</strong></p>
            <p class="text-xs text-slate-500 mt-2">${escapeHtml(pricing.summary || '')}</p>
        `);

        html += section('Rambursări', `
            <div class="analiza-kpi-grid">
                <div class="analiza-kpi"><div class="analiza-kpi__value">${fmtPct(refunds.refund_rate)}</div><div class="analiza-kpi__label">Rată rambursare</div></div>
                <div class="analiza-kpi"><div class="analiza-kpi__value">${fmtInt(refunds.total_refunded)}</div><div class="analiza-kpi__label">Bilete rambursate</div></div>
                <div class="analiza-kpi"><div class="analiza-kpi__value">${fmtMoney(refunds.refunded_value)} RON</div><div class="analiza-kpi__label">Valoare rambursată</div></div>
            </div>
        `);

        html += section('Prognoză venituri (6 luni)', renderTable(
            ['Lună', 'Estimare (RON)'],
            (forecast.months || []).map((m, i) => [
                escapeHtml(m),
                fmtMoney((forecast.values || [])[i]) + ' RON',
            ]),
            'Fără prognoză'
        ));

        html += section('Venit / loc', `
            <p class="text-sm text-slate-600">Venit mediu pe loc: <strong>${fmtMoney(perSeat.avg_per_seat)} RON</strong></p>
            <p class="text-xs text-slate-500 mt-2">${escapeHtml(perSeat.summary || '')}</p>
        `);

        root.innerHTML = html;
    }

    // ── Audience ────────────────────────────────────────────────
    function renderAudience(d, root) {
        const p = d.audience_personas || {};
        const loyalty = d.customer_loyalty || {};
        const geo = d.geographic_origin || [];
        const genreLoyalty = d.genre_loyalty || [];
        const checkin = d.checkin_analysis || {};

        let html = '';

        html += section('Loialitate cumpărători', `
            <div class="analiza-kpi-grid">
                <div class="analiza-kpi"><div class="analiza-kpi__value">${fmtInt(loyalty.one_time)}</div><div class="analiza-kpi__label">Prima cumpărare</div></div>
                <div class="analiza-kpi"><div class="analiza-kpi__value">${fmtInt(loyalty.repeat)}</div><div class="analiza-kpi__label">Cumpărători repeat</div></div>
                <div class="analiza-kpi"><div class="analiza-kpi__value">${fmtInt(loyalty.regulars)}</div><div class="analiza-kpi__label">Regulari</div></div>
                <div class="analiza-kpi"><div class="analiza-kpi__value">${fmtInt(loyalty.superfans)}</div><div class="analiza-kpi__label">Superfani</div></div>
            </div>
        `);

        if (Array.isArray(p.age_buckets) && p.age_buckets.length) {
            html += section('Distribuție vârste', renderTable(
                ['Grupă', 'Cumpărători', 'Pondere'],
                p.age_buckets.map(b => [escapeHtml(b.label), fmtInt(b.count), fmtPct(b.share)]),
                'Fără date'
            ));
        }

        html += section('Origine geografică', renderTable(
            ['Oraș', 'Cumpărători', 'Pondere'],
            (geo.cities || geo).slice(0, 15).map(g => [
                escapeHtml(g.city || g.label || ''),
                fmtInt(g.buyers || g.count),
                fmtPct(g.share || g.percentage),
            ]),
            'Fără date geografice'
        ));

        if (Array.isArray(loyalty.superfan_details) && loyalty.superfan_details.length) {
            html += section('Top superfani', renderTable(
                ['Nume', 'Email', 'Evenimente', 'Cheltuit'],
                loyalty.superfan_details.slice(0, 15).map(s => [
                    escapeHtml(s.name || '—'),
                    escapeHtml(s.email || ''),
                    fmtInt(s.events),
                    fmtMoney(s.total_spent) + ' RON',
                ]),
                'Nici un superfan detectat'
            ));
        }

        if (Array.isArray(genreLoyalty) && genreLoyalty.length) {
            html += section('Loialitate pe gen muzical', renderTable(
                ['Gen', 'Cumpărători recurenți', 'Pondere'],
                genreLoyalty.slice(0, 15).map(g => [
                    escapeHtml(g.genre || ''),
                    fmtInt(g.repeat_buyers),
                    fmtPct(g.retention_rate),
                ]),
                ''
            ));
        }

        if (checkin.avg_arrival_time) {
            html += section('Timp mediu de sosire', `
                <p class="text-sm text-slate-600">Cumpărătorii sosesc în medie: <strong>${escapeHtml(checkin.avg_arrival_time)}</strong> înainte de eveniment.</p>
                <p class="text-xs text-slate-500 mt-2">${escapeHtml(checkin.summary || '')}</p>
            `);
        }

        root.innerHTML = html;
    }

    // ── Artists ─────────────────────────────────────────────────
    function renderArtists(d, root) {
        let html = '';

        html += section('Artiști la locație', renderTable(
            ['Artist', 'Concerte', 'Bilete vândute', 'Venit'],
            (d.artist_performance || []).slice(0, 20).map(a => [
                escapeHtml(a.name || a.artist_name || ''),
                fmtInt(a.events),
                fmtInt(a.tickets_sold),
                fmtMoney(a.revenue) + ' RON',
            ]),
            'Fără date'
        ));

        html += section('Performanță pe gen', renderTable(
            ['Gen', 'Evenimente', 'Ocupare medie', 'Venit mediu'],
            (d.genre_performance || []).slice(0, 15).map(g => [
                escapeHtml(g.genre || ''),
                fmtInt(g.events),
                fmtPct(g.avg_occupancy),
                fmtMoney(g.avg_revenue) + ' RON',
            ]),
            'Fără date'
        ));

        html += section('Artiști recomandați pentru bookings', renderTable(
            ['Artist', 'Motivare'],
            (d.never_played_artists || []).slice(0, 15).map(a => [
                escapeHtml(a.name || a.artist_name || ''),
                escapeHtml(a.reason || a.rationale || ''),
            ]),
            'Nici o recomandare disponibilă'
        ));

        root.innerHTML = html;
    }

    // ── Scheduling ──────────────────────────────────────────────
    function renderScheduling(d, root) {
        const heat = d.scheduling_heatmap || {};
        const dow = d.day_of_week || {};
        const seas = d.seasonality || {};
        const idle = d.idle_days || {};
        const sales = d.sales_intelligence || {};

        let html = '';

        if (Array.isArray(dow.days) && dow.days.length) {
            html += section('Performanță pe zi a săptămânii', renderTable(
                ['Zi', 'Evenimente', 'Ocupare medie'],
                dow.days.map(day => [escapeHtml(day.name), fmtInt(day.events), fmtPct(day.occupancy)]),
                ''
            ));
        }

        if (Array.isArray(seas.months) && seas.months.length) {
            html += section('Sezonalitate', renderTable(
                ['Lună', 'Evenimente', 'Bilete'],
                seas.months.map(m => [escapeHtml(m.month), fmtInt(m.events), fmtInt(m.tickets)]),
                ''
            ));
        }

        html += section('Zile libere / potențial pierdut', `
            <p class="text-sm text-slate-600">Zile libere pe weekend: <strong>${fmtInt(idle.idle_weekend_days)}</strong></p>
            <p class="text-sm text-slate-600 mt-1">Venit estimat pierdut: <strong>${fmtMoney(idle.estimated_lost_revenue)} RON</strong></p>
            <p class="text-xs text-slate-500 mt-3">${escapeHtml(idle.summary || '')}</p>
        `);

        if (sales.velocity) {
            html += section('Viteză vânzări', renderTable(
                ['Eveniment', 'Bilete / zi', 'Zile până la eveniment'],
                (sales.velocity || []).slice(0, 10).map(v => [
                    escapeHtml(titleOf(v)),
                    fmtInt(v.velocity),
                    fmtInt(v.days_until),
                ]),
                ''
            ));
        }

        root.innerHTML = html;
    }

    // ── Opportunities ───────────────────────────────────────────
    function renderOpportunities(d, root) {
        let html = '';

        const opps = d.opportunities || {};
        html += section('Oportunități', `
            <div class="space-y-3">
                ${(opps.findings || opps || []).slice(0, 12).map(o => `
                    <div class="p-3 border rounded-lg border-slate-100 bg-slate-50/40">
                        <p class="text-sm font-semibold text-slate-900">${escapeHtml(o.title || o.name || '')}</p>
                        <p class="text-xs text-slate-500 mt-1">${escapeHtml(o.description || o.detail || '')}</p>
                    </div>
                `).join('') || '<p class="text-sm text-slate-500">Nici o oportunitate detectată încă.</p>'}
            </div>
        `);

        html += section('Alerte churn', renderTable(
            ['Cumpărător', 'Ultima achiziție', 'Zile de la ultimul eveniment', 'Risc'],
            (d.churn_alerts || []).slice(0, 15).map(c => [
                escapeHtml(c.name || c.email || ''),
                escapeHtml(c.last_purchase || ''),
                fmtInt(c.days_since),
                `<span class="analiza-tag analiza-tag--${(c.risk || 'warn')}">${escapeHtml(c.risk_label || c.risk || '')}</span>`,
            ]),
            'Fără alerte de risc'
        ));

        // Event simulator
        html += section('Simulator eveniment', `
            <p class="text-sm text-slate-500 mb-4">Verifică ce numere ai avea pentru un eveniment ipotetic la locația ta.</p>
            <div class="grid gap-3 md:grid-cols-3">
                <input id="sim-genre" placeholder="Gen (ex: rock)" class="px-3 py-2 text-sm border rounded-lg border-slate-200" />
                <input id="sim-dow" placeholder="Zi (ex: Vineri)" class="px-3 py-2 text-sm border rounded-lg border-slate-200" />
                <input id="sim-price" type="number" placeholder="Preț bilet (RON)" class="px-3 py-2 text-sm border rounded-lg border-slate-200" />
            </div>
            <button id="sim-run" class="px-4 py-2 mt-3 text-sm font-semibold text-white rounded-lg" style="background:#3b82f6;">Simulează</button>
            <div id="sim-result" class="mt-4"></div>
        `);

        root.innerHTML = html;

        // Wire simulator
        const runBtn = document.getElementById('sim-run');
        if (runBtn) {
            runBtn.addEventListener('click', async () => {
                const genre = document.getElementById('sim-genre').value.trim();
                const dow   = document.getElementById('sim-dow').value.trim();
                const price = parseFloat(document.getElementById('sim-price').value);
                if (!genre || !dow || !price) {
                    document.getElementById('sim-result').innerHTML = '<p class="text-sm text-red-500">Completează toate câmpurile.</p>';
                    return;
                }
                document.getElementById('sim-result').innerHTML = '<p class="text-sm text-slate-500">Se simulează…</p>';
                try {
                    const res = await AmbiletVenueAPI.simulate(genre, dow, price);
                    if (res && res.success && res.data) {
                        const r = res.data;
                        document.getElementById('sim-result').innerHTML = `
                            <div class="analiza-kpi-grid mt-2">
                                <div class="analiza-kpi"><div class="analiza-kpi__value">${fmtInt(r.estimated_tickets)}</div><div class="analiza-kpi__label">Bilete estimate</div></div>
                                <div class="analiza-kpi"><div class="analiza-kpi__value">${fmtMoney(r.estimated_revenue)} RON</div><div class="analiza-kpi__label">Venit estimat</div></div>
                                <div class="analiza-kpi"><div class="analiza-kpi__value">${fmtPct(r.estimated_occupancy)}</div><div class="analiza-kpi__label">Ocupare estimată</div></div>
                            </div>
                            <p class="mt-3 text-xs text-slate-500">${escapeHtml(r.summary || '')}</p>
                        `;
                    } else {
                        document.getElementById('sim-result').innerHTML = '<p class="text-sm text-red-500">Simulare eșuată.</p>';
                    }
                } catch (e) {
                    console.error(e);
                    document.getElementById('sim-result').innerHTML = '<p class="text-sm text-red-500">Eroare.</p>';
                }
            });
        }
    }

    // ── Promotion ───────────────────────────────────────────────
    function renderPromotion(d, root) {
        const pp = d.promotion_planner || {};
        let html = '';

        html += section('Fereastră optimă de anunț', `
            <p class="text-sm text-slate-600">Cel mai bun moment pentru anunț: <strong>${escapeHtml(pp.optimal_window || '—')}</strong></p>
            <p class="text-xs text-slate-500 mt-2">${escapeHtml(pp.window_summary || '')}</p>
        `);

        if (pp.recommended_budget) {
            html += section('Buget recomandat reclame', `
                <p class="text-sm text-slate-600">Buget total sugerat: <strong>${fmtMoney(pp.recommended_budget)} RON</strong></p>
                <p class="text-xs text-slate-500 mt-2">${escapeHtml(pp.budget_summary || '')}</p>
            `);
        }

        if (Array.isArray(pp.budget_split) && pp.budget_split.length) {
            html += section('Distribuție pe platforme', renderTable(
                ['Platformă', 'Buget (RON)', 'Pondere'],
                pp.budget_split.map(b => [escapeHtml(b.platform), fmtMoney(b.amount) + ' RON', fmtPct(b.share)]),
                ''
            ));
        }

        root.innerHTML = html;
    }

    // ── Upcoming ────────────────────────────────────────────────
    function renderUpcoming(d, root) {
        const upcoming = d.upcoming || [];
        root.innerHTML = section('Evenimente ce urmează', renderTable(
            ['Data', 'Eveniment', 'Locație', 'Vândute'],
            upcoming.slice(0, 30).map(e => [
                escapeHtml(e.event_date || ''),
                escapeHtml(titleOf(e)),
                escapeHtml(e.venue_name || ''),
                `${fmtInt(e.tickets_sold)}/${fmtInt(e.capacity)}`,
            ]),
            'Niciun eveniment programat'
        ));
    }

    // ── Actions ─────────────────────────────────────────────────
    function renderActions(d, root) {
        const priorities = d.action_priority || {};
        const items = priorities.actions || priorities || [];
        root.innerHTML = section('Priorități acțiune', `
            <div class="space-y-3">
                ${(Array.isArray(items) ? items : []).slice(0, 12).map(a => {
                    const priorityClass = a.priority === 'high' ? 'danger' : a.priority === 'medium' ? 'warn' : 'info';
                    return `
                        <div class="p-4 border rounded-lg border-slate-100">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-slate-900">${escapeHtml(a.title || a.action || '')}</p>
                                    <p class="text-xs text-slate-500 mt-1">${escapeHtml(a.description || '')}</p>
                                </div>
                                <span class="analiza-tag analiza-tag--${priorityClass}">${escapeHtml(a.priority_label || a.priority || '')}</span>
                            </div>
                        </div>
                    `;
                }).join('') || '<p class="text-sm text-slate-500">Nici o acțiune prioritară detectată.</p>'}
            </div>
        `);
    }

    // ── Section + table helpers ─────────────────────────────────
    function section(title, body) {
        return `<div class="analiza-section"><h3>${title}</h3>${body}</div>`;
    }

    function renderTable(headers, rows, emptyMsg) {
        if (!rows || !rows.length) {
            return `<p class="text-sm text-slate-500 py-4 text-center">${emptyMsg || 'Fără date'}</p>`;
        }
        return `
            <div class="overflow-x-auto -mx-2">
                <table class="analiza-table">
                    <thead><tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr></thead>
                    <tbody>${rows.map(r => `<tr>${r.map(c => `<td>${c ?? ''}</td>`).join('')}</tr>`).join('')}</tbody>
                </table>
            </div>
        `;
    }

    // ── Boot ────────────────────────────────────────────────────
    document.querySelectorAll('.analiza-tab-btn').forEach(b => {
        b.addEventListener('click', () => switchTab(b.dataset.tab));
    });

    await initVenuePicker();
    switchTab('overview');
})();
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
