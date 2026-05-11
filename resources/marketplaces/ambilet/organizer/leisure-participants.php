<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Participanți';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_participants';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Participanți</h1>
            <p class="mt-1 text-sm text-muted">Vizitatorii care au cumpărat bilete pentru locația ta.</p>
        </div>

        <!-- Date range filter -->
        <div class="bg-white border rounded-2xl border-border p-4 mb-6">
            <div class="flex flex-wrap items-end gap-2">
                <div class="flex-1 min-w-[200px]">
                    <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-2">Filtru perioadă</p>
                    <div class="flex flex-wrap gap-2">
                        <button data-range="7" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">Ultimele 7 zile</button>
                        <button data-range="14" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">14 zile</button>
                        <button data-range="30" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">1 lună</button>
                        <button data-range="90" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">3 luni</button>
                        <button data-range="180" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">6 luni</button>
                        <button data-range="custom" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">Custom</button>
                    </div>
                </div>
                <div id="lv-custom-range" class="hidden flex items-end gap-2">
                    <label class="block">
                        <span class="text-xs font-semibold text-muted">De la</span>
                        <input id="lv-from" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted">Până la</span>
                        <input id="lv-to" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
                    </label>
                    <button id="lv-apply-custom" class="px-3 py-1.5 bg-primary text-white text-xs font-medium rounded-lg hover:bg-primary-dark">Aplică</button>
                </div>
                <div class="text-xs text-muted">
                    <span id="lv-range-label">Ultimele 7 zile</span>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Total participanți</p>
                <p class="text-2xl font-bold text-secondary"><span id="lv-stat-total">0</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Check-in efectuat</p>
                <p class="text-2xl font-bold text-emerald-600"><span id="lv-stat-checked">0</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Rata prezență</p>
                <p class="text-2xl font-bold text-secondary"><span id="lv-stat-rate">0</span>%</p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">No-show</p>
                <p class="text-2xl font-bold text-amber-600"><span id="lv-stat-noshow">0</span></p>
            </div>
        </div>

        <!-- Search + table -->
        <div class="bg-white border rounded-2xl border-border">
            <div class="px-5 py-4 border-b border-border flex flex-wrap items-center gap-3">
                <input id="lv-search" type="text" placeholder="🔍 Caută după nume, email, cod bilet..." class="flex-1 min-w-[200px] px-3 py-2 text-sm border border-border rounded-lg">
                <button id="lv-export" class="px-3 py-2 text-xs font-medium bg-white border border-border rounded-lg hover:bg-slate-50">📥 Export CSV</button>
            </div>
            <div id="lv-loading" class="p-8 text-center"><div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div></div>
            <div id="lv-table-wrap" class="hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase bg-slate-50 text-muted">
                        <tr>
                            <th class="px-5 py-3 text-left">Cod bilet</th>
                            <th class="px-5 py-3 text-left">Nume / Email</th>
                            <th class="px-5 py-3 text-left">Tip bilet</th>
                            <th class="px-5 py-3 text-left">Data vizită</th>
                            <th class="px-5 py-3 text-left">Status</th>
                            <th class="px-5 py-3 text-left">Check-in</th>
                        </tr>
                    </thead>
                    <tbody id="lv-rows" class="divide-y divide-border"></tbody>
                </table>
            </div>
            <div id="lv-empty" class="hidden p-8 text-center text-muted">Niciun participant în perioada selectată.</div>
        </div>

        <div class="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-xl text-sm text-amber-900">
            ℹ️ <strong>Implementare în curs:</strong> tabelul folosește un endpoint nou (F5.4) <code class="bg-amber-100 px-1 rounded">GET /organizer/events/{event}/leisure/participants</code>. Pentru moment afișează zero. Datele vor veni din join Order ↔ Ticket ↔ TicketType cu filtre pe paid_at și status check-in.
        </div>
    </main>
</div>
<script>
(function(){
    const $ = (id) => document.getElementById(id);
    let currentRange = '7'; // zile

    function setRange(days) {
        currentRange = days;
        document.querySelectorAll('.lv-range-btn').forEach(b => b.classList.remove('bg-primary', 'text-white', 'border-primary'));
        const btn = document.querySelector(`.lv-range-btn[data-range="${days}"]`);
        if (btn) btn.classList.add('bg-primary', 'text-white', 'border-primary');

        if (days === 'custom') {
            $('lv-custom-range').classList.remove('hidden');
            $('lv-custom-range').classList.add('flex');
            $('lv-range-label').textContent = 'Perioadă custom';
        } else {
            $('lv-custom-range').classList.add('hidden');
            $('lv-custom-range').classList.remove('flex');
            const labels = { '7': 'Ultimele 7 zile', '14': 'Ultimele 14 zile', '30': 'Ultima lună', '90': 'Ultimele 3 luni', '180': 'Ultimele 6 luni' };
            $('lv-range-label').textContent = labels[days] || `${days} zile`;
            loadParticipants(days);
        }
    }

    async function loadParticipants(days) {
        $('lv-loading').classList.remove('hidden');
        $('lv-table-wrap').classList.add('hidden');
        $('lv-empty').classList.add('hidden');
        // TODO F5.4: fetch real data
        // const to = new Date().toISOString().slice(0,10);
        // const from = new Date(Date.now() - days*86400000).toISOString().slice(0,10);
        // try { const r = await AmbiletAPI.get(`/organizer/events/${eventId}/leisure/participants`, {from, to}); ... } catch{}
        setTimeout(() => {
            $('lv-loading').classList.add('hidden');
            $('lv-empty').classList.remove('hidden');
        }, 300);
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.lv-range-btn').forEach(btn => {
            btn.addEventListener('click', () => setRange(btn.dataset.range));
        });
        setRange('7');
    });
})();
</script>
<?php
require_once dirname(__DIR__) . '/includes/organizer-footer.php';
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
