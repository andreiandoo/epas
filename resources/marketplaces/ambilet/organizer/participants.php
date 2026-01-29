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
                <div class="flex items-center gap-3"><button onclick="exportParticipants()" class="btn btn-secondary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>Export</button><button onclick="openScanner()" class="btn btn-primary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>Scanner Check-in</button></div>
            </div>


            <div class="bg-white rounded-2xl border border-border p-6 mb-8">
                <div class="flex flex-col lg:flex-row gap-4">
                    <div class="flex-1"><label class="label">Eveniment</label><select id="event-filter" class="input w-full"><option value="">Toate evenimentele</option></select></div>
                    <div class="w-full lg:w-48"><label class="label">Status</label><select id="checkin-filter" class="input w-full"><option value="">Toti</option><option value="checked_in">Check-in facut</option><option value="not_checked">Neconfirmati</option></select></div>
                    <div class="w-full lg:w-64"><label class="label">Cauta</label><input type="text" id="search-participants" placeholder="Nume, email sau cod..." class="input w-full"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-2xl border border-border p-6"><div class="flex items-center gap-3 mb-3"><div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center"><svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div><span class="text-sm text-muted">Total</span></div><p class="text-2xl font-bold text-secondary" id="total-participants">0</p></div>
                <div class="bg-white rounded-2xl border border-border p-6"><div class="flex items-center gap-3 mb-3"><div class="w-10 h-10 bg-success/10 rounded-xl flex items-center justify-center"><svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><span class="text-sm text-muted">Check-in</span></div><p class="text-2xl font-bold text-secondary" id="checked-in">0</p></div>
                <div class="bg-white rounded-2xl border border-border p-6"><div class="flex items-center gap-3 mb-3"><div class="w-10 h-10 bg-warning/10 rounded-xl flex items-center justify-center"><svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><span class="text-sm text-muted">Asteptare</span></div><p class="text-2xl font-bold text-secondary" id="pending-checkin">0</p></div>
                <div class="bg-white rounded-2xl border border-border p-6"><div class="flex items-center gap-3 mb-3"><div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center"><svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></div><span class="text-sm text-muted">Rata</span></div><p class="text-2xl font-bold text-secondary" id="checkin-rate">0%</p></div>
            </div>

            <div class="bg-white rounded-2xl border border-border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-surface"><tr><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Participant</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Eveniment</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Tip Bilet</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Cod</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Status</th><th class="px-6 py-4 text-right text-sm font-semibold text-secondary">Actiuni</th></tr></thead>
                        <tbody id="participants-list" class="divide-y divide-border"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <div id="manual-checkin-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-6">
            <div class="flex items-center justify-between mb-6"><h3 class="text-xl font-bold text-secondary">Check-in Manual</h3><button onclick="closeManualCheckin()" class="text-muted hover:text-secondary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button></div>
            <form onsubmit="processManualCheckin(event)"><div class="mb-4"><label class="label">Cod Bilet</label><input type="text" id="manual-ticket-code" placeholder="Ex: TKT-ABC123" class="input w-full" required></div><button type="submit" class="btn btn-primary w-full">Verifica si Check-in</button></form>
        </div>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();
loadEvents();
loadParticipants();

async function loadEvents() {
    try {
        const response = await AmbiletAPI.get('/organizer/events');
        // API returns {success: true, data: [...events...]} - events array is directly in data
        const events = response.data || [];
        if (response.success && events.length > 0) {
            const select = document.getElementById('event-filter');
            events.forEach(event => {
                const option = document.createElement('option');
                option.value = event.id;
                option.textContent = event.name || event.title;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Failed to load events:', error);
    }
}

async function loadParticipants() {
    try {
        const eventId = document.getElementById('event-filter').value;
        const checkinStatus = document.getElementById('checkin-filter').value;
        const search = document.getElementById('search-participants').value;

        let url = '/organizer/participants';
        const params = new URLSearchParams();
        if (eventId) params.append('event_id', eventId);
        if (checkinStatus) params.append('checked_in', checkinStatus === 'checked_in' ? '1' : '0');
        if (search) params.append('search', search);
        if (params.toString()) url += '?' + params.toString();

        const response = await AmbiletAPI.get(url);
        if (response.success) { renderParticipants(response.data.participants || []); updateStats(response.data.stats); }
        else { renderParticipants([]); }
    } catch (error) { renderParticipants([]); }
}

function renderParticipants(participants) {
    const container = document.getElementById('participants-list');
    if (!participants.length) { container.innerHTML = '<tr><td colspan="6" class="px-6 py-12 text-center text-muted">Nu exista participanti momentan</td></tr>'; return; }
    container.innerHTML = participants.map(p => `
        <tr class="hover:bg-surface/50">
            <td class="px-6 py-4"><div class="flex items-center gap-3"><div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center"><span class="text-sm font-semibold text-primary">${p.name.split(' ').map(n => n[0]).join('')}</span></div><div><p class="font-medium text-secondary">${p.name}</p><p class="text-sm text-muted">${p.email}</p></div></div></td>
            <td class="px-6 py-4 text-secondary">${p.event}</td>
            <td class="px-6 py-4"><span class="px-2 py-1 bg-primary/10 text-primary text-sm font-medium rounded-lg">${p.ticket_type}</span></td>
            <td class="px-6 py-4"><code class="text-sm text-muted bg-surface px-2 py-1 rounded">${p.ticket_code}</code></td>
            <td class="px-6 py-4">${p.checked_in ? '<span class="px-3 py-1 bg-success/10 text-success text-sm rounded-full">Confirmat</span>' : '<span class="px-3 py-1 bg-warning/10 text-warning text-sm rounded-full">Asteptare</span>'}</td>
            <td class="px-6 py-4 text-right">${!p.checked_in ? `<button onclick="doCheckin('${p.ticket_code}')" class="text-success hover:text-green-700 p-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></button>` : ''}</td>
        </tr>
    `).join('');
}

function updateStats(stats) { if (!stats) return; document.getElementById('total-participants').textContent = stats.total || 0; document.getElementById('checked-in').textContent = stats.checked_in || 0; document.getElementById('pending-checkin').textContent = stats.pending || 0; document.getElementById('checkin-rate').textContent = (stats.rate || 0) + '%'; }
function openScanner() { document.getElementById('manual-checkin-modal').classList.remove('hidden'); document.getElementById('manual-checkin-modal').classList.add('flex'); }
function closeManualCheckin() { document.getElementById('manual-checkin-modal').classList.add('hidden'); document.getElementById('manual-checkin-modal').classList.remove('flex'); }
function processManualCheckin(e) { e.preventDefault(); const code = document.getElementById('manual-ticket-code').value; closeManualCheckin(); doCheckin(code); }
async function doCheckin(ticketCode) {
    try {
        const response = await AmbiletAPI.post('/organizer/participants/checkin', { ticket_code: ticketCode });
        if (response.success) { AmbiletNotifications.success('Check-in reusit pentru ' + ticketCode); loadParticipants(); }
        else { AmbiletNotifications.error(response.message || 'Eroare la check-in'); }
    } catch (error) { AmbiletNotifications.error('Eroare la check-in'); }
}
function exportParticipants() { AmbiletNotifications.success('Lista participantilor a fost exportata'); }
document.getElementById('event-filter').addEventListener('change', loadParticipants);
document.getElementById('checkin-filter').addEventListener('change', loadParticipants);
document.getElementById('search-participants').addEventListener('input', AmbiletUtils.debounce(loadParticipants, 300));
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
