/* bilete.online v2: single guide. Copy the link (with feedback) and the phone's own share sheet where there is one;
   the recommendations rail is base.js. */
(function () {
  'use strict';
  var status = document.getElementById('gd-copy-status');

  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(text).then(function () { return true; }, function () { return legacyCopy(text); });
    }
    return Promise.resolve(legacyCopy(text));
  }
  function legacyCopy(text) {
    var area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) {}
    area.remove();
    return ok;
  }

  var copy = document.querySelector('[data-copy]');
  if (copy) {
    var label = copy.textContent, timer = null;
    copy.addEventListener('click', function () {
      copyText(copy.getAttribute('data-copy')).then(function (ok) {
        copy.textContent = ok ? 'Copiat ✓' : 'Nu s-a putut copia';
        if (status) status.textContent = ok ? 'Linkul ghidului a fost copiat.' : 'Linkul nu a putut fi copiat.';
        clearTimeout(timer);
        timer = setTimeout(function () { copy.textContent = label; }, 2500);
      });
    });
  }

  var native = document.querySelector('[data-native-share]');
  if (native && navigator.share) {
    native.hidden = false;
    native.addEventListener('click', function () {
      navigator.share({ title: native.getAttribute('data-title'), url: native.getAttribute('data-url') }).catch(function () {});
    });
  }
})();
