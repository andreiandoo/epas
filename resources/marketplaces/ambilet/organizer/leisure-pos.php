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
        <div class="mb-6 print:hidden">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">🎫 POS — Emite bilete</h1>
            <p class="mt-1 text-sm text-muted">Vânzare on-site rapidă cu chitanță 80mm.</p>
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
                <div class="px-5 py-4 border-b border-border">
                    <h2 class="font-bold text-secondary">Coș</h2>
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

                <!-- Date client opțional -->
                <details class="px-5 py-3 border-t border-border text-sm">
                    <summary class="cursor-pointer font-medium text-secondary">Date client (opțional)</summary>
                    <div class="mt-3 space-y-2">
                        <input id="lv-cname" type="text" placeholder="Nume" class="w-full px-2 py-1.5 text-sm border border-border rounded">
                        <input id="lv-cemail" type="email" placeholder="Email (pentru bilete pe mail)" class="w-full px-2 py-1.5 text-sm border border-border rounded">
                        <input id="lv-cphone" type="text" placeholder="Telefon" class="w-full px-2 py-1.5 text-sm border border-border rounded">
                        <input id="lv-cplate" type="text" placeholder="Nr. înmatriculare (pentru parcare)" class="w-full px-2 py-1.5 text-sm border border-border rounded">
                    </div>
                </details>

                <!-- Plată -->
                <div class="px-5 py-4 border-t border-border space-y-2">
                    <p class="text-xs uppercase tracking-wider text-muted font-semibold">Metodă plată</p>
                    <div class="grid grid-cols-3 gap-2">
                        <button data-pay="cash" class="lv-pay-btn px-3 py-2 text-sm font-medium border border-border rounded-lg hover:bg-slate-50">💵 Cash</button>
                        <button data-pay="card" disabled title="Necesită integrare terminal POS — în pregătire" class="lv-pay-btn px-3 py-2 text-sm font-medium border border-border rounded-lg opacity-50 cursor-not-allowed">💳 Card<span class="ml-1 text-[10px] font-bold text-amber-600">(soon)</span></button>
                        <button data-pay="invoice" class="lv-pay-btn px-3 py-2 text-sm font-medium border border-border rounded-lg hover:bg-slate-50">📧 Link plată pe email</button>
                    </div>
                    <p class="text-[10px] text-muted leading-snug mt-1">
                        💡 <strong>Cash</strong>: marchezi încasarea fizică acum, biletele sunt emise valid. <strong>Link plată pe email</strong>: clientul primește un link pentru plată online — biletele rămân în „așteptare" până la confirmare.
                    </p>
                    <button id="lv-checkout" disabled class="w-full mt-2 px-4 py-3 bg-primary text-white font-bold rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-primary-dark transition-colors">Finalizează</button>
                </div>
            </div>
        </div>

        <!-- Chitanță print -->
        <div id="lv-receipt" class="hidden"></div>
    </main>
</div>
<script>
(function(){
    const $ = (id) => document.getElementById(id);
    let currentEventId = null;
    let types = [];
    let cart = {}; // { key (tid sau tid|variantId): {qty, price, name, category, ticket_type_id, variant} }
    let payment = 'cash';
    let commission = { rate: 0, fixed: 0, mode: 'included' };

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

    function renderGrid() {
        $('lv-loading').classList.add('hidden');
        $('lv-grid').classList.remove('hidden');
        $('lv-grid').classList.add('grid');
        if (!types.length) {
            $('lv-grid').innerHTML = '<p class="col-span-3 text-center text-muted py-8">Nu există tipuri de bilete configurate.</p>';
            return;
        }
        $('lv-grid').innerHTML = types.map(t => {
            const cat = t.service_category || 'access';
            const color = CAT_COLOR[cat] || 'slate';
            const variants = Array.isArray(t.variants) ? t.variants : [];
            const hasVariants = variants.length > 0;
            const basePrice = Number(t.price_max ?? t.price ?? 0);

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
        }).join('');

        $('lv-grid').querySelectorAll('.lv-tt-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = btn.dataset.tt;
                const name = btn.dataset.name;
                const price = Number(btn.dataset.price);
                const cat = btn.dataset.cat;
                const vid = btn.dataset.vid || null;
                const vlabel = btn.dataset.vlabel || null;
                const vduration = btn.dataset.vduration ? parseInt(btn.dataset.vduration, 10) : null;
                const key = cartKey(id, vid);
                if (!cart[key]) {
                    cart[key] = {
                        qty: 0,
                        price,
                        name: vlabel ? `${name} — ${vlabel}` : name,
                        category: cat,
                        ticket_type_id: parseInt(id, 10),
                        variant: vid ? { id: vid, label: vlabel, duration_minutes: vduration } : null,
                    };
                }
                cart[key].qty++;
                renderCart();
                renderGrid();
            });
        });
    }

    function renderCart() {
        const entries = Object.entries(cart);
        const wrap = $('lv-cart');
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
                ${addonsRows}
            </div>`;
        }).join('');
        const grandTotal = subtotal + addonsGrandTotal + commissionTotal;
        $('lv-subtotal').textContent = fmtMoney(subtotal + addonsGrandTotal) + ' RON';
        $('lv-total').textContent = fmtMoney(grandTotal) + ' RON';
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
                if (act === 'inc') cart[id].qty++;
                else if (act === 'dec') { cart[id].qty--; if (cart[id].qty <= 0) delete cart[id]; }
                else if (act === 'del') delete cart[id];
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
                addons: addonList.length > 0 ? addonList : undefined,
            };
        });
        const body = {
            date: $('lv-visit-date').value || new Date().toISOString().slice(0,10),
            items,
            customer: {
                name: $('lv-cname').value || null,
                email: $('lv-cemail').value || null,
                phone: $('lv-cphone').value || null,
                vehicle_plate: $('lv-cplate').value || null,
            },
            payment_method: payment,
        };

        try {
            const res = await AmbiletAPI.post(`/organizer/events/${currentEventId}/leisure/pos-sale`, body);
            const data = res.data || {};
            // Render chitanță și auto-print
            $('lv-receipt').innerHTML = buildReceiptHtml(data);
            $('lv-receipt').classList.remove('hidden');
            setTimeout(() => {
                window.print();
                $('lv-receipt').classList.add('hidden');
            }, 200);
            // Reset coș
            cart = {};
            $('lv-cname').value = '';
            $('lv-cemail').value = '';
            $('lv-cphone').value = '';
            $('lv-cplate').value = '';
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
            if (res.data?.commission) commission = res.data.commission;
        } catch (e) {
            $('lv-error').textContent = 'Eroare la încărcarea biletelor: ' + (e?.message || '');
            $('lv-error').classList.remove('hidden');
        }

        if (!$('lv-visit-date').value) $('lv-visit-date').value = new Date().toISOString().slice(0,10);
        renderGrid();
        renderCart();
        selectPayment('cash');

        document.querySelectorAll('.lv-pay-btn').forEach(b => b.addEventListener('click', () => selectPayment(b.dataset.pay)));
        $('lv-checkout').addEventListener('click', checkout);
    });
})();
</script>
<?php
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
