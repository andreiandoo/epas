/* Template-ul agency: componentele paginilor (bilete → coșul comun, rosterul cu filtre,
   formularul de booking) și efectele: antetul, numărătoarea, fotografia care urmărește
   mouse-ul pe rândurile evenimentelor, cardurile care se înclină, videoclipul încărcat la
   click, bara de pe mobil. Se încarcă înaintea lui Alpine. Fără animații la prefers-reduced-motion. */
(function () {
    'use strict';

    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var hover = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    /* ---------------- Biletele fără loc numerotat → coșul comun (locurile de pe hartă rămân) */
    window.agencyTickets = function (cfg) {
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

    /* ---------------- Rosterul: căutare + filtre (tip, gen, teritoriu), ținute în adresă */
    window.rosterPage = function (cfg) {
        var p = new URLSearchParams(location.search);
        var norm = function (s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); };
        return {
            cards: cfg.cards || [], q: p.get('q') || '', kind: p.get('tip') || '', genre: p.get('gen') || '', territory: p.get('teritoriu') || '',
            init: function () {
                var self = this;
                ['q', 'kind', 'genre', 'territory'].forEach(function (k) { self.$watch(k, function () { self.sync(); }); });
            },
            get list() {
                var q = norm(this.q), kind = this.kind, genre = this.genre, terr = this.territory;
                return this.cards.filter(function (c) {
                    if (kind && c.kind !== kind) { return false; }
                    if (genre && c.genres.indexOf(genre) === -1) { return false; }
                    if (terr && c.territories.indexOf(terr) === -1) { return false; }
                    if (q && norm(c.name + ' ' + c.genres.join(' ') + ' ' + c.city).indexOf(q) === -1) { return false; }
                    return true;
                });
            },
            sync: function () {
                var u = new URLSearchParams();
                if (this.q) { u.set('q', this.q); }
                if (this.kind) { u.set('tip', this.kind); }
                if (this.genre) { u.set('gen', this.genre); }
                if (this.territory) { u.set('teritoriu', this.territory); }
                var lang = new URLSearchParams(location.search).get('lang');
                if (lang) { u.set('lang', lang); }
                history.replaceState(null, '', location.pathname + (u.toString() ? '?' + u.toString() : ''));
            },
            reset: function () { this.q = ''; this.kind = ''; this.genre = ''; this.territory = ''; }
        };
    };

    /* ---------------- Cererea de booking → core (agenția o primește pe email și în panou) */
    window.bookingForm = function (cfg) {
        var t = function (s) { return window.TS ? TS.t(s) : s; };
        return {
            f: { artist: cfg.artist || '', name: '', company: '', email: '', phone: '', event_type: '', event_date: '', city: '', venue: '', capacity: '', budget: '', message: '', consent: false },
            busy: false, done: false, error: '', tried: false,
            today: new Date().toISOString().slice(0, 10),
            bad: function (k) {
                if (!this.tried) { return false; }
                if (k === 'email') { return !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(this.f.email.trim()); }
                return !String(this.f[k] || '').trim();
            },
            submit: async function () {
                this.tried = true; this.error = '';
                if (this.bad('name') || this.bad('email')) { this.error = t('Completează numele și o adresă de email corectă.'); return; }
                if (!this.f.consent) { this.error = t('Bifează acordul ca să putem răspunde cererii.'); return; }
                this.busy = true;
                var body = {};
                var f = this.f;
                Object.keys(f).forEach(function (k) { if (f[k] !== '' && f[k] !== null) { body[k] = f[k]; } });
                body.capacity = f.capacity ? parseInt(f.capacity, 10) : undefined;
                body.locale = window.TS ? TS.locale() : 'ro';
                body.consent = true;
                try {
                    var r = await fetch('/api/proxy.php?action=booking-request', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
                    var d = await r.json().catch(function () { return {}; });
                    if (r.ok && d.success) { this.done = true; }
                    else if (r.status === 429) { this.error = t('Prea multe încercări. Mai încearcă peste un minut.'); }
                    else if (r.status === 422) { this.error = t('Verifică datele: data evenimentului nu poate fi în trecut, iar emailul trebuie să fie corect.'); }
                    else { this.error = t('Cererea nu a putut fi trimisă. Încearcă din nou.'); }
                } catch (e) { this.error = t('Conexiunea a eșuat. Încearcă din nou.'); }
                this.busy = false;
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
        var h = document.querySelector('[data-ag-head]');
        if (!h || h.hasAttribute('data-solid')) { return; }
        var on = function () { h.classList.toggle('is-solid', window.scrollY > 60); };
        on();
        window.addEventListener('scroll', on, { passive: true });
    }

    // Fotografia concertului urmărește mouse-ul pe rândurile turneului
    function follow() {
        if (!hover || reduced) { return; }
        document.querySelectorAll('[data-ag-rows]').forEach(function (list) {
            var box = list.parentElement && list.parentElement.parentElement && list.parentElement.parentElement.querySelector('[data-ag-follow]');
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
            list.querySelectorAll('.ag-row').forEach(function (row) {
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

    // Cardurile artiștilor se înclină după mouse
    function tilt() {
        if (!hover || reduced) { return; }
        document.querySelectorAll('[data-ag-tilt]').forEach(function (card) {
            card.addEventListener('mousemove', function (e) {
                var r = card.getBoundingClientRect(), px = (e.clientX - r.left) / r.width - .5, py = (e.clientY - r.top) / r.height - .5;
                card.style.transform = 'perspective(900px) rotateY(' + (px * 8).toFixed(2) + 'deg) rotateX(' + (-py * 8).toFixed(2) + 'deg)';
            });
            card.addEventListener('mouseleave', function () { card.style.transform = ''; });
        });
    }

    // Videoclipul: iframe-ul YouTube (fără cookies) se încarcă doar la click
    function video() {
        document.querySelectorAll('[data-ag-video]').forEach(function (box) {
            var btn = box.querySelector('.ag-video__play');
            if (!btn) { return; }
            btn.addEventListener('click', function () {
                var f = document.createElement('iframe');
                f.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(box.getAttribute('data-ag-video')) + '?autoplay=1&rel=0';
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
        var bar = document.querySelector('[data-ag-mbar]'), tix = document.getElementById('bilete');
        if (!bar || !tix || !window.IntersectionObserver) { return; }
        new IntersectionObserver(function (en) { bar.classList.toggle('is-hidden', en[0].isIntersecting); }, { rootMargin: '0px 0px -30% 0px' }).observe(tix);
    }

    // Afișul: textul apare pe rând, după ce pagina e gata
    function heroIn() {
        var g = window.gsap, ins = document.querySelectorAll('[data-ag-in]');
        if (!g || reduced || !ins.length) { return; }
        g.from(ins, { y: 26, opacity: 0, duration: .9, ease: 'power3.out', stagger: .09, delay: .1 });
    }

    document.addEventListener('DOMContentLoaded', function () { countdowns(); header(); follow(); tilt(); video(); mobileBar(); });
    window.addEventListener('load', heroIn);
})();
