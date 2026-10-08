/* viaqui.com v2: a list that goes on. The next page of cards arrives as the visitor nears the end of the grid.

   The pager stays in the page for visitors without JavaScript and for search engines; here it is hidden and its
   "next" link says where the following page is. Each page is the same address with the next ?page=, so the
   filters travel with it. After AUTO_PAGES pages loaded by scrolling, a button takes over: the sections below
   the list and the footer stay within reach.

   A grid asks for it with data attributes:
     data-more="attractions"            which list it is: picks the texts below (they are written here, whole, so the
                                        catalogue of translations can collect them)
     data-more-grid="#list .grid"       the grid itself, as a selector that finds it in this page and in the next one
     data-more-item=":scope > li"       the cards, from the grid
     data-more-pager="#list .pager"     the pager, from the document
     data-more-note="#list .note"       optional: a line under the grid that a later page may bring, copied once

   Loaded after i18n.js and base.js (see $v2Scripts). */
(function () {
  'use strict';
  var VQ = window.VQ;
  if (!VQ || !window.fetch || !window.DOMParser || !window.URL || !history.replaceState || !('IntersectionObserver' in window)) return;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var AUTO_PAGES = 5;

  var TEXTS = {
    experiences: function () {
      return {
        loading: VQ.t('Loading more experiences…'),
        show: VQ.t('Show more experiences'),
        failed: VQ.t('We could not load more experiences.'),
        added: function (n) { return VQ.t('{count} added to the list.', { count: VQ.n(n, 'more experience', 'more experiences') }); }
      };
    },
    attractions: function () {
      return {
        loading: VQ.t('Loading more attractions…'),
        show: VQ.t('Show more attractions'),
        failed: VQ.t('We could not load more attractions.'),
        added: function (n) { return VQ.t('{count} added to the list.', { count: VQ.n(n, 'more attraction', 'more attractions') }); }
      };
    }
  };

  var sameSite = function (href) {
    try { var u = new URL(href, location.href); u.hash = ''; return u.origin === location.origin ? u : null; } catch (e) { return null; }
  };
  // the link of a card, whether the card is the link or holds it
  var linkOf = function (card) { return card.matches('a[href]') ? card : card.querySelector('a[href]'); };

  function start(grid) {
    var cfg = grid.dataset;
    var text = TEXTS[cfg.more] && TEXTS[cfg.more]();
    var pager = cfg.morePager ? document.querySelector(cfg.morePager) : null;
    if (!text || !pager || !cfg.moreGrid || !cfg.moreItem) return;

    var nextOf = function (scope) {
      var p = scope.querySelector(cfg.morePager);
      var a = p && p.querySelector('a[rel="next"]');
      return a ? sameSite(a.getAttribute('href')) : null;
    };
    var next = nextOf(document);
    var busy = false, failed = false, byHand = false, autoLoaded = 0;
    var seen = {};
    [].forEach.call(grid.querySelectorAll(cfg.moreItem), function (card) {
      var a = linkOf(card);
      if (a) seen[a.getAttribute('href')] = true;
    });

    var more = document.createElement('div');
    more.className = 'cl-more';
    var status = document.createElement('p');
    status.className = 'cl-more-status';
    status.setAttribute('role', 'status');
    status.setAttribute('aria-live', 'polite');
    var said = document.createElement('span');      // what was added: for screen readers only
    said.className = 'sr';
    var line = document.createElement('span');      // what is happening: for everyone
    line.className = 'cl-more-line';
    status.appendChild(said);
    status.appendChild(line);
    var moreBtn = document.createElement('button');
    moreBtn.type = 'button';
    moreBtn.className = 'btn btn-ghost cl-more-btn';
    moreBtn.hidden = true;
    more.appendChild(status);
    more.appendChild(moreBtn);
    pager.parentNode.insertBefore(more, pager);
    pager.hidden = true;

    var io = new IntersectionObserver(function (entries) {
      if (entries.some(function (en) { return en.isIntersecting; }) && !byHand && !failed) loadNext(true);
    }, { rootMargin: '0px 0px 800px 0px' });
    // asks the observer again where the end of the grid is now that it has moved
    var rearm = function () { io.unobserve(more); io.observe(more); };

    var settle = function () {
      more.classList.remove('is-busy');
      if (!next) {
        io.disconnect();
        line.textContent = VQ.t('You have reached the end of the list.');
        moreBtn.hidden = true;
      } else if (failed) {
        line.textContent = text.failed;
        moreBtn.textContent = VQ.t('Load more');
        moreBtn.hidden = false;
      } else if (byHand) {
        line.textContent = '';
        moreBtn.textContent = text.show;
        moreBtn.hidden = false;
      } else {
        line.textContent = '';
        moreBtn.hidden = true;
        rearm();
      }
    };

    var append = function (html, auto) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var theirs = doc.querySelector(cfg.moreGrid);
      var fresh = [];
      // After a press on the button, or when the visitor has caught up with the loading row, the new cards take
      // that row's place on screen. Scroll anchoring would hold the row (or the section under it) still instead
      // and leave the cards above the screen, unseen.
      if (!auto || more.getBoundingClientRect().top < window.innerHeight) {
        document.body.style.overflowAnchor = 'none';
        setTimeout(function () { document.body.style.overflowAnchor = ''; }, 400);
      }
      [].forEach.call(theirs ? theirs.querySelectorAll(cfg.moreItem) : [], function (item) {
        var a = linkOf(item);
        var key = a ? a.getAttribute('href') : '';
        if (key && seen[key]) return;      // a listing that moved between two pages while the visitor was reading
        if (key) seen[key] = true;
        var card = document.importNode(item, true);
        // the same entrance base.js gives the cards that were in the page: hidden, then in, one after the other
        if (!reduce) { card.classList.add('will-reveal'); card.style.setProperty('--i', fresh.length); }
        grid.appendChild(card);
        fresh.push(card);
      });
      if (!reduce && fresh.length) {
        grid.getBoundingClientRect();      // the hidden state is laid out before it is lifted
        setTimeout(function () { fresh.forEach(function (c) { c.classList.add('is-in'); }); }, 40);
      }
      // a line that belongs under the grid (who sells the partner cards): once, from the first page that has it
      if (cfg.moreNote && !document.querySelector(cfg.moreNote)) {
        var note = doc.querySelector(cfg.moreNote);
        if (note) grid.parentNode.insertBefore(document.importNode(note, true), grid.nextSibling);
      }
      // the address follows the last page shown, in the language the visitor is reading (the path is left alone)
      var here = new URL(location.href);
      var page = next.searchParams.get('page');
      if (page) {
        here.searchParams.set('page', page);
        try { history.replaceState(history.state, '', here.pathname + here.search + here.hash); } catch (e) {}
      }
      next = fresh.length ? nextOf(doc) : null;      // a page with nothing new is the end
      said.textContent = fresh.length ? text.added(fresh.length) : '';
      if (auto) { autoLoaded++; if (autoLoaded >= AUTO_PAGES) byHand = true; }
      // a visitor who pressed the button goes on from the first new card, not from a button that moved down the page
      else if (fresh.length) { var firstLink = linkOf(fresh[0]); if (firstLink) firstLink.focus({ preventScroll: true }); }
    };

    var loadNext = function (auto) {
      if (busy || !next) return;
      busy = true;
      more.classList.add('is-busy');
      moreBtn.hidden = true;
      said.textContent = '';
      line.textContent = text.loading;
      var ctl = window.AbortController ? new AbortController() : null;
      var timer = ctl ? setTimeout(function () { ctl.abort(); }, 20000) : 0;
      fetch(next.href, { credentials: 'same-origin', signal: ctl ? ctl.signal : undefined })
        .then(function (res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.text(); })
        .then(function (html) { failed = false; append(html, auto); })
        .catch(function () { failed = true; })
        .then(function () { clearTimeout(timer); busy = false; settle(); });
    };

    moreBtn.addEventListener('click', function () { loadNext(false); });
    if (next) io.observe(more); else settle();
  }

  [].forEach.call(document.querySelectorAll('[data-more]'), start);
})();
