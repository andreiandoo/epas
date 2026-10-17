/* Template-ul artist: componenta biletelor (cantități → coșul comun) și efectele: antetul,
   numărătoarea, intrarea numelui în afiș, fotografia care urmărește mouse-ul pe rândurile
   turneului, vinilul care iese din copertă, videoclipul încărcat doar la click, bara de pe
   mobil. Se încarcă înaintea lui Alpine; animațiile pornesc după GSAP. Nimic animat la
   prefers-reduced-motion. */
(function () {
    'use strict';

    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var hover = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    /* ---------------- Biletele fără loc numerotat → coșul comun (locurile de pe hartă rămân) */
    window.artistTickets = function (cfg) {
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
            max: function (id) {
                var t = types.filter(function (x) { return x.id === id; })[0];
                var cap = window.TS ? TS.MAX_PER_TYPE : 10;
                return t && t.left ? Math.min(cap, t.left) : cap;
            },
            inc: function (id) { if (this.qty[id] < this.max(id)) { this.qty[id]++; } },
            dec: function (id) { if (this.qty[id] > 0) { this.qty[id]--; } },
            get count() { var q = this.qty; return types.reduce(function (n, x) { return n + q[x.id]; }, 0); },
            get total() { var q = this.qty; return types.reduce(function (n, x) { return n + q[x.id] * x.price; }, 0); },
            save: function () {
                if (!this.count) { return; }
                var q = this.qty;
                TS.Cart.merge(cfg.event, {
                    items: types.filter(function (x) { return q[x.id] > 0; }).map(function (x) {
                        return { ticket_type_id: x.id, name: x.name, price: x.price, qty: q[x.id] };
                    })
                });
                // Sertarul coșului arată timerul de rezervare și drumul spre plată
                if (TS.Drawer) { TS.Drawer.open(); } else { window.location.href = '/cos'; }
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

    // Antetul devine opac după afiș
    function header() {
        var h = document.querySelector('[data-ar-head]');
        if (!h || h.hasAttribute('data-solid')) { return; }
        var hero = document.querySelector('.ar-hero, .ar-show');
        var limit = function () { return hero ? Math.max(60, hero.offsetHeight - 90) : 40; };
        var on = function () { h.classList.toggle('is-solid', window.scrollY > limit()); };
        on();
        window.addEventListener('scroll', on, { passive: true });
        window.addEventListener('resize', on, { passive: true });
    }

    // Fotografia concertului urmărește mouse-ul pe rândurile turneului
    function follow() {
        if (!hover || reduced) { return; }
        document.querySelectorAll('[data-ar-rows]').forEach(function (list) {
            var box = list.parentElement && list.parentElement.parentElement && list.parentElement.parentElement.querySelector('[data-ar-follow]');
            if (!box) { return; }
            var img = box.querySelector('img'), x = 0, y = 0, cx = 0, cy = 0, raf = 0, active = false;
            var loop = function () {
                cx += (x - cx) * 0.16; cy += (y - cy) * 0.16;
                box.style.transform = 'translate3d(' + (cx + 28) + 'px,' + (cy - 150) + 'px,0) rotate(' + Math.max(-6, Math.min(6, (x - cx) * 0.05)) + 'deg)';
                raf = active || Math.abs(x - cx) > .5 ? requestAnimationFrame(loop) : 0;
            };
            list.addEventListener('mousemove', function (e) {
                x = e.clientX; y = e.clientY;
                if (!raf) { if (!active) { cx = x; cy = y; } raf = requestAnimationFrame(loop); }
            });
            list.querySelectorAll('.ar-row').forEach(function (row) {
                row.addEventListener('mouseenter', function () {
                    var src = row.getAttribute('data-img');
                    if (!src) { return; }
                    if (img.getAttribute('src') !== src) { img.setAttribute('src', src.replace(/w=\d+/, 'w=600')); }
                    active = true; box.classList.add('is-on');
                });
            });
            list.addEventListener('mouseleave', function () { active = false; box.classList.remove('is-on'); });
        });
    }

    // Videoclipul: iframe-ul YouTube (fără cookies) se încarcă doar la click
    function video() {
        document.querySelectorAll('[data-ar-video]').forEach(function (box) {
            var btn = box.querySelector('.ar-video__play');
            if (!btn) { return; }
            btn.addEventListener('click', function () {
                var id = box.getAttribute('data-ar-video');
                var f = document.createElement('iframe');
                f.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0';
                f.title = btn.getAttribute('aria-label') || 'Video';
                f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
                f.allowFullscreen = true;
                box.appendChild(f);
                box.classList.add('is-playing');
                btn.remove();
            });
        });
    }

    // Bara „Bilete de la” de pe mobil dispare cât biletele sunt pe ecran
    function mobileBar() {
        var bar = document.querySelector('[data-ar-mbar]'), tix = document.getElementById('bilete');
        if (!bar || !tix || !window.IntersectionObserver) { return; }
        new IntersectionObserver(function (en) { bar.classList.toggle('is-hidden', en[0].isIntersecting); }, { rootMargin: '0px 0px -30% 0px' }).observe(tix);
    }

    // Afișul: cuvintele numelui urcă din spatele liniei, restul apare după
    function heroIn() {
        var g = window.gsap;
        if (!g || reduced) { return; }
        var words = document.querySelectorAll('[data-ar-word]');
        if (words.length) {
            g.from(words, { yPercent: 110, duration: 1.15, ease: 'power4.out', stagger: .12, delay: .05 });
        }
        var ins = document.querySelectorAll('[data-ar-in]');
        if (ins.length) {
            g.from(ins, { y: 24, opacity: 0, duration: .9, ease: 'power3.out', stagger: .1, delay: .45 });
        }
    }

    // Vinilul iese din copertă și se rotește cât derulezi prin secțiunea albumului
    function vinyl() {
        var g = window.gsap, box = document.querySelector('[data-ar-vinyl]');
        if (!g || !box || reduced || !window.ScrollTrigger) { return; }
        var disc = box.querySelector('.ar-vinyl');
        // Poziția din CSS (pentru fără animații) lasă locul celei din GSAP, altfel s-ar aduna
        disc.style.transform = 'none';
        g.fromTo(disc, { xPercent: 4, rotate: 0 }, {
            xPercent: 46, rotate: 240, ease: 'none',
            scrollTrigger: { trigger: box, start: 'top 85%', end: 'bottom 25%', scrub: .6 }
        });
    }

    document.addEventListener('DOMContentLoaded', function () { countdowns(); header(); follow(); video(); mobileBar(); });
    window.addEventListener('load', function () { heroIn(); vinyl(); });
})();
