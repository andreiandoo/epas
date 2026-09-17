/* bilete.online v2: /cauta. The filters are checkboxes: on large screens a change applies at once, on phones they
   wait for "Arată rezultatele" (city, category and price keep one value each). The city list has its own search.
   "Alte date" opens a month calendar of links (today to +90 days); phones sort from a select next to "Filtre". */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var wide = window.matchMedia('(min-width: 1024px)');

  function fold(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
  }
  function go(params) {
    var q = [];
    Object.keys(params).forEach(function (k) {
      if (params[k] !== '' && params[k] != null) q.push(encodeURIComponent(k) + '=' + encodeURIComponent(params[k]).replace(/%2C/g, ','));
    });
    window.location.href = '/cauta' + (q.length ? '?' + q.join('&') : '');
  }

  /* ---------- phones: the filter panel and the sort select ---------- */
  var toggle = document.querySelector('.sr-filter-toggle'), body = $('sr-filter-body');
  if (toggle && body) {
    toggle.addEventListener('click', function () {
      var open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(open));
      body.classList.toggle('is-open', open);
    });
  }
  var sortSel = $('sr-sort-m');
  if (sortSel && sortSel.form) {
    sortSel.addEventListener('change', function () {
      var params = {};
      [].forEach.call(sortSel.form.querySelectorAll('input[type="hidden"]'), function (i) { params[i.name] = i.value; });
      params.sort = sortSel.value;
      go(params);
    });
  }

  /* ---------- filters ---------- */
  function filterParams() {
    var params = {};
    [].forEach.call(body.querySelectorAll('input[type="hidden"]'), function (i) { params[i.name] = i.value; });
    [].forEach.call(body.querySelectorAll('[data-group]'), function (g) {
      var values = [].map.call(g.querySelectorAll('input[type="checkbox"]:checked'), function (i) { return i.value; });
      params[g.getAttribute('data-group')] = values.join(',');
    });
    return params;
  }
  if (body) {
    body.addEventListener('change', function (e) {
      var box = e.target;
      if (!box.matches || !box.matches('input[type="checkbox"]')) return;
      var group = box.closest('[data-group]');
      if (box.checked && group && group.hasAttribute('data-single')) {
        [].forEach.call(group.querySelectorAll('input[type="checkbox"]'), function (other) { if (other !== box) other.checked = false; });
      }
      if (wide.matches) go(filterParams());
    });
    body.addEventListener('submit', function (e) {
      e.preventDefault();
      go(filterParams());
    });
  }

  /* ---------- city search inside the filter ---------- */
  var cityQ = $('sr-city-q'), cityList = $('sr-city-list'), cityMore = $('sr-city-more'), cityNone = $('sr-city-none');
  if (cityQ && cityList) {
    var rows = [].slice.call(cityList.children), all = false;
    var showCities = function () {
      var q = fold(cityQ.value), shown = 0;
      rows.forEach(function (li) {
        var checked = li.querySelector('input').checked;
        var hit = q ? li.getAttribute('data-q').indexOf(q) !== -1 : (all || !li.classList.contains('is-extra') || checked);
        li.hidden = !hit;
        if (hit) shown++;
      });
      cityList.classList.toggle('is-scroll', !!q || all);
      if (cityNone) cityNone.hidden = shown > 0;
      if (cityMore) cityMore.hidden = !!q || all;
    };
    cityQ.addEventListener('input', showCities);
    cityQ.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); }); // never submits the filters
    if (cityMore) {
      cityMore.addEventListener('click', function () {
        all = true;
        cityMore.setAttribute('aria-expanded', 'true');
        showCities();
        var first = rows.filter(function (li) { return li.classList.contains('is-extra'); })[0];
        if (first) first.querySelector('input').focus();
      });
    }
    showCities();
  }

  /* ---------- "Alte date": month calendar ---------- */
  var calBtn = $('sr-cal-btn'), cal = $('sr-cal'), grid = $('sr-cal-grid');
  if (calBtn && cal && grid) {
    var MONTHS = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
    var parse = function (iso) { var p = iso.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); };
    var iso = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };
    var min = parse(cal.getAttribute('data-min')), max = parse(cal.getAttribute('data-max'));
    var selected = cal.getAttribute('data-selected') || '';
    var href = cal.getAttribute('data-href');
    var view = selected ? parse(selected) : new Date(min);
    view.setDate(1);
    var focusIso = null;

    var render = function () {
      var y = view.getFullYear(), m = view.getMonth();
      $('sr-cal-title').textContent = MONTHS[m] + ' ' + y;
      var prev = cal.querySelector('[data-cal-step="-1"]'), next = cal.querySelector('[data-cal-step="1"]');
      prev.disabled = y * 12 + m <= min.getFullYear() * 12 + min.getMonth();
      next.disabled = y * 12 + m >= max.getFullYear() * 12 + max.getMonth();
      // a month button that just switched itself off would drop the focus out of the calendar
      if (document.activeElement === prev && prev.disabled && !next.disabled) next.focus();
      if (document.activeElement === next && next.disabled && !prev.disabled) prev.focus();
      grid.textContent = '';
      var lead = (new Date(y, m, 1).getDay() + 6) % 7; // weeks start on Monday
      for (var i = 0; i < lead; i++) grid.appendChild(document.createElement('span'));
      var days = new Date(y, m + 1, 0).getDate(), todayIso = iso(min);
      for (var d = 1; d <= days; d++) {
        var date = new Date(y, m, d), key = iso(date), cell;
        var label = d + ' ' + MONTHS[m] + ' ' + y;
        if (date < min || date > max) {
          cell = document.createElement('span');
          cell.className = 'sr-cal-day is-off';
          cell.setAttribute('aria-disabled', 'true');
        } else {
          cell = document.createElement('a');
          cell.className = 'sr-cal-day';
          cell.href = href.replace('0000-00-00', key);
          cell.setAttribute('data-date', key);
          if (key === selected) cell.setAttribute('aria-current', 'date');
          if (key === todayIso) { cell.classList.add('is-today'); label = 'azi, ' + label; }
        }
        cell.textContent = d;
        cell.setAttribute('aria-label', label);
        grid.appendChild(cell);
      }
      if (focusIso) {
        var f = grid.querySelector('[data-date="' + focusIso + '"]');
        if (f) f.focus();
        focusIso = null;
      }
    };
    var open = function () {
      cal.hidden = false;
      calBtn.setAttribute('aria-expanded', 'true');
      render();
      var first = grid.querySelector('[aria-current="date"]') || grid.querySelector('a.sr-cal-day');
      if (first) first.focus();
    };
    var close = function (focusBtn) {
      if (cal.hidden) return;
      cal.hidden = true;
      calBtn.setAttribute('aria-expanded', 'false');
      if (focusBtn) calBtn.focus();
    };
    calBtn.addEventListener('click', function () { if (cal.hidden) open(); else close(true); });
    cal.addEventListener('click', function (e) {
      var step = e.target.closest('[data-cal-step]');
      if (step && !step.disabled) {
        view.setMonth(view.getMonth() + Number(step.getAttribute('data-cal-step')));
        render();
      }
      if (e.target.closest('[data-cal-close]')) close(true);
    });
    // arrows move between days (and months), Escape closes
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !cal.hidden) { e.preventDefault(); close(true); }
    });
    cal.addEventListener('keydown', function (e) {
      var moves = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
      var day = e.target.closest && e.target.closest('a.sr-cal-day');
      if (!day || !(e.key in moves)) return;
      e.preventDefault();
      var next = parse(day.getAttribute('data-date'));
      next.setDate(next.getDate() + moves[e.key]);
      if (next < min || next > max) return;
      focusIso = iso(next);
      if (next.getMonth() !== view.getMonth() || next.getFullYear() !== view.getFullYear()) {
        view = new Date(next.getFullYear(), next.getMonth(), 1);
        render();
      } else {
        grid.querySelector('[data-date="' + focusIso + '"]').focus();
        focusIso = null;
      }
    });
    document.addEventListener('click', function (e) {
      if (!cal.hidden && !cal.contains(e.target) && !calBtn.contains(e.target)) close(false);
    });
  }

  /* without JavaScript the native date field submits its own form; with it, the calendar above replaces it */
  var date = $('sr-date');
  if (date && date.form) {
    date.addEventListener('change', function () { if (date.value) date.form.submit(); });
  }
})();
