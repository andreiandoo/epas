/**
 * Embed Event Page — ticket type rendering + add to cart.
 */
(function () {
    'use strict';

    const CONFIG = window.__EMBED_CONFIG__;
    const EVENT_DATA = window.__EMBED_EVENT__;
    if (!CONFIG || !EVENT_DATA) return;

    const event = EVENT_DATA.event || {};
    const ticketTypes = EVENT_DATA.ticket_types || [];
    const $container = document.getElementById('embed-ticket-types');
    const $summary = document.getElementById('embed-cart-summary');
    const $cartItems = document.getElementById('embed-cart-items');
    const $cartTotal = document.getElementById('embed-cart-total');
    const $addBtn = document.getElementById('embed-add-to-cart-btn');

    let quantities = {};

    function render() {
        if (ticketTypes.length === 0) {
            $container.innerHTML = '<p style="color:var(--muted-color);text-align:center;padding:20px;">Nu sunt bilete disponibile.</p>';
            return;
        }

        // Group by ticket_group
        const groups = {};
        ticketTypes.forEach(tt => {
            const group = tt.ticket_group || 'Bilete';
            if (!groups[group]) groups[group] = [];
            groups[group].push(tt);
        });

        let html = '';
        for (const [groupName, tickets] of Object.entries(groups)) {
            html += '<div style="margin-bottom:16px;">';
            html += '<div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--muted-color, #64748b);margin-bottom:8px;">' + esc(groupName) + '</div>';

            tickets.forEach(tt => {
                const available = tt.available;
                const soldOut = available !== null && available <= 0;
                const min = tt.min_per_order || 1;
                const max = tt.max_per_order || 10;
                const maxAllowed = available !== null ? Math.min(max, available) : max;
                const qty = quantities[tt.id] || 0;
                const price = parseFloat(tt.price || 0);
                const originalPrice = tt.original_price ? parseFloat(tt.original_price) : null;

                html += '<div style="display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--border-color, #e2e8f0);border-radius:10px;margin-bottom:6px;' + (soldOut ? 'opacity:0.5;' : '') + '">';
                html += '<div style="flex:1;min-width:0;">';
                html += '<div style="font-weight:600;font-size:14px;">' + esc(tt.name) + '</div>';
                if (tt.description) html += '<div style="font-size:12px;color:var(--muted-color);margin-top:2px;">' + esc(tt.description) + '</div>';
                if (min > 1) html += '<div style="font-size:11px;color:#d97706;margin-top:2px;">Minim ' + min + '</div>';
                html += '</div>';
                html += '<div style="text-align:right;white-space:nowrap;margin-right:8px;">';
                if (originalPrice && originalPrice > price) {
                    html += '<div style="font-size:12px;color:var(--muted-color);text-decoration:line-through;">' + originalPrice.toFixed(2) + ' ' + (tt.currency || 'RON') + '</div>';
                }
                html += '<div style="font-weight:700;font-size:15px;">' + price.toFixed(2) + ' ' + (tt.currency || 'RON') + '</div>';
                html += '</div>';

                if (soldOut) {
                    html += '<div style="font-size:12px;font-weight:600;color:#ef4444;">Sold out</div>';
                } else {
                    html += '<div style="display:flex;align-items:center;gap:6px;">';
                    html += '<button class="qty-btn" data-tt="' + tt.id + '" data-dir="-1" ' + (qty <= 0 ? 'disabled' : '') + ' style="width:30px;height:30px;border:1px solid var(--border-color);border-radius:6px;background:none;cursor:pointer;font-size:16px;">−</button>';
                    html += '<span style="width:24px;text-align:center;font-weight:600;">' + qty + '</span>';
                    html += '<button class="qty-btn" data-tt="' + tt.id + '" data-dir="1" ' + (qty >= maxAllowed ? 'disabled' : '') + ' style="width:30px;height:30px;border:1px solid var(--border-color);border-radius:6px;background:none;cursor:pointer;font-size:16px;">+</button>';
                    html += '</div>';
                }
                html += '</div>';
            });

            html += '</div>';
        }

        $container.innerHTML = html;

        // Bind qty buttons
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
            const price = parseFloat(tt.price || 0);
            const line = qty * price;
            total += line;
            items.push({ tt, qty, line, price });
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

    // Add to cart
    $addBtn?.addEventListener('click', () => {
        ticketTypes.forEach(tt => {
            const qty = quantities[tt.id] || 0;
            if (qty <= 0) return;
            const price = parseFloat(tt.price || 0);

            EmbedCart.addItem(
                { id: event.id, title: event.name, slug: event.slug, image: event.poster_url || event.image },
                { id: tt.id, name: tt.name, price: price },
                qty,
                null
            );
        });

        // Go to cart
        window.location.href = CONFIG.baseUrl + '/cos';
    });

    function esc(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    render();
})();
