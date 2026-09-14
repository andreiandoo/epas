/* bilete.online v2: order recovery. Posts the order number and email through the API proxy, then turns the search card
   into the found order: summary, whether the tickets email went out, and the way into an account. A signed-in customer
   attaches the order; anyone else can log in and is brought back here with the order restored, so attaching doesn't
   need a second search (and a second email). Every error is said in Romanian. */
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
  var MONTHS = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
  var STATUS = {
    paid: ['Plătită', 'ok'], confirmed: ['Confirmată', 'ok'], completed: ['Finalizată', 'ok'],
    pending: ['În așteptare', 'wait'], processing: ['În procesare', 'wait'], partially_refunded: ['Rambursată parțial', 'wait'],
    cancelled: ['Anulată', 'bad'], canceled: ['Anulată', 'bad'], refunded: ['Rambursată', 'bad'], failed: ['Eșuată', 'bad'], expired: ['Expirată', 'bad']
  };
  var GENERIC = 'Nu am putut căuta comanda acum. Încearcă din nou în câteva momente.';
  var money = new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
    if (n === 1) return '1 bilet';
    return n + (n !== 0 && (n % 100 === 0 || n % 100 >= 20) ? ' de bilete' : ' bilete');
  }
  function formatDate(value) {
    var d = value ? new Date(value) : null;
    return d && !isNaN(d) ? d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() : '';
  }
  function loginHref() {
    return '/autentificare?email=' + encodeURIComponent(state.email) + '&redirect=' + encodeURIComponent('/recuperare-comanda?order=' + state.order);
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
      ? 'Intră în cont cu acest email și te aducem înapoi aici ca s-o atașezi.'
      : 'Creează-ți un cont cu acest email ca să ai biletele mereu la îndemână.';
    $('rc-login').href = loginHref();
    var register = $('rc-register');
    register.href = '/inregistrare?email=' + encodeURIComponent(state.email);
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
    if (!order && !email) { say(error, 'Completează numărul comenzii și emailul.', orderInput); return; }
    if (!order) { say(error, 'Introdu numărul comenzii.', orderInput); return; }
    if (!email) { say(error, 'Introdu emailul folosit la comandă.', emailInput); return; }
    if (!emailInput.checkValidity()) { say(error, 'Adresa de email nu pare corectă. Verific-o și încearcă din nou.', emailInput); return; }
    if (typeof BileteOnlineAPI === 'undefined') { say(error, GENERIC); return; }

    state.busy = true;
    submit.disabled = true;
    submit.textContent = 'Se caută…';
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
        if (status === 404) say(error, 'Nu am găsit o comandă cu aceste date. Verifică numărul comenzii și emailul folosit la cumpărare.', orderInput);
        else if (status === 422 && errors.email) say(error, 'Adresa de email nu pare corectă. Verific-o și încearcă din nou.', emailInput);
        else if (status === 422) say(error, 'Verifică numărul comenzii și emailul, apoi încearcă din nou.', orderInput);
        else if (status === 429) say(error, 'Prea multe încercări. Încearcă din nou peste un minut.');
        else if (status === 0) say(error, 'Nu ne-am putut conecta. Verifică internetul și încearcă din nou.');
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
    attachLabel.textContent = 'Se atașează…';
    BileteOnlineAPI.post('/customer/recover-order/attach', { order_number: state.order, email: state.email })
      .then(function (resp) {
        if (!(resp && resp.success)) { say(attachError, 'Nu am putut atașa comanda acum. Încearcă din nou.'); return; }
        state.attached = true;
        session(null);
        $('rc-attached-t').textContent = /deja/i.test(resp.message || '') ? 'Comanda este deja în contul tău' : 'Comanda a fost atașată contului tău';
        renderAccount();
        $('rc-attached').focus();
      })
      .catch(function (err) {
        var status = err && err.status;
        if (status === 401) say(attachError, 'Sesiunea ta a expirat. Intră din nou în cont, apoi atașează comanda.', null, { href: loginHref(), text: 'Intră în cont' });
        else if (status === 404) say(attachError, 'Nu am găsit o comandă cu aceste date.');
        else if (status === 409) say(attachError, 'Comanda este deja atașată unui alt cont. Dacă e comanda ta,', null, { href: '/contact', text: 'scrie-ne.' });
        else if (status === 429) say(attachError, 'Prea multe încercări. Încearcă din nou peste un minut.');
        else if (status === 0) say(attachError, 'Nu ne-am putut conecta. Verifică internetul și încearcă din nou.');
        else say(attachError, 'Nu am putut atașa comanda acum. Încearcă din nou.');
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
