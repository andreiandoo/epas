<?php
/**
 * bilete.online — Organizator › Sold / Finanțe (v3).
 * Route: /organizator/sold
 *
 * Per-activity balances (gross / commission / net / paid-out / available),
 * expandable transactions + payouts, and a payout-request modal. Ported from
 * the ambilet organizer finance page, restyled to v3 and wired to
 * BileteOnlineAPI (/organizer/finance, /organizer/bank-accounts, /organizer/payouts).
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Sold';
$currentPage = 'finance';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="font-display text-3xl font-bold leading-none">Sold &amp; finanțe</h1>
            <p class="mt-1.5 text-sm text-ink-soft">Gestionează balanța și plățile tale, pe activitate.</p>
        </div>

        <!-- Summary cards -->
        <div class="mb-8 grid gap-5 lg:grid-cols-3">
            <div class="rounded-2xl bg-gradient-to-br from-vermilion to-vermilion-d p-6 text-paper shadow-deep">
                <div class="flex items-center gap-3">
                    <span class="grid h-12 w-12 place-items-center rounded-xl bg-paper/20">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm text-paper/80">Sold disponibil</p>
                        <p class="font-display text-3xl font-bold" id="available-balance">0 lei</p>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                <div class="flex items-center gap-3">
                    <span class="grid h-12 w-12 place-items-center rounded-xl bg-ochre/15 text-ochre">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div>
                        <p class="text-sm text-ink-soft">În procesare</p>
                        <p class="font-display text-3xl font-bold" id="pending-balance">0 lei</p>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                <div class="flex items-center gap-3">
                    <span class="grid h-12 w-12 place-items-center rounded-xl bg-forest/15 text-forest">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                    </span>
                    <div>
                        <p class="text-sm text-ink-soft">Total încasat</p>
                        <p class="font-display text-3xl font-bold" id="total-paid-out">0 lei</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Per-activity balances -->
        <h2 class="mb-4 font-display text-xl font-bold">Sold per activitate</h2>
        <div class="overflow-hidden rounded-2xl border-2 border-ink bg-paper">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-paper-2 text-left">
                        <tr class="font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">
                            <th class="px-5 py-3">Activitate</th>
                            <th class="px-5 py-3 text-right">Venituri brute</th>
                            <th class="px-5 py-3 text-right">Comision</th>
                            <th class="px-5 py-3 text-right">Venituri nete</th>
                            <th class="px-5 py-3 text-right">Retras</th>
                            <th class="px-5 py-3 text-right">Sold disponibil</th>
                            <th class="px-5 py-3 text-center">Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody id="events-list" class="divide-y divide-ink/10 text-sm">
                        <tr><td colspan="7" class="px-5 py-12 text-center text-ink-soft">Se încarcă…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<!-- Payout modal -->
<div id="payout-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-ink/60 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-deep">
        <div class="mb-6 flex items-center justify-between">
            <h3 class="font-display text-2xl font-bold">Solicită plată</h3>
            <button onclick="closePayoutModal()" aria-label="Închide" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-paper transition hover:bg-vermilion">×</button>
        </div>
        <form onsubmit="submitPayoutRequest(event)">
            <input type="hidden" id="payout-event-id" value="">
            <div id="payout-event-info" class="mb-4 hidden rounded-2xl bg-paper-2 p-4">
                <p class="mb-1 text-sm text-ink-soft">Activitate</p>
                <p class="font-bold" id="payout-event-name"></p>
            </div>
            <div class="mb-6 rounded-2xl bg-paper-2 p-4">
                <p class="mb-1 text-sm text-ink-soft">Suma disponibilă</p>
                <p class="font-display text-2xl font-bold" id="modal-available-balance">0 lei</p>
            </div>
            <label class="mb-4 block">
                <span class="mb-1.5 block text-xs font-bold text-ink-soft">Suma de retras</span>
                <input type="number" id="payout-amount" min="100" step="0.01" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2.5 text-sm font-medium outline-none focus:border-ink">
                <span class="mt-1 block text-xs text-ink-soft" id="payout-amount-hint">Suma minimă: 100 lei</span>
            </label>
            <label class="mb-4 block">
                <span class="mb-1.5 block text-xs font-bold text-ink-soft">Cont bancar</span>
                <select id="payout-account" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2.5 text-sm font-medium outline-none focus:border-ink"><option value="">Se încarcă…</option></select>
            </label>
            <label class="mb-6 block">
                <span class="mb-1.5 block text-xs font-bold text-ink-soft">Note (opțional)</span>
                <textarea id="payout-notes" rows="2" placeholder="Adaugă note sau detalii…" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2.5 text-sm font-medium outline-none focus:border-ink"></textarea>
            </label>
            <div class="flex gap-3">
                <button type="button" onclick="closePayoutModal()" class="flex-1 rounded-full border-2 border-ink px-4 py-3 font-bold transition hover:bg-ink hover:text-paper">Anulează</button>
                <button type="submit" class="flex-1 rounded-full bg-vermilion px-4 py-3 font-bold text-paper transition hover:bg-vermilion-d">Solicită plata</button>
            </div>
        </form>
    </div>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
let financeData = null;
const highlightEventId = new URLSearchParams(window.location.search).get('event');

function orgNotify(msg, type) {
    try { if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) { BileteOnlineNotifications[type || 'info'](msg); return; } } catch (e) {}
    if (type === 'error') alert(msg);
}
function money(v) { try { return BileteOnlineUtils.formatCurrency(v || 0); } catch (e) { return (Math.round((v||0)*100)/100) + ' lei'; } }
function fmtDate(d) { try { return BileteOnlineUtils.formatDate(d); } catch (e) { return d || ''; } }
function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

document.addEventListener('DOMContentLoaded', loadFinanceData);

async function loadFinanceData() {
    try {
        const response = await BileteOnlineAPI.get('/organizer/finance');
        if (response && response.success) {
            financeData = response.data || {};
            const events = financeData.events || [];
            const available = events.reduce((s, e) => s + (e.available_balance || 0), 0);
            const pending = events.reduce((s, e) => s + (e.pending_payout || 0), 0);
            const paidOut = events.reduce((s, e) => s + (e.total_paid_out || 0), 0);
            document.getElementById('available-balance').textContent = money(available);
            document.getElementById('pending-balance').textContent = money(pending || financeData.pending_balance || 0);
            document.getElementById('total-paid-out').textContent = money(paidOut || financeData.total_paid_out || 0);
            renderEvents(events);
            if (highlightEventId) {
                const row = document.querySelector('.event-row[data-event-id="' + highlightEventId + '"]');
                if (row) { row.classList.add('ring-2', 'ring-vermilion'); row.scrollIntoView({ behavior: 'smooth', block: 'center' }); toggleEventDetails(parseInt(highlightEventId)); }
            }
        } else { showEmpty(); }
    } catch (e) { showEmpty(); }
}

function showEmpty() {
    document.getElementById('available-balance').textContent = money(0);
    document.getElementById('pending-balance').textContent = money(0);
    document.getElementById('total-paid-out').textContent = money(0);
    document.getElementById('events-list').innerHTML = '<tr><td colspan="7" class="px-5 py-12 text-center text-ink-soft">Nu există activități.</td></tr>';
}

function renderEvents(events) {
    const tbody = document.getElementById('events-list');
    if (!events.length) { showEmpty(); return; }
    tbody.innerHTML = events.map(e => {
        const statusBadge = e.is_past
            ? '<span class="rounded-full bg-ink/10 px-2 py-0.5 text-xs font-bold text-ink-soft">Încheiată</span>'
            : '<span class="rounded-full bg-forest/15 px-2 py-0.5 text-xs font-bold text-forest">Activă</span>';
        let payoutBtn;
        if (!e.is_past) payoutBtn = '<span class="text-xs text-ink-soft">Activitate activă</span>';
        else if (e.pending_payout > 0 && e.available_balance < 100) payoutBtn = '<span class="text-xs text-ochre">Plată solicitată</span>';
        else if (e.available_balance < 100) payoutBtn = '<span class="text-xs text-ink-soft">' + (e.available_balance > 0 ? 'Min. 100 lei' : 'Fără sold') + '</span>';
        else payoutBtn = '<button onclick="event.stopPropagation(); openPayoutModal(' + e.id + ', \'' + String(e.title || '').replace(/'/g, "\\'") + '\', ' + e.available_balance + ')" class="rounded-full bg-vermilion px-3 py-1.5 text-xs font-bold text-paper transition hover:bg-vermilion-d">Solicită plata</button>';

        const img = e.image
            ? '<img src="' + e.image + '" class="h-full w-full object-cover">'
            : '<div class="grid h-full w-full place-items-center text-ink-soft"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>';
        const meta = [e.starts_at ? fmtDate(e.starts_at) + (e.start_time ? ' ' + e.start_time : '') : '', e.venue_name, e.venue_city].filter(Boolean).join(' · ');

        return ''
        + '<tr class="event-row cursor-pointer hover:bg-paper-2/60" data-event-id="' + e.id + '" onclick="toggleEventDetails(' + e.id + ')">'
            + '<td class="px-5 py-4">'
                + '<div class="flex items-center gap-3">'
                    + '<span class="h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-paper-2">' + img + '</span>'
                    + '<span class="flex items-center gap-2">'
                        + '<svg class="event-expand-icon h-4 w-4 text-ink-soft transition-transform" id="expand-icon-' + e.id + '" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>'
                        + '<span><span class="block font-bold">' + esc(e.title) + '</span>'
                            + '<span class="mt-0.5 block text-xs text-ink-soft">' + esc(meta) + '</span>'
                            + '<span class="mt-0.5 flex items-center gap-2">' + statusBadge + '<span class="text-xs text-ink-soft">' + (e.tickets_sold || 0) + ' bilete</span></span>'
                        + '</span>'
                    + '</span>'
                + '</div>'
            + '</td>'
            + '<td class="px-5 py-4 text-right font-medium">' + money(e.gross_revenue) + '</td>'
            + '<td class="px-5 py-4 text-right text-ochre">' + money(e.commission_amount) + '</td>'
            + '<td class="px-5 py-4 text-right font-bold text-forest">' + money(e.net_revenue) + '</td>'
            + '<td class="px-5 py-4 text-right text-ink-soft">' + money(e.total_paid_out) + '</td>'
            + '<td class="px-5 py-4 text-right"><span class="font-bold ' + (e.available_balance > 0 ? 'text-vermilion' : 'text-ink-soft') + '">' + money(e.available_balance) + '</span>'
                + (e.pending_payout > 0 ? '<br><span class="text-xs text-ochre">În procesare: ' + money(e.pending_payout) + '</span>' : '') + '</td>'
            + '<td class="px-5 py-4 text-center">' + payoutBtn + '</td>'
        + '</tr>'
        + '<tr class="event-details-row hidden" id="event-details-' + e.id + '"><td colspan="7" class="bg-paper-2/50 p-0">'
            + '<div class="border-t-2 border-dashed border-ink/15 p-4">'
                + '<div class="mb-4 flex items-center gap-2 border-b border-ink/10">'
                    + '<button onclick="event.stopPropagation(); setEventTab(' + e.id + ', \'transactions\')" class="event-tab-btn border-b-2 border-vermilion px-4 py-2 text-sm font-bold text-vermilion" data-event-id="' + e.id + '" data-tab="transactions">Tranzacții</button>'
                    + '<button onclick="event.stopPropagation(); setEventTab(' + e.id + ', \'payouts\')" class="event-tab-btn border-b-2 border-transparent px-4 py-2 text-sm font-bold text-ink-soft hover:text-ink" data-event-id="' + e.id + '" data-tab="payouts">Plăți primite</button>'
                + '</div>'
                + '<div id="event-' + e.id + '-transactions" class="event-tab-content"><div class="overflow-hidden rounded-xl border border-ink/10 bg-paper"><div class="divide-y divide-ink/10" id="event-' + e.id + '-transactions-list"><div class="p-4 text-center text-sm text-ink-soft">Se încarcă…</div></div></div></div>'
                + '<div id="event-' + e.id + '-payouts" class="event-tab-content hidden"><div class="overflow-hidden rounded-xl border border-ink/10 bg-paper"><table class="w-full"><thead class="bg-paper-2 text-left"><tr class="text-xs font-bold text-ink-soft"><th class="px-4 py-3">Referință</th><th class="px-4 py-3">Suma</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Data</th></tr></thead><tbody id="event-' + e.id + '-payouts-list" class="divide-y divide-ink/10"><tr><td colspan="4" class="px-4 py-4 text-center text-sm text-ink-soft">Se încarcă…</td></tr></tbody></table></div></div>'
            + '</div>'
        + '</td></tr>';
    }).join('');
}

const expandedEvents = new Set();
function toggleEventDetails(eventId) {
    const row = document.getElementById('event-details-' + eventId);
    const icon = document.getElementById('expand-icon-' + eventId);
    if (expandedEvents.has(eventId)) { row.classList.add('hidden'); icon.classList.remove('rotate-180'); expandedEvents.delete(eventId); }
    else { row.classList.remove('hidden'); icon.classList.add('rotate-180'); expandedEvents.add(eventId); loadEventDetails(eventId); }
}
function setEventTab(eventId, tab) {
    document.querySelectorAll('.event-tab-btn[data-event-id="' + eventId + '"]').forEach(b => { b.classList.remove('border-vermilion', 'text-vermilion'); b.classList.add('border-transparent', 'text-ink-soft'); });
    const active = document.querySelector('.event-tab-btn[data-event-id="' + eventId + '"][data-tab="' + tab + '"]');
    if (active) { active.classList.add('border-vermilion', 'text-vermilion'); active.classList.remove('border-transparent', 'text-ink-soft'); }
    document.getElementById('event-' + eventId + '-transactions').classList.add('hidden');
    document.getElementById('event-' + eventId + '-payouts').classList.add('hidden');
    document.getElementById('event-' + eventId + '-' + tab).classList.remove('hidden');
}
function loadEventDetails(eventId) {
    const txns = (financeData.transactions || []).filter(t => t.event_id === eventId && t.type !== 'payout' && t.type !== 'payout_reversal');
    const payouts = (financeData.payouts || []).filter(p => p.event_id === eventId);
    renderTxns(eventId, txns);
    renderPayouts(eventId, payouts);
}
function renderTxns(eventId, txns) {
    const el = document.getElementById('event-' + eventId + '-transactions-list');
    if (!txns.length) { el.innerHTML = '<div class="p-4 text-center text-sm text-ink-soft">Nu există tranzacții.</div>'; return; }
    el.innerHTML = txns.map(t => {
        const pos = (t.amount >= 0);
        return '<div class="flex items-center justify-between p-3 hover:bg-paper-2/50"><div class="flex items-center gap-3">'
            + '<span class="grid h-8 w-8 place-items-center rounded-lg ' + (t.type === 'sale' ? 'bg-forest/10 text-forest' : t.type === 'refund' ? 'bg-vermilion/10 text-vermilion' : 'bg-sky/10 text-sky') + '"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg></span>'
            + '<div><p class="text-sm font-medium">' + esc(t.description) + '</p><p class="text-xs text-ink-soft">' + fmtDate(t.date) + '</p></div></div>'
            + '<span class="text-sm font-bold ' + (pos ? 'text-forest' : 'text-vermilion') + '">' + (pos ? '+' : '') + money(t.amount) + '</span></div>';
    }).join('');
}
function renderPayouts(eventId, payouts) {
    const tbody = document.getElementById('event-' + eventId + '-payouts-list');
    if (!payouts.length) { tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-4 text-center text-sm text-ink-soft">Nu există plăți.</td></tr>'; return; }
    const sc = { pending: ['bg-ochre/15 text-ochre', 'În așteptare'], approved: ['bg-sky/15 text-sky', 'Aprobată'], processing: ['bg-sky/15 text-sky', 'În procesare'], completed: ['bg-forest/15 text-forest', 'Finalizată'], rejected: ['bg-vermilion/10 text-vermilion', 'Respinsă'], cancelled: ['bg-ink/10 text-ink-soft', 'Anulată'] };
    tbody.innerHTML = payouts.map(p => {
        const s = sc[p.status] || sc.pending;
        return '<tr class="hover:bg-paper-2/50"><td class="px-4 py-3 text-sm font-medium">' + esc(p.reference || ('#' + p.id)) + '</td>'
            + '<td class="px-4 py-3 text-sm font-bold">' + money(p.amount) + '</td>'
            + '<td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-bold ' + s[0] + '">' + s[1] + '</span></td>'
            + '<td class="px-4 py-3 text-sm text-ink-soft">' + fmtDate(p.created_at) + '</td></tr>';
    }).join('');
}

let currentPayoutMax = 0;
async function openPayoutModal(eventId, eventName, available) {
    currentPayoutMax = available;
    document.getElementById('payout-event-id').value = eventId;
    document.getElementById('payout-event-name').textContent = eventName;
    document.getElementById('payout-event-info').classList.remove('hidden');
    document.getElementById('modal-available-balance').textContent = money(available);
    const amt = document.getElementById('payout-amount');
    amt.value = ''; amt.max = available;
    document.getElementById('payout-amount-hint').textContent = 'Suma minimă: 100 lei, maximă: ' + money(available);
    document.getElementById('payout-notes').value = '';
    const select = document.getElementById('payout-account');
    select.innerHTML = '<option value="">Se încarcă…</option>';
    try {
        const r = await BileteOnlineAPI.get('/organizer/bank-accounts');
        const accounts = (r && r.data && (r.data.accounts || r.data)) || [];
        if (!accounts.length) select.innerHTML = '<option value="">Nu ai conturi bancare. Adaugă unul în Setări.</option>';
        else {
            select.innerHTML = '<option value="">Selectează contul</option>';
            accounts.forEach(a => {
                const last4 = a.iban ? a.iban.slice(-4) : (a.account_number ? a.account_number.slice(-4) : '');
                select.innerHTML += '<option value="' + a.id + '">' + esc((a.bank_name || 'Cont') + ' - ****' + last4) + '</option>';
            });
        }
    } catch (e) { select.innerHTML = '<option value="">Eroare la încărcarea conturilor</option>'; }
    const modal = document.getElementById('payout-modal');
    modal.classList.remove('hidden'); modal.classList.add('flex');
    amt.oninput = function () { if ((parseFloat(this.value) || 0) > currentPayoutMax) this.value = currentPayoutMax; };
}
function closePayoutModal() { const m = document.getElementById('payout-modal'); m.classList.add('hidden'); m.classList.remove('flex'); }

async function submitPayoutRequest(e) {
    e.preventDefault();
    const eventId = document.getElementById('payout-event-id').value;
    const amount = parseFloat(document.getElementById('payout-amount').value);
    const accountId = document.getElementById('payout-account').value;
    const notes = document.getElementById('payout-notes').value;
    if (!accountId) { orgNotify('Selectează un cont bancar.', 'error'); return; }
    if (!(amount >= 100)) { orgNotify('Suma minimă este 100 lei.', 'error'); return; }
    if (amount > currentPayoutMax) { orgNotify('Suma depășește soldul disponibil.', 'error'); return; }
    try {
        const r = await BileteOnlineAPI.organizer.requestPayout({ amount: amount, event_id: eventId || null, bank_account_id: accountId, notes: notes || null });
        if (r && r.success) { orgNotify('Cererea de plată a fost trimisă!', 'success'); closePayoutModal(); loadFinanceData(); }
        else orgNotify((r && r.message) || 'Eroare la trimiterea cererii.', 'error');
    } catch (e2) { orgNotify('Eroare la trimiterea cererii de plată.', 'error'); }
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
