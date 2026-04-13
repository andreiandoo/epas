/**
 * WL Event page — ticket rendering with commission.
 */
(function () {
    const DATA = window.__WL_EVENT__;
    if (!DATA) return;

    const event = DATA.event || {};
    const ticketTypes = DATA.ticket_types || [];
    const defMode = DATA.commission_mode || 'included';
    const defRate = DATA.commission_rate || 5;
    const $container = document.getElementById('wl-ticket-types');
    const $summary = document.getElementById('wl-cart-summary');
    const $cartItems = document.getElementById('wl-cart-items');
    const $cartTotal = document.getElementById('wl-cart-total');
    const $addBtn = document.getElementById('wl-add-btn');
    let quantities = {};

    function calcComm(tt, base) {
        if (tt.commission && tt.commission.type) {
            const c = tt.commission;
            let amt = 0;
            if (c.type === 'percentage') amt = base * (c.rate / 100);
            else if (c.type === 'fixed') amt = c.fixed || 0;
            else if (c.type === 'both') amt = (base * (c.rate / 100)) + (c.fixed || 0);
            return { amount: Math.round(amt * 100) / 100, mode: c.mode || defMode };
        }
        return { amount: Math.round(base * defRate / 100 * 100) / 100, mode: defMode };
    }

    function displayPrice(tt) {
        const base = parseFloat(tt.price || 0);
        const c = calcComm(tt, base);
        if (c.mode === 'added_on_top' || c.mode === 'on_top')
            return { display: Math.round((base + c.amount) * 100) / 100, commission: c.amount, mode: c.mode };
        return { display: base, commission: c.amount, mode: c.mode };
    }

    function render() {
        if (!ticketTypes.length) { $container.innerHTML = '<p style="padding:16px;text-align:center;color:var(--muted);">Nu sunt bilete disponibile.</p>'; return; }

        let html = '';
        ticketTypes.forEach(tt => {
            const avail = tt.available;
            const soldOut = avail !== null && avail <= 0;
            const min = tt.min_per_order || 1;
            const max = tt.max_per_order || 10;
            const maxA = avail !== null ? Math.min(max, avail) : max;
            const qty = quantities[tt.id] || 0;
            const p = displayPrice(tt);

            html += '<div class="wl-ticket"' + (soldOut ? ' style="opacity:0.5;"' : '') + '>';
            html += '<div class="wl-ticket-info"><div class="wl-ticket-name">' + esc(tt.name) + '</div>';
            if (tt.description) html += '<div class="wl-ticket-desc">' + esc(tt.description) + '</div>';
            if (min > 1) html += '<div class="wl-ticket-desc" style="color:#d97706;">Min. ' + min + '</div>';
            html += '</div>';
            html += '<div class="wl-ticket-price"><div class="wl-ticket-price-main">' + p.display.toFixed(2) + ' ' + (tt.currency || 'RON') + '</div>';
            if (p.mode !== 'included') html += '<div class="wl-ticket-price-sub">incl. taxe ' + p.commission.toFixed(2) + '</div>';
            html += '</div>';

            if (soldOut) {
                html += '<div style="font-size:12px;font-weight:600;color:#ef4444;">Sold out</div>';
            } else {
                html += '<div class="wl-qty"><button data-tt="' + tt.id + '" data-d="-1"' + (qty <= 0 ? ' disabled' : '') + '>−</button>';
                html += '<span>' + qty + '</span>';
                html += '<button data-tt="' + tt.id + '" data-d="1"' + (qty >= maxA ? ' disabled' : '') + '>+</button></div>';
            }
            html += '</div>';
        });

        $container.innerHTML = html;
        $container.querySelectorAll('.wl-qty button').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.tt), dir = parseInt(btn.dataset.d);
                const tt = ticketTypes.find(t => t.id === id); if (!tt) return;
                let q = quantities[id] || 0; q += dir;
                if (dir > 0 && q < (tt.min_per_order || 1)) q = tt.min_per_order || 1;
                if (dir < 0 && q < (tt.min_per_order || 1)) q = 0;
                const maxA = tt.available !== null ? Math.min(tt.max_per_order || 10, tt.available) : (tt.max_per_order || 10);
                if (q > maxA) q = maxA;
                quantities[id] = Math.max(0, q);
                render();
            });
        });
        updateSummary();
    }

    function updateSummary() {
        const items = []; let total = 0;
        ticketTypes.forEach(tt => {
            const q = quantities[tt.id] || 0; if (q <= 0) return;
            const p = displayPrice(tt); const line = q * p.display;
            total += line;
            items.push({ tt, q, line, price: p.display });
        });

        if (!items.length) { $summary.style.display = 'none'; return; }
        $summary.style.display = '';
        $cartItems.innerHTML = items.map(i => '<div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span>' + i.q + '× ' + esc(i.tt.name) + '</span><span style="font-weight:500;">' + i.line.toFixed(2) + ' RON</span></div>').join('');
        $cartTotal.textContent = total.toFixed(2) + ' RON';
        $addBtn.disabled = false;
    }

    $addBtn?.addEventListener('click', () => {
        ticketTypes.forEach(tt => {
            const q = quantities[tt.id] || 0; if (q <= 0) return;
            const p = displayPrice(tt);
            WLCart.addItem(
                { id: event.id, title: event.name, slug: event.slug, image: event.poster_url || event.image },
                { id: tt.id, name: tt.name, price: p.display, commission: tt.commission, min_per_order: tt.min_per_order, max_per_order: tt.max_per_order },
                q, null
            );
        });
        window.location.href = (typeof WL_BASE !== 'undefined' ? WL_BASE : '') + '/checkout';
    });

    render();
})();
