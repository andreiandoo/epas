<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Circuit Sanius';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_sanius';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-3 sm:p-4 lg:p-8">
        <div class="w-full mx-auto space-y-5" style="max-width:1200px;">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold text-secondary lg:text-3xl">
                        <?= amb_icon('snowflake', 'w-7 h-7') ?> Circuit Sanius
                        <span id="sn-mode-badge" class="text-[11px] font-bold uppercase px-2 py-1 rounded bg-slate-200 text-slate-700">—</span>
                    </h1>
                    <p class="mt-1 text-sm text-muted">Sănii închiriate pe tichetul de parcare, tractări numărate de șoferi, plată la casierie la ieșire.</p>
                </div>
                <a href="/organizator/leisure-sanius-casa" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-lg bg-secondary hover:opacity-90"><?= amb_icon('banknote', 'w-4 h-4') ?> Casierie Sanius</a>
            </div>

            <div id="sn-error" class="hidden p-4 text-sm border rounded-xl bg-rose-50 border-rose-200 text-rose-900"></div>
            <div id="sn-flash" class="hidden p-3 text-sm border rounded-xl bg-emerald-50 border-emerald-200 text-emerald-900"></div>

            <!-- ===== MOD ===== -->
            <section class="p-5 bg-white border shadow-sm rounded-2xl border-border">
                <h2 class="text-lg font-bold text-secondary">Mod de funcționare</h2>
                <p class="mt-1 text-sm text-muted">„Oprit”: nimic nu se schimbă la operatori. „Test”: doar conturile cu rol în circuit îl văd, cu sănii de test și plată simulată (nu se creează comenzi). „Activ”: fluxul real.</p>
                <div class="grid grid-cols-1 gap-2 mt-4 sm:grid-cols-3" id="sn-modes">
                    <button data-mode="off" class="px-4 py-3 text-sm font-bold border-2 rounded-xl border-border">Oprit</button>
                    <button data-mode="test" class="px-4 py-3 text-sm font-bold border-2 rounded-xl border-border">Test</button>
                    <button data-mode="live" class="px-4 py-3 text-sm font-bold border-2 rounded-xl border-border">Activ</button>
                </div>
                <p id="sn-live-note" class="hidden mt-2 text-xs text-amber-700">Modul Activ se deblochează după etapa de încasare reală.</p>
            </section>

            <!-- ===== ECHIPA ===== -->
            <section class="p-5 bg-white border shadow-sm rounded-2xl border-border">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-bold text-secondary">Roluri în circuit</h2>
                        <p class="mt-1 text-sm text-muted">Doar membrii cu rol aici văd ecranele circuitului. În modul Test, dă roluri doar conturilor de test. Rolurile de echipă existente nu se schimbă.</p>
                    </div>
                    <button id="sn-save-members" class="px-4 py-2 text-sm font-semibold text-white rounded-lg bg-primary hover:bg-primary-dark">Salvează rolurile</button>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="text-xs text-left uppercase text-muted"><th class="py-2 pr-3">Membru</th><th class="py-2 pr-3">Email</th><th class="py-2">Rol în circuit</th></tr></thead>
                        <tbody id="sn-members" class="divide-y divide-slate-100"></tbody>
                    </table>
                </div>
            </section>

            <!-- ===== PRETURI ===== -->
            <section class="p-5 bg-white border shadow-sm rounded-2xl border-border">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-bold text-secondary">Prețuri și reguli</h2>
                        <p class="mt-1 text-sm text-muted">Fiecare taxă e un produs ascuns (nu apare pe site și nici în POS). Prețurile se setează din <a href="/organizator/leisure" class="underline text-primary">Produse</a>.</p>
                    </div>
                    <button id="sn-bootstrap" class="px-4 py-2 text-sm font-semibold border rounded-lg border-border hover:bg-slate-50"><?= amb_icon('plus', 'w-4 h-4') ?> Generează produsele lipsă</button>
                </div>
                <div id="sn-products" class="grid grid-cols-1 gap-2 mt-4 sm:grid-cols-2 lg:grid-cols-4"></div>

                <h3 class="mt-5 text-sm font-bold text-secondary">Chiria pe cicluri</h3>
                <div class="grid grid-cols-2 gap-3 mt-2 lg:grid-cols-3">
                    <label class="block"><span class="text-xs font-semibold tracking-wider uppercase text-muted">Durata ciclului (min.)</span>
                        <input id="sn-cycle-min" type="number" min="5" max="1440" class="w-full px-3 py-2 mt-1 text-sm border rounded-lg border-border"></label>
                    <label class="block"><span class="text-xs font-semibold tracking-wider uppercase text-muted">Tractări incluse pe ciclu</span>
                        <input id="sn-tows-cycle" type="number" min="0" max="100" class="w-full px-3 py-2 mt-1 text-sm border rounded-lg border-border"></label>
                    <label class="block"><span class="text-xs font-semibold tracking-wider uppercase text-muted">Toleranță la returnare (min.)</span>
                        <input id="sn-grace-min" type="number" min="0" max="120" class="w-full px-3 py-2 mt-1 text-sm border rounded-lg border-border"></label>
                </div>
                <label class="block mt-3"><span class="text-xs font-semibold tracking-wider uppercase text-muted">Zile cu preț de weekend, pe lângă sâmbătă și duminică (sărbători)</span>
                    <textarea id="sn-weekend-dates" rows="2" placeholder="2026-12-01, 2026-12-25, 2026-12-26, 2027-01-01, 2027-01-02, 2027-01-06, 2027-01-07" class="w-full px-3 py-2 mt-1 text-sm border rounded-lg border-border"></textarea></label>
                <p class="mt-2 text-xs text-muted">Se plătesc ciclurile începute; toleranța se scade din durată (cu 60 min și 10 min toleranță, 1 h 10 min = 1 ciclu, 1 h 11 min = 2 cicluri). Ciclurile de depășire includ și ele tractări. Prețul e al zilei în care a pornit închirierea. Azi: <strong id="sn-today-type">—</strong>.</p>

                <h3 class="mt-5 text-sm font-bold text-secondary">Parcare și sincronizare</h3>
                <div class="grid grid-cols-2 gap-3 mt-2 lg:grid-cols-4">
                    <label class="block"><span class="text-xs font-semibold tracking-wider uppercase text-muted">Parcare: min. gratuite</span>
                        <input id="sn-free-min" type="number" min="0" class="w-full px-3 py-2 mt-1 text-sm border rounded-lg border-border"></label>
                    <label class="block"><span class="text-xs font-semibold tracking-wider uppercase text-muted">Gratuit cu bilet (min.)</span>
                        <input id="sn-free-access" type="number" min="0" class="w-full px-3 py-2 mt-1 text-sm border rounded-lg border-border"></label>
                    <label class="block"><span class="text-xs font-semibold tracking-wider uppercase text-muted">Interval taxare (min.)</span>
                        <input id="sn-unit-min" type="number" min="1" class="w-full px-3 py-2 mt-1 text-sm border rounded-lg border-border"></label>
                    <label class="block"><span class="text-xs font-semibold tracking-wider uppercase text-muted">Șofer offline după (sec.)</span>
                        <input id="sn-ping-timeout" type="number" min="10" max="300" class="w-full px-3 py-2 mt-1 text-sm border rounded-lg border-border"></label>
                </div>
                <p class="mt-2 text-xs text-muted">Cu bilet de acces: se plătește doar timpul care depășește minutele gratuite. Fără bilet: peste minutele gratuite se plătește toată durata. Intervalele începute se plătesc întregi.</p>
                <div class="mt-3"><button id="sn-save-rules" class="px-4 py-2 text-sm font-semibold text-white rounded-lg bg-primary hover:bg-primary-dark">Salvează regulile</button></div>
            </section>

            <!-- ===== CONTRACT ===== -->
            <section class="p-5 bg-white border shadow-sm rounded-2xl border-border">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-bold text-secondary">Contract</h2>
                        <p class="mt-1 text-sm text-muted">Casiera completează datele clientului în leisure-pos, iar clientul semnează pe tabletă sau pe hârtie. Tableta de semnat deschide <a href="/organizator/leisure-sanius-semnatura" target="_blank" class="underline text-primary">/organizator/leisure-sanius-semnatura</a>.</p>
                    </div>
                </div>
                <label class="flex items-center gap-2 mt-4 text-sm font-semibold"><input id="sn-con-required" type="checkbox" class="w-4 h-4"> Fără contract semnat, ghișeul nu dă sănii</label>
                <label class="block mt-3"><span class="text-xs font-semibold tracking-wider uppercase text-muted">Titlu</span>
                    <input id="sn-con-title" type="text" maxlength="160" class="w-full px-3 py-2 mt-1 text-sm border rounded-lg border-border"></label>
                <label class="block mt-3"><span class="text-xs font-semibold tracking-wider uppercase text-muted">Text (paragrafele se despart cu un rând gol)</span>
                    <textarea id="sn-con-body" rows="10" class="w-full px-3 py-2 mt-1 text-sm border rounded-lg border-border"></textarea></label>
                <p id="sn-con-vars" class="mt-1 text-xs text-muted"></p>
                <label class="block mt-3"><span class="text-xs font-semibold tracking-wider uppercase text-muted">Tablete de semnat (câte una pe casă, separate prin virgulă)</span>
                    <input id="sn-con-stations" type="text" class="w-full px-3 py-2 mt-1 text-sm border rounded-lg border-border" placeholder="Casa 1, Casa 2"></label>
                <p class="mt-2 text-xs text-muted">Versiunea textului: <strong id="sn-con-version">—</strong>. La fiecare schimbare a textului crește versiunea; contractele semnate păstrează textul pe care l-a văzut clientul.</p>
                <div class="mt-3"><button id="sn-save-contract" class="px-4 py-2 text-sm font-semibold text-white rounded-lg bg-primary hover:bg-primary-dark">Salvează contractul</button></div>
            </section>

            <!-- ===== SANII ===== -->
            <section class="p-5 bg-white border shadow-sm rounded-2xl border-border">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-bold text-secondary">Sănii</h2>
                        <p class="mt-1 text-sm text-muted">Fiecare sanie are un cod QR de lipit pe ea. Săniile de test se folosesc doar în modul Test.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex p-1 rounded-lg bg-slate-100" id="sn-sled-kind">
                            <button data-test="1" class="px-3 py-1.5 text-xs font-bold rounded-md">De test</button>
                            <button data-test="0" class="px-3 py-1.5 text-xs font-bold rounded-md">Reale</button>
                        </div>
                        <input id="sn-sled-count" type="number" min="1" max="200" value="3" class="w-20 px-3 py-2 text-sm border rounded-lg border-border">
                        <button id="sn-sled-add" class="px-3 py-2 text-sm font-semibold border rounded-lg border-border hover:bg-slate-50"><?= amb_icon('plus', 'w-4 h-4') ?> Adaugă</button>
                        <button id="sn-sled-print" class="px-3 py-2 text-sm font-semibold text-white rounded-lg bg-secondary hover:opacity-90"><?= amb_icon('printer', 'w-4 h-4') ?> Printează QR</button>
                    </div>
                </div>
                <div id="sn-sleds" class="grid grid-cols-2 gap-2 mt-4 sm:grid-cols-3 lg:grid-cols-6"></div>
            </section>

            <!-- ===== SOFERI ===== -->
            <section class="p-5 bg-white border shadow-sm rounded-2xl border-border">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-bold text-secondary">Șoferi în tură</h2>
                        <p class="mt-1 text-sm text-muted">Cât timp un șofer e fără semnal sau are scanări netrimise, returnările de la ghișeu așteaptă. Dacă telefonul s-a descărcat sau s-a stricat, managerul poate debloca.</p>
                    </div>
                    <button id="sn-tow-refresh" class="px-3 py-2 text-sm font-semibold border rounded-lg border-border hover:bg-slate-50"><?= amb_icon('refresh-cw', 'w-4 h-4') ?> Actualizează</button>
                </div>
                <div id="sn-tow" class="mt-4 space-y-2"></div>
            </section>

            <!-- ===== RAPORT ===== -->
            <section class="p-5 bg-white border shadow-sm rounded-2xl border-border">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-bold text-secondary">Raport zi</h2>
                    <div class="flex flex-wrap items-center gap-2">
                        <input id="sn-rep-date" type="date" class="px-3 py-2 text-sm border rounded-lg border-border">
                        <label class="inline-flex items-center gap-2 text-sm"><input id="sn-rep-test" type="checkbox"> Date de test</label>
                        <button id="sn-rep-load" class="px-3 py-2 text-sm font-semibold border rounded-lg border-border hover:bg-slate-50">Afișează</button>
                    </div>
                </div>
                <div id="sn-report" class="mt-4"></div>
            </section>
        </div>
    </main>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const money = (n) => (Number(n) || 0).toFixed(2).replace('.', ',') + ' lei';
    const ROLE_LABELS = { '': 'Fără rol', desk: 'Ghișeu Sanius', tow: 'Șofer tractare', cashier: 'Casier', manager: 'Manager' };
    const PRODUCT_LABELS = { cycle_weekday: 'Ciclu sanie (L–V)', cycle_weekend: 'Ciclu sanie (weekend)', tow: 'Tractare', damage_small: 'Avarie mică', damage_medium: 'Avarie medie', damage_large: 'Avarie mare', lost: 'Sanie pierdută', parking_car: 'Parcare auto / interval', parking_bus: 'Parcare autobuz / interval' };
    const SLED_STATUS = { available: ['Disponibilă', 'bg-emerald-100 text-emerald-800'], rented: ['Închiriată', 'bg-amber-100 text-amber-800'], repair: ['În reparație', 'bg-rose-100 text-rose-800'], lost: ['Pierdută', 'bg-slate-800 text-white'], retired: ['Retrasă', 'bg-slate-200 text-slate-600'] };
    const RENTAL_STATUS = { active: 'Închiriată', pending_sync: 'Așteaptă sincronizarea', closed: 'Returnată', lost: 'Pierdută' };
    const ANOMALY = { duplicate: 'Scanare dublă', unknown_sled: 'Cod necunoscut', no_rental: 'Sanie neînchiriată', other_mode: 'Sanie din alt mod', after_close: 'Ajunsă după închidere' };

    let eventId = null;
    let settings = null;
    let sledsTest = true;
    let sledsCache = [];

    const base = () => `/organizer/events/${eventId}/leisure/sanius`;

    function showError(msg) { const el = $('sn-error'); el.textContent = msg; el.classList.remove('hidden'); setTimeout(() => el.classList.add('hidden'), 8000); }
    function flash(msg) { const el = $('sn-flash'); el.textContent = msg; el.classList.remove('hidden'); setTimeout(() => el.classList.add('hidden'), 4000); }
    async function call(fn) { try { return await fn(); } catch (e) { showError(e?.message || 'Eroare'); return null; } }

    async function loadEvent() {
        const res = await AmbiletAPI.getAllOrganizerEvents();
        const leisure = (res.data || []).filter(e => (e.display_template || 'standard') === 'leisure_venue');
        const wanted = parseInt(new URLSearchParams(location.search).get('event') || '0', 10);
        const ev = leisure.find(e => e.id === wanted) || leisure[0];
        if (!ev) throw new Error('Nu există un eveniment de tip Locație de agrement.');
        eventId = ev.id;
    }

    // ---------- Setari ----------
    async function loadSettings() {
        const res = await AmbiletAPI.get(base() + '/settings');
        settings = res.data || {};
        renderMode(); renderMembers(); renderProducts(); renderRules();
    }

    function renderMode() {
        const mode = settings.mode || 'off';
        const badge = $('sn-mode-badge');
        badge.textContent = { off: 'Oprit', test: 'Test', live: 'Activ' }[mode];
        badge.className = 'text-[11px] font-bold uppercase px-2 py-1 rounded ' + ({ off: 'bg-slate-200 text-slate-700', test: 'bg-amber-100 text-amber-800', live: 'bg-emerald-100 text-emerald-800' }[mode]);
        document.querySelectorAll('#sn-modes button').forEach(b => {
            const active = b.dataset.mode === mode;
            const color = { off: 'border-slate-500 bg-slate-50', test: 'border-amber-500 bg-amber-50', live: 'border-emerald-600 bg-emerald-50' }[b.dataset.mode];
            b.className = 'px-4 py-3 text-sm font-bold border-2 rounded-xl ' + (active ? color : 'border-border bg-white hover:bg-slate-50');
            b.disabled = b.dataset.mode === 'live' && !settings.live_available;
            if (b.disabled) b.className += ' opacity-50 cursor-not-allowed';
        });
        $('sn-live-note').classList.toggle('hidden', !!settings.live_available);
    }

    async function setMode(mode) {
        if (mode === settings.mode) return;
        const msg = { off: 'Oprești circuitul Sanius? Ecranele circuitului dispar pentru toți operatorii.', test: 'Pornești modul Test? Doar membrii cu rol în circuit îl vor vedea.', live: 'Pornești modul Activ (fluxul real)?' }[mode];
        if (!confirm(msg)) return;
        const res = await call(() => AmbiletAPI.put(base() + '/settings', { mode }));
        if (res) { settings = res.data; renderMode(); flash(res.message || 'Mod schimbat'); refreshTow(); try { Object.keys(sessionStorage).filter(k => k.startsWith('ambilet_sanius_menu_')).forEach(k => sessionStorage.removeItem(k)); } catch (e) {} }
    }

    function renderMembers() {
        const roles = {};
        (settings.members || []).forEach(m => { roles[m.member_id] = m.role; });
        const team = settings.team || [];
        $('sn-members').innerHTML = team.length ? team.map(m => `
            <tr>
                <td class="py-2 pr-3 font-semibold text-secondary">${esc(m.name)}</td>
                <td class="py-2 pr-3 text-muted">${esc(m.email)}</td>
                <td class="py-2"><select data-member="${m.id}" class="px-2 py-1.5 text-sm border rounded-lg border-border">
                    ${Object.entries(ROLE_LABELS).map(([k, l]) => `<option value="${k}" ${(roles[m.id] || '') === k ? 'selected' : ''}>${l}</option>`).join('')}
                </select></td>
            </tr>`).join('') : '<tr><td colspan="3" class="py-4 text-sm text-center text-muted">Nu ai membri în echipă. Adaugă-i din pagina Echipă.</td></tr>';
    }

    async function saveMembers() {
        const members = [];
        document.querySelectorAll('#sn-members select[data-member]').forEach(s => {
            if (s.value) members.push({ member_id: parseInt(s.dataset.member, 10), role: s.value });
        });
        const res = await call(() => AmbiletAPI.put(base() + '/settings', { members }));
        if (res) { settings = res.data; renderMembers(); flash('Roluri salvate'); }
    }

    function renderProducts() {
        const p = settings.products || {};
        $('sn-products').innerHTML = Object.keys(PRODUCT_LABELS).map(k => {
            const item = p[k] || {};
            const missing = !item.ticket_type_id;
            return `<div class="p-3 border rounded-xl ${missing ? 'border-rose-200 bg-rose-50' : 'border-border bg-surface'}">
                <p class="text-xs font-semibold text-muted">${PRODUCT_LABELS[k]}</p>
                <p class="mt-1 text-lg font-bold ${missing ? 'text-rose-700' : 'text-secondary'}">${missing ? 'Lipsește' : money(item.price)}</p>
            </div>`;
        }).join('');
    }

    function renderRules() {
        const pk = settings.parking || {};
        const rl = settings.rental || {};
        $('sn-cycle-min').value = rl.cycle_minutes ?? 60;
        $('sn-tows-cycle').value = rl.tows_per_cycle ?? 1;
        $('sn-grace-min').value = rl.grace_minutes ?? 10;
        $('sn-weekend-dates').value = (rl.weekend_extra_dates || []).join(', ');
        $('sn-today-type').textContent = settings.today_day_type === 'weekend' ? 'preț de weekend' : 'preț L–V';
        $('sn-free-min').value = pk.free_minutes ?? 10;
        $('sn-free-access').value = pk.free_minutes_with_access ?? 240;
        $('sn-unit-min').value = pk.unit_minutes ?? 30;
        $('sn-ping-timeout').value = settings.ping_timeout_seconds ?? 30;
    }

    async function saveRules() {
        const body = {
            rental: {
                cycle_minutes: parseInt($('sn-cycle-min').value || '60', 10),
                tows_per_cycle: parseInt($('sn-tows-cycle').value || '0', 10),
                grace_minutes: parseInt($('sn-grace-min').value || '0', 10),
                weekend_extra_dates: $('sn-weekend-dates').value.split(/[\s,;]+/).map(x => x.trim()).filter(Boolean),
            },
            ping_timeout_seconds: parseInt($('sn-ping-timeout').value || '30', 10),
            parking: {
                free_minutes: parseInt($('sn-free-min').value || '0', 10),
                free_minutes_with_access: parseInt($('sn-free-access').value || '0', 10),
                unit_minutes: parseInt($('sn-unit-min').value || '30', 10),
            },
        };
        const res = await call(() => AmbiletAPI.put(base() + '/settings', body));
        if (res) { settings = res.data; renderRules(); renderProducts(); flash('Reguli salvate'); }
    }

    function renderContract() {
        const c = settings.contract || {};
        $('sn-con-required').checked = !!c.required;
        $('sn-con-title').value = c.title || '';
        $('sn-con-body').value = c.body || '';
        $('sn-con-stations').value = (c.stations || []).join(', ');
        $('sn-con-version').textContent = c.version || 1;
        $('sn-con-vars').textContent = 'Variabile: ' + (settings.contract_variables || []).join(' ');
    }

    async function saveContract() {
        const body = { contract: {
            required: $('sn-con-required').checked,
            title: $('sn-con-title').value,
            body: $('sn-con-body').value,
            stations: $('sn-con-stations').value.split(',').map(x => x.trim()).filter(Boolean),
        } };
        const res = await call(() => AmbiletAPI.put(base() + '/settings', body));
        if (res) { settings = res.data; renderContract(); flash('Contract salvat'); }
    }

    async function bootstrapProducts() {
        if (!confirm('Creez produsele de taxare care lipsesc? Sunt ascunse: nu apar pe site și nici în POS.')) return;
        const res = await call(() => AmbiletAPI.post(base() + '/products/bootstrap', {}));
        if (res) { settings = res.data; renderProducts(); flash(res.message || 'Gata'); }
    }

    // ---------- Sanii ----------
    async function loadSleds() {
        document.querySelectorAll('#sn-sled-kind button').forEach(b => {
            const on = (b.dataset.test === '1') === sledsTest;
            b.className = 'px-3 py-1.5 text-xs font-bold rounded-md ' + (on ? 'bg-white shadow text-secondary' : 'text-muted');
        });
        const res = await call(() => AmbiletAPI.get(base() + '/sleds', { is_test: sledsTest ? 1 : 0 }));
        sledsCache = res?.data?.sleds || [];
        $('sn-sleds').innerHTML = sledsCache.length ? sledsCache.map(s => {
            const [label, cls] = SLED_STATUS[s.status] || [s.status, 'bg-slate-100'];
            const action = s.status === 'repair' || s.status === 'lost' || s.status === 'retired'
                ? `<button data-sled-status="available" data-sled="${s.id}" class="mt-2 w-full px-2 py-1 text-xs font-semibold border rounded-lg border-border hover:bg-slate-50">Repune în circuit</button>`
                : (s.status === 'available' ? `<button data-sled-status="retired" data-sled="${s.id}" class="mt-2 w-full px-2 py-1 text-xs border rounded-lg border-border text-muted hover:bg-slate-50">Retrage</button>` : '');
            return `<div class="p-3 border rounded-xl border-border">
                <p class="text-sm font-bold text-secondary">${esc(s.name)}</p>
                <p class="text-[10px] font-mono text-muted">${esc(s.code)}</p>
                <span class="inline-block mt-2 text-[10px] font-bold uppercase px-2 py-0.5 rounded ${cls}">${label}</span>
                ${action}
            </div>`;
        }).join('') : '<p class="text-sm col-span-full text-muted">Nicio sanie încă.</p>';
        document.querySelectorAll('#sn-sleds [data-sled-status]').forEach(b => b.addEventListener('click', () => setSledStatus(b.dataset.sled, b.dataset.sledStatus)));
    }

    async function addSleds() {
        const count = parseInt($('sn-sled-count').value || '0', 10);
        if (!count) return;
        if (!confirm(`Adaugi ${count} sănii ${sledsTest ? 'de test' : 'reale'}?`)) return;
        const res = await call(() => AmbiletAPI.post(base() + '/sleds', { count, is_test: sledsTest }));
        if (res) { flash(res.message || 'Sănii adăugate'); loadSleds(); }
    }

    async function setSledStatus(id, status) {
        const res = await call(() => AmbiletAPI.put(base() + '/sleds/' + id, { status }));
        if (res) loadSleds();
    }

    function printSleds() {
        const list = sledsCache.filter(s => s.status !== 'retired');
        if (!list.length) { showError('Nu sunt sănii de printat.'); return; }
        const qr = (data) => `https://api.qrserver.com/v1/create-qr-code/?size=400x400&margin=10&data=${encodeURIComponent(data)}`;
        const w = window.open('', '_blank');
        if (!w) { showError('Browserul a blocat fereastra de print.'); return; }
        w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>QR sănii</title>
            <style>body{font-family:'Plus Jakarta Sans',Arial,sans-serif;margin:0;padding:10mm}
            .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6mm}
            .c{border:1px dashed #94a3b8;border-radius:4mm;padding:4mm;text-align:center;page-break-inside:avoid}
            .c img{width:100%;max-width:55mm}.n{font-size:20pt;font-weight:800;margin-top:2mm}.k{font:9pt monospace;color:#475569}</style></head>
            <body><div class="grid">${list.map(s => `<div class="c"><img src="${qr(s.qr)}"><div class="n">${esc(s.name)}</div><div class="k">${esc(s.code)}</div></div>`).join('')}</div>
            <script>window.onload=function(){setTimeout(function(){window.print()},600)}<\/script></body></html>`);
        w.document.close();
    }

    // ---------- Soferi ----------
    async function refreshTow() {
        if (!settings || settings.mode === 'off') {
            $('sn-tow').innerHTML = '<p class="text-sm text-muted">Circuitul e oprit.</p>';
            return;
        }
        let res;
        try { res = await AmbiletAPI.get(base() + '/tow/status'); } catch (e) { $('sn-tow').innerHTML = `<p class="text-sm text-rose-700">${esc(e.message)}</p>`; return; }
        const d = res.data || {};
        const shifts = d.shifts || [];
        const head = `<div class="p-3 rounded-xl ${d.blocked ? 'bg-amber-50 border border-amber-200 text-amber-900' : 'bg-emerald-50 border border-emerald-200 text-emerald-900'} text-sm font-semibold">
            ${d.blocked ? 'Returnările așteaptă sincronizarea unui șofer.' : 'Toți șoferii sunt sincronizați.'}
            ${d.pending_returns ? ` · ${d.pending_returns} returnări în așteptare` : ''}</div>`;
        $('sn-tow').innerHTML = head + (shifts.length ? shifts.map(s => {
            const ago = s.last_ping_at ? Math.max(0, Math.round((Date.now() - new Date(s.last_ping_at)) / 1000)) : null;
            return `<div class="flex flex-wrap items-center justify-between gap-2 p-3 border rounded-xl border-border">
                <div>
                    <p class="font-semibold text-secondary">${esc(s.member)} <span class="text-xs font-normal text-muted">· tura de la ${new Date(s.started_at).toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' })}</span></p>
                    <p class="text-xs text-muted">Ultimul semnal: ${ago === null ? '—' : (ago < 60 ? ago + ' s' : Math.round(ago / 60) + ' min')} · netrimise: ${s.pending_count} · tractări azi: ${s.tows_today}${s.force_unlocked_at ? ' · deblocat de manager' : ''}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-bold uppercase px-2 py-1 rounded ${s.blocking ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}">${s.blocking ? 'Blochează' : 'Sincronizat'}</span>
                    ${s.blocking ? `<button data-unlock="${s.id}" class="px-3 py-1.5 text-xs font-semibold text-white rounded-lg bg-amber-600 hover:bg-amber-700">${ambIcon('lock-open', 'w-3.5 h-3.5')} Deblocare forțată</button>` : ''}
                    <button data-close-shift="${s.id}" class="px-3 py-1.5 text-xs font-semibold border rounded-lg border-border hover:bg-slate-50">Închide tura</button>
                </div>
            </div>`;
        }).join('') : '<p class="text-sm text-muted">Niciun șofer în tură.</p>');
        document.querySelectorAll('#sn-tow [data-unlock]').forEach(b => b.addEventListener('click', () => shiftAction(b.dataset.unlock, 'unlock')));
        document.querySelectorAll('#sn-tow [data-close-shift]').forEach(b => b.addEventListener('click', () => shiftAction(b.dataset.closeShift, 'close')));
    }

    async function shiftAction(id, action) {
        const reason = prompt(action === 'unlock'
            ? 'Motivul deblocării (ex. telefon descărcat). Tractările netrimise de pe acel telefon nu se vor mai adăuga la săniile returnate între timp.'
            : 'Motivul închiderii turei:');
        if (!reason || reason.trim().length < 3) return;
        const res = await call(() => AmbiletAPI.post(base() + `/tow/shifts/${id}/${action}`, { reason: reason.trim() }));
        if (res) { flash(res.message || 'Gata'); refreshTow(); }
    }

    // ---------- Raport ----------
    async function loadReport() {
        const params = { is_test: $('sn-rep-test').checked ? 1 : 0 };
        if ($('sn-rep-date').value) params.date = $('sn-rep-date').value;
        const res = await call(() => AmbiletAPI.get(base() + '/reports/overview', params));
        if (!res) return;
        const d = res.data || {}; const t = d.totals || {};
        const tile = (v, l) => `<div class="p-3 text-center rounded-xl bg-surface"><p class="text-xl font-bold text-secondary tabular-nums">${v}</p><p class="text-[11px] text-muted">${l}</p></div>`;
        const open = (d.open_rentals || []).map(r => `<div class="flex items-center justify-between gap-2 p-2 border rounded-lg border-border text-sm">
            <span><strong>${esc(r.sled)}</strong> · ${RENTAL_STATUS[r.status] || r.status} · din ${new Date(r.started_at).toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' })} · ${r.tows_count} tractări</span>
            ${r.status === 'active' ? `<button data-lost="${r.id}" class="px-2 py-1 text-xs font-semibold border rounded-lg border-rose-300 text-rose-700 hover:bg-rose-50">Trece ca pierdută</button>` : ''}
        </div>`).join('');
        const anomalies = (d.anomalies || []).map(a => `<li>${new Date(a.scanned_at).toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' })} · ${esc(a.sled_code)} · ${ANOMALY[a.reason] || esc(a.reason)}</li>`).join('');
        const gaps = (d.suspect_gaps || []).map(g => `<li>Închirierea #${g.rental_id}: ${Math.round(g.minutes)} min între tractări</li>`).join('');
        const unlocks = (d.force_unlocks || []).map(u => `<li>${new Date(u.at).toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' })} · ${esc(u.reason)}</li>`).join('');
        $('sn-report').innerHTML = `
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-8">
                ${tile(t.tabs ?? 0, 'Conturi')}${tile(t.tabs_open ?? 0, 'Deschise')}${tile(t.tabs_settled ?? 0, 'Achitate')}${tile(money(t.settled_amount), 'Încasat')}
                ${tile(t.rentals ?? 0, 'Închirieri')}${tile(t.tows_counted ?? 0, 'Tractări')}${tile(t.damaged ?? 0, 'Avariate')}${tile(t.lost ?? 0, 'Pierdute')}
            </div>
            <p class="mt-2 text-xs text-muted">Plătit la intrare: ${t.prepaid_sled_cycles ?? 0} sanie-cicluri (${money(t.prepaid_amount)}) · cicluri folosite de săniile returnate: ${t.cycles_used ?? 0} · închirieri în weekend: ${t.weekend_rentals ?? 0}</p>
            <p class="mt-1 text-xs text-muted">Mediana între tractări: ${t.median_minutes_between_tows ? Math.round(t.median_minutes_between_tows) + ' min' : '—'} · anomalii: ${t.tows_anomalies ?? 0}</p>
            ${open ? `<h3 class="mt-4 mb-2 text-sm font-bold text-secondary">Sănii încă afară</h3><div class="space-y-1">${open}</div>` : ''}
            ${gaps ? `<h3 class="mt-4 mb-1 text-sm font-bold text-secondary">Pauze suspecte între tractări (posibile scanări uitate)</h3><ul class="text-sm list-disc list-inside text-muted">${gaps}</ul>` : ''}
            ${anomalies ? `<h3 class="mt-4 mb-1 text-sm font-bold text-secondary">Scanări netaxate</h3><ul class="text-sm list-disc list-inside text-muted">${anomalies}</ul>` : ''}
            ${unlocks ? `<h3 class="mt-4 mb-1 text-sm font-bold text-secondary">Deblocări forțate</h3><ul class="text-sm list-disc list-inside text-muted">${unlocks}</ul>` : ''}`;
        document.querySelectorAll('#sn-report [data-lost]').forEach(b => b.addEventListener('click', async () => {
            if (!confirm('Treci sania ca pierdută? Contul clientului primește chiria, tractările și taxa de sanie pierdută.')) return;
            const r = await call(() => AmbiletAPI.post(base() + `/rentals/${b.dataset.lost}/lost`, {}));
            if (r) { flash(r.message || 'Gata'); loadReport(); loadSleds(); }
        }));
    }

    window.addEventListener('load', async () => {
        if (typeof AmbiletAuth !== 'undefined') AmbiletAuth.requireOrganizerAuth();
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 20) { await new Promise(r => setTimeout(r, 100)); retries++; }
        try {
            await loadEvent();
            await loadSettings();
        } catch (e) {
            showError(e?.message || 'Nu am putut încărca setările.');
            return;
        }
        sledsTest = settings.mode !== 'live';
        $('sn-rep-test').checked = settings.mode !== 'live';
        $('sn-rep-date').value = new Date().toLocaleDateString('sv-SE', { timeZone: 'Europe/Bucharest' });

        document.querySelectorAll('#sn-modes button').forEach(b => b.addEventListener('click', () => { if (!b.disabled) setMode(b.dataset.mode); }));
        $('sn-save-members').addEventListener('click', saveMembers);
        $('sn-save-rules').addEventListener('click', saveRules);
        $('sn-save-contract').addEventListener('click', saveContract);
        renderContract();
        $('sn-bootstrap').addEventListener('click', bootstrapProducts);
        document.querySelectorAll('#sn-sled-kind button').forEach(b => b.addEventListener('click', () => { sledsTest = b.dataset.test === '1'; loadSleds(); }));
        $('sn-sled-add').addEventListener('click', addSleds);
        $('sn-sled-print').addEventListener('click', printSleds);
        $('sn-tow-refresh').addEventListener('click', refreshTow);
        $('sn-rep-load').addEventListener('click', loadReport);

        loadSleds();
        refreshTow();
        loadReport();
        setInterval(refreshTow, 15000);
    });
})();
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
