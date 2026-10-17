/* Template-ul gala: componentele paginilor (bilete, luptători) și efectele
   (numărătoare, antet, intrarea luptătorilor în afiș). Se încarcă înaintea lui
   Alpine; efectele pornesc după GSAP. Nimic animat la prefers-reduced-motion. */
(function () {
    'use strict';

    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------------- Bilete pe zone → coșul comun */
    window.galaTickets = function (cfg) {
        var types = cfg.types || [], qty = {};
        types.forEach(function (x) { qty[x.id] = 0; });
        return {
            qty: qty,
            init: function () {
                var cart = window.TS && TS.Cart.get(), self = this;
                if (cart && cart.event && cart.event.id === cfg.event.id) {
                    cart.items.forEach(function (i) { if (i.ticket_type_id in self.qty) { self.qty[i.ticket_type_id] = i.qty; } });
                }
            },
            inc: function (id) { if (this.qty[id] < (window.TS ? TS.MAX_PER_TYPE : 10)) { this.qty[id]++; } },
            dec: function (id) { if (this.qty[id] > 0) { this.qty[id]--; } },
            get count() { var q = this.qty; return types.reduce(function (n, x) { return n + q[x.id]; }, 0); },
            get total() { var q = this.qty; return types.reduce(function (n, x) { return n + q[x.id] * x.price; }, 0); },
            save: function () {
                if (!this.count) { return; }
                var q = this.qty, prev = TS.Cart.get(), same = prev && prev.event && prev.event.id === cfg.event.id && !(prev.seats || []).length;
                TS.Cart.set({
                    event: cfg.event,
                    items: types.filter(function (x) { return q[x.id] > 0; }).map(function (x) {
                        return { ticket_type_id: x.id, name: x.name, price: x.price, qty: q[x.id] };
                    }),
                    expires_at: same ? prev.expires_at : null,
                    coupon_code: same ? (prev.coupon_code || null) : null
                });
                window.location.href = '/cos';
            }
        };
    };

    /* ---------------- Luptătorii: filtru pe categorie + fișa */
    window.fightersPage = function (cfg) {
        return {
            fighters: cfg.fighters, classes: cfg.classes, wc: '', cur: null,
            init: function () {
                var m = location.hash.match(/^#f(\d+)$/), self = this;
                if (m) {
                    var f = this.fighters.filter(function (x) { return String(x.id) === m[1]; })[0];
                    if (f) { this.$nextTick(function () { self.open(f); }); }
                }
            },
            get list() {
                var wc = this.wc;
                return this.fighters.filter(function (f) { return !wc || f.weight_class === wc; })
                    .sort(function (a, b) { return (b.is_champion ? 1 : 0) - (a.is_champion ? 1 : 0); });
            },
            open: function (f) { this.cur = f; history.replaceState(null, '', '#f' + f.id); document.documentElement.style.overflow = 'hidden'; },
            close: function () { this.cur = null; history.replaceState(null, '', location.pathname + location.search); document.documentElement.style.overflow = ''; }
        };
    };

    /* ---------------- Efecte */
    function countdowns() {
        var els = document.querySelectorAll('[data-countdown]');
        if (!els.length) { return; }
        var tick = function () {
            els.forEach(function (el) {
                var left = Math.max(0, new Date(el.getAttribute('data-countdown')).getTime() - Date.now());
                var p = { d: Math.floor(left / 864e5), h: Math.floor(left / 36e5) % 24, m: Math.floor(left / 6e4) % 60, s: Math.floor(left / 1e3) % 60 };
                Object.keys(p).forEach(function (k) {
                    var b = el.querySelector('[data-cd="' + k + '"]');
                    if (b) { b.textContent = (k !== 'd' && p[k] < 10 ? '0' : '') + p[k]; }
                });
            });
        };
        tick();
        setInterval(tick, 1000);
    }

    function header() {
        var h = document.querySelector('[data-gx-head]');
        if (!h || h.hasAttribute('data-solid')) { return; }
        var on = function () { h.classList.toggle('is-solid', window.scrollY > 40); };
        on();
        window.addEventListener('scroll', on, { passive: true });
    }

    // Afișul: luptătorii intră din colțurile lor, „VS” apare la mijloc
    function faceoff() {
        var box = document.querySelector('[data-gx-faceoff]');
        if (!box || reduced || !window.gsap) { return; }
        var g = window.gsap;
        g.from(box.querySelector('[data-gx-side="red"]'), { xPercent: -30, opacity: 0, duration: 1, ease: 'power4.out', delay: .1 });
        g.from(box.querySelector('[data-gx-side="blue"]'), { xPercent: 30, opacity: 0, duration: 1, ease: 'power4.out', delay: .1 });
        g.from(box.querySelector('.gx-faceoff__vs'), { scale: 2.4, opacity: 0, duration: .7, ease: 'back.out(2)', delay: .55 });
        // Ușor parallax la mișcarea mouse-ului
        if (window.matchMedia('(hover: hover)').matches) {
            var sides = box.querySelectorAll('.gx-pic--hero');
            window.addEventListener('mousemove', function (e) {
                var x = (e.clientX / window.innerWidth - .5);
                sides.forEach(function (s, i) { g.to(s, { x: x * (i ? -18 : 18), duration: .6, overwrite: 'auto' }); });
            }, { passive: true });
        }
    }

    document.addEventListener('DOMContentLoaded', function () { countdowns(); header(); });
    window.addEventListener('load', faceoff);
})();
