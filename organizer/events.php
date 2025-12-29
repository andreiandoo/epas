<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Evenimente';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'events';
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
                    <h1 class="text-2xl font-bold text-secondary">Evenimentele mele</h1>
                    <p class="text-sm text-muted">Gestioneaza si monitorizeaza evenimentele tale</p>
                </div>
                <button onclick="openNewEventModal()" class="btn btn-primary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>Eveniment nou</button>
            </div>


            <div class="flex flex-wrap items-center gap-4 mb-6">
                <div class="flex-1 min-w-[200px]"><input type="text" placeholder="Cauta evenimente..." class="input w-full" id="search-input"></div>
                <select class="input w-auto" id="status-filter"><option value="">Toate statusurile</option><option value="published">Publicate</option><option value="draft">Ciorne</option><option value="ended">Incheiate</option></select>
                <select class="input w-auto" id="sort-filter"><option value="date_desc">Cele mai recente</option><option value="date_asc">Cele mai vechi</option><option value="sales_desc">Cele mai vandute</option></select>
            </div>

            <div id="events-list" class="space-y-4"><div class="animate-pulse bg-white rounded-2xl border border-border p-6"><div class="flex gap-6"><div class="w-32 h-24 bg-surface rounded-lg"></div><div class="flex-1 space-y-3"><div class="h-5 bg-surface rounded w-1/3"></div><div class="h-4 bg-surface rounded w-1/4"></div></div></div></div></div>

            <div id="no-events" class="hidden text-center py-16 bg-white rounded-2xl border border-border">
                <div class="w-24 h-24 bg-muted/10 rounded-full flex items-center justify-center mx-auto mb-6"><svg class="w-12 h-12 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></div>
                <h2 class="text-xl font-bold text-secondary mb-2">Nu ai evenimente inca</h2>
                <p class="text-muted mb-6">Creeaza primul tau eveniment si incepe sa vinzi bilete!</p>
                <button onclick="openNewEventModal()" class="btn btn-primary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>Creeaza eveniment</button>
            </div>
        </main>
    </div>

    <div id="new-event-modal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-black/50" onclick="closeNewEventModal()"></div>
            <div class="relative bg-white rounded-2xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-secondary">Eveniment nou</h2>
                    <button onclick="closeNewEventModal()" class="text-muted hover:text-secondary"><svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
                </div>
                <form id="new-event-form" class="space-y-4">
                    <div><label class="label">Titlu eveniment *</label><input type="text" name="title" required class="input" placeholder="Concert Rock"></div>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div><label class="label">Categorie *</label><select name="category_id" required class="input"><option value="">Selecteaza</option><option value="1">Concert</option><option value="2">Festival</option><option value="3">Teatru</option><option value="4">Stand-up</option><option value="5">Sport</option><option value="6">Altele</option></select></div>
                        <div><label class="label">Oras *</label><input type="text" name="city" required class="input" placeholder="Bucuresti"></div>
                    </div>
                    <div><label class="label">Locatie / Sala *</label><input type="text" name="venue" required class="input" placeholder="Sala Palatului"></div>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div><label class="label">Data *</label><input type="date" name="start_date" required class="input"></div>
                        <div><label class="label">Ora *</label><input type="time" name="start_time" required class="input"></div>
                    </div>
                    <div><label class="label">Descriere scurta</label><textarea name="description" rows="3" class="input" placeholder="Descrierea evenimentului..."></textarea></div>
                    <div class="flex gap-4 mt-6">
                        <button type="button" onclick="closeNewEventModal()" class="btn btn-secondary flex-1">Anuleaza</button>
                        <button type="submit" class="btn btn-primary flex-1"><span id="create-btn-text">Creeaza eveniment</span><div id="create-btn-spinner" class="hidden spinner"></div></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();
loadEvents();

async function loadEvents() {
    try {
        const response = await AmbiletAPI.organizer.getEvents();
        const events = response.data || response || [];
        if (events.length === 0) { document.getElementById('events-list').classList.add('hidden'); document.getElementById('no-events').classList.remove('hidden'); }
        else { renderEvents(events); }
    } catch (error) { document.getElementById('events-list').classList.add('hidden'); document.getElementById('no-events').classList.remove('hidden'); }
}

function renderEvents(events) {
    const container = document.getElementById('events-list');
    const statusColors = { published: 'success', draft: 'warning', ended: 'muted' };
    const statusLabels = { published: 'Publicat', draft: 'Ciorna', ended: 'Incheiat' };
    container.innerHTML = events.map(event => `
        <div class="bg-white rounded-2xl border border-border p-6 hover:border-primary/30 transition-colors">
            <div class="flex flex-col md:flex-row gap-6">
                <img src="${event.image || AMBILET_CONFIG.PLACEHOLDER_EVENT}" alt="${event.title}" class="w-full md:w-40 h-28 rounded-xl object-cover">
                <div class="flex-1">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-secondary mb-1">${event.title}</h3>
                            <div class="flex flex-wrap items-center gap-3 text-sm text-muted">
                                <span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>${AmbiletUtils.formatDate(event.start_date)}</span>
                                <span class="flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>${event.venue?.city || event.city || ''}</span>
                            </div>
                        </div>
                        <span class="badge badge-${statusColors[event.status] || 'secondary'}">${statusLabels[event.status] || event.status}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-4 mt-4 pt-4 border-t border-border">
                        <div><p class="text-2xl font-bold text-secondary">${event.tickets_sold || 0}</p><p class="text-xs text-muted">Bilete vandute</p></div>
                        <div><p class="text-2xl font-bold text-secondary">${AmbiletUtils.formatCurrency(event.revenue || 0)}</p><p class="text-xs text-muted">Vanzari</p></div>
                        <div class="flex items-center justify-end gap-2">
                            <a href="/organizer/events.php?id=${event.id}" class="btn btn-sm btn-secondary"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>Editeaza</a>
                            <a href="/event.php?slug=${event.slug}" target="_blank" class="btn btn-sm btn-secondary"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function openNewEventModal() { document.getElementById('new-event-modal').classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
function closeNewEventModal() { document.getElementById('new-event-modal').classList.add('hidden'); document.body.style.overflow = ''; }

document.getElementById('new-event-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target; const formData = new FormData(form);
    const btnText = document.getElementById('create-btn-text'); const btnSpinner = document.getElementById('create-btn-spinner');
    btnText.classList.add('hidden'); btnSpinner.classList.remove('hidden');
    try {
        const result = await AmbiletAPI.organizer.createEvent({ title: formData.get('title'), category_id: formData.get('category_id'), city: formData.get('city'), venue_name: formData.get('venue'), start_date: formData.get('start_date'), start_time: formData.get('start_time'), description: formData.get('description') });
        if (result.success !== false) { AmbiletNotifications.success('Eveniment creat cu succes!'); closeNewEventModal(); form.reset(); loadEvents(); }
        else { AmbiletNotifications.error(result.message || 'Eroare la crearea evenimentului.'); }
    } catch (error) { AmbiletNotifications.error('A aparut o eroare. Incearca din nou.'); }
    btnText.classList.remove('hidden'); btnSpinner.classList.add('hidden');
});

document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeNewEventModal(); });
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
