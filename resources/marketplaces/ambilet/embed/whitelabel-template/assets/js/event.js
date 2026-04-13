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

    // Filter out entry/app-only tickets (should be filtered by API but double-check)
    ticketTypes = ticketTypes.filter(function(tt) { return !tt.is_entry_ticket; });

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

            // Build commission label
            var base = parseFloat(tt.price || 0);
            var c = calcComm(tt, base);
            var commLabel = '';
            if (c.amount > 0) {
                var commTypeLabel = '';
                if (tt.commission && tt.commission.type === 'fixed') commTypeLabel = c.amount.toFixed(2) + ' lei';
                else if (tt.commission && tt.commission.type === 'both') commTypeLabel = (tt.commission.rate || defRate) + '% + ' + (tt.commission.fixed || 0).toFixed(2) + ' lei';
                else commTypeLabel = (tt.commission && tt.commission.rate ? tt.commission.rate : defRate) + '%';

                if (c.mode === 'added_on_top' || c.mode === 'on_top') {
                    commLabel = 'Taxă procesare: +' + c.amount.toFixed(2) + ' lei (' + commTypeLabel + ')';
                } else {
                    commLabel = 'Taxă procesare inclusă: ' + c.amount.toFixed(2) + ' lei (' + commTypeLabel + ')';
                }
            }

            html += '<div class="ticket-type' + (selected ? ' selected' : '') + '" id="tt-' + tt.id + '">';
            html += '<div class="ticket-type-header">';
            html += '<div>';
            html += '<div class="tt-name" style="position:relative;">' + esc(tt.name);
            // Commission tooltip trigger
            if (commLabel && !soldOut) {
                html += ' <span class="tt-comm-trigger" style="display:inline-flex;align-items:center;cursor:help;vertical-align:middle;margin-left:2px;">';
                html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;color:var(--text-muted);"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>';
                html += '<span class="tt-comm-tooltip">';
                html += '<p style="margin:0 0 6px;font-weight:600;font-size:12px;">Detalii preț bilet</p>';
                html += '<div style="font-size:11px;line-height:1.6;">';
                html += '<div style="display:flex;justify-content:space-between;"><span>Preț bilet:</span><span>' + base.toFixed(2) + ' lei</span></div>';
                if (c.mode === 'added_on_top' || c.mode === 'on_top') {
                    html += '<div style="display:flex;justify-content:space-between;"><span>Taxă procesare (' + commTypeLabel + '):</span><span>+' + c.amount.toFixed(2) + ' lei</span></div>';
                    html += '<div style="display:flex;justify-content:space-between;border-top:1px solid var(--border);padding-top:4px;margin-top:4px;font-weight:600;"><span>Total la plată:</span><span>' + p.display.toFixed(2) + ' lei</span></div>';
                } else {
                    html += '<div style="display:flex;justify-content:space-between;"><span>Taxă procesare (' + commTypeLabel + '):</span><span>' + c.amount.toFixed(2) + ' lei</span></div>';
                    html += '<div style="display:flex;justify-content:space-between;border-top:1px solid var(--border);padding-top:4px;margin-top:4px;font-weight:600;"><span>Total:</span><span>' + p.display.toFixed(2) + ' lei</span></div>';
                }
                html += '</div></span>';
                html += '</span>';
            }
            html += '</div>';
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
            var c = calcComm(tt, parseFloat(tt.price || 0));
            WLCart.addItem(
                { id: event.id, title: event.name, slug: event.slug, image: event.poster_url || event.image },
                { id: tt.id, name: tt.name, price: p.display, base_price: parseFloat(tt.price || 0), commission_amount: c.amount, commission_mode: c.mode, commission: tt.commission, min_per_order: tt.min_per_order, max_per_order: tt.max_per_order },
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
