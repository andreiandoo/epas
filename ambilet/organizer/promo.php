<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Coduri Promo';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'promo';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
        <!-- Page Content -->
        <main class="flex-1 p-4 lg:p-8">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Coduri Promotionale</h1>
                    <p class="text-sm text-muted">Creeaza si gestioneaza coduri de reducere</p>
                </div>
                <div class="flex items-center gap-3">
                    <input type="text" id="search-codes" placeholder="Cauta cod..." class="input w-48">
                    <select id="status-filter" class="input">
                        <option value="">Toate</option>
                        <option value="active">Active</option>
                        <option value="expired">Expirate</option>
                        <option value="disabled">Dezactivate</option>
                    </select>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        </div>
                        <span class="text-sm text-muted">Coduri Active</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="active-codes">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-success/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-sm text-muted">Utilizari</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="total-uses">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-accent/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-sm text-muted">Reduceri Acordate</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="total-discounts">0 RON</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                        <span class="text-sm text-muted">Venituri Generate</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="revenue-codes">0 RON</p>
                </div>
            </div>

            <!-- Promo Codes Grid -->
            <div id="promo-codes-grid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
                <!-- Cards will be inserted here by JS -->
            </div>
        </main>
    </div>

    <div id="promo-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white p-6 border-b border-border flex items-center justify-between">
                <h3 id="modal-title" class="text-xl font-bold text-secondary">Creeaza Cod Promotional</h3>
                <button onclick="closePromoModal()" class="p-2 hover:bg-surface rounded-lg"><svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>
            <form onsubmit="savePromoCode(event)" class="p-6 space-y-4">
                <input type="hidden" id="promo-id">
                <div><label class="label">Cod Promotional *</label><div class="flex gap-2"><input type="text" id="promo-code" placeholder="Ex: VARA2024" class="input flex-1 uppercase" required><button type="button" onclick="generateCode()" class="btn btn-secondary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg></button></div></div>
                <div><label class="label">Tip Reducere *</label><div class="grid grid-cols-2 gap-3"><label><input type="radio" name="discount_type" value="percentage" class="peer sr-only" checked><div class="p-4 border-2 border-border rounded-xl cursor-pointer peer-checked:border-primary peer-checked:bg-primary/5"><p class="font-medium text-secondary">Procent</p><p class="text-sm text-muted">Ex: 10% reducere</p></div></label><label><input type="radio" name="discount_type" value="fixed" class="peer sr-only"><div class="p-4 border-2 border-border rounded-xl cursor-pointer peer-checked:border-primary peer-checked:bg-primary/5"><p class="font-medium text-secondary">Suma Fixa</p><p class="text-sm text-muted">Ex: 50 RON</p></div></label></div></div>
                <div><label class="label">Valoare Reducere *</label><div class="relative"><input type="number" id="discount-value" min="1" max="100" class="input w-full pr-12" required><span id="discount-suffix" class="absolute right-4 top-1/2 -translate-y-1/2 text-muted">%</span></div></div>
                <div><label class="label">Aplicabil Pentru</label><select id="promo-event" class="input w-full"><option value="">Toate evenimentele</option></select></div>
                <div id="ticket-type-container" class="hidden"><label class="label">Tip Bilet</label><select id="promo-ticket-type" class="input w-full"><option value="">Toate tipurile de bilete</option></select><p class="text-sm text-muted mt-1">Optional: aplica doar pentru un anumit tip de bilet</p></div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="label">Limita Utilizari Totale</label><input type="number" id="usage-limit" min="0" placeholder="Nelimitat" class="input w-full"><p class="text-sm text-muted mt-1">Lasa gol pentru nelimitat</p></div>
                    <div><label class="label">Limita Per Client</label><input type="number" id="usage-limit-per-customer" min="0" placeholder="Nelimitat" class="input w-full"><p class="text-sm text-muted mt-1">Cate utilizari per client</p></div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div><label class="label">Data Inceput *</label><input type="date" id="start-date" class="input w-full" required></div>
                    <div><label class="label">Data Sfarsit *</label><input type="date" id="end-date" class="input w-full" required></div>
                </div>
                <div class="flex gap-3 pt-4"><button type="button" onclick="closePromoModal()" class="btn btn-secondary flex-1">Anuleaza</button><button type="submit" class="btn btn-primary flex-1">Salveaza</button></div>
            </form>
        </div>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();
let promoCodes = [];
let allPromoCodes = [];

document.addEventListener('DOMContentLoaded', function() { loadPromoCodes(); loadEvents(); setupDiscountType(); document.getElementById('start-date').value = new Date().toISOString().split('T')[0]; });

function setupDiscountType() { document.querySelectorAll('input[name="discount_type"]').forEach(r => r.addEventListener('change', function() { document.getElementById('discount-suffix').textContent = this.value === 'percentage' ? '%' : 'RON'; document.getElementById('discount-value').max = this.value === 'percentage' ? 100 : 10000; })); }

let promoEvents = [];

async function loadEvents() {
    try {
        const res = await AmbiletAPI.get('/organizer/events');
        if (res.success && res.data) {
            let allEvents = [];
            if (Array.isArray(res.data.events)) {
                allEvents = res.data.events;
            } else if (Array.isArray(res.data.data)) {
                allEvents = res.data.data;
            } else if (Array.isArray(res.data)) {
                allEvents = res.data;
            }
            // Filter out past/finished events
            promoEvents = allEvents.filter(e => e.is_editable !== false && e.is_past !== true && !e.is_cancelled);

            const sel = document.getElementById('promo-event');
            promoEvents.forEach(e => {
                const opt = document.createElement('option');
                opt.value = e.id || e.event_id || '';
                opt.textContent = e.name || e.title || 'Eveniment';
                sel.appendChild(opt);
            });

            // Setup event change listener for ticket types
            sel.addEventListener('change', onEventSelected);
        }
    } catch (e) { console.error('Failed to load events:', e); }
}

async function onEventSelected() {
    const eventId = document.getElementById('promo-event').value;
    const ticketTypeContainer = document.getElementById('ticket-type-container');

    if (!eventId) {
        ticketTypeContainer.classList.add('hidden');
        return;
    }

    try {
        const res = await AmbiletAPI.get(`/organizer/events/${eventId}`);
        if (res.success && res.data?.event?.ticket_types) {
            const ticketTypes = res.data.event.ticket_types;
            const sel = document.getElementById('promo-ticket-type');
            sel.innerHTML = '<option value="">Toate tipurile de bilete</option>';
            ticketTypes.forEach(tt => {
                const opt = document.createElement('option');
                opt.value = tt.id;
                opt.textContent = `${tt.name} (${AmbiletUtils.formatCurrency(tt.price || tt.display_price || 0)})`;
                sel.appendChild(opt);
            });
            ticketTypeContainer.classList.remove('hidden');
        } else {
            ticketTypeContainer.classList.add('hidden');
        }
    } catch (e) {
        console.error('Failed to load ticket types:', e);
        ticketTypeContainer.classList.add('hidden');
    }
}

async function loadPromoCodes() {
    try {
        const response = await AmbiletAPI.get('/organizer/promo-codes');
        if (response.success) {
            // API returns array directly in response.data, not response.data.promo_codes
            allPromoCodes = Array.isArray(response.data) ? response.data : (response.data.promo_codes || []);
            promoCodes = [...allPromoCodes];
            renderPromoCodes();
            document.getElementById('active-codes').textContent = allPromoCodes.filter(c => c.status === 'active').length;
            document.getElementById('total-uses').textContent = allPromoCodes.reduce((s, c) => s + (c.usage_count || 0), 0);
            // Calculate total discounts from usage
            const totalDiscounts = allPromoCodes.reduce((sum, c) => sum + ((c.usage_count || 0) * (c.value || 0)), 0);
            document.getElementById('total-discounts').textContent = AmbiletUtils.formatCurrency(response.meta?.total_discounts || totalDiscounts || 0);
            document.getElementById('revenue-codes').textContent = AmbiletUtils.formatCurrency(response.meta?.revenue_generated || 0);
        } else { allPromoCodes = []; promoCodes = []; renderPromoCodes(); }
    } catch (error) { console.error('Failed to load promo codes:', error); allPromoCodes = []; promoCodes = []; renderPromoCodes(); }
}

function getCardColor(code) {
    // Return different colors based on status and discount type
    if (code.status !== 'active') return 'muted';
    const type = code.type || code.discount_type;
    const value = code.value || code.discount_value || 0;
    if (type === 'percentage' && value >= 20) return 'accent';
    if (type === 'fixed' && value >= 50) return 'success';
    return 'primary';
}

function renderPromoCodes() {
    const container = document.getElementById('promo-codes-grid');

    // Generate cards HTML
    let cardsHtml = promoCodes.map(c => {
        const discountType = c.type || c.discount_type;
        const discountValue = c.value || c.discount_value || 0;
        const color = getCardColor(c);
        const isExpired = c.status === 'expired' || c.status === 'disabled';

        // Handle event name
        let eventName = 'Toate evenimentele';
        if (c.event) {
            if (typeof c.event === 'string') {
                eventName = c.event;
            } else if (typeof c.event === 'object') {
                eventName = c.event.name || c.event.title || 'Toate evenimentele';
            }
        } else if (c.event_name) {
            eventName = c.event_name;
        }

        const endDate = c.expires_at || c.end_date;
        const usageCount = c.usage_count || 0;
        const usageLimit = c.usage_limit || 0;
        const usagePercent = usageLimit > 0 ? Math.min((usageCount / usageLimit) * 100, 100) : 0;

        // Discount display
        const discountDisplay = discountType === 'percentage'
            ? discountValue + '% reducere'
            : AmbiletUtils.formatCurrency(discountValue) + ' reducere';

        // Status badge
        let statusBadge = '';
        if (c.status === 'active') {
            statusBadge = '<span class="px-2 py-1 bg-success/10 text-success text-xs font-semibold rounded-full">Activ</span>';
        } else if (c.status === 'expired') {
            statusBadge = '<span class="px-2 py-1 bg-muted/10 text-muted text-xs font-semibold rounded-full">Expirat</span>';
        } else {
            statusBadge = '<span class="px-2 py-1 bg-error/10 text-error text-xs font-semibold rounded-full">Dezactivat</span>';
        }

        return `
        <div class="promo-card bg-white rounded-xl lg:rounded-2xl border border-border overflow-hidden ${isExpired ? 'opacity-60' : ''}">
            <div class="p-4 lg:p-5">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-${color}/10 rounded-xl flex items-center justify-center">
                            <svg class="w-6 h-6 text-${color}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                        </div>
                        <div>
                            ${statusBadge}
                        </div>
                    </div>
                    <div class="relative">
                        <button onclick="togglePromoMenu(${c.id})" class="p-2 hover:bg-surface rounded-lg">
                            <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                        </button>
                        <div id="promo-menu-${c.id}" class="hidden absolute right-0 mt-1 w-36 bg-white rounded-lg shadow-lg border border-border z-10">
                            <button onclick="editCode(${c.id}); closeAllPromoMenus();" class="w-full px-4 py-2 text-left text-sm text-secondary hover:bg-surface flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Editeaza
                            </button>
                            <button onclick="deleteCode(${c.id}); closeAllPromoMenus();" class="w-full px-4 py-2 text-left text-sm text-error hover:bg-surface flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                Sterge
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 mb-3">
                    <code class="text-lg font-bold text-secondary bg-surface px-3 py-1.5 rounded-lg">${c.code}</code>
                    ${!isExpired ? `
                    <button onclick="copyCode('${c.code}')" class="copy-btn p-2 rounded-lg hover:bg-surface transition-colors">
                        <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                    </button>
                    ` : ''}
                </div>

                <p class="text-2xl font-bold text-${isExpired ? 'muted' : 'primary'} mb-1">${discountDisplay}</p>
                <p class="text-sm text-muted mb-4">${eventName}</p>

                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <p class="text-xs text-muted">Utilizari</p>
                        <p class="font-semibold text-${isExpired ? 'muted' : 'secondary'}">${usageCount}${usageLimit ? ' / ' + usageLimit : ''}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted">${isExpired ? 'Expirat la' : 'Expira'}</p>
                        <p class="font-semibold text-${isExpired ? 'muted' : 'secondary'}">${endDate ? AmbiletUtils.formatDate(endDate) : 'Nelimitat'}</p>
                    </div>
                </div>
            </div>
            ${usageLimit > 0 ? `
            <div class="h-1.5 bg-surface">
                <div class="h-full bg-${color} rounded-r-full" style="width: ${usagePercent}%"></div>
            </div>
            ` : ''}
        </div>`;
    }).join('');

    // Add "Create New" card at the end
    cardsHtml += `
    <div onclick="openCreateModal()" class="border-2 border-dashed border-border rounded-xl lg:rounded-2xl flex items-center justify-center min-h-[240px] hover:border-primary hover:bg-primary/5 transition-all cursor-pointer group">
        <div class="text-center p-6">
            <div class="w-12 h-12 bg-surface group-hover:bg-primary/10 rounded-xl flex items-center justify-center mx-auto mb-3 transition-colors">
                <svg class="w-6 h-6 text-muted group-hover:text-primary transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            </div>
            <p class="font-semibold text-secondary">Creeaza cod nou</p>
            <p class="text-sm text-muted">Adauga un nou cod de reducere</p>
        </div>
    </div>`;

    container.innerHTML = cardsHtml;
}

function filterPromoCodes() {
    const searchQuery = document.getElementById('search-codes').value.toLowerCase().trim();
    const statusFilter = document.getElementById('status-filter').value;

    promoCodes = allPromoCodes.filter(c => {
        const matchesSearch = !searchQuery || c.code.toLowerCase().includes(searchQuery);
        const matchesStatus = !statusFilter || c.status === statusFilter;
        return matchesSearch && matchesStatus;
    });

    renderPromoCodes();
}

function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Creeaza Cod Promotional';
    document.getElementById('promo-id').value = '';
    document.getElementById('promo-code').value = '';
    document.getElementById('discount-value').value = '';
    document.getElementById('promo-event').value = '';
    document.getElementById('usage-limit').value = '';
    document.getElementById('usage-limit-per-customer').value = '';
    document.getElementById('promo-ticket-type').innerHTML = '<option value="">Toate tipurile de bilete</option>';
    document.getElementById('ticket-type-container').classList.add('hidden');
    document.querySelector('input[name="discount_type"][value="percentage"]').checked = true;
    document.getElementById('start-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('end-date').value = '';
    document.getElementById('promo-modal').classList.remove('hidden');
    document.getElementById('promo-modal').classList.add('flex');
}
function closePromoModal() { document.getElementById('promo-modal').classList.add('hidden'); document.getElementById('promo-modal').classList.remove('flex'); }

async function editCode(id) {
    const code = promoCodes.find(c => c.id === id);
    if (!code) return;
    document.getElementById('modal-title').textContent = 'Editeaza Cod';
    document.getElementById('promo-id').value = code.id;
    document.getElementById('promo-code').value = code.code;
    document.getElementById('discount-value').value = code.value || code.discount_value;
    document.getElementById('promo-event').value = code.event?.id || code.event_id || '';
    document.getElementById('usage-limit').value = code.usage_limit || '';
    document.getElementById('usage-limit-per-customer').value = code.usage_limit_per_customer || '';

    // Load ticket types if event is selected
    const eventId = code.event?.id || code.event_id;
    if (eventId) {
        await onEventSelected();
        const ticketTypeId = code.ticket_type?.id || code.ticket_type_id;
        if (ticketTypeId) {
            document.getElementById('promo-ticket-type').value = ticketTypeId;
        }
    } else {
        document.getElementById('ticket-type-container').classList.add('hidden');
    }

    // Handle both date formats (ISO string and date string)
    const startDate = code.starts_at || code.start_date;
    const endDate = code.expires_at || code.end_date;
    document.getElementById('start-date').value = startDate ? startDate.split('T')[0] : '';
    document.getElementById('end-date').value = endDate ? endDate.split('T')[0] : '';
    const discountType = code.type || code.discount_type || 'percentage';
    document.querySelector(`input[name="discount_type"][value="${discountType}"]`).checked = true;
    document.getElementById('promo-modal').classList.remove('hidden');
    document.getElementById('promo-modal').classList.add('flex');
}
function generateCode() { const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'; let code = ''; for (let i = 0; i < 8; i++) code += chars.charAt(Math.floor(Math.random() * chars.length)); document.getElementById('promo-code').value = code; }
function copyCode(code) { navigator.clipboard.writeText(code); AmbiletNotifications.success('Codul a fost copiat'); }

function togglePromoMenu(id) {
    closeAllPromoMenus();
    const menu = document.getElementById('promo-menu-' + id);
    if (menu) menu.classList.toggle('hidden');
}
function closeAllPromoMenus() {
    document.querySelectorAll('[id^="promo-menu-"]').forEach(m => m.classList.add('hidden'));
}
// Close menus on click outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('[id^="promo-menu-"]') && !e.target.closest('button[onclick^="togglePromoMenu"]')) {
        closeAllPromoMenus();
    }
});

async function deleteCode(id) {
    if (!confirm('Stergi acest cod?')) return;
    try {
        const response = await AmbiletAPI.delete('/organizer/promo-codes/' + id);
        if (response.success) { AmbiletNotifications.success('Codul a fost sters'); loadPromoCodes(); }
        else { AmbiletNotifications.error(response.message || 'Eroare la stergere'); }
    } catch (error) { AmbiletNotifications.error('Eroare la stergere'); }
}
async function savePromoCode(e) {
    e.preventDefault();
    const eventIdRaw = document.getElementById('promo-event').value;
    // Only include event_id if it's a valid non-empty value
    const eventId = eventIdRaw && eventIdRaw.trim() !== '' ? parseInt(eventIdRaw, 10) : null;
    // Validate that parseInt didn't return NaN
    const validEventId = eventId !== null && !isNaN(eventId) ? eventId : null;

    // Get ticket type if selected
    const ticketTypeRaw = document.getElementById('promo-ticket-type')?.value;
    const ticketTypeId = ticketTypeRaw && ticketTypeRaw.trim() !== '' ? parseInt(ticketTypeRaw, 10) : null;
    const validTicketTypeId = ticketTypeId !== null && !isNaN(ticketTypeId) ? ticketTypeId : null;

    // Get usage limit per customer
    const usageLimitPerCustomerRaw = document.getElementById('usage-limit-per-customer')?.value;
    const usageLimitPerCustomer = usageLimitPerCustomerRaw ? parseInt(usageLimitPerCustomerRaw, 10) : null;

    const data = {
        code: document.getElementById('promo-code').value.trim().toUpperCase(),
        type: document.querySelector('input[name="discount_type"]:checked').value,
        value: parseFloat(document.getElementById('discount-value').value) || 0,
        applies_to: validEventId ? 'specific_event' : 'all_events',
        usage_limit: document.getElementById('usage-limit').value ? parseInt(document.getElementById('usage-limit').value, 10) : null,
        usage_limit_per_customer: usageLimitPerCustomer,
        starts_at: document.getElementById('start-date').value || null,
        expires_at: document.getElementById('end-date').value || null
    };

    // Only add event_id if it's valid to avoid "The selected event id is invalid" error
    if (validEventId) {
        data.event_id = validEventId;
    }

    // Add ticket_type_id if selected
    if (validTicketTypeId) {
        data.ticket_type_id = validTicketTypeId;
    }

    const id = document.getElementById('promo-id').value;
    try {
        const response = id ? await AmbiletAPI.put('/organizer/promo-codes/' + id, data) : await AmbiletAPI.post('/organizer/promo-codes', data);
        if (response.success) { AmbiletNotifications.success('Codul a fost salvat'); closePromoModal(); loadPromoCodes(); }
        else { AmbiletNotifications.error(response.message || 'Eroare la salvare'); }
    } catch (error) { AmbiletNotifications.error(error.message || 'Eroare la salvare'); }
}

document.getElementById('search-codes').addEventListener('input', AmbiletUtils.debounce(filterPromoCodes, 300));
document.getElementById('status-filter').addEventListener('change', filterPromoCodes);
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
