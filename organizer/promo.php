<?php
/**
 * bilete.online — Organizator › Promo (v3).
 * Route: /organizator/promo
 *
 * Promo code management: stats, searchable/filterable card grid, create/edit
 * modal (percentage/fixed, per-activity + per-ticket-type scoping, usage
 * limits, validity range). Ported from ambilet to v3 + shell, wired to
 * BileteOnlineAPI.organizer promo-code endpoints. Dynamic Tailwind colour
 * classes were replaced with fixed class maps (bilete CSS is pre-built).
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Coduri promo';
$currentPage = 'promo';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <h1 class="font-display text-3xl font-bold leading-none">Coduri promoționale</h1>
                <p class="mt-1.5 text-sm text-ink-soft">Creează și gestionează coduri de reducere.</p>
            </div>
            <div class="flex items-center gap-3">
                <input type="text" id="search-codes" placeholder="Caută cod…" class="w-44 rounded-xl border-2 border-ink/15 bg-paper px-4 py-2 text-sm font-medium outline-none transition focus:border-ink">
                <select id="status-filter" class="rounded-xl border-2 border-ink/15 bg-paper px-4 py-2 text-sm font-medium outline-none transition focus:border-ink">
                    <option value="">Toate</option>
                    <option value="active">Active</option>
                    <option value="expired">Expirate</option>
                    <option value="disabled">Dezactivate</option>
                </select>
            </div>
        </div>

        <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-2xl border-2 border-ink bg-paper p-6"><div class="mb-3 flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-vermilion/10 text-vermilion"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg></span><span class="text-sm text-ink-soft">Coduri active</span></div><p id="active-codes" class="font-display text-2xl font-bold">0</p></div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-6"><div class="mb-3 flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-forest/10 text-forest"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span><span class="text-sm text-ink-soft">Utilizări</span></div><p id="total-uses" class="font-display text-2xl font-bold">0</p></div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-6"><div class="mb-3 flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-ochre/10 text-ochre"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span><span class="text-sm text-ink-soft">Reduceri acordate</span></div><p id="total-discounts" class="font-display text-2xl font-bold">0 RON</p></div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-6"><div class="mb-3 flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl bg-sky/10 text-sky"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></span><span class="text-sm text-ink-soft">Venituri generate</span></div><p id="revenue-codes" class="font-display text-2xl font-bold">0 RON</p></div>
        </div>

        <div id="promo-codes-grid" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6"></div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<!-- Promo modal -->
<div id="promo-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-ink/60 p-4 backdrop-blur-sm">
    <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-[2rem] border-2 border-ink bg-paper shadow-deep">
        <div class="sticky top-0 z-10 flex items-center justify-between border-b-2 border-ink/10 bg-paper p-6">
            <h3 id="modal-title" class="font-display text-xl font-bold">Creează cod promoțional</h3>
            <button onclick="closePromoModal()" aria-label="Închide" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-paper transition hover:bg-vermilion">×</button>
        </div>
        <form onsubmit="savePromoCode(event)" class="space-y-4 p-6">
            <input type="hidden" id="promo-id">
            <div>
                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Cod promoțional *</label>
                <div class="flex gap-2">
                    <input type="text" id="promo-code" placeholder="Ex: VARA2026" required class="flex-1 rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm font-bold uppercase outline-none transition focus:border-ink">
                    <button type="button" onclick="generateCode()" class="grid w-11 place-items-center rounded-xl border-2 border-ink transition hover:bg-ink hover:text-paper"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg></button>
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Tip reducere *</label>
                <div class="grid grid-cols-2 gap-3">
                    <label><input type="radio" name="discount_type" value="percentage" class="peer sr-only" checked><div class="cursor-pointer rounded-xl border-2 border-ink/15 p-4 transition peer-checked:border-vermilion peer-checked:bg-vermilion/5"><p class="font-bold">Procent</p><p class="text-sm text-ink-soft">Ex: 10% reducere</p></div></label>
                    <label><input type="radio" name="discount_type" value="fixed" class="peer sr-only"><div class="cursor-pointer rounded-xl border-2 border-ink/15 p-4 transition peer-checked:border-vermilion peer-checked:bg-vermilion/5"><p class="font-bold">Sumă fixă</p><p class="text-sm text-ink-soft">Ex: 50 RON</p></div></label>
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Valoare reducere *</label>
                <div class="relative"><input type="number" id="discount-value" min="1" max="100" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 pr-12 text-sm outline-none transition focus:border-ink"><span id="discount-suffix" class="absolute right-4 top-1/2 -translate-y-1/2 text-ink-soft">%</span></div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Limitează la o activitate *</label>
                <select id="promo-event" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink"><option value="">— Selectează o activitate —</option></select>
                <p class="mt-1 text-sm text-ink-soft">Codul se aplică doar pe această activitate.</p>
            </div>
            <div id="ticket-type-container" class="hidden">
                <label class="mb-1.5 block text-xs font-bold text-ink-soft">Limitează la tipuri de bilete *</label>
                <div id="promo-ticket-type-list" class="max-h-56 space-y-1 overflow-y-auto rounded-xl border-2 border-ink/15 bg-paper-2 p-3"><p class="text-sm text-ink-soft">Selectează o activitate pentru a vedea tipurile disponibile.</p></div>
                <p class="mt-1 text-sm text-ink-soft">Bifează unul sau mai multe. Codul va funcționa doar pentru tipurile selectate.</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="mb-1.5 block text-xs font-bold text-ink-soft">Limită utilizări totale</label><input type="number" id="usage-limit" min="0" placeholder="Nelimitat" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink"></div>
                <div><label class="mb-1.5 block text-xs font-bold text-ink-soft">Limită per client</label><input type="number" id="usage-limit-per-customer" min="0" placeholder="Nelimitat" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink"></div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="mb-1.5 block text-xs font-bold text-ink-soft">Dată început *</label><input type="date" id="start-date" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink"></div>
                <div><label class="mb-1.5 block text-xs font-bold text-ink-soft">Dată sfârșit *</label><input type="date" id="end-date" required class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink"></div>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closePromoModal()" class="flex-1 rounded-full border-2 border-ink py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Anulează</button>
                <button type="submit" class="flex-1 rounded-full bg-vermilion py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">Salvează</button>
            </div>
        </form>
    </div>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
function orgNotify(msg, type) {
    try { if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) { BileteOnlineNotifications[type || 'info'](msg); return; } } catch (e) {}
    if (type === 'error') alert(msg);
}
function escHtml(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }
function money(v) { try { return BileteOnlineUtils.formatCurrency(v); } catch (e) { return (parseFloat(v) || 0) + ' RON'; } }
function fmtDate(d) { try { return BileteOnlineUtils.formatDate(d); } catch (e) { return d || ''; } }

let allPromoCodes = [], promoCodes = [], promoEvents = [];

document.addEventListener('DOMContentLoaded', function () {
    if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth && !BileteOnlineAuth.requireOrganizerAuth()) return;
    loadPromoCodes();
    loadEvents();
    setupDiscountType();
    document.getElementById('start-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('search-codes').addEventListener('input', BileteOnlineUtils.debounce(filterPromoCodes, 300));
    document.getElementById('status-filter').addEventListener('change', filterPromoCodes);
    document.addEventListener('click', function (e) {
        if (!e.target.closest('[id^="promo-menu-"]') && !e.target.closest('button[onclick^="togglePromoMenu"]')) closeAllPromoMenus();
    });
});

function setupDiscountType() {
    document.querySelectorAll('input[name="discount_type"]').forEach(r => r.addEventListener('change', function () {
        document.getElementById('discount-suffix').textContent = this.value === 'percentage' ? '%' : 'RON';
        document.getElementById('discount-value').max = this.value === 'percentage' ? 100 : 10000;
    }));
}

async function loadEvents() {
    try {
        const res = await BileteOnlineAPI.get('/organizer/events');
        if (res && res.success && res.data) {
            let all = Array.isArray(res.data.events) ? res.data.events : (Array.isArray(res.data.data) ? res.data.data : (Array.isArray(res.data) ? res.data : []));
            promoEvents = all.filter(e => e.is_editable !== false && e.is_past !== true && !e.is_cancelled);
            const sel = document.getElementById('promo-event');
            promoEvents.forEach(e => { const o = document.createElement('option'); o.value = e.id || e.event_id || ''; o.textContent = e.name || e.title || 'Activitate'; sel.appendChild(o); });
            sel.addEventListener('change', () => onEventSelected());
        }
    } catch (e) {}
}

async function onEventSelected(preSelected) {
    if (!Array.isArray(preSelected)) preSelected = [];
    const eventId = document.getElementById('promo-event').value;
    const container = document.getElementById('ticket-type-container');
    const list = document.getElementById('promo-ticket-type-list');
    if (!eventId) { container.classList.add('hidden'); list.innerHTML = '<p class="text-sm text-ink-soft">Selectează o activitate pentru a vedea tipurile disponibile.</p>'; return; }
    list.innerHTML = '<p class="text-sm text-ink-soft">Se încarcă…</p>';
    container.classList.remove('hidden');
    try {
        const res = await BileteOnlineAPI.get(`/organizer/events/${eventId}`);
        const tts = (res && res.success && res.data && res.data.event && res.data.event.ticket_types) || [];
        if (tts.length) {
            const preSet = new Set(preSelected.map(x => parseInt(x, 10)));
            list.innerHTML = tts.map(tt => `<label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 transition hover:bg-paper"><input type="checkbox" name="promo_ticket_type_ids" value="${tt.id}" class="promo-tt-checkbox rounded" ${preSet.has(parseInt(tt.id, 10)) ? 'checked' : ''}><span class="flex-1 text-sm">${escHtml(tt.name)}</span><span class="text-xs text-ink-soft">${money(tt.price || tt.display_price || 0)}</span></label>`).join('');
        } else list.innerHTML = '<p class="text-sm text-ink-soft">Această activitate nu are tipuri de bilete configurate.</p>';
    } catch (e) { list.innerHTML = '<p class="text-sm text-vermilion">Eroare la încărcarea tipurilor de bilete.</p>'; }
}

function getSelectedTicketTypeIds() {
    return Array.from(document.querySelectorAll('.promo-tt-checkbox:checked')).map(c => parseInt(c.value, 10)).filter(v => !isNaN(v));
}

async function loadPromoCodes() {
    try {
        const r = await BileteOnlineAPI.get('/organizer/promo-codes');
        if (r && r.success) {
            allPromoCodes = Array.isArray(r.data) ? r.data : (r.data.data || r.data.promo_codes || []);
            promoCodes = [...allPromoCodes];
            renderPromoCodes();
            document.getElementById('active-codes').textContent = allPromoCodes.filter(c => c.status === 'active').length;
            document.getElementById('total-uses').textContent = allPromoCodes.reduce((s, c) => s + (c.usage_count || 0), 0);
            const totalDisc = allPromoCodes.reduce((s, c) => s + ((c.usage_count || 0) * (c.value || 0)), 0);
            document.getElementById('total-discounts').textContent = money((r.meta && r.meta.total_discounts) || totalDisc || 0);
            document.getElementById('revenue-codes').textContent = money((r.meta && r.meta.revenue_generated) || 0);
        } else { allPromoCodes = []; promoCodes = []; renderPromoCodes(); }
    } catch (e) { allPromoCodes = []; promoCodes = []; renderPromoCodes(); }
}

// Fixed colour palette per card (bilete CSS is pre-built — no runtime classes).
function cardTheme(code) {
    if (code.status !== 'active') return { icon: 'bg-ink/10 text-ink-soft', bar: 'bg-ink/30', big: 'text-ink-soft' };
    const type = code.type || code.discount_type, value = code.value || code.discount_value || 0;
    if (type === 'percentage' && value >= 20) return { icon: 'bg-ochre/10 text-ochre', bar: 'bg-ochre', big: 'text-ochre' };
    if (type === 'fixed' && value >= 50) return { icon: 'bg-forest/10 text-forest', bar: 'bg-forest', big: 'text-forest' };
    return { icon: 'bg-vermilion/10 text-vermilion', bar: 'bg-vermilion', big: 'text-vermilion' };
}

function statusBadge(status) {
    if (status === 'active') return '<span class="rounded-full bg-forest/15 px-2 py-1 text-xs font-bold text-forest">Activ</span>';
    if (status === 'expired') return '<span class="rounded-full bg-ink/10 px-2 py-1 text-xs font-bold text-ink-soft">Expirat</span>';
    return '<span class="rounded-full bg-vermilion/15 px-2 py-1 text-xs font-bold text-vermilion">Dezactivat</span>';
}

function renderPromoCodes() {
    const container = document.getElementById('promo-codes-grid');
    let html = promoCodes.map(c => {
        const type = c.type || c.discount_type, value = c.value || c.discount_value || 0;
        const th = cardTheme(c);
        const expired = c.status === 'expired' || c.status === 'disabled';
        let eventName = 'Toate activitățile';
        if (c.event) eventName = (typeof c.event === 'string') ? c.event : (c.event.name || c.event.title || eventName);
        else if (c.event_name) eventName = c.event_name;
        const endDate = c.expires_at || c.end_date;
        const usage = c.usage_count || 0, limit = c.usage_limit || 0;
        const pct = limit > 0 ? Math.min((usage / limit) * 100, 100) : 0;
        const discountDisplay = type === 'percentage' ? value + '% reducere' : money(value) + ' reducere';
        const menu = c.source === 'admin'
            ? '<span class="rounded-full bg-sky/15 px-2 py-1 text-xs font-bold text-sky">Admin</span>'
            : `<div class="relative">
                <button onclick="togglePromoMenu('${c.id}')" class="grid h-9 w-9 place-items-center rounded-lg transition hover:bg-paper-2"><svg class="h-5 w-5 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01"/></svg></button>
                <div id="promo-menu-${c.id}" class="absolute right-0 z-10 mt-1 hidden w-36 rounded-xl border-2 border-ink bg-paper shadow-deep">
                    <button onclick="editCode(${c.id}); closeAllPromoMenus();" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm transition hover:bg-paper-2"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>Editează</button>
                    <button onclick="deleteCode(${c.id}); closeAllPromoMenus();" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-vermilion transition hover:bg-paper-2"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>Șterge</button>
                </div>
            </div>`;
        return `
        <div class="overflow-hidden rounded-2xl border-2 border-ink bg-paper ${expired ? 'opacity-60' : ''}">
            <div class="p-5">
                <div class="mb-4 flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <span class="grid h-12 w-12 place-items-center rounded-xl ${th.icon}"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg></span>
                        <div>${statusBadge(c.status)}</div>
                    </div>
                    ${menu}
                </div>
                <div class="mb-3 flex items-center gap-2">
                    <code class="rounded-lg bg-paper-2 px-3 py-1.5 text-lg font-bold">${escHtml(c.code)}</code>
                    ${!expired ? `<button onclick="copyCode('${escHtml(c.code)}')" class="grid h-9 w-9 place-items-center rounded-lg transition hover:bg-paper-2"><svg class="h-4 w-4 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg></button>` : ''}
                </div>
                <p class="mb-1 font-display text-2xl font-bold ${expired ? 'text-ink-soft' : th.big}">${discountDisplay}</p>
                <p class="mb-4 text-sm text-ink-soft">${escHtml(eventName)}</p>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div><p class="text-xs text-ink-soft">Utilizări</p><p class="font-bold ${expired ? 'text-ink-soft' : ''}">${usage}${limit ? ' / ' + limit : ''}</p></div>
                    <div><p class="text-xs text-ink-soft">${expired ? 'Expirat la' : 'Expiră'}</p><p class="font-bold ${expired ? 'text-ink-soft' : ''}">${endDate ? fmtDate(endDate) : 'Nelimitat'}</p></div>
                </div>
            </div>
            ${limit > 0 ? `<div class="h-1.5 bg-paper-2"><div class="h-full ${th.bar} rounded-r-full" style="width:${pct}%"></div></div>` : ''}
        </div>`;
    }).join('');

    html += `
    <div onclick="openCreateModal()" class="flex min-h-[240px] cursor-pointer items-center justify-center rounded-2xl border-2 border-dashed border-ink/30 transition hover:border-vermilion hover:bg-vermilion/5">
        <div class="p-6 text-center">
            <span class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-xl bg-paper-2 text-ink-soft"><svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg></span>
            <p class="font-bold">Creează cod nou</p>
            <p class="text-sm text-ink-soft">Adaugă un nou cod de reducere</p>
        </div>
    </div>`;
    container.innerHTML = html;
}

function filterPromoCodes() {
    const q = document.getElementById('search-codes').value.toLowerCase().trim();
    const status = document.getElementById('status-filter').value;
    promoCodes = allPromoCodes.filter(c => (!q || (c.code || '').toLowerCase().includes(q)) && (!status || c.status === status));
    renderPromoCodes();
}

function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Creează cod promoțional';
    document.getElementById('promo-id').value = '';
    document.getElementById('promo-code').value = '';
    document.getElementById('discount-value').value = '';
    document.getElementById('promo-event').value = '';
    document.getElementById('usage-limit').value = '';
    document.getElementById('usage-limit-per-customer').value = '';
    document.getElementById('promo-ticket-type-list').innerHTML = '<p class="text-sm text-ink-soft">Selectează o activitate pentru a vedea tipurile disponibile.</p>';
    document.getElementById('ticket-type-container').classList.add('hidden');
    document.querySelector('input[name="discount_type"][value="percentage"]').checked = true;
    document.getElementById('discount-suffix').textContent = '%';
    document.getElementById('start-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('end-date').value = '';
    showModal();
}
function showModal() { const m = document.getElementById('promo-modal'); m.classList.remove('hidden'); m.classList.add('flex'); }
function closePromoModal() { const m = document.getElementById('promo-modal'); m.classList.add('hidden'); m.classList.remove('flex'); }

async function editCode(id) {
    const code = promoCodes.find(c => c.id === id);
    if (!code) return;
    document.getElementById('modal-title').textContent = 'Editează cod';
    document.getElementById('promo-id').value = code.id;
    document.getElementById('promo-code').value = code.code;
    document.getElementById('discount-value').value = code.value || code.discount_value;
    document.getElementById('promo-event').value = (code.event && code.event.id) || code.event_id || '';
    document.getElementById('usage-limit').value = code.usage_limit || '';
    document.getElementById('usage-limit-per-customer').value = code.usage_limit_per_customer || '';
    const eventId = (code.event && code.event.id) || code.event_id;
    if (eventId) {
        let pre = [];
        if (Array.isArray(code.applicable_ticket_type_ids) && code.applicable_ticket_type_ids.length) pre = code.applicable_ticket_type_ids;
        else { const ttId = (code.ticket_type && code.ticket_type.id) || code.ticket_type_id; if (ttId) pre = [ttId]; }
        await onEventSelected(pre);
    } else document.getElementById('ticket-type-container').classList.add('hidden');
    const start = code.starts_at || code.start_date, end = code.expires_at || code.end_date;
    document.getElementById('start-date').value = start ? start.split('T')[0] : '';
    document.getElementById('end-date').value = end ? end.split('T')[0] : '';
    const type = code.type || code.discount_type || 'percentage';
    document.querySelector(`input[name="discount_type"][value="${type}"]`).checked = true;
    document.getElementById('discount-suffix').textContent = type === 'percentage' ? '%' : 'RON';
    showModal();
}

function generateCode() { const c = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'; let s = ''; for (let i = 0; i < 8; i++) s += c.charAt(Math.floor(Math.random() * c.length)); document.getElementById('promo-code').value = s; }
function copyCode(code) { navigator.clipboard.writeText(code); orgNotify('Codul a fost copiat.', 'success'); }
function togglePromoMenu(id) { closeAllPromoMenus(); const m = document.getElementById('promo-menu-' + id); if (m) m.classList.toggle('hidden'); }
function closeAllPromoMenus() { document.querySelectorAll('[id^="promo-menu-"]').forEach(m => m.classList.add('hidden')); }

async function deleteCode(id) {
    if (!confirm('Ștergi acest cod?')) return;
    try {
        const r = await BileteOnlineAPI.delete('/organizer/promo-codes/' + id);
        if (r && r.success) { orgNotify('Codul a fost șters.', 'success'); loadPromoCodes(); }
        else orgNotify((r && r.message) || 'Eroare la ștergere.', 'error');
    } catch (e) { orgNotify('Eroare la ștergere.', 'error'); }
}

async function savePromoCode(e) {
    e.preventDefault();
    const raw = document.getElementById('promo-event').value;
    const eventId = raw && raw.trim() !== '' ? parseInt(raw, 10) : null;
    if (!eventId || isNaN(eventId)) { orgNotify('Selectează o activitate.', 'error'); return; }
    const ttIds = getSelectedTicketTypeIds();
    if (!ttIds.length) { orgNotify('Selectează cel puțin un tip de bilet.', 'error'); return; }
    const perCustRaw = document.getElementById('usage-limit-per-customer').value;
    const data = {
        code: document.getElementById('promo-code').value.trim().toUpperCase(),
        type: document.querySelector('input[name="discount_type"]:checked').value,
        value: parseFloat(document.getElementById('discount-value').value) || 0,
        applies_to: 'ticket_type',
        event_id: eventId,
        ticket_type_ids: ttIds,
        usage_limit: document.getElementById('usage-limit').value ? parseInt(document.getElementById('usage-limit').value, 10) : null,
        usage_limit_per_customer: perCustRaw ? parseInt(perCustRaw, 10) : null,
        starts_at: document.getElementById('start-date').value || null,
        expires_at: document.getElementById('end-date').value || null
    };
    const id = document.getElementById('promo-id').value;
    try {
        const r = id ? await BileteOnlineAPI.put('/organizer/promo-codes/' + id, data) : await BileteOnlineAPI.post('/organizer/promo-codes', data);
        if (r && r.success) { orgNotify('Codul a fost salvat.', 'success'); closePromoModal(); loadPromoCodes(); }
        else orgNotify((r && r.message) || 'Eroare la salvare.', 'error');
    } catch (e) { orgNotify((e && e.message) || 'Eroare la salvare.', 'error'); }
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
