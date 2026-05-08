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
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Locație de agrement</h1>
            <p class="mt-1 text-sm text-muted">
                Configurare bilete, capacități zilnice și rapoarte fiscale pe cele 2 societăți emitente.
            </p>
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

        <!-- Content -->
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

    document.addEventListener('DOMContentLoaded', () => {
        $('leisure-refresh-report').addEventListener('click', () => loadReport());
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
