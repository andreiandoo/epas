<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Raport Staff';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'events';
$headExtra = '
<style>
    .stat-card { background: linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.85) 100%); backdrop-filter: blur(10px); }
    .report-section { page-break-inside: avoid; }
    @media print {
        .no-print { display: none !important; }
        .print-break { page-break-after: always; }
    }
</style>
';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';

// Get event ID from URL (?event=... matches the same convention as
// /organizator/sold, /organizator/participanti, etc.)
$eventId = $_GET['event'] ?? null;
?>

<!-- Main Content -->
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <!-- Top Bar -->
    <div class="sticky z-30 bg-white border-b border-gray-200 top-16 no-print">
        <div class="px-4 py-3 lg:px-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex items-center gap-4">
                    <a href="/organizator/events" class="flex flex-col items-start text-sm text-muted hover:text-secondary">
                        <span class="text-xl font-bold text-secondary">Raport staff</span>
                        <span class="flex items-center gap-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                            Înapoi
                        </span>
                    </a>

                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-cyan-600">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <div>
                            <h1 id="event-title" class="text-lg font-bold text-gray-900">Raport staff</h1>
                            <div id="event-info" class="text-xs text-gray-500"></div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button onclick="window.print()" class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-200 transition-all rounded-xl hover:bg-gray-50">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Printează
                    </button>
                </div>
            </div>
        </div>
    </div>

    <main class="flex-1 p-4 lg:p-6">
        <!-- Loading state -->
        <div id="loading-state" class="py-16 text-center text-muted">
            <svg class="w-10 h-10 mx-auto mb-3 animate-spin text-primary" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
            <p class="text-sm">Se încarcă raportul...</p>
        </div>

        <!-- Empty state -->
        <div id="empty-state" class="hidden py-16 text-center text-muted">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            <h3 class="text-base font-semibold text-secondary">Niciun bilet vândut</h3>
            <p class="mt-1 text-sm">Acest eveniment nu are încă vânzări înregistrate.</p>
        </div>

        <!-- Content -->
        <div id="content-state" class="hidden space-y-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-5 report-section">
                <div class="p-4 bg-white border border-gray-100 shadow-sm rounded-2xl">
                    <div class="text-xs font-medium text-gray-500 uppercase">Venituri din bilete</div>
                    <div id="sum-revenue" class="mt-1 text-2xl font-bold text-secondary">0 lei</div>
                    <div class="mt-1 text-[10px] leading-tight text-gray-400">Fără asigurări / taxe online — vezi Vânzări pentru gross-ul total.</div>
                </div>
                <div class="p-4 bg-white border border-gray-100 shadow-sm rounded-2xl">
                    <div class="text-xs font-medium text-gray-500 uppercase">Bilete vândute</div>
                    <div id="sum-tickets" class="mt-1 text-2xl font-bold text-secondary">0</div>
                </div>
                <div class="p-4 border shadow-sm bg-emerald-50 border-emerald-100 rounded-2xl">
                    <div class="text-xs font-medium uppercase text-emerald-700">Cash</div>
                    <div id="sum-cash" class="mt-1 text-2xl font-bold text-emerald-700">0 lei</div>
                </div>
                <div class="p-4 bg-blue-50 border border-blue-100 shadow-sm rounded-2xl">
                    <div class="text-xs font-medium text-blue-700 uppercase">Card POS</div>
                    <div id="sum-card" class="mt-1 text-2xl font-bold text-blue-700">0 lei</div>
                </div>
                <div class="p-4 border shadow-sm bg-violet-50 border-violet-100 rounded-2xl">
                    <div class="text-xs font-medium uppercase text-violet-700">Online</div>
                    <div id="sum-online" class="mt-1 text-2xl font-bold text-violet-700">0 lei</div>
                </div>
            </div>

            <!-- Staff Members Table -->
            <div class="bg-white border border-gray-100 shadow-sm rounded-2xl report-section">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-900">Vânzări per membru staff</h2>
                    <p class="mt-1 text-xs text-gray-500">Sortat după venituri, descrescător. „Online" cumulează vânzările din site-ul public.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-xs font-medium text-left text-gray-500 uppercase bg-gray-50">
                                <th class="px-6 py-3">Membru staff</th>
                                <th class="px-3 py-3 text-right">Comenzi</th>
                                <th class="px-3 py-3 text-right">Bilete</th>
                                <th class="px-3 py-3 text-right">Cash</th>
                                <th class="px-3 py-3 text-right">Card POS</th>
                                <th class="px-3 py-3 text-right">Online</th>
                                <th class="px-3 py-3 text-right">Total</th>
                                <th class="px-3 py-3 text-right">Detalii</th>
                            </tr>
                        </thead>
                        <tbody id="staff-table-body" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>

            <!-- Overall ticket types performance -->
            <div class="bg-white border border-gray-100 shadow-sm rounded-2xl report-section">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-900">Vânzări per tip de bilet (total)</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-xs font-medium text-left text-gray-500 uppercase bg-gray-50">
                                <th class="px-6 py-3">Tip bilet</th>
                                <th class="px-3 py-3 text-right">Bilete vândute</th>
                                <th class="px-3 py-3 text-right">Venituri</th>
                                <th class="px-3 py-3 text-right">% din total</th>
                            </tr>
                        </thead>
                        <tbody id="ticket-types-body" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
const eventId = <?= json_encode($eventId) ?>;
let reportData = null;

document.addEventListener('DOMContentLoaded', () => {
    if (!eventId) {
        window.location.href = '/organizator/events';
        return;
    }
    loadReport();
});

function formatLei(amount) {
    const v = Number(amount || 0);
    return v.toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' lei';
}

function escapeHtml(s) {
    const div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
}

async function loadReport() {
    try {
        const response = await AmbiletAPI.get(`/organizer/events/${eventId}/staff-report`);
        if (!response || !response.success) {
            throw new Error(response?.message || 'Eroare la încărcarea raportului');
        }
        reportData = response.data;
        renderReport(reportData);
    } catch (err) {
        console.error('Failed to load staff report:', err);
        const loading = document.getElementById('loading-state');
        loading.innerHTML = '<div class="text-sm text-red-600"><strong>Eroare:</strong> ' + escapeHtml(err.message || 'Nu s-a putut încărca raportul.') + '</div>';
    }
}

function renderReport(data) {
    const loading = document.getElementById('loading-state');
    const content = document.getElementById('content-state');
    const empty = document.getElementById('empty-state');

    loading.classList.add('hidden');

    // Header info
    const titleEl = document.getElementById('event-title');
    const infoEl = document.getElementById('event-info');
    if (data.event) {
        titleEl.textContent = data.event.title || 'Raport staff';
        const bits = [];
        if (data.event.date) {
            const d = new Date(data.event.date);
            if (!isNaN(d.getTime())) {
                bits.push(d.toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' }));
            }
        }
        infoEl.textContent = bits.join(' · ');
    }

    const totals = data.totals || {};
    const staff = Array.isArray(data.staff) ? data.staff : [];

    if (!totals.tickets) {
        empty.classList.remove('hidden');
        return;
    }

    content.classList.remove('hidden');

    // Summary cards
    document.getElementById('sum-revenue').textContent = formatLei(totals.revenue);
    document.getElementById('sum-tickets').textContent = (totals.tickets || 0).toLocaleString('ro-RO');
    document.getElementById('sum-cash').textContent = formatLei(totals.cash);
    document.getElementById('sum-card').textContent = formatLei(totals.card);
    document.getElementById('sum-online').textContent = formatLei(totals.online);

    // Staff table
    const tbody = document.getElementById('staff-table-body');
    tbody.innerHTML = staff.map((s, idx) => {
        const onlineBadge = s.is_online ? '<span class="ml-2 inline-flex items-center px-2 py-0.5 text-[10px] font-semibold uppercase rounded-full bg-violet-100 text-violet-700">Online</span>' : '';
        const firstSale = s.first_sale_at ? new Date(s.first_sale_at).toLocaleString('ro-RO', { day:'numeric', month:'short', hour:'2-digit', minute:'2-digit' }) : '—';
        const lastSale = s.last_sale_at ? new Date(s.last_sale_at).toLocaleString('ro-RO', { day:'numeric', month:'short', hour:'2-digit', minute:'2-digit' }) : '—';
        return `
            <tr class="text-sm hover:bg-gray-50/50">
                <td class="px-6 py-4">
                    <div class="font-semibold text-secondary">${escapeHtml(s.name)}${onlineBadge}</div>
                    <div class="text-xs text-gray-500">${firstSale} → ${lastSale}</div>
                </td>
                <td class="px-3 py-4 text-right text-secondary">${(s.orders || 0).toLocaleString('ro-RO')}</td>
                <td class="px-3 py-4 text-right text-secondary">${(s.tickets || 0).toLocaleString('ro-RO')}</td>
                <td class="px-3 py-4 text-right ${s.cash > 0 ? 'font-semibold text-emerald-700' : 'text-gray-400'}">${formatLei(s.cash)}</td>
                <td class="px-3 py-4 text-right ${s.card > 0 ? 'font-semibold text-blue-700' : 'text-gray-400'}">${formatLei(s.card)}</td>
                <td class="px-3 py-4 text-right ${s.online > 0 ? 'font-semibold text-violet-700' : 'text-gray-400'}">${formatLei(s.online)}</td>
                <td class="px-3 py-4 text-right font-bold text-secondary">${formatLei(s.revenue)}</td>
                <td class="px-3 py-4 text-right">
                    <button type="button" onclick="toggleStaffDetails(${idx})" class="text-xs font-medium text-primary hover:underline">
                        Vezi detalii ▾
                    </button>
                </td>
            </tr>
            <tr id="staff-details-${idx}" class="hidden bg-gray-50/50">
                <td colspan="8" class="px-6 py-4">
                    <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Vânzări per tip bilet — ${escapeHtml(s.name)}</div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-[11px] font-medium text-left text-gray-400 uppercase">
                                    <th class="py-2">Tip bilet</th>
                                    <th class="py-2 text-right">Bilete</th>
                                    <th class="py-2 text-right">Venituri</th>
                                    <th class="py-2 text-right">Preț mediu / bilet</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                ${(s.ticket_types || []).map(tt => `
                                    <tr class="text-sm">
                                        <td class="py-2 text-secondary">${escapeHtml(tt.name)}</td>
                                        <td class="py-2 text-right">${(tt.count || 0).toLocaleString('ro-RO')}</td>
                                        <td class="py-2 text-right font-semibold text-secondary">${formatLei(tt.amount)}</td>
                                        <td class="py-2 text-right text-muted">${tt.count > 0 ? formatLei(tt.amount / tt.count) : '—'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    // Overall ticket types table
    const ttBody = document.getElementById('ticket-types-body');
    const ttData = Array.isArray(data.ticket_types_overall) ? data.ticket_types_overall : [];
    const totalCount = ttData.reduce((acc, t) => acc + (t.count || 0), 0);
    ttBody.innerHTML = ttData.map(tt => {
        const pct = totalCount > 0 ? Math.round((tt.count / totalCount) * 100) : 0;
        return `
            <tr class="text-sm hover:bg-gray-50/50">
                <td class="px-6 py-3 font-medium text-secondary">${escapeHtml(tt.name)}</td>
                <td class="px-3 py-3 text-right text-secondary">${(tt.count || 0).toLocaleString('ro-RO')}</td>
                <td class="px-3 py-3 text-right font-semibold text-secondary">${formatLei(tt.amount)}</td>
                <td class="px-3 py-3 text-right text-muted">${pct}%</td>
            </tr>
        `;
    }).join('');
    if (!ttData.length) {
        ttBody.innerHTML = '<tr><td colspan="4" class="px-6 py-6 text-sm text-center text-gray-400">Niciun bilet vândut.</td></tr>';
    }
}

function toggleStaffDetails(idx) {
    const row = document.getElementById('staff-details-' + idx);
    if (!row) return;
    row.classList.toggle('hidden');
}
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
