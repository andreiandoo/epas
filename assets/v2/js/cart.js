/* bilete.online v2: cart page. Phone checkout bar: mirrors the total and shows while the summary's own button is
   off screen. The head (title, steps, count) follows the cart: hidden while it is empty. Items, totals, promo and timer
   are rendered by assets/js/pages/cart-page.js. */
(function () {
  'use strict';
  var head = document.getElementById('co-head'), emptyCart = document.getElementById('emptyCart'), items = document.getElementById('cartPageItems');
  if (head && emptyCart && items) {
    var syncHead = function () {
      if (!emptyCart.classList.contains('hidden')) head.hidden = true;
      else if (!items.classList.contains('hidden')) head.hidden = false;
    };
    new MutationObserver(syncHead).observe(emptyCart, { attributes: true, attributeFilter: ['class'] });
    new MutationObserver(syncHead).observe(items, { attributes: true, attributeFilter: ['class'] });
    syncHead();
  }

  var bar = document.getElementById('co-mbar');
  var total = document.getElementById('totalPrice');
  var summary = document.getElementById('summary-section');
  var button = document.getElementById('checkoutBtn');
  if (!bar || !total || !summary || !button) return;

  var out = bar.querySelector('[data-total]');
  var phone = window.matchMedia('(max-width: 1023px)');
  var buttonInView = false;

  function sync() {
    out.textContent = total.textContent;
    var on = phone.matches && !summary.classList.contains('hidden') && !buttonInView;
    bar.classList.toggle('is-on', on);
    bar.inert = !on;
    bar.setAttribute('aria-hidden', on ? 'false' : 'true');
    document.body.classList.toggle('has-cobar', on);
  }

  new MutationObserver(sync).observe(total, { childList: true, characterData: true, subtree: true });
  new MutationObserver(sync).observe(summary, { attributes: true, attributeFilter: ['class'] });
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function (entries) {
      buttonInView = entries[entries.length - 1].isIntersecting;
      sync();
    }).observe(button);
  }
  if (phone.addEventListener) phone.addEventListener('change', sync);
  else if (phone.addListener) phone.addListener(sync);
  sync();
})();
