<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Tichete suport';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'support';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8">
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Tichete suport</h1>
                    <p class="text-sm text-muted">Deschide un tichet catre echipa de suport AmBilet si urmareste statusul.</p>
                </div>
                <button onclick="openCreateModal()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-xl text-sm font-medium hover:bg-primary/90 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Tichet nou
                </button>
            </div>

            <!-- Beta-gate notice (shown if API returns 403) -->
            <div id="gate-notice" class="hidden p-6 mb-6 bg-amber-50 border border-amber-200 rounded-2xl">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                    <div>
                        <h3 class="font-semibold text-amber-900 mb-1">Sistemul de tichete este in faza de testare</h3>
                        <p class="text-sm text-amber-800">Accesul nu este inca activat pentru contul tau. Pentru intrebari urgente, contacteaza echipa pe canalele obisnuite.</p>
                    </div>
                </div>
            </div>

            <!-- Filter pills -->
            <div class="flex flex-wrap gap-2 mb-4" id="status-filters">
                <button data-status="open" class="status-pill active px-4 py-2 bg-primary text-white rounded-full text-sm font-medium transition-colors">Active</button>
                <button data-status="" class="status-pill px-4 py-2 bg-white border border-border text-muted rounded-full text-sm font-medium hover:bg-surface transition-colors">Toate</button>
                <button data-status="resolved" class="status-pill px-4 py-2 bg-white border border-border text-muted rounded-full text-sm font-medium hover:bg-surface transition-colors">Rezolvate</button>
                <button data-status="closed" class="status-pill px-4 py-2 bg-white border border-border text-muted rounded-full text-sm font-medium hover:bg-surface transition-colors">Inchise</button>
            </div>

            <!-- Tickets list -->
            <div class="bg-white rounded-2xl border border-border overflow-hidden">
                <div id="tickets-list" class="divide-y divide-border"></div>

                <div id="empty-state" class="hidden p-12 text-center">
                    <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-secondary mb-2">Nu ai niciun tichet aici</h3>
                    <p class="text-muted mb-4">Cand ai o problema sau o intrebare, deschide un tichet si echipa noastra iti raspunde.</p>
                    <button onclick="openCreateModal()" class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-xl text-sm font-medium hover:bg-primary/90">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Deschide primul tichet
                    </button>
                </div>

                <div id="loading-state" class="p-12 text-center">
                    <div class="animate-spin w-8 h-8 border-4 border-primary border-t-transparent rounded-full mx-auto mb-4"></div>
                    <p class="text-muted">Se incarca...</p>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Ticket Modal -->
    <div id="create-modal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-sm items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[92vh] flex flex-col">
            <div class="px-6 py-4 border-b border-border flex items-center justify-between">
                <h2 class="text-lg font-bold text-secondary">Tichet nou</h2>
                <button onclick="closeCreateModal()" class="text-muted hover:text-secondary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form id="create-form" class="flex-1 overflow-y-auto p-6 space-y-5" onsubmit="submitCreate(event)">
                <!-- Step indicator -->
                <p class="text-xs text-muted uppercase tracking-wider font-semibold">Pasul 1 — Categoria problemei</p>

                <div>
                    <label class="block text-sm font-medium text-secondary mb-2">Departament <span class="text-red-500">*</span></label>
                    <select id="dept-select" required onchange="onDeptChange()" class="w-full px-4 py-2.5 border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">Alege un departament...</option>
                    </select>
                    <p id="dept-desc" class="hidden mt-1 text-xs text-muted"></p>
                </div>

                <div id="problem-type-wrapper" class="hidden">
                    <label class="block text-sm font-medium text-secondary mb-2">Tip problema <span class="text-red-500">*</span></label>
                    <select id="problem-type-select" required onchange="onProblemTypeChange()" class="w-full px-4 py-2.5 border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">Alege tipul...</option>
                    </select>
                </div>

                <!-- Conditional fields container -->
                <div id="conditional-fields" class="hidden space-y-4"></div>

                <div id="step2" class="hidden space-y-5 border-t border-border pt-5">
                    <p class="text-xs text-muted uppercase tracking-wider font-semibold">Pasul 2 — Detalii</p>

                    <div>
                        <label class="block text-sm font-medium text-secondary mb-2">Subiect <span class="text-red-500">*</span></label>
                        <input type="text" id="subject-input" required maxlength="255" placeholder="Pe scurt: ce s-a intamplat?" class="w-full px-4 py-2.5 border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-secondary mb-2">Descriere <span class="text-red-500">*</span></label>
                        <textarea id="description-input" required rows="6" maxlength="10000" placeholder="Descrie problema in detaliu. Cu cat ne dai mai multe informatii (cand s-a intamplat, ce ai facut inainte, mesajul de eroare exact), cu atat raspundem mai repede." class="w-full px-4 py-2.5 border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-secondary mb-2">Atasamente (optional)</label>
                        <input type="file" id="attachments-input" multiple accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm text-muted file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary/10 file:text-primary hover:file:bg-primary/20 cursor-pointer">
                        <p id="attachments-help" class="mt-1 text-xs text-muted">jpg, png, pdf — maxim 3 MB pe fisier, max 5 fisiere.</p>
                        <div id="attachments-preview" class="mt-2 space-y-1"></div>
                    </div>
                </div>

                <div id="form-error" class="hidden p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700"></div>
            </form>

            <div class="px-6 py-4 border-t border-border flex items-center justify-end gap-2 bg-slate-50 rounded-b-2xl">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2.5 bg-white border border-border text-muted rounded-xl text-sm font-medium hover:bg-surface">Renunta</button>
                <button type="submit" form="create-form" id="submit-btn" disabled class="px-5 py-2.5 bg-primary text-white rounded-xl text-sm font-medium hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed">Trimite tichetul</button>
            </div>
        </div>
    </div>

<script>
'use strict';

const STATUS_LABELS = {
    open: { label: 'Deschis', class: 'bg-blue-100 text-blue-700' },
    in_progress: { label: 'In lucru', class: 'bg-purple-100 text-purple-700' },
    awaiting_organizer: { label: 'Asteapta raspunsul tau', class: 'bg-amber-100 text-amber-800' },
    resolved: { label: 'Rezolvat', class: 'bg-emerald-100 text-emerald-700' },
    closed: { label: 'Inchis', class: 'bg-slate-200 text-slate-700' },
};

const FIELD_LABELS = {
    url: { label: 'URL-ul paginii', placeholder: 'https://ambilet.ro/...', type: 'url' },
    invoice_series: { label: 'Seria decontului', placeholder: 'ex: AB', type: 'text' },
    invoice_number: { label: 'Numar decont', placeholder: 'ex: 12345', type: 'text' },
    module_name: { label: 'Modulul afectat', placeholder: 'ex: Vanzari, Decont, Bilete', type: 'text' },
    event_id: { label: 'Eveniment', type: 'select-event' },
};

let taxonomyCache = null;
let eventsCache = null;
let attachmentRules = { max_size_kb: 3072, allowed_mimes: ['jpg', 'png', 'pdf'], max_per_message: 5 };
let currentStatus = 'open';
let isSubmitting = false;

document.addEventListener('DOMContentLoaded', () => {
    if (typeof AmbiletAuth === 'undefined' || !AmbiletAuth.isAuthenticated()) {
        window.location.href = '/organizator/login';
        return;
    }
    bindStatusFilters();
    loadTickets();
});

function bindStatusFilters() {
    document.querySelectorAll('#status-filters .status-pill').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#status-filters .status-pill').forEach(p => {
                p.classList.remove('active', 'bg-primary', 'text-white');
                p.classList.add('bg-white', 'border', 'border-border', 'text-muted');
            });
            btn.classList.remove('bg-white', 'border', 'border-border', 'text-muted');
            btn.classList.add('active', 'bg-primary', 'text-white');
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
        const res = await AmbiletAPI.organizer.getSupportTickets(params);
        const tickets = res?.data || [];

        document.getElementById('loading-state').classList.add('hidden');
        if (!tickets.length) {
            document.getElementById('empty-state').classList.remove('hidden');
            return;
        }
        renderTickets(tickets);
    } catch (err) {
        document.getElementById('loading-state').classList.add('hidden');
        if (err && err.status === 403) {
            document.getElementById('gate-notice').classList.remove('hidden');
            document.getElementById('empty-state').classList.add('hidden');
            return;
        }
        document.getElementById('empty-state').classList.remove('hidden');
        if (typeof AmbiletNotifications !== 'undefined') {
            AmbiletNotifications.error('Nu am putut incarca tichetele. Reincearca in cateva secunde.');
        }
    }
}

function renderTickets(tickets) {
    const html = tickets.map(t => {
        const status = STATUS_LABELS[t.status] || { label: t.status, class: 'bg-slate-100 text-slate-700' };
        const dept = t.department?.name || '—';
        const opened = formatDate(t.opened_at);
        const lastActivity = formatRelative(t.last_activity_at);
        return `
            <a href="/organizator/suport/${t.id}" class="block px-5 py-4 hover:bg-slate-50 transition-colors">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <span class="text-xs text-muted font-mono">${escapeHtml(t.ticket_number || '#' + t.id)}</span>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium ${status.class}">${status.label}</span>
                            <span class="text-xs text-muted">·</span>
                            <span class="text-xs text-muted">${escapeHtml(dept)}</span>
                        </div>
                        <h3 class="font-semibold text-secondary truncate">${escapeHtml(t.subject)}</h3>
                        <p class="text-xs text-muted mt-1">${t.messages_count || 0} mesaj${(t.messages_count === 1) ? '' : 'e'} · Activitate: ${escapeHtml(lastActivity)} · Deschis pe ${escapeHtml(opened)}</p>
                    </div>
                    <svg class="w-5 h-5 text-muted flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </div>
            </a>
        `;
    }).join('');
    document.getElementById('tickets-list').innerHTML = html;
}

function formatDate(iso) {
    if (!iso) return '—';
    try { return new Date(iso).toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' }); }
    catch (_) { return iso; }
}

function formatRelative(iso) {
    if (!iso) return '—';
    try {
        const d = new Date(iso); const now = new Date();
        const diff = (now - d) / 1000;
        if (diff < 60) return 'acum cateva secunde';
        if (diff < 3600) return `acum ${Math.floor(diff/60)} min`;
        if (diff < 86400) return `acum ${Math.floor(diff/3600)} h`;
        if (diff < 604800) return `acum ${Math.floor(diff/86400)} zile`;
        return d.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch (_) { return iso; }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

// ==================== Create modal ====================

async function openCreateModal() {
    const modal = document.getElementById('create-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    if (!taxonomyCache) {
        try {
            const res = await AmbiletAPI.organizer.getSupportDepartments();
            taxonomyCache = res?.data?.departments || [];
            attachmentRules = res?.data?.attachment_rules || attachmentRules;
            populateDepartments();
        } catch (err) {
            if (err && err.status === 403) {
                closeCreateModal();
                document.getElementById('gate-notice').classList.remove('hidden');
                return;
            }
            showFormError('Nu am putut incarca categoriile. Inchide modalul si incearca din nou.');
        }
    }
    updateAttachmentsHelp();
    updateSubmitButton();
}

function closeCreateModal() {
    const modal = document.getElementById('create-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
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
    const sel = document.getElementById('dept-select');
    sel.innerHTML = '<option value="">Alege un departament...</option>' + taxonomyCache.map(d => `<option value="${d.id}">${escapeHtml(d.name)}</option>`).join('');
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

    if (!dept) {
        ptWrapper.classList.add('hidden');
        desc.classList.add('hidden');
        updateSubmitButton();
        return;
    }
    if (dept.description) {
        desc.textContent = dept.description;
        desc.classList.remove('hidden');
    } else {
        desc.classList.add('hidden');
    }
    ptSel.innerHTML = '<option value="">Alege tipul...</option>' + (dept.problem_types || []).map(pt => `<option value="${pt.id}">${escapeHtml(pt.name)}</option>`).join('');
    ptWrapper.classList.remove('hidden');
    updateSubmitButton();
}

async function onProblemTypeChange() {
    const deptSel = document.getElementById('dept-select');
    const ptSel = document.getElementById('problem-type-select');
    const dept = taxonomyCache.find(d => String(d.id) === deptSel.value);
    const pt = dept?.problem_types?.find(p => String(p.id) === ptSel.value);

    const fields = document.getElementById('conditional-fields');
    fields.innerHTML = '';

    if (!pt) {
        fields.classList.add('hidden');
        document.getElementById('step2').classList.add('hidden');
        updateSubmitButton();
        return;
    }

    const required = pt.required_fields || [];
    if (required.length) {
        fields.classList.remove('hidden');
        for (const field of required) {
            fields.appendChild(await renderConditionalField(field));
        }
    } else {
        fields.classList.add('hidden');
    }

    document.getElementById('step2').classList.remove('hidden');
    updateSubmitButton();
}

async function renderConditionalField(field) {
    const wrap = document.createElement('div');
    const meta = FIELD_LABELS[field] || { label: field, type: 'text', placeholder: '' };

    if (meta.type === 'select-event') {
        if (!eventsCache) {
            try {
                const res = await AmbiletAPI.organizer.getEvents({ per_page: 50 });
                eventsCache = res?.data || res || [];
            } catch (_) { eventsCache = []; }
        }
        const opts = eventsCache.map(ev => `<option value="${ev.id}">${escapeHtml(ev.name || ev.title || ('Eveniment #' + ev.id))}</option>`).join('');
        wrap.innerHTML = `
            <label class="block text-sm font-medium text-secondary mb-2">${meta.label} <span class="text-red-500">*</span></label>
            <select name="meta[${field}]" required class="cf-input w-full px-4 py-2.5 border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
                <option value="">Alege un eveniment...</option>${opts}
            </select>
        `;
    } else {
        wrap.innerHTML = `
            <label class="block text-sm font-medium text-secondary mb-2">${meta.label} <span class="text-red-500">*</span></label>
            <input type="${meta.type}" name="meta[${field}]" placeholder="${escapeHtml(meta.placeholder || '')}" required class="cf-input w-full px-4 py-2.5 border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20">
        `;
    }
    return wrap;
}

function updateAttachmentsHelp() {
    const mb = (attachmentRules.max_size_kb / 1024).toFixed(0);
    const mimes = (attachmentRules.allowed_mimes || []).join(', ');
    const max = attachmentRules.max_per_message || 5;
    document.getElementById('attachments-help').textContent = `${mimes} — maxim ${mb} MB pe fisier, max ${max} fisiere.`;
    document.getElementById('attachments-input').setAttribute('accept', (attachmentRules.allowed_mimes || []).map(m => '.' + m).join(','));
}

document.addEventListener('change', (e) => {
    if (e.target && e.target.id === 'attachments-input') {
        renderAttachmentsPreview();
    }
});

function renderAttachmentsPreview() {
    const inp = document.getElementById('attachments-input');
    const prev = document.getElementById('attachments-preview');
    const max = attachmentRules.max_per_message || 5;
    const maxBytes = (attachmentRules.max_size_kb || 3072) * 1024;
    const files = Array.from(inp.files || []);
    if (files.length > max) {
        showFormError(`Poti atasa cel mult ${max} fisiere.`);
        inp.value = '';
        prev.innerHTML = '';
        return;
    }
    const tooBig = files.find(f => f.size > maxBytes);
    if (tooBig) {
        showFormError(`Fisierul "${tooBig.name}" depaseste limita de ${(maxBytes/1024/1024).toFixed(0)} MB.`);
        inp.value = '';
        prev.innerHTML = '';
        return;
    }
    hideFormError();
    prev.innerHTML = files.map(f => `
        <div class="flex items-center gap-2 text-xs text-muted">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
            <span class="flex-1 truncate">${escapeHtml(f.name)}</span>
            <span>${(f.size/1024).toFixed(0)} KB</span>
        </div>
    `).join('');
}

function showFormError(msg) {
    const el = document.getElementById('form-error');
    el.textContent = msg;
    el.classList.remove('hidden');
}

function hideFormError() {
    document.getElementById('form-error').classList.add('hidden');
}

document.addEventListener('input', updateSubmitButton);
function updateSubmitButton() {
    const btn = document.getElementById('submit-btn');
    if (!btn) return;
    const ptId = document.getElementById('problem-type-select')?.value;
    const subject = document.getElementById('subject-input')?.value?.trim();
    const desc = document.getElementById('description-input')?.value?.trim();
    const conditionalsValid = Array.from(document.querySelectorAll('#conditional-fields .cf-input')).every(i => i.value.trim() !== '');
    btn.disabled = isSubmitting || !ptId || !subject || !desc || !conditionalsValid;
    btn.textContent = isSubmitting ? 'Se trimite...' : 'Trimite tichetul';
}

async function submitCreate(e) {
    e.preventDefault();
    if (isSubmitting) return;
    isSubmitting = true;
    updateSubmitButton();
    hideFormError();

    const ptId = document.getElementById('problem-type-select').value;
    const subject = document.getElementById('subject-input').value.trim();
    const description = document.getElementById('description-input').value.trim();

    const meta = {};
    document.querySelectorAll('#conditional-fields .cf-input').forEach(i => {
        const name = i.getAttribute('name'); // meta[field]
        const m = name && name.match(/^meta\[(.+)\]$/);
        if (m) meta[m[1]] = i.value.trim();
    });

    const context = {
        source_url: window.location.href,
        screen_resolution: `${screen.width}x${screen.height}`,
        viewport: `${window.innerWidth}x${window.innerHeight}`,
    };

    const files = Array.from(document.getElementById('attachments-input').files || []);

    try {
        const res = await AmbiletAPI.organizer.createSupportTicket({
            support_problem_type_id: ptId, subject, description, meta, context
        }, files);
        const ticket = res?.data?.ticket;
        if (typeof AmbiletNotifications !== 'undefined') {
            AmbiletNotifications.success('Tichet trimis. Iti raspundem cat de curand.');
        }
        if (ticket?.id) {
            window.location.href = `/organizator/suport/${ticket.id}`;
        } else {
            closeCreateModal();
            loadTickets();
        }
    } catch (err) {
        isSubmitting = false;
        updateSubmitButton();
        if (err && err.errors) {
            const first = Object.values(err.errors)[0];
            showFormError(Array.isArray(first) ? first[0] : String(first));
        } else if (err && err.status === 403) {
            closeCreateModal();
            document.getElementById('gate-notice').classList.remove('hidden');
        } else {
            showFormError(err?.message || 'A aparut o eroare. Reincearca.');
        }
    }
}
</script>

<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
