<?php
/**
 * Venue Owner — /venue/analiza
 *
 * Full 1:1 port of the tenant Filament page
 * (resources/views/filament/tenant/pages/venue-analytics.blade.php).
 * Single fetch to /venue-owner/analytics/all returns every key the
 * blade reads; the 9 render functions below mirror the section order
 * and content of the source page.
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

            <!-- Tab panels (header sits inside Overview only) -->
            <?php foreach ($tabs as $t): ?>
                <div id="tab-<?= $t['id'] ?>" class="analiza-tab-panel hidden space-y-4"></div>
            <?php endforeach; ?>

            <div id="analiza-loading" class="p-12 text-center bg-white border rounded-2xl border-slate-200">
                <div class="inline-block w-8 h-8 border-4 rounded-full animate-spin border-blue-200 border-t-blue-500"></div>
                <p class="mt-3 text-sm text-slate-500">Se încarcă…</p>
            </div>
        </main>
    </div>

<style>
    :root {
        --v-warn: #d97706;
        --v-danger: #dc2626;
        --v-success: #059669;
        --v-primary: #3b82f6;
        --v-accent: #06b6d4;
        --v-muted: #64748b;
        --v-text: #0f172a;
        --v-ring: #e2e8f0;
    }
    .analiza-tab-btn.active { color: var(--v-primary); border-color: var(--v-primary); }
    .analiza-tab-btn:hover:not(.active) { color: #1e293b; }
    .a-card { background:#fff; border:1px solid var(--v-ring); border-radius:.75rem; padding:1rem; }
    .a-card-h { font-size:.875rem; font-weight:700; color: var(--v-text); margin-bottom:.75rem; display:flex; align-items:center; }
    .a-g2 { display:grid; gap:1rem; grid-template-columns: 1fr; }
    @media (min-width: 1024px) { .a-g2 { grid-template-columns: 1fr 1fr; } }
    .a-g3 { display:grid; gap:.75rem; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
    .a-tbl { width:100%; font-size:.8125rem; border-collapse:collapse; }
    .a-tbl th { padding:.4rem .5rem; text-align:left; font-size:.7rem; font-weight:600; text-transform:uppercase; color:var(--v-muted); background:#f8fafc; }
    .a-tbl td { padding:.4rem .5rem; border-top:1px solid #f1f5f9; color: var(--v-text); }
    .a-tbl tr:hover td { background:#f8fafc; }
    .a-progress { background: #f1f5f9; border-radius:9999px; height:.5rem; overflow:hidden; }
    .a-progress-fill { height:100%; border-radius:9999px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => (async function () {
    if (typeof AmbiletVenueAPI === 'undefined') return;

    // ── Helpers ─────────────────────────────────────────────────
    const fmtInt = n => Number(n || 0).toLocaleString('ro-RO');
    const fmtMoney = n => Number(n || 0).toLocaleString('ro-RO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    const escapeHtml = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
    const truncate = (s, n) => { const str = String(s ?? ''); return str.length > n ? str.slice(0, n - 1) + '…' : str; };
    const stColor = pct => pct >= 75 ? 'var(--v-success)' : pct >= 50 ? 'var(--v-warn)' : 'var(--v-danger)';
    const fmtDate = d => {
        if (!d) return '—';
        try { return new Date(d).toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: '2-digit' }); }
        catch (e) { return d; }
    };
    // Normalize a city name — strip diacritics, trim, lowercase — so
    // "Constanta", "CONSTANTA" and "Constanța" all collapse into the
    // same bucket in the Geographic Origin table. Uses NFD decomposition
    // to remove combining marks (works for all Romanian diacritics).
    const normalizeCity = s => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').trim().toLowerCase();
    // Prettify the collapsed city bucket key for display (Title Case).
    const prettyCity = key => key.split(/\s+/).map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
    // ── Translation dicts (blade labels are in English) ─────────
    const T_DAY = {
        'Monday': 'Luni', 'Tuesday': 'Marți', 'Wednesday': 'Miercuri',
        'Thursday': 'Joi', 'Friday': 'Vineri', 'Saturday': 'Sâmbătă', 'Sunday': 'Duminică',
        'Mon': 'Luni', 'Tue': 'Mar', 'Wed': 'Mie', 'Thu': 'Joi', 'Fri': 'Vin', 'Sat': 'Sâm', 'Sun': 'Dum',
    };
    const T_MONTH = {
        'Jan': 'Ian', 'Feb': 'Feb', 'Mar': 'Mar', 'Apr': 'Apr', 'May': 'Mai', 'Jun': 'Iun',
        'Jul': 'Iul', 'Aug': 'Aug', 'Sep': 'Sep', 'Oct': 'Oct', 'Nov': 'Noi', 'Dec': 'Dec',
        'January': 'Ianuarie', 'February': 'Februarie', 'March': 'Martie', 'April': 'Aprilie',
        'June': 'Iunie', 'July': 'Iulie', 'August': 'August', 'September': 'Septembrie',
        'October': 'Octombrie', 'November': 'Noiembrie', 'December': 'Decembrie',
    };
    const T_GENDER = { 'male': 'Masculin', 'female': 'Feminin', 'other': 'Altul' };
    const T_HEALTH_LABEL = {
        'Excellent': 'Excelent', 'Good': 'Bun', 'Average': 'Mediu',
        'Below Average': 'Sub medie', 'Critical': 'Critic', 'No Data': 'Fără date',
    };
    const T_HEALTH_COMP = { 'Occupancy': 'Ocupare', 'Revenue Growth': 'Creștere venit', 'Customer Loyalty': 'Loialitate', 'Activity': 'Activitate' };
    const T_MOMENTUM = { 'Events': 'Evenimente', 'Tickets Sold': 'Bilete vândute', 'Revenue': 'Venit', 'Avg Occupancy': 'Ocupare medie' };
    const T_DAYTYPE = { 'Weekend': 'Weekend', 'Weekday': 'Zi lucrătoare', 'Fri': 'Vineri', 'Sat': 'Sâmbătă', 'Sun': 'Duminică' };
    // Translate a token by looking it up in a dict; fall back to the
    // original text so English strings we haven't mapped still render.
    const tr = (dict, key) => (dict[key] !== undefined ? dict[key] : key);
    // "May 25" (Carbon 'M y' format) → "Mai 25". Regex handles both
    // 3-letter month prefixes and full month names.
    const translateMonthLabel = (label) => {
        if (!label) return label;
        const parts = String(label).split(' ');
        parts[0] = tr(T_MONTH, parts[0]);
        return parts.join(' ');
    };

    // ── State ───────────────────────────────────────────────────
    const state = {
        venueId: 'all',
        data: null,     // full analytics payload
        charts: {},     // { yearlyEv, yearlyRev }
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
                const urlVenueId = new URLSearchParams(window.location.search).get('venue_id');
                if (urlVenueId) { sel.value = urlVenueId; state.venueId = urlVenueId; }
                sel.addEventListener('change', async () => {
                    state.venueId = sel.value;
                    await loadAll();
                });
            }
        } catch (e) { console.error(e); }
    }

    // ── Load ────────────────────────────────────────────────────
    async function loadAll() {
        const loading = document.getElementById('analiza-loading');
        loading.classList.remove('hidden');
        document.querySelectorAll('.analiza-tab-panel').forEach(p => p.classList.add('hidden'));

        try {
            const venueId = state.venueId !== 'all' ? state.venueId : null;
            const res = await AmbiletVenueAPI.analyticsAll(venueId);
            if (res && res.success && res.data) {
                state.data = res.data;
                renderAllTabs();
                loading.classList.add('hidden');
                switchTab('overview');
            } else {
                loading.innerHTML = '<p class="text-sm text-slate-500">Nu există date pentru această locație.</p>';
            }
        } catch (e) {
            console.error('analytics load failed', e);
            loading.innerHTML = '<p class="text-sm text-red-500">Eroare la încărcarea datelor.</p>';
        }
    }

    function switchTab(id) {
        document.querySelectorAll('.analiza-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === id));
        document.querySelectorAll('.analiza-tab-panel').forEach(p => p.classList.toggle('hidden', p.id !== 'tab-' + id));
        if (id === 'overview') renderOverviewCharts();
    }

    // ── Render all tabs (skeleton, charts drawn lazily) ─────────
    function renderAllTabs() {
        document.getElementById('tab-overview').innerHTML     = renderOverview();
        document.getElementById('tab-financial').innerHTML    = renderFinancial();
        document.getElementById('tab-audience').innerHTML     = renderAudience();
        document.getElementById('tab-artists').innerHTML      = renderArtists();
        document.getElementById('tab-scheduling').innerHTML   = renderScheduling();
        document.getElementById('tab-opportunities').innerHTML = renderOpportunities();
        document.getElementById('tab-promotion').innerHTML    = renderPromotion();
        document.getElementById('tab-upcoming').innerHTML     = renderUpcoming();
        document.getElementById('tab-actions').innerHTML      = renderActions();
        wireInteractive();
    }

    // ── OVERVIEW ────────────────────────────────────────────────
    function renderOverview() {
        const d = state.data;
        const k = d.kpis || {};
        const h = d.venueHealthScore || {};
        const m = d.monthlyMomentum || {};
        const loyalty = d.customerLoyalty || {};
        let html = '';

        // KPI strip. Backend returns total_events/total_tickets/total_revenue/
        // avg_occupancy/avg_ticket_price. "Cumpărători unici" is derived from
        // customerLoyalty.total (buildVenueCustomerLoyalty counts distinct
        // paying customers) — the KPI builder itself doesn't expose it.
        const uniqueBuyers = loyalty.total || 0;
        const kpiCards = [
            { label: 'Evenimente',        value: fmtInt(k.total_events), color: 'var(--v-primary)' },
            { label: 'Bilete vândute',    value: fmtInt(k.total_tickets), color: 'var(--v-accent)' },
            { label: 'Venit total',       value: fmtMoney(k.total_revenue) + ' RON', color: 'var(--v-warn)' },
            { label: 'Cumpărători unici', value: fmtInt(uniqueBuyers), color: 'var(--v-primary)' },
            { label: 'Ocupare medie',     value: (Number(k.avg_occupancy || 0)).toFixed(1) + '%', color: stColor(k.avg_occupancy || 0) },
            { label: 'Preț mediu',        value: fmtMoney(k.avg_ticket_price) + ' RON', color: 'var(--v-warn)' },
        ];
        html += `<div class="a-card"><div class="a-card-h">Indicatori cheie</div>
            <div class="a-g3">${kpiCards.map(c => `
                <div style="text-align:center;padding:.75rem;background:#f8fafc;border-radius:.5rem;">
                    <div style="font-size:1.5rem;font-weight:700;color:${c.color}">${c.value}</div>
                    <div style="font-size:.7rem;color:var(--v-muted);text-transform:uppercase;margin-top:.25rem;">${c.label}</div>
                </div>
            `).join('')}</div>
        </div>`;

        // Health Score. components is an array [{name, score, max, detail}, ...],
        // NOT a plain object — the previous renderer stringified {} → "[object
        // Object]". Iterate and show each component's translated name + score/max
        // + detail row (blade's "detail" text is what actually explains the
        // computation).
        if (h.score !== undefined) {
            const healthColor = h.color || ((h.score || 0) >= 75 ? 'var(--v-success)' : (h.score || 0) >= 50 ? 'var(--v-warn)' : 'var(--v-danger)');
            const components = Array.isArray(h.components) ? h.components : [];
            html += `<div class="a-card"><div class="a-card-h">Scor sănătate locație</div>
                <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
                    <div style="position:relative;width:6rem;height:6rem;flex-shrink:0;">
                        <svg viewBox="0 0 36 36" style="width:100%;height:100%;">
                            <path d="M18 2 a 16 16 0 1 1 0 32 a 16 16 0 1 1 0 -32" fill="none" stroke="#e2e8f0" stroke-width="3"/>
                            <path d="M18 2 a 16 16 0 1 1 0 32 a 16 16 0 1 1 0 -32" fill="none" stroke="${healthColor}" stroke-width="3" stroke-dasharray="${h.score || 0}, 100"/>
                        </svg>
                        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:700;color:${healthColor}">${Math.round(h.score || 0)}</div>
                    </div>
                    <div style="flex:1;min-width:220px;">
                        <p style="font-size:.875rem;font-weight:700;color:${healthColor}">${escapeHtml(tr(T_HEALTH_LABEL, h.label || ''))}</p>
                        <div style="margin-top:.5rem;font-size:.75rem;color:var(--v-muted);display:flex;gap:.75rem;flex-wrap:wrap;">
                            ${components.map(c => `<div><strong style="color:var(--v-text);">${escapeHtml(tr(T_HEALTH_COMP, c.name || ''))}</strong>: ${c.score}/${c.max} <span style="color:var(--v-muted);">— ${escapeHtml(c.detail || '')}</span></div>`).join('')}
                        </div>
                    </div>
                </div>
            </div>`;
        }

        // Monthly Momentum. Backend returns { current_label, previous_label,
        // metrics: [{name, current, previous, trend: {direction, pct}, format}] }.
        // The old renderer read events_delta / tickets_current etc. which never
        // existed → empty cards.
        if (m && Array.isArray(m.metrics) && m.metrics.length) {
            html += `<div class="a-card">
                <div class="a-card-h">Impuls lunar <span style="font-size:.7rem;font-weight:400;color:var(--v-muted);margin-left:.5rem;">${escapeHtml(m.current_label || '')} vs ${escapeHtml(m.previous_label || '')}</span></div>
                <div class="a-g3">${m.metrics.map(x => {
                    const dir = (x.trend && x.trend.direction) || 'flat';
                    const arrow = dir === 'up' ? '↑' : dir === 'down' ? '↓' : '→';
                    const color = dir === 'up' ? 'var(--v-success)' : dir === 'down' ? 'var(--v-danger)' : 'var(--v-muted)';
                    let curr = x.current;
                    if (x.format === 'currency') curr = fmtMoney(curr) + ' RON';
                    else if (x.format === 'pct') curr = curr + '%';
                    else curr = fmtInt(curr);
                    const pct = x.trend && x.trend.pct !== undefined ? x.trend.pct : null;
                    return `<div style="text-align:center;padding:.75rem;background:#f8fafc;border-radius:.5rem;">
                        <div style="font-size:1.25rem;font-weight:700;color:var(--v-text);">${curr}</div>
                        <div style="font-size:.75rem;color:${color};margin-top:.25rem;font-weight:600;">${arrow} ${pct !== null ? (pct >= 0 ? '+' : '') + pct + '%' : ''}</div>
                        <div style="font-size:.7rem;color:var(--v-muted);margin-top:.25rem;">${escapeHtml(tr(T_MOMENTUM, x.name || ''))}</div>
                    </div>`;
                }).join('')}</div>
            </div>`;
        }

        // Charts
        if (d.months && d.months.length) {
            html += `
                <div class="a-g2">
                    <div class="a-card"><div class="a-card-h">Evenimente & Bilete / lună</div>
                        <div style="height:220px;position:relative;"><canvas id="chart-yearly-evtx"></canvas></div>
                    </div>
                    <div class="a-card"><div class="a-card-h">Venit & Ocupare / lună</div>
                        <div style="height:220px;position:relative;"><canvas id="chart-yearly-revocc"></canvas></div>
                    </div>
                </div>
            `;
        }

        // Event Performance
        const evPerf = d.eventPerformance || [];
        if (evPerf.length) {
            html += `
                <div class="a-card">
                    <div class="a-card-h">Performanță evenimente (${evPerf.length})</div>
                    <div style="overflow-x:auto;">
                        <table class="a-tbl">
                            <thead><tr>
                                <th>Data</th><th>Eveniment</th><th>Artiști</th>
                                <th style="text-align:right">Sold</th><th style="text-align:right">Cap.</th>
                                <th style="text-align:right">ST</th><th style="text-align:right">Venit</th><th style="text-align:right">Check-in</th>
                            </tr></thead>
                            <tbody>
                                ${evPerf.slice(0, 25).map(ev => `
                                    <tr style="${ev.is_past ? 'opacity:.65' : ''}">
                                        <td style="white-space:nowrap;">${fmtDate(ev.date)}</td>
                                        <td style="font-weight:600;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(truncate(ev.title, 40))}</td>
                                        <td style="color:var(--v-muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escapeHtml(truncate(ev.artists || '', 35))}</td>
                                        <td style="text-align:right;font-family:monospace;">${fmtInt(ev.sold)}</td>
                                        <td style="text-align:right;color:var(--v-muted);font-family:monospace;">${ev.capacity || '—'}</td>
                                        <td style="text-align:right;font-weight:700;color:${stColor(ev.sell_through || 0)}">${ev.sell_through !== null ? ev.sell_through + '%' : '—'}</td>
                                        <td style="text-align:right;font-family:monospace;color:var(--v-warn);">${fmtMoney(ev.revenue)}</td>
                                        <td style="text-align:right;color:var(--v-muted);">${ev.checkin_rate !== null ? ev.checkin_rate + '%' : '—'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        // Weekend vs Weekday + YoY
        const rb = d.revenueBreakdown || {};
        const dayType = rb.revenue_by_day_type || [];
        const yoy = rb.yoy || {};
        if (dayType.length || (yoy.last_12 || 0) > 0) {
            html += `<div class="a-g2">
                ${dayType.length ? `
                    <div class="a-card"><div class="a-card-h">Weekend vs zi lucrătoare</div>
                        ${dayType.map(dt => `
                            <div style="display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px dashed var(--v-ring);">
                                <span style="font-weight:600;">${escapeHtml(tr(T_DAYTYPE, dt.day_type || ''))}</span>
                                <span style="color:var(--v-muted);font-size:.8125rem;">${fmtInt(dt.events)} ev. · ocupare medie ${dt.avg_st}% · venit mediu ${fmtMoney(dt.avg_revenue)} RON</span>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}
                ${(yoy.last_12 || 0) > 0 ? `
                    <div class="a-card"><div class="a-card-h">An vs an</div>
                        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
                            <div><div style="color:var(--v-muted);font-size:.7rem;">Ultimele 12 luni</div><div style="font-size:1.375rem;font-weight:700;color:var(--v-warn);">${fmtMoney(yoy.last_12)} RON</div></div>
                            <div><div style="color:var(--v-muted);font-size:.7rem;">Anterioarele 12</div><div style="font-size:1.375rem;font-weight:700;color:var(--v-muted);">${fmtMoney(yoy.prev_12)} RON</div></div>
                            ${yoy.change_pct !== null ? `<div style="font-size:1.125rem;font-weight:700;color:${yoy.change_pct >= 0 ? 'var(--v-success)' : 'var(--v-danger)'}">${yoy.change_pct >= 0 ? '+' : ''}${yoy.change_pct}%</div>` : ''}
                        </div>
                    </div>
                ` : ''}
            </div>`;
        }

        // Competitor benchmark + Revenue per Seat
        const bench = d.competitorBenchmark || {};
        const rps = d.revenuePerSeat || {};
        if ((bench.city_avg && bench.my) || (rps.avg_rev_per_seat || 0) > 0) {
            html += `<div class="a-g2">
                ${bench.city_avg ? `
                    <div class="a-card"><div class="a-card-h">Comparație oraș</div>
                        <div style="display:flex;gap:1rem;margin-bottom:.75rem;flex-wrap:wrap;">
                            <div style="text-align:center;"><div style="color:var(--v-muted);font-size:.7rem;">Al tău</div><div style="font-size:1.5rem;font-weight:700;color:var(--v-accent);">${bench.my.avg_st}%</div></div>
                            <div style="text-align:center;"><div style="color:var(--v-muted);font-size:.7rem;">Oraș</div><div style="font-size:1.5rem;font-weight:700;color:var(--v-muted);">${bench.city_avg.avg_st}%</div></div>
                            ${bench.vs_city !== null ? `<div style="text-align:center;"><div style="color:var(--v-muted);font-size:.7rem;">Diferență</div><div style="font-size:1.5rem;font-weight:700;color:${bench.vs_city >= 0 ? 'var(--v-success)' : 'var(--v-danger)'}">${bench.vs_city >= 0 ? '+' : ''}${bench.vs_city}%</div></div>` : ''}
                        </div>
                        ${bench.competitors && bench.competitors.length ? `
                            <div style="font-size:.75rem;font-weight:600;color:var(--v-muted);margin-bottom:.25rem;">Alte locații în oraș</div>
                            <table class="a-tbl" style="margin-top:.25rem;"><thead><tr><th>Locație</th><th style="text-align:right">Capacitate</th><th style="text-align:right">Ev.</th><th style="text-align:right">Ocupare medie</th><th style="text-align:right">Preț mediu</th></tr></thead>
                            <tbody>${bench.competitors.slice(0, 5).map(c => `
                                <tr>
                                    <td style="font-weight:600;">${escapeHtml(c.name)}</td>
                                    <td style="text-align:right;color:var(--v-muted);">${fmtInt(c.capacity)}</td>
                                    <td style="text-align:right;">${c.events}</td>
                                    <td style="text-align:right;font-weight:600;color:${c.avg_st > bench.my.avg_st ? 'var(--v-danger)' : 'var(--v-success)'};">${c.avg_st}%</td>
                                    <td style="text-align:right;color:var(--v-warn);font-family:monospace;">${c.avg_price} RON</td>
                                </tr>
                            `).join('')}</tbody></table>
                        ` : ''}
                    </div>
                ` : ''}
                ${(rps.avg_rev_per_seat || 0) > 0 ? `
                    <div class="a-card"><div class="a-card-h">Venit / loc</div>
                        <div style="display:flex;gap:1rem;margin-bottom:.75rem;flex-wrap:wrap;">
                            <div><div style="color:var(--v-muted);font-size:.7rem;">Mediu / loc / eveniment</div><div style="font-size:1.5rem;font-weight:700;color:var(--v-warn);">${fmtMoney(rps.avg_rev_per_seat)} RON</div></div>
                            <div><div style="color:var(--v-muted);font-size:.7rem;">Capacitate</div><div style="font-size:1.5rem;font-weight:700;">${fmtInt(rps.capacity)}</div></div>
                        </div>
                        ${rps.best_event ? `<div style="padding:.5rem .75rem;background:rgba(5,150,105,.06);border:1px solid rgba(5,150,105,.15);border-radius:.5rem;font-size:.75rem;">Cel mai bun: <strong>${escapeHtml(rps.best_event.title)}</strong> — ${fmtMoney(rps.best_event.rev_per_seat)} RON/loc (${fmtMoney(rps.best_event.revenue)} total)</div>` : ''}
                    </div>
                ` : ''}
            </div>`;
        }

        // Event Comparison Tool
        const evPerf = d.eventPerformance || [];
        if (evPerf.length >= 2) {
            html += `<div class="a-card"><div class="a-card-h">Comparație evenimente</div>
                <p style="font-size:.75rem;color:var(--v-muted);margin-bottom:.75rem;">Selectează 2 evenimente pentru comparație.</p>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.75rem;">
                    <div style="flex:1;min-width:200px;">
                        <label style="font-size:.7rem;color:var(--v-muted);display:block;margin-bottom:.25rem;">Eveniment A</label>
                        <select id="cmp-a" style="width:100%;padding:.4rem;border:1px solid var(--v-ring);border-radius:.375rem;font-size:.8125rem;">
                            <option value="">Selectează…</option>
                            ${evPerf.slice(0, 30).map(e => `<option value="${e.id}">${escapeHtml(truncate(e.title, 40))} (${fmtDate(e.date)})</option>`).join('')}
                        </select>
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <label style="font-size:.7rem;color:var(--v-muted);display:block;margin-bottom:.25rem;">Eveniment B</label>
                        <select id="cmp-b" style="width:100%;padding:.4rem;border:1px solid var(--v-ring);border-radius:.375rem;font-size:.8125rem;">
                            <option value="">Selectează…</option>
                            ${evPerf.slice(0, 30).map(e => `<option value="${e.id}">${escapeHtml(truncate(e.title, 40))} (${fmtDate(e.date)})</option>`).join('')}
                        </select>
                    </div>
                    <button id="cmp-run" style="align-self:flex-end;padding:.4rem 1rem;background:var(--v-primary);color:#fff;border:none;border-radius:.375rem;font-size:.8125rem;font-weight:600;cursor:pointer;">Compară</button>
                </div>
                <div id="cmp-result"></div>
            </div>`;
        }

        return html || '<div class="a-card"><p class="text-sm text-slate-500">Nu există date suficiente pentru vederea generală.</p></div>';
    }

    function renderOverviewCharts() {
        const d = state.data;
        if (!d.months || !d.months.length) return;

        // Kill any old charts before recreating (venue picker change).
        Object.values(state.charts).forEach(c => c && c.destroy && c.destroy());
        state.charts = {};

        const monthLabels = d.months.map(translateMonthLabel);
        const evTx = document.getElementById('chart-yearly-evtx');
        if (evTx) {
            state.charts.evTx = new Chart(evTx, {
                type: 'bar',
                data: {
                    labels: monthLabels,
                    datasets: [
                        { label: 'Evenimente', data: d.eventsSeries, backgroundColor: 'rgba(59,130,246,.7)', yAxisID: 'y1' },
                        { label: 'Bilete', data: d.ticketsSeries, type: 'line', borderColor: '#06b6d4', backgroundColor: 'rgba(6,182,212,.2)', yAxisID: 'y2', tension: 0.3 },
                    ],
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { y1: { position: 'left', beginAtZero: true }, y2: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } } } },
            });
        }
        const revOcc = document.getElementById('chart-yearly-revocc');
        if (revOcc) {
            state.charts.revOcc = new Chart(revOcc, {
                type: 'line',
                data: {
                    labels: monthLabels,
                    datasets: [
                        { label: 'Venit (RON)', data: d.revenueSeries, borderColor: '#d97706', backgroundColor: 'rgba(217,119,6,.15)', yAxisID: 'y1', tension: 0.3, fill: true },
                        { label: 'Ocupare (%)', data: d.occupancySeries, borderColor: '#059669', backgroundColor: 'rgba(5,150,105,.15)', yAxisID: 'y2', tension: 0.3 },
                    ],
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { y1: { position: 'left', beginAtZero: true }, y2: { position: 'right', beginAtZero: true, max: 100, grid: { drawOnChartArea: false } } } },
            });
        }
    }

    // ── FINANCIAL ───────────────────────────────────────────────
    function renderFinancial() {
        const d = state.data;
        const rb = d.revenueBreakdown || {};
        const pi = d.pricingIntelligence || {};
        const rf = d.revenueForecast || {};
        const refunds = d.refundAnalysis || {};
        let html = '';

        // Top Artists by Revenue
        if (rb.top_artists_by_revenue && rb.top_artists_by_revenue.length) {
            html += `<div class="a-card"><div class="a-card-h">Top artiști după venit</div>
                <table class="a-tbl"><thead><tr><th>Artist</th><th style="text-align:right">Ev.</th><th style="text-align:right">Venit</th><th style="text-align:right">ST med</th></tr></thead>
                <tbody>${rb.top_artists_by_revenue.map(a => `
                    <tr><td style="font-weight:600;">${escapeHtml(a.name)}</td><td style="text-align:right;">${fmtInt(a.events)}</td><td style="text-align:right;font-family:monospace;color:var(--v-warn);">${fmtMoney(a.total_revenue)}</td><td style="text-align:right;">${a.avg_st}%</td></tr>
                `).join('')}</tbody></table></div>`;
        }

        // Genre + Channel
        html += `<div class="a-g2">
            ${rb.revenue_by_genre && rb.revenue_by_genre.length ? `
                <div class="a-card"><div class="a-card-h">Venit pe gen muzical</div>
                    ${rb.revenue_by_genre.map(g => `
                        <div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px dashed var(--v-ring);font-size:.8125rem;">
                            <span>${escapeHtml(g.genre)} <span style="color:var(--v-muted);font-size:.7rem;">(${g.events} ev)</span></span>
                            <span style="color:var(--v-warn);font-family:monospace;font-weight:600;">${fmtMoney(g.revenue)} RON</span>
                        </div>
                    `).join('')}
                </div>
            ` : ''}
            ${rb.revenue_by_channel && rb.revenue_by_channel.length ? `
                <div class="a-card"><div class="a-card-h">Venit pe canal</div>
                    ${rb.revenue_by_channel.map(c => `
                        <div style="display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px dashed var(--v-ring);font-size:.8125rem;">
                            <span>${escapeHtml(c.source || 'unknown')} <span style="color:var(--v-muted);font-size:.7rem;">(${c.orders || 0} comenzi)</span></span>
                            <span style="color:var(--v-warn);font-family:monospace;font-weight:600;">${fmtMoney(c.revenue || 0)} RON</span>
                        </div>
                    `).join('')}
                </div>
            ` : ''}
        </div>`;

        // Pricing Intelligence
        if (pi.price_buckets && pi.price_buckets.length) {
            html += `<div class="a-g2">
                <div class="a-card"><div class="a-card-h">Sensibilitate preț ${pi.sweet_spot ? `<span style="color:var(--v-success);font-size:.7rem;margin-left:.5rem;">Sweet spot: ${pi.sweet_spot} RON</span>` : ''}</div>
                    ${pi.price_buckets.map(pb => `
                        <div style="display:flex;justify-content:space-between;align-items:center;padding:.3rem 0;">
                            <span style="font-size:.8125rem;">${escapeHtml(pb.range)} RON</span>
                            <div style="display:flex;align-items:center;gap:.5rem;">
                                <div class="a-progress" style="width:6.25rem;"><div class="a-progress-fill" style="width:${Math.min(pb.sell_through, 100)}%;background:var(--v-success);"></div></div>
                                <span style="font-family:monospace;color:var(--v-muted);width:2.5rem;text-align:right;font-size:.75rem;">${pb.sell_through}%</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
                <div class="space-y-3">
                    ${pi.underpriced && pi.underpriced.length ? `
                        <div class="a-card"><div class="a-card-h" style="color:var(--v-warn);">Sub-preț (peste 90% ocupare)</div>
                            ${pi.underpriced.map(u => `<div style="display:flex;justify-content:space-between;padding:.25rem 0;font-size:.75rem;"><span>${escapeHtml(truncate(u.title, 30))}</span><span style="color:var(--v-success);font-weight:600;">${u.sell_through}% · ${u.avg_price} RON</span></div>`).join('')}
                        </div>
                    ` : ''}
                    ${pi.overpriced && pi.overpriced.length ? `
                        <div class="a-card"><div class="a-card-h" style="color:var(--v-danger);">Supra-preț (sub 30% ocupare)</div>
                            ${pi.overpriced.map(o => `<div style="display:flex;justify-content:space-between;padding:.25rem 0;font-size:.75rem;"><span>${escapeHtml(truncate(o.title, 30))}</span><span style="color:var(--v-danger);font-weight:600;">${o.sell_through}% · ${o.avg_price} RON</span></div>`).join('')}
                        </div>
                    ` : ''}
                </div>
            </div>`;
        }

        // Revenue Forecast
        if (rf.forecast && rf.forecast.length) {
            html += `<div class="a-card"><div class="a-card-h">Prognoză venituri (6 luni) ${rf.yoy_change_pct !== null ? `<span style="color:${rf.yoy_change_pct >= 0 ? 'var(--v-success)' : 'var(--v-danger)'};font-size:.7rem;margin-left:.5rem;">YoY: ${rf.yoy_change_pct >= 0 ? '+' : ''}${rf.yoy_change_pct}%</span>` : ''}</div>
                <table class="a-tbl"><thead><tr><th>Lună</th><th style="text-align:right">Pesimist</th><th style="text-align:right">Realist</th><th style="text-align:right">Optimist</th></tr></thead>
                <tbody>${rf.forecast.map(f => `
                    <tr><td>${escapeHtml(f.month)}</td><td style="text-align:right;color:var(--v-muted);font-family:monospace;">${fmtMoney(f.pessimistic)}</td><td style="text-align:right;font-weight:600;color:var(--v-warn);font-family:monospace;">${fmtMoney(f.realistic)}</td><td style="text-align:right;color:var(--v-success);font-family:monospace;">${fmtMoney(f.optimistic)}</td></tr>
                `).join('')}</tbody></table>
            </div>`;
        }

        // Refunds
        if ((refunds.total_refunds || 0) > 0) {
            const refColor = refunds.refund_rate > 5 ? 'var(--v-danger)' : refunds.refund_rate > 2 ? 'var(--v-warn)' : 'var(--v-success)';
            html += `<div class="a-card"><div class="a-card-h">Rambursări & anulări</div>
                <div class="a-g3" style="margin-bottom:.75rem;">
                    <div><div style="color:var(--v-muted);font-size:.7rem;">Total comenzi</div><div style="font-size:1.25rem;font-weight:700;">${fmtInt(refunds.total_orders)}</div></div>
                    <div><div style="color:var(--v-muted);font-size:.7rem;">Rambursări</div><div style="font-size:1.25rem;font-weight:700;color:var(--v-danger);">${fmtInt(refunds.total_refunds)}</div></div>
                    <div><div style="color:var(--v-muted);font-size:.7rem;">Rată</div><div style="font-size:1.25rem;font-weight:700;color:${refColor};">${refunds.refund_rate}%</div></div>
                    <div><div style="color:var(--v-muted);font-size:.7rem;">Venit pierdut</div><div style="font-size:1.25rem;font-weight:700;color:var(--v-danger);">${fmtMoney(refunds.refund_revenue_lost)} RON</div></div>
                </div>
                ${refunds.by_event && refunds.by_event.length ? `
                    <div style="font-size:.75rem;font-weight:600;color:var(--v-muted);margin-bottom:.4rem;">Top evenimente rambursate</div>
                    <table class="a-tbl"><thead><tr><th>Eveniment</th><th style="text-align:right">Com.</th><th style="text-align:right">Ramb.</th><th style="text-align:right">Rată</th><th style="text-align:right">Venit pierdut</th></tr></thead>
                    <tbody>${refunds.by_event.slice(0, 8).map(re => `
                        <tr><td style="font-weight:600;">${escapeHtml(truncate(re.title, 35))} <span style="color:var(--v-muted);font-size:.7rem;">${fmtDate(re.date)}</span></td><td style="text-align:right;">${re.total_orders}</td><td style="text-align:right;color:var(--v-danger);">${re.refunds}</td><td style="text-align:right;font-weight:700;color:${re.refund_rate > 10 ? 'var(--v-danger)' : 'var(--v-muted)'}">${re.refund_rate}%</td><td style="text-align:right;font-family:monospace;color:var(--v-danger);">${fmtMoney(re.lost_revenue)}</td></tr>
                    `).join('')}</tbody></table>
                ` : ''}
            </div>`;
        }

        return html || '<div class="a-card"><p class="text-sm text-slate-500">Fără date financiare.</p></div>';
    }

    // ── AUDIENCE ────────────────────────────────────────────────
    function renderAudience() {
        const d = state.data;
        const personas = (d.audiencePersonas && d.audiencePersonas.personas) || [];
        const totals = (d.audiencePersonas && d.audiencePersonas.totals) || {};
        const loyalty = d.customerLoyalty || {};
        const geo = d.geographicOrigin || {};
        const gLoyalty = d.genreLoyalty || [];
        let html = '';

        // Personas
        if (personas.length) {
            html += `<div class="a-card"><div class="a-card-h">Persoane public (${totals.total_customers || 0} cumpărători)</div>
                <div class="a-g3">${personas.map(p => `
                    <div style="padding:.875rem;border-radius:.5rem;background:rgba(59,130,246,.04);border:1px solid var(--v-ring);">
                        <div style="font-size:.7rem;font-weight:600;color:var(--v-accent);text-transform:uppercase;margin-bottom:.4rem;">${escapeHtml(p.label || '')}</div>
                        <div style="font-size:1rem;font-weight:700;">${escapeHtml(p.age_group || '')} / ${escapeHtml(tr(T_GENDER, (p.gender || '').toLowerCase()) || p.gender || '')}</div>
                        <div style="color:var(--v-muted);font-size:.75rem;margin-top:.25rem;">${fmtInt(p.count)} (${p.percentage}%)</div>
                        <div style="color:var(--v-muted);font-size:.75rem;">Cheltuială medie: ${fmtMoney(p.avg_spend)} RON</div>
                        ${p.top_cities && Object.keys(p.top_cities).length ? `<div style="color:var(--v-muted);font-size:.7rem;margin-top:.25rem;">Orașe: ${escapeHtml(Object.keys(p.top_cities).join(', '))}</div>` : ''}
                    </div>
                `).join('')}</div>
            </div>`;
        }

        // Age + Gender
        const ageDist = totals.age_distribution || {};
        const genderDist = totals.gender_overall || {};
        if (Object.keys(ageDist).length || Object.keys(genderDist).length) {
            const maxAge = Math.max(1, ...Object.values(ageDist).map(v => Number(v) || 0));
            const totalGender = Math.max(1, Object.values(genderDist).reduce((a, b) => a + Number(b || 0), 0));
            html += `<div class="a-g2">
                ${Object.keys(ageDist).length ? `
                    <div class="a-card"><div class="a-card-h">Distribuție vârste</div>
                        ${Object.entries(ageDist).map(([ag, c]) => `
                            <div style="display:flex;align-items:center;gap:.5rem;padding:.25rem 0;">
                                <span style="width:3rem;font-size:.75rem;font-weight:600;">${escapeHtml(ag)}</span>
                                <div class="a-progress" style="flex:1;height:1.125rem;border-radius:.375rem;">
                                    <div class="a-progress-fill" style="width:${Math.round(c / maxAge * 100)}%;background:linear-gradient(90deg,rgba(99,102,241,.7),rgba(6,182,212,.7));border-radius:.375rem;display:flex;align-items:center;padding-left:.4rem;">
                                        <span style="font-size:.65rem;font-weight:600;color:white;">${fmtInt(c)}</span>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}
                ${Object.keys(genderDist).length ? `
                    <div class="a-card"><div class="a-card-h">Distribuție gen</div>
                        <div style="display:flex;gap:1rem;align-items:center;justify-content:center;padding:.5rem 0;flex-wrap:wrap;">
                            ${Object.entries(genderDist).map(([g, c]) => {
                                const pct = Math.round(c / totalGender * 1000) / 10;
                                const gLower = g.toLowerCase();
                                const color = gLower === 'male' ? 'var(--v-primary)' : gLower === 'female' ? '#c084fc' : 'var(--v-muted)';
                                const label = tr(T_GENDER, gLower) || g;
                                return `<div style="text-align:center;"><div style="font-size:2rem;font-weight:700;color:${color};">${pct}%</div><div style="font-size:.75rem;color:var(--v-muted);margin-top:.25rem;">${escapeHtml(label)}</div><div style="font-size:.7rem;color:var(--v-muted);">${fmtInt(c)}</div></div>`;
                            }).join('')}
                        </div>
                        <div style="display:flex;height:.75rem;border-radius:.375rem;overflow:hidden;margin-top:.5rem;">
                            ${Object.entries(genderDist).map(([g, c]) => {
                                const pct = Math.round(c / totalGender * 1000) / 10;
                                const color = g.toLowerCase() === 'male' ? 'var(--v-primary)' : g.toLowerCase() === 'female' ? '#c084fc' : 'var(--v-muted)';
                                return `<div style="width:${pct}%;background:${color};"></div>`;
                            }).join('')}
                        </div>
                    </div>
                ` : ''}
            </div>`;
        }

        // Loyalty + Geographic
        html += `<div class="a-g2">
            ${(loyalty.total || 0) > 0 ? `
                <div class="a-card"><div class="a-card-h">Loialitate cumpărători</div>
                    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:.5rem;text-align:center;">
                        ${[['total','Total','var(--v-text)'],['one_time','O dată','var(--v-muted)'],['repeat','Repeat','var(--v-primary)'],['regulars','Regulari','var(--v-accent)'],['superfan','Superfani','var(--v-warn)']].map(([k, l, c]) => `
                            <div><div style="font-size:1.25rem;font-weight:700;color:${c};">${fmtInt(loyalty[k])}</div><div style="font-size:.625rem;color:var(--v-muted);">${l}</div></div>
                        `).join('')}
                    </div>
                    <div style="text-align:center;margin-top:.625rem;font-size:.8125rem;"><span style="color:var(--v-success);font-weight:700;">${loyalty.repeat_rate}%</span> <span style="color:var(--v-muted);">rată repetare</span></div>
                </div>
            ` : ''}
            ${geo.cities && geo.cities.length ? (() => {
                // Merge "Constanta", "CONSTANTA" and "Constanța" into one
                // row — backend keeps whatever the buyer typed at checkout,
                // so we normalize on display before summing.
                const bucketed = new Map();
                geo.cities.forEach(c => {
                    const key = normalizeCity(c.city);
                    if (!key) return;
                    if (!bucketed.has(key)) {
                        bucketed.set(key, { display: c.city, customer_count: 0, total_revenue: 0 });
                    }
                    const row = bucketed.get(key);
                    row.customer_count += Number(c.customer_count || 0);
                    row.total_revenue += Number(c.total_revenue || 0);
                });
                const merged = Array.from(bucketed.values())
                    .map(r => ({ ...r, display: prettyCity(normalizeCity(r.display)), avg_spend: r.customer_count > 0 ? r.total_revenue / r.customer_count : 0 }))
                    .sort((a, b) => b.customer_count - a.customer_count)
                    .slice(0, 15);
                return `<div class="a-card"><div class="a-card-h">De unde vin cumpărătorii <span style="color:var(--v-accent);font-size:.7rem;margin-left:.5rem;">${geo.out_of_town_ratio}% din alt oraș</span></div>
                    <div style="max-height:18rem;overflow-y:auto;">
                    <table class="a-tbl"><thead><tr><th>Oraș</th><th style="text-align:right">Cumpărători</th><th style="text-align:right">Venit</th><th style="text-align:right">Chelt. medie</th></tr></thead>
                    <tbody>${merged.map(c => `
                        <tr><td style="font-weight:600;">${escapeHtml(c.display)}</td><td style="text-align:right;">${fmtInt(c.customer_count)}</td><td style="text-align:right;font-family:monospace;color:var(--v-warn);">${fmtMoney(c.total_revenue)}</td><td style="text-align:right;color:var(--v-muted);font-family:monospace;">${fmtMoney(c.avg_spend)}</td></tr>
                    `).join('')}</tbody></table></div>
                </div>`;
            })() : ''}
        </div>`;

        // Superfans
        if (loyalty.superfan_details && loyalty.superfan_details.length) {
            html += `<div class="a-card"><div class="a-card-h">Top superfani</div>
                <table class="a-tbl"><thead><tr><th>Nume</th><th>Email</th><th>Oraș</th><th style="text-align:right">Evenimente</th><th style="text-align:right">Total cheltuit</th></tr></thead>
                <tbody>${loyalty.superfan_details.slice(0, 15).map(sf => `
                    <tr><td style="font-weight:600;">${escapeHtml(sf.name)}</td><td style="color:var(--v-muted);">${escapeHtml(sf.email)}</td><td>${escapeHtml(sf.city || '')}</td><td style="text-align:right;">${fmtInt(sf.events)}</td><td style="text-align:right;color:var(--v-warn);font-family:monospace;">${fmtMoney(sf.total_spent)}</td></tr>
                `).join('')}</tbody></table></div>`;
        }

        // Genre Loyalty
        if (gLoyalty.length) {
            html += `<div class="a-card"><div class="a-card-h">Cumpărători recurenți pe gen — Ce genuri construiesc loialitate?</div>
                <table class="a-tbl"><thead><tr><th>Gen</th><th style="text-align:right">Total</th><th style="text-align:right">Recurenți</th><th style="text-align:right">Rată</th><th style="text-align:right">Ev./cumpărător</th></tr></thead>
                <tbody>${gLoyalty.map(g => `
                    <tr><td style="font-weight:600;">${escapeHtml(g.genre)}</td><td style="text-align:right;">${fmtInt(g.total_buyers)}</td><td style="text-align:right;color:var(--v-accent);">${fmtInt(g.repeat_buyers)}</td><td style="text-align:right;font-weight:700;color:${g.repeat_rate >= 20 ? 'var(--v-success)' : g.repeat_rate >= 10 ? 'var(--v-warn)' : 'var(--v-muted)'};">${g.repeat_rate}%</td><td style="text-align:right;font-family:monospace;">${g.avg_events_per_buyer}</td></tr>
                `).join('')}</tbody></table></div>`;
        }

        return html || '<div class="a-card"><p class="text-sm text-slate-500">Fără date despre public.</p></div>';
    }

    // ── ARTISTS ─────────────────────────────────────────────────
    function renderArtists() {
        const d = state.data;
        const ap = d.artistPerformance || [];
        const gp = d.genrePerformance || [];
        const np = d.neverPlayed || [];
        let html = '';

        if (ap.length) {
            html += `<div class="a-card"><div class="a-card-h">Performanță artiști la locație (${ap.length})</div>
                <div style="overflow-x:auto;">
                <table class="a-tbl"><thead><tr><th>Artist</th><th style="text-align:right">Ev.</th><th style="text-align:right">Bilete</th><th style="text-align:right">ST med</th><th style="text-align:right">Best ST</th><th style="text-align:right">Venit med</th></tr></thead>
                <tbody>${ap.slice(0, 20).map(a => `
                    <tr><td style="font-weight:600;">${escapeHtml(a.artist_name)}</td><td style="text-align:right;">${a.events_count}</td><td style="text-align:right;font-family:monospace;">${fmtInt(a.total_tickets)}</td><td style="text-align:right;font-weight:700;color:${stColor(a.avg_sell_through)};">${a.avg_sell_through}%</td><td style="text-align:right;color:var(--v-muted);">${a.best_sell_through}%</td><td style="text-align:right;color:var(--v-warn);font-family:monospace;">${fmtMoney(a.avg_revenue)}</td></tr>
                `).join('')}</tbody></table></div>
            </div>`;
        }

        if (gp.length) {
            html += `<div class="a-card"><div class="a-card-h">Performanță pe gen</div>
                <table class="a-tbl"><thead><tr><th>Gen</th><th style="text-align:right">Ev.</th><th style="text-align:right">ST med</th><th style="text-align:right">Venit med</th><th style="text-align:right">Preț med</th><th style="text-align:right">Bilete</th></tr></thead>
                <tbody>${gp.map(g => `
                    <tr><td style="font-weight:600;">${escapeHtml(g.genre)}</td><td style="text-align:right;">${g.events_count}</td><td style="text-align:right;font-weight:700;color:${g.avg_sell_through >= 70 ? 'var(--v-success)' : 'var(--v-muted)'};">${g.avg_sell_through}%</td><td style="text-align:right;color:var(--v-warn);font-family:monospace;">${fmtMoney(g.avg_revenue)}</td><td style="text-align:right;color:var(--v-muted);font-family:monospace;">${g.avg_ticket_price}</td><td style="text-align:right;font-family:monospace;">${fmtInt(g.total_tickets)}</td></tr>
                `).join('')}</tbody></table></div>`;
        }

        if (np.length) {
            html += `<div class="a-card"><div class="a-card-h">Artiști de considerat pentru bookings</div>
                <table class="a-tbl"><thead><tr><th>Artist</th><th style="text-align:right">Ev. în oraș</th><th style="text-align:right">ST med</th><th style="text-align:right">Public estimat</th></tr></thead>
                <tbody>${np.slice(0, 15).map(n => `
                    <tr><td style="font-weight:600;">${escapeHtml(n.artist_name)}</td><td style="text-align:right;">${n.city_events}</td><td style="text-align:right;font-weight:700;color:var(--v-success);">${n.avg_sell_through}%</td><td style="text-align:right;font-family:monospace;">${fmtInt(n.estimated_draw)}</td></tr>
                `).join('')}</tbody></table></div>`;
        }

        return html || '<div class="a-card"><p class="text-sm text-slate-500">Fără date artiști.</p></div>';
    }

    // ── SCHEDULING ──────────────────────────────────────────────
    function renderScheduling() {
        const d = state.data;
        const heatmap = d.schedulingHeatmap || {};
        const dow = d.dayOfWeek || [];
        const season = d.seasonality || [];
        const idle = d.idleDays || {};
        const si = d.salesIntelligence || {};
        const optFreq = si.optimal_frequency || [];
        const timing = si.purchase_timing || {};
        const velCurves = si.velocity_curves || [];
        let html = '';

        // Heatmap
        if (heatmap.matrix) {
            html += `<div class="a-card"><div class="a-card-h">Heatmap performanță (zi × lună)</div>
                <div style="overflow-x:auto;"><table style="width:100%;font-size:.75rem;border-collapse:collapse;">
                    <thead><tr><th style="padding:.4rem;"></th>${(heatmap.months || []).map(m => `<th style="padding:.4rem;color:var(--v-muted);text-align:center;font-weight:600;">${escapeHtml(tr(T_MONTH, m) || m)}</th>`).join('')}</tr></thead>
                    <tbody>${(heatmap.days || []).map((dayName, di) => `
                        <tr><td style="padding:.4rem;font-weight:600;color:var(--v-muted);">${escapeHtml(tr(T_DAY, dayName || ''))}</td>${Array.from({length: 12}, (_, mi) => {
                            const cell = heatmap.matrix[di] && heatmap.matrix[di][mi];
                            const st = cell ? cell.st : null;
                            const bg = st === null ? 'transparent' : st >= 75 ? 'rgba(5,150,105,.3)' : st >= 50 ? 'rgba(217,119,6,.2)' : st > 0 ? 'rgba(220,38,38,.15)' : 'transparent';
                            return `<td style="padding:.4rem;text-align:center;background:${bg};border-radius:.25rem;color:${st !== null ? 'var(--v-text)' : 'var(--v-muted)'};font-weight:${st !== null ? '600' : '400'};">${st !== null ? st + '%' : '·'}</td>`;
                        }).join('')}</tr>
                    `).join('')}</tbody>
                </table></div>
            </div>`;
        }

        // Day of Week + Seasonality
        html += `<div class="a-g2">
            ${dow.length ? `
                <div class="a-card"><div class="a-card-h">Zi a săptămânii</div>
                    <table class="a-tbl"><thead><tr><th>Zi</th><th style="text-align:right">Ev.</th><th style="text-align:right">ST med</th><th style="text-align:right">Venit med</th></tr></thead>
                    <tbody>${dow.map(x => `
                        <tr><td style="font-weight:600;">${escapeHtml(tr(T_DAY, x.day || ''))}</td><td style="text-align:right;">${x.events}</td><td style="text-align:right;font-weight:700;color:${x.avg_sell_through >= 70 ? 'var(--v-success)' : 'var(--v-muted)'};">${x.avg_sell_through}%</td><td style="text-align:right;color:var(--v-warn);font-family:monospace;">${fmtMoney(x.avg_revenue)}</td></tr>
                    `).join('')}</tbody></table>
                </div>
            ` : ''}
            ${season.length ? `
                <div class="a-card"><div class="a-card-h">Sezonalitate</div>
                    <table class="a-tbl"><thead><tr><th>Lună</th><th style="text-align:right">Ev.</th><th style="text-align:right">ST med</th><th style="text-align:right">Venit med</th><th style="text-align:right">Idle</th></tr></thead>
                    <tbody>${season.map(s => `
                        <tr><td style="font-weight:600;">${escapeHtml(translateMonthLabel(s.month || ''))}</td><td style="text-align:right;">${s.events}</td><td style="text-align:right;font-weight:700;color:${s.avg_sell_through >= 70 ? 'var(--v-success)' : 'var(--v-muted)'};">${s.avg_sell_through}%</td><td style="text-align:right;color:var(--v-warn);font-family:monospace;">${fmtMoney(s.avg_revenue)}</td><td style="text-align:right;color:${s.idle_days > 20 ? 'var(--v-danger)' : 'var(--v-muted)'};">${s.idle_days}</td></tr>
                    `).join('')}</tbody></table>
                </div>
            ` : ''}
        </div>`;

        // Idle + Frequency
        html += `<div class="a-g2">
            ${(idle.total_idle_weekend_days || 0) > 0 ? `
                <div class="a-card"><div class="a-card-h">Zile weekend libere (ultimele 12 luni)</div>
                    <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
                        <div><div style="color:var(--v-muted);font-size:.7rem;">Vin/Sâm/Dum libere</div><div style="font-size:1.75rem;font-weight:700;color:var(--v-danger);">${idle.total_idle_weekend_days}</div></div>
                        <div><div style="color:var(--v-muted);font-size:.7rem;">Venit mediu / ev.</div><div style="font-size:1.125rem;font-weight:700;color:var(--v-warn);">${fmtMoney(idle.avg_revenue_per_event)} RON</div></div>
                        <div><div style="color:var(--v-muted);font-size:.7rem;">Venit pierdut est.</div><div style="font-size:1.125rem;font-weight:700;color:var(--v-danger);">${fmtMoney(idle.estimated_lost_revenue)} RON</div></div>
                    </div>
                </div>
            ` : ''}
            ${optFreq.length ? `
                <div class="a-card"><div class="a-card-h">Frecvență optimă</div>
                    <table class="a-tbl"><thead><tr><th>Frecvență</th><th style="text-align:right">Săpt.</th><th style="text-align:right">ST med</th></tr></thead>
                    <tbody>${optFreq.map(o => `
                        <tr><td style="font-weight:600;">${escapeHtml(o.frequency)}</td><td style="text-align:right;">${o.weeks}</td><td style="text-align:right;font-weight:700;color:${o.avg_sell_through >= 70 ? 'var(--v-success)' : 'var(--v-muted)'};">${o.avg_sell_through}%</td></tr>
                    `).join('')}</tbody></table>
                </div>
            ` : ''}
        </div>`;

        // Purchase Timing + Sales Velocity
        html += `<div class="a-g2">
            ${Object.keys(timing).length ? `
                <div class="a-card"><div class="a-card-h">Timing achiziții <span style="color:var(--v-muted);font-size:.7rem;margin-left:.5rem;">(medie ${si.avg_lead_days || 0}z înainte)</span></div>
                    ${(() => {
                        const labels = { super_early: '90+ zile', early_bird: '31-90 zile', last_month: '8-30 zile', last_week: '2-7 zile', last_minute: 'În ultima zi' };
                        return Object.entries(labels).filter(([k]) => timing[k] > 0).map(([k, l]) => `
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:.3rem 0;">
                                <span style="font-size:.8125rem;">${escapeHtml(l)}</span>
                                <div style="display:flex;align-items:center;gap:.5rem;">
                                    <div class="a-progress" style="width:6.25rem;"><div class="a-progress-fill" style="width:${Math.min(timing[k], 100)}%;background:var(--v-primary);"></div></div>
                                    <span style="font-family:monospace;color:var(--v-muted);width:2.5rem;text-align:right;font-size:.75rem;">${timing[k]}%</span>
                                </div>
                            </div>
                        `).join('');
                    })()}
                </div>
            ` : ''}
            ${velCurves.length ? `
                <div class="a-card"><div class="a-card-h">Viteză vânzări (ultimele 5 evenimente)</div>
                    <div style="font-size:.75rem;">${velCurves.map((vc, i) => `
                        <div style="margin-bottom:.875rem;padding-bottom:.875rem;${i < velCurves.length - 1 ? 'border-bottom:1px dashed var(--v-ring);' : ''}">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem;">
                                <strong>${escapeHtml(truncate(vc.event_name || '', 35))}</strong>
                                <span style="color:var(--v-muted);font-size:.7rem;">${vc.total_tickets} bilete</span>
                            </div>
                            <div style="display:flex;align-items:flex-end;gap:.2rem;height:2.5rem;">
                                ${(vc.points || []).map(pt => {
                                    const h = Math.max(4, Math.round((pt.pct || 0) * 0.4));
                                    const opacity = 0.3 + ((pt.pct || 0) / 100 * 0.7);
                                    return `<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:.125rem;">
                                        <span style="font-size:.55rem;color:var(--v-muted);">${pt.pct}%</span>
                                        <div style="width:100%;height:${h}px;background:var(--v-primary);border-radius:.2rem;opacity:${opacity};"></div>
                                        <span style="font-size:.55rem;color:var(--v-muted);">${pt.days}z</span>
                                    </div>`;
                                }).join('')}
                            </div>
                        </div>
                    `).join('')}</div>
                </div>
            ` : ''}
        </div>`;

        return html || '<div class="a-card"><p class="text-sm text-slate-500">Fără date de programare.</p></div>';
    }

    // ── OPPORTUNITIES ───────────────────────────────────────────
    function renderOpportunities() {
        const d = state.data;
        const opps = d.opportunities || {};
        const churn = d.churnAlerts || [];
        let html = '';

        // Opportunities list
        const findings = opps.findings || opps || [];
        html += `<div class="a-card"><div class="a-card-h">Oportunități</div>
            <div class="space-y-2">${(Array.isArray(findings) ? findings : []).slice(0, 12).map(o => `
                <div style="padding:.75rem;border:1px solid var(--v-ring);border-radius:.5rem;background:#f8fafc;">
                    <p style="font-size:.8125rem;font-weight:600;color:var(--v-text);">${escapeHtml(o.title || o.name || '')}</p>
                    ${o.description || o.detail ? `<p style="font-size:.75rem;color:var(--v-muted);margin-top:.25rem;">${escapeHtml(o.description || o.detail)}</p>` : ''}
                </div>
            `).join('') || '<p class="text-sm text-slate-500">Nici o oportunitate detectată încă.</p>'}</div>
        </div>`;

        // Churn Alerts
        if (churn.length) {
            html += `<div class="a-card"><div class="a-card-h">Alerte churn</div>
                <table class="a-tbl"><thead><tr><th>Cumpărător</th><th>Ultima achiziție</th><th style="text-align:right">Zile</th><th style="text-align:right">Risc</th></tr></thead>
                <tbody>${churn.slice(0, 15).map(c => {
                    const risk = c.risk || 'medium';
                    const riskColor = risk === 'high' ? 'var(--v-danger)' : risk === 'medium' ? 'var(--v-warn)' : 'var(--v-muted)';
                    return `<tr><td style="font-weight:600;">${escapeHtml(c.name || c.email || '')}</td><td style="color:var(--v-muted);">${escapeHtml(c.last_purchase || '')}</td><td style="text-align:right;">${fmtInt(c.days_since)}</td><td style="text-align:right;color:${riskColor};font-weight:600;">${escapeHtml(c.risk_label || risk)}</td></tr>`;
                }).join('')}</tbody></table>
            </div>`;
        }

        // Event Simulator
        html += `<div class="a-card"><div class="a-card-h">Simulator eveniment</div>
            <p style="font-size:.75rem;color:var(--v-muted);margin-bottom:.75rem;">Verifică ce numere ai avea pentru un eveniment ipotetic.</p>
            <div class="a-g3">
                <input id="sim-genre" placeholder="Gen (ex: rock)" style="padding:.4rem .625rem;border:1px solid var(--v-ring);border-radius:.375rem;font-size:.8125rem;">
                <input id="sim-dow" placeholder="Zi (ex: Vineri)" style="padding:.4rem .625rem;border:1px solid var(--v-ring);border-radius:.375rem;font-size:.8125rem;">
                <input id="sim-price" type="number" placeholder="Preț bilet (RON)" style="padding:.4rem .625rem;border:1px solid var(--v-ring);border-radius:.375rem;font-size:.8125rem;">
            </div>
            <button id="sim-run" style="margin-top:.625rem;padding:.5rem 1rem;background:var(--v-primary);color:#fff;border:none;border-radius:.375rem;font-size:.8125rem;font-weight:600;cursor:pointer;">Simulează</button>
            <div id="sim-result" style="margin-top:.75rem;"></div>
        </div>`;

        return html;
    }

    // ── PROMOTION ───────────────────────────────────────────────
    function renderPromotion() {
        const d = state.data;
        const pp = d.promotionPlanner || {};
        const aw = pp.announcement_window || {};
        const ab = pp.ad_budget || {};
        const ps = pp.platform_strategy || [];
        const topGenres = pp.top_genres || [];
        let html = '';

        // Announcement window
        if (aw.optimal_announce_days !== undefined) {
            html += `<div class="a-card"><div class="a-card-h">Fereastră optimă de anunț</div>
                <div class="a-g3" style="margin-bottom:.75rem;">
                    <div style="text-align:center;padding:.75rem;background:#f8fafc;border-radius:.5rem;">
                        <div style="font-size:1.5rem;font-weight:700;color:var(--v-primary);">${aw.optimal_announce_days} zile</div>
                        <div style="font-size:.7rem;color:var(--v-muted);margin-top:.25rem;">Anunț optim înainte</div>
                    </div>
                    <div style="text-align:center;padding:.75rem;background:#f8fafc;border-radius:.5rem;">
                        <div style="font-size:1.5rem;font-weight:700;color:var(--v-warn);">${aw.p90_days} zile</div>
                        <div style="font-size:.7rem;color:var(--v-muted);margin-top:.25rem;">P90 (90% cumpără cu)</div>
                    </div>
                    <div style="text-align:center;padding:.75rem;background:#f8fafc;border-radius:.5rem;">
                        <div style="font-size:1.5rem;font-weight:700;color:var(--v-accent);">${aw.median_days} zile</div>
                        <div style="font-size:.7rem;color:var(--v-muted);margin-top:.25rem;">Median lead time</div>
                    </div>
                </div>
                <p style="font-size:.75rem;color:var(--v-muted);">Bazat pe timpii de cumpărare istoric — publică cu ${aw.optimal_announce_days} zile înainte pentru captura maximă.</p>
            </div>`;
        }

        // Ad budget with phases
        if (ab.recommended_budget !== undefined) {
            html += `<div class="a-card"><div class="a-card-h">Buget recomandat reclame</div>
                <div style="display:flex;gap:1rem;margin-bottom:.75rem;flex-wrap:wrap;">
                    <div><div style="color:var(--v-muted);font-size:.7rem;">Venit estimat / eveniment</div><div style="font-size:1.375rem;font-weight:700;">${fmtMoney(ab.estimated_revenue)} RON</div></div>
                    <div><div style="color:var(--v-muted);font-size:.7rem;">Buget recomandat (12%)</div><div style="font-size:1.375rem;font-weight:700;color:var(--v-warn);">${fmtMoney(ab.recommended_budget)} RON</div></div>
                </div>
                ${ab.budget_phases && ab.budget_phases.length ? `
                    <div style="font-size:.75rem;font-weight:600;color:var(--v-muted);margin-bottom:.4rem;">Distribuție pe faze</div>
                    <table class="a-tbl"><thead><tr><th>Faza</th><th>Interval</th><th style="text-align:right">Pondere</th><th style="text-align:right">Sumă</th></tr></thead>
                    <tbody>${ab.budget_phases.map(p => {
                        const T_PHASE = { 'Announce': 'Anunț', 'Peak': 'Maxim', 'Urgency': 'Urgență', 'Last Call': 'Ultimul apel' };
                        return `<tr><td style="font-weight:600;">${escapeHtml(tr(T_PHASE, p.phase) || p.phase)}</td><td style="color:var(--v-muted);">${escapeHtml(p.days_range)}</td><td style="text-align:right;">${p.pct}%</td><td style="text-align:right;font-family:monospace;color:var(--v-warn);">${fmtMoney(p.amount)} RON</td></tr>`;
                    }).join('')}</tbody></table>
                ` : ''}
            </div>`;
        }

        // Platform strategy
        if (ps.length) {
            html += `<div class="a-card"><div class="a-card-h">Strategie pe platforme</div>
                ${ps.map(p => `
                    <div style="padding:.75rem;border:1px solid var(--v-ring);border-radius:.5rem;background:#f8fafc;margin-bottom:.5rem;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem;">
                            <p style="font-size:.875rem;font-weight:700;">${escapeHtml(p.platform || '')}</p>
                            ${p.recommended_budget_share !== undefined ? `<span style="font-size:.75rem;color:var(--v-warn);font-weight:600;">${p.recommended_budget_share}% buget</span>` : ''}
                        </div>
                        ${p.rationale ? `<p style="font-size:.75rem;color:var(--v-muted);">${escapeHtml(p.rationale)}</p>` : ''}
                        ${p.targeting ? `<p style="font-size:.75rem;color:var(--v-muted);margin-top:.25rem;"><strong>Targeting:</strong> ${escapeHtml(p.targeting)}</p>` : ''}
                    </div>
                `).join('')}
            </div>`;
        }

        // Top genres for targeting
        if (topGenres.length) {
            html += `<div class="a-card"><div class="a-card-h">Genuri principale pentru targetare</div>
                <div class="a-g3">${topGenres.map(g => `
                    <div style="padding:.75rem;background:#f8fafc;border-radius:.5rem;">
                        <div style="font-size:.875rem;font-weight:700;">${escapeHtml(g.genre || g.name || '')}</div>
                        <div style="font-size:.7rem;color:var(--v-muted);margin-top:.25rem;">${g.events || 0} evenimente · ocupare medie ${g.avg_sell_through || g.avg_st || 0}%</div>
                    </div>
                `).join('')}</div>
            </div>`;
        }

        return html || '<div class="a-card"><p class="text-sm text-slate-500">Fără date de promovare.</p></div>';
    }

    // ── UPCOMING ────────────────────────────────────────────────
    function renderUpcoming() {
        const d = state.data;
        const upcoming = d.upcomingEvents || [];
        if (!upcoming.length) return '<div class="a-card"><p class="text-sm text-slate-500">Niciun eveniment programat.</p></div>';

        return `<div class="a-card"><div class="a-card-h">Evenimente ce urmează (${upcoming.length})</div>
            <table class="a-tbl"><thead><tr><th>Data</th><th>Eveniment</th><th>Locație</th><th style="text-align:right">Zile</th><th style="text-align:right">Vândute</th></tr></thead>
            <tbody>${upcoming.slice(0, 30).map(e => {
                const title = e.title || e.name || '—';
                const venue = e.venue_name || (e.venue && e.venue.name) || '';
                return `<tr><td style="white-space:nowrap;">${fmtDate(e.event_date || e.date || e.start_date)}</td><td style="font-weight:600;">${escapeHtml(truncate(title, 40))}</td><td style="color:var(--v-muted);">${escapeHtml(venue)}</td><td style="text-align:right;">${fmtInt(e.days_until)}</td><td style="text-align:right;font-family:monospace;">${fmtInt(e.tickets_sold)}${e.capacity ? '/' + fmtInt(e.capacity) : ''}</td></tr>`;
            }).join('')}</tbody></table>
        </div>`;
    }

    // ── ACTIONS ─────────────────────────────────────────────────
    function renderActions() {
        const d = state.data;
        const ap = d.actionPriority || {};
        const items = ap.actions || (Array.isArray(ap) ? ap : []);
        if (!items.length) return '<div class="a-card"><p class="text-sm text-slate-500">Nici o acțiune prioritară detectată.</p></div>';

        return `<div class="a-card"><div class="a-card-h">Priorități acțiune</div>
            <div class="space-y-2">${items.slice(0, 15).map(a => {
                const p = a.priority || 'medium';
                const c = p === 'high' ? 'var(--v-danger)' : p === 'medium' ? 'var(--v-warn)' : 'var(--v-primary)';
                return `<div style="padding:.75rem;border:1px solid var(--v-ring);border-radius:.5rem;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;">
                        <div style="flex:1;">
                            <p style="font-size:.8125rem;font-weight:600;">${escapeHtml(a.title || a.action || '')}</p>
                            ${a.description ? `<p style="font-size:.75rem;color:var(--v-muted);margin-top:.25rem;">${escapeHtml(a.description)}</p>` : ''}
                        </div>
                        <span style="padding:.15rem .5rem;font-size:.65rem;font-weight:700;border-radius:.25rem;text-transform:uppercase;background:${c};color:#fff;">${escapeHtml(a.priority_label || p)}</span>
                    </div>
                </div>`;
            }).join('')}</div>
        </div>`;
    }

    function wireInteractive() {
        // Event comparison
        const cmpRun = document.getElementById('cmp-run');
        if (cmpRun) {
            cmpRun.addEventListener('click', async () => {
                const a = parseInt(document.getElementById('cmp-a').value, 10);
                const b = parseInt(document.getElementById('cmp-b').value, 10);
                const result = document.getElementById('cmp-result');
                if (!a || !b) { result.innerHTML = '<p style="font-size:.75rem;color:var(--v-danger);">Selectează ambele evenimente.</p>'; return; }
                if (a === b) { result.innerHTML = '<p style="font-size:.75rem;color:var(--v-danger);">Alege 2 evenimente diferite.</p>'; return; }
                result.innerHTML = '<p style="font-size:.75rem;color:var(--v-muted);">Se compară…</p>';
                try {
                    const res = await AmbiletVenueAPI.compare(a, b);
                    if (res && res.success && res.data) {
                        const r = res.data;
                        const evA = r.event_a || {}, evB = r.event_b || {};
                        result.innerHTML = `
                            <table class="a-tbl"><thead><tr><th>Metrică</th><th style="text-align:right">${escapeHtml(truncate(evA.title || 'A', 30))}</th><th style="text-align:right">${escapeHtml(truncate(evB.title || 'B', 30))}</th></tr></thead>
                            <tbody>
                                <tr><td style="font-weight:600;">Data</td><td style="text-align:right;">${fmtDate(evA.date)}</td><td style="text-align:right;">${fmtDate(evB.date)}</td></tr>
                                <tr><td style="font-weight:600;">Artiști</td><td style="text-align:right;color:var(--v-muted);font-size:.75rem;">${escapeHtml(evA.artists || '')}</td><td style="text-align:right;color:var(--v-muted);font-size:.75rem;">${escapeHtml(evB.artists || '')}</td></tr>
                                <tr><td style="font-weight:600;">Vândute / Capacitate</td><td style="text-align:right;font-family:monospace;">${fmtInt(evA.sold)} / ${fmtInt(evA.capacity)}</td><td style="text-align:right;font-family:monospace;">${fmtInt(evB.sold)} / ${fmtInt(evB.capacity)}</td></tr>
                                <tr><td style="font-weight:600;">Ocupare</td><td style="text-align:right;font-weight:700;color:${stColor(evA.sell_through||0)};">${evA.sell_through !== null ? evA.sell_through + '%' : '—'}</td><td style="text-align:right;font-weight:700;color:${stColor(evB.sell_through||0)};">${evB.sell_through !== null ? evB.sell_through + '%' : '—'}</td></tr>
                                <tr><td style="font-weight:600;">Venit</td><td style="text-align:right;font-family:monospace;color:var(--v-warn);">${fmtMoney(evA.revenue)} RON</td><td style="text-align:right;font-family:monospace;color:var(--v-warn);">${fmtMoney(evB.revenue)} RON</td></tr>
                                <tr><td style="font-weight:600;">Preț mediu</td><td style="text-align:right;font-family:monospace;">${evA.avg_price || 0} RON</td><td style="text-align:right;font-family:monospace;">${evB.avg_price || 0} RON</td></tr>
                                <tr><td style="font-weight:600;">Timp mediu cumpărare</td><td style="text-align:right;">${evA.avg_lead_days || 0}z</td><td style="text-align:right;">${evB.avg_lead_days || 0}z</td></tr>
                                <tr><td style="font-weight:600;">Rata check-in</td><td style="text-align:right;">${evA.checkin_rate !== null ? evA.checkin_rate + '%' : '—'}</td><td style="text-align:right;">${evB.checkin_rate !== null ? evB.checkin_rate + '%' : '—'}</td></tr>
                            </tbody></table>`;
                    } else {
                        result.innerHTML = '<p style="font-size:.75rem;color:var(--v-danger);">Comparație eșuată.</p>';
                    }
                } catch (e) {
                    console.error(e);
                    result.innerHTML = '<p style="font-size:.75rem;color:var(--v-danger);">Eroare.</p>';
                }
            });
        }

        const runBtn = document.getElementById('sim-run');
        if (!runBtn) return;
        runBtn.addEventListener('click', async () => {
            const genre = document.getElementById('sim-genre').value.trim();
            const dow   = document.getElementById('sim-dow').value.trim();
            const price = parseFloat(document.getElementById('sim-price').value);
            const result = document.getElementById('sim-result');
            if (!genre || !dow || !price) {
                result.innerHTML = '<p style="font-size:.75rem;color:var(--v-danger);">Completează toate câmpurile.</p>';
                return;
            }
            result.innerHTML = '<p style="font-size:.75rem;color:var(--v-muted);">Se simulează…</p>';
            try {
                const res = await AmbiletVenueAPI.simulate(genre, dow, price);
                if (res && res.success && res.data) {
                    const r = res.data;
                    result.innerHTML = `<div class="a-g3">
                        <div style="padding:.75rem;background:#f8fafc;border-radius:.5rem;text-align:center;"><div style="font-size:1.25rem;font-weight:700;">${fmtInt(r.estimated_tickets || 0)}</div><div style="font-size:.7rem;color:var(--v-muted);">Bilete estimate</div></div>
                        <div style="padding:.75rem;background:#f8fafc;border-radius:.5rem;text-align:center;"><div style="font-size:1.25rem;font-weight:700;color:var(--v-warn);">${fmtMoney(r.estimated_revenue || 0)} RON</div><div style="font-size:.7rem;color:var(--v-muted);">Venit estimat</div></div>
                        <div style="padding:.75rem;background:#f8fafc;border-radius:.5rem;text-align:center;"><div style="font-size:1.25rem;font-weight:700;color:var(--v-success);">${r.estimated_occupancy || 0}%</div><div style="font-size:.7rem;color:var(--v-muted);">Ocupare estimată</div></div>
                    </div>${r.summary ? `<p style="margin-top:.5rem;font-size:.75rem;color:var(--v-muted);">${escapeHtml(r.summary)}</p>` : ''}`;
                } else {
                    result.innerHTML = '<p style="font-size:.75rem;color:var(--v-danger);">Simulare eșuată.</p>';
                }
            } catch (e) {
                console.error(e);
                result.innerHTML = '<p style="font-size:.75rem;color:var(--v-danger);">Eroare.</p>';
            }
        });
    }

    // ── Boot ────────────────────────────────────────────────────
    document.querySelectorAll('.analiza-tab-btn').forEach(b => {
        b.addEventListener('click', () => switchTab(b.dataset.tab));
    });

    await initVenuePicker();
    await loadAll();
})());
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
