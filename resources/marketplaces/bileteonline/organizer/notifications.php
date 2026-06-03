<?php
/**
 * bilete.online — Organizator › Notificări (v3).
 * Route: /organizator/notificari
 *
 * Notification center: stats, type/read filters, paginated list with
 * mark-as-read (single + all). Ported from ambilet to v3 + shell, wired to the
 * organizer.notifications(.types/.read/.mark-all-read) proxy actions. Delete /
 * clear-read are intentionally omitted (no matching endpoint on bilete.online).
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Notificări';
$currentPage = 'notifications';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="font-display text-3xl font-bold leading-none">Notificări</h1>
                <p class="mt-1.5 text-sm text-ink-soft">Urmărește vânzările și evenimentele importante.</p>
            </div>
            <button onclick="markAllRead()" id="mark-all-read-btn" class="hidden items-center gap-2 self-start rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper sm:self-auto">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                Marchează toate ca citite
            </button>
        </div>

        <!-- Stats -->
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-2xl border-2 border-ink bg-paper p-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-full bg-vermilion/10 text-vermilion"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg></span><div><p id="stat-total" class="font-display text-2xl font-bold">0</p><p class="text-xs text-ink-soft">Total notificări</p></div></div></div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-full bg-ochre/10 text-ochre"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span><div><p id="stat-unread" class="font-display text-2xl font-bold text-ochre">0</p><p class="text-xs text-ink-soft">Necitite</p></div></div></div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-full bg-forest/10 text-forest"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span><div><p id="stat-sales" class="font-display text-2xl font-bold text-forest">0</p><p class="text-xs text-ink-soft">Vânzări</p></div></div></div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-full bg-sky/10 text-sky"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg></span><div><p id="stat-documents" class="font-display text-2xl font-bold text-sky">0</p><p class="text-xs text-ink-soft">Documente</p></div></div></div>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <select id="type-filter" class="rounded-xl border-2 border-ink/15 bg-paper px-4 py-2 text-sm font-medium outline-none transition focus:border-ink"><option value="">Toate tipurile</option></select>
            <select id="read-filter" class="rounded-xl border-2 border-ink/15 bg-paper px-4 py-2 text-sm font-medium outline-none transition focus:border-ink">
                <option value="">Toate notificările</option>
                <option value="0">Necitite</option>
                <option value="1">Citite</option>
            </select>
        </div>

        <div class="overflow-hidden rounded-2xl border-2 border-ink bg-paper">
            <div id="notifications-list" class="divide-y divide-ink/10"></div>
            <div id="empty-state" class="hidden p-12 text-center">
                <span class="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-full bg-vermilion/10 text-vermilion"><svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg></span>
                <h3 class="mb-1 font-display text-lg font-bold">Nu ai notificări</h3>
                <p class="text-ink-soft">Vei primi notificări când apar vânzări noi, documente sau alte evenimente importante.</p>
            </div>
            <div id="loading-state" class="p-12 text-center text-ink-soft">Se încarcă notificările…</div>
        </div>

        <div id="load-more-container" class="mt-4 hidden text-center">
            <button onclick="loadMore()" class="rounded-full border-2 border-ink px-6 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Încarcă mai multe</button>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
function orgNotify(msg, type) {
    try { if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) { BileteOnlineNotifications[type || 'info'](msg); return; } } catch (e) {}
    if (type === 'error') alert(msg);
}
function escHtml(t) { const d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; }

let currentPage = 1, totalPages = 1, hasMore = false, notificationTypes = {};

document.addEventListener('DOMContentLoaded', function () {
    if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth && !BileteOnlineAuth.requireOrganizerAuth()) return;
    document.getElementById('type-filter').addEventListener('change', () => loadNotifications());
    document.getElementById('read-filter').addEventListener('change', () => loadNotifications());
    init();
});

async function init() { await loadTypes(); await loadNotifications(); }

async function loadTypes() {
    try {
        const r = await BileteOnlineAPI.get('/organizer/notifications/types');
        if (r && r.success && r.data && r.data.types) {
            notificationTypes = r.data.types;
            const sel = document.getElementById('type-filter');
            sel.innerHTML = '<option value="">Toate tipurile</option>';
            for (const [type, label] of Object.entries(notificationTypes)) {
                const o = document.createElement('option'); o.value = type; o.textContent = label; sel.appendChild(o);
            }
        }
    } catch (e) {}
}

async function loadNotifications(append = false) {
    if (!append) {
        currentPage = 1;
        document.getElementById('loading-state').classList.remove('hidden');
        document.getElementById('notifications-list').innerHTML = '';
        document.getElementById('empty-state').classList.add('hidden');
    }
    const type = document.getElementById('type-filter').value;
    const read = document.getElementById('read-filter').value;
    let url = `/organizer/notifications?page=${currentPage}&per_page=20`;
    if (type) url += `&type=${encodeURIComponent(type)}`;
    if (read !== '') url += `&read=${read}`;
    try {
        const r = await BileteOnlineAPI.get(url);
        document.getElementById('loading-state').classList.add('hidden');
        if (r && r.success) {
            const list = r.data || [];
            const meta = r.meta || {};
            totalPages = meta.last_page || 1;
            hasMore = currentPage < totalPages;
            if (!append) { render(list); updateStats(list); } else { append_(list); }
            updateButtons(list);
            document.getElementById('load-more-container').classList.toggle('hidden', !hasMore);
        }
    } catch (e) {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('empty-state').classList.remove('hidden');
    }
}

function render(list) {
    const c = document.getElementById('notifications-list');
    if (!list.length) { c.innerHTML = ''; document.getElementById('empty-state').classList.remove('hidden'); return; }
    document.getElementById('empty-state').classList.add('hidden');
    c.innerHTML = list.map(html).join('');
}
function append_(list) { const c = document.getElementById('notifications-list'); list.forEach(n => c.insertAdjacentHTML('beforeend', html(n))); }

function colorClass(color) {
    const m = { success: 'bg-forest/10 text-forest', warning: 'bg-ochre/10 text-ochre', danger: 'bg-vermilion/10 text-vermilion', info: 'bg-sky/10 text-sky', primary: 'bg-vermilion/10 text-vermilion' };
    return m[color] || m.primary;
}
function iconSvg(type, color) {
    const icons = {
        ticket_sale: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/>',
        refund_request: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>',
        document_generated: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
        payout_request: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>'
    };
    const text = { success: 'text-forest', warning: 'text-ochre', danger: 'text-vermilion', info: 'text-sky', primary: 'text-vermilion' };
    const path = icons[type] || '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>';
    return `<svg class="h-5 w-5 ${text[color] || 'text-vermilion'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">${path}</svg>`;
}

function html(n) {
    const read = n.is_read ? 'opacity-60' : '';
    const dot = !n.is_read ? '<span class="absolute right-4 top-4 h-2.5 w-2.5 rounded-full bg-vermilion"></span>' : '';
    return `
        <div class="relative p-4 transition hover:bg-paper-2/50 lg:p-5 ${read}" data-notification-id="${n.id}">
            ${dot}
            <div class="flex items-start gap-4">
                <span class="grid h-10 w-10 flex-shrink-0 place-items-center rounded-full ${colorClass(n.color)}">${iconSvg(n.type, n.color)}</span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-bold">${escHtml(n.title)}</p>
                            ${n.message ? `<p class="mt-0.5 text-sm text-ink-soft">${escHtml(n.message)}</p>` : ''}
                            <div class="mt-2 flex items-center gap-3">
                                <span class="text-xs text-ink-soft">${escHtml(n.time_ago || '')}</span>
                                ${n.type_label ? `<span class="rounded-full ${colorClass(n.color)} px-2 py-0.5 text-xs font-bold">${escHtml(n.type_label)}</span>` : ''}
                            </div>
                        </div>
                        <div class="flex flex-shrink-0 items-center gap-2">
                            ${n.action_url ? `<a href="${escHtml(n.action_url)}" title="Vezi detalii" class="grid h-8 w-8 place-items-center rounded-lg text-ink-soft transition hover:bg-paper-2"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>` : ''}
                            ${!n.is_read ? `<button onclick="markAsRead(${n.id})" title="Marchează ca citită" class="grid h-8 w-8 place-items-center rounded-lg text-ink-soft transition hover:bg-paper-2"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></button>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
}

function updateStats(list) {
    document.getElementById('stat-total').textContent = list.length;
    document.getElementById('stat-unread').textContent = list.filter(n => !n.is_read).length;
    document.getElementById('stat-sales').textContent = list.filter(n => n.type === 'ticket_sale').length;
    document.getElementById('stat-documents').textContent = list.filter(n => n.type === 'document_generated').length;
}

function updateButtons(list) {
    const hasUnread = list.some(n => !n.is_read);
    const btn = document.getElementById('mark-all-read-btn');
    btn.classList.toggle('hidden', !hasUnread);
    btn.classList.toggle('flex', hasUnread);
}

async function markAsRead(id) {
    try {
        const r = await BileteOnlineAPI.post(`/organizer/notifications/${id}/read`);
        if (r && r.success) {
            const el = document.querySelector(`[data-notification-id="${id}"]`);
            if (el) {
                el.classList.add('opacity-60');
                const d = el.querySelector('.bg-vermilion.rounded-full'); if (d) d.remove();
                const b = el.querySelector('button[onclick^="markAsRead"]'); if (b) b.remove();
            }
            const u = document.getElementById('stat-unread');
            const c = parseInt(u.textContent) || 0; if (c > 0) u.textContent = c - 1;
        }
    } catch (e) { orgNotify('Eroare la marcarea notificării.', 'error'); }
}

async function markAllRead() {
    try {
        const r = await BileteOnlineAPI.post('/organizer/notifications/mark-all-read');
        if (r && r.success) { orgNotify('Toate notificările au fost marcate ca citite.', 'success'); loadNotifications(); }
    } catch (e) { orgNotify('Eroare la marcarea notificărilor.', 'error'); }
}

function loadMore() { if (hasMore) { currentPage++; loadNotifications(true); } }
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
