<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Participanti';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'participants';
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
                    <h1 class="text-2xl font-bold text-secondary">Participanti</h1>
                    <p class="text-sm text-muted">Gestioneaza participantii la evenimentele tale</p>
                </div>
            </div>

            <!-- Event Selector & Stats Card -->
            <div class="bg-white rounded-xl lg:rounded-2xl border border-border p-4 lg:p-6 mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center gap-4 mb-6">
                    <div class="flex-1">
                        <label class="text-sm font-medium text-secondary mb-2 block">Selecteaza evenimentul</label>
                        <select id="event-filter" class="w-full lg:w-80 px-4 py-3 bg-surface border border-border rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <option value="">Se incarca...</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="openScanner()" class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-white text-sm bg-primary hover:bg-primary/90 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                            Scanner QR
                        </button>
                        <button onclick="exportParticipants()" class="flex items-center gap-2 px-4 py-2.5 bg-secondary text-white rounded-xl text-sm font-medium hover:bg-secondary/90 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            Export
                        </button>
                    </div>
                </div>

                <!-- Check-in Stats -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
                    <div class="bg-surface rounded-xl p-4 text-center">
                        <p class="text-2xl lg:text-3xl font-bold text-secondary" id="total-participants">0</p>
                        <p class="text-xs lg:text-sm text-muted">Total participanti</p>
                    </div>
                    <div class="bg-success/10 rounded-xl p-4 text-center">
                        <p class="text-2xl lg:text-3xl font-bold text-success" id="checked-in">0</p>
                        <p class="text-xs lg:text-sm text-success">Check-in facut</p>
                    </div>
                    <div class="bg-warning/10 rounded-xl p-4 text-center">
                        <p class="text-2xl lg:text-3xl font-bold text-warning" id="pending-checkin">0</p>
                        <p class="text-xs lg:text-sm text-warning">In asteptare</p>
                    </div>
                    <div class="bg-primary/10 rounded-xl p-4 text-center">
                        <p class="text-2xl lg:text-3xl font-bold text-primary" id="checkin-rate">0%</p>
                        <p class="text-xs lg:text-sm text-primary">Rata check-in</p>
                    </div>
                </div>
            </div>

            <!-- Filters Row -->
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <select id="checkin-filter" class="px-4 py-2 bg-white border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Toti participantii</option>
                    <option value="checked_in">Check-in facut</option>
                    <option value="not_checked">In asteptare</option>
                </select>
                <input type="text" id="search-participant" placeholder="Cauta participant..." class="px-4 py-2 bg-white border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 w-64">
            </div>

            <!-- No Event Selected Message -->
            <div id="no-event-message" class="bg-white rounded-2xl border border-border p-12 text-center hidden">
                <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <h3 class="text-lg font-semibold text-secondary mb-2">Selecteaza un eveniment</h3>
                <p class="text-muted">Alege un eveniment din lista de mai sus pentru a vedea participantii</p>
            </div>

            <!-- Participants Table -->
            <div id="participants-table-container" class="bg-white rounded-2xl border border-border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-surface">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Participant</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Bilet</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Tip Bilet</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Comanda</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Status</th>
                                <th class="px-6 py-4 text-right text-sm font-semibold text-secondary">Actiuni</th>
                            </tr>
                        </thead>
                        <tbody id="participants-list" class="divide-y divide-border"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div id="manual-checkin-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-6"><h3 class="text-xl font-bold text-secondary">Check-in Manual</h3><button onclick="closeManualCheckin()" class="text-muted hover:text-secondary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <form onsubmit="processManualCheckin(event)"><div class="mb-4"><label class="label">Cod Control</label><input type="text" id="manual-ticket-code" placeholder="Ex: X1SG7TLS" class="input w-full" required><p class="text-xs text-muted mt-1">Introdu codul de control afisat sub codul QR al biletului</p></div><button type="submit" class="btn btn-primary w-full">Verifica si Check-in</button></form>
        </div>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();
let allParticipants = [];
let selectedEventId = null;

// Initialize
loadEvents();

async function loadEvents() {
    try {
        const response = await AmbiletAPI.get('/organizer/events');
        const events = response.data || [];
        const select = document.getElementById('event-filter');
        select.innerHTML = '';

        if (response.success && events.length > 0) {
            // Sort events: live/published first, then by date (most recent first)
            const sortedEvents = events.sort((a, b) => {
                // Prioritize live/published events
                const aIsLive = a.status === 'live' || a.status === 'published' || a.is_published;
                const bIsLive = b.status === 'live' || b.status === 'published' || b.is_published;
                if (aIsLive && !bIsLive) return -1;
                if (!aIsLive && bIsLive) return 1;
                // Then sort by date (most recent first)
                const aDate = new Date(a.event_date || a.created_at || 0);
                const bDate = new Date(b.event_date || b.created_at || 0);
                return bDate - aDate;
            });

            sortedEvents.forEach((event, index) => {
                const option = document.createElement('option');
                option.value = event.id;
                const eventDate = event.event_date ? AmbiletUtils.formatDate(event.event_date) : '';
                option.textContent = (event.name || event.title) + (eventDate ? ' - ' + eventDate : '');
                select.appendChild(option);
            });

            // Pre-select first event (most recent live)
            if (sortedEvents.length > 0) {
                selectedEventId = sortedEvents[0].id;
                select.value = selectedEventId;
                loadParticipants();
            }
        } else {
            select.innerHTML = '<option value="">Nu ai evenimente</option>';
            showNoEventMessage();
        }
    } catch (error) {
        console.error('Failed to load events:', error);
        document.getElementById('event-filter').innerHTML = '<option value="">Eroare la incarcare</option>';
        showNoEventMessage();
    }
}

function showNoEventMessage() {
    document.getElementById('no-event-message').classList.remove('hidden');
    document.getElementById('participants-table-container').classList.add('hidden');
}

function hideNoEventMessage() {
    document.getElementById('no-event-message').classList.add('hidden');
    document.getElementById('participants-table-container').classList.remove('hidden');
}

async function loadParticipants() {
    const eventId = document.getElementById('event-filter').value;
    selectedEventId = eventId;

    if (!eventId) {
        showNoEventMessage();
        updateStats({ total: 0, checked_in: 0, pending: 0, rate: 0 });
        return;
    }

    hideNoEventMessage();

    try {
        const checkinStatus = document.getElementById('checkin-filter').value;
        let url = '/organizer/participants?event_id=' + eventId;
        if (checkinStatus) url += '&checked_in=' + (checkinStatus === 'checked_in' ? '1' : '0');

        const response = await AmbiletAPI.get(url);
        if (response.success) {
            allParticipants = response.data.participants || [];
            filterAndRenderParticipants();
            updateStats(response.data.stats);
        } else {
            allParticipants = [];
            renderParticipants([]);
            updateStats({ total: 0, checked_in: 0, pending: 0, rate: 0 });
        }
    } catch (error) {
        allParticipants = [];
        renderParticipants([]);
        updateStats({ total: 0, checked_in: 0, pending: 0, rate: 0 });
    }
}

function filterAndRenderParticipants() {
    const searchQuery = document.getElementById('search-participant').value.toLowerCase().trim();
    let filtered = allParticipants;

    if (searchQuery) {
        filtered = allParticipants.filter(p =>
            (p.name || '').toLowerCase().includes(searchQuery) ||
            (p.email || '').toLowerCase().includes(searchQuery) ||
            (p.control_code || '').toLowerCase().includes(searchQuery) ||
            (p.ticket_code || '').toLowerCase().includes(searchQuery)
        );
    }

    renderParticipants(filtered);
}

function renderParticipants(participants) {
    const container = document.getElementById('participants-list');
    if (!participants.length) {
        container.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-muted">Nu exista participanti pentru acest eveniment</td></tr>';
        return;
    }

    container.innerHTML = participants.map(p => {
        // Get initials safely
        const initials = (p.name || '').split(' ').map(n => n[0] || '').join('').substring(0, 2).toUpperCase();
        // Spoof email for privacy
        const spoofedEmail = spoofEmail(p.email);
        // Format order date
        const orderDate = p.order_date ? AmbiletUtils.formatDate(p.order_date) : (p.created_at ? AmbiletUtils.formatDate(p.created_at) : '-');

        return `
        <tr class="hover:bg-surface/50">
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                        <span class="text-sm font-semibold text-primary">${initials || '?'}</span>
                    </div>
                    <div>
                        <p class="font-medium text-secondary">${p.name || '-'}</p>
                        <p class="text-sm text-muted">${spoofedEmail}</p>
                    </div>
                </div>
            </td>
            <td class="px-6 py-4">
                <div>
                    <code class="text-sm font-bold text-secondary bg-surface px-2 py-1 rounded">${p.control_code || '-'}</code>
                </div>
                <div class="text-xs text-muted mt-1">#${p.ticket_id || p.id || '-'}</div>
            </td>
            <td class="px-6 py-4">
                <span class="px-2.5 py-1 bg-primary/10 text-primary text-sm font-medium rounded-lg">${p.ticket_type || '-'}</span>
            </td>
            <td class="px-6 py-4">
                <div class="text-sm font-medium text-secondary">${p.order_number || '#' + (p.order_id || '-')}</div>
                <div class="text-xs text-muted">${orderDate}</div>
            </td>
            <td class="px-6 py-4">
                ${p.checked_in
                    ? '<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-success/10 text-success text-sm font-medium rounded-full"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Check-in</span>'
                    : '<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-warning/10 text-warning text-sm font-medium rounded-full"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Asteptare</span>'}
            </td>
            <td class="px-6 py-4 text-right">
                ${!p.checked_in
                    ? `<button onclick="doCheckin('${p.control_code || p.ticket_code}')" class="inline-flex items-center gap-2 px-3 py-1.5 bg-success text-white text-sm font-medium rounded-lg hover:bg-success/90 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Check-in
                    </button>`
                    : `<span class="text-xs text-muted">${p.checked_in_at ? AmbiletUtils.formatDate(p.checked_in_at) : ''}</span>`}
            </td>
        </tr>`;
    }).join('');
}

function spoofEmail(email) {
    if (!email) return '-';
    const parts = email.split('@');
    if (parts.length !== 2) return email;
    const name = parts[0];
    const domain = parts[1];
    if (name.length <= 3) return name[0] + '***@' + domain;
    return name.substring(0, 2) + '***' + name.slice(-1) + '@' + domain;
}

function updateStats(stats) {
    if (!stats) return;
    document.getElementById('total-participants').textContent = stats.total || 0;
    document.getElementById('checked-in').textContent = stats.checked_in || 0;
    document.getElementById('pending-checkin').textContent = stats.pending || (stats.total - stats.checked_in) || 0;
    document.getElementById('checkin-rate').textContent = (stats.rate || 0) + '%';
}

function openScanner() {
    document.getElementById('manual-checkin-modal').classList.remove('hidden');
    document.getElementById('manual-checkin-modal').classList.add('flex');
    document.getElementById('manual-ticket-code').focus();
}

function closeManualCheckin() {
    document.getElementById('manual-checkin-modal').classList.add('hidden');
    document.getElementById('manual-checkin-modal').classList.remove('flex');
}

function processManualCheckin(e) {
    e.preventDefault();
    const code = document.getElementById('manual-ticket-code').value.trim();
    if (!code) return;
    closeManualCheckin();
    doCheckin(code);
}

async function doCheckin(ticketCode) {
    try {
        const response = await AmbiletAPI.post('/organizer/participants/checkin', {
            ticket_code: ticketCode,
            event_id: selectedEventId
        });
        if (response.success) {
            AmbiletNotifications.success('Check-in reusit pentru ' + ticketCode);
            loadParticipants();
        } else {
            AmbiletNotifications.error(response.message || 'Eroare la check-in');
        }
    } catch (error) {
        AmbiletNotifications.error('Eroare la check-in');
    }
}

async function exportParticipants() {
    if (!selectedEventId) {
        AmbiletNotifications.error('Selecteaza un eveniment');
        return;
    }
    try {
        AmbiletNotifications.info('Se genereaza lista participantilor...');

        // Get auth token
        const authToken = localStorage.getItem('organizer_token');
        if (!authToken) {
            AmbiletNotifications.error('Sesiune expirata. Te rugam sa te autentifici din nou.');
            return;
        }

        // Fetch CSV with authentication
        const response = await fetch(`/api/marketplace-client/organizer/participants/export?event_id=${selectedEventId}`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${authToken}`,
                'Accept': 'text/csv'
            }
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || 'Eroare la export');
        }

        // Get filename from header or use default
        const contentDisposition = response.headers.get('Content-Disposition');
        let filename = `participanti-${selectedEventId}.csv`;
        if (contentDisposition) {
            const match = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
            if (match && match[1]) {
                filename = match[1].replace(/['"]/g, '');
            }
        }

        // Create blob and download
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);

        AmbiletNotifications.success('Lista participantilor a fost exportata');
    } catch (error) {
        console.error('Export error:', error);
        AmbiletNotifications.error(error.message || 'Eroare la export');
    }
}

// Event listeners
document.getElementById('event-filter').addEventListener('change', loadParticipants);
document.getElementById('checkin-filter').addEventListener('change', loadParticipants);
document.getElementById('search-participant').addEventListener('input', AmbiletUtils.debounce(filterAndRenderParticipants, 300));
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
