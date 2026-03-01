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
                <!-- Page Content -->
        <main class="flex-1 p-4 lg:p-8">
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Documente</h1>
                    <p class="text-sm text-muted">Genereaza si descarca documentele fiscale pentru evenimentele tale</p>
                </div>
            </div>

            <!-- Event Selector -->
            <div class="bg-white rounded-2xl border border-border p-6 mb-6">
                <label class="label mb-2 block">Selecteaza evenimentul</label>
                <div class="relative w-full max-w-lg" id="event-dropdown-wrapper">
                    <input type="text" id="event-search-input" class="input w-full pr-10" placeholder="Cauta eveniment..." autocomplete="off"
                           onfocus="openEventDropdown()" oninput="filterEventDropdown()">
                    <input type="hidden" id="event-selector" value="">
                    <svg class="w-5 h-5 text-muted absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    <div id="event-dropdown-list" class="hidden absolute z-50 mt-1 w-full max-h-64 overflow-y-auto bg-white border border-border rounded-xl shadow-lg"></div>
                </div>
                <div id="events-loading" class="text-sm text-muted mt-2">Se incarca evenimentele...</div>
            </div>

            <!-- Event Detail + Document Generation (hidden until event selected) -->
            <div id="event-detail-section" class="hidden mb-6">
                <div class="bg-white rounded-2xl border border-border overflow-hidden">
                    <!-- Event Info Header -->
                    <div class="p-6 border-b border-border">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h2 id="event-name" class="text-lg font-bold text-secondary"></h2>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted mt-1">
                                    <span id="event-venue" class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        <span id="event-venue-text"></span>
                                    </span>
                                    <span id="event-date" class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <span id="event-date-text"></span>
                                    </span>
                                    <span id="event-status-badge"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Document Generation Buttons -->
                    <div class="p-6">
                        <h3 class="text-sm font-semibold text-muted uppercase tracking-wide mb-4">Documente disponibile</h3>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <!-- Cerere Avizare -->
                            <div class="border border-border rounded-xl p-5">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-secondary">Cerere Avizare</h4>
                                        <p class="text-xs text-muted">Document necesar pentru avizarea evenimentului</p>
                                    </div>
                                </div>
                                <div id="aviz-actions" class="flex items-center gap-2">
                                    <!-- Populated by JS -->
                                </div>
                            </div>

                            <!-- Declaratie Impozite -->
                            <div class="border border-border rounded-xl p-5">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div>
                                        <h4 class="font-semibold text-secondary">Declaratie Impozite</h4>
                                        <p class="text-xs text-muted">Disponibil dupa terminarea evenimentului</p>
                                    </div>
                                </div>
                                <div id="impozite-actions" class="flex items-center gap-2">
                                    <!-- Populated by JS -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents History for selected event -->
            <div id="event-docs-history" class="hidden">
                <div class="bg-white rounded-2xl border border-border overflow-hidden">
                    <div class="p-6 border-b border-border">
                        <h2 class="text-lg font-bold text-secondary">Istoric documente</h2>
                        <p class="text-sm text-muted mt-1" id="history-subtitle">Documentele generate pentru acest eveniment</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-surface">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Document</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Tip</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Data generarii</th>
                                    <th class="px-6 py-4 text-right text-sm font-semibold text-secondary">Actiuni</th>
                                </tr>
                            </thead>
                            <tbody id="event-documents-list" class="divide-y divide-border"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();
let eventsData = {};
let eventsList = [];
let selectedEventId = null;
let dropdownOpen = false;

document.addEventListener('DOMContentLoaded', function() {
    loadEvents();

    // Close dropdown on outside click
    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('event-dropdown-wrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            closeEventDropdown();
        }
    });
});

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ro-RO', { day: '2-digit', month: 'long', year: 'numeric' });
}

function formatDateShort(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

async function loadEvents() {
    try {
        const response = await AmbiletAPI.get('/organizer/documents/events');
        if (response.success && response.data.events) {
            const events = response.data.events;
            events.forEach(e => {
                eventsData[e.id] = e;
                const dateStr = e.starts_at ? ' — ' + formatDateShort(e.starts_at) : '';
                eventsList.push({ id: e.id, label: (e.name || 'Eveniment') + dateStr });
            });

            document.getElementById('events-loading').classList.add('hidden');

            // Pre-select from URL param
            const params = new URLSearchParams(window.location.search);
            const preselect = params.get('event');
            if (preselect && eventsData[preselect]) {
                selectEvent(preselect);
            }
        } else {
            document.getElementById('events-loading').textContent = 'Nu ai evenimente.';
        }
    } catch (error) {
        console.error('Error loading events:', error);
        document.getElementById('events-loading').textContent = 'Eroare la incarcarea evenimentelor.';
    }
}

function openEventDropdown() {
    dropdownOpen = true;
    renderEventDropdown(eventsList);
    document.getElementById('event-dropdown-list').classList.remove('hidden');
}

function closeEventDropdown() {
    dropdownOpen = false;
    document.getElementById('event-dropdown-list').classList.add('hidden');
}

function filterEventDropdown() {
    const query = document.getElementById('event-search-input').value.toLowerCase().trim();
    if (!query) {
        renderEventDropdown(eventsList);
    } else {
        const filtered = eventsList.filter(e => e.label.toLowerCase().includes(query));
        renderEventDropdown(filtered);
    }
    document.getElementById('event-dropdown-list').classList.remove('hidden');
}

function renderEventDropdown(items) {
    const list = document.getElementById('event-dropdown-list');
    if (!items.length) {
        list.innerHTML = '<div class="px-4 py-3 text-sm text-muted">Niciun rezultat</div>';
        return;
    }
    list.innerHTML = items.map(item =>
        '<div class="px-4 py-3 text-sm cursor-pointer hover:bg-primary/5 transition-colors ' +
        (String(item.id) === String(selectedEventId) ? 'bg-primary/10 font-semibold text-primary' : 'text-secondary') +
        '" onclick="selectEvent(\'' + item.id + '\')">' + escapeHtml(item.label) + '</div>'
    ).join('');
}

function selectEvent(id) {
    document.getElementById('event-selector').value = id;
    const item = eventsList.find(e => String(e.id) === String(id));
    if (item) {
        document.getElementById('event-search-input').value = item.label;
    }
    closeEventDropdown();
    onEventSelected();
}

function onEventSelected() {
    const id = document.getElementById('event-selector').value;
    if (!id || !eventsData[id]) {
        document.getElementById('event-detail-section').classList.add('hidden');
        document.getElementById('event-docs-history').classList.add('hidden');
        selectedEventId = null;
        return;
    }

    selectedEventId = parseInt(id);
    const event = eventsData[id];

    // Populate event info
    document.getElementById('event-name').textContent = event.name || '';
    document.getElementById('event-venue-text').textContent = (event.venue_name || '') + (event.venue_city ? ', ' + event.venue_city : '');
    document.getElementById('event-date-text').textContent = event.starts_at ? formatDate(event.starts_at) : '-';

    const statusColors = {
        published: 'success', pending_review: 'warning', ended: 'muted', draft: 'secondary', cancelled: 'error'
    };
    const sc = statusColors[event.status] || 'secondary';
    document.getElementById('event-status-badge').innerHTML =
        '<span class="px-2.5 py-1 bg-' + sc + '/10 text-' + sc + ' text-xs font-medium rounded-lg">' + escapeHtml(event.status_label || event.status || '') + '</span>';

    // Render aviz actions
    renderAvizActions(event);
    renderImpoziteActions(event);

    document.getElementById('event-detail-section').classList.remove('hidden');

    // Load event-specific document history
    loadEventDocuments(selectedEventId);
}

function renderAvizActions(event) {
    const container = document.getElementById('aviz-actions');
    if (event.cerere_avizare) {
        container.innerHTML = '<button onclick="downloadEventDoc(' + event.id + ', \'cerere_avizare\')" class="btn btn-sm bg-success/10 text-success hover:bg-success/20 gap-2">' +
            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>' +
            'Descarca Aviz</button>' +
            '<button onclick="regenerateDocument(' + event.id + ', \'cerere_avizare\')" class="btn btn-sm bg-amber-50 text-amber-700 hover:bg-amber-100 gap-1.5" title="Regenereaza documentul">' +
            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>' +
            'Regenereaza</button>';
    } else {
        container.innerHTML = '<button onclick="generateDocument(' + event.id + ', \'cerere_avizare\')" class="btn btn-sm bg-primary/10 text-primary hover:bg-primary/20 gap-2">' +
            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>' +
            'Genereaza Aviz</button>';
    }
}

function renderImpoziteActions(event) {
    const container = document.getElementById('impozite-actions');
    if (event.declaratie_impozite) {
        container.innerHTML = '<button onclick="downloadEventDoc(' + event.id + ', \'declaratie_impozite\')" class="btn btn-sm bg-success/10 text-success hover:bg-success/20 gap-2">' +
            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>' +
            'Descarca Impozite</button>' +
            '<button onclick="regenerateDocument(' + event.id + ', \'declaratie_impozite\')" class="btn btn-sm bg-amber-50 text-amber-700 hover:bg-amber-100 gap-1.5" title="Regenereaza documentul">' +
            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>' +
            'Regenereaza</button>';
    } else if (event.status === 'ended') {
        container.innerHTML = '<button onclick="generateDocument(' + event.id + ', \'declaratie_impozite\')" class="btn btn-sm bg-primary/10 text-primary hover:bg-primary/20 gap-2">' +
            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>' +
            'Genereaza Impozite</button>';
    } else {
        container.innerHTML = '<span class="text-xs text-muted italic">Disponibil dupa terminarea evenimentului</span>';
    }
}

function downloadEventDoc(eventId, docType) {
    const event = eventsData[eventId];
    if (event && event[docType] && event[docType].download_url) {
        window.open(event[docType].download_url, '_blank');
    } else {
        AmbiletNotifications.error('Documentul nu este disponibil');
    }
}

async function generateDocument(eventId, documentType) {
    try {
        AmbiletNotifications.info('Se genereaza documentul...');

        const response = await AmbiletAPI.post('/organizer/documents/generate', {
            event_id: eventId,
            document_type: documentType
        });

        if (response.success) {
            AmbiletNotifications.success(response.data.message || 'Document generat cu succes!');

            // Refresh event data and history
            await refreshEventData(eventId);

            // Auto-download
            if (response.data.document && response.data.document.download_url) {
                window.open(response.data.document.download_url, '_blank');
            }
        } else {
            AmbiletNotifications.error(response.message || 'Eroare la generarea documentului');
        }
    } catch (error) {
        console.error('Generate error:', error);
        AmbiletNotifications.error('Eroare la generarea documentului. Verifica daca exista un template configurat.');
    }
}

async function regenerateDocument(eventId, documentType) {
    if (!confirm('Esti sigur ca vrei sa regenerezi acest document? Versiunea anterioara va fi pastrata in istoric.')) return;

    try {
        AmbiletNotifications.info('Se regenereaza documentul...');

        const response = await AmbiletAPI.post('/organizer/documents/generate', {
            event_id: eventId,
            document_type: documentType
        });

        if (response.success) {
            AmbiletNotifications.success('Document regenerat cu succes!');

            // Refresh event data and history
            await refreshEventData(eventId);

            // Auto-download
            if (response.data.document && response.data.document.download_url) {
                window.open(response.data.document.download_url, '_blank');
            }
        } else {
            AmbiletNotifications.error(response.message || 'Eroare la regenerarea documentului');
        }
    } catch (error) {
        console.error('Regenerate error:', error);
        AmbiletNotifications.error('Eroare la regenerarea documentului.');
    }
}

async function refreshEventData(eventId) {
    try {
        const response = await AmbiletAPI.get('/organizer/documents/events');
        if (response.success && response.data.events) {
            response.data.events.forEach(function(e) {
                eventsData[e.id] = e;
            });
            if (selectedEventId == eventId) {
                const event = eventsData[eventId];
                if (event) {
                    renderAvizActions(event);
                    renderImpoziteActions(event);
                }
            }
        }
    } catch(e) { /* ignore */ }

    // Also reload history
    loadEventDocuments(eventId);
}

async function loadEventDocuments(eventId) {
    const container = document.getElementById('event-documents-list');
    const section = document.getElementById('event-docs-history');

    container.innerHTML = '<tr><td colspan="4" class="px-6 py-8 text-center text-muted">Se incarca...</td></tr>';
    section.classList.remove('hidden');

    try {
        const response = await AmbiletAPI.get('/organizer/documents', { event_id: eventId });
        if (response.success) {
            const docs = response.data.documents || [];
            renderEventDocuments(docs);
        } else {
            renderEventDocuments([]);
        }
    } catch (error) {
        console.error('Error loading event documents:', error);
        renderEventDocuments([]);
    }
}

function renderEventDocuments(documents) {
    const container = document.getElementById('event-documents-list');

    if (!documents.length) {
        container.innerHTML = '<tr><td colspan="4" class="px-6 py-12 text-center text-muted">Nu exista documente generate pentru acest eveniment</td></tr>';
        return;
    }

    const typeLabels = {
        cerere_avizare: 'Cerere Avizare',
        declaratie_impozite: 'Declaratie Impozite'
    };
    const typeColors = {
        cerere_avizare: 'blue-600',
        declaratie_impozite: 'green-600'
    };

    container.innerHTML = documents.map(function(doc) {
        const typeLabel = typeLabels[doc.type] || doc.type_label || doc.type || '';
        const typeColor = typeColors[doc.type] || 'primary';
        const dateStr = doc.issued_at ? formatDateShort(doc.issued_at) : (doc.created_at ? formatDateShort(doc.created_at) : '-');

        let actions = '';
        if (doc.download_url) {
            actions += '<button onclick="window.open(\'' + doc.download_url + '\', \'_blank\')" class="p-2 text-primary hover:text-primary-dark hover:bg-primary/10 rounded-lg" title="Descarca">' +
                '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></button>';
        }
        if (doc.view_url) {
            actions += '<button onclick="window.open(\'' + doc.view_url + '\', \'_blank\')" class="p-2 text-muted hover:text-secondary hover:bg-surface rounded-lg" title="Vizualizeaza">' +
                '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></button>';
        }

        return '<tr class="hover:bg-surface/50">' +
            '<td class="px-6 py-4"><div class="flex items-center gap-3">' +
            '<div class="w-10 h-10 bg-surface rounded-xl flex items-center justify-center">' +
            '<svg class="w-5 h-5 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></div>' +
            '<div><p class="font-medium text-secondary">' + escapeHtml(doc.title || '') + '</p>' +
            '<p class="text-xs text-muted">' + escapeHtml(doc.file_size || '') + '</p></div></div></td>' +
            '<td class="px-6 py-4"><span class="px-2.5 py-1 bg-' + typeColor + '/10 text-' + typeColor + ' text-xs font-medium rounded-lg">' + escapeHtml(typeLabel) + '</span></td>' +
            '<td class="px-6 py-4 text-sm text-muted">' + dateStr + '</td>' +
            '<td class="px-6 py-4 text-right"><div class="flex items-center justify-end gap-1">' + actions + '</div></td>' +
            '</tr>';
    }).join('');
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
