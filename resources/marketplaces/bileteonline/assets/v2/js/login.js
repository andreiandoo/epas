/* bilete.online v2: sign-in / sign-up (/autentificare). Vanilla port of the previous Alpine component (authPage) with the
   same rules: account type (client / venue) and mode (login / register), the customer 2FA step, BileteOnlineAuth calls
   and redirects, and the jump to the account for someone already signed in. The copy of each state comes from
   login.php through #v2-data. */
(function () {
  'use strict';
  var root = document.getElementById('au');
  if (!root) return;

  var $ = function (id) { return document.getElementById(id); };
  var data = {};
  try { data = JSON.parse(($('v2-data') || {}).textContent || '{}'); } catch (e) {}
  var copy = data.copy || {};
  var redirectAfter = data.redirectAfter || '/cont';
  var state = {
    type: data.accountType === 'venue' ? 'venue' : 'client',
    mode: data.mode === 'register' ? 'register' : 'login',
    twofa: false,
    challenge: '',
    submitting: false,
    showPassword: false
  };
  var msg = $('au-msg');
  var forms = { login: $('au-login'), twofa: $('au-2fa'), register: $('au-register') };

  function hasAuth() {
    return typeof BileteOnlineAuth !== 'undefined';
  }

  function textFor(type, mode, key) {
    return ((copy[type] || {})[mode] || {})[key] || '';
  }

  function showMessage(text, type) {
    msg.textContent = text || '';
    msg.className = 'au-msg ' + (type === 'success' ? 'is-ok' : 'is-err');
    msg.setAttribute('role', type === 'success' ? 'status' : 'alert');
    msg.hidden = !text;
    // On a phone the submit button sits well below the message: bring an error into view
    if (text && type !== 'success') {
      var r = msg.getBoundingClientRect();
      if (r.top < 80 || r.bottom > window.innerHeight) msg.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  function setSubmitting(on) {
    state.submitting = on;
    root.querySelectorAll('.au-go').forEach(function (b) {
      b.disabled = on;
      b.classList.toggle('is-busy', on);
    });
  }

  function render() {
    root.setAttribute('data-type', state.type);
    root.setAttribute('data-mode', state.mode);
    $('au-title').textContent = textFor(state.type, state.mode, 'hero');
    $('au-text').textContent = textFor(state.type, state.mode, 'heroText');
    $('au-form-title').textContent = textFor(state.type, state.mode, 'form');
    $('au-form-text').textContent = textFor(state.type, state.mode, 'formText');
    $('au-form-kicker').textContent = state.type === 'venue' ? 'Cont locație' : 'Cont client';

    root.querySelectorAll('[data-set-type]').forEach(function (b) {
      b.setAttribute('aria-pressed', String(b.getAttribute('data-set-type') === state.type));
    });
    root.querySelectorAll('[data-set-mode]').forEach(function (b) {
      b.setAttribute('aria-pressed', String(b.getAttribute('data-set-mode') === state.mode));
    });

    forms.login.hidden = !(state.mode === 'login' && !state.twofa);
    forms.twofa.hidden = !(state.mode === 'login' && state.twofa);
    forms.register.hidden = state.mode !== 'register';

    // Fields of the other account type are hidden and disabled, so the browser's required checks skip them
    root.querySelectorAll('[data-for]').forEach(function (el) {
      var on = el.getAttribute('data-for') === state.type;
      el.hidden = !on;
      el.querySelectorAll('input').forEach(function (input) { input.disabled = !on; });
    });

    $('au-login-email').placeholder = state.type === 'venue' ? 'email organizator / staff' : 'emailul folosit la comandă';
    $('au-reg-email').placeholder = state.type === 'venue' ? 'email@locatie.ro' : 'email@example.ro';
    setIdle($('au-login-submit'), textFor(state.type, 'login', 'submit'));
    setIdle($('au-register-submit'), textFor(state.type, 'register', 'submit'));
    $('au-switch-text').textContent = state.mode === 'login' ? 'Nu ai cont?' : 'Ai deja cont?';
    $('au-switch').textContent = state.mode === 'login' ? 'Creează unul acum' : 'Intră în cont';
  }

  function setIdle(button, text) {
    var span = button && button.querySelector('[data-idle]');
    if (span && text) span.textContent = text;
  }

  // ---------- tabs, quick buttons, password visibility ----------
  root.addEventListener('click', function (e) {
    var t = e.target.closest('[data-set-type], [data-set-mode], [data-go-type], [data-toggle-pass]');
    if (!t) return;

    if (t.hasAttribute('data-set-type')) {
      state.type = t.getAttribute('data-set-type');
      render();
    } else if (t.hasAttribute('data-set-mode')) {
      state.mode = t.getAttribute('data-set-mode');
      render();
    } else if (t.hasAttribute('data-go-type')) {
      state.type = t.getAttribute('data-go-type');
      state.mode = 'login';
      render();
      var card = $('au-card');
      var r = card.getBoundingClientRect();
      if (r.top < 0 || r.top > window.innerHeight * 0.6) card.scrollIntoView({ behavior: 'smooth', block: 'start' });
      if (!forms.login.hidden) $('au-login-email').focus({ preventScroll: true });
    } else {
      state.showPassword = !state.showPassword;
      ['au-login-pass', 'au-reg-pass', 'au-reg-pass2'].forEach(function (id) {
        var input = $(id);
        if (input) input.type = state.showPassword ? 'text' : 'password';
      });
      root.querySelectorAll('[data-toggle-pass]').forEach(function (b) {
        b.textContent = state.showPassword ? 'ascunde' : 'arată';
        b.setAttribute('aria-pressed', String(state.showPassword));
        b.setAttribute('aria-label', state.showPassword ? 'Ascunde parola' : 'Arată parola');
      });
    }
  });

  $('au-switch').addEventListener('click', function () {
    state.mode = state.mode === 'login' ? 'register' : 'login';
    showMessage('');
    render();
  });

  // ---------- login ----------
  forms.login.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (state.submitting) return;
    if (!hasAuth()) {
      showMessage('Sistemul de autentificare nu este încărcat. Reîncarcă pagina.', 'error');
      return;
    }
    showMessage('');
    setSubmitting(true);
    try {
      var fn = state.type === 'venue'
        ? BileteOnlineAuth.loginOrganizer.bind(BileteOnlineAuth)
        : BileteOnlineAuth.loginCustomer.bind(BileteOnlineAuth);
      var result = await fn($('au-login-email').value.trim(), $('au-login-pass').value);

      // 2FA challenge (client accounts): show the code form instead of finishing the login
      if (result && result.success && result.requires2fa) {
        state.twofa = true;
        state.challenge = result.challenge;
        $('au-2fa-code').value = '';
        setSubmitting(false);
        render();
        $('au-2fa-code').focus();
        return;
      }

      if (result && result.success) {
        showMessage('Conectare reușită. Te redirecționăm…', 'success');
        var target = state.type === 'venue' ? '/organizator/panou' : redirectAfter;
        setTimeout(function () { window.location.href = target; }, 500);
      } else {
        showMessage((result && result.message) || 'Email sau parolă incorecte.', 'error');
        setSubmitting(false);
      }
    } catch (err) {
      showMessage('Eroare la conectare. Încearcă din nou.', 'error');
      setSubmitting(false);
    }
  });

  // ---------- 2FA ----------
  function cancel2fa() {
    state.twofa = false;
    state.challenge = '';
    setSubmitting(false);
    render();
    $('au-login-email').focus();
  }

  forms.twofa.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (state.submitting) return;
    if (!hasAuth() || !state.challenge) {
      showMessage('Sesiunea a expirat. Reia autentificarea.', 'error');
      cancel2fa();
      return;
    }
    var code = $('au-2fa-code').value.trim();
    if (!code) {
      showMessage('Introdu codul.', 'error');
      return;
    }
    setSubmitting(true);
    try {
      var r = await BileteOnlineAuth.finishCustomer2faLogin(state.challenge, code);
      if (r && r.success) {
        showMessage('Cod corect. Te redirecționăm…', 'success');
        setTimeout(function () { window.location.href = redirectAfter; }, 500);
      } else {
        showMessage((r && r.message) || 'Codul nu este valid.', 'error');
        setSubmitting(false);
      }
    } catch (err) {
      showMessage('Eroare la verificare.', 'error');
      setSubmitting(false);
    }
  });

  $('au-2fa-cancel').addEventListener('click', cancel2fa);

  // ---------- register ----------
  forms.register.addEventListener('submit', async function (e) {
    e.preventDefault();
    if (state.submitting) return;
    if (!hasAuth()) {
      showMessage('Sistemul de înregistrare nu este încărcat. Reîncarcă pagina.', 'error');
      return;
    }
    var pass = $('au-reg-pass').value;
    var pass2 = $('au-reg-pass2').value;
    if (!$('au-terms').checked) {
      showMessage('Trebuie să accepți termenii și condițiile.', 'error');
      return;
    }
    if (pass !== pass2) {
      showMessage('Parolele nu coincid.', 'error');
      return;
    }
    if (pass.length < 8) {
      showMessage('Parola trebuie să aibă minim 8 caractere.', 'error');
      return;
    }

    showMessage('');
    setSubmitting(true);
    var val = function (id) { return ($(id).value || '').trim(); };
    var payload;
    var result;
    try {
      if (state.type === 'venue') {
        payload = {
          contact_name: val('au-contact'),
          venue_name: val('au-venue'),
          name: val('au-venue'),
          email: val('au-reg-email'),
          phone: ($('au-venue-phone').value || '').replace(/\s/g, ''),
          city: val('au-venue-city'),
          password: pass,
          password_confirmation: pass2
        };
        result = await BileteOnlineAuth.registerOrganizer(payload);
      } else {
        payload = {
          first_name: val('au-first'),
          last_name: val('au-last'),
          email: val('au-reg-email'),
          phone: ($('au-client-phone').value || '').replace(/\s/g, ''),
          password: pass,
          password_confirmation: pass2,
          newsletter: !!$('au-newsletter').checked
        };
        result = await BileteOnlineAuth.registerCustomer(payload);
      }

      if (result && result.success) {
        showMessage('Cont creat cu succes. Te redirecționăm…', 'success');
        try {
          if (window.EPASTracking && typeof EPASTracking.trackSignUp === 'function') {
            EPASTracking.trackSignUp(state.type === 'venue' ? 'organizer' : 'email', { email: payload.email });
          }
        } catch (err) { /* tracking never breaks signup */ }
        var target = state.type === 'venue' ? '/organizator/panou' : '/verify-email';
        setTimeout(function () { window.location.href = target; }, 1200);
      } else {
        showMessage((result && result.message) || 'Înregistrarea a eșuat.', 'error');
        setSubmitting(false);
      }
    } catch (err) {
      showMessage('Eroare la înregistrare. Încearcă din nou.', 'error');
      setSubmitting(false);
    }
  });

  // ---------- already signed in: skip the form ----------
  function goIfSignedIn() {
    if (hasAuth() && typeof BileteOnlineAuth.isLoggedIn === 'function' && BileteOnlineAuth.isLoggedIn()) {
      var isOrg = BileteOnlineAuth.isOrganizer && BileteOnlineAuth.isOrganizer();
      window.location.replace(isOrg ? '/organizator/panou' : redirectAfter);
      return true;
    }
    return false;
  }
  if (!goIfSignedIn()) {
    window.addEventListener('bileteonline:auth:init', goIfSignedIn);
    window.addEventListener('bileteonline:auth:login', goIfSignedIn);
    setTimeout(goIfSignedIn, 400);
  }

  // ---------- FAQ: one answer open at a time, like before ----------
  var faqs = document.querySelectorAll('.au-faq details');
  faqs.forEach(function (d) {
    d.addEventListener('toggle', function () {
      if (!d.open) return;
      faqs.forEach(function (other) { if (other !== d) other.open = false; });
    });
  });

  render();
})();
