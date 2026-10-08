/* viaqui.com v2: order recovery. Posts the order number and email through the API proxy, then turns the search card
   into the found order: summary, whether the tickets email went out, and the way into an account. A signed-in customer
   attaches the order; anyone else can log in and is brought back here with the order restored, so attaching doesn't
   need a second search (and a second email). Every error is said in English. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var form = $('rc-form');
  if (!form) return;

  var formView = $('rc-form-view'), result = $('rc-result'), error = $('rc-error');
  var orderInput = $('rc-order'), emailInput = $('rc-email'), submit = $('rc-submit');
  var attachBtn = $('rc-attach'), attachError = $('rc-attach-error'), attachLabel = attachBtn.querySelector('span');
  var LABEL = submit.textContent, ATTACH_LABEL = attachLabel.textContent;
  var PENDING = 'bo_recover_pending', PENDING_TTL = 30 * 60 * 1000;
  var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var STATUS = {
    paid: ['Paid', 'ok'], confirmed: ['Confirmed', 'ok'], completed: ['Completed', 'ok'],
    pending: ['Pending', 'wait'], processing: ['Processing', 'wait'], partially_refunded: ['Partially refunded', 'wait'],
    cancelled: ['Cancelled', 'bad'], canceled: ['Cancelled', 'bad'], refunded: ['Refunded', 'bad'], failed: ['Failed', 'bad'], expired: ['Expired', 'bad']
  };
  var GENERIC = 'We could not look up the order just now. Try again in a few moments.';
  var money = new Intl.NumberFormat('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  var state = { order: '', email: '', data: null, attached: false, busy: false, attaching: false };

  // ---------- helpers ----------
  function customer() {
    try {
      if (typeof BileteOnlineAuth === 'undefined' || !BileteOnlineAuth.isLoggedIn()) return false;
      var type = BileteOnlineAuth.getUserType();
      return !type || type === 'customer'; // the same rule the header uses for the account badge
    } catch (e) { return false; }
  }
  function session(value) {
    try {
      if (value === undefined) return JSON.parse(sessionStorage.getItem(PENDING) || 'null');
      if (value === null) sessionStorage.removeItem(PENDING);
      else sessionStorage.setItem(PENDING, JSON.stringify(value));
    } catch (e) {}
    return null;
  }
  function say(box, text, field, link) {
    box.textContent = text;
    if (link) {
      var a = document.createElement('a');
      a.href = link.href;
      a.textContent = link.text;
      box.appendChild(document.createTextNode(' '));
      box.appendChild(a);
    }
    box.hidden = false;
    [orderInput, emailInput].forEach(function (el) { el.removeAttribute('aria-invalid'); });
    if (field) { field.setAttribute('aria-invalid', 'true'); field.focus(); }
  }
  function tickets(n) {
    n = Math.floor(Number(n));
    if (isNaN(n) || n < 0) return '—';
    if (n === 1) return '1 ticket';
    return n + ' tickets';
  }
  function formatDate(value) {
    var d = value ? new Date(value) : null;
    return d && !isNaN(d) ? d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() : '';
  }
  function loginHref() {
    return '/login?email=' + encodeURIComponent(state.email) + '&redirect=' + encodeURIComponent('/find-order?order=' + state.order);
  }

  // ---------- the found order ----------
  function renderAccount() {
    var signedIn = customer();
    var data = state.data || {};
    [].forEach.call(document.querySelectorAll('.rc-card-foot'), function (p) { p.hidden = (p.getAttribute('data-when') === 'customer') !== signedIn; });
    $('rc-attached').hidden = !state.attached;
    $('rc-attach-box').hidden = !signedIn || state.attached;
    $('rc-guest-box').hidden = signedIn || state.attached;
    $('rc-guest-p').textContent = data.has_account
      ? 'Sign in with this email and we will bring you back here to add it.'
      : 'Create an account with this email to keep your tickets close at hand.';
    $('rc-login').href = loginHref();
    var register = $('rc-register');
    register.href = '/register?email=' + encodeURIComponent(state.email);
    register.hidden = !!data.has_account;
  }

  function render(data, restored) {
    var o = data.order || {};
    state.data = data;
    state.attached = false;
    $('rc-r-number').textContent = o.order_number || state.order;
    $('rc-r-event').textContent = o.event_name || '—';
    $('rc-r-tickets').textContent = o.ticket_count == null ? '—' : tickets(o.ticket_count);
    var placed = formatDate(o.created_at);
    $('rc-r-date').textContent = placed;
    $('rc-r-date-row').hidden = !placed;
    var status = STATUS[String(o.status || '').toLowerCase()];
    var pill = $('rc-r-status');
    pill.textContent = status ? status[0] : (o.status || '—');
    pill.setAttribute('data-tone', status ? status[1] : 'wait');
    $('rc-r-total').textContent = o.total == null || o.total === '' ? '—' : money.format(Number(o.total) || 0) + ' ' + (o.currency || 'RON');
    $('rc-r-email').textContent = state.email;
    // the email notes belong to the search that just ran, not to an order restored after logging in
    $('rc-sent').hidden = restored || !data.email_resent;
    $('rc-not-sent').hidden = restored || !!data.email_resent;
    attachError.hidden = true;
    renderAccount();
    formView.hidden = true;
    result.hidden = false;
    // kept for the way back from the login page (this tab only)
    session({ order: state.order, email: state.email, data: data, at: Date.now() });
  }

  // ---------- search ----------
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (state.busy) return;
    error.hidden = true;
    var order = orderInput.value.trim().replace(/^#+\s*/, '').toUpperCase();
    var email = emailInput.value.trim();
    orderInput.value = order;
    emailInput.value = email;
    if (!order && !email) { say(error, 'Enter your order number and email.', orderInput); return; }
    if (!order) { say(error, 'Enter your order number.', orderInput); return; }
    if (!email) { say(error, 'Enter the email used for the order.', emailInput); return; }
    if (!emailInput.checkValidity()) { say(error, 'That email address does not look right. Check it and try again.', emailInput); return; }
    if (typeof BileteOnlineAPI === 'undefined') { say(error, GENERIC); return; }

    state.busy = true;
    submit.disabled = true;
    submit.textContent = 'Searching…';
    form.setAttribute('aria-busy', 'true');
    BileteOnlineAPI.post('/customer/recover-order', { order_number: order, email: email, resend: true })
      .then(function (resp) {
        if (resp && resp.success && resp.data) {
          state.order = (resp.data.order && resp.data.order.order_number) || order;
          state.email = email;
          [orderInput, emailInput].forEach(function (el) { el.removeAttribute('aria-invalid'); });
          render(resp.data, false);
          $('rc-r-number').focus();
          return;
        }
        say(error, GENERIC);
      })
      .catch(function (err) {
        var status = err && err.status, errors = (err && err.data && err.data.errors) || {};
        if (status === 404) say(error, 'We could not find an order with these details. Check the order number and the email you used to buy.', orderInput);
        else if (status === 422 && errors.email) say(error, 'That email address does not look right. Check it and try again.', emailInput);
        else if (status === 422) say(error, 'Check the order number and email, then try again.', orderInput);
        else if (status === 429) say(error, 'Too many attempts. Try again in a minute.');
        else if (status === 0) say(error, 'We could not connect. Check your internet connection and try again.');
        else say(error, GENERIC);
      })
      .then(function () {
        state.busy = false;
        submit.disabled = false;
        submit.textContent = LABEL;
        form.removeAttribute('aria-busy');
      });
  });

  // ---------- attach to the account ----------
  attachBtn.addEventListener('click', function () {
    if (state.attaching) return;
    if (!customer()) { renderAccount(); return; }
    state.attaching = true;
    attachError.hidden = true;
    attachBtn.disabled = true;
    attachLabel.textContent = 'Adding…';
    BileteOnlineAPI.post('/customer/recover-order/attach', { order_number: state.order, email: state.email })
      .then(function (resp) {
        if (!(resp && resp.success)) { say(attachError, 'We could not add the order just now. Please try again.'); return; }
        state.attached = true;
        session(null);
        $('rc-attached-t').textContent = /deja|already/i.test(resp.message || '') ? 'This order is already in your account' : 'The order has been added to your account';
        renderAccount();
        $('rc-attached').focus();
      })
      .catch(function (err) {
        var status = err && err.status;
        if (status === 401) say(attachError, 'Your session has expired. Sign in again, then add the order.', null, { href: loginHref(), text: 'Sign in' });
        else if (status === 404) say(attachError, 'We could not find an order with these details.');
        else if (status === 409) say(attachError, 'This order is already attached to another account. If it is yours,', null, { href: '/contact', text: 'contact us.' });
        else if (status === 429) say(attachError, 'Too many attempts. Try again in a minute.');
        else if (status === 0) say(attachError, 'We could not connect. Check your internet connection and try again.');
        else say(attachError, 'We could not add the order just now. Please try again.');
      })
      .then(function () {
        state.attaching = false;
        attachBtn.disabled = false;
        attachLabel.textContent = ATTACH_LABEL;
      });
  });

  // ---------- another order ----------
  $('rc-again').addEventListener('click', function () {
    session(null);
    state.data = null;
    state.attached = false;
    orderInput.value = '';
    emailInput.value = state.email || emailInput.value; // the same person usually looks for another order of theirs
    error.hidden = true;
    [orderInput, emailInput].forEach(function (el) { el.removeAttribute('aria-invalid'); });
    result.hidden = true;
    formView.hidden = false;
    try {
      var url = new URL(window.location.href);
      if (url.searchParams.has('order')) { url.searchParams.delete('order'); history.replaceState(null, '', url.pathname + url.search + url.hash); }
    } catch (e) {}
    orderInput.focus();
  });

  // ---------- back from logging in ----------
  var pending = session();
  if (pending && pending.data && Date.now() - (pending.at || 0) < PENDING_TTL && (!orderInput.value || orderInput.value === pending.order)) {
    orderInput.value = pending.order;
    emailInput.value = pending.email;
    if (customer()) {
      state.order = pending.order;
      state.email = pending.email;
      render(pending.data, true);
    }
  } else if (pending) {
    session(null);
  }
  renderAccount();

  // logging in or out in another tab changes what the found order offers
  window.addEventListener('storage', function (e) { if (!e.key || e.key.indexOf('bileteonline_') === 0) renderAccount(); });
  window.addEventListener('bileteonline:auth:login', renderAccount);
})();
