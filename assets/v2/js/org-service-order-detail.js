/* bilete.online v2: organizer service order (/organizator/services/{uuid}). Reads /organizer/services/orders/{uuid};
   the e-mail campaign figures for e-mail orders; for a paid ad tracking order, the pixel IDs per platform, saved through
   /organizer/services/orders/{uuid}/tracking-pixels. Runs inside the organizer shell (window.BO_ORG); text from the API
   is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('sd');
  if (!O || !root) return;
  var F = O.fmt, el = O.el;
  var $ = function (id) { return document.getElementById(id); };
  var uuid = root.getAttribute('data-uuid') || '';
  var STATUS = { draft: ['Draft', 'is-muted'], pending_payment: ['Așteaptă plata', 'is-wait'], processing: ['În procesare', 'is-wait'], active: ['Activ', 'is-ok'], completed: ['Finalizat', 'is-muted'], cancelled: ['Anulat', 'is-bad'], refunded: ['Rambursat', 'is-bad'] };
  var PAY = { paid: 'Plătit', unpaid: 'Neplătit', pending: 'În așteptare', failed: 'Eșuată', refunded: 'Rambursată' };
  var NL = { draft: ['Draft', 'is-muted'], scheduled: ['Programat', 'is-wait'], sending: ['Se trimite', 'is-wait'], sent: ['Trimis', 'is-ok'], failed: ['Eșuat', 'is-bad'], cancelled: ['Anulat', 'is-bad'] };
  var PLATFORMS = { facebook: ['Facebook Pixel', '1234567890123456'], google: ['Google Ads', 'AW-XXXXXXXXX'], tiktok: ['TikTok Pixel', 'CXXXXXXXXXXXXXXXXX'] };
  var MAX_PIXEL = 50;
  var order = null;

  function txt(v) { return F.flat(v).trim(); }
  function day(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric' }) : ''; }
  function stamp(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'; }
  function money(v, cur) { return cur && !/^(lei|ron)$/i.test(cur) ? F.num(F.toNum(v)) + ' ' + cur : F.money(F.toNum(v)); }
  function set(id, v) { $(id).textContent = v == null || v === '' ? '—' : String(v); }
  function tag(node, pair) { node.className = 'org-tag ' + pair[1]; node.textContent = pair[0]; }
  function typeIcon(t) { return t === 'featuring' ? O.icon('star') : t === 'tracking' ? O.icon('target') : t === 'email' || t === 'campaign' ? O.icon('megaphone') : O.icon('receipt'); }
  function show(which) { ['sd-loading', 'sd-content', 'sd-missing', 'sd-failed'].forEach(function (id) { $(id).hidden = id !== which; }); }

  function load() {
    if (!uuid) { show('sd-missing'); return; }
    show('sd-loading');
    O.api('/organizer/services/orders/' + encodeURIComponent(uuid)).then(function (r) {
      var o = r && r.data && r.data.order;
      if (!o || typeof o !== 'object') { show('sd-missing'); return; }
      render(o);
      show('sd-content');
    }, function (err) {
      if (err && err.status === 401) return;
      show(err && (err.status === 404 || err.status === 403) ? 'sd-missing' : 'sd-failed');
    });
  }

  function render(o) {
    order = o;
    var number = txt(o.order_number), label = txt(o.type_label) || 'Serviciu', cur = txt(o.currency);
    $('sd-crumb').textContent = number || 'Comandă';
    $('sd-title').textContent = number ? label + ' - ' + number : label;
    document.title = (number ? label + ' ' + number : label) + ' — bilete.online';
    var ic = $('sd-type-ic');
    ic.className = 'sd-type-ic is-' + (/^[a-z]+$/.test(o.type) ? o.type : 'other');
    ic.textContent = '';
    ic.appendChild(typeIcon(o.type));
    $('sd-type').textContent = label;
    $('sd-number').textContent = number;
    tag($('sd-status'), STATUS[o.status] ? [txt(o.status_label) || STATUS[o.status][0], STATUS[o.status][1]] : [txt(o.status_label) || txt(o.status) || '—', 'is-muted']);

    // what the service applies to: one activity, a whole location, or the account (ad tracking on bilete.online)
    var cfg = o.config || {};
    var appliesLabel = 'Activitate', applies = txt(o.event && o.event.name) || txt(o.event_name);
    if (o.type === 'location_featuring') { appliesLabel = 'Locația'; applies = txt(cfg.location_name) || applies; }
    else if (o.type === 'tracking' && !applies) { appliesLabel = 'Se aplică la'; applies = 'Tot contul tău'; }
    if ($('sd-event-l')) $('sd-event-l').textContent = appliesLabel;
    set('sd-event', applies);
    set('sd-details', txt(o.details));
    set('sd-created', stamp(o.created_at));
    var start = day(o.service_start_date), end = day(o.service_end_date);
    set('sd-period', start && end ? start + ' - ' + end : start ? 'Din ' + start : '');

    set('sd-subtotal', money(o.subtotal, cur));
    set('sd-tax', money(o.tax, cur));
    set('sd-total', money(o.total, cur));
    set('sd-method', o.payment_method === 'card' ? 'Card online' : o.payment_method === 'transfer' ? 'Transfer bancar' : txt(o.payment_method));
    set('sd-paystatus', PAY[o.payment_status] || txt(o.payment_status));
    set('sd-paidat', o.paid_at ? stamp(o.paid_at) : '');

    $('sd-config').textContent = JSON.stringify(o.config == null ? {} : o.config, null, 2);
    renderEmail(o);
    renderPixels(o);
  }

  function renderEmail(o) {
    var box = $('sd-email');
    box.hidden = o.type !== 'email';
    if (box.hidden) return;
    var nl = o.newsletter && typeof o.newsletter === 'object' ? o.newsletter : null;
    var n = function (k, v) { $('sd-n-' + k).textContent = F.num(F.toNum(v)); };
    var rate = function (k, v) { var p = Math.max(0, Math.min(100, F.toNum(v))); $('sd-' + k + '-rate').textContent = F.pct(F.toNum(v), 1); $('sd-' + k + '-bar').style.width = p + '%'; };
    n('sent', nl ? nl.sent_count : o.sent_count);
    n('opened', nl && nl.opened_count); n('clicked', nl && nl.clicked_count); n('failed', nl && nl.failed_count); n('unsub', nl && nl.unsubscribed_count);
    rate('open', nl && nl.open_rate); rate('click', nl && nl.click_rate);
    var st = $('sd-nl-status'), dates = [];
    st.hidden = !nl;
    if (nl) {
      tag(st, NL[nl.status] || [txt(nl.status) || '—', 'is-muted']);
      if (nl.scheduled_at) dates.push('Programat: ' + stamp(nl.scheduled_at));
      if (nl.completed_at) dates.push('Finalizat: ' + stamp(nl.completed_at));
    }
    $('sd-nl-dates').textContent = dates.join(' · ');

    var c = o.config && typeof o.config === 'object' ? o.config : {};
    set('sd-aud-type', c.audience_type === 'own' ? 'Clienții tăi' : c.audience_type === 'marketplace' ? 'Baza marketplace' : txt(c.audience_type));
    var perfect = c.perfect_count != null ? c.perfect_count : c.recipient_count;
    set('sd-aud-perfect', perfect != null ? F.num(F.toNum(perfect)) : '');
    set('sd-aud-partial', F.num(F.toNum(c.partial_count)));
    set('sd-aud-template', txt(c.template));
    var f = c.filters && typeof c.filters === 'object' ? c.filters : {}, tags = [];
    var list = function (v, prefix) { if (Array.isArray(v)) v.forEach(function (x) { var s = txt(x && typeof x === 'object' ? x.name || x.label || x.slug : x); if (s) tags.push(prefix + s); }); };
    list(f.cities, 'Oraș: '); list(f.categories, 'Categorie: '); list(f.genres, 'Gen: ');
    if (txt(f.gender)) tags.push('Gen: ' + txt(f.gender));
    if (f.age_min || f.age_max) tags.push('Vârstă: ' + (txt(f.age_min) || '?') + '-' + (txt(f.age_max) || '?'));
    var ul = $('sd-filter-tags');
    ul.textContent = '';
    tags.forEach(function (t) { ul.appendChild(el('li', { text: t })); });
    $('sd-filters').hidden = !tags.length;
  }

  function renderPixels(o) {
    var box = $('sd-px'), setup = Array.isArray(o.tracking_setup) ? o.tracking_setup : null;
    box.hidden = !(o.type === 'tracking' && o.payment_status === 'paid' && setup);
    if (box.hidden) return;
    var listEl = $('sd-px-list');
    listEl.textContent = '';
    setup.forEach(function (t) {
      var platform = txt(t && t.platform);
      if (!/^[a-z0-9_]+$/.test(platform)) return;
      var meta = PLATFORMS[platform] || [platform, ''], id = 'sd-px-' + platform, filled = !!t.has_pixel;
      var input = el('input', { class: 'sd-input', type: 'text', id: id, maxlength: MAX_PIXEL, autocomplete: 'off', spellcheck: 'false', placeholder: meta[1], 'data-tracking-pixel': platform });
      input.value = txt(t.pixel_id);
      listEl.appendChild(el('div', { class: 'sd-px-row' }, [
        el('div', null, [
          el('label', { for: id, text: meta[0] }),
          el('span', { class: 'org-tag ' + (filled ? 'is-ok' : 'is-wait') }, [O.icon(filled ? 'check-circle' : 'warning-circle'), filled ? 'Activ' : 'Necesită Pixel ID']),
        ]),
        input,
      ]));
    });
  }

  $('sd-px-form').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!order) return;
    var btn = $('sd-px-save'), msg = $('sd-px-msg'), ids = {}, tooLong = null;
    Array.prototype.forEach.call(document.querySelectorAll('[data-tracking-pixel]'), function (i) {
      var v = i.value.trim();
      i.removeAttribute('aria-invalid');
      if (v.length > MAX_PIXEL && !tooLong) tooLong = i;
      ids[i.getAttribute('data-tracking-pixel')] = v;
    });
    if (tooLong) {
      tooLong.setAttribute('aria-invalid', 'true');
      tooLong.focus();
      msg.className = 'is-bad';
      msg.textContent = 'Un Pixel ID are cel mult ' + MAX_PIXEL + ' de caractere.';
      return;
    }
    btn.disabled = true;
    msg.className = '';
    msg.textContent = 'Se salvează…';
    O.api('/organizer/services/orders/' + encodeURIComponent(txt(order.id) || uuid) + '/tracking-pixels', { method: 'POST', body: { pixel_ids: ids } }).then(function (r) {
      var fresh = r && r.data && r.data.order;
      if (fresh && typeof fresh === 'object') { order = fresh; renderPixels(fresh); }
      msg.className = 'is-ok';
      msg.textContent = 'Salvat ' + F.date(new Date(), { hour: '2-digit', minute: '2-digit' });
    }, function (err) {
      if (err && err.status === 401) { msg.textContent = ''; return; }
      msg.className = 'is-bad';
      msg.textContent = err && err.status === 422 ? 'Verifică ID-urile: fiecare are cel mult ' + MAX_PIXEL + ' de caractere.' : 'Nu am putut salva ID-urile. Încearcă din nou.';
    }).then(function () { btn.disabled = false; });
  });
  $('sd-retry').addEventListener('click', load);

  O.ready.then(function (ok) { if (ok) load(); });
})();
