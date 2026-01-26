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
                    <p class="text-sm text-muted">Toate documentele fiscale emise de platforma pentru evenimentele tale</p>
                </div>
                <div class="flex items-center gap-3">
                    <select id="event-filter" class="input w-auto">
                        <option value="">Toate evenimentele</option>
                    </select>
                    <select id="type-filter" class="input w-auto">
                        <option value="">Toate tipurile</option>
                        <option value="invoice">Factura</option>
                        <option value="receipt">Chitanta</option>
                        <option value="fiscal_receipt">Bon fiscal</option>
                        <option value="credit_note">Nota de credit</option>
                    </select>
                </div>
            </div>

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
                        <div class="w-10 h-10 bg-success/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-sm text-muted">Facturi</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="total-invoices">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                        <span class="text-sm text-muted">Chitante</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="total-receipts">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-warning/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-sm text-muted">Luna curenta</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="docs-this-month">0</p>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-border overflow-hidden">
                <div class="p-6 border-b border-border flex items-center justify-between">
                    <h2 class="text-lg font-bold text-secondary">Toate Documentele</h2>
                    <input type="text" id="search-docs" placeholder="Cauta dupa numar sau eveniment..." class="input w-64">
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-surface">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Document</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Tip</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Eveniment</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Suma</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Data</th>
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

document.addEventListener('DOMContentLoaded', function() { loadDocuments(); loadEvents(); });

async function loadEvents() {
    try {
        const res = await AmbiletAPI.get('/organizer/events');
        if (res.success && res.data.events) {
            const sel = document.getElementById('event-filter');
            res.data.events.forEach(e => { const opt = document.createElement('option'); opt.value = e.id; opt.textContent = e.title; sel.appendChild(opt); });
        }
    } catch (e) { /* Events will load when API is available */ }
}

async function loadDocuments() {
    try {
        const params = new URLSearchParams();
        const eventId = document.getElementById('event-filter').value;
        const type = document.getElementById('type-filter').value;
        if (eventId) params.append('event_id', eventId);
        if (type) params.append('type', type);

        const response = await AmbiletAPI.get('/organizer/documents?' + params.toString());
        if (response.success) {
            allDocuments = response.data.documents || [];
            renderDocuments(allDocuments);
            updateStats(response.data.stats || {});
        } else { renderDocuments([]); }
    } catch (error) { renderDocuments([]); }
}

function updateStats(stats) {
    document.getElementById('total-docs').textContent = stats.total || 0;
    document.getElementById('total-invoices').textContent = stats.invoices || 0;
    document.getElementById('total-receipts').textContent = stats.receipts || 0;
    document.getElementById('docs-this-month').textContent = stats.this_month || 0;
}

function renderDocuments(documents) {
    const container = document.getElementById('documents-list');
    if (!documents.length) {
        container.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-muted">Nu exista documente momentan</td></tr>';
        return;
    }
    const typeLabels = { invoice: 'Factura', receipt: 'Chitanta', fiscal_receipt: 'Bon fiscal', credit_note: 'Nota credit' };
    const typeColors = { invoice: 'primary', receipt: 'success', fiscal_receipt: 'blue-600', credit_note: 'warning' };

    container.innerHTML = documents.map(doc => `
        <tr class="hover:bg-surface/50">
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-surface rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <p class="font-medium text-secondary">${doc.number || doc.id}</p>
                        <p class="text-xs text-muted">${doc.series || ''}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4"><span class="px-2.5 py-1 bg-${typeColors[doc.type] || 'primary'}/10 text-${typeColors[doc.type] || 'primary'} text-xs font-medium rounded-lg">${typeLabels[doc.type] || doc.type}</span></td>
            <td class="px-6 py-4 text-sm text-secondary">${doc.event_name || '-'}</td>
            <td class="px-6 py-4 font-semibold text-secondary">${AmbiletUtils.formatCurrency(doc.amount || 0)}</td>
            <td class="px-6 py-4 text-sm text-muted">${AmbiletUtils.formatDate(doc.date)}</td>
            <td class="px-6 py-4 text-right">
                <button onclick="downloadDocument('${doc.id}')" class="p-2 text-primary hover:text-primary-dark hover:bg-primary/10 rounded-lg" title="Descarca">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                </button>
                <button onclick="viewDocument('${doc.id}')" class="p-2 text-muted hover:text-secondary hover:bg-surface rounded-lg" title="Vizualizeaza">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </button>
            </td>
        </tr>
    `).join('');
}

async function downloadDocument(docId) {
    try {
        const response = await AmbiletAPI.get('/organizer/documents/' + docId + '/download');
        if (response.success && response.data.url) { window.open(response.data.url, '_blank'); }
        else { AmbiletNotifications.error('Documentul nu este disponibil'); }
    } catch (error) { AmbiletNotifications.error('Eroare la descarcare'); }
}

async function viewDocument(docId) {
    try {
        const response = await AmbiletAPI.get('/organizer/documents/' + docId + '/view');
        if (response.success && response.data.url) { window.open(response.data.url, '_blank'); }
        else { AmbiletNotifications.error('Documentul nu este disponibil'); }
    } catch (error) { AmbiletNotifications.error('Eroare la vizualizare'); }
}

document.getElementById('event-filter').addEventListener('change', loadDocuments);
document.getElementById('type-filter').addEventListener('change', loadDocuments);
document.getElementById('search-docs').addEventListener('input', AmbiletUtils.debounce(function() {
    const q = this.value.toLowerCase();
    if (!q) { renderDocuments(allDocuments); return; }
    const filtered = allDocuments.filter(d => (d.number || '').toLowerCase().includes(q) || (d.event_name || '').toLowerCase().includes(q));
    renderDocuments(filtered);
}, 300));
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
