/* Template-ul festival: componentele paginilor și efectele.
   Se încarcă ÎNAINTEA lui Alpine (head.php), deci funcțiile globale de mai jos
   există când Alpine evaluează x-data. Efectele pornesc la `load`, după GSAP
   (ts-motion.js face Lenis + reveal; aici: numărătoare, scântei, galerie
   orizontală, imagine la hover). Totul se oprește la prefers-reduced-motion. */
(function () {
    'use strict';

    var FAV_KEY = 'fest_program';
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function t(s, p) { return window.TS ? window.TS.t(s, p) : s; }

    /* ---------------- „Programul meu”: sloturile favorite, comune lineup / program */
    var Fav = {
        all: function () { try { return JSON.parse(localStorage.getItem(FAV_KEY) || '[]'); } catch (e) { return []; } },
        set: function (list) { try { localStorage.setItem(FAV_KEY, JSON.stringify(list)); } catch (e) {} },
        has: function (id) { return Fav.all().indexOf(id) !== -1; },
        toggle: function (id) {
            var l = Fav.all(), i = l.indexOf(id);
            if (i === -1) { l.push(id); } else { l.splice(i, 1); }
            Fav.set(l);
            return i === -1;
        }
    };

    /* ---------------- Bilete: cantități → coșul comun */
    window.festTickets = function (cfg) {
        var types = cfg.types || [], qty = {};
        types.forEach(function (x) { qty[x.id] = 0; });
        return {
            qty: qty,
            init: function () {
                var cart = window.TS && TS.Cart.get();
                if (cart && cart.event && cart.event.id === cfg.event.id) {
                    var self = this;
                    cart.items.forEach(function (i) { if (i.ticket_type_id in self.qty) { self.qty[i.ticket_type_id] = i.qty; } });
                }
            },
            type: function (id) { return types.filter(function (x) { return x.id === id; })[0]; },
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
                        return { ticket_type_id: x.id, name: x.name, price: x.price, qty: q[x.id], when: x.dateLabel || null };
                    }),
                    expires_at: same ? prev.expires_at : null,
                    coupon_code: same ? (prev.coupon_code || null) : null
                });
                window.location.href = '/cos';
            }
        };
    };

    /* ---------------- Lineup: filtre + fișa artistului */
    window.lineupPage = function (cfg) {
        return {
            artists: cfg.artists, days: cfg.days, stages: cfg.stages,
            day: 0, stage: 0, favOnly: false, cur: null, favs: Fav.all(),
            init: function () {
                var m = location.hash.match(/^#a(\d+)$/), self = this;
                if (m) {
                    var a = this.artists.filter(function (x) { return String(x.id) === m[1]; })[0];
                    if (a) { this.$nextTick(function () { self.open(a); }); }
                }
            },
            get list() {
                var self = this;
                return this.artists.filter(function (a) {
                    return (!self.day || a.day === self.day) && (!self.stage || a.stage === self.stage) && (!self.favOnly || self.favs.indexOf(a.slot) !== -1);
                });
            },
            dayName: function (id) { var d = this.days.filter(function (x) { return x.id === id; })[0]; return d ? d.name : ''; },
            stageName: function (id) { var s = this.stages.filter(function (x) { return x.id === id; })[0]; return s ? s.name : ''; },
            isFav: function (id) { return this.favs.indexOf(id) !== -1; },
            toggleFav: function (id) {
                var on = Fav.toggle(id);
                this.favs = Fav.all();
                if (window.TS) { TS.toast(on ? t('Adăugat în programul tău.') : t('Scos din programul tău.')); }
            },
            open: function (a) {
                this.cur = a;
                history.replaceState(null, '', '#a' + a.id);
                document.documentElement.style.overflow = 'hidden';
            },
            close: function () {
                this.cur = null;
                history.replaceState(null, '', location.pathname + location.search);
                document.documentElement.style.overflow = '';
            }
        };
    };

    /* ---------------- Program: orarul pe scene, rânduri de 15 minute */
    window.programPage = function (cfg) {
        var base = 24 * 60;
        cfg.days.forEach(function (d) { var p = String(d.open || '15:00').split(':'); base = Math.min(base, (+p[0]) * 60 + (+p[1] || 0)); });
        base = Math.floor(base / 60) * 60;
        var mins = function (hm) {
            var p = String(hm).split(':'), m = (+p[0]) * 60 + (+p[1] || 0);
            return m < base ? m + 24 * 60 : m;
        };
        var last = base;
        cfg.slots.forEach(function (s) { last = Math.max(last, mins(s.end)); });
        var span = Math.ceil((last - base) / 60);
        var hours = [];
        for (var i = 0; i < span; i++) { var h = (base / 60 + i) % 24; hours.push({ i: i, label: (h < 10 ? '0' : '') + h + ':00' }); }
        return {
            days: cfg.days, stages: cfg.stages, slots: cfg.slots, hours: hours, rows: span * 4,
            day: cfg.days.length ? cfg.days[0].id : 0, favOnly: false, favs: Fav.all(),
            init: function () {
                var m = location.hash.match(/^#s(\d+)$/);
                if (m) { var el = document.getElementById('s' + m[1]); if (el) { el.scrollIntoView({ behavior: 'smooth', inline: 'center' }); } }
            },
            slotsFor: function (stage) { var d = this.day; return this.slots.filter(function (s) { return s.day === d && s.stage === stage; }); },
            rowOf: function (hm, isEnd) { var r = Math.round((mins(hm) - base) / 15) + 2; return isEnd ? Math.max(r, 3) : r; },
            isFav: function (id) { return this.favs.indexOf(id) !== -1; },
            get favCount() { return this.favs.length; },
            toggleFav: function (id) {
                var on = Fav.toggle(id);
                this.favs = Fav.all();
                if (window.TS) { TS.toast(on ? t('Adăugat în programul tău.') : t('Scos din programul tău.')); }
            }
        };
    };

    /* ---------------- Efecte */
    function countdowns() {
        var els = document.querySelectorAll('[data-countdown]');
        if (!els.length) { return; }
        var tick = function () {
            els.forEach(function (el) {
                var left = Math.max(0, new Date(el.getAttribute('data-countdown')).getTime() - Date.now());
                var parts = { d: Math.floor(left / 864e5), h: Math.floor(left / 36e5) % 24, m: Math.floor(left / 6e4) % 60, s: Math.floor(left / 1e3) % 60 };
                Object.keys(parts).forEach(function (k) {
                    var b = el.querySelector('[data-cd="' + k + '"]');
                    if (b) { b.textContent = (k !== 'd' && parts[k] < 10 ? '0' : '') + parts[k]; }
                });
            });
        };
        tick();
        setInterval(tick, 1000);
    }

    function header() {
        var h = document.querySelector('[data-fx-head]');
        if (!h || h.hasAttribute('data-solid')) { return; }
        var on = function () { h.classList.toggle('is-solid', window.scrollY > 40); };
        on();
        window.addEventListener('scroll', on, { passive: true });
    }

    // Scântei care urcă din partea de jos a hero-ului (canvas simplu, fără bibliotecă)
    function embers() {
        var c = document.querySelector('[data-embers]');
        if (!c || reduced) { return; }
        var ctx = c.getContext('2d'), w, h, dots = [];
        var color = getComputedStyle(document.documentElement).getPropertyValue('--c-primary').trim().split(/\s+/).join(',') || '255,90,54';
        var size = function () { w = c.width = c.offsetWidth; h = c.height = c.offsetHeight; };
        size();
        window.addEventListener('resize', size);
        var n = window.innerWidth < 768 ? 28 : 64;
        for (var i = 0; i < n; i++) { dots.push({ x: Math.random() * w, y: Math.random() * h, r: Math.random() * 1.8 + .4, v: Math.random() * .5 + .15, a: Math.random() }); }
        var visible = true;
        if ('IntersectionObserver' in window) { new IntersectionObserver(function (e) { visible = e[0].isIntersecting; }).observe(c); }
        (function frame() {
            if (visible) {
                ctx.clearRect(0, 0, w, h);
                dots.forEach(function (d) {
                    d.y -= d.v; d.x += Math.sin((d.y + d.a * 100) / 40) * .3; d.a += .01;
                    if (d.y < -10) { d.y = h + 10; d.x = Math.random() * w; }
                    ctx.beginPath(); ctx.arc(d.x, d.y, d.r, 0, 6.283);
                    ctx.fillStyle = 'rgba(' + color + ',' + (.25 + .55 * Math.abs(Math.sin(d.a))) + ')';
                    ctx.fill();
                });
            }
            requestAnimationFrame(frame);
        })();
    }

    // Titlul hero-ului: literele urcă una câte una (GSAP)
    function splitTitle() {
        if (reduced || !window.gsap) { return; }
        document.querySelectorAll('[data-fx-split]').forEach(function (el, li) {
            var txt = el.textContent;
            el.textContent = '';
            Array.prototype.forEach.call(txt, function (ch) {
                var s = document.createElement('span');
                s.className = 'fx-char';
                s.textContent = ch === ' ' ? ' ' : ch;
                el.appendChild(s);
            });
            window.gsap.from(el.children, { yPercent: 110, opacity: 0, duration: .9, ease: 'power4.out', stagger: .035, delay: .15 + li * .18 });
        });
    }

    // Galeria orizontală: fixată la scroll pe desktop, glisabilă pe mobil
    function hscroll() {
        var sec = document.querySelector('[data-fx-hscroll]');
        if (!sec || reduced || !window.gsap || !window.ScrollTrigger || window.innerWidth < 900) { return; }
        var track = sec.querySelector('[data-fx-htrack]');
        var dist = function () { return Math.max(0, track.scrollWidth - window.innerWidth + 48); };
        window.gsap.to(track, {
            x: function () { return -dist(); }, ease: 'none',
            scrollTrigger: { trigger: sec, start: 'top top', end: function () { return '+=' + dist(); }, pin: true, scrub: 1, invalidateOnRefresh: true }
        });
    }

    // Afișul: fotografia artistului urmărește cursorul
    function peek() {
        var img = document.querySelector('[data-fx-peek-img]');
        if (!img || !window.matchMedia('(hover: hover)').matches) { return; }
        document.querySelectorAll('[data-fx-peek]').forEach(function (a) {
            a.addEventListener('mouseenter', function () { var src = a.getAttribute('data-fx-peek'); if (src) { img.src = src; img.classList.add('is-on'); } });
            a.addEventListener('mouseleave', function () { img.classList.remove('is-on'); });
        });
        window.addEventListener('mousemove', function (e) {
            img.style.transform = 'translate(' + (e.clientX + 24) + 'px,' + (e.clientY - 120) + 'px) rotate(-4deg)';
        }, { passive: true });
    }

    document.addEventListener('DOMContentLoaded', function () { countdowns(); header(); peek(); });
    window.addEventListener('load', function () { embers(); splitTitle(); hscroll(); });
})();
