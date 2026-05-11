<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Echipă & schimburi';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_team';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl">👨‍💼 Echipă & schimburi</h1>
                <p class="mt-1 text-sm text-muted">Calendar săptămânal: alocă membrii echipei pe zile cu rol și poartă.</p>
            </div>
            <div class="flex items-center gap-2">
                <button id="lv-prev-week" class="px-3 py-1.5 text-sm bg-white border border-border rounded-lg hover:bg-slate-50">←</button>
                <span id="lv-week-label" class="text-sm font-semibold text-secondary px-2"></span>
                <button id="lv-next-week" class="px-3 py-1.5 text-sm bg-white border border-border rounded-lg hover:bg-slate-50">→</button>
                <button id="lv-today" class="ml-2 px-3 py-1.5 text-sm bg-primary text-white rounded-lg">Azi</button>
            </div>
        </div>

        <div id="lv-error" class="hidden mb-4 p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-900"></div>

        <div class="bg-white border rounded-2xl border-border overflow-x-auto">
            <div id="lv-loading" class="p-8 text-center"><div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div></div>
            <table id="lv-grid" class="hidden w-full text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-muted">
                    <tr>
                        <th class="px-3 py-3 text-left w-40">Membru</th>
                        <th class="px-2 py-3 text-center" data-d="0">Lun</th>
                        <th class="px-2 py-3 text-center" data-d="1">Mar</th>
                        <th class="px-2 py-3 text-center" data-d="2">Mie</th>
                        <th class="px-2 py-3 text-center" data-d="3">Joi</th>
                        <th class="px-2 py-3 text-center" data-d="4">Vin</th>
                        <th class="px-2 py-3 text-center" data-d="5">Sâm</th>
                        <th class="px-2 py-3 text-center" data-d="6">Dum</th>
                    </tr>
                </thead>
                <tbody id="lv-body" class="divide-y divide-border"></tbody>
            </table>
            <div id="lv-empty" class="hidden p-8 text-center text-muted">
                Niciun membru activ în echipa ta. Adaugă membri din pagina <a href="/organizator/echipa" class="text-primary underline">Echipă</a>.
            </div>
        </div>

        <div class="mt-4 text-xs text-muted">
            💡 Apasă pe o celulă goală ca să adaugi o turnetă. Click pe o turnetă existentă ca s-o editezi.
        </div>
    </main>
</div>

<!-- Modal turnetă -->
<div id="lv-modal" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-border max-w-md w-full p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 id="lv-modal-title" class="font-bold text-secondary text-lg">Turnetă</h2>
            <button id="lv-modal-close" class="text-muted hover:text-secondary">✕</button>
        </div>
        <div class="space-y-3">
            <label class="block">
                <span class="text-xs font-semibold text-muted">Membru</span>
                <select id="lv-f-member" class="block mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg"></select>
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block">
                    <span class="text-xs font-semibold text-muted">Start</span>
                    <input id="lv-f-start" type="datetime-local" class="block mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg">
                </label>
                <label class="block">
                    <span class="text-xs font-semibold text-muted">Sfârșit</span>
                    <input id="lv-f-end" type="datetime-local" class="block mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg">
                </label>
            </div>
            <label class="block">
                <span class="text-xs font-semibold text-muted">Rol</span>
                <select id="lv-f-role" class="block mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg">
                    <option value="gate_scanner">Scanner poartă</option>
                    <option value="sales_operator">Operator vânzări</option>
                    <option value="shift_manager">Manager schimb</option>
                    <option value="accountant">Contabil</option>
                </select>
            </label>
            <label class="block">
                <span class="text-xs font-semibold text-muted">Poartă (opțional)</span>
                <input id="lv-f-gate" type="text" placeholder="A, B, Parcare…" class="block mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg">
            </label>
            <label class="block">
                <span class="text-xs font-semibold text-muted">Notițe (opțional)</span>
                <textarea id="lv-f-notes" rows="2" class="block mt-1 w-full px-3 py-2 text-sm border border-border rounded-lg"></textarea>
            </label>
        </div>
        <div class="mt-5 flex justify-between">
            <button id="lv-f-delete" class="hidden px-3 py-2 text-sm text-rose-600 hover:bg-rose-50 rounded-lg">🗑 Șterge</button>
            <div class="ml-auto flex gap-2">
                <button id="lv-f-cancel" class="px-3 py-2 text-sm border border-border rounded-lg hover:bg-slate-50">Renunță</button>
                <button id="lv-f-save" class="px-4 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary-dark">Salvează</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const $ = (id) => document.getElementById(id);
    const ROLE_LABEL = { gate_scanner: 'Scanner', sales_operator: 'Vânzări', shift_manager: 'Manager', accountant: 'Contabil' };
    const ROLE_COLOR = { gate_scanner: 'emerald', sales_operator: 'amber', shift_manager: 'violet', accountant: 'blue' };

    let currentEventId = null;
    let weekStart = startOfWeek(new Date());
    let members = [];
    let shifts = [];
    let editing = null;

    function startOfWeek(d) {
        const r = new Date(d);
        const dow = r.getDay(); // 0=sun..6=sat
        const diff = (dow + 6) % 7; // distance to monday
        r.setDate(r.getDate() - diff);
        r.setHours(0,0,0,0);
        return r;
    }

    function fmtDay(d) { return d.toLocaleDateString('ro-RO', { day: '2-digit', month: 'short' }); }
    function fmtWeekLabel(s) {
        const end = new Date(s); end.setDate(end.getDate() + 6);
        return `${fmtDay(s)} → ${fmtDay(end)}, ${s.getFullYear()}`;
    }
    function isoDate(d) { return d.toISOString().slice(0,10); }
    function toLocalDateTime(d) {
        const pad = (n) => String(n).padStart(2,'0');
        return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    async function loadWeek() {
        $('lv-error').classList.add('hidden');
        $('lv-loading').classList.remove('hidden');
        $('lv-grid').classList.add('hidden');
        $('lv-empty').classList.add('hidden');
        $('lv-week-label').textContent = fmtWeekLabel(weekStart);

        if (!currentEventId) {
            $('lv-loading').classList.add('hidden');
            $('lv-empty').classList.remove('hidden');
            return;
        }
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/shifts`, { week: isoDate(weekStart) });
            const data = res.data || {};
            members = data.members || [];
            shifts = data.shifts || [];
            renderGrid();
        } catch (e) {
            console.error('[leisure-team] load failed', e);
            $('lv-error').textContent = 'Eroare la încărcare: ' + (e?.message || '');
            $('lv-error').classList.remove('hidden');
        } finally {
            $('lv-loading').classList.add('hidden');
        }
    }

    function renderGrid() {
        if (!members.length) {
            $('lv-empty').classList.remove('hidden');
            return;
        }
        // Group shifts by member_id × day_index (0..6 from monday)
        const grid = {};
        for (const s of shifts) {
            const start = new Date(s.start_at);
            const day = Math.floor((start - weekStart) / 86400000);
            if (day < 0 || day > 6) continue;
            const key = `${s.team_member_id || 'unassigned'}_${day}`;
            (grid[key] = grid[key] || []).push(s);
        }
        const rows = members.map(m => {
            const cells = Array.from({ length: 7 }, (_, d) => {
                const list = grid[`${m.id}_${d}`] || [];
                const date = new Date(weekStart); date.setDate(date.getDate() + d);
                const chips = list.map(s => {
                    const c = ROLE_COLOR[s.role] || 'slate';
                    const t1 = new Date(s.start_at).toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
                    const t2 = new Date(s.end_at).toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
                    const gateLabel = s.gate ? ` · ${s.gate}` : '';
                    return `<div data-shift-id="${s.id}" class="lv-chip cursor-pointer text-[10px] px-1.5 py-1 bg-${c}-100 text-${c}-900 rounded mb-1 hover:bg-${c}-200">
                        <div class="font-bold">${ROLE_LABEL[s.role] || s.role}${gateLabel}</div>
                        <div class="opacity-75">${t1}–${t2}</div>
                    </div>`;
                }).join('');
                return `<td class="lv-cell px-1.5 py-1 align-top border-l border-border min-w-[110px] cursor-pointer hover:bg-slate-50"
                        data-member-id="${m.id}" data-day="${d}" data-date="${isoDate(date)}">
                        ${chips}
                        ${list.length === 0 ? '<div class="text-[10px] text-muted italic py-2 text-center">+ adaugă</div>' : ''}
                    </td>`;
            }).join('');
            return `<tr>
                <td class="px-3 py-2 align-top font-medium text-secondary text-sm">${m.name}<div class="text-[10px] text-muted">${m.email}</div></td>
                ${cells}
            </tr>`;
        }).join('');
        $('lv-body').innerHTML = rows;
        $('lv-grid').classList.remove('hidden');

        // Bind clicks
        $('lv-body').querySelectorAll('.lv-cell').forEach(td => {
            td.addEventListener('click', (e) => {
                if (e.target.closest('.lv-chip')) return;
                openModal(null, parseInt(td.dataset.memberId, 10), td.dataset.date);
            });
        });
        $('lv-body').querySelectorAll('.lv-chip').forEach(chip => {
            chip.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = parseInt(chip.dataset.shiftId, 10);
                const s = shifts.find(x => x.id === id);
                if (s) openModal(s);
            });
        });
    }

    function openModal(shift, defaultMemberId, defaultDate) {
        editing = shift;
        $('lv-modal-title').textContent = shift ? 'Editează turnetă' : 'Adaugă turnetă';

        // members select
        $('lv-f-member').innerHTML = '<option value="">— Fără atribuire —</option>' +
            members.map(m => `<option value="${m.id}">${m.name}</option>`).join('');

        if (shift) {
            $('lv-f-member').value = shift.team_member_id || '';
            $('lv-f-start').value = toLocalDateTime(new Date(shift.start_at));
            $('lv-f-end').value = toLocalDateTime(new Date(shift.end_at));
            $('lv-f-role').value = shift.role || 'gate_scanner';
            $('lv-f-gate').value = shift.gate || '';
            $('lv-f-notes').value = shift.notes || '';
            $('lv-f-delete').classList.remove('hidden');
        } else {
            $('lv-f-member').value = defaultMemberId || '';
            const start = new Date(defaultDate + 'T09:00');
            const end = new Date(defaultDate + 'T17:00');
            $('lv-f-start').value = toLocalDateTime(start);
            $('lv-f-end').value = toLocalDateTime(end);
            $('lv-f-role').value = 'gate_scanner';
            $('lv-f-gate').value = '';
            $('lv-f-notes').value = '';
            $('lv-f-delete').classList.add('hidden');
        }
        $('lv-modal').classList.remove('hidden');
        $('lv-modal').classList.add('flex');
    }
    function closeModal() { $('lv-modal').classList.add('hidden'); $('lv-modal').classList.remove('flex'); editing = null; }

    async function saveShift() {
        const body = {
            team_member_id: $('lv-f-member').value ? parseInt($('lv-f-member').value, 10) : null,
            start_at: $('lv-f-start').value,
            end_at: $('lv-f-end').value,
            role: $('lv-f-role').value,
            gate: $('lv-f-gate').value || null,
            notes: $('lv-f-notes').value || null,
        };
        if (!body.start_at || !body.end_at) { alert('Completează start și sfârșit.'); return; }
        try {
            if (editing) {
                await AmbiletAPI.put(`/organizer/events/${currentEventId}/leisure/shifts/${editing.id}`, body);
            } else {
                await AmbiletAPI.post(`/organizer/events/${currentEventId}/leisure/shifts`, body);
            }
            closeModal();
            loadWeek();
        } catch (e) {
            alert('Eroare salvare: ' + (e?.message || ''));
        }
    }

    async function deleteShift() {
        if (!editing) return;
        if (!confirm('Sigur ștergi această turnetă?')) return;
        try {
            await AmbiletAPI.delete(`/organizer/events/${currentEventId}/leisure/shifts/${editing.id}`);
            closeModal();
            loadWeek();
        } catch (e) {
            alert('Eroare ștergere: ' + (e?.message || ''));
        }
    }

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined') {
            $('lv-error').textContent = 'API indisponibil.';
            $('lv-error').classList.remove('hidden');
            $('lv-loading').classList.add('hidden');
            return;
        }
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const events = res.data || [];
            const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
            if (leisure.length > 0) currentEventId = leisure[0].id;
        } catch (e) { console.error(e); }

        $('lv-prev-week').addEventListener('click', () => { weekStart.setDate(weekStart.getDate() - 7); loadWeek(); });
        $('lv-next-week').addEventListener('click', () => { weekStart.setDate(weekStart.getDate() + 7); loadWeek(); });
        $('lv-today').addEventListener('click', () => { weekStart = startOfWeek(new Date()); loadWeek(); });
        $('lv-modal-close').addEventListener('click', closeModal);
        $('lv-f-cancel').addEventListener('click', closeModal);
        $('lv-f-save').addEventListener('click', saveShift);
        $('lv-f-delete').addEventListener('click', deleteShift);
        $('lv-modal').addEventListener('click', (e) => { if (e.target === $('lv-modal')) closeModal(); });

        loadWeek();
    });
})();
</script>
<?php
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
