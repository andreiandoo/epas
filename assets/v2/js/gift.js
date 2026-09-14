/* bilete.online v2: gift card landing. The configurator drives both card previews (value, recipient, design,
   message), shows the delivery date when it is needed, and the two buttons do something honest: "Previzualizează"
   brings the preview into view, "Adaugă în coș" says buying online isn't available yet. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var form = $('gc-form');
  if (!form) return;

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var amount = $('gc-amount'), recipient = $('gc-recipient'), delivery = $('gc-delivery'), theme = $('gc-theme');
  var message = $('gc-message'), count = $('gc-count'), dateField = $('gc-date-field'), preview = $('gc-preview'), msg = $('gc-msg');
  var money = new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 0 });

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

  [amount, delivery, theme].forEach(function (el) { el.addEventListener('change', update); });
  [recipient, message].forEach(function (el) { el.addEventListener('input', update); });
  form.addEventListener('submit', function (e) { e.preventDefault(); });

  $('gc-show').addEventListener('click', function () {
    preview.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
    preview.focus({ preventScroll: true });
  });

  $('gc-add').addEventListener('click', function () {
    msg.textContent = '';
    msg.className = 'gc-msg is-shown';
    msg.appendChild(document.createTextNode('Comanda online a cardului cadou nu este disponibilă încă. '));
    var link = document.createElement('a');
    link.href = '/contact';
    link.textContent = 'Scrie-ne din pagina de contact';
    msg.appendChild(link);
    msg.appendChild(document.createTextNode(' pentru un card cadou.'));
  });

  update(); // values the browser kept after a reload
})();
