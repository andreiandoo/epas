<?php
/**
 * bilete.online — Organizator › Participanți (v3).
 * Route: /organizator/participanti
 *
 * Per-activity participant list with check-in stats, search/filter, manual
 * check-in by control code, and CSV export. Ported from ambilet to v3 + shell,
 * wired to BileteOnlineAPI.organizer (getEvents / getParticipants / checkIn) and
 * the organizer.event.participants.export proxy action.
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Participanți';
$currentPage = 'participants';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="font-display text-3xl font-bold leading-none">Participanți</h1>
            <p class="mt-1.5 text-sm text-ink-soft">Gestionează participanții la activitățile tale.</p>
        </div>

        <!-- Activity selector + stats -->
        <div class="mb-6 rounded-2xl border-2 border-ink bg-paper p-4 lg:p-6">
            <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end">
                <div class="flex-1">
                    <label class="mb-1.5 block text-xs font-bold text-ink-soft">Selectează activitatea</label>
                    <div class="relative w-full lg:w-96" id="event-dropdown-wrapper">
                        <input type="text" id="event-search-input" autocomplete="off" placeholder="Caută activitate…" onfocus="openEventDropdown()" oninput="filterEventDropdown()"
                               class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-3 pr-10 text-sm font-medium outline-none transition focus:border-ink">
                        <input type="hidden" id="event-filter" value="">
                        <svg class="pointer-events-none absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        <div id="event-dropdown-list" class="absolute z-50 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-xl border-2 border-ink bg-paper shadow-deep"></div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button onclick="openScanner()" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-4 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        Check-in manual
                    </button>
                    <button onclick="exportParticipants()" class="inline-flex items-center gap-2 rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Export
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
                <div class="rounded-xl bg-paper-2 p-4 text-center">
                    <p class="font-display text-3xl font-bold" id="total-participants">0</p>
                    <p class="text-xs text-ink-soft">Total participanți</p>
                </div>
                <div class="rounded-xl bg-forest/10 p-4 text-center">
                    <p class="font-display text-3xl font-bold text-forest" id="checked-in">0</p>
                    <p class="text-xs text-forest">Check-in făcut</p>
                </div>
                <div class="rounded-xl bg-ochre/10 p-4 text-center">
                    <p class="font-display text-3xl font-bold text-ochre" id="pending-checkin">0</p>
                    <p class="text-xs text-ochre">În așteptare</p>
                </div>
                <div class="rounded-xl bg-vermilion/10 p-4 text-center">
                    <p class="font-display text-3xl font-bold text-vermilion" id="checkin-rate">0%</p>
                    <p class="text-xs text-vermilion">Rată check-in</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <select id="checkin-filter" class="rounded-xl border-2 border-ink/15 bg-paper px-4 py-2 text-sm font-medium outline-none transition focus:border-ink">
                <option value="">Toți participanții</option>
                <option value="checked_in">Check-in făcut</option>
                <option value="not_checked">În așteptare</option>
            </select>
            <input type="text" id="search-participant" placeholder="Caută participant…" class="w-64 rounded-xl border-2 border-ink/15 bg-paper px-4 py-2 text-sm font-medium outline-none transition focus:border-ink">
        </div>

        <!-- No activity selected -->
        <div id="no-event-message" class="hidden rounded-2xl border-2 border-ink bg-paper p-12 text-center">
            <span class="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-full bg-vermilion/10 text-vermilion">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </span>
            <h3 class="mb-1 font-display text-xl font-bold">Selectează o activitate</h3>
            <p class="text-ink-soft">Alege o activitate de mai sus pentru a vedea participanții.</p>
        </div>

        <!-- Participants table -->
        <div id="participants-table-container" class="overflow-hidden rounded-2xl border-2 border-ink bg-paper">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-paper-2 text-left">
                        <tr class="font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">
                            <th class="px-5 py-3">Participant</th>
                            <th class="px-5 py-3">Telefon</th>
                            <th class="px-5 py-3">Bilet</th>
                            <th class="px-5 py-3">Tip bilet</th>
                            <th class="px-5 py-3">Comandă</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody id="participants-list" class="divide-y divide-ink/10 text-sm"></tbody>
                </table>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<!-- Manual check-in modal -->
<div id="manual-checkin-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-ink/60 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-deep">
        <div class="mb-6 flex items-center justify-between">
            <h3 class="font-display text-2xl font-bold">Check-in manual</h3>
            <button onclick="closeManualCheckin()" aria-label="Închide" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-paper transition hover:bg-vermilion">×</button>
        </div>
        <form onsubmit="processManualCheckin(event)">
            <label class="mb-4 block">
                <span class="mb-1.5 block text-xs font-bold text-ink-soft">Cod control</span>
                <input type="text" id="manual-ticket-code" placeholder="Ex: X1SG7TLS" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2.5 text-sm font-medium uppercase outline-none focus:border-ink">
                <span class="mt-1 block text-xs text-ink-soft">Introdu codul de control afișat sub codul QR al biletului.</span>
            </label>
            <button type="submit" class="w-full rounded-full bg-vermilion px-4 py-3 font-bold text-paper transition hover:bg-vermilion-d">Verifică și fă check-in</button>
        </form>
    </div>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
let allParticipants = [];
let selectedEventId = null;
let eventsList = [];

function orgNotify(msg, type) {
    try { if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) { BileteOnlineNotifications[type || 'info'](msg); return; } } catch (e) {}
    if (type === 'error') alert(msg);
}
function esc(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
function fmtDate(d) { try { return BileteOnlineUtils.formatDate(d); } catch (e) { return d || ''; } }
function isEventLive(ev) {
    if (ev.is_cancelled || ev.is_postponed || ev.is_past || ev.is_ended) return false;
    if (ev.status !== 'published' && ev.status !== 'active') return false;
    const endDate = ev.ends_at || ev.starts_at;
    return !endDate || new Date(endDate) >= new Date();
}
function spoofEmail(email) {
    if (!email) return '-';
    const p = email.split('@'); if (p.length !== 2) return email;
    const n = p[0]; if (n.length <= 3) return n[0] + '***@' + p[1];
    return n.substring(0, 2) + '***' + n.slice(-1) + '@' + p[1];
}

document.addEventListener('DOMContentLoaded', function () {
    loadEvents();
    document.addEventListener('click', function (e) {
        const w = document.getElementById('event-dropdown-wrapper');
        if (w && !w.contains(e.target)) closeEventDropdown();
    });
    document.getElementById('checkin-filter').addEventListener('change', loadParticipants);
    document.getElementById('search-participant').addEventListener('input', BileteOnlineUtils.debounce(filterAndRender, 300));
});

async function loadEvents() {
    try {
        const r = await BileteOnlineAPI.organizer.getEvents();
        const rows = (r && (r.data && (r.data.events || r.data.items))) || (r && r.data) || [];
        const events = Array.isArray(rows) ? rows : [];
        if (!events.length) { document.getElementById('event-search-input').placeholder = 'Nu ai activități'; showNoEvent(); return; }
        events.sort((a, b) => {
            const aL = isEventLive(a), bL = isEventLive(b);
            if (aL && !bL) return -1; if (!aL && bL) return 1;
            const ad = new Date(a.starts_at || 0), bd = new Date(b.starts_at || 0);
            return aL ? ad - bd : bd - ad;
        });
        eventsList = events.map(ev => {
            const dot = isEventLive(ev) ? '🟢 ' : '⚫ ';
            const date = ev.starts_at ? fmtDate(ev.starts_at) : '';
            const meta = [date, ev.venue_name || (ev.venue && ev.venue.name) || ''].filter(Boolean).join(' · ');
            return { id: ev.id, label: dot + (ev.name || ev.title || ('#' + ev.id)) + (meta ? ' — ' + meta : '') };
        });
        const urlId = new URLSearchParams(window.location.search).get('event');
        const matched = urlId ? eventsList.find(e => String(e.id) === String(urlId)) : null;
        selectEvent(matched ? matched.id : eventsList[0].id);
    } catch (e) { document.getElementById('event-search-input').placeholder = 'Eroare la încărcare'; showNoEvent(); }
}

function openEventDropdown() { renderEventDropdown(eventsList); document.getElementById('event-dropdown-list').classList.remove('hidden'); }
function closeEventDropdown() { document.getElementById('event-dropdown-list').classList.add('hidden'); }
function filterEventDropdown() {
    const q = document.getElementById('event-search-input').value.toLowerCase().trim();
    renderEventDropdown(q ? eventsList.filter(e => e.label.toLowerCase().includes(q)) : eventsList);
    document.getElementById('event-dropdown-list').classList.remove('hidden');
}
function renderEventDropdown(items) {
    const list = document.getElementById('event-dropdown-list');
    if (!items.length) { list.innerHTML = '<div class="px-4 py-3 text-sm text-ink-soft">Niciun rezultat.</div>'; return; }
    list.innerHTML = items.map(i =>
        '<div class="cursor-pointer px-4 py-3 text-sm transition hover:bg-paper-2 ' + (String(i.id) === String(selectedEventId) ? 'bg-vermilion/10 font-bold text-vermilion' : '') + '" onclick="selectEvent(\'' + i.id + '\')">' + esc(i.label) + '</div>'
    ).join('');
}
function selectEvent(id) {
    document.getElementById('event-filter').value = id;
    const it = eventsList.find(e => String(e.id) === String(id));
    if (it) document.getElementById('event-search-input').value = it.label;
    closeEventDropdown();
    selectedEventId = id;
    loadParticipants();
}
function showNoEvent() { document.getElementById('no-event-message').classList.remove('hidden'); document.getElementById('participants-table-container').classList.add('hidden'); }
function hideNoEvent() { document.getElementById('no-event-message').classList.add('hidden'); document.getElementById('participants-table-container').classList.remove('hidden'); }

async function loadParticipants() {
    const eventId = document.getElementById('event-filter').value;
    selectedEventId = eventId;
    if (!eventId) { showNoEvent(); updateStats({ total: 0, checked_in: 0, pending: 0, rate: 0 }); return; }
    hideNoEvent();
    const params = {};
    const cs = document.getElementById('checkin-filter').value;
    if (cs) params.checked_in = (cs === 'checked_in') ? 1 : 0;
    document.getElementById('participants-list').innerHTML = '<tr><td colspan="7" class="px-5 py-12 text-center text-ink-soft">Se încarcă…</td></tr>';
    try {
        const r = await BileteOnlineAPI.organizer.getParticipants(eventId, params);
        const data = (r && r.data) || {};
        allParticipants = data.participants || (Array.isArray(data) ? data : []) || [];
        filterAndRender();
        updateStats(data.stats || {});
    } catch (e) {
        allParticipants = []; renderParticipants([]); updateStats({ total: 0, checked_in: 0, pending: 0, rate: 0 });
    }
}

function filterAndRender() {
    const q = document.getElementById('search-participant').value.toLowerCase().trim();
    let list = allParticipants;
    if (q) list = allParticipants.filter(p =>
        (p.name || '').toLowerCase().includes(q) || (p.email || '').toLowerCase().includes(q) ||
        (p.control_code || '').toLowerCase().includes(q) || (p.ticket_code || '').toLowerCase().includes(q));
    renderParticipants(list);
}

function renderParticipants(participants) {
    const c = document.getElementById('participants-list');
    if (!participants.length) { c.innerHTML = '<tr><td colspan="7" class="px-5 py-12 text-center text-ink-soft">Nu există participanți pentru această activitate.</td></tr>'; return; }
    c.innerHTML = participants.map(p => {
        const initials = (p.name || '').split(' ').map(n => n[0] || '').join('').substring(0, 2).toUpperCase() || '?';
        const orderDate = p.order_date ? fmtDate(p.order_date) : (p.created_at ? fmtDate(p.created_at) : '-');
        const seat = p.seat_label ? '<div class="mt-1 text-xs text-ink-soft">' + esc(p.seat_label) + '</div>' : '';
        const status = p.checked_in
            ? '<span class="inline-flex items-center gap-1.5 rounded-full bg-forest/15 px-3 py-1 text-sm font-bold text-forest"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Check-in</span>'
            : '<span class="inline-flex items-center gap-1.5 rounded-full bg-ochre/15 px-3 py-1 text-sm font-bold text-ochre"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Așteptare</span>';
        const action = !p.checked_in
            ? '<button onclick="doCheckin(\'' + (p.control_code || p.ticket_code || '').replace(/'/g, "\\'") + '\')" class="inline-flex items-center gap-2 rounded-full bg-forest px-3 py-1.5 text-sm font-bold text-paper transition hover:bg-forest-l"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>Check-in</button>'
            : '<span class="text-xs text-ink-soft">' + (p.checked_in_at ? fmtDate(p.checked_in_at) : '') + '</span>';
        return '<tr class="hover:bg-paper-2/60">'
            + '<td class="px-5 py-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-full bg-vermilion/10 text-sm font-bold text-vermilion">' + initials + '</span><div><p class="font-medium">' + (esc(p.name) || '-') + '</p><p class="text-sm text-ink-soft">' + esc(spoofEmail(p.email)) + '</p></div></div></td>'
            + '<td class="px-5 py-4 text-sm">' + (esc(p.phone) || '-') + '</td>'
            + '<td class="px-5 py-4"><code class="rounded bg-paper-2 px-2 py-1 text-sm font-bold">' + esc(p.control_code || '-') + '</code><div class="mt-1 text-xs text-ink-soft">#' + (p.ticket_id || p.id || '-') + '</div></td>'
            + '<td class="px-5 py-4"><span class="inline-block rounded-lg px-2.5 py-1 text-sm font-medium ' + (p.is_invitation ? 'bg-sky/10 text-sky' : 'bg-vermilion/10 text-vermilion') + '">' + (esc(p.ticket_type) || '-') + '</span>' + (p.is_invitation ? '<span class="ml-1 rounded bg-sky/15 px-2 py-0.5 text-[10px] font-bold uppercase text-sky">Invitație</span>' : '') + seat + '</td>'
            + '<td class="px-5 py-4"><div class="text-sm font-medium">' + (p.is_invitation ? '<span class="text-sky">Invitație</span>' : esc(p.order_number || ('#' + (p.order_id || '-')))) + '</div><div class="text-xs text-ink-soft">' + orderDate + '</div></td>'
            + '<td class="px-5 py-4">' + status + '</td>'
            + '<td class="px-5 py-4 text-right">' + action + '</td>'
            + '</tr>';
    }).join('');
}

function updateStats(s) {
    s = s || {};
    document.getElementById('total-participants').textContent = s.total || 0;
    document.getElementById('checked-in').textContent = s.checked_in || 0;
    document.getElementById('pending-checkin').textContent = (s.pending != null) ? s.pending : ((s.total || 0) - (s.checked_in || 0));
    document.getElementById('checkin-rate').textContent = (s.rate || 0) + '%';
}

function openScanner() { const m = document.getElementById('manual-checkin-modal'); m.classList.remove('hidden'); m.classList.add('flex'); document.getElementById('manual-ticket-code').focus(); }
function closeManualCheckin() { const m = document.getElementById('manual-checkin-modal'); m.classList.add('hidden'); m.classList.remove('flex'); }
function processManualCheckin(e) { e.preventDefault(); const code = document.getElementById('manual-ticket-code').value.trim(); if (!code) return; closeManualCheckin(); doCheckin(code); }

async function doCheckin(code) {
    if (!selectedEventId) { orgNotify('Selectează o activitate.', 'error'); return; }
    try {
        const r = await BileteOnlineAPI.organizer.checkIn(selectedEventId, code);
        if (r && r.success) { orgNotify('Check-in reușit: ' + code, 'success'); loadParticipants(); }
        else orgNotify((r && r.message) || 'Eroare la check-in.', 'error');
    } catch (e) { orgNotify((e && e.message) || 'Eroare la check-in.', 'error'); }
}

async function exportParticipants() {
    if (!selectedEventId) { orgNotify('Selectează o activitate.', 'error'); return; }
    try {
        const token = (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken) ? BileteOnlineAuth.getToken() : null;
        if (!token) { orgNotify('Sesiune expirată. Autentifică-te din nou.', 'error'); return; }
        orgNotify('Se generează lista…', 'info');
        const base = (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php';
        const resp = await fetch(base + '?action=organizer.event.participants.export&event_id=' + encodeURIComponent(selectedEventId), { headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'text/csv' } });
        if (!resp.ok) throw new Error('Eroare la export');
        const blob = await resp.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = 'participanti-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a); a.click(); window.URL.revokeObjectURL(url); a.remove();
        orgNotify('Lista participanților a fost exportată.', 'success');
    } catch (e) { orgNotify(e.message || 'Eroare la export', 'error'); }
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
