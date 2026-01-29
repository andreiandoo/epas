<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Documente';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'documents';
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

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <span class="text-sm text-muted">Total Documente</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="total-docs">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-sm text-muted">Cereri Avizare</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="total-cerere">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-success/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <span class="text-sm text-muted">Declaratii Impozite</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="total-declaratie">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-warning/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <span class="text-sm text-muted">Evenimente</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="total-events">0</p>
                </div>
            </div>

            <!-- Events List with Document Generation -->
            <div class="bg-white rounded-2xl border border-border overflow-hidden">
                <div class="p-6 border-b border-border">
                    <h2 class="text-lg font-bold text-secondary">Genereaza Documente</h2>
                    <p class="text-sm text-muted mt-1">Selecteaza un eveniment si genereaza documentele necesare</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-surface">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Eveniment</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Data</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Status</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-secondary">Cerere Avizare</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-secondary">Declaratie Impozite</th>
                            </tr>
                        </thead>
                        <tbody id="events-list" class="divide-y divide-border"></tbody>
                    </table>
                </div>
            </div>

            <!-- Documents History -->
            <div class="bg-white rounded-2xl border border-border overflow-hidden mt-8">
                <div class="p-6 border-b border-border flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-secondary">Istoric Documente</h2>
                        <p class="text-sm text-muted mt-1">Toate documentele generate</p>
                    </div>
                    <input type="text" id="search-docs" placeholder="Cauta..." class="input w-64">
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-surface">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Document</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Tip</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Eveniment</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Data Emiterii</th>
                                <th class="px-6 py-4 text-right text-sm font-semibold text-secondary">Actiuni</th>
                            </tr>
                        </thead>
                        <tbody id="documents-list" class="divide-y divide-border"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();
let allDocuments = [];

document.addEventListener('DOMContentLoaded', function() {
    loadEventsWithDocuments();
    loadDocuments();
});

async function loadEventsWithDocuments() {
    try {
        const response = await AmbiletAPI.get('/organizer/documents/events');
        if (response.success && response.data.events) {
            renderEvents(response.data.events);
            document.getElementById('total-events').textContent = response.data.events.length;
        } else {
            renderEvents([]);
        }
    } catch (error) {
        console.error('Error loading events:', error);
        renderEvents([]);
    }
}

async function loadDocuments() {
    try {
        const response = await AmbiletAPI.get('/organizer/documents');
        if (response.success) {
            allDocuments = response.data.documents || [];
            renderDocuments(allDocuments);
            updateStats(response.data.stats || {});
        } else {
            renderDocuments([]);
        }
    } catch (error) {
        console.error('Error loading documents:', error);
        renderDocuments([]);
    }
}

function updateStats(stats) {
    document.getElementById('total-docs').textContent = stats.total || 0;
    document.getElementById('total-cerere').textContent = stats.cerere_avizare || 0;
    document.getElementById('total-declaratie').textContent = stats.declaratie_impozite || 0;
}

function renderEvents(events) {
    const container = document.getElementById('events-list');
    if (!events.length) {
        container.innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-muted">Nu exista evenimente momentan</td></tr>';
        return;
    }

    const statusColors = {
        published: 'success',
        pending_review: 'warning',
        ended: 'muted',
        draft: 'secondary',
        cancelled: 'error'
    };

    container.innerHTML = events.map(event => `
        <tr class="hover:bg-surface/50">
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <p class="font-medium text-secondary">${event.name}</p>
                        <p class="text-xs text-muted">${event.venue_name || ''} ${event.venue_city ? ', ' + event.venue_city : ''}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4 text-sm text-secondary">${event.starts_at ? formatDate(event.starts_at) : '-'}</td>
            <td class="px-6 py-4">
                <span class="px-2.5 py-1 bg-${statusColors[event.status] || 'secondary'}/10 text-${statusColors[event.status] || 'secondary'} text-xs font-medium rounded-lg">${event.status_label}</span>
            </td>
            <td class="px-6 py-4 text-center">
                ${event.cerere_avizare
                    ? `<button onclick="downloadDocument(${event.cerere_avizare.id}, '${event.cerere_avizare.download_url}')" class="btn btn-sm bg-success/10 text-success hover:bg-success/20 gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Descarca Aviz
                       </button>`
                    : `<button onclick="generateDocument(${event.id}, 'cerere_avizare')" class="btn btn-sm bg-primary/10 text-primary hover:bg-primary/20 gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Genereaza Aviz
                       </button>`
                }
            </td>
            <td class="px-6 py-4 text-center">
                ${event.declaratie_impozite
                    ? `<button onclick="downloadDocument(${event.declaratie_impozite.id}, '${event.declaratie_impozite.download_url}')" class="btn btn-sm bg-success/10 text-success hover:bg-success/20 gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Descarca Impozite
                       </button>`
                    : `<button onclick="generateDocument(${event.id}, 'declaratie_impozite')" class="btn btn-sm bg-primary/10 text-primary hover:bg-primary/20 gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Genereaza Impozite
                       </button>`
                }
            </td>
        </tr>
    `).join('');
}

function renderDocuments(documents) {
    const container = document.getElementById('documents-list');
    if (!documents.length) {
        container.innerHTML = '<tr><td colspan="5" class="px-6 py-12 text-center text-muted">Nu exista documente generate</td></tr>';
        return;
    }

    const typeLabels = {
        cerere_avizare: 'Cerere Avizare',
        declaratie_impozite: 'Declaratie Impozite'
    };
    const typeColors = {
        cerere_avizare: 'blue-600',
        declaratie_impozite: 'success'
    };

    container.innerHTML = documents.map(doc => `
        <tr class="hover:bg-surface/50">
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-surface rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <p class="font-medium text-secondary">${doc.title}</p>
                        <p class="text-xs text-muted">${doc.file_size || ''}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4"><span class="px-2.5 py-1 bg-${typeColors[doc.type] || 'primary'}/10 text-${typeColors[doc.type] || 'primary'} text-xs font-medium rounded-lg">${typeLabels[doc.type] || doc.type_label}</span></td>
            <td class="px-6 py-4 text-sm text-secondary">${doc.event_name || '-'}</td>
            <td class="px-6 py-4 text-sm text-muted">${doc.issued_at ? formatDate(doc.issued_at) : '-'}</td>
            <td class="px-6 py-4 text-right">
                <button onclick="downloadDocument(${doc.id}, '${doc.download_url}')" class="p-2 text-primary hover:text-primary-dark hover:bg-primary/10 rounded-lg" title="Descarca">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </button>
            </td>
        </tr>
    `).join('');
}

async function generateDocument(eventId, documentType) {
    const typeLabels = {
        cerere_avizare: 'Cerere Avizare',
        declaratie_impozite: 'Declaratie Impozite'
    };

    try {
        AmbiletNotifications.info('Se genereaza documentul...');

        const response = await AmbiletAPI.post('/organizer/documents/generate', {
            event_id: eventId,
            document_type: documentType
        });

        if (response.success) {
            AmbiletNotifications.success(response.data.message || 'Document generat cu succes!');

            // Reload data
            loadEventsWithDocuments();
            loadDocuments();

            // Auto-download the generated document
            if (response.data.document?.download_url) {
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

function downloadDocument(docId, url) {
    if (url) {
        window.open(url, '_blank');
    } else {
        AmbiletNotifications.error('Documentul nu este disponibil');
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

// Search functionality
document.getElementById('search-docs').addEventListener('input', AmbiletUtils.debounce(function() {
    const q = this.value.toLowerCase();
    if (!q) {
        renderDocuments(allDocuments);
        return;
    }
    const filtered = allDocuments.filter(d =>
        (d.title || '').toLowerCase().includes(q) ||
        (d.event_name || '').toLowerCase().includes(q) ||
        (d.type_label || '').toLowerCase().includes(q)
    );
    renderDocuments(filtered);
}, 300));
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
