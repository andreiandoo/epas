<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Facturile mele';
$currentPage = 'invoices';
$cssBundle = 'account';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<?php require_once dirname(__DIR__) . '/includes/user-wrap.php'; ?>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-secondary">Facturile mele</h1>
            <p class="mt-1 text-sm text-muted">Facturile pe firmă cerute la comenzi: cea AmBilet pentru comision și servicii și cea a organizatorului pentru bilete.</p>
        </div>

        <div class="flex flex-wrap gap-2 mb-5" role="tablist" aria-label="Filtrează facturile">
            <button type="button" class="inv-filter px-4 py-2 text-sm font-medium rounded-xl bg-primary text-white" data-filter="" aria-selected="true">Toate</button>
            <button type="button" class="inv-filter px-4 py-2 text-sm font-medium rounded-xl bg-surface text-secondary hover:bg-primary/10" data-filter="marketplace" aria-selected="false">AmBilet (comision și servicii)</button>
            <button type="button" class="inv-filter px-4 py-2 text-sm font-medium rounded-xl bg-surface text-secondary hover:bg-primary/10" data-filter="organizer" aria-selected="false">Organizatori (bilete)</button>
        </div>

        <div id="invoices-list" class="space-y-3" aria-live="polite">
            <div class="py-8 text-center">
                <div class="w-8 h-8 mx-auto border-4 rounded-full animate-spin border-primary border-t-transparent"></div>
                <p class="mt-2 text-muted">Se încarcă facturile...</p>
            </div>
        </div>
<?php
require_once dirname(__DIR__) . '/includes/user-wrap-end.php';
require_once dirname(__DIR__) . '/includes/user-footer.php';
?>

<?php
$scriptsExtra = <<<'JS'
<script>
const InvoicesPage = {
    invoices: [],
    filter: '',

    init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=/cont/facturi';
            return;
        }
        document.querySelectorAll('.inv-filter').forEach(btn => btn.addEventListener('click', () => {
            this.filter = btn.dataset.filter;
            document.querySelectorAll('.inv-filter').forEach(b => {
                const on = b === btn;
                b.setAttribute('aria-selected', on ? 'true' : 'false');
                b.classList.toggle('bg-primary', on);
                b.classList.toggle('text-white', on);
                b.classList.toggle('bg-surface', !on);
                b.classList.toggle('text-secondary', !on);
            });
            this.render();
        }));
        this.load();
    },

    async load() {
        const list = document.getElementById('invoices-list');
        try {
            const res = await AmbiletAPI.get('/customer/company-invoices');
            this.invoices = (res.data && res.data.invoices) || [];
            this.render();
        } catch (e) {
            list.innerHTML = '<div class="p-6 text-center bg-white border rounded-2xl border-border">'
                + '<p class="text-sm text-secondary">Facturile nu au putut fi încărcate.</p>'
                + '<button type="button" class="mt-3 btn btn-primary px-5 py-2 text-sm rounded-xl" onclick="InvoicesPage.load()">Încearcă din nou</button></div>';
        }
    },

    esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    },

    render() {
        const list = document.getElementById('invoices-list');
        const rows = this.invoices.filter(i => !this.filter || i.issuer === this.filter);
        if (!rows.length) {
            list.innerHTML = '<div class="p-8 text-center bg-white border rounded-2xl border-border">'
                + '<p class="font-semibold text-secondary">' + (this.invoices.length ? 'Nicio factură în această categorie' : 'Nu ai încă facturi pe firmă') + '</p>'
                + '<p class="max-w-md mx-auto mt-1 text-sm text-muted">La checkout bifează „Vreau factură pe firmă” și introdu CUI-ul. Pentru o comandă deja plătită o poți cere din detaliile comenzii.</p></div>';
            return;
        }
        const esc = (v) => this.esc(v);
        const money = (v, cur) => Number(v || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + (cur || 'RON');
        list.innerHTML = rows.map(inv => {
            const ready = inv.has_document;
            const meta = [
                'Comanda ' + esc(inv.order_number),
                inv.order_date ? esc(inv.order_date) : '',
                inv.event ? esc(inv.event) : ''
            ].filter(Boolean).join(' · ');
            return '<div class="flex flex-col gap-3 p-4 bg-white border sm:flex-row sm:items-center sm:justify-between rounded-2xl border-border">'
                + '<div class="min-w-0">'
                + '<p class="text-sm font-semibold text-secondary">' + esc(inv.subject) + ' · ' + esc(inv.issuer_name)
                + (inv.number ? ' <span class="font-normal text-muted">nr. ' + esc(inv.number) + '</span>' : '') + '</p>'
                + '<p class="mt-0.5 text-xs text-muted">' + meta + '</p>'
                + (inv.company ? '<p class="mt-0.5 text-xs text-muted">Către ' + esc(inv.company) + '</p>' : '')
                + '</div>'
                + '<div class="flex items-center gap-3 sm:flex-shrink-0">'
                + '<div class="text-right"><p class="text-sm font-bold text-secondary">' + money(inv.amount, inv.currency) + '</p>'
                + '<p class="text-xs ' + (ready ? 'text-success' : 'text-muted') + '">' + esc(inv.status_label) + '</p></div>'
                + (ready
                    ? '<button type="button" class="px-4 py-2 text-sm font-medium bg-white border rounded-xl border-border text-secondary hover:border-primary hover:text-primary" data-invoice="' + inv.id + '" data-order="' + esc(inv.order_number) + '">Descarcă PDF</button>'
                    : '')
                + '</div></div>';
        }).join('');
        list.querySelectorAll('[data-invoice]').forEach(btn => btn.addEventListener('click', () => this.download(btn)));
    },

    async download(btn) {
        const label = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Se descarcă...';
        try {
            await AmbiletAPI.downloadFile('/customer/orders/' + btn.dataset.order + '/company-invoices/' + btn.dataset.invoice + '/download',
                'Factura-' + btn.dataset.order + '-' + btn.dataset.invoice + '.pdf');
        } catch (e) {
            AmbiletNotifications.error(e.message || 'Factura nu este disponibilă.');
        } finally {
            btn.disabled = false;
            btn.textContent = label;
        }
    }
};

document.addEventListener('DOMContentLoaded', () => InvoicesPage.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
