<?php
/**
 * bilete.online — Organizator › Suport › Tichet (v3).
 * Route: /organizator/suport/{id}
 *
 * Single support ticket: header + meta, conversation thread, reply form with
 * attachments, close/reopen actions. Ported from ambilet to v3 + shell, wired
 * to BileteOnlineAPI.organizer support methods.
 */
require_once dirname(__DIR__) . '/includes/config.php';
$ticketId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($ticketId <= 0) { header('Location: /organizator/suport'); exit; }

$pageTitle   = 'Tichet suport';
$currentPage = 'support';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <a href="/organizator/suport" class="mb-4 inline-flex items-center gap-2 text-sm font-bold text-ink-soft transition hover:text-ink">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Înapoi la tichete
        </a>

        <div id="loading-state" class="rounded-2xl border-2 border-ink bg-paper p-12 text-center text-ink-soft">Se încarcă tichetul…</div>

        <div id="error-state" class="hidden rounded-2xl border-2 border-ink bg-paper p-12 text-center">
            <span class="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-full bg-vermilion/10 text-vermilion"><svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg></span>
            <h2 id="error-title" class="mb-1 font-display text-lg font-bold">Tichet inexistent</h2>
            <p id="error-desc" class="text-ink-soft">Verifică linkul sau întoarce-te la lista de tichete.</p>
        </div>

        <div id="ticket-detail" class="hidden">
            <div class="mb-4 rounded-2xl border-2 border-ink bg-paper p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span id="t-number" class="font-mono text-xs text-ink-soft"></span>
                            <span id="t-status-badge" class="rounded-full px-2 py-0.5 text-xs font-bold"></span>
                            <span class="text-xs text-ink-soft">·</span>
                            <span id="t-dept" class="text-xs text-ink-soft"></span>
                            <span id="t-pt-sep" class="hidden text-xs text-ink-soft">·</span>
                            <span id="t-pt" class="text-xs text-ink-soft"></span>
                        </div>
                        <h1 id="t-subject" class="font-display text-xl font-bold"></h1>
                        <p class="mt-1 text-xs text-ink-soft">Deschis pe <span id="t-opened"></span></p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="close-btn" onclick="closeTicket()" class="hidden rounded-full border-2 border-ink px-4 py-2 text-sm font-bold transition hover:bg-ink hover:text-paper">Marchează ca rezolvat</button>
                        <button id="reopen-btn" onclick="reopenTicket()" class="hidden rounded-full bg-vermilion px-4 py-2 text-sm font-bold text-paper transition hover:bg-vermilion-d">Redeschide tichetul</button>
                    </div>
                </div>
                <div id="t-meta" class="mt-4 hidden grid-cols-1 gap-3 border-t-2 border-ink/10 pt-4 text-sm md:grid-cols-3"></div>
            </div>

            <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
                <div class="rounded-2xl border-2 border-ink bg-paper p-5 lg:col-span-2">
                    <h2 class="mb-4 font-mono text-[11px] font-semibold uppercase tracking-[.12em] text-ink-soft">Conversație</h2>
                    <div id="thread" class="space-y-4"></div>
                </div>
                <div class="lg:sticky lg:top-4">
                    <div id="reply-card" class="rounded-2xl border-2 border-ink bg-paper p-5">
                        <h2 class="mb-3 font-mono text-[11px] font-semibold uppercase tracking-[.12em] text-ink-soft">Trimite un răspuns</h2>
                        <form id="reply-form" onsubmit="submitReply(event)" class="space-y-3">
                            <textarea id="reply-body" required rows="6" maxlength="10000" placeholder="Scrie răspunsul tău…" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink"></textarea>
                            <div>
                                <label class="mb-2 block text-xs font-bold text-ink-soft">Atașamente (opțional)</label>
                                <input type="file" id="reply-attachments" multiple accept=".jpg,.jpeg,.png,.pdf" class="w-full cursor-pointer text-sm text-ink-soft file:mr-3 file:rounded-lg file:border-0 file:bg-vermilion/10 file:px-4 file:py-2 file:text-sm file:font-bold file:text-vermilion hover:file:bg-vermilion/20">
                                <div id="reply-attachments-preview" class="mt-2 space-y-1"></div>
                            </div>
                            <div id="reply-error" class="hidden rounded-lg border-2 border-vermilion/30 bg-vermilion/10 p-3 text-sm text-vermilion"></div>
                            <div class="flex justify-end">
                                <button type="submit" id="reply-btn" class="rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d disabled:cursor-not-allowed disabled:opacity-50">Trimite răspunsul</button>
                            </div>
                        </form>
                    </div>
                    <div id="closed-banner" class="hidden rounded-2xl border-2 border-ink/15 bg-paper-2 p-5 text-center">
                        <p class="text-sm text-ink-soft">Tichetul este închis. Dacă problema reapare, redeschide-l din butonul de sus.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<?php
$scriptsExtra = "<script>\nconst TICKET_ID = " . json_encode($ticketId) . ";\n</script>\n";
$scriptsExtra .= <<<'JS'
<script>
'use strict';
function orgNotify(msg, type) {
    try { if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) { BileteOnlineNotifications[type || 'info'](msg); return; } } catch (e) {}
    if (type === 'error') alert(msg);
}

const STATUS_LABELS = {
    open: { label: 'Deschis', class: 'bg-sky/15 text-sky' },
    in_progress: { label: 'În lucru', class: 'bg-vermilion/15 text-vermilion' },
    awaiting_organizer: { label: 'Așteaptă răspunsul tău', class: 'bg-ochre/15 text-ochre' },
    resolved: { label: 'Rezolvat', class: 'bg-forest/15 text-forest' },
    closed: { label: 'Închis', class: 'bg-ink/10 text-ink-soft' },
};
const META_LABELS = { url: 'URL pagină', invoice_series: 'Seria decont', invoice_number: 'Număr decont', event_id: 'Activitate', module_name: 'Modul' };

let currentTicket = null;
let attachmentRules = { max_size_kb: 3072, allowed_mimes: ['jpg', 'png', 'pdf'], max_per_message: 5 };

document.addEventListener('DOMContentLoaded', () => {
    if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth && !BileteOnlineAuth.requireOrganizerAuth()) return;
    loadTicket();
    BileteOnlineAPI.organizer.getSupportDepartments().then(r => { if (r && r.data && r.data.attachment_rules) attachmentRules = r.data.attachment_rules; }).catch(() => {});
    document.addEventListener('change', (e) => { if (e.target && e.target.id === 'reply-attachments') renderReplyAttachmentsPreview(); });
});

async function loadTicket() {
    try {
        const res = await BileteOnlineAPI.organizer.getSupportTicket(TICKET_ID);
        currentTicket = res && res.data && res.data.ticket;
        const messages = (res && res.data && res.data.messages) || [];
        if (!currentTicket) throw new Error('not found');
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('ticket-detail').classList.remove('hidden');
        renderTicket(currentTicket, messages);
    } catch (err) {
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('error-state').classList.remove('hidden');
        if (err && err.status === 403) {
            document.getElementById('error-title').textContent = 'Acces restricționat';
            document.getElementById('error-desc').textContent = 'Sistemul de tichete este în testare și nu este încă activat pentru contul tău.';
        } else if (err && err.status === 404) {
            document.getElementById('error-title').textContent = 'Tichet inexistent';
            document.getElementById('error-desc').textContent = 'Tichetul nu există sau nu îți aparține.';
        }
    }
}

function renderTicket(t, messages) {
    document.title = `${t.ticket_number || ('#' + t.id)} — ${t.subject}`;
    document.getElementById('t-number').textContent = t.ticket_number || ('#' + t.id);
    document.getElementById('t-subject').textContent = t.subject;
    document.getElementById('t-dept').textContent = (t.department && t.department.name) || '—';
    if (t.problem_type && t.problem_type.name) { document.getElementById('t-pt').textContent = t.problem_type.name; document.getElementById('t-pt-sep').classList.remove('hidden'); }
    document.getElementById('t-opened').textContent = formatDateTime(t.opened_at);
    const status = STATUS_LABELS[t.status] || { label: t.status, class: 'bg-ink/10 text-ink-soft' };
    const badge = document.getElementById('t-status-badge');
    badge.textContent = status.label;
    badge.className = 'rounded-full px-2 py-0.5 text-xs font-bold ' + status.class;
    renderMeta(t.meta || {});
    const closeBtn = document.getElementById('close-btn'), reopenBtn = document.getElementById('reopen-btn');
    const replyCard = document.getElementById('reply-card'), closedBanner = document.getElementById('closed-banner');
    if (t.is_closed) { closeBtn.classList.add('hidden'); reopenBtn.classList.remove('hidden'); replyCard.classList.add('hidden'); closedBanner.classList.remove('hidden'); }
    else { closeBtn.classList.remove('hidden'); reopenBtn.classList.add('hidden'); replyCard.classList.remove('hidden'); closedBanner.classList.add('hidden'); }
    renderThread(messages);
}

function renderMeta(meta) {
    const wrap = document.getElementById('t-meta');
    const entries = Object.entries(meta).filter(([k, v]) => v !== null && v !== '' && META_LABELS[k]);
    if (!entries.length) { wrap.classList.add('hidden'); return; }
    wrap.classList.remove('hidden'); wrap.classList.add('grid');
    wrap.innerHTML = entries.map(([k, v]) => {
        let valueHtml;
        if (k === 'url' && /^https?:\/\//.test(String(v))) valueHtml = `<a href="${escapeAttr(v)}" target="_blank" rel="noopener" class="inline-block max-w-full truncate font-bold text-vermilion underline">${escapeHtml(v)}</a>`;
        else if (k === 'event_id') valueHtml = `<a href="/organizator/events?id=${encodeURIComponent(v)}" class="font-bold text-vermilion underline">Activitate #${escapeHtml(v)}</a>`;
        else valueHtml = `<span class="font-bold">${escapeHtml(v)}</span>`;
        return `<div><p class="mb-0.5 text-xs text-ink-soft">${escapeHtml(META_LABELS[k])}</p>${valueHtml}</div>`;
    }).join('');
}

function renderThread(messages) {
    const thread = document.getElementById('thread');
    if (!messages.length) { thread.innerHTML = '<p class="py-4 text-center text-sm text-ink-soft">Încă nu a fost trimis niciun mesaj.</p>'; return; }
    thread.innerHTML = messages.map(m => {
        if (m.event_type) {
            const author = m.author_name || (m.author_type === 'staff' ? 'Echipa bilete.online' : 'Tu');
            return `<div class="my-1 flex items-center gap-3 py-1"><div class="h-px flex-1 bg-ink/10"></div><div class="flex items-center gap-2 rounded-full bg-paper-2 px-3 py-1 text-xs text-ink-soft"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span><strong class="text-ink">${escapeHtml(m.body)}</strong> <span class="opacity-70">de ${escapeHtml(author)} · ${formatDateTime(m.created_at)}</span></span></div><div class="h-px flex-1 bg-ink/10"></div></div>`;
        }
        const fromOpener = m.author_type === 'organizer' || m.author_type === 'customer';
        const align = fromOpener ? 'justify-end' : 'justify-start';
        const bubble = fromOpener ? 'bg-vermilion text-paper' : 'bg-paper-2 text-ink';
        const author = m.author_name || (fromOpener ? 'Tu' : 'Echipa bilete.online');
        const initials = (author || '?').trim().charAt(0).toUpperCase();
        const avatar = `<div class="grid h-9 w-9 flex-shrink-0 place-items-center rounded-full text-xs font-bold ${fromOpener ? 'bg-vermilion/20 text-vermilion' : 'bg-ink/15 text-ink'}">${escapeHtml(initials)}</div>`;
        const attachments = (m.attachments || []).map(a => `<a href="${escapeAttr(a.url)}" target="_blank" rel="noopener" class="mt-1 inline-flex items-center gap-2 rounded-lg bg-paper/20 px-3 py-1.5 text-xs transition hover:bg-paper/30"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg><span>${escapeHtml(a.original_name || 'Fișier')}</span></a>`).join('');
        return `<div class="flex ${align} gap-3 ${fromOpener ? 'flex-row-reverse' : ''}">${avatar}<div class="max-w-[80%]"><div class="${bubble} break-words rounded-2xl px-4 py-2.5"><div class="mb-1 text-xs opacity-80">${escapeHtml(author)}</div><div class="whitespace-pre-wrap text-sm">${escapeHtml(m.body)}</div>${attachments}</div><p class="mt-1 text-[10px] text-ink-soft ${fromOpener ? 'text-right' : ''}">${formatDateTime(m.created_at)}</p></div></div>`;
    }).join('');
}

function formatDateTime(iso) { if (!iso) return '—'; try { return new Date(iso).toLocaleString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }); } catch (e) { return iso; } }
function escapeHtml(str) { const d = document.createElement('div'); d.textContent = str == null ? '' : str; return d.innerHTML; }
function escapeAttr(str) { return String(str == null ? '' : str).replace(/"/g, '&quot;').replace(/</g, '&lt;'); }

async function closeTicket() {
    if (!confirm('Marchezi tichetul ca rezolvat? Vei putea să-l redeschizi dacă problema reapare.')) return;
    try { await BileteOnlineAPI.organizer.closeSupportTicket(TICKET_ID); orgNotify('Tichet marcat ca rezolvat.', 'success'); loadTicket(); }
    catch (err) { orgNotify((err && err.message) || 'Nu am putut închide tichetul.', 'error'); }
}
async function reopenTicket() {
    try { await BileteOnlineAPI.organizer.reopenSupportTicket(TICKET_ID); orgNotify('Tichet redeschis.', 'success'); loadTicket(); }
    catch (err) { orgNotify((err && err.message) || 'Nu am putut redeschide tichetul.', 'error'); }
}

function renderReplyAttachmentsPreview() {
    const inp = document.getElementById('reply-attachments');
    const prev = document.getElementById('reply-attachments-preview');
    const errEl = document.getElementById('reply-error');
    const max = attachmentRules.max_per_message || 5;
    const maxBytes = (attachmentRules.max_size_kb || 3072) * 1024;
    const files = Array.from(inp.files || []);
    if (files.length > max) { errEl.textContent = `Poți atașa cel mult ${max} fișiere.`; errEl.classList.remove('hidden'); inp.value = ''; prev.innerHTML = ''; return; }
    const big = files.find(f => f.size > maxBytes);
    if (big) { errEl.textContent = `Fișierul "${big.name}" depășește limita de ${(maxBytes / 1024 / 1024).toFixed(0)} MB.`; errEl.classList.remove('hidden'); inp.value = ''; prev.innerHTML = ''; return; }
    errEl.classList.add('hidden');
    prev.innerHTML = files.map(f => `<div class="flex items-center gap-2 text-xs text-ink-soft"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg><span class="flex-1 truncate">${escapeHtml(f.name)}</span><span>${(f.size / 1024).toFixed(0)} KB</span></div>`).join('');
}

async function submitReply(e) {
    e.preventDefault();
    const btn = document.getElementById('reply-btn');
    const body = document.getElementById('reply-body').value.trim();
    if (!body) return;
    const files = Array.from(document.getElementById('reply-attachments').files || []);
    const errEl = document.getElementById('reply-error');
    errEl.classList.add('hidden');
    btn.disabled = true; btn.textContent = 'Se trimite…';
    try {
        await BileteOnlineAPI.organizer.replySupportTicket(TICKET_ID, body, files);
        document.getElementById('reply-form').reset();
        document.getElementById('reply-attachments-preview').innerHTML = '';
        loadTicket();
        orgNotify('Mesaj trimis.', 'success');
    } catch (err) {
        if (err && err.errors) { const first = Object.values(err.errors)[0]; errEl.textContent = Array.isArray(first) ? first[0] : String(first); }
        else errEl.textContent = (err && err.message) || 'Nu am putut trimite mesajul.';
        errEl.classList.remove('hidden');
    } finally { btn.disabled = false; btn.textContent = 'Trimite răspunsul'; }
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
