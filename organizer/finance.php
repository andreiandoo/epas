<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Finante';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'finance';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
                <!-- Page Content -->
        <main class="flex-1 p-4 lg:p-8">
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Finanțe</h1>
                    <p class="text-sm text-muted">Gestionează balanța și plățile tale</p>
                </div>
            </div>


            <div class="grid gap-6 mb-8 lg:grid-cols-3">
                <!-- Disponibil de retras -->
                <div class="overflow-hidden text-white bg-gradient-to-br from-primary to-primary-dark rounded-2xl">
                    <div class="p-6">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-white/20 rounded-xl"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                            <div class="min-w-0">
                                <p class="text-sm text-white/80">Disponibil de retras</p>
                                <p class="text-3xl font-bold" id="available-balance">0 RON</p>
                            </div>
                        </div>
                        <p class="mt-3 text-xs leading-relaxed text-white/70">Bani din bilete vândute pe care îi poți cere la plată acum.</p>
                        <button type="button" onclick="toggleBreakdown('available')" class="flex items-center gap-1.5 mt-3 text-xs font-medium text-white/90 hover:text-white">
                            <span>Din ce evenimente</span>
                            <svg id="chev-available" class="w-3.5 h-3.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                    </div>
                    <div id="bd-available" class="hidden px-6 pb-6">
                        <div class="pt-3 space-y-2 overflow-auto border-t border-white/20 max-h-64" id="bd-available-list"></div>
                    </div>
                </div>

                <!-- În procesare -->
                <div class="overflow-hidden bg-white border rounded-2xl border-border">
                    <div class="p-6">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-warning/10 rounded-xl"><svg class="w-6 h-6 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                            <div class="min-w-0">
                                <p class="text-sm text-muted">În procesare</p>
                                <p class="text-3xl font-bold text-secondary" id="pending-balance">0 RON</p>
                            </div>
                        </div>
                        <p class="mt-3 text-xs leading-relaxed text-muted">Deconturi aprobate, în curs de plată către tine.</p>
                        <button type="button" onclick="toggleBreakdown('pending')" class="flex items-center gap-1.5 mt-3 text-xs font-medium text-primary hover:underline">
                            <span>Din ce deconturi</span>
                            <svg id="chev-pending" class="w-3.5 h-3.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                    </div>
                    <div id="bd-pending" class="hidden px-6 pb-6">
                        <div class="pt-3 space-y-2 overflow-auto border-t border-border max-h-64" id="bd-pending-list"></div>
                    </div>
                </div>

                <!-- Total încasat -->
                <div class="overflow-hidden bg-white border rounded-2xl border-border">
                    <div class="p-6">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-success/10 rounded-xl"><svg class="w-6 h-6 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></div>
                            <div class="min-w-0">
                                <p class="text-sm text-muted">Total încasat</p>
                                <p class="text-3xl font-bold text-secondary" id="total-paid-out">0 RON</p>
                            </div>
                        </div>
                        <p class="mt-3 text-xs leading-relaxed text-muted">Suma deconturilor deja plătite către tine.</p>
                        <button type="button" onclick="toggleBreakdown('paid')" class="flex items-center gap-1.5 mt-3 text-xs font-medium text-primary hover:underline">
                            <span>Din ce deconturi</span>
                            <svg id="chev-paid" class="w-3.5 h-3.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                    </div>
                    <div id="bd-paid" class="hidden px-6 pb-6">
                        <div class="pt-3 space-y-2 overflow-auto border-t border-border max-h-64" id="bd-paid-list"></div>
                    </div>
                </div>
            </div>

            <!-- Events with Balances -->
            <div class="mb-8">
                <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-secondary">Evenimentele tale</h2>
                        <p class="text-sm text-muted">Cât s-a încasat de la clienți, cât ți se cuvine și cât ai primit deja — pentru fiecare eveniment.</p>
                    </div>
                    <div class="flex items-center gap-2" id="event-filters">
                        <button type="button" data-filter="all" onclick="setEventFilter('all')" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-primary text-white">Toate</button>
                        <button type="button" data-filter="active" onclick="setEventFilter('active')" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-surface text-muted hover:text-secondary">Active</button>
                        <button type="button" data-filter="past" onclick="setEventFilter('past')" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-surface text-muted hover:text-secondary">Încheiate</button>
                    </div>
                </div>
                <div class="space-y-3" id="events-list">
                    <div class="p-12 text-center bg-white border rounded-2xl border-border text-muted">Se încarcă...</div>
                </div>
            </div>

        </main>
    </div>

    <div id="payout-modal" class="fixed inset-0 z-50 items-center justify-center hidden p-4 bg-black/50">
        <div class="w-full max-w-md p-6 bg-white rounded-2xl">
            <div class="flex items-center justify-between mb-6"><h3 class="text-xl font-bold text-secondary">Solicită Plată</h3><button onclick="closePayoutModal()" aria-label="Închide" class="text-muted hover:text-secondary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <form onsubmit="submitPayoutRequest(event)">
                <input type="hidden" id="payout-event-id" value="">
                <div id="payout-event-info" class="hidden p-4 mb-4 bg-surface rounded-xl">
                    <p class="mb-1 text-sm text-muted">Eveniment</p>
                    <p class="font-semibold text-secondary" id="payout-event-name"></p>
                </div>
                <div class="p-4 mb-6 bg-surface rounded-xl"><p class="mb-1 text-sm text-muted">Suma disponibilă</p><p class="text-2xl font-bold text-secondary" id="modal-available-balance">0 RON</p></div>
                <div class="mb-4"><label class="label">Suma de retras</label><input type="number" id="payout-amount" min="100" step="0.01" class="w-full input" required><p class="mt-1 text-sm text-muted" id="payout-amount-hint">Suma minimă: 100 RON</p></div>
                <div class="mb-4"><label class="label">Cont Bancar</label><select id="payout-account" class="w-full input" required><option value="">Se încarcă...</option></select></div>
                <div class="mb-6"><label class="label">Note (opțional)</label><textarea id="payout-notes" class="w-full input" rows="2" placeholder="Adaugă note sau detalii..."></textarea></div>
                <div class="flex gap-3"><button type="button" onclick="closePayoutModal()" class="flex-1 btn btn-secondary">Anulează</button><button type="submit" class="flex-1 btn btn-primary bg-primary">Solicită Plată</button></div>
            </form>
        </div>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function() { AmbiletAuth.requireOrganizerAuth(); });
let financeData = null;
let allEvents = [];
let currentEventFilter = 'all';
const highlightEventId = new URLSearchParams(window.location.search).get('event');

// Filter "Sold per eveniment" by event status. Kept purely client-side — the
// finance endpoint already returns every event with its is_past flag.
function setEventFilter(filter) {
    currentEventFilter = filter;
    document.querySelectorAll('#event-filters button').forEach(function (btn) {
        const on = btn.dataset.filter === filter;
        btn.className = 'px-3 py-1.5 text-xs font-medium rounded-lg ' +
            (on ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-secondary');
    });
    renderEvents();
}

function filteredEvents() {
    return (allEvents || []).filter(function (e) {
        if (currentEventFilter === 'active') return !e.is_past;
        if (currentEventFilter === 'past') return !!e.is_past;
        return true;
    });
}

document.addEventListener('DOMContentLoaded', function() { loadFinanceData(); });

async function loadFinanceData() {
    try {
        const response = await AmbiletAPI.get('/organizer/finance');
        if (response.success) {
            financeData = response.data;
            const events = financeData.events || [];
            // The cards read the ORG-WIDE figures the backend derives and keeps
            // reconciled (MarketplaceOrganizer::deriveBalances + the nightly
            // balances:reconcile). Summing the per-event column instead — what
            // this used to do — overstated the total, because each event's
            // available_balance is clamped at 0: an over-paid event contributed
            // nothing rather than reducing the balance.
            document.getElementById('available-balance').textContent = AmbiletUtils.formatCurrency(financeData.available_balance || 0);
            document.getElementById('pending-balance').textContent = AmbiletUtils.formatCurrency(financeData.pending_balance || 0);
            document.getElementById('total-paid-out').textContent = AmbiletUtils.formatCurrency(financeData.total_paid_out || 0);
            allEvents = events;
            renderEvents();
            renderBreakdowns();
            // Highlight event if coming from events page
            if (highlightEventId) {
                const targetRow = document.querySelector(`.event-row[data-event-id="${highlightEventId}"]`);
                if (targetRow) {
                    targetRow.classList.add('ring-2', 'ring-primary', 'bg-primary/5');
                    targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    // Auto-expand the highlighted event
                    toggleEventDetails(parseInt(highlightEventId));
                }
            }
        } else { showEmptyFinance(); }
    } catch (error) { console.error(error); showEmptyFinance(); }
}

function showEmptyFinance() {
    document.getElementById('available-balance').textContent = AmbiletUtils.formatCurrency(0);
    document.getElementById('pending-balance').textContent = AmbiletUtils.formatCurrency(0);
    document.getElementById('total-paid-out').textContent = AmbiletUtils.formatCurrency(0);
    document.getElementById('events-list').innerHTML = '<div class="p-12 text-center bg-white border rounded-2xl border-border text-muted">Nu exista evenimente</div>';
}

// ---------- Breakdown-uri sub cardurile de sus ----------
// Fiecare card poate fi desfăcut ca să arate DIN CE anume e compusă suma:
// disponibilul din evenimente, iar "în procesare" / "încasat" din deconturi.
function toggleBreakdown(which) {
    const box = document.getElementById('bd-' + which);
    const chev = document.getElementById('chev-' + which);
    if (!box) return;
    const nowHidden = box.classList.toggle('hidden');
    if (chev) chev.classList.toggle('rotate-180', !nowHidden);
}

function escAttr(s) {
    return String(s == null ? '' : s).replace(/"/g, '&quot;');
}

function renderBreakdowns() {
    const fmt = AmbiletUtils.formatCurrency;
    const events = allEvents || [];

    // Disponibil: evenimentele care contribuie cu sold pozitiv, plus cele
    // supra-decontate (afișate separat, ca sumă de regularizat).
    const contributing = events
        .filter(e => (e.available_balance || 0) > 0.005)
        .sort((a, b) => (b.available_balance || 0) - (a.available_balance || 0));
    const overpaid = events.filter(e => (e.available_balance_signed ?? 0) < -0.005);

    const availList = document.getElementById('bd-available-list');
    if (availList) {
        if (!contributing.length && !overpaid.length) {
            availList.innerHTML = '<p class="text-xs text-white/70">Niciun eveniment cu sold disponibil momentan.</p>';
        } else {
            availList.innerHTML =
                contributing.map(e => `
                    <div class="flex items-start justify-between gap-3 text-sm">
                        <span class="min-w-0 truncate text-white/90" title="${escAttr(e.title)}">${e.title}</span>
                        <span class="font-semibold whitespace-nowrap">${fmt(e.available_balance)}</span>
                    </div>`).join('') +
                overpaid.map(e => `
                    <div class="flex items-start justify-between gap-3 text-sm">
                        <span class="min-w-0 truncate text-white/60" title="${escAttr(e.title)}">${e.title} · de regularizat</span>
                        <span class="font-semibold text-red-200 whitespace-nowrap">− ${fmt(Math.abs(e.available_balance_signed))}</span>
                    </div>`).join('');
        }
    }

    renderPayoutBreakdown('pending', (financeData && financeData.payouts_pending) || [], 'Niciun decont în procesare.');
    renderPayoutBreakdown('paid', (financeData && financeData.payouts_completed) || [], 'Niciun decont plătit încă.');
}

function renderPayoutBreakdown(which, list, emptyLabel) {
    const fmt = AmbiletUtils.formatCurrency;
    const el = document.getElementById('bd-' + which + '-list');
    if (!el) return;
    if (!list.length) {
        el.innerHTML = '<p class="text-xs text-muted">' + emptyLabel + '</p>';
        return;
    }
    el.innerHTML = list.map(p => {
        // event_id NULL = decont care acoperă mai multe evenimente.
        const label = p.event_title || 'Decont multi-eveniment';
        const ref = p.decont_series || p.reference || ('#' + p.id);
        const when = p.completed_at || p.created_at;
        return `
            <div class="flex items-start justify-between gap-3 text-sm">
                <div class="min-w-0">
                    <p class="truncate text-secondary" title="${escAttr(label)}">${label}</p>
                    <p class="text-xs text-muted">${ref}${when ? ' · ' + AmbiletUtils.formatDate(when) : ''}</p>
                </div>
                <span class="font-semibold whitespace-nowrap text-secondary">${fmt(p.amount)}</span>
            </div>`;
    }).join('');
}

function renderEvents() {
    const container = document.getElementById('events-list');
    const events = filteredEvents();
    if (!events.length) {
        const label = currentEventFilter === 'active' ? 'Niciun eveniment activ'
            : currentEventFilter === 'past' ? 'Niciun eveniment încheiat'
            : 'Nu exista evenimente';
        container.innerHTML = '<div class="p-12 text-center bg-white border rounded-2xl border-border text-muted">' + label + '</div>';
        return;
    }

    const fmt = AmbiletUtils.formatCurrency;

    container.innerHTML = events.map(e => {
        const net = e.net_revenue || 0;
        const paid = e.total_paid_out || 0;
        const pending = e.pending_payout || 0;
        const avail = e.available_balance || 0;
        const signed = (e.available_balance_signed ?? avail);
        const pct = net > 0 ? Math.max(0, Math.min(100, Math.round((paid / net) * 100))) : 0;
        const meta = [e.starts_at ? AmbiletUtils.formatDate(e.starts_at) + (e.start_time ? ' ' + e.start_time : '') : '', e.venue_name, e.venue_city].filter(Boolean).join(' · ');

        const statusBadge = e.is_past
            ? '<span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded-full">Încheiat</span>'
            : '<span class="px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded-full">Activ</span>';

        let payoutButton;
        if (!e.is_past) {
            payoutButton = `<p class="mt-2 text-xs text-muted">Poți cere plata<br>după eveniment</p>`;
        } else if (pending > 0 && avail < 100) {
            payoutButton = `<p class="mt-2 text-xs text-warning">Plată solicitată</p>`;
        } else if (avail < 100) {
            payoutButton = `<p class="mt-2 text-xs text-muted">${avail > 0 ? 'Minim 100 RON<br>pentru plată' : 'Fără sold disponibil'}</p>`;
        } else {
            payoutButton = `<button onclick="event.stopPropagation(); openPayoutModal(${e.id}, '${String(e.title).replace(/'/g, "\\'")}', ${avail})" class="px-4 py-2 mt-2 text-xs font-medium text-white rounded-lg bg-primary hover:bg-primary-dark">Solicită plata</button>`;
        }

        return `
        <div class="overflow-hidden bg-white border rounded-2xl border-border event-row" data-event-id="${e.id}">
            <div class="p-5 cursor-pointer hover:bg-surface/40" onclick="toggleEventDetails(${e.id})">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-12 h-12 overflow-hidden rounded-lg bg-surface">
                        ${e.image ? `<img src="${e.image}" alt="" class="object-cover w-full h-full">` : '<div class="flex items-center justify-center w-full h-full text-muted"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>'}
                    </div>
                    <div class="flex items-start flex-1 min-w-0 gap-2">
                        <svg class="w-4 h-4 mt-1 transition-transform flex-shrink-0 text-muted event-expand-icon" id="expand-icon-${e.id}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        <div class="min-w-0">
                            <p class="font-semibold truncate text-secondary">${e.title}</p>
                            <p class="text-xs text-muted mt-0.5">${meta}</p>
                            <div class="flex flex-wrap items-center gap-2 mt-1">
                                ${statusBadge}
                                <span class="text-xs text-muted">${e.tickets_sold || 0} bilete vândute</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex-shrink-0 text-right">
                        <p class="text-xs text-muted">Disponibil de retras</p>
                        <p class="text-2xl font-bold ${avail > 0 ? 'text-primary' : 'text-muted'}">${fmt(avail)}</p>
                        ${payoutButton}
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 p-4 mt-4 sm:grid-cols-3 rounded-xl bg-surface/60">
                    <div>
                        <p class="text-xs text-muted">Încasat de la clienți</p>
                        <p class="font-semibold text-secondary">${fmt(e.gross_revenue)}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted">Comision Ambilet</p>
                        <p class="font-semibold text-amber-600">− ${fmt(e.commission_amount)}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted">Ți se cuvine</p>
                        <p class="font-semibold text-success">${fmt(net)}</p>
                    </div>
                </div>

                <div class="mt-3">
                    <div class="w-full h-2 overflow-hidden rounded-full bg-surface">
                        <div class="h-full transition-all bg-success" style="width: ${pct}%"></div>
                    </div>
                    <p class="mt-1.5 text-xs text-muted">Ai primit <span class="font-medium text-secondary">${fmt(paid)}</span> din ${fmt(net)}${pending > 0 ? ` · <span class="text-warning">${fmt(pending)} în procesare</span>` : ''}</p>
                    ${signed < -0.005 ? `<p class="mt-1 text-xs text-red-600">De regularizat: ${fmt(Math.abs(signed))} — s-a decontat mai mult decât valoarea biletelor rămase valide (rambursări ulterioare).</p>` : ''}
                </div>
            </div>

            <div class="hidden border-t border-border bg-slate-50 event-details-row" id="event-details-${e.id}">
                <div class="p-4">
                    <div class="flex items-center gap-2 mb-4 border-b border-border">
                        <button onclick="event.stopPropagation(); setEventTab(${e.id}, 'transactions')" class="px-4 py-2 text-sm font-medium border-b-2 border-primary text-primary event-tab-btn" data-event-id="${e.id}" data-tab="transactions">Tranzacții</button>
                        <button onclick="event.stopPropagation(); setEventTab(${e.id}, 'payouts')" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent text-muted hover:text-secondary event-tab-btn" data-event-id="${e.id}" data-tab="payouts">Plăți primite</button>
                    </div>
                    <div id="event-${e.id}-transactions" class="event-tab-content">
                        <div class="overflow-hidden bg-white border rounded-xl border-border">
                            <div class="divide-y divide-border" id="event-${e.id}-transactions-list">
                                <div class="p-4 text-sm text-center text-muted">Se încarcă...</div>
                            </div>
                        </div>
                    </div>
                    <div id="event-${e.id}-payouts" class="hidden event-tab-content">
                        <div class="overflow-x-auto bg-white border rounded-xl border-border">
                            <table class="w-full">
                                <thead class="bg-surface"><tr><th class="px-4 py-3 text-xs font-semibold text-left text-secondary">Decont</th><th class="px-4 py-3 text-xs font-semibold text-left text-secondary">Suma</th><th class="px-4 py-3 text-xs font-semibold text-left text-secondary">Status</th><th class="px-4 py-3 text-xs font-semibold text-left text-secondary">Data</th></tr></thead>
                                <tbody id="event-${e.id}-payouts-list" class="divide-y divide-border">
                                    <tr><td colspan="4" class="px-4 py-4 text-sm text-center text-muted">Se încarcă...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        `;
    }).join('');
}

// Track expanded events
const expandedEvents = new Set();

function toggleEventDetails(eventId) {
    const detailsRow = document.getElementById(`event-details-${eventId}`);
    const expandIcon = document.getElementById(`expand-icon-${eventId}`);

    if (expandedEvents.has(eventId)) {
        // Collapse
        detailsRow.classList.add('hidden');
        expandIcon.classList.remove('rotate-180');
        expandedEvents.delete(eventId);
    } else {
        // Expand
        detailsRow.classList.remove('hidden');
        expandIcon.classList.add('rotate-180');
        expandedEvents.add(eventId);
        // Load event-specific data
        loadEventFinanceDetails(eventId);
    }
}

function setEventTab(eventId, tabName) {
    // Update tab buttons for this event
    document.querySelectorAll(`.event-tab-btn[data-event-id="${eventId}"]`).forEach(btn => {
        btn.classList.remove('border-primary', 'text-primary');
        btn.classList.add('border-transparent', 'text-muted');
    });
    const activeBtn = document.querySelector(`.event-tab-btn[data-event-id="${eventId}"][data-tab="${tabName}"]`);
    if (activeBtn) {
        activeBtn.classList.add('border-primary', 'text-primary');
        activeBtn.classList.remove('border-transparent', 'text-muted');
    }

    // Show/hide tab content
    document.getElementById(`event-${eventId}-transactions`).classList.add('hidden');
    document.getElementById(`event-${eventId}-payouts`).classList.add('hidden');
    document.getElementById(`event-${eventId}-${tabName}`).classList.remove('hidden');
}

function loadEventFinanceDetails(eventId) {
    // Filter transactions for this event (exclude payout-type transactions)
    const eventTransactions = (financeData.transactions || []).filter(t =>
        t.event_id === eventId && t.type !== 'payout' && t.type !== 'payout_reversal'
    );
    const eventPayouts = (financeData.payouts || []).filter(p => p.event_id === eventId);

    renderEventTransactions(eventId, eventTransactions);
    renderEventPayouts(eventId, eventPayouts);
}

function renderEventTransactions(eventId, transactions) {
    const container = document.getElementById(`event-${eventId}-transactions-list`);
    if (!transactions.length) {
        container.innerHTML = '<div class="p-4 text-sm text-center text-muted">Nu exista tranzacții pentru acest eveniment</div>';
        return;
    }
    container.innerHTML = transactions.map(t => `
        <div class="flex items-center justify-between p-3 hover:bg-surface/50">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center ${t.type === 'sale' ? 'bg-success/10' : t.type === 'refund' ? 'bg-error/10' : 'bg-blue-100'}">
                    <svg class="w-4 h-4 ${t.type === 'sale' ? 'text-success' : t.type === 'refund' ? 'text-error' : 'text-blue-600'}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${t.type === 'sale' ? 'M12 4v16m8-8H4' : t.type === 'refund' ? 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6' : 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'}"/></svg>
                </div>
                <div><p class="text-sm font-medium text-secondary">${t.description}</p><p class="text-xs text-muted">${AmbiletUtils.formatDate(t.date)}</p></div>
            </div>
            <span class="text-sm font-semibold ${t.amount >= 0 ? 'text-success' : 'text-error'}">${t.amount >= 0 ? '+' : ''}${AmbiletUtils.formatCurrency(t.amount)}</span>
        </div>
    `).join('');
}

function renderEventPayouts(eventId, payouts) {
    const tbody = document.getElementById(`event-${eventId}-payouts-list`);
    if (!payouts.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-4 text-sm text-center text-muted">Nu exista plăți pentru acest eveniment</td></tr>';
        return;
    }
    tbody.innerHTML = payouts.map(p => {
        const statusColors = {
            'pending': { class: 'bg-warning/10 text-warning', label: 'În așteptare' },
            'approved': { class: 'bg-blue-100 text-blue-600', label: 'Aprobată' },
            'processing': { class: 'bg-blue-100 text-blue-600', label: 'În procesare' },
            'completed': { class: 'bg-success/10 text-success', label: 'Finalizată' },
            'rejected': { class: 'bg-error/10 text-error', label: 'Respinsă' },
            'cancelled': { class: 'bg-gray-100 text-gray-600', label: 'Anulată' }
        };
        const statusInfo = statusColors[p.status] || statusColors['pending'];
        const rejectionTooltip = p.status === 'rejected' && p.rejection_reason
            ? `<span class="relative ml-1 group cursor-help">
                <svg class="inline w-4 h-4 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="absolute z-50 invisible max-w-xs px-3 py-2 mb-2 text-xs text-white transition-all -translate-x-1/2 bg-gray-900 rounded-lg opacity-0 bottom-full left-1/2 group-hover:opacity-100 group-hover:visible whitespace-nowrap">
                    <span class="font-semibold">Motiv:</span> ${p.rejection_reason}
                </span>
               </span>`
            : '';
        return `
            <tr class="hover:bg-surface/50">
                <td class="px-4 py-3 text-sm font-medium text-secondary">${p.reference || '#' + p.id}</td>
                <td class="px-4 py-3 text-sm font-semibold">${AmbiletUtils.formatCurrency(p.amount)}</td>
                <td class="px-4 py-3">
                    <span class="px-2 py-0.5 ${statusInfo.class} text-xs rounded-full">${statusInfo.label}</span>
                    ${rejectionTooltip}
                </td>
                <td class="px-4 py-3 text-sm text-muted">${AmbiletUtils.formatDate(p.created_at)}</td>
            </tr>
        `;
    }).join('');
}

let currentPayoutMaxAmount = 0;

async function openPayoutModal(eventId, eventName, availableBalance) {
    currentPayoutMaxAmount = availableBalance;
    document.getElementById('payout-event-id').value = eventId;
    document.getElementById('payout-event-name').textContent = eventName;
    document.getElementById('payout-event-info').classList.remove('hidden');
    document.getElementById('modal-available-balance').textContent = AmbiletUtils.formatCurrency(availableBalance);
    document.getElementById('payout-amount').value = '';
    document.getElementById('payout-amount').max = availableBalance;
    document.getElementById('payout-amount-hint').textContent = `Suma minima: 100 RON, maxima: ${AmbiletUtils.formatCurrency(availableBalance)}`;
    document.getElementById('payout-notes').value = '';

    // Load bank accounts
    const select = document.getElementById('payout-account');
    select.innerHTML = '<option value="">Se incarca...</option>';
    try {
        const response = await AmbiletAPI.get('/organizer/bank-accounts');
        if (response.success && response.data) {
            const accounts = response.data.accounts || response.data || [];
            select.innerHTML = '<option value="">Selecteaza contul</option>';
            if (accounts.length === 0) {
                select.innerHTML = '<option value="">Nu ai conturi bancare adăugate. Adaugă unul în Setări.</option>';
            } else {
                accounts.forEach(acc => {
                    const label = (acc.bank_name || 'Cont') + ' - ****' + (acc.iban ? acc.iban.slice(-4) : acc.account_number?.slice(-4) || '');
                    select.innerHTML += `<option value="${acc.id}">${label}</option>`;
                });
            }
        }
    } catch (error) {
        console.error('Failed to load bank accounts:', error);
        select.innerHTML = '<option value="">Eroare la încărcarea conturilor</option>';
    }

    document.getElementById('payout-modal').classList.remove('hidden');
    document.getElementById('payout-modal').classList.add('flex');

    // Add input watcher for amount
    const amountInput = document.getElementById('payout-amount');
    amountInput.oninput = function() {
        const val = parseFloat(this.value) || 0;
        if (val > currentPayoutMaxAmount) {
            this.value = currentPayoutMaxAmount;
        }
    };
}

function closePayoutModal() {
    document.getElementById('payout-modal').classList.add('hidden');
    document.getElementById('payout-modal').classList.remove('flex');
}

async function submitPayoutRequest(e) {
    e.preventDefault();
    const eventId = document.getElementById('payout-event-id').value;
    const amount = parseFloat(document.getElementById('payout-amount').value);
    const notes = document.getElementById('payout-notes').value;
    const accountId = document.getElementById('payout-account').value;

    if (!accountId) {
        AmbiletNotifications.error('Te rugăm să selectezi un cont bancar');
        return;
    }
    if (amount < 100) {
        AmbiletNotifications.error('Suma minimă este 100 RON');
        return;
    }
    if (amount > currentPayoutMaxAmount) {
        AmbiletNotifications.error('Suma depășește soldul disponibil');
        return;
    }

    try {
        const response = await AmbiletAPI.post('/organizer/payouts', {
            amount: amount,
            event_id: eventId || null,
            bank_account_id: accountId,
            notes: notes || null
        });

        if (response.success) {
            AmbiletNotifications.success('Cererea de plată a fost trimisă cu succes!');
            closePayoutModal();
            loadFinanceData(); // Refresh data
        } else {
            AmbiletNotifications.error(response.message || 'Eroare la trimiterea cererii de plată');
        }
    } catch (error) {
        console.error('Payout request failed:', error);
        AmbiletNotifications.error('Eroare la trimiterea cererii de plată');
    }
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
