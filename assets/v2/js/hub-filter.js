/* bilete.online v2: the filter bar of a listing hub (/experiente, /atractii and their /{oras}/… versions).
   Progressive enhancement only — the form is plain GET and every state has its own URL, so with this file missing
   the filter still works, just with native selects and an "Arată rezultatele" button.

   What it adds:
     · a change to any field applies straight away (the submit button hides itself on wide screens)
     · the long lists (Oraș) become a dropdown you type into, diacritics and all
     · under 768px the fields fold behind the "Filtre" button, which counts what is in force */
(function () {
  'use strict';
  var form = document.querySelector('[data-hub-filter]');
  if (!form) return;

  /* "Târgu Mureș" and "targu mures" are the same thing to someone typing in a hurry. */
  function fold(s) {
    return String(s == null ? '' : s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
  }
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }
  function icon(name) {
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'ic');
    svg.setAttribute('aria-hidden', 'true');
    var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    use.setAttribute('href', '#i-' + name);
    svg.appendChild(use);
    return svg;
  }

  var submitting = false;
  /* The form posts to the list's own address without a page number, so a new filter always starts at page 1. */
  function apply() {
    if (submitting) return;
    submitting = true;
    if (form.requestSubmit) form.requestSubmit(); else form.submit();
  }

  // ------------------------------------------------------------------ fields apply themselves
  [].slice.call(form.querySelectorAll('.hf-fields select, .hf-fields input[type="date"]')).forEach(function (f) {
    f.addEventListener('change', apply);
  });

  // ------------------------------------------------------------------ "Filtre" on narrow screens
  var toggle = form.querySelector('[data-hf-toggle]');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var open = form.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // ------------------------------------------------------------------ type-to-find over a long <select>
  /* The native select stays in the form and keeps holding the value — the input in front of it is only a way of
     picking one. That way nothing breaks if this code throws halfway, and the value always submits. */
  [].slice.call(form.querySelectorAll('[data-hf-find]')).forEach(function (ctl) {
    var select = ctl.querySelector('select');
    if (!select || select.options.length < 8) return;

    var options = [];                                  // [value, label, groupLabel]
    [].slice.call(select.children).forEach(function (child) {
      if (child.tagName === 'OPTGROUP') {
        var g = child.label;
        [].slice.call(child.children).forEach(function (o) { options.push([o.value, o.textContent, g]); });
      } else {
        options.push([child.value, child.textContent, '']);
      }
    });
    var labelOf = function (v) {
      for (var i = 0; i < options.length; i++) if (options[i][0] === v) return options[i][1];
      return '';
    };

    var id = select.id + '-find';
    var listId = id + '-list';
    var wrap = el('span', 'hf-find');
    var input = el('input', 'hf-find-in');
    input.type = 'text';
    input.id = id;
    input.autocomplete = 'off';
    input.spellcheck = false;
    input.placeholder = ctl.getAttribute('data-hf-find') || 'Caută în listă…';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');
    input.setAttribute('aria-controls', listId);
    input.setAttribute('aria-autocomplete', 'list');
    input.value = labelOf(select.value);
    var list = el('ul', 'hf-find-list');
    list.id = listId;
    list.hidden = true;
    list.setAttribute('role', 'listbox');
    wrap.appendChild(input);
    wrap.appendChild(icon('caret-down'));
    wrap.appendChild(list);

    var label = ctl.parentNode.querySelector('label[for="' + select.id + '"]');
    if (label) label.setAttribute('for', id);
    ctl.parentNode.insertBefore(wrap, ctl);
    ctl.hidden = true;                                 // the select keeps the value, out of sight
    ctl.style.display = 'none';

    var shown = [], active = -1, open = false;

    function rows(q) {
      q = fold(q);
      if (!q) return options.slice(0);
      return options.filter(function (o) { return fold(o[1]).indexOf(q) >= 0; });
    }
    function draw() {
      list.textContent = '';
      if (!shown.length) {
        list.appendChild(el('li', 'hf-find-none', 'Nimic găsit. Șterge din text și încearcă altfel.'));
        return;
      }
      var group = null;
      shown.forEach(function (o, i) {
        if (o[2] && o[2] !== group) {
          group = o[2];
          var g = el('li', 'hf-find-opt is-group', group);
          g.setAttribute('aria-hidden', 'true');
          list.appendChild(g);
        }
        var li = el('li', 'hf-find-opt' + (i === active ? ' is-active' : ''), o[1]);
        li.id = listId + '-' + i;
        li.setAttribute('role', 'option');
        li.setAttribute('aria-selected', o[0] === select.value ? 'true' : 'false');
        li.addEventListener('mousedown', function (e) { e.preventDefault(); pick(o[0]); });
        list.appendChild(li);
      });
      input.setAttribute('aria-activedescendant', active >= 0 ? listId + '-' + active : '');
    }
    function scrollActive() {
      var n = list.querySelector('.is-active');
      if (!n) return;
      if (n.offsetTop < list.scrollTop) list.scrollTop = n.offsetTop;
      else if (n.offsetTop + n.offsetHeight > list.scrollTop + list.clientHeight) list.scrollTop = n.offsetTop + n.offsetHeight - list.clientHeight;
    }
    function show(q) {
      shown = rows(q);
      active = -1;
      for (var i = 0; i < shown.length; i++) if (shown[i][0] === select.value) { active = i; break; }
      if (active < 0 && shown.length) active = 0;
      open = true;
      list.hidden = false;
      wrap.classList.add('is-open');
      input.setAttribute('aria-expanded', 'true');
      draw();
      scrollActive();
    }
    function hide() {
      open = false;
      list.hidden = true;
      wrap.classList.remove('is-open');
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      input.value = labelOf(select.value);
    }
    function pick(v) {
      select.value = v;
      hide();
      apply();
    }
    function move(step) {
      if (!open) { show(''); return; }
      if (!shown.length) return;
      active = (active + step + shown.length) % shown.length;
      draw();
      scrollActive();
    }

    input.addEventListener('focus', function () { input.select(); });
    input.addEventListener('mousedown', function () { if (!open) setTimeout(function () { show(''); }, 0); });
    input.addEventListener('input', function () { show(input.value); });
    input.addEventListener('blur', function () { setTimeout(function () { if (open) hide(); }, 120); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
      else if (e.key === 'Home' && open) { e.preventDefault(); active = 0; draw(); scrollActive(); }
      else if (e.key === 'End' && open) { e.preventDefault(); active = shown.length - 1; draw(); scrollActive(); }
      else if (e.key === 'Enter') { if (open) { e.preventDefault(); if (active >= 0) pick(shown[active][0]); else hide(); } }
      else if (e.key === 'Escape') { if (open) { e.preventDefault(); e.stopPropagation(); hide(); } }
      else if (e.key === 'Tab') { if (open) hide(); }
    });
  });
})();
