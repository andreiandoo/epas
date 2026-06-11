<?php
/**
 * bilete.online — Organizator › Invitații (v3).
 * Route: /organizator/invitatii
 *
 * Generate PDF invitations for a chosen activity. Faithful 1:1 port of the
 * ambilet organizer/invitatii.php page, restyled in v3 + activity-centric
 * wording ("eveniment" → "activitate"). Supports both non-seated (quantity)
 * and seated (interactive seat picker with pan/zoom) flows, manual + CSV
 * recipient entry, batch history, per-invite download/delete, and regenerate.
 * Wired to BileteOnlineAPI organizer invitation endpoints.
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Invitații';
$currentPage = 'invitatii';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl font-bold leading-none">Invitații</h1>
                <p class="mt-1.5 text-sm text-ink-soft">Generează invitații în format PDF pentru activitatea aleasă.</p>
            </div>
            <a href="/organizator/events" class="inline-flex items-center gap-1.5 rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">&larr; Înapoi la activități</a>
        </div>

        <div id="event-header" class="mb-6 hidden rounded-2xl border-2 border-ink bg-paper p-6">
            <div class="flex items-start gap-4">
                <div class="grid h-12 w-12 flex-shrink-0 place-items-center rounded-xl bg-vermilion/10">
                    <svg class="h-6 w-6 text-vermilion" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 id="event-name" class="font-display text-lg font-bold"></h2>
                    <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-ink-soft">
                        <span id="event-date"></span>
                        <span id="event-venue"></span>
                    </div>
                </div>
            </div>
        </div>

        <div id="event-missing" class="mb-6 hidden rounded-2xl border-2 border-ochre/40 bg-ochre/10 p-6">
            <p class="text-sm text-ink">Nu am găsit activitatea selectată. <a href="/organizator/events" class="font-bold underline">Alege o activitate</a> din lista ta.</p>
        </div>

        <div id="step-quantity" class="mb-6 hidden rounded-2xl border-2 border-ink bg-paper p-6">
            <h3 class="font-display text-lg font-bold">Pasul 1 — Detalii serie</h3>
            <p class="mt-1 text-sm text-ink-soft">Dă un nume seriei (opțional, pentru organizare — ex. „Firma X", „Sponsori") și alege numărul de invitații.</p>

            <div class="mb-4 mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="batch-name-input" class="mb-1 block text-xs font-bold text-ink-soft">Nume serie (opțional)</label>
                    <input type="text" id="batch-name-input" maxlength="120" placeholder="ex. Firma X - presa" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" />
                </div>
                <div>
                    <label for="qty-input" class="mb-1 block text-xs font-bold text-ink-soft">Număr invitații <span class="text-vermilion">*</span></label>
                    <input type="number" id="qty-input" min="1" max="50" value="1" class="w-32 rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink" />
                </div>
            </div>

            <div class="mb-4 flex items-start gap-2 rounded-xl border-2 border-ochre/40 bg-ochre/10 p-3">
                <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-ochre" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-xs text-ink">
                    <strong>Maxim 50 de invitații per serie.</strong> Generarea poate dura câteva secunde — fiecare invitație produce un PDF cu QR + șablonul tău de bilet. Dacă ai nevoie de mai multe, creează mai multe serii.
                </p>
            </div>

            <button id="qty-continue" class="rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">Continuă</button>
        </div>

        <div id="step-seats" class="mb-6 hidden rounded-2xl border-2 border-ink bg-paper p-6">
            <h3 class="font-display text-lg font-bold">Pasul 1 — Alege locurile</h3>
            <p class="mb-4 mt-1 text-sm text-ink-soft">Activitatea are hartă de locuri. Selectează locurile pe care vrei să le blochezi pentru invitații. Locurile alese vor fi marcate ca <strong>vândute</strong> și nu vor putea fi cumpărate de clienți.</p>
            <div class="flex flex-wrap items-center gap-3">
                <button id="open-seat-picker" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l5.553 2.776A1 1 0 0022 18.882V8.118a1 1 0 00-1.447-.894L15 10m-6-3l6 3m0 0v10"/></svg>
                    Alege locurile pe hartă
                </button>
                <span id="seats-summary" class="text-sm text-ink-soft">Niciun loc selectat.</span>
                <button id="seats-continue" class="ml-auto hidden rounded-full bg-forest px-5 py-2.5 text-sm font-bold text-paper transition hover:opacity-90">Continuă &rarr;</button>
            </div>
            <div id="seats-loading" class="mt-4 hidden text-sm text-ink-soft">Se încarcă harta…</div>
            <div id="seats-error" class="mt-4 hidden text-sm text-vermilion"></div>
        </div>

        <div id="step-recipients" class="mb-6 hidden rounded-2xl border-2 border-ink bg-paper p-6">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="font-display text-lg font-bold">Pasul 2 — Datele invitaților</h3>
                    <p class="mt-1 text-sm text-ink-soft">Completează datele invitaților dacă le ai. Toate câmpurile sunt opționale.</p>
                </div>
                <div id="mode-switcher" class="hidden overflow-hidden rounded-full border-2 border-ink">
                    <button id="mode-manual" type="button" class="bg-vermilion px-3 py-1.5 text-sm font-bold text-paper">Completare manuală</button>
                    <button id="mode-csv" type="button" class="px-3 py-1.5 text-sm font-bold text-ink-soft transition hover:bg-paper-2">Încarcă CSV</button>
                </div>
            </div>

            <div id="pane-manual">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b-2 border-ink/15 text-left text-xs uppercase text-ink-soft">
                                <th class="w-10 py-2 pr-3">#</th>
                                <th id="col-seat" class="hidden py-2 pr-3">Loc</th>
                                <th class="py-2 pr-3">Prenume</th>
                                <th class="py-2 pr-3">Nume</th>
                                <th class="py-2 pr-3">Email</th>
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
                <div class="rounded-xl border-2 border-dashed border-ink/15 p-6 text-center">
                    <input type="file" id="csv-file" accept=".csv,text/csv" class="hidden" />
                    <p class="mb-3 text-sm text-ink-soft">Încarcă un fișier CSV cu coloanele: <code class="rounded bg-paper-2 px-1">first_name, last_name, email, phone, company, notes</code></p>
                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <button type="button" id="csv-pick-btn" class="rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">Alege fișier CSV</button>
                        <a id="csv-template-link" href="#" class="rounded-full px-4 py-2.5 text-sm font-bold text-ink-soft transition hover:bg-paper-2">Descarcă template CSV</a>
                    </div>
                    <p id="csv-filename" class="mt-3 text-sm font-bold text-ink"></p>
                    <p id="csv-error" class="mt-2 text-sm text-vermilion"></p>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <button id="back-to-qty" type="button" class="rounded-full px-4 py-2.5 text-sm font-bold text-ink-soft transition hover:bg-paper-2">&larr; Înapoi</button>
                <button id="generate-btn" type="button" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Generează PDF-uri
                </button>
            </div>
        </div>

        <div id="step-done" class="mb-6 hidden rounded-2xl border-2 border-ink bg-paper p-6">
            <div class="mb-4 flex items-start gap-3">
                <div class="grid h-10 w-10 place-items-center rounded-lg bg-forest/10">
                    <svg class="h-6 w-6 text-forest" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <div>
                    <h3 class="font-display text-lg font-bold">Gata! Invitațiile au fost generate.</h3>
                    <p id="done-summary" class="text-sm text-ink-soft"></p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a id="download-link" href="#" class="inline-flex items-center gap-2 rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Descarcă invitațiile
                </a>
                <button id="new-batch-btn" type="button" class="rounded-full px-4 py-2.5 text-sm font-bold text-ink-soft transition hover:bg-paper-2">Generează altă serie</button>
            </div>
        </div>

        <div id="history-section" class="hidden rounded-2xl border-2 border-ink bg-paper p-6">
            <h3 class="mb-4 font-display text-lg font-bold">Serii de invitații pentru această activitate</h3>
            <div id="history-empty" class="hidden text-sm text-ink-soft">Încă nu ai generat invitații pentru această activitate.</div>
            <div id="history-rows" class="divide-y divide-ink/10"></div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<!-- Seat picker modal -->
<div id="seat-modal" class="fixed inset-0 z-[80] hidden bg-ink/60 backdrop-blur-sm">
    <div class="absolute inset-4 flex flex-col overflow-hidden rounded-[2rem] border-2 border-ink bg-paper shadow-deep md:inset-8">
        <div class="flex flex-shrink-0 items-center justify-between border-b-2 border-ink/15 px-6 py-4">
            <div>
                <h3 class="font-display text-lg font-bold">Alege locurile pentru invitații</h3>
                <p id="seat-modal-help" class="mt-0.5 text-xs text-ink-soft">Click pe un loc pentru a-l selecta / deselecta.</p>
            </div>
            <div class="flex items-center gap-3">
                <span id="seat-modal-count" class="text-sm font-bold text-ink">0 locuri</span>
                <button id="seat-modal-close" class="grid h-9 w-9 place-items-center rounded-full bg-ink text-paper transition hover:bg-vermilion" aria-label="Închide">&times;</button>
            </div>
        </div>
        <div id="seat-modal-body" class="relative flex-1 overflow-hidden bg-paper-2" style="touch-action:none;">
            <div id="seat-modal-loading" class="absolute inset-0 flex items-center justify-center text-ink-soft">Se încarcă harta…</div>
            <!-- seat-modal-map gets transform: translate + scale for pan/zoom -->
            <div id="seat-modal-map" class="absolute left-0 top-0 hidden origin-top-left" style="transform-origin:0 0; will-change:transform; cursor:grab;"></div>
            <!-- zoom controls overlay (desktop + mobile) -->
            <div class="absolute bottom-3 right-3 flex flex-col gap-1 rounded-lg border-2 border-ink/15 bg-paper p-1 shadow-lg">
                <button id="seat-zoom-in" class="flex h-8 w-8 items-center justify-center rounded text-ink transition hover:bg-paper-2" title="Mărește" aria-label="Mărește">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                </button>
                <button id="seat-zoom-out" class="flex h-8 w-8 items-center justify-center rounded text-ink transition hover:bg-paper-2" title="Micșorează" aria-label="Micșorează">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                </button>
                <button id="seat-zoom-reset" class="flex h-8 w-8 items-center justify-center rounded text-ink transition hover:bg-paper-2" title="Potrivește pe ecran" aria-label="Potrivește pe ecran">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5v4m0-4h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                </button>
            </div>
            <div id="seat-zoom-level" class="absolute bottom-3 left-3 rounded-lg border-2 border-ink/15 bg-paper px-2 py-1 text-xs font-bold text-ink shadow-lg">100%</div>
        </div>
        <div class="flex flex-shrink-0 flex-wrap items-center justify-between gap-3 border-t-2 border-ink/15 px-6 py-3">
            <div class="flex flex-wrap items-center gap-4 text-xs text-ink-soft">
                <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-full" style="background:#1E4A3D"></span>Disponibil</span>
                <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-full" style="background:#E84527"></span>Selectat de tine</span>
                <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded-full" style="background:#d1d5db"></span>Indisponibil</span>
            </div>
            <div class="flex items-center gap-2">
                <button id="seat-modal-clear" class="rounded-full px-3 py-1.5 text-sm font-bold text-ink-soft transition hover:bg-paper-2">Deselectează tot</button>
                <button id="seat-modal-confirm" class="rounded-full bg-vermilion px-5 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion-d">Confirmă selecția</button>
            </div>
        </div>
    </div>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
function orgNotify(msg, type) {
    try { if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) { BileteOnlineNotifications[type || 'info'](msg); return; } } catch (e) {}
    if (type === 'error' || type === 'warning') alert(msg);
}

(function () {
    const params = new URLSearchParams(window.location.search);
    const eventId = params.get('event') || params.get('event_id');
    let currentEvent = null;
    let currentMode = 'manual';
    let parsedCsvRecipients = null;

    // Seat-mode state: populated once the organizer loads a seated activity.
    // `seatingData` holds the layout payload; `selectedSeats` is the ordered
    // list of seats the organizer picked in the modal (array of objects with
    // seat_uid / section_name / row_label / seat_label). When non-empty it
    // replaces the manual quantity input and drives recipient row count.
    let isSeated = false;
    let seatingData = null;
    let selectedSeats = [];

    // Pan/zoom state for the seat modal. The transform is applied to
    // #seat-modal-map (transform-origin:0 0), so changing mapPan/mapZoom
    // and calling applyMapTransform() updates the view.
    const mapView = {
        zoom: 1,
        pan: { x: 0, y: 0 },
        min: 0.4,
        max: 4,
    };

    const $ = (id) => document.getElementById(id);
    const escHtml = (s) => { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
    const esc = escHtml;
    const fmtDate = (d) => d ? new Date(d).toLocaleDateString('ro-RO', { day: '2-digit', month: 'long', year: 'numeric' }) : '';

    function buildAuthHeaders() {
        const token = (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken) ? BileteOnlineAuth.getToken() : null;
        return token ? { 'Authorization': 'Bearer ' + token, 'Accept': 'application/octet-stream, application/zip, text/csv, */*' } : {};
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth && !BileteOnlineAuth.requireOrganizerAuth()) return;
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

        // Seated vs. non-seated UX: when the activity has a seating layout we
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
            const res = await BileteOnlineAPI.get('/organizer/events/' + id);
            if (res && res.success && res.data) {
                currentEvent = res.data.event || res.data;
                $('event-name').textContent = currentEvent.name || currentEvent.title || 'Activitate';
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
    // Seat picker (seated activities only)
    // ===================================================================

    async function openSeatModal() {
        $('seat-modal').classList.remove('hidden');
        $('seat-modal-loading').classList.remove('hidden');
        $('seat-modal-map').classList.add('hidden');
        $('seat-modal-map').innerHTML = '';

        try {
            if (!seatingData) {
                const res = await BileteOnlineAPI.get('/organizer/events/' + eventId + '/seating-map');
                if (!res || !res.success) throw new Error((res && res.message) || 'Nu pot încărca harta');
                seatingData = res.data;
            }
            // Make the map visible before rendering so getBoundingClientRect
            // returns real dimensions for the fit-to-screen calculation.
            $('seat-modal-loading').classList.add('hidden');
            $('seat-modal-map').classList.remove('hidden');
            renderSeatModal();
            bindPanZoom();
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
            summary.className = 'text-sm text-ink-soft';
        } else {
            summary.textContent = n + (n === 1 ? ' loc selectat' : ' locuri selectate');
            summary.className = 'text-sm font-bold text-forest';
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
            host.innerHTML = '<p class="py-8 text-center text-ink-soft">Harta nu este disponibilă.</p>';
            return;
        }

        const canvasW = (data.canvas && data.canvas.width) || 1000;
        const canvasH = (data.canvas && data.canvas.height) || 800;

        // Render at intrinsic canvas pixel size; #seat-modal-map scales via
        // CSS transform (applyMapTransform). This way 1:1 == 100% zoom.
        let svg = '<svg viewBox="0 0 ' + canvasW + ' ' + canvasH + '" width="' + canvasW + '" height="' + canvasH + '" xmlns="http://www.w3.org/2000/svg" style="display:block;">';

        data.sections.forEach(function (section) {
            const rotation = section.rotation || 0;
            const cx = (section.x || 0) + (section.width || 0) / 2;
            const cy = (section.y || 0) + (section.height || 0) / 2;
            const transform = rotation !== 0 ? ' transform="rotate(' + rotation + ' ' + cx + ' ' + cy + ')"' : '';

            svg += '<g' + transform + '>';

            // Icon sections (stage, exit, toilet, bar, etc.) — same rendering
            // as the customer map so the organizer sees venue orientation cues.
            if (section.section_type === 'icon') {
                svg += renderIconSection(section);
                svg += '</g>';
                return;
            }

            // Decorative sections (text labels, lines, polygons) — shapes the
            // designer added to outline stage/aisles/areas. Label-only so no
            // seat click logic.
            if (section.section_type === 'decorative') {
                svg += renderDecorativeSection(section);
                svg += '</g>';
                return;
            }

            if (!section.rows) {
                svg += '</g>';
                return;
            }

            const meta = section.metadata || {};
            const seatSize = parseInt(meta.seat_size) || 15;
            const seatRadius = seatSize / 2;
            const seatFontSize = Math.round(seatRadius * 0.85 * 10) / 10;

            // Row labels: align to section-wide leftmost/rightmost seat columns
            // (same approach the customer map uses — placed just outside the
            // first/last seat of each row). Skipped when section opts out.
            const allSeatXs = [];
            let seatGap = seatRadius * 3;
            let gapDetected = false;
            section.rows.forEach(function (_r) {
                if (!_r.seats) return;
                _r.seats.forEach(function (_s) { allSeatXs.push(_s.x || 0); });
                if (!gapDetected && _r.seats.length >= 2) {
                    const xs = _r.seats.map(function (s) { return s.x || 0; }).sort(function (a, b) { return a - b; });
                    seatGap = Math.abs(xs[1] - xs[0]);
                    gapDetected = true;
                }
            });
            const secMinX = allSeatXs.length > 0 ? Math.min.apply(null, allSeatXs) : 0;
            const secMaxX = allSeatXs.length > 0 ? Math.max.apply(null, allSeatXs) : 0;
            const leftLabelX = (section.x || 0) + secMinX - seatGap;
            const rightLabelX = (section.x || 0) + secMaxX + seatGap;
            const rowLabelSize = Math.max(10, Math.round(seatFontSize * 1.1 * 10) / 10);
            const autoShowRowLabels = (meta.auto_show_row_labels !== false);

            section.rows.forEach(function (row) {
                if (!row.seats || row.seats.length === 0) return;

                // Row labels at both ends (matches the customer map)
                if (autoShowRowLabels) {
                    const firstSeat = row.seats[0];
                    if (firstSeat) {
                        const rlY = (section.y || 0) + (firstSeat.y || 0) + seatRadius * 0.4;
                        svg += '<text x="' + leftLabelX + '" y="' + rlY + '" text-anchor="end" font-size="' + rowLabelSize + '" font-weight="600" fill="rgba(0,0,0,0.7)" class="pointer-events-none select-none">' + esc(row.label || '') + '</text>';
                        svg += '<text x="' + rightLabelX + '" y="' + rlY + '" text-anchor="start" font-size="' + rowLabelSize + '" font-weight="600" fill="rgba(0,0,0,0.7)" class="pointer-events-none select-none">' + esc(row.label || '') + '</text>';
                    }
                }

                row.seats.forEach(function (seat) {
                    const cx2 = (section.x || 0) + (seat.x || 0);
                    const cy2 = (section.y || 0) + (seat.y || 0);
                    const uid = seat.seat_uid;

                    // Seat circle — click handler attached in renderSeatModal.
                    // <title> gives the hover tooltip.
                    svg += '<circle data-seat-uid="' + esc(uid) + '"'
                        + ' data-section="' + esc(section.name || '') + '"'
                        + ' data-row="' + esc(row.label || '') + '"'
                        + ' data-seat="' + esc(seat.label || '') + '"'
                        + ' data-status="' + esc(seat.status || 'available') + '"'
                        + ' cx="' + cx2 + '" cy="' + cy2 + '" r="' + seatRadius + '"'
                        + ' stroke-width="1" style="cursor:pointer">'
                        + '<title>' + esc(section.name || '') + ' · Rând ' + esc(row.label || '') + ' · Loc ' + esc(seat.label || '') + '</title>'
                        + '</circle>';

                    // Seat number inside the circle (skip for unavailable —
                    // they render gray without a number, same as customer view)
                    const status = seat.status || 'available';
                    if (status === 'available' && seat.label) {
                        svg += '<text x="' + cx2 + '" y="' + (cy2 + seatRadius * 0.35) + '" text-anchor="middle" font-size="' + seatFontSize + '" font-weight="600" fill="white" class="pointer-events-none select-none">' + esc(seat.label) + '</text>';
                    }
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

        // Fit the map to the visible modal area on first open; subsequent
        // opens preserve the previous zoom/pan so organizers don't lose
        // their viewport when deselecting/closing.
        fitMapToScreen();
    }

    // ====== Pan / zoom (desktop drag + wheel, mobile pinch + drag) =====

    function applyMapTransform() {
        const host = $('seat-modal-map');
        if (!host) return;
        host.style.transform = 'translate(' + mapView.pan.x + 'px, ' + mapView.pan.y + 'px) scale(' + mapView.zoom + ')';
        const zl = $('seat-zoom-level');
        if (zl) zl.textContent = Math.round(mapView.zoom * 100) + '%';
    }

    function fitMapToScreen() {
        const body = $('seat-modal-body');
        const data = seatingData;
        if (!body || !data) return;
        const rect = body.getBoundingClientRect();
        if (rect.width === 0 || rect.height === 0) return;
        const canvasW = (data.canvas && data.canvas.width) || 1000;
        const canvasH = (data.canvas && data.canvas.height) || 800;
        const pad = 24;
        const fitZoom = Math.min((rect.width - pad) / canvasW, (rect.height - pad) / canvasH);
        mapView.zoom = Math.max(mapView.min, Math.min(mapView.max, fitZoom));
        mapView.pan.x = (rect.width - canvasW * mapView.zoom) / 2;
        mapView.pan.y = (rect.height - canvasH * mapView.zoom) / 2;
        applyMapTransform();
    }

    function zoomAt(delta, focalX, focalY) {
        const body = $('seat-modal-body');
        if (!body) return;
        if (typeof focalX !== 'number' || typeof focalY !== 'number') {
            const r = body.getBoundingClientRect();
            focalX = r.width / 2;
            focalY = r.height / 2;
        }
        const oldZoom = mapView.zoom;
        const newZoom = Math.max(mapView.min, Math.min(mapView.max, oldZoom + delta));
        if (newZoom === oldZoom) return;
        // Keep focal point stationary: newPan = focal − (focal − oldPan) * (newZoom / oldZoom)
        const ratio = newZoom / oldZoom;
        mapView.pan.x = focalX - (focalX - mapView.pan.x) * ratio;
        mapView.pan.y = focalY - (focalY - mapView.pan.y) * ratio;
        mapView.zoom = newZoom;
        applyMapTransform();
    }

    function setZoomAbsolute(newZoom, focalX, focalY) {
        const delta = Math.max(mapView.min, Math.min(mapView.max, newZoom)) - mapView.zoom;
        if (delta !== 0) zoomAt(delta, focalX, focalY);
    }

    let panZoomBound = false;
    function bindPanZoom() {
        if (panZoomBound) return;
        panZoomBound = true;

        const body = $('seat-modal-body');
        const host = $('seat-modal-map');
        if (!body || !host) return;

        // Zoom buttons
        $('seat-zoom-in').addEventListener('click', () => zoomAt(0.2));
        $('seat-zoom-out').addEventListener('click', () => zoomAt(-0.2));
        $('seat-zoom-reset').addEventListener('click', () => fitMapToScreen());

        // Mouse wheel — anchor at cursor
        body.addEventListener('wheel', (e) => {
            e.preventDefault();
            const r = body.getBoundingClientRect();
            const step = e.deltaY > 0 ? -0.15 : 0.15;
            zoomAt(step, e.clientX - r.left, e.clientY - r.top);
        }, { passive: false });

        // Mouse drag pan — skip when the user actually clicked a seat.
        // Clicks on circles are filtered via a "did the mouse move?" check:
        // tiny movements count as clicks and leave the seat toggle intact.
        let dragging = false;
        let startX, startY, startPanX, startPanY, movedEnough;
        body.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return;
            dragging = true;
            movedEnough = false;
            startX = e.clientX; startY = e.clientY;
            startPanX = mapView.pan.x; startPanY = mapView.pan.y;
            host.style.cursor = 'grabbing';
        });
        window.addEventListener('mousemove', (e) => {
            if (!dragging) return;
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            if (!movedEnough && (Math.abs(dx) > 3 || Math.abs(dy) > 3)) movedEnough = true;
            mapView.pan.x = startPanX + dx;
            mapView.pan.y = startPanY + dy;
            applyMapTransform();
        });
        window.addEventListener('mouseup', () => {
            if (!dragging) return;
            dragging = false;
            host.style.cursor = 'grab';
        });
        // If a drag actually happened, swallow the next click so it doesn't
        // toggle a seat the user was only panning past.
        body.addEventListener('click', (e) => {
            if (movedEnough) { e.stopPropagation(); e.preventDefault(); movedEnough = false; }
        }, true);

        // Touch: single-finger pan, two-finger pinch-zoom
        let pinchStartDist = 0;
        let pinchStartZoom = 1;
        let touchDragging = false;
        let tStartX, tStartY, tStartPanX, tStartPanY, tMoved;

        body.addEventListener('touchstart', (e) => {
            if (e.touches.length === 2) {
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                pinchStartDist = Math.hypot(dx, dy);
                pinchStartZoom = mapView.zoom;
                touchDragging = false;
                e.preventDefault();
            } else if (e.touches.length === 1) {
                touchDragging = true;
                tMoved = false;
                tStartX = e.touches[0].clientX;
                tStartY = e.touches[0].clientY;
                tStartPanX = mapView.pan.x;
                tStartPanY = mapView.pan.y;
            }
        }, { passive: false });

        body.addEventListener('touchmove', (e) => {
            if (e.touches.length === 2 && pinchStartDist > 0) {
                e.preventDefault();
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                const dist = Math.hypot(dx, dy);
                const scale = dist / pinchStartDist;
                const newZoom = Math.max(mapView.min, Math.min(mapView.max, pinchStartZoom * scale));
                const r = body.getBoundingClientRect();
                const focalX = ((e.touches[0].clientX + e.touches[1].clientX) / 2) - r.left;
                const focalY = ((e.touches[0].clientY + e.touches[1].clientY) / 2) - r.top;
                setZoomAbsolute(newZoom, focalX, focalY);
            } else if (e.touches.length === 1 && touchDragging) {
                const dx = e.touches[0].clientX - tStartX;
                const dy = e.touches[0].clientY - tStartY;
                if (!tMoved && (Math.abs(dx) > 4 || Math.abs(dy) > 4)) tMoved = true;
                if (tMoved) {
                    e.preventDefault();
                    mapView.pan.x = tStartPanX + dx;
                    mapView.pan.y = tStartPanY + dy;
                    applyMapTransform();
                }
            }
        }, { passive: false });

        body.addEventListener('touchend', (e) => {
            if (e.touches.length < 2) pinchStartDist = 0;
            if (e.touches.length === 0) {
                touchDragging = false;
                // Swallow the ghost click after a pan
                if (tMoved) {
                    const swallow = (ev) => { ev.stopPropagation(); ev.preventDefault(); body.removeEventListener('click', swallow, true); };
                    body.addEventListener('click', swallow, true);
                    setTimeout(() => body.removeEventListener('click', swallow, true), 400);
                    tMoved = false;
                }
            }
        });
    }

    function renderIconSection(section) {
        const metadata = section.metadata || {};
        const iconSize = metadata.icon_size || 40;
        const bgColor = metadata.background_color || section.color_hex || '#3B82F6';
        const iconColor = metadata.icon_color || '#FFFFFF';
        const iconX = section.x || 0;
        const iconY = section.y || 0;
        const radius = iconSize / 2;

        let out = '<circle cx="' + (iconX + radius) + '" cy="' + (iconY + radius) + '" r="' + radius + '" fill="' + bgColor + '"/>';

        if (section.icon_svg) {
            const innerSize = iconSize * 0.6;
            const iconOffset = (iconSize - innerSize) / 2;
            const raw = section.icon_svg;
            if (raw.indexOf('<svg') !== -1) {
                const vbMatch = raw.match(/viewBox="([^"]+)"/);
                const viewBox = vbMatch ? vbMatch[1] : '0 0 512 512';
                let innerMatch = raw.match(/<g[^>]*>([\s\S]*?)<\/g>/);
                let inner = innerMatch ? innerMatch[1] : '';
                if (!inner) {
                    innerMatch = raw.match(/<svg[^>]*>([\s\S]*?)<\/svg>/);
                    inner = innerMatch ? innerMatch[1] : '';
                }
                out += '<svg x="' + (iconX + iconOffset) + '" y="' + (iconY + iconOffset) + '" width="' + innerSize + '" height="' + innerSize + '" viewBox="' + viewBox + '">';
                out += '<g fill="' + iconColor + '">' + inner.replace(/fill="[^"]*"/g, 'fill="' + iconColor + '"') + '</g>';
                out += '</svg>';
            } else {
                out += '<svg x="' + (iconX + iconOffset) + '" y="' + (iconY + iconOffset) + '" width="' + innerSize + '" height="' + innerSize + '" viewBox="0 0 24 24">';
                out += '<path d="' + raw + '" fill="' + iconColor + '"/>';
                out += '</svg>';
            }
        }

        const labelY = iconY + iconSize + 12;
        const labelX = iconX + radius;
        out += '<text x="' + labelX + '" y="' + labelY + '" text-anchor="middle" font-size="10" font-weight="500" fill="#1F2937" style="text-shadow: 0 0 3px white, 0 0 3px white;">' + esc(section.icon_label || section.name || '') + '</text>';
        return out;
    }

    function renderDecorativeSection(section) {
        const metadata = section.metadata || {};
        const shape = metadata.shape || 'polygon';
        const opacity = parseFloat(metadata.opacity) || 0.3;
        const color = section.background_color || section.color_hex || '#10B981';
        let out = '';

        if (shape === 'polygon' && metadata.points) {
            const points = metadata.points;
            const minX = section.x || 0;
            const minY = section.y || 0;
            let svgPts = '';
            for (let i = 0; i < points.length; i += 2) {
                svgPts += (points[i] - minX) + ',' + (points[i + 1] - minY) + ' ';
            }
            out += '<g transform="translate(' + (section.x || 0) + ',' + (section.y || 0) + ')">';
            out += '<polygon points="' + svgPts.trim() + '" fill="' + color + '" opacity="' + opacity + '" stroke="' + color + '" stroke-width="1"/>';
            if (metadata.label || section.name) {
                out += '<text x="10" y="20" font-size="12" font-family="Arial" fill="#1f2937" opacity="0.8">' + esc(metadata.label || section.name) + '</text>';
            }
            out += '</g>';
        } else if (shape === 'text') {
            const fontSize = parseInt(metadata.fontSize) || 16;
            const fontFamily = metadata.fontFamily || 'Arial';
            const fontWeight = metadata.fontWeight || 'normal';
            const textContent = metadata.text || section.name || 'Text';
            const textY = (section.height > 0) ? (section.y || 0) + section.height / 2 : (section.y || 0) + fontSize;
            out += '<text x="' + (section.x || 0) + '" y="' + textY + '" dominant-baseline="central" font-size="' + fontSize + '" font-family="' + fontFamily + '" font-weight="' + fontWeight + '" fill="' + color + '">' + esc(textContent) + '</text>';
        } else if (shape === 'line') {
            const linePoints = metadata.points || [0, 0, 100, 0];
            const strokeWidth = parseInt(metadata.strokeWidth) || 2;
            const strokeColor = metadata.strokeColor || color;
            const x1 = (section.x || 0) + (linePoints[0] || 0);
            const y1 = (section.y || 0) + (linePoints[1] || 0);
            const x2 = (section.x || 0) + (linePoints[2] || 100);
            const y2 = (section.y || 0) + (linePoints[3] || 0);
            out += '<line x1="' + x1 + '" y1="' + y1 + '" x2="' + x2 + '" y2="' + y2 + '" stroke="' + strokeColor + '" stroke-width="' + strokeWidth + '" stroke-linecap="round"/>';
        }

        return out;
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
            fill = '#E84527';
            stroke = '#c23a20';
            clickable = true;
        } else {
            fill = '#1E4A3D';
            stroke = '#163a30';
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
        $('mode-manual').classList.toggle('bg-vermilion', mode === 'manual');
        $('mode-manual').classList.toggle('text-paper', mode === 'manual');
        $('mode-manual').classList.toggle('text-ink-soft', mode !== 'manual');
        $('mode-csv').classList.toggle('bg-vermilion', mode === 'csv');
        $('mode-csv').classList.toggle('text-paper', mode === 'csv');
        $('mode-csv').classList.toggle('text-ink-soft', mode !== 'csv');
    }

    function buildRecipientRows(qty) {
        const tbody = $('recipients-tbody');
        tbody.innerHTML = '';
        const inputCls = 'w-full rounded-lg border-2 border-ink/15 bg-paper-2 px-2.5 py-1.5 text-sm outline-none transition focus:border-ink';
        for (let i = 1; i <= qty; i++) {
            const tr = document.createElement('tr');
            tr.className = 'border-b border-ink/10';
            // When seated, show a locked seat cell pinned to this row's index.
            const seatCell = isSeated && selectedSeats[i - 1]
                ? '<td class="py-2 pr-3 align-middle whitespace-nowrap text-xs"><span class="inline-block rounded bg-vermilion/10 px-2 py-1 font-bold text-vermilion">' + esc(seatRefFor(selectedSeats[i - 1])) + '</span></td>'
                : (isSeated ? '<td class="py-2 pr-3 text-xs text-ink-soft"></td>' : '');
            tr.innerHTML =
                '<td class="py-2 pr-3 align-middle text-xs text-ink-soft">' + i + '</td>' +
                seatCell +
                '<td class="py-2 pr-3"><input class="' + inputCls + '" data-field="first_name" placeholder="Prenume"></td>' +
                '<td class="py-2 pr-3"><input class="' + inputCls + '" data-field="last_name" placeholder="Nume"></td>' +
                '<td class="py-2 pr-3"><input class="' + inputCls + '" type="email" data-field="email" placeholder="email@exemplu.ro"></td>' +
                '<td class="py-2 pr-3"><input class="' + inputCls + '" data-field="phone" placeholder="Telefon"></td>' +
                '<td class="py-2 pr-3"><input class="' + inputCls + '" data-field="company" placeholder="Companie"></td>' +
                '<td class="py-2 pr-3"><input class="' + inputCls + '" data-field="notes" placeholder="Note"></td>';
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
        // Emit one record per rendered row — all recipient fields are
        // optional now, so rows with blank inputs become {} and still
        // count toward the batch quantity.
        const rows = Array.from(document.querySelectorAll('#recipients-tbody tr'));
        return rows.map((row) => {
            const rec = {};
            row.querySelectorAll('input[data-field]').forEach(inp => {
                const v = (inp.value || '').trim();
                if (v !== '') rec[inp.dataset.field] = v;
            });
            return rec;
        });
    }

    async function onCsvTemplateDownload(e) {
        e.preventDefault();
        try {
            const res = await fetch(BileteOnlineAPI.getApiUrl() + '?action=organizer.invitations.csv-template', {
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

        // Seated activities: each row is tied to a specific picked seat (same
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
            const batchNameInput = $('batch-name-input');
            if (batchNameInput && batchNameInput.value.trim() !== '') {
                payload.name = batchNameInput.value.trim();
            }
            if (isSeated && seatingData && seatingData.event_seating_id) {
                payload.event_seating_id = seatingData.event_seating_id;
                payload.seats = selectedSeats;
            }
            const res = await BileteOnlineAPI.post('/organizer/invitations', payload);
            if (!res || !res.success) throw new Error((res && res.message) || 'Eroare la generare');
            const batch = res.data.batch;
            const rendered = res.data.rendered || 0;
            $('done-summary').textContent = rendered + ' invitații generate în seria "' + (batch.name || '') + '".';
            $('download-link').onclick = (e) => { e.preventDefault(); downloadZip(batch.id); };
            $('step-recipients').classList.add('hidden');
            $('step-done').classList.remove('hidden');
            // Seated activities: after a successful submit, the chosen seats are
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
            const url = BileteOnlineAPI.getApiUrl() + '?action=organizer.invitations.download&batch_id=' + encodeURIComponent(batchId);
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

    async function downloadInvite(batchId, inviteId, btn) {
        const original = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.innerHTML = '…'; }
        try {
            const url = BileteOnlineAPI.getApiUrl() + '?action=organizer.invitations.download-invite'
                + '&batch_id=' + encodeURIComponent(batchId)
                + '&invite_id=' + encodeURIComponent(inviteId);
            const res = await fetch(url, { headers: buildAuthHeaders() });
            if (!res.ok) {
                let msg = 'HTTP ' + res.status;
                try { const j = await res.json(); if (j && j.message) msg = j.message; } catch (_) {}
                throw new Error(msg);
            }
            const blob = await res.blob();
            const blobUrl = URL.createObjectURL(blob);
            const cd = res.headers.get('content-disposition') || '';
            const m = cd.match(/filename="?([^";]+)"?/i);
            const filename = m ? m[1] : ('invitatie-' + inviteId + '.pdf');
            const a = document.createElement('a');
            a.href = blobUrl;
            a.download = filename;
            document.body.appendChild(a); a.click(); a.remove();
            URL.revokeObjectURL(blobUrl);

            // Mark as downloaded inline (server already updated downloaded_at).
            const row = document.querySelector('[data-invite-row="' + inviteId + '"]');
            if (row && !row.querySelector('[data-downloaded-badge="' + inviteId + '"]')) {
                const codeCell = row.querySelector('td:nth-child(' + (row.querySelectorAll('td').length - 1) + ')');
                if (codeCell) {
                    const stamp = fmtDate(new Date().toISOString());
                    const span = document.createElement('span');
                    span.className = 'ml-2 inline-block rounded border border-forest/30 bg-forest/10 px-2 py-0.5 text-[10px] font-bold text-forest';
                    span.setAttribute('data-downloaded-badge', inviteId);
                    span.title = 'Descărcată la ' + stamp;
                    span.textContent = '✓ ' + stamp;
                    codeCell.appendChild(span);
                }
            }
        } catch (e) {
            alert('Descărcarea a eșuat: ' + (e.message || e));
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = original; }
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
            const res = await BileteOnlineAPI.get('/organizer/invitations?event_id=' + eventId + '&per_page=50');
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
            const isReady = (b.status === 'ready');
            const badgeCls = isReady
                ? 'inline-block rounded-full border border-forest/30 bg-forest/10 px-2.5 py-1 text-xs font-bold text-forest'
                : 'inline-block rounded-full border border-ink/20 bg-paper-2 px-2.5 py-1 text-xs font-bold text-ink-soft';
            const planned = b.qty_planned || 0;
            const rendered = b.qty_rendered || 0;
            const downloaded = b.qty_downloaded || 0;
            const row = document.createElement('div');
            row.className = 'flex flex-wrap items-center justify-between gap-3 py-3';
            row.innerHTML =
                '<div class="min-w-0">' +
                    '<p class="font-bold text-ink">' + esc(b.name) + '</p>' +
                    '<p class="text-xs text-ink-soft">' + created + ' · ' + planned + ' planificate · ' + rendered + ' generate · ' + downloaded + ' descărcate</p>' +
                '</div>' +
                '<div class="flex items-center gap-2">' +
                    '<span class="' + badgeCls + '">' + esc(b.status || '') + '</span>' +
                    '<button class="rounded-full px-3 py-1.5 text-sm font-bold text-ink-soft transition hover:bg-paper-2" data-view-invites="' + b.id + '">Vezi invitați</button>' +
                    '<button class="rounded-full border-2 border-ochre/40 px-3 py-1.5 text-sm font-bold text-ochre transition hover:bg-ochre/10" data-regen="' + b.id + '" title="Re-randează PDF-urile cu template-ul / datele actuale">Regenerează</button>' +
                    '<button class="rounded-full bg-vermilion px-4 py-1.5 text-sm font-bold text-paper transition hover:bg-vermilion-d" data-dl="' + b.id + '">Descarcă ZIP</button>' +
                '</div>' +
                '<div class="hidden w-full" id="invites-panel-' + b.id + '"></div>';
            host.appendChild(row);
        });

        host.querySelectorAll('[data-dl]').forEach(btn => btn.addEventListener('click', () => downloadZip(btn.dataset.dl)));
        host.querySelectorAll('[data-view-invites]').forEach(btn => btn.addEventListener('click', () => toggleInvites(btn.dataset.viewInvites)));
        host.querySelectorAll('[data-regen]').forEach(btn => btn.addEventListener('click', () => regenerateBatch(btn.dataset.regen, btn)));
    }

    async function regenerateBatch(batchId, btn) {
        if (!confirm('Se vor re-genera toate PDF-urile din această serie cu template-ul și datele actuale ale activității. Vrei să continui?')) return;
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Se regenerează…';
        try {
            const res = await BileteOnlineAPI.post('/organizer/invitations/' + batchId + '/generate', {});
            if (!res || !res.success) throw new Error((res && res.message) || 'Eroare la regenerare');
            alert((res.data?.rendered || 0) + ' invitații re-randate. Click „Descarcă ZIP" pentru a le re-descărca.');
            await loadHistory();
        } catch (e) {
            alert('Regenerarea a eșuat: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    }

    async function onDeleteInvite(batchId, inviteId) {
        if (!confirm('Sigur vrei să ștergi această invitație? Locul rezervat va fi eliberat pe hartă și biletul va fi invalidat.')) return;
        try {
            const res = await BileteOnlineAPI.delete('/organizer/invitations/' + batchId + '/invites', {
                invite_ids: [parseInt(inviteId, 10)],
            });
            if (!res || !res.success) throw new Error((res && res.message) || 'Eroare la ștergere');
            // Drop the row from the DOM; if the batch is gone, reload history
            const row = document.querySelector('[data-invite-row="' + inviteId + '"]');
            if (row) row.remove();
            if (res.data && res.data.batch_remaining === 0) {
                // Batch was deleted server-side — refresh the history list
                await loadHistory();
            }
        } catch (e) {
            alert('Ștergerea a eșuat: ' + (e.message || e));
        }
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
        panel.innerHTML = '<p class="py-2 text-sm text-ink-soft">Se încarcă…</p>';
        try {
            const res = await BileteOnlineAPI.get('/organizer/invitations/' + batchId);
            const invites = (res && res.data && res.data.invites) ? res.data.invites : [];
            if (invites.length === 0) {
                panel.innerHTML = '<p class="py-2 text-sm text-ink-soft">Fără invitați.</p>';
                return;
            }
            const hasSeats = invites.some(i => i.seat_ref || (i.recipient && i.recipient.seat));
            const rowsHtml = invites.map(i => {
                const r = i.recipient || {};
                const seat = r.seat || null;
                const seatRef = i.seat_ref
                    || (seat ? seatRefFor({ section_name: seat.section, row_label: seat.row, seat_label: seat.label, seat_uid: seat.uid }) : '');
                const downloadedBadge = i.downloaded_at
                    ? '<span class="ml-2 inline-block rounded border border-forest/30 bg-forest/10 px-2 py-0.5 text-[10px] font-bold text-forest" data-downloaded-badge="' + i.id + '" title="Descărcată la ' + esc(fmtDate(i.downloaded_at)) + '">✓ ' + esc(fmtDate(i.downloaded_at)) + '</span>'
                    : '';
                const downloadBtn = i.has_pdf
                    ? '<button class="mr-3 text-xs font-bold text-sky transition hover:opacity-80" data-dl-invite="' + i.id + '" data-batch-id="' + batchId + '" title="Descarcă PDF-ul invitației">Descarcă</button>'
                    : '<span class="mr-3 text-xs text-ink-soft" title="PDF indisponibil — regenerează batch-ul">PDF lipsă</span>';
                return '<tr class="border-b border-ink/10" data-invite-row="' + i.id + '">' +
                    '<td class="py-1.5 pr-3">' + esc(r.name || '') + '</td>' +
                    '<td class="py-1.5 pr-3">' + esc(r.email || '') + '</td>' +
                    (hasSeats ? '<td class="py-1.5 pr-3">' + esc(seatRef || '—') + '</td>' : '') +
                    '<td class="py-1.5 pr-3">' + esc(r.phone || '') + '</td>' +
                    '<td class="py-1.5 pr-3">' + esc(r.company || '') + '</td>' +
                    '<td class="py-1.5 pr-3"><code class="text-xs">' + esc(i.code) + '</code>' + downloadedBadge + '</td>' +
                    '<td class="py-1.5 pr-3 text-right whitespace-nowrap">' +
                        downloadBtn +
                        '<button class="text-xs font-bold text-vermilion transition hover:opacity-80" data-del-invite="' + i.id + '" data-batch-id="' + batchId + '" title="Șterge invitația și eliberează locul">Șterge</button>' +
                    '</td>' +
                '</tr>';
            }).join('');
            panel.innerHTML =
                '<div class="mt-3 overflow-x-auto rounded-xl bg-paper-2 p-3">' +
                    '<table class="w-full text-sm">' +
                        '<thead><tr class="text-left text-xs uppercase text-ink-soft">' +
                            '<th class="py-1.5 pr-3">Nume</th>' +
                            '<th class="py-1.5 pr-3">Email</th>' +
                            (hasSeats ? '<th class="py-1.5 pr-3">Loc</th>' : '') +
                            '<th class="py-1.5 pr-3">Telefon</th>' +
                            '<th class="py-1.5 pr-3">Companie</th>' +
                            '<th class="py-1.5 pr-3">Cod</th>' +
                            '<th class="py-1.5 pr-3 text-right">Acțiuni</th>' +
                        '</tr></thead>' +
                        '<tbody>' + rowsHtml + '</tbody>' +
                    '</table>' +
                '</div>';
            panel.querySelectorAll('[data-del-invite]').forEach(btn => {
                btn.addEventListener('click', () => onDeleteInvite(btn.dataset.batchId, btn.dataset.delInvite));
            });
            panel.querySelectorAll('[data-dl-invite]').forEach(btn => {
                btn.addEventListener('click', () => downloadInvite(btn.dataset.batchId, btn.dataset.dlInvite, btn));
            });
        } catch (e) {
            panel.innerHTML = '<p class="py-2 text-sm text-vermilion">Nu pot încărca invitații: ' + esc(e.message) + '</p>';
        }
    }
})();
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
