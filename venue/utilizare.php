<?php
/**
 * Venue Owner — /venue/utilizare
 * Portul VenueUsage (KPI + table + filtre). Reads from /venue-owner/usage.
 */
require_once dirname(__DIR__) . '/includes/config.php';

$pageTitle       = 'Utilizare';
$venuePageTitle  = 'Utilizare';
$bodyClass       = 'min-h-screen flex bg-slate-100';
$currentPage     = 'venue_utilizare';
$cssBundle       = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/venue-sidebar.php';
?>

    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/venue-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8">
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-slate-900">Utilizare</h2>
                <p class="mt-1 text-sm text-slate-500">Evenimente găzduite la locațiile tale.</p>
            </div>

            <!-- KPI cards -->
            <div class="grid grid-cols-2 gap-4 mb-6 lg:grid-cols-5">
                <div class="p-4 bg-white border rounded-xl border-slate-200">
                    <p id="usage-kpi-total" class="text-2xl font-bold text-slate-900">—</p>
                    <p class="text-xs text-slate-500 mt-1">Total</p>
                </div>
                <div class="p-4 bg-white border rounded-xl border-slate-200">
                    <p id="usage-kpi-upcoming" class="text-2xl font-bold" style="color:#3b82f6;">—</p>
                    <p class="text-xs text-slate-500 mt-1">Viitoare</p>
                </div>
                <div class="p-4 bg-white border rounded-xl border-slate-200">
                    <p id="usage-kpi-ended" class="text-2xl font-bold text-slate-700">—</p>
                    <p class="text-xs text-slate-500 mt-1">Trecute</p>
                </div>
                <div class="p-4 bg-white border rounded-xl border-slate-200">
                    <p id="usage-kpi-sold" class="text-2xl font-bold text-emerald-600">—</p>
                    <p class="text-xs text-slate-500 mt-1">Bilete emise</p>
                </div>
                <div class="p-4 bg-white border rounded-xl border-slate-200">
                    <p id="usage-kpi-revenue" class="text-2xl font-bold" style="color:#f59e0b;">—</p>
                    <p class="text-xs text-slate-500 mt-1">Venit total (RON)</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3 mb-4 p-4 bg-white border rounded-xl border-slate-200">
                <div class="flex items-center gap-2">
                    <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Locație:</label>
                    <select id="filter-venue" class="px-3 py-2 text-sm border rounded-lg border-slate-200">
                        <option value="all">Toate</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-xs font-semibold uppercase tracking-wider text-slate-500">Stare:</label>
                    <select id="filter-status" class="px-3 py-2 text-sm border rounded-lg border-slate-200">
                        <option value="all">Toate</option>
                        <option value="live">Viitoare</option>
                        <option value="ended">Trecute</option>
                        <option value="cancelled">Anulate</option>
                        <option value="postponed">Amânate</option>
                    </select>
                </div>
            </div>

            <!-- Events table -->
            <div class="overflow-hidden bg-white border rounded-xl border-slate-200">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-xs font-semibold text-left uppercase tracking-wider text-slate-500">Data</th>
                                <th class="px-4 py-3 text-xs font-semibold text-left uppercase tracking-wider text-slate-500">Eveniment</th>
                                <th class="px-4 py-3 text-xs font-semibold text-left uppercase tracking-wider text-slate-500">Locație</th>
                                <th class="px-4 py-3 text-xs font-semibold text-right uppercase tracking-wider text-slate-500">Ocupare</th>
                                <th class="px-4 py-3 text-xs font-semibold text-right uppercase tracking-wider text-slate-500">Venit</th>
                            </tr>
                        </thead>
                        <tbody id="usage-tbody" class="divide-y divide-slate-100">
                            <tr><td colspan="5" class="p-8 text-center text-sm text-slate-400">Se încarcă…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => (async function () {
    if (typeof AmbiletVenueAPI === 'undefined') return;

    function fmtInt(n) { return Number(n || 0).toLocaleString('ro-RO'); }
    function fmtDate(d) {
        if (!d) return '—';
        try {
            const dt = new Date(d);
            return dt.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
        } catch (e) { return d; }
    }
    function fmtMoney(n) {
        return Number(n || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    async function reload() {
        const filters = {
            venue_filter:  document.getElementById('filter-venue').value,
            status_filter: document.getElementById('filter-status').value,
        };
        try {
            const res = await AmbiletVenueAPI.usage(filters);
            if (!res || !res.success || !res.data) return;

            const d = res.data;

            // KPIs
            const s = d.stats || {};
            document.getElementById('usage-kpi-total').textContent = fmtInt(s.total);
            document.getElementById('usage-kpi-upcoming').textContent = fmtInt(s.upcoming);
            document.getElementById('usage-kpi-ended').textContent = fmtInt(s.ended);
            document.getElementById('usage-kpi-sold').textContent = fmtInt(s.total_sold);
            document.getElementById('usage-kpi-revenue').textContent = fmtMoney(s.total_revenue);

            // Populate venue filter first time
            const venueSel = document.getElementById('filter-venue');
            if (venueSel.options.length === 1 && d.venues && typeof d.venues === 'object') {
                Object.entries(d.venues).forEach(([id, label]) => {
                    const opt = document.createElement('option');
                    opt.value = id;
                    opt.textContent = label;
                    venueSel.appendChild(opt);
                });
                if (!d.showVenueFilter) venueSel.parentElement.style.display = 'none';
            }

            // Table
            const tbody = document.getElementById('usage-tbody');
            const events = d.events || [];
            if (!events.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="p-8 text-center text-sm text-slate-400">Niciun eveniment</td></tr>';
                return;
            }
            tbody.innerHTML = events.map(ev => {
                const stats = ev.ticket_stats || {};
                const title = (ev.title && (ev.title.ro || ev.title.en)) || ev.name || '—';
                const venueName = (ev.venue && (ev.venue.name && (ev.venue.name.ro || ev.venue.name.en || ev.venue.name))) || '—';
                const city = ev.venue ? ev.venue.city : '';
                const fill = stats.fill_rate || 0;
                const barColor = fill >= 80 ? '#10b981' : fill >= 40 ? '#f59e0b' : '#94a3b8';
                return `
                    <tr class="hover:bg-slate-50/60 cursor-pointer transition-colors group" onclick="window.location.href='/venue/eveniment/${ev.id}'">
                        <td class="px-4 py-3 text-sm text-slate-900 whitespace-nowrap">${fmtDate(ev.event_date)}</td>
                        <td class="px-4 py-3 text-sm font-medium text-slate-900 group-hover:text-blue-700 transition-colors">
                            <span class="inline-flex items-center gap-1.5">${title}
                                <svg class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 transition-opacity text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-500">${venueName}${city ? ' · ' + city : ''}</td>
                        <td class="px-4 py-3 text-sm text-right">
                            <p class="font-semibold text-slate-900">${fmtInt(stats.sold)}<span class="text-slate-500 font-normal">/${fmtInt(stats.capacity)}</span></p>
                            <div class="w-24 h-1.5 mt-1 ml-auto overflow-hidden bg-slate-100 rounded-full">
                                <div class="h-full rounded-full" style="width:${Math.min(100, fill)}%; background:${barColor};"></div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm font-semibold text-right text-slate-900">${fmtMoney(stats.revenue)} RON</td>
                    </tr>
                `;
            }).join('');
        } catch (e) {
            console.error('usage load failed', e);
        }
    }

    document.getElementById('filter-venue').addEventListener('change', reload);
    document.getElementById('filter-status').addEventListener('change', reload);
    reload();
})());
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
