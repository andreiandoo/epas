<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Facturi cerute de clienți';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'company_invoices';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Facturi cerute de clienți</h1>
            <p class="max-w-3xl mt-1 text-sm text-muted">Clienții care au cerut factură pe firmă pentru biletele tale. Emite factura cu datele de mai jos (valoarea biletelor, cu TVA-ul tău inclus dacă ești plătitor) și încarc-o aici în PDF. O trimitem noi clientului pe email. Comisionul și serviciile platformei le facturează separat <?= htmlspecialchars(SITE_NAME) ?>.</p>
        </div>

        <div class="flex flex-wrap gap-2 mb-4" role="tablist" aria-label="Filtru stare">
            <button type="button" data-status="requested" class="ci-tab px-3 py-1.5 text-sm font-medium border rounded-lg border-border bg-white">De emis <span id="ci-pending-count" class="ml-1 text-xs font-bold text-primary"></span></button>
            <button type="button" data-status="uploaded" class="ci-tab px-3 py-1.5 text-sm font-medium border rounded-lg border-border bg-white">Trimise</button>
            <button type="button" data-status="" class="ci-tab px-3 py-1.5 text-sm font-medium border rounded-lg border-border bg-white">Toate</button>
        </div>

        <div id="ci-error" class="hidden p-4 mb-4 text-sm border bg-rose-50 border-rose-200 rounded-xl text-rose-900" role="alert"></div>
        <div id="ci-loading" class="p-8 text-center"><div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div></div>
        <div id="ci-empty" class="hidden p-10 text-center bg-white border rounded-2xl border-border">
            <p class="font-semibold text-secondary">Nicio cerere aici</p>
            <p class="mt-1 text-sm text-muted">Când un client cere factură pe firmă pentru biletele tale, cererea apare în această listă și primești și un email.</p>
        </div>
        <div id="ci-list" class="space-y-4"></div>
    </main>
</div>

<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    const money = (v, cur) => Number(v || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + (cur || 'RON');
    const date = (iso) => iso ? new Date(iso).toLocaleString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
    let status = 'requested';

    function badge(item) {
        const map = {
            requested: 'bg-amber-100 text-amber-800',
            uploaded: 'bg-emerald-100 text-emerald-800',
            not_applicable: 'bg-slate-100 text-slate-700',
        };
        return `<span class="px-2 py-0.5 text-xs font-medium rounded ${map[item.status] || 'bg-slate-100 text-slate-700'}">${esc(item.status_label)}</span>`;
    }

    function card(item) {
        const lines = (item.lines || []).map(l => `<tr class="border-t border-border">
                <td class="py-2 pr-3">${esc(l.name)}</td>
                <td class="py-2 pr-3 text-center tabular-nums">${esc(l.quantity)}</td>
                <td class="py-2 pr-3 text-right tabular-nums">${money(l.unit_price, item.currency)}</td>
                <td class="py-2 text-right tabular-nums">${money(l.amount, item.currency)}</td></tr>`).join('');
        const sent = item.sent_to_customer_at
            ? `<p class="text-xs text-emerald-700">Trimisă clientului pe ${date(item.sent_to_customer_at)}${item.file_name ? ' · ' + esc(item.file_name) : ''}</p>`
            : '';
        const actions = item.status === 'not_applicable' ? '' : `
            <div class="flex flex-wrap items-center gap-3 pt-4 mt-4 border-t border-border">
                <label class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-lg cursor-pointer bg-primary hover:opacity-90">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    ${item.status === 'uploaded' ? 'Înlocuiește factura' : 'Încarcă factura (PDF)'}
                    <input type="file" accept="application/pdf" class="sr-only" data-upload="${item.id}">
                </label>
                ${item.file_name ? `<button type="button" data-view="${item.id}" class="px-4 py-2 text-sm font-medium bg-white border rounded-lg border-border text-secondary hover:bg-slate-50">Vezi factura încărcată</button>` : ''}
                <span class="text-xs text-muted" data-progress="${item.id}"></span>
            </div>`;

        return `<article class="p-5 bg-white border rounded-2xl border-border">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs text-muted">Comanda <strong class="text-secondary">${esc(item.order_number)}</strong> · plătită ${date(item.paid_at)}</p>
                    <h2 class="mt-1 text-lg font-bold text-secondary">${esc(item.company.name)}</h2>
                    <p class="text-sm text-muted">CUI ${esc(item.company.cui)}${item.company.reg_com ? ' · ' + esc(item.company.reg_com) : ''}</p>
                    <p class="text-sm text-muted">${esc(item.company.address)}</p>
                </div>
                <div class="text-right">
                    ${badge(item)}
                    <p class="mt-2 text-2xl font-bold tabular-nums text-secondary">${money(item.amount, item.currency)}</p>
                    <p class="text-xs text-muted">de facturat</p>
                </div>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-xs text-left uppercase text-muted"><th class="pb-2 pr-3 font-semibold">Bilete</th><th class="pb-2 pr-3 font-semibold text-center">Cant.</th><th class="pb-2 pr-3 font-semibold text-right">Preț</th><th class="pb-2 font-semibold text-right">Total</th></tr></thead>
                    <tbody>${lines}</tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-muted">Cumpărător: ${esc(item.buyer.name)} · ${esc(item.buyer.email)}</p>
            ${sent}
            ${actions}
        </article>`;
    }

    async function load() {
        $('ci-error').classList.add('hidden');
        $('ci-loading').classList.remove('hidden');
        $('ci-list').innerHTML = '';
        $('ci-empty').classList.add('hidden');
        document.querySelectorAll('.ci-tab').forEach(b => {
            const on = b.dataset.status === status;
            b.classList.toggle('bg-secondary', on);
            b.classList.toggle('text-white', on);
            b.classList.toggle('bg-white', !on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        try {
            const res = await AmbiletAPI.get('/organizer/company-invoices' + (status ? '?status=' + status : ''));
            const data = res.data || {};
            const items = data.items || [];
            if (status === 'requested') $('ci-pending-count').textContent = items.length ? items.length : '';
            if (!items.length) {
                $('ci-empty').classList.remove('hidden');
            } else {
                $('ci-list').innerHTML = items.map(card).join('');
            }
        } catch (e) {
            $('ci-error').textContent = e.message || 'Lista nu a putut fi încărcată.';
            $('ci-error').classList.remove('hidden');
        } finally {
            $('ci-loading').classList.add('hidden');
        }
    }

    async function upload(input) {
        const id = input.dataset.upload;
        const file = input.files && input.files[0];
        if (!file) return;
        const progress = document.querySelector(`[data-progress="${id}"]`);
        if (file.type !== 'application/pdf') {
            progress.textContent = 'Încarcă un fișier PDF.';
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            progress.textContent = 'Fișierul depășește 10 MB.';
            return;
        }
        progress.textContent = 'Se încarcă și se trimite clientului...';
        input.disabled = true;
        const fd = new FormData();
        fd.append('file', file);
        try {
            await AmbiletAPI.upload('/organizer/company-invoices/' + id + '/upload', fd);
            if (typeof AmbiletNotifications !== 'undefined') AmbiletNotifications.success('Factura a fost trimisă clientului.');
            load();
        } catch (e) {
            progress.textContent = e.message || 'Încărcarea a eșuat.';
            input.disabled = false;
        }
    }

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 20) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined') {
            $('ci-loading').classList.add('hidden');
            $('ci-error').textContent = 'Resurse JS indisponibile, reîncarcă pagina.';
            $('ci-error').classList.remove('hidden');
            return;
        }
        document.querySelectorAll('.ci-tab').forEach(b => b.addEventListener('click', () => { status = b.dataset.status; load(); }));
        $('ci-list').addEventListener('change', (e) => { if (e.target.matches('[data-upload]')) upload(e.target); });
        $('ci-list').addEventListener('click', async (e) => {
            const btn = e.target.closest('[data-view]');
            if (!btn) return;
            btn.disabled = true;
            try {
                await AmbiletAPI.downloadFile('/organizer/company-invoices/' + btn.dataset.view + '/file', 'factura.pdf');
            } catch (err) {
                if (typeof AmbiletNotifications !== 'undefined') AmbiletNotifications.error(err.message || 'Fișierul nu este disponibil.');
            } finally {
                btn.disabled = false;
            }
        });
        load();
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
