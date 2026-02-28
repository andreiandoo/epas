<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Raport Eveniment';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'events';
$headExtra = '
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<style>
    .stat-card { background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.85) 100%); backdrop-filter: blur(10px); }
    .report-section { page-break-inside: avoid; }
    @media print {
        .no-print { display: none !important; }
        .print-break { page-break-after: always; }
    }
</style>
';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';

// Get event ID from URL
$eventId = $_GET['event'] ?? null;
?>

<!-- Main Content -->
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <!-- Top Bar -->
    <div class="sticky top-0 z-30 bg-white border-b border-gray-200 no-print">
        <div class="px-4 py-3 lg:px-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <!-- Back Button -->
                    <a href="/organizator/events" class="flex items-center gap-2 text-sm text-muted hover:text-secondary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Înapoi la evenimente
                    </a>

                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-400 to-purple-600">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <h1 id="event-title" class="text-lg font-bold text-gray-900">Raport eveniment</h1>
                            <div id="event-info" class="text-xs text-gray-500"></div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <!-- Print Button -->
                    <button onclick="window.print()" class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 transition-all rounded-xl hover:bg-gray-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Printează
                    </button>

                    <!-- Export PDF -->
                    <button onclick="exportReport()" class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-white transition-all rounded-xl bg-primary hover:bg-primary-dark">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Export PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <main class="flex-1 p-4 lg:p-6">
        <!-- Report Header -->
        <div class="p-6 mb-6 bg-gradient-to-br from-indigo-600 to-purple-700 rounded-2xl text-white report-section">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="flex items-center gap-4">
                    <div id="event-image" class="w-20 h-20 rounded-xl bg-white/20 overflow-hidden"></div>
                    <div>
                        <h2 id="report-event-title" class="text-2xl font-bold">Raport Final</h2>
                        <p id="report-event-date" class="text-white/80 text-sm mt-1"></p>
                        <p id="report-event-venue" class="text-white/90 text-sm"></p>
                    </div>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="p-4 bg-white/10 rounded-xl text-center">
                        <div id="summary-revenue" class="text-2xl font-bold">0 lei</div>
                        <div class="text-xs text-white/90">Venituri totale</div>
                    </div>
                    <div class="p-4 bg-white/10 rounded-xl text-center">
                        <div id="summary-tickets" class="text-2xl font-bold">0</div>
                        <div class="text-xs text-white/90">Bilete vândute</div>
                    </div>
                    <div class="p-4 bg-white/10 rounded-xl text-center">
                        <div id="summary-commission" class="text-2xl font-bold">0%</div>
                        <div id="summary-commission-label" class="text-xs text-white/90">Comision</div>
                    </div>
                    <div class="p-4 bg-white/10 rounded-xl text-center">
                        <div id="summary-views" class="text-2xl font-bold">0</div>
                        <div class="text-xs text-white/90">Vizualizări</div>
                    </div>
                    <div class="p-4 bg-white/10 rounded-xl text-center">
                        <div id="summary-conversion" class="text-2xl font-bold">0%</div>
                        <div class="text-xs text-white/90">Rată conversie</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sales Performance Chart -->
        <div class="grid gap-6 mb-6 lg:grid-cols-3 report-section">
            <div class="p-6 bg-white border border-gray-100 shadow-sm lg:col-span-2 rounded-2xl">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Performanță vânzări în timp</h2>
                <div id="salesChart" class="h-[280px]"></div>
            </div>

            <!-- Ticket Distribution -->
            <div class="p-6 bg-white border border-gray-100 shadow-sm rounded-2xl">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Distribuție bilete</h2>
                <div id="ticketChart" class="h-[280px]"></div>
            </div>
        </div>

        <!-- Ticket Types Performance -->
        <div class="p-6 mb-6 bg-white border border-gray-100 shadow-sm rounded-2xl report-section">
            <h2 class="mb-4 text-lg font-semibold text-gray-900">Performanță tipuri bilete</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="pb-3 text-xs font-medium text-left text-gray-500 uppercase">Tip bilet</th>
                            <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Preț</th>
                            <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Vândute</th>
                            <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">Venituri</th>
                            <th class="pb-3 text-xs font-medium text-right text-gray-500 uppercase">% din total</th>
                        </tr>
                    </thead>
                    <tbody id="ticket-types-table">
                        <tr><td colspan="5" class="py-8 text-sm text-center text-gray-400">Se încarcă...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Goals & Milestones -->
        <div class="grid gap-6 mb-6 lg:grid-cols-2 report-section">
            <!-- Goals Achievement -->
            <div class="p-6 bg-white border border-gray-100 shadow-sm rounded-2xl">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Obiective</h2>
                <div id="goals-list" class="space-y-4">
                    <div class="py-6 text-sm text-center text-gray-400">Niciun obiectiv setat</div>
                </div>
            </div>

            <!-- Campaigns/Milestones ROI -->
            <div class="p-6 bg-white border border-gray-100 shadow-sm rounded-2xl">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Campanii marketing</h2>
                <div id="campaigns-list" class="space-y-3">
                    <div class="py-6 text-sm text-center text-gray-400">Nicio campanie înregistrată</div>
                </div>
            </div>
        </div>

        <!-- Traffic & Locations -->
        <div class="grid gap-6 mb-6 lg:grid-cols-2 report-section">
            <!-- Traffic Sources -->
            <div class="p-6 bg-white border border-gray-100 shadow-sm rounded-2xl">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Surse de trafic</h2>
                <div id="traffic-sources" class="space-y-3">
                    <div class="py-6 text-sm text-center text-gray-400">Nu există date</div>
                </div>
            </div>

            <!-- Top Locations -->
            <div class="p-6 bg-white border border-gray-100 shadow-sm rounded-2xl">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Locații top cumpărători</h2>
                <div id="locations-list" class="space-y-3">
                    <div class="py-6 text-sm text-center text-gray-400">Nu există date</div>
                </div>
            </div>
        </div>

        <!-- Refunds Section -->
        <div class="p-6 mb-6 bg-white border border-gray-100 shadow-sm rounded-2xl report-section">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Rambursări</h2>
                <span id="refunds-total" class="text-sm font-medium text-red-600">0 lei</span>
            </div>
            <div id="refunds-list" class="space-y-2">
                <div class="py-4 text-sm text-center text-gray-400">Nicio rambursare</div>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="p-6 bg-gradient-to-br from-slate-800 to-slate-900 text-white rounded-2xl report-section">
            <h2 class="mb-6 text-lg font-semibold">Sumar financiar</h2>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <div class="text-sm text-white/90 mb-1">Venituri brute</div>
                    <div id="financial-gross" class="text-2xl font-bold">0 lei</div>
                </div>
                <div>
                    <div class="text-sm text-white/90 mb-1">Rambursări</div>
                    <div id="financial-refunds" class="text-2xl font-bold text-red-400">-0 lei</div>
                </div>
                <div>
                    <div id="financial-commission-label" class="text-sm text-white/90 mb-1">Comision platformă (5%)</div>
                    <div id="financial-commission" class="text-2xl font-bold text-amber-400">-0 lei</div>
                </div>
                <div>
                    <div class="text-sm text-white/90 mb-1">Venituri nete</div>
                    <div id="financial-net" class="text-2xl font-bold text-emerald-400">0 lei</div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
const eventId = <?= json_encode($eventId) ?>;
let reportData = null;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    if (!eventId) {
        window.location.href = '/organizator/events';
        return;
    }
    loadReport();
});

async function loadReport() {
    try {
        // Load analytics data
        const response = await AmbiletAPI.get(`/organizer/events/${eventId}/analytics?period=all`);
        if (response.success) {
            reportData = response.data;
            updateReport(response.data);
        }

        // Load goals
        try {
            const goalsResponse = await AmbiletAPI.get(`/organizer/events/${eventId}/goals`);
            if (goalsResponse.success) {
                updateGoals(goalsResponse.data);
            }
        } catch (e) { console.log('No goals data'); }

        // Load milestones
        try {
            const milestonesResponse = await AmbiletAPI.get(`/organizer/events/${eventId}/milestones`);
            if (milestonesResponse.success) {
                updateCampaigns(milestonesResponse.data);
            }
        } catch (e) { console.log('No milestones data'); }
    } catch (error) {
        console.error('Error loading report:', error);
    }
}

function updateReport(data) {
    // Event header
    if (data.event) {
        const e = data.event;
        document.getElementById('event-title').textContent = e.title || 'Eveniment';
        document.getElementById('report-event-title').textContent = e.title || 'Eveniment';

        // Format date
        let dateStr = '';
        if (e.starts_at) {
            const d = new Date(e.starts_at);
            dateStr = `${d.getDate()}.${d.getMonth()+1}.${d.getFullYear()}`;
        } else if (e.date) {
            dateStr = e.date;
        }
        document.getElementById('event-info').textContent = dateStr;
        document.getElementById('report-event-date').textContent = dateStr;

        // Venue
        let venueStr = '';
        if (e.venue) {
            venueStr = typeof e.venue === 'object' ? (e.venue.name || '') : e.venue;
        }
        document.getElementById('report-event-venue').textContent = venueStr;

        // Image
        if (e.image) {
            document.getElementById('event-image').innerHTML = `<img src="${e.image}" class="w-full h-full object-cover">`;
        }
    }

    // Summary stats
    if (data.overview) {
        const o = data.overview;
        document.getElementById('summary-revenue').textContent = formatCurrency(o.total_revenue || 0);
        document.getElementById('summary-tickets').textContent = formatNumber(o.tickets_sold || 0);
        document.getElementById('summary-views').textContent = formatNumber(o.page_views || 0);
        document.getElementById('summary-conversion').textContent = (o.conversion_rate || 0).toFixed(1) + '%';

        // Commission rate display
        const commissionRate = o.commission_rate || data.event?.commission_rate || 5;
        const useFixedCommission = o.use_fixed_commission || data.event?.use_fixed_commission || false;
        const commissionMode = o.commission_mode || data.event?.commission_mode || 'included';
        document.getElementById('summary-commission').textContent = commissionRate + '%';
        document.getElementById('summary-commission-label').textContent = useFixedCommission ? 'Comision fix' : 'Comision';

        // Financial summary - calculate based on commission mode
        const refunds = o.refunds_total || 0;
        const grossRevenue = o.total_revenue || 0;

        // Calculate commission amount
        const commission = o.commission_amount || (grossRevenue * (commissionRate / 100));

        // Net revenue depends on commission mode:
        // - "added_on_top": commission paid by customer extra, organizer gets full ticket price
        // - "included": commission deducted from ticket price
        let netRevenue;
        let commissionLabel;
        if (commissionMode === 'added_on_top') {
            // Commission was added on top - organizer receives full revenue
            netRevenue = grossRevenue - refunds;
            commissionLabel = `Comision platformă (${commissionRate}%${useFixedCommission ? ' fix' : ''} +preț)`;
        } else {
            // Commission is included - deducted from revenue
            netRevenue = grossRevenue - refunds - commission;
            commissionLabel = `Comision platformă (${commissionRate}%${useFixedCommission ? ' fix' : ''} inclus)`;
        }

        document.getElementById('financial-gross').textContent = formatCurrency(grossRevenue);
        document.getElementById('financial-refunds').textContent = '-' + formatCurrency(refunds);
        document.getElementById('financial-commission').textContent = commissionMode === 'added_on_top' ? formatCurrency(commission) + ' (plătit de client)' : '-' + formatCurrency(commission);
        document.getElementById('financial-commission-label').textContent = commissionLabel;
        document.getElementById('financial-net').textContent = formatCurrency(netRevenue);
        document.getElementById('refunds-total').textContent = formatCurrency(refunds);
    }

    // Charts
    if (data.chart) {
        renderSalesChart(data.chart);
    }

    // Ticket types
    if (data.ticket_performance) {
        renderTicketTypes(data.ticket_performance);
        renderTicketChart(data.ticket_performance);
    }

    // Traffic sources
    if (data.traffic_sources) {
        renderTrafficSources(data.traffic_sources);
    }

    // Locations
    if (data.top_locations) {
        renderLocations(data.top_locations);
    }

    // Refunds (if available)
    if (data.refunds) {
        renderRefunds(data.refunds);
    }
}

function renderSalesChart(chartData) {
    const options = {
        series: [
            { name: 'Venituri', data: chartData.revenue || [] },
            { name: 'Bilete', data: chartData.tickets || [] }
        ],
        chart: {
            type: 'area',
            height: 280,
            toolbar: { show: false },
            zoom: { enabled: false }
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
        xaxis: {
            categories: chartData.labels || [],
            labels: { style: { colors: '#94a3b8', fontSize: '10px' } }
        },
        yaxis: [
            { title: { text: 'Venituri (RON)' }, labels: { formatter: v => formatNumber(v) } },
            { opposite: true, title: { text: 'Bilete' }, labels: { formatter: v => formatNumber(v) } }
        ],
        tooltip: {
            shared: true,
            y: { formatter: (val, { seriesIndex }) => seriesIndex === 0 ? formatCurrency(val) : formatNumber(val) + ' bilete' }
        },
        colors: ['#10b981', '#3b82f6'],
        legend: { position: 'bottom' }
    };

    new ApexCharts(document.getElementById('salesChart'), options).render();
}

function renderTicketChart(tickets) {
    if (!tickets || tickets.length === 0) return;

    const options = {
        series: tickets.map(t => t.sold || 0),
        labels: tickets.map(t => t.name || 'Bilet'),
        chart: {
            type: 'donut',
            height: 280
        },
        dataLabels: { enabled: true, formatter: (val) => val.toFixed(0) + '%' },
        legend: { position: 'bottom' },
        colors: ['#10b981', '#3b82f6', '#f59e0b', '#ec4899', '#8b5cf6']
    };

    new ApexCharts(document.getElementById('ticketChart'), options).render();
}

function renderTicketTypes(tickets) {
    const tbody = document.getElementById('ticket-types-table');
    if (!tickets || tickets.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-sm text-center text-gray-400">Nu există tipuri de bilete</td></tr>';
        return;
    }

    const totalRevenue = tickets.reduce((sum, t) => sum + (t.revenue || t.price * (t.sold || 0)), 0);
    const colors = ['#10b981', '#3b82f6', '#f59e0b', '#ec4899', '#8b5cf6'];

    const html = tickets.map((t, i) => {
        const revenue = t.revenue || t.price * (t.sold || 0);
        const percent = totalRevenue > 0 ? Math.round((revenue / totalRevenue) * 100) : 0;
        return `
            <tr class="border-b border-gray-50">
                <td class="py-3">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-6 rounded-full" style="background: ${colors[i % colors.length]}"></div>
                        <span class="text-sm font-medium text-gray-800">${t.name}</span>
                    </div>
                </td>
                <td class="py-3 text-sm text-right text-gray-600">${formatCurrency(t.price)}</td>
                <td class="py-3 text-sm font-semibold text-right text-gray-900">${formatNumber(t.sold || 0)}</td>
                <td class="py-3 text-sm font-semibold text-right text-gray-900">${formatCurrency(revenue)}</td>
                <td class="py-3 text-right">
                    <div class="flex items-center justify-end gap-2">
                        <div class="w-16 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full" style="width: ${percent}%; background: ${colors[i % colors.length]}"></div>
                        </div>
                        <span class="text-xs text-gray-500 w-8">${percent}%</span>
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
        container.innerHTML = '<div class="py-6 text-sm text-center text-gray-400">Niciun obiectiv setat</div>';
        return;
    }

    const html = goals.map(g => {
        const progress = Math.min(g.progress_percent || 0, 100);
        const isAchieved = g.is_achieved || progress >= 100;
        const progressColor = isAchieved ? 'bg-emerald-500' : progress >= 75 ? 'bg-blue-500' : progress >= 50 ? 'bg-amber-500' : 'bg-red-400';
        const statusIcon = isAchieved
            ? '<svg class="w-5 h-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>'
            : '<svg class="w-5 h-5 text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>';

        return `
            <div class="p-4 border rounded-xl ${isAchieved ? 'border-emerald-200 bg-emerald-50' : 'border-gray-100'}">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-800">${g.name || g.type_label || g.type}</span>
                    ${statusIcon}
                </div>
                <div class="flex items-baseline gap-1 mb-2">
                    <span class="text-xl font-bold text-gray-900">${g.formatted_current || 0}</span>
                    <span class="text-sm text-gray-500">/ ${g.formatted_target || 0}</span>
                </div>
                <div class="h-2 overflow-hidden bg-gray-100 rounded-full">
                    <div class="h-full transition-all rounded-full ${progressColor}" style="width: ${progress}%"></div>
                </div>
                <div class="mt-2 text-xs ${isAchieved ? 'text-emerald-600 font-medium' : 'text-gray-500'}">
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
        container.innerHTML = '<div class="py-6 text-sm text-center text-gray-400">Nicio campanie înregistrată</div>';
        return;
    }

    const html = campaigns.map(c => {
        const roi = c.budget > 0 ? Math.round(((c.attributed_revenue || 0) - c.budget) / c.budget * 100) : 0;
        const roiColor = roi >= 100 ? 'text-emerald-600' : roi >= 0 ? 'text-blue-600' : 'text-red-600';

        return `
            <div class="p-4 border border-gray-100 rounded-xl">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">${c.type_icon || '📌'}</span>
                        <span class="text-sm font-medium text-gray-800">${c.title || c.name || 'Campanie'}</span>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full ${roi >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}">
                        ${roi >= 0 ? '+' : ''}${roi}% ROI
                    </span>
                </div>
                <div class="grid grid-cols-3 gap-3 text-xs">
                    <div>
                        <div class="text-gray-500">Buget</div>
                        <div class="font-semibold text-gray-900">${formatCurrency(c.budget || 0)}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Venituri</div>
                        <div class="font-semibold text-emerald-600">${formatCurrency(c.attributed_revenue || 0)}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Conversii</div>
                        <div class="font-semibold text-gray-900">${c.conversions || 0}</div>
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
        container.innerHTML = '<div class="py-6 text-sm text-center text-gray-400">Nu există date despre trafic</div>';
        return;
    }

    const total = sources.reduce((sum, s) => sum + (s.visitors || 0), 0);

    const html = sources.map(s => {
        const percent = total > 0 ? Math.round((s.visitors / total) * 100) : 0;
        return `
            <div class="flex items-center justify-between py-2">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-gray-800">${s.source || 'Direct'}</span>
                </div>
                <div class="flex items-center gap-3">
                    <div class="w-24 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full bg-primary" style="width: ${percent}%"></div>
                    </div>
                    <span class="text-sm font-semibold text-gray-900 w-12 text-right">${formatNumber(s.visitors)}</span>
                    <span class="text-xs text-gray-400 w-10">${percent}%</span>
                </div>
            </div>
        `;
    }).join('');
    container.innerHTML = html;
}

function renderLocations(locations) {
    const container = document.getElementById('locations-list');
    if (!locations || locations.length === 0) {
        container.innerHTML = '<div class="py-6 text-sm text-center text-gray-400">Nu există date despre locații</div>';
        return;
    }

    const html = locations.slice(0, 8).map((l, i) => `
        <div class="flex items-center justify-between py-2">
            <div class="flex items-center gap-3">
                <span class="flex items-center justify-center w-6 h-6 text-xs font-semibold text-gray-600 rounded-lg bg-gray-100">${i + 1}</span>
                <span class="text-sm font-medium text-gray-800">${l.city || l.country || 'Necunoscut'}</span>
            </div>
            <span class="text-sm font-semibold text-gray-900">${formatNumber(l.visitors || l.count || 0)}</span>
        </div>
    `).join('');
    container.innerHTML = html;
}

function renderRefunds(refunds) {
    const container = document.getElementById('refunds-list');
    if (!refunds || refunds.length === 0) {
        container.innerHTML = '<div class="py-4 text-sm text-center text-gray-400">Nicio rambursare</div>';
        return;
    }

    const html = refunds.map(r => `
        <div class="flex items-center justify-between py-2 px-3 bg-red-50 rounded-lg">
            <div>
                <div class="text-sm font-medium text-gray-800">${r.buyer_name || 'Client'}</div>
                <div class="text-xs text-gray-500">${r.date || r.created_at || ''}</div>
            </div>
            <div class="text-sm font-semibold text-red-600">-${formatCurrency(r.amount || 0)}</div>
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
        AmbiletNotifications.info('Se genereaza raportul PDF...');

        // Get auth token
        const authToken = localStorage.getItem('organizer_token');
        if (!authToken) {
            AmbiletNotifications.error('Sesiune expirata. Te rugam sa te autentifici din nou.');
            return;
        }

        // Fetch PDF with authentication
        const response = await fetch(`/api/marketplace-client/organizer/events/${eventId}/report/export`, {
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

        // Get filename from Content-Disposition header or use default
        const contentDisposition = response.headers.get('Content-Disposition');
        let filename = `raport-eveniment-${eventId}.pdf`;
        if (contentDisposition) {
            const match = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
            if (match && match[1]) {
                filename = match[1].replace(/['"]/g, '');
            }
        }

        // Create blob and download
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);

        AmbiletNotifications.success('Raportul a fost descarcat');
    } catch (error) {
        console.error('Export error:', error);
        AmbiletNotifications.error(error.message || 'Eroare la generarea raportului PDF');
    }
}
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
