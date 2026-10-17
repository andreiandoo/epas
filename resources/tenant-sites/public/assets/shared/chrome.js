/* Elementele comune ale tuturor site-urilor de tenant, fără Alpine (merg la fel în orice
   template): sertarul coșului (se deschide din iconița coșului), pastila cu timerul de
   rezervare cât timp coșul are bilete și bara de derulare transparentă, peste conținut.
   Se încarcă după commerce.js (window.TS) și înaintea lui Alpine. Stilurile: chrome.css. */
(function () {
    'use strict';
    var TS = window.TS;
    if (!TS || window.TSChrome) { return; }
    var t = TS.t, lei = TS.lei;
    var path = location.pathname.replace(/\/+$/, '') || '/';
    // Paginile comenzii au timerul și sumarul lor; acolo nu dublăm nimic
    var ORDER_PAGE = ['/cos', '/finalizare', '/confirmare'].indexOf(path) !== -1;
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function h(html) { var d = document.createElement('div'); d.innerHTML = html.trim(); return d.firstChild; }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function clock(sec) { var m = Math.floor(sec / 60), s = sec % 60; return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s; }
    function secondsLeft(cart) { return cart && cart.expires_at ? Math.max(0, Math.round((cart.expires_at - Date.now()) / 1000)) : 0; }
    function holdTotal(cart) { return ((cart && cart.seats && cart.seats.length) ? TS.SEAT_HOLD_MS : TS.HOLD_MS) / 1000; }
    var ICON_X = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
    var ICON_BAG = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M6 7h12l1 13H5L6 7z"/><path d="M9 7a3 3 0 0 1 6 0"/></svg>';

    /* ---------------- Sertarul coșului */
    var drawer = h(
        '<div class="tsx-drawer" hidden>' +
            '<div class="tsx-drawer__shade" data-close></div>' +
            '<aside class="tsx-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="tsx-dt" tabindex="-1">' +
                '<div class="tsx-drawer__head"><h2 id="tsx-dt">' + esc(t('Coșul tău')) + '</h2>' +
                '<button type="button" class="tsx-x" data-close aria-label="' + esc(t('Închide')) + '">' + ICON_X + '</button></div>' +
                '<div class="tsx-timer" role="timer" aria-live="off" hidden><b>15:00</b><p><span></span><i><span></span></i></p></div>' +
                '<div class="tsx-drawer__body"></div>' +
                '<div class="tsx-drawer__foot" hidden></div>' +
            '</aside>' +
        '</div>'
    );
    var dPanel = drawer.querySelector('.tsx-drawer__panel'), dBody = drawer.querySelector('.tsx-drawer__body'),
        dFoot = drawer.querySelector('.tsx-drawer__foot'), dTimer = drawer.querySelector('.tsx-timer');
    var lastFocus = null, isOpen = false;

    function lineMeta(i) {
        var bits = [];
        if (i.when) { bits.push(i.when); }
        bits.push(lei(i.price) + ' / ' + t('bilet'));
        return bits.join(' · ');
    }

    function renderDrawer() {
        var cart = TS.Cart.get();
        if (!cart) {
            dBody.innerHTML = '<div class="tsx-empty">' + ICON_BAG + '<p>' + esc(t('Coșul tău e gol.')) + '</p>' +
                '<a class="tsx-btn" href="' + esc((window.TS_CFG || {}).eventsUrl || '/') + '">' + esc(t('Vezi evenimentele')) + '</a></div>';
            dFoot.hidden = true; dTimer.hidden = true;
            return;
        }
        var ev = cart.event || {}, html = '';
        html += '<a class="tsx-ev" href="' + esc(ev.url || '#') + '">' + (ev.poster ? '<img src="' + esc(ev.poster) + '" alt="" loading="lazy">' : '') +
            '<span><b>' + esc(ev.title) + '</b><small>' + esc([ev.date, ev.place].filter(Boolean).join(' · ')) + '</small></span></a>';
        html += '<ul class="tsx-lines">';
        cart.items.forEach(function (i) {
            var key = TS.Cart.lineKey(i);
            html += '<li class="tsx-line"><span class="tsx-line__name">' + esc(i.name) + '</span>' +
                '<span class="tsx-line__sum">' + esc(lei(i.price * i.qty)) + '</span>' +
                '<span class="tsx-line__meta">' + esc(lineMeta(i)) + '</span>' +
                '<span class="tsx-line__ctl"><span class="tsx-step">' +
                    '<button type="button" data-step="-1" data-key="' + esc(key) + '" aria-label="' + esc(t('Mai puține')) + '">−</button>' +
                    '<span aria-live="polite">' + i.qty + '</span>' +
                    '<button type="button" data-step="1" data-key="' + esc(key) + '" aria-label="' + esc(t('Mai multe')) + '"' + (i.qty >= TS.MAX_PER_TYPE || i.visit_date ? ' disabled' : '') + '>+</button>' +
                '</span></span></li>';
        });
        cart.seats.forEach(function (s) {
            html += '<li class="tsx-line"><span class="tsx-line__name">' + esc(s.label || (s.section + ' · ' + s.row + ' · ' + s.seat)) + '</span>' +
                '<span class="tsx-line__sum">' + esc(lei(s.price)) + '</span>' +
                '<span class="tsx-line__meta">' + esc(t('Loc numerotat')) + '</span>' +
                '<span class="tsx-line__ctl"><button type="button" class="tsx-rm" data-seat="' + esc(s.seat_uid) + '">' + esc(t('Scoate')) + '</button></span></li>';
        });
        html += '</ul>';
        dBody.innerHTML = html;
        dFoot.hidden = false;
        dFoot.innerHTML = '<div class="tsx-total"><span>' + esc(TS.ticketsLabel(TS.Cart.count(cart))) + '</span><b>' + esc(lei(TS.Cart.subtotal(cart))) + '</b></div>' +
            '<p class="tsx-note">' + esc(t('Reducerile și taxele se calculează la plată.')) + '</p>' +
            '<a class="tsx-btn" href="/finalizare">' + esc(t('Finalizează comanda')) + ' →</a>' +
            '<a class="tsx-btn tsx-btn--ghost" href="/cos">' + esc(t('Vezi coșul')) + '</a>';
        tickTimer();
    }

    dBody.addEventListener('click', function (e) {
        var b = e.target.closest('button');
        if (!b) { return; }
        var cart = TS.Cart.get();
        if (!cart) { return; }
        if (b.hasAttribute('data-step')) {
            var by = +b.getAttribute('data-step'), key = b.getAttribute('data-key');
            cart.items.forEach(function (i) { if (TS.Cart.lineKey(i) === key) { i.qty = Math.max(0, Math.min(TS.MAX_PER_TYPE, i.qty + by)); } });
            TS.Cart.set(cart);
        } else if (b.hasAttribute('data-seat')) {
            var uid = b.getAttribute('data-seat');
            TS.Cart.releaseSeats(cart, [uid]);
            cart.seats = cart.seats.filter(function (s) { return s.seat_uid !== uid; });
            TS.Cart.set(cart);
        }
    });
    drawer.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) { close(); } });
    drawer.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { close(); }
        if (e.key === 'Tab') {
            // Focusul rămâne în sertar cât e deschis
            var f = dPanel.querySelectorAll('a[href],button:not([disabled])');
            if (!f.length) { return; }
            var first = f[0], last = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });

    function lockScroll(on) {
        document.documentElement.style.overflow = on ? 'hidden' : '';
        if (window.tsLenis) { on ? window.tsLenis.stop() : window.tsLenis.start(); }
    }
    function open() {
        if (isOpen) { return; }
        isOpen = true; lastFocus = document.activeElement;
        renderDrawer();
        drawer.hidden = false;
        lockScroll(true);
        void drawer.offsetWidth; // reflow: tranziția pornește din starea închisă
        drawer.classList.add('is-open');
        setTimeout(function () { var x = drawer.querySelector('.tsx-x'); if (x) { x.focus(); } }, reduced ? 0 : 60);
        syncPill();
    }
    function close() {
        if (!isOpen) { return; }
        isOpen = false;
        drawer.classList.remove('is-open');
        lockScroll(false);
        setTimeout(function () { if (!isOpen) { drawer.hidden = true; } }, reduced ? 0 : 420);
        if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
        syncPill();
    }

    // Iconița și linkurile „Coșul meu” deschid sertarul; pe paginile comenzii linkul rămâne normal
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        var a = e.target.closest('a[href="/cos"], [data-ts-cart-open]');
        if (!a || drawer.contains(a) || ORDER_PAGE) { return; }
        e.preventDefault();
        open();
    }, true);
    window.addEventListener('ts:cart-open', open);

    /* ---------------- Pastila cu timerul */
    var pill = h(
        '<button type="button" class="tsx-pill" aria-live="off">' +
            '<svg viewBox="0 0 36 36" aria-hidden="true"><circle cx="18" cy="18" r="15" fill="none" stroke="currentColor" stroke-opacity=".15" stroke-width="3"/>' +
            '<circle cx="18" cy="18" r="15" fill="none" style="stroke:rgb(var(--c-primary))" stroke-width="3" stroke-linecap="round" stroke-dasharray="94.25" stroke-dashoffset="0"/></svg>' +
            '<span><b>15:00</b><br><small></small></span>' +
        '</button>'
    );
    var pillRing = pill.querySelectorAll('circle')[1], pillClock = pill.querySelector('b'), pillText = pill.querySelector('small');
    var goneUntil = 0;
    pill.addEventListener('click', function () { if (TS.Cart.get()) { open(); } else { location.href = (window.TS_CFG || {}).eventsUrl || '/'; } });

    // Contorul coșului din antetele care nu au unul al lor ([data-ts-cart-count])
    function syncCount() {
        var n = TS.Cart.count();
        document.querySelectorAll('[data-ts-cart-count]').forEach(function (el) { el.textContent = n; el.hidden = !n; });
    }

    function syncPill() {
        syncCount();
        if (ORDER_PAGE) { pill.classList.remove('is-on'); return; }
        var cart = TS.Cart.get(), now = Date.now();
        if (!cart) {
            var gone = now < goneUntil;
            pill.classList.toggle('is-gone', gone);
            pill.classList.toggle('is-on', gone && !isOpen);
            if (gone) { pillClock.textContent = '00:00'; pillText.textContent = t('Rezervarea a expirat'); }
            return;
        }
        var left = secondsLeft(cart), total = holdTotal(cart);
        pill.classList.remove('is-gone');
        pill.classList.toggle('is-on', !isOpen);
        pill.classList.toggle('is-low', left <= 120);
        pillClock.textContent = clock(left);
        pillText.textContent = TS.ticketsLabel(TS.Cart.count(cart)) + ' · ' + t('rezervate');
        pillRing.setAttribute('stroke-dashoffset', String(94.25 * (1 - Math.min(1, left / total))));
        pill.setAttribute('aria-label', t('Coșul tău: {n}, rezervate încă {time}', { n: TS.ticketsLabel(TS.Cart.count(cart)), time: clock(left) }));
    }

    // Pastila urcă deasupra barelor fixe de jos ale template-ului (bara de total, bara de cookies …)
    function liftPill() {
        if (!pill.classList.contains('is-on')) { return; }
        var y = window.innerHeight - 6, lift = 0, seen = [];
        [24, Math.min(window.innerWidth - 24, 180)].forEach(function (x) {
            (document.elementsFromPoint ? document.elementsFromPoint(x, y) : []).forEach(function (el) {
                for (var n = el; n && n !== document.body && n !== document.documentElement; n = n.parentElement) {
                    if (n === pill || pill.contains(n) || seen.indexOf(n) !== -1) { return; }
                    var pos = getComputedStyle(n).position;
                    if (pos === 'fixed' || pos === 'sticky') {
                        seen.push(n);
                        var top = n.getBoundingClientRect().top;
                        if (top > window.innerHeight * 0.5) { lift = Math.max(lift, window.innerHeight - top); }
                        return;
                    }
                }
            });
        });
        pill.style.bottom = lift ? 'calc(' + (lift + 12) + 'px + env(safe-area-inset-bottom))' : '';
    }
    window.addEventListener('scroll', function () { requestAnimationFrame(liftPill); }, { passive: true });

    function tickTimer() {
        var cart = TS.Cart.get();
        if (!cart) { dTimer.hidden = true; return; }
        var left = secondsLeft(cart), total = holdTotal(cart);
        dTimer.hidden = false;
        dTimer.classList.toggle('is-low', left <= 120);
        dTimer.querySelector('b').textContent = clock(left);
        dTimer.querySelector('p > span').textContent = left <= 120 ? t('Grăbește-te: rezervarea expiră imediat.') : t('Biletele îți sunt rezervate în acest timp.');
        dTimer.querySelector('i span').style.width = (100 * Math.min(1, left / total)) + '%';
    }

    function everySecond() {
        var c = null;
        try { c = JSON.parse(localStorage.getItem('site_cart') || 'null'); } catch (e) {}
        // Rezervarea tocmai a expirat: Cart.get() golește coșul și eliberează locurile
        // (pe coș și la plată se ocupă timerul paginii, care trimite înapoi la eveniment)
        if (!ORDER_PAGE && c && c.expires_at && c.expires_at <= Date.now()) {
            TS.Cart.get();
            TS.Cart.takeExpired();
            goneUntil = Date.now() + 6000;
            TS.toast(t('Timpul de rezervare a expirat. Alege din nou biletele.'));
            if (isOpen) { renderDrawer(); }
        }
        syncPill();
        liftPill();
        if (isOpen) { tickTimer(); }
    }
    window.addEventListener('site:cart', function () { syncPill(); if (isOpen) { renderDrawer(); } });
    window.addEventListener('storage', function (e) { if (e.key === 'site_cart') { syncPill(); if (isOpen) { renderDrawer(); } } });

    /* ---------------- Bara de derulare peste conținut (doar mouse; pe touch rămâne cea nativă) */
    function overlayScrollbar() {
        if (!window.matchMedia || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) { return; }
        var root = document.documentElement;
        root.classList.add('tsx-sb');
        var bar = h('<div class="tsx-sbar" aria-hidden="true"><div class="tsx-sbar__thumb"></div></div>');
        var thumb = bar.firstChild;
        document.body.appendChild(bar);
        var hideT = null, drag = null, raf = 0, thumbH = 0;

        function metrics() {
            var vh = window.innerHeight, sh = Math.max(root.scrollHeight, document.body.scrollHeight);
            return { vh: vh, sh: sh, max: Math.max(0, sh - vh) };
        }
        function place() {
            raf = 0;
            var m = metrics();
            if (m.max <= 2) { bar.style.display = 'none'; return; }
            bar.style.display = '';
            thumbH = Math.max(40, Math.round(m.vh * m.vh / m.sh));
            var top = Math.round((window.scrollY / m.max) * (m.vh - thumbH - 8)) + 4;
            thumb.style.height = thumbH + 'px';
            thumb.style.transform = 'translateY(' + top + 'px)';
        }
        function show() {
            bar.classList.add('is-on');
            clearTimeout(hideT);
            hideT = setTimeout(function () { if (!drag) { bar.classList.remove('is-on'); } }, 1100);
        }
        function schedule() { if (!raf) { raf = requestAnimationFrame(place); } }
        window.addEventListener('scroll', function () { schedule(); show(); }, { passive: true });
        window.addEventListener('resize', schedule, { passive: true });
        if (window.ResizeObserver) { new ResizeObserver(schedule).observe(document.body); }
        // Apropierea mouse-ului de marginea dreaptă arată bara
        window.addEventListener('mousemove', function (e) { if (window.innerWidth - e.clientX < 16) { show(); } }, { passive: true });

        thumb.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            thumb.setPointerCapture(e.pointerId);
            drag = { y: e.clientY, start: window.scrollY };
            bar.classList.add('is-drag', 'is-on');
            root.classList.add('tsx-dragging');
        });
        thumb.addEventListener('pointermove', function (e) {
            if (!drag) { return; }
            var m = metrics(), track = m.vh - thumbH - 8;
            var y = drag.start + (e.clientY - drag.y) * (track > 0 ? m.max / track : 0);
            if (window.tsLenis) { window.tsLenis.scrollTo(y, { immediate: true }); } else { window.scrollTo(0, y); }
        });
        function endDrag() {
            if (!drag) { return; }
            drag = null;
            bar.classList.remove('is-drag');
            root.classList.remove('tsx-dragging');
            show();
        }
        thumb.addEventListener('pointerup', endDrag);
        thumb.addEventListener('pointercancel', endDrag);
        place();
    }

    function boot() {
        document.body.appendChild(drawer);
        document.body.appendChild(pill);
        overlayScrollbar();
        syncPill();
        setInterval(everySecond, 1000);
    }
    if (document.body) { boot(); } else { document.addEventListener('DOMContentLoaded', boot); }

    window.TSChrome = { open: open, close: close };
    TS.Drawer = window.TSChrome;
})();
