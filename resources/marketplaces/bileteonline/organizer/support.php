<?php
/**
 * bilete.online — Organizator › Suport (v3).
 * Route: /organizator/suport
 *
 * Support ticket list + multi-step "new ticket" modal (department → problem
 * type → conditional fields → details + attachments). Ported from ambilet to
 * v3 + shell, wired to BileteOnlineAPI.organizer support methods
 * (getSupportTickets / getSupportDepartments / createSupportTicket). The
 * feature is beta-gated server-side; a 403 shows the gate notice.
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Tichete suport';
$currentPage = 'support';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl font-bold leading-none">Tichete suport</h1>
                <p class="mt-1.5 text-sm text-ink-soft">Deschide un tichet către echipa de suport și urmărește statusul.</p>
            </div>
            <button onclick="openCreateModal()" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-4 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tichet nou
            </button>
        </div>

        <div id="gate-notice" class="mb-6 hidden rounded-2xl border-2 border-ochre/30 bg-ochre/10 p-6">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-6 w-6 flex-shrink-0 text-ochre" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                <div>
                    <h3 class="mb-1 font-display font-bold text-ink">Sistemul de tichete este în testare</h3>
                    <p class="text-sm text-ink-soft">Accesul nu este încă activat pentru contul tău. Pentru întrebări urgente, contactează echipa pe canalele obișnuite.</p>
                </div>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-2" id="status-filters">
            <button data-status="open" class="status-pill active rounded-full bg-vermilion px-4 py-2 text-sm font-bold text-paper transition">Active</button>
            <button data-status="" class="status-pill rounded-full bg-paper-2 px-4 py-2 text-sm font-bold text-ink-soft transition hover:text-ink">Toate</button>
            <button data-status="resolved" class="status-pill rounded-full bg-paper-2 px-4 py-2 text-sm font-bold text-ink-soft transition hover:text-ink">Rezolvate</button>
            <button data-status="closed" class="status-pill rounded-full bg-paper-2 px-4 py-2 text-sm font-bold text-ink-soft transition hover:text-ink">Închise</button>
        </div>

        <div class="overflow-hidden rounded-2xl border-2 border-ink bg-paper">
            <div id="tickets-list" class="divide-y divide-ink/10"></div>
            <div id="empty-state" class="hidden p-12 text-center">
                <span class="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-full bg-vermilion/10 text-vermilion"><svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></span>
                <h3 class="mb-1 font-display text-lg font-bold">Nu ai niciun tichet aici</h3>
                <p class="mb-4 text-ink-soft">Când ai o problemă sau o întrebare, deschide un tichet și echipa noastră îți răspunde.</p>
                <button onclick="openCreateModal()" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-4 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Deschide primul tichet
                </button>
            </div>
            <div id="loading-state" class="p-12 text-center text-ink-soft">Se încarcă…</div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<!-- Create ticket modal -->
<div id="create-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-ink/60 p-4 backdrop-blur-sm">
    <div class="flex max-h-[92vh] w-full max-w-2xl flex-col rounded-[2rem] border-2 border-ink bg-paper shadow-deep">
        <div class="flex items-center justify-between border-b-2 border-ink/10 px-6 py-4">
            <h2 class="font-display text-lg font-bold">Tichet nou</h2>
            <button onclick="closeCreateModal()" aria-label="Închide" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-paper transition hover:bg-vermilion">×</button>
        </div>
        <form id="create-form" class="flex-1 space-y-5 overflow-y-auto p-6" onsubmit="submitCreate(event)">
            <p class="font-mono text-[11px] font-semibold uppercase tracking-[.12em] text-ink-soft">Pasul 1 — Categoria problemei</p>
            <div>
                <label class="mb-2 block text-sm font-bold">Departament <span class="text-vermilion">*</span></label>
                <select id="dept-select" required onchange="onDeptChange()" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink"><option value="">Alege un departament…</option></select>
                <p id="dept-desc" class="mt-1 hidden text-xs text-ink-soft"></p>
            </div>
            <div id="problem-type-wrapper" class="hidden">
                <label class="mb-2 block text-sm font-bold">Tip problemă <span class="text-vermilion">*</span></label>
                <select id="problem-type-select" required onchange="onProblemTypeChange()" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink"><option value="">Alege tipul…</option></select>
            </div>
            <div id="conditional-fields" class="hidden space-y-4"></div>
            <div id="step2" class="hidden space-y-5 border-t-2 border-ink/10 pt-5">
                <p class="font-mono text-[11px] font-semibold uppercase tracking-[.12em] text-ink-soft">Pasul 2 — Detalii</p>
                <div>
                    <label class="mb-2 block text-sm font-bold">Subiect <span class="text-vermilion">*</span></label>
                    <input type="text" id="subject-input" required maxlength="255" placeholder="Pe scurt: ce s-a întâmplat?" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-bold">Descriere <span class="text-vermilion">*</span></label>
                    <textarea id="description-input" required rows="6" maxlength="10000" placeholder="Descrie problema în detaliu: când s-a întâmplat, ce ai făcut înainte, mesajul de eroare exact. Cu cât mai multe informații, cu atât răspundem mai repede." class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink"></textarea>
                </div>
                <div>
                    <label class="mb-2 block text-sm font-bold">Atașamente (opțional)</label>
                    <input type="file" id="attachments-input" multiple accept=".jpg,.jpeg,.png,.pdf" class="w-full cursor-pointer text-sm text-ink-soft file:mr-3 file:rounded-lg file:border-0 file:bg-vermilion/10 file:px-4 file:py-2 file:text-sm file:font-bold file:text-vermilion hover:file:bg-vermilion/20">
                    <p id="attachments-help" class="mt-1 text-xs text-ink-soft">jpg, png, pdf — maxim 3 MB pe fișier, max 5 fișiere.</p>
                    <div id="attachments-preview" class="mt-2 space-y-1"></div>
                </div>
            </div>
            <div id="form-error" class="hidden rounded-lg border-2 border-vermilion/30 bg-vermilion/10 p-3 text-sm text-vermilion"></div>
        </form>
        <div class="flex items-center justify-end gap-2 rounded-b-[2rem] border-t-2 border-ink/10 bg-paper-2 px-6 py-4">
            <button type="button" onclick="closeCreateModal()" class="rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Renunță</button>
            <button type="submit" form="create-form" id="submit-btn" disabled class="rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d disabled:cursor-not-allowed disabled:opacity-50">Trimite tichetul</button>
        </div>
    </div>
</div>

<?php
$scriptsExtra = <<<'JS'
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
const FIELD_LABELS = {
    url: { label: 'URL-ul paginii', placeholder: 'https://bilete.online/…', type: 'url' },
    invoice_series: { label: 'Seria decontului', placeholder: 'ex: AB', type: 'text' },
    invoice_number: { label: 'Număr decont', placeholder: 'ex: 12345', type: 'text' },
    module_name: { label: 'Modulul afectat', placeholder: 'ex: Vânzări, Decont, Bilete', type: 'text' },
    event_id: { label: 'Activitate', type: 'select-event' },
};

let taxonomyCache = null, eventsCache = null, currentStatus = 'open', isSubmitting = false;
let attachmentRules = { max_size_kb: 3072, allowed_mimes: ['jpg', 'png', 'pdf'], max_per_message: 5 };

document.addEventListener('DOMContentLoaded', () => {
    if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth && !BileteOnlineAuth.requireOrganizerAuth()) return;
    bindStatusFilters();
    loadTickets();
    document.addEventListener('input', updateSubmitButton);
    document.addEventListener('change', (e) => { if (e.target && e.target.id === 'attachments-input') renderAttachmentsPreview(); });
});

function bindStatusFilters() {
    document.querySelectorAll('#status-filters .status-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#status-filters .status-pill').forEach(p => {
                p.classList.remove('active', 'bg-vermilion', 'text-paper');
                p.classList.add('bg-paper-2', 'text-ink-soft');
            });
            btn.classList.remove('bg-paper-2', 'text-ink-soft');
            btn.classList.add('active', 'bg-vermilion', 'text-paper');
            currentStatus = btn.dataset.status || '';
            loadTickets();
        });
    });
}

async function loadTickets() {
    document.getElementById('loading-state').classList.remove('hidden');
    document.getElementById('empty-state').classList.add('hidden');
    document.getElementById('tickets-list').innerHTML = '';
    document.getElementById('gate-notice').classList.add('hidden');
    try {
        const params = currentStatus ? { status: currentStatus } : {};
        const res = await BileteOnlineAPI.organizer.getSupportTickets(params);
        const tickets = (res && res.data) || [];
        document.getElementById('loading-state').classList.add('hidden');
        if (!tickets.length) { document.getElementById('empty-state').classList.remove('hidden'); return; }
        renderTickets(tickets);
    } catch (err) {
        document.getElementById('loading-state').classList.add('hidden');
        if (err && err.status === 403) { document.getElementById('gate-notice').classList.remove('hidden'); return; }
        document.getElementById('empty-state').classList.remove('hidden');
        orgNotify('Nu am putut încărca tichetele. Reîncearcă în câteva secunde.', 'error');
    }
}

function renderTickets(tickets) {
    document.getElementById('tickets-list').innerHTML = tickets.map(t => {
        const status = STATUS_LABELS[t.status] || { label: t.status, class: 'bg-ink/10 text-ink-soft' };
        const dept = (t.department && t.department.name) || '—';
        const count = t.messages_count || 0;
        return `
            <a href="/organizator/suport/${t.id}" class="block px-5 py-4 transition hover:bg-paper-2/60">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs text-ink-soft">${escapeHtml(t.ticket_number || ('#' + t.id))}</span>
                            <span class="rounded-full px-2 py-0.5 text-xs font-bold ${status.class}">${status.label}</span>
                            <span class="text-xs text-ink-soft">·</span>
                            <span class="text-xs text-ink-soft">${escapeHtml(dept)}</span>
                        </div>
                        <h3 class="truncate font-bold">${escapeHtml(t.subject)}</h3>
                        <p class="mt-1 text-xs text-ink-soft">${count} mesaj${count === 1 ? '' : 'e'} · Activitate: ${escapeHtml(formatRelative(t.last_activity_at))} · Deschis pe ${escapeHtml(formatDate(t.opened_at))}</p>
                    </div>
                    <svg class="mt-1 h-5 w-5 flex-shrink-0 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </div>
            </a>`;
    }).join('');
}

function formatDate(iso) { if (!iso) return '—'; try { return new Date(iso).toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' }); } catch (e) { return iso; } }
function formatRelative(iso) {
    if (!iso) return '—';
    try {
        const diff = (new Date() - new Date(iso)) / 1000;
        if (diff < 60) return 'acum câteva secunde';
        if (diff < 3600) return `acum ${Math.floor(diff / 60)} min`;
        if (diff < 86400) return `acum ${Math.floor(diff / 3600)} h`;
        if (diff < 604800) return `acum ${Math.floor(diff / 86400)} zile`;
        return new Date(iso).toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch (e) { return iso; }
}
function escapeHtml(str) { const d = document.createElement('div'); d.textContent = str == null ? '' : str; return d.innerHTML; }

async function openCreateModal() {
    const m = document.getElementById('create-modal');
    m.classList.remove('hidden'); m.classList.add('flex');
    if (!taxonomyCache) {
        try {
            const res = await BileteOnlineAPI.organizer.getSupportDepartments();
            taxonomyCache = (res && res.data && res.data.departments) || [];
            attachmentRules = (res && res.data && res.data.attachment_rules) || attachmentRules;
            populateDepartments();
        } catch (err) {
            if (err && err.status === 403) { closeCreateModal(); document.getElementById('gate-notice').classList.remove('hidden'); return; }
            showFormError('Nu am putut încărca categoriile. Închide fereastra și încearcă din nou.');
        }
    }
    updateAttachmentsHelp();
    updateSubmitButton();
}

function closeCreateModal() {
    const m = document.getElementById('create-modal');
    m.classList.add('hidden'); m.classList.remove('flex');
    document.getElementById('create-form').reset();
    document.getElementById('conditional-fields').innerHTML = '';
    document.getElementById('conditional-fields').classList.add('hidden');
    document.getElementById('problem-type-wrapper').classList.add('hidden');
    document.getElementById('step2').classList.add('hidden');
    document.getElementById('attachments-preview').innerHTML = '';
    document.getElementById('dept-desc').classList.add('hidden');
    hideFormError();
    isSubmitting = false;
    updateSubmitButton();
}

function populateDepartments() {
    document.getElementById('dept-select').innerHTML = '<option value="">Alege un departament…</option>' + taxonomyCache.map(d => `<option value="${d.id}">${escapeHtml(d.name)}</option>`).join('');
}

function onDeptChange() {
    const sel = document.getElementById('dept-select');
    const dept = taxonomyCache.find(d => String(d.id) === sel.value);
    const ptWrapper = document.getElementById('problem-type-wrapper');
    const ptSel = document.getElementById('problem-type-select');
    const desc = document.getElementById('dept-desc');
    document.getElementById('conditional-fields').classList.add('hidden');
    document.getElementById('conditional-fields').innerHTML = '';
    document.getElementById('step2').classList.add('hidden');
    if (!dept) { ptWrapper.classList.add('hidden'); desc.classList.add('hidden'); updateSubmitButton(); return; }
    if (dept.description) { desc.textContent = dept.description; desc.classList.remove('hidden'); } else desc.classList.add('hidden');
    ptSel.innerHTML = '<option value="">Alege tipul…</option>' + (dept.problem_types || []).map(pt => `<option value="${pt.id}">${escapeHtml(pt.name)}</option>`).join('');
    ptWrapper.classList.remove('hidden');
    updateSubmitButton();
}

async function onProblemTypeChange() {
    const dept = taxonomyCache.find(d => String(d.id) === document.getElementById('dept-select').value);
    const pt = dept && dept.problem_types && dept.problem_types.find(p => String(p.id) === document.getElementById('problem-type-select').value);
    const fields = document.getElementById('conditional-fields');
    fields.innerHTML = '';
    if (!pt) { fields.classList.add('hidden'); document.getElementById('step2').classList.add('hidden'); updateSubmitButton(); return; }
    const required = pt.required_fields || [];
    if (required.length) { fields.classList.remove('hidden'); for (const f of required) fields.appendChild(await renderConditionalField(f)); }
    else fields.classList.add('hidden');
    document.getElementById('step2').classList.remove('hidden');
    updateSubmitButton();
}

async function renderConditionalField(field) {
    const wrap = document.createElement('div');
    const meta = FIELD_LABELS[field] || { label: field, type: 'text', placeholder: '' };
    const cls = 'cf-input w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink';
    if (meta.type === 'select-event') {
        if (!eventsCache) { try { const res = await BileteOnlineAPI.organizer.getEvents({ per_page: 50 }); eventsCache = (res && res.data) || res || []; } catch (e) { eventsCache = []; } }
        const rows = Array.isArray(eventsCache) ? eventsCache : (eventsCache.events || eventsCache.items || []);
        const opts = rows.map(ev => `<option value="${ev.id}">${escapeHtml(ev.name || ev.title || ('Activitate #' + ev.id))}</option>`).join('');
        wrap.innerHTML = `<label class="mb-2 block text-sm font-bold">${meta.label} <span class="text-vermilion">*</span></label><select name="meta[${field}]" required class="${cls}"><option value="">Alege o activitate…</option>${opts}</select>`;
    } else {
        wrap.innerHTML = `<label class="mb-2 block text-sm font-bold">${meta.label} <span class="text-vermilion">*</span></label><input type="${meta.type}" name="meta[${field}]" placeholder="${escapeHtml(meta.placeholder || '')}" required class="${cls}">`;
    }
    return wrap;
}

function updateAttachmentsHelp() {
    const mb = (attachmentRules.max_size_kb / 1024).toFixed(0);
    const mimes = (attachmentRules.allowed_mimes || []).join(', ');
    document.getElementById('attachments-help').textContent = `${mimes} — maxim ${mb} MB pe fișier, max ${attachmentRules.max_per_message || 5} fișiere.`;
    document.getElementById('attachments-input').setAttribute('accept', (attachmentRules.allowed_mimes || []).map(m => '.' + m).join(','));
}

function renderAttachmentsPreview() {
    const inp = document.getElementById('attachments-input');
    const prev = document.getElementById('attachments-preview');
    const max = attachmentRules.max_per_message || 5;
    const maxBytes = (attachmentRules.max_size_kb || 3072) * 1024;
    const files = Array.from(inp.files || []);
    if (files.length > max) { showFormError(`Poți atașa cel mult ${max} fișiere.`); inp.value = ''; prev.innerHTML = ''; return; }
    const big = files.find(f => f.size > maxBytes);
    if (big) { showFormError(`Fișierul "${big.name}" depășește limita de ${(maxBytes / 1024 / 1024).toFixed(0)} MB.`); inp.value = ''; prev.innerHTML = ''; return; }
    hideFormError();
    prev.innerHTML = files.map(f => `<div class="flex items-center gap-2 text-xs text-ink-soft"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg><span class="flex-1 truncate">${escapeHtml(f.name)}</span><span>${(f.size / 1024).toFixed(0)} KB</span></div>`).join('');
}

function showFormError(msg) { const el = document.getElementById('form-error'); el.textContent = msg; el.classList.remove('hidden'); }
function hideFormError() { document.getElementById('form-error').classList.add('hidden'); }

function updateSubmitButton() {
    const btn = document.getElementById('submit-btn');
    if (!btn) return;
    const ptId = (document.getElementById('problem-type-select') || {}).value;
    const subject = ((document.getElementById('subject-input') || {}).value || '').trim();
    const desc = ((document.getElementById('description-input') || {}).value || '').trim();
    const ok = Array.from(document.querySelectorAll('#conditional-fields .cf-input')).every(i => i.value.trim() !== '');
    btn.disabled = isSubmitting || !ptId || !subject || !desc || !ok;
    btn.textContent = isSubmitting ? 'Se trimite…' : 'Trimite tichetul';
}

async function submitCreate(e) {
    e.preventDefault();
    if (isSubmitting) return;
    isSubmitting = true; updateSubmitButton(); hideFormError();
    const ptId = document.getElementById('problem-type-select').value;
    const subject = document.getElementById('subject-input').value.trim();
    const description = document.getElementById('description-input').value.trim();
    const meta = {};
    document.querySelectorAll('#conditional-fields .cf-input').forEach(i => {
        const m = (i.getAttribute('name') || '').match(/^meta\[(.+)\]$/);
        if (m) meta[m[1]] = i.value.trim();
    });
    const context = { source_url: window.location.href, screen_resolution: `${screen.width}x${screen.height}`, viewport: `${window.innerWidth}x${window.innerHeight}`, user_agent: navigator.userAgent };
    const files = Array.from(document.getElementById('attachments-input').files || []);
    try {
        const res = await BileteOnlineAPI.organizer.createSupportTicket({ support_problem_type_id: ptId, subject, description, meta, context }, files);
        const ticket = res && res.data && res.data.ticket;
        orgNotify('Tichet trimis. Îți răspundem cât de curând.', 'success');
        if (ticket && ticket.id) window.location.href = `/organizator/suport/${ticket.id}`;
        else { closeCreateModal(); loadTickets(); }
    } catch (err) {
        isSubmitting = false; updateSubmitButton();
        if (err && err.errors) { const first = Object.values(err.errors)[0]; showFormError(Array.isArray(first) ? first[0] : String(first)); }
        else if (err && err.status === 403) { closeCreateModal(); document.getElementById('gate-notice').classList.remove('hidden'); }
        else showFormError((err && err.message) || 'A apărut o eroare. Reîncearcă.');
    }
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
