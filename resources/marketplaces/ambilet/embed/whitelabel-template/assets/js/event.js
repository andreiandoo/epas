/**
 * WL Event — premium ticket selector with commission calculation.
 */
(function () {
    var DATA = window.__WL_EVENT__;
    if (!DATA) return;

    var event = DATA.event || {};
    var ticketTypes = DATA.ticket_types || [];
    var defMode = DATA.commission_mode || 'included';
    var defRate = DATA.commission_rate || 5;
    var $types = document.getElementById('wl-ticket-types');
    var $summary = document.getElementById('wl-order-summary');
    var $addBtn = document.getElementById('wl-add-btn');
    var $voucher = document.getElementById('wl-voucher');
    var $voucherBtn = document.getElementById('wl-voucher-btn');
    var $voucherMsg = document.getElementById('wl-voucher-msg');
    var quantities = {};

    // Pre-populate quantities from existing cart
    if (typeof WLCart !== 'undefined') {
        var existingCart = WLCart.getCart();
        existingCart.items.forEach(function(item) {
            if (item.event && item.event.id === event.id) {
                ticketTypes.forEach(function(tt) {
                    if (tt.id === item.ticketType.id) {
                        quantities[tt.id] = item.quantity;
                    }
                });
            }
        });
    }

    function calcComm(tt, base) {
        if (tt.commission && tt.commission.type) {
            var c = tt.commission, amt = 0;
            if (c.type === 'percentage') amt = base * (c.rate / 100);
            else if (c.type === 'fixed') amt = c.fixed || 0;
            else if (c.type === 'both') amt = base * (c.rate / 100) + (c.fixed || 0);
            return { amount: Math.round(amt * 100) / 100, mode: c.mode || defMode };
        }
        return { amount: Math.round(base * defRate / 100 * 100) / 100, mode: defMode };
    }

    function getPrice(tt) {
        var base = parseFloat(tt.price || 0);
        var c = calcComm(tt, base);
        if (c.mode === 'added_on_top' || c.mode === 'on_top')
            return { display: Math.round((base + c.amount) * 100) / 100, comm: c.amount, mode: c.mode };
        return { display: base, comm: c.amount, mode: c.mode };
    }

    function render() {
        if (!ticketTypes.length) {
            $types.innerHTML = '<div style="padding:24px;text-align:center;color:var(--text-muted);">Nu sunt bilete disponibile.</div>';
            return;
        }

        var html = '';
        ticketTypes.forEach(function(tt) {
            var avail = tt.available;
            var soldOut = avail !== null && avail <= 0;
            var min = tt.min_per_order || 1;
            var max = tt.max_per_order || 10;
            var maxA = avail !== null ? Math.min(max, avail) : max;
            var qty = quantities[tt.id] || 0;
            var p = getPrice(tt);
            var origPrice = tt.original_price ? parseFloat(tt.original_price) : null;
            var selected = qty > 0;
            var sub = qty > 0 ? (qty * p.display).toFixed(0) + ' lei' : '— lei';

            // Availability class
            var availCls = 'ok', availText = 'Disponibil';
            if (soldOut) { availCls = 'low'; availText = 'Sold out'; }
            else if (avail !== null && avail < 30) { availCls = 'low'; availText = 'Doar ' + avail + ' bilete disponibile'; }

            html += '<div class="ticket-type' + (selected ? ' selected' : '') + '" id="tt-' + tt.id + '">';
            html += '<div class="ticket-type-header">';
            html += '<div>';
            html += '<div class="tt-name">' + esc(tt.name) + '</div>';
            if (tt.description) html += '<div class="tt-desc">' + esc(tt.description) + '</div>';
            html += '<div class="tt-avail ' + availCls + '"><div class="tt-avail-dot"></div>' + availText + '</div>';
            html += '</div>';
            html += '<div class="tt-price-block">';
            if (origPrice && origPrice > p.display) html += '<div class="tt-price-old">' + origPrice.toFixed(0) + ' lei</div>';
            html += '<div class="tt-price"><span class="tt-currency">lei </span>' + p.display.toFixed(0) + '</div>';
            html += '</div></div>';

            if (!soldOut) {
                html += '<div class="ticket-type-footer">';
                html += '<div class="qty-control">';
                html += '<button class="qty-btn" data-tt="' + tt.id + '" data-d="-1"' + (qty <= 0 ? ' disabled' : '') + '>−</button>';
                html += '<div class="qty-val">' + qty + '</div>';
                html += '<button class="qty-btn" data-tt="' + tt.id + '" data-d="1"' + (qty >= maxA ? ' disabled' : '') + '>+</button>';
                html += '</div>';
                html += '<div class="tt-subtotal' + (selected ? ' active' : '') + '">' + sub + '</div>';
                html += '</div>';
            }
            html += '</div>';
        });

        $types.innerHTML = html;

        // Bind qty buttons
        $types.querySelectorAll('.qty-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = parseInt(btn.dataset.tt), dir = parseInt(btn.dataset.d);
                var tt = ticketTypes.find(function(t) { return t.id === id; });
                if (!tt) return;
                var q = quantities[id] || 0;
                q += dir;
                if (dir > 0 && q < (tt.min_per_order || 1)) q = tt.min_per_order || 1;
                if (dir < 0 && q < (tt.min_per_order || 1)) q = 0;
                var maxA = tt.available !== null ? Math.min(tt.max_per_order || 10, tt.available) : (tt.max_per_order || 10);
                if (q > maxA) q = maxA;
                quantities[id] = Math.max(0, q);
                render();
            });
        });

        updateSummary();
    }

    function updateSummary() {
        var rows = [], total = 0;
        ticketTypes.forEach(function(tt) {
            var q = quantities[tt.id] || 0;
            if (q <= 0) return;
            var p = getPrice(tt);
            var line = q * p.display;
            total += line;
            rows.push({ label: q + '× ' + tt.name, amount: line.toFixed(0) + ' lei' });
        });

        // Check if cart already has items for this event
        var hasCartItems = false;
        if (typeof WLCart !== 'undefined') {
            var cart = WLCart.getCart();
            hasCartItems = cart.items.some(function(item) { return item.event && item.event.id === event.id; });
        }

        if (!rows.length) {
            $summary.style.display = 'none';
            $addBtn.disabled = !hasCartItems;
            $addBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg> Continuă spre finalizare';
            return;
        }

        var html = '';
        rows.forEach(function(r) {
            html += '<div class="order-row"><span>' + esc(r.label) + '</span><span>' + r.amount + '</span></div>';
        });
        html += '<div class="order-row total"><span>Total</span><span class="amount">' + total.toFixed(0) + ' lei</span></div>';
        $summary.innerHTML = html;
        $summary.style.display = '';
        $addBtn.disabled = false;

        // Update button label
        $addBtn.innerHTML = hasCartItems
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg> Actualizează și continuă'
            : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg> Continuă spre finalizare';
    }

    // Add to cart (replace existing quantities for this event)
    $addBtn.addEventListener('click', function() {
        // Remove existing items for this event first
        var cart = WLCart.getCart();
        var keysToRemove = [];
        cart.items.forEach(function(item) {
            if (item.event && item.event.id === event.id) keysToRemove.push(item.key);
        });
        keysToRemove.forEach(function(key) { WLCart.removeItem(key); });

        // Add selected ticket types
        ticketTypes.forEach(function(tt) {
            var q = quantities[tt.id] || 0;
            if (q <= 0) return;
            var p = getPrice(tt);
            WLCart.addItem(
                { id: event.id, title: event.name, slug: event.slug, image: event.poster_url || event.image },
                { id: tt.id, name: tt.name, price: p.display, commission: tt.commission, min_per_order: tt.min_per_order, max_per_order: tt.max_per_order },
                q, null
            );
        });
        window.location.href = (typeof WL_BASE !== 'undefined' ? WL_BASE : '') + '/checkout';
    });

    // Voucher
    if ($voucherBtn) {
        $voucherBtn.addEventListener('click', function() {
            var code = $voucher.value.trim();
            if (!code) return;
            $voucherMsg.style.display = 'block';
            $voucherMsg.style.color = 'var(--text-muted)';
            $voucherMsg.textContent = 'Se verifică...';

            WLApi.post('/promo-codes/validate', { code: code, event_id: event.id }).then(function(resp) {
                if (resp.success && resp.data && resp.data.valid) {
                    $voucherMsg.style.color = '#5cc87a';
                    $voucherMsg.textContent = 'Cod valid! Reducere aplicată.';
                } else {
                    $voucherMsg.style.color = '#e05c44';
                    $voucherMsg.textContent = resp.data?.message || resp.message || 'Cod invalid sau expirat.';
                }
            }).catch(function() {
                $voucherMsg.style.color = '#e05c44';
                $voucherMsg.textContent = 'Eroare la verificare. Încearcă din nou.';
            });
        });
    }

    function esc(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    render();
})();
