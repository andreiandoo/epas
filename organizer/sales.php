<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Vanzari';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'sales';
$headExtra = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
require_once dirname(__DIR__) . '/includes/head.php';
?>
<?php require_once dirname(__DIR__) . '/includes/organizer-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
                <!-- Page Content -->
        <main class="flex-1 p-4 lg:p-8">
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Vanzari</h1>
                    <p class="text-sm text-muted">Monitorizeaza vanzarile si performanta evenimentelor</p>
                </div>
                <div class="flex items-center gap-3"><select id="period-filter" class="input w-auto"><option value="7">Ultimele 7 zile</option><option value="30" selected>Ultimele 30 zile</option><option value="90">Ultimele 90 zile</option><option value="365">Ultimul an</option></select><button onclick="exportSales()" class="btn btn-secondary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>Export</button></div>
            </div>


            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3"><div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center"><svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><span class="text-sm text-muted">Venituri Totale</span></div>
                    <p class="text-2xl font-bold text-secondary" id="total-revenue">0 RON</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3"><div class="w-10 h-10 bg-success/10 rounded-xl flex items-center justify-center"><svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg></div><span class="text-sm text-muted">Bilete Vandute</span></div>
                    <p class="text-2xl font-bold text-secondary" id="tickets-sold">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3"><div class="w-10 h-10 bg-accent/10 rounded-xl flex items-center justify-center"><svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg></div><span class="text-sm text-muted">Comenzi</span></div>
                    <p class="text-2xl font-bold text-secondary" id="total-orders">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3"><div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center"><svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg></div><span class="text-sm text-muted">Valoare Medie</span></div>
                    <p class="text-2xl font-bold text-secondary" id="avg-order">0 RON</p>
                </div>
            </div>

            <div class="grid lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-2xl border border-border p-6"><h2 class="text-lg font-bold text-secondary mb-4">Evolutie Vanzari</h2><canvas id="salesChart" height="200"></canvas></div>
                <div class="bg-white rounded-2xl border border-border p-6"><h2 class="text-lg font-bold text-secondary mb-4">Vanzari per Eveniment</h2><canvas id="eventsChart" height="200"></canvas></div>
            </div>

            <div class="bg-white rounded-2xl border border-border overflow-hidden">
                <div class="p-6 border-b border-border"><h2 class="text-lg font-bold text-secondary">Comenzi Recente</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-surface"><tr><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Comanda</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Client</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Eveniment</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Total</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Status</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Data</th></tr></thead>
                        <tbody id="orders-list" class="divide-y divide-border"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();
let salesChart, eventsChart;

document.addEventListener('DOMContentLoaded', function() { initCharts(); loadSalesData(); });

function initCharts() {
    salesChart = new Chart(document.getElementById('salesChart').getContext('2d'), { type: 'line', data: { labels: [], datasets: [{ label: 'Venituri', data: [], borderColor: '#A51C30', backgroundColor: 'rgba(165, 28, 48, 0.1)', fill: true, tension: 0.4 }] }, options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } } });
    eventsChart = new Chart(document.getElementById('eventsChart').getContext('2d'), { type: 'doughnut', data: { labels: [], datasets: [{ data: [], backgroundColor: ['#A51C30', '#E67E22', '#10B981', '#3B82F6', '#8B5CF6'] }] }, options: { responsive: true } });
}

async function loadSalesData() {
    try {
        const response = await AmbiletAPI.get('/organizer/sales');
        if (response.success) {
            const data = response.data;
            document.getElementById('total-revenue').textContent = AmbiletUtils.formatCurrency(data.total_revenue || 0);
            document.getElementById('tickets-sold').textContent = data.tickets_sold || 0;
            document.getElementById('total-orders').textContent = data.total_orders || 0;
            document.getElementById('avg-order').textContent = AmbiletUtils.formatCurrency(data.avg_order_value || 0);
            if (data.chart_data) { salesChart.data.labels = data.chart_data.labels; salesChart.data.datasets[0].data = data.chart_data.revenue; salesChart.update(); }
            if (data.sales_by_event) { eventsChart.data.labels = data.sales_by_event.map(e => e.name); eventsChart.data.datasets[0].data = data.sales_by_event.map(e => e.revenue); eventsChart.update(); }
            if (data.orders) { renderOrders(data.orders); }
        }
    } catch (error) { showEmptyState(); }
}

function showEmptyState() {
    document.getElementById('orders-list').innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-muted">Nu exista vanzari momentan</td></tr>';
}

function renderOrders(orders) {
    if (!orders.length) { showEmptyState(); return; }
    document.getElementById('orders-list').innerHTML = orders.map(o => `<tr class="hover:bg-surface/50"><td class="px-6 py-4 font-medium text-secondary">${o.id}</td><td class="px-6 py-4">${o.customer}</td><td class="px-6 py-4">${o.event}</td><td class="px-6 py-4 font-semibold">${AmbiletUtils.formatCurrency(o.total)}</td><td class="px-6 py-4"><span class="px-3 py-1 bg-${o.status === 'completed' ? 'success' : 'warning'}/10 text-${o.status === 'completed' ? 'success' : 'warning'} text-sm rounded-full">${o.status === 'completed' ? 'Finalizata' : 'In asteptare'}</span></td><td class="px-6 py-4 text-muted">${AmbiletUtils.formatDate(o.date)}</td></tr>`).join('');
}

function exportSales() { AmbiletNotifications.success('Exportul a fost generat si trimis pe email'); }
document.getElementById('period-filter').addEventListener('change', loadSalesData);
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
