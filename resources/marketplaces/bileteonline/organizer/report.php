<?php
/**
 * bilete.online — Organizator › Raport activitate (v3).
 * Route: /organizator/report/{id}  (→ report.php?event={id})
 *
 * Single-activity report view: summary KPIs, sales-over-time + ticket
 * distribution charts (ApexCharts), ticket-type performance table, goals,
 * marketing campaigns, traffic sources, top buyer locations, refunds and a
 * financial summary. Includes print + PDF export. Activity-centric port of the
 * ambilet organizer report page, restyled to the bilete.online v3 design and
 * wired to BileteOnlineAPI.
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Raport activitate';
$currentPage = 'events';

// Get activity ID from URL
$eventId = $_GET['event'] ?? null;

// ApexCharts (loaded via the real $extraHead hook that head.php emits).
$extraHead = '<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>'
    . "\n<style>"
    . '.report-section{page-break-inside:avoid;}'
    . '@media print{.no-print{display:none!important;}.print-break{page-break-after:always;}'
    . 'body{background:#fff;}}'
    . '</style>';

require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <!-- Report header bar -->
    <div class="sticky top-0 z-20 border-b-2 border-ink bg-paper no-print">
        <div class="px-4 py-3 lg:px-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <a href="/organizator/panou" class="inline-flex items-center gap-2 text-sm font-bold text-ink-soft transition hover:text-ink">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Înapoi
                    </a>
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-vermilion text-paper">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <h1 id="event-title" class="font-display text-lg font-bold leading-none">Raport activitate</h1>
                            <div id="event-info" class="mt-1 text-xs text-ink-soft"></div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Printează
                    </button>
                    <button onclick="exportReport()" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Export PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <main class="flex-1 p-4 lg:p-8">
        <!-- Summary Cards -->
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-2xl border-2 border-ink bg-paper p-4 text-center">
                <div id="summary-revenue" class="font-display text-2xl font-bold">0 lei</div>
                <div class="mt-1 font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Venituri totale</div>
            </div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-4 text-center">
                <div id="summary-tickets" class="font-display text-2xl font-bold">0</div>
                <div class="mt-1 font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Bilete vândute</div>
            </div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-4 text-center">
                <div id="summary-views" class="font-display text-2xl font-bold">0</div>
                <div class="mt-1 font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Vizualizări</div>
            </div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-4 text-center">
                <div id="summary-conversion" class="font-display text-2xl font-bold">0%</div>
                <div class="mt-1 font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Rată conversie</div>
            </div>
        </div>

        <!-- Sales Performance Chart -->
        <div class="report-section mb-6 grid gap-6 lg:grid-cols-3">
            <div class="rounded-2xl border-2 border-ink bg-paper p-6 lg:col-span-2">
                <h2 class="mb-4 font-display text-lg font-bold">Performanță vânzări în timp</h2>
                <div id="salesChart" class="h-[280px]"></div>
            </div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                <h2 class="mb-4 font-display text-lg font-bold">Distribuție bilete</h2>
                <div id="ticketChart" class="h-[280px]"></div>
            </div>
        </div>

        <!-- Ticket Types Performance -->
        <div class="report-section mb-6 rounded-2xl border-2 border-ink bg-paper p-6">
            <h2 class="mb-4 font-display text-lg font-bold">Performanță tipuri bilete</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-dashed border-ink/15">
                            <th class="pb-3 text-left font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Tip bilet</th>
                            <th class="pb-3 text-right font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Preț</th>
                            <th class="pb-3 text-right font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Vândute</th>
                            <th class="pb-3 text-right font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Venituri</th>
                            <th class="pb-3 text-right font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">% din total</th>
                        </tr>
                    </thead>
                    <tbody id="ticket-types-table">
                        <tr><td colspan="5" class="py-8 text-center text-sm text-ink-soft">Se încarcă…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Goals & Campaigns -->
        <div class="report-section mb-6 grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                <h2 class="mb-4 font-display text-lg font-bold">Obiective</h2>
                <div id="goals-list" class="space-y-4">
                    <div class="py-6 text-center text-sm text-ink-soft">Niciun obiectiv setat</div>
                </div>
            </div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                <h2 class="mb-4 font-display text-lg font-bold">Campanii marketing</h2>
                <div id="campaigns-list" class="space-y-3">
                    <div class="py-6 text-center text-sm text-ink-soft">Nicio campanie înregistrată</div>
                </div>
            </div>
        </div>

        <!-- Traffic & Locations -->
        <div class="report-section mb-6 grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                <h2 class="mb-4 font-display text-lg font-bold">Surse de trafic</h2>
                <div id="traffic-sources" class="space-y-3">
                    <div class="py-6 text-center text-sm text-ink-soft">Nu există date</div>
                </div>
            </div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                <h2 class="mb-4 font-display text-lg font-bold">Locații top cumpărători</h2>
                <div id="locations-list" class="space-y-3">
                    <div class="py-6 text-center text-sm text-ink-soft">Nu există date</div>
                </div>
            </div>
        </div>

        <!-- Refunds -->
        <div class="report-section mb-6 rounded-2xl border-2 border-ink bg-paper p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-display text-lg font-bold">Rambursări</h2>
                <span id="refunds-total" class="text-sm font-bold text-vermilion">0 lei</span>
            </div>
            <div id="refunds-list" class="space-y-2">
                <div class="py-4 text-center text-sm text-ink-soft">Nicio rambursare</div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="report-section rounded-2xl border-2 border-ink bg-ink p-6 text-paper">
            <h2 class="mb-6 font-display text-lg font-bold">Sumar financiar</h2>
            <div class="grid grid-cols-2 gap-6 lg:grid-cols-4">
                <div>
                    <div class="mb-1 text-sm text-paper/80">Venituri brute</div>
                    <div id="financial-gross" class="font-display text-2xl font-bold">0 lei</div>
                </div>
                <div>
                    <div class="mb-1 text-sm text-paper/80">Rambursări</div>
                    <div id="financial-refunds" class="font-display text-2xl font-bold text-vermilion">-0 lei</div>
                </div>
                <div>
                    <div id="financial-commission-label" class="mb-1 text-sm text-paper/80">Comision platformă</div>
                    <div id="financial-commission" class="font-display text-2xl font-bold text-ochre">-0 lei</div>
                </div>
                <div>
                    <div class="mb-1 text-sm text-paper/80">Venituri nete</div>
                    <div id="financial-net" class="font-display text-2xl font-bold text-forest">0 lei</div>
                </div>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<?php
$scriptsExtra  = "<script>\nconst eventId = " . json_encode($eventId) . ";\n</script>\n";
$scriptsExtra .= <<<'JS'
<script>
let reportData = null;

function orgNotify(msg, type) {
    try {
        if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) {
            BileteOnlineNotifications[type || 'info'](msg);
            return;
        }
    } catch (e) {}
    if (type === 'error' || type === 'warning') alert(msg);
}

// V3 chart palette
const CHART_COLORS = ['#1E4A3D', '#2C5F8A', '#DA9A33', '#E84527', '#5A4F46'];
const CHART_GRID = '#E8DFCF';
const CHART_TEXT = '#5A4F46';

document.addEventListener('DOMContentLoaded', () => {
    if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth && !BileteOnlineAuth.requireOrganizerAuth()) return;
    if (!eventId) {
        window.location.href = '/organizator/panou';
        return;
    }
    loadReport();
});

async function loadReport() {
    try {
        const response = await BileteOnlineAPI.get(`/organizer/events/${eventId}/analytics?period=all`);
        if (response.success) {
            const data = response.data || {};
            if (response.event && !data.event) data.event = response.event;
            if (data.chart_data && !data.chart) {
                const cd = data.chart_data;
                data.chart = {
                    labels: cd.map(d => d.date),
                    raw_dates: cd.map(d => d.raw_date),
                    revenue: cd.map(d => d.revenue || 0),
                    tickets: cd.map(d => d.tickets || 0),
                    visits: cd.map(d => d.visits || 0),
                };
            }
            reportData = data;
            updateReport(data);
        }

        try {
            const goalsResponse = await BileteOnlineAPI.get(`/organizer/events/${eventId}/goals`);
            if (goalsResponse.success) {
                updateGoals(goalsResponse.data);
            }
        } catch (e) { console.log('No goals data'); }

        try {
            const milestonesResponse = await BileteOnlineAPI.get(`/organizer/events/${eventId}/milestones`);
            if (milestonesResponse.success) {
                updateCampaigns(milestonesResponse.data);
            }
        } catch (e) { console.log('No milestones data'); }
    } catch (error) {
        console.error('Error loading report:', error);
    }
}

function updateReport(data) {
    if (data.event) {
        const e = data.event;
        document.getElementById('event-title').textContent = e.title || 'Activitate';

        let dateStr = '';
        if (e.starts_at) {
            const d = new Date(e.starts_at);
            dateStr = `${d.getDate()}.${d.getMonth()+1}.${d.getFullYear()}`;
        } else if (e.date) {
            dateStr = e.date;
        }
        let venueStr = '';
        if (e.venue) {
            venueStr = typeof e.venue === 'object' ? (e.venue.name || '') : e.venue;
        }
        document.getElementById('event-info').textContent = [dateStr, venueStr].filter(Boolean).join(' · ');
    }

    if (data.overview) {
        const o = data.overview;
        document.getElementById('summary-revenue').textContent = formatCurrency(o.net_revenue ?? o.total_revenue ?? 0);
        document.getElementById('summary-tickets').textContent = formatNumber(o.tickets_sold || 0);
        document.getElementById('summary-views').textContent = formatNumber(o.page_views || 0);
        document.getElementById('summary-conversion').textContent = (o.conversion_rate || 0).toFixed(1) + '%';

        const commissionRate = o.commission_rate || data.event?.commission_rate || 5;
        const useFixedCommission = o.use_fixed_commission || data.event?.use_fixed_commission || false;
        const commissionMode = o.commission_mode || data.event?.commission_mode || 'included';

        const grossRevenue = o.gross_revenue ?? o.total_revenue ?? 0;
        const refunds = o.refunds_total || 0;
        const commission = o.commission_amount ?? (grossRevenue * (commissionRate / 100));
        const netRevenue = o.net_revenue ?? (grossRevenue - refunds - commission);

        let commissionLabel;
        if (commissionMode === 'added_on_top') {
            commissionLabel = `Comision platformă (${commissionRate}%${useFixedCommission ? ' fix' : ''} +preț)`;
        } else {
            commissionLabel = `Comision platformă`;
        }

        document.getElementById('financial-gross').textContent = formatCurrency(grossRevenue);
        document.getElementById('financial-refunds').textContent = '-' + formatCurrency(refunds);
        document.getElementById('financial-commission').textContent = commissionMode === 'added_on_top' ? formatCurrency(commission) + ' (plătit de client)' : '-' + formatCurrency(commission);
        document.getElementById('financial-commission-label').textContent = commissionLabel;
        document.getElementById('financial-net').textContent = formatCurrency(netRevenue);
        document.getElementById('refunds-total').textContent = formatCurrency(refunds);
    }

    if (data.chart) {
        renderSalesChart(data.chart, data.event);
    }

    if (data.ticket_performance) {
        renderTicketTypes(data.ticket_performance);
        renderTicketChart(data.ticket_performance);
    }

    if (data.traffic_sources) {
        renderTrafficSources(data.traffic_sources);
    }

    if (data.top_locations) {
        renderLocations(data.top_locations);
    }

    if (data.refunds) {
        renderRefunds(data.refunds);
    }
}

function renderSalesChart(chartData, eventData) {
    if (typeof ApexCharts === 'undefined') return;
    let labels = chartData.labels || [];
    let rawDates = chartData.raw_dates || [];
    let revenue = chartData.revenue || [];
    let tickets = chartData.tickets || [];

    if (eventData) {
        const endDate = eventData.ends_at || eventData.starts_at;
        if (endDate && rawDates.length > 0) {
            const eventEnd = new Date(endDate);
            eventEnd.setHours(23, 59, 59);
            const cutoffIdx = rawDates.findIndex(d => new Date(d) > eventEnd);
            if (cutoffIdx > 0) {
                labels = labels.slice(0, cutoffIdx);
                rawDates = rawDates.slice(0, cutoffIdx);
                revenue = revenue.slice(0, cutoffIdx);
                tickets = tickets.slice(0, cutoffIdx);
            }
        }
    }

    const tooltipLabels = rawDates.map(d => {
        if (!d) return '';
        const dt = new Date(d);
        return dt.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
    });

    const options = {
        series: [
            { name: 'Venituri', data: revenue },
            { name: 'Bilete', data: tickets }
        ],
        chart: {
            type: 'area',
            height: 280,
            fontFamily: 'inherit',
            toolbar: { show: false },
            zoom: { enabled: false }
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
        grid: { borderColor: CHART_GRID },
        xaxis: {
            categories: labels,
            labels: { style: { colors: CHART_TEXT, fontSize: '10px' } }
        },
        yaxis: [
            { title: { text: 'Venituri (RON)', style: { color: CHART_TEXT } }, labels: { style: { colors: CHART_TEXT }, formatter: v => formatNumber(v) } },
            { opposite: true, title: { text: 'Bilete', style: { color: CHART_TEXT } }, labels: { style: { colors: CHART_TEXT }, formatter: v => formatNumber(v) } }
        ],
        tooltip: {
            shared: true,
            x: {
                formatter: (val, { dataPointIndex }) => tooltipLabels[dataPointIndex] || val
            },
            y: { formatter: (val, { seriesIndex }) => seriesIndex === 0 ? formatCurrency(val) : formatNumber(val) + ' bilete' }
        },
        colors: ['#1E4A3D', '#2C5F8A'],
        legend: { position: 'bottom', labels: { colors: CHART_TEXT } }
    };

    new ApexCharts(document.getElementById('salesChart'), options).render();
}

function renderTicketChart(tickets) {
    if (typeof ApexCharts === 'undefined') return;
    if (!tickets || tickets.length === 0) return;

    const options = {
        series: tickets.map(t => t.sold || 0),
        labels: tickets.map(t => t.name || 'Bilet'),
        chart: {
            type: 'donut',
            height: 280,
            fontFamily: 'inherit'
        },
        dataLabels: { enabled: true, formatter: (val) => val.toFixed(0) + '%' },
        legend: { position: 'bottom', labels: { colors: CHART_TEXT } },
        colors: CHART_COLORS
    };

    new ApexCharts(document.getElementById('ticketChart'), options).render();
}

function renderTicketTypes(tickets) {
    const tbody = document.getElementById('ticket-types-table');
    if (!tickets || tickets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-sm text-ink-soft">Nu există tipuri de bilete</td></tr>';
        return;
    }

    tickets = [...tickets].sort((a, b) => (b.sold || 0) - (a.sold || 0));

    const totalRevenue = tickets.reduce((sum, t) => sum + (t.revenue || t.price * (t.sold || 0)), 0);

    const html = tickets.map((t, i) => {
        const revenue = t.revenue || t.price * (t.sold || 0);
        const percent = totalRevenue > 0 ? Math.round((revenue / totalRevenue) * 100) : 0;
        const color = CHART_COLORS[i % CHART_COLORS.length];
        let nameSuffix = '';
        if (t.is_invitation) {
            nameSuffix = ' <span class="text-xs font-normal text-ink-soft">(titlu gratuit)</span>';
        } else if (t.is_entry_ticket) {
            nameSuffix = ' <span class="text-xs font-normal text-ink-soft">(încasat de organizator)</span>';
        }
        return `
            <tr class="border-b border-ink/10">
                <td class="py-3">
                    <div class="flex items-center gap-2">
                        <div class="h-6 w-2 rounded-full" style="background: ${color}"></div>
                        <span class="text-sm font-medium">${t.name}${nameSuffix}</span>
                    </div>
                </td>
                <td class="py-3 text-right text-sm text-ink-soft">${formatCurrency(t.price)}</td>
                <td class="py-3 text-right text-sm font-bold">${formatNumber(t.sold || 0)}</td>
                <td class="py-3 text-right text-sm font-bold">${formatCurrency(revenue)}</td>
                <td class="py-3 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <div class="h-1.5 w-16 overflow-hidden rounded-full bg-ink/10">
                            <div class="h-full rounded-full" style="width: ${percent}%; background: ${color}"></div>
                        </div>
                        <span class="w-8 text-xs text-ink-soft">${percent}%</span>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
    tbody.innerHTML = html;
}

function updateGoals(data) {
    const container = document.getElementById('goals-list');
    const goals = data.goals || data;

    if (!goals || goals.length === 0) {
        container.innerHTML = '<div class="py-6 text-center text-sm text-ink-soft">Niciun obiectiv setat</div>';
        return;
    }

    const html = goals.map(g => {
        const progress = Math.min(g.progress_percent || 0, 100);
        const isAchieved = g.is_achieved || progress >= 100;
        const progressColor = isAchieved ? 'bg-forest' : progress >= 75 ? 'bg-sky' : progress >= 50 ? 'bg-ochre' : 'bg-vermilion';
        const statusIcon = isAchieved
            ? '<svg class="h-5 w-5 text-forest" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>'
            : '<svg class="h-5 w-5 text-ink/30" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>';

        return `
            <div class="rounded-xl border-2 p-4 ${isAchieved ? 'border-forest/40 bg-forest/5' : 'border-ink/15'}">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm font-bold">${g.name || g.type_label || g.type}</span>
                    ${statusIcon}
                </div>
                <div class="mb-2 flex items-baseline gap-1">
                    <span class="font-display text-xl font-bold">${g.formatted_current || 0}</span>
                    <span class="text-sm text-ink-soft">/ ${g.formatted_target || 0}</span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-ink/10">
                    <div class="h-full rounded-full transition-all ${progressColor}" style="width: ${progress}%"></div>
                </div>
                <div class="mt-2 text-xs ${isAchieved ? 'font-bold text-forest' : 'text-ink-soft'}">
                    ${isAchieved ? 'Obiectiv atins!' : progress.toFixed(1) + '% completat'}
                </div>
            </div>
        `;
    }).join('');
    container.innerHTML = html;
}

function updateCampaigns(data) {
    const container = document.getElementById('campaigns-list');
    const campaigns = data.milestones || data;

    if (!campaigns || campaigns.length === 0) {
        container.innerHTML = '<div class="py-6 text-center text-sm text-ink-soft">Nicio campanie înregistrată</div>';
        return;
    }

    const html = campaigns.map(c => {
        const roi = c.budget > 0 ? Math.round(((c.attributed_revenue || 0) - c.budget) / c.budget * 100) : 0;
        const roiBadge = roi >= 0 ? 'bg-forest/15 text-forest' : 'bg-vermilion/10 text-vermilion';

        return `
            <div class="rounded-xl border-2 border-ink/15 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">${c.type_icon || '📌'}</span>
                        <span class="text-sm font-bold">${c.title || c.name || 'Campanie'}</span>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-xs font-bold ${roiBadge}">
                        ${roi >= 0 ? '+' : ''}${roi}% ROI
                    </span>
                </div>
                <div class="grid grid-cols-3 gap-3 text-xs">
                    <div>
                        <div class="text-ink-soft">Buget</div>
                        <div class="font-bold">${formatCurrency(c.budget || 0)}</div>
                    </div>
                    <div>
                        <div class="text-ink-soft">Venituri</div>
                        <div class="font-bold text-forest">${formatCurrency(c.attributed_revenue || 0)}</div>
                    </div>
                    <div>
                        <div class="text-ink-soft">Conversii</div>
                        <div class="font-bold">${c.conversions || 0}</div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
    container.innerHTML = html;
}

function renderTrafficSources(sources) {
    const container = document.getElementById('traffic-sources');
    if (!sources || sources.length === 0) {
        container.innerHTML = '<div class="py-6 text-center text-sm text-ink-soft">Nu există date despre trafic</div>';
        return;
    }

    const total = sources.reduce((sum, s) => sum + (s.visitors || 0), 0);

    const html = sources.map(s => {
        const percent = total > 0 ? Math.round((s.visitors / total) * 100) : 0;
        return `
            <div class="flex items-center justify-between py-2">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium">${s.source || 'Direct'}</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="h-1.5 w-24 overflow-hidden rounded-full bg-ink/10">
                        <div class="h-full rounded-full bg-vermilion" style="width: ${percent}%"></div>
                    </div>
                    <span class="w-12 text-right text-sm font-bold">${formatNumber(s.visitors)}</span>
                    <span class="w-10 text-xs text-ink-soft">${percent}%</span>
                </div>
            </div>
        `;
    }).join('');
    container.innerHTML = html;
}

function renderLocations(locations) {
    const container = document.getElementById('locations-list');
    if (!locations || locations.length === 0) {
        container.innerHTML = '<div class="py-6 text-center text-sm text-ink-soft">Nu există date despre locații</div>';
        return;
    }

    const html = locations.slice(0, 8).map((l, i) => `
        <div class="flex items-center justify-between py-2">
            <div class="flex items-center gap-3">
                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-ink/10 text-xs font-bold text-ink-soft">${i + 1}</span>
                <span class="text-sm font-medium">${l.city || l.country || 'Necunoscut'}</span>
            </div>
            <span class="text-sm font-bold">${formatNumber(l.visitors || l.count || 0)}</span>
        </div>
    `).join('');
    container.innerHTML = html;
}

function renderRefunds(refunds) {
    const container = document.getElementById('refunds-list');
    if (!refunds || refunds.length === 0) {
        container.innerHTML = '<div class="py-4 text-center text-sm text-ink-soft">Nicio rambursare</div>';
        return;
    }

    const html = refunds.map(r => `
        <div class="flex items-center justify-between rounded-lg bg-vermilion/5 px-3 py-2">
            <div>
                <div class="text-sm font-bold">${r.buyer_name || 'Client'}</div>
                <div class="text-xs text-ink-soft">${r.date || r.created_at || ''}</div>
            </div>
            <div class="text-sm font-bold text-vermilion">-${formatCurrency(r.amount || 0)}</div>
        </div>
    `).join('');
    container.innerHTML = html;
}

function formatCurrency(value) {
    return new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value || 0) + ' lei';
}

function formatNumber(value) {
    return new Intl.NumberFormat('ro-RO').format(value || 0);
}

async function exportReport() {
    try {
        orgNotify('Se generează raportul PDF…', 'info');

        const authToken = (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken)
            ? BileteOnlineAuth.getToken()
            : null;
        if (!authToken) {
            orgNotify('Sesiune expirată. Te rugăm să te autentifici din nou.', 'error');
            return;
        }

        const base = (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php';
        const response = await fetch(`${base}?action=organizer.event.report.export&event_id=${encodeURIComponent(eventId)}`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${authToken}`,
                'Accept': 'application/pdf'
            }
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || 'Eroare la generarea raportului');
        }

        const contentDisposition = response.headers.get('Content-Disposition');
        let filename = `raport-activitate-${eventId}.pdf`;
        if (contentDisposition) {
            const match = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
            if (match && match[1]) {
                filename = match[1].replace(/['"]/g, '');
            }
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);

        orgNotify('Raportul a fost descărcat', 'success');
    } catch (error) {
        console.error('Export error:', error);
        orgNotify(error.message || 'Eroare la generarea raportului PDF', 'error');
    }
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
