/* Template-ul party: componenta biletelor (→ coșul comun) și efectele, după plans/mds/UI_RESOURCES.md:
   - globul disco din afiș: three.js (oglinzi instanțiate, lumini colorate), încărcat leneș după
     conținut, oprit cât nu e pe ecran sau fila e ascunsă; pe telefoane slabe, fără WebGL sau cu
     animațiile oprite rămâne globul desenat în CSS;
   - petele de lumină pe care globul le aruncă pe afiș (se mișcă odată cu rotația lui);
   - confetti PixiJS când biletele intră în coș (biblioteca se aduce abia la prima adăugare);
   - galeria edițiilor trecute fixată pe ecran și derulată orizontal cu GSAP ScrollTrigger (desktop);
   - lumina foto (lightbox), afișele care se înclină, numărătoarea, antetul, bara de pe mobil.
   Se încarcă înaintea lui Alpine. Versiunile bibliotecilor sunt fixate (niciodată „latest”). */
(function () {
    'use strict';

    // three.js și PixiJS se aduc doar când e nevoie (globul după load, confetti la primul bilet ales)
    var THREE_URL = 'https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.min.js';
    var PIXI_URL = 'https://cdn.jsdelivr.net/npm/pixi.js@8.6.6/dist/pixi.min.js';
    var mq = function (q) { return !!(window.matchMedia && window.matchMedia(q).matches); };
    var reduced = mq('(prefers-reduced-motion: reduce)');
    var hover = mq('(hover: hover) and (pointer: fine)');
    var t = function (s) { return window.TS ? TS.t(s) : s; };

    /* ---------------- Biletele fără loc numerotat → coșul comun (locurile de pe hartă rămân) */
    window.partyTickets = function (cfg) {
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
                var x = types.filter(function (y) { return y.id === id; })[0];
                var cap = window.TS ? TS.MAX_PER_TYPE : 10;
                return x && x.left ? Math.min(cap, x.left) : cap;
            },
            inc: function (id) {
                if (this.qty[id] < this.max(id)) { this.qty[id]++; }
                // Biblioteca de confetti se aduce abia când vizitatorul începe să aleagă bilete
                loadPixi().catch(function () {});
            },
            dec: function (id) { if (this.qty[id] > 0) { this.qty[id]--; } },
            get count() { var q = this.qty; return types.reduce(function (n, x) { return n + q[x.id]; }, 0); },
            get total() { var q = this.qty; return types.reduce(function (n, x) { return n + q[x.id] * x.price; }, 0); },
            save: function (e) {
                if (!this.count) { return; }
                var q = this.qty;
                TS.Cart.merge(cfg.event, {
                    items: types.filter(function (x) { return q[x.id] > 0; }).map(function (x) {
                        return { ticket_type_id: x.id, name: x.name, price: x.price, qty: q[x.id] };
                    })
                });
                confetti(e && e.currentTarget);
                // Sertarul coșului arată timerul de rezervare și drumul spre plată
                if (TS.Drawer) { TS.Drawer.open(); } else { window.location.href = '/cos'; }
            }
        };
    };

    function idle(fn) { (window.requestIdleCallback || function (f) { return setTimeout(f, 600); })(fn, { timeout: 2500 }); }

    function loadScript(src) {
        return new Promise(function (ok, fail) {
            var s = document.createElement('script');
            s.src = src; s.async = true; s.crossOrigin = 'anonymous';
            s.onload = ok; s.onerror = fail;
            document.head.appendChild(s);
        });
    }

    /* ---------------- Confetti (PixiJS) */
    var pixi = null;
    function loadPixi() {
        if (reduced) { return Promise.reject(); }
        if (!pixi) { pixi = window.PIXI ? Promise.resolve(window.PIXI) : loadScript(PIXI_URL).then(function () { return window.PIXI; }); }
        return pixi;
    }

    var stage = null;
    function confettiStage(P) {
        if (stage) { return Promise.resolve(stage); }
        var app = new P.Application();
        return app.init({ backgroundAlpha: 0, resizeTo: window, antialias: false, autoDensity: true, resolution: Math.min(window.devicePixelRatio || 1, 2), autoStart: false }).then(function () {
            var c = app.canvas;
            c.className = 'pt-confetti';
            c.setAttribute('aria-hidden', 'true');
            document.body.appendChild(c);
            var g = new P.Graphics().rect(0, 0, 12, 7).fill(0xffffff);
            var g2 = new P.Graphics().circle(5, 5, 5).fill(0xffffff);
            stage = { app: app, tex: [app.renderer.generateTexture(g), app.renderer.generateTexture(g2)], parts: [] };
            app.ticker.add(function (tk) {
                var dt = Math.min(tk.deltaTime, 3), alive = 0;
                stage.parts.forEach(function (p) {
                    if (p.dead) { return; }
                    p.vy += 0.32 * dt; p.vx *= Math.pow(0.985, dt); p.vy *= Math.pow(0.985, dt);
                    p.s.x += p.vx * dt; p.s.y += p.vy * dt;
                    p.s.rotation += p.vr * dt;
                    p.s.scale.y = Math.cos(p.flip += 0.18 * dt) * p.k; // hârtia se întoarce în cădere
                    p.life -= dt;
                    if (p.life < 30) { p.s.alpha = Math.max(0, p.life / 30); }
                    if (p.life <= 0 || p.s.y > app.screen.height + 40) { p.dead = true; p.s.destroy(); } else { alive++; }
                });
                stage.parts = stage.parts.filter(function (p) { return !p.dead; });
                if (!alive) { app.ticker.stop(); c.style.display = 'none'; }
            });
            return stage;
        });
    }

    function confetti(from) {
        if (reduced) { return; }
        loadPixi().then(function (P) { return confettiStage(P).then(function (st) { burst(P, st, from); }); }).catch(function () {});
    }

    function burst(P, st, from) {
        var r = from && from.getBoundingClientRect ? from.getBoundingClientRect() : null;
        var x = r ? r.left + r.width / 2 : window.innerWidth / 2, y = r ? r.top : window.innerHeight * 0.7;
        var colors = [0xFF2E7E, 0x38E1FF, 0xFFE14D, 0xF4F4F6, 0xB45CFF, 0xC9CED6];
        var n = window.innerWidth < 700 ? 90 : 160;
        for (var i = 0; i < n; i++) {
            var s = new P.Sprite(st.tex[i % 5 === 0 ? 1 : 0]);
            s.anchor.set(0.5);
            s.tint = colors[i % colors.length];
            s.x = x; s.y = y;
            var a = -Math.PI / 2 + (Math.random() - 0.5) * 1.6, v = 9 + Math.random() * 11, k = 0.6 + Math.random() * 0.7;
            s.scale.set(k);
            st.app.stage.addChild(s);
            st.parts.push({ s: s, vx: Math.cos(a) * v, vy: Math.sin(a) * v, vr: (Math.random() - 0.5) * 0.3, flip: Math.random() * 6, k: k, life: 110 + Math.random() * 70 });
        }
        st.app.canvas.style.display = '';
        st.app.ticker.start();
    }

    // Locurile alese pe harta clubului: același confetti la „Continuă” (locurile sunt deja în coș)
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-sm-done]');
        if (b && !b.disabled) { confetti(b); }
    });
    // Pe harta clubului confetti-ul e probabil: biblioteca se pregătește din timp
    document.addEventListener('ts-seatmap', function (e) { if (e.detail && e.detail.ok) { idle(function () { loadPixi().catch(function () {}); }); } });

    /* ---------------- Globul disco (three.js) */
    function canRun3d() {
        if (reduced) { return false; }
        var weak = (navigator.hardwareConcurrency || 4) <= 4 && window.innerWidth < 768;
        if (weak || (navigator.connection && navigator.connection.saveData)) { return false; }
        try { var c = document.createElement('canvas'); return !!(c.getContext('webgl2') || c.getContext('webgl')); } catch (e) { return false; }
    }

    function discoBall() {
        var box = document.querySelector('[data-pt-ball]');
        if (!box || !canRun3d()) { return; }
        import(THREE_URL).then(function (THREE) { buildBall(THREE, box); }).catch(function () {});
    }

    function buildBall(THREE, box) {
        var w = box.clientWidth, h = box.clientHeight;
        if (!w || !h) { return; }
        var renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
        renderer.setSize(w, h);
        renderer.outputColorSpace = THREE.SRGBColorSpace;
        var scene = new THREE.Scene();
        var camera = new THREE.PerspectiveCamera(32, w / h, 0.1, 50);
        camera.position.set(0, 0, 6.2);

        // Oglinzile: plăcuțe pătrate pe inele de latitudine, câte una pe instanță
        var R = 1.5, rows = 26, tile = (Math.PI * R) / rows * 0.86, mats = [];
        var geo = new THREE.BoxGeometry(tile, tile, 0.035);
        var mat = new THREE.MeshPhongMaterial({ color: 0xbfc5ce, specular: 0xffffff, shininess: 140, flatShading: true });
        for (var i = 1; i < rows; i++) {
            var phi = (i / rows) * Math.PI, ring = Math.max(6, Math.round((2 * Math.PI * R * Math.sin(phi)) / (tile / 0.86)));
            for (var j = 0; j < ring; j++) { mats.push([phi, (j / ring) * Math.PI * 2 + (i % 2) * 0.12]); }
        }
        var mesh = new THREE.InstancedMesh(geo, mat, mats.length);
        var m = new THREE.Object3D(), col = new THREE.Color();
        mats.forEach(function (p, k) {
            var s = Math.sin(p[0]);
            m.position.set(R * s * Math.cos(p[1]), R * Math.cos(p[0]), R * s * Math.sin(p[1]));
            m.lookAt(m.position.clone().multiplyScalar(2));
            m.rotateZ((Math.random() - 0.5) * 0.08); // oglinzile nu sunt lipite perfect
            m.updateMatrix();
            mesh.setMatrixAt(k, m.matrix);
            var g = 0.62 + Math.random() * 0.38;
            mesh.setColorAt(k, col.setRGB(g, g, g * 1.04));
        });
        var ball = new THREE.Group();
        ball.add(mesh);
        // Miezul închis dintre oglinzi
        ball.add(new THREE.Mesh(new THREE.SphereGeometry(R * 0.985, 32, 24), new THREE.MeshBasicMaterial({ color: 0x0b0b10 })));
        // Lanțul de care atârnă
        var chain = new THREE.Mesh(new THREE.CylinderGeometry(0.018, 0.018, 4, 6), new THREE.MeshBasicMaterial({ color: 0x55555f }));
        chain.position.y = R + 2;
        scene.add(chain);
        ball.rotation.z = 0.12;
        scene.add(ball);

        // Lumini colorate care se rotesc: fiecare oglindă sclipește când le prinde
        scene.add(new THREE.AmbientLight(0xffffff, 0.35));
        var lights = [[0xFF2E7E, 3.2], [0x38E1FF, 2.6], [0xffffff, 2.2], [0xB45CFF, 2]].map(function (c, k) {
            var l = new THREE.PointLight(c[0], c[1] * 6, 14, 1.4);
            l.userData.a = k * Math.PI / 2;
            scene.add(l);
            return l;
        });

        box.appendChild(renderer.domElement);
        box.classList.add('is-3d');

        var px = 0, py = 0, tx = 0, ty = 0, visible = true, raf = 0, last = performance.now(), angle = 0;
        var spots = lightSpots();
        if (hover) {
            window.addEventListener('pointermove', function (e) { tx = (e.clientX / window.innerWidth - 0.5); ty = (e.clientY / window.innerHeight - 0.5); }, { passive: true });
        }
        function frame(now) {
            var dt = Math.min(0.05, (now - last) / 1000);
            last = now;
            angle += dt * 0.32;
            px += (tx - px) * 0.05; py += (ty - py) * 0.05;
            ball.rotation.y = angle;
            ball.rotation.x = py * 0.25;
            ball.position.x = px * 0.25;
            lights.forEach(function (l, k) {
                var a = l.userData.a + now / 1000 * (0.5 + k * 0.13);
                l.position.set(Math.cos(a) * 4, Math.sin(a * 1.3) * 2.2, Math.sin(a) * 3 + 2.5);
            });
            renderer.render(scene, camera);
            if (spots) { spots(angle); }
            raf = visible && !document.hidden ? requestAnimationFrame(frame) : 0;
        }
        var start = function () { if (!raf && visible && !document.hidden) { last = performance.now(); raf = requestAnimationFrame(frame); } };
        // Nu desenăm nimic cât afișul nu e pe ecran sau fila e în fundal
        new IntersectionObserver(function (en) { visible = en[0].isIntersecting; start(); }).observe(box);
        document.addEventListener('visibilitychange', start);
        window.addEventListener('resize', function () {
            var W = box.clientWidth, H = box.clientHeight;
            if (!W || !H) { return; }
            renderer.setSize(W, H);
            camera.aspect = W / H;
            camera.updateProjectionMatrix();
        });
        if (window.gsap) {
            window.gsap.from(ball.scale, { x: 0.6, y: 0.6, z: 0.6, duration: 1.6, ease: 'elastic.out(1, 0.6)' });
            window.gsap.from(renderer.domElement, { opacity: 0, duration: 1 });
        }
        start();
    }

    // Petele de lumină reflectate de glob pe afiș: pornesc din glob și alunecă odată cu rotația
    function lightSpots() {
        var host = document.querySelector('[data-pt-hero] [data-pt-lights]');
        if (!host) { return null; }
        var n = window.innerWidth < 768 ? 14 : 30, list = [], colors = ['#FF2E7E', '#38E1FF', '#ffffff', '#B45CFF', '#FFE14D'];
        for (var i = 0; i < n; i++) {
            var s = document.createElement('i');
            var size = 6 + Math.random() * 16;
            s.style.width = s.style.height = size + 'px';
            s.style.background = colors[i % colors.length];
            host.appendChild(s);
            list.push({ el: s, phase: Math.random() * Math.PI * 2, y: 8 + Math.random() * 84, speed: 0.8 + Math.random() * 0.6 });
        }
        host.classList.add('is-on');
        return function (angle) {
            list.forEach(function (p) {
                var a = p.phase + angle * p.speed, x = ((a / (Math.PI * 2)) % 1) * 120 - 10;
                p.el.style.transform = 'translate3d(' + x.toFixed(2) + 'vw,' + p.y + 'vh,0) scale(' + (0.6 + Math.abs(Math.sin(a * 3)) * 0.6).toFixed(2) + ')';
                p.el.style.opacity = (0.25 + Math.abs(Math.cos(a * 2)) * 0.6).toFixed(2);
            });
        };
    }

    /* ---------------- Galeria fixată (GSAP ScrollTrigger) */
    function reel() {
        var g = window.gsap, ST = window.ScrollTrigger;
        var sec = document.querySelector('[data-pt-reel]');
        if (!sec || !g || !ST || reduced) { return; }
        g.registerPlugin(ST);
        var track = sec.querySelector('[data-pt-track]');
        var mm = g.matchMedia();
        mm.add('(min-width: 900px)', function () {
            sec.classList.add('is-pinned');
            var dist = function () { return Math.max(0, track.scrollWidth - window.innerWidth + 48); };
            var tw = g.to(track, {
                x: function () { return -dist(); },
                ease: 'none',
                scrollTrigger: { trigger: sec, start: 'top top', end: function () { return '+=' + dist(); }, pin: true, scrub: 0.8, invalidateOnRefresh: true, anticipatePin: 1 }
            });
            // Fotografiile se mișcă puțin pe verticală, în ritmul benzii
            sec.querySelectorAll('.pt-roll__shot').forEach(function (shot, k) {
                g.fromTo(shot, { yPercent: k % 2 ? -8 : 8 }, { yPercent: k % 2 ? 8 : -8, ease: 'none', scrollTrigger: { trigger: shot, containerAnimation: tw, start: 'left right', end: 'right left', scrub: true } });
            });
            return function () { sec.classList.remove('is-pinned'); g.set(track, { clearProps: 'transform' }); };
        });
        window.addEventListener('load', function () { ST.refresh(); });
    }

    /* ---------------- Lumina foto */
    function lightbox() {
        var shots = document.querySelectorAll('[data-pt-lightbox]');
        if (!shots.length) { return; }
        var box = document.createElement('div'), list = [], idx = 0, back = null;
        box.className = 'pt-lb';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.setAttribute('aria-label', t('Fotografie'));
        box.hidden = true;
        box.innerHTML = '<img alt=""><button type="button" class="pt-lb__x" aria-label="' + t('Închide') + '">×</button>'
            + '<button type="button" class="pt-lb__nav pt-lb__prev" aria-label="' + t('Fotografia anterioară') + '">‹</button>'
            + '<button type="button" class="pt-lb__nav pt-lb__next" aria-label="' + t('Fotografia următoare') + '">›</button><span class="pt-lb__n" aria-live="polite"></span>';
        document.body.appendChild(box);
        var img = box.querySelector('img'), n = box.querySelector('.pt-lb__n');
        var show = function (i) {
            idx = (i + list.length) % list.length;
            var b = list[idx];
            img.src = b.getAttribute('data-pt-lightbox');
            img.alt = (b.querySelector('img') && b.querySelector('img').alt) || '';
            n.textContent = (idx + 1) + ' / ' + list.length;
        };
        var close = function () {
            box.hidden = true;
            document.documentElement.classList.remove('pt-lock');
            if (window.tsLenis) { window.tsLenis.start(); }
            if (back) { back.focus(); }
        };
        document.addEventListener('click', function (e) {
            var b = e.target.closest && e.target.closest('[data-pt-lightbox]');
            if (!b) { return; }
            var scope = b.closest('[data-pt-reel], .pt-album') || document;
            list = Array.prototype.slice.call(scope.querySelectorAll('[data-pt-lightbox]'));
            back = b;
            box.hidden = false;
            document.documentElement.classList.add('pt-lock');
            if (window.tsLenis) { window.tsLenis.stop(); }
            show(list.indexOf(b));
            box.querySelector('.pt-lb__x').focus();
        });
        box.addEventListener('click', function (e) {
            if (e.target.closest('.pt-lb__prev')) { show(idx - 1); }
            else if (e.target.closest('.pt-lb__next')) { show(idx + 1); }
            else if (e.target === box || e.target.closest('.pt-lb__x')) { close(); }
        });
        document.addEventListener('keydown', function (e) {
            if (box.hidden) { return; }
            if (e.key === 'Escape') { close(); }
            if (e.key === 'ArrowLeft') { show(idx - 1); }
            if (e.key === 'ArrowRight') { show(idx + 1); }
            if (e.key === 'Tab') { // focusul rămâne în fereastră
                var f = box.querySelectorAll('button'), first = f[0], lastB = f[f.length - 1];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); lastB.focus(); }
                else if (!e.shiftKey && document.activeElement === lastB) { e.preventDefault(); first.focus(); }
            }
        });
    }

    /* ---------------- Mărunțișuri */
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
        var h = document.querySelector('[data-pt-head]');
        if (!h || h.hasAttribute('data-solid')) { return; }
        var on = function () { h.classList.toggle('is-solid', window.scrollY > 60); };
        on();
        window.addEventListener('scroll', on, { passive: true });
    }

    // Afișele se înclină după mouse, cu o lumină care le urmărește
    function tilt() {
        if (!hover || reduced) { return; }
        document.querySelectorAll('[data-pt-tilt]').forEach(function (card) {
            card.addEventListener('mousemove', function (e) {
                var r = card.getBoundingClientRect(), px = (e.clientX - r.left) / r.width - .5, py = (e.clientY - r.top) / r.height - .5;
                card.style.transform = 'perspective(900px) rotateY(' + (px * 9).toFixed(2) + 'deg) rotateX(' + (-py * 9).toFixed(2) + 'deg)';
                card.style.setProperty('--mx', ((px + .5) * 100).toFixed(1) + '%');
                card.style.setProperty('--my', ((py + .5) * 100).toFixed(1) + '%');
            });
            card.addEventListener('mouseleave', function () { card.style.transform = ''; });
        });
    }

    // Bara „Bilete de la” de pe mobil dispare cât biletele sunt pe ecran
    function mobileBar() {
        var bar = document.querySelector('[data-pt-mbar]'), tix = document.getElementById('bilete');
        if (!bar || !tix || !window.IntersectionObserver) { return; }
        new IntersectionObserver(function (en) { bar.classList.toggle('is-hidden', en[0].isIntersecting); }, { rootMargin: '0px 0px -30% 0px' }).observe(tix);
    }

    // Afișul: textul apare pe rând, titlul „se derulează” ca o casetă
    function heroIn() {
        var g = window.gsap, ins = document.querySelectorAll('[data-pt-in]');
        if (!g || reduced || !ins.length) { return; }
        g.timeline()
            .from(ins, { y: 30, opacity: 0, duration: .9, ease: 'power3.out', stagger: .09 })
            .from('.pt-hero__title', { skewX: -12, filter: 'blur(6px)', duration: .7, ease: 'power2.out' }, 0);
    }

    document.addEventListener('DOMContentLoaded', function () {
        countdowns(); header(); tilt(); mobileBar(); lightbox(); reel(); heroIn();
    });
    // Globul vine după conținut și după ce browserul e liber
    window.addEventListener('load', function () { idle(discoBall); });
})();
