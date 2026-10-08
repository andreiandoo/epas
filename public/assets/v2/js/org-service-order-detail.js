/* viaqui.com v2: organizer service order (/organizator/services/{uuid}). Reads /organizer/services/orders/{uuid};
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
  var STATUS = { draft: [VQ.t('Draft'), 'is-muted'], pending_payment: [VQ.t('Awaiting payment'), 'is-wait'], processing: [VQ.t('Processing'), 'is-wait'], active: [VQ.t('Active'), 'is-ok'], completed: [VQ.t('Completed'), 'is-muted'], cancelled: [VQ.t('Cancelled'), 'is-bad'], refunded: [VQ.t('Refunded'), 'is-bad'] };
  var TYPES = { featuring: VQ.t('Experience promotion'), location_featuring: VQ.t('Venue promotion'), email: VQ.t('Email marketing'), tracking: VQ.t('Ad tracking'), campaign: VQ.t('Campaign creation') };
  var PAY = { paid: VQ.t('Paid'), unpaid: VQ.t('Unpaid'), pending: VQ.t('Pending'), failed: VQ.t('Failed'), refunded: VQ.t('Refunded') };
  var NL = { draft: [VQ.t('Draft'), 'is-muted'], scheduled: [VQ.t('Scheduled'), 'is-wait'], sending: [VQ.t('Sending'), 'is-wait'], sent: [VQ.t('Sent'), 'is-ok'], failed: [VQ.t('Failed'), 'is-bad'], cancelled: [VQ.t('Cancelled'), 'is-bad'] };
  var PLATFORMS = { facebook: ['Facebook Pixel', '1234567890123456'], google: ['Google Ads', 'AW-XXXXXXXXX'], tiktok: ['TikTok Pixel', 'CXXXXXXXXXXXXXXXXX'] };
  var MAX_PIXEL = 50;
  var order = null;

  function txt(v) { return F.flat(v).trim(); }
  function day(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric' }) : ''; }
  function stamp(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'; }
  /** Money in the currency the order carries; the currency of the site (euro) when it carries none. */
  function money(v, cur) {
    cur = /^lei$/i.test(cur || '') ? 'RON' : cur;
    if (typeof BileteOnlineUtils !== 'undefined' && BileteOnlineUtils.formatCurrency) return BileteOnlineUtils.formatCurrency(F.toNum(v), cur || true);
    return cur ? F.num(F.toNum(v)) + ' ' + cur : '€' + F.num(F.toNum(v));
  }
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
    var number = txt(o.order_number), label = TYPES[o.type] || txt(o.type_label) || VQ.t('Service'), cur = txt(o.currency);
    $('sd-crumb').textContent = number || VQ.t('Order');
    $('sd-title').textContent = number ? label + ' - ' + number : label;
    document.title = (number ? label + ' ' + number : label) + ' · viaqui.com';
    var ic = $('sd-type-ic');
    ic.className = 'sd-type-ic is-' + (/^[a-z]+$/.test(o.type) ? o.type : 'other');
    ic.textContent = '';
    ic.appendChild(typeIcon(o.type));
    $('sd-type').textContent = label;
    $('sd-number').textContent = number;
    tag($('sd-status'), STATUS[o.status] || [txt(o.status_label) || txt(o.status) || '—', 'is-muted']);

    // what the service applies to: one activity, a whole location, or the account (ad tracking on viaqui.com)
    var cfg = o.config || {};
    var appliesLabel = VQ.t('Experience'), applies = txt(o.event && o.event.name) || txt(o.event_name);
    if (o.type === 'location_featuring') { appliesLabel = VQ.t('Venue'); applies = txt(cfg.location_name) || applies; }
    else if (o.type === 'featuring' && txt(o.activity_name || cfg.activity_name)) { applies = txt(o.activity_name || cfg.activity_name); }
    else if (o.type === 'tracking' && !applies) { appliesLabel = VQ.t('Applies to'); applies = VQ.t('Your whole account'); }
    if ($('sd-event-l')) $('sd-event-l').textContent = appliesLabel;
    set('sd-event', applies);
    set('sd-details', txt(o.details));
    set('sd-created', stamp(o.created_at));
    var start = day(o.service_start_date), end = day(o.service_end_date);
    var period = start && end ? start + ' - ' + end : start ? VQ.t('From {date}', { date: start }) : '';
    // A paid promotion runs only between its dates: say where it stands today.
    if (period && o.status === 'active' && (o.type === 'featuring' || o.type === 'location_featuring')) {
      var today = F.ymd(), s0 = String(o.service_start_date || '').slice(0, 10), e0 = String(o.service_end_date || '').slice(0, 10);
      period += ' · ' + (s0 && today < s0 ? VQ.t('scheduled') : e0 && today > e0 ? VQ.t('ended') : VQ.t('showing now'));
    }
    set('sd-period', period);

    set('sd-subtotal', money(o.subtotal, cur));
    set('sd-tax', money(o.tax, cur));
    set('sd-total', money(o.total, cur));
    set('sd-method', o.payment_method === 'card' ? VQ.t('Card online') : o.payment_method === 'transfer' ? VQ.t('Bank transfer') : txt(o.payment_method));
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
      if (nl.scheduled_at) dates.push(VQ.t('Scheduled: {date}', { date: stamp(nl.scheduled_at) }));
      if (nl.completed_at) dates.push(VQ.t('Completed: {date}', { date: stamp(nl.completed_at) }));
    }
    $('sd-nl-dates').textContent = dates.join(' · ');

    var c = o.config && typeof o.config === 'object' ? o.config : {};
    set('sd-aud-type', c.audience_type === 'own' ? VQ.t('Your customers') : c.audience_type === 'marketplace' ? VQ.t('The marketplace audience') : txt(c.audience_type));
    var perfect = c.perfect_count != null ? c.perfect_count : c.recipient_count;
    set('sd-aud-perfect', perfect != null ? F.num(F.toNum(perfect)) : '');
    set('sd-aud-partial', F.num(F.toNum(c.partial_count)));
    set('sd-aud-template', txt(c.template));
    var f = c.filters && typeof c.filters === 'object' ? c.filters : {}, tags = [];
    var list = function (v, label) { if (Array.isArray(v)) v.forEach(function (x) { var s = txt(x && typeof x === 'object' ? x.name || x.label || x.slug : x); if (s) tags.push(label(s)); }); };
    list(f.cities, function (s) { return VQ.t('City: {name}', { name: s }); }); list(f.categories, function (s) { return VQ.t('Category: {name}', { name: s }); }); list(f.genres, function (s) { return VQ.t('Genre: {name}', { name: s }); });
    if (txt(f.gender)) tags.push(VQ.t('Gender: {value}', { value: txt(f.gender) }));
    if (f.age_min || f.age_max) tags.push(VQ.t('Age: {from}-{to}', { from: txt(f.age_min) || '?', to: txt(f.age_max) || '?' }));
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
          el('span', { class: 'org-tag ' + (filled ? 'is-ok' : 'is-wait') }, [O.icon(filled ? 'check-circle' : 'warning-circle'), filled ? VQ.t('Active') : VQ.t('Pixel ID needed')]),
        ]),
        input,
      ]));
      // Google Ads counts a purchase only against a conversion action: its label comes from Google Ads
      // (Goals → Conversions → "Conversion label"). Sent only when core returns the field.
      if (platform === 'google' && t.conversion_label !== undefined) {
        var lid = 'sd-px-google-label', label = el('input', { class: 'sd-input', type: 'text', id: lid, maxlength: 64, autocomplete: 'off', spellcheck: 'false', placeholder: VQ.t('e.g. AbC-D_efGhIjKlMn'), 'data-tracking-label': 'google' });
        label.value = txt(t.conversion_label);
        listEl.appendChild(el('div', { class: 'sd-px-row' }, [
          el('div', null, [
            el('label', { for: lid, text: VQ.t('Google Ads: conversion label') }),
            el('span', { class: 'org-tag ' + (label.value ? 'is-ok' : 'is-wait') }, [O.icon(label.value ? 'check-circle' : 'warning-circle'), label.value ? VQ.t('Purchases are reported') : VQ.t('Without it, purchases do not appear in Google Ads')]),
          ]),
          label,
        ]));
      }
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
    var gl = document.querySelector('[data-tracking-label="google"]');
    if (gl) ids.google_label = gl.value.trim();
    if (tooLong) {
      tooLong.setAttribute('aria-invalid', 'true');
      tooLong.focus();
      msg.className = 'is-bad';
      msg.textContent = VQ.t('A Pixel ID has {max} characters at most.', { max: MAX_PIXEL });
      return;
    }
    btn.disabled = true;
    msg.className = '';
    msg.textContent = VQ.t('Saving…');
    O.api('/organizer/services/orders/' + encodeURIComponent(txt(order.id) || uuid) + '/tracking-pixels', { method: 'POST', body: { pixel_ids: ids } }).then(function (r) {
      var fresh = r && r.data && r.data.order;
      if (fresh && typeof fresh === 'object') { order = fresh; renderPixels(fresh); }
      msg.className = 'is-ok';
      msg.textContent = VQ.t('Saved at {time}', { time: F.date(new Date(), { hour: '2-digit', minute: '2-digit' }) });
    }, function (err) {
      if (err && err.status === 401) { msg.textContent = ''; return; }
      msg.className = 'is-bad';
      msg.textContent = err && err.status === 422 ? VQ.t('Check the IDs: each has {max} characters at most.', { max: MAX_PIXEL }) : VQ.t('We could not save the IDs. Try again.');
    }).then(function () { btn.disabled = false; });
  });
  $('sd-retry').addEventListener('click', load);

  O.ready.then(function (ok) { if (ok) load(); });
})();
