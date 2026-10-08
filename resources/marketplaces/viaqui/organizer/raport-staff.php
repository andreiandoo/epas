<?php
/**
 * viaqui.com — Operator › Staff report (v3).
 * Route: /organizator/raport-staff?event={id}
 *
 * Per-activity staff sales report: gross takings per staff member and per
 * payment method (cash POS, card POS, online), plus overall ticket-type
 * breakdown. Printable. Ported from ambilet to v3 + shell, wired to the
 * organizer.event.staff-report proxy action.
 *
 * Texts go through v2_t() / v2_te() here and VQ.t() / VQ.n() in the inline script. The page sits in the old shell,
 * which does not load the language layer, so it loads includes/v2/i18n.php and assets/v2/js/i18n.js itself.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/v2/i18n.php';
$pageTitle   = v2_t('Staff report');
$currentPage = 'events';
$eventId     = $_GET['event'] ?? null;
$rsZero      = htmlspecialchars((defined('SITE_CURRENCY_SYMBOL') ? SITE_CURRENCY_SYMBOL : '€') . '0', ENT_QUOTES, 'UTF-8');
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
                    <h1 id="event-title" class="font-display text-xl font-bold leading-none"><?= v2_te('Staff report') ?></h1>
                    <div id="event-info" class="mt-1 text-xs text-ink-soft"></div>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="/organizator/events" class="inline-flex items-center gap-2 rounded-full border-2 border-ink/15 px-4 py-2 text-sm font-bold text-ink-soft transition hover:border-ink hover:text-ink">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <?= v2_te('Back') ?>
                </a>
                <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-4 py-2 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <?= v2_te('Print') ?>
                </button>
            </div>
        </div>
    </div>

    <main class="flex-1 p-4 lg:p-8">
        <div id="loading-state" class="py-16 text-center text-ink-soft"><?= v2_te('Loading the report…') ?></div>

        <div id="empty-state" class="hidden py-16 text-center">
            <svg class="mx-auto mb-3 h-12 w-12 text-ink/15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            <h3 class="font-display text-lg font-bold"><?= v2_te('No tickets sold') ?></h3>
            <p class="mt-1 text-sm text-ink-soft"><?= v2_te('This activity has no sales recorded yet.') ?></p>
        </div>

        <div id="content-state" class="hidden space-y-6">
            <!-- Disclaimer -->
            <div class="flex items-start gap-3 rounded-2xl border-2 border-sky/20 bg-sky/5 p-4">
                <svg class="mt-0.5 h-5 w-5 flex-none text-sky" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-xs leading-relaxed text-ink">
                    <?= v2_t('The amounts are the <strong>gross takings</strong> per staff member and per payment method (cash POS, card POS, online). Commissions, fees, insurance or other costs are <strong>not</strong> taken out of them. For net amounts see the <a href="{url}" class="{class}">Balance</a> page.', ['url' => '/organizator/sold?event=' . htmlspecialchars($eventId ?? ''), 'class' => 'font-bold text-vermilion underline hover:text-vermilion-d']) ?>
                </p>
            </div>

            <!-- Summary cards -->
            <div class="report-section grid grid-cols-2 gap-4 lg:grid-cols-5">
                <div class="rounded-2xl border-2 border-ink bg-paper p-4"><div class="font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft"><?= v2_te('Ticket revenue') ?></div><div id="sum-revenue" class="mt-1 font-display text-2xl font-bold"><?= $rsZero ?></div></div>
                <div class="rounded-2xl border-2 border-ink bg-paper p-4"><div class="font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft"><?= v2_te('Tickets sold') ?></div><div id="sum-tickets" class="mt-1 font-display text-2xl font-bold">0</div></div>
                <div class="rounded-2xl border-2 border-forest/30 bg-forest/10 p-4"><div class="font-mono text-[11px] uppercase tracking-[.12em] text-forest"><?= v2_te('Cash') ?></div><div id="sum-cash" class="mt-1 font-display text-2xl font-bold text-forest"><?= $rsZero ?></div></div>
                <div class="rounded-2xl border-2 border-sky/30 bg-sky/10 p-4"><div class="font-mono text-[11px] uppercase tracking-[.12em] text-sky"><?= v2_te('Card POS') ?></div><div id="sum-card" class="mt-1 font-display text-2xl font-bold text-sky"><?= $rsZero ?></div></div>
                <div class="rounded-2xl border-2 border-vermilion/30 bg-vermilion/10 p-4"><div class="font-mono text-[11px] uppercase tracking-[.12em] text-vermilion"><?= v2_te('Online') ?></div><div id="sum-online" class="mt-1 font-display text-2xl font-bold text-vermilion"><?= $rsZero ?></div></div>
            </div>

            <!-- Staff table -->
            <div class="report-section overflow-hidden rounded-2xl border-2 border-ink bg-paper">
                <div class="border-b-2 border-ink/10 px-6 py-4">
                    <h2 class="font-display text-lg font-bold"><?= v2_te('Sales by staff member') ?></h2>
                    <p class="mt-1 text-xs text-ink-soft"><?= v2_te('Sorted by revenue, highest first. “Online” adds up the sales made on the public site.') ?></p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-paper-2 text-left">
                            <tr class="font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">
                                <th class="px-6 py-3"><?= v2_te('Staff member') ?></th>
                                <th class="px-3 py-3 text-right"><?= v2_te('Orders') ?></th>
                                <th class="px-3 py-3 text-right"><?= v2_te('Tickets') ?></th>
                                <th class="px-3 py-3 text-right"><?= v2_te('Cash POS') ?></th>
                                <th class="px-3 py-3 text-right"><?= v2_te('Card POS') ?></th>
                                <th class="px-3 py-3 text-right"><?= v2_te('Online') ?></th>
                                <th class="px-3 py-3 text-right"><?= v2_te('Total') ?></th>
                                <th class="px-3 py-3 text-right"><?= v2_te('Details') ?></th>
                            </tr>
                        </thead>
                        <tbody id="staff-table-body" class="divide-y divide-ink/10"></tbody>
                    </table>
                </div>
            </div>

            <!-- Ticket types overall -->
            <div class="report-section overflow-hidden rounded-2xl border-2 border-ink bg-paper">
                <div class="border-b-2 border-ink/10 px-6 py-4"><h2 class="font-display text-lg font-bold"><?= v2_te('Sales by ticket type (total)') ?></h2></div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-paper-2 text-left">
                            <tr class="font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">
                                <th class="px-6 py-3"><?= v2_te('Ticket type') ?></th>
                                <th class="px-3 py-3 text-right"><?= v2_te('Tickets sold') ?></th>
                                <th class="px-3 py-3 text-right"><?= v2_te('Revenue') ?></th>
                                <th class="px-3 py-3 text-right"><?= v2_te('% of total') ?></th>
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
// The language layer for the inline script (the old shell does not load it), the selected event id, then a
// fully-static NOWDOC body.
$scriptsExtra = v2_i18n_script() . '<script src="' . htmlspecialchars(asset('assets/v2/js/i18n.js'), ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
$scriptsExtra .= "<script>\nconst eventId = " . json_encode($eventId) . ";\n</script>\n";
$scriptsExtra .= <<<'JS'
<script>
let reportData = null;
const RS_LOC = VQ.locale === 'en' ? 'en-GB' : VQ.locale;
const RS_CUR = (typeof BILETEONLINE_CONFIG !== 'undefined' && BILETEONLINE_CONFIG && BILETEONLINE_CONFIG.CURRENCY_SYMBOL) || '€';

document.addEventListener('DOMContentLoaded', () => {
    if (!eventId) { window.location.href = VQ.url('/organizator/events'); return; }
    loadReport();
});

function formatMoney(amount) {
    return RS_CUR + Number(amount || 0).toLocaleString(RS_LOC, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

async function loadReport() {
    try {
        const response = await BileteOnlineAPI.get(`/organizer/events/${eventId}/staff-report`);
        if (!response || !response.success) throw new Error((response && response.message) || VQ.t('The report could not be loaded.'));
        reportData = response.data;
        renderReport(reportData);
    } catch (err) {
        document.getElementById('loading-state').innerHTML = '<div class="text-sm text-vermilion"><strong>' + escapeHtml(VQ.t('Error:')) + '</strong> ' + escapeHtml(err.message || VQ.t('The report could not be loaded.')) + '</div>';
    }
}

function renderReport(data) {
    document.getElementById('loading-state').classList.add('hidden');
    const content = document.getElementById('content-state');
    const empty = document.getElementById('empty-state');

    if (data.event) {
        document.getElementById('event-title').textContent = data.event.title || VQ.t('Staff report');
        const bits = [];
        if (data.event.date) {
            const d = new Date(data.event.date);
            if (!isNaN(d.getTime())) bits.push(d.toLocaleDateString(RS_LOC, { day: 'numeric', month: 'long', year: 'numeric' }));
        }
        document.getElementById('event-info').textContent = bits.join(' · ');
    }

    const totals = data.totals || {};
    const staff = Array.isArray(data.staff) ? data.staff : [];
    if (!totals.tickets) { empty.classList.remove('hidden'); return; }
    content.classList.remove('hidden');

    document.getElementById('sum-revenue').textContent = formatMoney(totals.revenue);
    document.getElementById('sum-tickets').textContent = (totals.tickets || 0).toLocaleString(RS_LOC);
    document.getElementById('sum-cash').textContent = formatMoney(totals.cash);
    document.getElementById('sum-card').textContent = formatMoney(totals.card);
    document.getElementById('sum-online').textContent = formatMoney(totals.online);

    const T = {
        online: escapeHtml(VQ.t('Online')),
        invitation: escapeHtml(VQ.t('Invitation')),
        details: escapeHtml(VQ.t('See details')),
        type: escapeHtml(VQ.t('Ticket type')),
        tickets: escapeHtml(VQ.t('Tickets')),
        revenue: escapeHtml(VQ.t('Revenue')),
        average: escapeHtml(VQ.t('Average price per ticket')),
        none: escapeHtml(VQ.t('No tickets sold.')),
    };

    const tbody = document.getElementById('staff-table-body');
    tbody.innerHTML = staff.map((s, idx) => {
        const onlineBadge = s.is_online ? '<span class="ml-2 inline-flex items-center rounded-full bg-vermilion/15 px-2 py-0.5 text-[10px] font-bold uppercase text-vermilion">' + T.online + '</span>' : '';
        const invBadge = s.is_invitation_bucket ? '<span class="ml-2 inline-flex items-center rounded-full bg-sky/15 px-2 py-0.5 text-[10px] font-bold uppercase text-sky">' + T.invitation + '</span>' : '';
        const fmtDT = (v) => v ? new Date(v).toLocaleString(RS_LOC, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—';
        return `
            <tr class="text-sm hover:bg-paper-2/60">
                <td class="px-6 py-4"><div class="font-bold">${escapeHtml(s.name)}${onlineBadge}${invBadge}</div><div class="text-xs text-ink-soft">${fmtDT(s.first_sale_at)} → ${fmtDT(s.last_sale_at)}</div></td>
                <td class="px-3 py-4 text-right">${(s.orders || 0).toLocaleString(RS_LOC)}</td>
                <td class="px-3 py-4 text-right">${(s.tickets || 0).toLocaleString(RS_LOC)}</td>
                <td class="px-3 py-4 text-right ${s.cash > 0 ? 'font-bold text-forest' : 'text-ink-soft/50'}">${formatMoney(s.cash)}</td>
                <td class="px-3 py-4 text-right ${s.card > 0 ? 'font-bold text-sky' : 'text-ink-soft/50'}">${formatMoney(s.card)}</td>
                <td class="px-3 py-4 text-right ${s.online > 0 ? 'font-bold text-vermilion' : 'text-ink-soft/50'}">${formatMoney(s.online)}</td>
                <td class="px-3 py-4 text-right font-bold">${formatMoney(s.revenue)}</td>
                <td class="px-3 py-4 text-right"><button type="button" onclick="toggleStaffDetails(${idx})" class="text-xs font-bold text-vermilion hover:underline">${T.details} ▾</button></td>
            </tr>
            <tr id="staff-details-${idx}" class="hidden bg-paper-2/60">
                <td colspan="8" class="px-6 py-4">
                    <div class="mb-2 font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">${escapeHtml(VQ.t('Sales by ticket type: {name}', { name: s.name }))}</div>
                    <div class="overflow-x-auto"><table class="w-full">
                        <thead><tr class="text-left font-mono text-[10px] uppercase tracking-[.1em] text-ink-soft"><th class="py-2">${T.type}</th><th class="py-2 text-right">${T.tickets}</th><th class="py-2 text-right">${T.revenue}</th><th class="py-2 text-right">${T.average}</th></tr></thead>
                        <tbody class="divide-y divide-ink/10">${(s.ticket_types || []).map(tt => `<tr class="text-sm"><td class="py-2">${escapeHtml(tt.name)}</td><td class="py-2 text-right">${(tt.count || 0).toLocaleString(RS_LOC)}</td><td class="py-2 text-right font-bold">${formatMoney(tt.amount)}</td><td class="py-2 text-right text-ink-soft">${tt.count > 0 ? formatMoney(tt.amount / tt.count) : '—'}</td></tr>`).join('')}</tbody>
                    </table></div>
                </td>
            </tr>`;
    }).join('');

    const ttBody = document.getElementById('ticket-types-body');
    const ttData = Array.isArray(data.ticket_types_overall) ? data.ticket_types_overall : [];
    const totalCount = ttData.reduce((acc, t) => acc + (t.count || 0), 0);
    ttBody.innerHTML = ttData.length ? ttData.map(tt => {
        const pct = totalCount > 0 ? Math.round((tt.count / totalCount) * 100) : 0;
        return `<tr class="text-sm hover:bg-paper-2/60"><td class="px-6 py-3 font-medium">${escapeHtml(tt.name)}</td><td class="px-3 py-3 text-right">${(tt.count || 0).toLocaleString(RS_LOC)}</td><td class="px-3 py-3 text-right font-bold">${formatMoney(tt.amount)}</td><td class="px-3 py-3 text-right text-ink-soft">${pct}%</td></tr>`;
    }).join('') : '<tr><td colspan="4" class="px-6 py-6 text-center text-sm text-ink-soft">' + T.none + '</td></tr>';
}

function toggleStaffDetails(idx) {
    const row = document.getElementById('staff-details-' + idx);
    if (row) row.classList.toggle('hidden');
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
