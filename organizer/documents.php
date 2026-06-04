<?php
/**
 * bilete.online — Organizator › Documente (v3).
 * Route: /organizator/documente
 *
 * Fiscal documents per activity. Mirrors the live ambilet behaviour: the
 * generation/history flow is intentionally gated behind a "serviciu
 * indisponibil" notice (document templates are not configured), so the page
 * exposes only a working activity selector + the notice — non-breaking.
 * Re-enabling is a matter of wiring the (already-mapped) organizer.documents.*
 * proxy actions into onActivitySelected().
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Documente';
$currentPage = 'documents';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="font-display text-3xl font-bold leading-none">Documente</h1>
            <p class="mt-1.5 text-sm text-ink-soft">Generează și descarcă documentele fiscale pentru activitățile tale.</p>
        </div>

        <!-- Activity selector -->
        <div class="mb-6 rounded-2xl border-2 border-ink bg-paper p-6">
            <label class="mb-2 block text-xs font-bold text-ink-soft">Selectează activitatea</label>
            <div class="relative w-full max-w-lg" id="event-dropdown-wrapper">
                <input type="text" id="event-search-input" autocomplete="off" placeholder="Caută activitate…" onfocus="openEventDropdown()" oninput="filterEventDropdown()"
                       class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-3 pr-10 text-sm font-medium outline-none transition focus:border-ink">
                <input type="hidden" id="event-selector" value="">
                <svg class="pointer-events-none absolute right-3 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                <div id="event-dropdown-list" class="absolute z-50 mt-1 hidden max-h-64 w-full overflow-y-auto rounded-xl border-2 border-ink bg-paper shadow-deep"></div>
            </div>
            <div id="events-loading" class="mt-2 text-sm text-ink-soft">Se încarcă activitățile…</div>
        </div>

        <!-- Service unavailable notice -->
        <div id="service-unavailable-message" class="hidden">
            <div class="flex items-start gap-4 rounded-2xl border-2 border-ochre/30 bg-ochre/10 p-6">
                <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-xl bg-ochre/20 text-ochre"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg></span>
                <div>
                    <h3 class="font-display text-base font-bold text-ink">Serviciu indisponibil momentan</h3>
                    <p class="mt-1 text-sm text-ink-soft">Generarea documentelor fiscale va fi disponibilă în curând. Lucrăm la configurare.</p>
                </div>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
let eventsData = {}, eventsList = [], selectedEventId = null;

function escHtml(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
function fmtShort(d) { if (!d) return ''; try { return new Date(d).toLocaleDateString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric' }); } catch (e) { return ''; } }
function isEventLive(ev) {
    if (ev.is_cancelled || ev.is_postponed || ev.is_past || ev.is_ended) return false;
    if (ev.status !== 'published' && ev.status !== 'active') return false;
    const end = ev.ends_at || ev.starts_at;
    return !end || new Date(end) >= new Date();
}

document.addEventListener('DOMContentLoaded', function () {
    if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth && !BileteOnlineAuth.requireOrganizerAuth()) return;
    loadEvents();
    document.addEventListener('click', function (e) {
        const w = document.getElementById('event-dropdown-wrapper');
        if (w && !w.contains(e.target)) closeEventDropdown();
    });
});

async function loadEvents() {
    try {
        const r = await BileteOnlineAPI.get('/organizer/documents/events');
        const events = (r && r.success && r.data && r.data.events) ? r.data.events : [];
        if (!events.length) { document.getElementById('events-loading').textContent = 'Nu ai activități.'; return; }
        events.sort((a, b) => {
            const aL = isEventLive(a), bL = isEventLive(b);
            if (aL && !bL) return -1; if (!aL && bL) return 1;
            const ad = new Date(a.starts_at || 0), bd = new Date(b.starts_at || 0);
            return aL ? ad - bd : bd - ad;
        });
        events.forEach(e => {
            eventsData[e.id] = e;
            const dot = isEventLive(e) ? '🟢 ' : '⚫ ';
            const meta = [e.starts_at ? fmtShort(e.starts_at) : '', e.venue_name || ''].filter(Boolean).join(' · ');
            eventsList.push({ id: e.id, label: dot + (e.name || 'Activitate') + (meta ? ' — ' + meta : '') });
        });
        document.getElementById('events-loading').classList.add('hidden');
        const pre = new URLSearchParams(window.location.search).get('event');
        if (pre && eventsData[pre]) selectEvent(pre);
    } catch (e) { document.getElementById('events-loading').textContent = 'Eroare la încărcarea activităților.'; }
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
    list.innerHTML = items.length
        ? items.map(i => '<div class="cursor-pointer px-4 py-3 text-sm transition hover:bg-paper-2 ' + (String(i.id) === String(selectedEventId) ? 'bg-vermilion/10 font-bold text-vermilion' : '') + '" onclick="selectEvent(\'' + i.id + '\')">' + escHtml(i.label) + '</div>').join('')
        : '<div class="px-4 py-3 text-sm text-ink-soft">Niciun rezultat.</div>';
}
function selectEvent(id) {
    document.getElementById('event-selector').value = id;
    const it = eventsList.find(e => String(e.id) === String(id));
    if (it) document.getElementById('event-search-input').value = it.label;
    closeEventDropdown();
    onActivitySelected();
}

// Generation/history is intentionally gated — show the unavailable notice.
function onActivitySelected() {
    const id = document.getElementById('event-selector').value;
    const msg = document.getElementById('service-unavailable-message');
    if (!id || !eventsData[id]) { msg.classList.add('hidden'); selectedEventId = null; return; }
    selectedEventId = parseInt(id);
    msg.classList.remove('hidden');
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
