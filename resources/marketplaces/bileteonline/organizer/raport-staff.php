<?php
/**
 * bilete.online — Organizator › Raport staff (v3).
 * Route: /organizator/raport-staff?event={id}
 *
 * Per-activity staff sales report: gross takings per staff member and per
 * payment method (cash POS, card POS, online), plus overall ticket-type
 * breakdown. Printable. Ported from ambilet to v3 + shell, wired to the
 * organizer.event.staff-report proxy action.
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Raport staff';
$currentPage = 'events';
$eventId     = $_GET['event'] ?? null;
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<style>
    .report-section { page-break-inside: avoid; }
    @media print {
        .no-print { display: none !important; }
        aside, .org-sidebar { display: none !important; }
        body { background: #fff !important; }
    }
</style>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <!-- Report sub-header -->
    <div class="no-print border-b-2 border-ink/10 bg-paper px-4 py-3 lg:px-8">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-vermilion/10 text-vermilion">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </span>
                <div>
                    <h1 id="event-title" class="font-display text-xl font-bold leading-none">Raport staff</h1>
                    <div id="event-info" class="mt-1 text-xs text-ink-soft"></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="/organizator/events" class="inline-flex items-center gap-2 rounded-full border-2 border-ink/15 px-4 py-2 text-sm font-bold text-ink-soft transition hover:border-ink hover:text-ink">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Înapoi
                </a>
                <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-4 py-2 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Printează
                </button>
            </div>
        </div>
    </div>

    <main class="flex-1 p-4 lg:p-8">
        <div id="loading-state" class="py-16 text-center text-ink-soft">Se încarcă raportul…</div>

        <div id="empty-state" class="hidden py-16 text-center">
            <svg class="mx-auto mb-3 h-12 w-12 text-ink/15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            <h3 class="font-display text-lg font-bold">Niciun bilet vândut</h3>
            <p class="mt-1 text-sm text-ink-soft">Această activitate nu are încă vânzări înregistrate.</p>
        </div>

        <div id="content-state" class="hidden space-y-6">
            <!-- Disclaimer -->
            <div class="flex items-start gap-3 rounded-2xl border-2 border-sky/20 bg-sky/5 p-4">
                <svg class="mt-0.5 h-5 w-5 flex-none text-sky" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-xs leading-relaxed text-ink">
                    Valorile reprezintă <strong>încasările absolute</strong> per membru staff și per metodă de plată (cash POS, card POS, online). Din ele <strong>NU</strong> sunt scăzute comisioane, taxe, asigurări sau alte costuri. Pentru valori nete consultă pagina <a href="/organizator/sold?event=<?= htmlspecialchars($eventId ?? '') ?>" class="font-bold text-vermilion underline hover:text-vermilion-d">Sold</a>.
                </p>
            </div>

            <!-- Summary cards -->
            <div class="report-section grid grid-cols-2 gap-4 lg:grid-cols-5">
                <div class="rounded-2xl border-2 border-ink bg-paper p-4"><div class="font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Venituri din bilete</div><div id="sum-revenue" class="mt-1 font-display text-2xl font-bold">0 lei</div></div>
                <div class="rounded-2xl border-2 border-ink bg-paper p-4"><div class="font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Bilete vândute</div><div id="sum-tickets" class="mt-1 font-display text-2xl font-bold">0</div></div>
                <div class="rounded-2xl border-2 border-forest/30 bg-forest/10 p-4"><div class="font-mono text-[11px] uppercase tracking-[.12em] text-forest">Cash</div><div id="sum-cash" class="mt-1 font-display text-2xl font-bold text-forest">0 lei</div></div>
                <div class="rounded-2xl border-2 border-sky/30 bg-sky/10 p-4"><div class="font-mono text-[11px] uppercase tracking-[.12em] text-sky">Card POS</div><div id="sum-card" class="mt-1 font-display text-2xl font-bold text-sky">0 lei</div></div>
                <div class="rounded-2xl border-2 border-vermilion/30 bg-vermilion/10 p-4"><div class="font-mono text-[11px] uppercase tracking-[.12em] text-vermilion">Online</div><div id="sum-online" class="mt-1 font-display text-2xl font-bold text-vermilion">0 lei</div></div>
            </div>

            <!-- Staff table -->
            <div class="report-section overflow-hidden rounded-2xl border-2 border-ink bg-paper">
                <div class="border-b-2 border-ink/10 px-6 py-4">
                    <h2 class="font-display text-lg font-bold">Vânzări per membru staff</h2>
                    <p class="mt-1 text-xs text-ink-soft">Sortat după venituri, descrescător. „Online" cumulează vânzările din site-ul public.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-paper-2 text-left">
                            <tr class="font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">
                                <th class="px-6 py-3">Membru staff</th>
                                <th class="px-3 py-3 text-right">Comenzi</th>
                                <th class="px-3 py-3 text-right">Bilete</th>
                                <th class="px-3 py-3 text-right">Cash POS</th>
                                <th class="px-3 py-3 text-right">Card POS</th>
                                <th class="px-3 py-3 text-right">Online</th>
                                <th class="px-3 py-3 text-right">Total</th>
                                <th class="px-3 py-3 text-right">Detalii</th>
                            </tr>
                        </thead>
                        <tbody id="staff-table-body" class="divide-y divide-ink/10"></tbody>
                    </table>
                </div>
            </div>

            <!-- Ticket types overall -->
            <div class="report-section overflow-hidden rounded-2xl border-2 border-ink bg-paper">
                <div class="border-b-2 border-ink/10 px-6 py-4"><h2 class="font-display text-lg font-bold">Vânzări per tip de bilet (total)</h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-paper-2 text-left">
                            <tr class="font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">
                                <th class="px-6 py-3">Tip bilet</th>
                                <th class="px-3 py-3 text-right">Bilete vândute</th>
                                <th class="px-3 py-3 text-right">Venituri</th>
                                <th class="px-3 py-3 text-right">% din total</th>
                            </tr>
                        </thead>
                        <tbody id="ticket-types-body" class="divide-y divide-ink/10"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<?php
// Inject the selected event id, then a fully-static NOWDOC body.
$scriptsExtra = "<script>\nconst eventId = " . json_encode($eventId) . ";\n</script>\n";
$scriptsExtra .= <<<'JS'
<script>
let reportData = null;

document.addEventListener('DOMContentLoaded', () => {
    if (!eventId) { window.location.href = '/organizator/events'; return; }
    loadReport();
});

function formatLei(amount) {
    return Number(amount || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' lei';
}
function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

async function loadReport() {
    try {
        const response = await BileteOnlineAPI.get(`/organizer/events/${eventId}/staff-report`);
        if (!response || !response.success) throw new Error((response && response.message) || 'Eroare la încărcarea raportului');
        reportData = response.data;
        renderReport(reportData);
    } catch (err) {
        document.getElementById('loading-state').innerHTML = '<div class="text-sm text-vermilion"><strong>Eroare:</strong> ' + escapeHtml(err.message || 'Nu s-a putut încărca raportul.') + '</div>';
    }
}

function renderReport(data) {
    document.getElementById('loading-state').classList.add('hidden');
    const content = document.getElementById('content-state');
    const empty = document.getElementById('empty-state');

    if (data.event) {
        document.getElementById('event-title').textContent = data.event.title || 'Raport staff';
        const bits = [];
        if (data.event.date) {
            const d = new Date(data.event.date);
            if (!isNaN(d.getTime())) bits.push(d.toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' }));
        }
        document.getElementById('event-info').textContent = bits.join(' · ');
    }

    const totals = data.totals || {};
    const staff = Array.isArray(data.staff) ? data.staff : [];
    if (!totals.tickets) { empty.classList.remove('hidden'); return; }
    content.classList.remove('hidden');

    document.getElementById('sum-revenue').textContent = formatLei(totals.revenue);
    document.getElementById('sum-tickets').textContent = (totals.tickets || 0).toLocaleString('ro-RO');
    document.getElementById('sum-cash').textContent = formatLei(totals.cash);
    document.getElementById('sum-card').textContent = formatLei(totals.card);
    document.getElementById('sum-online').textContent = formatLei(totals.online);

    const tbody = document.getElementById('staff-table-body');
    tbody.innerHTML = staff.map((s, idx) => {
        const onlineBadge = s.is_online ? '<span class="ml-2 inline-flex items-center rounded-full bg-vermilion/15 px-2 py-0.5 text-[10px] font-bold uppercase text-vermilion">Online</span>' : '';
        const invBadge = s.is_invitation_bucket ? '<span class="ml-2 inline-flex items-center rounded-full bg-sky/15 px-2 py-0.5 text-[10px] font-bold uppercase text-sky">Invitație</span>' : '';
        const fmtDT = (v) => v ? new Date(v).toLocaleString('ro-RO', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—';
        return `
            <tr class="text-sm hover:bg-paper-2/60">
                <td class="px-6 py-4"><div class="font-bold">${escapeHtml(s.name)}${onlineBadge}${invBadge}</div><div class="text-xs text-ink-soft">${fmtDT(s.first_sale_at)} → ${fmtDT(s.last_sale_at)}</div></td>
                <td class="px-3 py-4 text-right">${(s.orders || 0).toLocaleString('ro-RO')}</td>
                <td class="px-3 py-4 text-right">${(s.tickets || 0).toLocaleString('ro-RO')}</td>
                <td class="px-3 py-4 text-right ${s.cash > 0 ? 'font-bold text-forest' : 'text-ink-soft/50'}">${formatLei(s.cash)}</td>
                <td class="px-3 py-4 text-right ${s.card > 0 ? 'font-bold text-sky' : 'text-ink-soft/50'}">${formatLei(s.card)}</td>
                <td class="px-3 py-4 text-right ${s.online > 0 ? 'font-bold text-vermilion' : 'text-ink-soft/50'}">${formatLei(s.online)}</td>
                <td class="px-3 py-4 text-right font-bold">${formatLei(s.revenue)}</td>
                <td class="px-3 py-4 text-right"><button type="button" onclick="toggleStaffDetails(${idx})" class="text-xs font-bold text-vermilion hover:underline">Vezi detalii ▾</button></td>
            </tr>
            <tr id="staff-details-${idx}" class="hidden bg-paper-2/60">
                <td colspan="8" class="px-6 py-4">
                    <div class="mb-2 font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">Vânzări per tip bilet — ${escapeHtml(s.name)}</div>
                    <div class="overflow-x-auto"><table class="w-full">
                        <thead><tr class="text-left font-mono text-[10px] uppercase tracking-[.1em] text-ink-soft"><th class="py-2">Tip bilet</th><th class="py-2 text-right">Bilete</th><th class="py-2 text-right">Venituri</th><th class="py-2 text-right">Preț mediu / bilet</th></tr></thead>
                        <tbody class="divide-y divide-ink/10">${(s.ticket_types || []).map(tt => `<tr class="text-sm"><td class="py-2">${escapeHtml(tt.name)}</td><td class="py-2 text-right">${(tt.count || 0).toLocaleString('ro-RO')}</td><td class="py-2 text-right font-bold">${formatLei(tt.amount)}</td><td class="py-2 text-right text-ink-soft">${tt.count > 0 ? formatLei(tt.amount / tt.count) : '—'}</td></tr>`).join('')}</tbody>
                    </table></div>
                </td>
            </tr>`;
    }).join('');

    const ttBody = document.getElementById('ticket-types-body');
    const ttData = Array.isArray(data.ticket_types_overall) ? data.ticket_types_overall : [];
    const totalCount = ttData.reduce((acc, t) => acc + (t.count || 0), 0);
    ttBody.innerHTML = ttData.length ? ttData.map(tt => {
        const pct = totalCount > 0 ? Math.round((tt.count / totalCount) * 100) : 0;
        return `<tr class="text-sm hover:bg-paper-2/60"><td class="px-6 py-3 font-medium">${escapeHtml(tt.name)}</td><td class="px-3 py-3 text-right">${(tt.count || 0).toLocaleString('ro-RO')}</td><td class="px-3 py-3 text-right font-bold">${formatLei(tt.amount)}</td><td class="px-3 py-3 text-right text-ink-soft">${pct}%</td></tr>`;
    }).join('') : '<tr><td colspan="4" class="px-6 py-6 text-center text-sm text-ink-soft">Niciun bilet vândut.</td></tr>';
}

function toggleStaffDetails(idx) {
    const row = document.getElementById('staff-details-' + idx);
    if (row) row.classList.toggle('hidden');
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
