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

        <div id="step-seats" class="bg-white rounded-2xl border border-border p-6 mb-6 hidden">
            <h3 class="font-semibold text-secondary mb-1">Pasul 1 — Alege locurile</h3>
            <p class="text-sm text-muted mb-4">Evenimentul are hartă de locuri. Selectează locurile pe care vrei să le blochezi pentru invitații. Locurile alese vor fi marcate ca <strong>vândute</strong> și nu vor putea fi cumpărate de clienți.</p>
            <div class="flex flex-wrap items-center gap-3">
                <button id="open-seat-picker" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-rose-600 text-white font-semibold hover:bg-rose-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.553 2.776A1 1 0 0022 18.882V8.118a1 1 0 00-1.447-.894L15 10m-6-3l6 3m0 0v10"/></svg>
                    Alege locurile pe hartă
                </button>
                <span id="seats-summary" class="text-sm text-muted">Niciun loc selectat.</span>
                <button id="seats-continue" class="hidden ml-auto px-4 py-2 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 transition-colors">Continuă &rarr;</button>
            </div>
            <div id="seats-loading" class="mt-4 text-sm text-muted hidden">Se încarcă harta…</div>
            <div id="seats-error" class="mt-4 text-sm text-red-600 hidden"></div>
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
                                <th id="col-seat" class="py-2 pr-3 hidden">Loc</th>
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

<!-- Seat picker modal -->
<div id="seat-modal" class="fixed inset-0 z-50 bg-black/50 hidden">
    <div class="absolute inset-4 md:inset-8 bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-border flex-shrink-0">
            <div>
                <h3 class="font-semibold text-secondary">Alege locurile pentru invitații</h3>
                <p id="seat-modal-help" class="text-xs text-muted mt-0.5">Click pe un loc pentru a-l selecta / deselecta.</p>
            </div>
            <div class="flex items-center gap-3">
                <span id="seat-modal-count" class="text-sm font-semibold text-secondary">0 locuri</span>
                <button id="seat-modal-close" class="text-slate-500 hover:text-slate-800">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
        <div id="seat-modal-body" class="flex-1 overflow-auto bg-slate-50 p-4">
            <div id="seat-modal-loading" class="text-center text-muted py-12">Se încarcă harta…</div>
            <div id="seat-modal-map" class="hidden"></div>
        </div>
        <div class="flex items-center justify-between gap-3 px-6 py-3 border-t border-border flex-shrink-0 flex-wrap">
            <div class="flex items-center gap-4 text-xs text-muted flex-wrap">
                <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full" style="background:#10b981"></span>Disponibil</span>
                <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full" style="background:#a51c30"></span>Selectat de tine</span>
                <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-full" style="background:#d1d5db"></span>Indisponibil</span>
            </div>
            <div class="flex items-center gap-2">
                <button id="seat-modal-clear" class="px-3 py-1.5 rounded-lg text-slate-700 hover:bg-slate-100 text-sm">Deselectează tot</button>
                <button id="seat-modal-confirm" class="px-4 py-2 rounded-lg bg-rose-600 text-white font-semibold hover:bg-rose-700 transition-colors">Confirmă selecția</button>
            </div>
        </div>
    </div>
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

    // Seat-mode state: populated once the organizer loads a seated event.
    // `seatingData` holds the layout payload; `selectedSeats` is the ordered
    // list of seats the organizer picked in the modal (array of objects with
    // seat_uid / section_name / row_label / seat_label). When non-empty it
    // replaces the manual quantity input and drives recipient row count.
    let isSeated = false;
    let seatingData = null;
    let selectedSeats = [];

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

        $('history-section').classList.remove('hidden');
        await loadHistory();

        // Seated vs. non-seated UX: when the event has a seating layout we
        // replace step 1 with a seat picker. Otherwise the legacy quantity
        // input flow stays intact.
        if (isSeated) {
            $('step-seats').classList.remove('hidden');
            $('open-seat-picker').addEventListener('click', openSeatModal);
            $('seat-modal-close').addEventListener('click', closeSeatModal);
            $('seat-modal-clear').addEventListener('click', clearAllSeats);
            $('seat-modal-confirm').addEventListener('click', confirmSeatSelection);
            $('seats-continue').addEventListener('click', onSeatsContinueToStep2);
        } else {
            $('step-quantity').classList.remove('hidden');
            $('qty-continue').addEventListener('click', onQuantityContinue);
        }

        $('back-to-qty').addEventListener('click', () => {
            $('step-recipients').classList.add('hidden');
            if (isSeated) $('step-seats').classList.remove('hidden');
            else $('step-quantity').classList.remove('hidden');
        });
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

                isSeated = !!(currentEvent.has_seating || currentEvent.seating_layout_id || currentEvent.seating_layout);
            } else {
                $('event-missing').classList.remove('hidden');
            }
        } catch (e) {
            console.error(e);
            $('event-missing').classList.remove('hidden');
        }
    }

    // ===================================================================
    // Seat picker (seated events only)
    // ===================================================================

    async function openSeatModal() {
        $('seat-modal').classList.remove('hidden');
        $('seat-modal-loading').classList.remove('hidden');
        $('seat-modal-map').classList.add('hidden');
        $('seat-modal-map').innerHTML = '';

        try {
            if (!seatingData) {
                const res = await AmbiletAPI.get('/organizer/events/' + eventId + '/seating-map');
                if (!res || !res.success) throw new Error((res && res.message) || 'Nu pot încărca harta');
                seatingData = res.data;
            }
            renderSeatModal();
            $('seat-modal-loading').classList.add('hidden');
            $('seat-modal-map').classList.remove('hidden');
        } catch (e) {
            console.error(e);
            $('seat-modal-loading').textContent = 'Nu pot încărca harta: ' + (e.message || e);
        }
    }

    function closeSeatModal() {
        $('seat-modal').classList.add('hidden');
    }

    function clearAllSeats() {
        selectedSeats = [];
        updateSeatModalCount();
        // Repaint each seat circle to clear the "selected" style
        document.querySelectorAll('#seat-modal-map [data-seat-uid]').forEach((el) => paintSeat(el));
    }

    function confirmSeatSelection() {
        if (selectedSeats.length === 0) {
            alert('Alege cel puțin un loc înainte de a confirma.');
            return;
        }
        updateSeatsSummary();
        $('seats-continue').classList.remove('hidden');
        closeSeatModal();
    }

    function onSeatsContinueToStep2() {
        if (selectedSeats.length === 0) return;
        $('step-seats').classList.add('hidden');
        $('step-recipients').classList.remove('hidden');
        // Seated mode: each row is pinned to a specific seat, so CSV import
        // doesn't make sense (can't line up N rows from CSV with N picked seats)
        $('mode-switcher').classList.add('hidden');
        $('mode-switcher').classList.remove('inline-flex');
        setMode('manual');
        $('col-seat').classList.remove('hidden');
        buildRecipientRows(selectedSeats.length);
    }

    function updateSeatsSummary() {
        const n = selectedSeats.length;
        const summary = $('seats-summary');
        if (n === 0) {
            summary.textContent = 'Niciun loc selectat.';
            summary.className = 'text-sm text-muted';
        } else {
            summary.textContent = n + (n === 1 ? ' loc selectat' : ' locuri selectate');
            summary.className = 'text-sm font-semibold text-emerald-700';
        }
    }

    function updateSeatModalCount() {
        const n = selectedSeats.length;
        $('seat-modal-count').textContent = n + (n === 1 ? ' loc' : ' locuri');
    }

    function renderSeatModal() {
        const data = seatingData;
        const host = $('seat-modal-map');
        if (!data || !data.sections) {
            host.innerHTML = '<p class="text-center text-muted py-8">Harta nu este disponibilă.</p>';
            return;
        }

        const canvasW = (data.canvas && data.canvas.width) || 1000;
        const canvasH = (data.canvas && data.canvas.height) || 800;

        let svg = '<svg viewBox="0 0 ' + canvasW + ' ' + canvasH + '" style="width:100%; max-width:' + canvasW + 'px; height:auto; display:block; margin:0 auto;" xmlns="http://www.w3.org/2000/svg">';

        data.sections.forEach(function (section) {
            // Skip non-seat sections (icons, decorative text/lines)
            if (section.section_type === 'icon' || section.section_type === 'decorative') return;
            if (!section.rows) return;

            const rotation = section.rotation || 0;
            const cx = (section.x || 0) + (section.width || 0) / 2;
            const cy = (section.y || 0) + (section.height || 0) / 2;
            const transform = rotation !== 0 ? ' transform="rotate(' + rotation + ' ' + cx + ' ' + cy + ')"' : '';

            svg += '<g' + transform + '>';

            // Section label
            if (section.name) {
                svg += '<text x="' + cx + '" y="' + ((section.y || 0) - 6) + '" text-anchor="middle" font-size="12" font-weight="700" fill="#374151">' + esc(section.name) + '</text>';
            }

            const meta = section.metadata || {};
            const seatSize = parseInt(meta.seat_size) || 15;
            const seatRadius = seatSize / 2;

            section.rows.forEach(function (row) {
                if (!row.seats) return;
                row.seats.forEach(function (seat) {
                    const cx2 = (section.x || 0) + (seat.x || 0);
                    const cy2 = (section.y || 0) + (seat.y || 0);
                    const uid = seat.seat_uid;
                    // Store everything the seat needs via data-* so paintSeat()
                    // can repaint without walking the tree.
                    svg += '<circle data-seat-uid="' + esc(uid) + '"'
                        + ' data-section="' + esc(section.name || '') + '"'
                        + ' data-row="' + esc(row.label || '') + '"'
                        + ' data-seat="' + esc(seat.label || '') + '"'
                        + ' data-status="' + esc(seat.status || 'available') + '"'
                        + ' cx="' + cx2 + '" cy="' + cy2 + '" r="' + seatRadius + '"'
                        + ' stroke-width="1" style="cursor:pointer">'
                        + '<title>' + esc(section.name || '') + ' · Rând ' + esc(row.label || '') + ' · Loc ' + esc(seat.label || '') + '</title>'
                        + '</circle>';
                });
            });

            svg += '</g>';
        });

        svg += '</svg>';
        host.innerHTML = svg;

        // Paint initial state + wire click handlers
        host.querySelectorAll('[data-seat-uid]').forEach((el) => {
            paintSeat(el);
            el.addEventListener('click', onSeatClick);
        });
    }

    function paintSeat(el) {
        const status = el.getAttribute('data-status') || 'available';
        const uid = el.getAttribute('data-seat-uid');
        const isSelected = selectedSeats.some((s) => s.seat_uid === uid);

        let fill, stroke, clickable;
        if (status !== 'available') {
            // sold / held / blocked / disabled are all unavailable for the organizer
            fill = '#d1d5db';
            stroke = '#9ca3af';
            clickable = false;
        } else if (isSelected) {
            fill = '#a51c30';
            stroke = '#7a141f';
            clickable = true;
        } else {
            fill = '#10b981';
            stroke = '#059669';
            clickable = true;
        }
        el.setAttribute('fill', fill);
        el.setAttribute('stroke', stroke);
        el.style.pointerEvents = clickable ? 'auto' : 'none';
    }

    function onSeatClick(ev) {
        const el = ev.currentTarget;
        const uid = el.getAttribute('data-seat-uid');
        const idx = selectedSeats.findIndex((s) => s.seat_uid === uid);
        if (idx >= 0) {
            selectedSeats.splice(idx, 1);
        } else {
            if (selectedSeats.length >= 1000) {
                alert('Maxim 1000 de locuri într-o serie de invitații.');
                return;
            }
            selectedSeats.push({
                seat_uid: uid,
                section_name: el.getAttribute('data-section') || null,
                row_label: el.getAttribute('data-row') || null,
                seat_label: el.getAttribute('data-seat') || null,
            });
        }
        paintSeat(el);
        updateSeatModalCount();
    }

    // ===================================================================
    // Non-seated flow (unchanged)
    // ===================================================================

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
        $('col-seat').classList.add('hidden');
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
            // When seated, show a locked seat cell pinned to this row's index.
            const seatCell = isSeated && selectedSeats[i - 1]
                ? '<td class="py-2 pr-3 text-xs text-secondary align-middle whitespace-nowrap"><span class="inline-block px-2 py-1 rounded bg-rose-50 text-rose-700 font-semibold">' + esc(seatRefFor(selectedSeats[i - 1])) + '</span></td>'
                : (isSeated ? '<td class="py-2 pr-3 text-xs text-muted"></td>' : '');
            tr.innerHTML =
                '<td class="py-2 pr-3 text-xs text-muted align-middle">' + i + '</td>' +
                seatCell +
                '<td class="py-2 pr-3"><input class="input input-sm w-full" data-field="first_name" placeholder="Prenume" required></td>' +
                '<td class="py-2 pr-3"><input class="input input-sm w-full" data-field="last_name" placeholder="Nume" required></td>' +
                '<td class="py-2 pr-3"><input class="input input-sm w-full" type="email" data-field="email" placeholder="email@exemplu.ro" required></td>' +
                '<td class="py-2 pr-3"><input class="input input-sm w-full" data-field="phone" placeholder="Telefon"></td>' +
                '<td class="py-2 pr-3"><input class="input input-sm w-full" data-field="company" placeholder="Companie"></td>' +
                '<td class="py-2 pr-3"><input class="input input-sm w-full" data-field="notes" placeholder="Note"></td>';
            tbody.appendChild(tr);
        }
    }

    function seatRefFor(seat) {
        if (!seat) return '';
        const parts = [];
        if (seat.section_name) parts.push(seat.section_name);
        if (seat.row_label) parts.push('R' + seat.row_label);
        if (seat.seat_label) parts.push('L' + seat.seat_label);
        return parts.length ? parts.join(' · ') : seat.seat_uid;
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

        // Seated events: each row is tied to a specific picked seat (same
        // index into selectedSeats). Reject mismatches upfront so the server
        // doesn't waste a transaction just to 422 us.
        if (isSeated) {
            if (recipients.length !== selectedSeats.length) {
                alert('Numărul de invitați completați (' + recipients.length + ') trebuie să fie egal cu numărul de locuri alese (' + selectedSeats.length + ').');
                return;
            }
        }

        const btn = $('generate-btn');
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span>Se generează…</span>';
        try {
            const payload = {
                event_id: parseInt(eventId, 10),
                recipients: recipients,
            };
            if (isSeated && seatingData && seatingData.event_seating_id) {
                payload.event_seating_id = seatingData.event_seating_id;
                payload.seats = selectedSeats;
            }
            const res = await AmbiletAPI.post('/organizer/invitations', payload);
            if (!res || !res.success) throw new Error((res && res.message) || 'Eroare la generare');
            const batch = res.data.batch;
            const rendered = res.data.rendered || 0;
            $('done-summary').textContent = rendered + ' invitații generate în seria "' + (batch.name || '') + '".';
            $('download-link').onclick = (e) => { e.preventDefault(); downloadZip(batch.id); };
            $('step-recipients').classList.add('hidden');
            $('step-done').classList.remove('hidden');
            // Seated events: after a successful submit, the chosen seats are
            // now sold (server confirmPurchase). Clear local state so the next
            // batch forces a fresh map fetch.
            if (isSeated) {
                selectedSeats = [];
                seatingData = null;
            }
            await loadHistory();
        } catch (e) {
            // Surface seat-specific conflict errors so the organizer understands
            // which seat another buyer grabbed between the map open and submit.
            let msg = e.message || 'Eroare necunoscută';
            if (e.data && e.data.errors && e.data.errors.unavailable_seats) {
                msg += '\n\nLocuri indisponibile: ' + e.data.errors.unavailable_seats.join(', ');
                seatingData = null; // force refresh on next open
            }
            alert('Generarea a eșuat: ' + msg);
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
        selectedSeats = [];
        if (isSeated) {
            $('step-seats').classList.remove('hidden');
            updateSeatsSummary();
            $('seats-continue').classList.add('hidden');
        } else {
            $('step-quantity').classList.remove('hidden');
            $('qty-input').value = 1;
        }
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
                const seat = r.seat || null;
                const seatRef = seat ? seatRefFor({ section_name: seat.section, row_label: seat.row, seat_label: seat.label, seat_uid: seat.uid }) : '';
                return '<tr class="border-b border-slate-100">' +
                    '<td class="py-1.5 pr-3">' + esc(r.name || '') + '</td>' +
                    '<td class="py-1.5 pr-3">' + esc(r.email || '') + '</td>' +
                    '<td class="py-1.5 pr-3">' + esc(seatRef) + '</td>' +
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
                            '<th class="py-1.5 pr-3">Loc</th>' +
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
