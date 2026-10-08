/* viaqui.com v2: gift card landing. The configurator drives both card previews (value, recipient, design,
   message), shows the delivery date when it is needed, and the two buttons do something honest: "Preview"
   brings the preview into view, "Add to cart" says buying online isn't available yet and opens the contact page
   with the configuration written out. Experiences picked in the finder (/gift-experiences, localStorage
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
  var money = new Intl.NumberFormat(VQ.locale === 'en' ? 'en-GB' : VQ.locale, { maximumFractionDigits: 0 });
  var PICK_KEY = 'bo_gift_pick', PICK_TTL = 6 * 3600 * 1000;
  var pick = null, pickMessage = '';

  function fill(key, value) {
    document.querySelectorAll('[data-gc="' + key + '"]').forEach(function (el) {
      el.textContent = value || el.getAttribute('data-fallback') || '';
    });
  }
  function update() {
    fill('amount', '€' + money.format(Number(amount.value) || 0));
    fill('recipient', recipient.value.trim());
    fill('message', message.value.trim());
    count.textContent = VQ.t('{n}/180 characters', { n: message.value.length });
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
  function lei(cents) { return '€' + money.format(Math.round(cents / 100)); }
  function titles(items) {
    var t = items.map(function (it) { return it.title; });
    return t.length > 1 ? VQ.t('{list} and {last}', { list: t.slice(0, -1).join(', '), last: t[t.length - 1] }) : t[0];
  }
  function showPick() {
    var box = $('gc-picked'), hint = $('gc-finder'), list = $('gc-picked-list');
    box.hidden = !pick;
    hint.hidden = !!pick;
    list.textContent = '';
    if (!pick) return;
    var people = Math.max(1, parseInt(pick.people, 10) || 1);
    $('gc-picked-sum').textContent = [
      pick.who ? VQ.t('For: {who}', { who: pick.who }) : '',
      people > 1 ? VQ.n(people, 'person', 'people') : '',
      pick.cents ? VQ.t('around {amount}', { amount: lei(pick.cents) }) : VQ.t('price to be confirmed')
    ].filter(Boolean).join(' · ');
    pick.items.forEach(function (it) {
      var li = document.createElement('li'), a = document.createElement('a'), small = document.createElement('small');
      // only links inside the site
      a.href = VQ.url(typeof it.href === 'string' && /^\/[a-z0-9-]/.test(it.href) ? it.href : '/gift-experiences');
      a.textContent = it.title;
      small.textContent = [it.city, it.cents ? VQ.t('from {price} per person', { price: lei(it.cents) }) : ''].filter(Boolean).join(' · ');
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
      pickMessage = VQ.t('I picked these for you: {titles}. Choose the day that suits you!', { titles: titles(pick.items) });
      if (pickMessage.length > 180) pickMessage = VQ.t('I picked {n} experiences for you on viaqui.com. Choose the day that suits you!', { n: pick.items.length });
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
    var lines = [VQ.t('I would like a viaqui.com gift card.'), VQ.t('Value: {value}', { value: selectedText(amount) })];
    if (recipient.value.trim()) lines.push(VQ.t('For: {who}', { who: recipient.value.trim() }));
    if (email && email.value.trim()) lines.push(VQ.t('Recipient email: {email}', { email: email.value.trim() }));
    if (delivery.value === 'scheduled' && date && date.value) lines.push(VQ.t('Delivery: {when} ({date})', { when: selectedText(delivery), date: date.value }));
    else lines.push(VQ.t('Delivery: {when}', { when: selectedText(delivery) }));
    lines.push(VQ.t('Design: {design}', { design: selectedText(theme) }));
    if (message.value.trim()) lines.push(VQ.t('Message on the card: {message}', { message: message.value.trim() }));
    if (pick) {
      var chosen = pick.items.map(function (it) { return it.title + (it.city ? ' (' + it.city + ')' : ''); }).join('; ');
      lines.push(pick.people > 1 ? VQ.t('Chosen experiences: {list}, {people}', { list: chosen, people: VQ.n(pick.people, 'person', 'people') }) : VQ.t('Chosen experiences: {list}', { list: chosen }));
    }
    try {
      localStorage.setItem('bo_contact_prefill', JSON.stringify({ v: 1, ts: Date.now(), reason: 'gift', subject: VQ.t('Gift card {value}', { value: selectedText(amount) }), message: lines.join('\n') }));
    } catch (e) {}
  }

  $('gc-add').addEventListener('click', function () {
    msg.textContent = '';
    msg.className = 'gc-msg is-shown';
    // one sentence for the translator; {link} marks where the link goes
    var parts = VQ.t('Buying a gift card online is not available yet. {link}: we fill it in with the card you set up here.').split('{link}');
    msg.appendChild(document.createTextNode(parts[0]));
    var link = document.createElement('a');
    link.href = VQ.url('/contact?motiv=card-cadou');
    link.textContent = VQ.t('Send us your request from the contact page');
    link.addEventListener('click', contactPrefill);
    link.addEventListener('auxclick', contactPrefill); // opened in a new tab
    msg.appendChild(link);
    msg.appendChild(document.createTextNode(parts[1] || ''));
  });

  applyPick();
  update(); // values the browser kept after a reload
})();
