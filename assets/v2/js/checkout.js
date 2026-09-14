/* bilete.online v2: checkout page. Phone pay bar: mirrors the total, pays through CheckoutPage.submit() (which runs the
   same validation as the summary's button) and shows while that button is off screen. The form itself is run by
   assets/js/pages/checkout-page.js. */
(function () {
  'use strict';
  var bar = document.getElementById('co-mbar');
  var total = document.getElementById('summary-total');
  var summary = document.getElementById('summary-section');
  var payBtn = document.getElementById('payBtn');
  var payText = document.getElementById('pay-btn-text');
  if (!bar || !total || !summary || !payBtn || !payText) return;

  var out = bar.querySelector('[data-total]');
  var go = bar.querySelector('[data-pay]');
  var label = go.querySelector('span');
  var phone = window.matchMedia('(max-width: 1023px)');
  var buttonInView = false;

  function busy() {
    return typeof CheckoutPage !== 'undefined' && CheckoutPage.submitting;
  }

  function sync() {
    out.textContent = total.textContent;
    var on = phone.matches && !summary.classList.contains('hidden') && !buttonInView;
    bar.classList.toggle('is-on', on);
    bar.inert = !on;
    bar.setAttribute('aria-hidden', on ? 'false' : 'true');
    document.body.classList.toggle('has-cobar', on);
    go.disabled = busy();
    label.textContent = busy() ? 'Se procesează…' : 'Plătește';
  }

  go.addEventListener('click', function () {
    if (typeof CheckoutPage !== 'undefined') CheckoutPage.submit();
  });

  new MutationObserver(sync).observe(total, { childList: true, characterData: true, subtree: true });
  new MutationObserver(sync).observe(summary, { attributes: true, attributeFilter: ['class'] });
  new MutationObserver(sync).observe(payText, { childList: true, characterData: true, subtree: true });
  new MutationObserver(sync).observe(payBtn, { attributes: true, attributeFilter: ['disabled'] });
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      buttonInView = entries[entries.length - 1].isIntersecting;
      sync();
    }).observe(payBtn);
  }
  if (phone.addEventListener) phone.addEventListener('change', sync);
  else if (phone.addListener) phone.addListener(sync);
  sync();
})();
