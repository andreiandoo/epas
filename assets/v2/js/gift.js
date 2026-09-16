/* bilete.online v2: gift card landing. The configurator drives both card previews (value, recipient, design,
   message), shows the delivery date when it is needed, and the two buttons do something honest: "Previzualizează"
   brings the preview into view, "Adaugă în coș" says buying online isn't available yet and opens the contact page
   with the configuration written out. Experiences picked in the finder (/experiente-cadou, localStorage
   bo_gift_pick) set the value, fill an empty message and are listed above the fields. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var form = $('gc-form');
  if (!form) return;

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var amount = $('gc-amount'), recipient = $('gc-recipient'), delivery = $('gc-delivery'), theme = $('gc-theme');
  var message = $('gc-message'), count = $('gc-count'), dateField = $('gc-date-field'), preview = $('gc-preview'), msg = $('gc-msg');
  var email = $('gc-email'), date = $('gc-date');
  var money = new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 0 });
  var PICK_KEY = 'bo_gift_pick', PICK_TTL = 6 * 3600 * 1000;
  var pick = null, pickMessage = '';

  function fill(key, value) {
    document.querySelectorAll('[data-gc="' + key + '"]').forEach(function (el) {
      el.textContent = value || el.getAttribute('data-fallback') || '';
    });
  }
  function update() {
    fill('amount', money.format(Number(amount.value) || 0) + ' RON');
    fill('recipient', recipient.value.trim());
    fill('message', message.value.trim());
    count.textContent = message.value.length + '/180 caractere';
    document.querySelectorAll('.gc-card[data-theme]').forEach(function (card) { card.setAttribute('data-theme', theme.value); });
    dateField.hidden = delivery.value !== 'scheduled';
  }
  function selectedText(select) {
    var o = select.options[select.selectedIndex];
    return o ? o.textContent.trim() : '';
  }

  // ------------------------------------------------------------ the pick from the finder
  function readPick() {
    try {
      var p = JSON.parse(localStorage.getItem(PICK_KEY) || 'null');
      if (!p || p.v !== 1 || !Array.isArray(p.items) || !p.items.length || Date.now() - Number(p.ts) > PICK_TTL) return null;
      p.items = p.items.filter(function (it) { return it && typeof it.title === 'string' && it.title; }).slice(0, 12);
      return p.items.length ? p : null;
    } catch (e) { return null; }
  }
  function lei(cents) { return money.format(Math.round(cents / 100)) + ' lei'; }
  function titles(items) {
    var t = items.map(function (it) { return it.title; });
    return t.length > 1 ? t.slice(0, -1).join(', ') + ' și ' + t[t.length - 1] : t[0];
  }
  function showPick() {
    var box = $('gc-picked'), hint = $('gc-finder'), list = $('gc-picked-list');
    box.hidden = !pick;
    hint.hidden = !!pick;
    list.textContent = '';
    if (!pick) return;
    var people = Math.max(1, parseInt(pick.people, 10) || 1);
    $('gc-picked-sum').textContent = (pick.who ? 'Pentru: ' + pick.who + ' · ' : '') + (people > 1 ? people + ' persoane · ' : '') + (pick.cents ? 'în jur de ' + lei(pick.cents) : 'preț de confirmat');
    pick.items.forEach(function (it) {
      var li = document.createElement('li'), a = document.createElement('a'), small = document.createElement('small');
      // only links inside the site
      a.href = typeof it.href === 'string' && /^\/[a-z0-9-]/.test(it.href) ? it.href : '/experiente-cadou';
      a.textContent = it.title;
      small.textContent = [it.city, it.cents ? 'de la ' + lei(it.cents) + ' / pers.' : ''].filter(Boolean).join(' · ');
      li.appendChild(a);
      li.appendChild(small);
      list.appendChild(li);
    });
  }
  function applyPick() {
    pick = readPick();
    showPick();
    if (!pick) return;
    var value = String(pick.value);
    if ([].some.call(amount.options, function (o) { return o.value === value; })) amount.value = value;
    if (!message.value.trim()) {
      pickMessage = 'Ți-am ales: ' + titles(pick.items) + '. Alege ziua care ți se potrivește!';
      if (pickMessage.length > 180) pickMessage = 'Ți-am ales ' + pick.items.length + ' experiențe pe bilete.online. Alege ziua care ți se potrivește!';
      message.value = pickMessage;
    }
  }
  $('gc-picked-clear').addEventListener('click', function () {
    try { localStorage.removeItem(PICK_KEY); } catch (e) {}
    if (pickMessage && message.value === pickMessage) message.value = '';
    pick = null;
    pickMessage = '';
    showPick();
    update();
    $('gc-finder').querySelector('a').focus();
  });

  // ------------------------------------------------------------ the form
  [amount, delivery, theme].forEach(function (el) { el.addEventListener('change', update); });
  [recipient, message].forEach(function (el) { el.addEventListener('input', update); });
  form.addEventListener('submit', function (e) { e.preventDefault(); });

  $('gc-show').addEventListener('click', function () {
    preview.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
    preview.focus({ preventScroll: true });
  });

  // the contact page picks this up (contact.js) so the request arrives with everything chosen here
  function contactPrefill() {
    var lines = ['Aș dori un card cadou bilete.online.', 'Valoare: ' + selectedText(amount)];
    if (recipient.value.trim()) lines.push('Pentru: ' + recipient.value.trim());
    if (email && email.value.trim()) lines.push('Email destinatar: ' + email.value.trim());
    lines.push('Trimitere: ' + selectedText(delivery) + (delivery.value === 'scheduled' && date && date.value ? ' (' + date.value + ')' : ''));
    lines.push('Design: ' + selectedText(theme));
    if (message.value.trim()) lines.push('Mesaj pe card: ' + message.value.trim());
    if (pick) {
      lines.push('Experiențe alese: ' + pick.items.map(function (it) { return it.title + (it.city ? ' (' + it.city + ')' : ''); }).join('; ') + (pick.people > 1 ? ' — ' + pick.people + ' persoane' : ''));
    }
    try {
      localStorage.setItem('bo_contact_prefill', JSON.stringify({ v: 1, ts: Date.now(), reason: 'gift', subject: 'Card cadou ' + selectedText(amount), message: lines.join('\n') }));
    } catch (e) {}
  }

  $('gc-add').addEventListener('click', function () {
    msg.textContent = '';
    msg.className = 'gc-msg is-shown';
    msg.appendChild(document.createTextNode('Comanda online a cardului cadou nu este disponibilă încă. '));
    var link = document.createElement('a');
    link.href = '/contact?motiv=card-cadou';
    link.textContent = 'Trimite-ne cererea din pagina de contact';
    link.addEventListener('click', contactPrefill);
    link.addEventListener('auxclick', contactPrefill); // opened in a new tab
    msg.appendChild(link);
    msg.appendChild(document.createTextNode(' — o completăm cu cardul configurat aici.'));
  });

  applyPick();
  update(); // values the browser kept after a reload
})();
