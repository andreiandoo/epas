<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Sesiuni casă POS';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_sessions';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl">👤 Sesiuni casă POS</h1>
                <p class="mt-1 text-sm text-muted">Detalii ture: operator, ore deschidere/închidere, încasări cash + card, comenzi + bilete.</p>
            </div>
            <a href="/organizator/leisure-sales" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold border rounded-lg text-secondary bg-white border-border hover:bg-slate-50">← Înapoi la Vânzări</a>
        </div>

        <!-- Filter perioada -->
        <div class="bg-white border rounded-2xl border-border p-4 mb-6 flex flex-wrap items-end gap-3">
            <div class="flex flex-wrap gap-2">
                <button data-range="7" class="ls-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">7 zile</button>
                <button data-range="14" class="ls-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">14 zile</button>
                <button data-range="30" class="ls-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">1 lună</button>
                <button data-range="90" class="ls-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">3 luni</button>
            </div>
            <div class="flex flex-wrap items-end gap-2 ml-4">
                <label class="block">
                    <span class="text-xs font-semibold text-muted">De la data</span>
                    <input id="ls-from" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
                </label>
                <label class="block">
                    <span class="text-xs font-semibold text-muted">Până la data</span>
                    <input id="ls-to" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
                </label>
                <button id="ls-apply" type="button" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-primary bg-primary text-white hover:opacity-90">Aplică</button>
            </div>
            <span id="ls-range-label" class="text-xs text-muted w-full">Ultimele 30 zile</span>
        </div>

        <div id="ls-error" class="hidden mb-4 p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-900"></div>
        <div id="ls-loading" class="hidden p-8 text-center"><div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div></div>

        <div id="ls-summary" class="hidden mb-6 p-4 bg-white border rounded-2xl border-border">
            <p class="text-sm text-muted">
                <strong id="ls-summary-count" class="text-2xl text-secondary tabular-nums">0</strong>
                <span class="text-lg">sesiuni</span>
                · Total încasat POS: <strong id="ls-summary-total" class="tabular-nums text-emerald-700">0.00 RON</strong>
                (💵 <span id="ls-summary-cash" class="tabular-nums font-semibold">0.00</span>
                · 💳 <span id="ls-summary-card" class="tabular-nums font-semibold">0.00</span>)
            </p>
        </div>

        <div id="ls-list" class="space-y-3"></div>
        <div id="ls-empty" class="hidden p-8 text-center text-muted bg-white border rounded-2xl border-border">Nicio sesiune în perioada selectată.</div>
    </main>
</div>
<script>
(function(){
    const $ = (id) => document.getElementById(id);
    let currentEventId = null;

    function fmtMoney(v) { return Number(v || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function esc(s) { return String(s || '').replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])); }
    function fmtDT(iso) {
        if (!iso) return '—';
        try {
            const d = new Date(iso);
            return d.toLocaleDateString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' +
                   d.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
        } catch { return iso; }
    }

    async function loadSessions() {
        $('ls-error').classList.add('hidden');
        $('ls-loading').classList.remove('hidden');
        $('ls-summary').classList.add('hidden');
        $('ls-list').innerHTML = '';
        $('ls-empty').classList.add('hidden');
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/sales/summary`, {
                from: $('ls-from').value || '',
                to: $('ls-to').value || '',
            });
            $('ls-loading').classList.add('hidden');
            const d = res.data || {};
            const sessions = Array.isArray(d.sessions) ? d.sessions : [];
            if (sessions.length === 0) {
                $('ls-empty').classList.remove('hidden');
                return;
            }
            // Summary card
            let tCash = 0, tCard = 0;
            sessions.forEach(s => { tCash += Number(s.cash || 0); tCard += Number(s.card || 0); });
            $('ls-summary-count').textContent = sessions.length;
            $('ls-summary-cash').textContent = fmtMoney(tCash);
            $('ls-summary-card').textContent = fmtMoney(tCard);
            $('ls-summary-total').textContent = fmtMoney(tCash + tCard) + ' RON';
            $('ls-summary').classList.remove('hidden');
            // Session cards
            $('ls-list').innerHTML = sessions.map(sessionHtml).join('');
        } catch (e) {
            $('ls-loading').classList.add('hidden');
            $('ls-error').textContent = 'Eroare: ' + (e?.message || 'necunoscut');
            $('ls-error').classList.remove('hidden');
        }
    }

    function sessionHtml(s) {
        const status = s.is_open
            ? '<span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-emerald-100 text-emerald-800">🔓 DESCHISĂ</span>'
            : '<span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-slate-100 text-slate-700">🔒 ÎNCHISĂ</span>';
        const total = Number(s.cash || 0) + Number(s.card || 0);
        return `
        <div class="bg-white border border-border rounded-2xl p-4">
            <div class="flex flex-wrap items-center gap-2 justify-between mb-3">
                <div>
                    <p class="text-base font-bold text-secondary">👤 ${esc(s.operator || 'Operator')}</p>
                    <p class="text-xs text-muted">${fmtDT(s.opened_at)} → ${s.closed_at ? fmtDT(s.closed_at) : '⏳ în desfășurare'}</p>
                </div>
                <div class="flex items-center gap-3">
                    ${status}
                    <div class="text-right">
                        <p class="text-[10px] text-muted uppercase">Total încasat</p>
                        <p class="text-xl font-extrabold text-emerald-700 tabular-nums">${fmtMoney(total)} <span class="text-xs">RON</span></p>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 text-xs">
                <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
                    <p class="text-[10px] text-amber-700 uppercase font-bold">💵 Cash</p>
                    <p class="text-lg font-extrabold text-amber-900 tabular-nums">${fmtMoney(s.cash)} <span class="text-xs">RON</span></p>
                </div>
                <div class="p-3 bg-sky-50 border border-sky-200 rounded-lg">
                    <p class="text-[10px] text-sky-700 uppercase font-bold">💳 Card</p>
                    <p class="text-lg font-extrabold text-sky-900 tabular-nums">${fmtMoney(s.card)} <span class="text-xs">RON</span></p>
                </div>
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg">
                    <p class="text-[10px] text-slate-600 uppercase font-bold">🧾 Comenzi</p>
                    <p class="text-lg font-extrabold text-slate-900 tabular-nums">${s.orders || 0}</p>
                </div>
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg">
                    <p class="text-[10px] text-slate-600 uppercase font-bold">🎟️ Bilete</p>
                    <p class="text-lg font-extrabold text-slate-900 tabular-nums">${s.tickets_sold || 0}${s.tickets_visitors && s.tickets_visitors !== s.tickets_sold ? ' <span class="text-[10px] text-muted">/ '+s.tickets_visitors+' vizitatori</span>' : ''}</p>
                </div>
            </div>
        </div>`;
    }

    function setRangePreset(days) {
        document.querySelectorAll('.ls-range-btn').forEach(b => {
            b.classList.remove('bg-primary', 'text-white', 'border-primary');
            b.classList.add('bg-white');
        });
        const btn = document.querySelector(`.ls-range-btn[data-range="${days}"]`);
        if (btn) { btn.classList.remove('bg-white'); btn.classList.add('bg-primary', 'text-white', 'border-primary'); }
        const to = new Date();
        const from = new Date(Date.now() - parseInt(days, 10) * 86400000);
        $('ls-from').value = from.toISOString().slice(0,10);
        $('ls-to').value = to.toISOString().slice(0,10);
        const lbl = { '7':'7 zile', '14':'14 zile', '30':'1 lună', '90':'3 luni' };
        $('ls-range-label').textContent = 'Ultimele ' + (lbl[days] || days);
        loadSessions();
    }

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined') { $('ls-error').textContent = 'API indisponibil.'; $('ls-error').classList.remove('hidden'); return; }
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const events = res.data || [];
            const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
            if (leisure.length > 0) currentEventId = leisure[0].id;
        } catch (e) { console.error(e); }
        if (!currentEventId) { $('ls-error').textContent = 'Nu există eveniment leisure.'; $('ls-error').classList.remove('hidden'); return; }

        document.querySelectorAll('.ls-range-btn').forEach(b => b.addEventListener('click', () => setRangePreset(b.dataset.range)));
        $('ls-apply').addEventListener('click', () => {
            document.querySelectorAll('.ls-range-btn').forEach(b => { b.classList.remove('bg-primary', 'text-white', 'border-primary'); b.classList.add('bg-white'); });
            const df = $('ls-from').value, dt = $('ls-to').value;
            const fmt = s => { try { return new Date(s + 'T00:00:00').toLocaleDateString('ro-RO', { day: '2-digit', month: 'short', year: 'numeric' }); } catch { return s; } };
            $('ls-range-label').textContent = 'Interval custom · ' + fmt(df) + ' – ' + fmt(dt);
            loadSessions();
        });

        setRangePreset('30');
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
