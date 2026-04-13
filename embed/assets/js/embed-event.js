/**
 * Embed Event Page — ticket type rendering with commission + add to cart.
 */
(function () {
    'use strict';

    const CONFIG = window.__EMBED_CONFIG__;
    const EVENT_DATA = window.__EMBED_EVENT__;
    if (!CONFIG || !EVENT_DATA) return;

    const event = EVENT_DATA.event || {};
    const ticketTypes = EVENT_DATA.ticket_types || [];
    const defaultCommissionMode = EVENT_DATA.commission_mode || 'included';
    const defaultCommissionRate = EVENT_DATA.commission_rate || 5;

    const $container = document.getElementById('embed-ticket-types');
    const $summary = document.getElementById('embed-cart-summary');
    const $cartItems = document.getElementById('embed-cart-items');
    const $cartTotal = document.getElementById('embed-cart-total');
    const $addBtn = document.getElementById('embed-add-to-cart-btn');

    let quantities = {};

    /**
     * Calculate commission for a ticket type (mirrors event-single.js logic)
     */
    function calcCommission(tt, basePrice) {
        if (tt.commission && tt.commission.type) {
            const c = tt.commission;
            let amount = 0;
            if (c.type === 'percentage') amount = basePrice * (c.rate / 100);
            else if (c.type === 'fixed') amount = c.fixed || 0;
            else if (c.type === 'both') amount = (basePrice * (c.rate / 100)) + (c.fixed || 0);
            return { amount: Math.round(amount * 100) / 100, mode: c.mode || defaultCommissionMode };
        }
        return { amount: Math.round(basePrice * (defaultCommissionRate / 100) * 100) / 100, mode: defaultCommissionMode };
    }

    /**
     * Get display price (what customer pays) for a ticket type
     */
    function getDisplayPrice(tt) {
        const base = parseFloat(tt.price || 0);
        const comm = calcCommission(tt, base);
        if (comm.mode === 'added_on_top' || comm.mode === 'on_top') {
            return { display: Math.round((base + comm.amount) * 100) / 100, base, commission: comm.amount, mode: comm.mode };
        }
        // included — display price IS the price (commission already inside)
        return { display: base, base, commission: comm.amount, mode: comm.mode };
    }

    function render() {
        if (ticketTypes.length === 0) {
            $container.innerHTML = '<p style="padding:20px;text-align:center;color:var(--muted-color);">Nu sunt bilete disponibile.</p>';
            return;
        }

        const groups = {};
        ticketTypes.forEach(tt => {
            const group = tt.ticket_group || 'Bilete';
            if (!groups[group]) groups[group] = [];
            groups[group].push(tt);
        });

        let html = '';
        for (const [groupName, tickets] of Object.entries(groups)) {
            html += '<div style="padding:0 14px;margin-bottom:12px;">';
            if (Object.keys(groups).length > 1) {
                html += '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-color, #64748b);margin-bottom:6px;padding-top:8px;">' + esc(groupName) + '</div>';
            }

            tickets.forEach(tt => {
                const available = tt.available;
                const soldOut = available !== null && available <= 0;
                const min = tt.min_per_order || 1;
                const max = tt.max_per_order || 10;
                const maxAllowed = available !== null ? Math.min(max, available) : max;
                const qty = quantities[tt.id] || 0;
                const pricing = getDisplayPrice(tt);
                const originalPrice = tt.original_price ? parseFloat(tt.original_price) : null;

                html += '<div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid var(--border-color, #e2e8f0);border-radius:10px;margin-bottom:6px;' + (soldOut ? 'opacity:0.5;' : '') + '">';

                // Info
                html += '<div style="flex:1;min-width:0;">';
                html += '<div style="font-weight:600;font-size:14px;">' + esc(tt.name) + '</div>';
                if (tt.description) html += '<div style="font-size:11px;color:var(--muted-color);margin-top:1px;">' + esc(tt.description) + '</div>';
                if (min > 1) html += '<div style="font-size:10px;color:#d97706;margin-top:1px;">Min. ' + min + ' buc</div>';
                html += '</div>';

                // Price
                html += '<div style="text-align:right;white-space:nowrap;margin-right:6px;">';
                if (originalPrice && originalPrice > pricing.display) {
                    html += '<div style="font-size:11px;color:var(--muted-color);text-decoration:line-through;">' + originalPrice.toFixed(2) + '</div>';
                }
                html += '<div style="font-weight:700;font-size:15px;">' + pricing.display.toFixed(2) + ' ' + (tt.currency || 'RON') + '</div>';
                if (pricing.mode === 'added_on_top' || pricing.mode === 'on_top') {
                    html += '<div style="font-size:9px;color:var(--muted-color);">incl. taxe ' + pricing.commission.toFixed(2) + '</div>';
                }
                html += '</div>';

                // Qty selector
                if (soldOut) {
                    html += '<div style="font-size:12px;font-weight:600;color:#ef4444;white-space:nowrap;">Sold out</div>';
                } else {
                    html += '<div style="display:flex;align-items:center;gap:4px;">';
                    html += '<button class="qty-btn" data-tt="' + tt.id + '" data-dir="-1" ' + (qty <= 0 ? 'disabled' : '') + ' style="width:28px;height:28px;border:1px solid var(--border-color);border-radius:6px;background:none;cursor:pointer;font-size:16px;line-height:1;">−</button>';
                    html += '<span style="width:22px;text-align:center;font-weight:600;font-size:14px;">' + qty + '</span>';
                    html += '<button class="qty-btn" data-tt="' + tt.id + '" data-dir="1" ' + (qty >= maxAllowed ? 'disabled' : '') + ' style="width:28px;height:28px;border:1px solid var(--border-color);border-radius:6px;background:none;cursor:pointer;font-size:16px;line-height:1;">+</button>';
                    html += '</div>';
                }
                html += '</div>';
            });
            html += '</div>';
        }

        $container.innerHTML = html;

        $container.querySelectorAll('.qty-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const ttId = parseInt(btn.dataset.tt);
                const dir = parseInt(btn.dataset.dir);
                const tt = ticketTypes.find(t => t.id === ttId);
                if (!tt) return;
                const min = tt.min_per_order || 1;
                const max = tt.max_per_order || 10;
                const maxAllowed = tt.available !== null ? Math.min(max, tt.available) : max;
                let qty = quantities[ttId] || 0;
                qty += dir;
                if (dir > 0 && qty < min) qty = min;
                if (dir < 0 && qty < min) qty = 0;
                if (qty > maxAllowed) qty = maxAllowed;
                if (qty < 0) qty = 0;
                quantities[ttId] = qty;
                render();
            });
        });

        updateSummary();
    }

    function updateSummary() {
        const items = [];
        let total = 0;

        ticketTypes.forEach(tt => {
            const qty = quantities[tt.id] || 0;
            if (qty <= 0) return;
            const pricing = getDisplayPrice(tt);
            const line = qty * pricing.display;
            total += line;
            items.push({ tt, qty, line, displayPrice: pricing.display });
        });

        if (items.length === 0) {
            $summary.style.display = 'none';
            return;
        }

        $summary.style.display = '';
        $cartItems.innerHTML = items.map(i =>
            '<div style="display:flex;justify-content:space-between;font-size:13px;color:var(--muted-color);margin-bottom:4px;">' +
            '<span>' + i.qty + '× ' + esc(i.tt.name) + '</span>' +
            '<span style="font-weight:500;">' + i.line.toFixed(2) + ' RON</span></div>'
        ).join('');

        $cartTotal.textContent = total.toFixed(2) + ' RON';
        $addBtn.disabled = false;
    }

    $addBtn?.addEventListener('click', () => {
        ticketTypes.forEach(tt => {
            const qty = quantities[tt.id] || 0;
            if (qty <= 0) return;
            const pricing = getDisplayPrice(tt);

            EmbedCart.addItem(
                { id: event.id, title: event.name, slug: event.slug, image: event.poster_url || event.image },
                { id: tt.id, name: tt.name, price: pricing.display, commission: tt.commission },
                qty,
                null
            );
        });
        window.location.href = CONFIG.baseUrl + '/checkout';
    });

    function esc(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    render();
})();
