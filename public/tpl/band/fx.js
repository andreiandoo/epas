/* Template-ul band: componenta biletelor (cantități → coșul comun) și efectele: globul cu
   orașele turneului (three.js, încărcat după conținut, oprit când nu se vede), antetul,
   numărătoarea, fotografia care urmărește mouse-ul pe rândurile turneului, ecusoanele
   echipajului care se înclină, videoclipul încărcat la click, bara de pe mobil.
   Se încarcă înaintea lui Alpine. La prefers-reduced-motion: globul static, fără animații. */
(function () {
    'use strict';

    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var hover = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    var THREE_URL = 'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js';

    /* ---------------- Biletele fără loc numerotat → coșul comun (locurile de pe hartă rămân) */
    window.bandTickets = function (cfg) {
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

    /* ---------------- Globul turneului */
    function globe() {
        var box = document.querySelector('[data-bd-globe]');
        if (!box) { return; }
        var points = [];
        try { points = JSON.parse(box.getAttribute('data-bd-globe') || '[]'); } catch (e) {}
        var small = window.innerWidth < 768 && !(navigator.deviceMemory >= 6);
        var gl = (function () { try { var c = document.createElement('canvas'); return !!(c.getContext('webgl2') || c.getContext('webgl')); } catch (e) { return false; } })();
        if (!gl || (small && navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 4)) { return; }

        import(THREE_URL).then(function (THREE) {
            var css = getComputedStyle(document.documentElement);
            var rgb = function (name, fb) { var v = css.getPropertyValue(name).trim().split(/\s+/).map(Number); return v.length === 3 ? new THREE.Color(v[0] / 255, v[1] / 255, v[2] / 255) : new THREE.Color(fb); };
            var cFg = rgb('--c-fg', '#e8ecf4'), cPrimary = rgb('--c-primary', '#ff6b2c'), cAccent = rgb('--c-accent', '#3de0c8');

            var renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
            renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.6));
            box.insertBefore(renderer.domElement, box.firstChild);
            var scene = new THREE.Scene();
            var camera = new THREE.PerspectiveCamera(38, 1, 0.1, 100);
            camera.position.set(0, 0, small ? 2.6 : 1.85); // aproape, ca un orizont: turneul e mai ales european

            var R = 1;
            var tilt = new THREE.Group(), spin = new THREE.Group();
            tilt.add(spin); scene.add(tilt);
            var toVec = function (lat, lng, r) {
                var phi = (90 - lat) * Math.PI / 180, th = (lng + 180) * Math.PI / 180;
                return new THREE.Vector3(-r * Math.sin(phi) * Math.cos(th), r * Math.cos(phi), r * Math.sin(phi) * Math.sin(th));
            };

            // Sfera de puncte (distribuție Fibonacci), ca o planetă desenată din semnale
            var N = small ? 2600 : 5200, pos = new Float32Array(N * 3);
            for (var i = 0; i < N; i++) {
                var y = 1 - (i / (N - 1)) * 2, rr = Math.sqrt(1 - y * y), a = i * 2.399963229728653;
                pos[i * 3] = Math.cos(a) * rr * R; pos[i * 3 + 1] = y * R; pos[i * 3 + 2] = Math.sin(a) * rr * R;
            }
            var dotsGeo = new THREE.BufferGeometry(); dotsGeo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
            spin.add(new THREE.Points(dotsGeo, new THREE.PointsMaterial({ color: cFg, size: 0.012, transparent: true, opacity: 0.38, depthWrite: false })));
            // Interiorul întunecat ascunde punctele din spate
            spin.add(new THREE.Mesh(new THREE.SphereGeometry(R * 0.985, 48, 48), new THREE.MeshBasicMaterial({ color: 0x05070d })));
            // Inelul orbitei
            var ring = new THREE.Mesh(new THREE.TorusGeometry(R * 1.32, 0.0025, 8, 160), new THREE.MeshBasicMaterial({ color: cAccent, transparent: true, opacity: 0.45 }));
            ring.rotation.x = Math.PI / 2.25; ring.rotation.y = 0.35; scene.add(ring);
            var sat = new THREE.Mesh(new THREE.SphereGeometry(0.022, 12, 12), new THREE.MeshBasicMaterial({ color: cAccent }));
            scene.add(sat);

            // Orașele: punct + puls, arce între datele consecutive
            var markers = [], labelsBox = box.querySelector('.bd-globe__labels');
            points.forEach(function (p, k) {
                var v = toVec(p.lat, p.lng, R * 1.004);
                var col = p.status === 'sold_out' ? cFg : cPrimary;
                var dot = new THREE.Mesh(new THREE.SphereGeometry(0.016, 12, 12), new THREE.MeshBasicMaterial({ color: col }));
                dot.position.copy(v); spin.add(dot);
                var pulse = new THREE.Mesh(new THREE.RingGeometry(0.02, 0.026, 32), new THREE.MeshBasicMaterial({ color: col, transparent: true, opacity: 0.8, side: THREE.DoubleSide }));
                pulse.position.copy(v); pulse.lookAt(v.clone().multiplyScalar(2)); spin.add(pulse);
                var el = document.createElement('a');
                el.className = 'bd-globe__label' + (p.status === 'sold_out' ? ' is-out' : '');
                el.href = p.url; el.tabIndex = -1;
                el.innerHTML = '<b></b><span></span>';
                el.querySelector('b').textContent = p.city; el.querySelector('span').textContent = p.date;
                labelsBox.appendChild(el);
                markers.push({ v: v, pulse: pulse, el: el, phase: k * 0.7 });
            });
            for (var j = 1; j < points.length; j++) {
                var a1 = toVec(points[j - 1].lat, points[j - 1].lng, R), b1 = toVec(points[j].lat, points[j].lng, R);
                var mid = a1.clone().add(b1).multiplyScalar(0.5); var lift = 1 + a1.distanceTo(b1) * 0.55;
                mid.normalize().multiplyScalar(R * lift);
                var curve = new THREE.QuadraticBezierCurve3(a1, mid, b1);
                var lg = new THREE.BufferGeometry().setFromPoints(curve.getPoints(48));
                spin.add(new THREE.Line(lg, new THREE.LineBasicMaterial({ color: cPrimary, transparent: true, opacity: 0.55 })));
            }

            // Europa (centrul turneului) în față
            var cLat = 47, cLng = 21;
            if (points.length) {
                cLat = points.reduce(function (n, p) { return n + p.lat; }, 0) / points.length;
                cLng = points.reduce(function (n, p) { return n + p.lng; }, 0) / points.length;
            }
            var baseY = -(cLng + 90) * Math.PI / 180, baseX = cLat * Math.PI / 180 * 0.85;
            spin.rotation.y = baseY; tilt.rotation.x = baseX;

            // Tragere cu mouse-ul / degetul
            var drag = null, offY = 0, offX = 0, velY = 0;
            renderer.domElement.addEventListener('pointerdown', function (e) { drag = { x: e.clientX, y: e.clientY, oy: offY, ox: offX }; renderer.domElement.setPointerCapture(e.pointerId); });
            renderer.domElement.addEventListener('pointermove', function (e) {
                if (!drag) { return; }
                var ny = drag.oy + (e.clientX - drag.x) * 0.005; velY = ny - offY; offY = ny;
                offX = Math.max(-0.6, Math.min(0.6, drag.ox + (e.clientY - drag.y) * 0.004));
            });
            var up = function () { drag = null; };
            renderer.domElement.addEventListener('pointerup', up);
            renderer.domElement.addEventListener('pointercancel', up);

            var w = 0, h = 0;
            function size() {
                w = box.clientWidth; h = box.clientHeight;
                renderer.setSize(w, h, false); camera.aspect = w / h;
                // Globul stă în dreapta pe ecran lat, în centru-sus pe telefon
                camera.setViewOffset(w, h, w > 900 ? -w * 0.24 : 0, w > 900 ? -h * 0.04 : h * 0.12, w, h);
                camera.updateProjectionMatrix();
            }
            size();
            window.addEventListener('resize', size, { passive: true });

            var visible = true, t0 = performance.now(), tmp = new THREE.Vector3(), camDir = new THREE.Vector3();
            if (window.IntersectionObserver) {
                new IntersectionObserver(function (en) { visible = en[0].isIntersecting; if (visible) { loop(); } }).observe(box);
            }
            var shown = [];
            function frame(t) {
                var s = (t - t0) / 1000;
                if (!drag && !reduced) { offY += velY; velY *= 0.94; offY += (Math.sin(s * 0.12) * 0.35 - offY) * 0.004; }
                spin.rotation.y = baseY + offY; tilt.rotation.x = baseX + offX;
                var sa = s * 0.35; sat.position.set(Math.cos(sa) * R * 1.32, 0, Math.sin(sa) * R * 1.32).applyEuler(ring.rotation);
                renderer.render(scene, camera);
                camera.getWorldDirection(camDir);
                shown.length = 0;
                markers.forEach(function (m) {
                    var k = ((s * 0.8 + m.phase) % 1);
                    m.pulse.scale.setScalar(reduced ? 1.4 : 1 + k * 2.6); m.pulse.material.opacity = reduced ? 0.6 : (1 - k) * 0.8;
                    m.pulse.updateMatrixWorld();
                    tmp.copy(m.v); spin.localToWorld(tmp);
                    var front = tmp.clone().normalize().dot(camDir) < -0.15;
                    tmp.project(camera);
                    var sx = (tmp.x * 0.5 + 0.5) * w, sy = (-tmp.y * 0.5 + 0.5) * h;
                    m.el.style.transform = 'translate(' + sx.toFixed(1) + 'px,' + sy.toFixed(1) + 'px)';
                    // O etichetă se vede doar dacă nu acoperă alta (orașele apropiate rămân doar ca puncte)
                    var free = front && shown.every(function (q) { return Math.abs(q[0] - sx) > 120 || Math.abs(q[1] - sy) > 44; });
                    if (free) { shown.push([sx, sy]); }
                    m.el.classList.toggle('is-on', free);
                });
            }
            var raf = 0;
            function loop() {
                if (raf) { return; }
                var step = function (t) { raf = 0; frame(t); if (visible && !reduced) { raf = requestAnimationFrame(step); } };
                raf = requestAnimationFrame(step);
            }
            loop();
            box.classList.add('is-ready');
        }).catch(function (e) { if (window.console) { console.warn('Globul turneului nu a pornit; rămâne imaginea de rezervă.', e); } });
    }

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
        var h = document.querySelector('[data-bd-head]');
        if (!h || h.hasAttribute('data-solid')) { return; }
        var on = function () { h.classList.toggle('is-solid', window.scrollY > 60); };
        on();
        window.addEventListener('scroll', on, { passive: true });
    }

    // Fotografia concertului urmărește mouse-ul pe rândurile turneului
    function follow() {
        if (!hover || reduced) { return; }
        document.querySelectorAll('[data-bd-rows]').forEach(function (list) {
            var box = list.parentElement && list.parentElement.parentElement && list.parentElement.parentElement.querySelector('[data-bd-follow]');
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
            list.querySelectorAll('.bd-row').forEach(function (row) {
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

    // Ecusoanele echipajului se înclină după mouse
    function tilt() {
        if (!hover || reduced) { return; }
        document.querySelectorAll('[data-bd-tilt]').forEach(function (card) {
            card.addEventListener('mousemove', function (e) {
                var r = card.getBoundingClientRect(), px = (e.clientX - r.left) / r.width - .5, py = (e.clientY - r.top) / r.height - .5;
                card.style.transform = 'perspective(900px) rotateY(' + (px * 8).toFixed(2) + 'deg) rotateX(' + (-py * 8).toFixed(2) + 'deg)';
            });
            card.addEventListener('mouseleave', function () { card.style.transform = ''; });
        });
    }

    // Videoclipul: iframe-ul YouTube (fără cookies) se încarcă doar la click
    function video() {
        document.querySelectorAll('[data-bd-video]').forEach(function (box) {
            var btn = box.querySelector('.bd-video__play');
            if (!btn) { return; }
            btn.addEventListener('click', function () {
                var f = document.createElement('iframe');
                f.src = 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(box.getAttribute('data-bd-video')) + '?autoplay=1&rel=0';
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
        var bar = document.querySelector('[data-bd-mbar]'), tix = document.getElementById('bilete');
        if (!bar || !tix || !window.IntersectionObserver) { return; }
        new IntersectionObserver(function (en) { bar.classList.toggle('is-hidden', en[0].isIntersecting); }, { rootMargin: '0px 0px -30% 0px' }).observe(tix);
    }

    // Afișul: textul apare pe rând, după ce pagina e gata
    function heroIn() {
        var g = window.gsap, ins = document.querySelectorAll('[data-bd-in]');
        if (!g || reduced || !ins.length) { return; }
        g.from(ins, { y: 26, opacity: 0, duration: .9, ease: 'power3.out', stagger: .09, delay: .1 });
    }

    document.addEventListener('DOMContentLoaded', function () { countdowns(); header(); follow(); tilt(); video(); mobileBar(); });
    window.addEventListener('load', function () { heroIn(); setTimeout(globe, 120); });
})();
