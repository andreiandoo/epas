<?php
/**
 * Booking conversation page (guest-side).
 *
 * Cumpărătorul (organizator/agenție/etc.) ajunge aici dintr-un email cu link
 * signed: /booking/conversation/{token}. Vede thread-ul și poate răspunde
 * (mesaj / contraofertă / accept / refuz).
 *
 * Token-ul e trecut prin .htaccess RewriteRule și ajunge în $_GET['token'].
 * Toate apelurile API trec prin proxy.php (action: public.booking.conversation.*).
 */
require_once __DIR__ . '/../includes/config.php';

$token = preg_replace('/[^A-Za-z0-9]/', '', $_GET['token'] ?? '');

$pageTitle = 'Conversație booking — ' . SITE_NAME;
$pageDescription = 'Vezi statusul cererii tale de booking și răspunde artistului.';
$bodyClass = 'min-h-screen bg-surface font-sans';

$cssBundle = 'single';
require_once __DIR__ . '/../includes/head.php';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .bk-msg-artist { background: #FEF2F2; border-color: #FECACA; }
    .bk-msg-guest { background: #F1F5F9; border-color: #E2E8F0; }
    .bk-input { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #E2E8F0; border-radius: 0.5rem; font-size: 0.875rem; background: white; }
    .bk-input:focus { outline: none; border-color: #A51C30; box-shadow: 0 0 0 3px rgba(165,28,48,0.1); }
    .bk-textarea { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #E2E8F0; border-radius: 0.5rem; font-size: 0.875rem; background: white; resize: vertical; min-height: 80px; }
    .bk-btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.625rem 1.25rem; border-radius: 0.75rem; font-weight: 600; font-size: 0.875rem; transition: all 0.15s; cursor: pointer; border: none; }
    .bk-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .bk-btn-primary { background: #A51C30; color: white; }
    .bk-btn-primary:hover:not(:disabled) { background: #8B1728; }
    .bk-btn-secondary { background: white; color: #1E293B; border: 1px solid #E2E8F0; }
    .bk-btn-secondary:hover:not(:disabled) { background: #F8FAFC; }
    .bk-btn-success { background: #16A34A; color: white; }
    .bk-btn-success:hover:not(:disabled) { background: #15803D; }
    .bk-btn-danger { background: #DC2626; color: white; }
    .bk-btn-danger:hover:not(:disabled) { background: #B91C1C; }
</style>

<main class="px-4 py-8 lg:py-12">
    <div class="max-w-4xl mx-auto" id="bookingConversation">
        <!-- Loading state -->
        <div id="convLoading" class="p-12 text-center bg-white border rounded-2xl border-border text-muted">
            <span class="inline-block w-5 h-5 border-2 rounded-full border-primary border-t-transparent animate-spin"></span>
            <span class="ml-2">Se încarcă conversația...</span>
        </div>

        <!-- Error state -->
        <div id="convError" class="hidden p-8 text-center bg-white border rounded-2xl border-border">
            <p id="convErrorMsg" class="text-base font-semibold text-rose-600">Conversație inexistentă sau expirată.</p>
            <p class="mt-2 text-sm text-muted">Link-ul nu mai e valid. Trimite o cerere nouă de pe profilul artistului.</p>
        </div>

        <!-- Loaded conversation -->
        <div id="convContent" class="hidden space-y-6">
            <header class="p-6 bg-white border rounded-2xl border-border">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold tracking-wider uppercase text-muted">Cerere booking pentru</p>
                        <h1 id="convArtistName" class="mt-1 text-2xl font-bold text-secondary"></h1>
                        <p class="mt-1 text-sm text-muted">
                            <span id="convEventDate"></span>
                            <span id="convEventCity"></span>
                            <span id="convEventVenue"></span>
                        </p>
                    </div>
                    <span id="convStatus" class="px-3 py-1 text-xs font-semibold rounded-full whitespace-nowrap"></span>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-4 lg:grid-cols-4">
                    <div>
                        <p class="text-[11px] font-semibold tracking-wider uppercase text-muted">Tip</p>
                        <p id="convEventType" class="mt-1 text-sm font-semibold text-secondary"></p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold tracking-wider uppercase text-muted">Audiență</p>
                        <p id="convEventAudience" class="mt-1 text-sm font-semibold text-secondary"></p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold tracking-wider uppercase text-muted">Cachet propus</p>
                        <p id="convEventFee" class="mt-1 text-sm font-bold text-primary"></p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold tracking-wider uppercase text-muted">Set</p>
                        <p id="convEventSet" class="mt-1 text-sm font-semibold text-secondary"></p>
                    </div>
                </div>

                <div id="convFinalTermsBlock" class="hidden p-4 mt-4 border rounded-xl bg-emerald-50 border-emerald-200">
                    <p class="text-xs font-bold tracking-wider uppercase text-emerald-700">✓ Booking confirmat — termeni acceptați</p>
                    <div class="grid grid-cols-3 gap-3 mt-2 text-sm">
                        <div>
                            <p class="text-xs text-emerald-700">Dată finală</p>
                            <p id="convFinalDate" class="font-semibold text-emerald-900"></p>
                        </div>
                        <div>
                            <p class="text-xs text-emerald-700">Cachet final</p>
                            <p id="convFinalFee" class="font-semibold text-emerald-900"></p>
                        </div>
                        <div>
                            <p class="text-xs text-emerald-700">Set</p>
                            <p id="convFinalSet" class="font-semibold text-emerald-900"></p>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Thread -->
            <section class="p-6 bg-white border rounded-2xl border-border">
                <h2 class="mb-4 text-base font-bold text-secondary">Conversație</h2>
                <div id="convThread" class="space-y-3"></div>
            </section>

            <!-- Reply panel (hidden when accepted/rejected/expired) -->
            <section id="convReplyPanel" class="hidden p-6 bg-white border rounded-2xl border-border">
                <div class="flex flex-wrap gap-2 mb-3">
                    <button data-mode="message" class="conv-mode-btn bk-btn bk-btn-primary">💬 Mesaj</button>
                    <button data-mode="counter" class="conv-mode-btn bk-btn bk-btn-secondary">🔄 Contraofertă</button>
                    <button data-mode="accept" class="conv-mode-btn bk-btn bk-btn-secondary">✓ Acceptă</button>
                    <button data-mode="reject" class="conv-mode-btn bk-btn bk-btn-secondary">✕ Refuză</button>
                </div>

                <div data-mode-pane="message">
                    <textarea id="convMsgBody" placeholder="Scrie un mesaj artistului..." class="bk-textarea"></textarea>
                    <div class="flex justify-end mt-3">
                        <button id="convSendMessage" class="bk-btn bk-btn-primary">Trimite mesaj</button>
                    </div>
                </div>

                <div data-mode-pane="counter" class="hidden space-y-3">
                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                        <div>
                            <label class="block mb-1 text-xs font-semibold text-muted">Cachet propus (RON)</label>
                            <input type="number" id="convCounterFee" min="0" step="100" class="bk-input">
                        </div>
                        <div>
                            <label class="block mb-1 text-xs font-semibold text-muted">Set length (min)</label>
                            <input type="number" id="convCounterSet" min="15" max="600" step="5" class="bk-input">
                        </div>
                        <div>
                            <label class="block mb-1 text-xs font-semibold text-muted">Dată propusă</label>
                            <input type="date" id="convCounterDate" class="bk-input">
                        </div>
                    </div>
                    <textarea id="convCounterBody" placeholder="Notă pentru artist (opțional)..." class="bk-textarea"></textarea>
                    <div class="flex justify-end">
                        <button id="convSendCounter" class="bk-btn bk-btn-primary">Trimite contraofertă</button>
                    </div>
                </div>

                <div data-mode-pane="accept" class="hidden space-y-3">
                    <div class="p-3 border rounded-lg bg-emerald-50 border-emerald-200">
                        <p class="text-sm font-semibold text-emerald-900">Vei accepta termenii curenți. Booking-ul devine confirmat după acceptare reciprocă.</p>
                        <p class="mt-1 text-xs text-emerald-700">Plata se face în afara platformei conform acordului direct cu artistul.</p>
                    </div>
                    <textarea id="convAcceptBody" placeholder="Mesaj de confirmare (opțional)..." class="bk-textarea"></textarea>
                    <div class="flex justify-end gap-2">
                        <button id="convSendAccept" class="bk-btn bk-btn-success">✓ Confirmă acceptare</button>
                    </div>
                </div>

                <div data-mode-pane="reject" class="hidden space-y-3">
                    <div class="p-3 border rounded-lg bg-rose-50 border-rose-200">
                        <p class="text-sm font-semibold text-rose-900">Vei refuza această cerere.</p>
                        <p class="mt-1 text-xs text-rose-700">Artistul va primi notificare.</p>
                    </div>
                    <textarea id="convRejectBody" placeholder="Motiv (opțional)..." class="bk-textarea"></textarea>
                    <div class="flex justify-end gap-2">
                        <button id="convSendReject" class="bk-btn bk-btn-danger">✕ Confirmă refuz</button>
                    </div>
                </div>

                <div id="convPanelError" class="hidden p-3 mt-3 text-sm font-medium text-red-700 border border-red-200 bg-red-50 rounded-lg"></div>
            </section>

            <p class="text-xs text-center text-muted">
                Plata se face în afara platformei conform acordului direct cu artistul. Tixello nu colectează bani și nu ia comision pe această tranzacție.
            </p>
        </div>
    </div>
</main>

<script>
(function () {
    'use strict';

    const TOKEN = <?php echo json_encode($token); ?>;

    if (!TOKEN || TOKEN.length < 10) {
        document.getElementById('convLoading').classList.add('hidden');
        document.getElementById('convError').classList.remove('hidden');
        return;
    }

    const els = {
        loading: document.getElementById('convLoading'),
        error: document.getElementById('convError'),
        errorMsg: document.getElementById('convErrorMsg'),
        content: document.getElementById('convContent'),
        artistName: document.getElementById('convArtistName'),
        eventDate: document.getElementById('convEventDate'),
        eventCity: document.getElementById('convEventCity'),
        eventVenue: document.getElementById('convEventVenue'),
        eventType: document.getElementById('convEventType'),
        eventAudience: document.getElementById('convEventAudience'),
        eventFee: document.getElementById('convEventFee'),
        eventSet: document.getElementById('convEventSet'),
        status: document.getElementById('convStatus'),
        thread: document.getElementById('convThread'),
        finalBlock: document.getElementById('convFinalTermsBlock'),
        finalDate: document.getElementById('convFinalDate'),
        finalFee: document.getElementById('convFinalFee'),
        finalSet: document.getElementById('convFinalSet'),
        replyPanel: document.getElementById('convReplyPanel'),
        panelError: document.getElementById('convPanelError'),
    };

    const STATUS_LABELS = { new: 'Nouă', viewed: 'Văzută', negotiating: 'Negociere', accepted: 'Acceptată', rejected: 'Refuzată', expired: 'Expirată' };
    const STATUS_CLASSES = {
        new: 'bg-blue-100 text-blue-800',
        viewed: 'bg-slate-100 text-slate-700',
        negotiating: 'bg-amber-100 text-amber-800',
        accepted: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-rose-100 text-rose-800',
        expired: 'bg-slate-200 text-slate-600',
    };
    const EVENT_TYPES = { concert: 'Concert', festival: 'Festival', private: 'Eveniment privat', corporate: 'Corporate', wedding: 'Nuntă', club: 'Club / lounge' };
    const FINAL_STATUSES = ['accepted', 'rejected', 'expired'];

    function fmtNumber(n) {
        if (n === null || n === undefined || n === '') return '0';
        return new Intl.NumberFormat('ro-RO').format(n);
    }
    function escapeHtml(s) {
        if (!s) return '';
        return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
    }

    async function fetchConversation() {
        try {
            const r = await fetch('/api/proxy.php?action=public.booking.conversation.view&token=' + encodeURIComponent(TOKEN), {
                headers: { 'Accept': 'application/json' },
            });
            if (!r.ok) {
                els.loading.classList.add('hidden');
                els.error.classList.remove('hidden');
                return;
            }
            const payload = await r.json();
            renderConversation(payload.data);
            els.loading.classList.add('hidden');
            els.content.classList.remove('hidden');
        } catch (e) {
            els.loading.classList.add('hidden');
            els.error.classList.remove('hidden');
        }
    }

    function renderConversation(d) {
        if (!d) return;
        els.artistName.textContent = d.artist?.name || 'Artist';
        els.eventDate.textContent = d.event?.date || '';
        els.eventCity.textContent = d.event?.city ? ' · ' + d.event.city : '';
        els.eventVenue.textContent = d.event?.venue ? ' · ' + d.event.venue : '';
        els.eventType.textContent = EVENT_TYPES[d.event?.type] || (d.event?.type || '—');
        els.eventAudience.textContent = d.event?.audience ? fmtNumber(d.event.audience) : '—';
        els.eventFee.textContent = fmtNumber(d.event?.fee_ron) + ' RON';
        els.eventSet.textContent = (d.event?.set_length_min || 0) + ' min';

        const statusLabel = STATUS_LABELS[d.status] || d.status;
        els.status.textContent = statusLabel;
        els.status.className = 'px-3 py-1 text-xs font-semibold rounded-full whitespace-nowrap ' + (STATUS_CLASSES[d.status] || 'bg-slate-100 text-slate-700');

        if (d.status === 'accepted' && d.final_terms) {
            els.finalBlock.classList.remove('hidden');
            els.finalDate.textContent = d.final_terms.event_date || '';
            els.finalFee.textContent = fmtNumber(d.final_terms.fee_ron) + ' RON';
            els.finalSet.textContent = (d.final_terms.set_length_min || 0) + ' min';
        }

        // Thread: initial guest message + thread items
        const items = [];
        items.push({
            sender_type: 'guest',
            type: 'message',
            body: d.initial_message,
            time: '— inițial —',
            is_initial: true,
        });
        (d.thread || []).forEach(m => items.push(m));
        els.thread.innerHTML = items.map(renderMsg).join('');

        // Show reply panel only if not finalized
        if (!FINAL_STATUSES.includes(d.status)) {
            els.replyPanel.classList.remove('hidden');
            // Pre-fill counter form with current event values
            const lastCounter = (d.thread || []).filter(m => m.type === 'counter' && m.counter_terms).pop();
            const baseFee = (lastCounter?.counter_terms?.fee_ron) || d.event?.fee_ron || 0;
            const baseSet = (lastCounter?.counter_terms?.set_length_min) || d.event?.set_length_min || 60;
            const baseDate = (lastCounter?.counter_terms?.event_date) || d.event?.date_iso || '';
            document.getElementById('convCounterFee').value = baseFee;
            document.getElementById('convCounterSet').value = baseSet;
            document.getElementById('convCounterDate').value = baseDate;
        }
    }

    function renderMsg(m) {
        const isArtist = m.sender_type === 'artist';
        const wrapClass = isArtist ? 'bk-msg-artist' : 'bk-msg-guest';
        const senderLabel = isArtist ? 'Artist' : 'Tu';
        const typeBadge = m.type === 'counter' ? '<span class="px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase rounded-full bg-amber-100 text-amber-800">contraofertă</span>' :
                          m.type === 'accept' ? '<span class="px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase rounded-full bg-emerald-100 text-emerald-800">acceptat</span>' :
                          m.type === 'reject' ? '<span class="px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase rounded-full bg-rose-100 text-rose-800">refuzat</span>' : '';
        let counterBlock = '';
        if (m.type === 'counter' && m.counter_terms) {
            counterBlock = '<div class="grid grid-cols-3 gap-3 p-3 mt-3 rounded-lg bg-white/60">' +
                (m.counter_terms.fee_ron ? '<div><p class="text-[10px] tracking-wider uppercase text-muted">Cachet</p><p class="text-sm font-bold text-secondary">' + fmtNumber(m.counter_terms.fee_ron) + ' RON</p></div>' : '') +
                (m.counter_terms.set_length_min ? '<div><p class="text-[10px] tracking-wider uppercase text-muted">Set</p><p class="text-sm font-bold text-secondary">' + m.counter_terms.set_length_min + ' min</p></div>' : '') +
                (m.counter_terms.event_date ? '<div><p class="text-[10px] tracking-wider uppercase text-muted">Dată</p><p class="text-sm font-bold text-secondary">' + escapeHtml(m.counter_terms.event_date) + '</p></div>' : '') +
                '</div>';
        }
        return '<div class="p-4 border rounded-xl ' + wrapClass + '">' +
            '<div class="flex items-center gap-2 mb-2">' +
                '<p class="text-sm font-semibold text-secondary">' + senderLabel + '</p>' +
                typeBadge +
                '<span class="ml-auto text-[11px] text-muted">' + escapeHtml(m.time || '') + '</span>' +
            '</div>' +
            (m.body ? '<p class="text-sm whitespace-pre-line text-secondary">' + escapeHtml(m.body) + '</p>' : '') +
            counterBlock +
            '</div>';
    }

    function setMode(mode) {
        document.querySelectorAll('.conv-mode-btn').forEach(b => {
            const isActive = b.dataset.mode === mode;
            b.classList.remove('bk-btn-primary', 'bk-btn-secondary', 'bk-btn-success', 'bk-btn-danger');
            if (isActive) {
                if (mode === 'accept') b.classList.add('bk-btn-success');
                else if (mode === 'reject') b.classList.add('bk-btn-danger');
                else b.classList.add('bk-btn-primary');
            } else {
                b.classList.add('bk-btn-secondary');
            }
        });
        document.querySelectorAll('[data-mode-pane]').forEach(p => {
            p.classList.toggle('hidden', p.dataset.modePane !== mode);
        });
    }

    async function postMessage(payload) {
        els.panelError.classList.add('hidden');
        try {
            const r = await fetch('/api/proxy.php?action=public.booking.conversation.post&token=' + encodeURIComponent(TOKEN), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const text = await r.text();
            let data = null;
            try { data = JSON.parse(text); } catch (_) {}
            if (!r.ok) {
                const msg = (data && (data.error || data.message)) || ('Eroare ' + r.status);
                els.panelError.textContent = msg;
                els.panelError.classList.remove('hidden');
                return false;
            }
            return true;
        } catch (err) {
            els.panelError.textContent = 'Conexiune eșuată. Reîncearcă.';
            els.panelError.classList.remove('hidden');
            return false;
        }
    }

    function bindActions() {
        document.querySelectorAll('.conv-mode-btn').forEach(b => {
            b.addEventListener('click', () => setMode(b.dataset.mode));
        });
        document.getElementById('convSendMessage').addEventListener('click', async () => {
            const body = document.getElementById('convMsgBody').value.trim();
            if (!body) return;
            const ok = await postMessage({ type: 'message', body });
            if (ok) { document.getElementById('convMsgBody').value = ''; await fetchConversation(); }
        });
        document.getElementById('convSendCounter').addEventListener('click', async () => {
            const fee = parseInt(document.getElementById('convCounterFee').value, 10) || 0;
            const set = parseInt(document.getElementById('convCounterSet').value, 10) || 60;
            const date = document.getElementById('convCounterDate').value;
            const body = document.getElementById('convCounterBody').value.trim();
            if (!fee) {
                els.panelError.textContent = 'Cachetul propus trebuie să fie mai mare decât 0.';
                els.panelError.classList.remove('hidden');
                return;
            }
            const ok = await postMessage({
                type: 'counter',
                body: body || null,
                counter_terms: { fee_ron: fee, set_length_min: set, event_date: date || undefined },
            });
            if (ok) { document.getElementById('convCounterBody').value = ''; await fetchConversation(); }
        });
        document.getElementById('convSendAccept').addEventListener('click', async () => {
            if (!confirm('Confirmi acceptarea termenilor? Booking-ul va fi marcat ca acceptat după ce ambele părți acceptă.')) return;
            const body = document.getElementById('convAcceptBody').value.trim();
            const ok = await postMessage({ type: 'accept', body: body || null });
            if (ok) { document.getElementById('convAcceptBody').value = ''; await fetchConversation(); }
        });
        document.getElementById('convSendReject').addEventListener('click', async () => {
            if (!confirm('Confirmi refuzul cererii?')) return;
            const body = document.getElementById('convRejectBody').value.trim();
            const ok = await postMessage({ type: 'reject', body: body || null });
            if (ok) { document.getElementById('convRejectBody').value = ''; await fetchConversation(); }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        bindActions();
        fetchConversation();
    });
})();
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
require_once __DIR__ . '/../includes/scripts.php';
?>
