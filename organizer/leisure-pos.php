<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'POS — Emitere bilete';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_pos';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<style>
@media print {
    body * { visibility: hidden; }
    #lv-receipt, #lv-receipt * { visibility: visible; }
    #lv-receipt { position: absolute; left: 0; top: 0; width: 80mm; padding: 4mm; font-family: 'Courier New', monospace; font-size: 11px; color: #000; }
    #lv-receipt h2 { font-size: 14px; margin: 0 0 4mm 0; text-align: center; }
    #lv-receipt .row { display: flex; justify-content: space-between; }
    #lv-receipt .sep { border-top: 1px dashed #000; margin: 2mm 0; }
}
</style>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8 print:p-0">
        <div class="mb-6 print:hidden flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl">🎫 POS — Emite bilete</h1>
                <p class="mt-1 text-sm text-muted">Vânzare on-site rapidă cu chitanță 80mm.</p>
            </div>
            <!-- Panou imprimantă termică (WebUSB) — collapsible -->
            <details id="lv-printer-panel" class="bg-white border border-border rounded-xl text-sm min-w-[320px]">
                <summary class="px-4 py-2.5 cursor-pointer font-semibold text-secondary flex items-center gap-2">
                    <span>🖨️ Imprimantă termică</span>
                    <span id="lv-printer-status" class="ml-auto inline-flex items-center gap-1.5 text-xs">
                        <span id="lv-printer-status-dot" class="w-2 h-2 rounded-full bg-slate-300"></span>
                        <span id="lv-printer-status-text" class="text-muted">Verificare...</span>
                    </span>
                </summary>
                <div class="px-4 py-3 border-t border-border space-y-3">
                    <p id="lv-printer-info" class="text-xs text-muted leading-snug"></p>
                    <!-- Paper / status indicator (DLE EOT) -->
                    <p id="lv-printer-paper" class="hidden text-xs leading-snug rounded p-2"></p>
                    <p id="lv-printer-error" class="hidden text-xs text-rose-700 leading-snug bg-rose-50 border border-rose-200 rounded p-2"></p>
                    <div class="flex flex-wrap gap-2">
                        <button id="lv-printer-connect" type="button" class="flex-1 px-3 py-2 text-xs font-semibold bg-primary text-white rounded hover:bg-primary-dark disabled:opacity-50">
                            🔌 Conectează
                        </button>
                        <button id="lv-printer-test" type="button" disabled class="flex-1 px-3 py-2 text-xs font-semibold border border-border rounded hover:bg-slate-50 disabled:opacity-50">
                            🧪 1 bilet
                        </button>
                        <button id="lv-printer-test3" type="button" disabled class="flex-1 px-3 py-2 text-xs font-semibold border border-border rounded hover:bg-slate-50 disabled:opacity-50">
                            🧪 3 bilete
                        </button>
                        <button id="lv-printer-test-invoice" type="button" disabled class="w-full px-3 py-2 text-xs font-semibold border border-border rounded hover:bg-slate-50 disabled:opacity-50">
                            🧾 Factură fiscală (test)
                        </button>
                    </div>
                    <!-- Re-print ultima comandă (vizibil doar dacă există state) -->
                    <button id="lv-printer-reprint" type="button" hidden class="w-full px-3 py-2 text-xs font-semibold bg-amber-100 border border-amber-300 text-amber-900 rounded hover:bg-amber-200 disabled:opacity-50">
                        🖨️ Reprintează ultima comandă
                        <span id="lv-printer-reprint-meta" class="text-[10px] font-normal text-amber-700 ml-1"></span>
                    </button>
                    <label class="flex items-center gap-2 text-xs">
                        <input id="lv-printer-auto" type="checkbox" class="w-4 h-4 accent-primary">
                        <span class="text-secondary">Print automat după fiecare comandă</span>
                    </label>
                    <p class="text-[10px] text-muted leading-snug">
                        Cere Chrome / Edge. Pe Windows, dacă imprimanta nu apare în lista, schimbă driver-ul ei cu <strong>WinUSB</strong> folosind <a href="https://zadig.akeo.ie/" target="_blank" rel="noopener" class="text-primary underline">Zadig</a> (1× per stație).
                    </p>
                </div>
            </details>
        </div>

        <div id="lv-error" class="hidden mb-4 p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-900 print:hidden"></div>

        <div class="grid lg:grid-cols-3 gap-6 print:hidden">
            <!-- Grid bilete -->
            <div class="lg:col-span-2 bg-white border rounded-2xl border-border">
                <div class="px-5 py-4 border-b border-border flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-bold text-secondary">Tipuri de bilete</h2>
                    <label class="flex items-center gap-2 text-sm">
                        <span class="text-muted">Data vizită:</span>
                        <input id="lv-visit-date" type="date" value="<?= date('Y-m-d') ?>" class="px-2 py-1 text-sm border border-border rounded-lg">
                    </label>
                </div>
                <div id="lv-loading" class="p-8 text-center"><div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div></div>
                <div id="lv-grid" class="hidden p-5 grid-cols-2 md:grid-cols-3 gap-3"></div>
            </div>

            <!-- Sumar coș -->
            <div class="bg-white border rounded-2xl border-border flex flex-col">
                <div class="px-5 py-4 border-b border-border flex items-center justify-between gap-2">
                    <h2 class="font-bold text-secondary">Coș</h2>
                    <button id="lv-cart-clear" type="button" hidden class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium text-rose-700 hover:text-white hover:bg-rose-600 border border-rose-300 hover:border-rose-600 rounded-lg transition-colors" title="Șterge toate produsele din coș">
                        🗑️ Golește coș
                    </button>
                </div>
                <div id="lv-cart" class="flex-1 p-4 space-y-2 max-h-[400px] overflow-y-auto">
                    <p class="text-sm text-muted text-center py-6">Coș gol. Apasă pe un bilet ca să-l adaugi.</p>
                </div>
                <div class="px-5 py-3 border-t border-border bg-slate-50 space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-muted">Subtotal bilete</span><span id="lv-subtotal">0.00 RON</span></div>
                    <div id="lv-commission-line" class="hidden justify-between text-muted">
                        <span>Comision ticketing</span><span id="lv-commission-amount">+0.00 RON</span>
                    </div>
                    <div class="flex justify-between font-bold text-lg pt-1 border-t border-border"><span>Total</span><span id="lv-total" class="text-primary">0.00 RON</span></div>
                </div>

                <!-- F6: Banner gating bilete acces -->
                <div id="lv-access-banner" class="hidden mx-5 my-2 p-2 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-900"></div>

                <!-- Date client opțional -->
                <details class="px-5 py-3 border-t border-border text-sm">
                    <summary class="cursor-pointer font-medium text-secondary">Date client (opțional)</summary>
                    <div class="mt-3 space-y-2">
                        <input id="lv-cname" type="text" placeholder="Nume" class="w-full px-2 py-1.5 text-sm border border-border rounded">
                        <input id="lv-cemail" type="email" placeholder="Email (pentru bilete pe mail)" class="w-full px-2 py-1.5 text-sm border border-border rounded">
                        <input id="lv-cphone" type="text" placeholder="Telefon" class="w-full px-2 py-1.5 text-sm border border-border rounded">
                        <input id="lv-cplate" type="text" placeholder="Nr. înmatriculare (pentru parcare)" class="w-full px-2 py-1.5 text-sm border border-border rounded">
                        <textarea id="lv-cnotes" rows="2" placeholder="Informații suplimentare (opțional) — ex: solicitări speciale, grup, observații" class="w-full px-2 py-1.5 text-sm border border-border rounded resize-y"></textarea>
                    </div>
                </details>

                <!-- Limba bilet/email (RO default, HU/EN pentru turisti) -->
                <div class="px-5 py-3 border-t border-border text-sm">
                    <p class="text-xs text-muted mb-1.5">🌐 Limba bilet & email</p>
                    <div class="grid grid-cols-3 gap-2">
                        <button data-lang="ro" type="button" class="lv-lang-btn px-2 py-1.5 text-xs font-medium border border-primary bg-primary/10 rounded">🇷🇴 RO</button>
                        <button data-lang="hu" type="button" class="lv-lang-btn px-2 py-1.5 text-xs font-medium border border-border bg-white rounded">🇭🇺 HU</button>
                        <button data-lang="en" type="button" class="lv-lang-btn px-2 py-1.5 text-xs font-medium border border-border bg-white rounded">🇬🇧 EN</button>
                    </div>
                    <p class="text-[10px] text-muted mt-1.5">Determină limba textelor pe biletul PDF (dacă template-ul are traduceri) și pe emailurile trimise.</p>
                </div>

                <!-- Date firma (opțional — pentru factura B2B) -->
                <details id="lv-company-section" class="px-5 py-3 border-t border-border text-sm">
                    <summary class="cursor-pointer font-medium text-secondary flex items-center gap-2">
                        <span>🏢 Date firmă (opțional)</span>
                        <span class="text-[10px] text-muted font-normal">Pentru factură pe persoană juridică</span>
                    </summary>
                    <div class="mt-3 space-y-2">
                        <input id="lv-co-name" type="text" placeholder="Denumire firmă" class="w-full px-2 py-1.5 text-sm border border-border rounded">
                        <div class="grid grid-cols-12 gap-2 items-center">
                            <input id="lv-co-cui" type="text" placeholder="CUI / CIF (ex: RO12345678)" class="col-span-5 px-2 py-1.5 text-sm border border-border rounded">
                            <button id="lv-co-anaf-btn" type="button" class="col-span-3 px-2 py-1.5 text-xs font-semibold bg-primary text-white rounded hover:bg-primary-dark disabled:opacity-50">🔎 ANAF</button>
                            <input id="lv-co-reg" type="text" placeholder="Nr. Reg. Com." class="col-span-4 px-2 py-1.5 text-sm border border-border rounded">
                        </div>
                        <p id="lv-co-anaf-msg" class="text-[10px] hidden"></p>
                        <input id="lv-co-address" type="text" placeholder="Sediu (adresă completă)" class="w-full px-2 py-1.5 text-sm border border-border rounded">
                        <div class="grid grid-cols-2 gap-2">
                            <input id="lv-co-iban" type="text" placeholder="IBAN (opțional)" class="px-2 py-1.5 text-sm border border-border rounded">
                            <input id="lv-co-contact" type="text" placeholder="Persoană contact" class="px-2 py-1.5 text-sm border border-border rounded">
                        </div>
                        <label class="flex items-center gap-2 mt-2 text-xs">
                            <input id="lv-co-invoice" type="checkbox" class="w-4 h-4 accent-primary">
                            <span class="text-secondary font-medium">📄 Generează factură fiscală după finalizare</span>
                        </label>
                        <p class="text-[10px] text-muted leading-snug">💡 Datele firmei sunt salvate pe comandă. Facturarea se generează la pasul 2 după emiterea biletelor.</p>
                    </div>
                </details>

                <!-- Plată -->
                <div class="px-5 py-4 border-t border-border space-y-2">
                    <p class="text-xs uppercase tracking-wider text-muted font-semibold">Metodă plată</p>
                    <div class="grid grid-cols-3 gap-2">
                        <button data-pay="cash" class="lv-pay-btn px-3 py-2 text-sm font-medium border border-border rounded-lg hover:bg-slate-50">💵 Cash</button>
                        <button data-pay="card" title="Înregistrează plata cu cardul (procesată la POS-ul bancar fizic) — fără integrare automată cu terminalul" class="lv-pay-btn px-3 py-2 text-sm font-medium border border-border rounded-lg hover:bg-slate-50">💳 Card</button>
                        <button data-pay="invoice" class="lv-pay-btn px-3 py-2 text-sm font-medium border border-border rounded-lg hover:bg-slate-50">📧 Link plată pe email</button>
                    </div>
                    <p class="text-[10px] text-muted leading-snug mt-1">
                        💡 <strong>Cash</strong>: marchezi încasarea fizică acum, biletele sunt emise valid. <strong>Link plată pe email</strong>: clientul primește un link pentru plată online — biletele rămân în „așteptare" până la confirmare.
                    </p>
                    <button id="lv-checkout" disabled class="w-full mt-2 px-4 py-3 bg-primary text-white font-bold rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-primary-dark transition-colors">Finalizează</button>
                    <button id="lv-checkout-test" type="button" class="w-full mt-2 px-4 py-2 bg-white border-2 border-dashed border-amber-400 text-amber-800 font-semibold rounded-lg hover:bg-amber-50 text-sm" title="Simulează vânzare: printează biletele (+ factura dacă e bifată) FĂRĂ să trimită în baza de date.">
                        🧪 Finalizare TEST (fără DB)
                    </button>
                </div>
            </div>
        </div>

        <!-- Chitanță print -->
        <div id="lv-receipt" class="hidden"></div>
    </main>
</div>
<script src="<?= asset('assets/js/pos-printer.js') ?>"></script>
<script>
(function(){
    const $ = (id) => document.getElementById(id);
    let currentEventId = null;
    let types = [];
    let cart = {}; // { key (tid sau tid|variantId): {qty, price, name, category, ticket_type_id, variant} }
    let payment = 'cash';
    let posLocale = 'ro'; // default RO; staff il schimba pentru turisti HU/EN
    let commission = { rate: 0, fixed: 0, mode: 'included' };
    // C2: categoriile custom de afișare definite în /organizator/leisure tab Produse.
    // Folosite pentru gruparea + ordonarea produselor în panoul POS.
    let ticketCategories = [];
    // Issuers organizator (primary + secondary daca exista) — incarcati din /leisure/config.
    // Folositi de PosPrinter ca header pe biletele termice.
    let posIssuerPrimary = null;
    let posIssuerSecondary = null;
    // State A1: ultima comanda finalizata cu succes (pentru reprint din panou).
    // Stocata in memorie (volatile la refresh). Pastram doar campurile necesare
    // print-ului — fara date sensibile customer/billing.
    let lastSaleSnapshot = null;
    // Interval polling status imprimanta (paper-out)
    let printerStatusTimer = null;
    // Stare deschis/închis pentru accordions (per categorie id). Default: toate deschise.
    let categoryAccordionState = {};

    // Helper: extrage numele categoriei in functie de tip (string sau {ro,hu,en}).
    // POS staff foloseste posLocale ales din UI (default RO).
    function categoryName(cat) {
        if (!cat) return '';
        if (typeof cat.name === 'string') return cat.name;
        if (cat.name && typeof cat.name === 'object') {
            return cat.name[posLocale] || cat.name.ro || cat.name.en || cat.name.hu || cat.id || '';
        }
        return cat.id || '';
    }

    function cartKey(ttId, variantId) { return variantId ? `${ttId}|${variantId}` : String(ttId); }

    function commissionPerTicket(price) {
        if ((commission.mode || 'included') !== 'added_on_top') return 0;
        const rate = parseFloat(commission.rate || 0);
        const fixed = parseFloat(commission.fixed || 0);
        return Math.max(parseFloat(price || 0) * rate / 100, fixed);
    }

    function fmtMoney(v) { return Number(v || 0).toFixed(2); }

    const CAT_LABEL = { 'access': 'Acces', 'parking': 'Parcare', 'rental': 'Închiriere', 'activity': 'Activitate', 'extra': 'Extra', 'package': '🎁 Pachet' };
    const CAT_COLOR = { 'access': 'blue', 'parking': 'violet', 'rental': 'amber', 'activity': 'emerald', 'extra': 'slate', 'package': 'rose' };

    function renderProductCard(t) {
            const cat = t.service_category || 'access';
            const color = CAT_COLOR[cat] || 'slate';
            const variants = Array.isArray(t.variants) ? t.variants : [];
            const hasVariants = variants.length > 0;
            // F9: pos_price are prioritate față de price online (pentru POS la fața locului)
            const basePrice = (t.pos_price !== null && t.pos_price !== undefined && t.pos_price !== '')
                ? Number(t.pos_price)
                : Number(t.price_max ?? t.price ?? 0);

            if (hasVariants) {
                // Card cu butoane separate per variantă
                const varBtns = variants.map(v => {
                    const key = cartKey(t.id, v.id);
                    const qty = (cart[key]?.qty || 0);
                    return `<button data-tt="${t.id}" data-vid="${v.id}" data-vlabel="${(v.label || '').replace(/"/g,'&quot;')}" data-vduration="${v.duration_minutes ?? ''}" data-price="${Number(v.price)}" data-name="${(t.name || '').replace(/"/g,'&quot;')}" data-cat="${cat}"
                        class="lv-tt-btn relative w-full p-2 border-2 border-border hover:border-${color}-400 rounded-lg text-left transition-colors">
                        <div class="flex items-center justify-between mb-0.5">
                            <span class="text-xs font-semibold text-secondary">${v.label}</span>
                            ${qty > 0 ? `<span class="text-[10px] font-bold bg-primary text-white rounded-full w-5 h-5 flex items-center justify-center">${qty}</span>` : ''}
                        </div>
                        <div class="text-base font-bold text-${color}-700">${fmtMoney(Number(v.price))} RON</div>
                    </button>`;
                }).join('');
                return `<div class="p-4 border-2 border-border rounded-xl">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-${color}-100 text-${color}-800">${CAT_LABEL[cat] || cat}</span>
                    </div>
                    <div class="font-bold text-secondary text-sm leading-tight mb-2">${t.name || ''}</div>
                    <div class="grid grid-cols-2 gap-1.5">${varBtns}</div>
                </div>`;
            }

            // Card simplu fără variante
            const inCart = (cart[t.id]?.qty || 0);
            return `<button data-tt="${t.id}" data-price="${basePrice}" data-name="${(t.name || '').replace(/"/g,'&quot;')}" data-cat="${cat}"
                class="lv-tt-btn relative p-4 border-2 border-border hover:border-${color}-400 rounded-xl text-left transition-colors group">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-${color}-100 text-${color}-800">${CAT_LABEL[cat] || cat}</span>
                    ${inCart > 0 ? `<span class="text-xs font-bold bg-primary text-white rounded-full w-6 h-6 flex items-center justify-center">${inCart}</span>` : ''}
                </div>
                <div class="font-bold text-secondary text-sm leading-tight">${t.name || ''}</div>
                <div class="text-lg font-bold text-${color}-700 mt-2">${fmtMoney(basePrice)} RON</div>
            </button>`;
    }

    function renderGrid() {
        $('lv-loading').classList.add('hidden');
        $('lv-grid').classList.remove('hidden');
        // Filtreaza la nivel UI: aratam DOAR produsele bifate "Doar pentru vanzare POS".
        // Backward compat: produsele cu pos_price setat sunt si ele acceptate (gating implicit).
        // Filtru is_active la nivel UI: ascundem produsele dezactivate (status != 'active').
        const posTypes = (types || []).filter(t => {
            const meta = t.meta || {};
            const isPosOnly = !!meta.pos_only;
            const hasPosPrice = (t.pos_price !== null && t.pos_price !== undefined && t.pos_price !== '');
            if (!isPosOnly && !hasPosPrice) return false;
            // Daca backend returneaza status='hidden' (toggle Activ debifat), filtram.
            // Backward compat: daca status nu e expus, presupunem activ.
            if (t.status && t.status !== 'active') return false;
            return true;
        });
        if (!posTypes.length) {
            $('lv-grid').classList.remove('grid');
            $('lv-grid').innerHTML = '<p class="col-span-3 text-center text-muted py-8">Nicio bilet/serviciu marcat pentru POS. Bifează „Doar pentru vânzare POS" pe produsele de la <a href="/organizator/leisure" class="text-primary underline">/organizator/leisure</a>.</p>';
            return;
        }

        // C2: grupare după ticket_categories. Daca nu există categorii configurate
        // sau niciun produs nu are ticket_group asignat, afișam un singur grup fără
        // header (backward compat — aceeași grila plata ca înainte).
        const cats = Array.isArray(ticketCategories) ? ticketCategories : [];
        const hasAnyGroup = cats.length > 0 && posTypes.some(t => t.ticket_group);

        if (!hasAnyGroup) {
            // Render plat (înainte) — grid simplu fără headere
            $('lv-grid').classList.remove('grid');
            $('lv-grid').classList.add('flex', 'flex-col', 'gap-0');
            $('lv-grid').innerHTML = `<div class="grid grid-cols-2 md:grid-cols-3 gap-3">${posTypes.map(renderProductCard).join('')}</div>`;
        } else {
            // Grupeaza posTypes după ticket_group, păstrând ordinea din `cats`.
            $('lv-grid').classList.remove('grid');
            $('lv-grid').classList.add('flex', 'flex-col', 'gap-6');
            const grouped = {};
            posTypes.forEach(t => {
                const gid = t.ticket_group || '__uncategorized__';
                if (!grouped[gid]) grouped[gid] = [];
                grouped[gid].push(t);
            });
            const sections = [];
            // 1. Categoriile definite (in ordinea din venue_config)
            cats.forEach(cat => {
                const gid = cat.id;
                if (grouped[gid] && grouped[gid].length) {
                    sections.push({ id: gid, name: categoryName(cat), items: grouped[gid] });
                    delete grouped[gid];
                }
            });
            // 2. Produse fără categorie sau cu categorie ștearsă → "Altele"
            const leftovers = [];
            Object.keys(grouped).forEach(gid => { leftovers.push(...grouped[gid]); });
            if (leftovers.length) {
                sections.push({ id: '__other__', name: 'Altele', items: leftovers });
            }
            // Accordion render: header click-able toggle. Implicit deschis = true.
            $('lv-grid').innerHTML = sections.map(sec => {
                const isOpen = categoryAccordionState[sec.id] !== false; // default true
                return `
                <div class="border border-border rounded-xl bg-white overflow-hidden" data-cat-section="${sec.id}">
                    <button type="button" class="w-full flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50 transition-colors" data-cat-toggle="${sec.id}">
                        <span class="text-sm font-bold text-secondary uppercase tracking-wider flex items-center gap-2">
                            ${sec.name || ''}
                            <span class="text-[10px] font-normal text-muted bg-slate-100 px-1.5 py-0.5 rounded">${sec.items.length}</span>
                        </span>
                        <svg class="w-4 h-4 text-muted transition-transform ${isOpen ? 'rotate-180' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="px-4 pb-4 ${isOpen ? '' : 'hidden'}" data-cat-body="${sec.id}">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">${sec.items.map(renderProductCard).join('')}</div>
                    </div>
                </div>
            `;}).join('');

            // Toggle handlers pentru accordions
            $('lv-grid').querySelectorAll('[data-cat-toggle]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.catToggle;
                    const body = $('lv-grid').querySelector(`[data-cat-body="${id}"]`);
                    const chevron = btn.querySelector('svg');
                    const isOpen = !body.classList.contains('hidden');
                    if (isOpen) {
                        body.classList.add('hidden');
                        chevron?.classList.remove('rotate-180');
                        categoryAccordionState[id] = false;
                    } else {
                        body.classList.remove('hidden');
                        chevron?.classList.add('rotate-180');
                        categoryAccordionState[id] = true;
                    }
                });
            });
        }

        $('lv-grid').querySelectorAll('.lv-tt-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.tt;
                const name = btn.dataset.name;
                const price = Number(btn.dataset.price);
                const cat = btn.dataset.cat;
                const vid = btn.dataset.vid || null;
                const vlabel = btn.dataset.vlabel || null;
                const vduration = btn.dataset.vduration ? parseInt(btn.dataset.vduration, 10) : null;
                const tt = types.find(t => t.id == id);
                const hasSlots = tt && tt.slots_config && tt.slots_config.enabled;
                const hasPhysical = tt && tt.physical_inventory && tt.physical_inventory.enabled;

                let slotTime = null;
                if (hasSlots || hasPhysical) {
                    // Quick prompt pentru ora (POS — input rapid)
                    const cfg = tt.slots_config || {};
                    const first = cfg.first_slot || '09:00';
                    const last = cfg.last_slot || '18:00';
                    slotTime = prompt(`Ora pentru ${name} (format HH:MM, între ${first}-${last}):`, first);
                    if (!slotTime) return; // anulare
                    if (!/^\d{2}:\d{2}$/.test(slotTime)) {
                        alert('Format invalid. Folosește HH:MM (ex: 14:30).');
                        return;
                    }
                }

                // Cheia diferă dacă ai slot — separa rezervările pe slot diferit
                const slotSuffix = slotTime ? '@' + slotTime : '';
                const key = cartKey(id, vid) + slotSuffix;
                if (!cart[key]) {
                    cart[key] = {
                        qty: 0,
                        price,
                        name: (vlabel ? `${name} — ${vlabel}` : name) + (slotTime ? ` · ${slotTime}` : ''),
                        category: cat,
                        ticket_type_id: parseInt(id, 10),
                        variant: vid ? { id: vid, label: vlabel, duration_minutes: vduration } : null,
                        slot_time: slotTime,
                    };
                }
                // Respectam min_per_order + meta.step_qty configurat de operator
                // pe TicketType (bilete de grup au min=8/10 + step=1; bilete
                // simple au min=1 + step=1 — comportament default ca inainte).
                const minQty = Math.max(1, parseInt(tt && tt.min_per_order, 10) || 1);
                const metaStep = tt && tt.meta && tt.meta.step_qty;
                const isGroup = !!(tt && tt.meta && tt.meta.is_group_ticket);
                const step = Math.max(1, parseInt(metaStep, 10) || (isGroup ? minQty : 1));
                const current = cart[key].qty || 0;
                cart[key].qty = current === 0 ? Math.max(minQty, step) : current + step;
                renderCart();
                renderGrid();
            });
        });
    }

    function renderCart() {
        const entries = Object.entries(cart);
        const wrap = $('lv-cart');
        // Buton "Golește coș" — vizibil doar cand sunt produse in cos
        const clearBtn = $('lv-cart-clear');
        if (clearBtn) clearBtn.hidden = entries.length === 0;
        if (!entries.length) {
            wrap.innerHTML = '<p class="text-sm text-muted text-center py-6">Coș gol. Apasă pe un bilet ca să-l adaugi.</p>';
            $('lv-subtotal').textContent = '0.00 RON';
            $('lv-total').textContent = '0.00 RON';
            $('lv-checkout').disabled = true;
            return;
        }
        let subtotal = 0;
        let commissionTotal = 0;
        let addonsGrandTotal = 0;
        wrap.innerHTML = entries.map(([key, it]) => {
            const line = it.qty * it.price;
            const com = commissionPerTicket(it.price) * it.qty;
            subtotal += line;
            commissionTotal += com;
            const comRow = (com > 0)
                ? `<div class="text-[10px] text-muted pl-1">+ Comision ticketing: ${fmtMoney(com)} RON</div>`
                : '';
            // Add-ons UI pentru această linie (dacă tipul de bilet are addons configurate)
            const tt = types.find(t => t.id === it.ticket_type_id);
            const addons = (tt && Array.isArray(tt.addons)) ? tt.addons : [];
            let addonsRows = '';
            if (addons.length > 0) {
                addonsRows = '<div class="mt-1.5 pt-1.5 border-t border-slate-200 space-y-1">' +
                    addons.map(a => {
                        const aQty = (it.addons && it.addons[a.id]) || 0;
                        const incPerTicket = parseInt(a.included_qty || 0, 10);
                        const maxPaidPerTicket = parseInt(a.max_per_unit || 5, 10);
                        const freePool = incPerTicket * it.qty;
                        const maxTotal = (incPerTicket + maxPaidPerTicket) * it.qty;
                        const freeUsed = Math.min(freePool, aQty);
                        const paid = Math.max(0, aQty - freePool);
                        const lineTotal = paid * parseFloat(a.price || 0);
                        addonsGrandTotal += lineTotal;
                        return `<div class="flex items-center gap-2 text-xs">
                            <span class="flex-1 min-w-0">
                                <span class="font-medium text-secondary">${a.label}</span>
                                <span class="text-muted">${freePool > 0 ? ` · ${freePool} gratis` : ''} · ${fmtMoney(parseFloat(a.price))} RON/buc</span>
                            </span>
                            <button data-ao-act="dec" data-key="${key}" data-aid="${a.id}" class="w-6 h-6 bg-white border border-border rounded hover:bg-slate-100 text-xs">−</button>
                            <span class="w-5 text-center font-semibold">${aQty}</span>
                            <button data-ao-act="inc" data-key="${key}" data-aid="${a.id}" data-max="${maxTotal}" class="w-6 h-6 bg-white border border-border rounded hover:bg-slate-100 text-xs">+</button>
                            <span class="w-16 text-right font-semibold text-xs">${lineTotal > 0 ? '+' + fmtMoney(lineTotal) : (aQty > 0 ? 'gratis' : '')}</span>
                        </div>`;
                    }).join('') +
                '</div>';
            }
            // Guide bonus: pentru bilete de grup cu meta.is_group_ticket +
            // meta.group_includes_guide, emitem VIZUAL +N bilete gratis
            // (ghid) la fiecare multiplu de min_per_order cumparat.
            let guideRow = '';
            if (tt && tt.meta && tt.meta.is_group_ticket && tt.meta.group_includes_guide) {
                const minPerGroup = Math.max(1, parseInt(tt.min_per_order, 10) || 1);
                const bonusCount = Math.floor(it.qty / minPerGroup);
                if (bonusCount > 0) {
                    const guideLabel = (tt.meta.group_guide_label || '').toString().trim() || 'Ghid grup';
                    const guideLabelHtml = guideLabel.replace(/[<>&]/g, s => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[s]));
                    guideRow = `<div class="mt-1 pt-1 border-t border-slate-200 flex items-center gap-2 text-xs">
                        <span class="text-amber-600">🎁</span>
                        <span class="flex-1 text-amber-900">
                            <span class="font-semibold">+${bonusCount} × ${guideLabelHtml}</span>
                            <span class="text-muted"> · gratuit (bonus grup)</span>
                        </span>
                        <span class="text-emerald-700 font-semibold">0.00 RON</span>
                    </div>`;
                }
            }
            return `<div class="bg-slate-50 rounded-lg p-2">
                <div class="flex items-center gap-2 text-sm">
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-secondary truncate">${it.name}</div>
                        <div class="text-xs text-muted">${fmtMoney(it.price)} × ${it.qty}</div>
                    </div>
                    <div class="flex items-center gap-1">
                        <button data-act="dec" data-id="${key}" class="w-7 h-7 bg-white border border-border rounded hover:bg-slate-100">−</button>
                        <span class="w-6 text-center text-sm font-semibold">${it.qty}</span>
                        <button data-act="inc" data-id="${key}" class="w-7 h-7 bg-white border border-border rounded hover:bg-slate-100">+</button>
                    </div>
                    <div class="w-20 text-right text-sm font-bold">${fmtMoney(line)}</div>
                    <button data-act="del" data-id="${key}" class="text-rose-500 hover:text-rose-700">✕</button>
                </div>
                ${comRow}
                ${guideRow}
                ${addonsRows}
            </div>`;
        }).join('');
        const grandTotal = subtotal + addonsGrandTotal + commissionTotal;
        $('lv-subtotal').textContent = fmtMoney(subtotal + addonsGrandTotal) + ' RON';
        $('lv-total').textContent = fmtMoney(grandTotal) + ' RON';

        // F6: gating bilete acces — count any/adult în coș + necesar
        let accessAny = 0, accessAdult = 0, needsAny = 0, needsAdult = 0;
        for (const [k, it] of Object.entries(cart)) {
            const tt = types.find(x => x.id === it.ticket_type_id);
            if (!tt) continue;
            const cat = tt.service_category || 'access';
            const req = tt.access_requirement || (tt.requires_access_ticket ? 'any' : 'none');
            const isChild = !!tt.is_child_ticket;
            if (cat === 'access') {
                accessAny += it.qty;
                if (!isChild) accessAdult += it.qty;
            }
            if (req === 'any') needsAny += it.qty;
            if (req === 'adult_only') needsAdult += it.qty;
        }
        const banner = $('lv-access-banner');
        if (banner) {
            const lackAdult = Math.max(0, needsAdult - accessAdult);
            const lackAny = Math.max(0, needsAny - accessAny);
            if (lackAdult > 0) {
                banner.textContent = `⚠️ Lipsește ${lackAdult} bilet acces ADULT pentru bărci. Scanează biletul clientului sau adaugă-l în coș.`;
                banner.classList.remove('hidden');
            } else if (lackAny > 0) {
                banner.textContent = `⚠️ Lipsește ${lackAny} bilet acces pentru vaporașe. Scanează biletul clientului sau adaugă-l în coș.`;
                banner.classList.remove('hidden');
            } else {
                banner.classList.add('hidden');
            }
        }
        // Afiseaza/ascunde linia "Comision ticketing" sub subtotal
        const comLine = $('lv-commission-line');
        if (comLine) {
            if (commissionTotal > 0) {
                comLine.classList.remove('hidden');
                comLine.classList.add('flex');
                $('lv-commission-amount').textContent = '+' + fmtMoney(commissionTotal) + ' RON';
            } else {
                comLine.classList.add('hidden');
                comLine.classList.remove('flex');
            }
        }
        $('lv-checkout').disabled = !payment;

        wrap.querySelectorAll('button[data-act]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.id;
                const act = btn.dataset.act;
                if (!cart[id]) return;
                // Respectam min_per_order + meta.step_qty al ticket type-ului
                const tt = types.find(x => x.id === cart[id].ticket_type_id);
                const minQty = Math.max(1, parseInt(tt && tt.min_per_order, 10) || 1);
                const metaStep = tt && tt.meta && tt.meta.step_qty;
                const isGroup = !!(tt && tt.meta && tt.meta.is_group_ticket);
                const step = Math.max(1, parseInt(metaStep, 10) || (isGroup ? minQty : 1));
                if (act === 'inc') {
                    cart[id].qty += step;
                } else if (act === 'dec') {
                    const next = cart[id].qty - step;
                    if (next < minQty) {
                        // Sub minim → delete line (operatorul nu poate vinde mai
                        // putin decat minim, deci scoatem complet din cos).
                        delete cart[id];
                    } else {
                        cart[id].qty = next;
                    }
                } else if (act === 'del') {
                    delete cart[id];
                }
                renderCart();
                renderGrid();
            });
        });
        wrap.querySelectorAll('button[data-ao-act]').forEach(btn => {
            btn.addEventListener('click', () => {
                const key = btn.dataset.key;
                const aid = btn.dataset.aid;
                const act = btn.dataset.aoAct;
                const max = parseInt(btn.dataset.max || 9999, 10);
                if (!cart[key]) return;
                if (!cart[key].addons) cart[key].addons = {};
                const cur = cart[key].addons[aid] || 0;
                if (act === 'inc') {
                    if (cur >= max) return;
                    cart[key].addons[aid] = cur + 1;
                } else if (act === 'dec') {
                    cart[key].addons[aid] = Math.max(0, cur - 1);
                    if (cart[key].addons[aid] === 0) delete cart[key].addons[aid];
                }
                renderCart();
            });
        });
    }

    function selectPayment(method) {
        payment = method;
        document.querySelectorAll('.lv-pay-btn').forEach(b => {
            const sel = b.dataset.pay === method;
            b.classList.toggle('bg-primary', sel);
            b.classList.toggle('text-white', sel);
            b.classList.toggle('border-primary', sel);
        });
        renderCart();
    }

    function selectLocale(lang) {
        posLocale = lang;
        document.querySelectorAll('.lv-lang-btn').forEach(b => {
            const sel = b.dataset.lang === lang;
            b.classList.toggle('bg-primary/10', sel);
            b.classList.toggle('border-primary', sel);
            b.classList.toggle('bg-white', !sel);
            b.classList.toggle('border-border', !sel);
        });
    }

    function buildReceiptHtml(data) {
        const o = data.order || {};
        const c = data.customer || {};
        const iss = data.issuer || {};
        const items = data.items || [];
        const lines = items.map(it => `
            <div class="row"><span>${it.qty} × ${it.name}</span><span>${fmtMoney(it.line_total)}</span></div>
            <div class="row" style="font-size:9px;color:#444;padding-left:8px"><span>${CAT_LABEL[it.service_category] || it.service_category}</span><span>${fmtMoney(it.unit_price)} / buc</span></div>
        `).join('');
        const ticketsList = (data.tickets || []).map(t =>
            `<div style="font-size:9px;padding:1mm 0;border-bottom:1px dotted #ccc"><strong>${t.code}</strong> · ${t.ticket_type}</div>`
        ).join('');
        const payMap = { cash: 'Cash', card: 'Card', invoice: 'Pe email' };
        return `
            <h2>${iss.name || 'Locație de agrement'}</h2>
            ${iss.tax_id ? `<div style="text-align:center;font-size:10px">CIF: ${iss.tax_id}</div>` : ''}
            ${iss.address ? `<div style="text-align:center;font-size:9px;margin-bottom:2mm">${iss.address}</div>` : ''}
            <div class="sep"></div>
            <div class="row"><span>Comandă:</span><span>${o.order_number || ''}</span></div>
            <div class="row"><span>Dată:</span><span>${window.AmbiletFmt ? AmbiletFmt.datetime(o.paid_at || new Date()) : new Date().toLocaleString('ro-RO')}</span></div>
            <div class="row"><span>Vizită:</span><span>${o.visit_date ? (AmbiletFmt?.date(o.visit_date) || o.visit_date) : ''}</span></div>
            ${c.name ? `<div class="row"><span>Client:</span><span>${c.name}</span></div>` : ''}
            <div class="sep"></div>
            ${lines}
            <div class="sep"></div>
            ${(Number(o.commission_total) > 0) ? `<div class="row"><span>Subtotal bilete</span><span>${fmtMoney(o.subtotal)}</span></div>
            <div class="row"><span>Comision ticketing</span><span>+${fmtMoney(o.commission_total)}</span></div>` : ''}
            <div class="row" style="font-weight:bold;font-size:13px"><span>TOTAL</span><span>${fmtMoney(o.total)} ${o.currency || 'RON'}</span></div>
            <div class="row"><span>Plată:</span><span>${payMap[o.payment_method] || o.payment_method}</span></div>
            <div class="sep"></div>
            <div style="font-size:10px;font-weight:bold;margin:2mm 0">BILETE EMISE:</div>
            ${ticketsList}
            <div class="sep"></div>
            <div style="text-align:center;font-size:9px;margin-top:3mm">Mulțumim! ${new Date().getFullYear()}</div>
        `;
    }

    async function checkout() {
        if ($('lv-checkout').disabled) return;
        $('lv-checkout').disabled = true;
        $('lv-checkout').textContent = 'Procesează...';
        $('lv-error').classList.add('hidden');

        const items = Object.entries(cart).map(([key, it]) => {
            const addonList = it.addons ? Object.entries(it.addons)
                .filter(([, q]) => q > 0)
                .map(([aid, q]) => ({ addon_id: aid, qty: q })) : [];
            return {
                ticket_type_id: it.ticket_type_id || parseInt(String(key).split('|')[0], 10),
                qty: it.qty,
                variant_id: it.variant ? it.variant.id : null,
                slot_time: it.slot_time || undefined,
                start_time: it.slot_time || undefined, // pentru physical_inventory
                addons: addonList.length > 0 ? addonList : undefined,
            };
        });
        // Date firma — incluse doar daca operatorul a introdus CUI sau Denumire
        const hasCompanyData = !!($('lv-co-cui').value.trim() || $('lv-co-name').value.trim());
        const companyData = hasCompanyData ? {
            name: $('lv-co-name').value.trim() || null,
            cui: $('lv-co-cui').value.trim() || null,
            reg_no: $('lv-co-reg').value.trim() || null,
            address: $('lv-co-address').value.trim() || null,
            iban: $('lv-co-iban').value.trim() || null,
            contact_person: $('lv-co-contact').value.trim() || null,
        } : null;

        const body = {
            date: $('lv-visit-date').value || new Date().toISOString().slice(0,10),
            items,
            customer: {
                name: $('lv-cname').value || null,
                email: $('lv-cemail').value || null,
                phone: $('lv-cphone').value || null,
                vehicle_plate: $('lv-cplate').value || null,
                notes: $('lv-cnotes').value || null,
            },
            ...(companyData ? { company: companyData } : {}),
            generate_invoice: $('lv-co-invoice').checked,
            locale: posLocale,
            payment_method: payment,
        };

        try {
            const res = await AmbiletAPI.post(`/organizer/events/${currentEventId}/leisure/pos-sale`, body);
            const data = res.data || {};
            // Render chitanță și auto-print (browser dialog pentru chitanță hartie A4/POS print)
            $('lv-receipt').innerHTML = buildReceiptHtml(data);
            $('lv-receipt').classList.remove('hidden');
            setTimeout(() => {
                window.print();
                $('lv-receipt').classList.add('hidden');
            }, 200);

            // Print termic bilete (WebUSB ESC/POS) — non-blocking, eseuc != esec vanzare.
            // Toggle-ul "Print automat dupa fiecare comanda" e in panou imprimanta termica.
            if (typeof window.posAutoPrintTickets === 'function') {
                try { await window.posAutoPrintTickets(data); }
                catch (e) { console.warn('[checkout] auto-print failed:', e); }
            }
            // Print factura fiscala pe imprimanta termica daca operatorul a bifat
            // "Genereaza factura fiscala" + avem date firma cumparator.
            if (typeof window.posPrintInvoiceFromSale === 'function' && $('lv-co-invoice').checked) {
                try { await window.posPrintInvoiceFromSale(data); }
                catch (e) { console.warn('[checkout] invoice print failed:', e); }
            }
            // Daca operatorul a cerut factura si exista date firma → ofera-i butonul de generare
            const orderId = data?.order?.id;
            const wantsInvoice = !!(data?.invoice_requested && data?.company_billing && orderId);
            if (wantsInvoice) {
                showInvoiceAction(orderId, data.company_billing);
            }

            // Reset coș
            cart = {};
            $('lv-cname').value = '';
            $('lv-cemail').value = '';
            $('lv-cphone').value = '';
            $('lv-cplate').value = '';
            $('lv-cnotes').value = '';
            $('lv-co-name').value = '';
            $('lv-co-cui').value = '';
            $('lv-co-reg').value = '';
            $('lv-co-address').value = '';
            $('lv-co-iban').value = '';
            $('lv-co-contact').value = '';
            $('lv-co-invoice').checked = false;
            renderCart();
            renderGrid();
        } catch (e) {
            console.error('[leisure-pos] sale failed', e);
            $('lv-error').textContent = 'Eroare la procesarea vânzării: ' + (e?.message || 'necunoscut');
            $('lv-error').classList.remove('hidden');
        } finally {
            $('lv-checkout').textContent = 'Finalizează';
            $('lv-checkout').disabled = Object.keys(cart).length === 0;
        }
    }

    // Construieste un fake sale-response din starea curenta UI a POS-ului
    // (cart + customer fields + date firma). NU trimite nimic la backend.
    function buildFakeSaleResponse() {
        const visitDate = $('lv-visit-date').value || new Date().toISOString().slice(0,10);
        const cartEntries = Object.entries(cart);
        const tickets = [];
        const items = [];
        let subtotal = 0;
        cartEntries.forEach(([key, it]) => {
            const tt = types.find(x => x.id === it.ticket_type_id);
            const issuingCompany = tt?.issuing_company || 'primary';
            const lineTotal = it.qty * it.price;
            subtotal += lineTotal;
            items.push({ name: it.name, qty: it.qty, unit_price: it.price, line_total: lineTotal, service_category: it.category, issuing_company: issuingCompany });
            for (let i = 0; i < it.qty; i++) {
                tickets.push({
                    id: 'TEST-' + Math.random().toString(36).substring(2, 8),
                    code: 'TEST-' + Math.random().toString(36).substring(2, 10).toUpperCase(),
                    ticket_type: it.name,
                    service_category: it.category,
                    issuing_company: issuingCompany,
                    price: it.price,
                    variant: it.variant || null,
                });
            }
            // Guide bonus tickets — mirror backend posSale + printare test.
            if (tt && tt.meta && tt.meta.is_group_ticket && tt.meta.group_includes_guide) {
                const minPerGroup = Math.max(1, parseInt(tt.min_per_order, 10) || 1);
                const bonusCount = Math.floor(it.qty / minPerGroup);
                const guideLabel = (tt.meta.group_guide_label || '').toString().trim() || 'Ghid grup';
                for (let g = 0; g < bonusCount; g++) {
                    tickets.push({
                        id: 'TEST-G-' + Math.random().toString(36).substring(2, 8),
                        code: 'TEST-G-' + Math.random().toString(36).substring(2, 10).toUpperCase(),
                        ticket_type: guideLabel,
                        service_category: it.category,
                        issuing_company: issuingCompany,
                        price: 0,
                        variant: null,
                        guide_bonus: true,
                    });
                }
            }
        });
        const hasCompany = !!($('lv-co-cui').value.trim() || $('lv-co-name').value.trim());
        return {
            order: {
                id: null,
                order_number: 'TEST-' + new Date().getTime().toString().slice(-8),
                visit_date: visitDate,
                subtotal: subtotal,
                total: subtotal,
                currency: 'RON',
                paid_at: new Date().toISOString(),
            },
            customer: {
                name: $('lv-cname').value || null,
                email: $('lv-cemail').value || null,
                phone: $('lv-cphone').value || null,
            },
            company_billing: hasCompany ? {
                name: $('lv-co-name').value.trim() || null,
                cui: $('lv-co-cui').value.trim() || null,
                reg_no: $('lv-co-reg').value.trim() || null,
                address: $('lv-co-address').value.trim() || null,
            } : null,
            issuer: posIssuerPrimary || {},
            issuer_secondary: posIssuerSecondary || null,
            event: { name: (typeof leisureEvents !== 'undefined' && Array.isArray(leisureEvents))
                ? (leisureEvents.find(e => e.id === currentEventId)?.title || '') : '' },
            items: items,
            tickets: tickets,
        };
    }

    // Finalizare TEST: printeaza bilete + (opțional) factura pe imprimanta termica
    // folosind datele din cosul curent. NU trimite nimic in DB. Cosul ramane intact.
    async function checkoutTest() {
        if (!Object.keys(cart).length) {
            alert('Cosul e gol — adauga produse inainte de testul de print.');
            return;
        }
        if (typeof PosPrinter === 'undefined' || !PosPrinter.isSupported()) {
            alert('WebUSB nu e disponibil in browser. Foloseste Chrome.');
            return;
        }
        if (!(await PosPrinter.isReady())) {
            alert('Conecteaza imprimanta termica inainte de testul de print.');
            return;
        }
        const btn = $('lv-checkout-test');
        btn.disabled = true;
        const orig = btn.textContent;
        btn.textContent = '⏳ Test: printez bilete...';
        const fakeData = buildFakeSaleResponse();
        const prevAuto = PosPrinter.getAutoPrintEnabled();
        PosPrinter.setAutoPrintEnabled(true);
        try {
            await window.posAutoPrintTickets(fakeData);
            if ($('lv-co-invoice').checked) {
                btn.textContent = '⏳ Test: printez factura...';
                await window.posPrintInvoiceFromSale(fakeData);
            }
            btn.textContent = '✓ Test trimis';
            setTimeout(() => { btn.textContent = orig; btn.disabled = false; }, 2000);
        } catch (e) {
            console.warn('[checkout-test] failed:', e);
            btn.textContent = orig;
            btn.disabled = false;
            alert('Eroare la test print: ' + (e?.message || ''));
        } finally {
            PosPrinter.setAutoPrintEnabled(prevAuto);
        }
    }

    function showInvoiceAction(orderId, company) {
        // Banner persistent care invita operatorul sa genereze factura pentru aceasta comanda.
        let banner = document.getElementById('lv-invoice-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'lv-invoice-banner';
            banner.className = 'mb-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-3 print:hidden';
            const main = document.querySelector('main');
            if (main) main.insertBefore(banner, main.firstChild);
        }
        const coName = (company && company.name) ? company.name : 'firmă';
        const coCui = (company && company.cui) ? company.cui : '';
        banner.innerHTML = `
            <div class="text-2xl">📄</div>
            <div class="flex-1 text-sm">
                <p class="font-semibold text-emerald-900">Comandă emisă cu date firmă</p>
                <p class="text-xs text-emerald-800 mt-0.5">${coName}${coCui ? ' · CUI ' + coCui : ''}</p>
            </div>
            <button id="lv-inv-gen" class="px-3 py-2 text-sm font-semibold bg-primary text-white rounded-lg hover:bg-primary-dark">Generează factură</button>
            <button id="lv-inv-dismiss" class="px-2 py-2 text-xs text-emerald-800 hover:bg-emerald-100 rounded">✕</button>
        `;
        banner.querySelector('#lv-inv-dismiss').addEventListener('click', () => banner.remove());
        banner.querySelector('#lv-inv-gen').addEventListener('click', async () => {
            const btn = banner.querySelector('#lv-inv-gen');
            btn.disabled = true;
            btn.textContent = 'Se generează...';
            try {
                const res = await AmbiletAPI.post(`/organizer/orders/${orderId}/generate-invoice`, {});
                const inv = res.data || {};
                if (inv.invoice_url) {
                    window.open(inv.invoice_url, '_blank');
                }
                btn.textContent = '✓ ' + (inv.invoice_number || 'Factură înregistrată');
                btn.classList.remove('bg-primary');
                btn.classList.add('bg-emerald-600');
                if (inv.message) {
                    const msg = document.createElement('p');
                    msg.className = 'text-xs text-emerald-800 mt-1 w-full';
                    msg.textContent = inv.message;
                    banner.appendChild(msg);
                }
                setTimeout(() => banner.remove(), 8000);
            } catch (e) {
                console.error('[leisure-pos] invoice gen failed', e);
                btn.disabled = false;
                btn.textContent = 'Generează factură';
                alert('Eroare la generarea facturii: ' + (e?.message || 'necunoscut'));
            }
        });
    }

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined') {
            $('lv-loading').classList.add('hidden');
            $('lv-error').textContent = 'API indisponibil.';
            $('lv-error').classList.remove('hidden');
            return;
        }
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const events = res.data || [];
            const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
            if (leisure.length > 0) currentEventId = leisure[0].id;
        } catch (e) { console.error(e); }
        if (!currentEventId) {
            $('lv-loading').classList.add('hidden');
            $('lv-error').textContent = 'Nu există un eveniment de tip Locație de agrement.';
            $('lv-error').classList.remove('hidden');
            return;
        }
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/config`);
            types = res.data?.ticket_types || [];
            ticketCategories = Array.isArray(res.data?.ticket_categories) ? res.data.ticket_categories : [];
            if (res.data?.commission) commission = res.data.commission;
            // Issuers: backend returneaza issuers.primary (mereu) si issuers.secondary
            // (doar daca organizatorul are has_secondary_issuer). Salvam ambii ca
            // sa fie disponibili pentru print termic (per bilet, dupa issuing_company).
            const issuers = res.data?.issuers || {};
            posIssuerPrimary = issuers.primary || null;
            posIssuerSecondary = issuers.secondary || null;
        } catch (e) {
            $('lv-error').textContent = 'Eroare la încărcarea biletelor: ' + (e?.message || '');
            $('lv-error').classList.remove('hidden');
        }

        if (!$('lv-visit-date').value) $('lv-visit-date').value = new Date().toISOString().slice(0,10);
        renderGrid();
        renderCart();
        selectPayment('cash');
        selectLocale('ro');

        document.querySelectorAll('.lv-pay-btn').forEach(b => b.addEventListener('click', () => selectPayment(b.dataset.pay)));
        document.querySelectorAll('.lv-lang-btn').forEach(b => b.addEventListener('click', () => selectLocale(b.dataset.lang)));
        $('lv-checkout').addEventListener('click', checkout);
        $('lv-checkout-test')?.addEventListener('click', checkoutTest);

        // Golire cos (cu confirmare): sterge toate produsele + addon-urile.
        // Date client/firma/payment raman setate (operatorul poate reincerca).
        $('lv-cart-clear')?.addEventListener('click', () => {
            if (!Object.keys(cart).length) return;
            if (!confirm('Ești sigur că vrei să golești coșul? Toate produsele adăugate vor fi șterse.')) return;
            cart = {};
            renderCart();
            // Re-randam grid-ul ca sa dispara badge-urile cu count de pe carduri
            // (altfel ramane "2" lipit pe cardul de unde s-au adaugat ultimele).
            renderGrid();
        });

        // ============ Panou imprimantă termică (WebUSB) ============
        initPrinterPanel();

        // ANAF lookup: completeaza automat denumire/sediu/reg.com/oras pe baza CUI.
        // Endpoint reutilizat: POST /organizer/verify-cui (folosit deja la onboarding).
        $('lv-co-anaf-btn')?.addEventListener('click', async () => {
            const cuiRaw = $('lv-co-cui').value.trim();
            const msg = $('lv-co-anaf-msg');
            const btn = $('lv-co-anaf-btn');
            if (!cuiRaw) {
                msg.textContent = 'Introdu un CUI mai întâi.';
                msg.className = 'text-[10px] text-rose-600 mt-1';
                msg.classList.remove('hidden');
                return;
            }
            btn.disabled = true; btn.textContent = '…';
            msg.classList.add('hidden');
            try {
                const res = await AmbiletAPI.post('/organizer/verify-cui', { cui: cuiRaw });
                const d = res.data || {};
                if (d.company_name) $('lv-co-name').value = d.company_name;
                if (d.reg_com) $('lv-co-reg').value = d.reg_com;
                // Concatenez address + city + county pentru sediu
                const addrParts = [d.address, d.city, d.county].filter(Boolean);
                if (addrParts.length) $('lv-co-address').value = addrParts.join(', ');
                msg.textContent = '✓ Date completate din ANAF' + (d.is_active === false ? ' (firmă inactivă)' : '');
                msg.className = 'text-[10px] text-emerald-700 mt-1';
                msg.classList.remove('hidden');
            } catch (e) {
                msg.textContent = 'CUI invalid sau ANAF indisponibil.';
                msg.className = 'text-[10px] text-rose-600 mt-1';
                msg.classList.remove('hidden');
            } finally {
                btn.disabled = false; btn.textContent = '🔎 ANAF';
            }
        });
    });

    // ============ Panou imprimanta termica WebUSB ============
    function refreshPrinterUi() {
        const dot = $('lv-printer-status-dot');
        const txt = $('lv-printer-status-text');
        const info = $('lv-printer-info');
        const testBtn = $('lv-printer-test');
        const connectBtn = $('lv-printer-connect');
        const autoCheck = $('lv-printer-auto');
        const err = $('lv-printer-error');
        if (!dot || !txt) return;

        if (typeof PosPrinter === 'undefined' || !PosPrinter.isSupported()) {
            dot.className = 'w-2 h-2 rounded-full bg-rose-500';
            txt.textContent = 'Browser neacceptat';
            txt.className = 'text-rose-700';
            if (info) info.textContent = 'WebUSB nu e suportat aici. Foloseşte Chrome sau Edge.';
            if (connectBtn) connectBtn.disabled = true;
            if (testBtn) testBtn.disabled = true;
            if (autoCheck) { autoCheck.disabled = true; autoCheck.checked = false; }
            return;
        }

        const test3Btn = $('lv-printer-test3');
        const testInvoiceBtn = $('lv-printer-test-invoice');
        PosPrinter.isReady().then(ready => {
            if (ready) {
                dot.className = 'w-2 h-2 rounded-full bg-emerald-500';
                txt.textContent = 'Conectată';
                txt.className = 'text-emerald-700';
                PosPrinter.deviceInfo().then(d => {
                    if (info && d) info.textContent = (d.manufacturerName || 'ESC/POS') + ' ' + (d.productName || '') + ' · VID 0x' + d.vendorId.toString(16).padStart(4, '0');
                });
                if (testBtn) testBtn.disabled = false;
                if (test3Btn) test3Btn.disabled = false;
                if (testInvoiceBtn) testInvoiceBtn.disabled = false;
                if (connectBtn) connectBtn.textContent = '🔁 Schimbă imprimanta';
            } else {
                dot.className = 'w-2 h-2 rounded-full bg-amber-500';
                txt.textContent = 'Neconectată';
                txt.className = 'text-amber-700';
                if (info) info.textContent = 'Apasă „Conectează" şi alege imprimanta din popup-ul browser-ului.';
                if (testBtn) testBtn.disabled = true;
                if (test3Btn) test3Btn.disabled = true;
                if (testInvoiceBtn) testInvoiceBtn.disabled = true;
                if (connectBtn) connectBtn.textContent = '🔌 Conectează';
            }
            if (autoCheck) {
                autoCheck.checked = PosPrinter.getAutoPrintEnabled();
                autoCheck.disabled = false;
            }
        });
        if (err) err.classList.add('hidden');
    }

    function initPrinterPanel() {
        refreshPrinterUi();

        const connectBtn = $('lv-printer-connect');
        if (connectBtn) connectBtn.addEventListener('click', async () => {
            const err = $('lv-printer-error');
            connectBtn.disabled = true;
            try {
                await PosPrinter.connect();
                refreshPrinterUi();
            } catch (e) {
                console.warn('[printer] connect failed:', e);
                if (err) {
                    err.textContent = e?.message || 'Conectare eşuată';
                    err.classList.remove('hidden');
                }
            } finally {
                connectBtn.disabled = false;
            }
        });

        const testBtn = $('lv-printer-test');
        if (testBtn) testBtn.addEventListener('click', async () => {
            const err = $('lv-printer-error');
            testBtn.disabled = true;
            testBtn.textContent = '⏳ Trimit...';
            try {
                // Issuer-ul real al organizatorului (incarcat din /leisure/config la initLoad).
                // Daca config-ul n-a apucat sa se incarce, lasam undefined → printTestTicket
                // afiseaza fara header firma (smoke test minimal).
                const ev = (typeof leisureEvents !== 'undefined' && Array.isArray(leisureEvents))
                    ? leisureEvents.find(e => e.id === currentEventId) : null;
                const evName = (ev && (ev.title || ev.name)) || 'Test eveniment';
                await PosPrinter.printTestTicket({
                    issuer: posIssuerPrimary || undefined,
                    event_name: evName,
                    pos_name: 'POS test',
                });
                testBtn.textContent = '✓ Trimis!';
                setTimeout(() => { testBtn.textContent = '🧪 Print test'; testBtn.disabled = false; }, 1500);
            } catch (e) {
                console.warn('[printer] test print failed:', e);
                testBtn.textContent = '🧪 Print test';
                testBtn.disabled = false;
                if (err) {
                    err.textContent = 'Print test eşuat: ' + (e?.message || 'necunoscut');
                    err.classList.remove('hidden');
                }
            }
        });

        // Test multi-bilete: simuleaza o comanda reala cu 3 bilete diferite
        // (varianta tipica de iesire: 1 adult + 1 copil + 1 parcare). Fiecare
        // bilet are cod propriu generat random ca QR-urile sa fie distincte.
        // Reflecta exact flow-ul auto-print din checkout() — acelasi PosPrinter
        // .printTicket() per bilet, aceeasi pauza 150ms, eseuc per-bilet
        // izolat (continue cu urmatorul daca unul esueaza).
        const test3Btn2 = $('lv-printer-test3');
        if (test3Btn2) test3Btn2.addEventListener('click', async () => {
            const err = $('lv-printer-error');
            test3Btn2.disabled = true;
            const origLabel = test3Btn2.textContent;
            const ev = (typeof leisureEvents !== 'undefined' && Array.isArray(leisureEvents))
                ? leisureEvents.find(e => e.id === currentEventId) : null;
            const evName = (ev && (ev.title || ev.name)) || 'Test eveniment';

            const now = new Date();
            const pad = n => String(n).padStart(2, '0');
            const dateStr = pad(now.getDate()) + '.' + pad(now.getMonth()+1) + '.' + now.getFullYear();
            const soldAt = dateStr + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());
            // Cod random ca QR-urile sa fie distincte
            const randCode = () => 'TEST-' + Math.random().toString(36).substring(2, 8).toUpperCase();

            const samples = [
                { ticket_type_name: 'Bilet Adult',     code: randCode() },
                { ticket_type_name: 'Bilet Copil',     code: randCode() },
                { ticket_type_name: 'Bilet Pensionar', code: randCode() },
            ];

            let okCount = 0, failCount = 0;
            for (let i = 0; i < samples.length; i++) {
                const s = samples[i];
                test3Btn2.textContent = `⏳ ${i + 1}/${samples.length}...`;
                try {
                    await PosPrinter.printTicket({
                        issuer: posIssuerPrimary || undefined,
                        event_name: evName,
                        ticket_type_name: s.ticket_type_name,
                        code: s.code,
                        qr_data: s.code,
                        visit_date: dateStr,
                        sold_at: soldAt,
                        pos_name: 'POS test',
                    });
                    okCount++;
                } catch (e) {
                    failCount++;
                    console.warn('[printer] test 3-tickets failed at', i + 1, e);
                }
                // Pauza ca buffer-ul imprimantei sa nu se infunde — la fel ca in checkout.
                await new Promise(r => setTimeout(r, 150));
            }

            test3Btn2.textContent = failCount ? `⚠ ${okCount}/${samples.length}` : `✓ ${okCount} trimise`;
            setTimeout(() => { test3Btn2.textContent = origLabel; test3Btn2.disabled = false; }, 2000);
            if (failCount && err) {
                err.textContent = `${failCount} bilete au eșuat la print — verifică log-ul consolei.`;
                err.classList.remove('hidden');
            }
        });

        const autoCheck = $('lv-printer-auto');
        if (autoCheck) autoCheck.addEventListener('change', () => {
            PosPrinter.setAutoPrintEnabled(autoCheck.checked);
        });

        // Buton test factura fiscala — print cu date dummy reprezentative pt
        // calibrarea layout-ului pe imprimanta Bixolon.
        const testInvBtn = $('lv-printer-test-invoice');
        if (testInvBtn) testInvBtn.addEventListener('click', async () => {
            const err = $('lv-printer-error');
            testInvBtn.disabled = true;
            const orig = testInvBtn.textContent;
            testInvBtn.textContent = '⏳ Trimit...';
            try {
                // Folosesc issuer real al organizatorului daca e incarcat,
                // altfel cade pe dummy din PosPrinter.printTestInvoice.
                const overrides = posIssuerPrimary ? { issuer: posIssuerPrimary } : {};
                await PosPrinter.printTestInvoice(overrides);
                testInvBtn.textContent = '✓ Trimisă!';
                setTimeout(() => { testInvBtn.textContent = orig; testInvBtn.disabled = false; }, 1500);
            } catch (e) {
                testInvBtn.textContent = orig;
                testInvBtn.disabled = false;
                if (err) { err.textContent = 'Factură test eșuată: ' + (e?.message || 'necunoscut'); err.classList.remove('hidden'); }
            }
        });

        // Buton reprint ultima comanda — apeleaza acelasi pipeline ca auto-print
        // dar bypaseaza toggle-ul "auto-print enabled" (chiar daca e off, reprint
        // manual se executa la cerere). Foloseste snapshot-ul capturat la sale.
        const reprintBtn = $('lv-printer-reprint');
        if (reprintBtn) reprintBtn.addEventListener('click', async () => {
            if (!lastSaleSnapshot) return;
            if (typeof PosPrinter === 'undefined' || !(await PosPrinter.isReady())) {
                const err = $('lv-printer-error');
                if (err) {
                    err.textContent = 'Imprimanta nu e conectată — reconectează și încearcă din nou.';
                    err.classList.remove('hidden');
                }
                return;
            }
            reprintBtn.disabled = true;
            const origText = reprintBtn.innerHTML;
            reprintBtn.textContent = '⏳ Reprintez...';
            try {
                // Reutilizam pipeline-ul auto-print, dar trecem prin un wrapper
                // care nu salveaza un snapshot nou (evita coruperea lastSaleSnapshot).
                const fakeResp = lastSaleSnapshot;
                // Construim manual print loop (similar cu posAutoPrintTickets dar fara captureLastSaleSnapshot)
                const issuerPrimary = fakeResp.issuer || {};
                const issuerSecondary = fakeResp.issuer_secondary || null;
                const tickets = fakeResp.tickets || [];
                const eventName = fakeResp.event?.name || '';
                const visitDate = fakeResp.order?.visit_date || '';
                const now = new Date();
                const pad = n => String(n).padStart(2, '0');
                const reprintedAt = pad(now.getDate()) + '.' + pad(now.getMonth()+1) + '.' + now.getFullYear() + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());
                let ok = 0;
                for (const t of tickets) {
                    try {
                        const issuerForTicket = (t.issuing_company === 'secondary' && issuerSecondary)
                            ? issuerSecondary : issuerPrimary;
                        await PosPrinter.printTicket({
                            issuer: issuerForTicket,
                            event_name: eventName,
                            ticket_type_name: t.ticket_type || 'Bilet',
                            variant_label: (t.variant && (t.variant.label || t.variant.name)) || '',
                            code: t.code || '',
                            qr_data: t.code || '',
                            visit_date: visitDate,
                            sold_at: reprintedAt + ' (REPRINT)',
                            pos_name: 'POS reprint',
                        });
                        ok++;
                        await new Promise(r => setTimeout(r, 150));
                    } catch (e) {
                        console.warn('[reprint] failed:', t.code, e);
                    }
                }
                reprintBtn.innerHTML = `✓ ${ok}/${tickets.length} reprintate`;
                setTimeout(() => { reprintBtn.innerHTML = origText; reprintBtn.disabled = false; updateReprintButton(); }, 2000);
            } catch (e) {
                reprintBtn.innerHTML = origText;
                reprintBtn.disabled = false;
                console.warn('[reprint] error:', e);
            }
        });
        updateReprintButton();

        // Poll status imprimanta la 30s (paper-out / cover open detection).
        // Doar daca imprimanta e ready. Clear interval la disconnect.
        function startStatusPolling() {
            if (printerStatusTimer) clearInterval(printerStatusTimer);
            checkPrinterStatus(); // immediate check
            printerStatusTimer = setInterval(checkPrinterStatus, 30000);
        }
        function stopStatusPolling() {
            if (printerStatusTimer) { clearInterval(printerStatusTimer); printerStatusTimer = null; }
        }
        async function checkPrinterStatus() {
            const box = $('lv-printer-paper');
            if (!box) return;
            if (!(await PosPrinter.isReady())) { box.classList.add('hidden'); return; }
            try {
                const s = await PosPrinter.getStatus();
                if (!s || s.paper === 'unknown') {
                    box.classList.add('hidden');
                    return;
                }
                if (s.paper === 'out') {
                    box.textContent = '🛑 Hârtia s-a terminat — schimb-o înainte de a vinde.';
                    box.className = 'text-xs leading-snug rounded p-2 bg-rose-50 border border-rose-300 text-rose-900';
                    box.classList.remove('hidden');
                } else if (s.paper === 'near_end') {
                    box.textContent = '⚠️ Rola e aproape goală — pregătește o rolă nouă.';
                    box.className = 'text-xs leading-snug rounded p-2 bg-amber-50 border border-amber-300 text-amber-900';
                    box.classList.remove('hidden');
                } else if (s.cover_open) {
                    box.textContent = '🛑 Capacul imprimantei e deschis.';
                    box.className = 'text-xs leading-snug rounded p-2 bg-rose-50 border border-rose-300 text-rose-900';
                    box.classList.remove('hidden');
                } else if (s.error) {
                    box.textContent = '⚠️ Imprimantă în eroare (verifică cutter / mecanism).';
                    box.className = 'text-xs leading-snug rounded p-2 bg-amber-50 border border-amber-300 text-amber-900';
                    box.classList.remove('hidden');
                } else {
                    box.textContent = '🟢 Hârtie OK';
                    box.className = 'text-xs leading-snug rounded p-2 bg-emerald-50 border border-emerald-200 text-emerald-800';
                    box.classList.remove('hidden');
                    // Discret: dispare după 3s ca să nu ocupe spațiu permanent
                    setTimeout(() => { if (box.classList.contains('hidden') === false && box.textContent.includes('OK')) box.classList.add('hidden'); }, 3000);
                }
            } catch (e) {
                console.debug('[printer-status] poll failed:', e?.message || e);
                box.classList.add('hidden');
            }
        }

        // Hot-plug: actualizam UI-ul cand utilizatorul deconecteaza/reconecteaza USB
        window.addEventListener('posprinter:disconnected', () => {
            stopStatusPolling();
            refreshPrinterUi();
        });
        window.addEventListener('posprinter:connected', () => {
            refreshPrinterUi();
            startStatusPolling();
        });
        // Pornim polling-ul daca avem deja imprimanta ready la initLoad
        PosPrinter.isReady().then(ready => { if (ready) startStatusPolling(); });
    }

    // ============ Auto-print dupa checkout reusit ============
    // Apelat din checkout() dupa raspunsul pos-sale. Daca toggle-ul e activ
    // si avem imprimanta conectata, trimite cate un bilet ESC/POS per ticket.
    // Esecul de print NU blocheaza vanzarea — biletele sunt deja emise in DB,
    // operatorul vede butonul de Re-print pe ultima comanda.
    /**
     * Stocheaza un snapshot minim din raspunsul pos-sale pentru reprint manual.
     * Nu include customer/billing/totals — doar ce e relevant la print termic.
     */
    function captureLastSaleSnapshot(saleResponse) {
        if (!saleResponse) return;
        const order = saleResponse.order || {};
        lastSaleSnapshot = {
            order: { id: order.id, order_number: order.order_number, visit_date: order.visit_date, paid_at: order.paid_at },
            event: saleResponse.event || null,
            issuer: saleResponse.issuer || null,
            issuer_secondary: saleResponse.issuer_secondary || null,
            tickets: (Array.isArray(saleResponse.tickets) ? saleResponse.tickets : []).map(t => ({
                id: t.id, code: t.code, ticket_type: t.ticket_type,
                service_category: t.service_category,
                issuing_company: t.issuing_company,
                variant: t.variant,
            })),
            captured_at: new Date().toISOString(),
        };
        updateReprintButton();
    }

    function updateReprintButton() {
        const btn = $('lv-printer-reprint');
        const meta = $('lv-printer-reprint-meta');
        if (!btn) return;
        if (!lastSaleSnapshot || !lastSaleSnapshot.tickets || !lastSaleSnapshot.tickets.length) {
            btn.hidden = true;
            return;
        }
        btn.hidden = false;
        const n = lastSaleSnapshot.tickets.length;
        const ord = lastSaleSnapshot.order?.order_number || ('#' + (lastSaleSnapshot.order?.id || ''));
        if (meta) meta.textContent = `· ${ord} · ${n} ${n === 1 ? 'bilet' : 'bilete'}`;
    }

    window.posAutoPrintTickets = async function (saleResponse) {
        // Salveaza snapshot CHIAR DACA auto-print e dezactivat sau imprimanta
        // nu e conectata — operatorul poate vrea sa reprintaze ulterior dupa
        // ce o conecteaza / pune hartie noua.
        captureLastSaleSnapshot(saleResponse);

        if (typeof PosPrinter === 'undefined' || !PosPrinter.isSupported()) return;
        if (!PosPrinter.getAutoPrintEnabled()) return;
        if (!(await PosPrinter.isReady())) return;

        const order = saleResponse?.order || {};
        // Backend response: { order, customer, company_billing, issuer, issuer_secondary, event, items, tickets }
        const issuerPrimary = saleResponse?.issuer || {};
        const issuerSecondary = saleResponse?.issuer_secondary || null;
        const tickets = Array.isArray(saleResponse?.tickets) ? saleResponse.tickets : [];
        if (!tickets.length) return;

        const now = new Date();
        const pad = n => String(n).padStart(2, '0');
        const soldAt = pad(now.getDate()) + '.' + pad(now.getMonth()+1) + '.' + now.getFullYear() + ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());

        // Numele evenimentului: backend trimite acum saleResponse.event.name; fallback la
        // lista de evenimente incarcata initial daca lipseste.
        let eventName = saleResponse?.event?.name || '';
        if (!eventName) {
            const ev = (typeof leisureEvents !== 'undefined' && Array.isArray(leisureEvents))
                ? leisureEvents.find(e => e.id === currentEventId) : null;
            eventName = (ev && (ev.title || ev.name)) || '';
        }
        const visitDate = order?.visit_date || '';

        for (const t of tickets) {
            try {
                // Backend issued[] shape: { id, code, ticket_type, service_category,
                // issuing_company, price, variant }
                const ticketName = t.ticket_type || t.ticket_type_name || 'Bilet';
                const variantLabel = (t.variant && (t.variant.label || t.variant.name)) || '';
                const qrData = t.code || '';
                // Alegerea emitentului: ticket-ul are issuing_company=primary|secondary.
                // Daca organizatorul nu are has_secondary_issuer setat, backend-ul
                // returneaza issuer_secondary=null si folosim primary ca fallback.
                const issuerForTicket = (t.issuing_company === 'secondary' && issuerSecondary)
                    ? issuerSecondary
                    : issuerPrimary;
                await PosPrinter.printTicket({
                    issuer: issuerForTicket,
                    event_name: eventName,
                    ticket_type_name: ticketName,
                    variant_label: variantLabel,
                    code: t.code || '',
                    qr_data: qrData,
                    visit_date: visitDate,
                    sold_at: soldAt,
                    pos_name: 'POS ' + (order?.cashier_name || 'on-site'),
                });
                await new Promise(r => setTimeout(r, 150));
            } catch (e) {
                console.warn('[auto-print] ticket failed:', t.code, e);
                // continuam cu urmatorul
            }
        }
    };

    /**
     * Printeaza factura fiscala pe imprimanta termica folosind datele dintr-un
     * sale-response (real sau fake). Construieste structura PosPrinter.printInvoice
     * cu issuer + customer + company billing + items + series. Apelata din:
     *   - checkout() real, dupa biletele printate, daca lv-co-invoice e bifat
     *   - checkoutTest() (test fara DB)
     */
    window.posPrintInvoiceFromSale = async function (saleResponse) {
        if (typeof PosPrinter === 'undefined' || !PosPrinter.isSupported()) return;
        if (!(await PosPrinter.isReady())) return;

        const order = saleResponse?.order || {};
        const customer = saleResponse?.customer || {};
        const buyerCompany = saleResponse?.company_billing || null;
        // Factura fiscala se emite DOAR pe societatea PRINCIPALA. Filtram items
        // catre cele cu issuing_company='primary' (sau lipsa = default primary).
        // Produsele de pe societatea secundara nu apar pe factura — ele se vand
        // separat (bilete imprimate pe alta societate, fara factura aici).
        const issuer = posIssuerPrimary || saleResponse?.issuer || {};
        const allItems = Array.isArray(saleResponse?.items) ? saleResponse.items : [];
        const primaryItems = allItems.filter(it => (it.issuing_company || 'primary') === 'primary');
        if (!primaryItems.length) {
            console.warn('[invoice-print] niciun produs pe societatea principala — nu se emite factura');
            return;
        }
        const total = primaryItems.reduce((s, i) => s + parseFloat(i.line_total || 0), 0);
        const skipped = allItems.length - primaryItems.length;

        // Seria facturii: priority (1) invoice_number din backend (cand exista),
        // (2) serie din primary_invoice_series + order_number cand exista,
        // (3) fallback construit din serie primary + timestamp.
        const seriesPrefix = issuer?.invoice_series
            || (saleResponse?.invoice_number ? null : 'P1-' + new Date().getFullYear());
        const orderNumPart = order.order_number
            ? String(order.order_number).replace(/[^0-9]/g, '').padStart(8, '0').slice(-8)
            : String(new Date().getTime()).slice(-8);
        const series = saleResponse?.invoice_number
            || (seriesPrefix ? seriesPrefix + '/' + orderNumPart : ('P1-' + new Date().getFullYear() + '/' + orderNumPart));

        try {
            await PosPrinter.printInvoice({
                issuer: issuer,
                series: series,
                customer: customer,
                buyer_company: buyerCompany,
                issued_at: order.paid_at || new Date(),
                items: primaryItems.map(it => ({
                    name: it.name,
                    qty: it.qty,
                    unit_price: it.unit_price,
                    total: it.line_total,
                })),
                total: total,
                currency: order.currency || 'RON',
                // Bottom note cand am sarit produse SC2 (ca operatorul sa stie ca
                // exista bilete printate care nu apar pe factura)
                footer_note: skipped > 0
                    ? 'Produsele de pe societatea secundara nu sunt facturate aici (' + skipped + ' produse separate)'
                    : null,
            });
        } catch (e) {
            console.warn('[invoice-print] failed:', e);
        }
    };
})();
</script>
<?php
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
