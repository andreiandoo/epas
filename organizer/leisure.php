<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Locație de agrement';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

<!-- Main Content -->
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <!-- Header -->
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Locație de agrement</h1>
                <p class="mt-1 text-sm text-muted">
                    Configurare bilete, capacități zilnice, rapoarte fiscale și conținut pagină publică.
                </p>
            </div>
            <!-- Tab switcher -->
            <div class="inline-flex rounded-xl bg-white border border-border p-1">
                <button type="button" id="tab-btn-overview" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors bg-primary text-white">Sumar & raport</button>
                <button type="button" id="tab-btn-content" class="px-4 py-2 text-sm font-medium rounded-lg transition-colors text-muted hover:bg-slate-50">Conținut pagină</button>
            </div>
        </div>

        <!-- State: no leisure events -->
        <div id="leisure-empty" class="hidden p-8 text-center bg-white border rounded-2xl border-border">
            <div class="inline-flex items-center justify-center w-16 h-16 mb-4 bg-slate-100 rounded-full">
                <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10m14-10v10M9 21h6m-6 0a2 2 0 01-2-2v-4a2 2 0 012-2h6a2 2 0 012 2v4a2 2 0 01-2 2"/>
                </svg>
            </div>
            <h2 class="mb-2 text-lg font-semibold text-secondary">Niciun eveniment de tip „Locație de agrement"</h2>
            <p class="max-w-md mx-auto text-sm text-muted">
                Pentru a vedea date aici, creează un eveniment cu „Tip pagină" = „Locație de agrement".
                Fiecare tip de bilet poate fi atribuit societății principale sau secundare.
            </p>
        </div>

        <!-- Event picker (when more than one leisure event) -->
        <div id="leisure-event-picker" class="hidden mb-6">
            <label class="block mb-2 text-sm font-medium text-secondary">Eveniment</label>
            <select id="leisure-event-select" class="w-full max-w-md px-4 py-2 bg-white border rounded-lg border-border focus:outline-none focus:ring-2 focus:ring-primary">
            </select>
        </div>

        <!-- Loading -->
        <div id="leisure-loading" class="p-8 text-center">
            <div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div>
            <p class="mt-2 text-sm text-muted">Se încarcă...</p>
        </div>

        <!-- Tab: Conținut pagină (initial hidden) -->
        <div id="tab-content" class="hidden space-y-6">
            <div class="p-5 bg-white border rounded-2xl border-border">
                <div class="flex items-center gap-3 mb-2">
                    <div class="w-9 h-9 bg-primary/10 text-primary rounded-xl flex items-center justify-center">
                        ✏️
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-secondary">Editor conținut pagină publică</h2>
                        <p class="text-xs text-muted">Modificările apar imediat pe pagina publică după salvare.</p>
                    </div>
                </div>
            </div>

            <!-- HERO & IDENTITATE -->
            <details class="bg-white border rounded-2xl border-border" open>
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>🎨 Hero & Identitate</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-4 border-t border-border">
                    <div class="grid md:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="text-xs font-semibold text-muted uppercase tracking-wider">Titlu principal</span>
                            <input type="text" data-vc="title_primary" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="ex: Lacul Sfânta Ana">
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-muted uppercase tracking-wider">Subtitlu italic</span>
                            <input type="text" data-vc="title_secondary" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="ex: & Tinovul Mohoș">
                        </label>
                    </div>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Kicker (text mic deasupra titlului)</span>
                        <input type="text" data-vc="hero_kicker" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="ex: Rezervație naturală protejată">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Badge-uri hero (separat prin virgulă)</span>
                        <input type="text" data-vc-list="hero_badges" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="🌿 Sit Natura 2000, 🏔️ Altitudine 950m, Jud. Harghita">
                        <span class="text-xs text-muted mt-1 block">Folosește emoji + text. Separă cu virgulă.</span>
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Titlu secțiune "Despre"</span>
                        <input type="text" data-vc="about_title" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="ex: Două cratere, o poveste">
                    </label>
                </div>
            </details>

            <!-- CONTACT & LINK-URI -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>📞 Contact & Link-uri</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-4 border-t border-border">
                    <div class="grid md:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="text-xs font-semibold text-muted uppercase tracking-wider">Telefon contact (cu prefix)</span>
                            <input type="text" data-vc="contact_phone" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="+40 752 171 050">
                            <span class="text-xs text-muted mt-1 block">Folosit pentru buton WhatsApp și bara info.</span>
                        </label>
                        <label class="block">
                            <span class="text-xs font-semibold text-muted uppercase tracking-wider">URL Google Maps</span>
                            <input type="url" data-vc="directions_url" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="https://maps.app.goo.gl/...">
                        </label>
                    </div>
                </div>
            </details>

            <!-- SAFETY WARNING -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>⚠️ Atenționare siguranță (afișată sub trasee)</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-4 border-t border-border">
                    <div class="grid md:grid-cols-4 gap-4">
                        <label class="block">
                            <span class="text-xs font-semibold text-muted uppercase tracking-wider">Emoji</span>
                            <input type="text" data-vc-nested="safety_warning.icon" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="🐻" maxlength="6">
                        </label>
                        <label class="block md:col-span-3">
                            <span class="text-xs font-semibold text-muted uppercase tracking-wider">Titlu</span>
                            <input type="text" data-vc-nested="safety_warning.title" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="Zonă cu urși">
                        </label>
                    </div>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted uppercase tracking-wider">Mesaj</span>
                        <textarea data-vc-nested="safety_warning.body" rows="3" class="vc-input mt-1 w-full px-3 py-2 border border-border rounded-lg" placeholder="Detalii recomandare..."></textarea>
                    </label>
                </div>
            </details>

            <!-- FAQ -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>❓ Întrebări frecvente (FAQ)</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-3 border-t border-border">
                    <div id="faq-list" class="space-y-3"></div>
                    <button type="button" id="faq-add" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg transition-colors">+ Adaugă întrebare</button>
                </div>
            </details>

            <!-- STATS HIGHLIGHTS -->
            <details class="bg-white border rounded-2xl border-border">
                <summary class="px-5 py-4 cursor-pointer font-semibold text-secondary flex items-center justify-between hover:bg-slate-50 rounded-2xl">
                    <span>📊 Statistici mari (numere)</span>
                    <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </summary>
                <div class="px-5 pb-5 pt-2 space-y-3 border-t border-border">
                    <div id="stats-list" class="space-y-3"></div>
                    <button type="button" id="stats-add" class="px-4 py-2 text-sm font-medium text-primary bg-primary/10 hover:bg-primary/20 rounded-lg transition-colors">+ Adaugă statistică</button>
                </div>
            </details>

            <!-- INFO RESTUL SECTIUNILOR -->
            <div class="p-5 bg-amber-50 border border-amber-200 rounded-2xl text-sm text-amber-900">
                <p class="font-semibold mb-1">ℹ️ Pentru editarea atracțiilor, traseelor, hărții, hotelurilor, galeriei, video-urilor și a programului:</p>
                <p>Accesează panoul Tixello → <a id="admin-edit-link" href="#" target="_blank" class="font-bold underline text-primary">core.tixello.com/marketplace/events/.../edit</a> → tab "Configurare Locație".</p>
            </div>

            <!-- Save bar -->
            <div class="sticky bottom-4 z-30">
                <div class="bg-primary text-white rounded-2xl px-5 py-4 shadow-2xl flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span id="vc-save-status" class="text-sm font-medium">Modificările sunt salvate manual.</span>
                    </div>
                    <button type="button" id="vc-save-btn" class="px-5 py-2 bg-white text-primary font-bold rounded-lg hover:bg-primary-dark hover:text-white transition-colors disabled:opacity-50">
                        💾 Salvează
                    </button>
                </div>
            </div>
        </div>

        <!-- Tab: Sumar & raport (existing) -->
        <div id="leisure-content" class="hidden space-y-6">
            <!-- Issuer overview -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div id="issuer-primary" class="p-5 bg-white border rounded-2xl border-border">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase rounded text-primary bg-primary/10">
                            Societate principală
                        </span>
                    </div>
                    <p class="text-lg font-semibold text-secondary" data-issuer="primary.name">—</p>
                    <p class="text-sm text-muted" data-issuer="primary.tax_id"></p>
                    <p class="mt-1 text-xs text-muted" data-issuer="primary.address"></p>
                </div>
                <div id="issuer-secondary" class="hidden p-5 bg-white border rounded-2xl border-border">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase rounded text-accent bg-accent/10">
                            Societate secundară
                        </span>
                    </div>
                    <p class="text-lg font-semibold text-secondary" data-issuer="secondary.name">—</p>
                    <p class="text-sm text-muted" data-issuer="secondary.tax_id"></p>
                    <p class="mt-1 text-xs text-muted" data-issuer="secondary.address"></p>
                </div>
            </div>

            <!-- Ticket types config -->
            <div class="bg-white border rounded-2xl border-border">
                <div class="flex items-center justify-between px-5 py-4 border-b border-border">
                    <h2 class="font-semibold text-secondary">Tipuri de bilete și emitent</h2>
                    <a id="leisure-edit-event-link" href="#" target="_blank"
                       class="text-xs font-medium text-primary hover:underline">
                        Editează în panou Tixello →
                    </a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase bg-slate-50 text-muted">
                            <tr>
                                <th class="px-5 py-3 text-left">Bilet</th>
                                <th class="px-5 py-3 text-left">Categorie</th>
                                <th class="px-5 py-3 text-left">Capacitate / zi</th>
                                <th class="px-5 py-3 text-left">Emitent factură</th>
                            </tr>
                        </thead>
                        <tbody id="ticket-types-rows" class="divide-y divide-border">
                            <tr><td class="px-5 py-4 text-center text-muted" colspan="4">Nu există tipuri de bilete.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Reports per issuer -->
            <div class="bg-white border rounded-2xl border-border">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-border">
                    <h2 class="font-semibold text-secondary">Vânzări pe societate emitentă</h2>
                    <div class="flex items-center gap-2">
                        <input id="leisure-from" type="date" class="px-3 py-1.5 text-sm bg-white border rounded-lg border-border">
                        <span class="text-xs text-muted">→</span>
                        <input id="leisure-to" type="date" class="px-3 py-1.5 text-sm bg-white border rounded-lg border-border">
                        <button id="leisure-refresh-report"
                                class="px-3 py-1.5 text-sm font-medium text-white bg-primary rounded-lg hover:bg-primary-dark">
                            Reîncarcă
                        </button>
                    </div>
                </div>
                <div id="leisure-report-rows" class="divide-y divide-border">
                    <div class="p-5 text-center text-sm text-muted">Selectează o perioadă și apasă „Reîncarcă".</div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));

    let leisureEvents = [];
    let currentEventId = null;

    async function loadEvents() {
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const all = res.data || [];
            leisureEvents = all.filter((e) => (e.display_template || 'standard') === 'leisure_venue');

            if (leisureEvents.length === 0) {
                $('leisure-loading').classList.add('hidden');
                $('leisure-empty').classList.remove('hidden');
                return;
            }

            const select = $('leisure-event-select');
            select.innerHTML = leisureEvents.map((e) => {
                const title = e.name || (e.title && (e.title.ro || e.title.en || Object.values(e.title)[0])) || `Event #${e.id}`;
                return `<option value="${e.id}">${escapeHtml(title)} (#${e.id})</option>`;
            }).join('');

            if (leisureEvents.length > 1) {
                $('leisure-event-picker').classList.remove('hidden');
            }

            select.addEventListener('change', () => loadEventDetails(parseInt(select.value, 10)));
            await loadEventDetails(leisureEvents[0].id);
        } catch (err) {
            console.error('loadEvents error', err);
            $('leisure-loading').textContent = 'Eroare la încărcarea evenimentelor.';
        }
    }

    async function loadEventDetails(eventId) {
        currentEventId = eventId;
        $('leisure-loading').classList.remove('hidden');
        $('leisure-content').classList.add('hidden');

        try {
            const config = await AmbiletAPI.get(`/organizer/events/${eventId}/leisure/config`);
            renderConfig(config.data || {});

            // Update edit link
            const tixelloAdminUrl = (window.TIXELLO_ADMIN_URL || 'https://core.tixello.com');
            $('leisure-edit-event-link').href = `${tixelloAdminUrl}/marketplace/events/${eventId}/edit?tab=bilete`;

            // Default range: last 30 days
            const today = new Date();
            const past = new Date(today);
            past.setDate(today.getDate() - 30);
            $('leisure-from').value = past.toISOString().slice(0, 10);
            $('leisure-to').value = today.toISOString().slice(0, 10);

            await loadReport();
        } catch (err) {
            console.error('loadEventDetails error', err);
            $('leisure-loading').textContent = 'Eroare la încărcarea detaliilor evenimentului.';
            return;
        }

        $('leisure-loading').classList.add('hidden');
        $('leisure-content').classList.remove('hidden');
    }

    function renderConfig(data) {
        // Issuers
        const issuers = data.issuers || {};
        ['primary', 'secondary'].forEach((key) => {
            const block = $('issuer-' + key);
            const issuer = issuers[key];
            if (!issuer || !issuer.name) {
                if (key === 'secondary') block.classList.add('hidden');
                return;
            }
            if (key === 'secondary') block.classList.remove('hidden');
            block.querySelectorAll('[data-issuer]').forEach((el) => {
                const path = el.getAttribute('data-issuer').split('.');
                if (path[0] !== key) return;
                const value = issuer[path[1]];
                el.textContent = value || '';
            });
        });

        // Ticket types
        const types = data.ticket_types || [];
        const rows = $('ticket-types-rows');
        if (types.length === 0) {
            rows.innerHTML = '<tr><td class="px-5 py-4 text-center text-muted" colspan="4">Nu există tipuri de bilete.</td></tr>';
            return;
        }

        const categoryLabel = {
            access: 'Acces', parking: 'Parcare', rental: 'Închiriere',
            activity: 'Activitate', extra: 'Alt produs',
        };

        rows.innerHTML = types.map((tt) => {
            const cat = tt.service_category || 'access';
            const company = tt.issuing_company || 'primary';
            const issuerData = issuers[company] || issuers.primary || {};
            const issuerName = issuerData.name || (company === 'secondary' ? 'Societate secundară' : 'Societate principală');
            const companyBadge = company === 'secondary'
                ? '<span class="px-2 py-0.5 text-[10px] font-bold rounded bg-accent/10 text-accent">SECUNDARĂ</span>'
                : '<span class="px-2 py-0.5 text-[10px] font-bold rounded bg-primary/10 text-primary">PRINCIPALĂ</span>';
            const cap = tt.daily_capacity ? tt.daily_capacity + ' bilete/zi' : '<span class="text-muted">—</span>';
            return `
                <tr>
                    <td class="px-5 py-3 font-medium text-secondary">${escapeHtml(tt.name)}</td>
                    <td class="px-5 py-3 text-muted">${escapeHtml(categoryLabel[cat] || cat)}</td>
                    <td class="px-5 py-3 text-muted">${cap}</td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            ${companyBadge}
                            <span class="text-sm text-secondary">${escapeHtml(issuerName)}</span>
                        </div>
                    </td>
                </tr>`;
        }).join('');
    }

    async function loadReport() {
        if (!currentEventId) return;
        const from = $('leisure-from').value;
        const to = $('leisure-to').value;
        const params = new URLSearchParams({ from, to }).toString();

        const container = $('leisure-report-rows');
        container.innerHTML = '<div class="p-5 text-center text-sm text-muted">Se încarcă raportul...</div>';

        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/reports/by-issuer?${params}`);
            const rows = (res.data && res.data.rows) || [];
            const currency = (res.data && res.data.currency) || 'RON';

            if (rows.length === 0) {
                container.innerHTML = '<div class="p-5 text-center text-sm text-muted">Nicio vânzare în perioada selectată.</div>';
                return;
            }

            const categoryLabel = {
                access: 'Acces', parking: 'Parcare', rental: 'Închiriere',
                activity: 'Activitate', extra: 'Alt produs',
            };

            container.innerHTML = rows.map((row) => {
                const company = row.company || 'primary';
                const issuer = row.issuer || {};
                const issuerName = issuer.name || (company === 'secondary' ? 'Societate secundară' : 'Societate principală');
                const badge = company === 'secondary'
                    ? '<span class="px-2 py-0.5 text-[10px] font-bold rounded bg-accent/10 text-accent">SECUNDARĂ</span>'
                    : '<span class="px-2 py-0.5 text-[10px] font-bold rounded bg-primary/10 text-primary">PRINCIPALĂ</span>';
                const cif = issuer.tax_id ? ` (CIF ${escapeHtml(issuer.tax_id)})` : '';

                let categoriesHtml = '';
                if (row.by_category && Object.keys(row.by_category).length > 0) {
                    const items = Object.entries(row.by_category).map(([cat, info]) => {
                        return `<div class="flex justify-between text-xs">
                            <span class="text-muted">${escapeHtml(categoryLabel[cat] || cat)}</span>
                            <span class="font-medium text-secondary">${info.count} • ${parseFloat(info.subtotal).toFixed(2)} ${currency}</span>
                        </div>`;
                    }).join('');
                    categoriesHtml = `<div class="mt-3 pt-3 border-t border-border space-y-1">${items}</div>`;
                }

                return `
                    <div class="p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                ${badge}
                                <span class="font-semibold text-secondary">${escapeHtml(issuerName)}${cif}</span>
                            </div>
                            <div class="text-right">
                                <p class="text-xl font-bold text-secondary">${parseFloat(row.subtotal).toFixed(2)} ${currency}</p>
                                <p class="text-xs text-muted">${row.tickets_count} bilete • ${row.orders_count} comenzi</p>
                            </div>
                        </div>
                        ${categoriesHtml}
                    </div>`;
            }).join('');
        } catch (err) {
            console.error('loadReport error', err);
            container.innerHTML = '<div class="p-5 text-center text-sm text-error">Eroare la încărcarea raportului.</div>';
        }
    }

    // ========== TAB SWITCHING ==========
    function setupTabs() {
        const btnOverview = $('tab-btn-overview');
        const btnContent = $('tab-btn-content');
        const overview = $('leisure-content');
        const content = $('tab-content');
        const loading = $('leisure-loading');
        const picker = $('leisure-event-picker');
        const empty = $('leisure-empty');

        if (!btnOverview || !btnContent) return;

        function activate(which) {
            if (which === 'overview') {
                btnOverview.classList.add('bg-primary', 'text-white');
                btnOverview.classList.remove('text-muted', 'hover:bg-slate-50');
                btnContent.classList.remove('bg-primary', 'text-white');
                btnContent.classList.add('text-muted', 'hover:bg-slate-50');
                content.classList.add('hidden');
                if (leisureEvents.length === 0) { empty.classList.remove('hidden'); }
                else if (currentEventId) { overview.classList.remove('hidden'); }
            } else {
                btnContent.classList.add('bg-primary', 'text-white');
                btnContent.classList.remove('text-muted', 'hover:bg-slate-50');
                btnOverview.classList.remove('bg-primary', 'text-white');
                btnOverview.classList.add('text-muted', 'hover:bg-slate-50');
                overview.classList.add('hidden');
                empty.classList.add('hidden');
                content.classList.remove('hidden');
                hydrateContentForm();
            }
        }

        btnOverview.addEventListener('click', () => activate('overview'));
        btnContent.addEventListener('click', () => activate('content'));
    }

    // ========== CONTENT EDITOR ==========
    let currentVenueConfig = {};

    function hydrateContentForm() {
        const ev = leisureEvents.find(e => e.id === currentEventId);
        if (!ev) return;
        currentVenueConfig = ev.venue_config || {};

        // Update admin link
        const adminLink = $('admin-edit-link');
        if (adminLink) adminLink.href = `https://core.tixello.com/marketplace/events/${ev.id}/edit?tab=venue-config`;

        // Populate simple scalar fields
        document.querySelectorAll('[data-vc]').forEach((input) => {
            const key = input.dataset.vc;
            input.value = currentVenueConfig[key] || '';
        });

        // Populate list fields (CSV)
        document.querySelectorAll('[data-vc-list]').forEach((input) => {
            const key = input.dataset.vcList;
            const arr = Array.isArray(currentVenueConfig[key]) ? currentVenueConfig[key] : [];
            input.value = arr.join(', ');
        });

        // Populate nested fields (e.g. safety_warning.title)
        document.querySelectorAll('[data-vc-nested]').forEach((input) => {
            const path = input.dataset.vcNested.split('.');
            let val = currentVenueConfig;
            for (const p of path) val = (val && val[p]) ? val[p] : '';
            input.value = typeof val === 'string' ? val : '';
        });

        // Populate FAQ list
        const faqList = $('faq-list');
        if (faqList) {
            faqList.innerHTML = '';
            (currentVenueConfig.faqs || []).forEach((f, i) => faqList.appendChild(makeFaqRow(f.q || '', f.a || '', i)));
            if ((currentVenueConfig.faqs || []).length === 0) faqList.appendChild(makeFaqRow('', '', 0));
        }

        // Populate stats highlights
        const statsList = $('stats-list');
        if (statsList) {
            statsList.innerHTML = '';
            (currentVenueConfig.stats_highlights || []).forEach((s, i) => statsList.appendChild(makeStatRow(s.value || '', s.label || '', i)));
            if ((currentVenueConfig.stats_highlights || []).length === 0) statsList.appendChild(makeStatRow('', '', 0));
        }
    }

    function makeFaqRow(q, a, idx) {
        const wrap = document.createElement('div');
        wrap.className = 'p-3 bg-slate-50 rounded-lg space-y-2';
        wrap.innerHTML = `
            <div class="flex items-start gap-2">
                <input type="text" class="faq-q flex-1 px-3 py-2 border border-border rounded-lg bg-white" placeholder="Întrebare" value="${escapeHtml(q)}">
                <button type="button" class="faq-remove p-2 text-error hover:bg-red-100 rounded-lg">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22"/></svg>
                </button>
            </div>
            <textarea class="faq-a w-full px-3 py-2 border border-border rounded-lg bg-white text-sm" rows="2" placeholder="Răspuns (poate conține HTML simplu)">${escapeHtml(a)}</textarea>
        `;
        wrap.querySelector('.faq-remove').addEventListener('click', () => wrap.remove());
        return wrap;
    }

    function makeStatRow(value, label) {
        const wrap = document.createElement('div');
        wrap.className = 'flex items-start gap-2';
        wrap.innerHTML = `
            <input type="text" class="stat-value flex-1 px-3 py-2 border border-border rounded-lg" placeholder="Valoare (ex: 30k, 950m)" value="${escapeHtml(value)}">
            <input type="text" class="stat-label flex-1 px-3 py-2 border border-border rounded-lg" placeholder="Etichetă (ex: Ani de la formare)" value="${escapeHtml(label)}">
            <button type="button" class="stat-remove p-2 text-error hover:bg-red-100 rounded-lg">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22"/></svg>
            </button>
        `;
        wrap.querySelector('.stat-remove').addEventListener('click', () => wrap.remove());
        return wrap;
    }

    function setNested(obj, pathStr, value) {
        const path = pathStr.split('.');
        let cur = obj;
        for (let i = 0; i < path.length - 1; i++) {
            if (!cur[path[i]] || typeof cur[path[i]] !== 'object') cur[path[i]] = {};
            cur = cur[path[i]];
        }
        cur[path[path.length - 1]] = value;
    }

    async function saveContent() {
        if (!currentEventId) return;
        const btn = $('vc-save-btn');
        const status = $('vc-save-status');
        btn.disabled = true;
        status.textContent = 'Se salvează...';

        const payload = {};

        // Scalar fields
        document.querySelectorAll('[data-vc]').forEach((input) => {
            payload[input.dataset.vc] = input.value.trim();
        });

        // List fields
        document.querySelectorAll('[data-vc-list]').forEach((input) => {
            const arr = input.value.split(',').map(s => s.trim()).filter(Boolean);
            payload[input.dataset.vcList] = arr;
        });

        // Nested fields
        document.querySelectorAll('[data-vc-nested]').forEach((input) => {
            setNested(payload, input.dataset.vcNested, input.value.trim());
        });

        // FAQ list
        const faqs = [];
        document.querySelectorAll('#faq-list > div').forEach((row) => {
            const q = row.querySelector('.faq-q')?.value.trim() || '';
            const a = row.querySelector('.faq-a')?.value.trim() || '';
            if (q || a) faqs.push({ q, a });
        });
        payload.faqs = faqs;

        // Stats highlights
        const stats = [];
        document.querySelectorAll('#stats-list > div').forEach((row) => {
            const value = row.querySelector('.stat-value')?.value.trim() || '';
            const label = row.querySelector('.stat-label')?.value.trim() || '';
            if (value || label) stats.push({ value, label });
        });
        payload.stats_highlights = stats;

        try {
            const res = await AmbiletAPI.put(`/organizer/events/${currentEventId}/leisure/venue-config`, { venue_config: payload });
            if (res.success) {
                status.textContent = '✓ Salvat. Recarcă pagina publică (?preview=1) pentru a vedea modificările.';
                status.classList.remove('text-red-200');
                // Update local cache
                currentVenueConfig = res.data?.venue_config || currentVenueConfig;
                const ev = leisureEvents.find(e => e.id === currentEventId);
                if (ev) ev.venue_config = currentVenueConfig;
                setTimeout(() => { status.textContent = 'Modificările sunt salvate manual.'; }, 4000);
            } else {
                status.textContent = '✗ Eroare: ' + (res.message || 'unknown');
                status.classList.add('text-red-200');
            }
        } catch (err) {
            console.error(err);
            status.textContent = '✗ Eroare conexiune';
            status.classList.add('text-red-200');
        }

        btn.disabled = false;
    }

    document.addEventListener('DOMContentLoaded', () => {
        $('leisure-refresh-report').addEventListener('click', () => loadReport());
        $('faq-add')?.addEventListener('click', () => {
            const list = $('faq-list');
            if (list) list.appendChild(makeFaqRow('', '', list.children.length));
        });
        $('stats-add')?.addEventListener('click', () => {
            const list = $('stats-list');
            if (list) list.appendChild(makeStatRow('', '', list.children.length));
        });
        $('vc-save-btn')?.addEventListener('click', () => saveContent());
        setupTabs();
    });

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) {
            await new Promise((r) => setTimeout(r, 100));
            retries++;
        }
        if (typeof AmbiletAPI === 'undefined') {
            $('leisure-loading').textContent = 'API indisponibil.';
            return;
        }
        await loadEvents();
    });
})();
</script>

<?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
