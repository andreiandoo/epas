<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Invitatii';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'invitatii';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

<div class="flex-1 flex flex-col min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-secondary">Invitații</h1>
                <p class="text-sm text-muted">Generează invitații în format PDF pentru evenimentul ales</p>
            </div>
            <a href="/organizator/events" class="btn btn-sm btn-ghost">&larr; Înapoi la evenimente</a>
        </div>

        <div id="event-header" class="bg-white rounded-2xl border border-border p-6 mb-6 hidden">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-rose-50 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h2 id="event-name" class="text-lg font-bold text-secondary"></h2>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted mt-1">
                        <span id="event-date"></span>
                        <span id="event-venue"></span>
                    </div>
                </div>
            </div>
        </div>

        <div id="event-missing" class="bg-amber-50 border border-amber-200 rounded-2xl p-6 mb-6 hidden">
            <p class="text-sm text-amber-800">Nu am găsit evenimentul selectat. <a href="/organizator/events" class="underline font-semibold">Alege un eveniment</a> din lista ta.</p>
        </div>

        <div id="step-quantity" class="bg-white rounded-2xl border border-border p-6 mb-6 hidden">
            <h3 class="font-semibold text-secondary mb-1">Pasul 1 — Câte invitații?</h3>
            <p class="text-sm text-muted mb-4">Introdu numărul de invitații pe care vrei să le generezi. Maxim 1000 pe o serie.</p>
            <div class="flex items-center gap-3">
                <input type="number" id="qty-input" min="1" max="1000" value="1" class="input w-32" />
                <button id="qty-continue" class="px-4 py-2 rounded-lg bg-rose-600 text-white font-semibold hover:bg-rose-700 transition-colors">Continuă</button>
            </div>
        </div>

        <div id="step-recipients" class="bg-white rounded-2xl border border-border p-6 mb-6 hidden">
            <div class="flex items-start justify-between mb-4 gap-4 flex-wrap">
                <div>
                    <h3 class="font-semibold text-secondary mb-1">Pasul 2 — Datele invitaților</h3>
                    <p class="text-sm text-muted">Pentru fiecare invitație, completează <strong>prenume, nume și email</strong> (obligatorii). Telefon, companie și note sunt opționale.</p>
                </div>
                <div id="mode-switcher" class="hidden rounded-lg border border-border overflow-hidden">
                    <button id="mode-manual" type="button" class="px-3 py-1.5 text-sm bg-rose-50 text-rose-700 font-semibold">Completare manuală</button>
                    <button id="mode-csv" type="button" class="px-3 py-1.5 text-sm text-muted hover:bg-slate-50">Încarcă CSV</button>
                </div>
            </div>

            <div id="pane-manual">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-xs uppercase text-muted">
                                <th class="py-2 pr-3 w-10">#</th>
                                <th class="py-2 pr-3">Prenume *</th>
                                <th class="py-2 pr-3">Nume *</th>
                                <th class="py-2 pr-3">Email *</th>
                                <th class="py-2 pr-3">Telefon</th>
                                <th class="py-2 pr-3">Companie</th>
                                <th class="py-2 pr-3">Note</th>
                            </tr>
                        </thead>
                        <tbody id="recipients-tbody"></tbody>
                    </table>
                </div>
            </div>

            <div id="pane-csv" class="hidden">
                <div class="border-2 border-dashed border-border rounded-xl p-6 text-center">
                    <input type="file" id="csv-file" accept=".csv,text/csv" class="hidden" />
                    <p class="text-sm text-muted mb-3">Încarcă un fișier CSV cu coloanele: <code class="px-1 rounded bg-slate-100">first_name, last_name, email, phone, company, notes</code></p>
                    <div class="flex items-center justify-center gap-3 flex-wrap">
                        <button type="button" id="csv-pick-btn" class="px-4 py-2 rounded-lg bg-rose-600 text-white font-semibold hover:bg-rose-700 transition-colors">Alege fișier CSV</button>
                        <a id="csv-template-link" href="#" class="px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100">Descarcă template CSV</a>
                    </div>
                    <p id="csv-filename" class="mt-3 text-sm font-semibold text-secondary"></p>
                    <p id="csv-error" class="mt-2 text-sm text-red-600"></p>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-between gap-3 flex-wrap">
                <button id="back-to-qty" type="button" class="px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100">&larr; Înapoi</button>
                <button id="generate-btn" type="button" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 text-white font-semibold hover:bg-rose-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Generează PDF-uri
                </button>
            </div>
        </div>

        <div id="step-done" class="bg-white rounded-2xl border border-border p-6 mb-6 hidden">
            <div class="flex items-start gap-3 mb-4">
                <div class="w-10 h-10 bg-emerald-50 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div>
                    <h3 class="font-semibold text-secondary">Gata! Invitațiile au fost generate.</h3>
                    <p id="done-summary" class="text-sm text-muted"></p>
                </div>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <a id="download-link" href="#" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 text-white font-semibold hover:bg-rose-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Descarcă invitațiile
                </a>
                <button id="new-batch-btn" type="button" class="px-4 py-2 rounded-lg text-slate-700 hover:bg-slate-100">Generează altă serie</button>
            </div>
        </div>

        <div id="history-section" class="bg-white rounded-2xl border border-border p-6 hidden">
            <h3 class="font-semibold text-secondary mb-4">Serii de invitații pentru acest eveniment</h3>
            <div id="history-empty" class="text-sm text-muted hidden">Încă nu ai generat invitații pentru acest eveniment.</div>
            <div id="history-rows" class="divide-y divide-slate-100"></div>
        </div>
    </main>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
(function () {
    const params = new URLSearchParams(window.location.search);
    const eventId = params.get('event') || params.get('event_id');
    let currentEvent = null;
    let currentMode = 'manual';
    let parsedCsvRecipients = null;

    const $ = (id) => document.getElementById(id);
    const esc = (s) => { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
    const fmtDate = (d) => d ? new Date(d).toLocaleDateString('ro-RO', { day: '2-digit', month: 'long', year: 'numeric' }) : '';

    function buildAuthHeaders() {
        const token = (typeof AmbiletAuth !== 'undefined' && AmbiletAuth.getToken) ? AmbiletAuth.getToken() : null;
        return token ? { 'Authorization': 'Bearer ' + token, 'Accept': 'application/octet-stream, application/zip, text/csv, */*' } : {};
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof AmbiletAuth !== 'undefined' && AmbiletAuth.requireOrganizerAuth) {
            AmbiletAuth.requireOrganizerAuth();
        }
        init();
    });

    async function init() {
        $('csv-template-link').addEventListener('click', onCsvTemplateDownload);

        if (!eventId) {
            $('event-missing').classList.remove('hidden');
            return;
        }

        await loadEvent(eventId);
        if (!currentEvent) return;

        $('step-quantity').classList.remove('hidden');
        $('history-section').classList.remove('hidden');
        await loadHistory();

        $('qty-continue').addEventListener('click', onQuantityContinue);
        $('back-to-qty').addEventListener('click', () => { $('step-recipients').classList.add('hidden'); $('step-quantity').classList.remove('hidden'); });
        $('mode-manual').addEventListener('click', () => setMode('manual'));
        $('mode-csv').addEventListener('click', () => setMode('csv'));
        $('csv-pick-btn').addEventListener('click', () => $('csv-file').click());
        $('csv-file').addEventListener('change', onCsvFilePicked);
        $('generate-btn').addEventListener('click', onGenerate);
        $('new-batch-btn').addEventListener('click', resetToStart);
    }

    async function loadEvent(id) {
        try {
            const res = await AmbiletAPI.get('/organizer/events/' + id);
            if (res && res.success && res.data) {
                currentEvent = res.data.event || res.data;
                $('event-name').textContent = currentEvent.name || currentEvent.title || 'Eveniment';
                const dateStr = currentEvent.starts_at || currentEvent.event_date || currentEvent.range_start_date;
                $('event-date').textContent = dateStr ? fmtDate(dateStr) : '';
                $('event-venue').textContent = currentEvent.venue_name || (currentEvent.venue && currentEvent.venue.name) || '';
                $('event-header').classList.remove('hidden');
            } else {
                $('event-missing').classList.remove('hidden');
            }
        } catch (e) {
            console.error(e);
            $('event-missing').classList.remove('hidden');
        }
    }

    function onQuantityContinue() {
        const qty = Math.max(1, Math.min(1000, parseInt($('qty-input').value || '1', 10)));
        $('qty-input').value = qty;
        $('step-quantity').classList.add('hidden');
        $('step-recipients').classList.remove('hidden');
        if (qty > 10) {
            $('mode-switcher').classList.remove('hidden');
            $('mode-switcher').classList.add('inline-flex');
        } else {
            $('mode-switcher').classList.add('hidden');
            $('mode-switcher').classList.remove('inline-flex');
            setMode('manual');
        }
        buildRecipientRows(qty);
    }

    function setMode(mode) {
        currentMode = mode;
        $('pane-manual').classList.toggle('hidden', mode !== 'manual');
        $('pane-csv').classList.toggle('hidden', mode !== 'csv');
        $('mode-manual').classList.toggle('bg-rose-50', mode === 'manual');
        $('mode-manual').classList.toggle('text-rose-700', mode === 'manual');
        $('mode-manual').classList.toggle('font-semibold', mode === 'manual');
        $('mode-manual').classList.toggle('text-muted', mode !== 'manual');
        $('mode-csv').classList.toggle('bg-rose-50', mode === 'csv');
        $('mode-csv').classList.toggle('text-rose-700', mode === 'csv');
        $('mode-csv').classList.toggle('font-semibold', mode === 'csv');
        $('mode-csv').classList.toggle('text-muted', mode !== 'csv');
    }

    function buildRecipientRows(qty) {
        const tbody = $('recipients-tbody');
        tbody.innerHTML = '';
        for (let i = 1; i <= qty; i++) {
            const tr = document.createElement('tr');
            tr.className = 'border-b border-slate-100';
            tr.innerHTML =
                '<td class="py-2 pr-3 text-xs text-muted align-middle">' + i + '</td>' +
                '<td class="py-2 pr-3"><input class="input input-sm w-full" data-field="first_name" placeholder="Prenume" required></td>' +
                '<td class="py-2 pr-3"><input class="input input-sm w-full" data-field="last_name" placeholder="Nume" required></td>' +
                '<td class="py-2 pr-3"><input class="input input-sm w-full" type="email" data-field="email" placeholder="email@exemplu.ro" required></td>' +
                '<td class="py-2 pr-3"><input class="input input-sm w-full" data-field="phone" placeholder="Telefon"></td>' +
                '<td class="py-2 pr-3"><input class="input input-sm w-full" data-field="company" placeholder="Companie"></td>' +
                '<td class="py-2 pr-3"><input class="input input-sm w-full" data-field="notes" placeholder="Note"></td>';
            tbody.appendChild(tr);
        }
    }

    function collectManualRecipients() {
        const rows = Array.from(document.querySelectorAll('#recipients-tbody tr'));
        const out = [];
        for (const row of rows) {
            const rec = {};
            row.querySelectorAll('input[data-field]').forEach(inp => {
                const v = (inp.value || '').trim();
                if (v !== '') rec[inp.dataset.field] = v;
            });
            if (rec.first_name && rec.last_name && rec.email) {
                out.push(rec);
            }
        }
        return out;
    }

    async function onCsvTemplateDownload(e) {
        e.preventDefault();
        try {
            const res = await fetch(AmbiletAPI.getApiUrl() + '?action=organizer.invitations.csv-template', {
                headers: buildAuthHeaders(),
            });
            if (!res.ok) throw new Error('Download failed (' + res.status + ')');
            const blob = await res.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = 'invitatii-template.csv';
            document.body.appendChild(a); a.click(); a.remove();
            URL.revokeObjectURL(url);
        } catch (err) {
            alert('Nu pot descărca template-ul: ' + err.message);
        }
    }

    function onCsvFilePicked(ev) {
        const file = ev.target.files[0];
        if (!file) return;
        $('csv-filename').textContent = file.name;
        $('csv-error').textContent = '';
        const reader = new FileReader();
        reader.onload = () => {
            try {
                parsedCsvRecipients = parseCsv(reader.result);
                $('csv-filename').textContent = file.name + ' — ' + parsedCsvRecipients.length + ' invitați detectați';
            } catch (e) {
                parsedCsvRecipients = null;
                $('csv-error').textContent = e.message;
            }
        };
        reader.readAsText(file);
    }

    function parseCsv(text) {
        if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
        const lines = text.split(/\r?\n/).filter(l => l.trim() !== '');
        if (lines.length < 2) throw new Error('CSV-ul e gol sau conține doar antetul.');
        const header = splitCsvLine(lines[0]).map(h => h.toLowerCase().trim());
        const idx = (name) => header.indexOf(name);
        const iFirst = idx('first_name');
        const iLast = idx('last_name');
        const iEmail = idx('email');
        if (iFirst < 0 || iLast < 0 || iEmail < 0) {
            throw new Error('Lipsesc coloane obligatorii. Antetul trebuie să conțină first_name, last_name, email.');
        }
        const iPhone = idx('phone'), iCompany = idx('company'), iNotes = idx('notes');
        const rows = [];
        for (let i = 1; i < lines.length; i++) {
            const c = splitCsvLine(lines[i]);
            const first = (c[iFirst] || '').trim();
            const last = (c[iLast] || '').trim();
            const email = (c[iEmail] || '').trim();
            if (!first || !last || !email) continue;
            const rec = { first_name: first, last_name: last, email: email };
            if (iPhone >= 0 && c[iPhone]) rec.phone = c[iPhone].trim();
            if (iCompany >= 0 && c[iCompany]) rec.company = c[iCompany].trim();
            if (iNotes >= 0 && c[iNotes]) rec.notes = c[iNotes].trim();
            rows.push(rec);
        }
        if (rows.length === 0) throw new Error('Niciun rând valid (toate trebuie să aibă prenume, nume, email).');
        if (rows.length > 1000) throw new Error('Maxim 1000 de invitații într-o serie. Împarte CSV-ul în mai multe fișiere.');
        return rows;
    }

    function splitCsvLine(line) {
        const out = [];
        let cur = '', inQ = false;
        for (let i = 0; i < line.length; i++) {
            const ch = line[i];
            if (ch === '"') {
                if (inQ && line[i + 1] === '"') { cur += '"'; i++; }
                else inQ = !inQ;
            } else if (ch === ',' && !inQ) {
                out.push(cur); cur = '';
            } else {
                cur += ch;
            }
        }
        out.push(cur);
        return out;
    }

    async function onGenerate() {
        const recipients = currentMode === 'csv' ? (parsedCsvRecipients || []) : collectManualRecipients();
        if (recipients.length === 0) {
            alert('Completează datele invitaților înainte de a genera.');
            return;
        }
        const btn = $('generate-btn');
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span>Se generează…</span>';
        try {
            const res = await AmbiletAPI.post('/organizer/invitations', {
                event_id: parseInt(eventId, 10),
                recipients: recipients,
            });
            if (!res || !res.success) throw new Error((res && res.message) || 'Eroare la generare');
            const batch = res.data.batch;
            const rendered = res.data.rendered || 0;
            $('done-summary').textContent = rendered + ' invitații generate în seria "' + (batch.name || '') + '".';
            $('download-link').onclick = (e) => { e.preventDefault(); downloadZip(batch.id); };
            $('step-recipients').classList.add('hidden');
            $('step-done').classList.remove('hidden');
            await loadHistory();
        } catch (e) {
            alert('Generarea a eșuat: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    }

    async function downloadZip(batchId) {
        try {
            const url = AmbiletAPI.getApiUrl() + '?action=organizer.invitations.download&batch_id=' + encodeURIComponent(batchId);
            const res = await fetch(url, { headers: buildAuthHeaders() });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const blob = await res.blob();
            const blobUrl = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = blobUrl;
            a.download = 'invitatii-' + batchId + '.zip';
            document.body.appendChild(a); a.click(); a.remove();
            URL.revokeObjectURL(blobUrl);
        } catch (e) {
            alert('Descărcarea a eșuat: ' + e.message);
        }
    }

    function resetToStart() {
        $('step-done').classList.add('hidden');
        $('step-quantity').classList.remove('hidden');
        $('qty-input').value = 1;
        parsedCsvRecipients = null;
        $('csv-filename').textContent = '';
        $('csv-error').textContent = '';
        $('csv-file').value = '';
    }

    async function loadHistory() {
        try {
            const res = await AmbiletAPI.get('/organizer/invitations?event_id=' + eventId + '&per_page=50');
            let rows = [];
            if (res && res.success) {
                if (Array.isArray(res.data)) rows = res.data;
                else if (res.data && Array.isArray(res.data.data)) rows = res.data.data;
                else if (res.data && Array.isArray(res.data.items)) rows = res.data.items;
            }
            renderHistory(rows);
        } catch (e) {
            console.error('History load failed', e);
        }
    }

    function renderHistory(rows) {
        const host = $('history-rows');
        host.innerHTML = '';
        if (!rows || rows.length === 0) {
            $('history-empty').classList.remove('hidden');
            return;
        }
        $('history-empty').classList.add('hidden');

        rows.forEach(b => {
            const created = fmtDate(b.created_at);
            const status = (b.status === 'ready') ? 'badge-success' : 'badge-secondary';
            const planned = b.qty_planned || 0;
            const rendered = b.qty_rendered || 0;
            const downloaded = b.qty_downloaded || 0;
            const row = document.createElement('div');
            row.className = 'py-3 flex flex-wrap items-center justify-between gap-3';
            row.innerHTML =
                '<div class="min-w-0">' +
                    '<p class="font-semibold text-secondary">' + esc(b.name) + '</p>' +
                    '<p class="text-xs text-muted">' + created + ' · ' + planned + ' planificate · ' + rendered + ' generate · ' + downloaded + ' descărcate</p>' +
                '</div>' +
                '<div class="flex items-center gap-2">' +
                    '<span class="badge ' + status + '">' + esc(b.status || '') + '</span>' +
                    '<button class="px-3 py-1.5 rounded-lg text-slate-700 hover:bg-slate-100 text-sm" data-view-invites="' + b.id + '">Vezi invitați</button>' +
                    '<button class="px-3 py-1.5 rounded-lg bg-rose-600 text-white font-semibold hover:bg-rose-700 text-sm" data-dl="' + b.id + '">Descarcă ZIP</button>' +
                '</div>' +
                '<div class="hidden w-full" id="invites-panel-' + b.id + '"></div>';
            host.appendChild(row);
        });

        host.querySelectorAll('[data-dl]').forEach(btn => btn.addEventListener('click', () => downloadZip(btn.dataset.dl)));
        host.querySelectorAll('[data-view-invites]').forEach(btn => btn.addEventListener('click', () => toggleInvites(btn.dataset.viewInvites)));
    }

    async function toggleInvites(batchId) {
        const panel = $('invites-panel-' + batchId);
        if (!panel) return;
        if (!panel.classList.contains('hidden')) {
            panel.classList.add('hidden');
            panel.innerHTML = '';
            return;
        }
        panel.classList.remove('hidden');
        panel.innerHTML = '<p class="text-sm text-muted py-2">Se încarcă…</p>';
        try {
            const res = await AmbiletAPI.get('/organizer/invitations/' + batchId);
            const invites = (res && res.data && res.data.invites) ? res.data.invites : [];
            if (invites.length === 0) {
                panel.innerHTML = '<p class="text-sm text-muted py-2">Fără invitați.</p>';
                return;
            }
            const rowsHtml = invites.map(i => {
                const r = i.recipient || {};
                return '<tr class="border-b border-slate-100">' +
                    '<td class="py-1.5 pr-3">' + esc(r.name || '') + '</td>' +
                    '<td class="py-1.5 pr-3">' + esc(r.email || '') + '</td>' +
                    '<td class="py-1.5 pr-3">' + esc(r.phone || '') + '</td>' +
                    '<td class="py-1.5 pr-3">' + esc(r.company || '') + '</td>' +
                    '<td class="py-1.5 pr-3"><code class="text-xs">' + esc(i.code) + '</code></td>' +
                '</tr>';
            }).join('');
            panel.innerHTML =
                '<div class="mt-3 bg-slate-50 rounded-lg p-3 overflow-x-auto">' +
                    '<table class="w-full text-sm">' +
                        '<thead><tr class="text-left text-xs uppercase text-muted">' +
                            '<th class="py-1.5 pr-3">Nume</th>' +
                            '<th class="py-1.5 pr-3">Email</th>' +
                            '<th class="py-1.5 pr-3">Telefon</th>' +
                            '<th class="py-1.5 pr-3">Companie</th>' +
                            '<th class="py-1.5 pr-3">Cod</th>' +
                        '</tr></thead>' +
                        '<tbody>' + rowsHtml + '</tbody>' +
                    '</table>' +
                '</div>';
        } catch (e) {
            panel.innerHTML = '<p class="text-sm text-red-600 py-2">Nu pot încărca invitații: ' + esc(e.message) + '</p>';
        }
    }
})();
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
