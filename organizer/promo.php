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
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Coduri Promotionale</h1>
                    <p class="text-sm text-muted">Creeaza si gestioneaza coduri de reducere</p>
                </div>
                <button onclick="openCreateModal()" class="btn btn-primary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>Cod Nou</button>
            </div>


            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-2xl border border-border p-6"><div class="flex items-center gap-3 mb-3"><div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center"><svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg></div><span class="text-sm text-muted">Coduri Active</span></div><p class="text-2xl font-bold text-secondary" id="active-codes">0</p></div>
                <div class="bg-white rounded-2xl border border-border p-6"><div class="flex items-center gap-3 mb-3"><div class="w-10 h-10 bg-success/10 rounded-xl flex items-center justify-center"><svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><span class="text-sm text-muted">Utilizari</span></div><p class="text-2xl font-bold text-secondary" id="total-uses">0</p></div>
                <div class="bg-white rounded-2xl border border-border p-6"><div class="flex items-center gap-3 mb-3"><div class="w-10 h-10 bg-accent/10 rounded-xl flex items-center justify-center"><svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div><span class="text-sm text-muted">Reduceri Acordate</span></div><p class="text-2xl font-bold text-secondary" id="total-discounts">0 RON</p></div>
                <div class="bg-white rounded-2xl border border-border p-6"><div class="flex items-center gap-3 mb-3"><div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center"><svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></div><span class="text-sm text-muted">Venituri Generate</span></div><p class="text-2xl font-bold text-secondary" id="revenue-codes">0 RON</p></div>
            </div>

            <div class="bg-white rounded-2xl border border-border overflow-hidden">
                <div class="p-6 border-b border-border flex items-center justify-between">
                    <h2 class="text-lg font-bold text-secondary">Toate Codurile</h2>
                    <div class="flex items-center gap-3">
                        <input type="text" id="search-codes" placeholder="Cauta cod..." class="input w-48">
                        <select id="status-filter" class="input"><option value="">Toate</option><option value="active">Active</option><option value="expired">Expirate</option><option value="disabled">Dezactivate</option></select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-surface"><tr><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Cod</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Reducere</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Eveniment</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Utilizari</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Valabilitate</th><th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Status</th><th class="px-6 py-4 text-right text-sm font-semibold text-secondary">Actiuni</th></tr></thead>
                        <tbody id="promo-codes-list" class="divide-y divide-border"></tbody>
                    </table>
                </div>
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
                <div><label class="label">Limita Utilizari</label><input type="number" id="usage-limit" min="0" placeholder="Nelimitat" class="input w-full"><p class="text-sm text-muted mt-1">Lasa gol pentru nelimitat</p></div>
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

document.addEventListener('DOMContentLoaded', function() { loadPromoCodes(); loadEvents(); setupDiscountType(); document.getElementById('start-date').value = new Date().toISOString().split('T')[0]; });

function setupDiscountType() { document.querySelectorAll('input[name="discount_type"]').forEach(r => r.addEventListener('change', function() { document.getElementById('discount-suffix').textContent = this.value === 'percentage' ? '%' : 'RON'; document.getElementById('discount-value').max = this.value === 'percentage' ? 100 : 10000; })); }

async function loadEvents() {
    try {
        const res = await AmbiletAPI.get('/organizer/events');
        if (res.success && res.data.events) { const sel = document.getElementById('promo-event'); res.data.events.forEach(e => { const opt = document.createElement('option'); opt.value = e.id; opt.textContent = e.title; sel.appendChild(opt); }); }
    } catch (e) { /* Events will load when API is available */ }
}

async function loadPromoCodes() {
    try {
        const response = await AmbiletAPI.get('/organizer/promo-codes');
        if (response.success) {
            promoCodes = response.data.promo_codes || [];
            renderPromoCodes();
            document.getElementById('active-codes').textContent = promoCodes.filter(c => c.status === 'active').length;
            document.getElementById('total-uses').textContent = promoCodes.reduce((s, c) => s + (c.usage_count || 0), 0);
            document.getElementById('total-discounts').textContent = AmbiletUtils.formatCurrency(response.data.total_discounts || 0);
            document.getElementById('revenue-codes').textContent = AmbiletUtils.formatCurrency(response.data.revenue_generated || 0);
        } else { promoCodes = []; renderPromoCodes(); }
    } catch (error) { promoCodes = []; renderPromoCodes(); }
}

function renderPromoCodes() {
    const container = document.getElementById('promo-codes-list');
    if (!promoCodes.length) { container.innerHTML = '<tr><td colspan="7" class="px-6 py-12 text-center text-muted">Nu ai coduri promotionale</td></tr>'; return; }
    container.innerHTML = promoCodes.map(c => `
        <tr class="hover:bg-surface/50"><td class="px-6 py-4"><div class="flex items-center gap-2"><code class="px-3 py-1 bg-primary/10 text-primary font-mono font-semibold rounded-lg">${c.code}</code><button onclick="copyCode('${c.code}')" class="p-1 text-muted hover:text-secondary"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></button></div></td>
        <td class="px-6 py-4 font-semibold text-secondary">${c.discount_type === 'percentage' ? c.discount_value + '%' : AmbiletUtils.formatCurrency(c.discount_value)}</td>
        <td class="px-6 py-4">${c.event || 'Toate'}</td>
        <td class="px-6 py-4">${c.usage_count}${c.usage_limit ? ' / ' + c.usage_limit : ''}</td>
        <td class="px-6 py-4"><span class="text-sm text-muted">${AmbiletUtils.formatDate(c.start_date)} - ${AmbiletUtils.formatDate(c.end_date)}</span></td>
        <td class="px-6 py-4"><span class="px-3 py-1 bg-${c.status === 'active' ? 'success' : c.status === 'expired' ? 'muted' : 'error'}/10 text-${c.status === 'active' ? 'success' : c.status === 'expired' ? 'muted' : 'error'} text-sm rounded-full">${c.status === 'active' ? 'Activ' : c.status === 'expired' ? 'Expirat' : 'Dezactivat'}</span></td>
        <td class="px-6 py-4 text-right"><button onclick="editCode(${c.id})" class="p-2 text-muted hover:text-secondary"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></button><button onclick="deleteCode(${c.id})" class="p-2 text-muted hover:text-error"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg></button></td></tr>
    `).join('');
}

function openCreateModal() { document.getElementById('modal-title').textContent = 'Creeaza Cod Promotional'; document.getElementById('promo-id').value = ''; document.getElementById('promo-code').value = ''; document.getElementById('discount-value').value = ''; document.getElementById('promo-event').value = ''; document.getElementById('usage-limit').value = ''; document.querySelector('input[name="discount_type"][value="percentage"]').checked = true; document.getElementById('start-date').value = new Date().toISOString().split('T')[0]; document.getElementById('end-date').value = ''; document.getElementById('promo-modal').classList.remove('hidden'); document.getElementById('promo-modal').classList.add('flex'); }
function closePromoModal() { document.getElementById('promo-modal').classList.add('hidden'); document.getElementById('promo-modal').classList.remove('flex'); }

function editCode(id) { const code = promoCodes.find(c => c.id === id); if (!code) return; document.getElementById('modal-title').textContent = 'Editeaza Cod'; document.getElementById('promo-id').value = code.id; document.getElementById('promo-code').value = code.code; document.getElementById('discount-value').value = code.discount_value; document.getElementById('promo-event').value = code.event_id || ''; document.getElementById('usage-limit').value = code.usage_limit || ''; document.getElementById('start-date').value = code.start_date; document.getElementById('end-date').value = code.end_date; document.querySelector(`input[name="discount_type"][value="${code.discount_type}"]`).checked = true; document.getElementById('promo-modal').classList.remove('hidden'); document.getElementById('promo-modal').classList.add('flex'); }
function generateCode() { const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'; let code = ''; for (let i = 0; i < 8; i++) code += chars.charAt(Math.floor(Math.random() * chars.length)); document.getElementById('promo-code').value = code; }
function copyCode(code) { navigator.clipboard.writeText(code); AmbiletNotifications.success('Codul a fost copiat'); }
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
    const data = {
        code: document.getElementById('promo-code').value,
        discount_type: document.querySelector('input[name="discount_type"]:checked').value,
        discount_value: document.getElementById('discount-value').value,
        event_id: document.getElementById('promo-event').value || null,
        usage_limit: document.getElementById('usage-limit').value || null,
        start_date: document.getElementById('start-date').value,
        end_date: document.getElementById('end-date').value
    };
    const id = document.getElementById('promo-id').value;
    try {
        const response = id ? await AmbiletAPI.put('/organizer/promo-codes/' + id, data) : await AmbiletAPI.post('/organizer/promo-codes', data);
        if (response.success) { AmbiletNotifications.success('Codul a fost salvat'); closePromoModal(); loadPromoCodes(); }
        else { AmbiletNotifications.error(response.message || 'Eroare la salvare'); }
    } catch (error) { AmbiletNotifications.error('Eroare la salvare'); }
}

document.getElementById('search-codes').addEventListener('input', AmbiletUtils.debounce(function() { const q = this.value.toLowerCase(); const status = document.getElementById('status-filter').value; const filtered = promoCodes.filter(c => (!q || c.code.toLowerCase().includes(q)) && (!status || c.status === status)); const temp = promoCodes; promoCodes = filtered; renderPromoCodes(); promoCodes = temp; }, 300));
document.getElementById('status-filter').addEventListener('change', function() { const q = document.getElementById('search-codes').value.toLowerCase(); const status = this.value; const filtered = promoCodes.filter(c => (!q || c.code.toLowerCase().includes(q)) && (!status || c.status === status)); const temp = promoCodes; promoCodes = filtered; renderPromoCodes(); promoCodes = temp; });
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
