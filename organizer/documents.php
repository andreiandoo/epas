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

            <!-- Event Selector -->
            <div class="p-6 mb-6 bg-white border rounded-2xl border-border">
                <label for="event-search-input" class="block mb-2 label">Selectează evenimentul</label>
                <div class="relative w-full max-w-lg" id="event-dropdown-wrapper">
                    <input type="text" id="event-search-input" class="w-full pr-10 input" placeholder="Caută eveniment..." autocomplete="off"
                           onfocus="openEventDropdown()" oninput="filterEventDropdown()">
                    <input type="hidden" id="event-selector" value="">
                    <svg class="absolute w-5 h-5 -translate-y-1/2 pointer-events-none text-muted right-3 top-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    <div id="event-dropdown-list" class="absolute z-50 hidden w-full mt-1 overflow-y-auto bg-white border shadow-lg max-h-64 border-border rounded-xl"></div>
                </div>
                <div id="events-loading" class="mt-2 text-sm text-muted">Se încarcă evenimentele...</div>
                <div id="attention-list" class="hidden mt-4"></div>
            </div>

            <!-- Selected event -->
            <div id="event-detail-section" class="hidden">
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

                    <div class="grid gap-6 mb-6 lg:grid-cols-2">
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
            </div>
        </main>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function() { AmbiletAuth.requireOrganizerAuth(); });

const ISSUE_LABELS = {
    cerere_missing: 'Cererea de vizare nu e generată',
    cerere_stale: 'Cererea de vizare trebuie regenerată',
    impozit_pv_missing: 'Impozitul și PV-ul de distrugere sunt de generat'
};
const ICON_DOWNLOAD = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>';
const ICON_ALERT = '<svg class="flex-shrink-0 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>';

let eventsData = {};
let eventsList = [];
let selectedEventId = null;
let current = null;
let busy = false;

document.addEventListener('DOMContentLoaded', function() {
    loadEvents();
    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('event-dropdown-wrapper');
        if (wrapper && !wrapper.contains(e.target)) closeEventDropdown();
    });
});

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

function formatDateShort(dateStr) {
    if (!dateStr) return '';
    const date = new Date(String(dateStr).replace(' ', 'T'));
    return isNaN(date) ? '' : date.toLocaleDateString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function isEventLive(ev) {
    return ev.status === 'published';
}

async function loadEvents() {
    const loading = document.getElementById('events-loading');
    try {
        const response = await AmbiletAPI.get('/organizer/documents/events');
        const events = (response.success && response.data.events) || [];
        if (!events.length) {
            loading.textContent = 'Nu ai încă evenimente.';
            return;
        }
        events.sort((a, b) => {
            const aLive = isEventLive(a), bLive = isEventLive(b);
            if (aLive !== bLive) return aLive ? -1 : 1;
            const aDate = new Date(a.starts_at || 0), bDate = new Date(b.starts_at || 0);
            return aLive ? aDate - bDate : bDate - aDate;
        });
        eventsData = {};
        eventsList = [];
        events.forEach(e => {
            eventsData[e.id] = e;
            const meta = [formatDateShort(e.starts_at), e.venue_name || ''].filter(Boolean).join(' · ');
            eventsList.push({
                id: e.id,
                label: (e.attention ? '⚠ ' : '') + (e.name || 'Eveniment') + (meta ? ' — ' + meta : ''),
                status: e.status_label || ''
            });
        });
        loading.classList.add('hidden');
        renderAttention(events.filter(e => e.attention));

        const preselect = new URLSearchParams(window.location.search).get('event');
        if (preselect && eventsData[preselect]) selectEvent(preselect);
    } catch (error) {
        console.error('Error loading events:', error);
        loading.textContent = 'Nu am putut încărca evenimentele. Reîncarcă pagina.';
    }
}

function renderAttention(items) {
    const box = document.getElementById('attention-list');
    if (!items.length) { box.classList.add('hidden'); return; }
    box.innerHTML = '<p class="mb-2 text-sm font-semibold text-secondary">Necesită atenția ta</p>' +
        '<ul class="space-y-2">' + items.map(e =>
            '<li><button type="button" onclick="selectEvent(\'' + e.id + '\')" class="flex items-center w-full gap-3 px-4 py-3 text-left transition-colors border rounded-xl border-warning/40 bg-warning/5 hover:bg-warning/10">' +
            '<span class="text-warning">' + ICON_ALERT + '</span>' +
            '<span class="flex-1 min-w-0"><span class="block text-sm font-semibold truncate text-secondary">' + escapeHtml(e.name) + '</span>' +
            '<span class="block text-xs text-muted">' + escapeHtml(ISSUE_LABELS[e.attention] || '') + '</span></span>' +
            '<span class="text-sm font-semibold text-primary">Deschide</span></button></li>'
        ).join('') + '</ul>';
    box.classList.remove('hidden');
}

function openEventDropdown() {
    renderEventDropdown(eventsList);
    document.getElementById('event-dropdown-list').classList.remove('hidden');
}

function closeEventDropdown() {
    document.getElementById('event-dropdown-list').classList.add('hidden');
}

function filterEventDropdown() {
    const query = document.getElementById('event-search-input').value.toLowerCase().trim();
    renderEventDropdown(query ? eventsList.filter(e => e.label.toLowerCase().includes(query)) : eventsList);
    document.getElementById('event-dropdown-list').classList.remove('hidden');
}

function renderEventDropdown(items) {
    const list = document.getElementById('event-dropdown-list');
    if (!items.length) {
        list.innerHTML = '<div class="px-4 py-3 text-sm text-muted">Niciun rezultat</div>';
        return;
    }
    list.innerHTML = items.map(item =>
        '<div class="flex items-center justify-between gap-3 px-4 py-3 text-sm transition-colors cursor-pointer hover:bg-primary/5 ' +
        (String(item.id) === String(selectedEventId) ? 'bg-primary/10 font-semibold text-primary' : 'text-secondary') +
        '" onclick="selectEvent(\'' + item.id + '\')"><span class="truncate">' + escapeHtml(item.label) + '</span>' +
        '<span class="flex-shrink-0 text-xs text-muted">' + escapeHtml(item.status) + '</span></div>'
    ).join('');
}

function selectEvent(id) {
    document.getElementById('event-selector').value = id;
    const item = eventsList.find(e => String(e.id) === String(id));
    if (item) document.getElementById('event-search-input').value = item.label;
    closeEventDropdown();
    const url = new URL(window.location.href);
    url.searchParams.set('event', id);
    history.replaceState(null, '', url);
    loadEventStatus(parseInt(id, 10));
}

async function loadEventStatus(id) {
    selectedEventId = id;
    const section = document.getElementById('event-detail-section');
    const loading = document.getElementById('event-detail-loading');
    const body = document.getElementById('event-detail-body');
    section.classList.remove('hidden');
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

    // Documents of the group
    html += '<ul class="mb-4 space-y-2">' + g.types.map(t => {
        const doc = g.documents[t.type];
        return '<li class="flex items-center justify-between gap-3 px-4 py-3 border rounded-xl border-border">' +
            '<div class="min-w-0"><p class="text-sm font-semibold text-secondary">' + escapeHtml(t.label) + '</p>' +
            '<p class="text-xs text-muted">' + (doc ? 'Generat ' + escapeHtml(doc.generated_at) + (doc.by ? ' · ' + escapeHtml(doc.by) : '') : 'Negenerat') + '</p></div>' +
            (doc ? '<button type="button" onclick="downloadDoc(\'' + doc.ref + '\', this)" class="gap-1.5 btn btn-sm bg-primary/10 text-primary hover:bg-primary/20">' + ICON_DOWNLOAD + 'Descarcă</button>' : '') +
            '</li>';
    }).join('') + '</ul>';

    // Actions
    const actions = [];
    if (!g.blocked) {
        const label = g.generated
            ? (isCerere ? 'Regenerează cererea' : 'Regenerează ambele')
            : (isCerere ? 'Generează cererea' : (g.types.length > 1 ? 'Generează ambele' : 'Generează PV-ul'));
        const primary = !g.generated || g.stale;
        actions.push('<button type="button" onclick="generateGroup(\'' + key + '\', ' + (g.generated ? 'true' : 'false') + ', this)" class="btn btn-sm ' +
            (primary ? 'bg-primary text-white hover:bg-primary-dark' : 'bg-surface text-secondary hover:bg-gray-200') + '">' + label + '</button>');
    }
    if (g.can_confirm) {
        actions.push('<button type="button" onclick="confirmFiled(\'' + key + '\', this)" class="btn btn-sm bg-success/10 text-success hover:bg-success/20">' +
            (g.types.length > 1 ? 'Le-am depus' : 'Am depus-o') + '</button>');
    }
    if (actions.length) html += '<div class="flex flex-wrap gap-2">' + actions.join('') + '</div>';

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
        refreshAttention();
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
        refreshAttention();
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

async function refreshAttention() {
    try {
        const response = await AmbiletAPI.get('/organizer/documents/events');
        const events = (response.success && response.data.events) || [];
        events.forEach(e => { eventsData[e.id] = e; });
        renderAttention(events.filter(e => e.attention));
    } catch (e) {}
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
