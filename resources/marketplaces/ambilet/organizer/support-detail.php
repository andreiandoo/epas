<?php
require_once dirname(__DIR__) . '/includes/config.php';

$ticketId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($ticketId <= 0) {
    header('Location: /organizator/suport');
    exit;
}

$pageTitle = 'Tichet suport';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'support';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8">
            <!-- Back link -->
            <a href="/organizator/suport" class="inline-flex items-center gap-2 text-sm text-muted hover:text-secondary mb-4">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Inapoi la tichete
            </a>

            <!-- Loading -->
            <div id="loading-state" class="bg-white rounded-2xl border border-border p-12 text-center">
                <div class="animate-spin w-8 h-8 border-4 border-primary border-t-transparent rounded-full mx-auto mb-4"></div>
                <p class="text-muted">Se incarca tichetul...</p>
            </div>

            <!-- Error -->
            <div id="error-state" class="hidden bg-white rounded-2xl border border-border p-12 text-center">
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                </div>
                <h2 class="text-lg font-bold text-secondary mb-2" id="error-title">Tichet inexistent</h2>
                <p class="text-muted" id="error-desc">Verifica linkul sau intoarce-te la lista de tichete.</p>
            </div>

            <!-- Detail -->
            <div id="ticket-detail" class="hidden">
                <!-- Header card -->
                <div class="bg-white rounded-2xl border border-border p-5 mb-4">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-2 flex-wrap">
                                <span class="text-xs text-muted font-mono" id="t-number"></span>
                                <span id="t-status-badge" class="px-2 py-0.5 rounded-full text-xs font-medium"></span>
                                <span class="text-xs text-muted">·</span>
                                <span class="text-xs text-muted" id="t-dept"></span>
                                <span class="text-xs text-muted hidden" id="t-pt-sep">·</span>
                                <span class="text-xs text-muted" id="t-pt"></span>
                            </div>
                            <h1 class="text-xl font-bold text-secondary" id="t-subject"></h1>
                            <p class="text-xs text-muted mt-1">Deschis pe <span id="t-opened"></span></p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button id="close-btn" onclick="closeTicket()" class="hidden px-4 py-2 bg-white border border-border text-muted rounded-xl text-sm font-medium hover:bg-surface">
                                Marcheaza ca rezolvat
                            </button>
                            <button id="reopen-btn" onclick="reopenTicket()" class="hidden px-4 py-2 bg-primary text-white rounded-xl text-sm font-medium hover:bg-primary/90">
                                Redeschide tichetul
                            </button>
                        </div>
                    </div>

                    <!-- Meta fields (URL, decont, eveniment) -->
                    <div id="t-meta" class="hidden mt-4 pt-4 border-t border-border grid grid-cols-1 md:grid-cols-3 gap-3 text-sm"></div>
                </div>

                <!-- Conversation + Reply layout: side-by-side on desktop, stacked on mobile -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
                    <!-- Conversation thread (left, 2/3 on desktop) -->
                    <div class="lg:col-span-2 bg-white rounded-2xl border border-border p-5">
                        <h2 class="text-sm font-semibold text-muted uppercase tracking-wider mb-4">Conversatie</h2>
                        <div id="thread" class="space-y-4"></div>
                    </div>

                    <!-- Reply form (right, 1/3 on desktop, sticky) -->
                    <div class="lg:col-span-1 lg:sticky lg:top-4">
                        <div id="reply-card" class="bg-white rounded-2xl border border-border p-5">
                            <h2 class="text-sm font-semibold text-muted uppercase tracking-wider mb-3">Trimite un raspuns</h2>
                            <form id="reply-form" onsubmit="submitReply(event)" class="space-y-3">
                                <textarea id="reply-body" required rows="6" maxlength="10000" placeholder="Scrie raspunsul tau..." class="w-full px-4 py-2.5 border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"></textarea>

                                <div>
                                    <label class="block text-xs font-medium text-muted mb-2">Atasamente (optional)</label>
                                    <input type="file" id="reply-attachments" multiple accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm text-muted file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer">
                                    <div id="reply-attachments-preview" class="mt-2 space-y-1"></div>
                                </div>

                                <div id="reply-error" class="hidden p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>

                                <div class="flex justify-end">
                                    <button type="submit" id="reply-btn" class="px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-medium hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed">Trimite raspunsul</button>
                                </div>
                            </form>
                        </div>

                        <!-- Closed banner takes the same column when ticket is closed -->
                        <div id="closed-banner" class="hidden bg-slate-50 border border-border rounded-2xl p-5 text-center">
                            <p class="text-sm text-muted">Tichetul este inchis. Daca problema reapare, redeschide-l din butonul de sus.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

<script>
'use strict';

const TICKET_ID = <?= json_encode($ticketId) ?>;

const STATUS_LABELS = {
    open: { label: 'Deschis', class: 'bg-blue-100 text-blue-700' },
    in_progress: { label: 'In lucru', class: 'bg-purple-100 text-purple-700' },
    awaiting_organizer: { label: 'Asteapta raspunsul tau', class: 'bg-amber-100 text-amber-800' },
    resolved: { label: 'Rezolvat', class: 'bg-emerald-100 text-emerald-700' },
    closed: { label: 'Inchis', class: 'bg-slate-200 text-slate-700' },
};

const META_LABELS = {
    url: 'URL pagina',
    invoice_series: 'Seria decont',
    invoice_number: 'Numar decont',
    event_id: 'Eveniment',
    module_name: 'Modul',
};

let currentTicket = null;
let attachmentRules = { max_size_kb: 3072, allowed_mimes: ['jpg', 'png', 'pdf'], max_per_message: 5 };

document.addEventListener('DOMContentLoaded', () => {
    if (typeof AmbiletAuth === 'undefined' || !AmbiletAuth.isAuthenticated()) {
        window.location.href = '/organizator/login';
        return;
    }
    loadTicket();
    // Load attachment rules from departments endpoint (best-effort, non-blocking).
    AmbiletAPI.organizer.getSupportDepartments().then(r => {
        if (r?.data?.attachment_rules) attachmentRules = r.data.attachment_rules;
    }).catch(() => {});
});

async function loadTicket() {
    try {
        const res = await AmbiletAPI.organizer.getSupportTicket(TICKET_ID);
        currentTicket = res?.data?.ticket;
        const messages = res?.data?.messages || [];
        if (!currentTicket) throw new Error('not found');
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('ticket-detail').classList.remove('hidden');
        renderTicket(currentTicket, messages);
    } catch (err) {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('error-state').classList.remove('hidden');
        if (err && err.status === 403) {
            document.getElementById('error-title').textContent = 'Acces restrictionat';
            document.getElementById('error-desc').textContent = 'Sistemul de tichete este in faza de testare si nu este inca activat pentru contul tau.';
        } else if (err && err.status === 404) {
            document.getElementById('error-title').textContent = 'Tichet inexistent';
            document.getElementById('error-desc').textContent = 'Tichetul nu exista sau nu iti apartine.';
        }
    }
}

function renderTicket(t, messages) {
    document.title = `${t.ticket_number || ('#' + t.id)} — ${t.subject}`;
    document.getElementById('t-number').textContent = t.ticket_number || ('#' + t.id);
    document.getElementById('t-subject').textContent = t.subject;
    document.getElementById('t-dept').textContent = t.department?.name || '—';
    if (t.problem_type?.name) {
        document.getElementById('t-pt').textContent = t.problem_type.name;
        document.getElementById('t-pt-sep').classList.remove('hidden');
    }
    document.getElementById('t-opened').textContent = formatDateTime(t.opened_at);

    const status = STATUS_LABELS[t.status] || { label: t.status, class: 'bg-slate-100 text-slate-700' };
    const badge = document.getElementById('t-status-badge');
    badge.textContent = status.label;
    badge.className = 'px-2 py-0.5 rounded-full text-xs font-medium ' + status.class;

    renderMeta(t.meta || {});

    // Action buttons
    const closeBtn = document.getElementById('close-btn');
    const reopenBtn = document.getElementById('reopen-btn');
    const replyCard = document.getElementById('reply-card');
    const closedBanner = document.getElementById('closed-banner');

    if (t.is_closed) {
        closeBtn.classList.add('hidden');
        reopenBtn.classList.remove('hidden');
        replyCard.classList.add('hidden');
        closedBanner.classList.remove('hidden');
    } else {
        closeBtn.classList.remove('hidden');
        reopenBtn.classList.add('hidden');
        replyCard.classList.remove('hidden');
        closedBanner.classList.add('hidden');
    }

    renderThread(messages);
}

function renderMeta(meta) {
    const wrap = document.getElementById('t-meta');
    const entries = Object.entries(meta).filter(([k, v]) => v !== null && v !== '' && META_LABELS[k]);
    if (!entries.length) { wrap.classList.add('hidden'); return; }
    wrap.classList.remove('hidden');
    wrap.innerHTML = entries.map(([k, v]) => {
        const label = META_LABELS[k];
        let valueHtml;
        if (k === 'url' && /^https?:\/\//.test(String(v))) {
            valueHtml = `<a href="${escapeAttr(v)}" target="_blank" rel="noopener" class="text-primary hover:underline truncate inline-block max-w-full">${escapeHtml(v)}</a>`;
        } else if (k === 'event_id') {
            valueHtml = `<a href="/organizator/event/${encodeURIComponent(v)}" class="text-primary hover:underline">Eveniment #${escapeHtml(v)}</a>`;
        } else {
            valueHtml = `<span class="text-secondary">${escapeHtml(v)}</span>`;
        }
        return `<div><p class="text-xs text-muted mb-0.5">${escapeHtml(label)}</p>${valueHtml}</div>`;
    }).join('');
}

function renderThread(messages) {
    const thread = document.getElementById('thread');
    if (!messages.length) {
        thread.innerHTML = '<p class="text-sm text-muted py-4 text-center">Inca nu a fost trimis niciun mesaj.</p>';
        return;
    }
    thread.innerHTML = messages.map(m => {
        // System events (close/reopen/resolve etc.) render as a slim
        // timeline line, not a chat bubble — both sides need to see who
        // closed/reopened the ticket and when.
        if (m.event_type) {
            const author = m.author_name || (m.author_type === 'staff' ? 'Echipa AmBilet' : 'Tu');
            return `
                <div class="flex items-center gap-3 py-1 my-1">
                    <div class="flex-1 h-px bg-border"></div>
                    <div class="flex items-center gap-2 px-3 py-1 text-xs text-muted bg-slate-100 rounded-full">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span><strong class="text-secondary">${escapeHtml(m.body)}</strong> <span class="opacity-70">de ${escapeHtml(author)} · ${formatDateTime(m.created_at)}</span></span>
                    </div>
                    <div class="flex-1 h-px bg-border"></div>
                </div>
            `;
        }

        const fromOpener = m.author_type === 'organizer' || m.author_type === 'customer';
        const align = fromOpener ? 'justify-end' : 'justify-start';
        const bubble = fromOpener
            ? 'bg-primary text-white'
            : 'bg-slate-100 text-secondary';
        const author = m.author_name || (fromOpener ? 'Tu' : 'Echipa AmBilet');
        const initials = (author || '?').trim().charAt(0).toUpperCase();
        const avatar = `<div class="w-9 h-9 rounded-full ${fromOpener ? 'bg-primary/20 text-primary' : 'bg-slate-300 text-slate-700'} flex items-center justify-center text-xs font-bold flex-shrink-0">${escapeHtml(initials)}</div>`;
        const attachments = (m.attachments || []).map(a => `
            <a href="${escapeAttr(a.url)}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-3 py-1.5 bg-white/20 rounded-lg text-xs hover:bg-white/30 transition-colors mt-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                <span>${escapeHtml(a.original_name || 'Fisier')}</span>
            </a>
        `).join('');
        return `
            <div class="flex ${align} gap-3 ${fromOpener ? 'flex-row-reverse' : ''}">
                ${avatar}
                <div class="max-w-[80%]">
                    <div class="${bubble} rounded-2xl px-4 py-2.5 break-words">
                        <div class="text-xs opacity-80 mb-1">${escapeHtml(author)}</div>
                        <div class="text-sm whitespace-pre-wrap">${escapeHtml(m.body)}</div>
                        ${attachments}
                    </div>
                    <p class="text-[10px] text-muted mt-1 ${fromOpener ? 'text-right' : ''}">${formatDateTime(m.created_at)}</p>
                </div>
            </div>
        `;
    }).join('');
}

function formatDateTime(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch (_) { return iso; }
}

function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
}

function escapeAttr(str) {
    return String(str ?? '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

// ==================== Actions ====================

async function closeTicket() {
    if (!confirm('Marchezi tichetul ca rezolvat? Vei putea sa-l redeschizi daca problema reapare.')) return;
    try {
        await AmbiletAPI.organizer.closeSupportTicket(TICKET_ID);
        if (typeof AmbiletNotifications !== 'undefined') AmbiletNotifications.success('Tichet marcat ca rezolvat.');
        loadTicket();
    } catch (err) {
        if (typeof AmbiletNotifications !== 'undefined') AmbiletNotifications.error(err?.message || 'Nu am putut inchide tichetul.');
    }
}

async function reopenTicket() {
    try {
        await AmbiletAPI.organizer.reopenSupportTicket(TICKET_ID);
        if (typeof AmbiletNotifications !== 'undefined') AmbiletNotifications.success('Tichet redeschis.');
        loadTicket();
    } catch (err) {
        if (typeof AmbiletNotifications !== 'undefined') AmbiletNotifications.error(err?.message || 'Nu am putut redeschide tichetul.');
    }
}

document.addEventListener('change', (e) => {
    if (e.target && e.target.id === 'reply-attachments') {
        renderReplyAttachmentsPreview();
    }
});

function renderReplyAttachmentsPreview() {
    const inp = document.getElementById('reply-attachments');
    const prev = document.getElementById('reply-attachments-preview');
    const max = attachmentRules.max_per_message || 5;
    const maxBytes = (attachmentRules.max_size_kb || 3072) * 1024;
    const files = Array.from(inp.files || []);
    const errEl = document.getElementById('reply-error');
    if (files.length > max) {
        errEl.textContent = `Poti atasa cel mult ${max} fisiere.`;
        errEl.classList.remove('hidden');
        inp.value = '';
        prev.innerHTML = '';
        return;
    }
    const tooBig = files.find(f => f.size > maxBytes);
    if (tooBig) {
        errEl.textContent = `Fisierul "${tooBig.name}" depaseste limita de ${(maxBytes/1024/1024).toFixed(0)} MB.`;
        errEl.classList.remove('hidden');
        inp.value = '';
        prev.innerHTML = '';
        return;
    }
    errEl.classList.add('hidden');
    prev.innerHTML = files.map(f => `
        <div class="flex items-center gap-2 text-xs text-muted">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
            <span class="flex-1 truncate">${escapeHtml(f.name)}</span>
            <span>${(f.size/1024).toFixed(0)} KB</span>
        </div>
    `).join('');
}

async function submitReply(e) {
    e.preventDefault();
    const btn = document.getElementById('reply-btn');
    const body = document.getElementById('reply-body').value.trim();
    if (!body) return;
    const files = Array.from(document.getElementById('reply-attachments').files || []);
    const errEl = document.getElementById('reply-error');
    errEl.classList.add('hidden');
    btn.disabled = true;
    btn.textContent = 'Se trimite...';
    try {
        await AmbiletAPI.organizer.replySupportTicket(TICKET_ID, body, files);
        document.getElementById('reply-form').reset();
        document.getElementById('reply-attachments-preview').innerHTML = '';
        loadTicket();
        if (typeof AmbiletNotifications !== 'undefined') AmbiletNotifications.success('Mesaj trimis.');
    } catch (err) {
        if (err && err.errors) {
            const first = Object.values(err.errors)[0];
            errEl.textContent = Array.isArray(first) ? first[0] : String(first);
        } else {
            errEl.textContent = err?.message || 'Nu am putut trimite mesajul.';
        }
        errEl.classList.remove('hidden');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Trimite raspunsul';
    }
}
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
