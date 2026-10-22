<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Documente';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'documents';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
        <main class="flex-1 p-4 lg:p-8">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-secondary">Documente fiscale</h1>
                <p class="text-sm text-muted">Generează, descarcă și ține evidența documentelor pe care le depui la primărie pentru fiecare eveniment.</p>
            </div>

            <!-- How it works -->
            <div class="grid gap-4 mb-6 md:grid-cols-2">
                <div class="flex gap-3 p-4 bg-white border rounded-2xl border-border">
                    <span class="flex items-center justify-center flex-shrink-0 w-8 h-8 text-sm font-bold rounded-full bg-primary/10 text-primary">1</span>
                    <div>
                        <p class="text-sm font-semibold text-secondary">Înainte de eveniment: cererea de vizare</p>
                        <p class="text-xs text-muted mt-0.5">După publicare o generezi și o depui la primărie cu cel puțin 2 zile înainte de eveniment. Dacă se schimbă prețul, stocul sau codurile de reducere ale biletelor, trebuie regenerată și depusă din nou.</p>
                    </div>
                </div>
                <div class="flex gap-3 p-4 bg-white border rounded-2xl border-border">
                    <span class="flex items-center justify-center flex-shrink-0 w-8 h-8 text-sm font-bold rounded-full bg-primary/10 text-primary">2</span>
                    <div>
                        <p class="text-sm font-semibold text-secondary">După eveniment: impozit + PV distrugere</p>
                        <p class="text-xs text-muted mt-0.5">După ce evenimentul s-a încheiat le generezi împreună și le depui împreună la aceeași primărie, până pe 10 a lunii următoare.</p>
                    </div>
                </div>
            </div>

            <div class="grid items-start gap-6 lg:grid-cols-[22rem_minmax(0,1fr)]">
                <!-- Events list -->
                <aside id="events-panel" class="overflow-hidden bg-white border rounded-2xl border-border lg:sticky lg:top-4">
                    <div class="p-4 border-b border-border">
                        <div class="flex items-baseline justify-between mb-3">
                            <h2 class="text-base font-bold text-secondary">Evenimente</h2>
                            <span id="events-count" class="text-xs text-muted"></span>
                        </div>
                        <label for="event-search-input" class="sr-only">Caută eveniment</label>
                        <input type="search" id="event-search-input" class="w-full input" placeholder="Caută după nume sau locație..." autocomplete="off" oninput="renderEventList()">
                        <div id="event-filters" class="flex flex-wrap gap-1.5 mt-3" role="tablist" aria-label="Filtrează evenimentele"></div>
                    </div>
                    <div id="events-loading" class="p-4 text-sm text-muted">Se încarcă evenimentele...</div>
                    <ul id="event-list" class="hidden overflow-y-auto divide-y divide-border max-h-[28rem] lg:max-h-[calc(100vh-14rem)]"></ul>
                </aside>

                <!-- Selected event -->
                <section id="event-detail-section" class="min-w-0 scroll-mt-4" aria-live="polite">
                    <div id="event-detail-empty" class="hidden p-10 text-center bg-white border border-dashed lg:block rounded-2xl border-border">
                        <svg class="w-10 h-10 mx-auto mb-3 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p class="text-sm font-semibold text-secondary">Alege un eveniment din listă</p>
                        <p class="mt-1 text-sm text-muted">Vezi aici documentele lui, termenele și unde le depui.</p>
                    </div>

                    <button type="button" id="back-to-list" onclick="backToList()" class="hidden items-center gap-1.5 mb-4 text-sm font-semibold text-primary hover:underline lg:!hidden">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Toate evenimentele
                    </button>

                    <div id="event-detail-loading" class="hidden p-6 mb-6 text-sm bg-white border rounded-2xl border-border text-muted">Se încarcă documentele evenimentului...</div>

                    <div id="event-detail-body" class="hidden">
                        <!-- Event header + where to file -->
                        <div class="mb-6 overflow-hidden bg-white border rounded-2xl border-border">
                            <div class="p-6 border-b border-border">
                                <div class="flex items-start gap-4">
                                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-primary/10 rounded-xl">
                                        <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h2 id="event-name" class="text-lg font-bold text-secondary"></h2>
                                        <p id="event-meta" class="mt-1 text-sm text-muted"></p>
                                    </div>
                                    <span id="event-status-badge"></span>
                                </div>
                            </div>
                            <div class="p-6">
                                <h3 class="mb-3 text-xs font-semibold tracking-wide uppercase text-muted">Unde se depun documentele</h3>
                                <div id="registry-info" class="text-sm text-secondary"></div>
                            </div>
                        </div>

                        <div id="manages-notice" class="hidden p-4 mb-6 text-sm border rounded-2xl bg-primary/5 border-primary/20 text-secondary">
                            <strong>Documentele acestui eveniment le pregătim și le depunem noi.</strong>
                            Le poți genera și descărca și tu oricând, dar nu e nevoie să le depui.
                        </div>

                        <div class="grid gap-6 mb-6 xl:grid-cols-2">
                            <div id="group-cerere" class="p-6 bg-white border rounded-2xl border-border"></div>
                            <div id="group-impozit_pv" class="p-6 bg-white border rounded-2xl border-border"></div>
                        </div>

                        <!-- History -->
                        <div class="overflow-hidden bg-white border rounded-2xl border-border">
                            <div class="p-6 border-b border-border">
                                <h2 class="text-lg font-bold text-secondary">Istoric documente</h2>
                                <p class="mt-1 text-sm text-muted">Toate versiunile generate pentru acest eveniment. Le poți descărca oricând.</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-surface">
                                        <tr>
                                            <th class="px-6 py-3 text-sm font-semibold text-left text-secondary">Document</th>
                                            <th class="px-6 py-3 text-sm font-semibold text-left text-secondary">Generat</th>
                                            <th class="px-6 py-3 text-sm font-semibold text-left text-secondary">Versiune</th>
                                            <th class="px-6 py-3 text-sm font-semibold text-right text-secondary">Descarcă</th>
                                        </tr>
                                    </thead>
                                    <tbody id="event-documents-list" class="divide-y divide-border"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function() { AmbiletAuth.requireOrganizerAuth(); });

const ICON_DOWNLOAD = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>';
const ICON_ALERT = '<svg class="flex-shrink-0 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>';
// Each problem has its own colour, on the filters and on the events in the list.
const TONES = {
    warning: { chip: 'bg-warning/15 text-secondary', dot: 'bg-warning', active: 'bg-warning text-white' },
    error: { chip: 'bg-error/10 text-error', dot: 'bg-error', active: 'bg-error text-white' },
    slate: { chip: 'bg-secondary/10 text-secondary', dot: 'bg-secondary', active: 'bg-secondary text-white' }
};
const ISSUES = {
    cerere_missing: { tone: 'warning', short: 'Cerere de generat' },
    cerere_stale: { tone: 'error', short: 'De regenerat' },
    impozit_pv_missing: { tone: 'slate', short: 'Impozit + PV' }
};
const FILTERS = [
    { key: 'all', label: 'Toate' },
    { key: 'upcoming', label: 'Viitoare' },
    { key: 'ended', label: 'Încheiate' },
    // Problem filters show up only when there is something to fix.
    { key: 'cerere_missing', label: 'Cerere de generat', issue: true },
    { key: 'cerere_stale', label: 'De regenerat', issue: true },
    { key: 'impozit_pv_missing', label: 'Impozit + PV', issue: true }
];

let eventsData = {};
let eventsList = [];
let activeFilter = 'all';
let selectedEventId = null;
let current = null;
let busy = false;

document.addEventListener('DOMContentLoaded', loadEvents);

function isDesktop() {
    return window.matchMedia('(min-width: 1024px)').matches;
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

function parseDate(dateStr) {
    if (!dateStr) return null;
    const date = new Date(String(dateStr).replace(' ', 'T'));
    return isNaN(date) ? null : date;
}

function formatDateShort(dateStr) {
    const date = parseDate(dateStr);
    return date ? date.toLocaleDateString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '';
}

// Days from today to a Y-m-d deadline (negative = passed).
function daysUntil(ymd) {
    if (!ymd) return null;
    const p = String(ymd).split('-').map(Number);
    const due = new Date(p[0], p[1] - 1, p[2]);
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    return Math.round((due - today) / 86400000);
}

function issueOf(ev) {
    if (!ev.attention || !ISSUES[ev.attention]) return null;
    const issue = ISSUES[ev.attention];
    const overdue = (daysUntil(ev.attention_deadline) ?? 1) < 0;
    return { issue: issue, tone: overdue ? 'error' : issue.tone, overdue: overdue };
}

function isUpcoming(ev) {
    return ev.status === 'published' || ev.status === 'draft';
}

async function loadEvents() {
    const loading = document.getElementById('events-loading');
    try {
        const response = await AmbiletAPI.get('/organizer/documents/events');
        const events = (response.success && response.data.events) || [];
        if (!events.length) {
            loading.textContent = 'Nu ai încă evenimente.';
            document.getElementById('event-detail-empty').classList.remove('lg:block');
            return;
        }
        setEvents(events);
        loading.classList.add('hidden');
        document.getElementById('event-list').classList.remove('hidden');

        const preselect = new URLSearchParams(window.location.search).get('event');
        if (preselect && eventsData[preselect]) {
            selectEvent(preselect);
        } else if (isDesktop()) {
            const first = eventsList.find(e => e.attention) || eventsList.find(isUpcoming) || eventsList[0];
            if (first) selectEvent(first.id, { scroll: false });
        }
    } catch (error) {
        console.error('Error loading events:', error);
        loading.textContent = 'Nu am putut încărca evenimentele. Reîncarcă pagina.';
    }
}

function setEvents(events) {
    // Problems first, then upcoming (soonest first), then the rest (latest first).
    const rank = e => e.attention ? 0 : (isUpcoming(e) ? 1 : 2);
    events.sort((a, b) => {
        if (rank(a) !== rank(b)) return rank(a) - rank(b);
        const aDate = parseDate(a.starts_at) || 0, bDate = parseDate(b.starts_at) || 0;
        return rank(a) === 2 ? bDate - aDate : aDate - bDate;
    });
    eventsData = {};
    events.forEach(e => { eventsData[e.id] = e; });
    eventsList = events;
    document.getElementById('events-count').textContent = events.length + (events.length === 1 ? ' eveniment' : ' evenimente');
    if (ISSUES[activeFilter] && !events.some(e => e.attention === activeFilter)) activeFilter = 'all';
    renderFilters();
    renderEventList();
}

function filterCount(key) {
    if (ISSUES[key]) return eventsList.filter(e => e.attention === key).length;
    if (key === 'upcoming') return eventsList.filter(isUpcoming).length;
    if (key === 'ended') return eventsList.filter(e => e.status === 'ended').length;
    return eventsList.length;
}

function renderFilters() {
    document.getElementById('event-filters').innerHTML = FILTERS.map(f => {
        const count = filterCount(f.key);
        if (f.issue && !count) return '';
        const active = f.key === activeFilter;
        const tone = f.issue ? TONES[ISSUES[f.key].tone] : null;
        const cls = tone
            ? (active ? tone.active : tone.chip + ' hover:opacity-80')
            : (active ? 'bg-secondary text-white' : 'bg-surface text-muted hover:text-secondary');
        return '<button type="button" role="tab" aria-selected="' + active + '" onclick="setFilter(\'' + f.key + '\')" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors ' + cls + '">' +
            (tone ? '<span class="w-2 h-2 rounded-full ' + (active ? 'bg-white' : tone.dot) + '"></span>' : '') +
            escapeHtml(f.label) + ' <span class="' + (active ? 'text-white/70' : 'opacity-70') + '">' + count + '</span></button>';
    }).join('');
}

function setFilter(key) {
    activeFilter = key;
    renderFilters();
    renderEventList();
}

function renderEventList() {
    const query = document.getElementById('event-search-input').value.toLowerCase().trim();
    const items = eventsList.filter(e => {
        if (ISSUES[activeFilter] && e.attention !== activeFilter) return false;
        if (activeFilter === 'upcoming' && !isUpcoming(e)) return false;
        if (activeFilter === 'ended' && e.status !== 'ended') return false;
        if (!query) return true;
        return [e.name, e.venue_name, e.venue_city].filter(Boolean).join(' ').toLowerCase().includes(query);
    });
    const list = document.getElementById('event-list');
    if (!items.length) {
        list.innerHTML = '<li class="px-4 py-8 text-sm text-center text-muted">Niciun eveniment' + (query ? ' pentru „' + escapeHtml(query) + '”' : ' aici') + '.</li>';
        return;
    }
    list.innerHTML = items.map(e => {
        const date = parseDate(e.starts_at);
        const selected = String(e.id) === String(selectedEventId);
        const problem = issueOf(e);
        const place = [e.venue_name, e.venue_city].filter(Boolean).join(', ');
        const badge = problem
            ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-semibold rounded-md ' + TONES[problem.tone].chip + '">' + escapeHtml(problem.overdue ? 'Întârziat' : problem.issue.short) + '</span>'
            : '<span class="text-[11px] text-muted">' + escapeHtml(e.status_label || '') + '</span>';
        return '<li><button type="button" onclick="selectEvent(\'' + e.id + '\')" ' + (selected ? 'aria-current="true" ' : '') +
            'class="flex items-start w-full gap-3 px-4 py-3 text-left transition-colors border-l-4 ' +
            (selected ? 'border-primary bg-primary/5' : 'border-transparent hover:bg-surface') + '">' +
            '<span class="flex flex-col items-center justify-center flex-shrink-0 w-11 h-12 border rounded-lg border-border bg-white">' +
            (date ? '<span class="text-base font-bold leading-none text-secondary">' + date.getDate() + '</span><span class="mt-0.5 text-[10px] font-semibold uppercase text-muted">' + escapeHtml(date.toLocaleDateString('ro-RO', { month: 'short' }).replace('.', '')) + '</span>' : '<span class="text-xs text-muted">—</span>') +
            '</span>' +
            '<span class="flex-1 min-w-0">' +
            '<span class="block text-sm font-semibold truncate ' + (selected ? 'text-primary' : 'text-secondary') + '">' + escapeHtml(e.name || 'Eveniment') + '</span>' +
            '<span class="block text-xs truncate text-muted">' + escapeHtml([date ? date.getFullYear() : '', place].filter(Boolean).join(' · ')) + '</span>' +
            '<span class="block mt-1">' + badge + '</span>' +
            '</span></button></li>';
    }).join('');
}

function selectEvent(id, opts) {
    opts = opts || {};
    const url = new URL(window.location.href);
    url.searchParams.set('event', id);
    history.replaceState(null, '', url);

    selectedEventId = parseInt(id, 10);
    renderEventList();

    // Mobile: the list makes room for the details.
    document.getElementById('events-panel').classList.add('hidden', 'lg:block');
    document.getElementById('back-to-list').classList.remove('hidden');
    document.getElementById('back-to-list').classList.add('flex');
    document.getElementById('event-detail-empty').classList.remove('lg:block');

    loadEventStatus(selectedEventId);
    if (opts.scroll !== false) revealDetail();
}

function backToList() {
    document.getElementById('events-panel').classList.remove('hidden');
    document.getElementById('back-to-list').classList.add('hidden');
    document.getElementById('back-to-list').classList.remove('flex');
    document.getElementById('events-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function revealDetail() {
    const section = document.getElementById('event-detail-section');
    const top = section.getBoundingClientRect().top;
    if (!isDesktop() || top < 0 || top > window.innerHeight * 0.5) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

async function loadEventStatus(id) {
    const loading = document.getElementById('event-detail-loading');
    const body = document.getElementById('event-detail-body');
    loading.textContent = 'Se încarcă documentele evenimentului...';
    loading.classList.remove('hidden');
    body.classList.add('hidden');
    try {
        const response = await AmbiletAPI.get('/organizer/documents/fiscal/' + id);
        if (selectedEventId !== id) return;
        render(response.data);
    } catch (error) {
        console.error('Error loading event documents:', error);
        loading.textContent = error.message || 'Nu am putut încărca documentele. Încearcă din nou.';
    }
}

function render(data) {
    current = data;
    document.getElementById('event-detail-loading').classList.add('hidden');
    document.getElementById('event-detail-body').classList.remove('hidden');

    const ev = data.event;
    document.getElementById('event-name').textContent = ev.name;
    const dates = ev.date && ev.end_date && ev.end_date !== ev.date ? ev.date + ' – ' + ev.end_date : (ev.date || '');
    document.getElementById('event-meta').textContent = [dates, ev.venue, ev.city].filter(Boolean).join(' · ');

    let badge = '<span class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-success/10 text-success">Live</span>';
    if (ev.is_cancelled) badge = '<span class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-error/10 text-error">Anulat</span>';
    else if (ev.is_ended) badge = '<span class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-surface text-muted">Încheiat</span>';
    else if (!ev.is_published) badge = '<span class="px-2.5 py-1 text-xs font-semibold rounded-lg bg-surface text-muted">Nepublicat</span>';
    document.getElementById('event-status-badge').innerHTML = badge;

    document.getElementById('registry-info').innerHTML = renderRegistry(data.registry);
    document.getElementById('manages-notice').classList.toggle('hidden', !data.manages_documents);

    renderGroup('cerere', data.groups.cerere, data);
    renderGroup('impozit_pv', data.groups.impozit_pv, data);
    renderHistory(data.history);
}

function renderRegistry(r) {
    if (!r) {
        return '<p class="text-error">Evenimentul nu are direcția fiscală (primăria) setată. Scrie-ne și o configurăm, apoi poți genera documentele.</p>';
    }
    let html = '<p class="font-semibold">' + escapeHtml(r.name) + (r.subname ? ' <span class="font-normal text-muted">· ' + escapeHtml(r.subname) + '</span>' : '') + '</p>';
    if (r.address) html += '<p class="text-muted">' + escapeHtml(r.address) + '</p>';
    if (r.method === 'third_party') {
        html += '<p class="mt-2">Depunerea se face prin <strong>' + escapeHtml(r.third_party_name || 'platforma primăriei') + '</strong>' +
            (r.third_party_url ? ': <a href="' + escapeHtml(r.third_party_url) + '" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">deschide platforma</a>' : '') + '.</p>';
        if (r.third_party_procedure) html += '<p class="mt-1 text-xs whitespace-pre-line text-muted">' + escapeHtml(r.third_party_procedure) + '</p>';
    } else if (r.email) {
        html += '<p class="mt-2">Documentele se trimit pe email la <a href="mailto:' + escapeHtml(r.email) + '" class="font-semibold text-primary hover:underline">' + escapeHtml(r.email) + '</a>, ca atașamente PDF.</p>';
    } else {
        html += '<p class="mt-2 text-muted">Documentele se depun la registratura primăriei de mai sus.</p>';
    }
    return html;
}

function chip(text, tone) {
    const tones = {
        muted: 'bg-surface text-muted',
        warning: 'bg-warning/10 text-warning',
        error: 'bg-error/10 text-error',
        success: 'bg-success/10 text-success'
    };
    return '<span class="px-2.5 py-1 text-xs font-semibold rounded-lg whitespace-nowrap ' + tones[tone] + '">' + escapeHtml(text) + '</span>';
}

function renderGroup(key, g, data) {
    const isCerere = key === 'cerere';
    const box = document.getElementById('group-' + key);
    const title = isCerere ? 'Cerere de vizare a biletelor' : 'Impozit spectacole + PV distrugere bilete';
    const subtitle = isCerere ? 'Înainte de eveniment' : 'După eveniment · se generează și se depun împreună';

    let status;
    if (g.stale) status = chip('Trebuie regenerată', 'error');
    else if (g.filed) status = chip('Depus', 'success');
    else if (g.generated && g.overdue) status = chip('Termen depășit', 'error');
    else if (g.generated) status = chip('Generat · de depus', 'warning');
    else if (g.blocked) status = chip('Indisponibil', 'muted');
    else if (g.overdue) status = chip('Termen depășit', 'error');
    else status = chip('De generat', 'warning');

    let html = '<div class="flex items-start justify-between gap-3 mb-4"><div>' +
        '<p class="text-xs font-semibold tracking-wide uppercase text-muted">' + subtitle + '</p>' +
        '<h3 class="mt-1 text-base font-bold text-secondary">' + title + '</h3></div>' + status + '</div>';

    if (g.deadline) {
        html += '<p class="mb-4 text-sm text-secondary">Termen de depunere: <strong>' + escapeHtml(g.deadline) + '</strong>' +
            (isCerere ? ' <span class="text-muted">(cu 2 zile înainte de eveniment)</span>' : ' <span class="text-muted">(10 a lunii de după eveniment)</span>') + '</p>';
    }

    if (g.stale) {
        html += '<div class="flex gap-3 p-4 mb-4 text-sm border rounded-xl border-error/30 bg-error/5 text-error">' + ICON_ALERT +
            '<div><p class="font-semibold">Cererea de vizare nu mai corespunde biletelor</p>' +
            '<p class="mt-1 text-secondary">După ce ai generat-o s-au schimbat prețul, stocul sau codurile de reducere ale biletelor. Regenerează cererea și depune noua versiune la primărie' +
            (g.filed_at ? ', chiar dacă ai depus-o deja pe cea veche' : '') + '.</p></div></div>';
    } else if (g.refile && !g.filed) {
        html += '<p class="p-3 mb-4 text-sm rounded-xl bg-warning/10 text-secondary">Ai generat o versiune nouă după depunere. Depune și versiunea nouă.</p>';
    }

    if (g.blocked) {
        html += '<p class="p-3 mb-4 text-sm rounded-xl bg-surface text-muted">' + escapeHtml(g.blocked) + '</p>';
    }

    // Documents of the group, each with its own action. Impozit + PV are always generated together.
    const generateBtn = (label, primary) => '<button type="button" onclick="generateGroup(\'' + key + '\', ' + (g.generated ? 'true' : 'false') + ', this)" class="btn btn-sm ' +
        (primary ? 'bg-primary text-white hover:bg-primary-dark' : 'bg-surface text-secondary hover:bg-gray-200') + '">' + label + '</button>';
    html += '<ul class="mb-4 space-y-2">' + g.types.map(t => {
        const doc = g.documents[t.type];
        const buttons = [];
        if (doc) {
            buttons.push('<button type="button" onclick="downloadDoc(\'' + doc.ref + '\', this)" class="gap-1.5 btn btn-sm bg-primary/10 text-primary hover:bg-primary/20">' + ICON_DOWNLOAD + 'Descarcă</button>');
            if (!g.blocked) buttons.push(generateBtn('Regenerează', g.stale));
        } else if (!g.blocked) {
            buttons.push(generateBtn('Generează', true));
        }
        return '<li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border rounded-xl ' + (g.stale && doc ? 'border-error/30' : 'border-border') + '">' +
            '<div class="min-w-0"><p class="text-sm font-semibold text-secondary">' + escapeHtml(t.label) + '</p>' +
            '<p class="text-xs text-muted">' + (doc ? 'Generat ' + escapeHtml(doc.generated_at) + (doc.by ? ' · ' + escapeHtml(doc.by) : '') : 'Negenerat') + '</p></div>' +
            (buttons.length ? '<div class="flex flex-wrap gap-2">' + buttons.join('') + '</div>' : '') +
            '</li>';
    }).join('') + '</ul>';
    if (!g.blocked && g.types.length > 1) {
        html += '<p class="mb-4 text-xs text-muted">Cele două documente se generează împreună.</p>';
    }

    if (g.can_confirm) {
        html += '<button type="button" onclick="confirmFiled(\'' + key + '\', this)" class="btn btn-sm bg-success/10 text-success hover:bg-success/20">' +
            (g.types.length > 1 ? 'Le-am depus' : 'Am depus-o') + '</button>';
    }

    if (g.filed && g.filed_at) {
        html += '<p class="mt-3 text-xs text-muted">Depunere confirmată pe ' + escapeHtml(g.filed_at) + (g.filed_by ? ' de ' + escapeHtml(g.filed_by) : '') + '.</p>';
    } else if (g.can_confirm) {
        html += '<p class="mt-3 text-xs text-muted">După ce depui ' + (g.types.length > 1 ? 'documentele' : 'cererea') + ' la primărie, apasă „' + (g.types.length > 1 ? 'Le-am depus' : 'Am depus-o') + '” ca să nu mai primești remindere.</p>';
    }

    box.innerHTML = html;
}

function renderHistory(history) {
    const tbody = document.getElementById('event-documents-list');
    if (!history.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-10 text-sm text-center text-muted">Încă nu ai generat documente pentru acest eveniment.</td></tr>';
        return;
    }
    tbody.innerHTML = history.map(d =>
        '<tr class="hover:bg-surface/50">' +
        '<td class="px-6 py-3 text-sm font-medium text-secondary">' + escapeHtml(d.label) + '</td>' +
        '<td class="px-6 py-3 text-sm text-muted">' + escapeHtml(d.generated_at) + (d.by ? '<span class="block text-xs">' + escapeHtml(d.by) + '</span>' : '') + '</td>' +
        '<td class="px-6 py-3">' + (d.current ? chip('Curentă', 'success') : chip('Înlocuită', 'muted')) + '</td>' +
        '<td class="px-6 py-3 text-right"><button type="button" onclick="downloadDoc(\'' + d.ref + '\', this)" class="p-2 rounded-lg text-primary hover:bg-primary/10" title="Descarcă" aria-label="Descarcă ' + escapeHtml(d.label) + '">' + ICON_DOWNLOAD + '</button></td>' +
        '</tr>'
    ).join('');
}

function setBusy(button, text) {
    if (!button) return;
    button.dataset.label = button.innerHTML;
    button.disabled = true;
    button.classList.add('opacity-60');
    button.textContent = text;
}

function clearBusy(button) {
    if (!button || !button.dataset.label) return;
    button.innerHTML = button.dataset.label;
    button.disabled = false;
    button.classList.remove('opacity-60');
}

async function generateGroup(group, regenerate, button) {
    if (busy || !current) return;
    if (regenerate) {
        const what = group === 'cerere' ? 'cererea de vizare' : 'declarația de impozit și PV-ul de distrugere';
        if (!confirm('Generez o versiune nouă pentru ' + what + '? Versiunea veche rămâne în istoric, dar la primărie trebuie depusă cea nouă.')) return;
    }
    busy = true;
    setBusy(button, 'Se generează...');
    try {
        const response = await AmbiletAPI.post('/organizer/documents/fiscal/' + current.event.id + '/generate', { group: group });
        AmbiletNotifications.success(response.data.message || 'Gata.');
        render(response.data);
        refreshEvents();
    } catch (error) {
        AmbiletNotifications.error(error.message || 'Generarea a eșuat. Încearcă din nou.');
        clearBusy(button);
    } finally {
        busy = false;
    }
}

async function confirmFiled(group, button) {
    if (busy || !current) return;
    const what = group === 'cerere' ? 'cererea de vizare' : 'declarația de impozit și PV-ul de distrugere';
    if (!confirm('Confirmi că ai depus ' + what + ' la primărie?')) return;
    busy = true;
    setBusy(button, 'Se salvează...');
    try {
        const response = await AmbiletAPI.post('/organizer/documents/fiscal/' + current.event.id + '/filed', { group: group });
        AmbiletNotifications.success(response.data.message || 'Depunere confirmată.');
        render(response.data);
        refreshEvents();
    } catch (error) {
        AmbiletNotifications.error(error.message || 'Nu am putut salva confirmarea.');
        clearBusy(button);
    } finally {
        busy = false;
    }
}

async function downloadDoc(ref, button) {
    const token = (typeof AmbiletAuth !== 'undefined' && AmbiletAuth.getToken) ? AmbiletAuth.getToken() : null;
    if (!token) {
        AmbiletNotifications.error('Sesiune expirată. Autentifică-te din nou.');
        return;
    }
    setBusy(button, '...');
    try {
        const response = await fetch('/api/proxy.php?action=organizer.documents.file&ref=' + encodeURIComponent(ref), {
            headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/pdf' }
        });
        if (!response.ok) {
            const err = await response.json().catch(() => ({}));
            throw new Error(err.message || 'Documentul nu este disponibil.');
        }
        let filename = 'document.pdf';
        const disposition = response.headers.get('Content-Disposition');
        const match = disposition && disposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
        if (match && match[1]) filename = match[1].replace(/['"]/g, '');
        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (error) {
        AmbiletNotifications.error(error.message || 'Descărcarea a eșuat.');
    } finally {
        clearBusy(button);
    }
}

async function refreshEvents() {
    try {
        const response = await AmbiletAPI.get('/organizer/documents/events');
        const events = (response.success && response.data.events) || [];
        if (events.length) setEvents(events);
    } catch (e) {}
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
