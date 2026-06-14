/* =============================================================================
 * Scan App — pages/vanzare.js (Sales / on-site POS)
 * -----------------------------------------------------------------------------
 * Mirrors tixello-app/src/screens/SalesScreen.js without NFC (Stripe Tap) and
 * without bank POS hardware integration — those are explicitly excluded for
 * the web version per the iteration 1 scope.
 *
 * Flow:
 *   1. Render entry ticket types (EventContext.ticketTypes filtered to
 *      is_entry_ticket = true).
 *   2. Operator taps + / - on each card to build a cart in local state.
 *   3. Cart bar appears at the bottom with running total (computed with the
 *      same commission rules as the mobile app: 'included' = price as-is,
 *      'added_on_top' = total * (1 + rate/100)).
 *   4. Tap "Plătește" → payment sheet (Numerar / Card POS).
 *   5. Both payment methods POST /orders with source='pos_app' and the
 *      default POS customer (pos@ambilet.ro), exactly like mobile does.
 *   6. After success: POST /orders/{id}/generate-claim-url → QR shown for the
 *      customer to scan with their phone to receive tickets by email.
 *      We poll GET /claim/{token}/status every 5s for up to 60s to reflect
 *      the claim being completed.
 *   7. If autoConfirmValid is on, immediately POST /orders/{id}/pos-complete
 *      to mark tickets as used (mirrors mobile auto check-in).
 *
 * Sales recorded in AppContext.addSale → updates shift turnover live.
 * ============================================================================= */
(function () {
  'use strict';

  function $(id) { return document.getElementById(id); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
  function escapeHtml(s) {
    if (s == null) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }
  function formatLei(amount) {
    var n = Number(amount) || 0;
    return n.toLocaleString('ro-RO', { maximumFractionDigits: 2 }) + ' lei';
  }
  function nowTime() {
    var d = new Date();
    return String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
  }

  var dom = {};
  function collectDom() {
    dom.reportsOnly       = $('scanapp-reports-only');
    dom.salesMain         = $('scanapp-sales-main');
    dom.grid              = $('scanapp-ticket-grid');
    dom.recent            = $('scanapp-recent-sales');
    dom.cartBar           = $('scanapp-cart-bar');
    dom.cartCount         = $('scanapp-cart-count');
    dom.cartTotal         = $('scanapp-cart-total');
    dom.checkoutBtn       = $('scanapp-checkout-btn');
    dom.paymentSheet      = $('scanapp-payment-sheet');
    dom.cartPreview       = $('scanapp-cart-preview');
    dom.paymentCancel     = $('scanapp-payment-cancel');
    dom.successSheet      = $('scanapp-success-sheet');
    dom.successTitle      = $('scanapp-success-title');
    dom.successSubtitle   = $('scanapp-success-subtitle');
    dom.qrDisplay         = $('scanapp-qr-display');
    dom.qrHost            = $('scanapp-qr-host');
    dom.claimStatus       = $('scanapp-claim-status');
    dom.successDone       = $('scanapp-success-done');
    dom.seatModal         = $('scanapp-seating-modal');
    dom.seatIframe        = $('scanapp-seating-iframe');
    dom.seatBack          = $('scanapp-seating-back');
    dom.seatLoading       = $('scanapp-seating-loading');
  }

  // ── Cart state ───────────────────────────────────────────────────────────
  var cart = [];                // [{ id, name, price, color, quantity, seatLabel? }]
  var selectedSeatUids = [];    // accumulates across multi-type seating sessions
  var selectedSeatDetails = []; // [{ uid, section, row, seat, ticket_type_id }]

  function cartCount() { return cart.reduce(function (n, i) { return n + i.quantity; }, 0); }
  function cartSubtotal() { return cart.reduce(function (n, i) { return n + Number(i.price) * i.quantity; }, 0); }
  function cartTotal() {
    var s = cartSubtotal();
    var c = EventContext.getState().eventCommission || {};
    if (c.mode === 'added_on_top' && c.rate > 0) {
      return s * (1 + Number(c.rate) / 100);
    }
    return s;
  }

  function isSeatedType(t) {
    var ev = EventContext.getState().selectedEvent;
    var eventHasSeating = !!(ev && (ev.has_seating === true || ev.seating_layout_id));
    return eventHasSeating && (t.has_seats === true || t.requires_seat_selection === true);
  }

  // ── Render ticket grid ───────────────────────────────────────────────────
  function renderGrid() {
    var s = EventContext.getState();
    var types = (s.ticketTypes && s.ticketTypes.length ? s.ticketTypes : s.allTicketTypes) || [];
    if (!types.length) {
      dom.grid.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Nu există tipuri de bilete configurate pentru acest eveniment.</p></div>';
      return;
    }
    dom.grid.innerHTML = types.map(function (t) {
      var avail = t.available != null ? t.available : 0;
      var sold = avail <= 0;
      var price = Number(t.price || 0);
      var color = t.color || '#8B5CF6';
      var seated = isSeatedType(t);
      var qtyInCart = (cart.find(function (i) { return Number(i.id) === Number(t.id); }) || { quantity: 0 }).quantity;

      // Seated ticket type: replace the +/- control with a 'Selectează locuri'
      // button that opens an explanatory sheet (web version cannot drive the
      // interactive canvas seating widget in this iteration; redirects user
      // to Android APK for full seated POS flow).
      var actionBlock;
      if (seated) {
        actionBlock = '<button type="button" class="scanapp-btn scanapp-btn--primary" data-action="seated" ' + (sold ? 'disabled' : '') + ' style="font-size:11px; padding: 8px 10px; white-space:nowrap;">Locuri rezervate</button>';
      } else {
        var minus = qtyInCart > 0 ? '' : 'disabled';
        var plus  = sold || (avail > 0 && qtyInCart >= avail) ? 'disabled' : '';
        actionBlock = '<div class="scanapp-qty">' +
                        '<button type="button" class="scanapp-qty__btn" data-action="dec" ' + minus + '>−</button>' +
                        '<span class="scanapp-qty__value">' + qtyInCart + '</span>' +
                        '<button type="button" class="scanapp-qty__btn scanapp-qty__btn--add" data-action="inc" ' + plus + '>+</button>' +
                      '</div>';
      }

      var hint;
      if (sold) hint = 'Stoc epuizat';
      else if (seated) hint = avail.toLocaleString('ro-RO') + ' locuri disponibile';
      else hint = avail.toLocaleString('ro-RO') + ' disponibile';

      return '' +
        '<div class="scanapp-ticket" data-id="' + escapeHtml(t.id) + '">' +
          '<div class="scanapp-ticket__color" style="background:' + escapeHtml(color) + ';"></div>' +
          '<div class="scanapp-ticket__body">' +
            '<div class="scanapp-ticket__name">' + escapeHtml(t.name) + (seated ? ' <span style="font-size:10px; color:var(--scanapp-warning);">📍 cu loc</span>' : '') + '</div>' +
            '<div class="scanapp-ticket__price">' + escapeHtml(formatLei(price)) + '</div>' +
            '<div class="scanapp-ticket__hint">' + hint + '</div>' +
          '</div>' +
          actionBlock +
        '</div>';
    }).join('');

    $$('.scanapp-ticket').forEach(function (el) {
      var id = el.getAttribute('data-id');
      el.querySelectorAll('button[data-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var action = btn.getAttribute('data-action');
          var type = types.find(function (t) { return String(t.id) === String(id); });
          if (!type) return;
          if (action === 'seated') {
            openSeating(type);
            return;
          }
          var idx = cart.findIndex(function (i) { return Number(i.id) === Number(id); });
          if (action === 'inc') {
            if (idx === -1) {
              cart.push({ id: type.id, name: type.name, price: Number(type.price || 0), color: type.color, quantity: 1 });
            } else {
              cart[idx].quantity += 1;
            }
          } else if (action === 'dec') {
            if (idx !== -1) {
              cart[idx].quantity -= 1;
              if (cart[idx].quantity <= 0) cart.splice(idx, 1);
            }
          }
          renderGrid();
          renderCart();
        });
      });
    });
  }

  // ── Seating widget (iframe over /seating/embed) ──────────────────────────
  // Mirrors tixello-app/src/screens/SeatingMapScreen.js: same backend endpoint
  // (/organizer/seating/embed-token), same /seating/embed page, same
  // postMessage contract. The only difference vs mobile is iframe vs WebView.
  function openSeating(type) {
    var ev = EventContext.getState().selectedEvent;
    if (!ev) { ScanApp.toast('Niciun eveniment selectat.', 'warning'); return; }

    dom.seatIframe.src = 'about:blank';
    dom.seatLoading.hidden = false;
    dom.seatModal.classList.add('scanapp-seating-modal--open');

    ScanAPI.post('/organizer/seating/embed-token', {
      event_id:        ev.id,
      ticket_type_id:  type ? type.id : null
    }).then(function (resp) {
      var data = (resp && resp.data) || resp || {};
      var url = data.url;
      if (!url) {
        ScanApp.toast('Token-ul pentru harta locurilor nu a fost emis.', 'danger');
        closeSeating();
        return;
      }
      dom.seatIframe.src = url;
      dom.seatLoading.hidden = false;
      dom.seatIframe.onload = function () { dom.seatLoading.hidden = true; };
    }).catch(function (err) {
      console.error('[vanzare] seating embed-token error:', err);
      ScanApp.toast((err && err.message) || 'Nu am putut deschide harta locurilor.', 'danger');
      closeSeating();
    });
  }

  function closeSeating() {
    dom.seatModal.classList.remove('scanapp-seating-modal--open');
    dom.seatIframe.src = 'about:blank';
  }

  function bindSeatingMessages() {
    window.addEventListener('message', function (event) {
      // Only accept messages from the iframe we just opened.
      if (!dom.seatIframe || event.source !== dom.seatIframe.contentWindow) return;
      var msg = event.data;
      if (typeof msg === 'string') {
        try { msg = JSON.parse(msg); } catch (e) { return; }
      }
      if (!msg || typeof msg !== 'object') return;

      switch (msg.type) {
        case 'ready':
          dom.seatLoading.hidden = true;
          break;
        case 'cancel':
          closeSeating();
          break;
        case 'confirm':
          handleSeatingConfirm(msg);
          break;
        default:
          // ignore unknown messages
      }
    });
  }

  function handleSeatingConfirm(msg) {
    // msg: { cartItems: [...], seatUids: [...], selectedSeats: [...] }
    var incoming = Array.isArray(msg.cartItems) ? msg.cartItems : [];
    incoming.forEach(function (newItem) {
      var existing = cart.find(function (i) { return Number(i.id) === Number(newItem.id); });
      if (existing) {
        existing.quantity += (newItem.quantity || 1);
      } else {
        cart.push({
          id: newItem.id,
          name: newItem.name,
          price: Number(newItem.price || 0),
          color: newItem.color || '#8B5CF6',
          quantity: newItem.quantity || 1
        });
      }
    });
    if (Array.isArray(msg.seatUids))    selectedSeatUids    = selectedSeatUids.concat(msg.seatUids);
    if (Array.isArray(msg.selectedSeats)) selectedSeatDetails = selectedSeatDetails.concat(msg.selectedSeats);

    closeSeating();
    renderGrid();
    renderCart();
  }

  function renderCart() {
    var count = cartCount();
    var total = cartTotal();
    if (count === 0) {
      dom.cartBar.classList.remove('scanapp-cart-bar--open');
      return;
    }
    dom.cartCount.textContent = count + ' bilet' + (count === 1 ? '' : 'e');
    dom.cartTotal.textContent = formatLei(total);
    dom.cartBar.classList.add('scanapp-cart-bar--open');
  }

  function renderCartPreview() {
    var rows = cart.map(function (i) {
      return '<div class="scanapp-sheet__row">' +
               '<div class="scanapp-sheet__row-color" style="background:' + escapeHtml(i.color || '#8B5CF6') + ';"></div>' +
               '<div class="scanapp-sheet__row-body">' +
                 '<div class="scanapp-sheet__row-name">' + escapeHtml(i.name) + '</div>' +
                 '<div class="scanapp-sheet__row-sub">' + i.quantity + ' × ' + escapeHtml(formatLei(i.price)) + '</div>' +
               '</div>' +
               '<div class="scanapp-sheet__row-value">' + escapeHtml(formatLei(i.price * i.quantity)) + '</div>' +
             '</div>';
    }).join('');
    var commission = EventContext.getState().eventCommission || {};
    if (commission.mode === 'added_on_top' && commission.rate > 0) {
      var fee = cartSubtotal() * Number(commission.rate) / 100;
      rows += '<div class="scanapp-sheet__row">' +
                '<div class="scanapp-sheet__row-body"><div class="scanapp-sheet__row-name">Comision</div>' +
                '<div class="scanapp-sheet__row-sub">' + commission.rate + '%</div></div>' +
                '<div class="scanapp-sheet__row-value">' + escapeHtml(formatLei(fee)) + '</div></div>';
    }
    rows += '<div class="scanapp-sheet__row">' +
              '<div class="scanapp-sheet__row-body"><div class="scanapp-sheet__row-name">Total</div></div>' +
              '<div class="scanapp-sheet__row-value">' + escapeHtml(formatLei(cartTotal())) + '</div>' +
            '</div>';
    dom.cartPreview.innerHTML = rows;
  }

  // ── Recent sales rendering ───────────────────────────────────────────────
  function renderRecentSales() {
    var ev = EventContext.getState().selectedEvent;
    if (!ev) return;
    var list = AppContext.getRecentSales(ev.id) || [];
    if (!list.length) {
      dom.recent.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Nu există vânzări înregistrate în această tură.</p></div>';
      return;
    }
    // Total badge in header (sum of recent sales — what the operator
    // collected in this shift).
    var totalCollected = list.reduce(function (n, s) { return n + (Number(s.total) || 0); }, 0);
    var totalBadge = $('scanapp-sales-total-badge');
    if (totalBadge) totalBadge.textContent = formatLei(totalCollected);

    dom.recent.innerHTML = list.slice(0, 10).map(function (s) {
      var cashIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>';
      var cardIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>';
      var iconCls = s.paymentMethod === 'cash' ? 'scanapp-recent-sale__icon--cash' : 'scanapp-recent-sale__icon--card';
      var icon = s.paymentMethod === 'cash' ? cashIcon : cardIcon;
      var desc = (s.items || []).map(function (i) { return i.quantity + 'x ' + i.name; }).join(', ') || 'Vânzare';
      return '<div class="scanapp-recent-sale">' +
               '<div class="scanapp-recent-sale__icon ' + iconCls + '">' + icon + '</div>' +
               '<div class="scanapp-recent-sale__info">' +
                 '<div class="scanapp-recent-sale__desc">' + escapeHtml(desc) + '</div>' +
                 '<div class="scanapp-recent-sale__time">' + nowTimeFromMs(s.time) + '</div>' +
               '</div>' +
               '<div class="scanapp-recent-sale__amount">' + escapeHtml(formatLei(s.total)) + '</div>' +
             '</div>';
    }).join('');
  }
  function nowTimeFromMs(ms) {
    try {
      var d = new Date(ms);
      return String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
    } catch (e) { return ''; }
  }

  // ── Payment flow ─────────────────────────────────────────────────────────
  function openPaymentSheet() {
    if (!cart.length) return;
    renderCartPreview();
    dom.paymentSheet.classList.add('scanapp-sheet-backdrop--open');
  }
  function closePaymentSheet() {
    dom.paymentSheet.classList.remove('scanapp-sheet-backdrop--open');
  }

  function openSuccessSheet(title, subtitle) {
    dom.successTitle.textContent = title || 'Plată reușită';
    dom.successSubtitle.textContent = subtitle || 'Comandă finalizată.';
    dom.qrDisplay.hidden = true;
    dom.claimStatus.hidden = true;
    dom.successDone.textContent = 'Finalizează';
    dom.successSheet.classList.add('scanapp-sheet-backdrop--open');
  }
  function closeSuccessSheet() {
    dom.successSheet.classList.remove('scanapp-sheet-backdrop--open');
    if (claimPollTimer) { clearInterval(claimPollTimer); claimPollTimer = null; }
  }

  function processPayment(method) {
    if (!cart.length) return;
    var event = EventContext.getState().selectedEvent;
    if (!event) {
      ScanApp.toast('Niciun eveniment selectat.', 'danger');
      return;
    }

    var subtotal = cartSubtotal();
    var total    = cartTotal();
    var commission = EventContext.getState().eventCommission || {};

    var payload = {
      event_id: event.id,
      payment_method: method,
      source: 'pos_app',
      sold_by: (ScanAuth.getTeamMember() && ScanAuth.getTeamMember().name) ||
               (ScanAuth.getOrganizer() && ScanAuth.getOrganizer().name) || 'POS',
      customer: {
        email: 'pos@ambilet.ro',
        first_name: 'POS',
        last_name: method === 'cash' ? 'Numerar' : 'Card'
      },
      tickets: cart.map(function (i) {
        return { ticket_type_id: i.id, quantity: i.quantity };
      })
    };
    // Include seat UIDs for seated ticket types — mirrors the mobile payload
    // (tixello-app/src/screens/SalesScreen.js line 576).
    if (selectedSeatUids.length > 0) {
      payload.seat_uids = selectedSeatUids.slice();
    }

    closePaymentSheet();
    openSuccessSheet('Se procesează…', 'Așteaptă confirmarea sistemului.');

    ScanAPI.post('/orders', payload).then(function (resp) {
      if (!resp || resp.success === false) {
        throw new Error((resp && resp.message) || 'Comanda nu a putut fi creată.');
      }
      var orderData = (resp.data && resp.data.order) || resp.data || {};
      onOrderCreated(orderData, method, total);
    }).catch(function (err) {
      console.error('[vanzare] order error:', err);
      var msg = (err && err.message) || 'Eroare la creare comandă.';
      dom.successTitle.textContent = 'Plată eșuată';
      dom.successSubtitle.textContent = msg;
      ScanApp.toast(msg, 'danger');
    });
  }

  var claimPollTimer = null;

  function onOrderCreated(orderData, method, total) {
    dom.successTitle.textContent = 'Plată reușită';
    dom.successSubtitle.textContent = 'Comandă #' + (orderData.id || '?') + ' · ' + formatLei(total);

    // Record sale
    var ev = EventContext.getState().selectedEvent;
    var saleEntry = {
      id: orderData.id,
      total: total,
      paymentMethod: method,
      items: cart.slice(),
      time: Date.now()
    };
    if (ev) AppContext.addSale(ev.id, saleEntry);

    // Generate claim URL
    if (orderData.id) {
      ScanAPI.post('/orders/' + orderData.id + '/generate-claim-url').then(function (claimResp) {
        var d = (claimResp && claimResp.data) || claimResp || {};
        if (d.claim_url) {
          renderQR(d.claim_url);
          dom.qrDisplay.hidden = false;
          if (d.token) startClaimPolling(d.token);
        }
      }).catch(function (e) {
        console.warn('[vanzare] generate-claim-url failed:', e);
      });

      // Auto-check-in (if setting enabled and we're on cash/card)
      var autoConfirm = AppContext.get('autoConfirmValid') === true;
      if (autoConfirm) {
        ScanAPI.post('/orders/' + orderData.id + '/pos-complete', {}).catch(function () {});
      }
    }

    // Clear cart + seat selections, refresh recent sales
    cart = [];
    selectedSeatUids = [];
    selectedSeatDetails = [];
    renderGrid();
    renderCart();
    renderRecentSales();

    // Refresh stats
    if (window.EventContext) EventContext.refreshStats();
  }

  function renderQR(text) {
    if (!dom.qrHost) return;
    if (typeof qrcode !== 'function') {
      console.warn('[vanzare] qrcode-generator lib not loaded yet — retrying in 300ms');
      setTimeout(function () { renderQR(text); }, 300);
      return;
    }
    try {
      // qrcode-generator API: qrcode(typeNumber, errorCorrectionLevel)
      // typeNumber=0 means auto-pick smallest fit; 'M' = 15% recovery.
      var qr = qrcode(0, 'M');
      qr.addData(text);
      qr.make();
      // Render at 5px per cell, 4 cell quiet zone → ~220px for typical URLs.
      var cell = 5;
      var margin = 4;
      var moduleCount = qr.getModuleCount();
      var size = (moduleCount + margin * 2) * cell;
      var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size +
                '" viewBox="0 0 ' + size + ' ' + size + '" shape-rendering="crispEdges">' +
                '<rect width="100%" height="100%" fill="#fff"/>';
      for (var r = 0; r < moduleCount; r++) {
        for (var c = 0; c < moduleCount; c++) {
          if (qr.isDark(r, c)) {
            var x = (c + margin) * cell;
            var y = (r + margin) * cell;
            svg += '<rect x="' + x + '" y="' + y + '" width="' + cell + '" height="' + cell + '" fill="#000"/>';
          }
        }
      }
      svg += '</svg>';
      dom.qrHost.innerHTML = svg;
    } catch (e) {
      console.error('[vanzare] renderQR threw:', e);
      dom.qrHost.innerHTML = '<p style="color:#111; font-size:11px;">Eroare la generarea QR. Folosește link-ul direct.</p>';
    }
  }

  function startClaimPolling(token) {
    if (claimPollTimer) clearInterval(claimPollTimer);
    var attempts = 0;
    dom.claimStatus.hidden = false;
    dom.claimStatus.textContent = 'Aștept ca clientul să scaneze QR-ul…';
    claimPollTimer = setInterval(function () {
      attempts++;
      if (attempts > 12) { // 60s
        clearInterval(claimPollTimer);
        claimPollTimer = null;
        dom.claimStatus.textContent = 'Polling oprit după 60s. Biletele au fost generate, clientul le poate accesa oricând prin QR.';
        return;
      }
      ScanAPI.get('/claim/' + token + '/status').then(function (resp) {
        var d = (resp && resp.data) || resp || {};
        if (d.completed || d.claimed || d.status === 'completed') {
          dom.claimStatus.classList.add('scanapp-claim-status--ok');
          dom.claimStatus.textContent = '✓ Biletele au fost trimise pe email.';
          clearInterval(claimPollTimer);
          claimPollTimer = null;
        }
      }).catch(function () { /* ignore */ });
    }, 5000);
  }

  // ── Reports-only switch ──────────────────────────────────────────────────
  function reflectReportsOnly() {
    var on = window.EventContext && EventContext.isReportsOnlyMode();
    dom.reportsOnly.hidden = !on;
    dom.salesMain.style.display = on ? 'none' : '';
    if (on) dom.cartBar.classList.remove('scanapp-cart-bar--open');
  }

  // ── Wire up ──────────────────────────────────────────────────────────────
  function init() {
    collectDom();
    if (!dom.grid) return;

    dom.checkoutBtn.addEventListener('click', openPaymentSheet);
    dom.paymentCancel.addEventListener('click', closePaymentSheet);
    dom.paymentSheet.addEventListener('click', function (e) { if (e.target === dom.paymentSheet) closePaymentSheet(); });
    dom.successDone.addEventListener('click', closeSuccessSheet);
    dom.successSheet.addEventListener('click', function (e) { if (e.target === dom.successSheet) closeSuccessSheet(); });

    $$('[data-method]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var method = btn.getAttribute('data-method');
        processPayment(method);
      });
    });

    bindSeatingMessages();
    if (dom.seatBack) dom.seatBack.addEventListener('click', closeSeating);

    if (window.EventContext) {
      EventContext.subscribe('event-selected', function () {
        cart = [];
        selectedSeatUids = [];
        selectedSeatDetails = [];
        renderGrid();
        renderCart();
        renderRecentSales();
        reflectReportsOnly();
      });
      EventContext.subscribe('ticket-types-updated', function () {
        renderGrid();
      });
      reflectReportsOnly();
      renderGrid();
      renderRecentSales();
    }

    if (window.AppContext) {
      AppContext.subscribe('sale-added', function () { renderRecentSales(); });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
